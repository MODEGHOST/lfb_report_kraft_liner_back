<?php
/**
 * เติม GAP กรกฎาคม–สิงหาคม ด้วยสูตรเดียวกับกันยายน
 * ยอดซื้อกระดาษของสองเดือนนี้อยู่ในแถวที่รหัสลูกค้าเป็น "-"
 * จึงจับคู่ด้วยชื่อ แล้วเขียนเฉพาะคอลัมน์ gap ในฐานรายงาน
 */
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

function norm_name($name)
{
    $name = preg_replace('/\s+/u', '', (string) $name);
    return $name === null ? '' : $name;
}

$year = 2026;
foreach (array(7, 8) as $month) {
    $from = sprintf('%04d-%02d-01', $year, $month);
    $to = date('Y-m-d', strtotime($from . ' +1 month'));
    $stmt = $erp->prepare(
        'SELECT customer_name, purchase_amount, purchase_weight
         FROM monthly_sales_combination_cache
         WHERE posting_date >= ? AND posting_date < ?
           AND (customer_no IS NULL OR customer_no = \'\' OR customer_no = \'-\')'
    );
    $stmt->execute(array($from, $to));
    $byName = array();
    foreach ($stmt->fetchAll() as $row) {
        $key = norm_name($row['customer_name']);
        if ($key === '') {
            continue;
        }
        if (!isset($byName[$key])) {
            $byName[$key] = array('amount' => 0.0, 'weight' => 0.0);
        }
        $byName[$key]['amount'] += (float) $row['purchase_amount'];
        $byName[$key]['weight'] += (float) $row['purchase_weight'];
    }

    $sales = $local->prepare(
        'SELECT customer_code, customer_name, sales_amount, weight_kg
         FROM kraft_monthly_sales WHERE year = ? AND month = ?'
    );
    $sales->execute(array($year, $month));
    $rows = $sales->fetchAll();
    $update = $local->prepare(
        'UPDATE kraft_monthly_sales SET gap = ?, synced_at = NOW()
         WHERE year = ? AND month = ? AND customer_code = ?'
    );

    $filled = 0;
    $missing = 0;
    $local->beginTransaction();
    foreach ($rows as $row) {
        $key = norm_name($row['customer_name']);
        $weight = (float) $row['weight_kg'];
        $gap = null;
        if ($weight != 0.0 && $key !== '' && isset($byName[$key]) && $byName[$key]['weight'] != 0.0) {
            $gap = round(((float) $row['sales_amount'] / $weight) - ($byName[$key]['amount'] / $byName[$key]['weight']), 2);
            $filled++;
        } else {
            $missing++;
        }
        $update->execute(array($gap, $year, $month, $row['customer_code']));
    }
    $local->commit();

    $ctr = $local->prepare(
        'SELECT sales_amount, weight_kg, gap FROM kraft_monthly_sales
         WHERE year = ? AND month = ? AND customer_code = \'CTR-00009\''
    );
    $ctr->execute(array($year, $month));
    echo "month {$month} filled={$filled} missing={$missing} CTR=" . json_encode($ctr->fetch()) . "\n";
}
