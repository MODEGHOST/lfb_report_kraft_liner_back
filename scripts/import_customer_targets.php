<?php
/**
 * ดึง customer_monthly_targets จาก leefibre
 * มาเก็บใน report_kraft_liner.kraft_monthly_targets
 * อ่านทีละเดือน และภายในเดือนอ่านทีละ 400 แถว เว้น 0.5 วินาทีระหว่างชุด
 *
 *   C:\xampp\php\php.exe backend/scripts/import_customer_targets.php
 */

function pdo_open($db)
{
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $db['host'],
        $db['port'],
        $db['name']
    );
    return new PDO($dsn, $db['user'], $db['pass'], array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ));
}

$sourceFile = dirname(__DIR__) . '/config/source.local.php';
$app = require dirname(__DIR__) . '/config/app.php';
if (!is_file($sourceFile)) {
    fwrite(STDERR, "ไม่พบ backend/config/source.local.php\n");
    exit(1);
}
$source = require $sourceFile;

echo "เชื่อมต้นทาง {$source['host']}/{$source['name']} ...\n";
$src = pdo_open($source);
echo "เชื่อมปลายทาง 127.0.0.1/{$app['report_db']['name']} ...\n";
$dst = pdo_open($app['report_db']);

$createRow = $src->query('SHOW CREATE TABLE customer_monthly_targets')->fetch();
if (!$createRow || empty($createRow['Create Table'])) {
    fwrite(STDERR, "อ่านโครงสร้างตารางต้นทางไม่สำเร็จ\n");
    exit(1);
}
$createSql = $createRow['Create Table'];
$createSql = preg_replace(
    '/CREATE TABLE `customer_monthly_targets`/',
    'CREATE TABLE `kraft_monthly_targets`',
    $createSql,
    1
);
if ($createSql === null || strpos($createSql, 'kraft_monthly_targets') === false) {
    fwrite(STDERR, "เปลี่ยนชื่อตารางปลายทางไม่สำเร็จ\n");
    exit(1);
}
$exists = $dst->query("SHOW TABLES LIKE 'kraft_monthly_targets'")->fetch();
if (!$exists) {
    $dst->exec($createSql);
    echo "สร้างตาราง kraft_monthly_targets ใน {$app['report_db']['name']} แล้ว\n";
}

$periods = $src->query(
    'SELECT year, month, COUNT(*) AS row_count
     FROM customer_monthly_targets
     GROUP BY year, month
     ORDER BY year ASC, month ASC'
)->fetchAll();

if (!$periods) {
    echo "ต้นทางไม่มีข้อมูล\n";
    exit(0);
}

echo 'พบ ' . count($periods) . " เดือน จะดึงทีละเดือน ทีละชุด\n";
$pageSize = 400;
$pauseUs = 500000;

$select = $src->prepare(
    'SELECT id, customer_name, year, month, company_target, sale_target, cs_target, created_at, updated_at
     FROM customer_monthly_targets
     WHERE year = ? AND month = ? AND id > ?
     ORDER BY id ASC
     LIMIT ' . (int) $pageSize
);
$deleteMonth = $dst->prepare(
    'DELETE FROM kraft_monthly_targets WHERE year = ? AND month = ?'
);
$insert = $dst->prepare(
    'INSERT INTO kraft_monthly_targets
        (id, customer_name, year, month, company_target, sale_target, cs_target, created_at, updated_at)
     VALUES
        (:id, :customer_name, :year, :month, :company_target, :sale_target, :cs_target, :created_at, :updated_at)'
);

$total = 0;
foreach ($periods as $index => $period) {
    $year = (int) $period['year'];
    $month = (int) $period['month'];
    $label = sprintf('%04d-%02d', $year, $month);
    echo sprintf("[%d/%d] ดึง %s ...\n", $index + 1, count($periods), $label);

    $rows = array();
    $lastId = 0;
    $page = 0;
    while (true) {
        $select->execute(array($year, $month, $lastId));
        $chunk = $select->fetchAll();
        if (!$chunk) {
            break;
        }
        $page++;
        foreach ($chunk as $row) {
            $rows[] = $row;
            $lastId = (int) $row['id'];
        }
        echo "  ชุดที่ {$page} อ่าน " . count($chunk) . " แถว\n";
        if (count($chunk) < $pageSize) {
            break;
        }
        usleep($pauseUs);
    }

    $dst->beginTransaction();
    try {
        $deleteMonth->execute(array($year, $month));
        foreach ($rows as $row) {
            $insert->execute(array(
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
        fwrite(STDERR, "เดือน {$label} ไม่สำเร็จ: " . $e->getMessage() . "\n");
        exit(1);
    }

    $count = count($rows);
    $total += $count;
    echo "  เก็บแล้ว {$count} แถว (รวม {$total})\n";
    if ($index + 1 < count($periods)) {
        sleep(1);
    }
}

$localCount = (int) $dst->query('SELECT COUNT(*) FROM kraft_monthly_targets')->fetchColumn();
echo "เสร็จสิ้น ปลายทางมี {$localCount} แถว\n";
