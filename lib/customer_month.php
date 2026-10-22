<?php
/**
 * แถวรายงานลูกค้าตามเดือน ใช้ร่วมกับหน้ารายงานลูกค้าและหน้าสรุปบริการ
 */

function customer_month_items($pdo, $year, $month)
{
    $stmt = $pdo->prepare(
        'SELECT id, customer_name, company_target
         FROM kraft_monthly_targets
         WHERE year = ? AND month = ?
         ORDER BY customer_name ASC, id ASC'
    );
    $stmt->execute(array($year, $month));
    $targets = $stmt->fetchAll();

    $salesStmt = $pdo->prepare(
        'SELECT customer_code, customer_name, sales_amount, weight_kg, gap, sort_order
         FROM kraft_monthly_sales
         WHERE year = ? AND month = ?
         ORDER BY sort_order ASC, customer_code ASC'
    );
    $salesStmt->execute(array($year, $month));
    $salesRows = $salesStmt->fetchAll();

    $optionRows = $pdo->query(
        'SELECT customer_code, customer_name, use_pallet, pallet_paper, rope, film, sale_name, cs_name
         FROM kraft_customer_options'
    )->fetchAll();

    $optByCode = array();
    $optByName = array();
    foreach ($optionRows as $opt) {
        if ($opt['customer_code'] !== null && $opt['customer_code'] !== '') {
            $optByCode[$opt['customer_code']] = $opt;
        }
        $optKey = customer_month_norm_name($opt['customer_name']);
        if ($optKey !== '' && !isset($optByName[$optKey])) {
            $optByName[$optKey] = $opt;
        }
    }

    $salesByName = array();
    foreach ($salesRows as $sale) {
        $key = customer_month_norm_name($sale['customer_name']);
        if ($key !== '' && !isset($salesByName[$key])) {
            $salesByName[$key] = $sale;
        }
    }

    $items = array();
    $usedSales = array();
    foreach ($targets as $row) {
        $key = customer_month_norm_name($row['customer_name']);
        $sale = ($key !== '' && isset($salesByName[$key])) ? $salesByName[$key] : null;
        if ($sale) {
            $usedSales[$sale['customer_code']] = true;
        }
        $items[] = array(
            'id' => (int) $row['id'],
            'customer_code' => $sale ? $sale['customer_code'] : null,
            'customer_name' => $row['customer_name'],
            'sale' => null,
            'cs' => null,
            'target' => (float) $row['company_target'],
            'sales_amount' => $sale ? (float) $sale['sales_amount'] : null,
            'weight_kg' => ($sale && $sale['weight_kg'] !== null) ? (float) $sale['weight_kg'] : null,
            'gap' => ($sale && $sale['gap'] !== null) ? (float) $sale['gap'] : null,
        );
    }

    foreach ($salesRows as $sale) {
        if (isset($usedSales[$sale['customer_code']])) {
            continue;
        }
        $items[] = array(
            'id' => 's-' . $sale['customer_code'],
            'customer_code' => $sale['customer_code'],
            'customer_name' => $sale['customer_name'],
            'sale' => null,
            'cs' => null,
            'target' => null,
            'sales_amount' => (float) $sale['sales_amount'],
            'weight_kg' => $sale['weight_kg'] !== null ? (float) $sale['weight_kg'] : null,
            'gap' => $sale['gap'] !== null ? (float) $sale['gap'] : null,
            'sort_order' => isset($sale['sort_order']) ? (int) $sale['sort_order'] : 999999,
        );
    }

    $sortMap = array();
    foreach ($salesRows as $sale) {
        $sortMap[$sale['customer_code']] = isset($sale['sort_order']) ? (int) $sale['sort_order'] : 999999;
    }
    foreach ($items as $index => $item) {
        $code = isset($item['customer_code']) ? $item['customer_code'] : '';
        $items[$index]['sort_order'] = ($code !== '' && isset($sortMap[$code])) ? $sortMap[$code] : 1000000 + $index;
    }
    usort($items, function ($a, $b) {
        $aMissing = !isset($a['sales_amount']) || $a['sales_amount'] === null;
        $bMissing = !isset($b['sales_amount']) || $b['sales_amount'] === null;
        if ($aMissing !== $bMissing) {
            return $aMissing ? 1 : -1;
        }
        $sa = $aMissing ? 0.0 : (float) $a['sales_amount'];
        $sb = $bMissing ? 0.0 : (float) $b['sales_amount'];
        if ($sa === $sb) {
            return strcmp((string) $a['customer_name'], (string) $b['customer_name']);
        }
        return ($sa > $sb) ? -1 : 1;
    });

    foreach ($items as $index => $item) {
        unset($items[$index]['sort_order']);
        $code = isset($item['customer_code']) ? $item['customer_code'] : '';
        $nameKey = customer_month_norm_name($item['customer_name']);
        $opt = null;
        if ($nameKey !== '' && isset($optByName[$nameKey])) {
            $opt = $optByName[$nameKey];
        } elseif ($code !== '' && isset($optByCode[$code])) {
            $opt = $optByCode[$code];
        }
        $items[$index]['use_pallet'] = $opt ? customer_month_flag($opt['use_pallet']) : null;
        $items[$index]['pallet_paper'] = $opt ? customer_month_flag($opt['pallet_paper']) : null;
        $items[$index]['rope'] = $opt ? customer_month_flag($opt['rope']) : null;
        $items[$index]['film'] = $opt ? customer_month_flag($opt['film']) : null;
        $items[$index]['sale'] = $opt ? customer_month_person($opt['sale_name']) : null;
        $items[$index]['cs'] = $opt ? customer_month_person($opt['cs_name']) : null;
    }

    return $items;
}

