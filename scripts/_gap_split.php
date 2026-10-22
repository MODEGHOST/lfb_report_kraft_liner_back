<?php
$src = require dirname(__DIR__) . '/config/source.local.php';
$erp = new PDO(
    'mysql:host=' . $src['host'] . ';port=' . (int) $src['port'] . ';dbname=' . $src['name'] . ';charset=utf8mb4',
    $src['user'],
    $src['pass'],
    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC)
);

foreach (array('2026-07', '2026-08', '2026-09') as $ym) {
    $from = $ym . '-01';
    $to = date('Y-m-d', strtotime($from . ' +1 month'));
    $row = $erp->prepare(
        "SELECT
            SUM(CASE WHEN customer_no IS NULL OR customer_no IN ('', '-') THEN 1 ELSE 0 END) blank_lines,
            SUM(CASE WHEN customer_no IS NOT NULL AND customer_no NOT IN ('', '-') THEN 1 ELSE 0 END) coded_lines,
            ROUND(SUM(CASE WHEN customer_no IS NULL OR customer_no IN ('', '-') THEN purchase_weight ELSE 0 END), 2) blank_pw,
            ROUND(SUM(CASE WHEN customer_no IS NOT NULL AND customer_no NOT IN ('', '-') THEN purchase_weight ELSE 0 END), 2) coded_pw,
            COUNT(DISTINCT CASE WHEN customer_no IS NOT NULL AND customer_no NOT IN ('', '-') AND purchase_weight <> 0 THEN customer_no END) coded_with_pw
         FROM monthly_sales_combination_cache
         WHERE posting_date >= ? AND posting_date < ?"
    );
    $row->execute(array($from, $to));
    $data = $row->fetch();
    $data['ym'] = $ym;
    echo json_encode($data) . "\n";
}
