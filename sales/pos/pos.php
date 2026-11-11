<?php
session_start();
include "../../config.php";

if (($appSettings['pos_enabled'] ?? '1') !== '1') {
    http_response_code(403);
    exit('The POS module is disabled in Admin Settings.');
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
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(($appSettings['language'] ?? 'en') === 'ur' ? 'ur' : 'en', ENT_QUOTES, 'UTF-8') ?>"<?= ($appSettings['language'] ?? 'en') === 'ur' ? ' dir="rtl"' : '' ?>>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard POS Counter & Billing</title>
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS & Order Billing</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <!-- Custom Style -->
    <link href="../../assets/css/style.css" rel="stylesheet">
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- ⭐️ YAHAN HTML5-QRCODE LIBRARY ADD KAREIN ⭐️ -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
</head>
  <style>
    /* Poori screen ko lock karne ke liye taake page ka baqi hissa scroll na ho */
    html, body {
        height: 100vh;
        margin: 0;
        padding: 0;
        overflow: hidden !important;
        font-family: 'Inter', sans-serif; 
        background-color: #f5f0e6; 
    }

    /* Main app container ko screen height ke mutabiq fix karna */
    .app-container-lock {
        height: 100vh;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        padding: 15px 20px;
    }

    /* Main content area - FIXED */
    .app-main {
        height: calc(100vh - 40px);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    /* Products wala left column scrollable banana - FIXED */
    .products-scroll-area {
        height: calc(100vh - 240px);
        overflow-y: auto;
        padding-right: 5px;
    }

    /* Product Card Professional Styling */
    .product-card {
        border: 1px solid #e8e1d5;
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
        border-color: #c6a15b;
    }

    /* Image Wrapper */
    .product-img-wrapper {
        width: 100%;
        height: 160px;
        overflow: hidden;
        background-color: #f5f0e6;
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
        padding: 6px 16px;
        font-weight: 500;
        font-size: 0.875rem;
        border: 1px solid #e8e1d5;
        background-color: #ffffff;
        color: #14243d;
        transition: all 0.2s ease-in-out;
        white-space: nowrap;
    }
    .category-pill:hover, .category-pill:focus {
        background-color: #c6a15b !important;
        color: #14243d !important;
        border-color: #c6a15b !important;
        box-shadow: none !important;
    }
    .category-pill.btn-dark {
        background-color: #14243d !important;
        color: #ffffff !important;
        border-color: #14243d !important;
    }

    /* Professional Scrollable Cart Sidebar Layout - FIXED */
    .cart-sidebar-wrapper {
        height: calc(100vh - 160px);
        overflow-y: auto;
        padding-right: 5px;
    }

    #payment-method, .form-select {
        cursor: pointer !important;
    }
    
    .cart-sidebar {
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(20, 36, 61, 0.07);
        border: 1px solid #e8e1d5;
        display: flex;
        flex-direction: column;
        padding: 1.25rem !important;
    }
    
    .cart-items-scroll {
        max-height: none; 
        overflow-y: visible;
    }

    .btn-dashboard-action {
        background-color: #14243d;
        border-color: #14243d;
        color: #ffffff;
        font-weight: 600;
    }
    .btn-dashboard-action:hover {
        background-color: #c6a15b;
        border-color: #c6a15b;
        color: #14243d;
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
    background-color: #000; /* Jab tak camera start ho, black background dikhega */
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
</style>
</head>
<body class="theme-<?= htmlspecialchars($appSettings['theme'] ?? 'luxury', ENT_QUOTES, 'UTF-8') ?>" data-bs-theme="<?= ($appSettings['theme'] ?? '') === 'dark' ? 'dark' : 'light' ?>">

<div class="app app-container-lock">
  <main class="app-main">
    <header class="topbar mb-2 bg-white p-3 rounded-4 shadow-sm border d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <a href="../../index.php" class="btn btn-sm btn-outline-dark px-3 py-1.5 rounded-pill fw-semibold text-decoration-none d-inline-flex align-items-center">
                <i class="bi bi-house-door-fill me-1"></i> Dashboard
            </a>
            <div class="vr text-muted opacity-25 d-none d-md-block"></div>
            <div>
                <h1 class="fw-bold text-dark fs-4 mb-0">POS &amp; Order Billing</h1>
                <p class="text-muted small mb-0">Manage inventory stock and complete fast checkouts.</p>
            </div>
        </div>

        <div class="d-flex align-items-center flex-wrap gap-2">
            <div class="bg-light border px-3 py-1 rounded-pill small d-flex align-items-center gap-2 shadow-sm">
                <i class="bi bi-cash-stack text-success"></i>
                <span class="text-muted">Sales:</span>
                <strong class="text-dark"><?php echo htmlspecialchars(formatAppCurrency($counter_sales), ENT_QUOTES, 'UTF-8'); ?></strong>
            </div>

            <div class="bg-light border px-3 py-1 rounded-pill small d-flex align-items-center gap-2 shadow-sm">
                <i class="bi bi-receipt text-primary"></i>
                <span class="text-muted">Orders:</span>
                <strong class="text-dark"><?php echo $today_orders_count; ?></strong>
            </div>

            <div class="badge bg-dark text-white px-3 py-2 rounded-pill small fw-normal d-flex align-items-center gap-1 shadow-sm">
                <i class="bi bi-clock text-warning"></i> <?php echo date('d M Y, h:i A'); ?>
            </div>
        </div>
    </header>

    <div class="row g-4 flex-grow-1 overflow-hidden">
      <!-- Left Section -->
      <div class="col-lg-8 d-flex flex-column overflow-hidden">
        
        <div class="card border-0 shadow-sm p-3 mb-3 rounded-4 flex-shrink-0">
          <form method="GET" action="" class="row g-2 align-items-center">
            <div class="col-xl-4 col-md-4">
              <div class="input-group bg-light rounded-pill overflow-hidden border">
                <span class="input-group-text bg-transparent border-0 ps-3"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="search" class="form-control border-0 bg-transparent shadow-none" placeholder="Search by name..." value="<?php echo htmlspecialchars($search); ?>">
              </div>
            </div>
            <div class="col-xl-5 col-md-5">
              <div class="input-group bg-light rounded-pill overflow-hidden border">
                <span class="input-group-text bg-transparent border-0 ps-3"><i class="bi bi-upc-scan text-primary"></i></span>
                <input type="text" id="barcode-scanner-input" class="form-control border-0 bg-transparent shadow-none" placeholder="Scan Barcode / SKU here..." autofocus>
                <!-- Camera Open Button -->
                <button type="button" class="btn btn-outline-primary border-0 px-3" onclick="openCameraScanner()" title="Scan with Camera">
                 <i class="bi bi-camera-fill fs-5"></i>
                </button>  
            </div>
            </div>
            <div class="col-xl-3 col-md-3 d-flex gap-2">
              <button type="submit" class="btn btn-dark w-100 rounded-pill fw-semibold btn-sm py-2">Filter</button>
              <?php if (!empty($search) || !empty($selected_cat)): ?>
                <a href="pos.php" class="btn btn-outline-secondary rounded-pill btn-sm px-3 py-2">Reset</a>
              <?php endif; ?>
            </div>
          </form>
        </div>

        <!-- Categories -->
        <div class="d-flex gap-2 overflow-auto pb-2 mb-3 flex-shrink-0">
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
                <div class="col-xl-4 col-md-6 col-sm-6 product-item-box" data-sku="<?php echo strtolower($prod['sku'] ?? ''); ?>" data-id="<?php echo $prod['id']; ?>">
                  <div class="card product-card h-100" onclick="addToCart(<?php echo $prod['id']; ?>, '<?php echo addslashes($prod['name']); ?>', <?php echo $prod['price']; ?>, <?php echo $prod['stock'] ?? 0; ?>)">
                    
                    <div class="product-img-wrapper">
                      <img src="../../uploads/<?php echo htmlspecialchars($prod['image'] ?? 'default.jpg'); ?>" alt="Product" onerror="this.src='https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=300'">
                      <span class="position-absolute top-0 start-0 m-2 badge bg-dark opacity-75 small px-2 py-1">
                        <?php echo htmlspecialchars($prod['category_name'] ?? 'Store'); ?>
                      </span>
                    </div>

                    <div class="card-body d-flex flex-column p-3">
                      <h6 class="fw-bold mb-1 text-truncate text-dark" title="<?php echo htmlspecialchars($prod['name']); ?>">
                        <?php echo htmlspecialchars($prod['name']); ?>
                      </h6>
                      
                      <div class="mb-2 d-flex justify-content-between align-items-center">
                        <span class="badge bg-light text-secondary border px-2 py-1 small">
                          <i class="bi bi-box-seam me-1"></i> Stock: <strong class="text-dark product-stock-val"><?php echo $prod['stock'] ?? 0; ?></strong>
                        </span>
                        <?php if(!empty($prod['sku'])): ?>
                          <span class="text-muted" style="font-size: 11px;">SKU: <?php echo htmlspecialchars($prod['sku']); ?></span>
                        <?php endif; ?>
                      </div>

                      <div class="d-flex justify-content-between align-items-center mt-auto pt-2 border-top">
                        <div>
                          <span class="text-muted small d-block" style="font-size: 11px;">Price</span>
                          <span class="fw-bold text-dark fs-6"><?php echo htmlspecialchars(formatAppCurrency($prod['price']), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <button class="btn btn-sm btn-outline-dark px-2 py-1" onclick="event.stopPropagation(); addToCart(<?php echo $prod['id']; ?>, '<?php echo addslashes($prod['name']); ?>', <?php echo $prod['price']; ?>, <?php echo $prod['stock'] ?? 0; ?>)">
                          <i class="bi bi-plus-lg"></i> Add
                        </button>
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
      <div class="col-lg-4">
        <div class="cart-sidebar-wrapper">
          <div class="cart-sidebar p-4">
            <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom flex-shrink-0">
              <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-cart3 text-dark me-2"></i> Order Cart</h5>
              <div class="d-flex align-items-center gap-2">
                <span class="badge counter-active-badge px-3 py-2 rounded-pill fw-semibold">Counter Active</span>
                <button type="button" id="clear-cart-button" class="btn btn-sm btn-outline-danger cart-clear-button" onclick="clearCart()" aria-label="Clear cart" title="Clear cart" disabled>
                  <i class="bi bi-trash3" aria-hidden="true"></i>
                </button>
              </div>
            </div>

            <div class="mb-3 flex-shrink-0">
              <div class="d-flex justify-content-between small text-muted mb-1">
                <span>Customer Type</span>
                <span class="fw-bold text-dark">Walk-in Customer</span>
              </div>
              <div class="d-flex justify-content-between small text-muted mb-2">
                <span>POS Terminal</span>
                <span class="fw-bold text-dark">Terminal #01</span>
              </div>
              <div class="mb-2">
              <label class="form-label small fw-bold text-dark mb-1">Payment Method:</label>
              <select id="payment-method" class="form-select form-select-sm">
               <option value="Cash">Cash</option>
               <option value="Online Transfer">Online Transfer</option>
               
              </select>
              </div>
            </div>

            <!-- Cart Items -->
            <div id="cart-items-container" class="cart-items-scroll mb-3 border-top border-bottom py-2">
              <div class="text-center text-muted py-5" id="empty-cart-text">
                <i class="bi bi-basket fs-2 text-black-50 mb-1 d-block"></i>
                <p class="small mb-0">Your cart is empty. Click any product or scan barcode to add items.</p>
              </div>
            </div>

            <!-- Totals & Checkout -->
            <div class="mt-auto pt-2 flex-shrink-0">
              <div class="mb-3">
                <div class="d-flex justify-content-between mb-2 text-muted small">
                  <span>Subtotal</span>
                  <span id="sub-total" class="fw-semibold text-dark"><?= htmlspecialchars(appCurrencySymbol(), ENT_QUOTES, 'UTF-8') ?>0.00</span>
                </div>
                <div class="d-flex justify-content-between mb-2 text-muted small">
                  <span>Estimated Tax (<?= htmlspecialchars(rtrim(rtrim(number_format($posTaxRate, 2), '0'), '.'), ENT_QUOTES, 'UTF-8') ?>%)</span>
                  <span id="tax-amount" class="fw-semibold text-dark"><?= htmlspecialchars(appCurrencySymbol(), ENT_QUOTES, 'UTF-8') ?>0.00</span>
                </div>
                <div class="d-flex justify-content-between fw-bold fs-5 text-dark mt-2 pt-2 border-top">
                  <span>Grand Total</span>
                  <span id="grand-total" class="text-success"><?= htmlspecialchars(appCurrencySymbol(), ENT_QUOTES, 'UTF-8') ?>0.00</span>
                </div>
              </div>

              <button type="button" id="posCheckoutButton" class="btn btn-dashboard-action w-100 py-2.5 fw-bold shadow-sm rounded-pill" onclick="confirmAndCheckout()">
                <i class="bi bi-bag-check-fill me-2"></i> Complete & Print Invoice
              </button>
            </div>

          </div>
        </div>
      </div>

    </div>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let cart = [];

const POS_CURRENCY_SYMBOL = <?= $posCurrencySymbol ?>;
const POS_TAX_RATE = <?= json_encode($posTaxRate) ?>;
const POS_CHECKOUT_CSRF = <?= json_encode($_SESSION['pos_checkout_csrf'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
let checkoutInProgress = false;
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

        // Barcode Scanner Event (Fixed & Working)
        barcodeInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                let barcode = this.value.trim();
                if (barcode === '') return;

                fetch('get_product_by_barcode.php?barcode=' + encodeURIComponent(barcode))
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success' && data.product) {
                            let p = data.product;
                            addToCart(parseInt(p.id), p.name, parseFloat(p.price), parseInt(p.stock ?? 0));
                            this.value = '';
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Product Not Found!',
                                text: 'No product matched with SKU/Barcode: ' + barcode,
                                timer: 1500,
                                showConfirmButton: false
                            });
                            this.value = '';
                        }
                    })
                    .catch(error => {
                        console.error('Fetch Error:', error);
                        this.value = '';
                    });
            }
        });
    }
});

