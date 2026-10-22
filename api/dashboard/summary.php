<?php
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';
require_once dirname(__DIR__, 2) . '/lib/db.php';
require_once dirname(__DIR__, 2) . '/lib/customer_month.php';
require_once dirname(__DIR__, 2) . '/lib/actual_weights.php';
require_once dirname(__DIR__, 2) . '/lib/dashboard_metrics.php';

require_auth();

$year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
$month = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n');
$scope = isset($_GET['scope']) ? strtolower(trim((string) $_GET['scope'])) : 'month';
if ($scope !== 'year') {
    $scope = 'month';
}

if ($year < 2000 || $year > 2100) {
    $year = (int) date('Y');
}
if ($month < 1 || $month > 12) {
    $month = (int) date('n');
}

try {
    $payload = dashboard_payload(report_pdo(), $year, $month, $scope);
} catch (Exception $e) {
    json_error('อ่านข้อมูล Dashboard ไม่สำเร็จ', 500);
}

json_ok($payload);
