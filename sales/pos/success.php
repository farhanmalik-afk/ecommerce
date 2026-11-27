header('Location: ' . getBaseUrl() . 'sales/orders/invoice.php?id=' . $orderId);
```[cite: 11]
Agar live server (`shopdesk.gt.tc`) par `getBaseUrl()` galat path ya subfolder return kare (ya `sales/orders/invoice.php` ka path `../../orders/invoice.php` ho), to redirect galat URL par ja sakta hai (`404 Not Found` jaisa masla pehle `checkout_process.php` ke redirect URL mein aaya tha)[cite: 9, 11].

### 2. Free Hosting (InfinityFree) Par Session / Cookie / `403` / `409` Chances
Jab user Stripe ki website se wapas `success.php` par aata hai:
* Agar session cookie (`PHPSESSID`) Stripe redirect ke baad wapas aane par preserve na ho, to `$_SESSION['pending_stripe_session_id']` mismatch ki wajah se **`403` (*"This payment session does not match your current checkout."*)** aa sakta hai[cite: 11].
* Agar `$_SESSION['pending_stripe_amount']` ya `amount_total` mein zero-decimal/minor-unit ya currency mismatch ho, to `RuntimeException` throw ho kar **`409` (*"Payment was received, but the order could not be finalized."*)** aa sakta hai[cite: 11].

---

### Updated `success.php` (Safer Redirect & Session Handling Ke Saath)
Is code mein invoice redirect ke liye relative path (`../orders/invoice.php?id=...`) aur `getBaseUrl()` ka safe fallback use kiya gaya hai taake subfolder/domain issue ki wajah se `404` na aaye:

```php
<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/pos-checkout.php';
require_once __DIR__ . '/../../vendor/autoload.php';

function posSuccessTargetUrl(string $relativePath): string
{
    $currentDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $cleanCurrentDir = trim($currentDir, '/');
    if ($cleanCurrentDir !== '' && preg_match('#(^|/)sales/pos$#i', $cleanCurrentDir)) {
        $parentDir = preg_replace('#(^|/)sales/pos$#i', '', $cleanCurrentDir);
        $basePrefix = $parentDir !== '' ? '/' . trim($parentDir, '/') . '/' : '/';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host !== '') {
            return $scheme . '://' . $host . $basePrefix . ltrim($relativePath, '/');
        }
    }

    if (function_exists('getBaseUrl')) {
        $baseUrl = rtrim((string) getBaseUrl(), '/') . '/';
        return $baseUrl . ltrim($relativePath, '/');
    }

    return '../../' . ltrim($relativePath, '/');
}

$stripeSecretKey = trim((string) ($appSettings['stripe_secret_key'] ?? ''));
if ($stripeSecretKey === '') {
    $stripeSecretKey = trim((string) get_env_setting(
        'STRIPE_SECRET_KEY',
        get_env_setting('STRIPE_API_KEY', $stripeSecretKeyConfig ?? '')
    ));
}

$sessionId = $_GET['session_id'] ?? '';
if (!is_string($sessionId) || $sessionId === '' || strlen($sessionId) > 255) {
    header('Location: ' . posSuccessTargetUrl('sales/pos/pos.php'));
    exit();
}

if (($appSettings['pos_enabled'] ?? '1') !== '1') {
    http_response_code(503);
    exit('POS is currently disabled in settings.');
}

if ($stripeSecretKey === '') {
    http_response_code(503);
    exit('The payment confirmation service is not configured.');
}

if (!isset($_SESSION['pending_stripe_session_id']) || !hash_equals((string) $_SESSION['pending_stripe_session_id'], $sessionId)) {
    http_response_code(403);
    exit('This payment session does not match your current checkout.');
}

$pendingCart = $_SESSION['pending_cart'] ?? null;
$paymentMethod = $_SESSION['pending_payment_method'] ?? '';
$taxRate = (float) ($_SESSION['pending_tax_rate'] ?? 0);
$currency = strtolower((string) ($_SESSION['pending_currency'] ?? ''));
if (!is_array($pendingCart) || !in_array($paymentMethod, ['Online Transfer'], true)) {
    http_response_code(400);
    exit('The pending checkout could not be found.');
}

