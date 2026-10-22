<?php
$p = new PDO('mysql:host=127.0.0.1;dbname=report_kraft_liner;charset=utf8mb4','root','',[
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
echo "options\n";
echo json_encode($p->query('SELECT COUNT(*) n, SUM(use_pallet=1) use_pallet, SUM(pallet_paper=1) pallet_paper, SUM(rope=1) rope, SUM(film=1) film FROM kraft_customer_options')->fetch()) . "\n";
echo "aug join\n";
echo json_encode($p->query(
    "SELECT COUNT(*) customers,
            SUM(o.customer_code IS NULL) no_opt,
            SUM(o.use_pallet=1) use_pallet,
            SUM(o.pallet_paper=1) pallet_paper,
            SUM(o.rope=1) rope,
            SUM(o.film=1) film,
            SUM(o.use_pallet=1 AND o.pallet_paper=1) both_paper,
            SUM((o.pallet_paper=1) + (o.rope=1) + (o.film=1) > 1) multi
     FROM kraft_monthly_sales s
     LEFT JOIN kraft_customer_options o ON o.customer_code = s.customer_code
     WHERE s.year=2026 AND s.month=8"
)->fetch()) . "\n";
echo "aug sales by flag\n";
foreach (array('use_pallet','pallet_paper','rope','film') as $f) {
    $row = $p->query(
        "SELECT COUNT(*) n, ROUND(SUM(s.sales_amount),2) sales, ROUND(SUM(s.weight_kg),2) w
         FROM kraft_monthly_sales s
         JOIN kraft_customer_options o ON o.customer_code = s.customer_code
         WHERE s.year=2026 AND s.month=8 AND o.$f=1"
    )->fetch();
    echo $f . ' ' . json_encode($row) . "\n";
}
