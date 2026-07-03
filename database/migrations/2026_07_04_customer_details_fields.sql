-- Customer detail fields for Office ERP
-- Review before import. Do not auto-run on live server.
-- Backup colojmbr_office before applying.

ALTER TABLE customers
    ADD COLUMN customer_code VARCHAR(50) NULL AFTER id,
    ADD COLUMN contact_person VARCHAR(150) NULL AFTER company_name,
    ADD COLUMN contact_number VARCHAR(50) NULL AFTER mobile,
    ADD COLUMN sms_number VARCHAR(50) NULL AFTER contact_number;

CREATE INDEX idx_customers_customer_code ON customers(customer_code);
CREATE INDEX idx_customers_sms_number ON customers(sms_number);
