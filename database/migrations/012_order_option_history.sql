ALTER TABLE order_options ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER config_snapshot;
ALTER TABLE order_options ADD COLUMN removed_at DATETIME NULL AFTER is_active;
CREATE INDEX idx_order_option_active ON order_options(order_id,is_active);