function service_summary_from_customers($items)
{
    $defs = array(
        array('id' => 'pallet_paper', 'service_name' => 'กระดาษรองเลท', 'key' => 'pallet_paper'),
        array('id' => 'rope', 'service_name' => 'รัดเชือก', 'key' => 'rope'),
        array('id' => 'film', 'service_name' => 'พันฟิล์ม', 'key' => 'film'),
    );
    $rows = array();
    foreach ($defs as $def) {
        $count = 0;
        $sales = 0.0;
        $weight = 0.0;
        $gapWeighted = 0.0;
        $gapWeight = 0.0;
        foreach ($items as $item) {
            if (!isset($item[$def['key']]) || (int) $item[$def['key']] !== 1) {
                continue;
            }
            $count++;
            if (isset($item['sales_amount']) && $item['sales_amount'] !== null) {
                $sales += (float) $item['sales_amount'];
            }
            $rowWeight = (isset($item['weight_kg']) && $item['weight_kg'] !== null) ? (float) $item['weight_kg'] : 0.0;
            $weight += $rowWeight;
            if (isset($item['gap']) && $item['gap'] !== null && $rowWeight > 0) {
                $gapWeighted += (float) $item['gap'] * $rowWeight;
                $gapWeight += $rowWeight;
            }
        }
        $rows[] = array(
            'id' => $def['id'],
            'service_name' => $def['service_name'],
            'customer_count' => $count,
            'sales_amount' => round($sales, 2),
            'gap' => $gapWeight > 0 ? round($gapWeighted / $gapWeight, 2) : null,
            'sales_weight' => round($weight, 2),
        );
    }
    return $rows;
}

function customer_month_flag($value)
{
    if ($value === null || $value === '') {
        return null;
    }
    return (int) $value;
}

function customer_month_person($value)
{
    $value = trim((string) $value);
    return $value === '' ? null : $value;
}

function customer_month_norm_name($name)
{
    $name = preg_replace('/\s+/u', '', (string) $name);
    return $name === null ? '' : $name;
}
