<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['status' => 'error', 'message' => 'POST is required.']);
    exit();
}

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!isset($_SESSION['notification_csrf']) || !hash_equals($_SESSION['notification_csrf'], $csrfToken)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request token. Refresh the page and try again.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'A valid JSON request is required.']);
    exit();
}
$notificationId = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
if ($notificationId === false || $notificationId === null || $notificationId < 1) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'A valid notification ID is required.']);
    exit();
}

try {
    $existsStmt = $conn->prepare('SELECT id FROM notifications WHERE id = ? AND is_deleted = 0 LIMIT 1');
    $existsStmt->bind_param('i', $notificationId);
    $existsStmt->execute();
    $notificationExists = $existsStmt->get_result()->num_rows === 1;
    $existsStmt->close();
    if (!$notificationExists) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Notification not found.']);
        exit();
    }

    $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND is_read = 0 AND is_deleted = 0');
    $stmt->bind_param('i', $notificationId);
    $stmt->execute();
    $stmt->close();

    $countResult = $conn->query('SELECT COUNT(*) AS total FROM notifications WHERE is_read = 0 AND is_deleted = 0');
    if ($countResult === false) {
        error_log('Unable to refresh unread notification count: ' . $conn->error);
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Unable to refresh unread notification count.']);
        exit();
    }
} catch (mysqli_sql_exception $e) {
    error_log('Unable to mark notification as read: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Unable to update the notification.']);
    exit();
}

echo json_encode([
    'status' => 'success',
    'unread_count' => (int) ($countResult->fetch_assoc()['total'] ?? 0),
]);
