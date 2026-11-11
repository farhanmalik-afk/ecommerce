<?php
session_start();
include "../config.php";

// Soft Delete Product Action (Moves to Trash: is_deleted = 1)
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    try {
        $stmt = $conn->prepare("UPDATE products SET is_deleted = 1 WHERE id = ?");
        $stmt->bind_param("i", $del_id);
        if ($stmt->execute()) {
            $_SESSION['swal_success'] = "Product moved to trash successfully!";
        } else {
            $_SESSION['swal_error'] = "Failed to move product to trash!";
        }
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        $_SESSION['swal_error'] = "Cannot move this product to trash!";
    }
    header("Location: products.php");
    exit();
}

// Bulk soft delete selected active products
if (isset($_POST['bulk_delete'])) {
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

    if (count($selected_ids) < 2) {
        $_SESSION['swal_error'] = "Please select at least two products.";
    } else {
        try {
            $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
            $types = str_repeat('i', count($selected_ids));
            $stmt = $conn->prepare("UPDATE products SET is_deleted = 1 WHERE is_deleted = 0 AND id IN ($placeholders)");
            $stmt->bind_param($types, ...$selected_ids);
            $stmt->execute();
            $deleted_count = $stmt->affected_rows;
            $stmt->close();

            if ($deleted_count > 0) {
                $_SESSION['swal_success'] = $deleted_count . " product(s) moved to trash successfully!";
            } else {
                $_SESSION['swal_error'] = "No active products were found in your selection.";
            }
        } catch (mysqli_sql_exception $e) {
            $_SESSION['swal_error'] = "Cannot move the selected products to trash!";
        }
    }
    header("Location: products.php");
    exit();
}

// Status Toggle Action
if (isset($_GET['toggle_id'])) {
    $t_id = intval($_GET['toggle_id']);
    $check_stmt = $conn->prepare("SELECT status FROM products WHERE id = ? AND is_deleted = 0");
    $check_stmt->bind_param("i", $t_id);
    $check_stmt->execute();
    $res = $check_stmt->get_result();
    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $new_status = ($row['status'] == 'Active') ? 'Draft' : 'Active';
        $check_stmt->close();

        $stmt = $conn->prepare("UPDATE products SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $t_id);
        if ($stmt->execute()) {
            $_SESSION['swal_success'] = "Product status updated successfully!";
        } else {
            $_SESSION['swal_error'] = "Failed to update status!";
        }
        $stmt->close();
    }
    header("Location: products.php");
    exit();
}

// Search & Filter Inputs
$search_name = isset($_GET['search_name']) ? trim($_GET['search_name']) : '';
$search_sku  = isset($_GET['search_sku']) ? trim($_GET['search_sku']) : '';
$category_filter = isset($_GET['category_id']) ? intval($_GET['category_id']) : '';
$per_page_options = [10, 25, 50, 100];
$requested_per_page = filter_var($_GET['per_page'] ?? 10, FILTER_VALIDATE_INT);
$per_page = in_array($requested_per_page, $per_page_options, true) ? $requested_per_page : 10;

$pageTitle = 'Products Management';
$parent = ['Dashboard', 'index.php'];
include __DIR__ . '/../layouts/header.php';
?>

<!-- SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    /* Explicitly handle product select column visibility & widths */
    #productsTable th.product-select-column,
    #productsTable td.product-select-column {
        width: 40px;
        text-align: center;
    }
    #productsTable th.product-select-column.d-none,
    #productsTable td.product-select-column.d-none {
        display: none !important;
    }
    #productsTable th.product-select-column:not(.d-none),
    #productsTable td.product-select-column:not(.d-none) {
        display: table-cell !important;
    }

    /* Tighten Product Name & SKU columns to reduce gap */
    #productsTable th.product-name-heading,
    #productsTable td.product-name-cell {
        width: 150px !important;
        max-width: 150px !important;
        padding-right: 4px !important;
    }
    #productsTable th.product-sku-column,
    #productsTable td.product-sku-column {
        width: 120px !important;
        max-width: 120px !important;
        padding-left: 4px !important;
    }

    .product-name-wrapper {
        display: flex;
        align-items: center;
        gap: 8px;
        overflow: hidden;
    }
    .product-name-wrapper img {
        flex-shrink: 0;
        width: 32px;
        height: 32px;
    }
    .product-name-wrapper .product-title-text {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        font-size: 0.9rem;
    }

    /* Product Row Actions Spacing & Separation */
    .product-row-actions {
        display: flex !important;
        align-items: center !important;
        justify-content: flex-end !important;
        gap: 6px !important;
        visibility: visible !important;
        opacity: 1 !important;
    }

    /* Reduce Action Icons Size & Clean Styling */
    #productsTable .product-action-link,
    #productsTable .product-action-delete {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        visibility: visible !important;
        opacity: 1 !important;
        font-size: 0.85rem !important;
        width: 26px;
        height: 26px;
        background: transparent !important;
        border: none !important;
        padding: 0;
        text-decoration: none;
    }
    #productsTable .product-action-delete {
        color: #dc3545 !important;
    }
    #productsTable .product-action-link:hover,
    #productsTable .product-action-delete:hover {
        opacity: 0.7 !important;
    }