// Add to Cart Function
function addToCart(id, name, price, stock) {
    if (stock <= 0) {
        Swal.fire({ icon: 'error', title: 'Out of Stock!', text: 'This product is currently out of stock.' });
        return;
    }

    let existingItem = cart.find(item => item.id === id);
    if (existingItem) {
        if (existingItem.qty < stock) {
            existingItem.qty += 1;
        } else {
            Swal.fire({ icon: 'warning', title: 'Stock Limit Reached!', text: 'Cannot add more than available stock.' });
            return;
        }
    } else {
        cart.push({ id: id, name: name, price: price, qty: 1, stock: stock });
    }
    renderCart();
}

// Remove from Cart
function removeFromCart(id) {
    cart = cart.filter(item => item.id !== id);
    renderCart();
}

function clearCart() {
    if (cart.length === 0) return;

    Swal.fire({
        icon: 'warning',
        title: 'Clear the cart?',
        text: 'All items will be removed from this order.',
        showCancelButton: true,
        confirmButtonText: 'Clear cart',
        confirmButtonColor: '#14243d',
        cancelButtonColor: '#718096'
    }).then((result) => {
        if (result.isConfirmed) {
            cart = [];
            renderCart();
        }
    });
}

// Render Cart & Totals (Fixed missing operators *)
function renderCart() {
    let container = document.getElementById('cart-items-container');
    document.getElementById('clear-cart-button').disabled = cart.length === 0;
    
    if (cart.length === 0) {
        container.innerHTML = `
            <div class="text-center text-muted py-5" id="empty-cart-text">
                <i class="bi bi-basket fs-2 text-black-50 mb-1 d-block"></i>
                <p class="small mb-0">Your cart is empty. Click any product or scan barcode to add items.</p>
            </div>`;
        document.getElementById('sub-total').innerText = POS_CURRENCY_SYMBOL + '0.00';
        document.getElementById('tax-amount').innerText = POS_CURRENCY_SYMBOL + '0.00';
        document.getElementById('grand-total').innerText = POS_CURRENCY_SYMBOL + '0.00';
        return;
    }

    let html = '';
    let subtotal = 0;

    cart.forEach(item => {
        let itemTotal = item.price * item.qty; // Fixed missing *
        subtotal += itemTotal;

        html += `
            <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                <div style="max-width: 60%;">
                    <h6 class="mb-0 fw-bold text-dark text-truncate small">${item.name}</h6>
                    <small class="text-muted">${POS_CURRENCY_SYMBOL}${item.price.toFixed(2)} × ${item.qty}</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-bold text-dark small">${POS_CURRENCY_SYMBOL}${itemTotal.toFixed(2)}</span>
                    <button type="button" class="btn btn-sm btn-outline-danger cart-remove-button" onclick="removeFromCart(${item.id})" title="Delete item" aria-label="Delete item">
                        <i class="bi bi-trash3" aria-hidden="true"></i>
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
        confirmButtonColor: '#14243d',
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
        checkoutButton.disabled = false;
    });
}
let html5QrCode = null;

function openCameraScanner() {
    // Modal open karein
    let myModal = new bootstrap.Modal(document.getElementById('cameraModal'));
    myModal.show();

    // Camera start karein
    setTimeout(() => {
        html5QrCode = new Html5Qrcode("interactive");
        html5QrCode.start(
            { facingMode: "environment" }, // Back camera (agar mobile ho) ya default webcam
            {
                fps: 10,    // Frames per second
                qrbox: { width: 250, height: 150 } // Scanning box size
            },
            (decodedText, decodedResult) => {
                // Jab barcode scan ho jaye
                console.log(`Scan result: ${decodedText}`);
                
                // Input box mein value daal kar Enter trigger karein ya fetch call karein
                document.getElementById('barcode-scanner-input').value = decodedText;
                
                // Camera band karein aur modal close kar dein
                stopCamera();
                bootstrap.Modal.getInstance(document.getElementById('cameraModal')).hide();

                // Product fetch karne wala function call karein
                fetchProductByBarcode(decodedText);
            },
            (errorMessage) => {
                // Scanning errors ignore karein jab tak code match na ho
            }
        ).catch((err) => {
            console.error("Unable to start scanning.", err);
            Swal.fire({ icon: 'error', title: 'Camera Error', text: 'Could not access camera. Make sure permissions are allowed.' });
        });
    }, 500);
}

function stopCamera() {
    if (html5QrCode) {
        html5QrCode.stop().then((ignore) => {
            // Stopped successfully
        }).catch((err) => {
            // Stop failed
        });
    }
}

// Product fetch karne ka function jo pehle se use ho raha hai
function fetchProductByBarcode(barcode) {
    fetch('get_product_by_barcode.php?barcode=' + encodeURIComponent(barcode))
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && data.product) {
                let p = data.product;
                addToCart(parseInt(p.id), p.name, parseFloat(p.price), parseInt(p.stock ?? 0));
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Product Not Found!',
                    text: 'No product matched with SKU/Barcode: ' + barcode,
                    timer: 1500,
                    showConfirmButton: false
                });
            }
        })
        .catch(error => console.error('Fetch Error:', error));
}
</script>

<?php if (isset($_SESSION['swal_success'])): ?>
<script>
    Swal.fire({ icon: 'success', title: 'Success!', text: '<?php echo $_SESSION['swal_success']; ?>', timer: 2000, showConfirmButton: false });
</script>
<?php unset($_SESSION['swal_success']); endif; ?>
<!-- Camera Scanner Modal -->
<div class="modal fade" id="cameraModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <h5 class="fw-bold text-dark"><i class="bi bi-camera me-2 text-primary"></i> Scan Barcode via Camera</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="stopCamera()"></button>
      </div>
      <div class="modal-body text-center">
        <!-- Jahan camera ki video show hogi -->
        <div id="interactive" style="width: 100%; max-height: 400px; overflow: hidden; border-radius: 10px;"></div>
        <p class="text-muted small mt-2">Place the barcode in front of your camera.</p>
      </div>
    </div>
  </div>
</div>
</body>
</html>