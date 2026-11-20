<?php
session_start();
include "../config.php";

// Ensure stock_history table exists
@$conn->query("CREATE TABLE IF NOT EXISTS stock_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT,
    type VARCHAR(50),
    quantity INT,
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = intval($_POST['product_id'] ?? 0);
    $adjust_type = $_POST['adjust_type'] ?? '';
    $quantity = intval($_POST['quantity'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif ($product_id <= 0 || $quantity <= 0 || !in_array($adjust_type, ['add', 'subtract'], true) || mb_strlen($reason) > 1000) {
        $error = "Please select a valid product and quantity.";
    } else {
        try {
            $conn->begin_transaction();
            $productStmt = $conn->prepare('SELECT name FROM products WHERE id = ? LIMIT 1');
            $productStmt->bind_param('i', $product_id);
            $productStmt->execute();
            $productName = $productStmt->get_result()->fetch_column();
            $productStmt->close();
            if (!is_string($productName)) {
                throw new RuntimeException('The selected product does not exist.');
            }

            $inventoryStmt = $conn->prepare('SELECT quantity FROM inventory WHERE product_id = ? FOR UPDATE');
            $inventoryStmt->bind_param('i', $product_id);
            $inventoryStmt->execute();
            $inventoryRow = $inventoryStmt->get_result()->fetch_assoc();
            $inventoryStmt->close();

            $current_qty = (int) ($inventoryRow['quantity'] ?? 0);
            $new_qty = $adjust_type === 'add'
                ? $current_qty + $quantity
                : max(0, $current_qty - $quantity);

            if ($inventoryRow) {
                $updateStmt = $conn->prepare('UPDATE inventory SET quantity = ? WHERE product_id = ?');
                $updateStmt->bind_param('ii', $new_qty, $product_id);
                $updateStmt->execute();
                $updateStmt->close();
            } else {
                $insertInventory = $conn->prepare('INSERT INTO inventory (product_id, quantity) VALUES (?, ?)');
                $insertInventory->bind_param('ii', $product_id, $new_qty);
                $insertInventory->execute();
                $insertInventory->close();
            }

            $historyStmt = $conn->prepare(
                'INSERT INTO stock_history (product_id, type, quantity, reason, created_at) VALUES (?, ?, ?, ?, NOW())'
            );
            $historyStmt->bind_param('isis', $product_id, $adjust_type, $quantity, $reason);
            $historyStmt->execute();
            $historyStmt->close();
            $conn->commit();

            notifyAdminStockTransition($conn, $productName, $current_qty, $new_qty, (int) $product_id);
            $success = true;
        } catch (mysqli_sql_exception | RuntimeException $exception) {
            $conn->rollback();
            error_log('Unable to adjust product stock: ' . $exception->getMessage());
            $error = 'The stock adjustment could not be saved.';
        }
    }
}

$pageTitle = 'Stock Adjustment';
include "../layouts/header.php";
?>

<!-- SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="app-main">
    <section class="page-head mb-4">
        <h1>Stock Adjustment</h1>
        <p class="text-muted">Add or remove stock quantities from warehouse.</p>
    </section>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Stock Updated!',
                    text: 'Inventory has been successfully adjusted.',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = 'inventory.php';
                });
            });
        </script>
    <?php endif; ?>

    <div class="card border-0 shadow-sm col-lg-8 mx-auto">
        <div class="card-body p-4">
            <form action="stock-adjust.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="mb-3">
                    <label class="form-label font-weight-bold">Select Product</label>
                    <select name="product_id" class="form-select" required>
                        <option value="">-- Choose Product --</option>
                        <?php
                        $prod_res = $conn->query("SELECT p.id, p.name, COALESCE(i.quantity, 0) as quantity 
                                                   FROM products p 
                                                   LEFT JOIN inventory i ON p.id = i.product_id");
                        while ($pr = $prod_res->fetch_assoc()) {
                            $selected = (isset($_GET['product_id']) && $_GET['product_id'] == $pr['id']) ? 'selected' : '';
                            echo '<option value="' . (int) $pr['id'] . '" ' . $selected . '>' . htmlspecialchars($pr['name'], ENT_QUOTES, 'UTF-8') . ' (Current Qty: ' . (int) $pr['quantity'] . ')</option>';
                        }
                        ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label font-weight-bold">Adjustment Type</label>
                    <select name="adjust_type" class="form-select" required>
                        <option value="add">Add Stock (+)</option>
                        <option value="subtract">Subtract Stock (-)</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label font-weight-bold">Quantity</label>
                    <input type="number" min="1" name="quantity" class="form-control" placeholder="Enter quantity..." required>
                </div>

                <div class="mb-4">
                    <label class="form-label font-weight-bold">Reason / Notes</label>
                    <textarea name="reason" class="form-control" rows="3" placeholder="e.g. New shipment arrived, damaged items, etc."></textarea>
                </div>

                <div class="d-flex justify-content-between">
                    <?php if (appUserCan('inventory')): ?><a href="inventory.php" class="btn btn-secondary">Back to Inventory</a><?php endif; ?>
                    <?php if (appUserCan('stock_management', 'edit')): ?><button type="submit" class="btn btn-dark px-4">Update Stock</button><?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include "../layouts/footer.php"; ?>