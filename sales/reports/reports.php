<?php
session_start();
include "../../config.php";

// Calculate Dynamic Base Path for relative assets
$scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$appBase = str_replace('\\', '/', rtrim(dirname($scriptPath), '/\\'));
while (in_array(basename($appBase), ['customers', 'products', 'orders', 'inventory', 'sales', 'payments', 'returns', 'pos', 'reports'], true)) {
    $appBase = str_replace('\\', '/', rtrim(dirname($appBase), '/\\'));
}

// Date & Search Filter Parameters Handling
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Base where clause for date filtering on orders
$date_filter_sql = "";
$params = [];
$types = "";

if (!empty($start_date) && !empty($end_date)) {
    $date_filter_sql = " WHERE DATE(o.created_at) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
    $types .= "ss";
} elseif (!empty($start_date)) {
    $date_filter_sql = " WHERE DATE(o.created_at) >= ?";
    $params[] = $start_date;
    $types .= "s";
} elseif (!empty($end_date)) {
    $date_filter_sql = " WHERE DATE(o.created_at) <= ?";
    $params[] = $end_date;
    $types .= "s";
}

// 1. Total Sales & Revenue Stats (Filtered by Date)
$stats_query = "SELECT COUNT(*) as total_orders, SUM(total) as total_revenue FROM orders o" . $date_filter_sql;
$stmt_stats = $conn->prepare($stats_query);
if (!empty($params)) {
    $stmt_stats->bind_param($types, ...$params);
}
$stmt_stats->execute();
$stats = $stmt_stats->get_result()->fetch_assoc();
$stmt_stats->close();

// 2. Payment Method wise Breakdown (Filtered by Date)
$payment_query = "SELECT payment_method, COUNT(*) as count, SUM(total) as revenue FROM orders o" . $date_filter_sql . " GROUP BY payment_method";
$stmt_pay = $conn->prepare($payment_query);
if (!empty($params)) {
    $stmt_pay->bind_param($types, ...$params);
}
$stmt_pay->execute();
$payment_res = $stmt_pay->get_result();

// 3. Low Stock Products (< 5 stock)
$low_stock_res = $conn->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.stock <= 5 ORDER BY p.stock ASC");

// 4. Orders History Report (Filtered by Date & Smart Search)
$orders_query = "SELECT o.*, c.name as customer_name FROM orders o LEFT JOIN customers c ON o.customer_id = c.id";
$where_clauses = [];
$order_params = [];
$order_types = "";

if (!empty($start_date) && !empty($end_date)) {
    $where_clauses[] = "DATE(o.created_at) BETWEEN ? AND ?";
    $order_params[] = $start_date;
    $order_params[] = $end_date;
    $order_types .= "ss";
} elseif (!empty($start_date)) {
    $where_clauses[] = "DATE(o.created_at) >= ?";
    $order_params[] = $start_date;
    $order_types .= "s";
} elseif (!empty($end_date)) {
    $where_clauses[] = "DATE(o.created_at) <= ?";
    $order_params[] = $end_date;
    $order_types .= "s";
}

// Smart Search with Typo/Soundex tolerance
if (!empty($search_query)) {
    $keywords = explode(' ', $search_query);
    $search_clauses = [];
    foreach ($keywords as $word) {
        $word = trim($word);
        if (!empty($word)) {
            $searchTerm = "%" . $word . "%";
            $fuzzyWord = "%";
            for ($i = 0; $i < strlen($word); $i++) {
                $fuzzyWord .= $word[$i] . "%";
            }

            $search_clauses[] = "(o.order_no LIKE ? OR c.name LIKE ? OR o.payment_method LIKE ? OR SOUNDEX(c.name) = SOUNDEX(?))";
            $order_params[] = $searchTerm;
            $order_params[] = $searchTerm;
            $order_params[] = $searchTerm;
            $order_params[] = $word;
            $order_types .= "ssss";
        }
    }
    if (!empty($search_clauses)) {
        $where_clauses[] = "(" . implode(" OR ", $search_clauses) . ")";
    }
}

if (!empty($where_clauses)) {
    $orders_query .= " WHERE " . implode(" AND ", $where_clauses);
}

$orders_query .= " ORDER BY o.id DESC LIMIT 20";

$stmt_ord = $conn->prepare($orders_query);
if (!empty($order_params)) {
    $stmt_ord->bind_param($order_types, ...$order_params);
}
$stmt_ord->execute();
$recent_orders = $stmt_ord->get_result();

// Page Variables for POS-style Header
$pageTitle = 'Sales Reports & Analytics';
$pageSubtitle = 'Monitor store revenue, payment method breakdowns, and inventory stock alerts.';
$parent = ['Dashboard', '../../index.php'];
$activeModule = 'reports';

include __DIR__ . '/../../layouts/header.php';
?>

<!-- Ensure CSS styles load properly across deep directory paths -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/assets/css/style.css" rel="stylesheet">

