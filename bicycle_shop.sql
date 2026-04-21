CREATE DATABASE IF NOT EXISTS bicycle_shop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bicycle_shop;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS favorites;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS bike_images;
DROP TABLE IF EXISTS bikes;
DROP TABLE IF EXISTS brands;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    role ENUM('admin','seller','buyer','inspector') NOT NULL DEFAULT 'buyer',
    status ENUM('active','inactive','banned') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(150) NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE brands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE bikes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    category_id INT DEFAULT NULL,
    brand_id INT DEFAULT NULL,
    title VARCHAR(180) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    price DECIMAL(12,2) NOT NULL DEFAULT 0,
    condition_status ENUM('new','like_new','used') NOT NULL DEFAULT 'used',
    frame_size VARCHAR(50) DEFAULT NULL,
    wheel_size VARCHAR(50) DEFAULT NULL,
    color VARCHAR(50) DEFAULT NULL,
    location VARCHAR(120) DEFAULT NULL,
    view_count INT NOT NULL DEFAULT 0,
    status ENUM('pending','approved','rejected','sold','completed') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_bikes_seller FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_bikes_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_bikes_brand FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE SET NULL
);

CREATE TABLE bike_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bike_id INT NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_bike_images_bike FOREIGN KEY (bike_id) REFERENCES bikes(id) ON DELETE CASCADE
);

CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    buyer_id INT DEFAULT NULL,
    bike_id INT DEFAULT NULL,
    quantity INT NOT NULL DEFAULT 1,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    status ENUM('pending','confirmed','shipping','completed','cancelled') NOT NULL DEFAULT 'pending',
    shipping_name VARCHAR(120) DEFAULT NULL,
    shipping_phone VARCHAR(30) DEFAULT NULL,
    shipping_address VARCHAR(255) DEFAULT NULL,
    note TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_buyer FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_orders_bike FOREIGN KEY (bike_id) REFERENCES bikes(id) ON DELETE SET NULL
);

