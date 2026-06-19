-- =============================================
-- KARTLY E-Commerce Database Schema
-- Compatible with MySQL 5.7+ / MariaDB 10.3+
-- =============================================

-- Create Database
CREATE DATABASE IF NOT EXISTS kartly_db;
USE kartly_db;

-- =============================================
-- Users Table (Customers & Admin)
-- =============================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    city VARCHAR(100),
    postal_code VARCHAR(20),
    country VARCHAR(100),
    role ENUM('customer', 'admin') DEFAULT 'customer',
    status ENUM('active', 'inactive', 'banned') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =============================================
-- Categories Table
-- =============================================
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    image VARCHAR(255),
    parent_id INT DEFAULT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- =============================================
-- Products Table
-- =============================================
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    sku VARCHAR(100) UNIQUE,
    description TEXT,
    short_description VARCHAR(500),
    price DECIMAL(10, 2) NOT NULL,
    sale_price DECIMAL(10, 2),
    cost_price DECIMAL(10, 2),
    stock_quantity INT DEFAULT 0,
    low_stock_threshold INT DEFAULT 5,
    weight DECIMAL(10, 2),
    dimensions VARCHAR(100),
    main_image VARCHAR(255),
    gallery_images TEXT,
    is_featured BOOLEAN DEFAULT FALSE,
    is_new BOOLEAN DEFAULT FALSE,
    is_bestseller BOOLEAN DEFAULT FALSE,
    status ENUM('active', 'inactive', 'out_of_stock') DEFAULT 'active',
    meta_title VARCHAR(255),
    meta_description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- =============================================
-- Product Images Table
-- =============================================
CREATE TABLE product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    alt_text VARCHAR(255),
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- =============================================
-- Coupons Table
-- =============================================
CREATE TABLE coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    type ENUM('percentage', 'fixed') NOT NULL,
    value DECIMAL(10, 2) NOT NULL,
    min_order_amount DECIMAL(10, 2) DEFAULT 0,
    max_uses INT DEFAULT NULL,
    used_count INT DEFAULT 0,
    start_date DATE,
    end_date DATE,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =============================================
-- Orders Table
-- =============================================
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    order_number VARCHAR(50) NOT NULL UNIQUE,
    status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded') DEFAULT 'pending',
    subtotal DECIMAL(10, 2) NOT NULL,
    discount DECIMAL(10, 2) DEFAULT 0,
    shipping_cost DECIMAL(10, 2) DEFAULT 0,
    tax DECIMAL(10, 2) DEFAULT 0,
    total DECIMAL(10, 2) NOT NULL,
    coupon_id INT,
    payment_method VARCHAR(50),
    payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
    transaction_id VARCHAR(255),
    shipping_first_name VARCHAR(50),
    shipping_last_name VARCHAR(50),
    shipping_email VARCHAR(100),
    shipping_phone VARCHAR(20),
    shipping_address TEXT,
    shipping_city VARCHAR(100),
    shipping_postal_code VARCHAR(20),
    shipping_country VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE SET NULL
);

-- =============================================
-- Payment Attempts Table
-- =============================================
CREATE TABLE payment_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    gateway VARCHAR(32) NOT NULL,
    gateway_payment_id VARCHAR(255) NULL,
    gateway_transaction_id VARCHAR(255) NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'initiated',
    request_payload LONGTEXT NULL,
    response_payload LONGTEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_payment_attempts_order_id (order_id),
    INDEX idx_payment_attempts_gateway (gateway),
    INDEX idx_payment_attempts_gateway_payment_id (gateway_payment_id),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- =============================================
-- Order Items Table
-- =============================================
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    product_sku VARCHAR(100),
    quantity INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    total DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- =============================================
-- Cart Table
-- =============================================
CREATE TABLE cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) NOT NULL,
    user_id INT,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- =============================================
-- Wishlist Table
-- =============================================
CREATE TABLE wishlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    session_id VARCHAR(255),
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- =============================================
-- Reviews Table
-- =============================================
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT,
    rating TINYINT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    title VARCHAR(255),
    review TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- =============================================
-- Review Images Table
-- =============================================
CREATE TABLE review_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    review_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE CASCADE
);

