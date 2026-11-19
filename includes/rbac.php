<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function getCurrentAdminId(): int
{
    return (int) ($_SESSION['admin_id'] ?? 0);
}

function currentAdminRole(): ?array
{
    global $conn;
    static $role = null;
    static $loaded = false;
    if ($loaded) {
        return $role;
    }

    $adminId = getCurrentAdminId();
    if ($adminId < 1) {
        $loaded = true;
        return null;
    }
    $stmt = $conn->prepare(
        "SELECT r.id, r.name, r.is_active
         FROM admins a JOIN roles r ON r.id = a.role_id
         WHERE a.id = ? AND a.status = 'Active' LIMIT 1"
    );
    if (!$stmt) {
        error_log('Unable to verify the current admin role: ' . $conn->error);
        sendAppError(500, 'We could not verify your account. Please try again later.');
    }
    $stmt->bind_param('i', $adminId);
    if (!$stmt->execute()) {
        error_log('Unable to verify the current admin role: ' . $stmt->error);
        $stmt->close();
        sendAppError(500, 'We could not verify your account. Please try again later.');
    }
    $role = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    $loaded = true;
    return $role;
}

function checkAuth(): void
{
    global $conn;
    $adminId = filter_var($_SESSION['admin_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$adminId || $adminId < 1) {
        unset($_SESSION['admin_id'], $_SESSION['admin_role_id'], $_SESSION['admin_email'], $_SESSION['admin_logged_in']);
        sendAppError(401, 'Please sign in to continue.');
    }

    $stmt = $conn->prepare(
        "SELECT a.role_id, a.email, a.name, r.is_active
         FROM admins a JOIN roles r ON r.id = a.role_id
         WHERE a.id = ? AND a.status = 'Active' LIMIT 1"
    );
    if (!$stmt) {
        error_log('Unable to verify the current admin session: ' . $conn->error);
        sendAppError(500, 'We could not verify your account. Please try again later.');
    }
    $stmt->bind_param('i', $adminId);
    if (!$stmt->execute()) {
        error_log('Unable to verify the current admin session: ' . $stmt->error);
        $stmt->close();
        sendAppError(500, 'We could not verify your account. Please try again later.');
    }
    $admin = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$admin || (int) $admin['is_active'] !== 1) {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        sendAppError(401, 'Your account or assigned role is inactive. Please contact an administrator.');
    }

    $_SESSION['admin_role_id'] = (int) $admin['role_id'];
    $_SESSION['admin_email'] = $admin['email'];
    $_SESSION['admin_name'] = $admin['name'];
    $_SESSION['admin_logged_in'] = true;
}

function getBaseUrl(): string
{
    $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $base = rtrim(dirname($scriptPath), '/');
    while (in_array(basename($base), [
        'users', 'customers', 'products', 'orders', 'inventory', 'sales', 'payments',
        'returns', 'pos', 'categories', 'reports', 'layouts', 'includes', 'notifications',
    ], true)) {
        $base = rtrim(dirname($base), '/');
    }
    return ($base === '' ? '' : $base) . '/';
}

function isSuperAdmin(?array $role = null): bool
{
    $role = $role ?? currentAdminRole();
    return $role !== null && strtolower(trim((string) $role['name'])) === 'super admin';
}

function appModuleEnabled(string $module): bool
{
    global $conn, $appSettings;
    if (str_starts_with($module, 'settings_') && $module !== 'settings') {
        if (!appModuleEnabled('settings')) {
            return false;
        }
    }
    if ($module === 'pos'
        && (($appSettings['pos_module_enabled'] ?? $appSettings['pos_enabled'] ?? '1') !== '1')) {
        return false;
    }

    $stmt = $conn->prepare('SELECT is_enabled FROM module_status WHERE module_name = ? LIMIT 1');
    if (!$stmt) {
        error_log('Unable to check module availability: ' . $conn->error);
        sendAppError(500, 'Module availability could not be verified.');
    }
    $stmt->bind_param('s', $module);
    if (!$stmt->execute()) {
        error_log('Unable to check module availability: ' . $stmt->error);
        $stmt->close();
        sendAppError(500, 'Module availability could not be verified.');
    }
    $status = $stmt->get_result()->fetch_column();
    $stmt->close();
    return $status === false || (int) $status === 1;
}

