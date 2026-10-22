<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=report_kraft_liner;charset=utf8mb4', 'root', '', array(
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
));
$sql = "SELECT s.customer_code, s.customer_name
        FROM kraft_monthly_sales s
        LEFT JOIN kraft_customer_options o ON o.customer_code = s.customer_code
        WHERE s.year = 2026 AND s.month = 9 AND o.id IS NULL
        ORDER BY s.customer_code";
$rows = $pdo->query($sql)->fetchAll();
echo 'sept_without_code_option=' . count($rows) . PHP_EOL;
foreach ($rows as $row) {
    echo $row['customer_code'] . "\t" . $row['customer_name'] . PHP_EOL;
}
