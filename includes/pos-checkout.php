<?php

function ensureStripeProcessedSessionsTable(mysqli $conn): void
{
    static $tableEnsured = false;
    if ($tableEnsured) {
        return;
    }

    $result = $conn->query(
        'CREATE TABLE IF NOT EXISTS stripe_processed_sessions (
            session_id VARCHAR(255) NOT NULL PRIMARY KEY,
            order_id INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    if (!$result) {
        throw new RuntimeException('Unable to initialize Stripe order tracking: ' . $conn->error);
    }
    $tableEnsured = true;
}

function validatePosCart(mysqli $conn, $requestedCart, bool $lockStock = false, bool $useStoredPrices = false): array
{
    if (!is_array($requestedCart) || !$requestedCart || count($requestedCart) > 100) {
        throw new InvalidArgumentException('The cart is empty or contains too many items.');
    }

    $quantities = [];
    $storedPrices = [];
    foreach ($requestedCart as $item) {
        if (!is_array($item)) {
            throw new InvalidArgumentException('The cart contains an invalid item.');
        }
        $productId = filter_var($item['id'] ?? null, FILTER_VALIDATE_INT);
        $quantity = filter_var($item['qty'] ?? null, FILTER_VALIDATE_INT);
        if ($productId === false || $productId < 1 || $quantity === false || $quantity < 1) {
            throw new InvalidArgumentException('The cart contains an invalid product or quantity.');
        }
        $quantities[$productId] = ($quantities[$productId] ?? 0) + $quantity;
        if (isset($item['price']) && is_numeric($item['price']) && is_finite((float) $item['price'])) {
            $storedPrices[$productId] = (float) $item['price'];
        }
    }

    if (count($quantities) > 100) {
        throw new InvalidArgumentException('The cart contains too many products.');
    }

    $productIds = array_keys($quantities);
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $query = "SELECT id, name, price, stock FROM products
              WHERE is_deleted = 0 AND status = 'Active' AND id IN ($placeholders)";
    if ($lockStock) {
        $query .= ' FOR UPDATE';
    }
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new RuntimeException('Unable to prepare product validation: ' . $conn->error);
    }
    $types = str_repeat('i', count($productIds));
    $stmt->bind_param($types, ...$productIds);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Unable to validate products: ' . $error);
    }
    $result = $stmt->get_result();
    $products = [];
    while ($product = $result->fetch_assoc()) {
        $products[(int) $product['id']] = $product;
    }
    $stmt->close();

    if (count($products) !== count($quantities)) {
        throw new InvalidArgumentException('One or more products are unavailable.');
    }

    $cart = [];
    foreach ($quantities as $productId => $quantity) {
        $product = $products[$productId];
        $stock = (int) $product['stock'];
        if ($stock < $quantity) {
            throw new InvalidArgumentException('Insufficient stock for ' . $product['name'] . '.');
        }

        $price = $useStoredPrices ? ($storedPrices[$productId] ?? null) : (float) $product['price'];
        if (!is_numeric($price) || !is_finite((float) $price) || (float) $price < 0) {
            throw new InvalidArgumentException('A product has an invalid price.');
        }
        $cart[] = [
            'id' => $productId,
            'name' => (string) $product['name'],
            'price' => round((float) $price, 2),
            'qty' => $quantity,
            'stock' => $stock,
        ];
    }
    return $cart;
}

function insertPosOrder(mysqli $conn, array $cart, float $total, string $paymentMethod, string $shippingAddress): int
{
    $subtotal = 0.0;
    foreach ($cart as $item) {
        $subtotal += $item['price'] * $item['qty'];
    }
    $discount = 0.0;
    $shippingFee = 0.0;
    $customerId = null;
    $status = 'Delivered';
    $orderNo = 'ORD-' . strtoupper(bin2hex(random_bytes(5)));

    $orderStmt = $conn->prepare(
        'INSERT INTO orders (order_no, customer_id, subtotal, discount, shipping_fee, total, shipping_address, status, payment_method, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    if (!$orderStmt) {
        throw new RuntimeException('Unable to prepare POS order: ' . $conn->error);
    }
    $orderStmt->bind_param('siddddsss', $orderNo, $customerId, $subtotal, $discount, $shippingFee, $total, $shippingAddress, $status, $paymentMethod);
    $orderStmt->execute();
    $orderId = (int) $conn->insert_id;
    $orderStmt->close();

    $itemStmt = $conn->prepare(
        'INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, price, line_total)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stockStmt = $conn->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');
    if (!$itemStmt || !$stockStmt) {
        throw new RuntimeException('Unable to prepare POS order items: ' . $conn->error);
    }

    foreach ($cart as $item) {
        $productId = (int) $item['id'];
        $productName = (string) $item['name'];
        $price = (float) $item['price'];
        $quantity = (int) $item['qty'];
        $lineTotal = $price * $quantity;
        $itemStmt->bind_param('iisdidd', $orderId, $productId, $productName, $price, $quantity, $price, $lineTotal);
        $itemStmt->execute();

        $stockStmt->bind_param('iii', $quantity, $productId, $quantity);
        $stockStmt->execute();
        if ($stockStmt->affected_rows !== 1) {
            throw new RuntimeException('Stock changed before the order could be completed.');
        }
    }
    $itemStmt->close();
    $stockStmt->close();
    return $orderId;
}

function notifyPosOrder(mysqli $conn, int $orderId, string $paymentMethod, array $cart): void
{
    createAdminNotification(
        $conn,
        'New order received (' . $paymentMethod . ')',
        'POS order ' . $orderId . ' was completed through ' . $paymentMethod . '.',
        'order',
        $orderId
    );
    foreach ($cart as $item) {
        $remainingStock = max(0, (int) $item['stock'] - (int) $item['qty']);
        notifyAdminStockTransition($conn, (string) $item['name'], (int) $item['stock'], $remainingStock, (int) $item['id']);
    }
}
