<?php
$src = require dirname(__DIR__) . '/config/source.local.php';
$erp = new PDO(
    'mysql:host=' . $src['host'] . ';port=' . (int) $src['port'] . ';dbname=' . $src['name'] . ';charset=utf8mb4',
    $src['user'],
    $src['pass'],
    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC)
);
$stmt = $erp->prepare(
    "SELECT customer_no, customer_name,
            ROUND(SUM(purchase_amount),2) pa, ROUND(SUM(purchase_weight),2) pw
     FROM monthly_sales_combination_cache
     WHERE posting_date >= '2026-07-01' AND posting_date < '2026-08-01'
       AND customer_name LIKE '%พีเอ็น%'
     GROUP BY customer_no, customer_name"
);
$stmt->execute();
foreach ($stmt->fetchAll() as $r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
}
