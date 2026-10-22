<?php
$src = require dirname(__DIR__) . '/config/source.local.php';
$pdo = new PDO(
    'mysql:host=' . $src['host'] . ';port=' . (int) $src['port'] . ';charset=utf8mb4',
    $src['user'],
    $src['pass'],
    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC)
);

echo "== columns with gap/weight/sales\n";
$q = $pdo->query(
    "SELECT TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA IN ('leefibre','om_appcenter')
       AND (COLUMN_NAME LIKE '%gap%' OR COLUMN_NAME LIKE '%weight%' OR COLUMN_NAME LIKE '%sales%')
     ORDER BY TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME"
);
foreach ($q as $r) {
    echo $r['TABLE_SCHEMA'] . '.' . $r['TABLE_NAME'] . '.' . $r['COLUMN_NAME'] . "\n";
}

echo "\n== sync log last 15\n";
foreach ($pdo->query('SELECT * FROM leefibre.om_system_sync_log ORDER BY id DESC LIMIT 15') as $r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
}
