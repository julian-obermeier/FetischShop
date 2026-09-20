ALTER TABLE order_components ADD COLUMN fulfillment_model VARCHAR(40) NULL AFTER compensation;
ALTER TABLE order_components ADD COLUMN duration_value INT NULL AFTER fulfillment_model;
ALTER TABLE order_components ADD COLUMN duration_unit VARCHAR(30) NULL AFTER duration_value;
ALTER TABLE order_components ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER config_json;

ALTER TABLE evidences ADD COLUMN precheck_requirement_id BIGINT UNSIGNED NULL AFTER order_component_id;
ALTER TABLE evidences ADD COLUMN retake_of_evidence_id BIGINT UNSIGNED NULL AFTER precheck_requirement_id;
CREATE INDEX idx_evidence_precheck_requirement ON evidences(precheck_requirement_id);

CREATE TABLE IF NOT EXISTS precheck_requirements(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_id BIGINT UNSIGNED NOT NULL,
 order_run_id BIGINT UNSIGNED NOT NULL,
 order_component_id BIGINT UNSIGNED NULL,
 requirement_key VARCHAR(120) NOT NULL,
 label VARCHAR(190) NOT NULL,
 description TEXT NULL,
 required_count INT NOT NULL DEFAULT 1,
 sort_order INT NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL,
 UNIQUE KEY uq_precheck_requirement(order_run_id,order_component_id,requirement_key),
 INDEX idx_precheck_order_run(order_id,order_run_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_adjustments(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_id BIGINT UNSIGNED NOT NULL,
 order_component_id BIGINT UNSIGNED NULL,
 adjustment_type ENUM('bonus','price_change','option_add','option_remove','shipping_subsidy','other') NOT NULL,
 label VARCHAR(190) NOT NULL,
 amount DECIMAL(12,2) NOT NULL DEFAULT 0,
 effective_day_no INT NULL,
 status ENUM('reserved','active','released','cancelled') NOT NULL DEFAULT 'active',
 metadata_json LONGTEXT NULL,
 created_at DATETIME NOT NULL,
 cancelled_at DATETIME NULL,
 INDEX idx_adjustment_order(order_id,status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
