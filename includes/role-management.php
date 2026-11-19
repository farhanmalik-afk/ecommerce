<?php

function handleRoleManagementRequest(mysqli $conn): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['role_action'])) {
        return;
    }
    if (!isSuperAdmin()) {
        sendAppError(403, 'Only the Super Admin can manage roles, permissions, and module availability.');
    }
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        sendAppError(403, 'Your session expired. Refresh the page and try again.');
    }

    $action = $_POST['role_action'];
    $transactionStarted = false;
    try {
        if ($action === 'save_role') {
            $roleId = filter_var($_POST['role_id'] ?? null, FILTER_VALIDATE_INT);
            $name = trim((string) ($_POST['name'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            if ($roleId === false || $roleId < 1 || $name === '' || strlen($name) > 100 || strlen($description) > 255) {
                throw new InvalidArgumentException('Enter a role name and a description within the allowed length.');
            }

            $stmt = $conn->prepare('SELECT name FROM roles WHERE id = ? LIMIT 1');
            $stmt->bind_param('i', $roleId);
            $stmt->execute();
            $currentName = $stmt->get_result()->fetch_column();
            $stmt->close();
            if ($currentName === false || strtolower(trim((string) $currentName)) === 'super admin') {
                throw new InvalidArgumentException('The Super Admin role is protected.');
            }
            $check = $conn->prepare('SELECT id FROM roles WHERE LOWER(name) = LOWER(?) AND id <> ? LIMIT 1');
            $check->bind_param('si', $name, $roleId);
            $check->execute();
            $duplicate = $check->get_result()->fetch_column();
            $check->close();
            if ($duplicate !== false) {
                throw new InvalidArgumentException('A role with that name already exists.');
            }
            $update = $conn->prepare('UPDATE roles SET name = ?, description = ?, is_active = ? WHERE id = ?');
            $update->bind_param('ssii', $name, $description, $isActive, $roleId);
            $update->execute();
            $update->close();
            $_SESSION['swal_success'] = 'Role updated successfully.';
            header('Location: settings.php?tab=roles&role_id=' . $roleId);
            exit();
        }

        if ($action === 'create_role') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            if ($name === '' || strlen($name) > 100 || strlen($description) > 255) {
                throw new InvalidArgumentException('Enter a role name and a description within the allowed length.');
            }
            if (in_array(strtolower($name), ['super admin', 'admin', 'staff'], true)) {
                throw new InvalidArgumentException('That name is reserved for a default role.');
            }
            $check = $conn->prepare('SELECT id FROM roles WHERE LOWER(name) = LOWER(?) LIMIT 1');
            $check->bind_param('s', $name);
            $check->execute();
            $duplicate = $check->get_result()->fetch_column();
            $check->close();
            if ($duplicate !== false) {
                throw new InvalidArgumentException('A role with that name already exists.');
            }
            $insert = $conn->prepare('INSERT INTO roles (name, description, is_active) VALUES (?, ?, 1)');
            $insert->bind_param('ss', $name, $description);
            $insert->execute();
            $roleId = (int) $conn->insert_id;
            $insert->close();
            $_SESSION['swal_success'] = 'Role created. Assign its permissions before assigning users.';
            header('Location: settings.php?tab=roles&role_id=' . $roleId);
            exit();
        }

        if ($action === 'delete_role') {
            $roleId = filter_var($_POST['role_id'] ?? null, FILTER_VALIDATE_INT);
            if ($roleId === false || $roleId < 1) {
                throw new InvalidArgumentException('Select a valid role.');
            }
            $stmt = $conn->prepare(
                "SELECT r.name, COUNT(a.id) AS assigned_users
                 FROM roles r LEFT JOIN admins a ON a.role_id = r.id
                 WHERE r.id = ? GROUP BY r.id, r.name"
            );
            $stmt->bind_param('i', $roleId);
            $stmt->execute();
            $role = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$role || strtolower(trim((string) $role['name'])) === 'super admin' || (int) $role['assigned_users'] > 0) {
                throw new InvalidArgumentException('Default roles and roles assigned to users cannot be deleted.');
            }
            $conn->begin_transaction();
            $transactionStarted = true;
            $clearPermissions = $conn->prepare('DELETE FROM role_permissions WHERE role_id = ?');
            $clearPermissions->bind_param('i', $roleId);
            $clearPermissions->execute();
            $clearPermissions->close();
            $delete = $conn->prepare('DELETE FROM roles WHERE id = ?');
            $delete->bind_param('i', $roleId);
            $delete->execute();
            $delete->close();
            $conn->commit();
            $transactionStarted = false;
            $_SESSION['swal_success'] = 'Role deleted successfully.';
            header('Location: settings.php?tab=roles');
            exit();
        }

        if ($action === 'save_permissions') {
            $roleId = filter_var($_POST['role_id'] ?? null, FILTER_VALIDATE_INT);
            if ($roleId === false || $roleId < 1) {
                throw new InvalidArgumentException('Select a valid role.');
            }
            $roleStmt = $conn->prepare("SELECT name FROM roles WHERE id = ? AND LOWER(name) <> 'super admin' LIMIT 1");
            $roleStmt->bind_param('i', $roleId);
            $roleStmt->execute();
            $role = $roleStmt->get_result()->fetch_assoc();
            $roleStmt->close();
            if (!$role) {
                throw new InvalidArgumentException('The Super Admin role is protected.');
            }

            $definitions = appPermissionDefinitions();
            $catalog = [];
            $catalogResult = $conn->query('SELECT id, module_name, action_name FROM permissions');
            if (!$catalogResult) {
                throw new RuntimeException('Unable to load permission catalog: ' . $conn->error);
            }
            while ($permission = $catalogResult->fetch_assoc()) {
                if (isset($definitions[$permission['module_name']]['actions'][$permission['action_name']])) {
                    $catalog[(int) $permission['id']] = $permission;
                }
            }
            $posted = $_POST['permission_ids'] ?? [];
            if (!is_array($posted)) {
                throw new InvalidArgumentException('The permission selection is invalid.');
            }
            $permissionIds = [];
            foreach ($posted as $value) {
                $id = filter_var($value, FILTER_VALIDATE_INT);
                if ($id === false || !isset($catalog[$id])) {
                    throw new InvalidArgumentException('The permission selection is invalid.');
                }
                $permissionIds[$id] = $id;
            }

            $conn->begin_transaction();
            $transactionStarted = true;
            $delete = $conn->prepare('DELETE FROM role_permissions WHERE role_id = ?');
            $delete->bind_param('i', $roleId);
            $delete->execute();
            $delete->close();
            $insert = $conn->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
            foreach ($permissionIds as $permissionId) {
                $insert->bind_param('ii', $roleId, $permissionId);
                $insert->execute();
            }
            $insert->close();

            $managedResult = $conn->query("SELECT setting_value FROM app_settings WHERE setting_key = 'role_permissions_managed' LIMIT 1");
            $managed = $managedResult ? json_decode((string) ($managedResult->fetch_column() ?? '[]'), true) : [];
            $managed = is_array($managed) ? array_map('intval', $managed) : [];
            $managed[] = (int) $roleId;
            $managed = array_values(array_unique($managed));
            $managedJson = json_encode($managed);
            $saveManaged = $conn->prepare(
                "INSERT INTO app_settings (setting_key, setting_value) VALUES ('role_permissions_managed', ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
            );
            $saveManaged->bind_param('s', $managedJson);
            $saveManaged->execute();
            $saveManaged->close();
            $conn->commit();
            $transactionStarted = false;
            $_SESSION['swal_success'] = 'Role permissions saved successfully.';
            header('Location: settings.php?tab=roles&role_id=' . $roleId);
            exit();
        }

        if ($action === 'save_modules') {
            $definitions = appPermissionDefinitions();
            $postedModules = $_POST['enabled_modules'] ?? [];
            if (!is_array($postedModules)) {
                throw new InvalidArgumentException('The module selection is invalid.');
            }
            $enabled = array_fill_keys(array_keys($definitions), false);
            foreach ($postedModules as $module) {
                if (!is_string($module) || !array_key_exists($module, $enabled)) {
                    throw new InvalidArgumentException('The module selection is invalid.');
                }
                $enabled[$module] = true;
            }
            if (!$enabled['settings']) {
                throw new InvalidArgumentException('Settings must remain enabled so the Super Admin can manage access.');
            }
            $conn->begin_transaction();
            $transactionStarted = true;
            $update = $conn->prepare(
                'INSERT INTO module_status (module_name, is_enabled, updated_by) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled), updated_by = VALUES(updated_by)'
            );
            $adminId = getCurrentAdminId();
            foreach ($enabled as $module => $isEnabled) {
                $status = $isEnabled ? 1 : 0;
                $update->bind_param('sii', $module, $status, $adminId);
                $update->execute();
            }
            $update->close();
            $conn->commit();
            $transactionStarted = false;
            $_SESSION['swal_success'] = 'Module availability updated successfully.';
            header('Location: settings.php?tab=roles');
            exit();
        }

        throw new InvalidArgumentException('Select a valid role management action.');
    } catch (InvalidArgumentException $exception) {
        if ($transactionStarted) {
            $conn->rollback();
        }
        $_SESSION['swal_error'] = $exception->getMessage();
        header('Location: settings.php?tab=roles');
        exit();
    } catch (Throwable $exception) {
        if ($transactionStarted) {
            $conn->rollback();
        }
        error_log('Role management operation failed: ' . $exception->getMessage());
        $_SESSION['swal_error'] = 'The role management change could not be completed.';
        header('Location: settings.php?tab=roles');
        exit();
    }
}

