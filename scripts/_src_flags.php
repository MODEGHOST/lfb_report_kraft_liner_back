<?php
$src = require dirname(__DIR__) . '/config/source.local.php';
$pdo = new PDO(
    'mysql:host=' . $src['host'] . ';port=' . $src['port'] . ';dbname=' . $src['name'] . ';charset=utf8mb4',
    $src['user'],
    $src['pass'],
    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
);
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM);
echo "tables=" . count($tables) . PHP_EOL;
foreach ($tables as $t) {
    echo 'T ' . $t[0] . PHP_EOL;
}
$sql = "SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = ?
          AND (
            COLUMN_NAME LIKE '%pallet%'
            OR COLUMN_NAME LIKE '%rope%'
            OR COLUMN_NAME LIKE '%film%'
            OR COLUMN_NAME LIKE '%option%'
            OR COLUMN_NAME LIKE '%flag%'
            OR COLUMN_NAME LIKE '%sheet%'
            OR COLUMN_NAME LIKE '%let%'
          )";
$stmt = $pdo->prepare($sql);
$stmt->execute(array($src['name']));
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo 'C ' . $row['TABLE_NAME'] . '.' . $row['COLUMN_NAME'] . ' ' . $row['DATA_TYPE'] . PHP_EOL;
}
