<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$dashboardBase = str_replace('\\', '/', rtrim(dirname($scriptPath), '/\\'));
// Yahan 'users' folder add kar diya hai taake sub-folder se base path bilkul theek nikle
$moduleFolders = ['customers', 'products', 'orders', 'inventory', 'sales', 'payments', 'returns', 'pos', 'categories', 'reports', 'users', 'layouts', 'includes'];
while (in_array(basename($dashboardBase), $moduleFolders, true)) {
    $dashboardBase = str_replace('\\', '/', rtrim(dirname($dashboardBase), '/\\'));
}
$dashboardHref = $dashboardBase . '/index.php';
$posHref = $dashboardBase . '/sales/pos/pos.php';
$isPosPage = basename($scriptPath) === 'pos.php';
$notificationCountResult = $conn->query('SELECT COUNT(*) AS total FROM notifications WHERE is_read = 0 AND is_deleted = 0');
if ($notificationCountResult === false) {
    throw new RuntimeException('Unable to load unread notifications: ' . $conn->error);
}
$notificationCount = (int) ($notificationCountResult->fetch_assoc()['total'] ?? 0);
$notificationResult = $conn->query('SELECT id, title, message, type, is_read, created_at FROM notifications WHERE is_read = 0 AND is_deleted = 0 ORDER BY created_at DESC, id DESC LIMIT 8');
if ($notificationResult === false) {
    throw new RuntimeException('Unable to load notifications: ' . $conn->error);
}
$notifications = $notificationResult->fetch_all(MYSQLI_ASSOC);
$notificationCsrfToken = $_SESSION['notification_csrf'] ??= bin2hex(random_bytes(32));
$notificationTypeIcons = [
    'order' => 'bi-receipt',
    'stock' => 'bi-exclamation-triangle',
    'customer' => 'bi-person-plus',
    'return' => 'bi-arrow-return-left',
];
?>
<header class="topbar">
    <div class="d-flex align-items-center gap-3">
        <?php if (($showSidebarToggle ?? true) === true): ?>
            <button class="btn btn-light d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-label="Open menu">
                <i class="bi bi-list"></i>
            </button>
        <?php endif; ?>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="btn btn-sm btn-outline-dark rounded-pill fw-semibold text-decoration-none d-inline-flex align-items-center header-breadcrumb-btn" href="<?= htmlspecialchars($dashboardHref, ENT_QUOTES, 'UTF-8') ?>">
                        <i class="bi bi-house-door-fill me-1"></i>Dashboard
                    </a>
                </li>
                <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($pageTitle ?? 'Page', ENT_QUOTES, 'UTF-8') ?></li>
            </ol>
        </nav>
        <?php if (!$isPosPage && ($appSettings['pos_enabled'] ?? '1') === '1' && appUserCan('pos')): ?>
            <a class="header-pos-btn" href="<?= htmlspecialchars($posHref, ENT_QUOTES, 'UTF-8') ?>">
                <i class="bi bi-receipt-cutoff" aria-hidden="true"></i>
                <span>Open POS</span>
                <i class="bi bi-arrow-up-right header-pos-btn-arrow" aria-hidden="true"></i>
            </a>
        <?php endif; ?>
    </div>
    <div class="topbar-tools d-flex align-items-center gap-3">
        <?php include __DIR__ . '/global-search-control.php'; ?>
        <?php if (appUserCan('settings')): ?><a href="<?= htmlspecialchars($dashboardBase, ENT_QUOTES, 'UTF-8') ?>/settings.php" class="topbar-action" aria-label="Settings" title="Settings"><i class="bi bi-gear"></i></a><?php endif; ?>
        <div class="dropdown">
            <button type="button" class="topbar-action notification-action<?= $notificationCount > 0 ? ' has-notifications' : '' ?>" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications<?= $notificationCount > 0 ? ', ' . $notificationCount . ' unread' : '' ?>" title="Notifications">
                <i class="bi bi-bell"></i>
                <?php if ($notificationCount > 0): ?>
                    <span class="notification-count"><?= $notificationCount > 99 ? '99+' : $notificationCount ?></span>
                <?php endif; ?>
            </button>
            <div class="dropdown-menu dropdown-menu-end notification-menu p-0" data-mark-read-url="<?= htmlspecialchars($dashboardBase, ENT_QUOTES, 'UTF-8') ?>/notifications/mark-read.php" data-csrf-token="<?= htmlspecialchars($notificationCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <div class="notification-menu-header">
                    <strong>Notifications</strong>
                    <span class="badge rounded-pill" data-unread-count><?= $notificationCount ?></span>
                </div>
                <?php if ($notifications): ?>
                    <div class="notification-list">
                        <?php foreach ($notifications as $notification): ?>
                            <?php $notificationType = $notification['type']; ?>
                            <a class="notification-item<?= (int) $notification['is_read'] === 0 ? ' is-unread' : '' ?>"
                               href="<?= htmlspecialchars($dashboardBase . '/notifications.php?focus=' . (int) $notification['id'], ENT_QUOTES, 'UTF-8') ?>"
                               data-notification-id="<?= (int) $notification['id'] ?>">
                                <span class="notification-item-icon"><i class="bi <?= htmlspecialchars($notificationTypeIcons[$notificationType] ?? 'bi-bell', ENT_QUOTES, 'UTF-8') ?>"></i></span>
                                <span class="notification-item-copy">
                                    <strong><?= htmlspecialchars($notification['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <small><?= htmlspecialchars($notification['message'], ENT_QUOTES, 'UTF-8') ?></small>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <a class="notification-footer" href="<?= htmlspecialchars($dashboardBase, ENT_QUOTES, 'UTF-8') ?>/notifications.php">View all notifications</a>
                <?php endif; ?>
                <?php if (!$notifications): ?>
                    <p class="notification-empty"><i class="bi bi-check-circle me-2"></i>No unread notifications.</p>
                <?php endif; ?>
                <p class="notification-action-error d-none" role="alert"></p>
            </div>
        </div>
        <a href="#" class="topbar-action" aria-label="Account" title="Account"><i class="bi bi-person-circle"></i></a>
    </div>
</header>