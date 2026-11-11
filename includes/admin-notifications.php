<?php

function ensureAdminNotificationsTable(mysqli $conn): void
{
    $sql = "CREATE TABLE IF NOT EXISTS notifications (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(180) NOT NULL,
        message TEXT NOT NULL,
        type ENUM('order', 'stock', 'customer', 'return') NOT NULL,
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
}

function createAdminNotification(mysqli $conn, string $title, string $message, string $type): void
{
    $allowedTypes = ['order', 'stock', 'customer', 'return'];
    if (!in_array($type, $allowedTypes, true)) {
        throw new InvalidArgumentException('Unsupported notification type.');
    }

    try {
        $stmt = $conn->prepare('INSERT INTO notifications (title, message, type) VALUES (?, ?, ?)');
        $stmt->bind_param('sss', $title, $message, $type);
        $stmt->execute();
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        error_log('Unable to create admin notification: ' . $e->getMessage());
    }
}

function notifyAdminStockTransition(mysqli $conn, string $productName, int $previousStock, int $currentStock): void
{
    if ($previousStock > 0 && $currentStock === 0) {
        createAdminNotification(
            $conn,
            'Product out of stock',
            $productName . ' is now out of stock.',
            'stock'
        );
        return;
    }

    if ($previousStock > 5 && $currentStock > 0 && $currentStock <= 5) {
        createAdminNotification(
            $conn,
            'Product low on stock',
            $productName . ' has only ' . $currentStock . ' item(s) remaining.',
            'stock'
        );
    }
}

ensureAdminNotificationsTable($conn);
