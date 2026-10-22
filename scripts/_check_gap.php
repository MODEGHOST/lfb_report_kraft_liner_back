<?php
$local = new PDO(
    'mysql:host=127.0.0.1;dbname=report_kraft_liner;charset=utf8mb4',
    'root',
    '',
    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC)
);
echo "== by month\n";
foreach ($local->query('SELECT month, COUNT(*) c, SUM(gap IS NULL) gap_null, ROUND(SUM(sales_amount),2) sales FROM kraft_monthly_sales WHERE year=2026 GROUP BY month ORDER BY month') as $r) {
    echo json_encode($r) . "\n";
}
echo "== CTR\n";
foreach ($local->query("SELECT month, sales_amount, weight_kg, gap, synced_at FROM kraft_monthly_sales WHERE customer_code='CTR-00009' AND year=2026 ORDER BY month") as $r) {
    echo json_encode($r) . "\n";
}

$src = require dirname(__DIR__) . '/config/source.local.php';
$erp = new PDO(
    'mysql:host=' . $src['host'] . ';port=' . (int) $src['port'] . ';dbname=' . $src['name'] . ';charset=utf8mb4',
    $src['user'],
    $src['pass'],
    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC)
);
echo "== CTR purchase by month (read only)\n";
foreach ($erp->query(
    "SELECT DATE_FORMAT(posting_date,'%m') m,
            ROUND(SUM(sales_amount),2) sales,
            ROUND(SUM(sales_weight),2) sw,
            ROUND(SUM(purchase_amount),2) pa,
            ROUND(SUM(purchase_weight),2) pw
     FROM monthly_sales_combination_cache
     WHERE customer_no='CTR-00009'
       AND posting_date >= '2026-07-01' AND posting_date < '2026-11-01'
     GROUP BY m ORDER BY m"
) as $r) {
    echo json_encode($r) . "\n";
}
