<?php
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';
require_once dirname(__DIR__, 2) . '/lib/actual_weights.php';

require_auth();

$baseYear = isset($_GET['base_year']) ? (int) $_GET['base_year'] : (int) date('Y');
$baseMonth = isset($_GET['base_month']) ? (int) $_GET['base_month'] : (int) date('n');

$compareYear = isset($_GET['compare_year']) ? (int) $_GET['compare_year'] : 0;
$compareMonth = isset($_GET['compare_month']) ? (int) $_GET['compare_month'] : 0;

if ($baseYear < 2000 || $baseYear > 2100) {
    $baseYear = (int) date('Y');
}
if ($baseMonth < 1 || $baseMonth > 12) {
    $baseMonth = (int) date('n');
}

// Default compare month to previous month if not provided
if ($compareYear === 0 || $compareMonth === 0) {
    $compareMonth = $baseMonth - 1;
    $compareYear = $baseYear;
    if ($compareMonth < 1) {
        $compareMonth = 12;
        $compareYear -= 1;
    }
}

$baseKey = sprintf('%04d-%02d', $baseYear, $baseMonth);
$compareKey = sprintf('%04d-%02d', $compareYear, $compareMonth);

$data = load_sample_data();
$monthlySummary = isset($data['monthly_summary']) && is_array($data['monthly_summary'])
    ? $data['monthly_summary']
    : array();
$monthlyCustomers = isset($data['monthly_customers']) && is_array($data['monthly_customers'])
    ? $data['monthly_customers']
    : array();

function extract_period_metrics($periodKey, $monthlySummary, $monthlyCustomers)
{
    $servicesRaw = isset($monthlySummary[$periodKey]) && is_array($monthlySummary[$periodKey])
        ? $monthlySummary[$periodKey]
        : array();
    $services = merge_summary_actuals($periodKey, $servicesRaw);

    $customers = isset($monthlyCustomers[$periodKey]) && is_array($monthlyCustomers[$periodKey])
        ? $monthlyCustomers[$periodKey]
        : array();

    $totalSales = 0.0;
    $totalWeight = 0.0;
    $totalActual = 0.0;
    $hasActual = false;

    $byService = array();
    foreach ($services as $row) {
        $sales = isset($row['sales_amount']) ? (float) $row['sales_amount'] : 0.0;
        $weight = isset($row['sales_weight']) ? (float) $row['sales_weight'] : 0.0;
        $actual = array_key_exists('actual_weight', $row) && $row['actual_weight'] !== null && $row['actual_weight'] !== ''
            ? (float) $row['actual_weight']
            : null;
        $cust = isset($row['customer_count']) ? (int) $row['customer_count'] : 0;

        $totalSales += $sales;
        $totalWeight += $weight;
        if ($actual !== null) {
            $totalActual += $actual;
            $hasActual = true;
        }

        $byService[isset($row['service_name']) ? $row['service_name'] : '-'] = array(
            'id' => isset($row['id']) ? $row['id'] : null,
            'name' => isset($row['service_name']) ? $row['service_name'] : '-',
            'customer_count' => $cust,
            'sales_amount' => $sales,
            'sales_weight' => $weight,
            'actual_weight' => $actual,
            'pct' => isset($row['pct']) ? $row['pct'] : null,
        );
    }

    $totalTarget = 0.0;
    $customerSales = 0.0;
    $bySaleMap = array();
    $customerMap = array();

    foreach ($customers as $row) {
        $code = isset($row['customer_code']) ? (string) $row['customer_code'] : '';
        $name = isset($row['customer_name']) ? (string) $row['customer_name'] : '-';
        $sales = isset($row['sales_amount']) ? (float) $row['sales_amount'] : 0.0;
        $target = isset($row['target']) ? (float) $row['target'] : 0.0;
        $weight = isset($row['weight_kg']) ? (float) $row['weight_kg'] : 0.0;
        $saleName = isset($row['sale']) && $row['sale'] !== '' ? (string) $row['sale'] : 'ไม่ระบุ';
        $gap = isset($row['gap']) ? $row['gap'] : null;

        $totalTarget += $target;
        $customerSales += $sales;

        if (!isset($bySaleMap[$saleName])) {
            $bySaleMap[$saleName] = array(
                'name' => $saleName,
                'sales' => 0.0,
                'customers' => 0,
                'weight' => 0.0,
            );
        }
        $bySaleMap[$saleName]['sales'] += $sales;
        $bySaleMap[$saleName]['customers'] += 1;
        $bySaleMap[$saleName]['weight'] += $weight;

        if ($code !== '') {
            $customerMap[$code] = array(
                'code' => $code,
                'name' => $name,
                'sale' => $saleName,
                'sales' => $sales,
                'weight' => $weight,
                'target' => $target,
                'gap' => $gap,
                'use_pallet' => !empty($row['use_pallet']) ? 1 : 0,
                'pallet_paper' => !empty($row['pallet_paper']) ? 1 : 0,
                'rope' => !empty($row['rope']) ? 1 : 0,
                'film' => !empty($row['film']) ? 1 : 0,
            );
        }
    }

    $actualPct = ($hasActual && $totalWeight > 0)
        ? round(($totalActual / $totalWeight) * 100, 2)
        : null;

    $targetPct = $totalTarget > 0 ? round(($customerSales / $totalTarget) * 100, 1) : null;

    return array(
        'total_sales' => $totalSales,
        'total_weight' => $totalWeight,
        'total_actual_weight' => $hasActual ? $totalActual : null,
        'actual_pct' => $actualPct,
        'customer_count' => count($customers),
        'total_target' => $totalTarget,
        'target_pct' => $targetPct,
        'by_service' => $byService,
        'by_sale' => $bySaleMap,
        'customers' => $customerMap,
    );
}

