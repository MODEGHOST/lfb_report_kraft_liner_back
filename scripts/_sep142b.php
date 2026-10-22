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
$sales = $p->query("SELECT customer_name, sales_amount, weight_kg FROM kraft_monthly_sales WHERE year=2026 AND month=9")->fetchAll();
$map = array();
foreach ($targets as $r) {
    $k = norm($r['customer_name']);
    if ($k === '') continue;
    if (!isset($map[$k])) $map[$k] = array('t' => 0.0, 's' => null, 'w' => null);
    $map[$k]['t'] = (float)$r['company_target'];
}
foreach ($sales as $r) {
    $k = norm($r['customer_name']);
    if ($k === '') continue;
    if (!isset($map[$k])) $map[$k] = array('t' => 0.0, 's' => null, 'w' => null);
    $map[$k]['s'] = (float)$r['sales_amount'];
    $map[$k]['w'] = (float)$r['weight_kg'];
}
$a = $b = $c = $d = 0;
foreach ($map as $row) {
    $hasS = $row['s'] !== null && ($row['s'] != 0.0 || $row['w'] != 0.0);
    $hasT = $row['t'] > 0;
    if ($hasT || $hasS) $a++;
    if ($hasT || $row['s'] !== null) $b++;
    if ($row['s'] !== null) $c++;
    if ($hasT) $d++;
}
echo json_encode(array(
    'names' => count($map),
    'target_or_activity' => $a,
    'target_or_any_sales_row' => $b,
    'any_sales_row' => $c,
    'target_gt_0' => $d,
), JSON_UNESCAPED_UNICODE), "\n";
