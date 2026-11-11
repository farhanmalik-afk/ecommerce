<?php
// users/users.php
session_start();
require_once "../config.php";
require_once "../includes/rbac.php";

enforceAccess($conn, 'user_management', 'view');

if (isset($_GET['action']) && isset($_GET['id'])) {
    $targetId = intval($_GET['id']);
    $checkRole = $conn->query("SELECT role_id FROM admins WHERE id = $targetId")->fetch_assoc();
    if ($checkRole && $checkRole['role_id'] == 1 && getCurrentAdminId() != 1) {
        $_SESSION['error'] = "You cannot modify a Super Admin account!";
    } else {
        if ($_GET['action'] == 'toggle') {
            $conn->query("UPDATE admins SET status = IF(status='Active', 'Inactive', 'Active') WHERE id = $targetId");
            logActivity($conn, getCurrentAdminId(), "Toggled status for user ID: $targetId", "User Management");
            $_SESSION['success'] = "User status updated successfully!";
        } elseif ($_GET['action'] == 'delete') {
            if ($targetId === getCurrentAdminId()) {
                $_SESSION['error'] = "You cannot delete your own active account.";
            } else {
                $conn->query("UPDATE admins SET status = 'Trash' WHERE id = $targetId");
                logActivity($conn, getCurrentAdminId(), "Moved user ID $targetId to Trash Bin", "User Management");
                $_SESSION['success'] = "User moved to Trash successfully!";
            }
        }
    }
    header("Location: users.php");
    exit();
}

$search = trim($_GET['search'] ?? '');
$limit = intval($_GET['limit'] ?? 10);
if (!in_array($limit, [10, 25, 50, 100])) {
    $limit = 10;
}
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$sortOrder = (strtoupper($_GET['sort'] ?? 'DESC') === 'ASC') ? 'ASC' : 'DESC';
$sortIcon = $sortOrder === 'ASC' ? 'bi-sort-numeric-down' : 'bi-sort-numeric-up-alt';

$searchQuery = "";
$params = [];
$types = "";

if (!empty($search)) {
    $searchQuery = " AND (a.name LIKE ? OR a.email LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "ss";
}

$countStmt = $conn->prepare("SELECT COUNT(*) as total FROM admins a JOIN roles r ON a.role_id = r.id WHERE a.status != 'Trash' $searchQuery");
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalRecords = $countStmt->get_result()->fetch_assoc()['total'] ?? 0;
$totalPages = ceil($totalRecords / $limit);
$countStmt->close();

$dataQuery = "SELECT a.*, r.name as role_name FROM admins a JOIN roles r ON a.role_id = r.id WHERE a.status != 'Trash' $searchQuery ORDER BY a.id $sortOrder LIMIT ? OFFSET ?";
$stmt = $conn->prepare($dataQuery);
$params[] = $limit;
$params[] = $offset;
$types .= "ii";
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$pageTitle = 'Users Management';
include "../layouts/header.php";
?>