function renderRoleManagementPanel(mysqli $conn): void
{
    if (!isSuperAdmin()) {
        echo '<section class="settings-content-card"><div class="settings-card-body"><p class="mb-0">Role and global module management is reserved for the Super Admin.</p></div></section>';
        return;
    }
    $search = trim((string) ($_GET['role_search'] ?? ''));
    $page = max(1, (int) ($_GET['role_page'] ?? 1));
    $pageSize = 10;
    $offset = ($page - 1) * $pageSize;
    $searchLike = '%' . $search . '%';
    $count = $conn->prepare(
        "SELECT COUNT(*) FROM roles WHERE LOWER(name) <> 'super admin' AND (name LIKE ? OR description LIKE ?)"
    );
    $count->bind_param('ss', $searchLike, $searchLike);
    $count->execute();
    $totalRoles = (int) $count->get_result()->fetch_column();
    $count->close();
    $totalPages = max(1, (int) ceil($totalRoles / $pageSize));
    $rolesStmt = $conn->prepare(
        "SELECT r.id, r.name, r.description, r.is_active, COUNT(a.id) AS assigned_users
         FROM roles r LEFT JOIN admins a ON a.role_id = r.id
         WHERE LOWER(r.name) <> 'super admin' AND (r.name LIKE ? OR r.description LIKE ?)
         GROUP BY r.id, r.name, r.description, r.is_active
         ORDER BY r.name LIMIT ? OFFSET ?"
    );
    $rolesStmt->bind_param('ssii', $searchLike, $searchLike, $pageSize, $offset);
    $rolesStmt->execute();
    $rolesResult = $rolesStmt->get_result();
    $roles = [];
    while ($row = $rolesResult->fetch_assoc()) {
        $roles[] = $row;
    }
    $rolesStmt->close();

    $allRoles = $conn->query("SELECT id, name, description, is_active FROM roles WHERE LOWER(name) <> 'super admin' ORDER BY name");
    $selectableRoles = [];
    while ($role = $allRoles->fetch_assoc()) {
        $selectableRoles[(int) $role['id']] = $role;
    }
    $requestedRole = filter_var($_GET['role_id'] ?? null, FILTER_VALIDATE_INT);
    $selectedRoleId = $requestedRole !== false && isset($selectableRoles[$requestedRole])
        ? (int) $requestedRole
        : (int) (array_key_first($selectableRoles) ?? 0);
    $selectedRole = $selectableRoles[$selectedRoleId] ?? null;

    $definitions = appPermissionDefinitions();
    $permissionCatalog = [];
    $permissionResult = $conn->query('SELECT id, module_name, action_name FROM permissions ORDER BY id');
    while ($permission = $permissionResult->fetch_assoc()) {
        if (!isset($permissionCatalog[$permission['module_name']][$permission['action_name']])) {
            $permissionCatalog[$permission['module_name']][$permission['action_name']] = (int) $permission['id'];
        }
    }
    $assignedPermissions = [];
    if ($selectedRole) {
        $assigned = $conn->prepare('SELECT permission_id FROM role_permissions WHERE role_id = ?');
        $assigned->bind_param('i', $selectedRoleId);
        $assigned->execute();
        $assignedResult = $assigned->get_result();
        while ($permission = $assignedResult->fetch_assoc()) {
            $assignedPermissions[] = (int) $permission['permission_id'];
        }
        $assigned->close();
    }

    $moduleStatus = [];
    $statusResult = $conn->query('SELECT module_name, is_enabled FROM module_status');
    while ($status = $statusResult->fetch_assoc()) {
        $moduleStatus[$status['module_name']] = (int) $status['is_enabled'] === 1;
    }
    $csrf = htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8');
    ?>
        <section class="settings-content-card mb-4">
            <div class="settings-card-header">
                <h2>Roles &amp; Permissions</h2>
                <p>Create roles, assign granular module actions, and control global module availability. Super Admin access is protected.</p>
            </div>
            <div class="settings-card-body">
                <form method="post" class="row g-3 align-items-end">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="role_action" value="create_role">
                    <div class="col-12 col-xl-4"><label class="form-label fw-semibold" for="newRoleName">Role name</label><input id="newRoleName" name="name" class="form-control" maxlength="100" required></div>
                    <div class="col-12 col-xl-5"><label class="form-label fw-semibold" for="newRoleDescription">Description</label><input id="newRoleDescription" name="description" class="form-control" maxlength="255"></div>
                    <div class="col-12 col-xl-3 d-grid"><button class="btn btn-settings-save" type="submit">Create Role</button></div>
                </form>
            </div>
        </section>

        <section class="settings-content-card mb-4">
            <div class="settings-card-header"><h2>Existing Roles</h2><p>Search, review assignments, and edit role details.</p></div>
            <div class="settings-card-body">
                <form method="get" class="d-flex gap-2 mb-3">
                    <input type="hidden" name="tab" value="roles">
                    <input name="role_search" class="form-control" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search roles">
                    <button class="btn btn-outline-secondary" type="submit">Search</button>
                </form>
                <div class="table-responsive"><table class="table align-middle">
                    <thead><tr><th>Role</th><th>Description</th><th>Users</th><th>Status</th><th></th></tr></thead><tbody>
                    <?php foreach ($roles as $role): ?>
                        <tr>
                            <td class="fw-semibold"><?= htmlspecialchars($role['name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($role['description'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= (int) $role['assigned_users'] ?></td>
                            <td><span class="badge <?= (int) $role['is_active'] === 1 ? 'bg-success' : 'bg-secondary' ?>"><?= (int) $role['is_active'] === 1 ? 'Enabled' : 'Disabled' ?></span></td>
                            <td><a class="btn btn-sm btn-outline-primary" href="?tab=roles&amp;role_id=<?= (int) $role['id'] ?>">Manage</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$roles): ?><tr><td colspan="5" class="text-center text-muted">No matching roles.</td></tr><?php endif; ?>
                    </tbody>
                </table></div>
                <?php if ($totalPages > 1): ?><nav aria-label="Role pages"><ul class="pagination pagination-sm mb-0">
                    <?php for ($index = 1; $index <= $totalPages; $index++): ?>
                        <li class="page-item <?= $index === $page ? 'active' : '' ?>"><a class="page-link" href="?tab=roles&amp;role_page=<?= $index ?>&amp;role_search=<?= urlencode($search) ?>"><?= $index ?></a></li>
                    <?php endfor; ?>
                </ul></nav><?php endif; ?>
            </div>
        </section>

        <?php if ($selectedRole): ?>
            <section class="settings-content-card mb-4">
                <div class="settings-card-header"><h2>Edit <?= htmlspecialchars($selectedRole['name'], ENT_QUOTES, 'UTF-8') ?></h2><p>Disable the role to immediately revoke access for its assigned users.</p></div>
                <div class="settings-card-body">
                    <form method="post" class="row g-3 align-items-end">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>"><input type="hidden" name="role_action" value="save_role"><input type="hidden" name="role_id" value="<?= $selectedRoleId ?>">
                        <div class="col-12 col-xl-4"><label class="form-label fw-semibold" for="editRoleName">Role name</label><input id="editRoleName" name="name" class="form-control" maxlength="100" required value="<?= htmlspecialchars($selectedRole['name'], ENT_QUOTES, 'UTF-8') ?>"></div>
                        <div class="col-12 col-xl-4"><label class="form-label fw-semibold" for="editRoleDescription">Description</label><input id="editRoleDescription" name="description" class="form-control" maxlength="255" value="<?= htmlspecialchars($selectedRole['description'], ENT_QUOTES, 'UTF-8') ?>"></div>
                        <div class="col-12 col-xl-2"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="roleActive" name="is_active" value="1" <?= (int) $selectedRole['is_active'] === 1 ? 'checked' : '' ?>><label class="form-check-label" for="roleActive">Enabled</label></div></div>
                        <div class="col-12 col-xl-2 d-grid"><button class="btn btn-settings-save" type="submit">Save</button></div>
                    </form>
                    <form method="post" class="mt-3" onsubmit="return confirm('Delete this role? Roles assigned to users cannot be deleted.');">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>"><input type="hidden" name="role_action" value="delete_role"><input type="hidden" name="role_id" value="<?= $selectedRoleId ?>">
                        <button class="btn btn-outline-danger btn-sm" type="submit">Delete Role</button>
                    </form>
                </div>
            </section>

            <section class="settings-content-card mb-4">
                <div class="settings-card-header"><h2>Permission Matrix</h2><p>Choose exact module actions for this role. Permission changes apply immediately to all assigned users.</p></div>
                <div class="settings-card-body">
                    <form method="post" id="rolePermissionForm">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>"><input type="hidden" name="role_action" value="save_permissions"><input type="hidden" name="role_id" value="<?= $selectedRoleId ?>">
                        <div class="d-flex gap-2 mb-3"><button type="button" class="btn btn-outline-secondary btn-sm" id="selectAllPermissions">Select All</button><button type="button" class="btn btn-outline-secondary btn-sm" id="clearAllPermissions">Clear All</button></div>
                        <div class="accordion" id="permissionModules">
                            <?php foreach ($definitions as $module => $definition): $collapseId = 'module-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $module); ?>
                                <div class="accordion-item">
                                    <h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= htmlspecialchars($collapseId, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($definition['label'], ENT_QUOTES, 'UTF-8') ?></button></h3>
                                    <div id="<?= htmlspecialchars($collapseId, ENT_QUOTES, 'UTF-8') ?>" class="accordion-collapse collapse" data-bs-parent="#permissionModules"><div class="accordion-body d-flex flex-wrap gap-3">
                                        <?php foreach ($definition['actions'] as $action => $label): $permissionId = $permissionCatalog[$module][$action] ?? 0; if (!$permissionId) continue; ?>
                                            <label class="form-check d-flex align-items-center gap-2 mb-0"><input class="form-check-input permission-checkbox m-0" type="checkbox" name="permission_ids[]" value="<?= $permissionId ?>" <?= in_array($permissionId, $assignedPermissions, true) ? 'checked' : '' ?>><span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span></label>
                                        <?php endforeach; ?>
                                    </div></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="d-flex justify-content-end gap-2 mt-4"><a class="btn btn-outline-secondary" href="?tab=roles">Cancel</a><button type="submit" class="btn btn-settings-save">Save Permissions</button></div>
                    </form>
                </div>
            </section>
        <?php endif; ?>

        <section class="settings-content-card">
            <div class="settings-card-header"><h2>Global Module Availability</h2><p>Disabled modules are blocked server-side on page, AJAX, and action requests, regardless of role grants.</p></div>
            <div class="settings-card-body">
                <form method="post" id="moduleAvailabilityForm">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>"><input type="hidden" name="role_action" value="save_modules">
                    <div class="row g-2">
                    <?php foreach ($definitions as $module => $definition): ?>
                        <div class="col-md-6 col-xl-4"><label class="border rounded-3 p-3 d-flex justify-content-between align-items-center" for="moduleStatus-<?= htmlspecialchars($module, ENT_QUOTES, 'UTF-8') ?>">
                            <span><?= htmlspecialchars($definition['label'], ENT_QUOTES, 'UTF-8') ?></span>
                            <input class="form-check-input m-0" type="checkbox" id="moduleStatus-<?= htmlspecialchars($module, ENT_QUOTES, 'UTF-8') ?>" name="enabled_modules[]" value="<?= htmlspecialchars($module, ENT_QUOTES, 'UTF-8') ?>" <?= ($moduleStatus[$module] ?? true) ? 'checked' : '' ?> <?= $module === 'settings' ? 'disabled' : '' ?>>
                            <?php if ($module === 'settings'): ?><input type="hidden" name="enabled_modules[]" value="settings"><?php endif; ?>
                        </label></div>
                    <?php endforeach; ?>
                    </div>
                    <div class="d-flex justify-content-end mt-3"><button class="btn btn-settings-save" type="submit">Save Module Availability</button></div>
                </form>
            </div>
        </section>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const checkboxes = document.querySelectorAll('.permission-checkbox');
        document.getElementById('selectAllPermissions')?.addEventListener('click', () => checkboxes.forEach((checkbox) => { checkbox.checked = true; }));
        document.getElementById('clearAllPermissions')?.addEventListener('click', () => checkboxes.forEach((checkbox) => { checkbox.checked = false; }));
    });
    </script>
    <?php
}
