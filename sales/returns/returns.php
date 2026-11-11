<?php
session_start();
require_once __DIR__ . '/../../config.php';

$pageTitle = 'Returns';
$parent = ['Orders', '../orders/orders.php'];
$_SESSION['notification_csrf'] ??= bin2hex(random_bytes(32));
$csrfToken = $_SESSION['notification_csrf'];
$returnError = '';
$returnSuccess = '';
$selectedOrderId = (string) ($_POST['order_id'] ?? '');
$returnReason = trim((string) ($_POST['reason'] ?? ''));
$refundAmount = trim((string) ($_POST['refund_amount'] ?? ''));

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrfToken, $postedToken)) {
        $returnError = 'Your form session expired. Refresh the page and try again.';
    } elseif (!ctype_digit($selectedOrderId) || (int) $selectedOrderId < 1) {
        $returnError = 'Select a valid order.';
    } elseif ($returnReason === '' || mb_strlen($returnReason) > 255) {
        $returnError = 'Enter a return reason of up to 255 characters.';
    } elseif ($refundAmount === '' || !is_numeric($refundAmount) || !is_finite((float) $refundAmount) || (float) $refundAmount < 0) {
        $returnError = 'Enter a valid refund amount of zero or more.';
    } else {
        $orderId = (int) $selectedOrderId;
        $orderStmt = $conn->prepare('SELECT order_no, total FROM orders WHERE id = ? LIMIT 1');
        $orderStmt->bind_param('i', $orderId);
        $orderStmt->execute();
        $order = $orderStmt->get_result()->fetch_assoc();
        $orderStmt->close();

        if (!$order) {
            $returnError = 'The selected order could not be found.';
        } elseif ((float) $refundAmount > (float) $order['total']) {
            $returnError = 'Refund amount cannot exceed the order total.';
        } else {
            $returnNo = 'RET-' . strtoupper(bin2hex(random_bytes(8)));
            $refundValue = (float) $refundAmount;
            $insertStmt = $conn->prepare("INSERT INTO `returns` (return_no, order_id, reason, refund_amount) VALUES (?, ?, ?, ?)");
            $insertStmt->bind_param('sisd', $returnNo, $orderId, $returnReason, $refundValue);
            $insertStmt->execute();
            $insertStmt->close();

            createAdminNotification(
                $conn,
                'Return request submitted',
                'Return ' . $returnNo . ' was requested for order ' . $order['order_no'] . '.',
                'return'
            );
            $returnSuccess = 'Return request ' . $returnNo . ' was submitted.';
            $selectedOrderId = '';
            $returnReason = '';
            $refundAmount = '';
        }
    }
}

$orderOptionsResult = $conn->query('SELECT id, order_no, total FROM orders ORDER BY created_at DESC, id DESC');
if ($orderOptionsResult === false) {
    throw new RuntimeException('Unable to load orders for return requests: ' . $conn->error);
}
$returnOrders = $orderOptionsResult->fetch_all(MYSQLI_ASSOC);

