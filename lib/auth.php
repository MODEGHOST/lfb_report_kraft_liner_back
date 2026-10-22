<?php
/**
 * Shared Auth — login จาก Center_user_lfb แบบ CMS / LFB Center
 * + ตรวจสิทธิ์ระบบจาก user_system_access แบบ Rollscan
 */

require_once __DIR__ . '/db.php';

function normalize_login_id($value)
{
    return strtolower(trim((string) $value));
}

function display_name_from_center($row)
{
    $first = isset($row['first_name']) ? trim((string) $row['first_name']) : '';
    $last = isset($row['last_name']) ? trim((string) $row['last_name']) : '';
    $name = trim($first . ' ' . $last);
    if ($name !== '') {
        return $name;
    }
    return isset($row['username']) ? (string) $row['username'] : '';
}

function has_system_access(PDO $pdo, $userId, $systemCode)
{
    $stmt = $pdo->prepare(
        'SELECT 1
         FROM user_system_access usa
         JOIN systems_registry s ON s.id = usa.system_id
         WHERE usa.user_id = ?
           AND s.code = ?
           AND s.is_active = 1
         LIMIT 1'
    );
    $stmt->execute(array((int) $userId, $systemCode));
    return (bool) $stmt->fetchColumn();
}

/**
 * @return array session user
 */
function authenticate_shared_user($employeeCode, $password)
{
    $config = require dirname(__DIR__) . '/config/app.php';
    $loginId = normalize_login_id($employeeCode);
    $password = (string) $password;

    if ($loginId === '' || $password === '') {
        json_error('กรุณากรอกรหัสพนักงานและรหัสผ่าน', 422);
    }

    $pdo = db_pdo();
    $stmt = $pdo->prepare(
        'SELECT id, first_name, last_name, username, email,
                department, division, status, portal_role, password_hash
         FROM Center_user_lfb
         WHERE username = ?
         LIMIT 1'
    );
    $stmt->execute(array($loginId));
    $row = $stmt->fetch();

    if (!$row || empty($row['password_hash'])) {
        json_error('รหัสพนักงานหรือรหัสผ่านไม่ถูกต้อง', 401);
    }

    if (!password_verify($password, $row['password_hash'])) {
        json_error('รหัสพนักงานหรือรหัสผ่านไม่ถูกต้อง', 401);
    }

    $status = isset($row['status']) ? (string) $row['status'] : '';
    if ($status === 'suspended') {
        json_error('บัญชีถูกระงับการใช้งาน กรุณาติดต่อ Admin', 403);
    }
    if ($status === 'pending') {
        json_error('บัญชียังไม่ได้รับการอนุมัติ กรุณาติดต่อ Admin', 403);
    }
    if ($status !== '' && $status !== 'active') {
        json_error('บัญชีไม่พร้อมใช้งาน กรุณาติดต่อ Admin', 403);
    }

    $systemCode = $config['system_code'];
    if (!empty($config['require_system_access']) && !has_system_access($pdo, (int) $row['id'], $systemCode)) {
        json_error('ไม่มีสิทธิ์เข้าถึงระบบ Kraft Liner Report', 403);
    }

    return array(
        'id' => (int) $row['id'],
        'employee_code' => (string) $row['username'],
        'username' => (string) $row['username'],
        'display_name' => display_name_from_center($row),
        'email' => isset($row['email']) ? $row['email'] : null,
        'department' => isset($row['department']) ? $row['department'] : null,
        'division' => isset($row['division']) ? $row['division'] : null,
        'role' => isset($row['portal_role']) ? $row['portal_role'] : 'user',
        'system_code' => $systemCode,
    );
}

/**
 * โหลด user ใหม่จาก DB สำหรับ /me (ตรวจสถานะ/สิทธิ์ซ้ำ)
 */
function refresh_shared_user($userId)
{
    $config = require dirname(__DIR__) . '/config/app.php';
    $pdo = db_pdo();

    $stmt = $pdo->prepare(
        'SELECT id, first_name, last_name, username, email,
                department, division, status, portal_role
         FROM Center_user_lfb
         WHERE id = ?
         LIMIT 1'
    );
    $stmt->execute(array((int) $userId));
    $row = $stmt->fetch();

    if (!$row) {
        json_error('กรุณาเข้าสู่ระบบ', 401);
    }

    $status = isset($row['status']) ? (string) $row['status'] : '';
    if ($status === 'suspended' || $status === 'pending') {
        json_error('บัญชีถูกระงับ', 401);
    }

    $systemCode = $config['system_code'];
    if (!empty($config['require_system_access']) && !has_system_access($pdo, (int) $row['id'], $systemCode)) {
        json_error('ไม่มีสิทธิ์เข้าถึงระบบนี้', 403);
    }

    return array(
        'id' => (int) $row['id'],
        'employee_code' => (string) $row['username'],
        'username' => (string) $row['username'],
        'display_name' => display_name_from_center($row),
        'email' => isset($row['email']) ? $row['email'] : null,
        'department' => isset($row['department']) ? $row['department'] : null,
        'division' => isset($row['division']) ? $row['division'] : null,
        'role' => isset($row['portal_role']) ? $row['portal_role'] : 'user',
        'system_code' => $systemCode,
    );
}
