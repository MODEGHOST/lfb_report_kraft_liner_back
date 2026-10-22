<?php
/**
 * Dev server helper (เหมือนแนว php artisan serve)
 * ใช้:  C:\xampp\php\php.exe serve.php
 * หรือถ้าใส่ PHP ใน PATH แล้ว:  php serve.php
 */

$host = '127.0.0.1';
$port = 8080;
$root = __DIR__;

echo "Kraft Liner Report API\n";
echo "Listening on http://{$host}:{$port}\n";
echo "Document root: {$root}\n";
echo "Press Ctrl+C to stop\n\n";

passthru(escapeshellarg(PHP_BINARY) . ' -S ' . escapeshellarg("{$host}:{$port}") . ' -t ' . escapeshellarg($root));
