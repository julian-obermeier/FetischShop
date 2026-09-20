ALTER TABLE evidence_windows ADD COLUMN camera_required TINYINT(1) NOT NULL DEFAULT 0 AFTER required_count;
ALTER TABLE precheck_requirements ADD COLUMN camera_required TINYINT(1) NOT NULL DEFAULT 0 AFTER required_count;
CREATE INDEX idx_evidence_window_camera ON evidence_windows(camera_required,status,starts_at);
CREATE INDEX idx_precheck_camera ON precheck_requirements(camera_required,order_id,order_run_id);