<div class="container-fluid p-0">
  <!-- Action Bar & Sub-navigation between reports -->
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div class="d-flex gap-2">
      <a href="reports.php" class="btn btn-dark btn-sm rounded-pill px-3 active"><i class="bi bi-bar-chart-fill me-1"></i> Sales Reports</a>
      <a href="profit-report.php" class="btn btn-outline-dark btn-sm rounded-pill px-3"><i class="bi bi-graph-up-arrow me-1"></i> Profit Tracker</a>
    </div>
    <div>
      <button onclick="window.print();" class="btn btn-outline-secondary btn-sm rounded-pill px-3"><i class="bi bi-printer me-1"></i> Print Report</button>
    </div>
  </div>

  <!-- Advanced Filter & Search Card -->
  <div class="card border-0 shadow-sm p-3 rounded-4 mb-4 bg-white">
    <form method="GET" action="" class="row g-3 align-items-end">
      <div class="col-md-3">
        <label class="form-label small fw-semibold text-muted mb-1">Search Customer / Order #</label>
        <div class="input-group">
          <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
          <input type="text" name="search" class="form-control" placeholder="Type name or order no..." value="<?php echo htmlspecialchars($search_query); ?>">
        </div>
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-semibold text-muted mb-1">From Date</label>
        <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-semibold text-muted mb-1">To Date</label>
        <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>">
      </div>
      <div class="col-md-3 d-flex gap-2">
        <button type="submit" class="btn btn-dark px-4 flex-grow-1"><i class="bi bi-filter me-1"></i> Filter</button>
        <?php if (!empty($start_date) || !empty($end_date) || !empty($search_query)): ?>
          <a href="reports.php" class="btn btn-outline-secondary">Reset</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Top Statistics Cards -->
  <div class="row g-4 mb-4">
    <div class="col-md-6">
      <div class="card stat-card p-4 bg-white border-0 shadow-sm rounded-4">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <p class="text-muted mb-1 small fw-semibold text-uppercase">Total Store Revenue <?php echo (!empty($start_date)) ? '<small class="text-primary">(Filtered)</small>' : ''; ?></p>
            <h2 class="fw-bold text-success mb-0"><?php echo htmlspecialchars(formatAppCurrency($stats['total_revenue'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></h2>
          </div>
          <div class="bg-success bg-opacity-10 p-3 rounded-circle text-success fs-3">
            <i class="bi bi-currency-dollar"></i>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card stat-card p-4 bg-white border-0 shadow-sm rounded-4">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <p class="text-muted mb-1 small fw-semibold text-uppercase">Total Orders Processed <?php echo (!empty($start_date)) ? '<small class="text-primary">(Filtered)</small>' : ''; ?></p>
            <h2 class="fw-bold text-dark mb-0"><?php echo number_format($stats['total_orders'] ?? 0); ?></h2>
          </div>
          <div class="bg-primary bg-opacity-10 p-3 rounded-circle text-primary fs-3">
            <i class="bi bi-bag-check"></i>
          </div>
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
                    <td class="fw-semibold"><span class="badge bg-dark"><?php echo htmlspecialchars($pay['payment_method']); ?></span></td>
                    <td class="text-center"><?php echo $pay['count']; ?></td>
                    <td class="text-end fw-bold text-success"><?php echo htmlspecialchars(formatAppCurrency($pay['revenue']), ENT_QUOTES, 'UTF-8'); ?></td>
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
                    <td class="fw-semibold text-truncate" style="max-width: 150px;"><?php echo htmlspecialchars($stock_item['name']); ?></td>
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

  <!-- Orders History Report Table -->
  <div class="card border-0 shadow-sm p-4 rounded-4 mb-4 bg-white">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="fw-bold text-dark mb-0"><i class="bi bi-receipt me-2 text-primary"></i>Orders History Report</h5>
      <a href="../orders/orders.php" class="btn btn-sm btn-outline-dark rounded-pill">View All Orders</a>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Order ID</th>
            <th>Customer</th>
            <th>Payment Method</th>
            <th>Total Amount</th>
            <th>Date / Time</th>
            <th class="text-end">Invoice</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($recent_orders && $recent_orders->num_rows > 0): ?>
            <?php while($ord = $recent_orders->fetch_assoc()): ?>
              <tr>
                <td class="fw-bold"><?php echo htmlspecialchars($ord['order_no'] ?? '#ORD-'.$ord['id']); ?></td>
                <td><?php echo htmlspecialchars($ord['customer_name'] ?? 'Walk-in Customer'); ?></td>
                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($ord['payment_method'] ?? 'Cash'); ?></span></td>
                <td class="fw-bold text-success"><?php echo htmlspecialchars(formatAppCurrency($ord['total']), ENT_QUOTES, 'UTF-8'); ?></td>
                <td class="text-muted small"><?php echo date('M d, Y - h:i A', strtotime($ord['created_at'])); ?></td>
                <td class="text-end">
                  <a href="../orders/invoice.php?id=<?php echo $ord['id']; ?>" target="_blank" class="btn btn-sm btn-outline-dark"><i class="bi bi-printer"></i> Print</a>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="6" class="text-center text-muted py-4">No orders found matching your search or date criteria.</td>
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