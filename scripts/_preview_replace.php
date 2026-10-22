<?php
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

echo "== local periods\n";
foreach ($local->query('SELECT year, month, COUNT(*) c, ROUND(SUM(sales_amount),2) sales, ROUND(SUM(weight_kg),2) w FROM kraft_monthly_sales GROUP BY year, month ORDER BY year, month') as $r) {
    echo json_encode($r) . "\n";
}

echo "\n== local indexes\n";
foreach ($local->query('SHOW INDEX FROM kraft_monthly_sales') as $r) {
    echo $r['Key_name'] . ' ' . $r['Column_name'] . "\n";
}

echo "\n== sep customer_no quality\n";
$row = $erp->query(
    "SELECT
        SUM(customer_no IS NULL OR customer_no = '' OR customer_no = '-') bad_lines,
        COUNT(DISTINCT CASE WHEN customer_no IS NULL OR customer_no = '' OR customer_no = '-' THEN customer_name END) bad_names,
        COUNT(DISTINCT customer_no) codes
     FROM monthly_sales_combination_cache
     WHERE posting_date >= '2026-09-01' AND posting_date < '2026-10-01'"
)->fetch();
echo json_encode($row) . "\n";

$erpRow = $erp->query(
    "SELECT ROUND(SUM(sales_amount),2) sales, ROUND(SUM(sales_weight),2) w,
            ROUND(SUM(purchase_amount),2) purch, ROUND(SUM(purchase_weight),2) pw
     FROM monthly_sales_combination_cache
     WHERE posting_date >= '2026-09-01' AND posting_date < '2026-10-01'
       AND customer_no = 'CTR-00009'"
)->fetch();
$sell = (float) $erpRow['sales'] / (float) $erpRow['w'];
$buyW = (float) $erpRow['pw'] > 0 ? (float) $erpRow['purch'] / (float) $erpRow['pw'] : null;
$buyS = (float) $erpRow['w'] > 0 ? (float) $erpRow['purch'] / (float) $erpRow['w'] : null;
echo "CTR sell=" . round($sell, 2)
    . " buy_per_pw=" . round($buyW, 4)
    . " gap_pw=" . round($sell - $buyW, 2)
    . " buy_per_sw=" . round($buyS, 4)
    . " gap_sw=" . round($sell - $buyS, 2) . "\n";
