<?php
// Session start karein taake messages ko manage kiya ja sake
session_start();

// Database connection include karein
include "../config.php";

// Move a customer to Trash without breaking historical order references.
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("UPDATE customers SET is_deleted = 1 WHERE id = ? AND is_deleted = 0");
    $stmt->bind_param("i", $del_id);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $_SESSION['swal_success'] = "Customer moved to Trash successfully!";
    } else {
        $_SESSION['swal_error'] = "Customer could not be moved to Trash.";
    }
    $stmt->close();
    header("Location: customers.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete'])) {
    $selectedIds = [];
    foreach (is_array($_POST['customer_ids'] ?? null) ? $_POST['customer_ids'] : [] as $postedId) {
        if (!is_scalar($postedId)) {
            continue;
        }
        $customerId = filter_var($postedId, FILTER_VALIDATE_INT);
        if ($customerId !== false && $customerId > 0) {
            $selectedIds[] = $customerId;
        }
    }
    $selectedIds = array_values(array_unique($selectedIds));
    if (!$selectedIds) {
        $_SESSION['swal_error'] = 'Select at least one customer.';
    } else {
        $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
        $stmt = $conn->prepare("UPDATE customers SET is_deleted = 1 WHERE is_deleted = 0 AND id IN ($placeholders)");
        $types = str_repeat('i', count($selectedIds));
        $stmt->bind_param($types, ...$selectedIds);
        $stmt->execute();
        $movedCount = $stmt->affected_rows;
        $stmt->close();
        $_SESSION['swal_success'] = $movedCount . ' customer(s) moved to Trash.';
    }
    header("Location: customers.php");
    exit();
}

// Status Toggle Action Handle Karna
if (isset($_GET['toggle_id'])) {
    $t_id = intval($_GET['toggle_id']);
    
    // Current status check karke reverse kar dein (Active <-> Inactive)
    $stmt = $conn->prepare("UPDATE customers SET status = CASE WHEN status = 'Active' THEN 'Inactive' ELSE 'Active' END WHERE id = ?");
    $stmt->bind_param("i", $t_id);
    
    if ($stmt->execute()) {
        $_SESSION['swal_success'] = "Customer status updated successfully!";
    } else {
        $_SESSION['swal_error'] = "Failed to update status!";
    }
    $stmt->close();
    
    header("Location: customers.php");
    exit();
}

// Search & Filter Input Variables
$search_query = trim($_GET['search'] ?? '');
$search_phone = trim($_GET['phone'] ?? '');
$search_city  = trim($_GET['city'] ?? '');
$group_filter = trim($_GET['group'] ?? '');
$per_page_options = [10, 25, 50, 100];
$requested_per_page = filter_var($_GET['per_page'] ?? 10, FILTER_VALIDATE_INT);
$per_page = in_array($requested_per_page, $per_page_options, true) ? $requested_per_page : 10;
$pageTitle = 'Customers';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(($appSettings['language'] ?? 'en') === 'ur' ? 'ur' : 'en', ENT_QUOTES, 'UTF-8') ?>"<?= ($appSettings['language'] ?? 'en') === 'ur' ? ' dir="rtl"' : '' ?>>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Customers Management - Dashboard</title>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
  <link href="../assets/css/style.css" rel="stylesheet">
    <style>
    /* Ensure customer select column behaves properly when toggled */
    #customersTable th.customer-select-column,
    #customersTable td.customer-select-column {
        width: 40px;
        text-align: center;
    }
    #customersTable th.customer-select-column.d-none,
    #customersTable td.customer-select-column.d-none {
        display: none !important;
    }
    #customersTable th.customer-select-column:not(.d-none),
    #customersTable td.customer-select-column:not(.d-none) {
        display: table-cell !important;
    }
</style>
</head>
<body class="luxury-theme theme-<?= htmlspecialchars($appSettings['theme'] ?? 'luxury', ENT_QUOTES, 'UTF-8') ?>" data-bs-theme="<?= ($appSettings['theme'] ?? '') === 'dark' ? 'dark' : 'light' ?>"<?= ($appSettings['language'] ?? 'en') === 'ur' ? ' dir="rtl"' : '' ?>>

