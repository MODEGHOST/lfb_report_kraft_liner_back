<?php
$p = new PDO('mysql:host=127.0.0.1;dbname=report_kraft_liner;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
echo json_encode($p->query("SELECT COUNT(*) rows_n, COUNT(DISTINCT customer_code) codes, SUM(customer_code IS NULL OR customer_code='') blank_code, SUM(use_pallet=1) use_pallet, SUM(pallet_paper=1) pallet_paper, SUM(rope=1) rope, SUM(film=1) film FROM kraft_customer_options")->fetch()), "\n";
