<?php
/**
 * เทียบข้อมูลที่มีอยู่แล้วกับต้นทาง แล้วดึงเฉพาะส่วนที่ยังไม่มีหรือค่าไม่ตรง
 * ยอดขาย: ดู id ใหม่ใน kraft_sales_seen แล้วหาว่าเดือนไหนมีแถวใหม่
 *         จากนั้นเขียนทับยอดทั้งเดือนจาก cache (ไม่บวกเพิ่มทับยอดเก่า — กันซ้ำเมื่อ cache ออก id ใหม่)
 * เป้าลูกค้า: จำเวลาอัปเดตล่าสุดในรายงาน แล้วอ่านเฉพาะแถวต้นทางที่ใหม่กว่านั้น ทีละ 100 แถว
 *
 * ถ้ายังไม่เคยดึงย้อนหลัง ตารางรหัสแถวจะว่าง และจะไม่ดึงยอดขายทั้งก้อนให้
 */
require dirname(__DIR__) . '/lib/sync_state.php';
require dirname(__DIR__) . '/lib/erp_pull.php';

date_default_timezone_set('Asia/Bangkok');

/** --auto = รอบตี 2 ไม่ยุ่งสถานะปุ่มดึงมือในหน้าเว็บ */
$GLOBALS['ERP_PULL_UI'] = !in_array('--auto', isset($argv) ? $argv : array(), true);

function track_pull_progress($pdo, $percent, $note)
{
    if (empty($GLOBALS['ERP_PULL_UI'])) {
        return;
    }
    erp_pull_set_progress($pdo, $percent, $note);
}

function track_pull_finish($pdo, $status, $message)
{
    if (empty($GLOBALS['ERP_PULL_UI'])) {
        return;
    }
    erp_pull_finish_if_running($pdo, $status, $message);
}

$src = require dirname(__DIR__) . '/config/source.local.php';
$app = require dirname(__DIR__) . '/config/app.php';
$erp = new PDO(
    'mysql:host=' . $src['host'] . ';port=' . (int) $src['port'] . ';dbname=' . $src['name'] . ';charset=utf8mb4',
    $src['user'],
    $src['pass'],
    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC)
);
$local = new PDO(
    sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $app['report_db']['host'],
        $app['report_db']['port'],
        $app['report_db']['name']
    ),
    $app['report_db']['user'],
    $app['report_db']['pass'],
    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC)
);

$pauseUs = 5000000;
$pageSize = 100;

sync_ensure_purchase_columns($local);
sync_ensure_seen_table($local);

try {
    erp_pull_ensure_table($local);
    track_pull_progress($local, 0, 'เริ่มดึงข้อมูล');
    $sales = import_new_sales($erp, $local, $pageSize, $pauseUs);
    $targets = import_changed_targets($erp, $local, $pageSize, $pauseUs);
    if (!empty($sales['skipped'])) {
        $salesNote = 'ข้ามยอดขาย เพราะยังไม่เคยดึงย้อนหลัง';
    } elseif (!empty($sales['months'])) {
        $salesNote = 'ยอดขายใหม่ ' . (int) $sales['rows'] . ' แถว · เขียนทับ '
            . (int) $sales['months'] . ' เดือน';
    } else {
        $salesNote = 'ยอดขายใหม่ 0 แถว';
    }
    $done = $salesNote . ' · เป้าลูกค้าที่อัปเดต ' . (int) $targets . ' แถว';
    track_pull_progress($local, 100, $done);
    track_pull_finish($local, 'success', $done);
    if (empty($GLOBALS['ERP_PULL_UI'])) {
        echo $done . PHP_EOL;
    }
} catch (Exception $e) {
    if (isset($local) && $local instanceof PDO) {
        track_pull_finish($local, 'failed', $e->getMessage());
    }
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}

