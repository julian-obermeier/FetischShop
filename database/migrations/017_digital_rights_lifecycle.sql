ALTER TABLE digital_components ADD COLUMN rights_status VARCHAR(30) NOT NULL DEFAULT 'pending' AFTER status;
CREATE INDEX idx_digital_rights_status ON digital_components(rights_status);

CREATE TABLE IF NOT EXISTS digital_rights_events(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_id BIGINT UNSIGNED NOT NULL,
 digital_component_id BIGINT UNSIGNED NOT NULL,
 event_type VARCHAR(50) NOT NULL,
 clause_version VARCHAR(40) NULL,
 actor_type VARCHAR(30) NOT NULL,
 actor_id BIGINT UNSIGNED NULL,
 note TEXT NULL,
 created_at DATETIME NOT NULL,
 INDEX idx_digital_rights_order(order_id,created_at),
 INDEX idx_digital_rights_component(digital_component_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE digital_components SET rights_status=CASE
 WHEN status IN('accepted','partially_accepted') THEN 'granted'
 WHEN status='rejected' THEN 'not_granted'
 ELSE 'consented'
END
WHERE rights_status='pending';
