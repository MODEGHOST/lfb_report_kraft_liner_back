<?php
function norm_name($name)
{
    $name = preg_replace('/\s+/u', '', (string) $name);
    return $name === null ? '' : $name;
}

$pdo = new PDO('mysql:host=127.0.0.1;dbname=report_kraft_liner;charset=utf8mb4', 'root', '', array(
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
));
$targets = $pdo->query("SELECT customer_name FROM kraft_monthly_targets WHERE year = 2026 AND month = 9")->fetchAll();
$sales = $pdo->query("SELECT customer_code, customer_name, sales_amount FROM kraft_monthly_sales WHERE year = 2026 AND month = 9")->fetchAll();
$map = array();
foreach ($targets as $row) {
    $map[norm_name($row['customer_name'])] = true;
}
$hit = 0;
foreach ($sales as $row) {
    if (isset($map[norm_name($row['customer_name'])])) {
        $hit++;
    }
}
echo "targets=" . count($targets) . " sales=" . count($sales) . " name_match=" . $hit . PHP_EOL;
foreach ($sales as $row) {
    if ($row['customer_code'] === 'CTR-00009') {
        echo $row['customer_name'] . ' ' . $row['sales_amount'] . ' match=' . (isset($map[norm_name($row['customer_name'])]) ? 'yes' : 'no') . PHP_EOL;
    }
}
