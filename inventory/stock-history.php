<?php
session_start();
include "../config.php";

// Create stock_history table if not exists
@$conn->query("CREATE TABLE IF NOT EXISTS stock_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT,
    type VARCHAR(50),
    quantity INT,
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$pageTitle = 'Stock History Logs';
include "../layouts/header.php";
?>

<div class="app-main">
    <section class="page-head mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h1>Stock History Logs</h1>
            <p class="text-muted">Audit trail of all inventory additions and deductions.</p>
        </div>
        <div>
            <a href="inventory.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to Inventory
            </a>
        </div>
    </section>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h6 class="m-0 font-weight-bold text-dark">Recent Adjustments</h6>
        </div>
        <div class="table-responsive">
            <table class="table data-table align-middle mb-0" style="width: 100%;">
                <thead>
                    <tr>
                        <th class="ps-3">ID</th>
                        <th>Product Name</th>
                        <th>Type</th>
                        <th>Quantity</th>
                        <th>Reason</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $history_query = $conn->query("SELECT sh.*, p.name as product_name 
                                                   FROM stock_history sh 
                                                   LEFT JOIN products p ON sh.product_id = p.id 
                                                   ORDER BY sh.id DESC");
                    if ($history_query && $history_query->num_rows > 0) {
                        while ($row = $history_query->fetch_assoc()) {
                            $type_badge = ($row['type'] === 'add') ? '<span class="badge bg-success">Added (+)</span>' : '<span class="badge bg-danger">Subtracted (-)</span>';
                            ?>
                            <tr>
                                <td class="ps-3">#<?php echo $row['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($row['product_name'] ?? 'Unknown Product'); ?></strong></td>
                                <td><?php echo $type_badge; ?></td>
                                <td><strong><?php echo $row['quantity']; ?></strong></td>
                                <td><?php echo htmlspecialchars($row['reason'] ?? '-'); ?></td>
                                <td><small class="text-muted"><?php echo $row['created_at']; ?></small></td>
                            </tr>
                            <?php
                        }
                    } else {
                        echo "<tr><td colspan='6' class='text-center text-muted py-3'>No stock history records found yet.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include "../layouts/footer.php"; ?>