function import_new_sales($erp, $local, $pageSize, $pauseUs)
{
    $seenCount = (int) $local->query('SELECT COUNT(*) FROM kraft_sales_seen')->fetchColumn();
    if ($seenCount === 0) {
        echo "ข้ามยอดขาย: ยังไม่มีรายการแถวที่นำเข้าแล้ว ให้ดึงย้อนหลังด้วย import_monthly_sales.php ก่อน\n";
        track_pull_progress($local, 50, 'ข้ามยอดขาย');
        return array('rows' => 0, 'months' => 0, 'skipped' => true);
    }

    $lastId = (int) $local->query('SELECT COALESCE(MAX(source_id), 0) FROM kraft_sales_seen')->fetchColumn();
    $salesTotal = (int) $erp->query(
        'SELECT COUNT(*) FROM monthly_sales_combination_cache WHERE id > ' . (int) $lastId
    )->fetchColumn();
    echo "ยอดขายในรายงานมีแถวต้นทางถึงรหัส {$lastId} แล้ว จะหาแถวที่ใหม่กว่านี้\n";
    if ($salesTotal === 0) {
        track_pull_progress($local, 50, 'ไม่มียอดขายใหม่');
        echo "ไม่มีแถวยอดขายที่ยังไม่ได้นำเข้า\n";
        return array('rows' => 0, 'months' => 0, 'skipped' => false);
    }

    $select = $erp->prepare(
        'SELECT id, posting_date
         FROM monthly_sales_combination_cache
         WHERE id > ?
         ORDER BY id ASC
         LIMIT ' . (int) $pageSize
    );

    $allIds = array();
    $months = array();
    $processed = 0;
    $cursor = $lastId;
    while (true) {
        $select->execute(array($cursor));
        $chunk = $select->fetchAll();
        if (!$chunk) {
            break;
        }
        foreach ($chunk as $row) {
            $allIds[] = (int) $row['id'];
            $stamp = strtotime($row['posting_date']);
            if ($stamp === false) {
                continue;
            }
            $months[date('Y-m', $stamp)] = true;
        }
        $cursor = (int) $chunk[count($chunk) - 1]['id'];
        $processed += count($chunk);
        $percent = (int) floor($processed * 20 / $salesTotal);
        track_pull_progress($local, $percent, 'กำลังตรวจเดือนที่ยอดขายเปลี่ยน');
        if (count($chunk) < $pageSize) {
            break;
        }
        usleep($pauseUs);
    }

    $monthList = array_keys($months);
    sort($monthList);
    $monthCount = count($monthList);
    echo 'พบแถวใหม่ ' . count($allIds) . ' แถว ครอบคลุม ' . $monthCount . " เดือน จะเขียนทับทั้งเดือน\n";

    $doneMonths = 0;
    foreach ($monthList as $key) {
        $parts = explode('-', $key);
        $year = (int) $parts[0];
        $month = (int) $parts[1];
        echo "  เขียนทับยอดขาย {$key}\n";
        rebuild_month_sales($erp, $local, $year, $month);
        $doneMonths++;
        $percent = 20 + (int) floor($doneMonths * 30 / max(1, $monthCount));
        track_pull_progress($local, $percent, 'กำลังเขียนทับยอดขาย ' . $key);
        if ($doneMonths < $monthCount) {
            usleep($pauseUs);
        }
    }

    sync_remember_sales_ids($local, $allIds);
    return array('rows' => count($allIds), 'months' => $monthCount, 'skipped' => false);
}

/**
 * รวมยอดทั้งเดือนจาก cache แล้วเขียนทับในรายงาน (ไม่บวกเพิ่ม)
 */
