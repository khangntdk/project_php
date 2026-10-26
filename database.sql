CREATE DATABASE IF NOT EXISTS techzone_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE techzone_db;

SET NAMES utf8mb4;
CREATE TABLE users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('customer', 'admin') NOT NULL DEFAULT 'customer',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role_created (role, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(150) NOT NULL,
    description TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_name (name),
    UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE brands (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(150) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_brands_name (name),
    UNIQUE KEY uq_brands_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id BIGINT UNSIGNED NOT NULL,
    brand_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(220) NOT NULL,
    slug VARCHAR(240) NOT NULL,
    sku VARCHAR(80) NOT NULL,
    description TEXT NOT NULL,
    specifications JSON NULL,
    price DECIMAL(12, 2) NOT NULL,
    sale_price DECIMAL(12, 2) NULL,
    stock INT UNSIGNED NOT NULL DEFAULT 0,
    image VARCHAR(255) NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_products_slug (slug),
    UNIQUE KEY uq_products_sku (sku),
    KEY idx_products_category_status (category_id, status),
    KEY idx_products_brand_status (brand_id, status),
    KEY idx_products_price (price),
    KEY idx_products_created (created_at),
    KEY idx_products_featured_status (is_featured, status),
    CONSTRAINT fk_products_category
        FOREIGN KEY (category_id) REFERENCES categories (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_products_brand
        FOREIGN KEY (brand_id) REFERENCES brands (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_products_price_nonnegative CHECK (price >= 0),
    CONSTRAINT chk_products_sale_price_nonnegative CHECK (sale_price IS NULL OR sale_price >= 0),
    CONSTRAINT chk_products_sale_price_valid CHECK (sale_price IS NULL OR sale_price <= price)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_images (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id BIGINT UNSIGNED NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_product_images_product_order (product_id, sort_order),
    CONSTRAINT fk_product_images_product
        FOREIGN KEY (product_id) REFERENCES products (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,
    order_code VARCHAR(40) NOT NULL,
    recipient_name VARCHAR(150) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    address VARCHAR(500) NOT NULL,
    note TEXT NULL,
    subtotal DECIMAL(12, 2) NOT NULL,
    shipping_fee DECIMAL(12, 2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(12, 2) NOT NULL,
    payment_method ENUM('cod', 'bank_transfer') NOT NULL DEFAULT 'cod',
    payment_status ENUM('unpaid', 'paid', 'refunded') NOT NULL DEFAULT 'unpaid',
    order_status ENUM('pending', 'confirmed', 'shipping', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_orders_order_code (order_code),
    KEY idx_orders_user_created (user_id, created_at),
    KEY idx_orders_status_created (order_status, created_at),
    KEY idx_orders_payment_status (payment_status),
    CONSTRAINT fk_orders_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT chk_orders_subtotal_nonnegative CHECK (subtotal >= 0),
    CONSTRAINT chk_orders_shipping_nonnegative CHECK (shipping_fee >= 0),
    CONSTRAINT chk_orders_total_nonnegative CHECK (total_amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NULL,
    product_name VARCHAR(220) NOT NULL,
    price DECIMAL(12, 2) NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    subtotal DECIMAL(12, 2) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_order_items_order (order_id),
    KEY idx_order_items_product (product_id),
    CONSTRAINT fk_order_items_order
        FOREIGN KEY (order_id) REFERENCES orders (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_order_items_product
        FOREIGN KEY (product_id) REFERENCES products (id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT chk_order_items_price_nonnegative CHECK (price >= 0),
    CONSTRAINT chk_order_items_quantity_positive CHECK (quantity > 0),
    CONSTRAINT chk_order_items_subtotal_nonnegative CHECK (subtotal >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reviews (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    comment TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_reviews_user_product (user_id, product_id),
    KEY idx_reviews_product_created (product_id, created_at),
    CONSTRAINT fk_reviews_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_reviews_product
        FOREIGN KEY (product_id) REFERENCES products (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT chk_reviews_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO users (full_name, email, phone, password, role) VALUES
    ('Quản trị viên TechZone', 'admin@techzone.local', '0900000000',
     '$2y$10$h6KM1TFjCxL2pkzRArY2rezgv6pi9AJsDq5r6rLyTfqa2lFQBeXBq', 'admin');

INSERT INTO categories (name, slug, description) VALUES
    ('Laptop', 'laptop', 'Laptop học tập, văn phòng, gaming và đồ họa.'),
    ('Điện thoại', 'dien-thoai', 'Điện thoại thông minh chính hãng.'),
    ('Máy tính bảng', 'may-tinh-bang', 'Máy tính bảng phục vụ học tập và giải trí.'),
    ('Màn hình', 'man-hinh', 'Màn hình máy tính cho làm việc và chơi game.'),
    ('Linh kiện PC', 'linh-kien-pc', 'Linh kiện nâng cấp và lắp ráp máy tính.'),
    ('Phụ kiện', 'phu-kien', 'Phụ kiện máy tính và thiết bị điện tử.');

INSERT INTO brands (name, slug) VALUES
    ('Acer', 'acer'),
    ('ASUS', 'asus'),
    ('Lenovo', 'lenovo'),
    ('HP', 'hp'),
    ('Dell', 'dell'),
    ('Apple', 'apple'),
    ('Samsung', 'samsung'),
    ('Xiaomi', 'xiaomi'),
    ('OPPO', 'oppo'),
    ('LG', 'lg'),
    ('Logitech', 'logitech'),
    ('Kingston', 'kingston'),
    ('Intel', 'intel'),
    ('NVIDIA', 'nvidia');

INSERT INTO products
    (category_id, brand_id, name, slug, sku, description, specifications, price, sale_price, stock, image, status, is_featured)
VALUES
    (1, 2, 'ASUS Vivobook 15 OLED', 'asus-vivobook-15-oled', 'LAP-ASU-VB15-OLED',
     'Laptop mỏng nhẹ với màn hình OLED sống động, phù hợp học tập và làm việc.',
     JSON_OBJECT('CPU', 'Intel Core i5', 'RAM', '16 GB', 'Ổ cứng', '512 GB SSD', 'Màn hình', '15.6 inch OLED'),
     21990000, 19990000, 12, NULL, 'active', 1),
    (1, 1, 'Acer Aspire 5', 'acer-aspire-5', 'LAP-ACE-ASPIRE5',
     'Laptop đa dụng cho công việc văn phòng và học tập hằng ngày.',
     JSON_OBJECT('CPU', 'Intel Core i5', 'RAM', '16 GB', 'Ổ cứng', '512 GB SSD', 'Màn hình', '15.6 inch FHD'),
     16990000, 15490000, 18, NULL, 'active', 1),
    (1, 3, 'Lenovo IdeaPad Slim 5', 'lenovo-ideapad-slim-5', 'LAP-LEN-IPS5',
     'Thiết kế gọn nhẹ, hiệu năng ổn định cho di chuyển và làm việc.',
     JSON_OBJECT('CPU', 'AMD Ryzen 7', 'RAM', '16 GB', 'Ổ cứng', '512 GB SSD', 'Màn hình', '14 inch WUXGA'),
     19990000, NULL, 9, NULL, 'active', 0),
    (1, 6, 'MacBook Air 13 M3', 'macbook-air-13-m3', 'LAP-APL-MBA13-M3',
     'MacBook mỏng nhẹ với chip Apple M3, thời lượng pin dài.',
     JSON_OBJECT('Chip', 'Apple M3', 'RAM', '8 GB', 'Ổ cứng', '256 GB SSD', 'Màn hình', '13.6 inch Liquid Retina'),
     27990000, 26990000, 7, NULL, 'active', 1),
    (2, 6, 'iPhone 15 128GB', 'iphone-15-128gb', 'PHN-APL-IP15-128',
     'Điện thoại Apple với camera kép và cổng USB-C.',
     JSON_OBJECT('Bộ nhớ', '128 GB', 'Màn hình', '6.1 inch OLED', 'Camera', 'Chính 48 MP'),
     22990000, 19990000, 15, NULL, 'active', 1),
    (2, 7, 'Samsung Galaxy A55 5G', 'samsung-galaxy-a55-5g', 'PHN-SAM-A55-5G',
     'Điện thoại 5G với màn hình Super AMOLED và thiết kế kim loại.',
     JSON_OBJECT('Bộ nhớ', '256 GB', 'RAM', '8 GB', 'Màn hình', '6.6 inch Super AMOLED'),
     10990000, 9990000, 20, NULL, 'active', 1),
    (3, 6, 'iPad Air 11 inch M2', 'ipad-air-11-m2', 'TAB-APL-IPA11-M2',
     'Máy tính bảng hiệu năng cao cho sáng tạo, học tập và giải trí.',
     JSON_OBJECT('Chip', 'Apple M2', 'Bộ nhớ', '128 GB', 'Màn hình', '11 inch Liquid Retina'),
     16990000, NULL, 8, NULL, 'active', 0),
    (4, 10, 'LG UltraGear 27GN800-B', 'lg-ultragear-27gn800-b', 'MON-LG-27GN800',
     'Màn hình gaming QHD tốc độ làm mới cao.',
     JSON_OBJECT('Kích thước', '27 inch', 'Độ phân giải', 'QHD', 'Tần số quét', '144 Hz'),
     7990000, 6990000, 10, NULL, 'active', 1),
    (5, 12, 'Kingston NV2 1TB NVMe', 'kingston-nv2-1tb-nvme', 'SSD-KIN-NV2-1TB',
     'Ổ cứng SSD NVMe M.2 dung lượng 1 TB cho máy tính tương thích.',
     JSON_OBJECT('Dung lượng', '1 TB', 'Giao tiếp', 'PCIe 4.0 NVMe', 'Chuẩn', 'M.2 2280'),
     2190000, 1890000, 25, NULL, 'active', 0),
    (6, 11, 'Logitech MX Master 3S', 'logitech-mx-master-3s', 'ACC-LOG-MXM3S',
     'Chuột không dây hiệu năng cao, hỗ trợ kết nối nhiều thiết bị.',
     JSON_OBJECT('Kết nối', 'Bluetooth / Logi Bolt', 'Độ phân giải', 'Tối đa 8000 DPI', 'Pin', 'Sạc USB-C'),
     2490000, 2190000, 14, NULL, 'active', 0);
