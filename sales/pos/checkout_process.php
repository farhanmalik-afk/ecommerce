<?php
error_reporting(0);
ini_set('display_errors', 0);

session_start();
include "../../config.php";

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['status' => 'error', 'message' => 'Use POST to complete checkout.']);
    exit();
}

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!isset($_SESSION['pos_checkout_csrf']) || !is_string($csrfToken) || !hash_equals($_SESSION['pos_checkout_csrf'], $csrfToken)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Your session expired. Refresh the page and try again.']);
    exit();
}

if (($appSettings['pos_enabled'] ?? '1') !== '1') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'The POS module is disabled in Admin Settings.']);
    exit();
}

// Stripe Secret Key fetch (Environment ya config se)
$stripeSecretKey = get_env_setting('STRIPE_SECRET_KEY', get_env_setting('STRIPE_API_KEY'));
if (empty($stripeSecretKey) && isset($stripeSecretKeyConfig)) {
    $stripeSecretKey = $stripeSecretKeyConfig;
}

if (file_exists("../../vendor/autoload.php")) {
    require_once "../../vendor/autoload.php";
    if (!empty($stripeSecretKey)) {
        \Stripe\Stripe::setApiKey($stripeSecretKey);
    }
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $cart = $input['cart'] ?? [];
    $payment_method = $input['payment_method'] ?? 'Cash';

    // Sirf Cash aur Online Transfer allowed hain
    if (!is_string($payment_method) || !in_array($payment_method, ['Cash', 'Online Transfer'], true)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Choose a supported payment method.']);
        exit();
    }
    $isGatewayPayment = in_array($payment_method, ['Online Transfer'], true);
    
    if (empty($cart)) {
        echo json_encode(['status' => 'error', 'message' => 'Cart is empty!']);
        exit();
    }

    $subtotal = 0;
    $line_items = [];
    foreach ($cart as $item) {
        $itemTotal = $item['price'] * $item['qty'];
        $subtotal += $itemTotal;

        if ($isGatewayPayment) {
            $line_items[] = [
                'price_data' => [
                    'currency' => strtolower($appSettings['currency'] ?? 'USD'),
                    'product_data' => ['name' => $item['name']],
                    'unit_amount' => round($item['price'] * 100), // Cents mein convert
                ],
                'quantity' => $item['qty'],
            ];
        }
    }
    
    $shipping_fee = 0.00;
    $discount = 0.00;
    $taxRate = (float) ($appSettings['pos_tax_rate'] ?? 0);
    $tax = $subtotal * ($taxRate / 100); // Fixed calculation
    $total = ($subtotal + $shipping_fee + $tax) - $discount;
    
    $order_no = 'ORD-' . strtoupper(substr(uniqid(), -6));
    $status = 'Delivered';
    $shipping_address = 'Walk-in Counter POS';
    $customer_id = NULL;

    // 1. Cash Payment ke liye direct save karein
    if ($payment_method === 'Cash') {
        $stmt = $conn->prepare("INSERT INTO orders (order_no, customer_id, subtotal, discount, shipping_fee, total, shipping_address, status, payment_method, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("siddddsss", $order_no, $customer_id, $subtotal, $discount, $shipping_fee, $total, $shipping_address, $status, $payment_method);
        
        if ($stmt->execute()) {
            $order_id = $conn->insert_id;
            $stmt->close();
            createAdminNotification($conn, 'New order received (Cash)', 'Order ' . $order_no . ' was completed through Cash.', 'order');
            $orderItemStmt = $conn->prepare('INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, price, line_total) VALUES (?, ?, ?, ?, ?, ?, ?)');
            foreach ($cart as $item) {
                $pid = (int) $item['id'];
                $pname = (string) $item['name'];
                $unit_price = (float) $item['price'];
                $qty = (int) $item['qty'];
                $line_total = $unit_price * $qty; // Fixed calculation
                
                $orderItemStmt->bind_param('iisdidd', $order_id, $pid, $pname, $unit_price, $qty, $unit_price, $line_total);
                $orderItemStmt->execute();
                
                $stockStmt = $conn->prepare('SELECT name, stock FROM products WHERE id = ?');
                $stockStmt->bind_param('i', $pid);
                $stockStmt->execute();
                $stockRow = $stockStmt->get_result()->fetch_assoc();
                $stockStmt->close();
                
                if ($stockRow) {
                    $previousStock = (int) $stockRow['stock'];
                    $updateStock = $conn->prepare('UPDATE products SET stock = GREATEST(stock - ?, 0) WHERE id = ?');
                    $updateStock->bind_param('ii', $qty, $pid);
                    $updateStock->execute();
                    $updateStock->close();
                    
                    notifyAdminStockTransition($conn, (string) $stockRow['name'], $previousStock, max(0, $previousStock - $qty)); // Fixed calculation
                }
            }
            $orderItemStmt->close();

            echo json_encode(['status' => 'success', 'order_id' => $order_id]);
            exit();
        }
    } 
    // 2. Online Transfer (Stripe) ke liye session banayein
    else if ($isGatewayPayment) {
        if (!class_exists('\Stripe\Stripe')) {
            echo json_encode(['status' => 'error', 'message' => 'Stripe SDK not found. Run composer require stripe/stripe-php']);
            exit();
        }
        if (empty($stripeSecretKey) || $stripeSecretKey === 'sk_test_YAHAN_APNI_ASAL_KEY_PASTE_KREIN') {
            http_response_code(503);
            echo json_encode(['status' => 'error', 'message' => 'Online payment is not configured. Add the Stripe secret key in config.php.']);
            exit();
        }

        if ($tax > 0) {
            $line_items[] = [
                'price_data' => [
                    'currency' => strtolower($appSettings['currency'] ?? 'USD'),
                    'product_data' => ['name' => 'Estimated Tax (' . $taxRate . '%)'],
                    'unit_amount' => round($tax * 100), // Fixed calculation
                ],
                'quantity' => 1,
            ];
        }

        $checkoutOptions = [
            'line_items' => $line_items,
            'mode' => 'payment',
            'success_url' => 'http://localhost/ecommerce/sales/pos/success.php?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => 'http://localhost/ecommerce/sales/pos/pos.php',
            'metadata' => ['payment_method' => $payment_method],
        ];

        $session = \Stripe\Checkout\Session::create($checkoutOptions);
        $_SESSION['pending_cart'] = $cart;
        $_SESSION['pending_payment_method'] = $payment_method;

        echo json_encode(['status' => 'stripe_redirect', 'url' => $session->url]);
        exit();
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit();
}
?>