<?php
$p = new PDO(
    'mysql:host=127.0.0.1;dbname=report_kraft_liner;charset=utf8mb4',
    'root',
    '',
    array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    )
);

echo "COLUMNS\n";
foreach ($p->query('SHOW COLUMNS FROM kraft_monthly_sales') as $c) {
    echo $c['Field'] . ' ' . $c['Type'] . "\n";
}

echo "\nROW\n";
$row = $p->query(
    "SELECT * FROM kraft_monthly_sales WHERE year = 2026 AND month = 9 AND customer_code = 'CTR-00009'"
)->fetch();
var_export($row);
echo "\n\nTARGETS\n";
$targets = $p->query(
    "SELECT id, customer_name, company_target, sale_target, cs_target
     FROM kraft_monthly_targets
     WHERE year = 2026 AND month = 9
       AND (customer_name LIKE '%โอ%เอ็ม%' OR customer_name LIKE '%OM%')"
)->fetchAll();
var_export($targets);

echo "\n\nTABLES\n";
foreach ($p->query('SHOW TABLES') as $t) {
    echo implode(',', $t) . "\n";
}
