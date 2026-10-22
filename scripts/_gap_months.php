<?php
$src = require dirname(__DIR__) . '/config/source.local.php';
$erp = new PDO(
    'mysql:host=' . $src['host'] . ';port=' . (int) $src['port'] . ';dbname=' . $src['name'] . ';charset=utf8mb4',
    $src['user'],
    $src['pass'],
    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC)
);

echo "== cache purchase by month 2026\n";
foreach ($erp->query(
    "SELECT DATE_FORMAT(posting_date,'%Y-%m') ym,
            COUNT(*) line_count,
            SUM(purchase_amount <> 0) purch_lines,
            ROUND(SUM(purchase_amount),2) pa,
            ROUND(SUM(purchase_weight),2) pw,
            ROUND(SUM(sales_amount),2) sales
     FROM monthly_sales_combination_cache
     WHERE posting_date >= '2026-01-01' AND posting_date < '2026-11-01'
     GROUP BY ym ORDER BY ym"
) as $r) {
    echo json_encode($r) . "\n";
}
