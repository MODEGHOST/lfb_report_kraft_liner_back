<?php
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';
require_once dirname(__DIR__, 2) . '/lib/db.php';

require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed', 405);
}

$pdo = report_pdo();
$rows = $pdo->query(
    'SELECT id, customer_code, customer_name, sale_name, cs_name, pallet_paper, rope, film
     FROM kraft_customer_options
     ORDER BY customer_name ASC, id ASC'
)->fetchAll();

$items = array();
$counts = array('pallet_paper' => 0, 'rope' => 0, 'film' => 0);
foreach ($rows as $row) {
    $paper = ((int) $row['pallet_paper'] === 1) ? 1 : 0;
    $rope = ((int) $row['rope'] === 1) ? 1 : 0;
    $film = ((int) $row['film'] === 1) ? 1 : 0;
    if ($paper === 1) {
        $counts['pallet_paper']++;
    }
    if ($rope === 1) {
        $counts['rope']++;
    }
    if ($film === 1) {
        $counts['film']++;
    }
    $items[] = array(
        'id' => (int) $row['id'],
        'customer_code' => $row['customer_code'],
        'customer_name' => $row['customer_name'],
        'sale' => $row['sale_name'],
        'cs' => $row['cs_name'],
        'pallet_paper' => $paper,
        'rope' => $rope,
        'film' => $film,
    );
}

json_ok(array(
    'items' => $items,
    'counts' => $counts,
));
