-- MySQL dump 10.13  Distrib 8.0.36, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: bicycle_shop
-- ------------------------------------------------------
-- Server version	8.0.39

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `bike_images`
--

DROP TABLE IF EXISTS `bike_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bike_images` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bike_id` int NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bike_images_bike_id` (`bike_id`),
  CONSTRAINT `fk_bike_images_bike` FOREIGN KEY (`bike_id`) REFERENCES `bikes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bike_images`
--

LOCK TABLES `bike_images` WRITE;
/*!40000 ALTER TABLE `bike_images` DISABLE KEYS */;
INSERT INTO `bike_images` VALUES (1,1,'uploads/bikes/trek-domane-main.jpg',1,1,'2026-04-09 12:28:36'),(2,1,'uploads/bikes/trek-domane-2.jpg',0,2,'2026-04-09 12:28:36'),(3,2,'uploads/bikes/giant-talon-main.jpg',1,1,'2026-04-09 12:28:36'),(4,3,'uploads/bikes/specialized-main.jpg',1,1,'2026-04-09 12:28:36'),(5,4,'uploads/bikes/brompton-main.jpg',1,1,'2026-04-09 12:28:36'),(6,5,'uploads/bikes/cannondale-main.jpg',1,1,'2026-04-09 12:28:36');
/*!40000 ALTER TABLE `bike_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bikes`
--

DROP TABLE IF EXISTS `bikes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bikes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `seller_id` int NOT NULL,
  `category_id` int NOT NULL,
  `brand_id` int NOT NULL,
  `title` varchar(150) NOT NULL,
  `slug` varchar(180) NOT NULL,
  `description` text NOT NULL,
  `price` decimal(12,2) NOT NULL,
  `condition_status` enum('new','like_new','used') NOT NULL DEFAULT 'used',
  `frame_size` varchar(50) DEFAULT NULL,
  `wheel_size` varchar(50) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `location` varchar(120) DEFAULT NULL,
  `status` enum('pending','approved','rejected','sold') NOT NULL DEFAULT 'pending',
  `is_featured` tinyint(1) NOT NULL DEFAULT '0',
  `view_count` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_bikes_seller_id` (`seller_id`),
  KEY `idx_bikes_category_id` (`category_id`),
  KEY `idx_bikes_brand_id` (`brand_id`),
  KEY `idx_bikes_status` (`status`),
  KEY `idx_bikes_is_featured` (`is_featured`),
  KEY `idx_bikes_price` (`price`),
  CONSTRAINT `fk_bikes_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_bikes_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_bikes_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bikes`
--

LOCK TABLES `bikes` WRITE;
/*!40000 ALTER TABLE `bikes` DISABLE KEYS */;
INSERT INTO `bikes` VALUES (1,2,1,1,'Trek Domane SL 6 2023','trek-domane-sl-6-2023','Xe đạp road bike carbon, đi rất êm, phù hợp luyện tập và thi đấu.',68000000.00,'like_new','M','700C','Đen đỏ','TP. Hồ Chí Minh','approved',1,145,'2026-04-09 12:28:36','2026-04-09 12:28:36'),(2,2,2,2,'Giant Talon 1 2022','giant-talon-1-2022','Xe đạp địa hình khung nhôm, còn mới, phù hợp đi phố và leo dốc nhẹ.',22.50,'used','M','29 inch','Xanh đen','TP. Hồ Chí Minh','approved',0,221,'2026-04-09 12:28:36','2026-04-09 15:47:40'),(3,3,2,3,'Specialized Stumpjumper','specialized-stumpjumper','Mẫu MTB cao cấp, giảm xóc tốt, thích hợp đi địa hình khó.',47900000.00,'like_new','L','29 inch','Xám','Đà Nẵng','approved',1,98,'2026-04-09 12:28:36','2026-04-09 12:28:36'),(4,3,4,5,'Brompton C Line','brompton-c-line','Xe đạp gấp nhỏ gọn, tiện di chuyển trong thành phố.',31500000.00,'used','One Size','16 inch','Trắng','Đà Nẵng','approved',0,74,'2026-04-09 12:28:36','2026-04-24 14:23:26'),(5,2,3,4,'Cannondale Quick 4','cannondale-quick-4','Xe đạp thể thao thành phố nhẹ, linh hoạt, đi làm và dạo phố rất ổn.',18900000.00,'used','M','700C','Xanh lá','TP. Hồ Chí Minh','sold',0,132,'2026-04-09 12:28:36','2026-04-09 12:28:36'),(6,2,1,1,'Trek a7','trek-a7','còn mới tầm 80%',400000000.00,'new','55','700','ĐỎ','','approved',0,0,'2026-04-09 15:37:14','2026-04-20 14:13:19'),(7,2,2,1,'Checkpoint ALR 3 Gen 3','checkpoint-alr-3-gen-3','Tình trạng: Mới, chưa qua sử dụng\r\nLịch sử sử dụng: Được sản xuất và kiểm tra chất lượng bởi Trek, chiếc xe này chưa được sử dụng.\r\nĐiểm nổi bật: Khung nhôm Alpha, phuộc carbon nhẹ và bền, hệ thống truyền động Shimano 105 chất lượng cao.\r\nPhụ kiện đi kèm: TLR sealant 180 ml, yên xe Verse Short Comp, tay lái Bontrager Elite Gravel.\r\nLý do bán: Đây là mẫu xe mới không sử dụng trong kho.',45.00,'like_new','Flat-mount disc, 142x12 mm chamfered thru axle','700C','Đen bóng','TP. Hồ Chí Minh','approved',0,0,'2026-04-24 14:21:01','2026-04-24 14:24:55');
/*!40000 ALTER TABLE `bikes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `brands`
--

DROP TABLE IF EXISTS `brands`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `brands` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `brands`
--

LOCK TABLES `brands` WRITE;
/*!40000 ALTER TABLE `brands` DISABLE KEYS */;
INSERT INTO `brands` VALUES (1,'Trek','trek','Thương hiệu xe đạp Trek','2026-04-09 12:28:36'),(2,'Giant','giant','Thương hiệu xe đạp Giant','2026-04-09 12:28:36'),(3,'Specialized','specialized','Thương hiệu xe đạp Specialized','2026-04-09 12:28:36'),(4,'Cannondale','cannondale','Thương hiệu xe đạp Cannondale','2026-04-09 12:28:36'),(5,'Brompton','brompton','Thương hiệu xe đạp Brompton','2026-04-09 12:28:36');
/*!40000 ALTER TABLE `brands` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,'Xe đạp đường trường','xe-dap-duong-truong','Các mẫu xe road bike dành cho đường trường','2026-04-09 12:28:36'),(2,'Xe đạp địa hình','xe-dap-dia-hinh','Các mẫu mountain bike cho địa hình gồ ghề','2026-04-09 12:28:36'),(3,'Xe đạp thành phố','xe-dap-thanh-pho','Các mẫu city bike phục vụ đi lại hàng ngày','2026-04-09 12:28:36'),(4,'Xe đạp gấp','xe-dap-gap','Các mẫu xe đạp có thể gấp gọn','2026-04-09 12:28:36'),(5,'Xe đạp touring','xe-dap-touring','Các mẫu xe dành cho hành trình dài','2026-04-09 12:28:36');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contact_messages`
--

DROP TABLE IF EXISTS `contact_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contact_messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(120) NOT NULL,
  `subject` varchar(150) DEFAULT NULL,
  `message` text NOT NULL,
  `status` enum('new','read','replied') NOT NULL DEFAULT 'new',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_contact_messages_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contact_messages`
--

LOCK TABLES `contact_messages` WRITE;
/*!40000 ALTER TABLE `contact_messages` DISABLE KEYS */;
INSERT INTO `contact_messages` VALUES (1,'Nguyễn Văn A','vana@example.com','Hỏi về xe đạp địa hình','Mình muốn hỏi thêm về các mẫu xe đạp địa hình đang có.','new','2026-04-09 12:28:36'),(2,'Trần Thị B','tranb@example.com','Hỗ trợ tài khoản','Tôi không đăng nhập được vào tài khoản seller.','read','2026-04-09 12:28:36'),(3,'Lê Văn C','levanc@example.com','Báo lỗi giao diện','Trang chi tiết xe hiển thị hơi lệch trên điện thoại.','replied','2026-04-09 12:28:36');
/*!40000 ALTER TABLE `contact_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `favorites`
--

DROP TABLE IF EXISTS `favorites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `favorites` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `bike_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_favorites_user_bike` (`user_id`,`bike_id`),
  KEY `idx_favorites_user_id` (`user_id`),
  KEY `idx_favorites_bike_id` (`bike_id`),
  CONSTRAINT `fk_favorites_bike` FOREIGN KEY (`bike_id`) REFERENCES `bikes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_favorites_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `favorites`
--

LOCK TABLES `favorites` WRITE;
/*!40000 ALTER TABLE `favorites` DISABLE KEYS */;
INSERT INTO `favorites` VALUES (1,4,1,'2026-04-09 12:28:36'),(3,5,3,'2026-04-09 12:28:36'),(5,4,2,'2026-04-14 03:25:23');
/*!40000 ALTER TABLE `favorites` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inspection_reports`
--

DROP TABLE IF EXISTS `inspection_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inspection_reports` (
  `id` int NOT NULL AUTO_INCREMENT,
  `request_id` int NOT NULL,
  `bike_id` int NOT NULL,
  `inspector_id` int NOT NULL,
  `frame_status` enum('good','warning','bad') NOT NULL DEFAULT 'good',
  `brake_status` enum('good','warning','bad') NOT NULL DEFAULT 'good',
  `drivetrain_status` enum('good','warning','bad') NOT NULL DEFAULT 'good',
  `wheel_status` enum('good','warning','bad') NOT NULL DEFAULT 'good',
  `overall_status` enum('approved','needs_service','rejected') NOT NULL DEFAULT 'approved',
  `summary` text,
  `evidence_image` varchar(255) DEFAULT NULL,
  `inspected_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_inspection_report_request` (`request_id`),
  KEY `fk_inspection_reports_bike` (`bike_id`),
  KEY `fk_inspection_reports_inspector` (`inspector_id`),
  CONSTRAINT `fk_inspection_reports_bike` FOREIGN KEY (`bike_id`) REFERENCES `bikes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inspection_reports_inspector` FOREIGN KEY (`inspector_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inspection_reports_request` FOREIGN KEY (`request_id`) REFERENCES `inspection_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inspection_reports`
--

LOCK TABLES `inspection_reports` WRITE;
/*!40000 ALTER TABLE `inspection_reports` DISABLE KEYS */;
/*!40000 ALTER TABLE `inspection_reports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inspection_requests`
--

DROP TABLE IF EXISTS `inspection_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inspection_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bike_id` int NOT NULL,
  `seller_id` int NOT NULL,
  `inspector_id` int DEFAULT NULL,
  `status` enum('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  `request_note` text,
  `requested_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_inspection_requests_bike` (`bike_id`),
  KEY `fk_inspection_requests_seller` (`seller_id`),
  KEY `fk_inspection_requests_inspector` (`inspector_id`),
  CONSTRAINT `fk_inspection_requests_bike` FOREIGN KEY (`bike_id`) REFERENCES `bikes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inspection_requests_inspector` FOREIGN KEY (`inspector_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inspection_requests_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inspection_requests`
--

LOCK TABLES `inspection_requests` WRITE;
/*!40000 ALTER TABLE `inspection_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `inspection_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_code` varchar(30) NOT NULL,
  `bike_id` int NOT NULL,
  `buyer_id` int NOT NULL,
  `seller_id` int NOT NULL,
  `offered_price` decimal(12,2) NOT NULL,
  `contact_method` enum('phone','email','chat','direct') NOT NULL DEFAULT 'phone',
  `meeting_location` varchar(255) DEFAULT NULL,
  `buyer_note` text,
  `status` enum('pending','confirmed','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  `payment_method` enum('cash','transfer','vnpay') NOT NULL DEFAULT 'cash',
  `payment_status` enum('unpaid','pending','paid','failed') NOT NULL DEFAULT 'unpaid',
  `payment_transaction_id` varchar(100) DEFAULT NULL,
  `payment_bank_code` varchar(30) DEFAULT NULL,
  `payment_response_code` varchar(10) DEFAULT NULL,
  `payment_paid_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `quantity` int DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_code` (`order_code`),
  KEY `idx_orders_bike_id` (`bike_id`),
  KEY `idx_orders_buyer_id` (`buyer_id`),
  KEY `idx_orders_seller_id` (`seller_id`),
  KEY `idx_orders_status` (`status`),
  KEY `idx_orders_payment_status` (`payment_status`),
  CONSTRAINT `fk_orders_bike` FOREIGN KEY (`bike_id`) REFERENCES `bikes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_orders_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_orders_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (1,'DH2026001',1,4,2,66500000.00,'phone','Quận 7, TP. Hồ Chí Minh','Mình muốn xem xe vào cuối tuần này.','pending','cash','unpaid','2026-04-09 12:28:36','2026-04-09 12:28:36',1),(2,'DH2026002',2,5,2,21000000.00,'email','Thủ Đức, TP. Hồ Chí Minh','Xin giữ xe cho mình đến chiều thứ 7.','confirmed','transfer','unpaid','2026-04-09 12:28:36','2026-04-09 12:28:36',1),(3,'DH2026003',3,4,3,47000000.00,'direct','Hải Châu, Đà Nẵng','Mình cần xe để đi trail, mong được test trước.','in_progress','cash','unpaid','2026-04-09 12:28:36','2026-04-09 12:28:36',1),(4,'DH2026004',5,5,2,18500000.00,'phone','Quận 10, TP. Hồ Chí Minh','Nếu xe đúng mô tả mình sẽ chốt luôn.','completed','cash','paid','2026-04-09 12:28:36','2026-04-09 12:28:36',1),(5,'ORD-20260421042526-6',6,6,2,400000000.00,'phone','91 ung văn khiêm','','pending','cash','unpaid','2026-04-21 04:25:26','2026-04-21 04:25:26',1),(6,'ORD-20260421042932-6',6,6,2,400000000.00,'email','91 ung văn khiêm','','pending','cash','unpaid','2026-04-21 04:29:32','2026-04-21 04:29:32',1),(7,'ORD20260428041733871',7,4,2,45.00,'email','Hà Nội','','pending','cash','unpaid','2026-04-28 04:17:33','2026-04-28 04:17:33',1),(8,'ORD20260428041843331',7,4,2,45.00,'phone','Hà Nội','','cancelled','cash','unpaid','2026-04-28 04:18:43','2026-04-28 04:21:51',1),(9,'ORD20260428042640262',7,4,2,45.00,'phone','Hà Nội','','pending','transfer','unpaid','2026-04-28 04:26:40','2026-04-28 04:26:40',1);
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(120) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `role` enum('buyer','seller','admin') NOT NULL DEFAULT 'buyer',
  `status` enum('active','inactive','banned') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Quản trị viên','admin@bicycleshop.com','$2y$12$O3P9B4FLZMmjTfC95acRKuIwxCOBiwFQs/g0qZCPIpTtt3LN4hAQm','0900000001','TP. Hồ Chí Minh',NULL,'admin','active','2026-04-09 12:28:36','2026-04-09 12:47:41'),(2,'Nguyễn Minh Khang','seller1@example.com','$2y$12$O3P9B4FLZMmjTfC95acRKuIwxCOBiwFQs/g0qZCPIpTtt3LN4hAQm','0901111111','TP. Hồ Chí Minh',NULL,'seller','active','2026-04-09 12:28:36','2026-04-09 12:47:41'),(3,'Trần Quốc Bảo','seller2@example.com','$2y$12$O3P9B4FLZMmjTfC95acRKuIwxCOBiwFQs/g0qZCPIpTtt3LN4hAQm','0902222222','Đà Nẵng',NULL,'seller','active','2026-04-09 12:28:36','2026-04-09 12:47:41'),(4,'Lê Thanh Hương','buyer1@example.com','$2y$12$O3P9B4FLZMmjTfC95acRKuIwxCOBiwFQs/g0qZCPIpTtt3LN4hAQm','0903333333','Hà Nội',NULL,'buyer','active','2026-04-09 12:28:36','2026-04-09 12:47:41'),(5,'Phạm Anh Duy','buyer2@example.com','$2y$12$O3P9B4FLZMmjTfC95acRKuIwxCOBiwFQs/g0qZCPIpTtt3LN4hAQm','0904444444','Cần Thơ',NULL,'buyer','active','2026-04-09 12:28:36','2026-04-09 12:47:41'),(6,'Nguyễn Hữu Chí','huuchinguyen241@gmail.com','$2y$12$0VPEsG1MZlv/dPpbGvf96.L6x/3E39gh528fKdwzChjOH.Wjt0cIm','+84 962 556 029','91 Ung Văn Khiêm',NULL,'buyer','active','2026-04-21 03:32:26','2026-04-21 03:32:26');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'bicycle_shop'
--

--
-- Dumping routines for database 'bicycle_shop'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-04-28 11:31:18