</style>

<section class="page-head d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
  <div>
    <h1>Products</h1>
    <p class="text-muted">Manage your store products, pricing, and stock.</p>
  </div>
  <!-- Action Buttons: Add, Import, Export -->
  <div class="d-flex gap-2">
     <a href="export_products.php" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-arrow-down me-1"></i> Export</a>
    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#importModal"><i class="bi bi-file-earmark-arrow-up me-1"></i> Import</button>
    <a href="add-product.php" class="btn btn-dark"><i class="bi bi-plus-lg me-1"></i> Add Product</a>
  </div>
</section>

<!-- Advanced Search & Filter Card with Separate SKU & Name Boxes -->
<div class="card border-0 shadow-sm p-3 mb-4">
    <form method="GET" action="" class="row g-3 align-items-center">
        <!-- Product Name Box -->
        <div class="col-md-3">
            <div class="input-group">
                <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="search_name" class="form-control" placeholder="Product name..." value="<?php echo htmlspecialchars($search_name); ?>">
            </div>
        </div>

        <!-- SKU Box -->
        <div class="col-md-3">
            <div class="input-group">
                <span class="input-group-text bg-light"><i class="bi bi-upc-scan text-muted"></i></span>
                <input type="text" name="search_sku" class="form-control" placeholder="SKU (e.g. SKU-001)..." value="<?php echo htmlspecialchars($search_sku); ?>">
            </div>
        </div>

        <!-- Category / Type Dropdown Box -->
        <div class="col-md-3">
            <select name="category_id" class="form-select">
                <option value="">All Categories</option>
                <?php
                $cat_res = $conn->query("SELECT id, name FROM categories");
                if ($cat_res && $cat_res->num_rows > 0) {
                    while ($cat = $cat_res->fetch_assoc()) {
                        $selected = ($category_filter == $cat['id']) ? 'selected' : '';
                        echo "<option value='" . $cat['id'] . "' $selected>" . htmlspecialchars($cat['name']) . "</option>";
                    }
                }
                ?>
            </select>
        </div>

        <!-- Action Buttons -->
        <div class="col-md-3">
            <button type="submit" class="btn btn-dark">Filter</button>
            <?php if (!empty($search_name) || !empty($search_sku) || !empty($category_filter)): ?>
                <a href="products.php" class="btn btn-outline-secondary ms-2">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Products Table Card -->
