<?php
/**
 * ดึงอัตโนมัติตี 2 — logic ข้อมูลเดียวกับปุ่มเว็บ แต่แยกสถานะคนละชุด
 * ไม่ล็อกปุ่ม "ดึงเอง" และไม่ถูกบล็อกถ้าวันนี้มีคนดึงมือแล้ว
 *
 *   C:\xampp\php\php.exe backend/scripts/sync_daily.php
 */
date_default_timezone_set('Asia/Bangkok');

require dirname(__DIR__) . '/lib/db.php';
require dirname(__DIR__) . '/lib/erp_pull.php';

$root = dirname(__DIR__);
$logDir = $root . '/logs';
if (!is_dir($logDir) && !mkdir($logDir, 0777, true) && !is_dir($logDir)) {
    fwrite(STDERR, "สร้างโฟลเดอร์ log ไม่ได้\n");
    exit(1);
}
$logFile = $logDir . '/sync.log';

function sync_log($logFile, $message)
{
    $line = date('Y-m-d H:i:s') . ' ' . $message . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND);
    echo $line;
}

function php_bin()
{
    $candidates = array(
        'C:\\xampp\\php\\php.exe',
        PHP_BINARY,
    );
    foreach ($candidates as $path) {
        if ($path && is_file($path)) {
            return $path;
        }
    }
    return 'php';
}

function auto_log_start(PDO $pdo)
{
    erp_pull_ensure_table($pdo);
    $today = erp_pull_today();
    $stmt = $pdo->prepare(
        'INSERT INTO kraft_erp_pull_log
            (pull_date, started_at, username, display_name, status, message)
         VALUES (?, NOW(), ?, ?, ?, ?)'
    );
    $stmt->execute(array($today, 'system', 'Auto ตี 2', 'running', 'รอบอัตโนมัติ'));
    return (int) $pdo->lastInsertId();
}

function auto_log_finish(PDO $pdo, $logId, $status, $message)
{
    $stmt = $pdo->prepare(
        'UPDATE kraft_erp_pull_log
         SET status = ?, message = ?, finished_at = NOW()
         WHERE id = ?'
    );
    $stmt->execute(array($status, $message, (int) $logId));
}

$started = microtime(true);
sync_log($logFile, '===== เริ่มดึงอัตโนมัติ (แยกจากดึงมือ) =====');

$logId = 0;
try {
    $pdo = report_pdo();
    $logId = auto_log_start($pdo);
} catch (Exception $e) {
    sync_log($logFile, 'เริ่มงานไม่สำเร็จ: ' . $e->getMessage());
    exit(1);
}

$php = php_bin();
$script = __DIR__ . '/import_daily_delta.php';
$cmd = escapeshellarg($php) . ' ' . escapeshellarg($script) . ' --auto';
sync_log($logFile, 'เริ่ม ' . $cmd);
$output = array();
$code = 0;
exec($cmd . ' 2>&1', $output, $code);
$lastUseful = '';
foreach ($output as $line) {
    sync_log($logFile, '  ' . $line);
    $trim = trim($line);
    if ($trim !== '') {
        $lastUseful = $trim;
    }
}

$message = $lastUseful !== '' ? $lastUseful : ('exit=' . $code);
$status = $code === 0 ? 'success' : 'failed';
try {
    auto_log_finish($pdo, $logId, $status, $message);
} catch (Exception $e) {
    sync_log($logFile, 'บันทึกประวัติอัตโนมัติไม่ได้: ' . $e->getMessage());
}

if ($code !== 0) {
    sync_log($logFile, 'ล้มเหลว exit=' . $code);
    exit($code);
}

$seconds = round(microtime(true) - $started, 1);
sync_log($logFile, '===== ดึงอัตโนมัติเสร็จ ใช้เวลา ' . $seconds . ' วินาที =====');
