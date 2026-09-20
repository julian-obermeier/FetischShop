ALTER TABLE tasks ADD COLUMN offer_task_id BIGINT UNSIGNED NULL AFTER task_template_id;
CREATE UNIQUE INDEX uq_task_offer_source ON tasks(order_id,offer_task_id);
