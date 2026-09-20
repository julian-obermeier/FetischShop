CREATE TABLE IF NOT EXISTS admins(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sellers(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 first_name VARCHAR(120) NOT NULL,
 last_name VARCHAR(120) NOT NULL,
 birth_date DATE NOT NULL,
 street VARCHAR(190) NOT NULL,
 postal_code VARCHAR(20) NOT NULL,
 city VARCHAR(120) NOT NULL,
 phone VARCHAR(80) NOT NULL,
 email VARCHAR(190) NOT NULL UNIQUE,
 email_verified_at DATETIME NULL,
 password_hash VARCHAR(255) NOT NULL,
 accepted_terms_version VARCHAR(40) NOT NULL,
 accepted_privacy_version VARCHAR(40) NOT NULL,
 accepted_content_rules_version VARCHAR(40) NOT NULL,
 accepted_evidence_rules_version VARCHAR(40) NOT NULL,
 accepted_shipping_rules_version VARCHAR(40) NOT NULL,
 accepted_wallet_rules_version VARCHAR(40) NOT NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 deleted_at DATETIME NULL,
 INDEX idx_sellers_email_verified(email_verified_at),
 INDEX idx_sellers_deleted(deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_verifications(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 seller_id BIGINT UNSIGNED NOT NULL,
 token_hash CHAR(64) NOT NULL UNIQUE,
 email_snapshot VARCHAR(190) NOT NULL,
 expires_at DATETIME NOT NULL,
 used_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 INDEX idx_email_verify_seller(seller_id),
 INDEX idx_email_verify_exp(expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 seller_id BIGINT UNSIGNED NULL,
 admin_id BIGINT UNSIGNED NULL,
 token_hash CHAR(64) NOT NULL UNIQUE,
 expires_at DATETIME NOT NULL,
 used_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 INDEX idx_password_reset_exp(expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 bucket_key VARCHAR(190) NOT NULL,
 created_at DATETIME NOT NULL,
 INDEX idx_rate_bucket_created(bucket_key,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 parent_id BIGINT UNSIGNED NULL,
 name VARCHAR(190) NOT NULL,
 slug VARCHAR(190) NOT NULL UNIQUE,
 icon VARCHAR(80) NULL,
 is_system_template TINYINT(1) NOT NULL DEFAULT 0,
 is_digital TINYINT(1) NOT NULL DEFAULT 0,
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 sort_order INT NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 INDEX idx_category_parent(parent_id),
 INDEX idx_category_active_sort(is_active,sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS category_fields(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 category_id BIGINT UNSIGNED NOT NULL,
 field_key VARCHAR(120) NOT NULL,
 label VARCHAR(190) NOT NULL,
 field_type ENUM('text','number','select','multiselect','boolean','date') NOT NULL,
 options_json LONGTEXT NULL,
 is_required TINYINT(1) NOT NULL DEFAULT 0,
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 sort_order INT NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 UNIQUE KEY uq_category_field(category_id,field_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS offers(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 category_id BIGINT UNSIGNED NOT NULL,
 seller_id BIGINT UNSIGNED NULL,
 current_version_id BIGINT UNSIGNED NULL,
 title VARCHAR(190) NOT NULL,
 status ENUM('draft','active','disabled') NOT NULL DEFAULT 'draft',
 is_private TINYINT(1) NOT NULL DEFAULT 0,
 acceptance_deadline DATETIME NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 INDEX idx_offer_status(status),
 INDEX idx_offer_category(category_id),
 INDEX idx_offer_private_seller(is_private,seller_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS offer_versions(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 offer_id BIGINT UNSIGNED NOT NULL,
 version_no INT NOT NULL,
 title VARCHAR(190) NOT NULL,
 description LONGTEXT NOT NULL,
 compensation DECIMAL(10,2) NOT NULL,
 fulfillment_model VARCHAR(40) NOT NULL,
 duration_value INT NULL,
 duration_unit VARCHAR(20) NULL,
 rules_json LONGTEXT NULL,
 evidence_json LONGTEXT NULL,
 start_control_json LONGTEXT NULL,
 shipping_json LONGTEXT NULL,
 end_workflow_json LONGTEXT NULL,
 violation_json LONGTEXT NULL,
 settings_json LONGTEXT NULL,
 created_at DATETIME NOT NULL,
 UNIQUE KEY uq_offer_version(offer_id,version_no),
 INDEX idx_offer_version_offer(offer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS offer_options(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 offer_version_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(190) NOT NULL,
 description TEXT NULL,
 price DECIMAL(10,2) NOT NULL DEFAULT 0,
 requirements_json LONGTEXT NULL,
 sort_order INT NOT NULL DEFAULT 0,
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL,
 INDEX idx_offer_option_version(offer_version_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS offer_tasks(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 offer_version_id BIGINT UNSIGNED NOT NULL,
 task_template_id BIGINT UNSIGNED NULL,
 title VARCHAR(190) NOT NULL,
 config_json LONGTEXT NOT NULL,
 sort_order INT NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL,
 INDEX idx_offer_task_version(offer_version_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_sequences(
 year SMALLINT UNSIGNED PRIMARY KEY,
 sequence INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_number CHAR(8) NOT NULL UNIQUE,
 seller_id BIGINT UNSIGNED NOT NULL,
 offer_id BIGINT UNSIGNED NOT NULL,
 offer_version_id BIGINT UNSIGNED NOT NULL,
 status VARCHAR(50) NOT NULL,
 phase VARCHAR(30) NOT NULL DEFAULT 'preparation',
 accepted_at DATETIME NOT NULL,
 started_at DATETIME NULL,
 finished_at DATETIME NULL,
 archived_at DATETIME NULL,
 base_compensation DECIMAL(10,2) NOT NULL,
 current_total DECIMAL(10,2) NOT NULL,
 config_snapshot LONGTEXT NOT NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 INDEX idx_orders_seller_status(seller_id,status),
 INDEX idx_orders_offer(offer_id),
 INDEX idx_orders_phase(phase)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_components(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_id BIGINT UNSIGNED NOT NULL,
 category_id BIGINT UNSIGNED NOT NULL,
 component_type ENUM('physical','digital') NOT NULL,
 title VARCHAR(190) NOT NULL,
 compensation DECIMAL(10,2) NOT NULL DEFAULT 0,
 status VARCHAR(40) NOT NULL DEFAULT 'preparation',
 deadline DATETIME NULL,
 config_json LONGTEXT NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 INDEX idx_component_order(order_id),
 INDEX idx_component_category(category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_runs(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_id BIGINT UNSIGNED NOT NULL,
 run_no INT NOT NULL,
 status VARCHAR(40) NOT NULL,
 restart_reason TEXT NULL,
 started_at DATETIME NULL,
 ended_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 UNIQUE KEY uq_order_run(order_id,run_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_id BIGINT UNSIGNED NOT NULL,
 order_run_id BIGINT UNSIGNED NOT NULL,
 category_id BIGINT UNSIGNED NOT NULL,
 short_name VARCHAR(190) NOT NULL,
 size VARCHAR(80) NULL,
 color VARCHAR(120) NULL,
 brand VARCHAR(120) NULL,
 material VARCHAR(120) NULL,
 attributes_json LONGTEXT NULL,
 created_at DATETIME NOT NULL,
 INDEX idx_order_item_run(order_run_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_options(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_id BIGINT UNSIGNED NOT NULL,
 offer_option_id BIGINT UNSIGNED NULL,
 name VARCHAR(190) NOT NULL,
 price DECIMAL(10,2) NOT NULL,
 config_snapshot LONGTEXT NULL,
 created_at DATETIME NOT NULL,
 INDEX idx_order_option_order(order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_days(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_id BIGINT UNSIGNED NOT NULL,
 order_run_id BIGINT UNSIGNED NOT NULL,
 day_no INT NULL,
 calendar_date DATE NOT NULL,
 day_type ENUM('start','regular','violation_extension','manual_extension','damage_extension','other_extension') NOT NULL,
 source_id BIGINT UNSIGNED NULL,
 is_paid TINYINT(1) NOT NULL DEFAULT 0,
 paid_amount DECIMAL(10,2) NULL,
 reason TEXT NULL,
 status VARCHAR(30) NOT NULL DEFAULT 'planned',
 created_at DATETIME NOT NULL,
 UNIQUE KEY uq_order_day(order_run_id,calendar_date,day_type,source_id),
 INDEX idx_order_day_order_date(order_id,calendar_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seller_data_changes(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 seller_id BIGINT UNSIGNED NOT NULL,
 field_name VARCHAR(120) NOT NULL,
 old_value TEXT NULL,
 new_value TEXT NULL,
 changed_at DATETIME NOT NULL,
 INDEX idx_seller_change(seller_id,changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings(
 setting_key VARCHAR(190) PRIMARY KEY,
 setting_value LONGTEXT NULL,
 updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_events(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 seller_id BIGINT UNSIGNED NULL,
 order_id BIGINT UNSIGNED NULL,
 event_type VARCHAR(100) NOT NULL,
 actor_type VARCHAR(30) NOT NULL,
 actor_id BIGINT UNSIGNED NULL,
 payload_json LONGTEXT NULL,
 created_at DATETIME NOT NULL,
 INDEX idx_event_seller(seller_id,created_at),
 INDEX idx_event_order(order_id,created_at),
 INDEX idx_event_type(event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