function rebuild_month_sales($erp, $local, $year, $month)
{
    $from = sprintf('%04d-%02d-01', $year, $month);
    $to = date('Y-m-d', strtotime($from . ' +1 month'));

    $coded = $erp->prepare(
        'SELECT customer_no,
                MAX(customer_name) AS customer_name,
                SUM(sales_amount) AS sales_amount,
                SUM(sales_weight) AS weight_kg,
                SUM(purchase_amount) AS purchase_amount,
                SUM(purchase_weight) AS purchase_weight
         FROM monthly_sales_combination_cache
         WHERE posting_date >= ? AND posting_date < ?
           AND customer_no IS NOT NULL AND customer_no <> \'\' AND customer_no <> \'-\'
         GROUP BY customer_no'
    );
    $coded->execute(array($from, $to));
    $salesByCode = array();
    while ($row = $coded->fetch()) {
        $salesByCode[$row['customer_no']] = array(
            'customer_no' => $row['customer_no'],
            'customer_name' => $row['customer_name'],
            'sales_amount' => (float) $row['sales_amount'],
            'weight_kg' => (float) $row['weight_kg'],
            'purchase_amount' => (float) $row['purchase_amount'],
            'purchase_weight' => (float) $row['purchase_weight'],
        );
    }

    $blankStmt = $erp->prepare(
        'SELECT customer_name, sales_amount, sales_weight, purchase_amount, purchase_weight
         FROM monthly_sales_combination_cache
         WHERE posting_date >= ? AND posting_date < ?
           AND (customer_no IS NULL OR customer_no = \'\' OR customer_no = \'-\')'
    );
    $blankStmt->execute(array($from, $to));
    $blankByName = array();
    while ($row = $blankStmt->fetch()) {
        add_blank_row($blankByName, $row);
    }

    $codeByName = local_code_by_name($local);
    $rows = array_values($salesByCode);
    $claimed = array();
    foreach ($rows as $index => $row) {
        $nameKey = norm_customer_name($row['customer_name']);
        $rows[$index]['name_key'] = $nameKey;
        if ($nameKey === '' || !isset($blankByName[$nameKey]) || isset($claimed[$nameKey])) {
            continue;
        }
        $rows[$index]['sales_amount'] += $blankByName[$nameKey]['sales'];
        $rows[$index]['weight_kg'] += $blankByName[$nameKey]['weight'];
        $claimed[$nameKey] = $row['customer_no'];
    }
    foreach ($blankByName as $nameKey => $blank) {
        if (isset($claimed[$nameKey])) {
            continue;
        }
        if (abs($blank['sales']) < 0.0000001 && abs($blank['weight']) < 0.0000001) {
            continue;
        }
        if (!isset($codeByName[$nameKey]) || count($codeByName[$nameKey]) !== 1) {
            fwrite(STDERR, "ข้ามชื่อที่ไม่มีรหัสเดียว: {$blank['name']}\n");
            continue;
        }
        $code = key($codeByName[$nameKey]);
        $rows[] = array(
            'customer_no' => $code,
            'customer_name' => $blank['name'],
            'sales_amount' => $blank['sales'],
            'weight_kg' => $blank['weight'],
            'purchase_amount' => $blank['amount'],
            'purchase_weight' => $blank['weight_purchase'],
            'name_key' => $nameKey,
        );
    }

    usort($rows, function ($a, $b) {
        $sa = (float) $a['sales_amount'];
        $sb = (float) $b['sales_amount'];
        if ($sa === $sb) {
            return strcmp($a['customer_no'], $b['customer_no']);
        }
        return ($sa > $sb) ? -1 : 1;
    });

    $update = $local->prepare(
        'UPDATE kraft_monthly_sales
         SET customer_name = ?, sort_order = ?, sales_amount = ?, weight_kg = ?, purchase_amount = ?, purchase_weight = ?, gap = ?, synced_at = NOW()
         WHERE year = ? AND month = ? AND customer_code = ?'
    );
    $insert = $local->prepare(
        'INSERT INTO kraft_monthly_sales
            (customer_code, customer_name, year, month, sort_order, sales_amount, weight_kg, purchase_amount, purchase_weight, gap, synced_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    $exists = $local->prepare(
        'SELECT id FROM kraft_monthly_sales WHERE year = ? AND month = ? AND customer_code = ?'
    );

    $local->beginTransaction();
    try {
        $codes = array();
        $rank = 0;
        foreach ($rows as $row) {
            if (isset($codes[$row['customer_no']])) {
                continue;
            }
            $rank++;
            $codes[$row['customer_no']] = true;
            $sales = round((float) $row['sales_amount'], 2);
            $weight = round((float) $row['weight_kg'], 2);
            $purchaseAmount = (float) $row['purchase_amount'];
            $purchaseWeight = (float) $row['purchase_weight'];
            $nameKey = isset($row['name_key']) ? $row['name_key'] : norm_customer_name($row['customer_name']);
            if ($purchaseWeight == 0.0 && $nameKey !== '' && isset($blankByName[$nameKey])) {
                $purchaseAmount = $blankByName[$nameKey]['amount'];
                $purchaseWeight = $blankByName[$nameKey]['weight_purchase'];
            }
            $gap = sales_gap($sales, $weight, $purchaseAmount, $purchaseWeight);
            $exists->execute(array($year, $month, $row['customer_no']));
            if ($exists->fetch()) {
                $update->execute(array(
                    $row['customer_name'],
                    $rank,
                    $sales,
                    $weight,
                    round($purchaseAmount, 2),
                    round($purchaseWeight, 2),
                    $gap,
                    $year,
                    $month,
                    $row['customer_no'],
                ));
            } else {
                $insert->execute(array(
                    $row['customer_no'],
                    $row['customer_name'],
                    $year,
                    $month,
                    $rank,
                    $sales,
                    $weight,
                    round($purchaseAmount, 2),
                    round($purchaseWeight, 2),
                    $gap,
                ));
            }
        }

        $codeList = array_keys($codes);
        if ($codeList) {
            $placeholders = implode(',', array_fill(0, count($codeList), '?'));
            $params = array_merge(array($year, $month), $codeList);
            $del = $local->prepare(
                "DELETE FROM kraft_monthly_sales
                 WHERE year = ? AND month = ? AND customer_code NOT IN ($placeholders)"
            );
            $del->execute($params);
        } else {
            $delAll = $local->prepare('DELETE FROM kraft_monthly_sales WHERE year = ? AND month = ?');
            $delAll->execute(array($year, $month));
        }
        $local->commit();
    } catch (Exception $e) {
        $local->rollBack();
        throw $e;
    }
}

function import_changed_targets($src, $dst, $pageSize, $pauseUs)
{
    $table = $dst->query("SHOW TABLES LIKE 'kraft_monthly_targets'")->fetch();
    if (!$table) {
        echo "ข้ามเป้าลูกค้า: ยังไม่มีข้อมูลในรายงาน ให้ดึงย้อนหลังด้วย import_customer_targets.php ก่อน\n";
        return 0;
    }

    $mark = $dst->query(
        'SELECT id, updated_at
         FROM kraft_monthly_targets
         WHERE updated_at IS NOT NULL
         ORDER BY updated_at DESC, id DESC
         LIMIT 1'
    )->fetch();
    $useWatermark = $mark && $mark['updated_at'] !== null && $mark['updated_at'] !== '';
    if ($useWatermark) {
        $sinceAt = $mark['updated_at'];
        $sinceId = (int) $mark['id'];
        echo "เป้าลูกค้าในรายงานใหม่ถึง {$sinceAt} รหัส {$sinceId} จะอ่านเฉพาะแถวที่ใหม่กว่านี้\n";
        $select = $src->prepare(
            'SELECT id, customer_name, year, month, company_target, sale_target, cs_target, created_at, updated_at
             FROM customer_monthly_targets
             WHERE updated_at > ? OR (updated_at = ? AND id > ?)
             ORDER BY updated_at ASC, id ASC
             LIMIT ' . (int) $pageSize
        );
        $countStmt = $src->prepare(
            'SELECT COUNT(*)
             FROM customer_monthly_targets
             WHERE updated_at > ? OR (updated_at = ? AND id > ?)'
        );
        $countStmt->execute(array($sinceAt, $sinceAt, $sinceId));
        $targetTotal = (int) $countStmt->fetchColumn();
    } else {
        echo "เป้าลูกค้ายังไม่มีเวลาอัปเดตในรายงาน จะไล่เทียบทั้งตาราง\n";
        $sinceAt = null;
        $sinceId = 0;
        $select = $src->prepare(
            'SELECT id, customer_name, year, month, company_target, sale_target, cs_target, created_at, updated_at
             FROM customer_monthly_targets
             WHERE id > ?
             ORDER BY id ASC
             LIMIT ' . (int) $pageSize
        );
        $targetTotal = (int) $src->query('SELECT COUNT(*) FROM customer_monthly_targets')->fetchColumn();
    }
    if ($targetTotal === 0) {
        echo "เป้าลูกค้าไม่มีแถวที่ใหม่กว่าที่รายงานมี\n";
        track_pull_progress($dst, 100, 'ไม่มีเป้าลูกค้าที่ต้องอัปเดต');
        return 0;
    }

    $upsert = $dst->prepare(
        'INSERT INTO kraft_monthly_targets
            (id, customer_name, year, month, company_target, sale_target, cs_target, created_at, updated_at)
         VALUES
            (:id, :customer_name, :year, :month, :company_target, :sale_target, :cs_target, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            customer_name = VALUES(customer_name),
            year = VALUES(year),
            month = VALUES(month),
            company_target = VALUES(company_target),
            sale_target = VALUES(sale_target),
            cs_target = VALUES(cs_target),
            created_at = VALUES(created_at),
            updated_at = VALUES(updated_at)'
    );

    $changed = 0;
    $scanned = 0;
    $page = 0;
    while (true) {
        if ($useWatermark) {
            $select->execute(array($sinceAt, $sinceAt, $sinceId));
        } else {
            $select->execute(array($sinceId));
        }
        $chunk = $select->fetchAll();
        if (!$chunk) {
            break;
        }
        $page++;
        $lastRow = $chunk[count($chunk) - 1];
        $sinceId = (int) $lastRow['id'];
        if ($useWatermark) {
            $sinceAt = $lastRow['updated_at'];
        }
        $ids = array();
        foreach ($chunk as $row) {
            $ids[] = (int) $row['id'];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $find = $dst->prepare(sprintf(
            'SELECT id, customer_name, year, month, company_target, sale_target, cs_target, created_at, updated_at
             FROM kraft_monthly_targets
             WHERE id IN (%s)',
            $placeholders
        ));
        $find->execute($ids);
        $have = array();
        while ($row = $find->fetch()) {
            $have[(int) $row['id']] = $row;
        }

        $pending = array();
        foreach ($chunk as $row) {
            $id = (int) $row['id'];
            if (!isset($have[$id]) || target_differs($have[$id], $row)) {
                $pending[] = $row;
            }
        }
        if ($pending) {
            $dst->beginTransaction();
            try {
                foreach ($pending as $row) {
                    $upsert->execute(array(
                        ':id' => $row['id'],
                        ':customer_name' => $row['customer_name'],
                        ':year' => $row['year'],
                        ':month' => $row['month'],
                        ':company_target' => $row['company_target'],
                        ':sale_target' => $row['sale_target'],
                        ':cs_target' => $row['cs_target'],
                        ':created_at' => $row['created_at'],
                        ':updated_at' => $row['updated_at'],
                    ));
                }
                $dst->commit();
            } catch (Exception $e) {
                $dst->rollBack();
                fwrite(STDERR, $e->getMessage() . "\n");
                throw $e;
            }
            $changed += count($pending);
            echo "เป้าลูกค้า ชุดที่ {$page} พบข้อมูลใหม่หรือไม่ตรง {$changed} แถว\n";
        }
        $scanned += count($chunk);
        $percent = $targetTotal > 0 ? 50 + (int) floor($scanned * 50 / $targetTotal) : 100;
        track_pull_progress($dst, $percent, 'กำลังดึงเป้าลูกค้า');
        if (count($chunk) < $pageSize) {
            break;
        }
        usleep($pauseUs);
    }

    echo "เป้าลูกค้าที่ต้องอัปเดต {$changed} แถว\n";
    return $changed;
}

function target_differs($local, $source)
{
    $fields = array('customer_name', 'year', 'month', 'company_target', 'sale_target', 'cs_target', 'created_at', 'updated_at');
    foreach ($fields as $field) {
        if ((string) $local[$field] !== (string) $source[$field]) {
            return true;
        }
    }
    return false;
}

function sales_gap($sales, $weight, $purchase, $purchaseWeight)
{
    if ($weight == 0.0 || $purchaseWeight == 0.0) {
        return null;
    }
    return round(($sales / $weight) - ($purchase / $purchaseWeight), 2);
}

function local_code_by_name($local)
{
    $map = array();
    $add = function ($code, $name) use (&$map) {
        $code = trim((string) $code);
        $key = norm_customer_name($name);
        if ($code === '' || $code === '-' || $key === '') {
            return;
        }
        if (!isset($map[$key])) {
            $map[$key] = array();
        }
        $map[$key][$code] = true;
    };
    foreach ($local->query('SELECT customer_code, customer_name FROM kraft_customer_options') as $row) {
        $add($row['customer_code'], $row['customer_name']);
    }
    foreach ($local->query('SELECT customer_code, customer_name FROM kraft_monthly_sales') as $row) {
        $add($row['customer_code'], $row['customer_name']);
    }
    return $map;
}

function norm_customer_name($name)
{
    $name = str_replace("\xE0\xB9\x8D\xE0\xB8\xB2", "\xE0\xB8\xB3", (string) $name);
    $name = preg_replace('/\s+/u', '', $name);
    return $name === null ? '' : $name;
}

function add_blank_row(&$map, $row)
{
    $key = norm_customer_name($row['customer_name']);
    if ($key === '') {
        return;
    }
    if (!isset($map[$key])) {
        $map[$key] = array(
            'name' => $row['customer_name'],
            'sales' => 0.0,
            'weight' => 0.0,
            'amount' => 0.0,
            'weight_purchase' => 0.0,
        );
    }
    $map[$key]['sales'] += (float) $row['sales_amount'];
    $map[$key]['weight'] += (float) $row['sales_weight'];
    $map[$key]['amount'] += (float) $row['purchase_amount'];
    $map[$key]['weight_purchase'] += (float) $row['purchase_weight'];
}
