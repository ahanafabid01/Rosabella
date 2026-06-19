-- =============================================
-- Add Payment Gateway Settings
-- =============================================
INSERT IGNORE INTO settings (setting_key, setting_value, setting_type) VALUES
('payment_cod_enabled', 'true', 'boolean'),
('payment_sslcommerz_enabled', 'true', 'boolean'),
('payment_sslcommerz_sandbox', 'true', 'boolean'),
('payment_sslcommerz_store_id', '', 'text'),
('payment_sslcommerz_store_password', '', 'text'),
('payment_bkash_enabled', 'false', 'boolean'),
('payment_bkash_sandbox', 'true', 'boolean'),
('payment_bkash_app_key', '', 'text'),
('payment_bkash_app_secret', '', 'text'),
('payment_bkash_username', '', 'text'),
('payment_bkash_password', '', 'text'),
('payment_nagad_enabled', 'false', 'boolean'),
('payment_nagad_sandbox', 'true', 'boolean'),
('payment_nagad_merchant_id', '', 'text'),
('payment_nagad_merchant_number', '', 'text');
