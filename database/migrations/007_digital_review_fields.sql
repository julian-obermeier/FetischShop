ALTER TABLE digital_components ADD COLUMN approved_amount DECIMAL(10,2) NULL AFTER compensation;
ALTER TABLE digital_components ADD COLUMN review_note TEXT NULL AFTER approved_amount;
ALTER TABLE digital_components ADD COLUMN reviewed_at DATETIME NULL AFTER review_note;
ALTER TABLE digital_versions ADD COLUMN content_type VARCHAR(30) NOT NULL DEFAULT 'file' AFTER version_no;
