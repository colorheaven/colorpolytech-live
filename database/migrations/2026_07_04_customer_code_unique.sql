-- Customer code uniqueness for Office ERP
-- Review before import. Do not auto-run on live server.
-- Backup colojmbr_office before applying.

-- Before adding the unique index, make sure existing duplicate/blank customer codes are cleaned.
-- Suggested check:
-- SELECT customer_code, COUNT(*) total FROM customers GROUP BY customer_code HAVING customer_code IS NOT NULL AND customer_code<>'' AND COUNT(*)>1;

CREATE UNIQUE INDEX idx_customers_customer_code_unique ON customers(customer_code);
