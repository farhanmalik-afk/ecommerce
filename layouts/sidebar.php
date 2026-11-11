<?php
$scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$appBase = str_replace('\\', '/', rtrim(dirname($scriptPath), '/\\'));
$moduleFolders = ['customers', 'products', 'orders', 'inventory', 'sales', 'payments', 'returns', 'pos', 'categories', 'reports', 'users', 'layouts', 'includes'];

while (in_array(basename($appBase), $moduleFolders, true)) {
    $appBase = str_replace('\\', '/', rtrim(dirname($appBase), '/\\'));
}

$currentPage = basename($scriptPath);
$currentDirectory = basename(dirname($scriptPath));
$activeModule = match ($currentPage) {
    'trash.php' => 'trash',
    'profit-report.php' => 'profit-report',
    'shift-summary.php' => 'reports',
    'settings.php' => 'settings',
    'users.php', 'add-user.php', 'edit-user.php', 'roles.php', 'activity-logs.php' => 'user_management',
    default => match ($currentDirectory) {
        'customers' => 'customers',
        'products' => 'products',
        'categories' => 'categories',
        'orders' => 'orders',
        'payments' => 'payments',
        'returns' => 'returns',
        'inventory' => 'inventory',
        'reports' => 'reports',
        'pos' => 'pos',
        'users' => 'user_management',
        default => match ($currentPage) {
            'customers.php', 'add-customers.php', 'customer-details.php', 'reviews.php' => 'customers',
            'products.php', 'add-product.php', 'edit-product.php' => 'products', 
            'categories.php', 'add-category.php', 'edit-category.php' => 'categories',
            'orders.php', 'add-order.php', 'order-details.php', 'invoice.php' => 'orders',
            'payments.php' => 'payments',
            'returns.php' => 'returns',
            'inventory.php', 'stock-adjust.php', 'stock-history.php', 'suppliers.php' => 'inventory',
            'reports.php' => 'reports', 'profit-report.php' => 'profit-report',
            'notifications.php' => 'notifications',
            'profile.php' => 'profile',
            'virtual-reality.php' => 'virtual-reality',
            'pos.php' => 'pos',
            'index.php' => 'dashboard',
            default => '',
        },
    },
};
?>
<aside class="app-sidebar">
  <div class="offcanvas-lg offcanvas-start" tabindex="-1" id="sidebar" aria-label="Main navigation">
    <div class="sidebar-card">

      <div class="d-flex justify-content-between align-items-center mb-4">
        <a href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/index.php" class="brand">
          <?php if (!empty($appSettings['logo'])): ?>
            <img src="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/uploads/<?= rawurlencode($appSettings['logo']) ?>" alt="" style="max-width:42px;max-height:36px;object-fit:contain">
          <?php else: ?>
            <i class="bi bi-grid-1x2"></i>
          <?php endif; ?>
          <span>Admin Dashboard</span>
        </a>
        <button type="button" class="btn-close d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Close"></button>
      </div>

     <ul class="nav flex-column side-nav">
        <?php
        $navigation = [
            ['dashboard', 'Dashboard', 'bi-speedometer2', '/index.php'],
            ['customers', 'Customers', 'bi-people', '/customers/customers.php'],
            ['products', 'Products', 'bi-box-seam', '/products/products.php'],
            ['categories', 'Categories', 'bi-tags', '/categories/categories.php'],
            ['orders', 'Orders', 'bi-bag-check', '/sales/orders/orders.php'],
            ['payments', 'Payments', 'bi-credit-card', '/sales/payments/payments.php'],
            ['returns', 'Returns', 'bi-arrow-return-left', '/sales/returns/returns.php'],
            ['inventory', 'Inventory', 'bi-boxes', '/inventory/inventory.php'],
            ['notifications', 'Notifications', 'bi-bell', '/notifications.php'],
            ['reports', 'Sales Reports', 'bi-bar-chart-line', '/sales/reports/reports.php'],
            ['profit-report', 'Profit Tracker', 'bi-graph-up-arrow', '/sales/reports/profit-report.php'],
            ['user_management', 'User Management', 'bi-shield-lock', '/users/users.php'],
            ['trash', 'Trash Bin', 'bi-trash', '/products/trash.php'],
            ['settings', 'Settings', 'bi-gear', '/settings.php'],
        ];
        foreach ($navigation as [$module, $label, $icon, $path]):
            if ($module === 'user_management') {
                // Bypass permission check for testing/visibility
            } elseif (function_exists('appUserCan') && !appUserCan($module)) {
                continue;
            }
            if ($module === 'pos' && ($appSettings['pos_enabled'] ?? '1') !== '1') {
                continue;
            }
        ?>
            <li>
                <a class="nav-link<?= $activeModule === $module ? ' active' : '' ?>" href="<?= htmlspecialchars($appBase . $path, ENT_QUOTES, 'UTF-8') ?>">
                    <i class="bi <?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>"></i><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                </a>
            </li>
        <?php endforeach; ?>
     </ul>

    </div>
  </div>
</aside>
<script>
  document.addEventListener("DOMContentLoaded", function() {
      const activeLink = document.querySelector('.app-sidebar .nav-link.active, .sidebar .nav-link.active, .nav-link.active');
      if (activeLink) {
          activeLink.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
  });
</script>