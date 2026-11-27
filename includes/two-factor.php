<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Endroid\QrCode\Builder\Builder;
use OTPHP\TOTP;

function ensureTwoFactorSchema(mysqli $conn): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $sql = "CREATE TABLE IF NOT EXISTS admin_two_factor (
        admin_id INT UNSIGNED NOT NULL PRIMARY KEY,
        secret_encrypted TEXT NOT NULL,
        is_enabled TINYINT(1) NOT NULL DEFAULT 0,
        verified_at TIMESTAMP NULL DEFAULT NULL,
        backup_codes_json MEDIUMTEXT NULL,
        totp_last_window BIGINT UNSIGNED NULL DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_two_factor_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (!$conn->query($sql)) {
        throw new RuntimeException('Unable to initialize 2FA schema: ' . $conn->error);
    }
    $ensured = true;
}

function twoFactorEncryptionKey(mysqli $conn): string
{
    $keyName = 'two_factor_encryption_key';
    $stmt = $conn->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
    $stmt->bind_param('s', $keyName);
    $stmt->execute();
    $stored = $stmt->get_result()->fetch_column();
    $stmt->close();

    if (is_string($stored) && strlen($stored) === 64) {
        $key = hex2bin($stored);
        if ($key !== false) {
            return $key;
        }
    }

    $envKey = getenv('APP_ENCRYPTION_KEY');
    if (is_string($envKey) && strlen($envKey) >= 32) {
        $key = hash('sha256', $envKey, true);
    } else {
        $key = random_bytes(32);
    }
    $hex = bin2hex($key);
    $save = $conn->prepare(
        "INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
    );
    $save->bind_param('ss', $keyName, $hex);
    if (!$save->execute()) {
        throw new RuntimeException('Unable to store the 2FA encryption key.');
    }
    $save->close();
    return $key;
}

function encryptTwoFactorSecret(mysqli $conn, string $secret): string
{
    $key = twoFactorEncryptionKey($conn);
    $nonce = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($secret, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);
    if ($cipher === false) {
        throw new RuntimeException('Unable to encrypt the 2FA secret.');
    }
    return 'v1:' . base64_encode($nonce . $tag . $cipher);
}

function decryptTwoFactorSecret(mysqli $conn, string $payload): ?string
{
    if (!str_starts_with($payload, 'v1:')) {
        return null;
    }
    $raw = base64_decode(substr($payload, 3), true);
    if ($raw === false || strlen($raw) < 28) {
        return null;
    }
    $nonce = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $cipher = substr($raw, 28);
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', twoFactorEncryptionKey($conn), OPENSSL_RAW_DATA, $nonce, $tag);
    return $plain === false ? null : $plain;
}

function getTwoFactorRecord(mysqli $conn, int $adminId): ?array
{
    $stmt = $conn->prepare('SELECT * FROM admin_two_factor WHERE admin_id = ? LIMIT 1');
    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function isTwoFactorEnabled(mysqli $conn, int $adminId): bool
{
    $record = getTwoFactorRecord($conn, $adminId);
    return $record !== null && (int) $record['is_enabled'] === 1;
}

function getTwoFactorTotp(mysqli $conn, int $adminId, ?string $secret = null): ?TOTP
{
    if ($secret === null) {
        $record = getTwoFactorRecord($conn, $adminId);
        if ($record === null) {
            return null;
        }
        $secret = decryptTwoFactorSecret($conn, (string) $record['secret_encrypted']);
    }
    if ($secret === null || $secret === '') {
        return null;
    }
    $totp = TOTP::createFromSecret($secret);
    $totp->setDigits(6);
    $totp->setPeriod(30);
    $totp->setDigest('sha1');
    return $totp;
}

function verifyTwoFactorTotp(mysqli $conn, int $adminId, string $otp, bool $consumeReplay = true): bool
{
    if (!preg_match('/^\d{6}$/', $otp)) {
        return false;
    }
    $record = getTwoFactorRecord($conn, $adminId);
    $totp = getTwoFactorTotp($conn, $adminId);
    if ($record === null || $totp === null) {
        return false;
    }
    if (!$totp->verify($otp, null, 1)) {
        return false;
    }

    if ($consumeReplay) {
        $window = (int) floor(time() / 30);
        $lastWindow = $record['totp_last_window'] !== null ? (int) $record['totp_last_window'] : null;
        if ($lastWindow === $window) {
            return false;
        }
        $stmt = $conn->prepare('UPDATE admin_two_factor SET totp_last_window = ? WHERE admin_id = ?');
        $stmt->bind_param('ii', $window, $adminId);
        $stmt->execute();
        $stmt->close();
    }
    return true;
}

function generateTwoFactorBackupCodes(mysqli $conn, int $adminId): array
{
    $codes = [];
    $stored = [];
    for ($i = 0; $i < 10; $i++) {
        $code = strtoupper(substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(6))), 0, 10));
        $codes[] = $code;
        $stored[] = [
            'hash' => password_hash($code, PASSWORD_DEFAULT),
            'used_at' => null,
        ];
    }
    $json = json_encode($stored, JSON_UNESCAPED_SLASHES);
    $stmt = $conn->prepare(
        'UPDATE admin_two_factor SET backup_codes_json = ? WHERE admin_id = ?'
    );
    $stmt->bind_param('si', $json, $adminId);
    $stmt->execute();
    $stmt->close();
    return $codes;
}

