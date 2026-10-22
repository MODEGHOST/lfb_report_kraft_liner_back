<?php
/**
 * อ่านยอดขายรายเดือนจาก ERP (monthly_sales_combination_cache) แบบอ่านอย่างเดียว
 * แล้วเขียนทับเฉพาะตารางรายงาน kraft_monthly_sales
 *
 * แถวที่ customer_no เป็น '-' เป็นยอดขายของชื่อเดียวกันอีกก้อน ไม่ใช่ลูกค้าคนละราย
 * จึงบวกเข้าแถวที่มีรหัส ถ้าชื่อนั้นไม่มีรหัสเลย ให้ใช้รหัสที่เคยพบของชื่อนั้น
 *
 * GAP = ราคาขายต่อกก. − ราคาซื้อกระดาษต่อกก.
 *     = SUM(sales_amount)/SUM(sales_weight) − SUM(purchase_amount)/SUM(purchase_weight)
 *
 * อ่านต้นทางทีละวัน แล้วเว้น 0.5 วินาทีก่อนวันถัดไป
 * ยอดของทั้งเดือนค่อยรวมในสคริปต์ก่อนเขียนลงฐานรายงานครั้งเดียว
 */
$year = isset($argv[1]) ? (int) $argv[1] : 2026;
$month = isset($argv[2]) ? (int) $argv[2] : 9;
if ($month < 1 || $month > 12 || $year < 2000) {
    fwrite(STDERR, "ใช้: php import_monthly_sales.php <year> <month>\n");
    exit(1);
}

$src = require dirname(__DIR__) . '/config/source.local.php';
$erp = new PDO(
    'mysql:host=' . $src['host'] . ';port=' . (int) $src['port'] . ';dbname=' . $src['name'] . ';charset=utf8mb4',
    $src['user'],
    $src['pass'],
    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC)
);
$local = new PDO(
    'mysql:host=127.0.0.1;dbname=report_kraft_liner;charset=utf8mb4',
    'root',
    '',
    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC)
);
require dirname(__DIR__) . '/lib/sync_state.php';
sync_ensure_purchase_columns($local);
sync_ensure_seen_table($local);

$from = sprintf('%04d-%02d-01', $year, $month);
$to = date('Y-m-d', strtotime($from . ' +1 month'));
$pauseUs = 500000;

