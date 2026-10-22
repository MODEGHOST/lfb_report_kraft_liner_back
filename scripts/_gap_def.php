<?php
$src = require dirname(__DIR__) . '/config/source.local.php';
$pdo = new PDO(
    'mysql:host=' . $src['host'] . ';port=' . (int) $src['port'] . ';dbname=' . $src['name'] . ';charset=utf8mb4',
    $src['user'],
    $src['pass'],
    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC)
);

function cols($pdo, $table)
{
    echo "== $table\n";
    foreach ($pdo->query('SHOW COLUMNS FROM `' . $table . '`') as $c) {
        echo $c['Field'] . ' ' . $c['Type'] . "\n";
    }
}

cols($pdo, 'lfbtarget');
cols($pdo, 'company_monthly_budgets');
cols($pdo, 'om_monthly_target');

echo "\n== om_monthly_actual 2026-09\n";
foreach ($pdo->query('SELECT * FROM om_monthly_actual WHERE actual_year=2026 AND actual_month=9') as $r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n== analysis month coverage\n";
foreach ($pdo->query('SELECT data_year, data_month, COUNT(*) c, ROUND(SUM(sales_amount),2) sales, ROUND(SUM(sales_variance),2) varn FROM om_customer_sales_analysis GROUP BY data_year, data_month ORDER BY data_year DESC, data_month DESC LIMIT 8') as $r) {
    echo json_encode($r) . "\n";
}

echo "\n== names like OM in analysis latest\n";
foreach ($pdo->query("SELECT data_year, data_month, customer_name, COUNT(*) c, ROUND(SUM(sales_amount),2) sales, ROUND(SUM(sales_variance),2) varn, ROUND(SUM(sqm),2) sqm FROM om_customer_sales_analysis WHERE customer_name LIKE '%แพ็คเกจ%' GROUP BY data_year, data_month, customer_name ORDER BY data_year DESC, data_month DESC LIMIT 12") as $r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
}