<div class="app">

  <?php include "../layouts/sidebar.php"; ?>

  <main class="app-main">

    <?php include "../layouts/topbar.php"; ?>

    <section class="page-head">
      <h1>Customers Management</h1>
      <p>Add new customers and view the list of all registered customers.</p>
    </section>

    <!-- Advanced Multi-Box Search & Filter Card -->
    <div class="card border-0 shadow-sm p-3 mb-4">
        <form method="GET" action="" class="row g-3 align-items-center">
            <div class="col-md-3">
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-person text-muted"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Name or Email..." value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
            </div>

            <div class="col-md-3">
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-telephone text-muted"></i></span>
                    <input type="text" name="phone" class="form-control" placeholder="Phone number..." value="<?php echo htmlspecialchars($search_phone); ?>">
                </div>
            </div>

            <div class="col-md-2">
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-geo-alt text-muted"></i></span>
                    <input type="text" name="city" class="form-control" placeholder="City..." value="<?php echo htmlspecialchars($search_city); ?>">
                </div>
            </div>

            <div class="col-md-2">
                <select name="group" class="form-select">
                    <option value="">All Groups</option>
                    <option value="Retail" <?php echo ($group_filter == 'Retail') ? 'selected' : ''; ?>>Retail</option>
                    <option value="Wholesale" <?php echo ($group_filter == 'Wholesale') ? 'selected' : ''; ?>>Wholesale</option>
                    <option value="VIP" <?php echo ($group_filter == 'VIP') ? 'selected' : ''; ?>>VIP</option>
                </select>
            </div>

            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-dark w-100">Filter</button>
                <?php if (!empty($search_query) || !empty($search_phone) || !empty($search_city) || !empty($group_filter)): ?>
                    <a href="customers.php" class="btn btn-outline-secondary">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Registered Customers List Section (Bottom) -->
    <section class="row mb-4">
      <div class="col-12">
        <div class="card table-card border-0 shadow-sm">
          <div class="table-card-header d-flex justify-content-between align-items-center p-3 border-bottom bg-white">
            <div class="d-flex align-items-center gap-2">
              <h6 class="card-title mb-0 fw-bold">Registered Customers List</h6>
              <button type="button" class="btn btn-outline-secondary btn-sm" id="toggleCustomerSelection" aria-label="Enable customer selection" aria-pressed="false" title="Enable customer selection"><i class="bi bi-check-square"></i></button>
              <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="selectAllCustomers">Select All</button>
              <button type="submit" form="customersBulkForm" class="btn btn-danger btn-sm d-none" id="moveCustomersToTrash" disabled><i class="bi bi-trash me-1"></i>Move Selected to Trash</button>
            </div>
            <div class="d-flex align-items-center gap-3 flex-wrap justify-content-end">
              <form method="GET" action="customers.php" class="d-flex align-items-center gap-2 mb-0">
                <input type="hidden" name="search" value="<?= htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="phone" value="<?= htmlspecialchars($search_phone, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="city" value="<?= htmlspecialchars($search_city, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="group" value="<?= htmlspecialchars($group_filter, ENT_QUOTES, 'UTF-8') ?>">
                <label for="customerPerPage" class="small text-muted text-nowrap mb-0">Show on page</label>
                <select id="customerPerPage" name="per_page" class="form-select form-select-sm w-auto" onchange="this.form.submit()" aria-label="Customers per page">
                  <?php foreach ($per_page_options as $option): ?>
                    <option value="<?= $option ?>" <?= $per_page === $option ? 'selected' : '' ?>><?= $option ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
              <button type="button" class="table-sort-toggle" data-sort-toggle data-sort-target="customersTable" aria-label="Sort Ascending" title="Sort Ascending" aria-pressed="false">
                <i class="bi bi-sort-up-alt" aria-hidden="true"></i>
              </button>
              <a href="add-customers.php" class="btn btn-dark"><i class="bi bi-plus-lg me-1"></i>Add Customer</a>
            </div>
          </div>

          <form method="POST" action="customers.php" id="customersBulkForm">
          <input type="hidden" name="bulk_delete" value="1">
          <div class="table-responsive customer-list-responsive">
            <table class="table data-table align-middle mb-0" id="customersTable">
              <thead class="table-light">
                <tr>
                  <!-- Select column added in header with d-none -->
                  <th class="customer-select-column d-none">Select</th>
                  <th class="customer-number-heading">#</th>
                  <th class="customer-name-heading">Customer</th>
                  <th class="customer-group-column">Group</th>
                  <th class="customer-phone-column">Phone</th>
                  <th>Status</th>
                  <th class="text-end pe-4">Action</th>
                </tr>
              </thead>
              <tbody>
                
                <?php
                $sql = "SELECT * FROM customers WHERE is_deleted = 0";
                $params = [];
                $types = "";

                if (!empty($search_query)) {
                    $keywords = explode(' ', $search_query);
                    $clauses = [];
                    foreach ($keywords as $word) {
                        $word = trim($word);
                        if (!empty($word)) {
                            $searchTerm = "%" . $word . "%";
                            
                            $fuzzyWord = "%";
                            for ($i = 0; $i < strlen($word); $i++) {
                                $fuzzyWord .= $word[$i] . "%";
                            }

                            $clauses[] = "(name LIKE ? OR email LIKE ? OR name LIKE ? OR email LIKE ? OR SOUNDEX(name) = SOUNDEX(?))";
                            $params[] = $searchTerm;
                            $params[] = $searchTerm;
                            $params[] = $fuzzyWord;
                            $params[] = $fuzzyWord;
                            $params[] = $word;
                            $types .= "sssss";
                        }
                    }
                    if (!empty($clauses)) {
                        $sql .= " AND (" . implode(" OR ", $clauses) . ")";
                    }
                }

                if (!empty($search_phone)) {
                    $clean_phone = preg_replace('/[^0-9]/', '', $search_phone);
                    $clean_phone_no_zero = ltrim($clean_phone, '0');
                    
                    if (!empty($clean_phone)) {
                        $sql .= " AND (phone LIKE ? OR REPLACE(REPLACE(REPLACE(REPLACE(phone, '-', ''), ' ', ''), '+', ''), '(', '') LIKE ? OR REPLACE(REPLACE(REPLACE(REPLACE(phone, '-', ''), ' ', ''), '+', ''), '(', '') LIKE ?)";
                        $params[] = "%" . $search_phone . "%";
                        $params[] = "%" . $clean_phone . "%";
                        $params[] = "%" . $clean_phone_no_zero . "%";
                        $types .= "sss";
                    } else {
                        $sql .= " AND phone LIKE ?";
                        $params[] = "%" . $search_phone . "%";
                        $types .= "s";
                    }
                }

                if (!empty($search_city)) {
                    $sql .= " AND city LIKE ?";
                    $params[] = "%" . $search_city . "%";
                    $types .= "s";
                }

                if (!empty($group_filter)) {
                    $sql .= " AND customer_group = ?";
                    $params[] = $group_filter;
                    $types .= "s";
                }

                $count_sql = "SELECT COUNT(*)" . substr($sql, strpos($sql, " FROM "));
                $count_stmt = $conn->prepare($count_sql);
                if (!empty($params)) {
                    $count_stmt->bind_param($types, ...$params);
                }
                $count_stmt->execute();
                $total_customers = (int)$count_stmt->get_result()->fetch_row()[0];
                $count_stmt->close();

                $total_pages = max(1, (int)ceil($total_customers / $per_page));
                $requested_page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
                $current_page = $requested_page !== false && $requested_page > 0
                    ? min($requested_page, $total_pages)
                    : 1;
                $offset = ($current_page - 1) * $per_page;
                $row_number = $offset + 1;

                $sql .= " ORDER BY id DESC LIMIT $per_page OFFSET $offset";

                $stmt = $conn->prepare($sql);
                if (!empty($params)) {
                    $stmt->bind_param($types, ...$params);
                }
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $name = $row['name'] ?? '';
                        $group = $row['customer_group'] ?? 'Retail';
                        $status = $row['status'] ?? 'Active';
                        $status_badge = ($status == 'Active') ? 'bg-success' : 'bg-secondary';
                        
                        // Clean Customer Avatar Handling with Default Fallback Link
                        $cust_img = $row['profile_image'] ?? '';
                        if (empty($cust_img)) {
                            $cust_img_path = "https://cdn-icons-png.flaticon.com/512/1165/1165687.png";
                        } else {
                            if (filter_var($cust_img, FILTER_VALIDATE_URL)) {
                                $cust_img_path = $cust_img;
                            } else {
                                $cust_img_path = "../uploads/" . $cust_img;
                                if (!file_exists($cust_img_path)) {
                                    $cust_img_path = "https://cdn-icons-png.flaticon.com/512/1165/1165687.png";
                                }
                            }
                        }

                        $customer_avatar = "<img src='{$cust_img_path}' width='40' height='40' class='rounded-circle border object-fit-cover'>";

                        echo "<tr>
                                <td class='customer-select-column d-none'><input type='checkbox' class='form-check-input customer-checkbox' name='customer_ids[]' value='" . (int)$row['id'] . "' aria-label='Select customer'></td>
                                <td class='customer-number-cell'>" . $row_number++ . "</td>
                                <td class='customer-name-cell'>
                                  <div class='d-flex align-items-center gap-3'>
                                    {$customer_avatar}
                                    <div>
                                     <strong>" . htmlspecialchars($name) . "</strong><br>
                                     <small class='text-muted'>" . htmlspecialchars($row['email'] ?? '') . "</small>
                                    </div>
                                  </div>
                                </td>
                                <td class='customer-group-column'><span class='badge bg-light text-dark border'>" . htmlspecialchars($group) . "</span></td>
                                <td class='customer-phone-column'>" . htmlspecialchars($row['phone'] ?? 'N/A') . "</td>
                                <td><span class='badge {$status_badge}'>{$status}</span></td>
                                <td class='text-end pe-4'>
                                  <a href='customer-details.php?id=" . $row['id'] . "' class='text-info me-2' title='View Details'><i class='bi bi-eye'></i></a>
                                  <a href='#' onclick='confirmStatusChange(" . $row['id'] . ", \"" . $status . "\")' class='text-warning me-2' title='Toggle Status'><i class='bi bi-slash-circle'></i></a>
                                  <a href='edit-customer.php?id=" . $row['id'] . "' class='text-secondary me-2' title='Edit'><i class='bi bi-pencil-square'></i></a>
                                  <a href='#' onclick='confirmDelete(" . $row['id'] . ")' class='text-danger' title='Delete'><i class='bi bi-trash'></i></a>
                                </td>
                              </tr>";
                    }
                } else {
                    echo "<tr><td colspan='7' class='text-center text-muted py-4'>No customers found.</td></tr>";
                }
                ?>
              </tbody>
            </table>
          </div>
          </form>
          <?php if ($total_pages > 1): ?>
            <nav aria-label="Customers pagination" class="p-3">
              <ul class="pagination justify-content-end mb-0">
                <?php for ($page_number = 1; $page_number <= $total_pages; $page_number++): ?>
                  <?php $page_query = http_build_query(array_merge($_GET, ['page' => $page_number])); ?>
                  <li class="page-item <?= $page_number === $current_page ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= htmlspecialchars($page_query, ENT_QUOTES, 'UTF-8') ?>"><?= $page_number ?></a>
                  </li>
                <?php endfor; ?>
              </ul>
            </nav>
          <?php endif; ?>
        </div>
      </div>
    </section>

  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../assets/js/table-sort.js" defer></script>

