<?php
$local = new PDO(
    'mysql:host=127.0.0.1;dbname=report_kraft_liner;charset=utf8mb4',
    'root',
    '',
    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC)
);
foreach ($local->query('SELECT year, month, COUNT(*) c, ROUND(SUM(sales_amount),2) sales FROM kraft_monthly_sales GROUP BY year, month ORDER BY year, month') as $r) {
    echo json_encode($r) . "\n";
}
echo "trx " . $local->query('SELECT @@in_transaction')->fetchColumn() . "\n";
