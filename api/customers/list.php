<?php
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

require_auth();

$year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
$month = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n');
if ($year < 2000 || $year > 2100) {
    $year = (int) date('Y');
}
if ($month < 1 || $month > 12) {
    $month = (int) date('n');
}

$key = sprintf('%04d-%02d', $year, $month);
$data = load_sample_data();
$monthly = isset($data['monthly_customers']) ? $data['monthly_customers'] : array();
$items = isset($monthly[$key]) && is_array($monthly[$key]) ? $monthly[$key] : array();

json_ok(array('items' => $items));
