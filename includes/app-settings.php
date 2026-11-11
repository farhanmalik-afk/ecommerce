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

function getDefaultAppSettings(): array
{
    return [
        'theme' => 'luxury',
        'currency' => 'USD',
        'language' => 'en',
        'store_name' => 'Admin Dashboard', // <-- Yahan store_name add kar diya hai
        'pos_enabled' => '1',
        'pos_tax_rate' => '5',
        'logo' => '',
        'role_permissions' => json_encode([
            'admin' => ['dashboard', 'customers', 'products', 'categories', 'orders', 'payments', 'returns', 'inventory', 'pos', 'reports', 'trash', 'settings', 'notifications', 'virtual-reality', 'profit-report'],
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

function appUserCan(string $module): bool
{
    global $appSettings;
    $role = $_SESSION['role'] ?? 'admin';
    $permissions = json_decode($appSettings['role_permissions'] ?? '{}', true);
    if (!is_array($permissions) || !isset($permissions[$role]) || !is_array($permissions[$role])) {
        return $role === 'admin';
    }
    return in_array($module, $permissions[$role], true);
}

ensureAppSettingsSchema($conn);
$appSettings = loadAppSettings($conn);