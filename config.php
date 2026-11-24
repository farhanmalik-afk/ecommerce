<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

error_reporting(E_ALL);
ini_set('display_errors', '0');

function get_env_setting(string $key, $default = null)
{
    $candidates = [
        getenv($key),
        $_ENV[$key] ?? null,
        $_SERVER[$key] ?? null,
    ];

    foreach ($candidates as $candidate) {
        if (is_string($candidate) && trim($candidate) !== '') {
            return trim($candidate);
        }
    }

    return $default;
}

require_once __DIR__ . '/includes/error-handler.php';

// ==========================================
// 1. SMTP EMAIL CONFIGURATION
// ==========================================
$host = get_env_setting('DB_HOST', 'localhost');
$username = get_env_setting('DB_USERNAME', 'root');
$password = get_env_setting('DB_PASSWORD', '');
$database = get_env_setting('DB_DATABASE', 'ecommerce_db');

// ==========================================
// 2. STRIPE SECRET KEY
// ==========================================
$stripeSecretKeyConfig = get_env_setting('STRIPE_SECRET_KEY', '');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn = new mysqli($host, $username, $password, $database);
} catch (mysqli_sql_exception $exception) {
    error_log('Database connection failed: ' . $exception->getMessage());
    sendAppError(500, 'The application is temporarily unavailable. Please try again later.');
}
// Charset UTF-8 set karna taake special characters / database values theek rahein
$conn->set_charset("utf8mb4");

require_once __DIR__ . '/includes/app-settings.php';
require_once __DIR__ . '/includes/admin-notifications.php';
require_once __DIR__ . '/includes/rbac.php';

$publicEntryPoints = ['login.php', 'setup-admin.php', '403.php'];
$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');

if (!in_array($currentScript, $publicEntryPoints, true)) {
    checkAuth();
    if ($currentScript !== 'logout.php') {
        $accessContext = getRequestAccessContext();
        if ($accessContext !== null) {
            enforceAccess($conn, $accessContext['module'], $accessContext['action']);
        }
        if ($currentScript === 'trash.php') {
            enforceAccess($conn, 'trash', 'view');
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
                $trashModule = match ($_GET['type'] ?? 'products') {
                    'users' => 'user_management',
                    'customers' => 'customers',
                    'orders' => 'orders',
                    'notifications' => 'notifications',
                    default => 'products',
                };
                enforceAccess($conn, $trashModule, 'view');
            }
        }
    }
}
// ==========================================
// 3. GLOBAL DYNAMIC THEME SWITCHER LOGIC
// ==========================================
$currentTheme = appThemeName();

// Global variable set kar diya taake har file isay access kar sakay
$GLOBALS['activeTheme'] = $currentTheme;
// Safe Logo URL helper function for all pages and subfolders
if (!function_exists('appLogoUrl')) {
    function appLogoUrl() {
        global $appSettings;
        $logoFile = trim($appSettings['store_logo'] ?? '');
        
        // Base path detect karna taake subfolders (jaise customers, pos) mein bhi image theek load ho
        $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $dashboardBase = str_replace('\\', '/', rtrim(dirname($scriptPath), '/\\'));
        $moduleFolders = ['customers', 'products', 'orders', 'inventory', 'sales', 'payments', 'returns', 'pos', 'categories', 'reports', 'users', 'layouts', 'includes'];
        while (in_array(basename($dashboardBase), $moduleFolders, true)) {
            $dashboardBase = str_replace('\\', '/', rtrim(dirname($dashboardBase), '/\\'));
        }
        
        if (!empty($logoFile) && file_exists(__DIR__ . '/uploads/' . $logoFile)) {
            return $dashboardBase . '/uploads/' . $logoFile;
        }
        
        // Default Fallback Logo Icon
        return 'https://cdn-icons-png.flaticon.com/512/1165/1165687.png';
    }
}
?>