function hasPermission(mysqli $conn, int $adminId, string $module, string $action = 'view'): bool
{
    if ($adminId < 1 || !appModuleEnabled($module)) {
        return false;
    }
    if (str_starts_with($module, 'settings_') && !hasPermission($conn, $adminId, 'settings', $action)) {
        return false;
    }

    $stmt = $conn->prepare(
        "SELECT r.id, r.name, r.is_active
         FROM admins a JOIN roles r ON r.id = a.role_id
         WHERE a.id = ? AND a.status = 'Active' LIMIT 1"
    );
    if (!$stmt) {
        error_log('Unable to check admin role: ' . $conn->error);
        sendAppError(500, 'Your access permissions could not be verified.');
    }
    $stmt->bind_param('i', $adminId);
    if (!$stmt->execute()) {
        error_log('Unable to check admin role: ' . $stmt->error);
        $stmt->close();
        sendAppError(500, 'Your access permissions could not be verified.');
    }
    $role = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$role || (int) $role['is_active'] !== 1) {
        return false;
    }
    if (strtolower(trim((string) $role['name'])) === 'super admin') {
        return true;
    }

    $permission = $conn->prepare(
        'SELECT 1 FROM role_permissions rp
         JOIN permissions p ON p.id = rp.permission_id
         WHERE rp.role_id = ? AND p.module_name = ? AND p.action_name = ? LIMIT 1'
    );
    if (!$permission) {
        error_log('Unable to check role permission: ' . $conn->error);
        sendAppError(500, 'Your access permissions could not be verified.');
    }
    $roleId = (int) $role['id'];
    $permission->bind_param('iss', $roleId, $module, $action);
    if (!$permission->execute()) {
        error_log('Unable to check role permission: ' . $permission->error);
        $permission->close();
        sendAppError(500, 'Your access permissions could not be verified.');
    }
    $permission->store_result();
    $allowed = $permission->num_rows > 0;
    $permission->close();
    return $allowed;
}

function appUserCan(string $module): bool
{
    global $conn;
    return hasPermission($conn, getCurrentAdminId(), $module, 'view');
}

function respondForbidden(string $reason = 'permission'): void
{
    if ($reason === 'module') {
        sendAppError(403, 'This module is currently unavailable. Please contact an administrator.', 'module');
    }
    sendAppError(403, 'You do not have permission to access this page or perform this action.');
}

function enforceAccess(mysqli $conn, string $module, string $action = 'view'): void
{
    checkAuth();
    if (!appModuleEnabled($module)) {
        respondForbidden('module');
    }
    if (!hasPermission($conn, getCurrentAdminId(), $module, $action)) {
        logActivity($conn, getCurrentAdminId(), "Unauthorized access attempt: {$module} ({$action})", 'Security');
        respondForbidden();
    }
}

