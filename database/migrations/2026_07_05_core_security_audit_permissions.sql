-- Office ERP core security, audit, soft delete, permission override and SMS foundation
-- Review before import. Do not auto-run on live server.
-- Backup `colojmbr_office` before applying this migration.
-- Some ALTER statements may fail if the column already exists; run only the parts needed for your live database.

-- 1) User-wise custom permission override
CREATE TABLE IF NOT EXISTS user_permission_overrides (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    permission_code VARCHAR(120) NOT NULL,
    effect ENUM('allow','deny') NOT NULL DEFAULT 'allow',
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by INT NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_user_permission_override (user_id, permission_code),
    KEY idx_user_permission_override_user (user_id),
    KEY idx_user_permission_override_code (permission_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2) Full audit log foundation
CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    user_name VARCHAR(150) NULL,
    role_name VARCHAR(100) NULL,
    module VARCHAR(100) NOT NULL,
    action VARCHAR(80) NOT NULL,
    record_id BIGINT NULL,
    voucher_type VARCHAR(80) NULL,
    voucher_no VARCHAR(100) NULL,
    old_value JSON NULL,
    new_value JSON NULL,
    reason TEXT NULL,
    ip_address VARCHAR(64) NULL,
    user_agent TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_module_record (module, record_id),
    KEY idx_audit_voucher (voucher_type, voucher_no),
    KEY idx_audit_user (user_id),
    KEY idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3) SMS template and log foundation
CREATE TABLE IF NOT EXISTS sms_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_key VARCHAR(100) NOT NULL,
    voucher_type VARCHAR(80) NULL,
    title VARCHAR(150) NOT NULL,
    message_template TEXT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by INT NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_sms_template_event_voucher (event_key, voucher_type),
    KEY idx_sms_template_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sms_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    receiver_type VARCHAR(50) NULL,
    receiver_id BIGINT NULL,
    mobile VARCHAR(50) NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    voucher_type VARCHAR(80) NULL,
    voucher_no VARCHAR(100) NULL,
    message TEXT NOT NULL,
    status ENUM('Pending','Sent','Failed') NOT NULL DEFAULT 'Pending',
    api_response TEXT NULL,
    sent_by INT NULL,
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sms_receiver (receiver_type, receiver_id),
    KEY idx_sms_voucher (voucher_type, voucher_no),
    KEY idx_sms_status (status),
    KEY idx_sms_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS voucher_sms_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    voucher_type VARCHAR(80) NOT NULL,
    event_key VARCHAR(100) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    updated_by INT NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_voucher_sms_setting (voucher_type, event_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4) Soft delete fields for master data.
-- Run only if these columns do not already exist.
-- ALTER TABLE customers ADD COLUMN deleted_at DATETIME NULL, ADD COLUMN deleted_by INT NULL, ADD COLUMN delete_reason TEXT NULL;
-- ALTER TABLE suppliers ADD COLUMN deleted_at DATETIME NULL, ADD COLUMN deleted_by INT NULL, ADD COLUMN delete_reason TEXT NULL;
-- ALTER TABLE products ADD COLUMN deleted_at DATETIME NULL, ADD COLUMN deleted_by INT NULL, ADD COLUMN delete_reason TEXT NULL;

-- 5) Voucher edit/delete audit fields.
-- Run only for existing transaction tables that need these fields.
-- ALTER TABLE sales_orders ADD COLUMN edit_reason TEXT NULL, ADD COLUMN deleted_at DATETIME NULL, ADD COLUMN deleted_by INT NULL, ADD COLUMN delete_reason TEXT NULL;
-- ALTER TABLE delivery_challans ADD COLUMN edit_reason TEXT NULL, ADD COLUMN deleted_at DATETIME NULL, ADD COLUMN deleted_by INT NULL, ADD COLUMN delete_reason TEXT NULL;
-- ALTER TABLE sales_invoices ADD COLUMN edit_reason TEXT NULL, ADD COLUMN deleted_at DATETIME NULL, ADD COLUMN deleted_by INT NULL, ADD COLUMN delete_reason TEXT NULL;
-- ALTER TABLE collections ADD COLUMN edit_reason TEXT NULL, ADD COLUMN deleted_at DATETIME NULL, ADD COLUMN deleted_by INT NULL, ADD COLUMN delete_reason TEXT NULL;
-- ALTER TABLE purchases ADD COLUMN edit_reason TEXT NULL, ADD COLUMN deleted_at DATETIME NULL, ADD COLUMN deleted_by INT NULL, ADD COLUMN delete_reason TEXT NULL;
-- ALTER TABLE payments ADD COLUMN edit_reason TEXT NULL, ADD COLUMN deleted_at DATETIME NULL, ADD COLUMN deleted_by INT NULL, ADD COLUMN delete_reason TEXT NULL;

-- 6) Recommended permission codes to seed if missing.
-- Adjust to match your live permissions table structure before import.
-- Examples:
-- reports.view, reports.export, reports.print
-- customers.bulk_delete, suppliers.bulk_delete, products.bulk_delete
-- sales_orders.approve, sales_orders.reject, sales_orders.sms_send
-- delivery_challans.approve, delivery_challans.sms_send
-- sales_invoices.approve, sales_invoices.edit_approved, sales_invoices.delete_approved, sales_invoices.sms_send
-- collections.approve, collections.edit_approved, collections.delete_approved, collections.sms_send
-- purchases.approve, payments.approve
-- audit_logs.view, sms_logs.view, backup.export