<div class="app-main dashboard-main">
    <section class="page-head mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h1>User Management</h1>
            <p class="text-muted">Manage system users, assign roles, and control account statuses.</p>
        </div>
        <div>
            <a href="add-user.php" class="header-pos-btn btn btn-dark btn-sm">
                <i class="bi bi-person-plus-fill me-1"></i> Add New User
            </a>
        </div>
    </section>

    <div class="card border-0 shadow-sm table-card dashboard-panel">
        <div class="table-card-header p-3 border-bottom bg-white d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="d-flex align-items-center gap-2">
                <span class="fw-bold text-dark">Registered Users List</span>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="toggleUserSelection" aria-label="Enable user selection" aria-pressed="false" title="Enable user selection">
                    <i class="bi bi-check-square"></i>
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="selectAllUsers">Select All</button>
            </div>

            <div class="d-flex align-items-center gap-3 flex-wrap">
                <form method="GET" action="" id="limitForm" class="d-flex align-items-center gap-2 m-0">
                    <?php if (!empty($search)): ?>
                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                    <?php endif; ?>
                    <?php if ($sortOrder !== 'DESC'): ?>
                        <input type="hidden" name="sort" value="<?php echo $sortOrder; ?>">
                    <?php endif; ?>
                    <label class="small text-muted mb-0">Show on page</label>
                    <select name="limit" class="form-select form-select-sm" style="width: 80px;" onchange="this.form.submit()">
                        <option value="10" <?php echo $limit == 10 ? 'selected' : ''; ?>>10</option>
                        <option value="25" <?php echo $limit == 25 ? 'selected' : ''; ?>>25</option>
                        <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                    </select>
                </form>

                <?php 
                $nextSort = $sortOrder === 'ASC' ? 'DESC' : 'ASC';
                $sortLink = "users.php?sort=$nextSort&limit=$limit";
                if (!empty($search)) $sortLink .= "&search=" . urlencode($search);
                ?>
                <a href="<?php echo $sortLink; ?>" class="btn btn-outline-secondary btn-sm p-2 rounded-3" title="Toggle Sorting">
                    <i class="bi bi-arrow-down-up"></i>
                </a>

                <form method="GET" action="" class="d-flex gap-2 m-0">
                    <input type="hidden" name="limit" value="<?php echo $limit; ?>">
                    <input type="hidden" name="sort" value="<?php echo $sortOrder; ?>">
                    <input type="text" name="search" class="form-control form-control-sm search-input" placeholder="Search user..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-sm btn-dark">Search</button>
                    <?php if (!empty($search)): ?>
                        <a href="users.php" class="btn btn-sm btn-outline-secondary">Reset</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table data-table align-middle mb-0" id="usersTable">
                <thead>
                    <tr>
                        <th class="ps-3 user-select-column d-none" style="width: 50px;">Select</th>
                        <th class="ps-3" style="width: 70px;">#</th>
                        <th>User</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result && $result->num_rows > 0) {
                        $counter = ($sortOrder === 'ASC') ? ($offset + 1) : ($totalRecords - $offset);
                        while ($user = $result->fetch_assoc()) {
                            $badgeClass = ($user['status'] == 'Active') ? 'bg-success' : 'bg-secondary';
                            ?>
                            <tr>
                                <td class="ps-3 user-select-column d-none">
                                    <input type="checkbox" name="user_ids[]" value="<?php echo $user['id']; ?>" class="form-check-input user-checkbox m-0" aria-label="Select user">
                                </td>
                                <td class="ps-3">
                                    <span class="text-muted fw-semibold" style="font-size: 0.85rem;"><?php echo $counter; ?></span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <?php 
                                        $avatarFile = isset($user['avatar']) ? trim($user['avatar']) : '';
                                        
                                        if (!empty($avatarFile)) {
                                            $serverPath = __DIR__ . '/../uploads/avatars/' . $avatarFile;
                                            if (file_exists($serverPath)) {
                                                $webAvatarPath = '../uploads/avatars/' . rawurlencode($avatarFile);
                                            } else {
                                                $webAvatarPath = '../uploads/default-avatar.png';
                                            }
                                        } else {
                                            $webAvatarPath = '../uploads/default-avatar.png';
                                        }
                                        ?>
                                        <img src="<?php echo $webAvatarPath; ?>" class="rounded-circle border" style="width: 32px; height: 32px; object-fit: cover;" alt="User Avatar">
                                        <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($user['role_name']); ?></span></td>
                                <td><span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($user['status']); ?></span></td>
                                <td class="text-end pe-3">
                                    <button type="button" onclick="confirmUserStatusChange(<?php echo (int)$user['id']; ?>, '<?php echo htmlspecialchars($user['status'], ENT_QUOTES); ?>')" class="btn btn-sm btn-outline-warning py-0 px-2" title="Toggle Status"><i class="bi bi-power"></i></button>
                                    <a href="edit-user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-primary py-0 px-2" title="Edit User"><i class="bi bi-pencil"></i></a>
                                    <?php if ($user['id'] != getCurrentAdminId()): ?>
                                        <a href="users.php?action=delete&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-danger py-0 px-2 delete-btn" data-name="<?php echo htmlspecialchars($user['name']); ?>" title="Move to Trash"><i class="bi bi-trash"></i></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php
                            if ($sortOrder === 'ASC') {
                                $counter++;
                            } else {
                                $counter--;
                            }
                        }
                    } else {
                        echo "<tr><td colspan='7' class='text-center text-muted py-3'>No users found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white border-top py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="text-muted small">
                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $totalRecords); ?> of <?php echo $totalRecords; ?> entries
                </div>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="users.php?page=<?php echo $page - 1; ?>&limit=<?php echo $limit; ?>&sort=<?php echo $sortOrder; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="users.php?page=<?php echo $i; ?>&limit=<?php echo $limit; ?>&sort=<?php echo $sortOrder; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="users.php?page=<?php echo $page + 1; ?>&limit=<?php echo $limit; ?>&sort=<?php echo $sortOrder; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleUserSelectionButton = document.getElementById('toggleUserSelection');
    const selectAllUsersButton = document.getElementById('selectAllUsers');
    const userSelectColumns = document.querySelectorAll('.user-select-column');
    const userCheckboxes = document.querySelectorAll('.user-checkbox');
    let userSelectionEnabled = false;

    if (toggleUserSelectionButton) {
        toggleUserSelectionButton.addEventListener('click', () => {
            userSelectionEnabled = !userSelectionEnabled;
            userSelectColumns.forEach((col) => {
                if (userSelectionEnabled) {
                    col.classList.remove('d-none');
                } else {
                    col.classList.add('d-none');
                }
            });
            selectAllUsersButton.classList.toggle('d-none', !userSelectionEnabled);
            toggleUserSelectionButton.setAttribute('aria-pressed', String(userSelectionEnabled));

            if (!userSelectionEnabled) {
                userCheckboxes.forEach((checkbox) => { checkbox.checked = false; });
            }
        });
    }

    if (selectAllUsersButton) {
        selectAllUsersButton.addEventListener('click', () => {
            const selectAll = selectAllUsersButton.textContent === 'Select All';
            userCheckboxes.forEach((checkbox) => { checkbox.checked = selectAll; });
            selectAllUsersButton.textContent = selectAll ? 'Deselect All' : 'Select All';
        });
    }

    document.querySelectorAll('#usersTable tbody tr').forEach((row) => {
        row.addEventListener('click', (event) => {
            if (!userSelectionEnabled || event.target.closest('a, button, input, select, textarea, label')) return;
            const checkbox = row.querySelector('.user-checkbox');
            if (checkbox) {
                checkbox.checked = !checkbox.checked;
            }
        });
    });

    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const deleteUrl = this.getAttribute('href');
            const userName = this.getAttribute('data-name');

            Swal.fire({
                title: 'Move to Trash?',
                text: `Are you sure you want to move user "${userName}" to the trash bin?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, move to trash!'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = deleteUrl;
                }
            });
        });
    });
});

function confirmUserStatusChange(id, currentStatus) {
    let targetStatus = (currentStatus === 'Active') ? 'Inactive' : 'Active';
    Swal.fire({
        title: 'Change User Status?',
        text: 'Are you sure you want to make this user ' + targetStatus + '?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#198754', // Green for success/active state
        cancelButtonColor: '#6c757d',  // Neutral grey for cancel
        confirmButtonText: 'Yes, change it!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'users.php?action=toggle&id=' + id;
        }
    });
}
</script>

<?php if (isset($_SESSION['success'])): ?>
<script>
    Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: '<?php echo addslashes($_SESSION['success']); ?>',
        timer: 2000,
        showConfirmButton: false
    });
</script>
<?php unset($_SESSION['success']); endif; ?>

<?php if (isset($_SESSION['error'])): ?>
<script>
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: '<?php echo addslashes($_SESSION['error']); ?>',
        confirmButtonColor: '#dc3545'
    });
</script>
<?php unset($_SESSION['error']); endif; ?>

<?php include "../layouts/footer.php"; ?>