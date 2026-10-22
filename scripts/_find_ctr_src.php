<?php
$src = require dirname(__DIR__) . '/config/source.local.php';
$pdo = new PDO(
    'mysql:host=' . $src['host'] . ';port=' . $src['port'] . ';dbname=' . $src['name'] . ';charset=utf8mb4',
    $src['user'],
    $src['pass'],
    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC)
);
$db = $src['name'];

$tables = $pdo->query(
    "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = " . $pdo->quote($db) . " AND TABLE_TYPE = 'BASE TABLE'"
)->fetchAll(PDO::FETCH_COLUMN);

echo "tables=" . count($tables) . "\n";

$hits = array();
$stmt = $pdo->prepare(
    'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
);
foreach ($tables as $table) {
    $stmt->execute(array($db, $table));
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $blob = strtolower(implode(' ', $cols));
    if (strpos($blob, 'customer') !== false && (strpos($blob, 'weight') !== false || strpos($blob, 'amount') !== false || strpos($blob, 'sales') !== false || strpos($blob, 'kg') !== false)) {
        $hits[] = $table . ' | ' . implode(',', array_slice($cols, 0, 18));
    }
}
echo "candidate sales-like tables: " . count($hits) . "\n";
foreach ($hits as $line) {
    echo $line . "\n";
}
