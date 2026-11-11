<?php

session_start();
include "../config.php";
require_once __DIR__ . '/../sales/orders/order-trash-helper.php';


$requestedTrashType = $_GET['type'] ?? 'products';
$trashType = is_string($requestedTrashType) && in_array($requestedTrashType, ['products', 'orders', 'customers', 'notifications', 'users'], true)
    ? $requestedTrashType
    : 'products';

if ($trashType === 'orders') {
    try {
        ensureOrderTrashSchema($conn);
    } catch (RuntimeException $exception) {
        error_log($exception->getMessage());
        http_response_code(500);
        exit('The order trash feature could not initialize. Check database permissions and server logs.');
    }
}

// ================= USERS TRASH ACTIONS =================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['user_bulk_action'] ?? '', ['restore', 'delete'], true)) {
    $postedUserIds = $_POST['user_ids'] ?? [];
    $userIds = [];
    if (is_array($postedUserIds)) {
        foreach ($postedUserIds as $postedUserId) {
            $userId = is_scalar($postedUserId) ? filter_var($postedUserId, FILTER_VALIDATE_INT) : false;
            if ($userId !== false && $userId > 0) {
                $userIds[] = $userId;
            }
        }
    }
    $userIds = array_values(array_unique($userIds));
    $bulkUserAction = $_POST['user_bulk_action'];

    if (!$userIds) {
        $_SESSION['swal_error'] = 'Select at least one user.';
    } elseif ($bulkUserAction === 'restore') {
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $types = str_repeat('i', count($userIds));
        $stmt = $conn->prepare("UPDATE admins SET status = 'Active' WHERE status = 'Trash' AND id IN ($placeholders)");
        $stmt->bind_param($types, ...$userIds);
        $stmt->execute();
        $restoredCount = $stmt->affected_rows;
        $stmt->close();
        $_SESSION['swal_success'] = $restoredCount > 0
            ? $restoredCount . ' user(s) recovered successfully.'
            : 'No deleted users were found in your selection.';
    } else {
        $deletedCount = 0;
        $failedCount = 0;
        $stmt = $conn->prepare("DELETE FROM admins WHERE id = ? AND status = 'Trash'");
        foreach ($userIds as $userId) {
            if (function_exists('getCurrentAdminId') && $userId === getCurrentAdminId()) {
                $failedCount++;
                continue;
            }
            try {
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $deletedCount += $stmt->affected_rows;
            } catch (mysqli_sql_exception $exception) {
                $failedCount++;
            }
        }
        $stmt->close();

        if ($deletedCount > 0) {
            $_SESSION['swal_success'] = $deletedCount . ' user(s) permanently deleted.';
            if ($failedCount > 0) {
                $_SESSION['swal_success'] .= ' ' . $failedCount . ' could not be deleted.';
            }
        } elseif ($failedCount > 0) {
            $_SESSION['swal_error'] = 'Selected users could not be permanently deleted.';
        } else {
            $_SESSION['swal_error'] = 'No deleted users were found in your selection.';
        }
    }
    header('Location: trash.php?type=users');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['user_action'] ?? '', ['restore', 'delete'], true)) {
    $postedUserId = $_POST['user_id'] ?? null;
    $userId = is_scalar($postedUserId) ? filter_var($postedUserId, FILTER_VALIDATE_INT) : false;
    if ($userId === false || $userId < 1) {
        $_SESSION['swal_error'] = 'Select a valid user.';
    } elseif ($_POST['user_action'] === 'restore') {
        $stmt = $conn->prepare("UPDATE admins SET status = 'Active' WHERE id = ? AND status = 'Trash'");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $_SESSION['swal_success'] = $stmt->affected_rows > 0 ? 'User recovered successfully.' : 'No deleted user was found.';
        $stmt->close();
    } else {
        if (function_exists('getCurrentAdminId') && $userId === getCurrentAdminId()) {
            $_SESSION['swal_error'] = 'You cannot delete your own account permanently.';
        } else {
            try {
                $stmt = $conn->prepare("DELETE FROM admins WHERE id = ? AND status = 'Trash'");
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $_SESSION['swal_success'] = $stmt->affected_rows > 0
                    ? 'User permanently deleted.'
                    : 'No deleted user was found.';
                $stmt->close();
            } catch (mysqli_sql_exception $exception) {
                $_SESSION['swal_error'] = 'This user cannot be permanently deleted.';
            }
        }
    }
    header('Location: trash.php?type=users');
    exit();
}
// =======================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['customer_bulk_action'] ?? '', ['restore', 'delete'], true)) {
    $postedCustomerIds = $_POST['customer_ids'] ?? [];
    $customerIds = [];
    if (is_array($postedCustomerIds)) {
        foreach ($postedCustomerIds as $postedCustomerId) {
            $customerId = is_scalar($postedCustomerId) ? filter_var($postedCustomerId, FILTER_VALIDATE_INT) : false;
            if ($customerId !== false && $customerId > 0) {
                $customerIds[] = $customerId;
            }
        }
    }
    $customerIds = array_values(array_unique($customerIds));
    $bulkCustomerAction = $_POST['customer_bulk_action'];

    if (!$customerIds) {
        $_SESSION['swal_error'] = 'Select at least one customer.';
    } elseif ($bulkCustomerAction === 'restore') {
        $placeholders = implode(',', array_fill(0, count($customerIds), '?'));
        $types = str_repeat('i', count($customerIds));
        $stmt = $conn->prepare("UPDATE customers SET is_deleted = 0 WHERE is_deleted = 1 AND id IN ($placeholders)");
        $stmt->bind_param($types, ...$customerIds);
        $stmt->execute();
        $restoredCount = $stmt->affected_rows;
        $stmt->close();
        $_SESSION['swal_success'] = $restoredCount > 0
            ? $restoredCount . ' customer(s) recovered successfully.'
            : 'No deleted customers were found in your selection.';
    } else {
        $deletedCount = 0;
        $failedCount = 0;
        $stmt = $conn->prepare('DELETE FROM customers WHERE id = ? AND is_deleted = 1');
        foreach ($customerIds as $customerId) {
            try {
                $stmt->bind_param('i', $customerId);
                $stmt->execute();
                $deletedCount += $stmt->affected_rows;
            } catch (mysqli_sql_exception $exception) {
                error_log('Unable to permanently delete customer ' . $customerId . ': ' . $exception->getMessage());
                $failedCount++;
            }
        }
        $stmt->close();

        if ($deletedCount > 0) {
            $_SESSION['swal_success'] = $deletedCount . ' customer(s) permanently deleted.';
            if ($failedCount > 0) {
                $_SESSION['swal_success'] .= ' ' . $failedCount . ' could not be deleted because related orders exist.';
            }
        } elseif ($failedCount > 0) {
            $_SESSION['swal_error'] = 'Selected customers could not be permanently deleted because related orders exist.';
        } else {
            $_SESSION['swal_error'] = 'No deleted customers were found in your selection.';
        }
    }
    header('Location: trash.php?type=customers');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['customer_action'] ?? '', ['restore', 'delete'], true)) {
    $postedCustomerId = $_POST['customer_id'] ?? null;
    $customerId = is_scalar($postedCustomerId) ? filter_var($postedCustomerId, FILTER_VALIDATE_INT) : false;
    if ($customerId === false || $customerId < 1) {
        $_SESSION['swal_error'] = 'Select a valid customer.';
    } elseif ($_POST['customer_action'] === 'restore') {
        $stmt = $conn->prepare('UPDATE customers SET is_deleted = 0 WHERE id = ? AND is_deleted = 1');
        $stmt->bind_param('i', $customerId);
        $stmt->execute();
        $_SESSION['swal_success'] = $stmt->affected_rows > 0 ? 'Customer recovered successfully.' : 'No deleted customer was found.';
        $stmt->close();
    } else {
        try {
            $stmt = $conn->prepare('DELETE FROM customers WHERE id = ? AND is_deleted = 1');
            $stmt->bind_param('i', $customerId);
            $stmt->execute();
            $_SESSION['swal_success'] = $stmt->affected_rows > 0
                ? 'Customer permanently deleted.'
                : 'No deleted customer was found.';
            $stmt->close();
        } catch (mysqli_sql_exception $exception) {
            error_log('Unable to permanently delete customer ' . $customerId . ': ' . $exception->getMessage());
            $_SESSION['swal_error'] = 'This customer cannot be permanently deleted because related records exist. Recover the customer to keep historical orders linked.';
        }
    }
    header('Location: trash.php?type=customers');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['bulk_action'] ?? '', ['order_restore', 'order_delete'], true)) {
    $orderIds = getPostedOrderIds();
    $action = $_POST['bulk_action'];

    if (empty($orderIds)) {
        $_SESSION['order_trash_notice'] = ['danger', 'Select at least one order.'];
    } elseif ($action === 'order_restore') {
        try {
            $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
            $types = str_repeat('i', count($orderIds));
            $stmt = $conn->prepare("UPDATE orders SET is_deleted = 0 WHERE is_deleted = 1 AND id IN ($placeholders)");
            $stmt->bind_param($types, ...$orderIds);
            $stmt->execute();
            $restoredCount = $stmt->affected_rows;
            $stmt->close();
            $_SESSION['order_trash_notice'] = $restoredCount > 0
                ? ['success', $restoredCount . ' order(s) recovered.']
                : ['warning', 'No trashed orders were found to recover in that selection.'];
        } catch (Throwable $exception) {
            error_log('Unable to restore orders: ' . $exception->getMessage());
            $_SESSION['order_trash_notice'] = ['danger', 'The selected orders could not be recovered. Check the server log for details.'];
        }
    } else {
        $deletedCount = 0;
        $failedCount = 0;
        foreach ($orderIds as $orderId) {
            try {
                $conn->begin_transaction();

                $itemsStmt = $conn->prepare("DELETE FROM order_items WHERE order_id = ?");
                $itemsStmt->bind_param("i", $orderId);
                $itemsStmt->execute();
                $itemsStmt->close();

                $orderStmt = $conn->prepare("DELETE FROM orders WHERE id = ? AND is_deleted = 1");
                $orderStmt->bind_param("i", $orderId);
                $orderStmt->execute();
                $wasDeleted = $orderStmt->affected_rows > 0;
                $orderStmt->close();

                if (!$wasDeleted) {
                    $conn->rollback();
                    continue;
                }

                $conn->commit();
                $deletedCount++;
            } catch (Throwable $exception) {
                $conn->rollback();
                error_log('Unable to permanently delete order ' . $orderId . ': ' . $exception->getMessage());
                $failedCount++;
            }
        }

        if ($deletedCount > 0) {
            $message = $deletedCount . ' order(s) permanently deleted.';
            if ($failedCount > 0) {
                $message .= ' ' . $failedCount . ' order(s) could not be deleted because related records exist.';
            }
            $_SESSION['order_trash_notice'] = ['success', $message];
        } elseif ($failedCount > 0) {
            $_SESSION['order_trash_notice'] = ['danger', 'Selected orders could not be permanently deleted because related records exist.'];
        } else {
            $_SESSION['order_trash_notice'] = ['warning', 'No trashed orders were found in that selection.'];
        }
    }

    header('Location: trash.php?type=orders');
    exit();
}

