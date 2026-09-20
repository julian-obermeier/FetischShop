ALTER TABLE shipping_steps ADD COLUMN created_at DATETIME NULL AFTER status;

UPDATE shipping_steps ss
JOIN shipping_workflows sw ON sw.id=ss.shipping_workflow_id
SET ss.created_at=COALESCE(sw.started_at,sw.created_at,NOW())
WHERE ss.created_at IS NULL;

ALTER TABLE shipping_steps MODIFY created_at DATETIME NOT NULL;
CREATE INDEX idx_shipping_step_created ON shipping_steps(created_at);
