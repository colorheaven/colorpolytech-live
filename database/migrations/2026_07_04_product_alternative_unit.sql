-- Product alternative unit fields for Office ERP
-- Review before import. Do not auto-run on live server.
-- Backup colojmbr_office before applying.

ALTER TABLE products
    ADD COLUMN alternative_unit_id INT NULL AFTER unit_id,
    ADD COLUMN alternative_unit_qty DECIMAL(18,4) NOT NULL DEFAULT 1.0000 AFTER alternative_unit_id,
    ADD COLUMN base_qty_per_alternative_unit DECIMAL(18,4) NOT NULL DEFAULT 1.0000 AFTER alternative_unit_qty;

CREATE INDEX idx_products_alternative_unit_id ON products(alternative_unit_id);
