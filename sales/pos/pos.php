<?php
include "../../config.php";
$canCreatePosOrders = appUserCan('pos', 'create');
$canViewDashboard = appUserCan('dashboard');

$posStatus = $appSettings['pos_module_enabled'] ?? $appSettings['pos_enabled'] ?? '1';
if ($posStatus !== '1' || !appModuleEnabled('pos')) {
    sendAppError(403, 'The POS module is currently unavailable.', 'module');
}

$_SESSION['pos_checkout_csrf'] ??= bin2hex(random_bytes(32));
$posCurrencySymbol = json_encode(appCurrencySymbol(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$posTaxRate = (float) ($appSettings['pos_tax_rate'] ?? 0);

$cat_result = $conn->query("SELECT * FROM categories WHERE status = 'Active'");
$selected_cat = $_GET['category_id'] ?? '';
$search = trim($_GET['search'] ?? '');
// Aaj ki live counter sales aur orders count fetch karne ke liye
$today_date = date('Y-m-d');
$sales_query = $conn->query("SELECT SUM(total) as total_sales, COUNT(id) as total_orders FROM orders WHERE DATE(created_at) = '$today_date'");
$sales_data = $sales_query->fetch_assoc();
$counter_sales = $sales_data['total_sales'] ?? 0;
$today_orders_count = $sales_data['total_orders'] ?? 0;
$sql = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE 1=1";
$params = [];
$types = "";

if (!empty($selected_cat)) {
    $sql .= " AND p.category_id = ?";
    $params[] = $selected_cat;
    $types .= "i";
}

if (!empty($search)) {
    $sql .= " AND (p.name LIKE ? OR p.sku LIKE ?)";
    $params[] = "%" . $search . "%";
    $params[] = "%" . $search . "%";
    $types .= "ss";
}

$sql .= " ORDER BY p.id DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products_result = $stmt->get_result();

// Recent POS Orders fetch karne ke liye query
$recent_orders = $conn->query("SELECT * FROM orders ORDER BY id DESC LIMIT 5");
?>
<!DOCTYPE html><html lang="<?= htmlspecialchars(appLanguage(), ENT_QUOTES, 'UTF-8') ?>" dir="<?= appLanguage() === 'ur' ? 'rtl' : 'ltr' ?>" data-theme="<?= htmlspecialchars($currentTheme, ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard POS Counter & Billing</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <!-- Custom Style -->
    <link href="../../assets/css/style.css?v=<?= filemtime(__DIR__ . '/../../assets/css/style.css') ?>" rel="stylesheet">
    <script src="../../assets/js/dashboard-navigation.js" defer></script>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- HTML5-QRCODE Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
 <style>
    /* Poori screen ko lock karne ke liye taake page ka baqi hissa scroll na ho */
    html, body {
        height: 100vh;
        margin: 0;
        padding: 0;
        overflow: hidden !important;
        font-family: 'Inter', sans-serif; 
        background-color: var(--bg);
    }

    /* Main app container ko screen height ke mutabiq fix karna (Compact Padding) */
    .app-container-lock {
        height: 100vh;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        padding: 8px 15px !important;
    }

    /* Main content area - FIXED */
    .app-main {
        height: calc(100vh - 25px);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

   /* Products wala left container */
    .products-scroll-area {
        height: calc(100vh - 210px);
        overflow-y: auto;
        padding-right: 5px;
    }

    /* Products ka row container */
    #products-grid-container {
        align-content: flex-start !important;
        align-items: flex-start !important;
    }

    /* Product Card Professional Styling */
    .product-card {
        border: 1px solid var(--border);
        border-radius: 12px;
        transition: all 0.25s ease;
        background: #fff;
        cursor: pointer;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        height: 100%;
        box-shadow: 0 4px 12px rgba(20, 36, 61, 0.05);
    }
    
    .product-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 25px rgba(20, 36, 61, 0.12);
        border-color: var(--gold);
    }

    .product-card.pos-view-only {
        cursor: default;
    }

    .product-card.pos-view-only:hover {
        transform: none;
        box-shadow: 0 4px 12px rgba(20, 36, 61, 0.05);
        border-color: var(--border);
    }

    /* Image Wrapper */
    .product-img-wrapper {
        width: 100%;
        height: 150px;
        overflow: hidden;
        background-color: var(--bg);
        position: relative;
        margin: 0;
        padding: 0;
        border-top-left-radius: 12px;
        border-top-right-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .product-img-wrapper img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
        transition: transform 0.3s ease;
    }

    .product-card:hover .product-img-wrapper img {
        transform: scale(1.05); 
    }

    /* Category Pills */
    .category-pill {
        border-radius: 20px;
        padding: 5px 14px;
        font-weight: 500;
        font-size: 0.85rem;
        border: 1px solid var(--border);
        background-color: #ffffff;
        color: var(--dark);
        transition: all 0.2s ease-in-out;
        white-space: nowrap;
    }
    .category-pill:hover, .category-pill:focus {
        background-color: var(--gold) !important;
        color: var(--dark) !important;
        border-color: var(--gold) !important;
        box-shadow: none !important;
    }
    .category-pill.btn-dark {
        background-color: var(--dark) !important;
        color: #ffffff !important;
        border-color: var(--dark) !important;
    }

    /* Cart Sidebar Wrapper - Flex column layout */
    .cart-sidebar-wrapper {
        height: calc(100vh - 120px);
        display: flex;
        flex-direction: column;
    }

    #payment-method, .form-select {
        cursor: pointer !important;
    }
    
    /* Cart Sidebar Box - Fills available height cleanly without bottom gaps */
    .cart-sidebar {
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(20, 36, 61, 0.07);
        border: 1px solid var(--border);
        display: flex;
        flex-direction: column;
        flex-grow: 1;
        height: 100%;
        padding: 1rem !important;
        overflow: hidden;
    }
    
    /* Cart Items Scroll Area - Fluid and scrollable vertically */
    .cart-items-scroll {
        flex-grow: 1;
        overflow-y: auto;
        padding-right: 4px;
        min-height: 80px;
    }

    .btn-dashboard-action {
        background-color: var(--dark);
        border-color: var(--dark);
        color: #ffffff;
        font-weight: 600;
    }
    .btn-dashboard-action:hover {
        background-color: var(--gold);
        border-color: var(--gold);
        color: var(--dark);
    }

    .pos-header-left,
    .pos-header-actions,
    .pos-header-stats,
    .pos-stat-item,
    .pos-stat-item strong,
    .pos-stat-item span {
        min-width: 0;
    }

    .pos-header-stats {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: flex-end;
        gap: 8px;
        width: auto;
        max-width: 100%;
        min-width: 0;
        padding: 0;
        font-size: 12px;
    }

    .pos-header-actions {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        max-width: 100%;
        min-width: 0;
    }

    .pos-stat-item {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        flex: 0 1 auto;
        max-width: 100%;
        padding: 0.4rem 0.65rem;
        overflow: hidden;
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: 999px;
        white-space: nowrap;
    }

    .pos-stat-item strong,
    .pos-stat-item span {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pos-filter-actions {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .pos-category-tabs {
        display: flex;
        flex-wrap: nowrap;
        gap: 0.5rem;
        max-width: 100%;
        min-width: 0;
        overflow-x: auto;
        overflow-y: hidden;
        padding-bottom: 0.25rem;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior-x: contain;
        scrollbar-width: thin;
    }

    .pos-category-tabs .category-pill {
        flex: 0 0 auto;
    }

    #barcode-scanner-input {
        cursor: text !important;
        pointer-events: auto !important;
        position: relative;
        z-index: 10;
    }

    .input-group {
        position: relative;
        z-index: 5;
    }
    #interactive {
        width: 100%;
        min-height: 300px;
        background-color: #000;
        border-radius: 10px;
        position: relative;
    }
    #interactive video {
        width: 100% !important;
        height: auto !important;
        border-radius: 10px;
        object-fit: cover;
    }

