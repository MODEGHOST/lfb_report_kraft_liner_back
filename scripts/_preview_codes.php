<?php
$src = require dirname(__DIR__) . '/config/source.local.php';
$erp = new PDO(
    'mysql:host=' . $src['host'] . ';port=' . (int) $src['port'] . ';dbname=' . $src['name'] . ';charset=utf8mb4',
    $src['user'],
    $src['pass'],
    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC)
);
$local = new PDO(
    'mysql:host=127.0.0.1;dbname=report_kraft_liner;charset=utf8mb4',
    'root',
    '',
    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC)
);

$agg = $erp->query(
    "SELECT customer_no,
            ROUND(SUM(sales_amount),2) sales,
            ROUND(SUM(sales_weight),2) weight,
            ROUND(SUM(purchase_amount),2) purch,
            ROUND(SUM(purchase_weight),2) pw
     FROM monthly_sales_combination_cache
     WHERE posting_date >= '2026-09-01' AND posting_date < '2026-10-01'
     GROUP BY customer_no"
)->fetchAll();
$by = array();
foreach ($agg as $r) {
    $by[$r['customer_no']] = $r;
}

$localRows = $local->query(
    "SELECT customer_code, customer_name, sales_amount, weight_kg, gap
     FROM kraft_monthly_sales WHERE year=2026 AND month=9"
)->fetchAll();
$localCodes = array();
foreach ($localRows as $r) {
    $localCodes[$r['customer_code']] = $r;
}

$onlyLocal = array();
$onlyErp = array();
foreach ($localRows as $r) {
    if (!isset($by[$r['customer_code']])) {
        $onlyLocal[] = $r['customer_code'] . ' ' . $r['customer_name'] . ' old=' . $r['sales_amount'];
    }
}
foreach ($by as $code => $r) {
    if (!isset($localCodes[$code])) {
        $onlyErp[] = $code . ' sales=' . $r['sales'];
    }
}
echo 'local=' . count($localRows) . ' erp=' . count($by) . "\n";
echo "only local:\n" . implode("\n", $onlyLocal) . "\n";
echo "only erp:\n" . implode("\n", $onlyErp) . "\n";

echo "\n== top by sales\n";
usort($agg, function ($a, $b) {
    if ($a['sales'] == $b['sales']) return 0;
    return ($a['sales'] > $b['sales']) ? -1 : 1;
});
foreach (array_slice($agg, 0, 3) as $r) {
    $sell = ((float) $r['weight'] != 0.0) ? (float) $r['sales'] / (float) $r['weight'] : null;
    $buy = ((float) $r['pw'] != 0.0) ? (float) $r['purch'] / (float) $r['pw'] : null;
    $gap = ($sell !== null && $buy !== null) ? round($sell - $buy, 2) : null;
    $name = $erp->prepare('SELECT customer_name FROM monthly_sales_combination_cache WHERE customer_no=? AND posting_date>=\'2026-09-01\' AND posting_date<\'2026-10-01\' LIMIT 1');
    $name->execute(array($r['customer_no']));
    echo $r['customer_no'] . ' ' . $name->fetchColumn()
        . ' sales=' . $r['sales'] . ' w=' . $r['weight']
        . ' price=' . ($sell === null ? '' : round($sell, 2))
        . ' gap=' . $gap . "\n";
}
