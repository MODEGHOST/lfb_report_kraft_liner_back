<?php
/**
 * ตัวเลข Dashboard จากชุดเดียวกับรายงานลูกค้าตามเดือน และสรุปบริการ
 */

function dashboard_periods($pdo)
{
    $rows = $pdo->query(
        'SELECT year, month FROM kraft_monthly_sales
         UNION
         SELECT year, month FROM kraft_monthly_targets
         ORDER BY year ASC, month ASC'
    )->fetchAll();
    $periods = array();
    foreach ($rows as $row) {
        $year = (int) $row['year'];
        $month = (int) $row['month'];
        $periods[] = array(
            'year' => $year,
            'month' => $month,
            'key' => sprintf('%04d-%02d', $year, $month),
        );
    }
    return $periods;
}

function dashboard_month_view($pdo, $year, $month)
{
    $items = customer_month_items($pdo, $year, $month);
    $services = service_summary_from_customers($items);
    $services = merge_summary_actuals(sprintf('%04d-%02d', $year, $month), $services);
    return array(
        'items' => $items,
        'services' => $services,
    );
}

function dashboard_customer_totals($items)
{
    $sales = 0.0;
    $weight = 0.0;
    $target = 0.0;
    $buyers = 0;
    $gapWeighted = 0.0;
    $gapWeight = 0.0;
    $flags = array(
        'pallet_paper' => 0,
        'rope' => 0,
        'film' => 0,
    );

    foreach ($items as $item) {
        if (isset($item['sales_amount']) && $item['sales_amount'] !== null) {
            $sales += (float) $item['sales_amount'];
            $buyers++;
        }
        $rowWeight = (isset($item['weight_kg']) && $item['weight_kg'] !== null)
            ? (float) $item['weight_kg']
            : 0.0;
        $weight += $rowWeight;
        if (isset($item['target']) && $item['target'] !== null) {
            $target += (float) $item['target'];
        }
        if (isset($item['gap']) && $item['gap'] !== null && $rowWeight > 0) {
            $gapWeighted += (float) $item['gap'] * $rowWeight;
            $gapWeight += $rowWeight;
        }
        foreach ($flags as $key => $count) {
            if (isset($item[$key]) && (int) $item[$key] === 1) {
                $flags[$key]++;
            }
        }
    }

    return array(
        'sales' => round($sales, 2),
        'weight' => round($weight, 2),
        'target' => round($target, 2),
        'buyers' => $buyers,
        'rows' => count($items),
        'gap' => $gapWeight > 0 ? round($gapWeighted / $gapWeight, 2) : null,
        'flags' => $flags,
    );
}

function dashboard_top_customers($items, $limit)
{
    $ranked = array();
    foreach ($items as $item) {
        if (!isset($item['sales_amount']) || $item['sales_amount'] === null) {
            continue;
        }
        $ranked[] = $item;
    }
    usort($ranked, function ($a, $b) {
        $sa = (float) $a['sales_amount'];
        $sb = (float) $b['sales_amount'];
        if ($sa === $sb) {
            return strcmp((string) $a['customer_name'], (string) $b['customer_name']);
        }
        return ($sa < $sb) ? 1 : -1;
    });
    $ranked = array_slice($ranked, 0, $limit);

    $out = array();
    foreach ($ranked as $row) {
        $out[] = array(
            'code' => $row['customer_code'],
            'name' => $row['customer_name'],
            'sale' => $row['sale'] ? $row['sale'] : '',
            'sales' => round((float) $row['sales_amount'], 2),
            'weight' => isset($row['weight_kg']) && $row['weight_kg'] !== null ? round((float) $row['weight_kg'], 2) : 0,
            'target' => isset($row['target']) && $row['target'] !== null ? round((float) $row['target'], 2) : 0,
            'gap' => isset($row['gap']) && $row['gap'] !== null ? round((float) $row['gap'], 2) : null,
            'pallet_paper' => !empty($row['pallet_paper']) ? 1 : 0,
            'rope' => !empty($row['rope']) ? 1 : 0,
            'film' => !empty($row['film']) ? 1 : 0,
        );
    }
    return $out;
}

