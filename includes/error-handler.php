<?php

function isJsonRequest(): bool
{
    $fetchDestination = strtolower($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '');
    $fetchMode = strtolower($_SERVER['HTTP_SEC_FETCH_MODE'] ?? '');
    return (defined('APP_JSON_RESPONSE') && APP_JSON_RESPONSE === true)
        || stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
        || stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false
        || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
        || ($fetchDestination === 'empty' && $fetchMode !== '' && $fetchMode !== 'navigate');
}

function sendAppError(int $statusCode, string $message, string $type = 'error'): void
{
    http_response_code($statusCode);
    header('Cache-Control: no-store');
    if (isJsonRequest()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'error',
            'error' => $type,
            'code' => $statusCode,
            'message' => $message,
        ], JSON_UNESCAPED_SLASHES);
        exit();
    }

    $details = match ($statusCode) {
        401 => ['Unauthorized', 'Please sign in to continue.', 'bi-person-lock'],
        403 => $type === 'module'
            ? ['Module Unavailable', 'This module has been disabled by an administrator. Contact your administrator if you need access.', 'bi-slash-circle']
            : ['Access Denied', $message, 'bi-shield-lock'],
        404 => ['Page Not Found', 'The page may have moved or is no longer available.', 'bi-compass'],
        default => ['Something Went Wrong', 'We could not complete your request. Please try again later.', 'bi-exclamation-triangle'],
    };
    $title = $details[0];
    $description = $statusCode === 401 ? $details[1] : $message;
    $icon = $details[2];
    $theme = function_exists('appThemeName') ? appThemeName() : 'original';
    $base = function_exists('getBaseUrl') ? getBaseUrl() : '/';
    $isAuthenticated = !empty($_SESSION['admin_id']);
    $buttonHref = $isAuthenticated ? $base . 'index.php' : $base . 'login.php';
    $buttonLabel = $isAuthenticated ? 'Back to Dashboard' : 'Sign In';
    $stylePath = $base . 'assets/css/style.css';
    $language = function_exists('appLanguage') ? appLanguage() : 'en';

    echo '<!doctype html><html lang="' . htmlspecialchars($language, ENT_QUOTES, 'UTF-8') . '" data-theme="' . htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>'
        . $statusCode . ' - ' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
        . '</title><link rel="stylesheet" href="' . htmlspecialchars($stylePath, ENT_QUOTES, 'UTF-8')
        . '"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"><style>'
        . 'body{min-height:100vh;display:grid;place-items:center;padding:24px;background:var(--bs-body-bg,#f4f6fb)}'
        . '.app-error{width:min(100%,520px);padding:clamp(28px,6vw,56px);text-align:center;border-radius:20px;background:var(--bs-white,#fff);box-shadow:0 20px 60px #14213d18}'
        . '.app-error-icon{width:78px;height:78px;margin:0 auto 24px;display:grid;place-items:center;border-radius:24px;background:#dc35451a;color:#dc3545;font-size:36px}'
        . '.app-error-code{font-size:clamp(3rem,12vw,5rem);line-height:1;font-weight:800;letter-spacing:-.06em;color:var(--bs-emphasis-color,#182230)}'
        . '.app-error h1{margin:18px 0 10px;font-size:1.55rem;font-weight:700;color:var(--bs-emphasis-color,#182230)}'
        . '.app-error p{margin:0 auto 28px;max-width:380px;color:var(--bs-secondary-color,#667085);line-height:1.65}'
        . '.app-error a{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:12px 20px;border-radius:10px;background:#182230;color:white;text-decoration:none;font-weight:600}'
        . '@media(max-width:480px){.app-error{border-radius:14px}.app-error-icon{width:64px;height:64px}}'
        . '</style></head><body class="theme-' . htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') . '" data-theme="' . htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') . '"><main class="app-error" role="main"><div class="app-error-icon" aria-hidden="true"><i class="bi ' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . '"></i></div><div class="app-error-code">'
        . $statusCode . '</div><h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
        . '</h1><p>' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8')
        . '</p><a href="' . htmlspecialchars($buttonHref, ENT_QUOTES, 'UTF-8') . '"><i class="bi bi-arrow-left"></i> '
        . htmlspecialchars($buttonLabel, ENT_QUOTES, 'UTF-8') . '</a></main></body></html>';
    exit();
}

set_exception_handler(static function (Throwable $exception): void {
    error_log('Unhandled application exception: ' . $exception->getMessage());
    if (!headers_sent()) {
        sendAppError(500, 'An unexpected error occurred. Please try again later.');
    }
    exit();
});