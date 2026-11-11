<?php

session_start();
include "config.php";

// Fetch Dynamic Stats from Database
$total_products = 0;
$total_customers = 0;
$total_orders = 0;
$delivered_orders = 0;
$total_inventory_items = 0;
$low_stock_count = 0;
$active_products = 0;
$active_customers = 0;

// 1. Total & Active Products Count
$prod_query = $conn->query("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active FROM products WHERE is_deleted = 0");
if ($prod_query) {
    $prod_data = $prod_query->fetch_assoc();
    $total_products = $prod_data['total'] ?? 0;
    $active_products = $prod_data['active'] ?? 0;
}

// 2. Total & Active Customers Count
$cust_query = $conn->query("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active FROM customers WHERE is_deleted = 0");
if ($cust_query) {
    $cust_data = $cust_query->fetch_assoc();
    $total_customers = $cust_data['total'] ?? 0;
    $active_customers = $cust_data['active'] ?? 0;
}

// 3. Total Orders Count
$orders_query = $conn->query("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Delivered' THEN 1 ELSE 0 END) as delivered
    FROM orders
");
if ($orders_query) {
    $orders_data = $orders_query->fetch_assoc();
    $total_orders = $orders_data['total'] ?? 0;
    $delivered_orders = $orders_data['delivered'] ?? 0;
}

// 4. Inventory Stats (Total stock units & Low stock items)
$inv_stats = $conn->query("SELECT SUM(quantity) as total_qty, SUM(CASE WHEN quantity <= 5 THEN 1 ELSE 0 END) as low_stock FROM inventory");
if ($inv_stats) {
    $inv_data = $inv_stats->fetch_assoc();
    $total_inventory_items = $inv_data['total_qty'] ?? 0;
    $low_stock_count = $inv_data['low_stock'] ?? 0;
}

$pageTitle = 'Dashboard';
include "layouts/header.php";
?>

<div class="app-main dashboard-main">
    <!-- Topbar & Page Heading -->
    <section class="page-head mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h1>Overview Dashboard</h1>
            <p class="text-muted">Real-time statistics and quick system activity overview.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="sales/orders/add-order.php" class="btn btn-outline-dark btn-sm">
                <i class="bi bi-plus-lg me-1"></i> Add Order
            </a>
            <a href="inventory/inventory.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-boxes me-1"></i> Manage Inventory
            </a>
        </div>
    </section>

    <!-- Stat Cards Row -->
    <div class="row g-3 mb-4 dashboard-stats">
        <!-- Total Products Card -->
        <div class="col-xl-3 col-sm-6">
            <div class="card stat-card border-0 shadow-sm">
                <div class="card-body">
                    <div>
                        <p class="stat-label">Total Products</p>
                        <h3 class="stat-value"><?php echo number_format($total_products); ?></h3>
                    </div>
                    <div class="stat-icon bg-a">
                        <i class="bi bi-box-seam"></i>
                    </div>
                </div>
                <div class="stat-foot">
                    <span class="text-up"><i class="bi bi-check-circle-fill"></i> <?php echo $active_products; ?> Active</span> in catalog
                </div>
            </div>
        </div>

        <!-- Total Customers Card -->
        <div class="col-xl-3 col-sm-6">
            <div class="card stat-card border-0 shadow-sm">
                <div class="card-body">
                    <div>
                        <p class="stat-label">Total Customers</p>
                        <h3 class="stat-value"><?php echo number_format($total_customers); ?></h3>
                    </div>
                    <div class="stat-icon bg-a">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>
                <div class="stat-foot">
                    <span class="text-up"><i class="bi bi-person-check-fill"></i> <?php echo $active_customers; ?> Active</span> registered
                </div>
            </div>
        </div>

        <!-- Inventory / Stock Card -->
        <div class="col-xl-3 col-sm-6">
            <div class="card stat-card border-0 shadow-sm">
                <div class="card-body">
                    <div>
                        <p class="stat-label">Total Stock Units</p>
                        <h3 class="stat-value"><?php echo number_format($total_inventory_items); ?></h3>
                    </div>
                    <div class="stat-icon bg-a">
                        <i class="bi bi-boxes"></i>
                    </div>
                </div>
                <div class="stat-foot">
                    <span class="text-danger fw-bold"><i class="bi bi-exclamation-triangle-fill"></i> <?php echo $low_stock_count; ?></span> Low stock items
                </div>
            </div>
        </div>

    </div>

    <!-- Order Summary Cards -->
    <div class="row g-3 mb-4 dashboard-order-stats">
        <!-- Total Orders Card -->
        <div class="col-xl-6 col-sm-6">
            <div class="card stat-card border-0 shadow-sm">
                <div class="card-body">
                    <div>
                        <p class="stat-label">Total Orders</p>
                        <h3 class="stat-value"><?php echo number_format($total_orders); ?></h3>
                    </div>
                    <div class="stat-icon bg-a">
                        <i class="bi bi-cart-fill"></i>
                    </div>
                </div>
                <div class="stat-foot">
                    Customer sales recorded
                </div>
            </div>
        </div>

        <!-- Delivered Orders Card -->
        <div class="col-xl-6 col-sm-6">
            <div class="card stat-card border-0 shadow-sm">
                <div class="card-body">
                    <div>
                        <p class="stat-label">Delivered Orders</p>
                        <h3 class="stat-value"><?php echo number_format($delivered_orders); ?></h3>
                    </div>
                    <div class="stat-icon bg-a">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                </div>
                <div class="stat-foot">
                    Successfully completed orders
                </div>
            </div>
        </div>
    </div>

    <!-- Data Tables Row (Products & Customers) -->
    <div class="row g-4 mb-4">
        <!-- Recent Products Table -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm table-card dashboard-panel">
                <div class="table-card-header d-flex justify-content-between align-items-center p-3 border-bottom">
                    <h6 class="m-0 font-weight-bold">Recently Added Products</h6>
                    <a href="products/products.php" class="btn btn-sm btn-link text-decoration-none p-0">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table data-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Orig. Price</th>
                                <th>Discount</th>
                                <th>Sale Price</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $recent_prod = $conn->query("SELECT name, original_price, discount_percent, sale_price, status, image FROM products ORDER BY id DESC LIMIT 5");
                            if ($recent_prod && $recent_prod->num_rows > 0) {
                                while ($p = $recent_prod->fetch_assoc()) {
                                   $img_src = (!empty($p['image']) && file_exists(__DIR__ . "/uploads/" . $p['image'])) 
    ? "uploads/" . htmlspecialchars($p['image']) 
    : "uploads/default-product.png"; $status_class = ($p['status'] == 'Active') ? 'bg-success' : (($p['status'] == 'Draft') ? 'bg-warning text-dark' : 'bg-secondary');
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="person d-flex align-items-center gap-2">
                                                <img src="<?php echo $img_src; ?>" alt="img" class="rounded border" style="width: 35px; height: 35px; object-fit: cover;">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($p['name']); ?></strong>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars(formatAppCurrency($p['original_price']), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><span class="badge bg-light text-dark border"><?php echo intval($p['discount_percent']); ?>% OFF</span></td>
                                        <td><strong class="text-success"><?php echo htmlspecialchars(formatAppCurrency($p['sale_price']), ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                        <td><span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($p['status']); ?></span></td>
                                    </tr>
                                    <?php
                                }
                            } else {
                                echo "<tr><td colspan='5' class='text-center text-muted py-3'>No products found.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Customers Table -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm table-card dashboard-panel">
                <div class="table-card-header d-flex justify-content-between align-items-center p-3 border-bottom">
                    <h6 class="m-0 font-weight-bold">Recent Customers</h6>
                    <a href="customers/customers.php" class="btn btn-sm btn-link text-decoration-none p-0">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table data-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Group</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $recent_cust = $conn->query("SELECT name, email, group_name, status FROM customers WHERE is_deleted = 0 ORDER BY id DESC LIMIT 5");
                            if ($recent_cust && $recent_cust->num_rows > 0) {
                                while ($c = $recent_cust->fetch_assoc()) {
                                    $cust_status_class = ($c['status'] == 'Active') ? 'status-online' : 'status-offline';
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($c['name']); ?></strong>
                                            <small class="text-muted d-block"><?php echo htmlspecialchars($c['email']); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($c['group_name'] ?? 'General'); ?></span>
                                        </td>
                                        <td>
                                            <span class="status <?php echo $cust_status_class; ?>"><?php echo htmlspecialchars($c['status']); ?></span>
                                        </td>
                                    </tr>
                                    <?php
                                }
                            } else {
                                echo "<tr><td colspan='3' class='text-center text-muted py-3'>No customers registered yet.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Inventory Overview & Recent Orders Row -->
    <div class="row g-4 mb-4">
        <!-- Low Stock Alert / Inventory Quick View -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm dashboard-panel">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                    <h6 class="m-0 font-weight-bold text-dark"><i class="bi bi-exclamation-triangle-fill text-warning me-1"></i> Low Stock Warning</h6>
                    <a href="inventory/inventory.php" class="btn btn-sm btn-outline-dark">View Inventory</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-3">Product Name</th>
                                <th>Quantity</th>
                                <th class="text-end pe-3">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $low_stock_query = $conn->query("SELECT p.name, i.quantity, p.id as product_id 
                                                       FROM inventory i 
                                                       JOIN products p ON i.product_id = p.id 
                                                       WHERE i.quantity <= 5 
                                                       ORDER BY i.quantity ASC LIMIT 5");
                            if ($low_stock_query && $low_stock_query->num_rows > 0) {
                                while ($ls = $low_stock_query->fetch_assoc()) {
                                    ?>
                                    <tr>
                                        <td class="ps-3 fw-bold"><?php echo htmlspecialchars($ls['name']); ?></td>
                                        <td><span class="badge bg-danger"><?php echo $ls['quantity']; ?> units</span></td>
                                        <td class="text-end pe-3">
                                            <a href="inventory/stock-adjust.php?product_id=<?php echo $ls['product_id']; ?>" class="btn btn-sm btn-dark py-0 px-2" style="font-size: 11px;">Restock</a>
                                        </td>
                                    </tr>
                                    <?php
                                }
                            } else {
                                echo "<tr><td colspan='3' class='text-center text-muted py-3'>All products have sufficient stock!</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Customer Orders Table Section -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm dashboard-panel">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                    <h6 class="m-0 font-weight-bold text-dark">Recent Customer Orders</h6>
                    <a href="sales/orders/orders.php" class="btn btn-sm btn-outline-secondary">View All Orders</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-3">Order No</th>
                                <th>Customer</th>
                                <th>Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $recent_orders = $conn->query("SELECT o.*, COALESCE(c.name, 'Guest Customer') as customer_name 
                                                       FROM orders o 
                                                       LEFT JOIN customers c ON o.customer_id = c.id 
                                                       ORDER BY o.id DESC LIMIT 5");
                            
                            if ($recent_orders && $recent_orders->num_rows > 0) {
                                while ($order = $recent_orders->fetch_assoc()) {
                                    $status = $order['status'] ?? 'Pending';
                                    $display_id = !empty($order['order_no']) ? $order['order_no'] : '#ORD-' . str_pad($order['id'], 4, '0', STR_PAD_LEFT);
                                    
                                    $badge_class = 'bg-secondary';
                                    if ($status === 'Delivered') $badge_class = 'bg-success';
                                    elseif ($status === 'Shipped') $badge_class = 'bg-primary';
                                    elseif ($status === 'Processing') $badge_class = 'bg-info text-dark';
                                    elseif ($status === 'Pending') $badge_class = 'bg-warning text-dark';
                                    elseif ($status === 'Cancelled') $badge_class = 'bg-danger';
                                    ?>
                                    <tr>
                                        <td class="ps-3 fw-bold">
                                            <a href="sales/orders/order-details.php?id=<?php echo $order['id']; ?>" class="text-dark text-decoration-none"><?php echo htmlspecialchars($display_id); ?></a>
                                        </td>
                                        <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                        <td><strong class="text-success"><?php echo htmlspecialchars(formatAppCurrency($order['total'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                        <td><span class="badge <?php echo $badge_class; ?> px-2 py-1"><?php echo htmlspecialchars($status); ?></span></td>
                                    </tr>
                                    <?php
                                }
                            } else {
                                echo "<tr><td colspan='4' class='text-center text-muted py-3'>No orders found yet.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
include "layouts/footer.php"; 
?>