CREATE TABLE IF NOT EXISTS cart_items(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 seller_id BIGINT UNSIGNED NOT NULL,
 offer_id BIGINT UNSIGNED NOT NULL,
 offer_version_id BIGINT UNSIGNED NOT NULL,
 option_ids_json LONGTEXT NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 UNIQUE KEY uq_cart_seller_offer(seller_id,offer_id),
 INDEX idx_cart_seller(seller_id,updated_at),
 INDEX idx_cart_offer(offer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_offer_items(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_id BIGINT UNSIGNED NOT NULL,
 offer_id BIGINT UNSIGNED NOT NULL,
 offer_version_id BIGINT UNSIGNED NOT NULL,
 title VARCHAR(190) NOT NULL,
 base_compensation DECIMAL(12,2) NOT NULL DEFAULT 0,
 sort_order INT NOT NULL DEFAULT 0,
 config_snapshot LONGTEXT NOT NULL,
 created_at DATETIME NOT NULL,
 INDEX idx_order_offer_order(order_id,sort_order,id),
 INDEX idx_order_offer_offer(offer_id,offer_version_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE order_components ADD COLUMN order_offer_item_id BIGINT UNSIGNED NULL AFTER order_id;
CREATE INDEX idx_order_component_offer_item ON order_components(order_offer_item_id);

ALTER TABLE order_options ADD COLUMN order_offer_item_id BIGINT UNSIGNED NULL AFTER order_id;
CREATE INDEX idx_order_option_offer_item ON order_options(order_offer_item_id);

ALTER TABLE tasks ADD COLUMN order_offer_item_id BIGINT UNSIGNED NULL AFTER order_id;
CREATE INDEX idx_task_offer_item ON tasks(order_offer_item_id);
