<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=report_kraft_liner;charset=utf8mb4', 'root', '', array(
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
));
$rows = $pdo->query(
    "SELECT customer_code, customer_name, sales_amount
     FROM kraft_monthly_sales
     WHERE year = 2026 AND month = 9
     ORDER BY customer_code
     LIMIT 8"
)->fetchAll();
$one = $pdo->query(
    "SELECT customer_code, customer_name, sales_amount
     FROM kraft_monthly_sales
     WHERE year = 2026 AND month = 9 AND customer_code = 'CTR-00009'"
)->fetch();
echo 'sample' . PHP_EOL;
foreach ($rows as $row) {
    echo $row['customer_code'] . ' | ' . $row['customer_name'] . ' | ' . $row['sales_amount'] . PHP_EOL;
}
echo 'CTR ' . ($one ? $one['customer_code'] . ' ' . $one['sales_amount'] : 'missing') . PHP_EOL;
