<?php
// users/users.php
require_once "../config.php";
require_once "../includes/rbac.php";

enforceAccess($conn, 'user_management', 'view');
$canCreateUsers = appUserCan('user_management', 'create');
$canEditUsers = appUserCan('user_management', 'edit');
$canDeleteUsers = appUserCan('user_management', 'delete');
$showUserActions = $canEditUsers || $canDeleteUsers;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Invalid request token.');
    }

    $postedIds = $_POST['user_ids'] ?? [];
    $userIds = [];
    if (is_array($postedIds)) {
        foreach ($postedIds as $postedId) {
            $userId = is_scalar($postedId) ? filter_var($postedId, FILTER_VALIDATE_INT) : false;
            if ($userId !== false && $userId > 0) {
                $userIds[] = $userId;
            }
        }
    }
    $userIds = array_values(array_unique($userIds));

    if ($userIds === []) {
        $_SESSION['error'] = 'Select at least one user.';
    } else {
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $params = $userIds;
        $params[] = getCurrentAdminId();
        $types = str_repeat('i', count($userIds) + 1);
        $bulkStmt = null;
        try {
            $bulkStmt = $conn->prepare(
                "UPDATE admins a
                 JOIN roles r ON r.id = a.role_id
                 SET a.status = 'Trash'
                 WHERE a.id IN ($placeholders)
                   AND a.id <> ?
                   AND LOWER(TRIM(r.name)) <> 'super admin'
                   AND a.status <> 'Trash'"
            );
            $bulkStmt->bind_param($types, ...$params);
            $bulkStmt->execute();
            $movedCount = $bulkStmt->affected_rows;
            $bulkStmt->close();
            $bulkStmt = null;

            if ($movedCount > 0) {
                logActivity($conn, getCurrentAdminId(), "Moved $movedCount user(s) to Trash", 'User Management');
                $_SESSION['success'] = "$movedCount user(s) moved to Trash.";
            } else {
                $_SESSION['error'] = 'No eligible users were moved to Trash.';
            }
        } catch (Throwable $exception) {
            if ($bulkStmt instanceof mysqli_stmt) {
                $bulkStmt->close();
            }
            error_log('Unable to move selected users to Trash: ' . $exception->getMessage());
            $_SESSION['error'] = 'Selected users could not be moved to Trash.';
        }
    }

    header('Location: users.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    $targetId = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null) || !$targetId || $targetId < 1) {
        http_response_code(403);
        exit('Invalid request token.');
    }

    $roleStmt = $conn->prepare(
        'SELECT a.role_id, a.status, r.name AS role_name
         FROM admins a JOIN roles r ON r.id = a.role_id WHERE a.id = ?'
    );
    $roleStmt->bind_param('i', $targetId);
    $roleStmt->execute();
    $target = $roleStmt->get_result()->fetch_assoc();
    $roleStmt->close();
    if (!$target) {
        $_SESSION['error'] = 'The selected user was not found.';
    } elseif (strtolower(trim((string) $target['role_name'])) === 'super admin') {
        $_SESSION['error'] = 'Super Admin accounts are protected from user-list actions.';
    } else {
        if ($_POST['action'] === 'toggle') {
            if ($targetId === getCurrentAdminId()) {
                $_SESSION['error'] = 'You cannot disable your own account.';
            } else {
                $stmt = $conn->prepare("UPDATE admins SET status = IF(status = 'Active', 'Inactive', 'Active') WHERE id = ?");
                $stmt->bind_param('i', $targetId);
                $stmt->execute();
                $stmt->close();
                logActivity($conn, getCurrentAdminId(), "Toggled status for user ID: $targetId", "User Management");
                $_SESSION['success'] = "User status updated successfully!";
            }
        } elseif ($_POST['action'] === 'delete') {
            if ($targetId === getCurrentAdminId()) {
                $_SESSION['error'] = "You cannot delete your own active account.";
            } else {
                $stmt = $conn->prepare("UPDATE admins SET status = 'Trash' WHERE id = ?");
                $stmt->bind_param('i', $targetId);
                $stmt->execute();
                $stmt->close();
                logActivity($conn, getCurrentAdminId(), "Moved user ID $targetId to Trash Bin", "User Management");
                $_SESSION['success'] = "User moved to Trash successfully!";
            }
        } else {
            http_response_code(400);
            exit('Invalid user action.');
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

<style>
    #usersTable .user-action-button {
        align-items: center;
        border-radius: var(--bs-btn-border-radius, .375rem);
        display: inline-flex;
        flex-shrink: 0;
        height: 30px;
        justify-content: center;
        line-height: 1;
        padding: 0;
        width: 30px;
    }
</style>

<div class="app-main dashboard-main">
    <section class="page-head mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h1>User Management</h1>
            <p class="text-muted">Manage system users, assign roles, and control account statuses.</p>
        </div>
        <?php if ($canCreateUsers): ?><div>
            <a href="add-user.php" class="header-pos-btn btn btn-dark btn-sm">
                <i class="bi bi-person-plus-fill me-1"></i> Add New User
            </a>
        </div><?php endif; ?>
    </section>

    <div class="card border-0 shadow-sm table-card dashboard-panel">
        <div class="table-card-header p-3 border-bottom bg-white d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="d-flex align-items-center gap-2">
                <span class="fw-bold text-dark">Registered Users List</span>
                <?php if ($canDeleteUsers): ?><button type="button" class="btn btn-outline-secondary btn-sm" id="toggleUserSelection" aria-label="Enable user selection" aria-pressed="false" title="Enable user selection">
                    <i class="bi bi-check-square"></i>
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="selectAllUsers">Select All</button>
                <button type="submit" form="usersBulkTrashForm" class="btn btn-danger btn-sm d-none text-nowrap flex-shrink-0" id="moveSelectedUsersToTrash" disabled>
                    <i class="bi bi-trash me-1" aria-hidden="true"></i>Move Selected to Trash
                </button><?php endif; ?>
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

                <form method="GET" action="" class="d-flex align-items-center gap-2 flex-nowrap m-0">
                    <input type="hidden" name="limit" value="<?php echo $limit; ?>">
                    <input type="hidden" name="sort" value="<?php echo $sortOrder; ?>">
                    <input type="text" name="search" class="form-control form-control-sm search-input flex-grow-1" style="width: auto; min-width: 0;" placeholder="Search user..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-sm btn-dark flex-shrink-0">Search</button>
                    <?php if (!empty($search)): ?>
                        <a href="users.php" class="btn btn-sm btn-outline-secondary flex-shrink-0">Reset</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <?php if ($canDeleteUsers): ?>
        <form method="POST" action="users.php" id="usersBulkTrashForm">
            <input type="hidden" name="bulk_delete" value="1">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
        <?php endif; ?>
        <div class="table-responsive">
            <table class="table data-table align-middle mb-0" id="usersTable" data-sortable-table>
                <thead>
                    <tr>
                        <?php if ($canDeleteUsers): ?><th class="ps-3 user-select-column d-none" style="width: 50px;">Select</th><?php endif; ?>
                        <th class="ps-3" style="width: 70px;">#</th>
                        <th>User</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <?php if ($showUserActions): ?><th class="text-end pe-3" style="width: 128px;">Actions</th><?php endif; ?>
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
                                <?php if ($canDeleteUsers): ?><td class="ps-3 user-select-column d-none">
                                    <input type="checkbox" name="user_ids[]" value="<?php echo (int) $user['id']; ?>" class="form-check-input user-checkbox m-0" aria-label="Select user">
                                </td><?php endif; ?>
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
                                <?php if ($showUserActions): ?><td class="text-end pe-3" style="width: 128px; min-width: 128px;">
                                    <div class="d-inline-flex align-items-center gap-2">
                                    <?php if (strtolower(trim((string) $user['role_name'])) !== 'super admin'): ?>
                                        <?php if ($canEditUsers && (int) $user['id'] !== getCurrentAdminId()): ?>
                                            <button type="button" onclick="confirmUserStatusChange(<?php echo (int)$user['id']; ?>, '<?php echo htmlspecialchars($user['status'], ENT_QUOTES); ?>')" class="btn btn-sm btn-outline-warning user-action-button" title="Toggle Status"><i class="bi bi-power"></i></button>
                                        <?php endif; ?>
                                        <?php if ($canEditUsers): ?><a href="edit-user.php?id=<?php echo (int) $user['id']; ?>" class="btn btn-sm btn-outline-primary user-action-button" title="Edit User"><i class="bi bi-pencil"></i></a><?php endif; ?>
                                    <?php endif; ?>
                                    <?php if ($canDeleteUsers && $user['id'] != getCurrentAdminId() && strtolower(trim((string) $user['role_name'])) !== 'super admin'): ?>
                                        <button type="button" class="btn btn-sm btn-danger user-action-button" onclick="confirmUserDelete(<?php echo (int) $user['id']; ?>, this.dataset.name)" data-name="<?php echo htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?>" aria-label="Move user to Trash" title="Move to Trash"><i class="bi bi-trash-fill" aria-hidden="true"></i></button>
                                    <?php endif; ?>
                                    </div>
                                </td><?php endif; ?>
                            </tr>
                            <?php
                            if ($sortOrder === 'ASC') {
                                $counter++;
                            } else {
                                $counter--;
                            }
                        }
                    } else {
                        echo "<tr><td colspan='" . ($showUserActions ? ($canDeleteUsers ? '7' : '6') : ($canDeleteUsers ? '6' : '5')) . "' class='text-center text-muted py-3'>No users found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <?php if ($canDeleteUsers): ?></form><?php endif; ?>

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
const userActionCsrf = <?= json_encode(csrfToken(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
function submitUserAction(action, id) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'users.php';
    [['action', action], ['id', id], ['csrf_token', userActionCsrf]].forEach(([name, value]) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
}

document.addEventListener('DOMContentLoaded', function() {
    const toggleUserSelectionButton = document.getElementById('toggleUserSelection');
    const selectAllUsersButton = document.getElementById('selectAllUsers');
    const moveSelectedUsersToTrashButton = document.getElementById('moveSelectedUsersToTrash');
    const usersBulkTrashForm = document.getElementById('usersBulkTrashForm');
    const userSelectColumns = document.querySelectorAll('.user-select-column');
    const userCheckboxes = document.querySelectorAll('.user-checkbox');
    let userSelectionEnabled = false;
    let userBulkTrashConfirmed = false;

    function updateUserSelectionControls() {
        const selectedCount = document.querySelectorAll('.user-checkbox:checked').length;
        moveSelectedUsersToTrashButton.disabled = selectedCount === 0;
        selectAllUsersButton.textContent = userCheckboxes.length > 0 && selectedCount === userCheckboxes.length
            ? 'Deselect All'
            : 'Select All';
    }

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
            moveSelectedUsersToTrashButton.classList.toggle('d-none', !userSelectionEnabled);
            toggleUserSelectionButton.setAttribute('aria-pressed', String(userSelectionEnabled));
            toggleUserSelectionButton.setAttribute(
                'aria-label',
                userSelectionEnabled ? 'Disable user selection' : 'Enable user selection'
            );
            toggleUserSelectionButton.title = userSelectionEnabled
                ? 'Disable user selection'
                : 'Enable user selection';

            if (!userSelectionEnabled) {
                userCheckboxes.forEach((checkbox) => { checkbox.checked = false; });
            }
            updateUserSelectionControls();
        });
    }

    if (selectAllUsersButton) {
        selectAllUsersButton.addEventListener('click', () => {
            const selectAll = selectAllUsersButton.textContent === 'Select All';
            userCheckboxes.forEach((checkbox) => { checkbox.checked = selectAll; });
            updateUserSelectionControls();
        });
    }

    document.addEventListener('change', (event) => {
        if (event.target.matches('.user-checkbox')) {
            updateUserSelectionControls();
        }
    });

    document.querySelectorAll('#usersTable tbody tr').forEach((row) => {
        row.addEventListener('click', (event) => {
            if (!userSelectionEnabled || event.target.closest('a, button, input, select, textarea, label')) return;
            const checkbox = row.querySelector('.user-checkbox');
            if (checkbox) {
                checkbox.checked = !checkbox.checked;
                updateUserSelectionControls();
            }
        });
    });

    if (usersBulkTrashForm) {
        usersBulkTrashForm.addEventListener('submit', (event) => {
            if (userBulkTrashConfirmed) return;
            event.preventDefault();
            const selectedCount = document.querySelectorAll('.user-checkbox:checked').length;
            if (selectedCount < 1) return;

            Swal.fire({
                title: 'Move selected users to Trash?',
                text: selectedCount + ' user(s) can be restored later from the Trash Bin.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Move to Trash'
            }).then((result) => {
                if (result.isConfirmed) {
                    userBulkTrashConfirmed = true;
                    usersBulkTrashForm.submit();
                }
            });
        });
    }

});

function confirmUserDelete(id, userName) {
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
            submitUserAction('delete', id);
        }
    });
}

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
            submitUserAction('toggle', id);
        }
    });
}
</script>

<?php if (isset($_SESSION['success'])): ?>
<script>
    Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: <?= json_encode(
            (string) $_SESSION['success'],
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
        ) ?>,
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
        text: <?= json_encode(
            (string) $_SESSION['error'],
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
        ) ?>,
        confirmButtonColor: '#dc3545'
    });
</script>
<?php unset($_SESSION['error']); endif; ?>

<?php include "../layouts/footer.php"; ?>