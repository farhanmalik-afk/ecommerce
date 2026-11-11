<?php
session_start();
include "../../config.php";
require_once __DIR__ . '/order-trash-helper.php';

try {
    ensureOrderTrashSchema($conn);
} catch (RuntimeException $exception) {
    error_log($exception->getMessage());
    http_response_code(500);
    exit('The order trash feature could not initialize. Check database permissions and server logs.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete'])) {
    $orderIds = getPostedOrderIds();
    if (empty($orderIds)) {
        $_SESSION['order_trash_notice'] = ['danger', 'Select at least one order to move to trash.'];
    } else {
        try {
            $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
            $types = str_repeat('i', count($orderIds));
            $stmt = $conn->prepare("UPDATE orders SET is_deleted = 1 WHERE is_deleted = 0 AND id IN ($placeholders)");
            $stmt->bind_param($types, ...$orderIds);
            $stmt->execute();
            $deletedCount = $stmt->affected_rows;
            $stmt->close();
            $_SESSION['order_trash_notice'] = $deletedCount > 0
                ? ['success', $deletedCount . ' order(s) moved to trash.']
                : ['warning', 'No active orders were found in that selection.'];
        } catch (Throwable $exception) {
            error_log('Unable to move orders to trash: ' . $exception->getMessage());
            $_SESSION['order_trash_notice'] = ['danger', 'The selected orders could not be moved to trash. Check the server log for details.'];
        }
    }

    header('Location: ../../products/trash.php?type=orders');
    exit();
}

// Get Filters, Search Queries & Pagination
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page_options = [10, 25, 50, 100];
$requested_per_page = filter_var($_GET['per_page'] ?? 10, FILTER_VALIDATE_INT);
$limit = in_array($requested_per_page, $per_page_options, true) ? $requested_per_page : 10;
$offset = ($page - 1) * $limit;

$pageTitle = 'Orders Management';
$activeModule = 'orders';
include "../../layouts/header.php";
?>

