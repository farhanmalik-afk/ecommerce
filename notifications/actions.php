<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

function respondWithError(int $statusCode, string $message): void
{
    http_response_code($statusCode);
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    respondWithError(405, 'POST is required.');
}

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!isset($_SESSION['notification_csrf']) || !hash_equals($_SESSION['notification_csrf'], $csrfToken)) {
    respondWithError(403, 'Invalid request token. Refresh the page and try again.');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    respondWithError(400, 'A valid JSON request is required.');
}

$action = $input['action'] ?? '';
$allowedActions = ['mark_read', 'trash', 'recover', 'delete'];
if (!is_string($action) || !in_array($action, $allowedActions, true)) {
    respondWithError(422, 'Select a valid notification action.');
}

$postedIds = $input['ids'] ?? [];
if (!is_array($postedIds)) {
    respondWithError(422, 'Select at least one notification.');
}
$ids = [];
foreach ($postedIds as $postedId) {
    $id = is_scalar($postedId) ? filter_var($postedId, FILTER_VALIDATE_INT) : false;
    if ($id !== false && $id > 0) {
        $ids[] = $id;
    }
}
$ids = array_values(array_unique($ids));
if (!$ids) {
    respondWithError(422, 'Select at least one valid notification.');
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$types = str_repeat('i', count($ids));

try {
    if ($action === 'mark_read') {
        $sql = "UPDATE notifications SET is_read = 1 WHERE is_deleted = 0 AND id IN ($placeholders)";
    } elseif ($action === 'trash') {
        $sql = "UPDATE notifications SET is_deleted = 1 WHERE is_deleted = 0 AND id IN ($placeholders)";
    } elseif ($action === 'recover') {
        $sql = "UPDATE notifications SET is_deleted = 0 WHERE is_deleted = 1 AND id IN ($placeholders)";
    } else {
        $sql = "DELETE FROM notifications WHERE is_deleted = 1 AND id IN ($placeholders)";
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $affectedRows = $stmt->affected_rows;
    $stmt->close();

    $countResult = $conn->query('SELECT COUNT(*) AS total FROM notifications WHERE is_read = 0 AND is_deleted = 0');
    if ($countResult === false) {
        throw new RuntimeException('Unable to refresh unread notification count: ' . $conn->error);
    }
} catch (Throwable $exception) {
    error_log('Notification action failed: ' . $exception->getMessage());
    respondWithError(500, 'The notification action could not be completed.');
}

echo json_encode([
    'status' => 'success',
    'affected' => $affectedRows,
    'unread_count' => (int) ($countResult->fetch_assoc()['total'] ?? 0),
]);
