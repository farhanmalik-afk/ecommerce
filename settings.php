<?php
session_start();
include __DIR__ . '/config.php';

$notice = '';
$noticeType = 'success';
$formErrors = [];

// Default Settings Load across all modules
$formValues = [
    // General & Theme
    'theme' => $appSettings['theme'] ?? 'navy',
    'currency' => $appSettings['currency'] ?? 'PKR',
    'language' => $appSettings['language'] ?? 'en',
    'timezone' => $appSettings['timezone'] ?? 'Asia/Karachi',
    'maintenance_mode' => ($appSettings['maintenance_mode'] ?? '0') === '1',

    // Store Info
    'store_name' => $appSettings['store_name'] ?? 'My E-Commerce Store',
    'store_email' => $appSettings['store_email'] ?? 'support@store.com',
    'store_phone' => $appSettings['store_phone'] ?? '+92 300 1234567',
    'store_website' => $appSettings['store_website'] ?? 'https://mystore.com',
    'store_address' => $appSettings['store_address'] ?? 'Main Boulevard, Suite 100',
    'store_brn' => $appSettings['store_brn'] ?? 'REG-987654321',

    // POS Settings
    'pos_enabled' => ($appSettings['pos_enabled'] ?? '1') === '1',
    'pos_tax_rate' => $appSettings['pos_tax_rate'] ?? '10.00',
    'pos_receipt_header' => $appSettings['pos_receipt_header'] ?? 'Thank you for shopping with us!',

    // Security Settings
    'min_password_length' => $appSettings['min_password_length'] ?? '8',
    'session_timeout' => $appSettings['session_timeout'] ?? '120',
    'max_login_attempts' => $appSettings['max_login_attempts'] ?? '5',

    // Email / SMTP
    'smtp_host' => $appSettings['smtp_host'] ?? 'smtp.mailtrap.io',
    'smtp_port' => $appSettings['smtp_port'] ?? '587',
    'smtp_username' => $appSettings['smtp_username'] ?? '',

    // Invoices
    'invoice_prefix' => $appSettings['invoice_prefix'] ?? 'INV-',
    'invoice_start_num' => $appSettings['invoice_start_num'] ?? '1001',

    // Inventory
    'low_stock_threshold' => $appSettings['low_stock_threshold'] ?? '5',
    'allow_negative_stock' => ($appSettings['allow_negative_stock'] ?? '0') === '1',
];

if (empty($_SESSION['settings_csrf'])) {
    $_SESSION['settings_csrf'] = bin2hex(random_bytes(32));
}

// Database Backup Handler
if (isset($_GET['action']) && $_GET['action'] === 'backup_db') {
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }
    $sqlScript = "-- E-Commerce Database Audit Backup\n-- Date: " . date('Y-m-d H:i:s') . "\n\n";
    foreach ($tables as $table) {
        $result = $conn->query("SELECT * FROM `$table`");
        $sqlScript .= "DROP TABLE IF EXISTS `$table`;\n";
        $row2 = $conn->query("SHOW CREATE TABLE `$table`")->fetch_row();
        $sqlScript .= $row2[1] . ";\n\n";
        while ($row = $result->fetch_assoc()) {
            $sqlScript .= "INSERT INTO `$table` VALUES(";
            $comma = "";
            foreach ($row as $value) {
                $sqlScript .= $comma . ($value === null ? "NULL" : "'" . $conn->real_escape_string($value) . "'");
                $comma = ",";
            }
            $sqlScript .= ");\n";
        }
        $sqlScript .= "\n\n";
    }
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="db_backup_' . date('Y-m-d_H-i') . '.sql"');
    echo $sqlScript;
    exit();
}

