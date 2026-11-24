<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');

session_start();
define('APP_JSON_RESPONSE', true);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/pos-checkout.php';

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['status' => 'error', 'message' => 'Use POST to complete checkout.']);
    exit();
}

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!isset($_SESSION['pos_checkout_csrf']) || !is_string($csrfToken) || !hash_equals($_SESSION['pos_checkout_csrf'], $csrfToken)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Your session expired. Refresh the page and try again.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$paymentMethod = is_array($input) ? ($input['payment_method'] ?? null) : null;
$requestedCart = is_array($input) ? ($input['cart'] ?? null) : null;
if (!is_string($paymentMethod) || !in_array($paymentMethod, ['Cash', 'Online Transfer'], true)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Choose a supported payment method.']);
    exit();
}

$stripeSecretKey = trim((string) ($appSettings['stripe_secret_key'] ?? ''));
if ($stripeSecretKey === '') {
    $stripeSecretKey = get_env_setting('STRIPE_SECRET_KEY', get_env_setting('STRIPE_API_KEY', $stripeSecretKeyConfig));
}

try {
    $transactionStarted = false;
    if ($paymentMethod === 'Cash') {
        $conn->begin_transaction();
        $transactionStarted = true;
        $cart = validatePosCart($conn, $requestedCart, true);
        $subtotalCents = 0;
        foreach ($cart as $item) {
            $subtotalCents += (int) round($item['price'] * 100) * $item['qty'];
        }
        $taxRate = (float) ($appSettings['pos_tax_rate'] ?? 0);
        $taxCents = (int) round($subtotalCents * $taxRate / 100);
        $total = ($subtotalCents + $taxCents) / 100;
        $orderId = insertPosOrder($conn, $cart, $total, $paymentMethod, 'Walk-in Counter POS');
        $conn->commit();
        $transactionStarted = false;
        notifyPosOrder($conn, $orderId, $paymentMethod, $cart);
        echo json_encode(['status' => 'success', 'order_id' => $orderId]);
        exit();
    }

    $autoloadPath = __DIR__ . '/../../vendor/autoload.php';
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
    }

    if (!class_exists('\Stripe\Stripe')) {
        http_response_code(503);
        echo json_encode(['status' => 'error', 'message' => 'The Stripe payment library is unavailable.']);
        exit();
    }
    if ($stripeSecretKey === '') {
        http_response_code(503);
        echo json_encode(['status' => 'error', 'message' => 'Online payment is not configured.']);
        exit();
    }

    $cart = validatePosCart($conn, $requestedCart);
    $currency = strtolower((string) ($appSettings['currency'] ?? 'USD'));
    if (!preg_match('/^[a-z]{3}$/', $currency)) {
        throw new InvalidArgumentException('The store currency is invalid.');
    }

    $subtotalCents = 0;
    $lineItems = [];
    foreach ($cart as $item) {
        $unitAmount = (int) round($item['price'] * 100);
        $subtotalCents += $unitAmount * $item['qty'];
        $lineItems[] = [
            'price_data' => [
                'currency' => $currency,
                'product_data' => ['name' => $item['name']],
                'unit_amount' => $unitAmount,
            ],
            'quantity' => $item['qty'],
        ];
    }
    $taxRate = (float) ($appSettings['pos_tax_rate'] ?? 0);
    $taxCents = (int) round($subtotalCents * $taxRate / 100);
    if ($taxCents > 0) {
        $lineItems[] = [
            'price_data' => [
                'currency' => $currency,
                'product_data' => ['name' => 'Estimated Tax (' . $taxRate . '%)'],
                'unit_amount' => $taxCents,
            ],
            'quantity' => 1,
        ];
    }

    \Stripe\Stripe::setApiKey($stripeSecretKey);
    $appUrl = get_env_setting('APP_URL', 'http://localhost');
    $urlParts = parse_url($appUrl);
    if (!is_array($urlParts) || empty($urlParts['scheme']) || empty($urlParts['host'])) {
        throw new RuntimeException('APP_URL must be a valid absolute URL.');
    }
    $origin = $urlParts['scheme'] . '://' . $urlParts['host']
        . (isset($urlParts['port']) ? ':' . $urlParts['port'] : '');
    $appPath = rtrim($urlParts['path'] ?? '', '/');
    $projectPath = rtrim(getBaseUrl(), '/');
    if ($projectPath !== '' && !str_ends_with($appPath, $projectPath)) {
        $appPath .= $projectPath;
    }
    $baseUrl = $origin . $appPath . '/';
    $session = \Stripe\Checkout\Session::create([
        'line_items' => $lineItems,
        'mode' => 'payment',
        'success_url' => $baseUrl . 'sales/pos/success.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => $baseUrl . 'sales/pos/pos.php',
        'metadata' => ['payment_method' => $paymentMethod],
    ]);

    $_SESSION['pending_cart'] = $cart;
    $_SESSION['pending_payment_method'] = $paymentMethod;
    $_SESSION['pending_tax_rate'] = $taxRate;
    $_SESSION['pending_currency'] = $currency;
    $_SESSION['pending_stripe_session_id'] = $session->id;
    echo json_encode(['status' => 'stripe_redirect', 'url' => $session->url]);
} catch (InvalidArgumentException $exception) {
    if (!empty($transactionStarted)) {
        $conn->rollback();
    }
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $exception->getMessage()]);
} catch (mysqli_sql_exception | RuntimeException $exception) {
    if (!empty($transactionStarted)) {
        $conn->rollback();
    }
    error_log('POS checkout failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'The order could not be completed.']);
} catch (\Stripe\Exception\ApiErrorException $exception) {
    error_log('Stripe checkout failed: ' . $exception->getMessage());
    http_response_code(502);
    echo json_encode(['status' => 'error', 'message' => 'The payment provider could not start checkout.']);
}