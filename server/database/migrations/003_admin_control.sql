INSERT INTO settings (setting_key, setting_value) VALUES
    ('update_channel', 'stable'),
    ('github_repository', ''),
    ('update_check_interval', '86400'),
    ('wizard_completed', '0')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
