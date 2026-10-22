<?php
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';
require_once dirname(__DIR__, 2) . '/lib/db.php';

require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$body = read_json_body();
$id = isset($body['id']) ? (int) $body['id'] : 0;
$code = isset($body['customer_code']) ? trim((string) $body['customer_code']) : '';
$name = isset($body['customer_name']) ? trim((string) $body['customer_name']) : '';
$sale = person_name($body, 'sale');
$cs = person_name($body, 'cs');

if ($name === '') {
    json_error('กรุณาระบุชื่อลูกค้า', 422);
}
if (mb_strlen($name) > 255) {
    json_error('ชื่อลูกค้ายาวเกินกำหนด', 422);
}
if (strlen($code) > 30) {
    json_error('รหัสลูกค้ายาวเกินกำหนด', 422);
}

$paper = flag_bit($body, 'pallet_paper');
$rope = flag_bit($body, 'rope');
$film = flag_bit($body, 'film');

$pdo = report_pdo();

if ($code !== '') {
    $dup = $pdo->prepare(
        'SELECT id, customer_code FROM kraft_customer_options WHERE customer_code = ? AND id <> ?'
    );
    $dup->execute(array($code, $id));
    $taken = $dup->fetch();
    if ($taken) {
        $current = null;
        if ($id > 0) {
            $own = $pdo->prepare('SELECT customer_code FROM kraft_customer_options WHERE id = ?');
            $own->execute(array($id));
            $current = $own->fetch();
        }
        $sameCode = $current && trim((string) $current['customer_code']) === $code;
        if (!$sameCode) {
            json_error('รหัสลูกค้านี้มีในมาสเตอร์แล้ว', 422);
        }
    }
}

if ($id > 0) {
    $update = $pdo->prepare(
        'UPDATE kraft_customer_options
         SET customer_code = ?, customer_name = ?, sale_name = ?, cs_name = ?, pallet_paper = ?, rope = ?, film = ?, loaded_at = NOW()
         WHERE id = ?'
    );
    $update->execute(array($code === '' ? null : $code, $name, $sale, $cs, $paper, $rope, $film, $id));
    if ($update->rowCount() === 0) {
        $exists = $pdo->prepare('SELECT id FROM kraft_customer_options WHERE id = ?');
        $exists->execute(array($id));
        if (!$exists->fetch()) {
            json_error('ไม่พบรายการที่ต้องการแก้ไข', 404);
        }
    }
} else {
    $insert = $pdo->prepare(
        'INSERT INTO kraft_customer_options
            (customer_code, customer_name, sale_name, cs_name, use_pallet, pallet_paper, rope, film, loaded_at)
         VALUES (?, ?, ?, ?, NULL, ?, ?, ?, NOW())'
    );
    $insert->execute(array($code === '' ? null : $code, $name, $sale, $cs, $paper, $rope, $film));
    $id = (int) $pdo->lastInsertId();
}

json_ok(array(
    'item' => array(
        'id' => $id,
        'customer_code' => $code === '' ? null : $code,
        'customer_name' => $name,
        'sale' => $sale,
        'cs' => $cs,
        'pallet_paper' => $paper,
        'rope' => $rope,
        'film' => $film,
    ),
));

function person_name($body, $key)
{
    $value = isset($body[$key]) ? trim((string) $body[$key]) : '';
    if (mb_strlen($value) > 80) {
        json_error('ชื่อ Sale หรือ CS ยาวเกินกำหนด', 422);
    }
    return $value === '' ? null : $value;
}

function flag_bit($body, $key)
{
    if (!isset($body[$key]) || $body[$key] === '' || !is_numeric($body[$key])) {
        json_error('เลือกได้เฉพาะ 0 ไม่ได้ใช้ หรือ 1 ใช้', 422);
    }
    $value = (int) $body[$key];
    if ($value !== 0 && $value !== 1) {
        json_error('เลือกได้เฉพาะ 0 ไม่ได้ใช้ หรือ 1 ใช้', 422);
    }
    return $value;
}
