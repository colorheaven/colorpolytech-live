-- Office ERP database compatibility fixes
-- Backup `colojmbr_office` before applying.
-- Import manually through phpMyAdmin only if these tables are missing.

CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(120) NOT NULL,
    setting_value TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_settings_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sms_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    api_url TEXT NULL,
    balance_url TEXT NULL,
    sender_id VARCHAR(100) NULL,
    username VARCHAR(190) NULL,
    api_key TEXT NULL,
    api_key_encrypted TEXT NULL,
    password_encrypted TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NULL,
    title VARCHAR(190) NOT NULL DEFAULT 'Notification',
    message TEXT NULL,
    module VARCHAR(100) NULL,
    link VARCHAR(255) NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notifications_user_read (user_id, is_read),
    KEY idx_notifications_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
