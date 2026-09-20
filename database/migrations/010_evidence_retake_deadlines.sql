ALTER TABLE evidences ADD COLUMN retake_deadline DATETIME NULL AFTER rejection_note;
ALTER TABLE evidences ADD COLUMN retake_grace_ends_at DATETIME NULL AFTER retake_deadline;
ALTER TABLE evidences ADD COLUMN resolved_by_evidence_id BIGINT UNSIGNED NULL AFTER retake_grace_ends_at;
CREATE INDEX idx_evidence_retake_due ON evidences(review_status,retake_grace_ends_at);
