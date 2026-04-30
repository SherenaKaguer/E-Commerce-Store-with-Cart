-- ============================================
-- COMPLETE E-COMMERCE DATABASE SCHEMA
-- Includes: Session Cart, Order Lifecycle, Product Variants, Inventory Tracking, Aggregate Queries
-- ============================================

-- Create database
CREATE DATABASE IF NOT EXISTS ecommerce_db;
USE ecommerce_db;

-- ============================================
-- 1. USERS TABLE (Authentication & Profiles)
-- ============================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    full_name VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    role ENUM('user', 'admin') DEFAULT 'user',
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================
-- 2. CATEGORIES TABLE (Product Organization)
-- ============================================
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    parent_id INT NULL,
    image_url VARCHAR(500),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- ============================================
-- 3. PRODUCTS TABLE (Core Product Data)
-- ============================================
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(200) UNIQUE NOT NULL,
    description TEXT,
    short_description VARCHAR(500),
    price DECIMAL(10, 2) NOT NULL,
    compare_price DECIMAL(10, 2) NULL,
    category_id INT,
    stock_quantity INT DEFAULT 0,
    sku VARCHAR(50) UNIQUE,
    image_url VARCHAR(500),
    gallery_images TEXT,
    is_featured BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- ============================================
-- 4. PRODUCT VARIANTS TABLE (Sizes, Colors, etc.)
-- ============================================
CREATE TABLE IF NOT EXISTS product_variants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    variant_name VARCHAR(100) NOT NULL,
    variant_value VARCHAR(100) NOT NULL,
    additional_price DECIMAL(10, 2) DEFAULT 0.00,
    stock_quantity INT DEFAULT 0,
    sku VARCHAR(50) UNIQUE,
    image_url VARCHAR(500),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- ============================================
-- 5. ORDERS TABLE (Order Lifecycle Tracking)
-- ============================================
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    tracking_number VARCHAR(100) NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    tax_amount DECIMAL(10, 2) DEFAULT 0,
    shipping_amount DECIMAL(10, 2) DEFAULT 0,
    discount_amount DECIMAL(10, 2) DEFAULT 0,
    status ENUM('pending', 'processing', 'paid', 'shipped', 'delivered', 'completed', 'cancelled', 'refunded') DEFAULT 'pending',
    payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
    payment_method VARCHAR(50),
    shipping_address TEXT,
    billing_address TEXT,
    notes TEXT,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================
-- 6. ORDER ITEMS TABLE (Line Items)
-- ============================================
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    variant_id INT NULL,
    quantity INT NOT NULL,
    price_at_time DECIMAL(10, 2) NOT NULL,
    total_price DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL
);

-- ============================================
-- 7. REVIEWS TABLE (Customer Feedback)
-- ============================================
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    title VARCHAR(200),
    comment TEXT,
    is_approved BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_review (product_id, user_id)
);

-- ============================================
-- 8. CART ITEMS TABLE (Session-Based Cart)
-- ============================================
CREATE TABLE IF NOT EXISTS cart_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) NOT NULL,
    user_id INT NULL,
    product_id INT NOT NULL,
    variant_id INT NULL,
    quantity INT NOT NULL DEFAULT 1,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_session (session_id),
    INDEX idx_user (user_id)
);

-- ============================================
-- 9. WISHLIST TABLE (Save for Later)
-- ============================================
CREATE TABLE IF NOT EXISTS wishlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_wishlist (user_id, product_id)
);

-- ============================================
-- 10. COUPONS TABLE (Discounts & Promotions)
-- ============================================
CREATE TABLE IF NOT EXISTS coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    discount_type ENUM('percentage', 'fixed') DEFAULT 'percentage',
    discount_value DECIMAL(10, 2) NOT NULL,
    min_order_amount DECIMAL(10, 2) DEFAULT 0,
    max_discount DECIMAL(10, 2) NULL,
    usage_limit INT DEFAULT 1,
    used_count INT DEFAULT 0,
    start_date DATE,
    end_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- SAMPLE DATA (For Testing)
-- ============================================

-- Insert categories
INSERT IGNORE INTO categories (name, slug, description) VALUES
('Electronics', 'electronics', 'Gadgets, devices, and electronic equipment'),
('Clothing', 'clothing', 'Fashion apparel and accessories'),
('Books', 'books', 'Books, magazines, and publications'),
('Home & Living', 'home-living', 'Home decor, furniture, and kitchenware');

-- Insert users (password = 'password123' for all)
-- Using REPLACE to ensure existing accounts are updated if they already exist
REPLACE INTO users (id, username, password, email, full_name, role) VALUES
(100, 'admin', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'admin@shop.com', 'Administrator', 'admin'),
(101, 'john_doe', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'john@example.com', 'John Doe', 'user'),
(102, 'jane_smith', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'jane@example.com', 'Jane Smith', 'user');

