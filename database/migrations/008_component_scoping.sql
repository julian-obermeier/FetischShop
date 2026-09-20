ALTER TABLE order_items ADD COLUMN order_component_id BIGINT UNSIGNED NULL AFTER order_run_id;
CREATE INDEX idx_order_item_component ON order_items(order_component_id);

ALTER TABLE order_days ADD COLUMN order_component_id BIGINT UNSIGNED NULL AFTER order_run_id;
CREATE INDEX idx_order_day_component ON order_days(order_component_id);

ALTER TABLE evidences ADD COLUMN order_component_id BIGINT UNSIGNED NULL AFTER order_run_id;
CREATE INDEX idx_evidence_component ON evidences(order_component_id);

ALTER TABLE tasks ADD COLUMN order_component_id BIGINT UNSIGNED NULL AFTER order_id;
CREATE INDEX idx_task_component ON tasks(order_component_id);

ALTER TABLE shipping_workflows ADD COLUMN order_component_id BIGINT UNSIGNED NULL AFTER order_id;
ALTER TABLE shipping_workflows DROP INDEX uq_shipping_order;
CREATE UNIQUE INDEX uq_shipping_order_component ON shipping_workflows(order_id,order_component_id);
