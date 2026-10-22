<?php
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';
require_once dirname(__DIR__, 2) . '/lib/db.php';
require_once dirname(__DIR__, 2) . '/lib/customer_month.php';
require_once dirname(__DIR__, 2) . '/lib/actual_weights.php';

require_auth();

$year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
$month = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n');

if ($year < 2000 || $year > 2100) {
    $year = (int) date('Y');
}
if ($month < 1 || $month > 12) {
    $month = (int) date('n');
}

try {
    $pdo = report_pdo();
    $customers = customer_month_items($pdo, $year, $month);
    $items = service_summary_from_customers($customers);
} catch (Exception $e) {
    json_error('อ่านสรุปบริการไม่สำเร็จ', 500);
}

$key = sprintf('%04d-%02d', $year, $month);
$items = merge_summary_actuals($key, $items);

json_ok(array(
    'year' => $year,
    'month' => $month,
    'period_label' => $month . '-' . $year,
    'items' => $items,
));