<div class="card table-card rounded-4 shadow-sm border-0">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 p-3 border-bottom bg-light">
    <div class="d-flex align-items-center gap-2">
      <button type="button" class="table-sort-toggle" data-sort-toggle data-sort-target="productsTable" aria-label="Sort Ascending" title="Sort Ascending" aria-pressed="false">
        <i class="bi bi-sort-up-alt" aria-hidden="true"></i>
      </button>
      <button type="button" class="btn btn-outline-secondary btn-sm" id="toggleProductSelection" aria-label="Enable product selection" aria-pressed="false" title="Enable product selection">
        <i class="bi bi-check-square"></i>
      </button>
      <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="selectAllProducts">Select All</button>
    </div>
    <div class="d-flex align-items-center gap-3 flex-wrap">
      <form method="GET" action="products.php" class="d-flex align-items-center gap-2 mb-0">
        <input type="hidden" name="search_name" value="<?= htmlspecialchars($search_name, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="search_sku" value="<?= htmlspecialchars($search_sku, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="category_id" value="<?= (int)$category_filter ?>">
        <label for="productPerPage" class="small fw-medium text-secondary text-nowrap mb-0">Show on page</label>
        <select id="productPerPage" name="per_page" class="form-select form-select-sm w-auto border-secondary-subtle text-dark shadow-sm" onchange="this.form.submit()" aria-label="Products per page">
          <?php foreach ($per_page_options as $option): ?>
            <option value="<?= $option ?>" <?= $per_page === $option ? 'selected' : '' ?>><?= $option ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <button type="submit" form="bulkDeleteForm" class="btn btn-danger btn-sm d-none" id="bulkDeleteButton" disabled>
        <i class="bi bi-trash me-1"></i> Move Selected to Trash
      </button>
    </div>
  </div>
  <form method="POST" action="products.php" id="bulkDeleteForm">
    <input type="hidden" name="bulk_delete" value="1">
    <div class="table-responsive product-list-responsive">
      <table class="table table-hover data-table align-middle mb-0" id="productsTable">
        <thead class="table-light">
          <tr>
            <th class="ps-4 product-select-column d-none">Select</th>
            <th class="product-number-heading">#</th>
            <th class="product-name-heading">Product Name</th>
            <th class="product-sku-column">SKU</th>
            <th class="product-category-column">Category</th>
            <th class="product-price-column">Price</th>
            <th class="product-stock-column">Stock</th>
            <th class="product-status-column">Status</th>
            <th class="product-date-column list-secondary-column">Added Date</th>
            <th class="text-end pe-4">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php
        // Only fetch non-deleted products
        $sql = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.is_deleted = 0";
        $params = [];
        $types = "";

        if (!empty($search_name)) {
            $keywords = explode(' ', $search_name);
            $nameClauses = [];
            foreach ($keywords as $word) {
                $word = trim($word);
                if (!empty($word)) {
                    $searchTerm = "%" . $word . "%";
                    
                    $fuzzyWord = "%";
                    for ($i = 0; $i < strlen($word); $i++) {
                        $fuzzyWord .= $word[$i] . "%";
                    }

                    $nameClauses[] = "(p.name LIKE ? OR p.name LIKE ? OR SOUNDEX(p.name) = SOUNDEX(?))";
                    $params[] = $searchTerm;
                    $params[] = $fuzzyWord;
                    $params[] = $word;
                    $types .= "sss";
                }
            }
            if (!empty($nameClauses)) {
                $sql .= " AND (" . implode(" OR ", $nameClauses) . ")";
            }
        }

        if (!empty($search_sku)) {
            $sql .= " AND p.sku LIKE ?";
            $params[] = "%" . $search_sku . "%";
            $types .= "s";
        }

        if (!empty($category_filter)) {
            $sql .= " AND p.category_id = ?";
            $params[] = $category_filter;
            $types .= "i";
        }

        $count_sql = "SELECT COUNT(*)" . substr($sql, strpos($sql, " FROM "));
        $count_stmt = $conn->prepare($count_sql);
        if (!empty($params)) {
            $count_stmt->bind_param($types, ...$params);
        }
        $count_stmt->execute();
        $total_products = (int)$count_stmt->get_result()->fetch_row()[0];
        $count_stmt->close();

        $total_pages = max(1, (int)ceil($total_products / $per_page));
        $requested_page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
        $current_page = $requested_page !== false && $requested_page > 0
            ? min($requested_page, $total_pages)
            : 1;
        
        $offset = ($current_page - 1) * $per_page;
        $row_number = $offset + 1;

        $sql .= " ORDER BY p.id DESC LIMIT $per_page OFFSET $offset";

        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $status = $row['status'] ?? 'Active';
                $status_badge = ($status == 'Active') ? 'bg-success' : 'bg-secondary';
               
                $img_name = $row['image'] ?? '';
                if (empty($img_name)) {
                    $img_name = "https://cdn-icons-png.flaticon.com/512/1165/1165687.png";
                }

                if (filter_var($img_name, FILTER_VALIDATE_URL)) {
                    $img_path = $img_name;
                } else {
                    $img_path = "../uploads/" . $img_name;
                    if (!file_exists($img_path)) {
                        $img_path = "https://cdn-icons-png.flaticon.com/512/1165/1165687.png";
                    }
                }

                $avatar_display = "<img src='{$img_path}' width='32' height='32' class='rounded border object-fit-cover'>";

                echo "<tr>
                        <td class='ps-4 product-select-column d-none'><input type='checkbox' class='form-check-input product-checkbox' name='product_ids[]' value='" . (int)$row['id'] . "' aria-label='Select " . htmlspecialchars($row['name'], ENT_QUOTES) . "'></td>
                        <td class='product-number-cell'>" . $row_number++ . "</td>
                        <td class='product-name-cell'>
                          <div class='product-name-wrapper'>
                            {$avatar_display}
                            <div class='product-title-text'>
                               <strong>" . htmlspecialchars($row['name']) . "</strong>
                            </div>
                          </div>
                        </td>
                        <td class='product-sku-column text-truncate'>" . htmlspecialchars($row['sku'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . "</td>
                        <td class='product-category-column'><span class='badge bg-light text-dark border'>" . htmlspecialchars($row['category_name'] ?? 'N/A') . "</span></td>
                        <td class='product-price-column'><strong>" . htmlspecialchars(formatAppCurrency($row['price'] ?? 0), ENT_QUOTES, 'UTF-8') . "</strong></td>
                        <td class='product-stock-column'><span class='badge bg-info text-dark'>" . ($row['stock'] ?? 0) . " units</span></td>
                        <td class='product-status-column'><span class='badge {$status_badge}'>{$status}</span></td>
                        <td class='product-date-column list-secondary-column'><small class='text-muted'>" . date('d M Y', strtotime($row['created_at'] ?? 'now')) . "</small></td>
                        <td class='product-actions-cell'>
                          <div class='product-row-actions'>
                            <a href='product-details.php?id=" . (int) $row['id'] . "' class='product-action-link product-action-view' title='View details' aria-label='View " . htmlspecialchars($row['name'], ENT_QUOTES) . "'><i class='bi bi-eye' aria-hidden='true'></i></a>
                            <button type='button' onclick='confirmStatusChange(" . (int) $row['id'] . ", this.dataset.currentStatus)' data-current-status='" . htmlspecialchars($status, ENT_QUOTES) . "' class='product-action-link product-action-status' title='Toggle status' aria-label='Toggle status for " . htmlspecialchars($row['name'], ENT_QUOTES) . "'><i class='bi bi-slash-circle' aria-hidden='true'></i></button>
                            <a href='edit-product.php?id=" . (int) $row['id'] . "' class='product-action-link product-action-edit' title='Edit product' aria-label='Edit " . htmlspecialchars($row['name'], ENT_QUOTES) . "'><i class='bi bi-pencil-square' aria-hidden='true'></i></a>
                            <button type='button' onclick='confirmDelete(" . (int) $row['id'] . ")' class='product-action-link product-action-delete' title='Move to Trash' aria-label='Move " . htmlspecialchars($row['name'], ENT_QUOTES) . " to Trash'><i class='bi bi-trash' aria-hidden='true'></i></button>
                          </div>
                        </td>
                      </tr>";
            }
        } else {
            echo "<tr><td colspan='10' class='text-center py-4 text-muted'>No products found matching your search criteria.</td></tr>";
        }
        ?>
        </tbody>
      </table>
    </div>
  </form>
