-- Add homepage deals module managed from admin panel
CREATE TABLE IF NOT EXISTS deals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    subtitle VARCHAR(255),
    badge_text VARCHAR(60),
    badge_style ENUM('primary', 'success', 'danger', 'warning') DEFAULT 'primary',
    timer_text VARCHAR(32),
    countdown_end_at DATETIME NULL,
    image_path VARCHAR(255),
    link_url VARCHAR(255) NOT NULL DEFAULT 'products.php?filter=sale',
    overlay_start VARCHAR(40) DEFAULT 'rgba(15, 118, 110, 0.84)',
    overlay_end VARCHAR(40) DEFAULT 'rgba(11, 91, 85, 0.62)',
    image_position VARCHAR(100) DEFAULT 'center center',
    sort_order INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_deals_status_sort (status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @deals_countdown_col_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'deals'
      AND column_name = 'countdown_end_at'
);
SET @deals_countdown_sql := IF(
    @deals_countdown_col_exists = 0,
    'ALTER TABLE deals ADD COLUMN countdown_end_at DATETIME NULL AFTER timer_text',
    'SELECT 1'
);
PREPARE deals_countdown_stmt FROM @deals_countdown_sql;
EXECUTE deals_countdown_stmt;
DEALLOCATE PREPARE deals_countdown_stmt;

INSERT INTO settings (setting_key, setting_value, setting_type)
VALUES ('home_deals_title', 'Hot Deals', 'text')
ON DUPLICATE KEY UPDATE setting_value = setting_value;

INSERT INTO settings (setting_key, setting_value, setting_type)
VALUES ('home_deals_subtitle', 'Don''t miss out on these amazing offers', 'text')
ON DUPLICATE KEY UPDATE setting_value = setting_value;

INSERT INTO settings (setting_key, setting_value, setting_type)
VALUES ('home_deals_cta_label', 'View All Deals', 'text')
ON DUPLICATE KEY UPDATE setting_value = setting_value;

INSERT INTO settings (setting_key, setting_value, setting_type)
VALUES ('home_deals_cta_url', 'products.php?filter=sale', 'text')
ON DUPLICATE KEY UPDATE setting_value = setting_value;

INSERT INTO deals (title, subtitle, badge_text, badge_style, timer_text, countdown_end_at, image_path, link_url, overlay_start, overlay_end, image_position, sort_order, status)
SELECT 'Up to 70% Off', 'Electronics & Gadgets', 'Limited Time', 'danger', '12:45:30', DATE_ADD(NOW(), INTERVAL 12 HOUR),
       'https://images.unsplash.com/photo-1593642632559-0c6d3fc62b89?w=800&q=80',
       'products.php?category=electronics',
       'rgba(15, 118, 110, 0.84)', 'rgba(11, 91, 85, 0.62)', 'center center', 1, 'active'
WHERE NOT EXISTS (SELECT 1 FROM deals LIMIT 1);

INSERT INTO deals (title, subtitle, badge_text, badge_style, timer_text, countdown_end_at, image_path, link_url, overlay_start, overlay_end, image_position, sort_order, status)
SELECT 'Fashion Forward', 'Summer Collection 2024', 'New Arrivals', 'primary', '23:59:59', DATE_ADD(NOW(), INTERVAL 24 HOUR),
       'https://images.unsplash.com/photo-1483985988355-763728e1935b?w=800&q=80',
       'products.php?category=fashion',
       'rgba(30, 64, 175, 0.82)', 'rgba(30, 58, 138, 0.62)', 'center top', 2, 'active'
WHERE (SELECT COUNT(*) FROM deals) = 1;

INSERT INTO deals (title, subtitle, badge_text, badge_style, timer_text, countdown_end_at, image_path, link_url, overlay_start, overlay_end, image_position, sort_order, status)
SELECT 'Buy 2 Get 1 Free', 'Home & Living Essentials', 'This Weekend', 'success', '48:00:00', DATE_ADD(NOW(), INTERVAL 48 HOUR),
       'https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=800&q=80',
       'products.php?category=home-living',
       'rgba(15, 118, 110, 0.84)', 'rgba(13, 89, 97, 0.62)', 'center center', 3, 'active'
WHERE (SELECT COUNT(*) FROM deals) = 2;
