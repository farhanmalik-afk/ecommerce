<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/role-management.php';

// 1. Authorization Guard
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || !appUserCan('settings')) {
    sendAppError(403, 'You do not have permission to manage system settings.');
}

// 2. CSRF Token Initialization
if (empty($_SESSION['settings_csrf'])) {
    $_SESSION['settings_csrf'] = bin2hex(random_bytes(32));
}

// 2.1 AJAX Endpoint for Real-Time Unique Key/Value Check in Database
if (isset($_GET['ajax_check_setting']) && $_GET['ajax_check_setting'] === '1') {
    header('Content-Type: application/json');
    $settingKey = trim($_GET['key'] ?? '');
    $settingVal = trim($_GET['val'] ?? '');

    // Allowlist allowed unique settings keys to check against database
    $uniqueAllowedKeys = ['app_name', 'store_name', 'store_email', 'store_phone', 'invoice_prefix'];
    
    if (!in_array($settingKey, $uniqueAllowedKeys, true) || $settingVal === '') {
        echo json_encode(['exists' => false]);
        exit();
    }

    $stmt = $conn->prepare("SELECT setting_value FROM app_settings WHERE setting_key = ? AND setting_value = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('ss', $settingKey, $settingVal);
        if (!$stmt->execute()) {
            error_log('Unable to check a unique settings value: ' . $stmt->error);
            $stmt->close();
            sendAppError(500, 'The requested setting could not be checked.');
        }
        $stmt->store_result();
        $exists = ($stmt->num_rows > 0);
        $stmt->close();
        echo json_encode(['exists' => $exists]);
        exit();
    }
    error_log('Unable to prepare a unique settings check: ' . $conn->error);
    sendAppError(500, 'The requested setting could not be checked.');
}

// Helper Function for Batch Saving to `app_settings`
function saveAppSettingsBatch(mysqli $conn, array $pairs): bool
{
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare(
            "INSERT INTO app_settings (setting_key, setting_value) 
             VALUES (?, ?) 
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );
        if (!$stmt) {
            throw new RuntimeException($conn->error);
        }

        foreach ($pairs as $key => $value) {
            $strVal = (string) $value;
            $stmt->bind_param('ss', $key, $strVal);
            if (!$stmt->execute()) {
                throw new RuntimeException($stmt->error);
            }
        }

        $stmt->close();
        $conn->commit();
        return true;
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('Settings Save Error: ' . $e->getMessage());
        return false;
    }
}

// Settings tabs, including centralized role and module access management.
$validTabs = [
    'general', 'store', 'pos', 'security', 'smtp', 'invoices', 
    'inventory', 'backup', 'payments', 'tax', 'notifications', 'system', 'roles'
];
$activeTab = $_GET['tab'] ?? 'general';
if (!in_array($activeTab, $validTabs, true)) {
    $activeTab = 'general';
}
enforceAccess(
    $conn,
    $activeTab === 'roles' ? 'settings' : 'settings_' . $activeTab,
    $_SERVER['REQUEST_METHOD'] === 'POST' ? 'edit' : 'view'
);

$permissionCatalog = [];
handleRoleManagementRequest($conn);

