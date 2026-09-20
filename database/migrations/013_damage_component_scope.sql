ALTER TABLE damage_cases ADD COLUMN order_component_id BIGINT UNSIGNED NULL AFTER order_run_id;
CREATE INDEX idx_damage_component ON damage_cases(order_component_id);