CREATE TABLE inspection_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bike_id INT NOT NULL,
    seller_id INT NOT NULL,
    inspector_id INT DEFAULT NULL,
    status ENUM('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
    request_note TEXT DEFAULT NULL,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_inspection_requests_bike FOREIGN KEY (bike_id) REFERENCES bikes(id) ON DELETE CASCADE,
    CONSTRAINT fk_inspection_requests_seller FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_inspection_requests_inspector FOREIGN KEY (inspector_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE inspection_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    bike_id INT NOT NULL,
    inspector_id INT NOT NULL,
    frame_status ENUM('good','warning','bad') NOT NULL DEFAULT 'good',
    brake_status ENUM('good','warning','bad') NOT NULL DEFAULT 'good',
    drivetrain_status ENUM('good','warning','bad') NOT NULL DEFAULT 'good',
    wheel_status ENUM('good','warning','bad') NOT NULL DEFAULT 'good',
    overall_status ENUM('approved','needs_service','rejected') NOT NULL DEFAULT 'approved',
    summary TEXT DEFAULT NULL,
    evidence_image VARCHAR(255) DEFAULT NULL,
    inspected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_inspection_report_request (request_id),
    CONSTRAINT fk_inspection_reports_request FOREIGN KEY (request_id) REFERENCES inspection_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_inspection_reports_bike FOREIGN KEY (bike_id) REFERENCES bikes(id) ON DELETE CASCADE,
    CONSTRAINT fk_inspection_reports_inspector FOREIGN KEY (inspector_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    bike_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_favorite (user_id, bike_id),
    CONSTRAINT fk_favorites_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_favorites_bike FOREIGN KEY (bike_id) REFERENCES bikes(id) ON DELETE CASCADE
);

INSERT INTO users (full_name, email, password, phone, address, role, status) VALUES
('Admin Demo', 'admin@bike.com', '$2y$12$SoQnONI7M.r7RjVyQWDXW.HVE4CsM15SJaRLhT9j4/JhALzbFuGdK', '0900000001', 'Hà Nội', 'admin', 'active'),
('Seller Demo', 'seller@bike.com', '$2y$12$SoQnONI7M.r7RjVyQWDXW.HVE4CsM15SJaRLhT9j4/JhALzbFuGdK', '0900000002', 'TP.HCM', 'seller', 'active'),
('Buyer Demo', 'buyer@bike.com', '$2y$12$SoQnONI7M.r7RjVyQWDXW.HVE4CsM15SJaRLhT9j4/JhALzbFuGdK', '0900000003', 'Đà Nẵng', 'buyer', 'active');

INSERT INTO categories (name, slug, description) VALUES
('Road Bike', 'road-bike', 'Xe đạp đua dành cho tốc độ và đường nhựa.'),
('Mountain Bike', 'mountain-bike', 'Xe đạp địa hình cho cung đường gồ ghề.'),
('City Bike', 'city-bike', 'Xe đạp thành phố tiện dụng hằng ngày.'),
('Hybrid Bike', 'hybrid-bike', 'Xe đạp lai cân bằng giữa tốc độ và thoải mái.'),
('Electric Bike', 'electric-bike', 'Xe đạp điện hỗ trợ di chuyển linh hoạt.');

INSERT INTO brands (name) VALUES
('Giant'),
('Trek'),
('Specialized'),
('Cannondale'),
('Merida'),
('Twitter'),
('Java');

INSERT INTO bikes (
    seller_id, category_id, brand_id, title, slug, description, price,
    condition_status, frame_size, wheel_size, color, location, view_count, status
) VALUES
(2, 1, 1, 'Giant Defy Advanced 2', 'giant-defy-advanced-2', 'Mẫu road bike carbon phù hợp luyện tập và đi đường dài.', 28500000, 'like_new', 'M', '700C', 'Đen', 'TP.HCM', 124, 'approved'),
(2, 2, 2, 'Trek Marlin 7', 'trek-marlin-7', 'Xe MTB khung nhôm, phuộc êm, phù hợp đổ đèo nhẹ và touring.', 18900000, 'used', 'L', '29', 'Xanh', 'Hà Nội', 87, 'approved'),
(2, 3, 5, 'Merida Crossway 100', 'merida-crossway-100', 'Mẫu city/hybrid tiện di chuyển nội đô và đi học đi làm.', 9900000, 'used', 'M', '700C', 'Trắng', 'Đà Nẵng', 45, 'approved'),
(2, 5, 3, 'Specialized Turbo Vado', 'specialized-turbo-vado', 'Xe đạp điện cao cấp, trợ lực tốt, pin ổn định.', 42000000, 'like_new', 'M', '700C', 'Xám', 'Cần Thơ', 61, 'approved'),
(2, 4, 4, 'Cannondale Quick 3', 'cannondale-quick-3', 'Hybrid bike nhẹ, đi phố rất linh hoạt.', 15500000, 'new', 'M', '700C', 'Đỏ', 'Hải Phòng', 33, 'pending');

INSERT INTO bike_images (bike_id, image_url, is_primary, sort_order) VALUES
(1, 'https://images.unsplash.com/photo-1541625602330-2277a4c46182?auto=format&fit=crop&w=900&q=80', 1, 1),
(2, 'https://images.unsplash.com/photo-1517649763962-0c623066013b?auto=format&fit=crop&w=900&q=80', 1, 1),
(3, 'https://images.unsplash.com/photo-1485965120184-e220f721d03e?auto=format&fit=crop&w=900&q=80', 1, 1),
(4, 'https://images.unsplash.com/photo-1571068316344-75bc76f77890?auto=format&fit=crop&w=900&q=80', 1, 1),
(5, 'https://images.unsplash.com/photo-1507035895480-2b3156c31fc8?auto=format&fit=crop&w=900&q=80', 1, 1);

INSERT INTO favorites (user_id, bike_id) VALUES
(3, 1),
(3, 2);

INSERT INTO orders (buyer_id, bike_id, quantity, total_amount, status, shipping_name, shipping_phone, shipping_address, note) VALUES
(3, 1, 1, 28500000, 'pending', 'Buyer Demo', '0900000003', 'Đà Nẵng', 'Gọi trước khi giao');

INSERT INTO users (full_name, email, password, phone, address, role, status) VALUES
('Inspector Demo', 'inspector@bike.com', '$2y$12$SoQnONI7M.r7RjVyQWDXW.HVE4CsM15SJaRLhT9j4/JhALzbFuGdK', '0900000004', 'HÃ  Ná»™i', 'inspector', 'active');

INSERT INTO inspection_requests (bike_id, seller_id, inspector_id, status, request_note) VALUES
(1, 2, 4, 'completed', 'Xe Ä‘Ã£ Ä‘Æ°á»£c giá»­i kiá»ƒm tra tá»•ng thá»ƒ trÆ°á»›c khi bÃ¡n.'),
(2, 2, 4, 'pending', 'Cáº§n kiá»ƒm tra phanh vÃ  truyá»n Ä‘á»™ng.');

INSERT INTO inspection_reports (
    request_id, bike_id, inspector_id, frame_status, brake_status, drivetrain_status, wheel_status, overall_status, summary, evidence_image
) VALUES
(1, 1, 4, 'good', 'good', 'good', 'warning', 'approved', 'Khung sá»­ dá»¥ng tá»‘t, phanh vÃ  bá»™ truyá»n Ä‘á»™ng á»•n Ä‘á»‹nh. BÃ¡nh sau cÃ³ dáº¥u hiá»‡u hao mÃ²n nháº¹ nhÆ°ng váº«n sá»­ dá»¥ng tá»‘t.', 'https://images.unsplash.com/photo-1541625602330-2277a4c46182?auto=format&fit=crop&w=900&q=80');

SET FOREIGN_KEY_CHECKS = 1;