</div>

<?php if ($total_pages > 1): ?>
<nav aria-label="Products pagination" class="mt-3">
  <ul class="pagination justify-content-end">
    <?php for ($page_number = 1; $page_number <= $total_pages; $page_number++): ?>
      <?php $page_query = http_build_query(array_merge($_GET, ['page' => $page_number])); ?>
      <li class="page-item <?= $page_number === $current_page ? 'active' : '' ?>">
        <a class="page-link" href="?<?= htmlspecialchars($page_query, ENT_QUOTES, 'UTF-8') ?>"><?= $page_number ?></a>
      </li>
    <?php endfor; ?>
  </ul>
</nav>
<?php endif; ?>

<!-- Import Products Modal -->
<div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow">
      <form action="import_products.php" method="POST" enctype="multipart/form-data">
        <div class="modal-header border-0 pb-0">
          <h5 class="fw-bold text-dark"><i class="bi bi-file-earmark-arrow-up me-2 text-success"></i> Import Products (CSV)</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label text-muted small">Upload CSV File (Columns: ID, Name, Barcode, Price, Stock)</label>
            <input type="file" name="csv_file" class="form-control" accept=".csv" required>
          </div>
          <div class="alert alert-info py-2 small mb-0">
            <i class="bi bi-info-circle-fill me-1"></i> Make sure your CSV file matches the export format.
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="import_submit" class="btn btn-success rounded-pill px-4">Upload & Import</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const productCheckboxes = document.querySelectorAll('.product-checkbox');
const productSelectionColumns = document.querySelectorAll('.product-select-column');
const toggleProductSelectionButton = document.getElementById('toggleProductSelection');
const selectAllProductsButton = document.getElementById('selectAllProducts');
const bulkDeleteButton = document.getElementById('bulkDeleteButton');
const bulkDeleteForm = document.getElementById('bulkDeleteForm');
let bulkDeleteConfirmed = false;
let productSelectionEnabled = false;

