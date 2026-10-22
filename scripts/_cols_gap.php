<?php
$src = require dirname(__DIR__) . '/config/source.local.php';
$pdo = new PDO(
    'mysql:host=' . $src['host'] . ';port=' . (int) $src['port'] . ';dbname=' . $src['name'] . ';charset=utf8mb4',
    $src['user'],
    $src['pass'],
    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC)
);

$tables = array(
    'scoreboard_results',
    'scoreboard_results_om',
    'om_monthly_actual',
    'monthly_paper_consumption_cache',
    'om_customer_sales_analysis',
);
$stmt = $pdo->prepare(
    'SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION'
);
foreach ($tables as $table) {
    echo "== $table\n";
    $stmt->execute(array($src['name'], $table));
    foreach ($stmt->fetchAll() as $row) {
        echo $row['COLUMN_NAME'] . ' ' . $row['DATA_TYPE'] . "\n";
    }
}
