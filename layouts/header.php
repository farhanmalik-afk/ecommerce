<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    require_once __DIR__ . '/../includes/security.php';
    secure_session_start();
}
$scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$appBase = str_replace('\\', '/', rtrim(dirname($scriptPath), '/\\'));
while (in_array(basename($appBase), ['users', 'customers', 'products', 'orders', 'inventory', 'sales', 'payments', 'returns', 'pos', 'categories', 'reports', 'layouts', 'includes'], true)) {
    $appBase = str_replace('\\', '/', rtrim(dirname($appBase), '/\\'));
}
$pageTitle = $pageTitle ?? 'Page';
$parent = $parent ?? ['Customers', 'customers.php'];
$canViewUserManagement = appUserCan('user_management');
$topbarUserName = trim((string) ($_SESSION['admin_name'] ?? ''));
if ($topbarUserName === '') {
    $topbarUserName = trim((string) ($_SESSION['admin_email'] ?? 'Admin'));
}

$themeName = appThemeName();
$language = appLanguage();

// Load global translations if file exists
if (file_exists(__DIR__ . '/../lang.php')) {
    include_once __DIR__ . '/../lang.php';
} elseif (file_exists(__DIR__ . '/lang.php')) {
    include_once __DIR__ . '/lang.php';
}

$currentLang = $language;
$langData = isset($translations[$currentLang]) ? $translations[$currentLang] : ($translations['en'] ?? []);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($language, ENT_QUOTES, 'UTF-8') ?>" dir="<?= $language === 'ur' ? 'rtl' : 'ltr' ?>" data-theme="<?= htmlspecialchars($themeName, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> Admin Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=optional" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&display=optional" rel="stylesheet">
    <!-- Asset Path for style.css -->
    <link href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>" rel="stylesheet">
    <style>
        .app .topbar {
            min-height: 68px;
            box-sizing: border-box;
        }

        @media (min-width: 992px) {
            .app {
                display: grid;
                grid-template-columns: 270px minmax(0, 1fr);
            }

            .app-sidebar {
                width: 270px;
                min-width: 270px;
                max-width: 270px;
                flex-shrink: 0;
                box-sizing: border-box;
            }
        }

        @media (min-width: 768px) {
            .app .topbar {
                grid-template-columns: auto minmax(180px, 1fr) auto auto auto;
            }

            .app .topbar-utilities,
            .app .topbar-end {
                display: contents;
            }

            .app .topbar-start {
                grid-column: 1;
                grid-row: 1;
            }

            .app .topbar-search {
                grid-column: 2;
                grid-row: 1;
                width: 100%;
                max-width: 340px;
                justify-self: center;
            }

            .app .topbar-actions {
                grid-column: 3;
                grid-row: 1;
            }

            .app .topbar-end .header-pos-btn {
                grid-column: 4;
                grid-row: 1;
                justify-self: end;
            }

            .app .topbar-user {
                grid-column: 5;
                grid-row: 1;
                justify-self: end;
            }

            .app .topbar-profile-logout {
                display: flex !important;
            }
        }

        .app-sidebar .side-nav .nav-link > .bi {
            display: inline-flex;
            flex: 0 0 18px;
            width: 18px;
            height: 18px;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }

    </style>
    <script src="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/assets/js/i18n.js" defer></script>
    <link rel="icon" href="data:,">

</head>
<body class="<?= basename($_SERVER['SCRIPT_NAME'] ?? '') === 'index.php' ? 'dashboard-theme ' : '' ?>theme-<?= htmlspecialchars($themeName, ENT_QUOTES, 'UTF-8') ?>" data-theme="<?= htmlspecialchars($themeName, ENT_QUOTES, 'UTF-8') ?>" data-bs-theme="light">
<div class="app">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main class="app-main">
        <?php include __DIR__ . '/topbar.php'; ?>