function getRequestAccessContext(): ?array
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $segments = array_values(array_filter(explode('/', trim($script, '/')), 'strlen'));
    $folder = count($segments) > 1 ? $segments[count($segments) - 2] : '';
    $file = basename($script);
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    $module = match ($folder) {
        'users' => 'user_management',
        'customers' => 'customers',
        'products' => $file === 'trash.php' ? 'trash' : 'products',
        'categories' => 'categories',
        'inventory' => match ($file) {
            'suppliers.php' => 'suppliers',
            'stock-adjust.php', 'stock-history.php' => 'stock_management',
            default => 'inventory',
        },
        'orders' => 'orders',
        'payments' => 'payments',
        'returns' => 'returns',
        'pos' => 'pos',
        'reports' => $file === 'profit-report.php' ? 'profit_tracker' : 'sales_reports',
        'notifications' => 'notifications',
        default => match ($file) {
            'settings.php' => 'settings',
            'users.php', 'add-user.php', 'edit-user.php', 'activity-logs.php' => 'user_management',
            'notifications.php' => 'notifications',
            'profile.php' => 'user_management',
            'index.php' => 'dashboard',
            'global-search.php', 'semantic-search.php', 'index-semantic-search.php' => 'dashboard',
            default => 'dashboard',
        },
    };

    if ($file === 'settings.php') {
        $tab = $method === 'POST'
            ? ($_POST['settings_module'] ?? $_GET['tab'] ?? 'general')
            : ($_GET['tab'] ?? 'general');
        $module = match ($tab) {
            'roles' => 'settings',
            'general', 'store', 'pos', 'security', 'smtp', 'invoices', 'inventory', 'backup',
            'payments', 'tax', 'notifications', 'system' => 'settings_' . $tab,
            default => 'settings',
        };
    }

    if ($file === 'trash.php' && $method === 'POST') {
        $module = match ($_GET['type'] ?? 'products') {
            'users' => 'user_management',
            'customers' => 'customers',
            'orders' => 'orders',
            'notifications' => 'notifications',
            default => 'products',
        };
    }

    $action = match ($method) {
        'DELETE' => 'delete',
        'POST', 'PUT', 'PATCH' => 'edit',
        default => 'view',
    };
    if (in_array($file, ['check-email.php', 'check-customer-duplicate.php', 'check-duplicate.php'], true)) {
        $action = 'view';
    } elseif ($file === 'export_products.php') {
        $action = 'export';
    } elseif ($method === 'POST') {
        if (str_starts_with($file, 'add-') || in_array($file, ['import_products.php', 'checkout_process.php'], true)) {
            $action = 'create';
        } elseif (str_starts_with($file, 'edit-') || in_array($file, ['stock-adjust.php', 'settings.php'], true)) {
            $action = 'edit';
        } elseif (isset($_POST['bulk_delete']) || ($_POST['action'] ?? '') === 'delete') {
            $action = 'delete';
        } elseif (in_array($_POST['action'] ?? '', ['toggle', 'restore', 'recover', 'mark_read'], true)) {
            $action = 'edit';
        } elseif (in_array($file, ['returns.php', 'suppliers.php'], true)) {
            $action = 'create';
        } elseif ($file === 'send-credentials.php') {
            $action = 'edit';
        } elseif ($file === 'actions.php') {
            $action = 'view';
        } elseif ($file === 'mark-read.php') {
            $action = 'edit';
        }
    } elseif (in_array($file, ['add-order.php', 'add-category.php', 'add-customers.php', 'add-product.php', 'import_products.php'], true)) {
        $action = 'create';
    } elseif ($file === 'success.php') {
        $action = 'create';
    } elseif ($file === 'stock-adjust.php') {
        $action = 'edit';
    }

    if ($file === 'trash.php' && $method === 'POST') {
        $trashAction = $_POST['user_bulk_action']
            ?? $_POST['user_action']
            ?? $_POST['customer_bulk_action']
            ?? $_POST['customer_action']
            ?? $_POST['product_action']
            ?? $_POST['bulk_action']
            ?? '';
        $action = in_array($trashAction, ['delete', 'order_delete'], true) ? 'delete' : 'edit';
    }

    return ['module' => $module, 'action' => $action];
}

function logActivity(mysqli $conn, int $adminId, string $action, string $module): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $stmt = $conn->prepare('INSERT INTO activity_logs (admin_id, action, module, ip_address) VALUES (?, ?, ?, ?)');
    if (!$stmt) {
        error_log('Unable to prepare the activity log entry: ' . $conn->error);
        return;
    }
    $stmt->bind_param('isss', $adminId, $action, $module, $ip);
    if (!$stmt->execute()) {
        error_log('Unable to save the activity log entry: ' . $stmt->error);
    }
    $stmt->close();
}

function csrfToken(): string
{
    return $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
}

function verifyCsrfToken(?string $token): bool
{
    return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