@media (max-width: 991.98px) {
    html, body {
        height: auto !important;
        min-height: 100%;
        overflow-x: hidden !important;
        overflow-y: auto !important;
    }

    .app-container-lock,
    .app-main {
        height: auto;
        min-height: 100vh;
        overflow: visible;
    }

    .app-container-lock {
        padding: 12px;
    }

    .products-scroll-area {
        height: auto;
        max-height: 48vh;
        overflow-x: hidden;
    }

    .cart-sidebar-wrapper {
        height: auto;
        max-height: none;
        overflow: visible;
    }
}

@media (max-width: 767.98px) {
    .pos-header-actions {
        width: 100%;
    }

    .pos-header-stats {
        display: flex;
        flex-wrap: wrap;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        gap: 8px;
    }

    .pos-stat-item {
        min-width: 0;
        flex: 0 1 auto;
    }

    .pos-filter-actions {
        justify-content: flex-end;
    }

    .pos-filter-actions .btn {
        width: auto !important;
    }

    .pos-filter-actions .btn-dark {
        min-width: 5.5rem;
    }
}

@media (max-width: 419.98px) {
    .pos-stat-item { max-width: 100%; }
}
</style>
</head>
<body class="theme-<?= htmlspecialchars($currentTheme, ENT_QUOTES, 'UTF-8') ?>" data-theme="<?= htmlspecialchars($currentTheme, ENT_QUOTES, 'UTF-8') ?>" data-bs-theme="light">
<div class="app app-container-lock">
  <main class="app-main">
    <!-- Compact & Professional Top Header -->
    <header class="topbar mb-2 bg-white px-3 py-2 rounded-4 shadow-sm border d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 flex-shrink-0">
        <div class="pos-header-left d-flex align-items-center gap-3 flex-wrap">
            <?php if ($canViewDashboard): ?><a href="../../index.php" class="btn btn-sm btn-outline-dark px-3 py-1 rounded-pill fw-semibold text-decoration-none d-inline-flex align-items-center">
                <i class="bi bi-house-door-fill me-1"></i> Dashboard
            </a><?php endif; ?>
            <div class="vr text-muted opacity-25 d-none d-md-block"></div>
            <div>
                <h5 class="fw-bold text-dark mb-0"><?= htmlspecialchars($langData['pos_counter'] ?? 'POS & Order Billing') ?></h5>
                <p class="text-muted mb-0" style="font-size: 11px;">Manage inventory stock and complete fast checkouts.</p>
            </div>
        </div>

        <div class="pos-header-actions d-flex align-items-center flex-wrap gap-2">
            <div class="pos-header-stats">
                <div class="pos-stat-item">
                    <i class="bi bi-cash-stack text-success fs-6"></i>
                    <span class="text-muted">Sales:</span>
                    <strong class="text-dark"><?php echo htmlspecialchars(formatAppCurrency($counter_sales), ENT_QUOTES, 'UTF-8'); ?></strong>
                </div>
                <div class="pos-stat-item">
                    <i class="bi bi-receipt text-primary fs-6"></i>
                    <span class="text-muted">Orders:</span>
                    <strong class="text-dark"><?php echo $today_orders_count; ?></strong>
                </div>
                <div class="pos-stat-item text-dark fw-medium">
                    <i class="bi bi-clock text-warning fs-6"></i>
                    <span><?php echo date('d M Y, h:i A'); ?></span>
                </div>
            </div>
        </div>
    </header>

    <div class="row g-3 flex-grow-1 overflow-hidden">
      <!-- Left Section -->
      <div class="<?= $canCreatePosOrders ? 'col-lg-8' : 'col-12' ?> d-flex flex-column overflow-hidden">
        
        <div class="card border-0 shadow-sm p-2.5 mb-2 rounded-4 flex-shrink-0">
          <form method="GET" action="" class="row g-2 align-items-center">
            <div class="<?= $canCreatePosOrders ? 'col-xl-4 col-md-4' : 'col-xl-9 col-md-9' ?>">
              <div class="input-group bg-light rounded-pill overflow-hidden border">
                <span class="input-group-text bg-transparent border-0 ps-3"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="search" class="form-control border-0 bg-transparent shadow-none form-control-sm" placeholder="Search by name..." value="<?php echo htmlspecialchars($search); ?>">
              </div>
            </div>
            <?php if ($canCreatePosOrders): ?><div class="col-xl-5 col-md-5">
              <div class="input-group bg-light rounded-pill overflow-hidden border">
                <span class="input-group-text bg-transparent border-0 ps-3"><i class="bi bi-upc-scan text-primary"></i></span>
                <input type="text" id="barcode-scanner-input" class="form-control border-0 bg-transparent shadow-none form-control-sm" placeholder="Scan Barcode / SKU here..." autofocus>
                <!-- Camera Open Button -->
                <button type="button" class="btn btn-outline-primary border-0 px-3 py-1" onclick="openCameraScanner()" title="Scan with Camera">
                 <i class="bi bi-camera-fill fs-5"></i>
                </button>  
              </div>
            </div><?php endif; ?>
            <div class="col-xl-3 col-md-3 pos-filter-actions">
              <button type="submit" class="btn btn-dark w-100 rounded-pill fw-semibold btn-sm py-1.5">Filter</button>
              <?php if (!empty($search) || !empty($selected_cat)): ?>
                <a href="pos.php" class="btn btn-outline-secondary rounded-pill btn-sm px-3 py-1.5">Reset</a>
              <?php endif; ?>
            </div>
          </form>
        </div>

        <!-- Categories -->
        <div class="pos-category-tabs d-flex gap-2 pb-1 mb-2 flex-shrink-0">
          <a href="pos.php" class="btn btn-sm category-pill <?php echo empty($selected_cat) ? 'btn-dark' : ''; ?>">
            <i class="bi bi-grid-fill me-1"></i> All Products
          </a>
          <?php while ($cat = $cat_result->fetch_assoc()): ?>
            <a href="pos.php?category_id=<?php echo $cat['id']; ?>" class="btn btn-sm category-pill <?php echo ($selected_cat == $cat['id']) ? 'btn-dark' : ''; ?>">
              <?php echo htmlspecialchars($cat['name']); ?>
            </a>
          <?php endwhile; ?>
        </div>

        <!-- Products Grid -->
        <div class="products-scroll-area flex-grow-1">
          <div class="row g-3" id="products-grid-container">
            <?php if ($products_result && $products_result->num_rows > 0): ?>
              <?php while ($prod = $products_result->fetch_assoc()): ?>
                <div class="col-xl-4 col-md-6 col-sm-6 product-item-box" data-sku="<?php echo e(strtolower($prod['sku'] ?? '')); ?>" data-id="<?php echo (int) $prod['id']; ?>">
                  <div class="card product-card h-100 <?= $canCreatePosOrders ? '' : 'pos-view-only' ?>" <?php if ($canCreatePosOrders): ?>onclick="addToCart(<?php echo (int) $prod['id']; ?>, <?= htmlspecialchars(
                      json_encode((string) $prod['name'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT),
                      ENT_QUOTES,
                      'UTF-8'
                  ) ?>, <?php echo (float) $prod['price']; ?>, <?php echo (int) ($prod['stock'] ?? 0); ?>)"<?php endif; ?>>
                    
                    <div class="product-img-wrapper">
                      <img src="../../uploads/<?php echo htmlspecialchars($prod['image'] ?? 'default.jpg'); ?>" alt="Product" onerror="this.src='https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=300'">
                      <span class="position-absolute top-0 start-0 m-2 badge bg-dark opacity-75 small px-2 py-1" style="font-size: 10px;">
                        <?php echo htmlspecialchars($prod['category_name'] ?? 'Store'); ?>
                      </span>
                    </div>

                    <div class="card-body d-flex flex-column p-2.5">
                      <h6 class="fw-bold mb-1 text-truncate text-dark" style="font-size: 0.9rem;" title="<?php echo htmlspecialchars($prod['name']); ?>">
                        <?php echo htmlspecialchars($prod['name']); ?>
                      </h6>
                      
                      <div class="mb-2 d-flex justify-content-between align-items-center">
                        <span class="badge bg-light text-secondary border px-2 py-0.5" style="font-size: 11px;">
                          <i class="bi bi-box-seam me-1"></i> Stock: <strong class="text-dark product-stock-val"><?php echo $prod['stock'] ?? 0; ?></strong>
                        </span>
                        <?php if(!empty($prod['sku'])): ?>
                          <span class="text-muted" style="font-size: 10px;">SKU: <?php echo htmlspecialchars($prod['sku']); ?></span>
                        <?php endif; ?>
                      </div>

                      <div class="d-flex justify-content-between align-items-center mt-auto pt-2 border-top">
                        <div>
                          <span class="text-muted small d-block" style="font-size: 10px;">Price</span>
                          <span class="fw-bold text-dark" style="font-size: 0.95rem;"><?php echo htmlspecialchars(formatAppCurrency($prod['price']), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <?php if ($canCreatePosOrders): ?><button class="btn btn-sm btn-outline-dark px-2 py-0.5" style="font-size: 12px;" onclick="event.stopPropagation(); addToCart(<?php echo (int) $prod['id']; ?>, <?= htmlspecialchars(
                            json_encode((string) $prod['name'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>, <?php echo (float) $prod['price']; ?>, <?php echo (int) ($prod['stock'] ?? 0); ?>)">
                          <i class="bi bi-plus-lg"></i> Add
                        </button><?php endif; ?>
                      </div>

                    </div>
                  </div>
                </div>
              <?php endwhile; ?>
            <?php else: ?>
              <div class="col-12 text-center text-muted py-5 bg-white rounded-4 shadow-sm">
                <i class="bi bi-cart-x fs-1 text-muted mb-2"></i>
                <h5>No products found in store.</h5>
                <p class="small">Try searching for a different item or resetting filters.</p>
              </div>
            <?php endif; ?>
          </div>
        </div>

      </div>

      <!-- Right Section: Cart & Checkout -->
      <?php if ($canCreatePosOrders): ?><div class="col-lg-4">
        <div class="cart-sidebar-wrapper">
          <div class="cart-sidebar p-3">
            <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom flex-shrink-0">
              <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-cart3 text-dark me-2"></i> Order Cart</h6>
              <div class="d-flex align-items-center gap-2">
                <span class="badge counter-active-badge px-2.5 py-1 rounded-pill fw-semibold" style="font-size: 11px;">Counter Active</span>
                <button type="button" id="clear-cart-button" class="btn btn-sm btn-outline-danger cart-clear-button py-0 px-2" onclick="clearCart()" aria-label="Clear cart" title="Clear cart" disabled>
                  <i class="bi bi-trash3" aria-hidden="true"></i>
                </button>
              </div>
            </div>

            <div class="mb-2 flex-shrink-0">
              <div class="d-flex justify-content-between small text-muted mb-1" style="font-size: 11px;">
                <span>Customer Type</span>
                <span class="fw-bold text-dark">Walk-in Customer</span>
              </div>
              <div class="d-flex justify-content-between small text-muted mb-2" style="font-size: 11px;">
                <span>POS Terminal</span>
                <span class="fw-bold text-dark">Terminal #01</span>
              </div>
              <div class="mb-2">
                <label class="form-label small fw-bold text-dark mb-1" style="font-size: 11px;">Payment Method:</label>
                <select id="payment-method" class="form-select form-select-sm py-1">
                 <option value="Cash">Cash</option>
                 <option value="Online Transfer">Online Transfer</option>
                </select>
              </div>
            </div>

            <!-- Cart Items -->
            <div id="cart-items-container" class="cart-items-scroll mb-2 border-top border-bottom py-2">
              <div class="text-center text-muted py-4" id="empty-cart-text">
                <i class="bi bi-basket fs-3 text-black-50 mb-1 d-block"></i>
                <p class="small mb-0" style="font-size: 12px;">Your cart is empty. Click any product or scan barcode.</p>
              </div>
            </div>

            <!-- Totals & Checkout -->
            <div class="mt-auto pt-1 flex-shrink-0">
              <div class="mb-2">
                <div class="d-flex justify-content-between mb-1 text-muted small" style="font-size: 11px;">
                  <span>Subtotal</span>
                  <span id="sub-total" class="fw-semibold text-dark"><?= htmlspecialchars(appCurrencySymbol(), ENT_QUOTES, 'UTF-8') ?>0.00</span>
                </div>
                <div class="d-flex justify-content-between mb-1 text-muted small" style="font-size: 11px;">
                  <span>Estimated Tax (<?= htmlspecialchars(rtrim(rtrim(number_format($posTaxRate, 2), '0'), '.'), ENT_QUOTES, 'UTF-8') ?>%)</span>
                  <span id="tax-amount" class="fw-semibold text-dark"><?= htmlspecialchars(appCurrencySymbol(), ENT_QUOTES, 'UTF-8') ?>0.00</span>
                </div>
                <div class="d-flex justify-content-between fw-bold fs-6 text-dark mt-1 pt-1 border-top">
                  <span>Grand Total</span>
                  <span id="grand-total" class="text-success"><?= htmlspecialchars(appCurrencySymbol(), ENT_QUOTES, 'UTF-8') ?>0.00</span>
                </div>
              </div>

              <button type="button" id="posCheckoutButton" class="btn btn-dashboard-action w-100 py-2 fw-bold shadow-sm rounded-pill" onclick="confirmAndCheckout()" style="font-size: 13px;">
                <i class="bi bi-bag-check-fill me-2"></i> Complete & Print Invoice
              </button>
            </div>

          </div>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let cart = Array.isArray(window.__posNavigationCart) ? window.__posNavigationCart : [];
window.__posNavigationCart = cart;

const POS_CURRENCY_SYMBOL = <?= $posCurrencySymbol ?>;
const POS_TAX_RATE = <?= json_encode($posTaxRate) ?>;
const POS_CHECKOUT_CSRF = <?= json_encode($_SESSION['pos_checkout_csrf'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
let checkoutInProgress = window.__posCheckoutInProgress === true;
let checkoutRedirecting = false;

document.addEventListener("DOMContentLoaded", function() {
    const barcodeInput = document.getElementById('barcode-scanner-input');
    
    if (barcodeInput) {
        setTimeout(function() {
            barcodeInput.focus();
        }, 100);

        document.addEventListener('click', function(e) {
            if (!document.querySelector('.modal.show') && !e.target.closest('button, a, select, textarea')) {
                barcodeInput.focus();
            }
        });

        // Barcode Scanner Event with Prefix Cleaning (Fixes SKU: prefix issue)
        barcodeInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                let rawValue = this.value.trim();
                if (rawValue === '') return;

                // Clean barcode / SKU prefix if scanned with 'SKU:' or similar prefixes
                let barcode = rawValue.replace(/^sku[:\s]+/i, '').trim();

                fetchProductByBarcode(barcode);
                this.value = '';
            }
        });
    }
});

// Add to Cart Function with Toast Notification
function addToCart(id, name, price, stock) {
    if (stock <= 0) {
        Swal.fire({ icon: 'error', title: 'Out of Stock!', text: 'This product is currently out of stock.' });
        return;
    }

    let existingItem = cart.find(item => item.id === id);
    if (existingItem) {
        if (existingItem.qty < stock) {
            existingItem.qty += 1;
            Swal.fire({
                icon: 'success',
                title: 'Quantity Updated',
                text: name + ' quantity increased.',
                timer: 900,
                showConfirmButton: false,
                position: 'top-end',
                toast: true
            });
        } else {
            Swal.fire({ icon: 'warning', title: 'Stock Limit Reached!', text: 'Cannot add more than available stock.' });
            return;
        }
    } else {
        cart.push({ id: id, name: name, price: price, qty: 1, stock: stock });
        Swal.fire({
            icon: 'success',
            title: 'Added to Cart',
            text: name + ' added successfully.',
            timer: 900,
            showConfirmButton: false,
            position: 'top-end',
            toast: true
        });
    }
    renderCart();
}

// Remove from Cart with Toast Notification
function removeFromCart(id) {
    let item = cart.find(i => i.id === id);
    let itemName = item ? item.name : 'Item';
    
    cart = cart.filter(i => i.id !== id);
    renderCart();

    Swal.fire({
        icon: 'info',
        title: 'Removed',
        text: itemName + ' removed from cart.',
        timer: 900,
        showConfirmButton: false,
        position: 'top-end',
        toast: true
    });
}

function clearCart() {
    if (cart.length === 0) return;

    Swal.fire({
        icon: 'warning',
        title: 'Clear the cart?',
        text: 'All items will be removed from this order.',
        showCancelButton: true,
        confirmButtonText: 'Clear cart',
        confirmButtonColor: getComputedStyle(document.body).getPropertyValue('--button-primary').trim() || getComputedStyle(document.body).getPropertyValue('--dark').trim(),
        cancelButtonColor: '#718096'
    }).then((result) => {
        if (result.isConfirmed) {
            cart = [];
            renderCart();
            
            Swal.fire({
                icon: 'success',
                title: 'Cart Cleared',
                text: 'All items have been cleared from the cart.',
                timer: 1000,
                showConfirmButton: false,
                position: 'top-end',
                toast: true
            });
        }
    });
}

// Render Cart & Totals
function renderCart() {
    window.__posNavigationCart = cart;
    let container = document.getElementById('cart-items-container');
    if (!container) return;
    document.getElementById('clear-cart-button').disabled = cart.length === 0;
    
    if (cart.length === 0) {
        container.innerHTML = `
            <div class="text-center text-muted py-4" id="empty-cart-text">
                <i class="bi bi-basket fs-3 text-black-50 mb-1 d-block"></i>
                <p class="small mb-0" style="font-size: 12px;">Your cart is empty. Click any product or scan barcode.</p>
            </div>`;
        document.getElementById('sub-total').innerText = POS_CURRENCY_SYMBOL + '0.00';
        document.getElementById('tax-amount').innerText = POS_CURRENCY_SYMBOL + '0.00';
        document.getElementById('grand-total').innerText = POS_CURRENCY_SYMBOL + '0.00';
        return;
    }

    let html = '';
    let subtotal = 0;

    cart.forEach(item => {
        let itemTotal = item.price * item.qty;
        subtotal += itemTotal;

        html += `
            <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                <div style="max-width: 60%;">
                    <h6 class="mb-0 fw-bold text-dark text-truncate small">${item.name}</h6>
                    <small class="text-muted" style="font-size: 11px;">${POS_CURRENCY_SYMBOL}${item.price.toFixed(2)} × ${item.qty}</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-bold text-dark small">${POS_CURRENCY_SYMBOL}${itemTotal.toFixed(2)}</span>
                    <button type="button" class="btn btn-sm btn-outline-danger cart-remove-button py-0 px-1" onclick="removeFromCart(${item.id})" title="Delete item" aria-label="Delete item">
                        <i class="bi bi-trash3" aria-hidden="true" style="font-size: 11px;"></i>
                    </button>
                </div>
            </div>`;
    });

    let tax = subtotal * POS_TAX_RATE / 100;
    let grandTotal = subtotal + tax;

    container.innerHTML = html;
    document.getElementById('sub-total').innerText = POS_CURRENCY_SYMBOL + subtotal.toFixed(2);
    document.getElementById('tax-amount').innerText = POS_CURRENCY_SYMBOL + tax.toFixed(2);
    document.getElementById('grand-total').innerText = POS_CURRENCY_SYMBOL + grandTotal.toFixed(2);
}

// Checkout Confirm & Execute
function confirmAndCheckout() {
    if (checkoutInProgress) return;
    if (cart.length === 0) {
        Swal.fire({ icon: 'warning', title: 'Cart is empty!', text: 'Please add items before checkout.' });
        return;
    }

    Swal.fire({
        title: 'Confirm Checkout?',
        text: "Are you sure you want to complete this order and generate invoice?",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: getComputedStyle(document.body).getPropertyValue('--button-primary').trim() || getComputedStyle(document.body).getPropertyValue('--dark').trim(),
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, Complete Order!'
    }).then((result) => {
        if (result.isConfirmed) {
            executeCheckout();
        }
    });
}

function executeCheckout() {
    if (checkoutInProgress) return;

    let paymentMethod = document.getElementById('payment-method').value;
    const checkoutButton = document.getElementById('posCheckoutButton');
    const isGatewayPayment = ['Online Transfer', 'Card Payment'].includes(paymentMethod);
    checkoutInProgress = true;
    window.__posCheckoutInProgress = true;
    checkoutButton.disabled = true;

    Swal.fire({
        title: isGatewayPayment ? 'Processing Payment...' : 'Completing Order...',
        text: isGatewayPayment
            ? 'Redirecting to secure gateway, please do not refresh or close the page.'
            : 'Please wait while your order is completed.',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading()
    });

    fetch('checkout_process.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-Token': POS_CHECKOUT_CSRF
        },
        body: JSON.stringify({ cart: cart, payment_method: paymentMethod })
    })
    .then(async (response) => {
        const data = await response.json();
        if (!response.ok || data.status === 'error') {
            throw new Error(data.message || 'The checkout could not be completed.');
        }
        return data;
    })
    .then((data) => {
        if (data.status === 'success') {
            checkoutRedirecting = true;
            Swal.fire({
                icon: 'success',
                title: 'Order Completed!',
                text: 'Invoice generated successfully.',
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                window.location.href = '../orders/invoice.php?id=' + data.order_id;
            });
        } else if (data.status === 'stripe_redirect') {
            checkoutRedirecting = true;
            window.location.href = data.url;
        } else {
            throw new Error(data.message || 'Unknown error occurred on server.');
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Checkout Failed',
            text: error.message || 'A network error occurred. Please try again.'
        });
    })
    .finally(() => {
        if (checkoutRedirecting) return;
        checkoutInProgress = false;
        window.__posCheckoutInProgress = false;
        const activeCheckoutButton = document.getElementById('posCheckoutButton');
        if (activeCheckoutButton) {
            activeCheckoutButton.disabled = false;
        }
    });
}

