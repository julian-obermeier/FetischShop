ALTER TABLE evidences ADD COLUMN spontaneous_request_id BIGINT UNSIGNED NULL AFTER evidence_window_id;
ALTER TABLE evidences ADD COLUMN task_execution_id BIGINT UNSIGNED NULL AFTER spontaneous_request_id;
ALTER TABLE evidences ADD COLUMN damage_evidence_request_id BIGINT UNSIGNED NULL AFTER task_execution_id;
CREATE INDEX idx_evidence_spontaneous ON evidences(spontaneous_request_id);
CREATE INDEX idx_evidence_task_execution ON evidences(task_execution_id);
CREATE INDEX idx_evidence_damage_request ON evidences(damage_evidence_request_id);

ALTER TABLE notifications ADD COLUMN dedupe_key VARCHAR(190) NULL AFTER seller_id;
CREATE UNIQUE INDEX uq_notification_dedupe ON notifications(dedupe_key);

ALTER TABLE offers ADD COLUMN private_offer_status ENUM('pending','accepted','declined','expired') NULL AFTER acceptance_deadline;
ALTER TABLE offers ADD COLUMN declined_reason TEXT NULL AFTER private_offer_status;

CREATE TABLE IF NOT EXISTS scheduler_locks(
 lock_key VARCHAR(190) PRIMARY KEY,
 locked_until DATETIME NOT NULL,
 updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
