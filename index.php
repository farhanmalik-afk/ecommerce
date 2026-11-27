<?php
include "config.php";
include "lang.php"; // Load language translations

// Active Language & Dynamic Threshold Setup
$currentLang = $appSettings['language'] ?? 'en';
$langData = $translations[$currentLang] ?? $translations['en'];
$lowStockLimit = intval($appSettings['low_stock_threshold'] ?? 5);
$storeName = $appSettings['store_name'] ?? 'E-Commerce Admin';
$canViewProducts = appUserCan('products');
$canViewCustomers = appUserCan('customers');
$canViewOrders = appUserCan('orders');
$canViewInventory = appUserCan('inventory');
$canViewPayments = appUserCan('payments');
$canCreateOrders = appUserCan('orders', 'create');
$canAdjustStock = appUserCan('stock_management', 'edit');

// Fetch Dynamic Stats from Database
$total_products = 0;
$total_customers = 0;
$total_orders = 0;
$total_payments = 0;
$delivered_orders = 0;
$total_inventory_items = 0;
$low_stock_count = 0;
$active_products = 0;
$active_customers = 0;

if ($canViewProducts) {
    $prod_query = $conn->query("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active FROM products WHERE is_deleted = 0");
    if ($prod_query) {
        $prod_data = $prod_query->fetch_assoc();
        $total_products = $prod_data['total'] ?? 0;
        $active_products = $prod_data['active'] ?? 0;
    }
}

if ($canViewCustomers) {
    $cust_query = $conn->query("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active FROM customers WHERE is_deleted = 0");
    if ($cust_query) {
        $cust_data = $cust_query->fetch_assoc();
        $total_customers = $cust_data['total'] ?? 0;
        $active_customers = $cust_data['active'] ?? 0;
    }
}

if ($canViewOrders) {
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
}

if ($canViewInventory) {
    $inv_stats = $conn->query("SELECT SUM(quantity) as total_qty, SUM(CASE WHEN quantity <= $lowStockLimit THEN 1 ELSE 0 END) as low_stock FROM inventory");
    if ($inv_stats) {
        $inv_data = $inv_stats->fetch_assoc();
        $total_inventory_items = $inv_data['total_qty'] ?? 0;
        $low_stock_count = $inv_data['low_stock'] ?? 0;
    }
}

if ($canViewPayments) {
    $payments_query = $conn->query("SELECT COUNT(*) as total FROM payments");
    if ($payments_query) {
        $payments_data = $payments_query->fetch_assoc();
        $total_payments = $payments_data['total'] ?? 0;
    }
}

// Maintenance Mode Check for Visitors
if (isset($appSettings['maintenance_mode']) && $appSettings['maintenance_mode'] === '1') {
    if (empty($_SESSION['admin_logged_in'])) {
        http_response_code(503);
        echo '<div style="text-align:center; padding:100px; font-family:sans-serif; background:#F5F0E6; color:#14243D;">
                <h1 style="font-size:36px; margin-bottom:15px;">🚧 ' . htmlspecialchars($storeName) . ' - Under Maintenance</h1>
                <p style="font-size:18px; color:#78869A;">We are currently performing scheduled upgrades. Please check back soon!</p>
              </div>';
        exit();
    }
}

