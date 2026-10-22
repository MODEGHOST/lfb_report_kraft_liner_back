<?php
$src = require dirname(__DIR__) . '/config/source.local.php';
$pdo = new PDO(
    'mysql:host=' . $src['host'] . ';port=' . (int) $src['port'] . ';charset=utf8mb4',
    $src['user'],
    $src['pass'],
    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC)
);
echo "== databases\n";
foreach ($pdo->query('SHOW DATABASES') as $r) {
    echo implode('', $r) . "\n";
}
echo "== routines in " . $src['name'] . "\n";
$pdo->exec('USE `' . str_replace('`', '', $src['name']) . '`');
foreach ($pdo->query('SHOW PROCEDURE STATUS WHERE Db = ' . $pdo->quote($src['name'])) as $r) {
    echo $r['Name'] . "\n";
}
foreach ($pdo->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'") as $r) {
    echo 'VIEW ' . implode(' | ', $r) . "\n";
}
