<?php
// users/users-content.php
?>
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
                <select name="limit" class="form-select form-select-sm" style="width: 80px;">
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
            <a href="<?php echo $sortLink; ?>" class="btn btn-outline-secondary btn-sm p-2 rounded-3 sort-link" title="Toggle Sorting">
                <i class="bi bi-arrow-down-up"></i>
            </a>

            <form method="GET" action="" id="userSearchForm" class="d-flex gap-2 m-0">
                <input type="hidden" name="limit" value="<?php echo $limit; ?>">
                <input type="hidden" name="sort" value="<?php echo $sortOrder; ?>">
                <input type="text" name="search" class="form-control form-control-sm search-input" placeholder="Search user..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-sm btn-dark">Search</button>
                <?php if (!empty($search)): ?>
                    <a href="users.php" class="btn btn-sm btn-outline-secondary reset-link">Reset</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Table cell overflow fix for avatars -->
    <style>
        #usersTable td {
            white-space: normal !important;
            overflow: visible !important;
        }
    </style>

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
                                    // 1. Database se avatar ka naam lein (agar khali ya NULL ho toh default-avatar.png lein)
                                    $avatarName = !empty($user['avatar']) ? trim($user['avatar']) : 'default-avatar.png';
                                    
                                    // 2. Server path check karne ke liye ke file sach mein folder mein hai ya nahi
                                    $serverAvatarPath = __DIR__ . '/../uploads/avatars/' . $avatarName;
                                    
                                    if (empty($user['avatar']) || !file_exists($serverAvatarPath)) {
                                        $avatarName = 'default-avatar.png';
                                    }

                                    // 3. Browser ke <img> tag ke liye link/path generate karna
                                    $webAvatarPath = '../uploads/avatars/' . rawurlencode($avatarName);
                                    ?>
                                    
                                    <!-- Image Tag jisme default ya real image ka link lag jata hai -->
                                    <img src="<?php echo $webAvatarPath; ?>" class="rounded-circle border" style="width: 32px; height: 32px; object-fit: cover;" alt="Avatar">
                                    
                                    <strong><?php echo htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($user['role_name']); ?></span></td>
                            <td><span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($user['status']); ?></span></td>
                            <td class="text-end pe-3">
                                <a href="users.php?action=toggle&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-warning py-0 px-2" title="Toggle Status"><i class="bi bi-power"></i></a>
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