// POST Form Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!is_string($csrfToken) || !hash_equals($_SESSION['settings_csrf'], $csrfToken)) {
        http_response_code(400);
        $notice = 'Security session expired. Please refresh the page and try again.';
        $noticeType = 'danger';
    } else {
        $valuesToSave = [];
        foreach ($_POST as $key => $val) {
            if ($key === 'csrf_token') continue;
            if (is_array($val)) continue;
            $valuesToSave[$key] = trim((string)$val);
        }

        // Handle Checkboxes / Switches
        $switchKeys = ['maintenance_mode', 'pos_enabled', 'allow_negative_stock'];
        foreach ($switchKeys as $sw) {
            $valuesToSave[$sw] = isset($_POST[$sw]) ? '1' : '0';
        }

        if (!$formErrors) {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
                foreach ($valuesToSave as $k => $v) {
                    $stmt->bind_param('ss', $k, $v);
                    $stmt->execute();
                }
                $stmt->close();
                $conn->commit();

                $_SESSION['settings_flash'] = [
                    'type' => 'success',
                    'message' => 'All system settings and global themes updated successfully!',
                ];
                header('Location: settings.php', true, 303);
                exit();
            } catch (Throwable $e) {
                $conn->rollback();
                $notice = 'Failed to update system settings: ' . $e->getMessage();
                $noticeType = 'danger';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_SESSION['settings_flash'])) {
    $flash = $_SESSION['settings_flash'];
    unset($_SESSION['settings_flash']);
    if (is_array($flash) && isset($flash['type'], $flash['message'])) {
        $noticeType = $flash['type'] === 'success' ? 'success' : 'danger';
        $notice = $flash['message'];
    }
}

// Refresh settings array from database
$res = $conn->query("SELECT setting_key, setting_value FROM app_settings");
$appSettings = [];
while($row = $res->fetch_assoc()){
    $appSettings[$row['setting_key']] = $row['setting_value'];
}
$formValues['theme'] = $appSettings['theme'] ?? 'navy';

$pageTitle = 'Settings & Configuration Hub';
include __DIR__ . '/layouts/header.php';
?>

