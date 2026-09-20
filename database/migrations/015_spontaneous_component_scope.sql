ALTER TABLE spontaneous_requests ADD COLUMN order_component_id BIGINT UNSIGNED NULL AFTER order_id;
CREATE INDEX idx_spontaneous_component ON spontaneous_requests(order_component_id);
