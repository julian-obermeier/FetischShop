INSERT INTO settings(setting_key,setting_value,updated_at)
SELECT 'cron_token',LOWER(SHA2(CONCAT(UUID(),UUID(),RAND(),NOW()),256)),NOW()
WHERE NOT EXISTS(SELECT 1 FROM settings WHERE setting_key='cron_token');