// 1. Handle Recovery (Restore Product: is_deleted = 0)
if (isset($_GET['recover_id'])) {
    $rec_id = intval($_GET['recover_id']);
    $stmt = $conn->prepare("UPDATE products SET is_deleted = 0 WHERE id = ?");
    $stmt->bind_param("i", $rec_id);
    if ($stmt->execute()) {
        $_SESSION['swal_success'] = "Product recovered successfully!";
    } else {
        $_SESSION['swal_error'] = "Failed to recover product!";
    }
    $stmt->close();
    header("Location: trash.php?type=products");
    exit();
}

// 2. Handle Permanent Deletion (Delete Forever)
if (isset($_GET['perm_delete'])) {
    $p_id = intval($_GET['perm_delete']);
    try {
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->bind_param("i", $p_id);
        if ($stmt->execute()) {
            $_SESSION['swal_success'] = "Product permanently deleted!";
        } else {
            $_SESSION['swal_error'] = "Failed to delete product permanently!";
        }
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        $_SESSION['swal_error'] = "Cannot delete this product because related records exist!";
    }
    header("Location: trash.php?type=products");
    exit();
}

// Handle bulk recovery and permanent deletion
if (isset($_POST['bulk_action'])) {
    $selected_ids = [];
    $posted_ids = $_POST['product_ids'] ?? [];
    if (is_array($posted_ids)) {
        foreach ($posted_ids as $posted_id) {
            if (!is_scalar($posted_id)) {
                continue;
            }
            $product_id = filter_var($posted_id, FILTER_VALIDATE_INT);
            if ($product_id !== false && $product_id > 0) {
                $selected_ids[] = $product_id;
            }
        }
    }
    $selected_ids = array_values(array_unique($selected_ids));
    $bulk_action = $_POST['bulk_action'];

    if (empty($selected_ids)) {
        $_SESSION['swal_error'] = "Please select at least one product.";
    } elseif ($bulk_action === 'recover') {
        $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
        $types = str_repeat('i', count($selected_ids));
        $stmt = $conn->prepare("UPDATE products SET is_deleted = 0 WHERE is_deleted = 1 AND id IN ($placeholders)");
        $stmt->bind_param($types, ...$selected_ids);
        $stmt->execute();
        $recovered_count = $stmt->affected_rows;
        $stmt->close();

        if ($recovered_count > 0) {
            $_SESSION['swal_success'] = $recovered_count . " product(s) recovered successfully!";
        } else {
            $_SESSION['swal_error'] = "No deleted products were found in your selection.";
        }
    } elseif ($bulk_action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ? AND is_deleted = 1");
        $deleted_count = 0;
        $failed_count = 0;

        foreach ($selected_ids as $product_id) {
            try {
                $stmt->bind_param("i", $product_id);
                $stmt->execute();
                $deleted_count += $stmt->affected_rows;
            } catch (mysqli_sql_exception $e) {
                $failed_count++;
            }
        }
        $stmt->close();

        if ($deleted_count > 0) {
            $_SESSION['swal_success'] = $deleted_count . " product(s) permanently deleted.";
            if ($failed_count > 0) {
                $_SESSION['swal_success'] .= " " . $failed_count . " product(s) could not be deleted because related records exist.";
            }
        } elseif ($failed_count > 0) {
            $_SESSION['swal_error'] = "Selected products could not be deleted because related records exist.";
        } else {
            $_SESSION['swal_error'] = "No deleted products were found in your selection.";
        }
    } else {
        $_SESSION['swal_error'] = "Invalid bulk action.";
    }

    header("Location: trash.php?type=products");
    exit();
}

