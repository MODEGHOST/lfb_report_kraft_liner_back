<?php
$src = require dirname(__DIR__) . '/config/source.local.php';
$pdo = new PDO(
    'mysql:host=' . $src['host'] . ';port=' . (int) $src['port'] . ';dbname=' . $src['name'] . ';charset=utf8mb4',
    $src['user'],
    $src['pass'],
    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC)
);

echo "== company budget 2026\n";
foreach ($pdo->query('SELECT year, month, sale_actual, weight_actual, selling_price, buying_price, gap_baht, total_sqm FROM company_monthly_budgets WHERE year=2026 ORDER BY month') as $r) {
    echo json_encode($r) . "\n";
}

echo "\n== lfbtarget 2026\n";
foreach ($pdo->query('SELECT year, month, sale_amount, weight, sqm, gap, paper_cost FROM lfbtarget WHERE year=2026 ORDER BY month') as $r) {
    echo json_encode($r) . "\n";
}

echo "\n== om_monthly_target 2026 gap\n";
foreach ($pdo->query('SELECT target_year, target_month, target_sales, output_sqm, avg_sale_per_sqm, avg_paper_price, paper_cost, gap FROM om_monthly_target WHERE target_year=2026 ORDER BY target_month') as $r) {
    echo json_encode($r) . "\n";
}

$sales = 14872305.64;
$w = 694818.49;
$price = $sales / $w;
echo "\ncache unit price=" . round($price, 4) . "\n";

$bud = $pdo->query('SELECT buying_price, selling_price, gap_baht, paper_cost_pct FROM company_monthly_budgets WHERE year=2026 AND month=9')->fetch();
echo 'budget sep ' . json_encode($bud) . "\n";
if ($bud && $bud['buying_price'] !== null) {
    echo 'price - buying = ' . round($price - (float) $bud['buying_price'], 4) . "\n";
}
$l = $pdo->query('SELECT gap, paper_cost FROM lfbtarget WHERE year=2026 AND month=9')->fetch();
echo 'lfg sep ' . json_encode($l) . "\n";
if ($l) {
    echo 'price - paper_cost = ' . round($price - (float) $l['paper_cost'], 4) . "\n";
}
