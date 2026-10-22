<?php
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';
require_once dirname(__DIR__, 2) . '/lib/db.php';
require_once dirname(__DIR__, 2) . '/lib/customer_month.php';
require_once dirname(__DIR__, 2) . '/lib/actual_weights.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$user = require_auth();
$body = read_json_body();

$year = isset($body['year']) ? (int) $body['year'] : 0;
$month = isset($body['month']) ? (int) $body['month'] : 0;
$serviceId = isset($body['service_id']) ? (string) $body['service_id'] : '';
$hasActual = array_key_exists('actual_weight', $body);

if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
    json_error('เดือนไม่ถูกต้อง', 422);
}
if ($serviceId === '') {
    json_error('ไม่พบรายการบริการ', 422);
}
if (!$hasActual) {
    json_error('กรุณาระบุน้ำหนักใช้จริง', 422);
}

$raw = $body['actual_weight'];
if ($raw === null || $raw === '') {
    $actual = null;
} else {
    if (!is_numeric($raw)) {
        json_error('น้ำหนักใช้จริงต้องเป็นตัวเลข', 422);
    }
    $actual = (float) $raw;
    if ($actual < 0) {
        json_error('น้ำหนักใช้จริงต้องไม่ติดลบ', 422);
    }
}

$key = sprintf('%04d-%02d', $year, $month);
try {
    $customers = customer_month_items(report_pdo(), $year, $month);
    $monthly = service_summary_from_customers($customers);
} catch (Exception $e) {
    json_error('อ่านสรุปบริการไม่สำเร็จ', 500);
}
$salesWeight = 0;
$found = false;
foreach ($monthly as $row) {
    if ((string) $row['id'] === $serviceId) {
        $found = true;
        $salesWeight = isset($row['sales_weight']) ? (float) $row['sales_weight'] : 0;
        break;
    }
}
if (!$found) {
    json_error('ไม่พบรายการในเดือนนี้', 404);
}

$store = load_actual_weights();
if (!isset($store[$key]) || !is_array($store[$key])) {
    $store[$key] = array();
}
if ($actual === null) {
    unset($store[$key][$serviceId]);
} else {
    $store[$key][$serviceId] = $actual;
}
$store[$key]['_meta'] = array(
    'updated_by' => isset($user['username']) ? $user['username'] : null,
    'updated_at' => date('c'),
);
save_actual_weights($store);

$pct = $actual === null ? null : calc_pct($actual, $salesWeight);

json_ok(array(
    'message' => 'บันทึกน้ำหนักใช้จริงแล้ว',
    'year' => $year,
    'month' => $month,
    'service_id' => $serviceId,
    'actual_weight' => $actual,
    'sales_weight' => $salesWeight,
    'pct' => $pct,
));