<script>
function confirmDelete(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "The customer will be moved to the Trash Bin and can be restored later.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Move to Trash',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'customers.php?delete_id=' + id;
        }
    });
}

const toggleCustomerSelectionButton = document.getElementById('toggleCustomerSelection');
const selectAllCustomersButton = document.getElementById('selectAllCustomers');
const moveCustomersToTrashButton = document.getElementById('moveCustomersToTrash');
const customersBulkForm = document.getElementById('customersBulkForm');
let customerSelectionEnabled = false;
let customerTrashConfirmed = false;

function updateCustomerSelectionControls() {
    const customerCheckboxes = document.querySelectorAll('.customer-checkbox');
    const selectedCount = document.querySelectorAll('.customer-checkbox:checked').length;
    moveCustomersToTrashButton.disabled = selectedCount === 0;
    selectAllCustomersButton.textContent = customerCheckboxes.length > 0 && selectedCount === customerCheckboxes.length ? 'Deselect All' : 'Select All';
}

toggleCustomerSelectionButton.addEventListener('click', () => {
    customerSelectionEnabled = !customerSelectionEnabled;
    
    // Toggle d-none class for header and row cells using Bootstrap classes
    const selectColumns = document.querySelectorAll('.customer-select-column');
    selectColumns.forEach((col) => {
        if (customerSelectionEnabled) {
            col.classList.remove('d-none');
        } else {
            col.classList.add('d-none');
        }
    });

    selectAllCustomersButton.classList.toggle('d-none', !customerSelectionEnabled);
    moveCustomersToTrashButton.classList.toggle('d-none', !customerSelectionEnabled);
    toggleCustomerSelectionButton.setAttribute('aria-pressed', String(customerSelectionEnabled));
    
    if (!customerSelectionEnabled) {
        document.querySelectorAll('.customer-checkbox').forEach((checkbox) => { checkbox.checked = false; });
    }
    updateCustomerSelectionControls();
});

