<?php

function secure_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    if (!session_start()) {
        throw new RuntimeException('Unable to start a secure session.');
    }
}

function e($value): string
{
    if (!is_scalar($value) && $value !== null) {
        return '';
    }

    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        secure_session_start();
    }

    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf($token): bool
{
    if (!is_string($token) || $token === '') {
        return false;
    }

    foreach ([
        'csrf_token',
        'login_csrf',
        'setup_csrf',
        '2fa_csrf',
        'settings_csrf',
        'notification_csrf',
        'pos_checkout_csrf',
        'credential_email_csrf',
    ] as $sessionKey) {
        $expected = $_SESSION[$sessionKey] ?? null;
        if (is_string($expected) && $expected !== '' && hash_equals($expected, $token)) {
            return true;
        }
    }

    return false;
}

function enforce_post_csrf(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }

    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!verify_csrf($token)) {
        sendAppError(403, 'Your request could not be verified. Refresh the page and try again.');
    }
}

function require_auth(array $roles = []): void
{
    checkAuth();
    if ($roles === []) {
        return;
    }

    $role = currentAdminRole();
    $roleName = strtolower(trim((string) ($role['name'] ?? '')));
    $allowedRoles = array_map(
        static function ($value): string {
            return is_string($value) ? strtolower(trim($value)) : '';
        },
        $roles
    );
    if ($roleName === '' || !in_array($roleName, $allowedRoles, true)) {
        sendAppError(403, 'You do not have permission to access this page.');
    }
}

function ensureSecuritySchema(mysqli $conn): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $queries = [
        "CREATE TABLE IF NOT EXISTS admin_sessions (
            admin_id INT UNSIGNED NOT NULL PRIMARY KEY,
            token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            login_ip VARCHAR(45) NOT NULL,
            user_agent VARCHAR(512) NOT NULL,
            logged_in_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_admin_sessions_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS admin_login_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            admin_id INT UNSIGNED NOT NULL,
            login_ip VARCHAR(45) NOT NULL,
            user_agent VARCHAR(512) NOT NULL,
            logged_in_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_admin_login_logs_admin_date (admin_id, logged_in_at),
            CONSTRAINT fk_admin_login_logs_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];

    foreach ($queries as $sql) {
        if (!$conn->query($sql)) {
            throw new RuntimeException('Unable to initialize security schema: ' . $conn->error);
        }
    }
    $ensured = true;
}

function createAdminSession(mysqli $conn, int $adminId): void
{
    if ($adminId < 1) {
        throw new InvalidArgumentException('A valid administrator is required to create a session.');
    }

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    $loginIp = filter_var($remoteAddress, FILTER_VALIDATE_IP) !== false ? $remoteAddress : 'UNKNOWN';
    $userAgent = preg_replace('/[\x00-\x1F\x7F]/', '', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $userAgent = substr($userAgent ?? '', 0, 512);

    $conn->begin_transaction();
    try {
        $sessionStmt = $conn->prepare(
            'INSERT INTO admin_sessions (admin_id, token_hash, login_ip, user_agent, logged_in_at)
             VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE token_hash = VALUES(token_hash), login_ip = VALUES(login_ip),
             user_agent = VALUES(user_agent), logged_in_at = CURRENT_TIMESTAMP'
        );
        $sessionStmt->bind_param('isss', $adminId, $tokenHash, $loginIp, $userAgent);
        $sessionStmt->execute();
        $sessionStmt->close();

        $logStmt = $conn->prepare(
            'INSERT INTO admin_login_logs (admin_id, login_ip, user_agent) VALUES (?, ?, ?)'
        );
        $logStmt->bind_param('iss', $adminId, $loginIp, $userAgent);
        $logStmt->execute();
        $logStmt->close();

        $conn->commit();
    } catch (Throwable $exception) {
        $conn->rollback();
        error_log('Unable to create the administrator session: ' . $exception->getMessage());
        throw new RuntimeException('Unable to establish a secure administrator session.');
    }

    $_SESSION['admin_session_token'] = $token;
}

function revokeAdminSession(mysqli $conn, int $adminId): void
{
    $token = $_SESSION['admin_session_token'] ?? null;
    if ($adminId < 1 || !is_string($token) || $token === '') {
        return;
    }

    $tokenHash = hash('sha256', $token);
    $stmt = $conn->prepare('DELETE FROM admin_sessions WHERE admin_id = ? AND token_hash = ?');
    $stmt->bind_param('is', $adminId, $tokenHash);
    $stmt->execute();
    $stmt->close();
}

function safe_upload(array $file, string $destinationDirectory, int $maxBytes, array $allowedTypes = []): string
{
    if (($file['error'] ?? null) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The uploaded file could not be received.');
    }
    if ($maxBytes < 1 || !isset($file['tmp_name'], $file['name'], $file['size'])
        || !is_string($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('The uploaded file is invalid.');
    }

    if ($allowedTypes === []) {
        $allowedTypes = [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'webp' => ['image/webp'],
        ];
    }

    $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    $allowedMimes = $allowedTypes[$extension] ?? null;
    if (!is_array($allowedMimes) || $allowedMimes === []) {
        throw new RuntimeException('This file type is not allowed.');
    }

    $fileSize = filesize($file['tmp_name']);
    if ($fileSize === false || $fileSize < 1 || $fileSize > $maxBytes) {
        throw new RuntimeException('The uploaded file exceeds the allowed size.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!is_string($mime) || !in_array($mime, $allowedMimes, true)) {
        throw new RuntimeException('The file contents do not match an allowed file type.');
    }
    if (str_starts_with($mime, 'image/')) {
        $image = getimagesize($file['tmp_name']);
        if ($image === false || ($image['mime'] ?? '') !== $mime) {
            throw new RuntimeException('The uploaded image is invalid.');
        }
    }

    $uploadsRoot = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads';
    if (!is_dir($uploadsRoot) && !mkdir($uploadsRoot, 0755, true) && !is_dir($uploadsRoot)) {
        throw new RuntimeException('The upload directory is unavailable.');
    }
    if (!is_dir($destinationDirectory) && !mkdir($destinationDirectory, 0755, true) && !is_dir($destinationDirectory)) {
        throw new RuntimeException('The upload directory is unavailable.');
    }

    $resolvedRoot = realpath($uploadsRoot);
    $resolvedDirectory = realpath($destinationDirectory);
    if ($resolvedRoot === false || $resolvedDirectory === false) {
        throw new RuntimeException('The upload directory is unavailable.');
    }
    $rootPrefix = rtrim($resolvedRoot, '/\\') . DIRECTORY_SEPARATOR;
    $directoryPrefix = rtrim($resolvedDirectory, '/\\') . DIRECTORY_SEPARATOR;
    if ($resolvedDirectory !== $resolvedRoot
        && strncasecmp($directoryPrefix, $rootPrefix, strlen($rootPrefix)) !== 0) {
        throw new RuntimeException('Uploads must be stored inside the uploads directory.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $extension;
    if (!move_uploaded_file($file['tmp_name'], $resolvedDirectory . DIRECTORY_SEPARATOR . $filename)) {
        throw new RuntimeException('The uploaded file could not be saved.');
    }

    return $filename;
}
