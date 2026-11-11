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
    $adjust_type = trim($_POST['adjust_type'] ?? 'add');
    $quantity = intval($_POST['quantity'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');

    if ($product_id <= 0 || $quantity <= 0) {
        $error = "Please select a valid product and quantity.";
    } else {
        // Check if product exists in inventory table
        $check_inv = $conn->query("SELECT quantity FROM inventory WHERE product_id = $product_id");
        
        if ($check_inv && $check_inv->num_rows > 0) {
            $inv_data = $check_inv->fetch_assoc();
            $current_qty = intval($inv_data['quantity']);

            $new_qty = ($adjust_type === 'add') ? ($current_qty + $quantity) : ($current_qty - $quantity);
            if ($new_qty < 0) $new_qty = 0;

            // Update inventory table
            $conn->query("UPDATE inventory SET quantity = $new_qty WHERE product_id = $product_id");
        } else {
            // Insert new record into inventory if not exists
            $current_qty = 6;
            $new_qty = ($adjust_type === 'add') ? $quantity : 0;
            $conn->query("INSERT INTO inventory (product_id, quantity) VALUES ($product_id, $new_qty)");
        }

        $productStmt = $conn->prepare('SELECT name FROM products WHERE id = ?');
        $productStmt->bind_param('i', $product_id);
        $productStmt->execute();
        $productName = $productStmt->get_result()->fetch_column();
        $productStmt->close();
        if (is_string($productName)) {
            notifyAdminStockTransition($conn, $productName, $current_qty, $new_qty);
        }

        // Insert log into stock_history
        $conn->query("INSERT INTO stock_history (product_id, type, quantity, reason, created_at) VALUES ($product_id, '$adjust_type', $quantity, '$reason', NOW())");

        $success = true;
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
                            echo "<option value='{$pr['id']}' $selected>{$pr['name']} (Current Qty: {$pr['quantity']})</option>";
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
                    <a href="inventory.php" class="btn btn-secondary">Back to Inventory</a>
                    <button type="submit" class="btn btn-dark px-4">Update Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include "../layouts/footer.php"; ?>