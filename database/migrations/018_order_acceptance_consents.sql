CREATE TABLE IF NOT EXISTS order_acceptance_consents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  seller_id BIGINT UNSIGNED NOT NULL,
  terms_version VARCHAR(40) NOT NULL,
  adult_confirmed TINYINT(1) NOT NULL DEFAULT 0,
  own_goods_confirmed TINYINT(1) NOT NULL DEFAULT 0,
  no_third_parties_confirmed TINYINT(1) NOT NULL DEFAULT 0,
  summary_confirmed TINYINT(1) NOT NULL DEFAULT 0,
  rights_confirmed TINYINT(1) NOT NULL DEFAULT 0,
  ip_address VARCHAR(45) NULL,
  user_agent TEXT NULL,
  accepted_at DATETIME NOT NULL,
  UNIQUE KEY uq_order_acceptance_consents_order (order_id),
  INDEX idx_order_acceptance_consents_seller (seller_id, accepted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
