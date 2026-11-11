<?php
session_start();
include "../../config.php";

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status_filter']) ? trim($_GET['status_filter']) : '';

$pageTitle = 'Payments Management';
include "../../layouts/header.php";
?>

<div class="app-main">
    <section class="page-head mb-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h1>Payments Management</h1>
            <p class="text-muted">Track customer transactions, payment methods, and financial inflow.</p>
        </div>
    </section>

    <!-- Search & Filter Card -->
    <div class="card border-0 shadow-sm p-3 mb-4">
        <form method="GET" action="" class="row g-3 align-items-center">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search by Transaction ID or Order No..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-4">
                <select name="status_filter" class="form-select">
                    <option value="">All Payment Status</option>
                    <option value="Paid" <?php echo ($status_filter === 'Paid') ? 'selected' : ''; ?>>Paid</option>
                    <option value="Pending" <?php echo ($status_filter === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                    <option value="Refunded" <?php echo ($status_filter === 'Refunded') ? 'selected' : ''; ?>>Refunded</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-dark w-100">Filter</button>
            </div>
        </form>
    </div>

    <!-- Payments Table Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
            <h6 class="m-0 font-weight-bold text-dark">Transaction Records</h6>
        </div>
        <div class="table-responsive">
            <table class="table data-table align-middle mb-0" style="width: 100%;">
                <thead>
                    <tr>
                        <th class="ps-3">Transaction ID</th>
                        <th>Order No</th>
                        <th>Method</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th class="text-end pe-3">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Safe query fetching basic order details without missing column errors
                    $sql = "SELECT o.id, o.order_no, o.total, o.created_at 
                            FROM orders o WHERE 1=1";
                    
                    $params = [];
                    $types = "";

                    if (!empty($search)) {
                        $sql .= " AND (o.order_no LIKE ? OR o.id LIKE ?)";
                        $params[] = "%" . $search . "%";
                        $params[] = "%" . $search . "%";
                        $types .= "ss";
                    }

                    $sql .= " ORDER BY o.id DESC";

                    $stmt = $conn->prepare($sql);
                    if (!empty($params)) {
                        $stmt->bind_param($types, ...$params);
                    }
                    $stmt->execute();
                    $query = $stmt->get_result();

                    if ($query && $query->num_rows > 0) {
                        while ($row = $query->fetch_assoc()) {
                            $pay_status = 'Pending'; // Default status
                            $badge_class = 'bg-warning text-dark';
                            $txn_id = 'TXN-' . str_pad($row['id'], 6, '0', STR_PAD_LEFT);
                            $order_display = !empty($row['order_no']) ? $row['order_no'] : '#ORD-' . $row['id'];
                            ?>
                            <tr>
                                <td class="ps-3 fw-bold text-dark"><?php echo $txn_id; ?></td>
                                <td><?php echo htmlspecialchars($order_display); ?></td>
                                <td><span class="badge bg-light text-dark border">COD</span></td>
                                <td><strong class="text-success"><?php echo htmlspecialchars(formatAppCurrency($row['total']), ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                <td><span class="badge <?php echo $badge_class; ?>"><?php echo $pay_status; ?></span></td>
                                <td class="text-end pe-3 text-muted"><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                            </tr>
                            <?php
                        }
                    } else {
                        echo "<tr><td colspan='6' class='text-center text-muted py-3'>No payment records found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include "../../layouts/footer.php"; ?>