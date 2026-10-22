<?php
/**
 * Local config — ปรับให้ตรง XAMPP / MySQL ของเครื่อง
 * Database กลาง: shared_auth (บัญชีเดียวกับ LFB Center / IPMS / CMS)
 */
return array(
    'db' => array(
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'shared_auth',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ),
    /** รหัสระบบใน systems_registry */
    'system_code' => 'SALES_REPORT',
    /**
     * true = ต้องมีสิทธิ์ใน user_system_access ถึงเข้าได้
     * false = login ได้ทุกบัญชี active ใน Center_user_lfb (ช่วง dev)
     */
    'require_system_access' => true,
);
