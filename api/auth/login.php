<?php
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';
require_once dirname(__DIR__, 2) . '/lib/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$body = read_json_body();
$code = isset($body['employeeCode'])
    ? $body['employeeCode']
    : (isset($body['username']) ? $body['username'] : '');
$password = isset($body['password']) ? $body['password'] : '';

$sessionUser = authenticate_shared_user($code, $password);
$_SESSION['user'] = $sessionUser;

json_ok(array(
    'message' => 'เข้าสู่ระบบสำเร็จ',
    'user' => $sessionUser,
));