function verifyTwoFactorBackupCode(mysqli $conn, int $adminId, string $code): bool
{
    $record = getTwoFactorRecord($conn, $adminId);
    if ($record === null) {
        return false;
    }
    $stored = json_decode((string) $record['backup_codes_json'], true);
    if (!is_array($stored)) {
        return false;
    }
    $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
    foreach ($stored as $index => $entry) {
        if (!is_array($entry) || !empty($entry['used_at'])) {
            continue;
        }
        if (password_verify($code, (string) ($entry['hash'] ?? ''))) {
            $stored[$index]['used_at'] = date('Y-m-d H:i:s');
            $json = json_encode($stored, JSON_UNESCAPED_SLASHES);
            $stmt = $conn->prepare('UPDATE admin_two_factor SET backup_codes_json = ? WHERE admin_id = ?');
            $stmt->bind_param('si', $json, $adminId);
            $stmt->execute();
            $stmt->close();
            return true;
        }
    }
    return false;
}

function twoFactorQrDataUri(string $secret, string $issuer, string $account): string
{
    $totp = TOTP::createFromSecret($secret);
    $totp->setLabel($account);
    $totp->setIssuer($issuer);
    $totp->setDigits(6);
    $totp->setPeriod(30);
    $totp->setDigest('sha1');
    $builder = new Builder();
    $result = $builder->build(
        data: $totp->getProvisioningUri(),
        size: 240,
        margin: 8
    );
    return $result->getDataUri();
}

function twoFactorOtpRateLimit(string $scope): bool
{
    $key = '2fa_attempt_' . $scope;
    $lockKey = $key . '_locked_until';
    $now = time();
    if ((int) ($_SESSION[$lockKey] ?? 0) > $now) {
        return false;
    }
    $_SESSION[$key] = ((int) ($_SESSION[$key] ?? 0)) + 1;
    if ((int) $_SESSION[$key] >= 5) {
        $_SESSION[$lockKey] = $now + 900;
        $_SESSION[$key] = 0;
        return false;
    }
    return true;
}

function resetTwoFactorOtpRateLimit(string $scope): void
{
    $key = '2fa_attempt_' . $scope;
    unset($_SESSION[$key], $_SESSION[$key . '_locked_until']);
}

function twoFactorSessionVerified(mysqli $conn, int $adminId): bool
{
    if (!isTwoFactorEnabled($conn, $adminId)) {
        return true;
    }
    $verifiedAdmin = (int) ($_SESSION['2fa_verified_admin_id'] ?? 0);
    $verifiedAt = (int) ($_SESSION['2fa_verified_at'] ?? 0);
    $timeout = 60;
    $setting = $conn->query("SELECT setting_value FROM app_settings WHERE setting_key = 'session_timeout_minutes' LIMIT 1");
    if ($setting) {
        $value = (int) ($setting->fetch_column() ?? 60);
        if ($value >= 5) {
            $timeout = $value;
        }
    }
    return $verifiedAdmin === $adminId
        && $verifiedAt > 0
        && (time() - $verifiedAt) < ($timeout * 60);
}