let html5QrCode = null;

function openCameraScanner() {
    let myModal = new bootstrap.Modal(document.getElementById('cameraModal'));
    myModal.show();

    setTimeout(() => {
        html5QrCode = new Html5Qrcode("interactive");
        html5QrCode.start(
            { facingMode: "environment" },
            {
                fps: 10,
                qrbox: { width: 250, height: 150 }
            },
            (decodedText, decodedResult) => {
                let cleanText = decodedText.replace(/^sku[:\s]+/i, '').trim();
                document.getElementById('barcode-scanner-input').value = cleanText;
                stopCamera();
                bootstrap.Modal.getInstance(document.getElementById('cameraModal')).hide();
                fetchProductByBarcode(cleanText);
            },
            (errorMessage) => {}
        ).catch((err) => {
            console.error("Unable to start scanning.", err);
            Swal.fire({ icon: 'error', title: 'Camera Error', text: 'Could not access camera. Make sure permissions are allowed.' });
        });
    }, 500);
}

function stopCamera() {
    if (html5QrCode) {
        html5QrCode.stop().then((ignore) => {}).catch((err) => {});
    }
}

// Product fetch by barcode function with clean handling
function fetchProductByBarcode(barcode) {
    let cleanBarcode = barcode.replace(/^sku[:\s]+/i, '').trim();
    fetch('get_product_by_barcode.php?barcode=' + encodeURIComponent(cleanBarcode))
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && data.product) {
                let p = data.product;
                addToCart(parseInt(p.id), p.name, parseFloat(p.price), parseInt(p.stock ?? 0));
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Product Not Found!',
                    text: 'No product matched with SKU/Barcode: ' + cleanBarcode,
                    timer: 1500,
                    showConfirmButton: false
                });
            }
        })
        .catch(error => console.error('Fetch Error:', error));
}

