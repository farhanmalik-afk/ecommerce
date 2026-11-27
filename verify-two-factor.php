<?php
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['2fa_pending']) || $_SESSION['2fa_pending'] !== true) {
    header('Location: ' . getBaseUrl() . 'login.php');
    exit();
}

$adminId = (int) ($_SESSION['2fa_pending_admin_id'] ?? $_SESSION['admin_id'] ?? 0);
$stmt = $conn->prepare(
    "SELECT a.id, a.role_id, a.name, a.email, r.name AS role_name
     FROM admins a JOIN roles r ON r.id = a.role_id
     WHERE a.id = ? AND a.status = 'Active' AND r.is_active = 1 LIMIT 1"
);
$stmt->bind_param('i', $adminId);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$admin) {
    $_SESSION = [];
    header('Location: ' . getBaseUrl() . 'login.php');
    exit();
}

if (!isTwoFactorEnabled($conn, $adminId)) {
    $_SESSION['2fa_pending'] = false;
    $_SESSION['admin_logged_in'] = true;
    createAdminSession($conn, $adminId);
    header('Location: ' . getPermittedLandingPageFallback($conn));
    exit();
}

$errorMessage = '';
$csrfToken = $_SESSION['2fa_csrf'] ??= bin2hex(random_bytes(32));
$lockedKey = '2fa_attempt_verify_locked_until';
$lockedUntil = (int) ($_SESSION[$lockedKey] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrfToken, $postedToken)) {
        $errorMessage = 'Your verification session expired. Refresh and try again.';
    } elseif ($lockedUntil > time()) {
        $minutes = (int) ceil(($lockedUntil - time()) / 60);
        $errorMessage = 'Too many attempts. Try again in ' . $minutes . ' minute(s).';
    } elseif (!twoFactorOtpRateLimit('verify')) {
        $errorMessage = 'Too many attempts. Try again in 15 minutes.';
    } else {
        $code = trim((string) ($_POST['verification_code'] ?? ''));
        $verified = verifyTwoFactorTotp($conn, $adminId, $code);
        if (!$verified) {
            $verified = verifyTwoFactorBackupCode($conn, $adminId, $code);
        }
        if ($verified) {
            resetTwoFactorOtpRateLimit('verify');
            session_regenerate_id(true);
            $_SESSION['admin_id'] = $adminId;
            $_SESSION['admin_role_id'] = (int) ($admin['role_id'] ?? 0);
            $_SESSION['admin_email'] = $admin['email'];
            $_SESSION['admin_name'] = $admin['name'];
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['2fa_pending'] = false;
            unset($_SESSION['2fa_pending_admin_id'], $_SESSION['2fa_csrf']);
            $_SESSION['2fa_verified_admin_id'] = $adminId;
            $_SESSION['2fa_verified_at'] = time();
            createAdminSession($conn, $adminId);
            header('Location: ' . getPermittedLandingPageFallback($conn));
            exit();
        }
        $errorMessage = 'The verification code is invalid.';
    }
}

function getPermittedLandingPageFallback(mysqli $conn): string
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
    return getBaseUrl() . 'login.php';
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
    <title>Two-Factor Verification</title>
    <link href="<?= htmlspecialchars($loginBase, ENT_QUOTES, 'UTF-8') ?>assets/css/style.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="login-page theme-<?= htmlspecialchars($themeName, ENT_QUOTES, 'UTF-8') ?>" data-theme="<?= htmlspecialchars($themeName, ENT_QUOTES, 'UTF-8') ?>">
    <main class="login-card">
        <h1><i class="bi bi-shield-lock me-2"></i>Two-Factor Verification</h1>
        <p class="text-muted">Enter the 6-digit code from Google or Microsoft Authenticator. You can also use an unused backup code.</p>
        <?php if ($errorMessage !== ''): ?>
            <p class="login-error" role="alert"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <form method="post" action="<?= htmlspecialchars($loginBase, ENT_QUOTES, 'UTF-8') ?>verify-two-factor.php" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <label for="verification_code">Authenticator or Backup Code</label>
            <input id="verification_code" name="verification_code" type="text" autocomplete="one-time-code" inputmode="text" maxlength="12" required autofocus>
            <button type="submit">Verify &amp; Continue</button>
        </form>
        <p><a href="<?= htmlspecialchars($loginBase, ENT_QUOTES, 'UTF-8') ?>logout.php" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">Cancel and sign out</a></p>
        <form id="logout-form" method="post" action="<?= htmlspecialchars($loginBase, ENT_QUOTES, 'UTF-8') ?>logout.php" class="d-none">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
        </form>
    </main>
</body>
</html>