function dashboard_by_sale($items)
{
    $map = array();
    foreach ($items as $item) {
        if (!isset($item['sales_amount']) || $item['sales_amount'] === null) {
            continue;
        }
        $name = (isset($item['sale']) && $item['sale'] !== null && $item['sale'] !== '')
            ? (string) $item['sale']
            : 'ไม่ระบุ';
        if (!isset($map[$name])) {
            $map[$name] = array(
                'name' => $name,
                'sales' => 0.0,
                'customers' => 0,
                'weight' => 0.0,
            );
        }
        $map[$name]['sales'] += (float) $item['sales_amount'];
        $map[$name]['customers'] += 1;
        if (isset($item['weight_kg']) && $item['weight_kg'] !== null) {
            $map[$name]['weight'] += (float) $item['weight_kg'];
        }
    }
    $rows = array_values($map);
    foreach ($rows as $index => $row) {
        $rows[$index]['sales'] = round($row['sales'], 2);
        $rows[$index]['weight'] = round($row['weight'], 2);
    }
    usort($rows, function ($a, $b) {
        if ($a['sales'] === $b['sales']) {
            return strcmp($a['name'], $b['name']);
        }
        return ($a['sales'] < $b['sales']) ? 1 : -1;
    });
    return $rows;
}

function dashboard_sum_services($bundles)
{
    $defs = array(
        'pallet_paper' => 'กระดาษรองเลท',
        'rope' => 'รัดเชือก',
        'film' => 'พันฟิล์ม',
    );
    $acc = array();
    foreach ($defs as $id => $name) {
        $acc[$id] = array(
            'id' => $id,
            'name' => $name,
            'customer_count' => 0,
            'sales_amount' => 0.0,
            'sales_weight' => 0.0,
            'actual_weight' => null,
            '_gap_weighted' => 0.0,
            '_gap_weight' => 0.0,
        );
    }
    foreach ($bundles as $bundle) {
        foreach ($bundle['services'] as $row) {
            $id = isset($row['id']) ? (string) $row['id'] : '';
            if (!isset($acc[$id])) {
                continue;
            }
            $weight = isset($row['sales_weight']) ? (float) $row['sales_weight'] : 0.0;
            $acc[$id]['customer_count'] += isset($row['customer_count']) ? (int) $row['customer_count'] : 0;
            $acc[$id]['sales_amount'] += isset($row['sales_amount']) ? (float) $row['sales_amount'] : 0.0;
            $acc[$id]['sales_weight'] += $weight;
            if (isset($row['actual_weight']) && $row['actual_weight'] !== null && $row['actual_weight'] !== '') {
                if ($acc[$id]['actual_weight'] === null) {
                    $acc[$id]['actual_weight'] = 0.0;
                }
                $acc[$id]['actual_weight'] += (float) $row['actual_weight'];
            }
            if (isset($row['gap']) && $row['gap'] !== null && $weight > 0) {
                $acc[$id]['_gap_weighted'] += (float) $row['gap'] * $weight;
                $acc[$id]['_gap_weight'] += $weight;
            }
        }
    }

    $out = array();
    foreach ($acc as $row) {
        $weight = $row['sales_weight'];
        $actual = $row['actual_weight'];
        $out[] = array(
            'id' => $row['id'],
            'name' => $row['name'],
            'customer_count' => $row['customer_count'],
            'sales_amount' => round($row['sales_amount'], 2),
            'sales_weight' => round($weight, 2),
            'actual_weight' => $actual === null ? null : round($actual, 2),
            'pct' => ($actual !== null && $weight > 0) ? round(($actual / $weight) * 100, 2) : null,
            'gap' => $row['_gap_weight'] > 0 ? round($row['_gap_weighted'] / $row['_gap_weight'], 2) : null,
        );
    }
    return $out;
}

