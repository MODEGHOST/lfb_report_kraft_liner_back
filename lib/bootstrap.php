<?php
/**
 * Shared bootstrap for Kraft Liner Report API (PHP 7.2+)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Credentials: true');

$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
$allowed = array('http://localhost:5174', 'http://127.0.0.1:5174', 'http://localhost:5173');
if (in_array($origin, $allowed, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    http_response_code(204);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_name('SCRSESSID');
    session_start();
}

function json_ok($data, $code = 200)
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function json_error($message, $code = 400)
{
    http_response_code($code);
    echo json_encode(array('message' => $message), JSON_UNESCAPED_UNICODE);
    exit;
}

function read_json_body()
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return array();
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : array();
}

function require_auth()
{
    if (empty($_SESSION['user'])) {
        json_error('กรุณาเข้าสู่ระบบ', 401);
    }
    return $_SESSION['user'];
}

function load_sample_data()
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $path = dirname(__DIR__) . '/data/sample.json';
    if (!is_file($path)) {
        json_error('ไม่พบไฟล์ข้อมูลตัวอย่าง', 500);
    }
    $decoded = json_decode(file_get_contents($path), true);
    if (!is_array($decoded)) {
        json_error('อ่านข้อมูลตัวอย่างไม่สำเร็จ', 500);
    }
    $cache = $decoded;
    return $cache;
}
