<?php
/**
 * App config — ใช้บัญชีกลาง shared_auth แบบเดียวกับ CMS / LFB Center / Rollscan
 */

return array(
    'db' => array(
        'host' => getenv('DB_HOST') ? getenv('DB_HOST') : '127.0.0.1',
        'port' => getenv('DB_PORT') ? (int) getenv('DB_PORT') : 3306,
        'name' => getenv('DB_NAME') ? getenv('DB_NAME') : 'shared_auth',
        'user' => getenv('DB_USER') ? getenv('DB_USER') : 'root',
        'pass' => getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '',
    ),
    /** ข้อมูลรายงานบนเครื่องนี้ (phpMyAdmin 127.0.0.1) */
    'report_db' => array(
        'host' => getenv('REPORT_DB_HOST') ? getenv('REPORT_DB_HOST') : '127.0.0.1',
        'port' => getenv('REPORT_DB_PORT') ? (int) getenv('REPORT_DB_PORT') : 3306,
        'name' => getenv('REPORT_DB_NAME') ? getenv('REPORT_DB_NAME') : 'report_kraft_liner',
        'user' => getenv('REPORT_DB_USER') ? getenv('REPORT_DB_USER') : 'root',
        'pass' => getenv('REPORT_DB_PASSWORD') !== false ? getenv('REPORT_DB_PASSWORD') : '',
    ),
    // ต้องตรงกับ systems_registry.code = SALES_REPORT
    'system_code' => getenv('SYSTEM_CODE') ? getenv('SYSTEM_CODE') : 'SALES_REPORT',
    // ถ้า true = ต้องมีสิทธิ์ใน user_system_access ถึงเข้าได้ (เหมือน Rollscan)
    'require_system_access' => true,
);
