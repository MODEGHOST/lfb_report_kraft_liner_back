<?php
$src = require dirname(__DIR__) . '/config/source.local.php';
$pdo = new PDO(
    'mysql:host=' . $src['host'] . ';port=' . $src['port'] . ';dbname=' . $src['name'] . ';charset=utf8mb4',
    $src['user'],
    $src['pass'],
    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC)
);

function cols($pdo, $db, $table)
{
    echo "== $table\n";
    $stmt = $pdo->prepare(
        'SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION'
    );
    $stmt->execute(array($db, $table));
    foreach ($stmt->fetchAll() as $row) {
        echo $row['COLUMN_NAME'] . ' ' . $row['DATA_TYPE'] . "\n";
    }
}

$db = $src['name'];
cols($pdo, $db, 'monthly_sales_combination_cache');
cols($pdo, $db, 'om_customer_sales_analysis');

echo "\n== sample cache OM\n";
$sql = "SELECT * FROM monthly_sales_combination_cache
        WHERE customer_name LIKE '%โอ%เอ็ม%' OR customer_no = 'CTR-00009'
        LIMIT 5";
$rows = $pdo->query($sql)->fetchAll();
echo 'rows=' . count($rows) . "\n";
if ($rows) {
    echo implode("\t", array_keys($rows[0])) . "\n";
    foreach ($rows as $r) {
        echo implode("\t", $r) . "\n";
    }
}