-- Insert products (Representative items and images)
INSERT IGNORE INTO products (name, slug, description, short_description, price, compare_price, category_id, stock_quantity, sku, image_url, is_featured) VALUES
('Wireless Headphones', 'wireless-headphones', 'Premium noise-cancelling wireless headphones with 30-hour battery life.', 'Premium noise-cancelling wireless headphones', 99.99, 149.99, 1, 50, 'WH-001', 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=800&q=80', TRUE),
('Smart Watch', 'smart-watch', 'Fitness tracker with heart rate monitor and GPS.', 'Fitness tracker with heart rate monitor', 199.99, 249.99, 1, 0, 'SW-001', 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=800&q=80', TRUE),
('Cotton T-Shirt', 'cotton-tshirt', 'Comfortable 100% cotton t-shirt.', 'Comfortable 100% cotton t-shirt', 24.99, 39.99, 2, 5, 'CT-001', 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?auto=format&fit=crop&w=800&q=80', FALSE),
('Programming Book', 'programming-book', 'Learn PHP and MySQL from scratch.', 'Learn PHP and MySQL from scratch', 45.99, 59.99, 3, 25, 'PB-001', 'https://images.unsplash.com/photo-1589998059171-988d887df646?auto=format&fit=crop&w=800&q=80', FALSE),
('Coffee Mug', 'coffee-mug', 'Ceramic coffee mug with lid.', 'Ceramic coffee mug with lid', 12.99, 19.99, 4, 0, 'CM-001', 'https://images.unsplash.com/photo-1517256011273-bc5b9181f726?auto=format&fit=crop&w=800&q=80', FALSE),
('Front Load Washing Machine', 'washing-machine', 'Energy-efficient front load washing machine with multiple wash cycles.', 'Energy-efficient front load washing machine', 599.99, 749.99, 4, 12, 'WM-001', 'https://images.unsplash.com/photo-1626806819282-2c1dc61a0e05?auto=format&fit=crop&w=800&q=80', TRUE),
('Modern Laptop', 'laptop', 'High-performance laptop for work and play.', 'High-performance laptop', 1299.99, 1499.99, 1, 15, 'LP-001', 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?auto=format&fit=crop&w=800&q=80', TRUE),
('Sports Running Shoes', 'running-shoes', 'Lightweight running shoes with superior cushioning.', 'Lightweight running shoes', 89.99, 119.99, 2, 40, 'RS-001', 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=800&q=80', FALSE);

-- Insert product variants
INSERT IGNORE INTO product_variants (product_id, variant_name, variant_value, additional_price, stock_quantity, sku) VALUES
(3, 'Size', 'Small', 0.00, 30, 'CT-001-S'),
(3, 'Size', 'Medium', 0.00, 40, 'CT-001-M'),
(3, 'Size', 'Large', 0.00, 30, 'CT-001-L'),
(3, 'Color', 'Red', 0.00, 35, 'CT-001-R'),
(3, 'Color', 'Blue', 0.00, 35, 'CT-001-B'),
(3, 'Color', 'Black', 0.00, 30, 'CT-001-BK');

-- Insert sample reviews
INSERT IGNORE INTO reviews (product_id, user_id, rating, title, comment, is_approved) VALUES
(1, 2, 5, 'Amazing sound quality!', 'These headphones are incredible. The noise cancellation works perfectly.', TRUE),
(1, 3, 4, 'Good but could be better', 'Sound quality is excellent but the fit is a bit tight.', TRUE),
(3, 2, 5, 'Very comfortable', 'Best t-shirt I have ever bought. Will definitely buy again.', TRUE);

-- Insert sample coupon
INSERT IGNORE INTO coupons (code, description, discount_type, discount_value, min_order_amount, usage_limit, end_date) VALUES
('WELCOME10', '10% off your first order', 'percentage', 10.00, 50.00, 100, DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY)),
('SAVE20', 'Save $20 on orders over $100', 'fixed', 20.00, 100.00, 50, DATE_ADD(CURRENT_DATE, INTERVAL 60 DAY));

-- ============================================
-- INDEXES (For Query Performance)
-- ============================================

CREATE INDEX idx_products_category ON products(category_id);
CREATE INDEX idx_products_price ON products(price);
CREATE INDEX idx_products_featured ON products(is_featured);
CREATE INDEX idx_orders_user ON orders(user_id);
CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_orders_date ON orders(order_date);
CREATE INDEX idx_reviews_product ON reviews(product_id);
CREATE INDEX idx_cart_session ON cart_items(session_id);
CREATE INDEX idx_wishlist_user ON wishlist(user_id);

-- ============================================
-- AGGREGATE QUERY: Top Selling Products View
-- ============================================

CREATE OR REPLACE VIEW top_selling_products AS
SELECT 
    p.id,
    p.name,
    p.slug,
    p.price,
    p.image_url,
    COALESCE(SUM(oi.quantity), 0) as total_sold,
    COALESCE(COUNT(DISTINCT oi.order_id), 0) as times_ordered
FROM products p
LEFT JOIN order_items oi ON p.id = oi.product_id
LEFT JOIN orders o ON oi.order_id = o.id AND o.status NOT IN ('cancelled', 'refunded')
GROUP BY p.id
ORDER BY total_sold DESC;

-- ============================================
-- AGGREGATE QUERY: Product with Average Rating View
-- ============================================

CREATE OR REPLACE VIEW product_ratings AS
SELECT 
    p.id,
    p.name,
    p.slug,
    COALESCE(AVG(r.rating), 0) as avg_rating,
    COUNT(r.id) as review_count,
    COUNT(CASE WHEN r.rating >= 4 THEN 1 END) as five_star,
    COUNT(CASE WHEN r.rating = 3 THEN 1 END) as three_star,
    COUNT(CASE WHEN r.rating <= 2 THEN 1 END) as low_rating
FROM products p
LEFT JOIN reviews r ON p.id = r.product_id AND r.is_approved = TRUE
GROUP BY p.id;

-- ============================================
-- AGGREGATE QUERY: Order Summary View
-- ============================================

CREATE OR REPLACE VIEW order_summary AS
SELECT 
    o.id,
    o.order_number,
    o.total_amount,
    o.status,
    o.payment_status,
    o.order_date,
    u.username,
    u.email,
    COUNT(oi.id) as item_count,
    SUM(oi.quantity) as total_items
FROM orders o
LEFT JOIN users u ON o.user_id = u.id
LEFT JOIN order_items oi ON o.id = oi.order_id
GROUP BY o.id;

-- ============================================
-- STORED PROCEDURE: Get Low Stock Products
-- ============================================

DELIMITER //
CREATE PROCEDURE GetLowStockProducts(IN threshold INT)
BEGIN
    SELECT id, name, sku, stock_quantity, category_id
    FROM products
    WHERE stock_quantity <= threshold AND stock_quantity > 0
    ORDER BY stock_quantity ASC;
END//
DELIMITER ;

-- ============================================
-- STORED PROCEDURE: Get Monthly Sales Report
-- ============================================

DELIMITER //
CREATE PROCEDURE GetMonthlySalesReport(IN yearInput INT, IN monthInput INT)
BEGIN
    SELECT 
        DATE(o.order_date) as sale_date,
        COUNT(DISTINCT o.id) as order_count,
        SUM(o.total_amount) as total_sales,
        SUM(o.tax_amount) as total_tax,
        SUM(o.shipping_amount) as total_shipping,
        AVG(o.total_amount) as avg_order_value
    FROM orders o
    WHERE YEAR(o.order_date) = yearInput 
        AND MONTH(o.order_date) = monthInput
        AND o.status NOT IN ('cancelled', 'refunded')
    GROUP BY DATE(o.order_date)
    ORDER BY sale_date DESC;
END//
DELIMITER ;

-- ============================================
-- TRIGGER: Update Product Stock After Order
-- ============================================

DELIMITER //
CREATE TRIGGER update_product_stock_after_order
AFTER INSERT ON order_items
FOR EACH ROW
BEGIN
    UPDATE products 
    SET stock_quantity = stock_quantity - NEW.quantity
    WHERE id = NEW.product_id;
END//
DELIMITER ;

-- ============================================
-- TRIGGER: Update Variant Stock After Order
-- ============================================

DELIMITER //
CREATE TRIGGER update_variant_stock_after_order
AFTER INSERT ON order_items
FOR EACH ROW
BEGIN
    IF NEW.variant_id IS NOT NULL THEN
        UPDATE product_variants 
        SET stock_quantity = stock_quantity - NEW.quantity
        WHERE id = NEW.variant_id;
    END IF;
END//
DELIMITER ;

-- ============================================
-- FUNCTION: Calculate Order Total
-- ============================================

DELIMITER //
CREATE FUNCTION CalculateOrderTotal(orderId INT)
RETURNS DECIMAL(10,2)
DETERMINISTIC
BEGIN
    DECLARE total DECIMAL(10,2);
    SELECT SUM(quantity * price_at_time) INTO total
    FROM order_items
    WHERE order_id = orderId;
    RETURN COALESCE(total, 0);
END//
DELIMITER ;

-- ============================================
-- FUNCTION: Get User Order Count
-- ============================================

DELIMITER //
CREATE FUNCTION GetUserOrderCount(userId INT)
RETURNS INT
DETERMINISTIC
BEGIN
    DECLARE orderCount INT;
    SELECT COUNT(*) INTO orderCount
    FROM orders
    WHERE user_id = userId AND status NOT IN ('cancelled', 'refunded');
    RETURN orderCount;
END//
DELIMITER ;

-- ============================================
-- RESET AUTO_INCREMENT (Optional)
-- ============================================

-- Reset sequence numbers (useful for clean testing)
ALTER TABLE users AUTO_INCREMENT = 100;
ALTER TABLE categories AUTO_INCREMENT = 100;
ALTER TABLE products AUTO_INCREMENT = 100;
ALTER TABLE orders AUTO_INCREMENT = 1000;