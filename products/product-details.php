<?php
session_start();
include "../config.php";

$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch product details along with category name using JOIN
$stmt = $conn->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();

if (!$product) {
    $_SESSION['swal_error'] = "Product not found!";
    header("Location: products.php");
    exit();
}

$productPrice = (float) ($product['price'] ?? $product['sale_price'] ?? $product['original_price'] ?? 0);
$productStock = (int) ($product['stock'] ?? 0);
$productStatus = (string) ($product['status'] ?? 'Unknown');
$productAddedDate = !empty($product['created_at']) ? date('d M Y', strtotime($product['created_at'])) : 'N/A';
$pageTitle = 'Product Details';
$parent = ['Products', 'products.php'];
include __DIR__ . '/../layouts/header.php';
?>

<div class="container-fluid px-4">
    <!-- Top Header Card -->
    <div class="card border-0 shadow-sm p-4 mb-4">
        <div class="row align-items-center">
            <div class="col-md-2 text-center mb-3 mb-md-0">
                <?php if (!empty($product['image'])): ?>
                    <img src="../uploads/<?php echo htmlspecialchars($product['image']); ?>" alt="Product Image" class="img-fluid rounded shadow-sm" style="max-height: 120px; object-fit: cover;">
                <?php else: ?>
                    <div class="bg-secondary text-white rounded d-flex align-items-center justify-content-center shadow-sm" style="height: 100px; width: 100px; margin: 0 auto;">
                        <i class="bi bi-box-seam fs-2"></i>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-md-10">
                <h2 class="mb-1 fw-bold"><?php echo htmlspecialchars($product['name']); ?></h2>
                <p class="text-muted mb-0">
                    SKU: <span class="fw-semibold text-dark"><?php echo htmlspecialchars($product['sku'] ?? 'N/A'); ?></span> | 
                    Category: <span class="fw-semibold text-dark"><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></span>
                </p>
            </div>
        </div>
    </div>

    <!-- Details Grid Cards -->
    <div class="row g-4">
        <!-- Pricing & Inventory Card -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm p-4 h-100">
                <h4 class="card-title fw-bold mb-3">Pricing & Inventory</h4>
                <div class="mb-3">
                    <span class="text-muted d-block">Price</span>
                    <h3 class="fw-bold text-success"><?php echo htmlspecialchars(formatAppCurrency($productPrice), ENT_QUOTES, 'UTF-8'); ?></h3>
                </div>
                <div class="mb-3">
                    <span class="text-muted d-block">Stock Quantity</span>
                    <h5 class="fw-semibold text-dark"><?php echo $productStock; ?> units</h5>
                </div>
                <div>
                    <span class="text-muted d-block">Status</span>
                    <span class="badge bg-<?php echo ($productStatus === 'Active') ? 'success' : 'secondary'; ?>">
                        <?php echo htmlspecialchars($productStatus, ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </div>
                <div class="mt-3">
                    <span class="text-muted d-block">Added Date</span>
                    <span class="fw-semibold text-dark"><?php echo htmlspecialchars($productAddedDate, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>
        </div>

        <!-- Description Card -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm p-4 h-100">
                <h4 class="card-title fw-bold mb-3">Description</h4>
                <p class="text-muted mb-0">
                    <?php echo !empty($product['description']) ? nl2br(htmlspecialchars($product['description'])) : 'No description available for this product.'; ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="mt-4">
        <a href="edit-product.php?id=<?php echo $product['id']; ?>" class="btn btn-dark">Edit Product</a>
        <a href="products.php" class="btn btn-outline-secondary ms-2">Back to List</a>
    </div>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>