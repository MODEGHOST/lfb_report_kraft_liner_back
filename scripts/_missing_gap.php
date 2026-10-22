<?php
$p = new PDO('mysql:host=127.0.0.1;dbname=report_kraft_liner;charset=utf8mb4', 'root', '');
foreach ($p->query("SELECT customer_code, customer_name, sales_amount, weight_kg FROM kraft_monthly_sales WHERE year=2026 AND month=7 AND gap IS NULL") as $r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
}
echo "jul avg " . json_encode($p->query("SELECT ROUND(SUM(gap*weight_kg)/SUM(weight_kg),2) g, SUM(gap IS NULL) n FROM kraft_monthly_sales WHERE year=2026 AND month=7")->fetch(PDO::FETCH_ASSOC)) . "\n";
echo "aug avg " . json_encode($p->query("SELECT ROUND(SUM(gap*weight_kg)/SUM(weight_kg),2) g, SUM(gap IS NULL) n FROM kraft_monthly_sales WHERE year=2026 AND month=8")->fetch(PDO::FETCH_ASSOC)) . "\n";
