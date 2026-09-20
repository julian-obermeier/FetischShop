ALTER TABLE evidences ADD COLUMN shipping_step_id BIGINT UNSIGNED NULL AFTER damage_evidence_request_id;
CREATE INDEX idx_evidence_shipping_step ON evidences(shipping_step_id);

ALTER TABLE shipments ADD UNIQUE KEY uq_shipment_order(order_id);

INSERT INTO settings(setting_key,setting_value,updated_at) VALUES
('payout_minimum','10.00',NOW()),
('payout_bank_enabled','1',NOW()),
('payout_paypal_enabled','1',NOW()),
('payout_bank_fee_type','none',NOW()),
('payout_bank_fee_value','0',NOW()),
('payout_paypal_fee_type','none',NOW()),
('payout_paypal_fee_value','0',NOW()),
('payout_days','Montag,Donnerstag',NOW())
ON DUPLICATE KEY UPDATE setting_key=VALUES(setting_key);
