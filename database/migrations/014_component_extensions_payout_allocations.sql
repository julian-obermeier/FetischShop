ALTER TABLE violations ADD COLUMN order_component_id BIGINT UNSIGNED NULL AFTER order_id;
CREATE INDEX idx_violation_component ON violations(order_component_id);

ALTER TABLE extension_days ADD COLUMN order_component_id BIGINT UNSIGNED NULL AFTER order_id;
CREATE INDEX idx_extension_component ON extension_days(order_component_id);

ALTER TABLE manual_extra_days ADD COLUMN order_component_id BIGINT UNSIGNED NULL AFTER order_id;
CREATE INDEX idx_manual_extra_component ON manual_extra_days(order_component_id);

CREATE TABLE IF NOT EXISTS payout_order_allocations(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 payout_request_id BIGINT UNSIGNED NOT NULL,
 order_id BIGINT UNSIGNED NOT NULL,
 amount DECIMAL(12,2) NOT NULL,
 created_at DATETIME NOT NULL,
 UNIQUE KEY uq_payout_order(payout_request_id,order_id),
 INDEX idx_payout_allocation_order(order_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE orders ADD COLUMN paid_out_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER current_total;
