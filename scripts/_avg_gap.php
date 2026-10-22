<?php
$p = new PDO('mysql:host=127.0.0.1;dbname=report_kraft_liner;charset=utf8mb4', 'root', '');
$r = $p->query(
    "SELECT ROUND(SUM(gap * weight_kg) / SUM(weight_kg), 2) AS avg_gap
     FROM kraft_monthly_sales
     WHERE year = 2026 AND month = 9 AND gap IS NOT NULL AND weight_kg > 0"
)->fetch(PDO::FETCH_ASSOC);
echo json_encode($r), PHP_EOL;