$base = extract_period_metrics($baseKey, $monthlySummary, $monthlyCustomers);
$compare = extract_period_metrics($compareKey, $monthlySummary, $monthlyCustomers);

function calc_delta($baseVal, $compVal)
{
    $diff = $baseVal - $compVal;
    $pct = $compVal > 0 ? round(($diff / $compVal) * 100, 2) : ($baseVal > 0 ? 100.0 : 0.0);
    return array('diff' => $diff, 'pct' => $pct);
}

$salesDelta = calc_delta($base['total_sales'], $compare['total_sales']);
$weightDelta = calc_delta($base['total_weight'], $compare['total_weight']);
$custDelta = calc_delta($base['customer_count'], $compare['customer_count']);

// Compare by Service
$allServiceNames = array_unique(array_merge(array_keys($base['by_service']), array_keys($compare['by_service'])));
$serviceComparison = array();
foreach ($allServiceNames as $sName) {
    $b = isset($base['by_service'][$sName]) ? $base['by_service'][$sName] : array('sales_amount' => 0, 'sales_weight' => 0, 'customer_count' => 0, 'actual_weight' => null);
    $c = isset($compare['by_service'][$sName]) ? $compare['by_service'][$sName] : array('sales_amount' => 0, 'sales_weight' => 0, 'customer_count' => 0, 'actual_weight' => null);

    $sSalesDelta = calc_delta($b['sales_amount'], $c['sales_amount']);
    $sWeightDelta = calc_delta($b['sales_weight'], $c['sales_weight']);

    $serviceComparison[] = array(
        'name' => $sName,
        'base_sales' => $b['sales_amount'],
        'compare_sales' => $c['sales_amount'],
        'sales_diff' => $sSalesDelta['diff'],
        'sales_growth_pct' => $sSalesDelta['pct'],
        'base_weight' => $b['sales_weight'],
        'compare_weight' => $c['sales_weight'],
        'weight_diff' => $sWeightDelta['diff'],
        'weight_growth_pct' => $sWeightDelta['pct'],
        'base_customers' => $b['customer_count'],
        'compare_customers' => $c['customer_count'],
    );
}

// Compare by Sale
$allSaleNames = array_unique(array_merge(array_keys($base['by_sale']), array_keys($compare['by_sale'])));
$saleComparison = array();
foreach ($allSaleNames as $saleName) {
    $b = isset($base['by_sale'][$saleName]) ? $base['by_sale'][$saleName] : array('sales' => 0, 'weight' => 0, 'customers' => 0);
    $c = isset($compare['by_sale'][$saleName]) ? $compare['by_sale'][$saleName] : array('sales' => 0, 'weight' => 0, 'customers' => 0);

    $saleSalesDelta = calc_delta($b['sales'], $c['sales']);

    $saleComparison[] = array(
        'name' => $saleName,
        'base_sales' => $b['sales'],
        'compare_sales' => $c['sales'],
        'sales_diff' => $saleSalesDelta['diff'],
        'sales_growth_pct' => $saleSalesDelta['pct'],
        'base_customers' => $b['customers'],
        'compare_customers' => $c['customers'],
    );
}
usort($saleComparison, function ($a, $b) {
    return $b['base_sales'] <=> $a['base_sales'];
});

