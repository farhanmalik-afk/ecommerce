<?php
require_once __DIR__ . '/config.php';

function getPermittedLandingPage(): ?string
{
    $pages = [
        ['dashboard', 'index.php'],
        ['customers', 'customers/customers.php'],
        ['products', 'products/products.php'],
        ['categories', 'categories/categories.php'],
        ['orders', 'sales/orders/orders.php'],
        ['payments', 'sales/payments/payments.php'],
        ['returns', 'sales/returns/returns.php'],
        ['inventory', 'inventory/inventory.php'],
        ['notifications', 'notifications.php'],
        ['sales_reports', 'sales/reports/reports.php'],
        ['profit_tracker', 'sales/reports/profit-report.php'],
        ['user_management', 'users/users.php'],
        ['settings', 'settings.php?tab=roles'],
    ];

    foreach ($pages as [$module, $path]) {
        if (appUserCan($module)) {
            return getBaseUrl() . $path;
        }
    }

    if (appUserCan('trash') && appUserCan('products')) {
        return getBaseUrl() . 'products/trash.php';
    }

    return null;
}

$noAccessibleModules = false;
if (!empty($_SESSION['admin_id'])) {
    $landingPage = getPermittedLandingPage();
    if ($landingPage !== null) {
        header('Location: ' . $landingPage);
        exit();
    }
    $noAccessibleModules = true;
}

$csrfToken = $_SESSION['login_csrf'] ??= bin2hex(random_bytes(32));
$errorMessage = $noAccessibleModules
    ? 'Your account is signed in, but no modules are assigned. Contact an administrator.'
    : '';
$adminCountResult = $conn->query('SELECT COUNT(*) AS total FROM admins');
$needsInitialSetup = $adminCountResult !== false && (int) $adminCountResult->fetch_assoc()['total'] === 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';
    $email = trim($_POST['login_email'] ?? $_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    

    if (!is_string($postedToken) || !hash_equals($csrfToken, $postedToken)) {
        
        
        $errorMessage = 'Your session expired. Refresh the page and try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || !is_string($password)) {
        
   
        $errorMessage = 'The email or password is incorrect.';
    } else {
        $stmt = $conn->prepare(
            "SELECT a.id, a.name, a.email, a.password_hash, a.role_id, r.name AS role_name
             FROM admins a
             JOIN roles r ON r.id = a.role_id
             WHERE a.email = ? AND a.status = 'Active' AND r.is_active = 1
             LIMIT 1"
        );
        if (!$stmt) {
            error_log('Unable to prepare admin sign-in: ' . $conn->error);
            http_response_code(500);
            $errorMessage = 'Sign-in is temporarily unavailable.';

           
  
        } else {
            $stmt->bind_param('s', $email);
            if (!$stmt->execute()) {
                error_log('Unable to execute admin sign-in: ' . $stmt->error);
                http_response_code(500);
                $errorMessage = 'Sign-in is temporarily unavailable.';
            } else {
                $admin = $stmt->get_result()->fetch_assoc();
                if ($admin && password_verify($password, $admin['password_hash'])) {
                    session_regenerate_id(true);
                    $_SESSION = [];
                    $_SESSION['admin_id'] = (int) $admin['id'];
                    $_SESSION['admin_role_id'] = (int) $admin['role_id'];
                    $_SESSION['admin_email'] = $admin['email'];
                    $_SESSION['admin_name'] = $admin['name'];
                    unset($_SESSION['login_csrf']);
                    if (isTwoFactorEnabled($conn, (int) $admin['id'])) {
                        $_SESSION['admin_logged_in'] = false;
                        $_SESSION['2fa_pending'] = true;
                        $_SESSION['2fa_pending_admin_id'] = (int) $admin['id'];
                        $_SESSION['2fa_verified_admin_id'] = 0;
                        $_SESSION['2fa_verified_at'] = 0;
                        header('Location: ' . getBaseUrl() . 'verify-two-factor.php');
                        exit();
                    }
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['2fa_pending'] = false;
                    createAdminSession($conn, (int) $admin['id']);
                    $landingPage = getPermittedLandingPage();
                    if ($landingPage !== null) {
                        header('Location: ' . $landingPage);
                        exit();
                    }
                    $noAccessibleModules = true;
                    $errorMessage = 'Your account is signed in, but no modules are assigned. Contact an administrator.';
                } else {
                    $errorMessage = 'The email or password is incorrect.';
                }
            }
            $stmt->close();
        }
    }
}

$loginBase = getBaseUrl();
$language = appLanguage();
$themeName = appThemeName();
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($language, ENT_QUOTES, 'UTF-8') ?>" dir="<?= $language === 'ur' ? 'rtl' : 'ltr' ?>" data-theme="<?= htmlspecialchars($themeName, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Sign In</title>
    <link href="<?= htmlspecialchars($loginBase, ENT_QUOTES, 'UTF-8') ?>assets/css/style.css" rel="stylesheet">
    <script src="<?= htmlspecialchars($loginBase, ENT_QUOTES, 'UTF-8') ?>assets/js/i18n.js" defer></script>
</head>
<body class="login-page theme-<?= htmlspecialchars($themeName, ENT_QUOTES, 'UTF-8') ?>" data-theme="<?= htmlspecialchars($themeName, ENT_QUOTES, 'UTF-8') ?>">
    <main class="login-card">
        <h1>Admin Sign In</h1>
        <?php if ($errorMessage !== ''): ?>
            <p class="login-error" role="alert"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <?php if (isset($_GET['setup']) && $_GET['setup'] === 'complete'): ?>
            <p role="status">Administrator created. Sign in with the account you just set up.</p>
        <?php endif; ?>
        <?php if ($noAccessibleModules): ?>
            <p><a href="<?= htmlspecialchars($loginBase, ENT_QUOTES, 'UTF-8') ?>logout.php">Sign out</a></p>
        <?php else: ?>
            <form method="post" action="<?= htmlspecialchars($loginBase, ENT_QUOTES, 'UTF-8') ?>login.php" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <label for="login_email">Email</label>
                <input id="login_email" name="login_email" type="email" autocomplete="username" required>
                <label for="password">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
                <button type="submit">Sign In</button>
            </form>
        <?php endif; ?>
        <?php if ($needsInitialSetup): ?>
            <p><a href="<?= htmlspecialchars($loginBase, ENT_QUOTES, 'UTF-8') ?>setup-admin.php">Set up the first administrator</a></p>
        <?php endif; ?>
    </main>
</body>
</html>