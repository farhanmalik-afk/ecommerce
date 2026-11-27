<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Use POST to sign out.');
}

$postedToken = $_POST['csrf_token'] ?? '';
if (!is_string($postedToken) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $postedToken)) {
    http_response_code(403);
    exit('Invalid request token.');
}

revokeAdminSession($conn, getCurrentAdminId());
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $cookie = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $cookie['path'], $cookie['domain'], $cookie['secure'], $cookie['httponly']);
}
session_destroy();

header('Location: ' . getBaseUrl() . 'login.php');
exit();
