<?php
$p = new PDO(
    'mysql:host=127.0.0.1;dbname=report_kraft_liner;charset=utf8mb4',
    'root',
    '',
    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC)
);
echo "sales\n";
foreach ($p->query(
    "SELECT month, COUNT(*) customers,
            ROUND(SUM(sales_amount),2) sales,
            ROUND(SUM(weight_kg),2) weight,
            SUM(gap IS NULL) no_gap
     FROM kraft_monthly_sales WHERE year=2026
     GROUP BY month ORDER BY month"
) as $r) {
    echo json_encode($r) . "\n";
}
echo "targets\n";
foreach ($p->query(
    "SELECT month, COUNT(*) customers FROM kraft_monthly_targets WHERE year=2026 GROUP BY month ORDER BY month"
) as $r) {
    echo json_encode($r) . "\n";
}