try {
    ensureStripeProcessedSessionsTable($conn);
    $existingStmt = $conn->prepare('SELECT order_id FROM stripe_processed_sessions WHERE session_id = ? LIMIT 1');
    if (!$existingStmt) {
        throw new RuntimeException('Unable to check processed Stripe session: ' . $conn->error);
    }
    $existingStmt->bind_param('s', $sessionId);
    $existingStmt->execute();
    $existingOrderId = $existingStmt->get_result()->fetch_column();
    $existingStmt->close();
    if ($existingOrderId !== null && $existingOrderId !== false) {
        header('Location: ' . posSuccessTargetUrl('sales/orders/invoice.php?id=' . (int) $existingOrderId));
        exit();
    }

    \Stripe\Stripe::setApiKey($stripeSecretKey);
    $paymentSession = \Stripe\Checkout\Session::retrieve($sessionId);
    if ($paymentSession->payment_status !== 'paid' || strtolower((string) $paymentSession->currency) !== $currency) {
        http_response_code(402);
        exit('Payment has not been completed.');
    }

    $expectedStripeAmount = $_SESSION['pending_stripe_amount'] ?? null;
    if ($expectedStripeAmount === null) {
        $subtotalCents = 0;
        foreach ($pendingCart as $item) {
            $subtotalCents += (int) round((float) $item['price'] * 100) * (int) $item['qty'];
        }
        $expectedStripeAmount = $subtotalCents + (int) round($subtotalCents * $taxRate / 100);
    }
    if (!is_numeric($expectedStripeAmount) || (int) $paymentSession->amount_total !== (int) $expectedStripeAmount) {
        throw new RuntimeException('Stripe paid amount does not match the stored checkout.');
    }

    $conn->begin_transaction();
    $transactionStarted = true;
    $claimStmt = $conn->prepare('INSERT IGNORE INTO stripe_processed_sessions (session_id) VALUES (?)');
    $claimStmt->bind_param('s', $sessionId);
    $claimStmt->execute();
    if ($claimStmt->affected_rows === 0) {
        $claimStmt->close();
        $existingStmt = $conn->prepare('SELECT order_id FROM stripe_processed_sessions WHERE session_id = ? FOR UPDATE');
        $existingStmt->bind_param('s', $sessionId);
        $existingStmt->execute();
        $existingOrderId = $existingStmt->get_result()->fetch_column();
        $existingStmt->close();
        if ($existingOrderId !== null && $existingOrderId !== false) {
            $conn->commit();
            $transactionStarted = false;
            header('Location: ' . posSuccessTargetUrl('sales/orders/invoice.php?id=' . (int) $existingOrderId));
            exit();
        }
        throw new RuntimeException('The payment session is already being processed.');
    }
    $claimStmt->close();

    $cart = validatePosCart($conn, $pendingCart, true, true);
    $total = isset($_SESSION['pending_store_total'])
        ? (float) $_SESSION['pending_store_total']
        : ((int) $paymentSession->amount_total) / 100;
    $orderId = insertPosOrder($conn, $cart, $total, $paymentMethod, 'Walk-in Counter POS (Online Paid)');
    $saveOrderStmt = $conn->prepare('UPDATE stripe_processed_sessions SET order_id = ? WHERE session_id = ?');
    $saveOrderStmt->bind_param('is', $orderId, $sessionId);
    $saveOrderStmt->execute();
    $saveOrderStmt->close();
    $conn->commit();
    $transactionStarted = false;

    notifyPosOrder($conn, $orderId, $paymentMethod, $cart);
    unset(
        $_SESSION['pending_cart'],
        $_SESSION['pending_payment_method'],
        $_SESSION['pending_tax_rate'],
        $_SESSION['pending_currency'],
        $_SESSION['pending_stripe_amount'],
        $_SESSION['pending_store_total'],
        $_SESSION['pending_stripe_session_id']
    );
    header('Location: ' . posSuccessTargetUrl('sales/orders/invoice.php?id=' . $orderId));
    exit();
} catch (\Stripe\Exception\ApiErrorException $exception) {
    if (!empty($transactionStarted)) {
        $conn->rollback();
    }
    error_log('Unable to verify Stripe payment: ' . $exception->getMessage());
    http_response_code(502);
    exit('Unable to verify the payment with the payment provider.');
} catch (InvalidArgumentException | mysqli_sql_exception | RuntimeException $exception) {
    if (!empty($transactionStarted)) {
        $conn->rollback();
    }
    error_log('Unable to record paid Stripe order: ' . $exception->getMessage());
    http_response_code(409);
    exit('Payment was received, but the order could not be finalized. Contact an administrator.');
}