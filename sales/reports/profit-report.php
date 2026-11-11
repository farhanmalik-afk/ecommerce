<?php
session_start();
include "../../config.php";

$today = date('Y-m-d');
$filter_date = $_GET['date'] ?? $today;

// 1. Total Sales Revenue, Cost, and Profit for selected date
$stmt = $conn->prepare("
    SELECT 
        COUNT(o.id) as total_orders,
        SUM(o.total) as total_revenue,
        SUM(oi.quantity * p.cost_price) as total_cost
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE DATE(o.created_at) = ?
");
$stmt->bind_param("s", $filter_date);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

$total_revenue = $data['total_revenue'] ?? 0;
$total_cost = $data['total_cost'] ?? 0;
$net_profit = $total_revenue - $total_cost;
$profit_margin = $total_revenue > 0 ? ($net_profit / $total_revenue) * 100 : 0;

// 2. Detailed items sold breakdown with profit per item
$items_stmt = $conn->prepare("
    SELECT 
        p.name as product_name,
        oi.quantity,
        oi.price as selling_price,
        p.cost_price,
        (oi.quantity * oi.price) as total_sales,
        (oi.quantity * (oi.price - p.cost_price)) as item_profit,
        o.order_no,
        o.created_at
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    WHERE DATE(o.created_at) = ?
    ORDER BY o.id DESC
");
$items_stmt->bind_param("s", $filter_date);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
$pageTitle = 'Profit Tracker';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Daily Profit & Margin Tracker</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
  <link href="../../assets/css/style.css" rel="stylesheet">
  <style>
    body { font-family: 'Inter', sans-serif; background-color: #f5f0e6; }
    .stat-card { border: 1px solid #e8e1d5; border-radius: 12px; box-shadow: 0 4px 20px rgba(20,36,61,0.06); }
  </style>
</head>
<body>

<div class="app">
  <?php include "../../layouts/sidebar.php"; ?>

  <main class="app-main">
    <?php include "../../layouts/topbar.php"; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h1 class="fw-bold text-dark mb-1">Daily Profit & Margin Tracker</h1>
        <p class="text-muted mb-0">Analyze net earnings, item-wise margins, and total store revenue performance.</p>
      </div>
      <!-- Date Filter -->
      <form method="GET" class="d-flex gap-2">
        <input type="date" name="date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filter_date); ?>">
        <button type="submit" class="btn btn-dark btn-sm">Filter</button>
      </form>
    </div>

    <!-- Profit Summary Cards -->
    <div class="row g-4 mb-4">
      <div class="col-xl-3 col-md-6">
        <div class="card stat-card p-3 bg-white">
          <span class="text-muted small fw-semibold">TOTAL REVENUE</span>
          <h3 class="fw-bold text-dark mt-1 mb-0"><?php echo htmlspecialchars(formatAppCurrency($total_revenue), ENT_QUOTES, 'UTF-8'); ?></h3>
          <span class="small text-muted mt-2"><i class="bi bi-cash me-1"></i> Gross Sales Amount</span>
        </div>
      </div>
      <div class="col-xl-3 col-md-6">
        <div class="card stat-card p-3 bg-white">
          <span class="text-muted small fw-semibold">TOTAL ITEM COST</span>
          <h3 class="fw-bold text-danger mt-1 mb-0"><?php echo htmlspecialchars(formatAppCurrency($total_cost), ENT_QUOTES, 'UTF-8'); ?></h3>
          <span class="small text-muted mt-2"><i class="bi bi-box-seam me-1"></i> Cost of Goods Sold</span>
        </div>
      </div>
      <div class="col-xl-3 col-md-6">
        <div class="card stat-card p-3 bg-white">
          <span class="text-muted small fw-semibold">NET PROFIT</span>
          <h3 class="fw-bold text-success mt-1 mb-0"><?php echo htmlspecialchars(formatAppCurrency($net_profit), ENT_QUOTES, 'UTF-8'); ?></h3>
          <span class="small text-success mt-2 fw-semibold"><i class="bi bi-graph-up-arrow me-1"></i> Earnings</span>
        </div>
      </div>
      <div class="col-xl-3 col-md-6">
        <div class="card stat-card p-3 bg-white">
          <span class="text-muted small fw-semibold">PROFIT MARGIN</span>
          <h3 class="fw-bold text-primary mt-1 mb-0"><?php echo number_format($profit_margin, 1); ?>%</h3>
          <span class="small text-muted mt-2"><i class="bi bi-percent me-1"></i> Margin Percentage</span>
        </div>
      </div>
    </div>

    <!-- Detailed Items Profit Table -->
    <div class="card border-0 shadow-sm p-4 rounded-4">
      <h5 class="fw-bold text-dark mb-3"><i class="bi bi-receipt-cutoff me-2 text-primary"></i>Sold Items Breakdown & Profit on <?php echo htmlspecialchars($filter_date); ?></h5>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Order ID</th>
              <th>Product Name</th>
              <th class="text-center">Qty</th>
              <th>Unit Cost</th>
              <th>Selling Price</th>
              <th>Total Sales</th>
              <th class="text-end">Item Profit</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($items_result && $items_result->num_rows > 0): ?>
              <?php while($item = $items_result->fetch_assoc()): ?>
                <tr>
                  <td class="fw-bold"><?php echo htmlspecialchars($item['order_no']); ?></td>
                  <td class="fw-semibold"><?php echo htmlspecialchars($item['product_name']); ?></td>
                  <td class="text-center"><?php echo $item['quantity']; ?></td>
                  <td><?php echo htmlspecialchars(formatAppCurrency($item['cost_price']), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars(formatAppCurrency($item['selling_price']), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td class="fw-semibold"><?php echo htmlspecialchars(formatAppCurrency($item['total_sales']), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td class="text-end fw-bold text-success">+<?php echo htmlspecialchars(formatAppCurrency($item['item_profit']), ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="7" class="text-center text-muted py-4">No sales items recorded for this date.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>