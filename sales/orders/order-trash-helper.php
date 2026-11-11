<?php
function ensureOrderTrashSchema(mysqli $conn): void
{
    $result = $conn->query("SHOW COLUMNS FROM orders LIKE 'is_deleted'");
    if (!$result) {
        throw new RuntimeException('Unable to check the orders table schema: ' . $conn->error);
    }

    if ($result->num_rows === 0 && !$conn->query("ALTER TABLE orders ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0")) {
        throw new RuntimeException('Unable to add the order trash field: ' . $conn->error);
    }
}

function getPostedOrderIds(): array
{
    $postedIds = $_POST['order_ids'] ?? [];
    if (!is_array($postedIds)) {
        $postedIds = [];
    }

    $singleId = $_POST['single_order_id'] ?? null;
    if (is_scalar($singleId) && $singleId !== '') {
        $postedIds[] = $singleId;
    }

    $orderIds = [];
    foreach ($postedIds as $postedId) {
        if (!is_scalar($postedId)) {
            continue;
        }
        $orderId = filter_var($postedId, FILTER_VALIDATE_INT);
        if ($orderId !== false && $orderId > 0) {
            $orderIds[] = $orderId;
        }
    }

    return array_values(array_unique($orderIds));
}