$pageTitle = ucfirst($trashType) . ' Trash Bin';
$pageSubtitle = 'Restore deleted records or permanently delete them.';
$parent = $trashType === 'orders' ? ['Orders', '../sales/orders/orders.php'] : ($trashType === 'customers' ? ['Customers', '../customers/customers.php'] : ($trashType === 'users' ? ['Users', 'users.php'] : ['Products', 'products.php']));
$activeModule = 'trash';

$ordersResult = $trashType === 'orders'
    ? $conn->query("SELECT o.id, o.order_no, o.total, o.status, o.created_at, COALESCE(c.name, 'Walk-in Customer') AS customer_name FROM orders o LEFT JOIN customers c ON o.customer_id = c.id WHERE o.is_deleted = 1 ORDER BY o.id DESC")
    : null;
$customersResult = $trashType === 'customers'
    ? $conn->query("SELECT id, name, email, phone, created_at FROM customers WHERE is_deleted = 1 ORDER BY id DESC")
    : null;
$usersResult = $trashType === 'users'
    ? $conn->query("SELECT a.id, a.name, a.email, a.avatar, r.name as role_name FROM admins a JOIN roles r ON a.role_id = r.id WHERE a.status = 'Trash' ORDER BY a.id DESC")
    : null;
$notificationTrashResult = $trashType === 'notifications'
    ? $conn->query('SELECT id, title, message, type, is_read, created_at FROM notifications WHERE is_deleted = 1 ORDER BY created_at DESC, id DESC')
    : null;
if ($trashType === 'notifications' && $notificationTrashResult === false) {
    throw new RuntimeException('Unable to load deleted notifications: ' . $conn->error);
}

include __DIR__ . '/../layouts/header.php';
?>

