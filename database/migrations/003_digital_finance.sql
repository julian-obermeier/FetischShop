CREATE TABLE IF NOT EXISTS digital_components(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_component_id BIGINT UNSIGNED NOT NULL,
 format_type VARCHAR(40) NOT NULL,
 requirements_json LONGTEXT NOT NULL,
 compensation DECIMAL(10,2) NOT NULL DEFAULT 0,
 status VARCHAR(40) NOT NULL DEFAULT 'open',
 deadline DATETIME NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 INDEX idx_digital_component_order_component(order_component_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS digital_submissions(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 digital_component_id BIGINT UNSIGNED NOT NULL,
 submission_no INT NOT NULL,
 status VARCHAR(30) NOT NULL DEFAULT 'draft',
 finalized_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 UNIQUE KEY uq_digital_submission(digital_component_id,submission_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS digital_versions(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 digital_submission_id BIGINT UNSIGNED NOT NULL,
 version_no INT NOT NULL,
 file_path VARCHAR(500) NULL,
 original_name VARCHAR(255) NULL,
 mime_type VARCHAR(120) NULL,
 file_size BIGINT UNSIGNED NULL,
 sha256 CHAR(64) NULL,
 text_content LONGTEXT NULL,
 technical_metadata_json LONGTEXT NULL,
 created_at DATETIME NOT NULL,
 UNIQUE KEY uq_digital_version(digital_submission_id,version_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS revision_rounds(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 digital_component_id BIGINT UNSIGNED NOT NULL,
 round_no INT NOT NULL,
 deadline DATETIME NOT NULL,
 grace_ends_at DATETIME NOT NULL,
 status VARCHAR(30) NOT NULL DEFAULT 'open',
 created_at DATETIME NOT NULL,
 completed_at DATETIME NULL,
 UNIQUE KEY uq_revision_round(digital_component_id,round_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS revision_items(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 revision_round_id BIGINT UNSIGNED NOT NULL,
 description TEXT NOT NULL,
 text_reference VARCHAR(255) NULL,
 time_from_seconds INT NULL,
 time_to_seconds INT NULL,
 priority VARCHAR(20) NOT NULL DEFAULT 'normal',
 status VARCHAR(30) NOT NULL DEFAULT 'open',
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 INDEX idx_revision_item_round(revision_round_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rights_acceptances(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_id BIGINT UNSIGNED NOT NULL,
 seller_id BIGINT UNSIGNED NOT NULL,
 clause_version VARCHAR(40) NOT NULL,
 accepted_at DATETIME NOT NULL,
 acceptance_ip VARCHAR(64) NULL,
 UNIQUE KEY uq_rights_acceptance(order_id,clause_version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wallets(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 seller_id BIGINT UNSIGNED NOT NULL UNIQUE,
 balance_available DECIMAL(12,2) NOT NULL DEFAULT 0,
 balance_reserved DECIMAL(12,2) NOT NULL DEFAULT 0,
 balance_in_review DECIMAL(12,2) NOT NULL DEFAULT 0,
 updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wallet_entries(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 wallet_id BIGINT UNSIGNED NOT NULL,
 order_id BIGINT UNSIGNED NULL,
 payout_request_id BIGINT UNSIGNED NULL,
 entry_type VARCHAR(50) NOT NULL,
 status ENUM('reserved','in_review','available','paid','cancelled','rejected') NOT NULL,
 amount DECIMAL(12,2) NOT NULL,
 balance_after DECIMAL(12,2) NULL,
 metadata_json LONGTEXT NULL,
 created_at DATETIME NOT NULL,
 INDEX idx_wallet_entry_wallet(wallet_id,created_at),
 INDEX idx_wallet_entry_order(order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payout_methods(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 seller_id BIGINT UNSIGNED NOT NULL,
 method_type ENUM('bank','paypal') NOT NULL,
 account_holder VARCHAR(190) NULL,
 iban VARCHAR(80) NULL,
 bic VARCHAR(30) NULL,
 paypal_identifier VARCHAR(190) NULL,
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 INDEX idx_payout_method_seller(seller_id,is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payout_requests(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 seller_id BIGINT UNSIGNED NOT NULL,
 payout_method_id BIGINT UNSIGNED NOT NULL,
 requested_amount DECIMAL(12,2) NOT NULL,
 fee_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
 net_amount DECIMAL(12,2) NOT NULL,
 method_snapshot LONGTEXT NOT NULL,
 status ENUM('requested','in_review','approved','paid','withdrawn','rejected') NOT NULL DEFAULT 'requested',
 requested_at DATETIME NOT NULL,
 processed_at DATETIME NULL,
 INDEX idx_payout_seller_status(seller_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 seller_id BIGINT UNSIGNED NULL,
 type VARCHAR(80) NOT NULL,
 title VARCHAR(190) NOT NULL,
 message TEXT NOT NULL,
 url VARCHAR(500) NULL,
 read_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 INDEX idx_notification_seller(seller_id,read_at,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS calendar_events(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 seller_id BIGINT UNSIGNED NULL,
 order_id BIGINT UNSIGNED NULL,
 event_type VARCHAR(80) NOT NULL,
 title VARCHAR(190) NOT NULL,
 starts_at DATETIME NOT NULL,
 ends_at DATETIME NULL,
 status VARCHAR(40) NOT NULL DEFAULT 'scheduled',
 source_type VARCHAR(60) NULL,
 source_id BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL,
 INDEX idx_calendar_starts(starts_at),
 INDEX idx_calendar_order(order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS platform_outages(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 starts_at DATETIME NOT NULL,
 ends_at DATETIME NOT NULL,
 reason TEXT NULL,
 created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS final_reviews(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_id BIGINT UNSIGNED NOT NULL UNIQUE,
 decision ENUM('accepted','contested','partially_accepted','rejected') NOT NULL,
 approved_amount DECIMAL(12,2) NULL,
 internal_note TEXT NULL,
 seller_message TEXT NULL,
 decided_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
