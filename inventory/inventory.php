<?php
session_start();
include "../config.php";

// Auto-sync products into inventory table if missing
$all_prods = $conn->query("SELECT id FROM products");
if ($all_prods) {
    while ($ap = $all_prods->fetch_assoc()) {
        $pid = $ap['id'];
        $check_exists = $conn->query("SELECT product_id FROM inventory WHERE product_id = $pid");
        if ($check_exists && $check_exists->num_rows == 0) {
            $conn->query("INSERT INTO inventory (product_id, quantity) VALUES ($pid, 10)"); // Default quantity 10
        }
    }
}

// Search and Filter Inputs
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$stock_filter = isset($_GET['stock_filter']) ? trim($_GET['stock_filter']) : '';

$pageTitle = 'Inventory Management';
include "../layouts/header.php";
?>

<div class="app-main">
    <section class="page-head mb-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h1>Inventory Management</h1>
            <p class="text-muted">Monitor stock levels and manage warehouse inventory.</p>
        </div>
        <div>
            <a href="stock-adjust.php" class="btn btn-dark btn-sm me-1">
                <i class="bi bi-sliders2 me-1"></i> Adjust Stock
            </a>
            <a href="stock-history.php" class="btn btn-outline-secondary btn-sm me-1">
                <i class="bi bi-clock-history me-1"></i> Stock History
            </a>
            <a href="suppliers.php" class="btn btn-outline-dark btn-sm">
                <i class="bi bi-truck me-1"></i> Suppliers
            </a>
        </div>
    </section>

    <!-- Search & Filter Card -->
    <div class="card border-0 shadow-sm p-3 mb-4">
        <form method="GET" action="" class="row g-3 align-items-center">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search by product name or SKU..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-4">
                <select name="stock_filter" class="form-select">
                    <option value="">All Stock Status</option>
                    <option value="in_stock" <?php echo ($stock_filter === 'in_stock') ? 'selected' : ''; ?>>In Stock (> 5)</option>
                    <option value="low_stock" <?php echo ($stock_filter === 'low_stock') ? 'selected' : ''; ?>>Low Stock (1 - 5)</option>
                    <option value="out_of_stock" <?php echo ($stock_filter === 'out_of_stock') ? 'selected' : ''; ?>>Out of Stock (0)</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-dark w-100">Filter</button>
            </div>
        </form>
    </div>

    <!-- Inventory Table Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
            <h6 class="m-0 font-weight-bold text-dark">Current Stock Levels</h6>
        </div>
        <div class="table-responsive">
            <table class="table data-table align-middle mb-0" style="width: 100%;">
                <thead>
                    <tr>
                        <th class="ps-3">Product Name</th>
                        <th>SKU / ID</th>
                        <th>Current Quantity</th>
                        <th>Status</th>
                        <th class="text-end pe-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Base query to fetch all inventory rows ordered by latest
                    $sql = "SELECT p.id as product_id, p.name, p.sku, COALESCE(i.quantity, 0) as quantity 
                            FROM products p 
                            LEFT JOIN inventory i ON p.id = i.product_id 
                            ORDER BY p.id DESC";
                    
                    $result = $conn->query($sql);
                    $rows = [];

                    if ($result && $result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            // 1. Stock Status Filter Check first
                            $qty = intval($row['quantity']);
                            $matches_stock = true;

                            if ($stock_filter === 'in_stock' && $qty <= 5) $matches_stock = false;
                            if ($stock_filter === 'low_stock' && ($qty < 1 || $qty > 5)) $matches_stock = false;
                            if ($stock_filter === 'out_of_stock' && $qty > 0) $matches_stock = false;

                            if (!$matches_stock) continue;

                            // 2. Smart PHP Search / Typo & Keyword Filter
                            if (!empty($search)) {
                                $searchLower = mb_strtolower(trim($search));
                                $nameLower = mb_strtolower($row['name']);
                                $skuLower = mb_strtolower($row['sku']);

                                $found = false;

                                // Exact or partial substring check (e.g. "malak" in "Farhan Malak")
                                if (strpos($nameLower, $searchLower) !== false || strpos($skuLower, $searchLower) !== false) {
                                    $found = true;
                                } else {
                                    $searchWords = explode(' ', $searchLower);
                                    $nameWords = explode(' ', $nameLower);

                                    foreach ($searchWords as $sw) {
                                        $sw = trim($sw);
                                        if (empty($sw)) continue;

                                        if (strpos($nameLower, $sw) !== false || strpos($skuLower, $sw) !== false) {
                                            $found = true;
                                            break;
                                        }

                                        foreach ($nameWords as $nw) {
                                            $nw = trim($nw);
                                            similar_text($sw, $nw, $percent);
                                            if (soundex($sw) === soundex($nw) || $percent >= 60 || levenshtein($sw, $nw) <= 2) {
                                                $found = true;
                                                break 2;
                                            }
                                        }
                                    }
                                }

                                if (!$found) {
                                    continue; 
                                }
                            }

                            $rows[] = $row;
                        }
                    }

                    if (!empty($rows)) {
                        foreach ($rows as $row) {
                            $qty = intval($row['quantity']);
                            $status_badge = ($qty > 5) ? '<span class="badge bg-success">In Stock</span>' : '<span class="badge bg-warning text-dark">Low Stock</span>';
                            if ($qty <= 0) $status_badge = '<span class="badge bg-danger">Out of Stock</span>';
                            ?>
                            <tr>
                                <td class="ps-3 fw-bold"><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><span class="text-muted"><?php echo !empty($row['sku']) ? htmlspecialchars($row['sku']) : '#PROD-' . $row['product_id']; ?></span></td>
                                <td><strong><?php echo $qty; ?> units</strong></td>
                                <td><?php echo $status_badge; ?></td>
                                <td class="text-end pe-3">
                                    <a href="stock-adjust.php?product_id=<?php echo $row['product_id']; ?>" class="btn btn-sm btn-outline-dark px-2 py-1" style="font-size: 11px;">Adjust</a>
                                </td>
                            </tr>
                            <?php
                        }
                    } else {
                        echo "<tr><td colspan='5' class='text-center text-muted py-3'>No inventory items found matching your search.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include "../layouts/footer.php"; ?>