<!-- SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="container-fluid px-0">
    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
        
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="fw-bold text-dark mb-1">
                    <?php echo $trashType === 'orders' ? 'Deleted Orders' : ($trashType === 'customers' ? 'Deleted Customers' : ($trashType === 'notifications' ? 'Deleted Notifications' : ($trashType === 'users' ? 'Deleted Users' : 'Deleted Products (Trash Bin)'))); ?>
                </h4>
                <p class="text-muted small mb-0">
                    <?php echo $trashType === 'orders'
                        ? 'Yahan se deleted orders recover ya permanently delete kar sakte hain.'
                        : ($trashType === 'customers'
                            ? 'Recover customers or permanently delete records that are no longer referenced by orders.'
                            : ($trashType === 'notifications'
                                ? 'Recover notification history or permanently delete selected notifications.'
                                : ($trashType === 'users'
                                    ? 'Manage deleted users, restore them, or delete them permanently.'
                                    : 'Yahan se deleted products recover ya permanently delete kar sakte hain.'))); ?>
                </p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="trash.php?type=products" class="btn btn-sm rounded-pill px-3 <?php echo $trashType === 'products' ? 'btn-dark' : 'btn-outline-secondary'; ?>">
                    <i class="bi bi-box-seam me-1"></i> Products
                </a>
                <a href="trash.php?type=orders" class="btn btn-sm rounded-pill px-3 <?php echo $trashType === 'orders' ? 'btn-dark' : 'btn-outline-secondary'; ?>">
                    <i class="bi bi-bag-check me-1"></i> Orders
                </a>
                <a href="trash.php?type=customers" class="btn btn-sm rounded-pill px-3 <?php echo $trashType === 'customers' ? 'btn-dark' : 'btn-outline-secondary'; ?>">
                    <i class="bi bi-people me-1"></i> Customers
                </a>
                <a href="trash.php?type=notifications" class="btn btn-sm rounded-pill px-3 <?php echo $trashType === 'notifications' ? 'btn-dark' : 'btn-outline-secondary'; ?>">
                    <i class="bi bi-bell me-1"></i> Notifications
                </a>
                <a href="trash.php?type=users" class="btn btn-sm rounded-pill px-3 <?php echo $trashType === 'users' ? 'btn-dark' : 'btn-outline-secondary'; ?>">
                    <i class="bi bi-person-fill me-1"></i> Users
                </a>
                <a href="<?php echo $trashType === 'orders' ? '../sales/orders/orders.php' : ($trashType === 'customers' ? '../customers/customers.php' : ($trashType === 'notifications' ? '../notifications.php' : ($trashType === 'users' ? 'users.php' : 'products.php'))); ?>" class="btn btn-outline-dark btn-sm rounded-pill px-3">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

        <?php if ($trashType === 'products'): ?>
        <form method="POST" action="trash.php" id="trashBulkForm">
            <input type="hidden" name="bulk_action" id="bulkAction">
            <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="toggleTrashSelection" aria-label="Enable product selection" aria-pressed="false" title="Enable product selection">
                        <i class="bi bi-check-square"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="selectAllTrashProducts">Select All</button>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-success btn-sm d-none" id="recoverSelectedButton" disabled>
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Recover Selected
                    </button>
                    <button type="button" class="btn btn-danger btn-sm d-none" id="deleteSelectedButton" disabled>
                        <i class="bi bi-trash me-1"></i> Delete Selected Forever
                    </button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light text-uppercase fs-7">
                        <tr>
                            <th class="py-3 ps-3 trash-select-column d-none">Select</th>
                            <th class="py-3 ps-3">Product Name</th>
                            <th class="py-3">Category</th>
                            <th class="py-3">Price</th>
                            <th class="py-3 text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $sql = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.is_deleted = 1 ORDER BY p.id DESC";
                    $result = $conn->query($sql);

                    if ($result && $result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            $img_name = $row['image'] ?? '';
                            if (empty($img_name)) {
                                $img_path = "https://cdn-icons-png.flaticon.com/512/1165/1165687.png";
                            } elseif (filter_var($img_name, FILTER_VALIDATE_URL)) {
                                $img_path = $img_name;
                            } else {
                                $img_path = "../uploads/" . $img_name;
                                if (!file_exists($img_path)) {
                                    $img_path = "https://cdn-icons-png.flaticon.com/512/1165/1165687.png";
                                }
                            }
                            ?>
                            <tr>
                                <td class="py-3 ps-3 trash-select-column d-none">
                                    <input type="checkbox" class="form-check-input trash-product-checkbox" name="product_ids[]" value="<?= (int)$row['id'] ?>" aria-label="Select <?= htmlspecialchars($row['name'] ?? 'product', ENT_QUOTES, 'UTF-8') ?>">
                                </td>
                                <td class="ps-3 fw-semibold text-dark">
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="<?= htmlspecialchars($img_path, ENT_QUOTES, 'UTF-8') ?>" alt="" style="width: 38px; height: 38px; object-fit: cover; border-radius: 8px;" class="border">
                                        <span><?= htmlspecialchars($row['name'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($row['category_name'] ?? 'General', ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><strong><?= htmlspecialchars(formatAppCurrency($row['price'] ?? 0), ENT_QUOTES, 'UTF-8') ?></strong></td>
                                <td class="text-end pe-3">
                                    <button type="button" onclick="confirmRecover(<?= $row['id'] ?>)" class="btn btn-sm btn-outline-success rounded-pill px-3 me-1" title="Restore Product">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i> Recover
                                    </button>
                                    <button type="button" onclick="confirmPermDelete(<?= $row['id'] ?>)" class="btn btn-sm btn-outline-danger rounded-pill px-3" title="Delete Forever">
                                        <i class="bi bi-trash me-1"></i> Delete Forever
                                    </button>
                                </td>
                            </tr>
                            <?php
                        }
                    } else {
                        echo '<tr><td colspan="5" class="text-center py-5 text-muted">
                                <i class="bi bi-trash3 fs-1 d-block mb-2 opacity-50"></i>
                                Trash bin bilkul khali hai!
                              </td></tr>';
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </form>

        <?php elseif ($trashType === 'orders'): ?>
        <form method="POST" action="trash.php?type=orders" id="orderTrashBulkForm">
            <input type="hidden" name="bulk_action" id="orderTrashBulkAction">
            <input type="hidden" name="single_order_id" id="singleTrashedOrderId">
            <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="toggleOrderTrashSelection" aria-label="Enable order selection" aria-pressed="false" title="Enable order selection">
                        <i class="bi bi-check-square"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="selectAllTrashedOrders">Select All</button>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-success btn-sm d-none" id="restoreSelectedOrders" disabled>
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Recover Selected
                    </button>
                    <button type="button" class="btn btn-danger btn-sm d-none" id="deleteSelectedOrders" disabled>
                        <i class="bi bi-trash me-1"></i> Delete Selected Forever
                    </button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light text-uppercase fs-7">
                        <tr>
                            <th class="py-3 ps-3 order-trash-select-column d-none">Select</th>
                            <th class="py-3">Order No</th>
                            <th class="py-3">Customer</th>
                            <th class="py-3">Total</th>
                            <th class="py-3">Status</th>
                            <th class="py-3">Order Date</th>
                            <th class="py-3 text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($ordersResult && $ordersResult->num_rows > 0): ?>
                            <?php while ($order = $ordersResult->fetch_assoc()):
                                $displayOrderNo = $order['order_no'] ?: '#ORD-' . str_pad((string)$order['id'], 4, '0', STR_PAD_LEFT);
                            ?>
                                <tr>
                                    <td class="py-3 ps-3 order-trash-select-column d-none">
                                        <input type="checkbox" class="form-check-input trashed-order-checkbox" name="order_ids[]" value="<?php echo (int)$order['id']; ?>" aria-label="Select order <?php echo htmlspecialchars($displayOrderNo, ENT_QUOTES, 'UTF-8'); ?>">
                                    </td>
                                    <td class="fw-semibold"><?php echo htmlspecialchars($displayOrderNo, ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars(formatAppCurrency($order['total']), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($order['status'] ?? 'Pending', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo !empty($order['created_at']) ? htmlspecialchars(date('d M Y', strtotime($order['created_at'])), ENT_QUOTES, 'UTF-8') : 'N/A'; ?></td>
                                    <td class="text-end text-nowrap">
                                        <button type="button" class="btn btn-outline-success btn-sm" onclick="runTrashedOrderAction('order_restore', <?php echo (int)$order['id']; ?>, 'Recover this order?')">
                                            <i class="bi bi-arrow-counterclockwise me-1"></i> Recover
                                        </button>
                                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="runTrashedOrderAction('order_delete', <?php echo (int)$order['id']; ?>, 'Permanently delete this order and its items? This cannot be undone.')">
                                            <i class="bi bi-trash me-1"></i> Delete Forever
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-trash3 fs-1 d-block mb-2 opacity-50"></i>
                                    Orders trash is empty.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>

        <?php elseif ($trashType === 'customers'): ?>
        <form method="post" action="trash.php?type=customers" id="customerTrashBulkForm">
            <input type="hidden" name="customer_bulk_action" id="customerTrashBulkAction">
            <input type="hidden" name="customer_action" id="singleCustomerTrashAction">
            <input type="hidden" name="customer_id" id="singleCustomerTrashId">
            <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="toggleCustomerTrashSelection" aria-label="Enable customer selection" aria-pressed="false" title="Enable customer selection">
                        <i class="bi bi-check-square"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="selectAllTrashCustomers">Select All</button>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-success btn-sm d-none" id="restoreSelectedCustomers" disabled>
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Recover Selected
                    </button>
                    <button type="button" class="btn btn-danger btn-sm d-none" id="deleteSelectedCustomers" disabled>
                        <i class="bi bi-trash me-1"></i> Delete Selected Forever
                    </button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th class="customer-trash-select-column d-none">Select</th>
                            <th>Customer</th><th>Phone</th><th>Joined</th><th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($customersResult && $customersResult->num_rows > 0): ?>
                        <?php while ($customer = $customersResult->fetch_assoc()): ?>
                            <tr>
                                <td class="customer-trash-select-column d-none">
                                    <input type="checkbox" class="form-check-input trashed-customer-checkbox" name="customer_ids[]" value="<?= (int) $customer['id'] ?>" aria-label="Select <?= htmlspecialchars($customer['name'] ?? 'customer', ENT_QUOTES, 'UTF-8') ?>">
                                </td>
                                <td><strong><?= htmlspecialchars($customer['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong><br><small class="text-muted"><?= htmlspecialchars($customer['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></small></td>
                                <td><?= htmlspecialchars($customer['phone'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= !empty($customer['created_at']) ? htmlspecialchars(date('d M Y', strtotime($customer['created_at'])), ENT_QUOTES, 'UTF-8') : 'N/A' ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-success" type="button" onclick="runSingleCustomerTrashAction('restore', <?= (int) $customer['id'] ?>)"><i class="bi bi-arrow-counterclockwise me-1"></i>Recover</button>
                                    <button class="btn btn-sm btn-outline-danger" type="button" onclick="runSingleCustomerTrashAction('delete', <?= (int) $customer['id'] ?>)"><i class="bi bi-trash me-1"></i>Delete forever</button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-trash3 fs-1 d-block mb-2 opacity-50"></i>No deleted customers.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>

        <?php elseif ($trashType === 'users'): ?>
        <!-- USERS TRASH TAB VIEW -->
        <form method="post" action="trash.php?type=users" id="userTrashBulkForm">
            <input type="hidden" name="user_bulk_action" id="userTrashBulkAction">
            <input type="hidden" name="user_action" id="singleUserTrashAction">
            <input type="hidden" name="user_id" id="singleUserTrashId">
            <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="toggleUserTrashSelection" aria-label="Enable user selection" aria-pressed="false" title="Enable user selection">
                        <i class="bi bi-check-square"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="selectAllTrashUsers">Select All</button>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-success btn-sm d-none" id="restoreSelectedUsers" disabled>
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Recover Selected
                    </button>
                    <button type="button" class="btn btn-danger btn-sm d-none" id="deleteSelectedUsers" disabled>
                        <i class="bi bi-trash me-1"></i> Delete Selected Forever
                    </button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light text-uppercase fs-7">
                        <tr>
                            <th class="py-3 ps-3 user-trash-select-column d-none">Select</th>
                            <th class="py-3">User</th>
                            <th class="py-3">Email</th>
                            <th class="py-3">Role</th>
                            <th class="py-3 text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($usersResult && $usersResult->num_rows > 0): ?>
                        <?php while ($user = $usersResult->fetch_assoc()): ?>
                            <tr>
                                <td class="py-3 ps-3 user-trash-select-column d-none">
                                    <input type="checkbox" class="form-check-input trashed-user-checkbox" name="user_ids[]" value="<?= (int)$user['id'] ?>" aria-label="Select user <?= htmlspecialchars($user['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if (!empty($user['avatar']) && file_exists('../uploads/avatars/' . $user['avatar'])): ?>
                                            <img src="../uploads/avatars/<?php echo htmlspecialchars($user['avatar']); ?>" class="rounded-circle border" style="width: 32px; height: 32px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center border" style="width: 32px; height: 32px; font-weight: bold; font-size: 0.85rem;">
                                                <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                        <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($user['role_name']); ?></span></td>
                                <td class="text-end pe-3">
                                    <button class="btn btn-sm btn-outline-success rounded-pill px-3 me-1" type="button" onclick="runSingleUserTrashAction('restore', <?= (int)$user['id'] ?>)">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i> Recover
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger rounded-pill px-3" type="button" onclick="runSingleUserTrashAction('delete', <?= (int)$user['id'] ?>)">
                                        <i class="bi bi-trash me-1"></i> Delete Forever
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-trash3 fs-1 d-block mb-2 opacity-50"></i>No deleted users found in trash.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>

        <?php elseif ($trashType === 'notifications'): ?>
        <section class="notification-trash"
                 data-action-url="../notifications/actions.php"
                 data-csrf-token="<?= htmlspecialchars($_SESSION['notification_csrf'] ??= bin2hex(random_bytes(32)), ENT_QUOTES, 'UTF-8') ?>">
            <div class="d-flex justify-content-between align-items-center gap-2 mb-2 flex-wrap">
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-notification-trash-toggle aria-pressed="false" title="Enable selection">
                        <i class="bi bi-check-square"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm d-none" data-notification-trash-select-all>Select All</button>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-success btn-sm d-none" data-notification-trash-recover disabled>
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Recover Selected
                    </button>
                    <button type="button" class="btn btn-danger btn-sm d-none" data-notification-trash-delete disabled>
                        <i class="bi bi-trash me-1"></i> Delete Forever
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light text-uppercase fs-7">
                        <tr>
                            <th class="py-3 ps-3 notification-trash-select-column d-none" style="width: 50px;">Select</th>
                            <th class="py-3 ps-3">Title</th>
                            <th class="py-3">Message</th>
                            <th class="py-3">Type</th>
                            <th class="py-3">Date</th>
                            <th class="py-3 text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($notificationTrashResult && $notificationTrashResult->num_rows > 0): ?>
                            <?php while ($notif = $notificationTrashResult->fetch_assoc()): ?>
                                <tr data-notification-trash-row>
                                    <td class="py-3 ps-3 notification-trash-select-column d-none">
                                        <input type="checkbox" class="form-check-input notification-trash-checkbox" value="<?= (int)$notif['id'] ?>" aria-label="Select notification">
                                    </td>
                                    <td class="ps-3 fw-semibold text-dark"><?= htmlspecialchars($notif['title'] ?? 'Notification', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($notif['message'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($notif['type'] ?? 'General', ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td><?= htmlspecialchars(!empty($notif['created_at']) ? date('d M Y, H:i', strtotime($notif['created_at'])) : 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-end pe-3">
                                        <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3 me-1" data-notification-trash-single="recover" data-notification-id="<?= (int)$notif['id'] ?>">
                                            <i class="bi bi-arrow-counterclockwise me-1"></i> Recover
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3" data-notification-trash-single="delete" data-notification-id="<?= (int)$notif['id'] ?>">
                                            <i class="bi bi-trash me-1"></i> Delete Forever
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-trash3 fs-1 d-block mb-2 opacity-50"></i>
                                    Notification trash is empty.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <p class="notification-action-error d-none m-3 text-danger" role="alert"></p>
        </section>
        <?php endif; ?>
    </div>
</div>
<script>
// SweetAlert2 Confirmation Helpers for Products, Customers, and Users
function confirmRecover(id) {
    Swal.fire({
        title: 'Recover Product?',
        text: "Yeh product wapas active list mein chala jayega.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Recover!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'trash.php?type=products&recover_id=' + id;
        }
    });
}

function confirmPermDelete(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "Yeh product hamesha ke liye delete ho jayega aur wapas nahi aayega!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Delete Forever!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'trash.php?type=products&perm_delete=' + id;
        }
    });
}

function runTrashedOrderAction(action, orderId, message) {
    const isRestore = action === 'order_restore';
    Swal.fire({
        title: isRestore ? 'Recover Order?' : 'Delete Order Forever?',
        text: message,
        icon: isRestore ? 'question' : 'warning',
        showCancelButton: true,
        confirmButtonColor: isRestore ? '#198754' : '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: isRestore ? 'Yes, Recover!' : 'Yes, Delete Forever!'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('orderTrashBulkAction').value = action;
            document.getElementById('singleTrashedOrderId').value = orderId;
            document.getElementById('orderTrashBulkForm').submit();
        }
    });
}

function runSingleCustomerTrashAction(action, customerId) {
    const isRestore = action === 'restore';
    Swal.fire({
        title: isRestore ? 'Recover Customer?' : 'Delete Customer Forever?',
        text: isRestore ? 'Yeh customer recover ho jayega.' : 'Yeh record hamesha ke liye delete ho jayega!',
        icon: isRestore ? 'question' : 'warning',
        showCancelButton: true,
        confirmButtonColor: isRestore ? '#198754' : '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: isRestore ? 'Yes, Recover!' : 'Yes, Delete Forever!'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('singleCustomerTrashAction').value = action;
            document.getElementById('singleCustomerTrashId').value = customerId;
            document.getElementById('customerTrashBulkForm').submit();
        }
    });
}

function runSingleUserTrashAction(action, userId) {
    const isRestore = action === 'restore';
    Swal.fire({
        title: isRestore ? 'Recover User?' : 'Delete User Forever?',
        text: isRestore ? 'Yeh user dobara active ho jayega.' : 'Yeh user hamesha ke liye delete ho jayega!',
        icon: isRestore ? 'question' : 'warning',
        showCancelButton: true,
        confirmButtonColor: isRestore ? '#198754' : '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: isRestore ? 'Yes, Recover!' : 'Yes, Delete Forever!'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('singleUserTrashAction').value = action;
            document.getElementById('singleUserTrashId').value = userId;
            document.getElementById('userTrashBulkForm').submit();
        }
    });
}

// DOM Content Loaded Handler for Selection & Bulk Actions
document.addEventListener('DOMContentLoaded', function () {
    // ================= PRODUCTS SELECTION LOGIC =================
    const toggleProdBtn = document.getElementById('toggleTrashSelection');
    const selectAllProdBtn = document.getElementById('selectAllTrashProducts');
    const recoverProdBtn = document.getElementById('recoverSelectedButton');
    const deleteProdBtn = document.getElementById('deleteSelectedButton');
    const prodSelectCols = document.querySelectorAll('.trash-select-column');
    const prodCheckboxes = document.querySelectorAll('.trash-product-checkbox');
    const prodTrashForm = document.getElementById('trashBulkForm');
    const prodTrashBulkAction = document.getElementById('bulkAction');

    if (toggleProdBtn) {
        toggleProdBtn.addEventListener('click', function () {
            const isHidden = prodSelectCols[0]?.classList.contains('d-none');
            prodSelectCols.forEach(col => col.classList.toggle('d-none', !isHidden));
            selectAllProdBtn?.classList.toggle('d-none', !isHidden);
            recoverProdBtn?.classList.toggle('d-none', !isHidden);
            deleteProdBtn?.classList.toggle('d-none', !isHidden);
            toggleProdBtn.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
            if (!isHidden) {
                prodCheckboxes.forEach(cb => cb.checked = false);
                updateProdButtons();
            }
        });
    }

    if (selectAllProdBtn) {
        selectAllProdBtn.addEventListener('click', function () {
            const allChecked = Array.from(prodCheckboxes).every(cb => cb.checked);
            prodCheckboxes.forEach(cb => cb.checked = !allChecked);
            selectAllProdBtn.textContent = allChecked ? 'Select All' : 'Deselect All';
            updateProdButtons();
        });
    }

    prodCheckboxes.forEach(cb => {
        cb.addEventListener('change', updateProdButtons);
    });

    function updateProdButtons() {
        const checkedCount = Array.from(prodCheckboxes).filter(cb => cb.checked).length;
        if (recoverProdBtn) recoverProdBtn.disabled = checkedCount === 0;
        if (deleteProdBtn) deleteProdBtn.disabled = checkedCount === 0;
    }

    if (recoverProdBtn && prodTrashForm) {
        recoverProdBtn.addEventListener('click', function () {
            Swal.fire({
                title: 'Recover Selected Products?',
                text: 'Aapne jo products select kiye hain unhe recover kar diya jaye?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Recover!'
            }).then((res) => {
                if (res.isConfirmed) {
                    prodTrashBulkAction.value = 'recover';
                    prodTrashForm.submit();
                }
            });
        });
    }

    if (deleteProdBtn && prodTrashForm) {
        deleteProdBtn.addEventListener('click', function () {
            Swal.fire({
                title: 'Delete Selected Products Forever?',
                text: 'Yeh products hamesha ke liye delete ho jayenge!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Delete Forever!'
            }).then((res) => {
                if (res.isConfirmed) {
                    prodTrashBulkAction.value = 'delete';
                    prodTrashForm.submit();
                }
            });
        });
    }

    // ================= ORDERS SELECTION LOGIC =================
    const toggleOrderBtn = document.getElementById('toggleOrderTrashSelection');
    const selectAllOrderBtn = document.getElementById('selectAllTrashedOrders');
    const restoreOrderBtn = document.getElementById('restoreSelectedOrders');
    const deleteOrderBtn = document.getElementById('deleteSelectedOrders');
    const orderSelectCols = document.querySelectorAll('.order-trash-select-column');
    const orderCheckboxes = document.querySelectorAll('.trashed-order-checkbox');
    const orderTrashForm = document.getElementById('orderTrashBulkForm');
    const orderTrashBulkAction = document.getElementById('orderTrashBulkAction');

    if (toggleOrderBtn) {
        toggleOrderBtn.addEventListener('click', function () {
            const isHidden = orderSelectCols[0]?.classList.contains('d-none');
            orderSelectCols.forEach(col => col.classList.toggle('d-none', !isHidden));
            selectAllOrderBtn?.classList.toggle('d-none', !isHidden);
            restoreOrderBtn?.classList.toggle('d-none', !isHidden);
            deleteOrderBtn?.classList.toggle('d-none', !isHidden);
            toggleOrderBtn.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
            if (!isHidden) {
                orderCheckboxes.forEach(cb => cb.checked = false);
                updateOrderButtons();
            }
        });
    }

    if (selectAllOrderBtn) {
        selectAllOrderBtn.addEventListener('click', function () {
            const allChecked = Array.from(orderCheckboxes).every(cb => cb.checked);
            orderCheckboxes.forEach(cb => cb.checked = !allChecked);
            selectAllOrderBtn.textContent = allChecked ? 'Select All' : 'Deselect All';
            updateOrderButtons();
        });
    }

    orderCheckboxes.forEach(cb => {
        cb.addEventListener('change', updateOrderButtons);
    });

    function updateOrderButtons() {
        const checkedCount = Array.from(orderCheckboxes).filter(cb => cb.checked).length;
        if (restoreOrderBtn) restoreOrderBtn.disabled = checkedCount === 0;
        if (deleteOrderBtn) deleteOrderBtn.disabled = checkedCount === 0;
    }

    if (restoreOrderBtn && orderTrashForm) {
        restoreOrderBtn.addEventListener('click', function () {
            Swal.fire({
                title: 'Recover Selected Orders?',
                text: 'Aapne jo orders select kiye hain unhe recover kar diya jaye?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Recover!'
            }).then((res) => {
                if (res.isConfirmed) {
                    orderTrashBulkAction.value = 'order_restore';
                    orderTrashForm.submit();
                }
            });
        });
    }

    if (deleteOrderBtn && orderTrashForm) {
        deleteOrderBtn.addEventListener('click', function () {
            Swal.fire({
                title: 'Delete Selected Orders Forever?',
                text: 'Yeh orders aur inke items hamesha ke liye delete ho jayenge!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Delete Forever!'
            }).then((res) => {
                if (res.isConfirmed) {
                    orderTrashBulkAction.value = 'order_delete';
                    orderTrashForm.submit();
                }
            });
        });
    }

    // ================= CUSTOMERS SELECTION LOGIC =================
    const toggleCustBtn = document.getElementById('toggleCustomerTrashSelection');
    const selectAllCustBtn = document.getElementById('selectAllTrashCustomers');
    const restoreCustBtn = document.getElementById('restoreSelectedCustomers');
    const deleteCustBtn = document.getElementById('deleteSelectedCustomers');
    const custSelectCols = document.querySelectorAll('.customer-trash-select-column');
    const custCheckboxes = document.querySelectorAll('.trashed-customer-checkbox');
    const custTrashForm = document.getElementById('customerTrashBulkForm');
    const custTrashBulkAction = document.getElementById('customerTrashBulkAction');

    if (toggleCustBtn) {
        toggleCustBtn.addEventListener('click', function () {
            const isHidden = custSelectCols[0]?.classList.contains('d-none');
            custSelectCols.forEach(col => col.classList.toggle('d-none', !isHidden));
            selectAllCustBtn?.classList.toggle('d-none', !isHidden);
            restoreCustBtn?.classList.toggle('d-none', !isHidden);
            deleteCustBtn?.classList.toggle('d-none', !isHidden);
            toggleCustBtn.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
            if (!isHidden) {
                custCheckboxes.forEach(cb => cb.checked = false);
                updateCustButtons();
            }
        });
    }

    if (selectAllCustBtn) {
        selectAllCustBtn.addEventListener('click', function () {
            const allChecked = Array.from(custCheckboxes).every(cb => cb.checked);
            custCheckboxes.forEach(cb => cb.checked = !allChecked);
            selectAllCustBtn.textContent = allChecked ? 'Select All' : 'Deselect All';
            updateCustButtons();
        });
    }

    custCheckboxes.forEach(cb => {
        cb.addEventListener('change', updateCustButtons);
    });

    function updateCustButtons() {
        const checkedCount = Array.from(custCheckboxes).filter(cb => cb.checked).length;
        if (restoreCustBtn) restoreCustBtn.disabled = checkedCount === 0;
        if (deleteCustBtn) deleteCustBtn.disabled = checkedCount === 0;
    }

    if (restoreCustBtn && custTrashForm) {
        restoreCustBtn.addEventListener('click', function () {
            Swal.fire({
                title: 'Recover Selected Customers?',
                text: 'Aapne jo customers select kiye hain unhe recover kar diya jaye?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Recover!'
            }).then((res) => {
                if (res.isConfirmed) {
                    custTrashBulkAction.value = 'restore';
                    custTrashForm.submit();
                }
            });
        });
    }

    if (deleteCustBtn && custTrashForm) {
        deleteCustBtn.addEventListener('click', function () {
            Swal.fire({
                title: 'Delete Selected Customers Forever?',
                text: 'Yeh customers hamesha ke liye delete ho jayenge!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Delete Forever!'
            }).then((res) => {
                if (res.isConfirmed) {
                    custTrashBulkAction.value = 'delete';
                    custTrashForm.submit();
                }
            });
        });
    }

    // ================= USERS SELECTION LOGIC =================
    const toggleUserBtn = document.getElementById('toggleUserTrashSelection');
    const selectAllUserBtn = document.getElementById('selectAllTrashUsers');
    const restoreUserBtn = document.getElementById('restoreSelectedUsers');
    const deleteUserBtn = document.getElementById('deleteSelectedUsers');
    const userSelectCols = document.querySelectorAll('.user-trash-select-column');
    const userCheckboxes = document.querySelectorAll('.trashed-user-checkbox');
    const userTrashForm = document.getElementById('userTrashBulkForm');
    const userTrashBulkAction = document.getElementById('userTrashBulkAction');

    if (toggleUserBtn) {
        toggleUserBtn.addEventListener('click', function () {
            const isHidden = userSelectCols[0]?.classList.contains('d-none');
            userSelectCols.forEach(col => col.classList.toggle('d-none', !isHidden));
            selectAllUserBtn?.classList.toggle('d-none', !isHidden);
            restoreUserBtn?.classList.toggle('d-none', !isHidden);
            deleteUserBtn?.classList.toggle('d-none', !isHidden);
            toggleUserBtn.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
            if (!isHidden) {
                userCheckboxes.forEach(cb => cb.checked = false);
                updateUserButtons();
            }
        });
    }

    if (selectAllUserBtn) {
        selectAllUserBtn.addEventListener('click', function () {
            const allChecked = Array.from(userCheckboxes).every(cb => cb.checked);
            userCheckboxes.forEach(cb => cb.checked = !allChecked);
            updateUserButtons();
        });
    }

    userCheckboxes.forEach(cb => {
        cb.addEventListener('change', updateUserButtons);
    });

    function updateUserButtons() {
        const checkedCount = Array.from(userCheckboxes).filter(cb => cb.checked).length;
        if (restoreUserBtn) restoreUserBtn.disabled = checkedCount === 0;
        if (deleteUserBtn) deleteUserBtn.disabled = checkedCount === 0;
    }

    if (restoreUserBtn) {
        restoreUserBtn.addEventListener('click', function () {
            Swal.fire({
                title: 'Recover Selected Users?',
                text: 'Aapne jo users select kiye hain unhe recover kar diya jaye?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Recover!'
            }).then((res) => {
                if (res.isConfirmed) {
                    userTrashBulkAction.value = 'restore';
                    userTrashForm.submit();
                }
            });
        });
    }

    if (deleteUserBtn) {
        deleteUserBtn.addEventListener('click', function () {
            Swal.fire({
                title: 'Delete Selected Users Forever?',
                text: 'Yeh users hamesha ke liye delete ho jayenge!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Delete Forever!'
            }).then((res) => {
                if (res.isConfirmed) {
                    userTrashBulkAction.value = 'delete';
                    userTrashForm.submit();
                }
            });
        });
    }
});
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>