// 4. Handle POST Submissions with Strict PHP Server-Side Validation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['settings_module'])) {
    $module = $_POST['settings_module'];
    $postedToken = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['settings_csrf'], $postedToken)) {
        $_SESSION['swal_error'] = 'Security validation failed (CSRF token mismatch).';
        header('Location: settings.php?tab=' . urlencode($activeTab));
        exit();
    }

    if (!in_array($module, $validTabs, true)) {
        $_SESSION['swal_error'] = 'Invalid settings module.';
        header('Location: settings.php');
        exit();
    }

    $updates = [];
    $validationError = null;

    switch ($module) {
       case 'general':
            $appName = trim($_POST['app_name'] ?? '');
            $pagination = $_POST['default_pagination'] ?? '';
            $currencySymbol = trim($_POST['currency_symbol'] ?? '');
            $selectedTheme = $_POST['theme'] ?? 'original'; // Theme catch karna
            
            if ($appName === '') {
                $validationError = 'Application Name is required.';
            } elseif (strlen($appName) > 100) {
                $validationError = 'Application Name cannot exceed 100 characters.';
            } elseif ($currencySymbol === '' || strlen($currencySymbol) > 5) {
                $validationError = 'Currency symbol is required and max 5 characters.';
            } elseif (!ctype_digit(strval($pagination)) || intval($pagination) < 5 || intval($pagination) > 100) {
                $validationError = 'Default pagination must be a positive integer between 5 and 100.';
            } else {
                $updates = [
                    'app_name'           => $appName,
                    'language'           => in_array($_POST['language'] ?? 'en', ['en', 'ur']) ? $_POST['language'] : 'en',
                    'default_language'   => in_array($_POST['default_language'] ?? 'en', ['en', 'ur']) ? $_POST['default_language'] : 'en',
                    'date_format'        => in_array($_POST['date_format'] ?? 'd M Y', ['d M Y', 'Y-m-d', 'd/m/Y']) ? $_POST['date_format'] : 'd M Y',
                    'time_format'        => in_array($_POST['time_format'] ?? '24h', ['24h', '12h']) ? $_POST['time_format'] : '24h',
                    'timezone'           => in_array($_POST['timezone'] ?? 'Asia/Karachi', ['Asia/Karachi', 'UTC']) ? $_POST['timezone'] : 'Asia/Karachi',
                    'currency'           => in_array($_POST['currency'] ?? 'PKR', ['PKR', 'USD', 'EUR']) ? $_POST['currency'] : 'PKR',
                    'currency_symbol'    => $currencySymbol,
                    'number_formatting'  => trim($_POST['number_formatting'] ?? 'standard'),
                    'default_pagination' => intval($pagination),
                    // YAHAN THEME SAVE HONE KE LIYE ADD KAREIN:
                    'theme'              => in_array($selectedTheme, ['original', 'navy-gold', 'modern-blue', 'emerald-green', 'purple-style'], true) ? $selectedTheme : 'original',
                ];
            }
            break;
        case 'store':
            $storeName = trim($_POST['store_name'] ?? '');
            $storeEmail = trim($_POST['store_email'] ?? '');
            $storePhone = trim($_POST['store_phone'] ?? '');
            $websiteUrl = trim($_POST['website_url'] ?? '');
            $storeHours = trim($_POST['store_hours'] ?? '');
            $storeAddress = trim($_POST['store_address'] ?? '');
            $storeDesc = trim($_POST['store_description'] ?? '');

            if ($storeName === '') {
                $validationError = 'Store Name is required.';
            } elseif (strlen($storeName) > 150) {
                $validationError = 'Store Name cannot exceed 150 characters.';
            } elseif ($storeEmail !== '' && !filter_var($storeEmail, FILTER_VALIDATE_EMAIL)) {
                $validationError = 'Please enter a valid store support email address.';
            } elseif ($storePhone !== '' && !preg_match('/^\+?[0-9\s]{7,15}$/', $storePhone)) {
                $validationError = 'Invalid phone number format. Only numbers and an optional leading plus sign are allowed (e.g. +923001234567).';
            } elseif ($websiteUrl !== '' && !filter_var($websiteUrl, FILTER_VALIDATE_URL)) {
                $validationError = 'Please enter a valid website URL starting with http:// or https://.';
            } elseif (strlen($storeHours) > 100) {
                $validationError = 'Store hours cannot exceed 100 characters.';
            } elseif (strlen($storeAddress) > 255) {
                $validationError = 'Store address cannot exceed 255 characters.';
            } elseif (strlen($storeDesc) > 500) {
                $validationError = 'Business description cannot exceed 500 characters.';
            } else {
                $_SESSION['store_name'] = $storeName;
                $_SESSION['app_name']   = $storeName;

                $updates = [
                    'store_name'        => $storeName,
                    'app_name'          => $storeName,
                    'store_email'       => $storeEmail,
                    'store_phone'       => $storePhone,
                    'store_address'     => $storeAddress,
                    'store_description' => $storeDesc,
                    'website_url'       => $websiteUrl,
                    'store_hours'       => $storeHours,
                    'store_status'      => isset($_POST['store_status']) ? '1' : '0',
                ];

                if (!empty($_FILES['store_logo']['name']) && $_FILES['store_logo']['error'] === UPLOAD_ERR_OK) {
                    $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($_FILES['store_logo']['tmp_name']);

                    if (in_array($mime, $allowedMime, true) && $_FILES['store_logo']['size'] <= (2 * 1024 * 1024)) {
                        $uploadDir = __DIR__ . '/uploads/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }
                        $ext = $mime === 'image/png' ? 'png' : ($mime === 'image/webp' ? 'webp' : 'jpg');
                        $filename = 'store_logo_' . time() . '.' . $ext;
                        if (move_uploaded_file($_FILES['store_logo']['tmp_name'], $uploadDir . $filename)) {
                            $updates['store_logo'] = $filename;
                            $_SESSION['store_logo'] = $filename;
                        }
                    } else {
                        $validationError = 'Invalid image file type or file size exceeds 2MB limit.';
                    }
                }
            }
            break;

       case 'pos':
            $discount = $_POST['pos_default_discount'] ?? '';
            if (!is_numeric($discount) || floatval($discount) < 0 || floatval($discount) > 100) {
                $validationError = 'POS default discount must be a positive number between 0 and 100.';
            } else {
                $updates = [
                    'pos_module_enabled'  => isset($_POST['pos_module_enabled']) ? '1' : '0',
                    'pos_layout'          => in_array($_POST['pos_layout'] ?? 'grid', ['grid', 'list']) ? $_POST['pos_layout'] : 'grid',
                    'pos_default_payment' => in_array($_POST['pos_default_payment'] ?? 'cash', ['cash', 'card']) ? $_POST['pos_default_payment'] : 'cash',
                    'pos_cash_enabled'    => isset($_POST['pos_cash_enabled']) ? '1' : '0',
                    'pos_card_enabled'    => isset($_POST['pos_card_enabled']) ? '1' : '0',
                    'pos_cust_select'     => isset($_POST['pos_cust_select']) ? '1' : '0',
                    'pos_barcode'         => isset($_POST['pos_barcode']) ? '1' : '0',
                    'pos_search'          => isset($_POST['pos_search']) ? '1' : '0',
                    'pos_default_discount'=> number_format(floatval($discount), 2, '.', ''),
                    'receipt_format'      => in_array($_POST['receipt_format'] ?? '80mm', ['80mm', '58mm', 'a4']) ? $_POST['receipt_format'] : '80mm',
                ];
            }
            break;

        case 'security':
            $minPass = $_POST['min_password_length'] ?? '';
            $timeout = $_POST['session_timeout_minutes'] ?? '';
            $maxAttempts = $_POST['max_login_attempts'] ?? '';

            if (!ctype_digit(strval($minPass)) || intval($minPass) < 6 || intval($minPass) > 32) {
                $validationError = 'Minimum password length must be a positive integer between 6 and 32.';
            } elseif (!ctype_digit(strval($timeout)) || intval($timeout) < 5 || intval($timeout) > 1440) {
                $validationError = 'Session timeout must be a positive integer between 5 and 1440 minutes.';
            } elseif (!ctype_digit(strval($maxAttempts)) || intval($maxAttempts) < 3 || intval($maxAttempts) > 20) {
                $validationError = 'Max login attempts must be a positive integer between 3 and 20.';
            } else {
                $updates = [
                    'min_password_length'     => intval($minPass),
                    'session_timeout_minutes' => intval($timeout),
                    'max_login_attempts'      => intval($maxAttempts),
                    'enforce_strong_password' => isset($_POST['enforce_strong_password']) ? '1' : '0',
                    'log_admin_activity'      => isset($_POST['log_admin_activity']) ? '1' : '0',
                ];
            }
            break;

        case 'smtp':
            $smtpHost = trim($_POST['smtp_host'] ?? '');
            $smtpPort = $_POST['smtp_port'] ?? '';
            $smtpUser = trim($_POST['smtp_username'] ?? '');
            $smtpPass = trim($_POST['smtp_password'] ?? '');
            if ($smtpPass === '') {
                $smtpPass = $appSettings['smtp_password'] ?? '';
            }
            $senderName = trim($_POST['smtp_sender_name'] ?? '');
            $senderEmail = trim($_POST['smtp_from_email'] ?? '');

            if (strlen($smtpHost) > 150) {
                $validationError = 'SMTP host cannot exceed 150 characters.';
            } elseif (!ctype_digit(strval($smtpPort)) || intval($smtpPort) < 1 || intval($smtpPort) > 65535) {
                $validationError = 'SMTP Port must be a positive integer between 1 and 65535.';
            } elseif (strlen($smtpUser) > 100) {
                $validationError = 'SMTP username cannot exceed 100 characters.';
            } elseif (strlen($smtpPass) > 150) {
                $validationError = 'SMTP password cannot exceed 150 characters.';
            } elseif (strlen($senderName) > 100) {
                $validationError = 'Sender name cannot exceed 100 characters.';
            } elseif ($senderEmail !== '' && !filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
                $validationError = 'Please enter a valid sender email address.';
            } else {
                $updates = [
                    'smtp_host'       => $smtpHost,
                    'smtp_port'       => intval($smtpPort),
                    'smtp_username'   => $smtpUser,
                    'smtp_password'   => $smtpPass,
                    'smtp_encryption' => in_array($_POST['smtp_encryption'] ?? 'tls', ['tls', 'ssl', 'none']) ? $_POST['smtp_encryption'] : 'tls',
                    'smtp_sender_name'=> $senderName,
                    'smtp_from_email' => $senderEmail,
                    'smtp_enabled'    => isset($_POST['smtp_enabled']) ? '1' : '0',
                ];
                
                if (isset($_POST['send_test_email']) && $_POST['send_test_email'] === '1') {
                    $testRecipient = trim($_POST['test_email_recipient'] ?? '');
                    if (filter_var($testRecipient, FILTER_VALIDATE_EMAIL)) {
                        $headers = 'From: ' . ($senderName ?: 'Store') . ' <' . ($senderEmail ?: 'no-reply@store.com') . ">\r\n";
                        $headers .= "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n";
                        
                        $sent = @mail(
                            $testRecipient,
                            'Test Email from ' . ($senderName ?: 'Store'),
                            'This is a test email dispatched from your E-commerce Admin Dashboard SMTP configuration.',
                            $headers
                        );
                        if ($sent) {
                            $_SESSION['swal_success'] = 'Test email successfully sent to ' . htmlspecialchars($testRecipient) . '!';
                        } else {
                            $_SESSION['swal_error'] = 'Failed to send test email. Please verify your mail server configuration.';
                            header('Location: settings.php?tab=smtp');
                            exit();
                        }
                    } else {
                        $_SESSION['swal_error'] = 'Please provide a valid recipient email address for testing.';
                        header('Location: settings.php?tab=smtp');
                        exit();
                    }
                }
            }
            break;

        case 'invoices':
            $invoicePrefix = trim($_POST['invoice_prefix'] ?? '');
            $invoiceHeader = trim($_POST['invoice_header'] ?? '');
            $invoiceFooter = trim($_POST['invoice_footer'] ?? '');

            if ($invoicePrefix === '') {
                $validationError = 'Invoice prefix is required.';
            } elseif (strlen($invoicePrefix) > 20) {
                $validationError = 'Invoice prefix cannot exceed 20 characters.';
            } elseif (strlen($invoiceHeader) > 255) {
                $validationError = 'Invoice header note cannot exceed 255 characters.';
            } elseif (strlen($invoiceFooter) > 255) {
                $validationError = 'Invoice footer note cannot exceed 255 characters.';
            } else {
                $updates = [
                    'invoice_prefix'     => $invoicePrefix,
                    'invoice_number_fmt' => in_array($_POST['invoice_number_fmt'] ?? 'sequential', ['sequential', 'timestamp']) ? $_POST['invoice_number_fmt'] : 'sequential',
                    'invoice_header'     => $invoiceHeader,
                    'invoice_footer'     => $invoiceFooter,
                    'invoice_tax_display'=> isset($_POST['invoice_tax_display']) ? '1' : '0',
                    'invoice_disc_display'=> isset($_POST['invoice_disc_display']) ? '1' : '0',
                ];
            }
            break;

        case 'inventory':
            $threshold = $_POST['low_stock_threshold'] ?? '';
            if (!ctype_digit(strval($threshold)) || intval($threshold) < 1) {
                $validationError = 'Low stock threshold units must be a positive integer of at least 1.';
            } else {
                $updates = [
                    'low_stock_threshold'  => intval($threshold),
                    'out_of_stock_alerts'  => isset($_POST['out_of_stock_alerts']) ? '1' : '0',
                    'allow_negative_stock' => isset($_POST['allow_negative_stock']) ? '1' : '0',
                    'stock_tracking'       => isset($_POST['stock_tracking']) ? '1' : '0',
                ];
            }
            break;

        case 'backup':
            if (isset($_POST['trigger_backup']) && $_POST['trigger_backup'] === '1') {
                $backupDir = __DIR__ . '/backups/';
                if (!is_dir($backupDir)) {
                    mkdir($backupDir, 0755, true);
                }
                $backupFilename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
                $backupPath = $backupDir . $backupFilename;
                
                $sqlDump = "-- E-commerce Admin Database Backup Snapshot\n-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
                $tablesRes = $conn->query("SHOW TABLES");
                if ($tablesRes) {
                    while ($row = $tablesRes->fetch_row()) {
                        $table = $row[0];
                        $createRes = $conn->query("SHOW CREATE TABLE `$table`");
                        if ($createRes && $createRow = $createRes->fetch_assoc()) {
                            $sqlDump .= "\n\n" . $createRow['Create Table'] . ";\n\n";
                        }
                        $dataRes = $conn->query("SELECT * FROM `$table`");
                        if ($dataRes && $dataRes->num_rows > 0) {
                            while ($dataRow = $dataRes->fetch_assoc()) {
                                $cols = array_map([$conn, 'real_escape_string'], array_keys($dataRow));
                                $vals = array_map(function($v) use ($conn) {
                                    return $v === null ? 'NULL' : "'" . $conn->real_escape_string($v) . "'";
                                }, array_values($dataRow));
                                $sqlDump .= "INSERT INTO `$table` (`" . implode('`, `', $cols) . "`) VALUES (" . implode(', ', $vals) . ");\n";
                            }
                        }
                    }
                }
                file_put_contents($backupPath, $sqlDump);
                $_SESSION['swal_success'] = 'Database backup generated successfully (' . $backupFilename . ').';
                header('Location: settings.php?tab=backup');
                exit();
            }
            $updates = [
                'backup_frequency' => in_array($_POST['backup_frequency'] ?? 'weekly', ['daily', 'weekly', 'monthly', 'off']) ? $_POST['backup_frequency'] : 'weekly',
            ];
            break;

        case 'payments':
            $pubKey = trim($_POST['stripe_publishable_key'] ?? '');
            $secKey = trim($_POST['stripe_secret_key'] ?? '');
            if ($secKey === '') {
                $secKey = $appSettings['stripe_secret_key'] ?? '';
            }
            $webSecret = trim($_POST['stripe_webhook_secret'] ?? '');
            if ($webSecret === '') {
                $webSecret = $appSettings['stripe_webhook_secret'] ?? '';
            }
            $payInstructions = trim($_POST['payment_instructions'] ?? '');

            if (strlen($pubKey) > 150) {
                $validationError = 'Stripe publishable key cannot exceed 150 characters.';
            } elseif (strlen($secKey) > 150) {
                $validationError = 'Stripe secret key cannot exceed 150 characters.';
            } elseif (strlen($webSecret) > 150) {
                $validationError = 'Stripe webhook secret cannot exceed 150 characters.';
            } elseif (strlen($payInstructions) > 500) {
                $validationError = 'Payment instructions cannot exceed 500 characters.';
            } else {
                $updates = [
                    'payment_cod_enabled'    => isset($_POST['payment_cod_enabled']) ? '1' : '0',
                    'payment_bank_enabled'   => isset($_POST['payment_bank_enabled']) ? '1' : '0',
                    'payment_stripe_enabled' => isset($_POST['payment_stripe_enabled']) ? '1' : '0',
                    'stripe_publishable_key' => $pubKey,
                    'stripe_secret_key'      => $secKey,
                    'stripe_webhook_secret'  => $webSecret,
                    'stripe_mode'            => in_array($_POST['stripe_mode'] ?? 'test', ['test', 'live']) ? $_POST['stripe_mode'] : 'test',
                    'payment_instructions'   => $payInstructions,
                ];
            }
            break;

        case 'tax':
            $taxRate = $_POST['pos_tax_rate'] ?? '';
            $taxLabel = trim($_POST['tax_label'] ?? '');

            if (!is_numeric($taxRate) || floatval($taxRate) < 0 || floatval($taxRate) > 100) {
                $validationError = 'Tax rate must be a valid number between 0 and 100.';
            } elseif ($taxLabel === '') {
                $validationError = 'Tax label/name is required.';
            } elseif (strlen($taxLabel) > 50) {
                $validationError = 'Tax label cannot exceed 50 characters.';
            } else {
                $updates = [
                    'tax_enabled'          => isset($_POST['tax_enabled']) ? '1' : '0',
                    'pos_tax_rate'         => number_format(floatval($taxRate), 2, '.', ''),
                    'tax_label'            => $taxLabel,
                    'tax_calculation_type' => in_array($_POST['tax_calculation_type'] ?? 'exclusive', ['exclusive', 'inclusive']) ? $_POST['tax_calculation_type'] : 'exclusive',
                ];
            }
            break;

        case 'notifications':
            $updates = [
                'notify_new_order'     => isset($_POST['notify_new_order']) ? '1' : '0',
                'notify_order_status'  => isset($_POST['notify_order_status']) ? '1' : '0',
                'notify_low_stock'     => isset($_POST['notify_low_stock']) ? '1' : '0',
                'notify_out_of_stock'  => isset($_POST['notify_out_of_stock']) ? '1' : '0',
                'notify_new_customer'  => isset($_POST['notify_new_customer']) ? '1' : '0',
                'notify_payment_conf'  => isset($_POST['notify_payment_conf']) ? '1' : '0',
                'admin_email_alerts'   => isset($_POST['admin_email_alerts']) ? '1' : '0',
            ];
            break;

        case 'system':
            $updates = [
                'maintenance_mode' => isset($_POST['maintenance_mode']) ? '1' : '0',
            ];
            break;
    }

    if ($validationError !== null) {
        $_SESSION['swal_error'] = $validationError;
    } elseif (empty($_SESSION['swal_error']) && saveAppSettingsBatch($conn, $updates)) {
        $_SESSION['swal_success'] = ucfirst($module) . ' settings saved successfully.';
    } elseif (empty($_SESSION['swal_error'])) {
        $_SESSION['swal_error'] = 'Database error occurred while saving settings.';
    }

    header('Location: settings.php?tab=' . urlencode($module));
    exit();
}