selectAllCustomersButton.addEventListener('click', () => {
    const customerCheckboxes = document.querySelectorAll('.customer-checkbox');
    const selectAll = selectAllCustomersButton.textContent === 'Select All';
    customerCheckboxes.forEach((checkbox) => { checkbox.checked = selectAll; });
    updateCustomerSelectionControls();
});

document.addEventListener('change', (e) => {
    if (e.target.classList.contains('customer-checkbox')) {
        updateCustomerSelectionControls();
    }
});

document.querySelectorAll('#customersBulkForm tbody tr').forEach((row) => {
    row.addEventListener('click', (event) => {
        if (!customerSelectionEnabled || event.target.closest('a, button, input, select, textarea, label')) return;
        const checkbox = row.querySelector('.customer-checkbox');
        if (checkbox) {
            checkbox.checked = !checkbox.checked;
            updateCustomerSelectionControls();
        }
    });
});

customersBulkForm.addEventListener('submit', (event) => {
    if (customerTrashConfirmed) return;
    event.preventDefault();
    const selectedCount = document.querySelectorAll('.customer-checkbox:checked').length;
    if (selectedCount < 1) return;
    Swal.fire({
        title: 'Move selected customers to Trash?',
        text: selectedCount + ' customer(s) can be restored later from the Trash Bin.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Move to Trash'
    }).then((result) => {
        if (result.isConfirmed) {
            customerTrashConfirmed = true;
            customersBulkForm.submit();
        }
    });
});

function confirmStatusChange(id, currentStatus) {
    let targetStatus = (currentStatus === 'Active') ? 'Inactive' : 'Active';
    let messageText = 'Are you sure you want to make this customer ' + targetStatus + '?';

    Swal.fire({
        title: 'Change Status?',
        text: messageText,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, change it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'customers.php?toggle_id=' + id;
        }
    });
}
</script>

<?php if (isset($_SESSION['swal_success'])): ?>
<script>
    Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: '<?php echo $_SESSION['swal_success']; ?>',
        timer: 2500,
        showConfirmButton: false
    });
</script>
<?php unset($_SESSION['swal_success']); endif; ?>

<?php if (isset($_SESSION['swal_error'])): ?>
<script>
    Swal.fire({
        icon: 'error',
        title: 'Notice',
        text: '<?php echo $_SESSION['swal_error']; ?>',
        confirmButtonColor: '#d33'
    });
</script>
<?php unset($_SESSION['swal_error']); endif; ?>

</body>
</html>