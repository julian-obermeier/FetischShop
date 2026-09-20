CREATE TABLE IF NOT EXISTS offer_components(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 offer_version_id BIGINT UNSIGNED NOT NULL,
 category_id BIGINT UNSIGNED NOT NULL,
 component_type ENUM('physical','digital') NOT NULL,
 title VARCHAR(190) NOT NULL,
 compensation DECIMAL(10,2) NOT NULL DEFAULT 0,
 fulfillment_model VARCHAR(40) NOT NULL,
 duration_value INT NULL,
 duration_unit VARCHAR(30) NULL,
 config_json LONGTEXT NULL,
 sort_order INT NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL,
 INDEX idx_offer_component_version(offer_version_id),
 INDEX idx_offer_component_category(category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS offer_templates(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(190) NOT NULL,
 category_id BIGINT UNSIGNED NULL,
 template_json LONGTEXT NOT NULL,
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
