<?php
$src = require dirname(__DIR__) . '/config/source.local.php';
$erp = new PDO(
    'mysql:host=' . $src['host'] . ';port=' . (int) $src['port'] . ';dbname=' . $src['name'] . ';charset=utf8mb4',
    $src['user'],
    $src['pass'],
    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC)
);

echo "== July blank sample\n";
foreach ($erp->query(
    "SELECT customer_no, customer_name, sales_amount, sales_weight, purchase_amount, purchase_weight
     FROM monthly_sales_combination_cache
     WHERE posting_date >= '2026-07-01' AND posting_date < '2026-08-01'
       AND (customer_no IS NULL OR customer_no IN ('', '-'))
     LIMIT 8"
) as $r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n== July name overlap\n";
echo json_encode($erp->query(
    "SELECT
        COUNT(DISTINCT CASE WHEN customer_no IS NOT NULL AND customer_no NOT IN ('', '-') THEN REPLACE(customer_name, ' ', '') END) coded_names,
        COUNT(DISTINCT CASE WHEN customer_no IS NULL OR customer_no IN ('', '-') THEN REPLACE(customer_name, ' ', '') END) blank_names
     FROM monthly_sales_combination_cache
     WHERE posting_date >= '2026-07-01' AND posting_date < '2026-08-01'"
)->fetch()) . "\n";

echo json_encode($erp->query(
    "SELECT COUNT(*) matched_names FROM (
        SELECT REPLACE(customer_name, ' ', '') n
        FROM monthly_sales_combination_cache
        WHERE posting_date >= '2026-07-01' AND posting_date < '2026-08-01'
          AND customer_no IS NOT NULL AND customer_no NOT IN ('', '-')
        GROUP BY n
     ) c
     INNER JOIN (
        SELECT REPLACE(customer_name, ' ', '') n
        FROM monthly_sales_combination_cache
        WHERE posting_date >= '2026-07-01' AND posting_date < '2026-08-01'
          AND (customer_no IS NULL OR customer_no IN ('', '-'))
          AND purchase_weight <> 0
        GROUP BY n
     ) b ON b.n = c.n"
)->fetch()) . "\n";
