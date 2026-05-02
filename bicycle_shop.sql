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
INSERT INTO `bikes` VALUES (1,2,1,1,'Trek Domane SL 6 2023','trek-domane-sl-6-2023','Xe Ä‘áº¡p road bike carbon, Ä‘i ráº¥t Ãªm, phÃ¹ há»£p luyá»‡n táº­p vÃ  thi Ä‘áº¥u.',68000000.00,'like_new','M','700C','Äen Ä‘á»','TP. Há»“ ChÃ­ Minh','approved',1,145,'2026-04-09 12:28:36','2026-04-09 12:28:36'),(2,2,2,2,'Giant Talon 1 2022','giant-talon-1-2022','Xe Ä‘áº¡p Ä‘á»‹a hÃ¬nh khung nhÃ´m, cÃ²n má»›i, phÃ¹ há»£p Ä‘i phá»‘ vÃ  leo dá»‘c nháº¹.',22.50,'used','M','29 inch','Xanh Ä‘en','TP. Há»“ ChÃ­ Minh','approved',0,221,'2026-04-09 12:28:36','2026-04-09 15:47:40'),(3,3,2,3,'Specialized Stumpjumper','specialized-stumpjumper','Máº«u MTB cao cáº¥p, giáº£m xÃ³c tá»‘t, thÃ­ch há»£p Ä‘i Ä‘á»‹a hÃ¬nh khÃ³.',47900000.00,'like_new','L','29 inch','XÃ¡m','ÄÃ  Náºµng','approved',1,98,'2026-04-09 12:28:36','2026-04-09 12:28:36'),(4,3,4,5,'Brompton C Line','brompton-c-line','Xe Ä‘áº¡p gáº¥p nhá» gá»n, tiá»‡n di chuyá»ƒn trong thÃ nh phá»‘.',31500000.00,'used','One Size','16 inch','Tráº¯ng','ÄÃ  Náºµng','approved',0,74,'2026-04-09 12:28:36','2026-04-24 14:23:26'),(5,2,3,4,'Cannondale Quick 4','cannondale-quick-4','Xe Ä‘áº¡p thá»ƒ thao thÃ nh phá»‘ nháº¹, linh hoáº¡t, Ä‘i lÃ m vÃ  dáº¡o phá»‘ ráº¥t á»•n.',18900000.00,'used','M','700C','Xanh lÃ¡','TP. Há»“ ChÃ­ Minh','sold',0,132,'2026-04-09 12:28:36','2026-04-09 12:28:36'),(6,2,1,1,'Trek a7','trek-a7','cÃ²n má»›i táº§m 80%',400000000.00,'new','55','700','Äá»Ž','','approved',0,0,'2026-04-09 15:37:14','2026-04-20 14:13:19'),(7,2,2,1,'Checkpoint ALR 3 Gen 3','checkpoint-alr-3-gen-3','TÃ¬nh tráº¡ng: Má»›i, chÆ°a qua sá»­ dá»¥ng\r\nLá»‹ch sá»­ sá»­ dá»¥ng: ÄÆ°á»£c sáº£n xuáº¥t vÃ  kiá»ƒm tra cháº¥t lÆ°á»£ng bá»Ÿi Trek, chiáº¿c xe nÃ y chÆ°a Ä‘Æ°á»£c sá»­ dá»¥ng.\r\nÄiá»ƒm ná»•i báº­t: Khung nhÃ´m Alpha, phuá»™c carbon nháº¹ vÃ  bá»n, há»‡ thá»‘ng truyá»n Ä‘á»™ng Shimano 105 cháº¥t lÆ°á»£ng cao.\r\nPhá»¥ kiá»‡n Ä‘i kÃ¨m: TLR sealant 180 ml, yÃªn xe Verse Short Comp, tay lÃ¡i Bontrager Elite Gravel.\r\nLÃ½ do bÃ¡n: ÄÃ¢y lÃ  máº«u xe má»›i khÃ´ng sá»­ dá»¥ng trong kho.',45.00,'like_new','Flat-mount disc, 142x12 mm chamfered thru axle','700C','Äen bÃ³ng','TP. Há»“ ChÃ­ Minh','approved',0,0,'2026-04-24 14:21:01','2026-04-24 14:24:55');
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
INSERT INTO `brands` VALUES (1,'Trek','trek','ThÆ°Æ¡ng hiá»‡u xe Ä‘áº¡p Trek','2026-04-09 12:28:36'),(2,'Giant','giant','ThÆ°Æ¡ng hiá»‡u xe Ä‘áº¡p Giant','2026-04-09 12:28:36'),(3,'Specialized','specialized','ThÆ°Æ¡ng hiá»‡u xe Ä‘áº¡p Specialized','2026-04-09 12:28:36'),(4,'Cannondale','cannondale','ThÆ°Æ¡ng hiá»‡u xe Ä‘áº¡p Cannondale','2026-04-09 12:28:36'),(5,'Brompton','brompton','ThÆ°Æ¡ng hiá»‡u xe Ä‘áº¡p Brompton','2026-04-09 12:28:36');
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
INSERT INTO `categories` VALUES (1,'Xe Ä‘áº¡p Ä‘Æ°á»ng trÆ°á»ng','xe-dap-duong-truong','CÃ¡c máº«u xe road bike dÃ nh cho Ä‘Æ°á»ng trÆ°á»ng','2026-04-09 12:28:36'),(2,'Xe Ä‘áº¡p Ä‘á»‹a hÃ¬nh','xe-dap-dia-hinh','CÃ¡c máº«u mountain bike cho Ä‘á»‹a hÃ¬nh gá»“ ghá»','2026-04-09 12:28:36'),(3,'Xe Ä‘áº¡p thÃ nh phá»‘','xe-dap-thanh-pho','CÃ¡c máº«u city bike phá»¥c vá»¥ Ä‘i láº¡i hÃ ng ngÃ y','2026-04-09 12:28:36'),(4,'Xe Ä‘áº¡p gáº¥p','xe-dap-gap','CÃ¡c máº«u xe Ä‘áº¡p cÃ³ thá»ƒ gáº¥p gá»n','2026-04-09 12:28:36'),(5,'Xe Ä‘áº¡p touring','xe-dap-touring','CÃ¡c máº«u xe dÃ nh cho hÃ nh trÃ¬nh dÃ i','2026-04-09 12:28:36');
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
INSERT INTO `contact_messages` VALUES (1,'Nguyá»…n VÄƒn A','vana@example.com','Há»i vá» xe Ä‘áº¡p Ä‘á»‹a hÃ¬nh','MÃ¬nh muá»‘n há»i thÃªm vá» cÃ¡c máº«u xe Ä‘áº¡p Ä‘á»‹a hÃ¬nh Ä‘ang cÃ³.','new','2026-04-09 12:28:36'),(2,'Tráº§n Thá»‹ B','tranb@example.com','Há»— trá»£ tÃ i khoáº£n','TÃ´i khÃ´ng Ä‘Äƒng nháº­p Ä‘Æ°á»£c vÃ o tÃ i khoáº£n seller.','read','2026-04-09 12:28:36'),(3,'LÃª VÄƒn C','levanc@example.com','BÃ¡o lá»—i giao diá»‡n','Trang chi tiáº¿t xe hiá»ƒn thá»‹ hÆ¡i lá»‡ch trÃªn Ä‘iá»‡n thoáº¡i.','replied','2026-04-09 12:28:36');
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
  `cancel_reason` text,
  `status` enum('pending','confirmed','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  `payment_method` enum('cash','transfer') NOT NULL DEFAULT 'cash',
  `payment_status` enum('unpaid','paid') NOT NULL DEFAULT 'unpaid',
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
INSERT INTO `orders` (`id`,`order_code`,`bike_id`,`buyer_id`,`seller_id`,`offered_price`,`contact_method`,`meeting_location`,`buyer_note`,`status`,`payment_method`,`payment_status`,`created_at`,`updated_at`,`quantity`) VALUES (1,'DH2026001',1,4,2,66500000.00,'phone','Quáº­n 7, TP. Há»“ ChÃ­ Minh','MÃ¬nh muá»‘n xem xe vÃ o cuá»‘i tuáº§n nÃ y.','pending','cash','unpaid','2026-04-09 12:28:36','2026-04-09 12:28:36',1),(2,'DH2026002',2,5,2,21000000.00,'email','Thá»§ Äá»©c, TP. Há»“ ChÃ­ Minh','Xin giá»¯ xe cho mÃ¬nh Ä‘áº¿n chiá»u thá»© 7.','confirmed','transfer','unpaid','2026-04-09 12:28:36','2026-04-09 12:28:36',1),(3,'DH2026003',3,4,3,47000000.00,'direct','Háº£i ChÃ¢u, ÄÃ  Náºµng','MÃ¬nh cáº§n xe Ä‘á»ƒ Ä‘i trail, mong Ä‘Æ°á»£c test trÆ°á»›c.','in_progress','cash','unpaid','2026-04-09 12:28:36','2026-04-09 12:28:36',1),(4,'DH2026004',5,5,2,18500000.00,'phone','Quáº­n 10, TP. Há»“ ChÃ­ Minh','Náº¿u xe Ä‘Ãºng mÃ´ táº£ mÃ¬nh sáº½ chá»‘t luÃ´n.','completed','cash','paid','2026-04-09 12:28:36','2026-04-09 12:28:36',1),(5,'ORD-20260421042526-6',6,6,2,400000000.00,'phone','91 ung vÄƒn khiÃªm','','pending','cash','unpaid','2026-04-21 04:25:26','2026-04-21 04:25:26',1),(6,'ORD-20260421042932-6',6,6,2,400000000.00,'email','91 ung vÄƒn khiÃªm','','pending','cash','unpaid','2026-04-21 04:29:32','2026-04-21 04:29:32',1),(7,'ORD20260428041733871',7,4,2,45.00,'email','HÃ  Ná»™i','','pending','cash','unpaid','2026-04-28 04:17:33','2026-04-28 04:17:33',1),(8,'ORD20260428041843331',7,4,2,45.00,'phone','HÃ  Ná»™i','','cancelled','cash','unpaid','2026-04-28 04:18:43','2026-04-28 04:21:51',1),(9,'ORD20260428042640262',7,4,2,45.00,'phone','HÃ  Ná»™i','','pending','transfer','unpaid','2026-04-28 04:26:40','2026-04-28 04:26:40',1);
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
INSERT INTO `users` VALUES (1,'Quáº£n trá»‹ viÃªn','admin@bicycleshop.com','$2y$12$O3P9B4FLZMmjTfC95acRKuIwxCOBiwFQs/g0qZCPIpTtt3LN4hAQm','0900000001','TP. Há»“ ChÃ­ Minh',NULL,'admin','active','2026-04-09 12:28:36','2026-04-09 12:47:41'),(2,'Nguyá»…n Minh Khang','seller1@example.com','$2y$12$O3P9B4FLZMmjTfC95acRKuIwxCOBiwFQs/g0qZCPIpTtt3LN4hAQm','0901111111','TP. Há»“ ChÃ­ Minh',NULL,'seller','active','2026-04-09 12:28:36','2026-04-09 12:47:41'),(3,'Tráº§n Quá»‘c Báº£o','seller2@example.com','$2y$12$O3P9B4FLZMmjTfC95acRKuIwxCOBiwFQs/g0qZCPIpTtt3LN4hAQm','0902222222','ÄÃ  Náºµng',NULL,'seller','active','2026-04-09 12:28:36','2026-04-09 12:47:41'),(4,'LÃª Thanh HÆ°Æ¡ng','buyer1@example.com','$2y$12$O3P9B4FLZMmjTfC95acRKuIwxCOBiwFQs/g0qZCPIpTtt3LN4hAQm','0903333333','HÃ  Ná»™i',NULL,'buyer','active','2026-04-09 12:28:36','2026-04-09 12:47:41'),(5,'Pháº¡m Anh Duy','buyer2@example.com','$2y$12$O3P9B4FLZMmjTfC95acRKuIwxCOBiwFQs/g0qZCPIpTtt3LN4hAQm','0904444444','Cáº§n ThÆ¡',NULL,'buyer','active','2026-04-09 12:28:36','2026-04-09 12:47:41'),(6,'Nguyá»…n Há»¯u ChÃ­','huuchinguyen241@gmail.com','$2y$12$0VPEsG1MZlv/dPpbGvf96.L6x/3E39gh528fKdwzChjOH.Wjt0cIm','+84 962 556 029','91 Ung VÄƒn KhiÃªm',NULL,'buyer','active','2026-04-21 03:32:26','2026-04-21 03:32:26');
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
