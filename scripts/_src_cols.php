<?php
$src = require dirname(__DIR__) . '/config/source.local.php';
$pdo = new PDO(
    'mysql:host=' . $src['host'] . ';port=' . $src['port'] . ';dbname=' . $src['name'] . ';charset=utf8mb4',
    $src['user'],
    $src['pass'],
    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC)
);
$want = array('customers', 'customer_care', 'om_order_condition', 'om_customer', 'excluded_customers');
foreach ($want as $table) {
    echo '== ' . $table . PHP_EOL;
    $stmt = $pdo->prepare(
        'SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION'
    );
    $stmt->execute(array($src['name'], $table));
    foreach ($stmt->fetchAll() as $row) {
        echo $row['COLUMN_NAME'] . ' ' . $row['DATA_TYPE'] . PHP_EOL;
    }
    $n = $pdo->query('SELECT COUNT(*) c FROM `' . $table . '`')->fetch();
    echo 'rows=' . $n['c'] . PHP_EOL;
}