// Compare Customers
$allCustCodes = array_unique(array_merge(array_keys($base['customers']), array_keys($compare['customers'])));
$customerChanges = array();

foreach ($allCustCodes as $code) {
    $b = isset($base['customers'][$code]) ? $base['customers'][$code] : null;
    $c = isset($compare['customers'][$code]) ? $compare['customers'][$code] : null;

    $name = $b ? $b['name'] : ($c ? $c['name'] : '-');
    $sale = $b ? $b['sale'] : ($c ? $c['sale'] : '-');
    $bSales = $b ? $b['sales'] : 0.0;
    $cSales = $c ? $c['sales'] : 0.0;
    $bWeight = $b ? $b['weight'] : 0.0;
    $cWeight = $c ? $c['weight'] : 0.0;

    $diff = $bSales - $cSales;
    $pct = $cSales > 0 ? round(($diff / $cSales) * 100, 2) : ($bSales > 0 ? 100.0 : 0.0);

    $status = 'same';
    if (!$c && $b) {
        $status = 'new';
    } elseif ($c && !$b) {
        $status = 'lost';
    } elseif ($diff > 0) {
        $status = 'growth';
    } elseif ($diff < 0) {
        $status = 'drop';
    }

    $customerChanges[] = array(
        'code' => $code,
        'name' => $name,
        'sale' => $sale,
        'base_sales' => $bSales,
        'compare_sales' => $cSales,
        'sales_diff' => $diff,
        'growth_pct' => $pct,
        'base_weight' => $bWeight,
        'compare_weight' => $cWeight,
        'status' => $status,
        'base_gap' => $b ? $b['gap'] : null,
        'compare_gap' => $c ? $c['gap'] : null,
    );
}

// Top Growth & Drop lists
$growthList = array_filter($customerChanges, function ($item) {
    return $item['sales_diff'] > 0;
});
usort($growthList, function ($a, $b) {
    return $b['sales_diff'] <=> $a['sales_diff'];
});

$dropList = array_filter($customerChanges, function ($item) {
    return $item['sales_diff'] < 0;
});
usort($dropList, function ($a, $b) {
    return $a['sales_diff'] <=> $b['sales_diff'];
});

// Available months in dataset for selection dropdowns
$availableMonths = array_unique(array_merge(array_keys($monthlySummary), array_keys($monthlyCustomers)));
sort($availableMonths);

json_ok(array(
    'base_key' => $baseKey,
    'base_label' => $baseMonth . '-' . $baseYear,
    'compare_key' => $compareKey,
    'compare_label' => $compareMonth . '-' . $compareYear,
    'available_months' => $availableMonths,
    'summary' => array(
        'base_sales' => $base['total_sales'],
        'compare_sales' => $compare['total_sales'],
        'sales_diff' => $salesDelta['diff'],
        'sales_growth_pct' => $salesDelta['pct'],
        'base_weight' => $base['total_weight'],
        'compare_weight' => $compare['total_weight'],
        'weight_diff' => $weightDelta['diff'],
        'weight_growth_pct' => $weightDelta['pct'],
        'base_customers' => $base['customer_count'],
        'compare_customers' => $compare['customer_count'],
        'customer_diff' => $custDelta['diff'],
        'customer_growth_pct' => $custDelta['pct'],
        'base_actual_pct' => $base['actual_pct'],
        'compare_actual_pct' => $compare['actual_pct'],
    ),
    'service_comparison' => $serviceComparison,
    'sale_comparison' => $saleComparison,
    'customer_changes' => $customerChanges,
    'top_growth_customers' => array_values(array_slice($growthList, 0, 10)),
    'top_drop_customers' => array_values(array_slice($dropList, 0, 10)),
));
