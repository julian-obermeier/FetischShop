CREATE TABLE IF NOT EXISTS support_tickets(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 ticket_number VARCHAR(24) NULL UNIQUE,
 seller_id BIGINT UNSIGNED NULL,
 name VARCHAR(160) NOT NULL,
 email VARCHAR(190) NOT NULL,
 subject VARCHAR(190) NOT NULL,
 status ENUM('open','waiting_admin','waiting_seller','closed') NOT NULL DEFAULT 'open',
 priority ENUM('normal','high') NOT NULL DEFAULT 'normal',
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 closed_at DATETIME NULL,
 INDEX idx_support_ticket_seller(seller_id,status),
 INDEX idx_support_ticket_status(status,updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_messages(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 ticket_id BIGINT UNSIGNED NOT NULL,
 sender_type ENUM('seller','admin','guest','system') NOT NULL,
 sender_id BIGINT UNSIGNED NULL,
 message TEXT NOT NULL,
 created_at DATETIME NOT NULL,
 INDEX idx_support_messages_ticket(ticket_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
