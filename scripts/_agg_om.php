<?php
$src = require dirname(__DIR__) . '/config/source.local.php';
$pdo = new PDO(
    'mysql:host=' . $src['host'] . ';port=' . $src['port'] . ';dbname=' . $src['name'] . ';charset=utf8mb4',
    $src['user'],
    $src['pass'],
    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC)
);

$name = 'บริษัท  โอ เอ็ม แพ็คเกจจิ้ง  โซลูชั่น  จำกัด';

echo "== name variants Sep 2026\n";
$q = $pdo->query(
    "SELECT customer_name, customer_no, COUNT(*) c,
            ROUND(SUM(sales_amount),2) sales,
            ROUND(SUM(sales_weight),2) w,
            ROUND(SUM(area_sqm),2) area,
            ROUND(SUM(purchase_amount),2) purch,
            ROUND(SUM(purchase_weight),2) pw
     FROM monthly_sales_combination_cache
     WHERE posting_date >= '2026-09-01' AND posting_date < '2026-10-01'
       AND (customer_name LIKE '%โอ%เอ็ม%แพ็ค%' OR customer_no = 'CTR-00009')
     GROUP BY customer_name, customer_no"
);
foreach ($q as $r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n== exact name all amounts\n";
$stmt = $pdo->prepare(
    "SELECT COUNT(*) c,
            ROUND(SUM(sales_amount),2) sales,
            ROUND(SUM(sales_weight),2) w,
            ROUND(SUM(area_sqm),2) area,
            ROUND(SUM(CASE WHEN sales_amount <> 0 THEN sales_amount ELSE 0 END),2) sales_nz,
            ROUND(SUM(CASE WHEN sales_weight <> 0 THEN sales_weight ELSE 0 END),2) w_nz,
            ROUND(SUM(CASE WHEN sales_amount > 0 THEN sales_amount ELSE 0 END),2) sales_pos,
            ROUND(SUM(CASE WHEN sales_weight > 0 THEN sales_weight ELSE 0 END),2) w_pos,
            ROUND(SUM(CASE WHEN sales_amount < 0 THEN sales_amount ELSE 0 END),2) sales_neg,
            ROUND(SUM(CASE WHEN sales_weight < 0 THEN sales_weight ELSE 0 END),2) w_neg
     FROM monthly_sales_combination_cache
     WHERE posting_date >= '2026-09-01' AND posting_date < '2026-10-01'
       AND customer_name = ?"
);
$stmt->execute(array($name));
echo json_encode($stmt->fetch(), JSON_UNESCAPED_UNICODE) . "\n";

echo "\n== min_create_date month instead\n";
$stmt = $pdo->prepare(
    "SELECT ROUND(SUM(sales_amount),2) sales, ROUND(SUM(sales_weight),2) w, ROUND(SUM(area_sqm),2) area, COUNT(*) c
     FROM monthly_sales_combination_cache
     WHERE min_create_date >= '2026-09-01' AND min_create_date < '2026-10-01'
       AND customer_name = ?"
);
$stmt->execute(array($name));
echo json_encode($stmt->fetch(), JSON_UNESCAPED_UNICODE) . "\n";
