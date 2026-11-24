<?php
session_start();
define('APP_JSON_RESPONSE', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

header('Content-Type: application/json; charset=utf-8');

function respondWithJson(int $statusCode, array $response): void
{
    http_response_code($statusCode);
    echo json_encode($response);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respondWithJson(405, ['status' => 'error', 'message' => 'Use POST to send customer credentials.']);
}

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!isset($_SESSION['credential_email_csrf']) || !is_string($csrfToken) || !hash_equals($_SESSION['credential_email_csrf'], $csrfToken)) {
    respondWithJson(403, ['status' => 'error', 'message' => 'Your session expired. Refresh the page and try again.']);
}

$input = json_decode(file_get_contents('php://input'), true);
$customerId = is_array($input) ? filter_var($input['id'] ?? null, FILTER_VALIDATE_INT) : false;
if ($customerId === false || $customerId < 1) {
    respondWithJson(400, ['status' => 'error', 'message' => 'Select a valid customer.']);
}

$stmt = $conn->prepare('SELECT name, email FROM customers WHERE id = ? AND is_deleted = 0');
$stmt->bind_param('i', $customerId);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$customer) {
    respondWithJson(404, ['status' => 'error', 'message' => 'Customer not found.']);
}

$tempPassword = 'Pass@' . random_int(100000, 999999);
$hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
$conn->begin_transaction();

try {
    $updateStmt = $conn->prepare('UPDATE customers SET password = ? WHERE id = ? AND is_deleted = 0');
    $updateStmt->bind_param('si', $hashedPassword, $customerId);
    $updateStmt->execute();
    if ($updateStmt->affected_rows !== 1) {
        $updateStmt->close();
        throw new RuntimeException('Customer credentials were not updated.');
    }
    $updateStmt->close();

    $mailUsername = trim((string) ($appSettings['fm3023098@gmail.com'] ?? 'fm3023098@gmail.com'));
    $dbPassword   = trim((string) ($appSettings['ahac egzf ldce bjlh
'] ?? ''));
    $rawPassword  = $dbPassword !== '' ? $dbPassword : 'ahac egzf ldce bjlh';
    $mailFrom     = trim((string) ($appSettings['smtp_from_email'] ?? ($mailUsername !== '' ? $mailUsername : 'fm3023098@gmail.com')));

    if ($mailUsername === '' || $rawPassword === '' || $mailFrom === '') {
        throw new RuntimeException('SMTP credentials are not configured.');
    }

    $cleanPassword = preg_replace('/\s+/', '', $rawPassword) ?? '';
    if (strlen($cleanPassword) !== 16) {
        throw new RuntimeException(
            'SMTP password length is ' . strlen($cleanPassword) . ' (expected 16). Fix smtp_password in app_settings.'
        );
    }

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = (string) ($appSettings['smtp_host'] ?? 'smtp.gmail.com');
    $mail->SMTPAuth = true;
    $mail->Username = $mailUsername;
    $mail->Password = $cleanPassword;

    $encryption = strtolower(trim((string) ($appSettings['smtp_encryption'] ?? 'tls')));
    if ($encryption === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->SMTPAutoTLS = true;
    } elseif ($encryption === 'none') {
        $mail->SMTPSecure = '';
        $mail->SMTPAutoTLS = false;
    } else {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->SMTPAutoTLS = true;
    }

    $mail->Port = (int) ($appSettings['smtp_port'] ?? 587);
    $mail->setFrom($mailFrom, (string) ($appSettings['smtp_sender_name'] ?? 'E-Commerce Dashboard'));
    $mail->addAddress($customer['email'], $customer['name']);
    $mail->isHTML(true);
    $mail->Subject = 'Your Updated Login Credentials';
    $mail->Body = '
        <div style="font-family: Arial, sans-serif; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
            <h2 style="color: #333;">Hello, ' . htmlspecialchars($customer['name'], ENT_QUOTES, 'UTF-8') . '!</h2>
            <p>Here are your new login credentials:</p>
            <ul style="background: #f9f9f9; padding: 15px; border-radius: 5px; list-style: none;">
                <li><strong>Email:</strong> ' . htmlspecialchars($customer['email'], ENT_QUOTES, 'UTF-8') . '</li>
                <li><strong>Temporary Password:</strong> ' . htmlspecialchars($tempPassword, ENT_QUOTES, 'UTF-8') . '</li>
            </ul>
            <p>Please change your password after logging in.</p>
            <p>Best Regards,<br><strong>E-Commerce Team</strong></p>
        </div>';

    $mail->send();

    $conn->commit();
    respondWithJson(200, ['status' => 'success', 'message' => 'Login credentials were emailed successfully.']);
} catch (PHPMailerException $exception) {
    $conn->rollback();
    error_log('Unable to email customer credentials: ' . $exception->getMessage());
    respondWithJson(502, [
        'status' => 'error',
        'message' => 'SMTP Failed: ' . $exception->getMessage() . ' | User: ' . $mailUsername . ' | PassLen: ' . strlen($cleanPassword ?? '')
    ]);
} catch (mysqli_sql_exception | RuntimeException $exception) {
    $conn->rollback();
    error_log('Unable to update emailed customer credentials: ' . $exception->getMessage());
    respondWithJson(500, ['status' => 'error', 'message' => $exception->getMessage()]);
}