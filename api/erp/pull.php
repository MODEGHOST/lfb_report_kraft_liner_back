<?php
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';
require_once dirname(__DIR__, 2) . '/lib/db.php';
require_once dirname(__DIR__, 2) . '/lib/erp_pull.php';

$user = require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

try {
    $pdo = report_pdo();
    erp_pull_ensure_table($pdo);
    $blocked = erp_pull_claim(
        $pdo,
        isset($user['username']) ? (string) $user['username'] : '',
        isset($user['display_name']) ? (string) $user['display_name'] : ''
    );
} catch (Exception $e) {
    json_error('เริ่มดึงข้อมูลไม่สำเร็จ', 500);
}

if ($blocked !== null) {
    json_error($blocked, 409);
}

$php = PHP_BINARY;
if (!$php || !is_file($php)) {
    $php = 'C:\\xampp\\php\\php.exe';
}
$root = dirname(__DIR__, 2);
$logDir = $root . '/logs';
if (!is_dir($logDir) && !mkdir($logDir, 0777, true) && !is_dir($logDir)) {
    erp_pull_finish_if_running($pdo, 'failed', 'สร้างโฟลเดอร์ log ไม่ได้');
    json_error('สร้างโฟลเดอร์ log ไม่ได้', 500);
}
$script = $root . '/scripts/import_daily_delta.php';
$log = $logDir . '/erp-pull.log';
$cmd = 'cmd /C start "" /B ' . escapeshellarg($php) . ' ' . escapeshellarg($script)
    . ' >> ' . escapeshellarg($log) . ' 2>&1';
$handle = popen($cmd, 'r');
if ($handle === false) {
    erp_pull_finish_if_running($pdo, 'failed', 'เปิดงานดึงข้อมูลไม่สำเร็จ');
    json_error('เปิดงานดึงข้อมูลไม่สำเร็จ', 500);
}
pclose($handle);

$row = erp_pull_row($pdo, erp_pull_today());
json_ok(erp_pull_payload($row));
