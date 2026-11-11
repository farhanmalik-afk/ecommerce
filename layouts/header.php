<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$appBase = str_replace('\\', '/', rtrim(dirname($scriptPath), '/\\'));
while (in_array(basename($appBase), ['users', 'customers', 'products', 'orders', 'inventory', 'sales', 'payments', 'returns', 'pos', 'categories', 'reports', 'layouts', 'includes'], true)) {
    $appBase = str_replace('\\', '/', rtrim(dirname($appBase), '/\\'));
}
$pageTitle = $pageTitle ?? 'Page';
$parent = $parent ?? ['Customers', 'customers.php'];
$themeName = in_array($appSettings['theme'] ?? 'luxury', ['luxury', 'light', 'dark'], true) ? $appSettings['theme'] : 'luxury';
$language = ($appSettings['language'] ?? 'en') === 'ur' ? 'ur' : 'en';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($language, ENT_QUOTES, 'UTF-8') ?>"<?= $language === 'ur' ? ' dir="rtl"' : '' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/assets/css/style.css" rel="stylesheet">
    <link rel="icon" href="data:,">

</head>
<body class="luxury-theme theme-<?= htmlspecialchars($themeName, ENT_QUOTES, 'UTF-8') ?><?= $pageTitle === 'Dashboard' ? ' dashboard-theme' : '' ?>" data-bs-theme="<?= $themeName === 'dark' ? 'dark' : 'light' ?>">
<div class="app">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main class="app-main">
        <?php include __DIR__ . '/topbar.php'; ?>
        <!-- Note: Main aur app div yahan khule rahenge, taake page ka content inke andar aaye -->

        <!-- Global SweetAlert Session Handler -->
        <?php if (isset($_SESSION['swal_success'])): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: '<?php echo addslashes($_SESSION['swal_success']); ?>',
                        timer: 2000,
                        showConfirmButton: false
                    });
                });
            </script>
            <?php unset($_SESSION['swal_success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['swal_error'])): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: '<?php echo addslashes($_SESSION['swal_error']); ?>',
                        confirmButtonColor: '#dc3545'
                    });
                });
            </script>
            <?php unset($_SESSION['swal_error']); ?>
        <?php endif; ?>