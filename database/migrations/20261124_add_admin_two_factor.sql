-- 2FA migration for ecommerce admin dashboard

CREATE TABLE IF NOT EXISTS admin_two_factor (
    admin_id INT UNSIGNED NOT NULL PRIMARY KEY,
    secret_encrypted TEXT NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 0,
    verified_at TIMESTAMP NULL DEFAULT NULL,
    backup_codes_json MEDIUMTEXT NULL,
    totp_last_window BIGINT UNSIGNED NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_two_factor_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