<div class="container-fluid px-4 py-3">
    <?php if (!empty($notice)): ?>
        <div class="alert alert-<?= $noticeType === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show shadow-sm mb-4" role="alert">
            <i class="bi bi-<?= $noticeType === 'success' ? 'check-circle' : 'exclamation-triangle' ?>-fill me-2"></i>
            <?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Header & Action Bar -->
    <div class="card settings-header-card text-white p-4 shadow-sm mb-4 rounded-4" style="background: var(--sidebar-bg); border-left: 5px solid var(--primary-color);">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h1 class="h3 mb-1 fw-bold text-white"><i class="bi bi-gear-wide-connected text-warning me-2"></i> Admin Settings & Configuration Hub</h1>
                <p class="mb-0 text-light opacity-75 small">Manage global themes, store info, POS counter, security policies, and backups.</p>
            </div>
            <div>
                <button type="submit" form="masterSettingsForm" class="btn btn-warning rounded-pill px-4 fw-bold shadow-sm">
                    <i class="bi bi-floppy-fill me-1"></i> Save All Settings
                </button>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-pills mb-4 gap-2 bg-white p-2 rounded-3 shadow-sm border" id="settingsTab" role="tablist">
        <li class="nav-item"><button class="nav-link active fw-bold" id="general-tab" data-bs-toggle="tab" data-bs-target="#general-pane" type="button"><i class="bi bi-palette me-1"></i> General & Theme</button></li>
        <li class="nav-item"><button class="nav-link fw-bold" id="store-tab" data-bs-toggle="tab" data-bs-target="#store-pane" type="button"><i class="bi bi-shop me-1"></i> Store Info</button></li>
        <li class="nav-item"><button class="nav-link fw-bold" id="pos-tab" data-bs-toggle="tab" data-bs-target="#pos-pane" type="button"><i class="bi bi-calculator me-1"></i> POS Setup</button></li>
        <li class="nav-item"><button class="nav-link fw-bold" id="security-tab" data-bs-toggle="tab" data-bs-target="#security-pane" type="button"><i class="bi bi-shield-lock me-1"></i> Security</button></li>
        <li class="nav-item"><button class="nav-link fw-bold" id="email-tab" data-bs-toggle="tab" data-bs-target="#email-pane" type="button"><i class="bi bi-envelope-check me-1"></i> SMTP</button></li>
        <li class="nav-item"><button class="nav-link fw-bold" id="invoice-tab" data-bs-toggle="tab" data-bs-target="#invoice-pane" type="button"><i class="bi bi-receipt-cutoff me-1"></i> Invoices</button></li>
        <li class="nav-item"><button class="nav-link fw-bold" id="inventory-tab" data-bs-toggle="tab" data-bs-target="#inventory-pane" type="button"><i class="bi bi-boxes me-1"></i> Inventory</button></li>
        <li class="nav-item"><button class="nav-link fw-bold" id="backup-tab" data-bs-toggle="tab" data-bs-target="#backup-pane" type="button"><i class="bi bi-database-down me-1"></i> Backup</button></li>
    </ul>

    <!-- Master Form -->
    <form method="post" id="masterSettingsForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['settings_csrf'], ENT_QUOTES, 'UTF-8') ?>">

        <div class="tab-content" id="settingsTabContent">

            <!-- 1. GENERAL & GLOBAL THEME PANE -->
            <div class="tab-pane fade show active" id="general-pane" role="tabpanel">
                <div class="card p-4 bg-white mb-4 shadow-sm">
                    <h2 class="h5 fw-bold mb-3 text-dark border-bottom pb-2"><i class="bi bi-palette-fill text-warning me-2"></i> Global Theme & Appearance</h2>
                    <p class="text-muted small">Choose between the 3 official professional global themes. Selecting a theme instantly updates your entire project.</p>
                    
                    <div class="mb-4" style="max-width: 450px;">
                        <label class="form-label fw-bold">Dashboard Theme Preset</label>
                        <select class="form-select form-select-lg fw-bold" id="themeDropdownSelector" name="theme">
                            <option value="ivory" <?= $formValues['theme'] === 'ivory' ? 'selected' : '' ?>>1. Ivory Cream & White</option>
                            <option value="navy" <?= $formValues['theme'] === 'navy' ? 'selected' : '' ?>>2. Royal Navy & Ice Blue</option>
                            <option value="plum" <?= $formValues['theme'] === 'plum' ? 'selected' : '' ?>>3. Charcoal Plum & Lavender</option>
                        </select>
                    </div>

                    <!-- Visual Preview Cards -->
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="card p-3 border cursor-pointer <?= $formValues['theme'] === 'ivory' ? 'border-primary shadow-sm' : '' ?>" onclick="setThemeOption('ivory')">
                                <h6 class="fw-bold">1. Ivory Cream & White</h6>
                                <div class="d-flex gap-1 my-2">
                                    <span style="width:25px;height:20px;background:#F3F0DF;border:1px solid #ccc;display:inline-block;"></span>
                                    <span style="width:25px;height:20px;background:#FFFFFF;border:1px solid #ccc;display:inline-block;"></span>
                                    <span style="width:25px;height:20px;background:#C9A34D;display:inline-block;"></span>
                                </div>
                                <small class="text-muted">Soft ivory cream background with white elements.</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card p-3 border cursor-pointer <?= $formValues['theme'] === 'navy' ? 'border-primary shadow-sm' : '' ?>" onclick="setThemeOption('navy')">
                                <h6 class="fw-bold">2. Royal Navy & Ice Blue</h6>
                                <div class="d-flex gap-1 my-2">
                                    <span style="width:25px;height:20px;background:#14243D;display:inline-block;"></span>
                                    <span style="width:25px;height:20px;background:#EAF1FA;border:1px solid #ccc;display:inline-block;"></span>
                                    <span style="width:25px;height:20px;background:#2563EB;display:inline-block;"></span>
                                </div>
                                <small class="text-muted">Deep navy sidebar with ice-blue background.</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card p-3 border cursor-pointer <?= $formValues['theme'] === 'plum' ? 'border-primary shadow-sm' : '' ?>" onclick="setThemeOption('plum')">
                                <h6 class="fw-bold">3. Charcoal Plum & Lavender</h6>
                                <div class="d-flex gap-1 my-2">
                                    <span style="width:25px;height:20px;background:#29243A;display:inline-block;"></span>
                                    <span style="width:25px;height:20px;background:#F1EFF7;border:1px solid #ccc;display:inline-block;"></span>
                                    <span style="width:25px;height:20px;background:#9878C9;display:inline-block;"></span>
                                </div>
                                <small class="text-muted">Dark charcoal plum sidebar with lavender background.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card p-4 bg-white mb-4 shadow-sm">
                    <h2 class="h5 fw-bold mb-3 text-dark border-bottom pb-2"><i class="bi bi-sliders me-2"></i> Localization & Maintenance</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Store Currency</label>
                            <select class="form-select" name="currency">
                                <option value="PKR" <?= $formValues['currency'] === 'PKR' ? 'selected' : '' ?>>Pakistani Rupee (PKR)</option>
                                <option value="USD" <?= $formValues['currency'] === 'USD' ? 'selected' : '' ?>>US Dollar (USD)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Timezone</label>
                            <select class="form-select" name="timezone">
                                <option value="Asia/Karachi" <?= $formValues['timezone'] === 'Asia/Karachi' ? 'selected' : '' ?>>Asia/Karachi</option>
                                <option value="UTC" <?= $formValues['timezone'] === 'UTC' ? 'selected' : '' ?>>UTC</option>
                            </select>
                        </div>
                        <div class="col-12 mt-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="maintenance_mode" name="maintenance_mode" value="1" <?= $formValues['maintenance_mode'] ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold text-danger" for="maintenance_mode">Enable Storefront Maintenance Mode</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. STORE INFO PANE -->
            <div class="tab-pane fade" id="store-pane" role="tabpanel">
                <div class="card p-4 bg-white mb-4 shadow-sm">
                    <h2 class="h5 fw-bold mb-3 text-dark border-bottom pb-2"><i class="bi bi-building me-2"></i> Store & Business Information</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Store Public Name</label>
                            <input type="text" class="form-control" name="store_name" value="<?= htmlspecialchars($formValues['store_name'], ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Support Email</label>
                            <input type="email" class="form-control" name="store_email" value="<?= htmlspecialchars($formValues['store_email'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Support Phone</label>
                            <input type="text" class="form-control" name="store_phone" value="<?= htmlspecialchars($formValues['store_phone'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Website URL</label>
                            <input type="url" class="form-control" name="store_website" value="<?= htmlspecialchars($formValues['store_website'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Store Address</label>
                            <input type="text" class="form-control" name="store_address" value="<?= htmlspecialchars($formValues['store_address'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. POS SETUP PANE -->
            <div class="tab-pane fade" id="pos-pane" role="tabpanel">
                <div class="card p-4 bg-white mb-4 shadow-sm">
                    <h2 class="h5 fw-bold mb-3 text-dark border-bottom pb-2"><i class="bi bi-calculator me-2"></i> POS Configuration</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Default POS Tax Rate (%)</label>
                            <input type="number" step="0.01" class="form-control" name="pos_tax_rate" value="<?= htmlspecialchars($formValues['pos_tax_rate'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Receipt Header Note</label>
                            <input type="text" class="form-control" name="pos_receipt_header" value="<?= htmlspecialchars($formValues['pos_receipt_header'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-12 mt-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="pos_enabled" name="pos_enabled" value="1" <?= $formValues['pos_enabled'] ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold" for="pos_enabled">Enable POS Module in Sidebar</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. SECURITY PANE -->
            <div class="tab-pane fade" id="security-pane" role="tabpanel">
                <div class="card p-4 bg-white mb-4 shadow-sm">
                    <h2 class="h5 fw-bold mb-3 text-dark border-bottom pb-2"><i class="bi bi-shield-lock me-2"></i> Security Policies</h2>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Minimum Password Length</label>
                            <input type="number" class="form-control" name="min_password_length" value="<?= htmlspecialchars($formValues['min_password_length'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Session Timeout (Minutes)</label>
                            <input type="number" class="form-control" name="session_timeout" value="<?= htmlspecialchars($formValues['session_timeout'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Max Login Attempts</label>
                            <input type="number" class="form-control" name="max_login_attempts" value="<?= htmlspecialchars($formValues['max_login_attempts'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 5. EMAIL & SMTP PANE -->
            <div class="tab-pane fade" id="email-pane" role="tabpanel">
                <div class="card p-4 bg-white mb-4 shadow-sm">
                    <h2 class="h5 fw-bold mb-3 text-dark border-bottom pb-2"><i class="bi bi-envelope-check me-2"></i> SMTP Server Configuration</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">SMTP Host</label>
                            <input type="text" class="form-control" name="smtp_host" value="<?= htmlspecialchars($formValues['smtp_host'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">SMTP Port</label>
                            <input type="text" class="form-control" name="smtp_port" value="<?= htmlspecialchars($formValues['smtp_port'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">SMTP Username</label>
                            <input type="text" class="form-control" name="smtp_username" value="<?= htmlspecialchars($formValues['smtp_username'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 6. INVOICES PANE -->
            <div class="tab-pane fade" id="invoice-pane" role="tabpanel">
                <div class="card p-4 bg-white mb-4 shadow-sm">
                    <h2 class="h5 fw-bold mb-3 text-dark border-bottom pb-2"><i class="bi bi-receipt-cutoff me-2"></i> Invoice Customization</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Invoice Number Prefix</label>
                            <input type="text" class="form-control" name="invoice_prefix" value="<?= htmlspecialchars($formValues['invoice_prefix'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Starting Invoice Number</label>
                            <input type="number" class="form-control" name="invoice_start_num" value="<?= htmlspecialchars($formValues['invoice_start_num'] ?? '1001', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 7. INVENTORY PANE -->
            <div class="tab-pane fade" id="inventory-pane" role="tabpanel">
                <div class="card p-4 bg-white mb-4 shadow-sm">
                    <h2 class="h5 fw-bold mb-3 text-dark border-bottom pb-2"><i class="bi bi-boxes me-2"></i> Stock Control Thresholds</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Low Stock Warning Threshold</label>
                            <input type="number" class="form-control" name="low_stock_threshold" value="<?= htmlspecialchars($formValues['low_stock_threshold'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-12 mt-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="allow_negative_stock" name="allow_negative_stock" value="1" <?= $formValues['allow_negative_stock'] ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold" for="allow_negative_stock">Allow Negative Stock Sales in POS/Orders</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 8. BACKUP PANE -->
            <div class="tab-pane fade" id="backup-pane" role="tabpanel">
                <div class="card p-4 bg-white mb-4 shadow-sm text-center">
                    <h2 class="h5 fw-bold mb-3 text-dark border-bottom pb-2"><i class="bi bi-database-down me-2"></i> Database Audit Backup</h2>
                    <p class="text-muted">Download a complete structural and data backup of your store database in SQL format.</p>
                    <a href="settings.php?action=backup_db" class="btn btn-warning px-4 fw-bold shadow-sm mt-2">
                        <i class="bi bi-download me-1"></i> Download Database Backup (.SQL)
                    </a>
                </div>
            </div>

        </div>

        <div class="mt-4 text-end">
            <button type="submit" class="btn btn-dark rounded-pill px-4"><i class="bi bi-check-circle me-1 text-warning"></i> Save All Settings</button>
        </div>
    </form>
</div>

<script>
function setThemeOption(themeVal) {
    document.getElementById('themeDropdownSelector').value = themeVal;
}
</script>

<?php include __DIR__ . '/layouts/footer.php'; ?>