<div class="container-fluid px-0">
    <section class="page-head mb-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h1>Orders Management</h1>
            <p class="text-muted mb-0">Track customer purchases, order status, and total revenue.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="add-order.php" class="btn btn-dark btn-sm rounded-pill px-3">
                <i class="bi bi-plus-lg me-1"></i> Add New Order
            </a>
            <a href="../../index.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                <i class="bi bi-house me-1"></i> Dashboard
            </a>
        </div>
    </section>

    <div class="card border-0 shadow-sm rounded-4 bg-white mb-4">
        <div class="p-3 border-bottom">
            <div class="row align-items-center g-3">
                <div class="col-md-4">
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="m-0 fw-bold text-dark">All Customer Orders</h5>
                        <button type="button" class="table-sort-toggle" data-sort-toggle data-sort-target="ordersTable" aria-label="Sort Ascending" title="Sort Ascending" aria-pressed="false">
                            <i class="bi bi-sort-up-alt" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Search & Status Filter Form -->
                <div class="col-md-8">
                    <form method="GET" action="orders.php" class="d-flex justify-content-md-end align-items-center gap-2 flex-wrap">
                        <div class="input-group input-group-sm" style="width: 240px;">
                            <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Search name or ID..." value="<?php echo htmlspecialchars($search_query); ?>">
                        </div>

                        <div style="width: 140px;">
                           <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                          <option value="">All Statuses</option>
                                 <?php foreach (['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'] as $status_option): ?>
                                     <option value="<?php echo $status_option; ?>" <?php echo $filter_status === $status_option ? 'selected' : ''; ?>><?php echo $status_option; ?></option>
                                 <?php endforeach; ?>
                          </select>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <label for="ordersPerPage" class="small text-muted text-nowrap mb-0">Show on page</label>
                            <select id="ordersPerPage" name="per_page" class="form-select form-select-sm w-auto" onchange="this.form.submit()" aria-label="Orders per page">
                                <?php foreach ($per_page_options as $option): ?>
                                    <option value="<?php echo $option; ?>" <?php echo $limit === $option ? 'selected' : ''; ?>><?php echo $option; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <?php if(!empty($filter_status) || !empty($search_query)): ?>
                            <a href="orders.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <form method="POST" action="orders.php" id="ordersBulkForm">
        <input type="hidden" name="bulk_delete" value="1">
        <div class="d-flex align-items-center gap-2 p-3 pb-0">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="toggleOrderSelection" aria-label="Enable order selection" aria-pressed="false" title="Enable order selection">
                <i class="bi bi-check-square"></i>
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="selectAllOrders">Select All</button>
            <button type="submit" class="btn btn-danger btn-sm d-none" id="moveOrdersToTrash" disabled>
                <i class="bi bi-trash me-1"></i> Move Selected to Trash
            </button>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="ordersTable">
                <thead class="table-light text-uppercase fs-7">
                    <tr>
                        <th class="py-3 ps-3 order-select-column d-none">Select</th>
                        <th class="py-3 ps-3" style="width: 8%;">SR #</th>
                        <th class="py-3" style="width: 17%;">Order No</th>
                        <th class="py-3" style="width: 27%;">Customer Name</th>
                        <th class="py-3" style="width: 15%;">Total</th>
                        <th class="py-3" style="width: 15%;">Status</th>
                        <th class="py-3 text-end pe-3" style="width: 18%;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Fetch orders with filters and pagination
                    $sql = "SELECT o.*, 
                            COALESCE(c.name, 'Walk-in Customer') as customer_name, 
                            COALESCE(c.email, '') as customer_email 
                            FROM orders o 
                            LEFT JOIN customers c ON o.customer_id = c.id WHERE o.is_deleted = 0";
                    
                    $params = [];
                    $types = "";

                    if (!empty($filter_status)) {
                        $sql .= " AND o.status = ?";
                        $params[] = $filter_status;
                        $types .= "s";
                    }

                    $sql .= " ORDER BY o.id DESC";

                    if (!empty($params)) {
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param($types, ...$params);
                        $stmt->execute();
                        $orders_query = $stmt->get_result();
                    } else {
                        $orders_query = $conn->query($sql);
                    }

                    $matched_orders = [];
                    if ($orders_query && $orders_query->num_rows > 0) {
                        while ($order = $orders_query->fetch_assoc()) {
                            if (!empty($search_query)) {
                                $q = strtolower(trim($search_query));
                                $cname = strtolower($order['customer_name']);
                                $cemail = strtolower($order['customer_email']);
                                $ono = strtolower($order['order_no'] ?? '');
                                $oid = strtolower($order['id']);

                                $is_matched = false;
                                if (strpos($cname, $q) !== false || strpos($cemail, $q) !== false || strpos($ono, $q) !== false || strpos($oid, $q) !== false) {
                                    $is_matched = true;
                                } else {
                                    $words = explode(' ', $cname);
                                    foreach ($words as $word) {
                                        similar_text($q, $word, $percent);
                                        if ($percent >= 70 || (strlen($q) >= 3 && levenshtein($q, $word) == 1)) {
                                            $is_matched = true;
                                            break;
                                        }
                                    }
                                }

                                if ($is_matched) {
                                    $matched_orders[] = $order;
                                }
                            } else {
                                $matched_orders[] = $order;
                            }
                        }
                    }

                    // Pagination calculations
                    $total_records = count($matched_orders);
                    $total_pages = max(1, (int)ceil($total_records / $limit));
                    $page = min($page, $total_pages);
                    $offset = ($page - 1) * $limit;
                    $paginated_orders = array_slice($matched_orders, $offset, $limit);

                    if (!empty($paginated_orders)) {
                        $sr = $offset + 1;
                        foreach ($paginated_orders as $order) {
                            $status = $order['status'] ?? 'Pending';
                            $display_id = !empty($order['order_no']) ? $order['order_no'] : '#ORD-' . str_pad($order['id'], 4, '0', STR_PAD_LEFT);
                            
                            $badge_class = 'bg-secondary';
                            if ($status === 'Delivered') $badge_class = 'bg-success';
                            elseif ($status === 'Shipped') $badge_class = 'bg-primary';
                            elseif ($status === 'Processing') $badge_class = 'bg-info text-dark';
                            elseif ($status === 'Pending') $badge_class = 'bg-warning text-dark';
                            elseif ($status === 'Cancelled') $badge_class = 'bg-danger';
                            ?>
                            <tr>
                                <td class="order-select-column d-none">
                                    <input type="checkbox" class="form-check-input order-checkbox" name="order_ids[]" value="<?php echo (int)$order['id']; ?>" aria-label="Select order <?php echo htmlspecialchars($display_id, ENT_QUOTES, 'UTF-8'); ?>">
                                </td>
                                <td class="ps-3 fw-semibold text-muted"><?php echo $sr++; ?></td>
                                <td>
                                    <strong><a href="order-details.php?id=<?php echo $order['id']; ?>" class="text-decoration-none text-dark"><?php echo htmlspecialchars($display_id); ?></a></strong>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($order['customer_email']); ?></small>
                                </td>
                                <td><strong class="text-success"><?php echo htmlspecialchars(formatAppCurrency($order['total'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                <td>
                                    <span class="badge <?php echo $badge_class; ?> px-2 py-1"><?php echo htmlspecialchars($status); ?></span>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-inline-flex align-items-center gap-1 justify-content-end">
                                        <!-- Manage Dropdown Button -->
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-dark dropdown-toggle px-2 py-1" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="font-size: 12px;">
                                                Manage
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="font-size: 13px;">
                                                <li><a class="dropdown-item py-1" href="order-details.php?id=<?php echo $order['id']; ?>"><i class="bi bi-eye me-2"></i> View Details</a></li>
                                                <li><a class="dropdown-item py-1" href="invoice.php?id=<?php echo $order['id']; ?>" target="_blank"><i class="bi bi-printer me-2"></i> View Invoice</a></li>
                                            </ul>
                                        </div>

                                    </div>
                                </td>
                            </tr>
                            <?php
                        }
                    } else {
                        echo '<tr><td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-bag-x fs-1 d-block mb-2 opacity-50"></i>
                                No matching orders found.
                              </td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
        </form>

        <!-- Professional Pagination Links -->
        <?php if ($total_pages > 1): ?>
            <div class="card-footer bg-white border-0 py-3">
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-end mb-0">
                        <!-- Previous Page Link -->
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&per_page=<?php echo $limit; ?>&status=<?php echo urlencode($filter_status); ?>&search=<?php echo urlencode($search_query); ?>">Previous</a>
                        </li>

                        <!-- Numbered Pages -->
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&per_page=<?php echo $limit; ?>&status=<?php echo urlencode($filter_status); ?>&search=<?php echo urlencode($search_query); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>

                        <!-- Next Page Link -->
                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&per_page=<?php echo $limit; ?>&status=<?php echo urlencode($filter_status); ?>&search=<?php echo urlencode($search_query); ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const orderCheckboxes = document.querySelectorAll('.order-checkbox');
const orderSelectColumns = document.querySelectorAll('.order-select-column');
const toggleOrderSelectionButton = document.getElementById('toggleOrderSelection');
const selectAllOrdersButton = document.getElementById('selectAllOrders');
const moveOrdersToTrashButton = document.getElementById('moveOrdersToTrash');
const ordersBulkForm = document.getElementById('ordersBulkForm');
let orderSelectionEnabled = false;
let orderTrashConfirmed = false;

function updateOrderSelectionControls() {
    const selectedCount = document.querySelectorAll('.order-checkbox:checked').length;
    moveOrdersToTrashButton.disabled = selectedCount === 0;
    selectAllOrdersButton.textContent = orderCheckboxes.length > 0 && selectedCount === orderCheckboxes.length
        ? 'Deselect All'
        : 'Select All';
}

toggleOrderSelectionButton.addEventListener('click', () => {
    orderSelectionEnabled = !orderSelectionEnabled;
    orderSelectColumns.forEach((column) => column.classList.toggle('d-none', !orderSelectionEnabled));
    selectAllOrdersButton.classList.toggle('d-none', !orderSelectionEnabled);
    moveOrdersToTrashButton.classList.toggle('d-none', !orderSelectionEnabled);
    toggleOrderSelectionButton.setAttribute('aria-pressed', String(orderSelectionEnabled));
    toggleOrderSelectionButton.setAttribute('aria-label', orderSelectionEnabled ? 'Disable order selection' : 'Enable order selection');
    toggleOrderSelectionButton.title = orderSelectionEnabled ? 'Disable order selection' : 'Enable order selection';
    if (!orderSelectionEnabled) {
        orderCheckboxes.forEach((checkbox) => { checkbox.checked = false; });
    }
    updateOrderSelectionControls();
});

selectAllOrdersButton.addEventListener('click', () => {
    const selectAll = selectAllOrdersButton.textContent === 'Select All';
    orderCheckboxes.forEach((checkbox) => { checkbox.checked = selectAll; });
    updateOrderSelectionControls();
});

orderCheckboxes.forEach((checkbox) => checkbox.addEventListener('change', updateOrderSelectionControls));
document.querySelectorAll('#ordersBulkForm tbody tr').forEach((row) => {
    row.addEventListener('click', (event) => {
        if (!orderSelectionEnabled || event.target.closest('a, button, input, select, textarea, label')) return;
        const checkbox = row.querySelector('.order-checkbox');
        if (checkbox) {
            checkbox.checked = !checkbox.checked;
            updateOrderSelectionControls();
        }
    });
});

ordersBulkForm.addEventListener('submit', (event) => {
    if (orderTrashConfirmed) {
        return;
    }
    event.preventDefault();
    const selectedCount = document.querySelectorAll('.order-checkbox:checked').length;
    if (selectedCount === 0) {
        return;
    }
    if (window.Swal) {
        Swal.fire({
            title: 'Move selected orders to Trash?',
            text: selectedCount + ' order(s) will be moved to the trash bin.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Yes, move to trash'
        }).then((result) => {
            if (result.isConfirmed) {
                orderTrashConfirmed = true;
                ordersBulkForm.submit();
            }
        });
    } else if (window.confirm('Move ' + selectedCount + ' selected order(s) to Trash?')) {
        orderTrashConfirmed = true;
        ordersBulkForm.submit();
    }
});
</script>
<script src="../../assets/js/table-sort.js" defer></script>
<?php include "../../layouts/footer.php"; ?>