function dashboard_payload($pdo, $year, $month, $scope)
{
    $periods = dashboard_periods($pdo);
    $byKey = array();
    foreach ($periods as $period) {
        $byKey[$period['key']] = $period;
    }

    $selected = array();
    if ($scope === 'year') {
        foreach ($periods as $period) {
            if ($period['year'] === $year) {
                $selected[] = $period;
            }
        }
    } else {
        $key = sprintf('%04d-%02d', $year, $month);
        if (isset($byKey[$key])) {
            $selected[] = $byKey[$key];
        }
    }

    $bundles = array();
    foreach ($selected as $period) {
        $bundles[] = dashboard_month_view($pdo, $period['year'], $period['month']);
    }

    $items = array();
    foreach ($bundles as $bundle) {
        foreach ($bundle['items'] as $item) {
            $items[] = $item;
        }
    }
    $totals = dashboard_customer_totals($items);
    $byService = dashboard_sum_services($bundles);

    $totalActual = 0.0;
    $hasActual = false;
    $serviceCustomerSum = 0;
    foreach ($byService as $row) {
        $serviceCustomerSum += (int) $row['customer_count'];
        if ($row['actual_weight'] !== null) {
            $totalActual += (float) $row['actual_weight'];
            $hasActual = true;
        }
    }

    $flagLabels = array(
        'pallet_paper' => 'กระดาษรองเลท',
        'rope' => 'รัดเชือก',
        'film' => 'พันฟิล์ม',
    );
    $serviceFlags = array();
    foreach ($flagLabels as $key => $label) {
        $count = $totals['flags'][$key];
        $serviceFlags[] = array(
            'key' => $key,
            'name' => $label,
            'count' => $count,
            'pct' => $totals['rows'] > 0 ? round(($count / $totals['rows']) * 100, 1) : 0,
        );
    }

    $trend = array();
    foreach ($periods as $period) {
        if ($scope === 'year' && $period['year'] !== $year) {
            continue;
        }
        $view = dashboard_month_view($pdo, $period['year'], $period['month']);
        $monthTotals = dashboard_customer_totals($view['items']);
        $trend[] = array(
            'key' => $period['key'],
            'year' => $period['year'],
            'month' => $period['month'],
            'label' => $period['month'] . '/' . $period['year'],
            'sales' => $monthTotals['sales'],
            'weight' => $monthTotals['weight'],
            'customers' => $monthTotals['buyers'],
        );
    }

    $prevSales = null;
    $prevPeriodYear = null;
    $prevPeriodMonth = null;
    if ($scope === 'year') {
        $prevYear = $year - 1;
        $prevSalesSum = 0.0;
        $foundPrev = false;
        foreach ($periods as $period) {
            if ($period['year'] !== $prevYear) {
                continue;
            }
            $foundPrev = true;
            $view = dashboard_month_view($pdo, $period['year'], $period['month']);
            $prevSalesSum += dashboard_customer_totals($view['items'])['sales'];
        }
        if ($foundPrev) {
            $prevSales = round($prevSalesSum, 2);
        }
        $prevPeriodYear = $prevYear;
    } else {
        $prevYear = $year;
        $prevMonth = $month - 1;
        if ($prevMonth < 1) {
            $prevMonth = 12;
            $prevYear -= 1;
        }
        $prevKey = sprintf('%04d-%02d', $prevYear, $prevMonth);
        if (isset($byKey[$prevKey])) {
            $view = dashboard_month_view($pdo, $prevYear, $prevMonth);
            $prevSales = dashboard_customer_totals($view['items'])['sales'];
        }
        $prevPeriodYear = $prevYear;
        $prevPeriodMonth = $prevMonth;
    }

    $momPct = null;
    if ($prevSales !== null && $prevSales > 0) {
        $momPct = round((($totals['sales'] - $prevSales) / $prevSales) * 100, 1);
    }

    return array(
        'year' => $year,
        'month' => $scope === 'year' ? null : $month,
        'scope' => $scope,
        'period_keys' => array_map(function ($period) {
            return $period['key'];
        }, $selected),
        'kpi' => array(
            'total_sales' => $totals['sales'],
            'total_weight' => $totals['weight'],
            'total_actual_weight' => $hasActual ? round($totalActual, 2) : null,
            'actual_pct' => ($hasActual && $totals['weight'] > 0)
                ? round(($totalActual / $totals['weight']) * 100, 2)
                : null,
            'customer_count' => $totals['buyers'],
            'report_customer_count' => $totals['rows'],
            'service_count' => count($byService),
            'service_customer_sum' => $serviceCustomerSum,
            'total_target' => $totals['target'],
            'target_pct' => $totals['target'] > 0 ? round(($totals['sales'] / $totals['target']) * 100, 1) : null,
            'avg_gap' => $totals['gap'],
            'mom_pct' => $momPct,
            'prev_year' => $prevPeriodYear,
            'prev_month' => $prevPeriodMonth,
        ),
        'by_service' => $byService,
        'trend' => $trend,
        'top_customers' => dashboard_top_customers($items, 8),
        'by_sale' => dashboard_by_sale($items),
        'service_flags' => $serviceFlags,
    );
}
