<?php

function findUniqueAdminNotificationTargetId(mysqli_stmt $lookup, string $value): ?int
{
    $lookup->bind_param('s', $value);
    if (!$lookup->execute()) {
        throw new RuntimeException('Unable to resolve a legacy notification target: ' . $lookup->error);
    }

    $matches = $lookup->get_result()->fetch_all(MYSQLI_NUM);
    return count($matches) === 1 ? (int) $matches[0][0] : null;
}

function backfillLegacyAdminNotificationTargets(mysqli $conn): void
{
    $result = $conn->query('SELECT id, type, title, message FROM notifications WHERE target_id IS NULL');
    if ($result === false) {
        throw new RuntimeException('Unable to load legacy notification targets: ' . $conn->error);
    }
    if ($result->num_rows === 0) {
        return;
    }

    $customerLookup = $conn->prepare('SELECT id FROM customers WHERE name = ? AND is_deleted = 0 LIMIT 2');
    $orderNumberLookup = $conn->prepare('SELECT id FROM orders WHERE order_no = ? LIMIT 2');
    $orderIdLookup = $conn->prepare('SELECT id FROM orders WHERE id = ? LIMIT 2');
    $productLookup = $conn->prepare('SELECT id FROM products WHERE name = ? LIMIT 2');
    $update = $conn->prepare('UPDATE notifications SET target_id = ? WHERE id = ? AND target_id IS NULL');
    if (!$customerLookup || !$orderNumberLookup || !$orderIdLookup || !$productLookup || !$update) {
        throw new RuntimeException('Unable to prepare legacy notification target migration: ' . $conn->error);
    }

    while ($notification = $result->fetch_assoc()) {
        $type = (string) $notification['type'];
        $title = (string) $notification['title'];
        $message = (string) $notification['message'];
        $lookup = null;
        $lookupValue = '';

        if ($type === 'customer' && preg_match('/^(.+) has registered as a customer\.$/u', $message, $matches)) {
            $lookup = $customerLookup;
            $lookupValue = $matches[1];
        } elseif ($type === 'order' && preg_match('/^Order (.+) has been created\.$/u', $message, $matches)) {
            $lookup = $orderNumberLookup;
            $lookupValue = $matches[1];
        } elseif ($type === 'order' && preg_match('/^POS order (\d+) was completed through .+\.$/u', $message, $matches)) {
            $lookup = $orderIdLookup;
            $lookupValue = $matches[1];
        } elseif ($type === 'return' && preg_match('/^Return .+ was requested for order (.+)\.$/u', $message, $matches)) {
            $lookup = $orderNumberLookup;
            $lookupValue = $matches[1];
        } elseif (
            $type === 'stock'
            && (
                ($title === 'New product added' && preg_match('/^(.+) has been added to the product catalog\.$/u', $message, $matches))
                || preg_match('/^(.+) is now out of stock\.$/u', $message, $matches)
                || preg_match('/^(.+) has only \d+ item\(s\) remaining\.$/u', $message, $matches)
            )
        ) {
            $lookup = $productLookup;
            $lookupValue = $matches[1];
        }

        if ($lookup && $lookupValue !== '') {
            $targetId = findUniqueAdminNotificationTargetId($lookup, $lookupValue);
            if ($targetId !== null) {
                $notificationId = (int) $notification['id'];
                $update->bind_param('ii', $targetId, $notificationId);
                if (!$update->execute()) {
                    throw new RuntimeException('Unable to update a legacy notification target: ' . $update->error);
                }
            }
        }
    }

    $customerLookup->close();
    $orderNumberLookup->close();
    $orderIdLookup->close();
    $productLookup->close();
    $update->close();
}

function adminNotificationTargetHref(array $notification, string $basePath): ?string
{
    $targetId = filter_var($notification['target_id'] ?? null, FILTER_VALIDATE_INT);
    if ($targetId === false || $targetId < 1) {
        return null;
    }

    $routes = [
        'customer' => ['customers', '/customers/customer-details.php?id='],
        'order' => ['orders', '/sales/orders/order-details.php?id='],
        'stock' => ['products', '/products/product-details.php?id='],
        'return' => ['orders', '/sales/orders/order-details.php?id='],
    ];
    $type = $notification['type'] ?? '';
    if (!isset($routes[$type])) {
        return null;
    }

    static $moduleAccess = [];
    $module = $routes[$type][0];
    if (!array_key_exists($module, $moduleAccess)) {
        $moduleAccess[$module] = appUserCan($module);
    }
    if (!$moduleAccess[$module]) {
        return null;
    }

    return rtrim($basePath, '/') . $routes[$type][1] . (int) $targetId;
}

function ensureAdminNotificationsTable(mysqli $conn): void
{
    $sql = "CREATE TABLE IF NOT EXISTS notifications (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(180) NOT NULL,
        message TEXT NOT NULL,
        type ENUM('order', 'stock', 'customer', 'return') NOT NULL,
        target_id BIGINT UNSIGNED NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        is_deleted TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_notifications_unread_created (is_read, created_at),
        INDEX idx_notifications_deleted_created (is_deleted, created_at),
        INDEX idx_notifications_type_created (type, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (!$conn->query($sql)) {
        throw new RuntimeException('Unable to initialize notifications table: ' . $conn->error);
    }

    $deletedColumn = $conn->query("SHOW COLUMNS FROM notifications LIKE 'is_deleted'");
    if ($deletedColumn === false) {
        throw new RuntimeException('Unable to check notification trash schema: ' . $conn->error);
    }
    if ($deletedColumn->num_rows === 0 && !$conn->query('ALTER TABLE notifications ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER is_read')) {
        throw new RuntimeException('Unable to add notification trash field: ' . $conn->error);
    }

    $targetIdColumn = $conn->query("SHOW COLUMNS FROM notifications LIKE 'target_id'");
    if ($targetIdColumn === false) {
        throw new RuntimeException('Unable to check notification target schema: ' . $conn->error);
    }
    if ($targetIdColumn->num_rows === 0) {
        if (!$conn->query('ALTER TABLE notifications ADD COLUMN target_id BIGINT UNSIGNED NULL AFTER type')) {
            throw new RuntimeException('Unable to add notification target field: ' . $conn->error);
        }
        backfillLegacyAdminNotificationTargets($conn);
    }
}

function createAdminNotification(mysqli $conn, string $title, string $message, string $type, ?int $targetId = null): void
{
    $allowedTypes = ['order', 'stock', 'customer', 'return'];
    if (!in_array($type, $allowedTypes, true)) {
        throw new InvalidArgumentException('Unsupported notification type.');
    }

    try {
        $stmt = $conn->prepare('INSERT INTO notifications (title, message, type, target_id) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('sssi', $title, $message, $type, $targetId);
        $stmt->execute();
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        error_log('Unable to create admin notification: ' . $e->getMessage());
    }
}

function notifyAdminStockTransition(mysqli $conn, string $productName, int $previousStock, int $currentStock, int $productId): void
{
    if ($previousStock > 0 && $currentStock === 0) {
        createAdminNotification(
            $conn,
            'Product out of stock',
            $productName . ' is now out of stock.',
            'stock',
            $productId
        );
        return;
    }

    if ($previousStock > 5 && $currentStock > 0 && $currentStock <= 5) {
        createAdminNotification(
            $conn,
            'Product low on stock',
            $productName . ' has only ' . $currentStock . ' item(s) remaining.',
            'stock',
            $productId
        );
    }
}

ensureAdminNotificationsTable($conn);
