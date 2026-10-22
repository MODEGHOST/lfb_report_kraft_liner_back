<?php
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';
require_once dirname(__DIR__, 2) . '/lib/db.php';
require_once dirname(__DIR__, 2) . '/lib/customer_month.php';

require_auth();

$year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
$month = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n');

if ($year < 2000 || $year > 2100) {
    $year = (int) date('Y');
}
if ($month < 1 || $month > 12) {
    $month = (int) date('n');
}

try {
    $pdo = report_pdo();
    $periodStmt = $pdo->query(
        'SELECT year, month, COUNT(*) AS row_count
         FROM kraft_monthly_targets
         GROUP BY year, month
         ORDER BY year DESC, month DESC'
    );
    $available = array();
    foreach ($periodStmt->fetchAll() as $period) {
        $available[] = array(
            'year' => (int) $period['year'],
            'month' => (int) $period['month'],
            'row_count' => (int) $period['row_count'],
        );
    }
    $items = customer_month_items($pdo, $year, $month);
} catch (Exception $e) {
    json_error('อ่าน Target ลูกค้าไม่สำเร็จ', 500);
}

json_ok(array(
    'year' => $year,
    'month' => $month,
    'period_label' => $month . '-' . $year,
    'target_column' => 'Target ' . $month . '-' . $year,
    'available_periods' => $available,
    'items' => $items,
));