// 5. Load Settings from Database
$appSettings = [];
$res = $conn->query("SELECT setting_key, setting_value FROM app_settings");
if (!$res) {
    throw new RuntimeException('Unable to load settings for the dashboard: ' . $conn->error);
}
while ($row = $res->fetch_assoc()) {
    $appSettings[$row['setting_key']] = $row['setting_value'];
}

$pageTitle = 'System Settings Center';
include __DIR__ . '/layouts/header.php';
?>
<style>
    .settings-container { padding-top: 1rem; padding-bottom: 2.5rem; }

    #mobileSettingsCategory {
        background-color: var(--surface, #ffffff);
        border-color: var(--border, #e8e1d5);
        color: var(--body-text, var(--dark, #14243d));
        accent-color: var(--scrollbar-thumb, var(--gold, #c6a15b));
    }

    #mobileSettingsCategory:focus {
        border-color: var(--scrollbar-thumb, var(--gold, #c6a15b));
        box-shadow: 0 0 0 0.2rem var(--input-focus-shadow, rgba(20, 36, 61, 0.12));
    }

    #mobileSettingsCategory option {
        background-color: var(--scrollbar-track, var(--surface, #ffffff));
        color: var(--body-text, var(--dark, #14243d));
    }

    #mobileSettingsCategory option:checked {
        background-color: var(--scrollbar-thumb, var(--gold, #c6a15b)) !important;
        color: #ffffff !important;
    }

    #mobileSettingsCategory option:hover,
    #mobileSettingsCategory option:focus,
    #mobileSettingsCategory option:active {
        background-color: var(--scrollbar-thumb, var(--gold, #c6a15b)) !important;
        color: #ffffff !important;
    }

    .settings-container .table-responsive {
        scrollbar-color: var(--scrollbar-thumb, var(--gold, #c6a15b)) var(--scrollbar-track, rgba(20, 36, 61, 0.06));
        scrollbar-width: thin;
    }

    .settings-container .table-responsive::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }

    .settings-container .table-responsive::-webkit-scrollbar-thumb {
        background-color: var(--scrollbar-thumb, var(--gold, #c6a15b));
        border: 2px solid transparent;
        border-radius: 999px;
        background-clip: padding-box;
    }

    .settings-container .table-responsive::-webkit-scrollbar-track {
        background-color: var(--scrollbar-track, rgba(20, 36, 61, 0.06));
        border-radius: 999px;
    }
    
    /* Row ko stretch karne ke liye taake dono columns barabar height lein */
    .settings-row { display: flex; flex-wrap: wrap; align-items: stretch; }

    .settings-sidebar-col { display: flex; flex-direction: column; }
    .settings-sidebar-card { 
        background: #ffffff; 
        border: 1px solid var(--border);
        border-radius: 12px; 
        box-shadow: 0 4px 18px rgba(20, 36, 61, 0.05); 
        height: 100%; 
        display: flex; 
        flex-direction: column; 
        padding: 0.75rem;
    }
    
    .settings-nav-pills { display: flex; flex-direction: column; gap: 0.25rem; }
    .settings-nav-pills .nav-link { display: flex; align-items: center; gap: 0.75rem; padding: 0.65rem 0.95rem; color: var(--dark); font-weight: 500; font-size: 0.9rem; border-radius: 8px; transition: all 0.2s ease; }
    .settings-nav-pills .nav-link i { font-size: 1.05rem; color: #64748b; transition: color 0.2s ease; }
    .settings-nav-pills .nav-link:hover { background-color: var(--bg); color: var(--dark); }
    .settings-nav-pills .nav-link:hover i { color: var(--gold); }
    .settings-nav-pills .nav-link.active, .settings-nav-pills .nav-link.active:hover { background-color: var(--dark) !important; color: #ffffff !important; font-weight: 600; box-shadow: var(--shadow); }
    .settings-nav-pills .nav-link.active i, .settings-nav-pills .nav-link.active:hover i { color: var(--gold) !important; }
    
    .settings-content-col { display: flex; flex-direction: column; }
    .settings-pane { display: flex; flex-direction: column; height: 100%; }
    
   .settings-content-card {
        background: #ffffff; 
        border: 1px solid var(--border);
        border-radius: 12px; 
        box-shadow: 0 4px 18px rgba(20, 36, 61, 0.05); 
        overflow: hidden; 
        display: flex; 
        flex-direction: column; 
        height: 100%; 
        min-height: 600px; 
    }
    
    #pane-roles {
        height: auto;
        min-height: 0;
    }

    #pane-roles .settings-content-card {
        height: auto;
        min-height: 0;
        overflow: visible;
    }

    #pane-roles .settings-card-body {
        flex: 0 0 auto;
        overflow: visible;
    }

    .settings-card-header { padding: 1.25rem 1.75rem; border-bottom: 1px solid var(--border); background: var(--bg); flex-shrink: 0; }
    .settings-card-header h2 { font-family: 'Playfair Display', Georgia, serif; font-size: 1.35rem; font-weight: 700; color: var(--dark); margin: 0 0 0.25rem 0; }
    .settings-card-header p { font-size: 0.88rem; color: #64748b; margin: 0; }
    
    .settings-card-body { padding: 1.75rem; flex: 1 1 auto; overflow-y: auto; }
    
    .settings-card-footer { padding: 1.15rem 1.75rem; background: var(--bg); border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; flex-shrink: 0; margin-top: auto; }

    @media (min-width: 992px) {
        .settings-row {
            height: calc(100vh - 17rem);
            min-height: 24rem;
            overflow: hidden;
        }

        .settings-sidebar-col,
        .settings-content-col {
            height: 100%;
            min-height: 0;
        }

        .settings-sidebar-card {
            min-height: 0;
            overflow: hidden;
        }

        .settings-nav-pills {
            flex: 1 1 auto;
            flex-wrap: nowrap;
            min-height: 0;
            overflow-x: hidden;
            overflow-y: auto;
            overscroll-behavior: contain;
            scroll-behavior: smooth;
            scrollbar-gutter: stable;
            scrollbar-width: thin;
            scrollbar-color: var(--scrollbar-thumb, var(--gold, #c6a15b)) var(--scrollbar-track, rgba(20, 36, 61, 0.06));
            padding-inline-end: 0.35rem;
            padding-block-end: 0.75rem;
        }

        .settings-nav-pills .nav-link {
            flex: 0 0 auto;
        }

        .settings-content-col {
            display: block;
            min-width: 0;
            overflow-x: hidden;
            overflow-y: auto;
            overscroll-behavior: contain;
            padding-block: 0.25rem 0.75rem;
            scroll-behavior: smooth;
            scrollbar-gutter: stable;
            scrollbar-width: thin;
            scrollbar-color: var(--scrollbar-thumb, var(--gold, #c6a15b)) var(--scrollbar-track, rgba(20, 36, 61, 0.06));
            padding-inline-end: 0.5rem;
        }

        .settings-content-col::-webkit-scrollbar,
        .settings-nav-pills::-webkit-scrollbar {
            width: 8px;
        }

        .settings-content-col::-webkit-scrollbar-thumb,
        .settings-nav-pills::-webkit-scrollbar-thumb {
            background-color: var(--scrollbar-thumb, var(--gold, #c6a15b));
            border-radius: 999px;
            border: 2px solid transparent;
            background-clip: padding-box;
        }

        .settings-content-col::-webkit-scrollbar-track,
        .settings-nav-pills::-webkit-scrollbar-track {
            background-color: var(--scrollbar-track, rgba(20, 36, 61, 0.06));
            border-radius: 999px;
            margin-block: 0.3rem;
        }

        .settings-content-col::-webkit-scrollbar-button,
        .settings-nav-pills::-webkit-scrollbar-button {
            display: none !important;
            width: 0 !important;
            height: 0 !important;
            min-width: 0 !important;
            min-height: 0 !important;
        }

        .settings-content-col::-webkit-scrollbar-button:vertical:start:decrement,
        .settings-content-col::-webkit-scrollbar-button:vertical:end:increment,
        .settings-nav-pills::-webkit-scrollbar-button:vertical:start:decrement,
        .settings-nav-pills::-webkit-scrollbar-button:vertical:end:increment {
            display: none !important;
            width: 0 !important;
            height: 0 !important;
        }

        .settings-content-col::-webkit-scrollbar-thumb:hover,
        .settings-nav-pills::-webkit-scrollbar-thumb:hover {
            background-color: var(--scrollbar-thumb, var(--gold, #dfbd68));
        }

        @media (prefers-reduced-motion: reduce) {
            .settings-content-col,
            .settings-nav-pills {
                scroll-behavior: auto;
            }
        }

        .settings-content-col .settings-pane {
            height: auto;
            min-height: 100%;
        }

        .settings-content-col .settings-content-card {
            height: auto;
            min-height: 0;
        }

        .settings-content-col .settings-card-body {
            overflow: visible;
        }
    }

    @media (max-width: 991.98px) {
        .settings-row {
            height: auto;
            min-height: 0;
            overflow: visible;
        }

        .settings-sidebar-card,
        .settings-content-col {
            height: auto;
            min-height: 0;
        }

        .settings-content-col {
            overflow: visible;
            padding-block: 0;
        }

        .settings-content-col .settings-pane,
        .settings-content-col .settings-content-card {
            height: auto;
            min-height: 0;
        }

        .settings-content-col .settings-card-body {
            overflow: visible;
        }
    }
    
    .btn-settings-save { background-color: var(--dark); border-color: var(--dark); color: #ffffff; font-weight: 600; padding: 0.55rem 1.5rem; border-radius: 8px; transition: all 0.2s ease; }
    .btn-settings-save:hover { background-color: var(--gold); border-color: var(--gold); color: var(--dark); }
    
    .is-invalid-custom { border-color: #dc3545 !important; padding-right: calc(1.5em + 0.75rem); background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e"); background-repeat: no-repeat; background-position: right calc(0.375em + 0.1875rem) center; background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem); }
    .invalid-feedback-custom { display: none; width: 100%; margin-top: 0.25rem; font-size: 0.875em; color: #dc3545; }
    .input-group .btn-outline-secondary { border-color: #ced4da; color: #64748b; }
    .input-group .btn-outline-secondary:hover { background-color: var(--bg); color: var(--dark); border-color: var(--border); }
    .form-check-input:not(:checked) { background-color: #dee2e6; border-color: #ced4da; }
</style>

<div class="container-fluid settings-container">
    <section class="page-head mb-4">
        <h1 class="fw-bold text-dark mb-1" style="font-family: 'Playfair Display', Georgia, serif; font-size: 1.75rem;">System Settings Center</h1>
        <p class="text-muted mb-0">Manage all 12 core administrative and store configuration modules with strict real-time backend database checks.</p>
    </section>

    <?php
    $settingsNavigation = [
        'general' => ['General', 'bi-sliders2'],
        'store' => ['Store', 'bi-shop'],
        'pos' => ['POS', 'bi-receipt-cutoff'],
        'security' => ['Security', 'bi-shield-lock'],
        'smtp' => ['SMTP', 'bi-envelope-at'],
        'invoices' => ['Invoices', 'bi-file-earmark-text'],
        'inventory' => ['Inventory', 'bi-boxes'],
        'backup' => ['Backup', 'bi-cloud-arrow-down'],
        'payments' => ['Payments', 'bi-credit-card-2-front'],
        'tax' => ['Tax', 'bi-percent'],
        'notifications' => ['Notifications', 'bi-bell'],
        'system' => ['System & Maintenance', 'bi-cpu'],
        'roles' => ['Roles & Permissions', 'bi-shield-check'],
    ];
    $canViewSettingsTab = static function (string $tab): bool {
        static $access = [];
        if (!array_key_exists($tab, $access)) {
            $access[$tab] = $tab === 'roles'
                ? (isSuperAdmin() && appUserCan('settings', 'manage'))
                : appUserCan('settings_' . $tab);
        }
        return $access[$tab];
    };
    $visibleSettingsTabs = [];
    foreach ($settingsNavigation as $tab => $item) {
        if ($canViewSettingsTab($tab)) {
            $visibleSettingsTabs[$tab] = $item;
        }
    }
    ?>

    <!-- Mobile Category Dropdown Selector -->
    <?php if ($visibleSettingsTabs): ?>
    <div class="d-lg-none mb-3">
        <label for="mobileSettingsCategory" class="form-label fw-semibold small text-muted">Select Module</label>
        <select id="mobileSettingsCategory" class="form-select fw-semibold" onchange="switchSettingsTab(this.value)">
            <?php foreach ($visibleSettingsTabs as $tab => [$label, $icon]): ?>
                <option value="<?= htmlspecialchars($tab, ENT_QUOTES, 'UTF-8') ?>" <?= $activeTab === $tab ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>

    <?php if ($visibleSettingsTabs) { ?>
    <div class="row g-4 settings-row">
        <!-- LEFT SIDE: Sidebar Navigation -->
        <div class="col-lg-3 d-none d-lg-block settings-sidebar-col">
            <div class="settings-sidebar-card p-2">
                <div class="nav flex-column nav-pills settings-nav-pills gap-1" role="tablist">
                    <?php foreach ($visibleSettingsTabs as $tab => [$label, $icon]): ?>
                        <a href="?tab=<?= htmlspecialchars($tab, ENT_QUOTES, 'UTF-8') ?>" data-tab-link="<?= htmlspecialchars($tab, ENT_QUOTES, 'UTF-8') ?>" onclick="event.preventDefault(); switchSettingsTab(<?= json_encode($tab) ?>);" class="nav-link <?= $activeTab === $tab ? 'active' : '' ?>"><i class="bi <?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>"></i> <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- RIGHT SIDE: Content Panels -->
        <div class="col-lg-9 settings-content-col">

            <!-- 1. GENERAL MODULE -->
            <?php if ($canViewSettingsTab('general')): ?><div class="settings-pane <?= $activeTab === 'general' ? '' : 'd-none' ?>" id="pane-general">
                <form method="POST" action="settings.php?tab=general" class="settings-content-card" data-module="general">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['settings_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="settings_module" value="general">
                    <div class="settings-card-header">
                        <h2>General Settings</h2>
                        <p>Application name, language dictionaries, regional formats, and pagination preferences.</p>
                    </div>
                    <div class="settings-card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Application Name <span class="text-danger">*</span></label>
                                <input type="text" name="app_name" data-check-unique="app_name" class="form-control" required maxlength="100" value="<?= htmlspecialchars($appSettings['app_name'] ?? 'Ecommerce Store', ENT_QUOTES, 'UTF-8') ?>">
                                <div class="invalid-feedback-custom">Application Name is required (max 100 chars).</div>
                            </div>
                            <div class="col-md-6">
    <label class="form-label fw-semibold">Global Dashboard Theme Preset</label>
    <select name="theme" class="form-select">
        <option value="original" <?= (($appSettings['theme'] ?? 'original') === 'original') ? 'selected' : '' ?>>1. Original Theme (Navy Blue + Gold + Cream)</option>
        <option value="navy-gold" <?= (($appSettings['theme'] ?? '') === 'navy-gold') ? 'selected' : '' ?>>2. Navy Gold (Premium Luxury)</option>
        <option value="modern-blue" <?= (($appSettings['theme'] ?? '') === 'modern-blue') ? 'selected' : '' ?>>3. Modern Blue (Corporate Professional)</option>
        <option value="emerald-green" <?= (($appSettings['theme'] ?? '') === 'emerald-green') ? 'selected' : '' ?>>4. Emerald Green (Elegant Business)</option>
        <option value="purple-style" <?= (($appSettings['theme'] ?? '') === 'purple-style') ? 'selected' : '' ?>>5. Purple Style (Modern Premium)</option>
    </select>
</div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Interface Language</label>
                                <select name="language" class="form-select">
                                    <option value="en" <?= (($appSettings['language'] ?? 'en') === 'en') ? 'selected' : '' ?>>English (EN)</option>
                                    <option value="ur" <?= (($appSettings['language'] ?? 'en') === 'ur') ? 'selected' : '' ?>>Urdu — اردو (UR)</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Default Language Fallback</label>
                                <select name="default_language" class="form-select">
                                    <option value="en" <?= (($appSettings['default_language'] ?? 'en') === 'en') ? 'selected' : '' ?>>English</option>
                                    <option value="ur" <?= (($appSettings['default_language'] ?? '') === 'ur') ? 'selected' : '' ?>>Urdu</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Currency Symbol <span class="text-danger">*</span></label>
                                <input type="text" name="currency_symbol" class="form-control" required maxlength="5" value="<?= htmlspecialchars($appSettings['currency_symbol'] ?? '₨', ENT_QUOTES, 'UTF-8') ?>">
                                <div class="invalid-feedback-custom">Currency symbol is required (max 5 chars).</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Date Format</label>
                                <select name="date_format" class="form-select">
                                    <option value="d M Y" <?= ($appSettings['date_format'] ?? '') === 'd M Y' ? 'selected' : '' ?>>d M Y</option>
                                    <option value="Y-m-d" <?= ($appSettings['date_format'] ?? '') === 'Y-m-d' ? 'selected' : '' ?>>Y-m-d</option>
                                    <option value="d/m/Y" <?= ($appSettings['date_format'] ?? '') === 'd/m/Y' ? 'selected' : '' ?>>d/m/Y</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Time Format</label>
                                <select name="time_format" class="form-select">
                                    <option value="24h" <?= ($appSettings['time_format'] ?? '') === '24h' ? 'selected' : '' ?>>24 Hours</option>
                                    <option value="12h" <?= ($appSettings['time_format'] ?? '') === '12h' ? 'selected' : '' ?>>12 Hours (AM/PM)</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Time Zone</label>
                                <select name="timezone" class="form-select">
                                    <option value="Asia/Karachi" <?= ($appSettings['timezone'] ?? '') === 'Asia/Karachi' ? 'selected' : '' ?>>Asia/Karachi</option>
                                    <option value="UTC" <?= ($appSettings['timezone'] ?? '') === 'UTC' ? 'selected' : '' ?>>UTC</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Currency Code</label>
                                <select name="currency" class="form-select">
                                    <option value="PKR" <?= ($appSettings['currency'] ?? '') === 'PKR' ? 'selected' : '' ?>>PKR</option>
                                    <option value="USD" <?= ($appSettings['currency'] ?? '') === 'USD' ? 'selected' : '' ?>>USD</option>
                                    <option value="EUR" <?= ($appSettings['currency'] ?? '') === 'EUR' ? 'selected' : '' ?>>EUR</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Default Table Pagination (Min: 5, Max: 100)</label>
                                <input type="number" min="5" max="100" step="1" name="default_pagination" class="form-control" required value="<?= htmlspecialchars($appSettings['default_pagination'] ?? '10', ENT_QUOTES, 'UTF-8') ?>">
                                <div class="invalid-feedback-custom">Must be a positive integer between 5 and 100.</div>
                            </div>
                        </div>
                    </div>
                    <div class="settings-card-footer">
                        <span class="text-muted small">Global formatting and translation rules.</span>
                        <?php if (appUserCan('settings_general', 'edit')): ?><button type="submit" class="btn btn-settings-save">Save General</button><?php endif; ?>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- 2. STORE MODULE -->
            <?php if ($canViewSettingsTab('store')): ?><div class="settings-pane <?= $activeTab === 'store' ? '' : 'd-none' ?>" id="pane-store">
                <form method="POST" action="settings.php?tab=store" enctype="multipart/form-data" class="settings-content-card" data-module="store">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['settings_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="settings_module" value="store">
                    <div class="settings-card-header">
                        <h2>Store Profile &amp; Business Info</h2>
                        <p>Store name, contact details, opening hours, website URL, and logo branding.</p>
                    </div>
                    <div class="settings-card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Store Name <span class="text-danger">*</span></label>
                                <input type="text" name="store_name" data-check-unique="store_name" class="form-control" required maxlength="150" value="<?= htmlspecialchars($appSettings['store_name'] ?? 'Ecommerce Store', ENT_QUOTES, 'UTF-8') ?>">
                                <div class="invalid-feedback-custom">Store Name is required (max 150 chars).</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Store Logo (PNG, JPG, WebP, Max 2MB)</label>
                                <input type="file" name="store_logo" id="store_logo" class="form-control" accept="image/png,image/jpeg,image/webp">
                                <div class="invalid-feedback-custom">File must be PNG, JPG, or WebP and under 2MB.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Store Support Email</label>
                                <input type="email" name="store_email" id="store_email" data-check-unique="store_email" class="form-control" placeholder="support@store.com" value="<?= htmlspecialchars($appSettings['store_email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <div class="invalid-feedback-custom">Please enter a valid email address.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Store Phone Number (Numbers and optional leading + only)</label>
                                <input type="text" name="store_phone" id="store_phone" data-check-unique="store_phone" class="form-control" placeholder="+923001234567" value="<?= htmlspecialchars($appSettings['store_phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <div class="invalid-feedback-custom">Invalid phone number format (e.g. +923001234567).</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Website URL</label>
                                <input type="text" name="website_url" id="website_url" class="form-control" placeholder="https://example.com" value="<?= htmlspecialchars($appSettings['website_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <div class="invalid-feedback-custom">Please enter a valid URL starting with http:// or https://.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Store Opening Hours</label>
                                <input type="text" name="store_hours" class="form-control" maxlength="100" placeholder="Mon - Sat: 9:00 AM - 8:00 PM" value="<?= htmlspecialchars($appSettings['store_hours'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <div class="invalid-feedback-custom">Max 100 characters allowed.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Store Address</label>
                                <input type="text" name="store_address" class="form-control" maxlength="255" value="<?= htmlspecialchars($appSettings['store_address'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <div class="invalid-feedback-custom">Max 255 characters allowed.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Business Description</label>
                                <textarea name="store_description" rows="2" class="form-control" maxlength="500"><?= htmlspecialchars($appSettings['store_description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                                <div class="invalid-feedback-custom">Max 500 characters allowed.</div>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="store_status" name="store_status" value="1" <?= (($appSettings['store_status'] ?? '1') === '1') ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold" for="store_status">Store Active Status (Online / Operational)</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="settings-card-footer">
                        <span class="text-muted small">Updates invoices and storefront branding.</span>
                        <?php if (appUserCan('settings_store', 'edit')): ?><button type="submit" class="btn btn-settings-save">Save Store</button><?php endif; ?>
                    </div>
                </form>
            </div>
            <?php endif; ?>

           <!-- 3. POS MODULE -->
<?php if ($canViewSettingsTab('pos')): ?><div class="settings-pane <?= $activeTab === 'pos' ? '' : 'd-none' ?>" id="pane-pos">
    <form method="POST" action="settings.php?tab=pos" class="settings-content-card" data-module="pos">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['settings_csrf'], ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="settings_module" value="pos">
        <div class="settings-card-header">
            <h2>POS &amp; Terminal Settings</h2>
            <p>Master POS activation, layout presets, checkout payment toggles, and receipt formats.</p>
        </div>
        <div class="settings-card-body">
            <!-- Master POS Enable Switch -->
            <div class="p-3 mb-4 border rounded-3 bg-light">
                <div class="form-check form-switch m-0">
                    <input class="form-check-input" type="checkbox" id="pos_module_enabled" name="pos_module_enabled" value="1" <?= (($appSettings['pos_module_enabled'] ?? '1') === '1') ? 'checked' : '' ?>>
                    <label class="form-check-label fw-bold text-dark" for="pos_module_enabled">Enable POS Module (Master Switch)</label>
                </div>
                <small class="text-muted d-block mt-1">Turn off to temporarily disable POS access across the entire admin dashboard.</small>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Default POS Layout</label>
                    <select name="pos_layout" class="form-select">
                        <option value="grid" <?= (($appSettings['pos_layout'] ?? 'grid') === 'grid') ? 'selected' : '' ?>>Product Grid</option>
                        <option value="list" <?= (($appSettings['pos_layout'] ?? '') === 'list') ? 'selected' : '' ?>>Compact List</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Default POS Payment Method</label>
                    <select name="pos_default_payment" class="form-select">
                        <option value="cash" <?= (($appSettings['pos_default_payment'] ?? 'cash') === 'cash') ? 'selected' : '' ?>>Cash</option>
                        <option value="card" <?= (($appSettings['pos_default_payment'] ?? '') === 'card') ? 'selected' : '' ?>>Card</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Default POS Discount (%) — Positive Only (0 - 100)</label>
                    <input type="number" step="0.01" min="0" max="100" name="pos_default_discount" class="form-control" required value="<?= htmlspecialchars($appSettings['pos_default_discount'] ?? '0', ENT_QUOTES, 'UTF-8') ?>">
                    <div class="invalid-feedback-custom">Must be a non-negative number between 0 and 100.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Receipt Format Width</label>
                    <select name="receipt_format" class="form-select">
                        <option value="80mm" <?= (($appSettings['receipt_format'] ?? '80mm') === '80mm') ? 'selected' : '' ?>>80mm Thermal Printer</option>
                        <option value="58mm" <?= (($appSettings['receipt_format'] ?? '') === '58mm') ? 'selected' : '' ?>>58mm Pocket Printer</option>
                        <option value="a4" <?= (($appSettings['receipt_format'] ?? '') === 'a4') ? 'selected' : '' ?>>Standard A4 Invoice</option>
                    </select>
                </div>
            </div>
            <div class="row g-3 pt-2 border-top">
                <div class="col-md-6">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="pos_cash_enabled" name="pos_cash_enabled" value="1" <?= (($appSettings['pos_cash_enabled'] ?? '1') === '1') ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="pos_cash_enabled">Enable Cash Payment Mode</label>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="pos_card_enabled" name="pos_card_enabled" value="1" <?= (($appSettings['pos_card_enabled'] ?? '1') === '1') ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="pos_card_enabled">Enable Card Payment Mode</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="pos_cust_select" name="pos_cust_select" value="1" <?= (($appSettings['pos_cust_select'] ?? '1') === '1') ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="pos_cust_select">Enable Customer Selection Prompt</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="pos_barcode" name="pos_barcode" value="1" <?= (($appSettings['pos_barcode'] ?? '1') === '1') ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="pos_barcode">Enable Barcode Scanner Integration</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="pos_search" name="pos_search" value="1" <?= (($appSettings['pos_search'] ?? '1') === '1') ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="pos_search">Enable Live Product Search Filter</label>
                    </div>
                </div>
            </div>
        </div>
        <div class="settings-card-footer">
            <span class="text-muted small">Controls POS terminal availability and checkout settings.</span>
            <?php if (appUserCan('settings_pos', 'edit')): ?><button type="submit" class="btn btn-settings-save">Save POS</button><?php endif; ?>
        </div>
    </form>
</div>
<?php endif; ?>
            <!-- 4. SECURITY MODULE -->
            <?php if ($canViewSettingsTab('security')): ?><div class="settings-pane <?= $activeTab === 'security' ? '' : 'd-none' ?>" id="pane-security">
                <form method="POST" action="settings.php?tab=security" class="settings-content-card" data-module="security">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['settings_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="settings_module" value="security">
                    <div class="settings-card-header">
                        <h2>Security &amp; Authentication</h2>
                        <p>Password length policies, session lifetimes, failed login locks, and CSRF protection.</p>
                    </div>
                    <div class="settings-card-body">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Minimum Password Length (6 - 32)</label>
                                <input type="number" min="6" max="32" step="1" name="min_password_length" class="form-control" required value="<?= htmlspecialchars($appSettings['min_password_length'] ?? '8', ENT_QUOTES, 'UTF-8') ?>">
                                <div class="invalid-feedback-custom">Must be a positive integer between 6 and 32.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Admin Session Timeout Minutes (5 - 1440)</label>
                                <input type="number" min="5" max="1440" step="1" name="session_timeout_minutes" class="form-control" required value="<?= htmlspecialchars($appSettings['session_timeout_minutes'] ?? '60', ENT_QUOTES, 'UTF-8') ?>">
                                <div class="invalid-feedback-custom">Must be a positive integer between 5 and 1440.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Max Login Attempts Before Lockout (3 - 20)</label>
                                <input type="number" min="3" max="20" step="1" name="max_login_attempts" class="form-control" required value="<?= htmlspecialchars($appSettings['max_login_attempts'] ?? '5', ENT_QUOTES, 'UTF-8') ?>">
                                <div class="invalid-feedback-custom">Must be a positive integer between 3 and 20.</div>
                            </div>
                        </div>
                        <div class="row g-3 pt-2 border-top">
                            <div class="col-12">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="enforce_strong_password" name="enforce_strong_password" value="1" <?= (($appSettings['enforce_strong_password'] ?? '1') === '1') ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold" for="enforce_strong_password">Enforce Strong Passwords (Letters, Numbers, Symbols)</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="log_admin_activity" name="log_admin_activity" value="1" <?= (($appSettings['log_admin_activity'] ?? '1') === '1') ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold" for="log_admin_activity">Enable Admin Activity Audit Logging</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="settings-card-footer">
                        <span class="text-muted small">Protects admin authorization boundaries.</span>
                        <?php if (appUserCan('settings_security', 'edit')): ?><button type="submit" class="btn btn-settings-save">Save Security</button><?php endif; ?>
                    </div>
                </form>
                <?php if (isSuperAdmin() && appUserCan('settings', 'manage')): ?>
                    <section class="settings-content-card mt-4">
                        <div class="settings-card-header"><h2>Role Access Management</h2><p>Manage roles, permissions, and module availability in one place.</p></div>
                        <div class="settings-card-body"><a class="btn btn-settings-save" href="settings.php?tab=roles">Open Roles &amp; Permissions</a></div>
                    </section>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($canViewSettingsTab('roles')): ?><div class="settings-pane <?= $activeTab === 'roles' ? '' : 'd-none' ?>" id="pane-roles">
                <?php renderRoleManagementPanel($conn); ?>
            </div>
            <?php endif; ?>

            <!-- 5. SMTP MODULE -->
            <?php if ($canViewSettingsTab('smtp')): ?><div class="settings-pane <?= $activeTab === 'smtp' ? '' : 'd-none' ?>" id="pane-smtp">
                <form method="POST" action="settings.php?tab=smtp" class="settings-content-card" data-module="smtp">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['settings_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="settings_module" value="smtp">
                    <div class="settings-card-header">
                        <h2>SMTP Mail Server Configuration</h2>
                        <p>Configure mail server hosts, authentication credentials, encryption types, and live connection testing.</p>
                    </div>
                    <div class="settings-card-body">
                        <div class="row g-3 mb-3">
                            <div class="col-md-8">
                                <label class="form-label fw-semibold">SMTP Host</label>
                                <input type="text" name="smtp_host" class="form-control" maxlength="150" placeholder="smtp.example.com" value="<?= htmlspecialchars($appSettings['smtp_host'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <div class="invalid-feedback-custom">Max 150 characters allowed.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">SMTP Port (1 - 65535)</label>
                                <input type="number" min="1" max="65535" step="1" name="smtp_port" class="form-control" required placeholder="587" value="<?= htmlspecialchars($appSettings['smtp_port'] ?? '587', ENT_QUOTES, 'UTF-8') ?>">
                                <div class="invalid-feedback-custom">Port must be a positive integer between 1 and 65535.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">SMTP Username</label>
                                <input type="text" name="smtp_username" class="form-control" maxlength="100" value="<?= htmlspecialchars($appSettings['smtp_username'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <div class="invalid-feedback-custom">Max 100 characters allowed.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">SMTP Password (Secure Storage)</label>
                                <div class="input-group">
                                    <input type="password" name="smtp_password" id="smtp_password" class="form-control" maxlength="150" autocomplete="new-password" placeholder="Leave blank to keep the current password">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('smtp_password', this)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback-custom">Max 150 characters allowed.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Encryption Type</label>
                                <select name="smtp_encryption" class="form-select">
                                    <option value="tls" <?= (($appSettings['smtp_encryption'] ?? 'tls') === 'tls') ? 'selected' : '' ?>>TLS</option>
                                    <option value="ssl" <?= (($appSettings['smtp_encryption'] ?? '') === 'ssl') ? 'selected' : '' ?>>SSL</option>
                                    <option value="none" <?= (($appSettings['smtp_encryption'] ?? '') === 'none') ? 'selected' : '' ?>>None</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Sender Name</label>
                                <input type="text" name="smtp_sender_name" class="form-control" maxlength="100" value="<?= htmlspecialchars($appSettings['smtp_sender_name'] ?? 'Ecommerce Store', ENT_QUOTES, 'UTF-8') ?>">
                                <div class="invalid-feedback-custom">Max 100 characters allowed.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Sender Email Address</label>
                                <input type="email" name="smtp_from_email" id="smtp_from_email" class="form-control" value="<?= htmlspecialchars($appSettings['smtp_from_email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <div class="invalid-feedback-custom">Please enter a valid sender email address.</div>
                            </div>
                        </div>

                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input" type="checkbox" id="smtp_enabled" name="smtp_enabled" value="1" <?= (($appSettings['smtp_enabled'] ?? '1') === '1') ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold text-success" for="smtp_enabled">Enable SMTP Outgoing Mailer</label>
                        </div>

                        <div class="p-3 border rounded-3 bg-light">
                            <h6 class="fw-bold mb-2"><i class="bi bi-send me-1"></i> Send Test Email</h6>
                            <div class="row g-2 align-items-center">
                                <div class="col-md-8">
                                    <input type="email" name="test_email_recipient" class="form-control form-control-sm" placeholder="Enter recipient email for live connection test...">
                                </div>
                                <div class="col-md-4">
                                    <?php if (appUserCan('settings_smtp', 'edit')): ?><button type="submit" name="send_test_email" value="1" class="btn btn-sm btn-outline-dark w-100">Send Test</button><?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="settings-card-footer">
                        <span class="text-muted small">Passwords are never exposed in JavaScript or frontend markup.</span>
                        <?php if (appUserCan('settings_smtp', 'edit')): ?><button type="submit" class="btn btn-settings-save">Save SMTP</button><?php endif; ?>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- 6. INVOICES MODULE -->
            <?php if ($canViewSettingsTab('invoices')): ?><div class="settings-pane <?= $activeTab === 'invoices' ? '' : 'd-none' ?>" id="pane-invoices">
                <form method="POST" action="settings.php?tab=invoices" class="settings-content-card" data-module="invoices">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['settings_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="settings_module" value="invoices">
                    <div class="settings-card-header">
                        <h2>Invoice Customization</h2>
                        <p>Invoice number prefixes, header/footer notes, and itemized tax/discount display options.</p>
                    </div>
                    <div class="settings-card-body">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Invoice Number Prefix <span class="text-danger">*</span></label>
                                <input type="text" name="invoice_prefix" data-check-unique="invoice_prefix" class="form-control" required maxlength="20" value="<?= htmlspecialchars($appSettings['invoice_prefix'] ?? 'INV-', ENT_QUOTES, 'UTF-8') ?>">
                                <div class="invalid-feedback-custom">Invoice prefix is required (max 20 chars).</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Invoice Number Format</label>
                                <select name="invoice_number_fmt" class="form-select">
                                    <option value="sequential" <?= (($appSettings['invoice_number_fmt'] ?? 'sequential') === 'sequential') ? 'selected' : '' ?>>Sequential (INV-1001)</option>
                                    <option value="timestamp" <?= (($appSettings['invoice_number_fmt'] ?? '') === 'timestamp') ? 'selected' : '' ?>>Date + Sequence (INV-2026-001)</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Invoice Header Note</label>
                                <textarea name="invoice_header" rows="2" class="form-control" maxlength="255" placeholder="Optional top banner note..."><?= htmlspecialchars($appSettings['invoice_header'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                                <div class="invalid-feedback-custom">Max 255 characters allowed.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Invoice Footer Note</label>
                                <textarea name="invoice_footer" rows="2" class="form-control" maxlength="255"><?= htmlspecialchars($appSettings['invoice_footer'] ?? 'Thank you for your business!', ENT_QUOTES, 'UTF-8') ?></textarea>
                                <div class="invalid-feedback-custom">Max 255 characters allowed.</div>
                            </div>
                        </div>
                        <div class="row g-3 pt-2 border-top">
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="invoice_tax_display" name="invoice_tax_display" value="1" <?= (($appSettings['invoice_tax_display'] ?? '1') === '1') ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold" for="invoice_tax_display">Display Itemized Tax on Invoices</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="invoice_disc_display" name="invoice_disc_display" value="1" <?= (($appSettings['invoice_disc_display'] ?? '1') === '1') ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold" for="invoice_disc_display">Display Applied Discounts on Invoices</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="settings-card-footer">
                        <span class="text-muted small">Preserves historical invoice data integrity.</span>
                        <?php if (appUserCan('settings_invoices', 'edit')): ?><button type="submit" class="btn btn-settings-save">Save Invoices</button><?php endif; ?>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- 7. INVENTORY MODULE -->
            <?php if ($canViewSettingsTab('inventory')): ?><div class="settings-pane <?= $activeTab === 'inventory' ? '' : 'd-none' ?>" id="pane-inventory">
                <form method="POST" action="settings.php?tab=inventory" class="settings-content-card" data-module="inventory">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['settings_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="settings_module" value="inventory">
                    <div class="settings-card-header">
                        <h2>Inventory &amp; Stock Thresholds</h2>
                        <p>Low stock warning levels, out-of-stock notification triggers, and negative stock permissions.</p>
                    </div>
                    <div class="settings-card-body">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Low Stock Alert Threshold (Min: 1)</label>
                                <input type="number" min="1" step="1" name="low_stock_threshold" class="form-control" required value="<?= htmlspecialchars($appSettings['low_stock_threshold'] ?? '5', ENT_QUOTES, 'UTF-8') ?>">
                                <div class="invalid-feedback-custom">Must be a positive integer of at least 1.</div>
                            </div>
                        </div>
                        <div class="row g-3 pt-2 border-top">
                            <div class="col-12">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="stock_tracking" name="stock_tracking" value="1" <?= (($appSettings['stock_tracking'] ?? '1') === '1') ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold" for="stock_tracking">Enable Real-Time Stock Quantity Tracking</label>
                                </div>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="out_of_stock_alerts" name="out_of_stock_alerts" value="1" <?= (($appSettings['out_of_stock_alerts'] ?? '1') === '1') ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold" for="out_of_stock_alerts">Send Alerts When Products Go Out of Stock</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="allow_negative_stock" name="allow_negative_stock" value="1" <?= (($appSettings['allow_negative_stock'] ?? '0') === '1') ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold text-danger" for="allow_negative_stock">Allow Checkout When Stock Level is Zero or Negative</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="settings-card-footer">
                        <span class="text-muted small">Does not alter existing stock calculation formulas.</span>
                        <?php if (appUserCan('settings_inventory', 'edit')): ?><button type="submit" class="btn btn-settings-save">Save Inventory</button><?php endif; ?>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- 8. BACKUP MODULE -->
            <?php if ($canViewSettingsTab('backup')): ?><div class="settings-pane <?= $activeTab === 'backup' ? '' : 'd-none' ?>" id="pane-backup">
                <form method="POST" action="settings.php?tab=backup" class="settings-content-card" data-module="backup">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['settings_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="settings_module" value="backup">
                    <div class="settings-card-header">
                        <h2>Database &amp; System Backup</h2>
                        <p>Generate database SQL snapshots, download backup archives, and configure backup schedules.</p>
                    </div>
                    <div class="settings-card-body">
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Automated Backup Schedule</label>
                                <select name="backup_frequency" class="form-select">
                                    <option value="daily" <?= (($appSettings['backup_frequency'] ?? 'weekly') === 'daily') ? 'selected' : '' ?>>Daily Snapshot</option>
                                    <option value="weekly" <?= (($appSettings['backup_frequency'] ?? 'weekly') === 'weekly') ? 'selected' : '' ?>>Weekly Snapshot</option>
                                    <option value="monthly" <?= (($appSettings['backup_frequency'] ?? '') === 'monthly') ? 'selected' : '' ?>>Monthly Snapshot</option>
                                    <option value="off" <?= (($appSettings['backup_frequency'] ?? '') === 'off') ? 'selected' : '' ?>>Manual Only</option>
                                </select>
                            </div>
                        </div>

                        <div class="p-3 border rounded-3 bg-light">
                            <h6 class="fw-bold mb-2"><i class="bi bi-database me-1"></i> Manual Database Backup Action</h6>
                            <p class="small text-muted mb-3">Click below to instantly generate and download a secure database export snapshot.</p>
                            <?php if (appUserCan('settings_backup', 'export')): ?><button type="submit" name="trigger_backup" value="1" class="btn btn-sm btn-dark">
                                <i class="bi bi-cloud-arrow-down me-1"></i> Create &amp; Download Backup Now
                            </button><?php endif; ?>
                        </div>
                    </div>
                    <div class="settings-card-footer">
                        <span class="text-muted small">Restricted to authorized administrators.</span>
                        <?php if (appUserCan('settings_backup', 'edit')): ?><button type="submit" class="btn btn-settings-save">Save Backup</button><?php endif; ?>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- 9. PAYMENTS MODULE -->
            <?php if ($canViewSettingsTab('payments')): ?><div class="settings-pane <?= $activeTab === 'payments' ? '' : 'd-none' ?>" id="pane-payments">
                <form method="POST" action="settings.php?tab=payments" class="settings-content-card" data-module="payments">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['settings_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="settings_module" value="payments">
                    <div class="settings-card-header">
                        <h2>Payment Gateways &amp; Methods</h2>
                        <p>Cash on delivery, Direct Bank Transfer, and secure Stripe Checkout credential configuration.</p>
                    </div>
                    <div class="settings-card-body">
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <div class="p-3 border rounded-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="payment_cod_enabled" name="payment_cod_enabled" value="1" <?= (($appSettings['payment_cod_enabled'] ?? '1') === '1') ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-semibold" for="payment_cod_enabled">Cash on Delivery</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 border rounded-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="payment_bank_enabled" name="payment_bank_enabled" value="1" <?= (($appSettings['payment_bank_enabled'] ?? '1') === '1') ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-semibold" for="payment_bank_enabled">Direct Bank Transfer</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 border rounded-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="payment_stripe_enabled" name="payment_stripe_enabled" value="1" <?= (($appSettings['payment_stripe_enabled'] ?? '1') === '1') ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-semibold" for="payment_stripe_enabled">Stripe Online Checkout</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="settings-section-divider">Stripe Integration Credentials</div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Stripe Publishable Key</label>
                                <input type="text" name="stripe_publishable_key" class="form-control" maxlength="150" placeholder="pk_test_..." value="<?= htmlspecialchars($appSettings['stripe_publishable_key'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <div class="invalid-feedback-custom">Max 150 characters allowed.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Stripe Secret Key (Securely stored)</label>
                                <div class="input-group">
                                    <input type="password" name="stripe_secret_key" id="stripe_secret_key" class="form-control" maxlength="150" autocomplete="new-password" placeholder="Leave blank to keep the current key">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('stripe_secret_key', this)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback-custom">Max 150 characters allowed.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Stripe Webhook Secret</label>
                                <div class="input-group">
                                    <input type="password" name="stripe_webhook_secret" id="stripe_webhook_secret" class="form-control" maxlength="150" autocomplete="new-password" placeholder="Leave blank to keep the current secret">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('stripe_webhook_secret', this)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback-custom">Max 150 characters allowed.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Stripe Environment Mode</label>
                                <select name="stripe_mode" class="form-select">
                                    <option value="test" <?= (($appSettings['stripe_mode'] ?? 'test') === 'test') ? 'selected' : '' ?>>Sandbox Test Mode</option>
                                    <option value="live" <?= (($appSettings['stripe_mode'] ?? '') === 'live') ? 'selected' : '' ?>>Live Production Mode</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Payment Instructions (Bank Transfer / COD Note)</label>
                                <textarea name="payment_instructions" rows="2" class="form-control" maxlength="500" placeholder="Instructions displayed during checkout..."><?= htmlspecialchars($appSettings['payment_instructions'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                                <div class="invalid-feedback-custom">Max 500 characters allowed.</div>
                            </div>
                        </div>
                    </div>
                    <div class="settings-card-footer">
                        <span class="text-muted small"><i class="bi bi-shield-lock-fill me-1"></i> Secret keys are protected on the server.</span>
                        <?php if (appUserCan('settings_payments', 'edit')): ?><button type="submit" class="btn btn-settings-save">Save Payments</button><?php endif; ?>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- 10. TAX MODULE -->
            <?php if ($canViewSettingsTab('tax')): ?><div class="settings-pane <?= $activeTab === 'tax' ? '' : 'd-none' ?>" id="pane-tax">
                <form method="POST" action="settings.php?tab=tax" class="settings-content-card" data-module="tax">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['settings_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="settings_module" value="tax">
                    <div class="settings-card-header">
                        <h2>Tax Calculation Settings</h2>
                        <p>Sales tax rates synchronized with POS, checkout, and generated customer invoices.</p>
                    </div>
                    <div class="settings-card-body">
                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input" type="checkbox" id="tax_enabled" name="tax_enabled" value="1" <?= (($appSettings['tax_enabled'] ?? '1') === '1') ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="tax_enabled">Enable Automated Tax Calculation</label>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Tax Name / Label <span class="text-danger">*</span></label>
                                <input type="text" name="tax_label" class="form-control" required maxlength="50" value="<?= htmlspecialchars($appSettings['tax_label'] ?? 'GST / Sales Tax', ENT_QUOTES, 'UTF-8') ?>">
                                <div class="invalid-feedback-custom">Tax label is required (max 50 chars).</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Tax Percentage Rate (%) — Positive Only (0 - 100)</label>
                                <input type="number" step="0.01" min="0" max="100" name="pos_tax_rate" class="form-control" required value="<?= htmlspecialchars($appSettings['pos_tax_rate'] ?? '0', ENT_QUOTES, 'UTF-8') ?>">
                                <div class="invalid-feedback-custom">Must be a non-negative number between 0 and 100.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Calculation Preference</label>
                                <select name="tax_calculation_type" class="form-select">
                                    <option value="exclusive" <?= (($appSettings['tax_calculation_type'] ?? 'exclusive') === 'exclusive') ? 'selected' : '' ?>>Tax Exclusive (Added at Checkout)</option>
                                    <option value="inclusive" <?= (($appSettings['tax_calculation_type'] ?? '') === 'inclusive') ? 'selected' : '' ?>>Tax Inclusive (Included in Price)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="settings-card-footer">
                        <span class="text-muted small">Synchronizes directly with `pos_tax_rate`.</span>
                        <?php if (appUserCan('settings_tax', 'edit')): ?><button type="submit" class="btn btn-settings-save">Save Tax</button><?php endif; ?>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- 11. NOTIFICATIONS MODULE -->
            <?php if ($canViewSettingsTab('notifications')): ?><div class="settings-pane <?= $activeTab === 'notifications' ? '' : 'd-none' ?>" id="pane-notifications">
                <form method="POST" action="settings.php?tab=notifications" class="settings-content-card" data-module="notifications">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['settings_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="settings_module" value="notifications">
                    <div class="settings-card-header">
                        <h2>Notification Event Preferences</h2>
                        <p>Configure automated email and dashboard alerts for orders, stock levels, and customer registrations.</p>
                    </div>
                    <div class="settings-card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="notify_new_order" name="notify_new_order" value="1" <?= (($appSettings['notify_new_order'] ?? '1') === '1') ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold" for="notify_new_order">New Order Placement Alerts</label>
                                </div>
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="notify_order_status" name="notify_order_status" value="1" <?= (($appSettings['notify_order_status'] ?? '1') === '1') ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold" for="notify_order_status">Order Status Update Notifications</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="notify_low_stock" name="notify_low_stock" value="1" <?= (($appSettings['notify_low_stock'] ?? '1') === '1') ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold" for="notify_low_stock">Low Stock Warning Notifications</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="notify_out_of_stock" name="notify_out_of_stock" value="1" <?= (($appSettings['notify_out_of_stock'] ?? '1') === '1') ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold" for="notify_out_of_stock">Out of Stock Alerts</label>
                                </div>
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="notify_new_customer" name="notify_new_customer" value="1" <?= (($appSettings['notify_new_customer'] ?? '1') === '1') ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold" for="notify_new_customer">New Customer Registration Alerts</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="admin_email_alerts" name="admin_email_alerts" value="1" <?= (($appSettings['admin_email_alerts'] ?? '1') === '1') ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold" for="admin_email_alerts">Send Broadcast Emails to Admin</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="settings-card-footer">
                        <span class="text-muted small">Integrates with SMTP and dashboard topbar bell.</span>
                        <?php if (appUserCan('settings_notifications', 'edit')): ?><button type="submit" class="btn btn-settings-save">Save Notifications</button><?php endif; ?>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- 12. SYSTEM & MAINTENANCE MODULE -->
            <?php if ($canViewSettingsTab('system')): ?><div class="settings-pane <?= $activeTab === 'system' ? '' : 'd-none' ?>" id="pane-system">
                <form method="POST" action="settings.php?tab=system" class="settings-content-card" data-module="system">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['settings_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="settings_module" value="system">
                    <div class="settings-card-header">
                        <h2>System Diagnostics &amp; Maintenance</h2>
                        <p>Server environment version inspects, database connectivity health, and maintenance mode toggles.</p>
                    </div>
                    <div class="settings-card-body">
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="p-3 border rounded-3 bg-light">
                                    <span class="text-muted small d-block">PHP Runtime Version</span>
                                    <strong class="text-dark fs-6"><?= phpversion() ?></strong>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 border rounded-3 bg-light">
                                    <span class="text-muted small d-block">MySQL Server Version</span>
                                    <strong class="text-dark fs-6"><?= htmlspecialchars($conn->server_info ?? 'Connected') ?></strong>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 border rounded-3 bg-light">
                                    <span class="text-muted small d-block">Database Connection Status</span>
                                    <span class="badge bg-success">Online &amp; Responsive</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 border rounded-3 bg-light">
                                    <span class="text-muted small d-block">Application Version</span>
                                    <strong class="text-dark fs-6">v2.5.0 Enterprise</strong>
                                </div>
                            </div>
                        </div>

                        <div class="form-check form-switch pt-2 border-top">
                            <input class="form-check-input" type="checkbox" id="maintenance_mode" name="maintenance_mode" value="1" <?= (($appSettings['maintenance_mode'] ?? '0') === '1') ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold text-danger" for="maintenance_mode">Enable Storefront Maintenance Mode</label>
                            <small class="text-muted d-block mt-1">Hides public store pages while allowing authenticated administrators full access.</small>
                        </div>
                    </div>
                    <div class="settings-card-footer">
                        <span class="text-muted small">Restricted to authorized administrators.</span>
                        <?php if (appUserCan('settings_system', 'edit')): ?><button type="submit" class="btn btn-settings-save">Save System</button><?php endif; ?>
                    </div>
                </form>
            </div>
            <?php endif; ?>

        </div>
    </div>
    <?php } ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function scrollSettingsNavigationToTab(tabKey) {
    if (!window.matchMedia('(min-width: 992px)').matches) {
        return;
    }

    const navigation = document.querySelector('.settings-nav-pills');
    if (!navigation) {
        return;
    }

    const activeLink = Array.from(navigation.querySelectorAll('[data-tab-link]'))
        .find(function (link) {
            return link.getAttribute('data-tab-link') === tabKey;
        });
    if (!activeLink) {
        return;
    }

    const navigationBounds = navigation.getBoundingClientRect();
    const linkBounds = activeLink.getBoundingClientRect();
    const inset = 8;
    let scrollTop = navigation.scrollTop;

    if (linkBounds.top < navigationBounds.top + inset) {
        scrollTop += linkBounds.top - navigationBounds.top - inset;
    } else if (linkBounds.bottom > navigationBounds.bottom - inset) {
        scrollTop += linkBounds.bottom - navigationBounds.bottom + inset;
    }

    if (scrollTop !== navigation.scrollTop) {
        navigation.scrollTo({ top: scrollTop, behavior: 'smooth' });
    }
}

function switchSettingsTab(tabKey) {
    document.querySelectorAll('.settings-pane').forEach(function (pane) {
        pane.classList.add('d-none');
    });

    const targetPane = document.getElementById('pane-' + tabKey);
    if (targetPane) {
        targetPane.classList.remove('d-none');
    }

    document.querySelectorAll('[data-tab-link]').forEach(function (link) {
        link.classList.toggle('active', link.getAttribute('data-tab-link') === tabKey);
    });
    scrollSettingsNavigationToTab(tabKey);

    const mobileSelect = document.getElementById('mobileSettingsCategory');
    if (mobileSelect && mobileSelect.value !== tabKey) {
        mobileSelect.value = tabKey;
    }

    const url = new URL(window.location.href);
    url.searchParams.set('tab', tabKey);
    window.history.replaceState({}, '', url.toString());

    const settingsContentCol = document.querySelector('.settings-content-col');
    if (settingsContentCol && window.matchMedia('(min-width: 992px)').matches) {
        settingsContentCol.scrollTo({ top: 0, behavior: 'smooth' });
    } else {
        const settingsContainer = document.querySelector('.settings-container');
        if (settingsContainer) {
            settingsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }
}

const initiallyActiveSettingsLink = document.querySelector('.settings-nav-pills .nav-link.active');
if (initiallyActiveSettingsLink) {
    window.requestAnimationFrame(function () {
        scrollSettingsNavigationToTab(initiallyActiveSettingsLink.getAttribute('data-tab-link'));
    });
}

// Password Eye Toggle Function
function togglePasswordVisibility(fieldId, btn) {
    const field = document.getElementById(fieldId);
    const icon = btn.querySelector('i');
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
}

// REAL-TIME STRICT VALIDATION & LIVE DATABASE CHECK ENGINE
document.addEventListener('DOMContentLoaded', function() {
    // 1. Strict key blocking on number inputs (preventing '-', '+', 'e', 'E')
    document.querySelectorAll('input[type="number"]').forEach(input => {
        input.addEventListener('keydown', function(e) {
            if (e.key === '-' || e.key === '+' || e.key === 'e' || e.key === 'E') {
                e.preventDefault();
            }
        });
        input.addEventListener('input', function() {
            if (this.value < 0) {
                this.value = Math.abs(this.value);
            }
        });
    });

    // 2. Strict phone input sanitization
    const phoneInput = document.getElementById('store_phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function() {
            let val = this.value;
            let hasPlus = val.startsWith('+');
            val = val.replace(/[^0-9\s]/g, '');
            if (hasPlus) {
                val = '+' + val.replace(/\+/g, '');
            } else {
                val = val.replace(/\+/g, '');
            }
            this.value = val;
            validateSingleField(this);
        });
    }

    // 3. Real-time live validation and database check on input & blur for ALL fields
    document.querySelectorAll('form[data-module] input, form[data-module] textarea').forEach(input => {
        if (input.id !== 'store_phone') {
            ['input', 'blur'].forEach(eventType => {
                input.addEventListener(eventType, function() {
                    validateSingleField(this);
                });
            });
        }
    });

    // 4. Form submission validation check
    document.querySelectorAll('form[data-module]').forEach(form => {
        form.addEventListener('submit', function(event) {
            let isFormValid = true;

            form.querySelectorAll('input, textarea').forEach(input => {
                if (!validateSingleField(input)) {
                    isFormValid = false;
                }
            });

            if (!isFormValid) {
                event.preventDefault();
                event.stopPropagation();
                Swal.fire({
                    icon: 'error',
                    title: 'Validation Error',
                    text: 'Please correct the highlighted fields before saving.',
                    confirmButtonColor: getComputedStyle(document.body).getPropertyValue('--button-primary').trim() || getComputedStyle(document.body).getPropertyValue('--dark').trim()
                });
            }
        });
    });
});

// Cache for DB check results to prevent redundant requests on every single keystroke
const dbCheckCache = {};

// Comprehensive Real-Time Validator with Backend Database Check
function validateSingleField(input) {
    const val = input.value.trim();
    const name = input.getAttribute('name');
    const type = input.getAttribute('type');
    const checkUniqueKey = input.getAttribute('data-check-original') !== val ? input.getAttribute('data-check-unique') : null;
    const maxLength = input.getAttribute('maxlength') ? parseInt(input.getAttribute('maxlength'), 10) : null;
    let errorMsg = '';
    let hasError = false;

    // Remove previous error state
    input.classList.remove('is-invalid-custom');
    let container = input.closest('.input-group') || input.parentNode;
    let feedback = container.querySelector('.invalid-feedback-custom') || container.parentNode.querySelector('.invalid-feedback-custom');
    if (feedback) feedback.style.display = 'none';

    // Required fields check
    if (input.hasAttribute('required') && !val) {
        errorMsg = 'This field is required.';
        hasError = true;
    }

    // Maxlength check
    if (!hasError && maxLength && val.length > maxLength) {
        errorMsg = `Cannot exceed ${maxLength} characters.`;
        hasError = true;
    }

    // Format Validations
    if (!hasError && val !== '') {
        if (type === 'number') {
            const num = Number(val);
            const min = input.hasAttribute('min') ? Number(input.getAttribute('min')) : 0;
            const max = input.hasAttribute('max') ? Number(input.getAttribute('max')) : Infinity;

            if (isNaN(num)) {
                errorMsg = 'Please enter a valid numeric value.';
                hasError = true;
            } else if (num < 0) {
                errorMsg = 'Negative numbers and plus signs are strictly not allowed.';
                hasError = true;
            } else if (num < min || num > max) {
                errorMsg = `Value must be between ${min} and ${max}.`;
                hasError = true;
            }
        } else if (name === 'store_email' || name === 'smtp_from_email') {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(val)) {
                errorMsg = 'Please enter a valid email address.';
                hasError = true;
            }
        } else if (name === 'store_phone') {
            const phoneRegex = /^\+?[0-9\s]{7,15}$/;
            if (!phoneRegex.test(val)) {
                errorMsg = 'Invalid phone number format. Only numbers and optional leading + allowed (e.g. +923001234567).';
                hasError = true;
            }
        } else if (name === 'website_url') {
            const urlRegex = /^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?$/;
            if (!urlRegex.test(val)) {
                errorMsg = 'Please enter a valid URL starting with http:// or https://.';
                hasError = true;
            }
        }
    }

    // Live Backend Database Check for Duplicate Values
    if (!hasError && input.hasAttribute('data-check-unique') && val !== '') {
        const uniqueKey = input.getAttribute('data-check-unique');
        const cacheKey = uniqueKey + ':' + val;
        
        // Store initial value on load to avoid flagging current stored value as duplicate
        if (!input.hasAttribute('data-check-original')) {
            input.setAttribute('data-check-original', val);
        }
        
        if (input.getAttribute('data-check-original') !== val) {
            if (dbCheckCache[cacheKey] !== undefined) {
                if (dbCheckCache[cacheKey]) {
                    errorMsg = 'This value already exists in the database. Please enter a unique value.';
                    hasError = true;
                }
            } else {
                // Perform asynchronous AJAX check against database
                fetch(`settings.php?ajax_check_setting=1&key=${encodeURIComponent(uniqueKey)}&val=${encodeURIComponent(val)}`)
                    .then(response => response.json())
                    .then(data => {
                        dbCheckCache[cacheKey] = data.exists;
                        if (data.exists) {
                            input.classList.add('is-invalid-custom');
                            if (!feedback) {
                                feedback = document.createElement('div');
                                feedback.className = 'invalid-feedback-custom';
                                container.appendChild(feedback);
                            }
                            feedback.textContent = 'This value already exists in the database. Please enter a unique value.';
                            feedback.style.display = 'block';
                        }
                    }).catch(err => console.error('DB Check Error:', err));
            }
        }
    }

    if (hasError) {
        input.classList.add('is-invalid-custom');
        if (!feedback) {
            feedback = document.createElement('div');
            feedback.className = 'invalid-feedback-custom';
            container.appendChild(feedback);
        }
        feedback.textContent = errorMsg;
        feedback.style.display = 'block';
        return false;
    }

    return true;
}
</script>

<?php if (isset($_SESSION['swal_success'])): ?>
<script>
    Swal.fire({
        icon: 'success',
        title: 'Saved',
        text: <?= json_encode($_SESSION['swal_success']) ?>,
        timer: 2000,
        showConfirmButton: false
    });
</script>
<?php unset($_SESSION['swal_success']); endif; ?>

<?php if (isset($_SESSION['swal_error'])): ?>
<script>
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: <?= json_encode($_SESSION['swal_error']) ?>,
        confirmButtonColor: getComputedStyle(document.body).getPropertyValue('--button-primary').trim() || getComputedStyle(document.body).getPropertyValue('--dark').trim()
    });
</script>
<?php unset($_SESSION['swal_error']); endif; ?>

<?php include __DIR__ . '/layouts/footer.php'; ?>