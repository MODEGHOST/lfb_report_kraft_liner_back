<?php
$p = new PDO('mysql:host=127.0.0.1;dbname=report_kraft_liner;charset=utf8mb4','root','',[
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
function norm($n) {
    $n = preg_replace('/\s+/u', '', (string)$n);
    return $n === null ? '' : $n;
}
$targets = $p->query("SELECT customer_name, company_target FROM kraft_monthly_targets WHERE year=2026 AND month=9")->fetchAll();
$sales = $p->query("SELECT customer_code, customer_name, sales_amount FROM kraft_monthly_sales WHERE year=2026 AND month=9")->fetchAll();
$tset = array();
foreach ($targets as $r) {
    $k = norm($r['customer_name']);
    if ($k !== '') $tset[$k] = $r;
}
$sset = array();
foreach ($sales as $r) {
    $k = norm($r['customer_name']);
    if ($k !== '') $sset[$k] = $r;
}
$both = 0; $onlyT = 0; $onlyS = 0; $tZero = 0; $tPos = 0;
foreach ($tset as $k => $r) {
    if (isset($sset[$k])) $both++; else $onlyT++;
    if ((float)$r['company_target'] > 0) $tPos++; else $tZero++;
}
foreach ($sset as $k => $r) {
    if (!isset($tset[$k])) $onlyS++;
}
echo json_encode(array(
    'targets' => count($tset),
    'sales' => count($sset),
    'both' => $both,
    'target_only' => $onlyT,
    'sales_only' => $onlyS,
    'union' => $both + $onlyT + $onlyS,
    'target_positive' => $tPos,
    'sales_zero' => count(array_filter($sales, function ($r) { return (float)$r['sales_amount'] == 0; })),
), JSON_UNESCAPED_UNICODE), "\n";
