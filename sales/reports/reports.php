<?php
include "../../config.php";
$canViewProfitTracker = (bool) appUserCan('profit_tracker');
$canViewOrders = (bool) appUserCan('orders');
$canViewCustomers = (bool) appUserCan('customers');
$canViewProducts = (bool) appUserCan('products');
$canViewInventory = (bool) appUserCan('inventory');
$canAdjustStock = (bool) appUserCan('stock_management', 'edit');

$searchParam = $_GET['search'] ?? '';
$search_query = is_string($searchParam) ? trim($searchParam) : '';
$filterError = is_string($searchParam) ? '' : 'Enter a valid text search term.';
$channelParam = $_GET['channel'] ?? '';
$channelFilter = is_string($channelParam) ? strtolower(trim($channelParam)) : '';
if (!is_string($channelParam) || !in_array($channelFilter, ['', 'pos', 'online'], true)) {
    $filterError = 'Choose a valid sales channel.';
    $channelFilter = '';
}
$paymentMethodParam = $_GET['payment_method'] ?? '';
$paymentMethodFilter = is_string($paymentMethodParam) ? trim($paymentMethodParam) : '';
if (!is_string($paymentMethodParam) || strlen($paymentMethodFilter) > 50) {
    $filterError = 'Choose a valid payment method.';
    $paymentMethodFilter = '';
}
$parseDate = static function (string $value): ?string {
    if ($value === '') {
        return '';
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d') !== $value) {
        return null;
    }

    return $value;
};

$startDateParam = $_GET['start_date'] ?? '';
$endDateParam = $_GET['end_date'] ?? '';
$rawStartDate = is_string($startDateParam) ? trim($startDateParam) : '';
$rawEndDate = is_string($endDateParam) ? trim($endDateParam) : '';
$start_date = $parseDate($rawStartDate);
$end_date = $parseDate($rawEndDate);
if (!is_string($startDateParam) || !is_string($endDateParam) || $start_date === null || $end_date === null) {
    $filterError = 'Enter valid dates to filter this report.';
    $start_date = $start_date ?? '';
    $end_date = $end_date ?? '';
}
if ($start_date !== '' && $end_date !== '' && $start_date > $end_date) {
    $filterError = 'The start date must be on or before the end date.';
    $start_date = '';
    $end_date = '';
}

$today = new DateTimeImmutable('today');
$datePresets = [
    'Today' => [$today, $today],
    '7D' => [$today->modify('-6 days'), $today],
    '30D' => [$today->modify('-29 days'), $today],
    'Month' => [$today->modify('first day of this month'), $today],
];
$dateFilterFor = static function (string $column) use ($start_date, $end_date): array {
    $clauses = [];
    $params = [];
    $types = '';
    if ($start_date !== '') {
        $clauses[] = "{$column} >= ?";
        $params[] = $start_date . ' 00:00:00';
        $types .= 's';
    }
    if ($end_date !== '') {
        $clauses[] = "{$column} < DATE_ADD(?, INTERVAL 1 DAY)";
        $params[] = $end_date . ' 00:00:00';
        $types .= 's';
    }

    return [
        'sql' => $clauses ? ' AND ' . implode(' AND ', $clauses) : '',
        'params' => $params,
        'types' => $types,
    ];
};
$orderDateFilter = $dateFilterFor('o.created_at');
$returnDateFilter = $dateFilterFor('r.created_at');

$executePrepared = static function (string $sql, string $types = '', array $params = []) use ($conn): mysqli_stmt {
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('Unable to prepare sales report query: ' . $conn->error);
    }

    if ($types !== '') {
        $bindArguments = [$types];
        foreach ($params as $index => $value) {
            $params[$index] = $value;
            $bindArguments[] = &$params[$index];
        }
        if (!call_user_func_array([$stmt, 'bind_param'], $bindArguments)) {
            $error = $stmt->error;
            $stmt->close();
            throw new RuntimeException('Unable to bind sales report query parameters: ' . $error);
        }
    }

    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Unable to execute sales report query: ' . $error);
    }

    return $stmt;
};

