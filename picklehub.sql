-- ========================================
-- USERS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('customer','admin','super_admin') DEFAULT 'customer',
    is_verified TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ========================================
-- USER_OTP TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS user_otps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    otp_code VARCHAR(6) NOT NULL,
    purpose ENUM('register','login') DEFAULT 'register',
    expires_at DATETIME NOT NULL,
    is_used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ========================================
-- REMEMBERED DEVICES TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS remembered_devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    device_token VARCHAR(128) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    last_used DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ========================================
-- CATEGORIES TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    image VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ========================================
-- PRODUCTS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(150) NOT NULL UNIQUE,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    stock INT DEFAULT 0,
    is_cart_enabled TINYINT(1) DEFAULT 1,
    is_featured TINYINT(1) DEFAULT 0,
    image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- ========================================
-- ORDERS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL DEFAULT NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    shipping_address TEXT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50) DEFAULT 'cod',
    status ENUM('pending','processing','shipped','completed','cancelled') DEFAULT 'pending',
    order_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ========================================
-- ORDER ITEMS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(255),
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- ========================================
-- ORDER STATUS LOG TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS order_status_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    status ENUM('pending','processing','shipped','completed','cancelled') NOT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- ========================================
-- COUPONS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    discount DECIMAL(5,2) NOT NULL,
    expiry_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ========================================
-- VISITS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS visits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(50) NOT NULL,
    url VARCHAR(255),
    user_agent VARCHAR(255),
    device_type ENUM('desktop','mobile','tablet') DEFAULT 'desktop',
    is_new TINYINT(1) DEFAULT 1,
    visited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ========================================
-- SETTINGS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(100) NOT NULL UNIQUE,
    `value` TEXT
);

-- ========================================
-- SEED DATA
-- ========================================
INSERT INTO users (name, email, password, role, is_verified) VALUES
('Super Admin', 'superadmin@picklehub.com',
'$2y$10$uWbTnYd2t5k9cF8GkzjC3O7GIX7oAqUu1uSxPi8L94lFvP88z7XCa', 'super_admin', 1),
('Admin', 'admin@picklehub.com',
'$2y$10$uWbTnYd2t5k9cF8GkzjC3O7GIX7oAqUu1uSxPi8L94lFvP88z7XCa', 'admin', 1);

INSERT INTO categories (name, image) VALUES
('Pickles', 'uploads/pickle.jpg'),
('Spices', 'uploads/spice.jpg'),
('Snacks', 'uploads/snack.jpg');

INSERT INTO products (category_id, name, slug, description, price, stock, is_cart_enabled, is_featured, image) VALUES
(1, 'Mango Pickle', 'mango-pickle', 'Delicious homemade mango pickle', 150.00, 100, 1, 1, 'uploads/mango.jpg'),
(1, 'Lemon Pickle', 'lemon-pickle', 'Tangy lemon pickle with spices', 120.00, 80, 1, 1, 'uploads/lemon.jpg'),
(2, 'Red Chili Powder', 'red-chili-powder', 'Pure and spicy chili powder', 90.00, 200, 1, 0, 'uploads/chili.jpg'),
(3, 'Banana Chips', 'banana-chips', 'Crispy banana chips', 60.00, 150, 1, 0, 'uploads/chips.jpg');

INSERT INTO settings (`key`, `value`) VALUES
('about_us_text', 'Family-run since 2020, crafting authentic pickles.'),
('homepage_banner', 'uploads/banner1.jpg'),
('homepage_banner_slides', 'uploads/banner1.jpg,uploads/banner2.jpg,uploads/banner3.jpg'),
('about_us_image', 'uploads/about_placeholder.jpg'),
('contact_info', '123 Pickle Street, Flavor Town, India'),
('homepage_map_embed', '<iframe src="https://www.google.com/maps/embed?..."></iframe>');

-- ========================================
-- INDEXES (Hostinger compatible)
-- ========================================
ALTER TABLE orders ADD INDEX idx_status (status);
ALTER TABLE orders ADD INDEX idx_order_date (order_date);
ALTER TABLE order_items ADD INDEX idx_order_id (order_id);
