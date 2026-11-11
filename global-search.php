<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

function respondWithSearchResults(
    array $results,
    int $statusCode = 200,
    string $searchMode = 'local_intent'
): void {
    http_response_code($statusCode);
    echo json_encode(
        ['results' => $results, 'search_mode' => $searchMode],
        JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

function normalizeGlobalSearchText(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? '';
    return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
}

function getGlobalSearchTokens(string $query): array
{
    $synonyms = [
        'items' => 'product', 'item' => 'product', 'goods' => 'product',
        'merchandise' => 'product', 'catalog' => 'product', 'catalogue' => 'product',
        'producs' => 'product', 'produts' => 'product', 'prducts' => 'product',
        'clients' => 'customer', 'client' => 'customer', 'shoppers' => 'customer',
        'shopper' => 'customer', 'buyers' => 'customer', 'buyer' => 'customer',
        'custmers' => 'customer', 'custumer' => 'customer', 'customres' => 'customer',
        'purchases' => 'order', 'purchase' => 'order', 'purchased' => 'order', 'bought' => 'order',
        'buying' => 'order', 'placed' => 'order', 'ordrs' => 'order',
        'orer' => 'order', 'transactions' => 'payment', 'transaction' => 'payment',
        'refunds' => 'return', 'refund' => 'return', 'exchanges' => 'return',
        'latest' => 'recent', 'newest' => 'recent', 'recently' => 'recent',
        'incomplete' => 'pending', 'unfinished' => 'pending',
        'unfulfilled' => 'pending', 'awaiting' => 'pending',
        'fulfilled' => 'delivered', 'completed' => 'delivered',
        'complete' => 'delivered', 'delevered' => 'delivered',
        'deliverd' => 'delivered', 'inventry' => 'inventory',
        'invntory' => 'inventory', 'warehousing' => 'inventory',
        'shortages' => 'shortage', 'depleting' => 'deplete',
        'depleted' => 'deplete', 'expensive' => 'highprice',
        'costliest' => 'highprice', 'priciest' => 'highprice',
        'profits' => 'profit', 'earnings' => 'profit',
        'revenues' => 'sales', 'sale' => 'sales', 'sales' => 'sales', 'sold' => 'sales',
    ];
    $stopWords = [
        'a', 'an', 'and', 'are', 'am', 'be', 'been', 'being', 'can', 'could',
        'do', 'does', 'did', 'for', 'from', 'have', 'has', 'had', 'how',
        'i', 'in', 'is', 'it', 'me', 'my', 'of', 'please', 'show', 'the',
        'them', 'to', 'want', 'what', 'which', 'who', 'whom', 'with',
        'would', 'you', 'your', 'information', 'info', 'all', 'many',
    ];
    $tokens = [];
    foreach (explode(' ', normalizeGlobalSearchText($query)) as $token) {
        if ($token === '' || in_array($token, $stopWords, true)) {
            continue;
        }

        if (isset($synonyms[$token])) {
            $token = $synonyms[$token];
        } elseif (mb_strlen($token, 'UTF-8') >= 5) {
            foreach (['product', 'customer', 'order', 'delivered', 'inventory'] as $knownTerm) {
                if (levenshtein($token, $knownTerm) === 1) {
                    $token = $knownTerm;
                    break;
                }
            }
        }

        if (mb_strlen($token, 'UTF-8') > 3 && str_ends_with($token, 's') && !str_ends_with($token, 'ss')) {
            $token = substr($token, 0, -1);
        }
        if (!in_array($token, $tokens, true)) {
            $tokens[] = $token;
        }
    }

    return $tokens;
}

function detectGlobalSearchIntents(string $query, array $tokens): array
{
    $normalized = ' ' . normalizeGlobalSearchText($query) . ' ';
    $has = static fn(string $token): bool => in_array($token, $tokens, true);
    $intents = [];

    $negatedCompletion = str_contains($normalized, ' not completed ')
        || str_contains($normalized, ' not complete ')
        || str_contains($normalized, ' unfinished ')
        || str_contains($normalized, ' incomplete ');
    if ($has('low') && ($has('stock') || $has('inventory'))
        || $has('shortage') || $has('deplete')
        || str_contains($normalized, ' running out ')
        || str_contains($normalized, ' running low ')
        || str_contains($normalized, ' out of stock ')
        || str_contains($normalized, ' stock alert ')
        || str_contains($normalized, ' stock warning ')
        || ($has('stock') && $has('alert'))) {
        $intents[] = 'low_stock';
    }
    if ($has('product') || in_array('highprice', $tokens, true)) {
        $intents[] = 'product';
    }
    if ($has('customer') || str_contains($normalized, ' who purchased ')) {
        $intents[] = 'customer';
    }
    if ($has('order') || $has('pending') || $has('delivered') || $has('recent')
        || str_contains($normalized, ' who purchased ')
        || str_contains($normalized, ' recent sale ')) {
        $intents[] = 'order';
    }
    if ($has('pending') || $negatedCompletion) {
        $intents[] = 'pending';
    }
    if (!$negatedCompletion && $has('delivered')) {
        $intents[] = 'delivered';
    }
    if ($has('recent') || str_contains($normalized, ' recently ')) {
        $intents[] = 'recent';
    }
    if ($has('inventory') || $has('stock') || $has('low') || $has('deplete') || $has('shortage')) {
        $intents[] = 'inventory';
    }
    if ($has('highprice') || $has('price') || $has('expensive') || $has('highest')) {
        $intents[] = 'high_price';
        $intents[] = 'product';
    }
    if ($has('sale') || $has('sales') || $has('profit') || $has('revenue') || $has('report')) {
        $intents[] = 'sales';
    }
    if ($has('payment')) {
        $intents[] = 'payment';
    }
    if ($has('return')) {
        $intents[] = 'returns';
    }
    if ($has('notification') || $has('alert')) {
        $intents[] = 'notifications';
    }
    if ($has('dashboard') || $has('overview') || $has('home')) {
        $intents[] = 'dashboard';
    }

    return array_values(array_unique($intents));
}

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

$rawQuery = $_GET['q'] ?? '';
if (!is_string($rawQuery)) {
    respondWithSearchResults([], 400, 'error');
}

$query = trim($rawQuery);
if ($query === '' || mb_strlen($query, 'UTF-8') > 160) {
    respondWithSearchResults([]);
}

$tokens = getGlobalSearchTokens($query);
$intents = detectGlobalSearchIntents($query, $tokens);
$scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$appBase = str_replace('\\', '/', rtrim(dirname($scriptPath), '/\\'));
$appBase = $appBase === '.' ? '' : $appBase;
$resultsByKey = [];
$semanticAvailable = false;

$addResult = static function (
    string $key,
    array $result,
    float $score,
    array $matchTerms = []
) use (&$resultsByKey): void {
    $result['rank_score'] = $score;
    $result['match_terms'] = array_values(array_unique($matchTerms));
    if (!isset($resultsByKey[$key]) || $score > $resultsByKey[$key]['rank_score']) {
        $resultsByKey[$key] = $result;
    }
};

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    define('APP_JSON_RESPONSE', true);
    require __DIR__ . '/config.php';
    require __DIR__ . '/semantic-search.php';

    $pages = [
        ['key' => 'dashboard', 'name' => 'Dashboard', 'detail' => 'Main overview', 'path' => '/index.php', 'icon' => 'bi-speedometer2', 'intents' => ['dashboard']],
        ['key' => 'products', 'name' => 'Products Management', 'detail' => 'Browse and manage the product catalog', 'path' => '/products/products.php', 'icon' => 'bi-box-seam', 'intents' => ['product']],
        ['key' => 'all-products', 'name' => 'All Products', 'detail' => 'View all products in the catalog', 'path' => '/products/products.php', 'icon' => 'bi-grid', 'intents' => ['product']],
        ['key' => 'add-product', 'name' => 'Add New Product', 'detail' => 'Create a product in the catalog', 'path' => '/products/add-product.php', 'icon' => 'bi-plus-square', 'intents' => ['product']],
        ['key' => 'product-inventory', 'name' => 'Product Inventory', 'detail' => 'Review product stock levels', 'path' => '/inventory/inventory.php', 'icon' => 'bi-boxes', 'intents' => ['product', 'inventory']],
        ['key' => 'low-stock', 'name' => 'Low Stock Products', 'detail' => 'Products with five or fewer units', 'path' => '/inventory/inventory.php?stock_filter=low_stock', 'icon' => 'bi-exclamation-triangle', 'intents' => ['low_stock']],
        ['key' => 'inventory', 'name' => 'Inventory Management', 'detail' => 'Manage stock levels and inventory', 'path' => '/inventory/inventory.php', 'icon' => 'bi-boxes', 'intents' => ['inventory', 'low_stock']],
        ['key' => 'stock-alerts', 'name' => 'Stock Alerts', 'detail' => 'Review low and out-of-stock products', 'path' => '/sales/reports/reports.php', 'icon' => 'bi-bell', 'intents' => ['low_stock']],
        ['key' => 'customers', 'name' => 'Customers Management', 'detail' => 'Browse the customer directory', 'path' => '/customers/customers.php', 'icon' => 'bi-people', 'intents' => ['customer']],
        ['key' => 'all-customers', 'name' => 'All Customers', 'detail' => 'View all registered customers', 'path' => '/customers/customers.php', 'icon' => 'bi-person-lines-fill', 'intents' => ['customer']],
        ['key' => 'add-customer', 'name' => 'Add New Customer', 'detail' => 'Create a customer record', 'path' => '/customers/add-customers.php', 'icon' => 'bi-person-plus', 'intents' => ['customer']],
        ['key' => 'categories', 'name' => 'Categories', 'detail' => 'Manage product categories', 'path' => '/categories/categories.php', 'icon' => 'bi-tags', 'intents' => ['product']],
        ['key' => 'orders', 'name' => 'Orders Management', 'detail' => 'Browse and manage customer orders', 'path' => '/sales/orders/orders.php', 'icon' => 'bi-bag-check', 'intents' => ['order']],
        ['key' => 'pending-orders', 'name' => 'Pending Orders', 'detail' => 'Orders waiting to be processed', 'path' => '/sales/orders/orders.php?status=Pending', 'icon' => 'bi-hourglass-split', 'intents' => ['pending']],
        ['key' => 'processing-orders', 'name' => 'Processing Orders', 'detail' => 'Orders currently being fulfilled', 'path' => '/sales/orders/orders.php?status=Processing', 'icon' => 'bi-arrow-repeat', 'intents' => ['pending']],
        ['key' => 'delivered-orders', 'name' => 'Delivered Orders', 'detail' => 'Orders successfully delivered', 'path' => '/sales/orders/orders.php?status=Delivered', 'icon' => 'bi-check-circle', 'intents' => ['delivered']],
        ['key' => 'recent-orders', 'name' => 'Recent Orders', 'detail' => 'View the latest customer purchases', 'path' => '/sales/orders/orders.php', 'icon' => 'bi-clock-history', 'intents' => ['recent', 'order']],
        ['key' => 'payments', 'name' => 'Payments', 'detail' => 'Review payment records', 'path' => '/sales/payments/payments.php', 'icon' => 'bi-credit-card', 'intents' => ['payment']],
        ['key' => 'returns', 'name' => 'Returns', 'detail' => 'Manage returns and refunds', 'path' => '/sales/returns/returns.php', 'icon' => 'bi-arrow-return-left', 'intents' => ['returns']],
        ['key' => 'notifications', 'name' => 'Notifications', 'detail' => 'View store notifications and alerts', 'path' => '/notifications.php', 'icon' => 'bi-bell', 'intents' => ['notifications']],
        ['key' => 'sales-reports', 'name' => 'Sales Reports', 'detail' => 'Analyze sales and revenue', 'path' => '/sales/reports/reports.php', 'icon' => 'bi-bar-chart-line', 'intents' => ['sales', 'recent']],
        ['key' => 'profit-tracker', 'name' => 'Profit Tracker', 'detail' => 'Review profit and margins', 'path' => '/sales/reports/profit-report.php', 'icon' => 'bi-graph-up-arrow', 'intents' => ['sales']],
    ];

    $normalizedQuery = normalizeGlobalSearchText($query);
    $queryTerms = preg_split('/\s+/u', $normalizedQuery, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $partialTerm = (string)end($queryTerms);
    $partialAliases = [
        'products' => ['products', 'product', 'items', 'catalog'],
        'all-products' => ['products', 'product', 'items', 'catalog'],
        'add-product' => ['products', 'product', 'add product'],
        'product-inventory' => ['products', 'product', 'inventory'],
        'low-stock' => ['low', 'low stock', 'stock alert', 'running out'],
        'inventory' => ['low', 'stock', 'inventory'],
        'stock-alerts' => ['low', 'stock alert', 'running out'],
        'customers' => ['customers', 'customer', 'clients', 'buyers'],
        'all-customers' => ['customers', 'customer', 'clients', 'buyers'],
        'add-customer' => ['customers', 'customer', 'add customer'],
        'orders' => ['orders', 'order', 'purchases'],
        'pending-orders' => ['orders', 'order', 'pending orders'],
        'processing-orders' => ['orders', 'order', 'processing orders'],
        'delivered-orders' => ['orders', 'order', 'delivered orders'],
        'recent-orders' => ['orders', 'order', 'recent orders'],
        'payments' => ['payments', 'payment', 'pay'],
        'sales-reports' => ['sales', 'sale', 'reports'],
        'profit-tracker' => ['profit', 'profits', 'earnings'],
        'returns' => ['returns', 'refunds'],
        'notifications' => ['notifications', 'alerts'],
    ];
    $preferredPrefixPages = [];
    if ($partialTerm === 'p') {
        $preferredPrefixPages = ['products', 'payments', 'profit-tracker', 'all-products', 'add-product', 'product-inventory'];
    } elseif (str_starts_with($partialTerm, 'prod')) {
        $preferredPrefixPages = ['products', 'all-products', 'add-product', 'product-inventory', 'low-stock', 'inventory', 'categories'];
    } elseif (str_starts_with($partialTerm, 'pr')) {
        $preferredPrefixPages = ['products', 'profit-tracker', 'all-products', 'add-product', 'product-inventory'];
    } elseif (str_starts_with($partialTerm, 'ord')) {
        $preferredPrefixPages = ['orders', 'pending-orders', 'delivered-orders', 'processing-orders', 'recent-orders'];
    } elseif (str_starts_with($partialTerm, 'cust')) {
        $preferredPrefixPages = ['customers', 'all-customers', 'add-customer'];
    } elseif (str_starts_with($partialTerm, 'low')) {
        $preferredPrefixPages = ['low-stock', 'inventory', 'stock-alerts', 'product-inventory'];
    }

    foreach ($pages as $page) {
        $matchedIntents = array_values(array_intersect($intents, $page['intents']));
        $pageTerms = array_merge(
            explode(' ', normalizeGlobalSearchText($page['name'])),
            $partialAliases[$page['key']] ?? []
        );
        $partialScore = 0.0;
        if ($partialTerm !== '') {
            foreach ($pageTerms as $index => $pageTerm) {
                $normalizedPageTerm = normalizeGlobalSearchText($pageTerm);
                if ($normalizedPageTerm !== '' && str_starts_with($normalizedPageTerm, $partialTerm)) {
                    $candidateScore = 3.0
                        + min(mb_strlen($partialTerm, 'UTF-8') / mb_strlen($normalizedPageTerm, 'UTF-8'), 1.0)
                        + ($index === 0 ? 0.35 : 0.0);
                    $partialScore = max($partialScore, $candidateScore);
                }
            }
        }
        if ($preferredPrefixPages !== []) {
            $preferredIndex = array_search($page['key'], $preferredPrefixPages, true);
            if ($preferredIndex === false) {
                $partialScore = 0.0;
            } else {
                $partialScore = max($partialScore, 8.0 - ($preferredIndex * 0.1));
            }
        }
        if ($matchedIntents === [] && $partialScore === 0.0) {
            continue;
        }

        $intentLabels = [
            'low_stock' => 'low-stock products',
            'high_price' => 'highest-priced products',
            'product' => 'products',
            'customer' => 'customers',
            'order' => 'orders',
            'pending' => 'pending orders',
            'delivered' => 'delivered orders',
            'recent' => 'recent activity',
            'inventory' => 'inventory',
            'sales' => 'sales',
            'payment' => 'payments',
            'returns' => 'returns',
            'notifications' => 'notifications',
            'dashboard' => 'dashboard',
        ];
        $score = max($partialScore, 2.0 + count($matchedIntents) * 0.45);
        if (in_array('low_stock', $matchedIntents, true)) {
            $score += 1.5;
        }
        if (in_array('high_price', $matchedIntents, true)) {
            $score += 1.5;
        }
        if (in_array('pending', $matchedIntents, true) || in_array('delivered', $matchedIntents, true)) {
            $score += 0.75;
        }
        if (str_contains($normalizedQuery, normalizeGlobalSearchText($page['name']))) {
            $score += 1.0;
        }
        $addResult('page:' . $page['key'], [
            'name' => $page['name'],
            'detail' => $page['detail'],
            'reason' => 'Relevant to your ' . implode(' and ', array_map(
                static fn(string $intent): string => $intentLabels[$intent] ?? $intent,
                $matchedIntents
            )) . ' search',
            'url' => $appBase . $page['path'],
            'type' => 'Page',
            'icon' => $page['icon'],
        ], $score, $tokens);
    }

    $entityTerms = ['product', 'customer', 'order', 'inventory', 'stock', 'sales', 'payment', 'return', 'recent', 'pending', 'delivered', 'highprice'];
    $recordTerms = array_values(array_filter(
        $tokens,
        static fn(string $token): bool => !in_array($token, $entityTerms, true) && mb_strlen($token, 'UTF-8') >= 2
    ));
    $recordTerms = array_slice($recordTerms, 0, 5);
    if ($recordTerms !== []) {
        $productClauses = [];
        $productParams = [];
        foreach ($recordTerms as $term) {
            $pattern = '%' . strtr($term, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
            $productClauses[] = "(p.name LIKE ? ESCAPE '!' OR p.sku LIKE ? ESCAPE '!' OR c.name LIKE ? ESCAPE '!' OR SOUNDEX(p.name) = SOUNDEX(?))";
            array_push($productParams, $pattern, $pattern, $pattern, $term);
        }
        $productSql = "SELECT p.id, p.name, p.sku, p.stock, c.name AS category_name
            FROM products p LEFT JOIN categories c ON c.id = p.category_id
            WHERE p.is_deleted = 0 AND (" . implode(' OR ', $productClauses) . ")
            ORDER BY p.name LIMIT 8";
        $statement = $conn->prepare($productSql);
        $statement->bind_param(str_repeat('s', count($productParams)), ...$productParams);
        $statement->execute();
        $rows = $statement->get_result();
        while ($row = $rows->fetch_assoc()) {
            $detail = 'SKU: ' . ($row['sku'] ?: 'N/A')
                . (!empty($row['category_name']) ? ' · ' . $row['category_name'] : '');
            $addResult('product:' . (int)$row['id'], [
                'name' => (string)$row['name'],
                'detail' => $detail,
                'reason' => 'Matches your search for ' . implode(', ', $recordTerms),
                'url' => $appBase . '/products/product-details.php?id=' . (int)$row['id'],
                'type' => 'Product',
                'icon' => 'bi-box-seam',
            ], 1.8, $recordTerms);
        }
        $statement->close();

        $customerClauses = [];
        $customerParams = [];
        foreach ($recordTerms as $term) {
            $pattern = '%' . strtr($term, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
            $customerClauses[] = "(name LIKE ? ESCAPE '!' OR email LIKE ? ESCAPE '!' OR phone LIKE ? ESCAPE '!' OR SOUNDEX(name) = SOUNDEX(?))";
            array_push($customerParams, $pattern, $pattern, $pattern, $term);
        }
        $customerSql = "SELECT id, name, email, phone FROM customers
            WHERE is_deleted = 0 AND (" . implode(' OR ', $customerClauses) . ") ORDER BY name LIMIT 8";
        $statement = $conn->prepare($customerSql);
        $statement->bind_param(str_repeat('s', count($customerParams)), ...$customerParams);
        $statement->execute();
        $rows = $statement->get_result();
        while ($row = $rows->fetch_assoc()) {
            $addResult('customer:' . (int)$row['id'], [
                'name' => (string)$row['name'],
                'detail' => (string)($row['email'] ?: ($row['phone'] ?: 'Customer')),
                'reason' => 'Matches your search for ' . implode(', ', $recordTerms),
                'url' => $appBase . '/customers/customer-details.php?id=' . (int)$row['id'],
                'type' => 'Customer',
                'icon' => 'bi-person',
            ], 1.8, $recordTerms);
        }
        $statement->close();

        $orderClauses = [];
        $orderParams = [];
        foreach ($recordTerms as $term) {
            $pattern = '%' . strtr($term, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
            $orderClauses[] = "(o.order_no LIKE ? ESCAPE '!' OR CAST(o.id AS CHAR) LIKE ? ESCAPE '!' OR c.name LIKE ? ESCAPE '!' OR c.email LIKE ? ESCAPE '!')";
            array_push($orderParams, $pattern, $pattern, $pattern, $pattern);
        }
        $orderSql = "SELECT o.id, o.order_no, c.name AS customer_name, c.email AS customer_email
            FROM orders o LEFT JOIN customers c ON c.id = o.customer_id
            WHERE " . implode(' OR ', $orderClauses) . " ORDER BY o.id DESC LIMIT 8";
        $statement = $conn->prepare($orderSql);
        $statement->bind_param(str_repeat('s', count($orderParams)), ...$orderParams);
        $statement->execute();
        $rows = $statement->get_result();
        while ($row = $rows->fetch_assoc()) {
            $addResult('order:' . (int)$row['id'], [
                'name' => (string)($row['order_no'] ?: 'Order #' . (int)$row['id']),
                'detail' => 'Customer: ' . ($row['customer_name'] ?: 'Guest'),
                'reason' => 'Order matches your search for ' . implode(', ', $recordTerms),
                'url' => $appBase . '/sales/orders/order-details.php?id=' . (int)$row['id'],
                'type' => 'Order',
                'icon' => 'bi-receipt',
            ], 1.8, $recordTerms);
        }
        $statement->close();
    }

    $categoryQuery = $conn->prepare(
        'SELECT id, name FROM categories ORDER BY name LIMIT 100'
    );
    $categoryQuery->execute();
    $categoryRows = $categoryQuery->get_result();
    while ($row = $categoryRows->fetch_assoc()) {
        $categoryName = (string)$row['name'];
        $normalizedName = normalizeGlobalSearchText($categoryName);
        $categoryTokens = getGlobalSearchTokens($categoryName);
        $matchedTerms = array_values(array_intersect($tokens, $categoryTokens));
        if ($matchedTerms === [] && !str_contains($normalizedName, $normalizedQuery)) {
            continue;
        }
        $addResult('category:' . (int)$row['id'], [
            'name' => $categoryName,
            'detail' => 'Product category',
            'url' => $appBase . '/categories/categories.php?search=' . rawurlencode($categoryName),
            'type' => 'Category',
            'icon' => 'bi-tags',
        ], 1.3 + count($matchedTerms) * 0.2, $matchedTerms);
    }
    $categoryQuery->close();

    if (in_array('low_stock', $intents, true)) {
        $stockCondition = str_contains(' ' . normalizeGlobalSearchText($query) . ' ', ' out of stock ')
            ? 'stock = 0'
            : 'stock BETWEEN 1 AND 5';
        $statement = $conn->prepare(
            'SELECT id, name, stock FROM products WHERE is_deleted = 0 AND ' . $stockCondition . ' ORDER BY stock ASC, name LIMIT 6'
        );
        $statement->execute();
        $rows = $statement->get_result();
        while ($row = $rows->fetch_assoc()) {
            $stock = (int)$row['stock'];
            $addResult('low-stock-product:' . (int)$row['id'], [
                'name' => (string)$row['name'],
                'detail' => $stock === 0 ? 'Out of stock' : $stock . ' units · Low stock',
                'reason' => 'Matches your stock alert search',
                'url' => $appBase . '/products/product-details.php?id=' . (int)$row['id'],
                'type' => 'Low Stock Product',
                'icon' => 'bi-exclamation-triangle',
            ], 2.6, ['stock']);
        }
        $statement->close();
    }

    if (in_array('high_price', $intents, true)) {
        $statement = $conn->prepare(
            'SELECT id, name, price FROM products WHERE is_deleted = 0 ORDER BY price DESC, id DESC LIMIT 5'
        );
        $statement->execute();
        $rows = $statement->get_result();
        while ($row = $rows->fetch_assoc()) {
            $addResult('price-product:' . (int)$row['id'], [
                'name' => (string)$row['name'],
                'detail' => 'Highest price · ' . formatAppCurrency((float)$row['price']),
                'reason' => 'One of the highest-priced products',
                'url' => $appBase . '/products/product-details.php?id=' . (int)$row['id'],
                'type' => 'Product',
                'icon' => 'bi-currency-dollar',
            ], 4.8, ['price']);
        }
        $statement->close();
    }

    if (in_array('order', $intents, true) || in_array('recent', $intents, true)) {
        $statuses = [];
        if (in_array('pending', $intents, true)) {
            $statuses = ['Pending', 'Processing'];
        } elseif (in_array('delivered', $intents, true)) {
            $statuses = ['Delivered'];
        }

        if ($statuses === []) {
            $statement = $conn->prepare(
                "SELECT o.id, o.order_no, o.status, c.name AS customer_name
                 FROM orders o LEFT JOIN customers c ON c.id = o.customer_id
                 ORDER BY o.id DESC LIMIT 6"
            );
            $statement->execute();
        } else {
            $statusPlaceholders = implode(',', array_fill(0, count($statuses), '?'));
            $statement = $conn->prepare(
                "SELECT o.id, o.order_no, o.status, c.name AS customer_name
                 FROM orders o LEFT JOIN customers c ON c.id = o.customer_id
                 WHERE o.status IN ($statusPlaceholders)
                 ORDER BY o.id DESC LIMIT 6"
            );
            $statement->bind_param(str_repeat('s', count($statuses)), ...$statuses);
            $statement->execute();
        }
        $rows = $statement->get_result();
        while ($row = $rows->fetch_assoc()) {
            $status = (string)($row['status'] ?? 'Pending');
            $isContextMatch = $statuses !== [];
            $addResult('order:' . (int)$row['id'], [
                'name' => (string)($row['order_no'] ?: 'Order #' . (int)$row['id']),
                'detail' => ($row['customer_name'] ?: 'Guest Customer') . ' · ' . $status,
                'reason' => $isContextMatch ? 'Matches your ' . strtolower($status) . ' orders search' : 'A recent order',
                'url' => $appBase . '/sales/orders/order-details.php?id=' . (int)$row['id'],
                'type' => 'Order',
                'icon' => 'bi-receipt',
            ], $isContextMatch ? 2.7 : 1.7, [$status]);
        }
        $statement->close();
    }

    if (in_array('delivered', $intents, true)) {
        $statement = $conn->prepare("SELECT COUNT(*) AS total FROM orders WHERE status = 'Delivered'");
        $statement->execute();
        $deliveredCount = (int)$statement->get_result()->fetch_assoc()['total'];
        $statement->close();
        $addResult('delivered-count', [
            'name' => number_format($deliveredCount) . ' Delivered Orders',
            'detail' => 'Total successfully delivered orders',
            'reason' => 'Answers your delivered-order count question',
            'url' => $appBase . '/sales/orders/orders.php?status=Delivered',
            'type' => 'Order Summary',
            'icon' => 'bi-check-circle',
        ], 2.8, ['delivered']);
    }

    if (str_contains($normalizedQuery, ' ')) {
        try {
            $semanticSearch = semanticSearchFindSimilar($conn, $query);
            $semanticAvailable = $semanticSearch['available'];
            foreach ($semanticSearch['matches'] as $match) {
                $documentId = (int)$match['id'];
                $similarity = (float)$match['similarity'];
                if ($match['type'] === 'product') {
                    $statement = $conn->prepare(
                        'SELECT p.id, p.name, p.sku, c.name AS category_name
                         FROM products p LEFT JOIN categories c ON c.id = p.category_id
                         WHERE p.id = ? AND p.is_deleted = 0'
                    );
                    $statement->bind_param('i', $documentId);
                    $statement->execute();
                    $row = $statement->get_result()->fetch_assoc();
                    $statement->close();
                    if ($row !== null) {
                        $addResult('product:' . $documentId, [
                            'name' => (string)$row['name'],
                            'detail' => 'SKU: ' . ($row['sku'] ?: 'N/A')
                                . (!empty($row['category_name']) ? ' · ' . $row['category_name'] : ''),
                            'url' => $appBase . '/products/product-details.php?id=' . $documentId,
                            'type' => 'Product',
                            'icon' => 'bi-box-seam',
                        ], 1.5 + $similarity, $tokens);
                    }
                } elseif ($match['type'] === 'category') {
                    $statement = $conn->prepare('SELECT id, name FROM categories WHERE id = ?');
                    $statement->bind_param('i', $documentId);
                    $statement->execute();
                    $row = $statement->get_result()->fetch_assoc();
                    $statement->close();
                    if ($row !== null) {
                        $addResult('category:' . $documentId, [
                            'name' => (string)$row['name'],
                            'detail' => 'Product category · semantically relevant',
                            'url' => $appBase . '/categories/categories.php?search=' . rawurlencode((string)$row['name']),
                            'type' => 'Category',
                            'icon' => 'bi-tags',
                        ], 1.5 + $similarity, $tokens);
                    }
                }
            }
        } catch (Throwable $exception) {
            error_log('Semantic embeddings unavailable; local intent search remains active: ' . $exception->getMessage());
        }
    }

    $results = array_values($resultsByKey);
    usort($results, static fn(array $left, array $right): int => $right['rank_score'] <=> $left['rank_score']);
    $results = array_slice($results, 0, 12);
    foreach ($results as &$result) {
        unset($result['rank_score']);
    }
    unset($result);

    respondWithSearchResults($results, 200, $semanticAvailable ? 'embedding_hybrid' : 'local_intent');
} catch (Throwable $exception) {
    error_log('Global search failed: ' . $exception->getMessage());
    respondWithSearchResults([], 500, 'error');
}
