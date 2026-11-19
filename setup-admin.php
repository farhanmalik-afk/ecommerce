<?php
require_once __DIR__ . '/config.php';

$adminCountResult = $conn->query('SELECT COUNT(*) AS total FROM admins');
if ($adminCountResult === false) {
    error_log('Unable to check first-run admin setup: ' . $conn->error);
    http_response_code(500);
    exit('Initial administrator setup is unavailable.');
}
if ((int) $adminCountResult->fetch_assoc()['total'] > 0) {
    header('Location: ' . getBaseUrl() . 'login.php');
    exit();
}

$csrfToken = $_SESSION['setup_csrf'] ??= bin2hex(random_bytes(32));
$errorMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $postedToken = $_POST['csrf_token'] ?? '';

    if (!is_string($postedToken) || !hash_equals($csrfToken, $postedToken)) {
        $errorMessage = 'Your session expired. Refresh the page and try again.';
    } elseif ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Enter a name and a valid email address.';
    } elseif (!is_string($password) || strlen($password) < 12) {
        $errorMessage = 'The password must contain at least 12 characters.';
    } else {
        $roleResult = $conn->query('SELECT id FROM roles WHERE id = 1 LIMIT 1');
        if ($roleResult === false || $roleResult->num_rows !== 1) {
            http_response_code(503);
            $errorMessage = 'The Super Admin role is not available. Contact the database administrator.';
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $status = 'Active';
            $avatar = 'default-avatar.png';
            $roleId = 1;
            try {
                $stmt = $conn->prepare(
                    'INSERT INTO admins (name, email, password_hash, role_id, status, avatar) VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->bind_param('sssiss', $name, $email, $passwordHash, $roleId, $status, $avatar);
                $stmt->execute();
                $stmt->close();
                unset($_SESSION['setup_csrf']);
                header('Location: ' . getBaseUrl() . 'login.php?setup=complete');
                exit();
            } catch (mysqli_sql_exception $exception) {
                error_log('Unable to create the initial administrator: ' . $exception->getMessage());
                $errorMessage = 'The administrator account could not be created.';
            }
        }
    }
}
$setupBase = getBaseUrl();
$language = appLanguage();
$themeName = appThemeName();
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($language, ENT_QUOTES, 'UTF-8') ?>" dir="<?= $language === 'ur' ? 'rtl' : 'ltr' ?>" data-theme="<?= htmlspecialchars($themeName, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Set Up Administrator</title>
    <link href="<?= htmlspecialchars($setupBase, ENT_QUOTES, 'UTF-8') ?>assets/css/style.css" rel="stylesheet">
    <script src="<?= htmlspecialchars($setupBase, ENT_QUOTES, 'UTF-8') ?>assets/js/i18n.js" defer></script>
</head>
<body class="login-page theme-<?= htmlspecialchars($themeName, ENT_QUOTES, 'UTF-8') ?>" data-theme="<?= htmlspecialchars($themeName, ENT_QUOTES, 'UTF-8') ?>">
    <main class="login-card">
        <h1>Set Up Administrator</h1>
        <?php if ($errorMessage !== ''): ?>
            <p class="login-error" role="alert"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <form method="post" action="<?= htmlspecialchars($setupBase, ENT_QUOTES, 'UTF-8') ?>setup-admin.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <label for="name">Name</label>
            <input id="name" name="name" autocomplete="name" required>
            <label for="email">Email</label>
            <input id="email" name="email" type="email" autocomplete="email" required>
            <label for="password">Password (at least 12 characters)</label>
            <input id="password" name="password" type="password" autocomplete="new-password" minlength="12" required>
            <button type="submit">Create Administrator</button>
        </form>
    </main>
</body>
</html>
