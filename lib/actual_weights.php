<?php
/**
 * เก็บน้ำหนักใช้จริงที่ user กรอก (แยกจาก sample) แล้วคำนวณ % อัตโนมัติ
 * % = (น้ำหนักใช้จริง / น้ำหนักขาย) * 100
 */

function actual_weights_path()
{
    return dirname(__DIR__) . '/data/actual_weights.json';
}

function load_actual_weights()
{
    $path = actual_weights_path();
    if (!is_file($path)) {
        return array();
    }
    $decoded = json_decode(file_get_contents($path), true);
    return is_array($decoded) ? $decoded : array();
}

function save_actual_weights($data)
{
    $path = actual_weights_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        json_error('บันทึกข้อมูลไม่สำเร็จ', 500);
    }
    if (file_put_contents($path, $json) === false) {
        json_error('เขียนไฟล์น้ำหนักใช้จริงไม่สำเร็จ', 500);
    }
}

function calc_pct($actualWeight, $salesWeight)
{
    $sales = (float) $salesWeight;
    if ($sales <= 0) {
        return null;
    }
    return round(((float) $actualWeight / $sales) * 100, 2);
}

/**
 * รวมค่าที่กรอกเข้าไปในแถวสรุป + คำนวณ %
 */
function merge_summary_actuals($periodKey, $items)
{
    $store = load_actual_weights();
    $period = isset($store[$periodKey]) && is_array($store[$periodKey]) ? $store[$periodKey] : array();
    $out = array();

    foreach ($items as $row) {
        $id = isset($row['id']) ? (string) $row['id'] : '';
        $salesWeight = isset($row['sales_weight']) ? (float) $row['sales_weight'] : 0;

        if ($id !== '' && array_key_exists($id, $period) && $period[$id] !== null && $period[$id] !== '') {
            $actual = (float) $period[$id];
        } elseif (isset($row['actual_weight']) && $row['actual_weight'] !== null && $row['actual_weight'] !== '') {
            $actual = (float) $row['actual_weight'];
        } else {
            $actual = null;
        }

        $row['actual_weight'] = $actual;
        $row['pct'] = $actual === null ? null : calc_pct($actual, $salesWeight);
        $out[] = $row;
    }

    return $out;
}