$daySales = $erp->prepare(
    'SELECT customer_no,
            MAX(customer_name) AS customer_name,
            SUM(sales_amount) AS sales_amount,
            SUM(sales_weight) AS weight_kg,
            SUM(purchase_amount) AS purchase_amount,
            SUM(purchase_weight) AS purchase_weight
     FROM monthly_sales_combination_cache
     WHERE posting_date >= ? AND posting_date < ?
       AND customer_no IS NOT NULL AND customer_no <> \'\' AND customer_no <> \'-\'
     GROUP BY customer_no'
);
$dayBlank = $erp->prepare(
    'SELECT customer_name, sales_amount, sales_weight, purchase_amount, purchase_weight
     FROM monthly_sales_combination_cache
     WHERE posting_date >= ? AND posting_date < ?
       AND (customer_no IS NULL OR customer_no = \'\' OR customer_no = \'-\')'
);
$dayIds = $erp->prepare(
    'SELECT id
     FROM monthly_sales_combination_cache
     WHERE posting_date >= ? AND posting_date < ?'
);

$salesByCode = array();
$blankByName = array();
$seenIds = array();
$cursor = $from;
while ($cursor < $to) {
    $next = date('Y-m-d', strtotime($cursor . ' +1 day'));
    echo "อ่าน {$cursor}\n";

    $daySales->execute(array($cursor, $next));
    while ($row = $daySales->fetch()) {
        $code = $row['customer_no'];
        if (!isset($salesByCode[$code])) {
            $salesByCode[$code] = array(
                'customer_no' => $code,
                'customer_name' => $row['customer_name'],
                'sales_amount' => 0.0,
                'weight_kg' => 0.0,
                'purchase_amount' => 0.0,
                'purchase_weight' => 0.0,
            );
        }
        if (strcmp((string) $row['customer_name'], (string) $salesByCode[$code]['customer_name']) > 0) {
            $salesByCode[$code]['customer_name'] = $row['customer_name'];
        }
        $salesByCode[$code]['sales_amount'] += (float) $row['sales_amount'];
        $salesByCode[$code]['weight_kg'] += (float) $row['weight_kg'];
        $salesByCode[$code]['purchase_amount'] += (float) $row['purchase_amount'];
        $salesByCode[$code]['purchase_weight'] += (float) $row['purchase_weight'];
    }

    $dayBlank->execute(array($cursor, $next));
    while ($row = $dayBlank->fetch()) {
        add_blank_row($blankByName, $row);
    }

    $dayIds->execute(array($cursor, $next));
    while ($idRow = $dayIds->fetch()) {
        $seenIds[] = (int) $idRow['id'];
    }

    $cursor = $next;
    if ($cursor < $to) {
        usleep($pauseUs);
    }
}

$rows = array_values($salesByCode);
$claimed = array();
foreach ($rows as $index => $row) {
    $key = norm_customer_name($row['customer_name']);
    $rows[$index]['name_key'] = $key;
    if ($key === '' || !isset($blankByName[$key]) || isset($claimed[$key])) {
        continue;
    }
    $rows[$index]['sales_amount'] = (float) $row['sales_amount'] + $blankByName[$key]['sales'];
    $rows[$index]['weight_kg'] = (float) $row['weight_kg'] + $blankByName[$key]['weight'];
    $claimed[$key] = $row['customer_no'];
}

$codeByName = known_code_by_name($erp, $local);
foreach ($blankByName as $key => $blank) {
    if (isset($claimed[$key])) {
        continue;
    }
    if (abs($blank['sales']) < 0.0000001 && abs($blank['weight']) < 0.0000001) {
        continue;
    }
    if (!isset($codeByName[$key]) || count($codeByName[$key]) !== 1) {
        fwrite(STDERR, "ข้ามชื่อที่ไม่มีรหัสเดียว: {$blank['name']}\n");
        continue;
    }
    $code = key($codeByName[$key]);
    $rows[] = array(
        'customer_no' => $code,
        'customer_name' => $blank['name'],
        'sales_amount' => $blank['sales'],
        'weight_kg' => $blank['weight'],
        'purchase_amount' => $blank['amount'],
        'purchase_weight' => $blank['weight_purchase'],
        'name_key' => $key,
    );
    $claimed[$key] = $code;
}

usort($rows, function ($a, $b) {
    $sa = (float) $a['sales_amount'];
    $sb = (float) $b['sales_amount'];
    if ($sa === $sb) {
        return strcmp($a['customer_no'], $b['customer_no']);
    }
    return ($sa > $sb) ? -1 : 1;
});

$local->beginTransaction();
try {
    $codes = array();
    $rank = 0;
    $update = $local->prepare(
        'UPDATE kraft_monthly_sales
         SET customer_name = ?, sort_order = ?, sales_amount = ?, weight_kg = ?, purchase_amount = ?, purchase_weight = ?, gap = ?, synced_at = NOW()
         WHERE year = ? AND month = ? AND customer_code = ?'
    );
    $insert = $local->prepare(
        'INSERT INTO kraft_monthly_sales
            (customer_code, customer_name, year, month, sort_order, sales_amount, weight_kg, purchase_amount, purchase_weight, gap, synced_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    $exists = $local->prepare(
        'SELECT id FROM kraft_monthly_sales WHERE year = ? AND month = ? AND customer_code = ?'
    );

    $updated = 0;
    $inserted = 0;
    foreach ($rows as $row) {
        if (isset($codes[$row['customer_no']])) {
            fwrite(STDERR, "รหัสซ้ำ ข้าม {$row['customer_no']} {$row['customer_name']}\n");
            continue;
        }
        $rank++;
        $codes[$row['customer_no']] = true;
        $sales = round((float) $row['sales_amount'], 2);
        $weight = round((float) $row['weight_kg'], 2);
        $purchaseAmount = (float) $row['purchase_amount'];
        $purchaseWeight = (float) $row['purchase_weight'];
        $key = $row['name_key'];
        if ($purchaseWeight == 0.0 && $key !== '' && isset($blankByName[$key])) {
            $purchaseAmount = $blankByName[$key]['amount'];
            $purchaseWeight = $blankByName[$key]['weight_purchase'];
        }
        $gap = null;
        if ($weight != 0.0 && $purchaseWeight != 0.0) {
            $gap = round(($sales / $weight) - ($purchaseAmount / $purchaseWeight), 2);
        }
        $exists->execute(array($year, $month, $row['customer_no']));
        if ($exists->fetch()) {
            $update->execute(array(
                $row['customer_name'],
                $rank,
                $sales,
                $weight,
                round($purchaseAmount, 2),
                round($purchaseWeight, 2),
                $gap,
                $year,
                $month,
                $row['customer_no'],
            ));
            $updated++;
        } else {
            $insert->execute(array(
                $row['customer_no'],
                $row['customer_name'],
                $year,
                $month,
                $rank,
                $sales,
                $weight,
                round($purchaseAmount, 2),
                round($purchaseWeight, 2),
                $gap,
            ));
            $inserted++;
        }
    }

    $codeList = array_keys($codes);
    $removed = 0;
    if ($codeList) {
        $placeholders = implode(',', array_fill(0, count($codeList), '?'));
        $params = array_merge(array($year, $month), $codeList);
        $del = $local->prepare(
            "DELETE FROM kraft_monthly_sales
             WHERE year = ? AND month = ? AND customer_code NOT IN ($placeholders)"
        );
        $del->execute($params);
        $removed = $del->rowCount();
    }

    $remembered = sync_remember_sales_ids($local, $seenIds);
    $local->commit();
} catch (Exception $e) {
    $local->rollBack();
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

$check = $local->prepare(
    'SELECT customer_code, sales_amount, weight_kg, gap
     FROM kraft_monthly_sales
     WHERE year = ? AND month = ? AND customer_code = \'CTR-00009\''
);
$check->execute(array($year, $month));
$sum = $local->prepare(
    'SELECT COUNT(*) c, ROUND(SUM(sales_amount),2) sales, ROUND(SUM(weight_kg),2) weight,
            ROUND(SUM(gap * weight_kg) / SUM(weight_kg), 2) gap
     FROM kraft_monthly_sales WHERE year = ? AND month = ?'
);
$sum->execute(array($year, $month));

echo 'จดแถวต้นทางที่นำเข้าแล้ว ' . $remembered . " รหัส\n";

echo "period {$from}\n";
echo "updated={$updated} inserted={$inserted} removed={$removed}\n";
echo 'CTR-00009 ' . json_encode($check->fetch()) . "\n";
echo 'total ' . json_encode($sum->fetch()) . "\n";

function norm_customer_name($name)
{
    $name = str_replace("\xE0\xB9\x8D\xE0\xB8\xB2", "\xE0\xB8\xB3", (string) $name);
    $name = preg_replace('/\s+/u', '', $name);
    return $name === null ? '' : $name;
}

function add_blank_row(&$map, $row)
{
    $key = norm_customer_name($row['customer_name']);
    if ($key === '') {
        return;
    }
    if (!isset($map[$key])) {
        $map[$key] = array(
            'name' => $row['customer_name'],
            'sales' => 0.0,
            'weight' => 0.0,
            'amount' => 0.0,
            'weight_purchase' => 0.0,
        );
    }
    $map[$key]['sales'] += (float) $row['sales_amount'];
    $map[$key]['weight'] += (float) $row['sales_weight'];
    $map[$key]['amount'] += (float) $row['purchase_amount'];
    $map[$key]['weight_purchase'] += (float) $row['purchase_weight'];
}

function known_code_by_name($erp, $local)
{
    $map = array();
    $add = function ($code, $name) use (&$map) {
        $code = trim((string) $code);
        $key = norm_customer_name($name);
        if ($code === '' || $code === '-' || $key === '') {
            return;
        }
        if (!isset($map[$key])) {
            $map[$key] = array();
        }
        $map[$key][$code] = true;
    };
    foreach ($local->query('SELECT customer_code, customer_name FROM kraft_customer_options') as $row) {
        $add($row['customer_code'], $row['customer_name']);
    }
    foreach ($local->query('SELECT customer_code, customer_name FROM kraft_monthly_sales') as $row) {
        $add($row['customer_code'], $row['customer_name']);
    }
    foreach ($erp->query(
        'SELECT customer_no, customer_name
         FROM monthly_sales_combination_cache
         WHERE customer_no IS NOT NULL AND customer_no <> \'\' AND customer_no <> \'-\'
         GROUP BY customer_no, customer_name'
    ) as $row) {
        $add($row['customer_no'], $row['customer_name']);
    }
    return $map;
}
