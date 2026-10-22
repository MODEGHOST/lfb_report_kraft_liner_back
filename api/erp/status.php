<?php
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';
require_once dirname(__DIR__, 2) . '/lib/db.php';
require_once dirname(__DIR__, 2) . '/lib/erp_pull.php';

require_auth();

try {
    $pdo = report_pdo();
    erp_pull_ensure_table($pdo);
    erp_pull_expire_stale($pdo);
    $row = erp_pull_row($pdo, erp_pull_today());
    $latestSuccess = erp_pull_latest_success($pdo);
    $history = erp_pull_history($pdo);
} catch (Exception $e) {
    json_error('อ่านสถานะการดึงข้อมูลไม่สำเร็จ', 500);
}

json_ok(erp_pull_payload($row, $history, $latestSuccess));