$returnStatsResult = $conn->query("SELECT
    SUM(status = 'Pending') AS pending_count,
    SUM(status = 'Approved') AS approved_count,
    SUM(status = 'Rejected') AS rejected_count,
    SUM(CASE WHEN status = 'Refunded' AND created_at >= DATE_FORMAT(CURRENT_DATE, '%Y-%m-01') THEN refund_amount ELSE 0 END) AS refunded_total
    FROM `returns`");
if ($returnStatsResult === false) {
    throw new RuntimeException('Unable to load return statistics: ' . $conn->error);
}
$returnStats = $returnStatsResult->fetch_assoc();

$returnsResult = $conn->query("SELECT r.return_no, r.order_id, r.reason, r.refund_amount, r.status, r.created_at,
        o.order_no, COALESCE(c.name, 'POS Customer') AS customer_name
    FROM `returns` r
    INNER JOIN orders o ON o.id = r.order_id
    LEFT JOIN customers c ON c.id = o.customer_id
    ORDER BY r.created_at DESC, r.id DESC");
if ($returnsResult === false) {
    throw new RuntimeException('Unable to load return requests: ' . $conn->error);
}
$returnRows = $returnsResult->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../../layouts/header.php';
?>
<section class="page-head d-flex flex-wrap justify-content-between align-items-start gap-3">
  <div>
    <h1>Returns</h1>
    <p>Review and record customer return/refund requests.</p>
  </div>
</section>

<section class="card mb-4">
  <div class="card-body">
    <h2 class="h5 mb-3">Submit a return/refund request</h2>
    <?php if ($returnError !== ''): ?>
      <div class="alert alert-danger" role="alert"><?= htmlspecialchars($returnError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php elseif ($returnSuccess !== ''): ?>
      <div class="alert alert-success" role="status"><?= htmlspecialchars($returnSuccess, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <form method="post" class="row g-3">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
      <div class="col-lg-5">
        <label for="returnOrder" class="form-label">Order</label>
        <select class="form-select" id="returnOrder" name="order_id" required>
          <option value="">Select an order</option>
          <?php foreach ($returnOrders as $orderOption): ?>
            <option value="<?= (int) $orderOption['id'] ?>" <?= $selectedOrderId === (string) $orderOption['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($orderOption['order_no'] . ' — ' . formatAppCurrency((float) $orderOption['total']), ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-lg-4">
        <label for="returnReason" class="form-label">Reason</label>
        <input class="form-control" id="returnReason" name="reason" maxlength="255" value="<?= htmlspecialchars($returnReason, ENT_QUOTES, 'UTF-8') ?>" required>
      </div>
      <div class="col-lg-2">
        <label for="refundAmount" class="form-label">Refund amount</label>
        <input class="form-control" type="number" id="refundAmount" name="refund_amount" min="0" step="0.01" value="<?= htmlspecialchars($refundAmount, ENT_QUOTES, 'UTF-8') ?>" required>
      </div>
      <div class="col-lg-1 d-flex align-items-end">
        <button type="submit" class="btn btn-dark w-100">Submit</button>
      </div>
    </form>
  </div>
</section>

<section class="row g-3 mb-4">
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="card stat-card"><div class="card-body"><div><p class="stat-label">Open requests</p><h3 class="stat-value"><?= (int) ($returnStats['pending_count'] ?? 0) ?></h3></div><span class="stat-icon"><i class="bi bi-arrow-return-left"></i></span></div><div class="stat-foot">Waiting for review</div></div>
  </div>
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="card stat-card"><div class="card-body"><div><p class="stat-label">Approved</p><h3 class="stat-value"><?= (int) ($returnStats['approved_count'] ?? 0) ?></h3></div><span class="stat-icon"><i class="bi bi-check2-circle"></i></span></div><div class="stat-foot">All requests</div></div>
  </div>
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="card stat-card"><div class="card-body"><div><p class="stat-label">Refunded this month</p><h3 class="stat-value"><?= htmlspecialchars(formatAppCurrency((float) ($returnStats['refunded_total'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></h3></div><span class="stat-icon"><i class="bi bi-cash-coin"></i></span></div><div class="stat-foot">Completed refunds</div></div>
  </div>
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="card stat-card"><div class="card-body"><div><p class="stat-label">Rejected</p><h3 class="stat-value"><?= (int) ($returnStats['rejected_count'] ?? 0) ?></h3></div><span class="stat-icon"><i class="bi bi-x-circle"></i></span></div><div class="stat-foot">All requests</div></div>
  </div>
</section>

<div class="card table-card mb-4">
  <div class="table-card-header"><h6 class="card-title mb-0">Return requests</h6></div>
  <div class="table-responsive">
    <table class="table data-table align-middle mb-0">
      <thead><tr><th>Return</th><th>Order</th><th>Customer</th><th>Product</th><th class="text-center">Qty</th><th>Reason</th><th class="text-center">Status</th><th class="text-end">Refund amount</th></tr></thead>
      <tbody>
        <?php foreach ($returnRows as $returnRow): ?>
          <?php
          $returnStatusClass = [
              'Pending' => 'requested',
              'Approved' => 'approved',
              'Rejected' => 'rejected',
              'Refunded' => 'refunded',
          ][$returnRow['status']] ?? 'requested';
          ?>
          <tr>
            <td><strong><?= htmlspecialchars($returnRow['return_no'], ENT_QUOTES, 'UTF-8') ?></strong></td>
            <td><a href="../orders/order-details.php?id=<?= (int) $returnRow['order_id'] ?>" class="order-link"><?= htmlspecialchars($returnRow['order_no'], ENT_QUOTES, 'UTF-8') ?></a></td>
            <td><?= htmlspecialchars($returnRow['customer_name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td>—</td>
            <td class="text-center">—</td>
            <td><?= htmlspecialchars($returnRow['reason'], ENT_QUOTES, 'UTF-8') ?></td>
            <td class="text-center"><span class="status status-<?= htmlspecialchars($returnStatusClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($returnRow['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
            <td class="text-end"><?= htmlspecialchars(formatAppCurrency((float) $returnRow['refund_amount']), ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$returnRows): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">No return requests have been submitted.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="table-footer"><span>Showing <?= count($returnRows) ?> return request(s)</span></div>
</div>

<?php include __DIR__ . '/../../layouts/footer.php'; ?>
