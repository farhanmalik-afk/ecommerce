<?php

function ensureAppSettingsSchema(mysqli $conn): void
{
    static $schemaEnsured = false;
    if ($schemaEnsured) {
        return;
    }

    if (!$conn->query("CREATE TABLE IF NOT EXISTS app_settings (
        setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
        setting_value MEDIUMTEXT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")) {
        throw new RuntimeException('Unable to initialize application settings: ' . $conn->error);
    }

    $customerColumn = $conn->query("SHOW COLUMNS FROM customers LIKE 'is_deleted'");
    if (!$customerColumn) {
        throw new RuntimeException('Unable to check customer trash schema: ' . $conn->error);
    }
    if ($customerColumn->num_rows === 0 && !$conn->query("ALTER TABLE customers ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0")) {
        throw new RuntimeException('Unable to add the customer trash field: ' . $conn->error);
    }
    $schemaEnsured = true;
}

function appPermissionDefinitions(): array
{
    $actions = ['view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete', 'restore' => 'Restore', 'export' => 'Export', 'manage' => 'Manage'];
    $modules = [
        'dashboard' => 'Dashboard',
        'customers' => 'Customers',
        'products' => 'Products',
        'categories' => 'Categories',
        'orders' => 'Orders',
        'payments' => 'Payments',
        'returns' => 'Returns',
        'inventory' => 'Inventory',
        'pos' => 'POS',
        'suppliers' => 'Suppliers',
        'purchase_management' => 'Purchase Management',
        'stock_management' => 'Stock Management',
        'notifications' => 'Notifications',
        'sales_reports' => 'Sales Reports',
        'profit_tracker' => 'Profit Tracker',
        'user_management' => 'User Management',
        'trash' => 'Trash Bin',
        'settings' => 'Settings',
    ];
    $definitions = [];
    foreach ($modules as $module => $label) {
        $definitions[$module] = ['label' => $label, 'actions' => $actions];
    }

    foreach ([
        'general' => 'General',
        'store' => 'Store',
        'pos' => 'POS',
        'security' => 'Security',
        'smtp' => 'SMTP',
        'invoices' => 'Invoices',
        'inventory' => 'Inventory',
        'backup' => 'Backup',
        'payments' => 'Payments',
        'tax' => 'Tax',
        'notifications' => 'Notifications',
        'system' => 'System & Maintenance',
    ] as $module => $label) {
        $key = 'settings_' . $module;
        $definitions[$key] = ['label' => 'Settings: ' . $label, 'actions' => $actions];
    }

    return $definitions;
}

function ensureRbacSchema(mysqli $conn): void
{
    static $schemaEnsured = false;
    if ($schemaEnsured) {
        return;
    }
    $versionResult = $conn->query("SELECT setting_value FROM app_settings WHERE setting_key = 'rbac_schema_version' LIMIT 1");
    if (!$versionResult) {
        throw new RuntimeException('Unable to check access-control migration status: ' . $conn->error);
    }
    if ((string) ($versionResult->fetch_column() ?? '') === '3') {
        $schemaEnsured = true;
        return;
    }

    $schemas = [
        "CREATE TABLE IF NOT EXISTS roles (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            description VARCHAR(255) NOT NULL DEFAULT '',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            UNIQUE KEY uq_roles_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS permissions (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            module_name VARCHAR(100) NOT NULL,
            action_name VARCHAR(50) NOT NULL,
            KEY idx_permissions_module_action (module_name, action_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS role_permissions (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            role_id INT NOT NULL,
            permission_id INT NOT NULL,
            UNIQUE KEY uq_role_permission (role_id, permission_id),
            KEY idx_role_permissions_permission (permission_id),
            CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE,
            CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS module_status (
            module_name VARCHAR(100) NOT NULL PRIMARY KEY,
            is_enabled TINYINT(1) NOT NULL DEFAULT 1,
            updated_by INT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_module_status_enabled (is_enabled)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];
    foreach ($schemas as $schema) {
        if (!$conn->query($schema)) {
            throw new RuntimeException('Unable to initialize access-control schema: ' . $conn->error);
        }
    }

    foreach ([
        ['Super Admin', 'Full system control and user management'],
        ['Admin', 'Store operations and staff management'],
        ['Staff', 'Assigned operational responsibilities'],
    ] as [$name, $description]) {
        $stmt = $conn->prepare(
            'INSERT INTO roles (name, description, is_active)
             SELECT ?, ?, 1 WHERE NOT EXISTS (SELECT 1 FROM roles WHERE LOWER(name) = LOWER(?))'
        );
        if (!$stmt) {
            throw new RuntimeException('Unable to prepare default role migration: ' . $conn->error);
        }
        $stmt->bind_param('sss', $name, $description, $name);
        if (!$stmt->execute()) {
            throw new RuntimeException('Unable to create a default role: ' . $stmt->error);
        }
        $stmt->close();
    }

    $permissionSelect = $conn->prepare(
        'SELECT id FROM permissions WHERE module_name = ? AND action_name = ? ORDER BY id LIMIT 1'
    );
    $permissionInsert = $conn->prepare(
        'INSERT INTO permissions (name, module_name, action_name) VALUES (?, ?, ?)'
    );
    if (!$permissionSelect || !$permissionInsert) {
        throw new RuntimeException('Unable to prepare permission catalog migration: ' . $conn->error);
    }
    $catalog = [];
    foreach (appPermissionDefinitions() as $module => $definition) {
        foreach ($definition['actions'] as $action => $label) {
            $permissionSelect->bind_param('ss', $module, $action);
            if (!$permissionSelect->execute()) {
                throw new RuntimeException('Unable to inspect permissions: ' . $permissionSelect->error);
            }
            $permissionId = (int) ($permissionSelect->get_result()->fetch_column() ?? 0);
            if ($permissionId === 0) {
                $name = $label . ' ' . $definition['label'];
                $permissionInsert->bind_param('sss', $name, $module, $action);
                if (!$permissionInsert->execute()) {
                    throw new RuntimeException('Unable to add a permission: ' . $permissionInsert->error);
                }
                $permissionId = (int) $conn->insert_id;
            }
            $catalog[$module][$action] = $permissionId;
        }
    }
    $permissionSelect->close();
    $permissionInsert->close();

    $moduleInsert = $conn->prepare('INSERT IGNORE INTO module_status (module_name, is_enabled) VALUES (?, 1)');
    if (!$moduleInsert) {
        throw new RuntimeException('Unable to prepare module availability migration: ' . $conn->error);
    }
    foreach (array_keys(appPermissionDefinitions()) as $module) {
        $moduleInsert->bind_param('s', $module);
        if (!$moduleInsert->execute()) {
            throw new RuntimeException('Unable to add module availability: ' . $moduleInsert->error);
        }
    }
    $moduleInsert->close();

    $settings = $conn->query('SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN ("role_permissions", "role_permissions_managed")');
    if (!$settings) {
        throw new RuntimeException('Unable to inspect legacy role permissions: ' . $conn->error);
    }
    $legacy = [];
    while ($row = $settings->fetch_assoc()) {
        $legacy[$row['setting_key']] = $row['setting_value'];
    }
    $managed = json_decode($legacy['role_permissions_managed'] ?? '[]', true);
    $managed = is_array($managed) ? array_map('intval', $managed) : [];
    $roles = $conn->query('SELECT id, name FROM roles');
    if (!$roles) {
        throw new RuntimeException('Unable to load roles during permission migration: ' . $conn->error);
    }
    $insertAssignment = $conn->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
    $legacyPermissions = json_decode($legacy['role_permissions'] ?? '{}', true);
    if (!$insertAssignment) {
        throw new RuntimeException('Unable to prepare permission migration: ' . $conn->error);
    }
    $managedRoleIds = array_fill_keys($managed, true);
    while ($role = $roles->fetch_assoc()) {
        $roleId = (int) $role['id'];
        if (!isset($managedRoleIds[$roleId])) {
            $roleName = strtolower(trim((string) $role['name']));
            $legacyModules = is_array($legacyPermissions) && isset($legacyPermissions[$roleName])
                ? $legacyPermissions[$roleName]
                : [];
            if (is_array($legacyModules)) {
                foreach ($legacyModules as $legacyModule) {
                    $module = match ($legacyModule) {
                        'reports' => 'sales_reports',
                        'profit-report' => 'profit_tracker',
                        default => $legacyModule,
                    };
                    if (isset($catalog[$module]['view'])) {
                        $permissionId = $catalog[$module]['view'];
                        $insertAssignment->bind_param('ii', $roleId, $permissionId);
                        if (!$insertAssignment->execute()) {
                            throw new RuntimeException('Unable to migrate legacy role permissions: ' . $insertAssignment->error);
                        }
                    }
                }
            }
        }
        $managed[] = $roleId;
    }
    $insertAssignment->close();
    $managedJson = json_encode(array_values(array_unique($managed)));
    $saveManaged = $conn->prepare(
        "INSERT INTO app_settings (setting_key, setting_value) VALUES ('role_permissions_managed', ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
    );
    if (!$saveManaged) {
        throw new RuntimeException('Unable to prepare permission migration marker: ' . $conn->error);
    }
    $saveManaged->bind_param('s', $managedJson);
    if (!$saveManaged->execute()) {
        throw new RuntimeException('Unable to save permission migration marker: ' . $saveManaged->error);
    }
    $saveManaged->close();

    $legacySettingsGrants = $conn->query(
        "SELECT DISTINCT rp.role_id, p.action_name
         FROM role_permissions rp
         JOIN permissions p ON p.id = rp.permission_id
         WHERE p.module_name = 'settings' AND p.action_name IN ('view', 'edit')"
    );
    if (!$legacySettingsGrants) {
        throw new RuntimeException('Unable to inspect existing Settings grants: ' . $conn->error);
    }
    $saveSubmoduleGrant = $conn->prepare(
        'INSERT INTO role_permissions (role_id, permission_id)
         SELECT ?, ? WHERE NOT EXISTS (
             SELECT 1 FROM role_permissions WHERE role_id = ? AND permission_id = ?
         )'
    );
    if (!$saveSubmoduleGrant) {
        throw new RuntimeException('Unable to prepare Settings permission migration: ' . $conn->error);
    }
    $settingSubmodules = array_filter(
        array_keys($catalog),
        static fn(string $module): bool => str_starts_with($module, 'settings_')
    );
    while ($grant = $legacySettingsGrants->fetch_assoc()) {
        foreach ($settingSubmodules as $module) {
            $permissionId = $catalog[$module][$grant['action_name']] ?? null;
            if ($permissionId === null) {
                continue;
            }
            $roleId = (int) $grant['role_id'];
            $permissionId = (int) $permissionId;
            $saveSubmoduleGrant->bind_param('iiii', $roleId, $permissionId, $roleId, $permissionId);
            if (!$saveSubmoduleGrant->execute()) {
                throw new RuntimeException('Unable to preserve Settings permissions: ' . $saveSubmoduleGrant->error);
            }
        }
    }
    $saveSubmoduleGrant->close();

    $version = '3';
    $saveVersion = $conn->prepare(
        "INSERT INTO app_settings (setting_key, setting_value) VALUES ('rbac_schema_version', ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
    );
    if (!$saveVersion) {
        throw new RuntimeException('Unable to prepare access-control migration marker: ' . $conn->error);
    }
    $saveVersion->bind_param('s', $version);
    if (!$saveVersion->execute()) {
        throw new RuntimeException('Unable to save access-control migration marker: ' . $saveVersion->error);
    }
    $saveVersion->close();
    $schemaEnsured = true;
}

function getDefaultAppSettings(): array
{
    return [
        'theme' => 'original',
        'currency' => 'USD',
        'language' => 'en',
        'store_name' => 'Admin Dashboard', // <-- Yahan store_name add kar diya hai
        'pos_enabled' => '1',
        'pos_tax_rate' => '5',
        'logo' => '',
        'role_permissions' => json_encode([
            'admin' => ['dashboard', 'customers', 'products', 'categories', 'orders', 'payments', 'returns', 'inventory', 'pos', 'reports', 'trash', 'settings', 'notifications', 'user_management', 'virtual-reality', 'profit-report'],
            'manager' => ['dashboard', 'customers', 'products', 'categories', 'orders', 'payments', 'returns', 'inventory', 'pos', 'reports', 'trash', 'notifications', 'virtual-reality', 'profit-report'],
            'cashier' => ['dashboard', 'customers', 'orders', 'pos'],
        ]),
    ];
}

function loadAppSettings(mysqli $conn): array
{
    $settings = getDefaultAppSettings();
    $result = $conn->query('SELECT setting_key, setting_value FROM app_settings');
    if (!$result) {
        throw new RuntimeException('Unable to load application settings: ' . $conn->error);
    }
    while ($row = $result->fetch_assoc()) {
        // Ab database mein mojood har key seedha load ho jaye gi
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}

function appCurrencySymbol(?array $settings = null): string
{
    global $appSettings;
    $currency = ($settings ?? $appSettings ?? [])['currency'] ?? 'USD';
    return [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'PKR' => 'Rs ',
        'INR' => '₹',
        'AED' => 'د.إ ',
        'SAR' => 'ر.س ',
    ][$currency] ?? '$';
}

function formatAppCurrency($amount, ?array $settings = null): string
{
    return appCurrencySymbol($settings) . number_format((float) $amount, 2);
}

function appThemeName(): string
{
    global $appSettings;
    $theme = $appSettings['theme'] ?? 'original';
    return in_array($theme, ['original', 'navy-gold', 'modern-blue', 'emerald-green', 'purple-style'], true)
        ? $theme
        : 'original';
}

function appLanguage(): string
{
    global $appSettings;
    return ($appSettings['language'] ?? 'en') === 'ur' ? 'ur' : 'en';
}

ensureAppSettingsSchema($conn);
ensureRbacSchema($conn);
$appSettings = loadAppSettings($conn);