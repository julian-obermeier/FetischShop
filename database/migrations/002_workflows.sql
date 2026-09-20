CREATE TABLE IF NOT EXISTS evidence_windows(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_day_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(120) NOT NULL,
 starts_at DATETIME NOT NULL,
 ends_at DATETIME NOT NULL,
 grace_ends_at DATETIME NOT NULL,
 required_count INT NOT NULL DEFAULT 1,
 config_json LONGTEXT NULL,
 status VARCHAR(30) NOT NULL DEFAULT 'open',
 created_at DATETIME NOT NULL,
 INDEX idx_window_due(status,ends_at),
 INDEX idx_window_day(order_day_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evidences(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_id BIGINT UNSIGNED NOT NULL,
 order_run_id BIGINT UNSIGNED NULL,
 evidence_window_id BIGINT UNSIGNED NULL,
 evidence_type VARCHAR(50) NOT NULL,
 file_path VARCHAR(500) NOT NULL,
 original_name VARCHAR(255) NOT NULL,
 mime_type VARCHAR(120) NOT NULL,
 file_size BIGINT UNSIGNED NOT NULL,
 sha256 CHAR(64) NOT NULL,
 captured_at DATETIME NOT NULL,
 metadata_json LONGTEXT NULL,
 review_status VARCHAR(30) NOT NULL DEFAULT 'pending',
 rejection_reason VARCHAR(120) NULL,
 rejection_note TEXT NULL,
 created_at DATETIME NOT NULL,
 INDEX idx_evidence_order(order_id,created_at),
 INDEX idx_evidence_window(evidence_window_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS spontaneous_requests(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_id BIGINT UNSIGNED NOT NULL,
 requested_count INT NOT NULL,
 motif VARCHAR(190) NOT NULL,
 description TEXT NULL,
 deadline DATETIME NOT NULL,
 grace_ends_at DATETIME NOT NULL,
 status VARCHAR(30) NOT NULL DEFAULT 'requested',
 seen_at DATETIME NULL,
 confirmed_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 INDEX idx_spontaneous_due(status,deadline)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS task_templates(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(190) NOT NULL,
 description TEXT NULL,
 fields_json LONGTEXT NOT NULL,
 photos_json LONGTEXT NULL,
 deadline_minutes INT NULL,
 compensation DECIMAL(10,2) NOT NULL DEFAULT 0,
 violation_json LONGTEXT NULL,
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tasks(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_id BIGINT UNSIGNED NOT NULL,
 task_template_id BIGINT UNSIGNED NULL,
 title VARCHAR(190) NOT NULL,
 description TEXT NULL,
 schedule_type VARCHAR(30) NOT NULL,
 config_json LONGTEXT NOT NULL,
 created_at DATETIME NOT NULL,
 INDEX idx_task_order(order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS task_executions(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 task_id BIGINT UNSIGNED NOT NULL,
 order_day_id BIGINT UNSIGNED NULL,
 due_at DATETIME NOT NULL,
 grace_ends_at DATETIME NOT NULL,
 response_json LONGTEXT NULL,
 submitted_at DATETIME NULL,
 review_status VARCHAR(30) NOT NULL DEFAULT 'open',
 created_at DATETIME NOT NULL,
 UNIQUE KEY uq_task_execution(task_id,due_at),
 INDEX idx_task_exec_due(review_status,due_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS violations(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_id BIGINT UNSIGNED NOT NULL,
 order_run_id BIGINT UNSIGNED NULL,
 violation_type VARCHAR(80) NOT NULL,
 source_type VARCHAR(60) NULL,
 source_id BIGINT UNSIGNED NULL,
 description TEXT NOT NULL,
 status ENUM('open','reviewed','confirmed','discarded') NOT NULL DEFAULT 'open',
 provisional_extension TINYINT(1) NOT NULL DEFAULT 1,
 confirmed_at DATETIME NULL,
 decided_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 UNIQUE KEY uq_violation_source(order_id,violation_type,source_type,source_id),
 INDEX idx_violation_status(status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS extension_days(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_id BIGINT UNSIGNED NOT NULL,
 violation_id BIGINT UNSIGNED NULL,
 source_type VARCHAR(50) NOT NULL,
 source_id BIGINT UNSIGNED NULL,
 reason TEXT NOT NULL,
 is_provisional TINYINT(1) NOT NULL DEFAULT 0,
 is_paid TINYINT(1) NOT NULL DEFAULT 0,
 amount DECIMAL(10,2) NULL,
 created_at DATETIME NOT NULL,
 INDEX idx_extension_order(order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS manual_extra_days(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_id BIGINT UNSIGNED NOT NULL,
 reason TEXT NOT NULL,
 is_paid TINYINT(1) NOT NULL DEFAULT 0,
 amount DECIMAL(10,2) NULL,
 created_at DATETIME NOT NULL,
 INDEX idx_manual_extra_order(order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS damage_cases(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_id BIGINT UNSIGNED NOT NULL,
 order_run_id BIGINT UNSIGNED NOT NULL,
 reason TEXT NOT NULL,
 initial_evidence_id BIGINT UNSIGNED NOT NULL,
 status VARCHAR(40) NOT NULL DEFAULT 'reported',
 decision_note TEXT NULL,
 created_at DATETIME NOT NULL,
 decided_at DATETIME NULL,
 INDEX idx_damage_order(order_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS damage_evidence_requests(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 damage_case_id BIGINT UNSIGNED NOT NULL,
 field_type VARCHAR(40) NOT NULL,
 instructions TEXT NOT NULL,
 deadline DATETIME NOT NULL,
 grace_ends_at DATETIME NOT NULL,
 response_json LONGTEXT NULL,
 submitted_at DATETIME NULL,
 status VARCHAR(30) NOT NULL DEFAULT 'requested',
 created_at DATETIME NOT NULL,
 INDEX idx_damage_req_due(status,deadline)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shipping_workflows(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_id BIGINT UNSIGNED NOT NULL,
 status VARCHAR(30) NOT NULL DEFAULT 'locked',
 recipient_address_id BIGINT UNSIGNED NULL,
 started_at DATETIME NULL,
 completed_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 UNIQUE KEY uq_shipping_order(order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shipping_steps(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 shipping_workflow_id BIGINT UNSIGNED NOT NULL,
 step_no INT NOT NULL,
 title VARCHAR(190) NOT NULL,
 instructions TEXT NULL,
 is_required TINYINT(1) NOT NULL DEFAULT 1,
 config_json LONGTEXT NULL,
 deadline DATETIME NULL,
 response_json LONGTEXT NULL,
 completed_at DATETIME NULL,
 status VARCHAR(30) NOT NULL DEFAULT 'pending',
 UNIQUE KEY uq_shipping_step(shipping_workflow_id,step_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recipient_addresses(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 label VARCHAR(190) NOT NULL,
 recipient_name VARCHAR(190) NOT NULL,
 street VARCHAR(190) NOT NULL,
 postal_code VARCHAR(20) NOT NULL,
 city VARCHAR(120) NOT NULL,
 country_code CHAR(2) NOT NULL DEFAULT 'DE',
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shipments(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_id BIGINT UNSIGNED NOT NULL,
 shipping_workflow_id BIGINT UNSIGNED NOT NULL,
 tracking_number VARCHAR(190) NULL,
 receipt_evidence_id BIGINT UNSIGNED NULL,
 shipping_cost DECIMAL(10,2) NULL,
 reimbursement_amount DECIMAL(10,2) NULL,
 status VARCHAR(40) NOT NULL DEFAULT 'preparing',
 shipped_at DATETIME NULL,
 received_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 INDEX idx_shipment_tracking(tracking_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chats(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_id BIGINT UNSIGNED NOT NULL UNIQUE,
 is_readonly TINYINT(1) NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_messages(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 chat_id BIGINT UNSIGNED NOT NULL,
 sender_type ENUM('admin','seller','system') NOT NULL,
 sender_id BIGINT UNSIGNED NULL,
 message TEXT NOT NULL,
 created_at DATETIME NOT NULL,
 INDEX idx_chat_message(chat_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