$searchClauses = [];
$searchParams = [];
$searchTypes = '';
foreach (preg_split('/\s+/', $search_query, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
    $searchClauses[] = "(o.order_no LIKE ? OR c.name LIKE ? OR o.payment_method LIKE ? OR SOUNDEX(c.name) = SOUNDEX(?))";
    $searchTerm = '%' . $word . '%';
    array_push($searchParams, $searchTerm, $searchTerm, $searchTerm, $word);
    $searchTypes .= 'ssss';
}
$searchSql = $searchClauses ? ' AND (' . implode(' OR ', $searchClauses) . ')' : '';
$searchSqlForCustomer = str_replace('c.name', 'cust.name', $searchSql);
$channelSql = match ($channelFilter) {
    'pos' => " AND o.shipping_address LIKE 'Walk-in Counter POS%'",
    'online' => " AND (o.shipping_address NOT LIKE 'Walk-in Counter POS%' OR o.shipping_address IS NULL)",
    default => '',
};
$paymentMethodSql = $paymentMethodFilter !== '' ? ' AND o.payment_method = ?' : '';
$orderParams = array_merge($orderDateFilter['params'], $searchParams);
$orderTypes = $orderDateFilter['types'] . $searchTypes;
$refundParams = array_merge($returnDateFilter['params'], $searchParams);
$refundTypes = $returnDateFilter['types'] . $searchTypes;
if ($paymentMethodFilter !== '') {
    $orderParams[] = $paymentMethodFilter;
    $orderTypes .= 's';
    $refundParams[] = $paymentMethodFilter;
    $refundTypes .= 's';
}
$ordersFrom = "
    FROM orders o
    LEFT JOIN customers c ON c.id = o.customer_id";
$ordersWhere = " WHERE o.is_deleted = 0{$orderDateFilter['sql']}{$searchSql}{$channelSql}{$paymentMethodSql}";
$ordersFromWhere = $ordersFrom . $ordersWhere;
$refundsFromWhere = "
    FROM `returns` r
    INNER JOIN orders o ON o.id = r.order_id
    LEFT JOIN customers c ON c.id = o.customer_id
    WHERE r.status = 'Refunded' AND o.is_deleted = 0{$returnDateFilter['sql']}{$searchSql}{$channelSql}{$paymentMethodSql}";
$ordersSelect = "SELECT o.id, o.order_no, o.total, o.created_at, o.payment_method, o.shipping_address,
    o.customer_id, COALESCE(c.name, 'Walk-in Customer') AS customer_name";

if (($_GET['export'] ?? '') === 'csv') {
    if (!$canViewOrders) {
        http_response_code(403);
        exit('You do not have permission to export orders.');
    }

    $exportStmt = $executePrepared(
        "SELECT o.id, o.order_no, o.total, o.created_at, o.payment_method, o.shipping_address, o.customer_id,
                COALESCE(c.name, 'Walk-in Customer') AS customer_name,
                COALESCE(refund_totals.total_refunds, 0) AS refund_amount
         {$ordersFrom}
         LEFT JOIN (
             SELECT r.order_id, SUM(r.refund_amount) AS total_refunds
             FROM `returns` r
             WHERE r.status = 'Refunded'{$returnDateFilter['sql']}
             GROUP BY r.order_id
         ) refund_totals ON refund_totals.order_id = o.id
         {$ordersWhere}
         ORDER BY o.created_at DESC, o.id DESC",
        $returnDateFilter['types'] . $orderTypes,
        array_merge($returnDateFilter['params'], $orderParams)
    );
    $exportRows = $exportStmt->get_result();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="sales-report-' . $today->format('Y-m-d') . '.csv"');
    echo "\xEF\xBB\xBF";
    $output = fopen('php://output', 'wb');
    if ($output === false) {
        $exportStmt->close();
        throw new RuntimeException('Unable to create the sales report CSV stream.');
    }

    $headers = ['Order #', 'Customer', 'Channel', 'Payment Method', 'Total', 'Refunds', 'Net Total', 'Date / Time'];
    if (fputcsv($output, $headers, ',', '"', '\\') === false) {
        $exportStmt->close();
        throw new RuntimeException('Unable to write the sales report CSV header.');
    }
    while ($order = $exportRows->fetch_assoc()) {
        $channel = str_starts_with((string) ($order['shipping_address'] ?? ''), 'Walk-in Counter POS') ? 'POS' : 'Online';
        $row = [
            $order['order_no'] ?? '',
            $order['customer_name'] ?? '',
            $channel,
            $order['payment_method'] ?? 'Cash',
            $order['total'] ?? '0.00',
            $order['refund_amount'] ?? '0.00',
            (float) ($order['total'] ?? 0) - (float) ($order['refund_amount'] ?? 0),
            $order['created_at'] ?? '',
        ];
        foreach ($row as &$cell) {
            $cell = (string) $cell;
            if (preg_match('/^[\s]*[=+\-@]/u', $cell) === 1) {
                $cell = "'" . $cell;
            }
        }
        unset($cell);
        if (fputcsv($output, $row, ',', '"', '\\') === false) {
            fclose($output);
            $exportStmt->close();
            throw new RuntimeException('Unable to write the sales report CSV data.');
        }
    }
    fclose($output);
    $exportStmt->close();
    exit;
}

$statsStmt = $executePrepared(
    "SELECT COUNT(*) AS total_orders, COALESCE(SUM(o.total), 0) AS gross_sales
     {$ordersFromWhere}",
    $orderTypes,
    $orderParams
);
$stats = $statsStmt->get_result()->fetch_assoc();
$statsStmt->close();

$refundStmt = $executePrepared(
    "SELECT COALESCE(SUM(r.refund_amount), 0) AS total_refunds
     {$refundsFromWhere}",
    $refundTypes,
    $refundParams
);
$refundStats = $refundStmt->get_result()->fetch_assoc();
$refundStmt->close();
$grossSales = (float) ($stats['gross_sales'] ?? 0);
$totalRefunds = (float) ($refundStats['total_refunds'] ?? 0);
$netSales = $grossSales - $totalRefunds;
$refundImpactPercent = $grossSales > 0 ? ($totalRefunds / $grossSales) * 100 : null;
$totalOrders = (int) ($stats['total_orders'] ?? 0);
$averageOrderValue = $totalOrders > 0 ? $grossSales / $totalOrders : 0;

$trendDays = $start_date !== '' && $end_date !== ''
    ? (int) (new DateTimeImmutable($start_date))->diff(new DateTimeImmutable($end_date))->days + 1
    : null;
$trendGranularity = $trendDays === null || $trendDays > 180
    ? 'month'
    : ($trendDays > 31 ? 'week' : 'day');
$orderTrendDate = match ($trendGranularity) {
    'day' => 'DATE(o.created_at)',
    'week' => 'DATE_SUB(DATE(o.created_at), INTERVAL WEEKDAY(o.created_at) DAY)',
    default => "DATE_FORMAT(o.created_at, '%Y-%m-01')",
};
$refundTrendDate = match ($trendGranularity) {
    'day' => 'DATE(r.created_at)',
    'week' => 'DATE_SUB(DATE(r.created_at), INTERVAL WEEKDAY(r.created_at) DAY)',
    default => "DATE_FORMAT(r.created_at, '%Y-%m-01')",
};
$trendStmt = $executePrepared(
    "SELECT trend_period, SUM(order_count) AS order_count,
            COALESCE(SUM(gross_sales), 0) AS gross_sales, COALESCE(SUM(refunds), 0) AS refunds
     FROM (
         SELECT {$orderTrendDate} AS trend_period, COUNT(*) AS order_count,
                COALESCE(SUM(o.total), 0) AS gross_sales, 0 AS refunds
         {$ordersFromWhere}
         GROUP BY trend_period
         UNION ALL
         SELECT {$refundTrendDate} AS trend_period, 0 AS order_count,
                0 AS gross_sales, COALESCE(SUM(r.refund_amount), 0) AS refunds
         {$refundsFromWhere}
         GROUP BY trend_period
     ) AS sales_trend
     GROUP BY trend_period
     ORDER BY trend_period DESC",
    $orderTypes . $refundTypes,
    array_merge($orderParams, $refundParams)
);
$salesTrend = $trendStmt->get_result();

$paymentStmt = $executePrepared(
    "SELECT o.payment_method, COUNT(*) AS order_count, COALESCE(SUM(o.total), 0) AS revenue
     {$ordersFromWhere}
     GROUP BY o.payment_method ORDER BY revenue DESC",
    $orderTypes,
    $orderParams
);
$payment_res = $paymentStmt->get_result();

$productsStmt = $executePrepared(
    "SELECT oi.product_id, oi.product_name, SUM(oi.quantity) AS units_sold, COALESCE(SUM(oi.line_total), 0) AS sales
     FROM order_items oi
     INNER JOIN orders o ON o.id = oi.order_id
     LEFT JOIN customers c ON c.id = o.customer_id
     WHERE o.is_deleted = 0{$orderDateFilter['sql']}{$searchSql}{$channelSql}{$paymentMethodSql}
     GROUP BY oi.product_id, oi.product_name
     ORDER BY units_sold DESC, sales DESC
     LIMIT 5",
    $orderTypes,
    $orderParams
);
$topProducts = $productsStmt->get_result();

$categoriesStmt = $executePrepared(
    "SELECT c.id AS category_id, COALESCE(c.name, 'Uncategorized') AS category_name,
            SUM(oi.quantity) AS units_sold, COALESCE(SUM(oi.line_total), 0) AS sales
     FROM order_items oi
     INNER JOIN orders o ON o.id = oi.order_id
     LEFT JOIN products p ON p.id = oi.product_id
     LEFT JOIN categories c ON c.id = p.category_id
     LEFT JOIN customers cust ON cust.id = o.customer_id
     WHERE o.is_deleted = 0{$orderDateFilter['sql']}{$searchSqlForCustomer}{$channelSql}{$paymentMethodSql}
     GROUP BY c.id, c.name
     ORDER BY units_sold DESC, sales DESC
     LIMIT 5",
    $orderTypes,
    $orderParams
);
$topCategories = $categoriesStmt->get_result();

$channelStmt = $executePrepared(
    "SELECT
        SUM(CASE WHEN o.shipping_address LIKE 'Walk-in Counter POS%' THEN 1 ELSE 0 END) AS pos_orders,
        COALESCE(SUM(CASE WHEN o.shipping_address LIKE 'Walk-in Counter POS%' THEN o.total ELSE 0 END), 0) AS pos_sales,
        SUM(CASE WHEN o.shipping_address NOT LIKE 'Walk-in Counter POS%' OR o.shipping_address IS NULL THEN 1 ELSE 0 END) AS online_orders,
        COALESCE(SUM(CASE WHEN o.shipping_address NOT LIKE 'Walk-in Counter POS%' OR o.shipping_address IS NULL THEN o.total ELSE 0 END), 0) AS online_sales
     {$ordersFromWhere}",
    $orderTypes,
    $orderParams
);
$channelStats = $channelStmt->get_result()->fetch_assoc();
$channelStmt->close();

$low_stock_res = $conn->query("SELECT p.*, c.name AS category_name
    FROM products p LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.stock <= 5 AND p.is_deleted = 0 ORDER BY p.stock ASC");
if ($low_stock_res === false) {
    throw new RuntimeException('Unable to load low stock report: ' . $conn->error);
}

$orderCountStmt = $executePrepared(
    'SELECT COUNT(*) AS matching_orders' . $ordersFromWhere,
    $orderTypes,
    $orderParams
);
$matchingOrderCount = (int) ($orderCountStmt->get_result()->fetch_assoc()['matching_orders'] ?? 0);
$orderCountStmt->close();
$ordersStmt = $executePrepared(
    $ordersSelect . $ordersFromWhere . ' ORDER BY o.created_at DESC, o.id DESC LIMIT 20',
    $orderTypes,
    $orderParams
);
$recent_orders = $ordersStmt->get_result();

$activeReportQuery = array_filter([
    'start_date' => $start_date,
    'end_date' => $end_date,
    'search' => $search_query,
    'channel' => $channelFilter,
    'payment_method' => $paymentMethodFilter,
], static function ($value): bool {
    return $value !== '';
});
$reportFilterUrl = static function (array $additionalFilters) use ($activeReportQuery): string {
    return 'reports.php?' . http_build_query(array_merge($activeReportQuery, $additionalFilters));
};
$presetUrls = [];
foreach ($datePresets as $presetName => [$presetStart, $presetEnd]) {
    $presetQuery = array_merge($activeReportQuery, [
        'start_date' => $presetStart->format('Y-m-d'),
        'end_date' => $presetEnd->format('Y-m-d'),
    ]);
    $presetUrls[$presetName] = 'reports.php?' . http_build_query($presetQuery);
}
$csvQuery = array_filter([
    'start_date' => $start_date,
    'end_date' => $end_date,
    'search' => $search_query,
    'channel' => $channelFilter,
    'payment_method' => $paymentMethodFilter,
    'export' => 'csv',
], static function ($value): bool {
    return $value !== '';
});
$csvUrl = 'reports.php?' . http_build_query($csvQuery);
$formatReportDate = static function (string $date): string {
    return (new DateTimeImmutable($date))->format('M j, Y');
};
$dateRangeLabel = $start_date !== '' && $end_date !== ''
    ? $formatReportDate($start_date) . ' - ' . $formatReportDate($end_date)
    : ($start_date !== ''
        ? 'From ' . $formatReportDate($start_date)
        : ($end_date !== '' ? 'Through ' . $formatReportDate($end_date) : 'All dates'));
$trendGranularityLabel = match ($trendGranularity) {
    'day' => 'Daily',
    'week' => 'Weekly',
    default => 'Monthly',
};

// Page Variables for POS-style Header
$pageTitle = 'Sales Reports & Analytics';
$pageSubtitle = 'Monitor store revenue, payment method breakdowns, and inventory stock alerts.';
$parent = ['Dashboard', '../../index.php'];
$activeModule = 'reports';

include __DIR__ . '/../../layouts/header.php';
?>

<style>
  .report-print-heading { display: none; }
  @media print {
    .app { display: block !important; min-height: 0 !important; }
    .app-sidebar, .topbar, .report-actions, .report-filters, .no-print { display: none !important; }
    .app-main { padding: 0 !important; }
    .report-print-heading { display: block !important; }
    .container-fluid { width: 100% !important; padding: 0 !important; }
  }
</style>

<div class="container-fluid p-0">
  <!-- Action Bar & Sub-navigation between reports -->
  <div class="report-actions d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div class="d-flex gap-2">
      <a href="reports.php" class="btn btn-dark btn-sm rounded-pill px-3 active"><i class="bi bi-bar-chart-fill me-1"></i> Sales Reports</a>
      <?php if ($canViewProfitTracker): ?><a href="profit-report.php" class="btn btn-outline-dark btn-sm rounded-pill px-3"><i class="bi bi-graph-up-arrow me-1"></i> Profit Tracker</a><?php endif; ?>
    </div>
    <div class="d-flex gap-2">
      <?php if ($canViewOrders): ?>
        <a href="<?= htmlspecialchars($csvUrl, ENT_QUOTES, 'UTF-8') ?>" download="sales-report-<?= htmlspecialchars($today->format('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>.csv" class="btn btn-outline-dark btn-sm rounded-pill px-3"><i class="bi bi-download me-1"></i> Export CSV</a>
      <?php endif; ?>
      <button onclick="window.print();" class="btn btn-outline-secondary btn-sm rounded-pill px-3"><i class="bi bi-printer me-1"></i> Print Report</button>
    </div>
  </div>

  <section class="report-print-heading mb-4">
    <h1 class="h3 mb-1">Sales Report</h1>
    <p class="mb-0">Date range: <?= htmlspecialchars($dateRangeLabel, ENT_QUOTES, 'UTF-8') ?> |
      Search: <?= $search_query !== '' ? htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8') : 'All orders' ?>
      <?php if ($channelFilter !== ''): ?>| Channel: <?= htmlspecialchars(strtoupper($channelFilter), ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
      <?php if ($paymentMethodFilter !== ''): ?>| Payment: <?= htmlspecialchars($paymentMethodFilter, ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
      | Refunds are grouped by refund date.</p>
  </section>

  <!-- Advanced Filter & Search Card -->
  <div class="report-filters card border-0 shadow-sm p-3 rounded-4 mb-4 bg-white">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
      <span class="small fw-semibold text-muted me-1">Quick dates</span>
      <?php foreach ($presetUrls as $presetName => $presetUrl): ?>
        <?php $isPresetActive = $start_date === $datePresets[$presetName][0]->format('Y-m-d') && $end_date === $datePresets[$presetName][1]->format('Y-m-d'); ?>
        <a href="<?= htmlspecialchars($presetUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm <?= $isPresetActive ? 'btn-dark' : 'btn-outline-secondary' ?> rounded-pill px-3"><?= htmlspecialchars($presetName, ENT_QUOTES, 'UTF-8') ?></a>
      <?php endforeach; ?>
      <a href="reports.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3">Reset</a>
      <?php if ($channelFilter !== '' || $paymentMethodFilter !== ''): ?>
        <span class="small text-muted">
          Active filters:
          <?= $channelFilter !== '' ? 'Channel: ' . htmlspecialchars(strtoupper($channelFilter), ENT_QUOTES, 'UTF-8') : '' ?>
          <?= $paymentMethodFilter !== '' ? 'Payment: ' . htmlspecialchars($paymentMethodFilter, ENT_QUOTES, 'UTF-8') : '' ?>
        </span>
      <?php endif; ?>
    </div>
    <form method="GET" action="" class="row g-3 align-items-end">
      <?php if ($channelFilter !== ''): ?><input type="hidden" name="channel" value="<?= htmlspecialchars($channelFilter, ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
      <?php if ($paymentMethodFilter !== ''): ?><input type="hidden" name="payment_method" value="<?= htmlspecialchars($paymentMethodFilter, ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
      <div class="col-md-3">
        <label class="form-label small fw-semibold text-muted mb-1">Search Customer / Order #</label>
        <div class="input-group">
          <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
          <input type="text" name="search" class="form-control" placeholder="Type name or order no..." value="<?= htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8') ?>">
        </div>
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-semibold text-muted mb-1">From Date</label>
        <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date, ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-semibold text-muted mb-1">To Date</label>
        <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date, ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="col-md-3 d-flex gap-2">
        <button type="submit" class="btn btn-dark px-4 flex-grow-1"><i class="bi bi-filter me-1"></i> Filter</button>
      </div>
    </form>
    <?php if ($filterError !== ''): ?>
      <div class="alert alert-warning mt-3 mb-0" role="alert"><?= htmlspecialchars($filterError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl">
      <div class="card stat-card p-4 h-100 bg-white border-0 shadow-sm rounded-4">
        <span class="small fw-semibold text-muted text-uppercase">Gross Sales</span>
        <strong class="fs-4 text-dark mt-2"><?= htmlspecialchars(formatAppCurrency($grossSales), ENT_QUOTES, 'UTF-8') ?></strong>
        <span class="small text-muted mt-1">Before refunds</span>
      </div>
    </div>
    <div class="col-sm-6 col-xl">
      <div class="card stat-card p-4 h-100 bg-white border-0 shadow-sm rounded-4">
        <span class="small fw-semibold text-muted text-uppercase">Refunds Issued</span>
        <strong class="fs-4 text-danger mt-2">−<?= htmlspecialchars(formatAppCurrency($totalRefunds), ENT_QUOTES, 'UTF-8') ?></strong>
        <span class="small text-muted mt-1"><?= $refundImpactPercent === null ? 'Refunds issued in selected period' : number_format($refundImpactPercent, 1) . '% of gross sales' ?></span>
      </div>
    </div>
    <div class="col-sm-6 col-xl">
      <div class="card stat-card p-4 h-100 bg-white border-0 shadow-sm rounded-4">
        <span class="small fw-semibold text-muted text-uppercase">Net Sales</span>
        <strong class="fs-4 <?= $netSales < 0 ? 'text-danger' : 'text-success' ?> mt-2"><?= htmlspecialchars(formatAppCurrency($netSales), ENT_QUOTES, 'UTF-8') ?></strong>
        <span class="small text-muted mt-1">Gross sales less refunds</span>
      </div>
    </div>
    <div class="col-sm-6 col-xl">
      <div class="card stat-card p-4 h-100 bg-white border-0 shadow-sm rounded-4">
        <span class="small fw-semibold text-muted text-uppercase">Orders Processed</span>
        <strong class="fs-4 text-dark mt-2"><?= number_format($totalOrders) ?></strong>
        <span class="small text-muted mt-1">Matching date and search filters</span>
      </div>
    </div>
    <div class="col-sm-6 col-xl">
      <div class="card stat-card p-4 h-100 bg-white border-0 shadow-sm rounded-4">
        <span class="small fw-semibold text-muted text-uppercase">Average Order Value</span>
        <strong class="fs-4 text-dark mt-2"><?= htmlspecialchars(formatAppCurrency($averageOrderValue), ENT_QUOTES, 'UTF-8') ?></strong>
        <span class="small text-muted mt-1">Gross sales per matching order</span>
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-sm p-3 rounded-4 mb-4 bg-white">
    <div class="d-flex justify-content-between align-items-baseline flex-wrap gap-2 mb-2">
      <h5 class="fw-bold text-dark mb-0"><i class="bi bi-graph-up me-2 text-primary"></i>Sales Trend</h5>
      <small class="text-muted"><?= htmlspecialchars($trendGranularityLabel, ENT_QUOTES, 'UTF-8') ?> breakdown | Refunds by refund date</small>
    </div>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Period</th>
            <th class="text-center">Orders</th>
            <th class="text-end">Gross Sales</th>
            <th class="text-end">Refunds</th>
            <th class="text-end">Net Sales</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($salesTrend->num_rows > 0): ?>
            <?php while ($trend = $salesTrend->fetch_assoc()): ?>
              <?php
              $trendGrossSales = (float) $trend['gross_sales'];
              $trendRefunds = (float) $trend['refunds'];
              $trendNetSales = $trendGrossSales - $trendRefunds;
              $trendDate = new DateTimeImmutable($trend['trend_period']);
              $trendPeriodLabel = match ($trendGranularity) {
                  'day' => $trendDate->format('M j, Y'),
                  'week' => 'Week of ' . $trendDate->format('M j, Y'),
                  default => $trendDate->format('M Y'),
              };
              ?>
              <tr>
                <td><?= htmlspecialchars($trendPeriodLabel, ENT_QUOTES, 'UTF-8') ?></td>
                <td class="text-center"><?= number_format((int) $trend['order_count']) ?></td>
                <td class="text-end"><?= htmlspecialchars(formatAppCurrency($trendGrossSales), ENT_QUOTES, 'UTF-8') ?></td>
                <td class="text-end text-danger"><?= htmlspecialchars(formatAppCurrency($trendRefunds), ENT_QUOTES, 'UTF-8') ?></td>
                <td class="text-end <?= $trendNetSales < 0 ? 'text-danger' : 'text-success' ?>"><?= htmlspecialchars(formatAppCurrency($trendNetSales), ENT_QUOTES, 'UTF-8') ?></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="5" class="text-center text-muted py-3">No sales or refunds in this date range.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-lg-4">
      <div class="card border-0 shadow-sm p-4 rounded-4 h-100 bg-white">
        <h5 class="fw-bold text-dark mb-3"><i class="bi bi-trophy me-2 text-warning"></i>Top Selling Products</h5>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead><tr><th>Product</th><th class="text-center">Units</th><th class="text-end">Sales</th></tr></thead>
            <tbody>
              <?php if ($topProducts->num_rows > 0): ?>
                <?php while ($product = $topProducts->fetch_assoc()): ?>
                  <tr>
                    <td class="fw-semibold">
                      <?php if ($canViewProducts && (int) ($product['product_id'] ?? 0) > 0): ?>
                        <a href="../../products/product-details.php?id=<?= (int) $product['product_id'] ?>"><?= htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8') ?></a>
                      <?php else: ?>
                        <?= htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8') ?>
                      <?php endif; ?>
                    </td>
                    <td class="text-center"><?= number_format((int) $product['units_sold']) ?></td>
                    <td class="text-end"><?= htmlspecialchars(formatAppCurrency($product['sales']), ENT_QUOTES, 'UTF-8') ?></td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr><td colspan="3" class="text-center text-muted py-3">No product sales in this date range.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card border-0 shadow-sm p-4 rounded-4 h-100 bg-white">
        <h5 class="fw-bold text-dark mb-3"><i class="bi bi-tags me-2 text-primary"></i>Top Selling Categories</h5>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead><tr><th>Category</th><th class="text-center">Units</th><th class="text-end">Sales</th></tr></thead>
            <tbody>
              <?php if ($topCategories->num_rows > 0): ?>
                <?php while ($category = $topCategories->fetch_assoc()): ?>
                  <tr>
                    <td class="fw-semibold">
                      <?php if ($canViewProducts && (int) ($category['category_id'] ?? 0) > 0): ?>
                        <a href="../../products/products.php?category_id=<?= (int) $category['category_id'] ?>"><?= htmlspecialchars($category['category_name'], ENT_QUOTES, 'UTF-8') ?></a>
                      <?php else: ?>
                        <?= htmlspecialchars($category['category_name'], ENT_QUOTES, 'UTF-8') ?>
                      <?php endif; ?>
                    </td>
                    <td class="text-center"><?= number_format((int) $category['units_sold']) ?></td>
                    <td class="text-end"><?= htmlspecialchars(formatAppCurrency($category['sales']), ENT_QUOTES, 'UTF-8') ?></td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr><td colspan="3" class="text-center text-muted py-3">No category sales in this date range.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card border-0 shadow-sm p-4 rounded-4 h-100 bg-white">
        <h5 class="fw-bold text-dark mb-3"><i class="bi bi-shop me-2 text-success"></i>Sales by Channel</h5>
        <div class="d-flex justify-content-between align-items-center border-bottom py-3">
          <div>
            <strong class="d-block text-dark"><a href="<?= htmlspecialchars($reportFilterUrl(['channel' => 'pos']), ENT_QUOTES, 'UTF-8') ?>">POS</a></strong>
            <small class="text-muted"><?= number_format((int) ($channelStats['pos_orders'] ?? 0)) ?> orders</small>
          </div>
          <strong class="text-success"><?= htmlspecialchars(formatAppCurrency($channelStats['pos_sales'] ?? 0), ENT_QUOTES, 'UTF-8') ?></strong>
        </div>
        <div class="d-flex justify-content-between align-items-center py-3">
          <div>
            <strong class="d-block text-dark"><a href="<?= htmlspecialchars($reportFilterUrl(['channel' => 'online']), ENT_QUOTES, 'UTF-8') ?>">Online</a></strong>
            <small class="text-muted"><?= number_format((int) ($channelStats['online_orders'] ?? 0)) ?> orders</small>
          </div>
          <strong class="text-primary"><?= htmlspecialchars(formatAppCurrency($channelStats['online_sales'] ?? 0), ENT_QUOTES, 'UTF-8') ?></strong>
        </div>
      </div>
    </div>
  </div>

  <!-- Row: Payment Methods & Low Stock Alerts -->
  <div class="row g-4 mb-4">
    <!-- Payment Methods Summary -->
    <div class="col-lg-6">
      <div class="card border-0 shadow-sm p-4 rounded-4 h-100 bg-white">
        <h5 class="fw-bold text-dark mb-3"><i class="bi bi-wallet2 me-2 text-warning"></i>Sales by Payment Method</h5>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Method</th>
                <th class="text-center">Orders</th>
                <th class="text-end">Revenue</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($payment_res && $payment_res->num_rows > 0): ?>
                <?php while($pay = $payment_res->fetch_assoc()): ?>
                  <tr>
                    <td class="fw-semibold">
                      <?php if (isset($pay['payment_method']) && $pay['payment_method'] !== ''): ?>
                        <a href="<?= htmlspecialchars($reportFilterUrl(['payment_method' => $pay['payment_method']]), ENT_QUOTES, 'UTF-8') ?>"><span class="badge bg-dark"><?= htmlspecialchars($pay['payment_method'], ENT_QUOTES, 'UTF-8') ?></span></a>
                      <?php else: ?>
                        <span class="badge bg-dark"><?= htmlspecialchars($pay['payment_method'] ?? 'Other', ENT_QUOTES, 'UTF-8') ?></span>
                      <?php endif; ?>
                    </td>
                    <td class="text-center"><?= number_format((int) $pay['order_count']) ?></td>
                    <td class="text-end fw-bold text-success"><?= htmlspecialchars(formatAppCurrency($pay['revenue']), ENT_QUOTES, 'UTF-8') ?></td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="3" class="text-center text-muted py-3">No payment data found.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Low Stock Alerts -->
    <div class="col-lg-6">
      <div id="low-stock-inventory-alerts" class="card border-0 shadow-sm p-4 rounded-4 h-100 bg-white">
        <h5 class="fw-bold text-dark mb-3"><i class="bi bi-exclamation-triangle me-2 text-danger"></i>Low Stock Inventory Alerts</h5>
        <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
          <table class="table align-middle mb-0 small">
            <thead class="table-light">
              <tr>
                <th>Product</th>
                <th>Category</th>
                <th class="text-end">Stock Left</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($low_stock_res && $low_stock_res->num_rows > 0): ?>
                <?php while($stock_item = $low_stock_res->fetch_assoc()): ?>
                  <tr>
                    <td class="fw-semibold text-truncate" style="max-width: 150px;">
                      <?php if ($canAdjustStock): ?>
                        <a href="../../inventory/stock-adjust.php?product_id=<?= (int) $stock_item['id'] ?>"><?= htmlspecialchars($stock_item['name'], ENT_QUOTES, 'UTF-8') ?></a>
                      <?php elseif ($canViewProducts): ?>
                        <a href="../../products/product-details.php?id=<?= (int) $stock_item['id'] ?>"><?= htmlspecialchars($stock_item['name'], ENT_QUOTES, 'UTF-8') ?></a>
                      <?php elseif ($canViewInventory): ?>
                        <a href="../../inventory/inventory.php"><?= htmlspecialchars($stock_item['name'], ENT_QUOTES, 'UTF-8') ?></a>
                      <?php else: ?>
                        <?= htmlspecialchars($stock_item['name'], ENT_QUOTES, 'UTF-8') ?>
                      <?php endif; ?>
                    </td>
                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($stock_item['category_name'] ?? 'General'); ?></span></td>
                    <td class="text-end"><span class="badge bg-danger"><?php echo $stock_item['stock']; ?> left</span></td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="3" class="text-center text-muted py-3">All products have sufficient stock levels!</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Filtered Orders Report Table -->
  <div class="card border-0 shadow-sm p-4 rounded-4 mb-4 bg-white">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
      <div>
        <h5 class="fw-bold text-dark mb-1"><i class="bi bi-receipt me-2 text-primary"></i>Filtered Orders</h5>
        <small class="text-muted">Showing <?= number_format(min(20, $matchingOrderCount)) ?> of <?= number_format($matchingOrderCount) ?> matching orders</small>
      </div>
      <?php if ($canViewOrders): ?><a href="../orders/orders.php" class="btn btn-sm btn-outline-dark rounded-pill">View All Orders</a><?php endif; ?>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Order #</th>
            <th>Customer</th>
            <th>Channel</th>
            <th>Payment Method</th>
            <th>Total Amount</th>
            <th>Date / Time</th>
            <?php if ($canViewOrders): ?><th class="text-end no-print">Invoice</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php if ($recent_orders && $recent_orders->num_rows > 0): ?>
            <?php while($ord = $recent_orders->fetch_assoc()): ?>
              <?php $orderChannel = str_starts_with((string) ($ord['shipping_address'] ?? ''), 'Walk-in Counter POS') ? 'POS' : 'Online'; ?>
              <tr>
                <td class="fw-bold">
                  <?php if ($canViewOrders): ?>
                    <a href="../orders/order-details.php?id=<?= (int) $ord['id'] ?>"><?= htmlspecialchars($ord['order_no'] ?? '#ORD-' . $ord['id'], ENT_QUOTES, 'UTF-8') ?></a>
                  <?php else: ?>
                    <?= htmlspecialchars($ord['order_no'] ?? '#ORD-' . $ord['id'], ENT_QUOTES, 'UTF-8') ?>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($canViewCustomers && !empty($ord['customer_id'])): ?>
                    <a href="../../customers/customer-details.php?id=<?= (int) $ord['customer_id'] ?>"><?= htmlspecialchars($ord['customer_name'] ?? 'Walk-in Customer', ENT_QUOTES, 'UTF-8') ?></a>
                  <?php else: ?>
                    <?= htmlspecialchars($ord['customer_name'] ?? 'Walk-in Customer', ENT_QUOTES, 'UTF-8') ?>
                  <?php endif; ?>
                </td>
                <td><a href="<?= htmlspecialchars($reportFilterUrl(['channel' => strtolower($orderChannel)]), ENT_QUOTES, 'UTF-8') ?>"><span class="badge <?= $orderChannel === 'POS' ? 'bg-success' : 'bg-primary' ?>"><?= htmlspecialchars($orderChannel, ENT_QUOTES, 'UTF-8') ?></span></a></td>
                <td>
                  <?php if (is_string($ord['payment_method'] ?? null) && $ord['payment_method'] !== ''): ?>
                    <a href="<?= htmlspecialchars($reportFilterUrl(['payment_method' => $ord['payment_method']]), ENT_QUOTES, 'UTF-8') ?>"><span class="badge bg-secondary"><?= htmlspecialchars($ord['payment_method'], ENT_QUOTES, 'UTF-8') ?></span></a>
                  <?php else: ?>
                    <span class="badge bg-secondary"><?= htmlspecialchars($ord['payment_method'] ?? 'Cash', ENT_QUOTES, 'UTF-8') ?></span>
                  <?php endif; ?>
                </td>
                <td class="fw-bold text-success"><?= htmlspecialchars(formatAppCurrency($ord['total']), ENT_QUOTES, 'UTF-8') ?></td>
                <td class="text-muted small"><?= htmlspecialchars(date('M d, Y - h:i A', strtotime($ord['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                <?php if ($canViewOrders): ?><td class="text-end no-print">
                  <a href="../orders/invoice.php?id=<?= (int) $ord['id'] ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-dark"><i class="bi bi-printer"></i> Print</a>
                </td><?php endif; ?>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="<?= $canViewOrders ? '7' : '6' ?>" class="text-center text-muted py-4">No orders found matching your filters.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php 
include __DIR__ . '/../../layouts/footer.php'; 
?>