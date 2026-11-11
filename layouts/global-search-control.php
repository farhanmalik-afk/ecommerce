<?php
$searchScriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$searchAppBase = str_replace('\\', '/', rtrim(dirname($searchScriptPath), '/\\'));
// Yahan 'users', 'layouts', aur 'includes' folders add kar diye hain taake sub-folder se base path bilkul theek nikle
$searchModuleFolders = ['customers', 'products', 'orders', 'inventory', 'sales', 'payments', 'returns', 'pos', 'categories', 'reports', 'users', 'layouts', 'includes'];
while (in_array(basename($searchAppBase), $searchModuleFolders, true)) {
    $searchAppBase = str_replace('\\', '/', rtrim(dirname($searchAppBase), '/\\'));
}
$searchAppBase = $searchAppBase === '.' ? '' : $searchAppBase;
?>
<div class="global-search" data-global-search data-endpoint="<?= htmlspecialchars($searchAppBase . '/global-search.php', ENT_QUOTES, 'UTF-8') ?>">
    <i class="bi bi-search global-search-icon" aria-hidden="true"></i>
    <input
        class="form-control search-input"
        type="search"
        placeholder="Search products, customers..."
        aria-label="Search products, customers, orders, and categories"
        aria-autocomplete="list"
        aria-controls="global-search-results"
        aria-expanded="false"
        autocomplete="off"
        data-global-search-input
    >
    <div
        class="global-search-results"
        id="global-search-results"
        role="listbox"
        aria-label="Search results"
        aria-live="polite"
        hidden
        data-global-search-results
    ></div>
</div>
<script src="<?= htmlspecialchars($searchAppBase . '/assets/js/global-search.js', ENT_QUOTES, 'UTF-8') ?>" defer></script>