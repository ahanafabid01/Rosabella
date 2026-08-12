-- Add clothing_brand theme option to homepage_theme setting
-- This migration adds support for the new "Clothing Brand" theme
-- Batch: 2
-- Date: 2026-08-12

-- Note: This is a documentation migration. The theme option is automatically
-- available through the application code once this migration is run.
-- Admins can select 'clothing_brand' from the theme selector in admin settings.

INSERT INTO migrations (migration, batch) VALUES ('2026_08_12_000000_add_clothing_brand_theme.sql', 2);
