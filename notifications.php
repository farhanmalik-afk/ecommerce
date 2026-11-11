<?php
session_start();
require_once __DIR__ . '/config.php';
$_SESSION['notification_csrf'] ??= bin2hex(random_bytes(32));

$allowedTypes = ['order', 'stock', 'customer', 'return'];
$requestedType = $_GET['type'] ?? '';
$typeFilter = is_string($requestedType) && in_array($requestedType, $allowedTypes, true) ? $requestedType : '';
$focusId = filter_var($_GET['focus'] ?? null, FILTER_VALIDATE_INT);
$focusId = $focusId !== false && $focusId > 0 ? $focusId : 0;
if ($focusId > 0) {
    $readStmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND is_deleted = 0');
    $readStmt->bind_param('i', $focusId);
    $readStmt->execute();
    $readStmt->close();
}
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
$page = $page !== false && $page > 0 ? $page : 1;
$perPage = 25;

$conditions = ['is_deleted = 0'];
$queryParams = [];
if ($typeFilter !== '') {
    $conditions[] = 'type = ?';
    $queryParams[] = $typeFilter;
}
$whereSql = implode(' AND ', $conditions);

$countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM notifications WHERE $whereSql");
if ($queryParams) {
    $countStmt->bind_param(str_repeat('s', count($queryParams)), ...$queryParams);
}
$countStmt->execute();
$totalNotifications = (int) ($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
$countStmt->close();

$totalPages = max(1, (int) ceil($totalNotifications / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$listStmt = $conn->prepare("SELECT id, title, message, type, is_read, created_at FROM notifications WHERE $whereSql ORDER BY (id = ?) DESC, created_at DESC, id DESC LIMIT ? OFFSET ?");
$listParams = array_merge($queryParams, [$focusId, $perPage, $offset]);
$listStmt->bind_param(str_repeat('s', count($queryParams)) . 'iii', ...$listParams);
$listStmt->execute();
$historyNotifications = $listStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$listStmt->close();

$pageTitle = 'Notifications';
include __DIR__ . '/layouts/header.php';
$paginationQuery = [];
if ($typeFilter !== '') {
    $paginationQuery['type'] = $typeFilter;
}
?>
<section class="page-head d-flex justify-content-between align-items-start flex-wrap gap-3">
  <div>
    <h1>Notifications</h1>
    <p>Notification history. Select notifications to delete them to Trash.</p>
  </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<section class="card mb-4">
  <div class="card-body">
    <form method="get" class="row g-3 align-items-end">
      <div class="col-md-4">
        <label class="form-label" for="notificationType">Type</label>
        <select class="form-select" id="notificationType" name="type">
          <option value="">All types</option>
          <?php foreach ($allowedTypes as $type): ?>
            <option value="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>" <?= $typeFilter === $type ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($type), ENT_QUOTES, 'UTF-8') ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-8 d-flex gap-2">
        <button class="btn btn-dark" type="submit">Filter</button>
        <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/notifications.php">Clear</a>
      </div>
    </form>
  </div>
</section>

<section class="card notification-inbox mb-4"
         data-mark-read-url="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/notifications/mark-read.php"
         data-action-url="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/notifications/actions.php"
         data-csrf-token="<?= htmlspecialchars($_SESSION['notification_csrf'], ENT_QUOTES, 'UTF-8') ?>">
  <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
    <strong><?= number_format($totalNotifications) ?> notification(s)</strong>
    <div class="d-flex flex-wrap gap-2">
      <button class="btn btn-outline-secondary btn-sm" type="button" data-notification-toggle-selection aria-pressed="false">Select</button>
      <button class="btn btn-outline-secondary btn-sm d-none" type="button" data-notification-select-all>Select All</button>
      <button class="btn btn-outline-danger btn-sm d-none" type="button" data-notification-trash-selected disabled>Move Selected to Trash</button>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead class="table-light">
        <tr><th class="notification-select-column d-none">Select</th><th>Notification</th><th>Type</th><th>Time</th></tr>
      </thead>
      <tbody>
      <?php foreach ($historyNotifications as $notification): ?>
        <?php $notificationType = $notification['type']; ?>
        <tr id="notification-<?= (int) $notification['id'] ?>" data-notification-row<?= $focusId === (int) $notification['id'] ? ' class="notification-focused"' : '' ?>>
          <td class="notification-select-column d-none">
            <input class="form-check-input notification-checkbox" type="checkbox" value="<?= (int) $notification['id'] ?>" aria-label="Select notification">
          </td>
          <td>
            <a class="notification-history-link<?= (int) $notification['is_read'] === 0 ? ' is-unread' : '' ?>"
               href="#notification-<?= (int) $notification['id'] ?>"
               data-notification-id="<?= (int) $notification['id'] ?>">
              <strong><?= htmlspecialchars($notification['title'], ENT_QUOTES, 'UTF-8') ?></strong>
            </a>
            <div class="text-muted small"><?= htmlspecialchars($notification['message'], ENT_QUOTES, 'UTF-8') ?></div>
          </td>
          <td><span class="badge text-bg-light text-capitalize"><?= htmlspecialchars($notificationType, ENT_QUOTES, 'UTF-8') ?></span></td>
          <td><?= htmlspecialchars(date('d M Y, H:i', strtotime($notification['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$historyNotifications): ?>
        <tr><td colspan="4" class="text-center text-muted py-4">No notifications match these filters.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <p class="notification-action-error d-none m-3" role="alert"></p>
</section>

<?php if ($totalPages > 1): ?>
<nav aria-label="Notification history pages">
  <ul class="pagination justify-content-center">
    <?php for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++): ?>
      <?php $pageHref = $appBase . '/notifications.php?' . http_build_query(array_merge($paginationQuery, ['page' => $pageNumber])); ?>
      <li class="page-item<?= $pageNumber === $page ? ' active' : '' ?>">
        <a class="page-link" href="<?= htmlspecialchars($pageHref, ENT_QUOTES, 'UTF-8') ?>"><?= $pageNumber ?></a>
      </li>
    <?php endfor; ?>
  </ul>
</nav>
<?php endif; ?>

<?php include __DIR__ . '/layouts/footer.php'; ?>