function updateBulkDeleteControls() {
    const selectedCount = document.querySelectorAll('.product-checkbox:checked').length;
    bulkDeleteButton.disabled = selectedCount < 2;
    selectAllProductsButton.textContent = productCheckboxes.length > 0 && selectedCount === productCheckboxes.length
        ? 'Deselect All'
        : 'Select All';
}

toggleProductSelectionButton.addEventListener('click', () => {
    productSelectionEnabled = !productSelectionEnabled;
    productSelectionColumns.forEach((column) => {
        if (productSelectionEnabled) {
            column.classList.remove('d-none');
        } else {
            column.classList.add('d-none');
        }
    });
    selectAllProductsButton.classList.toggle('d-none', !productSelectionEnabled);
    bulkDeleteButton.classList.toggle('d-none', !productSelectionEnabled);
    toggleProductSelectionButton.setAttribute('aria-pressed', String(productSelectionEnabled));
    toggleProductSelectionButton.setAttribute(
        'aria-label',
        productSelectionEnabled ? 'Disable product selection' : 'Enable product selection'
    );
    toggleProductSelectionButton.title = productSelectionEnabled
        ? 'Disable product selection'
        : 'Enable product selection';

    if (!productSelectionEnabled) {
        document.querySelectorAll('.product-checkbox').forEach((checkbox) => {
            checkbox.checked = false;
        });
    }
    updateBulkDeleteControls();
});

selectAllProductsButton.addEventListener('click', () => {
    const selectAll = selectAllProductsButton.textContent === 'Select All';
    document.querySelectorAll('.product-checkbox').forEach((checkbox) => {
        checkbox.checked = selectAll;
    });
    updateBulkDeleteControls();
});

document.addEventListener('change', (e) => {
    if (e.target.classList.contains('product-checkbox')) {
        updateBulkDeleteControls();
    }
});

document.querySelectorAll('#bulkDeleteForm tbody tr').forEach((row) => {
    row.addEventListener('click', (event) => {
        if (!productSelectionEnabled || event.target.closest('a, button, input, select, textarea, label')) {
            return;
        }

        const checkbox = row.querySelector('.product-checkbox');
        if (checkbox) {
            checkbox.checked = !checkbox.checked;
            updateBulkDeleteControls();
        }
    });
});

bulkDeleteForm.addEventListener('submit', (event) => {
    if (bulkDeleteConfirmed) {
        return;
    }
    event.preventDefault();
    const selectedCount = document.querySelectorAll('.product-checkbox:checked').length;
    if (selectedCount < 2) {
        return;
    }
    Swal.fire({
        title: 'Move selected products to Trash?',
        text: selectedCount + ' product(s) will be moved to the trash bin.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, move them!'
    }).then((result) => {
        if (result.isConfirmed) {
            bulkDeleteConfirmed = true;
            bulkDeleteForm.submit();
        }
    });
});

function confirmDelete(id) {
    Swal.fire({
        title: 'Move to Trash?',
        text: "This product will be moved to the trash bin!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, move it!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'products.php?delete_id=' + id;
        }
    });
}

function confirmStatusChange(id, currentStatus) {
    let targetStatus = (currentStatus === 'Active') ? 'Draft' : 'Active';
    Swal.fire({
        title: 'Change Status?',
        text: 'Are you sure you want to make this product ' + targetStatus + '?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#198754', // Green color for success/active state
        cancelButtonColor: '#6c757d',  // Neutral grey for cancel
        confirmButtonText: 'Yes, change it!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'products.php?toggle_id=' + id;
        }
    });
}
</script>

<script src="../assets/js/table-sort.js" defer></script>
<?php if (isset($_SESSION['swal_success'])): ?>
<script>
    Swal.fire({ icon: 'success', title: 'Success!', text: '<?php echo $_SESSION['swal_success']; ?>', timer: 2500, showConfirmButton: false });
</script>
<?php unset($_SESSION['swal_success']); endif; ?>

<?php if (isset($_SESSION['swal_error'])): ?>
<script>
    Swal.fire({ icon: 'error', title: 'Error', text: '<?php echo $_SESSION['swal_error']; ?>', confirmButtonColor: '#d33' });
</script>
<?php unset($_SESSION['swal_error']); endif; ?>

<?php include __DIR__ . '/../layouts/footer.php'; ?>