-- =============================================
-- Deals Table
-- =============================================
CREATE TABLE deals (
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
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =============================================
-- Contact Messages Table
-- =============================================
CREATE TABLE contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    subject VARCHAR(255),
    message TEXT NOT NULL,
    status ENUM('unread', 'read', 'replied') DEFAULT 'unread',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =============================================
-- Newsletter Subscribers
-- =============================================
CREATE TABLE newsletter_subscribers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL UNIQUE,
    status ENUM('active', 'unsubscribed') DEFAULT 'active',
    subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =============================================
-- Settings Table
-- =============================================
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('text', 'number', 'boolean', 'json') DEFAULT 'text',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =============================================
-- Insert Default Admin User
-- Password: admin123 (bcrypt hashed)
-- =============================================
INSERT INTO users (first_name, last_name, email, password, role, status) VALUES
('Admin', 'User', 'admin@kartly.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active');

-- =============================================
-- Insert Sample Categories
-- =============================================
INSERT INTO categories (name, slug, description, image, status) VALUES
('Electronics', 'electronics', 'Latest gadgets and electronic devices', 'assets/images/categories/electronics.jpg', 'active'),
('Fashion', 'fashion', 'Trendy clothing and accessories', 'assets/images/categories/fashion.jpg', 'active'),
('Home & Living', 'home-living', 'Furniture and home decor', 'assets/images/categories/home.jpg', 'active'),
('Sports', 'sports', 'Sports equipment and activewear', 'assets/images/categories/sports.jpg', 'active'),
('Beauty', 'beauty', 'Skincare, makeup, and beauty products', 'assets/images/categories/beauty.jpg', 'active'),
('Books', 'books', 'Books, magazines, and media', 'assets/images/categories/books.jpg', 'active');

-- =============================================
-- Insert Sample Coupon
-- =============================================
INSERT INTO coupons (code, type, value, min_order_amount, start_date, end_date, status) VALUES
('WELCOME10', 'percentage', 10, 50, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 'active'),
('SAVE20', 'fixed', 20, 100, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 6 MONTH), 'active');

-- =============================================
-- Insert Default Settings
-- =============================================
INSERT INTO settings (setting_key, setting_value, setting_type) VALUES
('site_name', 'KARTLY', 'text'),
('site_tagline', 'Your Premium E-Commerce Destination', 'text'),
('site_email', 'support@kartly.com', 'text'),
('site_phone', '+880 1700-000000', 'text'),
('site_address', 'Dhaka, Bangladesh', 'text'),
('currency', 'BDT', 'text'),
('currency_symbol', 'Tk', 'text'),
('tax_rate', '5', 'number'),
('free_shipping_threshold', '5000', 'number'),
('shipping_cost', '120', 'number'),
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
('payment_nagad_merchant_number', '', 'text'),
('enable_reviews', 'true', 'boolean'),
('enable_wishlist', 'true', 'boolean'),
('enable_coupons', 'true', 'boolean'),
('home_deals_title', 'Hot Deals', 'text'),
('home_deals_subtitle', 'Don''t miss out on these amazing offers', 'text'),
('home_deals_cta_label', 'View All Deals', 'text'),
('home_deals_cta_url', 'products.php?filter=sale', 'text');

-- =============================================
-- Insert Default Deals
-- =============================================
INSERT INTO deals (title, subtitle, badge_text, badge_style, timer_text, countdown_end_at, image_path, link_url, overlay_start, overlay_end, image_position, sort_order, status) VALUES
('Up to 70% Off', 'Electronics & Gadgets', 'Limited Time', 'danger', '12:45:30', DATE_ADD(NOW(), INTERVAL 12 HOUR), 'https://images.unsplash.com/photo-1593642632559-0c6d3fc62b89?w=800&q=80', 'products.php?category=electronics', 'rgba(15, 118, 110, 0.84)', 'rgba(11, 91, 85, 0.62)', 'center center', 1, 'active'),
('Fashion Forward', 'Summer Collection 2024', 'New Arrivals', 'primary', '23:59:59', DATE_ADD(NOW(), INTERVAL 24 HOUR), 'https://images.unsplash.com/photo-1483985988355-763728e1935b?w=800&q=80', 'products.php?category=fashion', 'rgba(30, 64, 175, 0.82)', 'rgba(30, 58, 138, 0.62)', 'center top', 2, 'active'),
('Buy 2 Get 1 Free', 'Home & Living Essentials', 'This Weekend', 'success', '48:00:00', DATE_ADD(NOW(), INTERVAL 48 HOUR), 'https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=800&q=80', 'products.php?category=home-living', 'rgba(15, 118, 110, 0.84)', 'rgba(13, 89, 97, 0.62)', 'center center', 3, 'active');

-- =============================================
-- Create Indexes for Better Performance
-- =============================================
CREATE INDEX idx_products_category ON products(category_id);
CREATE INDEX idx_products_status ON products(status);
CREATE INDEX idx_products_featured ON products(is_featured);
CREATE INDEX idx_orders_user ON orders(user_id);
CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_cart_session ON cart(session_id);
CREATE INDEX idx_cart_user ON cart(user_id);
CREATE INDEX idx_reviews_product ON reviews(product_id);
CREATE INDEX idx_reviews_status ON reviews(status);
CREATE INDEX idx_review_images_review ON review_images(review_id);
CREATE INDEX idx_deals_status_sort ON deals(status, sort_order);
