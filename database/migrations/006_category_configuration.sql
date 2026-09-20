ALTER TABLE categories ADD COLUMN config_json LONGTEXT NULL AFTER icon;

UPDATE categories SET config_json='{"precheck":{"required":["selected_item","bare_feet_multiple_angles"]},"evidence":{"windows":["morning","midday","evening"]},"shipping":{"enabled":true},"photo_requirements":{"live_camera":true}}' WHERE slug='getragene-socken';
UPDATE categories SET config_json='{"precheck":{"required":["shoe_exterior_multiple_angles","shoe_interior","soles","bare_feet_multiple_angles"]},"evidence":{"windows":["morning","midday","evening"]},"shipping":{"enabled":true},"photo_requirements":{"live_camera":true}}' WHERE slug='schuhe';
UPDATE categories SET config_json='{"precheck":{"required":["item_front","item_back","details","worn_before_start"]},"shipping":{"enabled":true},"photo_requirements":{"live_camera":true}}' WHERE slug IN('slips','tops','bhs');
UPDATE categories SET config_json='{"precheck":{"required":[]},"shipping":{"enabled":true,"special_packaging":["leakproof_primary_container","sealed_secondary_packaging"]},"end_workflow":{"requires_filled_container_photo":true}}' WHERE slug='spucke';
UPDATE categories SET config_json='{"digital":{"formats":["text","audio","video"],"versioning":true,"revisions":"unlimited","technical_validation":true},"shipping":{"enabled":false}}' WHERE slug IN('digitale-inhalte','wichsanleitung');

INSERT IGNORE INTO category_fields(category_id,field_key,label,field_type,options_json,is_required,is_active,sort_order,created_at,updated_at)
SELECT id,'size','Größe','text',NULL,0,1,10,NOW(),NOW() FROM categories WHERE is_digital=0;
INSERT IGNORE INTO category_fields(category_id,field_key,label,field_type,options_json,is_required,is_active,sort_order,created_at,updated_at)
SELECT id,'color','Farbe','text',NULL,0,1,20,NOW(),NOW() FROM categories WHERE is_digital=0;
INSERT IGNORE INTO category_fields(category_id,field_key,label,field_type,options_json,is_required,is_active,sort_order,created_at,updated_at)
SELECT id,'brand','Marke','text',NULL,0,1,30,NOW(),NOW() FROM categories WHERE is_digital=0;
INSERT IGNORE INTO category_fields(category_id,field_key,label,field_type,options_json,is_required,is_active,sort_order,created_at,updated_at)
SELECT id,'material','Material','text',NULL,0,1,40,NOW(),NOW() FROM categories WHERE is_digital=0;