const paymentMethodSelect = document.getElementById('payment-method');
const checkoutButton = document.getElementById('posCheckoutButton');
if (checkoutButton) {
    checkoutButton.disabled = checkoutInProgress;
}
if (paymentMethodSelect) {
    paymentMethodSelect.value = window.__posNavigationPaymentMethod || paymentMethodSelect.value;
    window.__posNavigationPaymentMethod = paymentMethodSelect.value;
    paymentMethodSelect.addEventListener('change', function () {
        window.__posNavigationPaymentMethod = this.value;
    });
}
if (document.getElementById('cart-items-container')) {
    renderCart();
}
</script>

<?php if (isset($_SESSION['swal_success'])): ?>
<script>
    Swal.fire({ icon: 'success', title: 'Success!', text: <?= json_encode(
        (string) $_SESSION['swal_success'],
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
    ) ?>, timer: 2000, showConfirmButton: false });
</script>
<?php unset($_SESSION['swal_success']); endif; ?>

<?php if ($canCreatePosOrders): ?><!-- Camera Scanner Modal -->
<div class="modal fade" id="cameraModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <h5 class="fw-bold text-dark"><i class="bi bi-camera me-2 text-primary"></i> Scan Barcode via Camera</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="stopCamera()"></button>
      </div>
      <div class="modal-body text-center">
        <div id="interactive" style="width: 100%; max-height: 400px; overflow: hidden; border-radius: 10px;"></div>
        <p class="text-muted small mt-2">Place the barcode in front of your camera.</p>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
</body>
</html>