<?php
$src = require dirname(__DIR__) . '/config/source.local.php';
$pdo = new PDO(
    'mysql:host=' . $src['host'] . ';port=' . (int) $src['port'] . ';dbname=' . $src['name'] . ';charset=utf8mb4',
    $src['user'],
    $src['pass'],
    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC)
);

$sql = "SELECT
    COUNT(*) line_count,
    COUNT(DISTINCT customer_no) customers,
    ROUND(SUM(sales_amount), 2) sales,
    ROUND(SUM(sales_weight), 2) weight,
    ROUND(SUM(area_sqm), 2) area
FROM monthly_sales_combination_cache
WHERE posting_date >= '2026-09-01' AND posting_date < '2026-10-01'";
echo "ALL SEP\n";
echo json_encode($pdo->query($sql)->fetch()) . "\n";

$sql2 = "SELECT
    ROUND(SUM(sales_amount), 2) sales,
    ROUND(SUM(sales_weight), 2) weight,
    ROUND(SUM(area_sqm), 2) area
FROM monthly_sales_combination_cache
WHERE posting_date >= '2026-09-01' AND posting_date < '2026-10-01'
  AND sales_amount <> 0";
echo "SEP sales<>0\n";
echo json_encode($pdo->query($sql2)->fetch()) . "\n";

$one = $pdo->query(
    "SELECT customer_no, customer_name,
            ROUND(SUM(sales_amount), 2) sales,
            ROUND(SUM(sales_weight), 2) weight,
            ROUND(SUM(area_sqm), 2) area
     FROM monthly_sales_combination_cache
     WHERE posting_date >= '2026-09-01' AND posting_date < '2026-10-01'
       AND customer_no = 'CTR-00009'
     GROUP BY customer_no, customer_name"
)->fetch();
echo "CTR-00009\n";
echo json_encode($one, JSON_UNESCAPED_UNICODE) . "\n";
if ($one && (float) $one['weight'] != 0) {
    echo 'unit=' . round(((float) $one['sales']) / ((float) $one['weight']), 4) . "\n";
}
