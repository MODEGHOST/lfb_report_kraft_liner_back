<?php
$src = require dirname(__DIR__) . '/config/source.local.php';
$pdo = new PDO(
    'mysql:host=' . $src['host'] . ';port=' . $src['port'] . ';dbname=' . $src['name'] . ';charset=utf8mb4',
    $src['user'],
    $src['pass'],
    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC)
);

echo "== analysis Sep 2026 OM\n";
$q = $pdo->query(
    "SELECT customer_name, COUNT(*) c, ROUND(SUM(sales_amount),2) sales, ROUND(SUM(quantity),2) qty, ROUND(SUM(sqm),2) sqm
     FROM om_customer_sales_analysis
     WHERE data_year = 2026 AND data_month = 9
       AND customer_name LIKE '%โอ%เอ็ม%แพ็ค%'
     GROUP BY customer_name"
);
foreach ($q as $r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n== cache other months CTR-00009 2026\n";
$q = $pdo->query(
    "SELECT DATE_FORMAT(posting_date, '%Y-%m') ym, COUNT(*) c,
            ROUND(SUM(sales_amount),2) sales, ROUND(SUM(sales_weight),2) w
     FROM monthly_sales_combination_cache
     WHERE customer_no = 'CTR-00009' AND posting_date >= '2026-01-01' AND posting_date < '2027-01-01'
     GROUP BY ym ORDER BY ym"
);
foreach ($q as $r) {
    echo json_encode($r) . "\n";
}

echo "\n== search amount 15456188 nearby via rounding buckets not feasible\n";
echo "diff our-cache sales=" . (15456188.45 - 14872305.64) . " weight=" . (721043.12 - 694818.49) . "\n";

echo "\n== weight lines that could be 800\n";
$q = $pdo->query(
    "SELECT id, posting_date, m5, m4, m3, m2, m1, lonn, sales_amount, sales_weight, area_sqm
     FROM monthly_sales_combination_cache
     WHERE customer_no = 'CTR-00009'
       AND posting_date >= '2026-09-01' AND posting_date < '2026-10-01'
       AND ABS(sales_weight - 800) < 0.05"
);
foreach ($q as $r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n== lines with sales_weight > 0 and sales_amount = 0 count/sum\n";
$q = $pdo->query(
    "SELECT COUNT(*) c, ROUND(SUM(sales_weight),2) w, ROUND(SUM(area_sqm),2) area
     FROM monthly_sales_combination_cache
     WHERE customer_no = 'CTR-00009'
       AND posting_date >= '2026-09-01' AND posting_date < '2026-10-01'
       AND sales_amount = 0 AND sales_weight <> 0"
);
echo json_encode($q->fetch()) . "\n";
