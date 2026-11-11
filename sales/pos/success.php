<?php
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once "../../config.php";

// Stripe Key fetch from environment or fallback config
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

$session_id = $_GET['session_id'] ?? null;

if (!$session_id) {
    header("Location: pos.php");
    exit();
}

if (empty($stripeSecretKey)) {
    http_response_code(503);
    exit('Online payment is not configured. Add the Stripe secret key in config.php.');
}

try {
    $session = \Stripe\Checkout\Session::retrieve($session_id);
    
    if ($session->payment_status === 'paid') {
        $total_amount = $session->amount_total / 100;
        $order_no = 'ORD-' . strtoupper(substr(uniqid(), -6));
        $payment_method = $_SESSION['pending_payment_method'] ?? ($session->metadata->payment_method ?? 'Online Transfer');
        if (!in_array($payment_method, ['Online Transfer', 'Card Payment'], true)) {
            $payment_method = 'Online Transfer';
        }
        $status = 'Delivered'; 
        $shipping_address = 'Walk-in Counter POS (Online Paid)';
        $customer_id = NULL;
        $subtotal = 0.00;
        
        foreach ($_SESSION['pending_cart'] ?? [] as $item) {
            $subtotal += (float)($item['price'] ?? 0) * (int)($item['qty'] ?? 0);
        }
        if ($subtotal <= 0) {
            $subtotal = $total_amount;
        }
        $discount = 0.00;
        $shipping_fee = 0.00;

        // Order database mein insert karein
        $stmt = $conn->prepare("INSERT INTO orders (order_no, customer_id, subtotal, discount, shipping_fee, total, shipping_address, status, payment_method, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("siddddsss", $order_no, $customer_id, $subtotal, $discount, $shipping_fee, $total_amount, $shipping_address, $status, $payment_method);
        
        if ($stmt->execute()) {
            $order_id = $conn->insert_id;
            $stmt->close();
           createAdminNotification($conn, 'New order received (' . $payment_method . ')', 'Order ' . $order_no . ' was completed through ' . $payment_method . '.', 'order');
            // Session se cart utha kar items save karein aur stock minus karein
            if (isset($_SESSION['pending_cart']) && !empty($_SESSION['pending_cart'])) {
                $orderItemStmt = $conn->prepare('INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, price, line_total) VALUES (?, ?, ?, ?, ?, ?, ?)');
                foreach ($_SESSION['pending_cart'] as $item) {
                    $pid = (int) $item['id'];
                    $pname = (string) $item['name'];
                    $unit_price = (float) $item['price'];
                    $qty = (int) $item['qty'];
                    $line_total = $unit_price * $qty; // Fixed multiplication error
                    
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
                unset($_SESSION['pending_cart']); // Clear session
                unset($_SESSION['pending_payment_method']);
            }

            // Seedha invoice page par redirect
            header("Location: ../orders/invoice.php?id=" . $order_id);
            exit();
        } else {
            throw new Exception("Database error while recording paid order.");
        }

    } else {
        echo "<h3 style='color:red; text-align:center; margin-top:50px;'>Payment was not completed.</h3>";
        echo "<p style='text-align:center;'><a href='pos.php'>Back to POS Counter</a></p>";
    }

} catch (Exception $e) {
    echo "<h3 style='color:red; text-align:center; margin-top:50px;'>Error: " . $e->getMessage() . "</h3>";
    echo "<p style='text-align:center;'><a href='pos.php'>Back to POS Counter</a></p>";
}
?>