$pageTitle = $langData['dashboard'] . ' - ' . $storeName;
include "layouts/header.php";
?>
<div class="dashboard-main">
    <!-- Topbar & Page Heading -->
    <section class="page-head mb-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <span class="badge bg-warning text-dark mb-1"><?= htmlspecialchars($storeName) ?></span>
            <h1><?= htmlspecialchars($langData['dashboard']) ?></h1>
            <p class="text-muted"><?= htmlspecialchars($langData['dashboard_sub']) ?></p>
        </div>
        <?php if ($canCreateOrders || $canViewOrders || $canViewProducts || $canViewCustomers || $canViewInventory || $canViewPayments): ?>
        <div class="dashboard-quick-actions d-flex flex-wrap gap-2">
            <?php if ($canCreateOrders): ?>
            <a href="sales/orders/add-order.php" class="btn btn-outline-dark btn-sm">
                <i class="bi bi-plus-lg me-1"></i> <?= htmlspecialchars($langData['add_order']) ?>
            </a>
            <?php endif; ?>
            <?php if ($canViewOrders): ?>
            <a href="sales/orders/orders.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-bag-check me-1"></i> Orders
            </a>
            <?php endif; ?>
            <?php if ($canViewProducts): ?>
            <a href="products/products.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-box-seam me-1"></i> Products
            </a>
            <?php endif; ?>
            <?php if ($canViewCustomers): ?>
            <a href="customers/customers.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-people me-1"></i> Customers
            </a>
            <?php endif; ?>
            <?php if ($canViewInventory): ?>
            <a href="inventory/inventory.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-boxes me-1"></i> <?= htmlspecialchars($langData['manage_inventory']) ?>
            </a>
            <?php endif; ?>
            <?php if ($canViewPayments): ?>
            <a href="sales/payments/payments.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-credit-card me-1"></i> Payments
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </section>

    <!-- Stat Cards Row -->
    <?php if ($canViewProducts || $canViewCustomers || $canViewInventory || $canViewPayments): ?>
    <div class="row g-3 mb-4 dashboard-stats">
        <!-- Total Products Card -->
        <?php if ($canViewProducts): ?>
        <div class="col-xl-3 col-sm-6">
            <a href="products/products.php" class="card stat-card border-0 shadow-sm text-reset text-decoration-none" aria-label="View products">
                <div class="card-body">
                    <div>
                        <p class="stat-label"><?= htmlspecialchars($langData['total_products']) ?></p>
                        <h3 class="stat-value"><?php echo number_format($total_products); ?></h3>
                    </div>
                    <div class="stat-icon bg-a">
                        <i class="bi bi-box-seam"></i>
                    </div>
                </div>
                <div class="stat-foot">
                    <span class="text-up"><i class="bi bi-check-circle-fill"></i> <?php echo $active_products; ?> Active</span> in catalog
                </div>
            </a>
        </div>
        <?php endif; ?>

        <!-- Total Customers Card -->
        <?php if ($canViewCustomers): ?>
        <div class="col-xl-3 col-sm-6">
            <a href="customers/customers.php" class="card stat-card border-0 shadow-sm text-reset text-decoration-none" aria-label="View customers">
                <div class="card-body">
                    <div>
                        <p class="stat-label"><?= htmlspecialchars($langData['total_customers']) ?></p>
                        <h3 class="stat-value"><?php echo number_format($total_customers); ?></h3>
                    </div>
                    <div class="stat-icon bg-a">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>
                <div class="stat-foot">
                    <span class="text-up"><i class="bi bi-person-check-fill"></i> <?php echo $active_customers; ?> Active</span> registered
                </div>
            </a>
        </div>
        <?php endif; ?>

        <!-- Inventory / Stock Card -->
        <?php if ($canViewInventory): ?>
        <div class="col-xl-3 col-sm-6">
            <a href="inventory/inventory.php" class="card stat-card border-0 shadow-sm text-reset text-decoration-none" aria-label="View inventory">
                <div class="card-body">
                    <div>
                        <p class="stat-label"><?= htmlspecialchars($langData['total_stock_units']) ?></p>
                        <h3 class="stat-value"><?php echo number_format($total_inventory_items); ?></h3>
                    </div>
                    <div class="stat-icon bg-a">
                        <i class="bi bi-boxes"></i>
                    </div>
                </div>
                <div class="stat-foot">
                    <span class="text-danger fw-bold"><i class="bi bi-exclamation-triangle-fill"></i> <?php echo $low_stock_count; ?></span> Low stock items (≤ <?= $lowStockLimit ?>)
                </div>
            </a>
        </div>
        <?php endif; ?>

        <?php if ($canViewPayments): ?>
        <div class="col-xl-3 col-sm-6">
            <a href="sales/payments/payments.php" class="card stat-card border-0 shadow-sm text-reset text-decoration-none" aria-label="View payments">
                <div class="card-body">
                    <div>
                        <p class="stat-label">Payments</p>
                        <h3 class="stat-value"><?php echo number_format($total_payments); ?></h3>
                    </div>
                    <div class="stat-icon bg-a">
                        <i class="bi bi-credit-card"></i>
                    </div>
                </div>
                <div class="stat-foot">
                    Recorded transactions
                </div>
            </a>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Order Summary Cards -->
    <?php if ($canViewOrders): ?>
    <div class="row g-3 mb-4 dashboard-order-stats">
        <!-- Total Orders Card -->
        <div class="col-xl-6 col-sm-6">
            <a href="sales/orders/orders.php" class="card stat-card border-0 shadow-sm text-reset text-decoration-none" aria-label="View orders">
                <div class="card-body">
                    <div>
                        <p class="stat-label"><?= htmlspecialchars($langData['total_orders']) ?></p>
                        <h3 class="stat-value"><?php echo number_format($total_orders); ?></h3>
                    </div>
                    <div class="stat-icon bg-a">
                        <i class="bi bi-cart-fill"></i>
                    </div>
                </div>
                <div class="stat-foot">
                    Customer sales recorded
                </div>
            </a>
        </div>

        <!-- Delivered Orders Card -->
        <div class="col-xl-6 col-sm-6">
            <a href="sales/orders/orders.php" class="card stat-card border-0 shadow-sm text-reset text-decoration-none" aria-label="View delivered orders">
                <div class="card-body">
                    <div>
                        <p class="stat-label"><?= htmlspecialchars($langData['delivered_orders']) ?></p>
                        <h3 class="stat-value"><?php echo number_format($delivered_orders); ?></h3>
                    </div>
                    <div class="stat-icon bg-a">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                </div>
                <div class="stat-foot">
                    Successfully completed orders
                </div>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Dashboard Tables & Operations -->
    <?php if ($canViewProducts || $canViewCustomers || $canViewInventory || $canViewOrders): ?>
    <div class="row g-4 mb-4 align-items-start">
        <?php if ($canViewProducts || $canViewInventory): ?>
        <div class="<?= ($canViewCustomers || $canViewOrders) ? 'col-lg-7' : 'col-12' ?> d-flex flex-column gap-4">
            <?php if ($canViewProducts): ?>
            <div class="card border-0 shadow-sm table-card dashboard-panel">
                <div class="table-card-header d-flex justify-content-between align-items-center p-3 border-bottom">
                    <h6 class="m-0 font-weight-bold"><?= htmlspecialchars($langData['recent_products']) ?></h6>
                    <a href="products/products.php" class="btn btn-sm btn-link text-decoration-none p-0"><?= htmlspecialchars($langData['view_all']) ?></a>
                </div>
                <div class="table-responsive" style="overflow-x: auto; max-width: 100%;">
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
                                        : "uploads/default-product.png"; 
                                    $status_class = ($p['status'] == 'Active') ? 'bg-success' : (($p['status'] == 'Draft') ? 'bg-warning text-dark' : 'bg-secondary');
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
            <?php endif; ?>

            <?php if ($canViewInventory): ?>
            <div class="card border-0 shadow-sm dashboard-panel">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                    <h6 class="m-0 font-weight-bold text-dark"><i class="bi bi-exclamation-triangle-fill text-warning me-1"></i> <?= htmlspecialchars($langData['low_stock_warning']) ?></h6>
                    <a href="inventory/inventory.php" class="btn btn-sm btn-outline-dark"><?= htmlspecialchars($langData['view_all']) ?></a>
                </div>
                <div class="table-responsive" style="overflow-x: auto; max-width: 100%;">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-3">Product Name</th>
                                <th>Quantity</th>
                                <?php if ($canAdjustStock): ?><th class="text-end pe-3">Action</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $low_stock_query = $conn->query("SELECT p.name, i.quantity, p.id as product_id
                                                       FROM inventory i
                                                       JOIN products p ON i.product_id = p.id
                                                       WHERE i.quantity <= $lowStockLimit
                                                       ORDER BY i.quantity ASC LIMIT 5");
                            if ($low_stock_query && $low_stock_query->num_rows > 0) {
                                while ($ls = $low_stock_query->fetch_assoc()) {
                                    ?>
                                    <tr>
                                        <td class="ps-3 fw-bold"><?php echo htmlspecialchars($ls['name']); ?></td>
                                        <td><span class="badge bg-danger"><?php echo $ls['quantity']; ?> units</span></td>
                                        <?php if ($canAdjustStock): ?><td class="text-end pe-3">
                                            <a href="inventory/stock-adjust.php?product_id=<?php echo $ls['product_id']; ?>" class="btn btn-sm btn-dark py-0 px-2" style="font-size: 11px;">Restock</a>
                                        </td><?php endif; ?>
                                    </tr>
                                    <?php
                                }
                            } else {
                                echo "<tr><td colspan='" . ($canAdjustStock ? '3' : '2') . "' class='text-center text-muted py-3'>All products have sufficient stock!</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($canViewCustomers || $canViewOrders): ?>
        <div class="<?= ($canViewProducts || $canViewInventory) ? 'col-lg-5' : 'col-12' ?> d-flex flex-column gap-4">
            <?php if ($canViewCustomers): ?>
            <div class="card border-0 shadow-sm table-card dashboard-panel">
                <div class="table-card-header d-flex justify-content-between align-items-center p-3 border-bottom">
                    <h6 class="m-0 font-weight-bold"><?= htmlspecialchars($langData['recent_customers']) ?></h6>
                    <a href="customers/customers.php" class="btn btn-sm btn-link text-decoration-none p-0"><?= htmlspecialchars($langData['view_all']) ?></a>
                </div>
                <div class="table-responsive" style="overflow-x: auto; max-width: 100%;">
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
            <?php endif; ?>

            <?php if ($canViewOrders): ?>
            <div class="card border-0 shadow-sm dashboard-panel">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                    <h6 class="m-0 font-weight-bold text-dark"><?= htmlspecialchars($langData['recent_orders']) ?></h6>
                    <a href="sales/orders/orders.php" class="btn btn-sm btn-outline-secondary"><?= htmlspecialchars($langData['view_all']) ?></a>
                </div>
                <div class="table-responsive" style="overflow-x: auto; max-width: 100%;">
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
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php 
include "layouts/footer.php"; 
?>