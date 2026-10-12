-- =====================================================================
-- awesum_combined_full.sql
-- COMPLETE combined database: awesum_brands + awesum_db in one file.
-- job.order_id links to orders.id  (the only structural change).
--
-- USE: create an empty database (e.g. awesum_combined), select it in
-- phpMyAdmin, then Import this single file. No other file is needed.
-- WARNING: it DROPS and recreates every table listed below.
-- NOTE: product #2 (Hanover) has image = NULL (see end of file).
-- =====================================================================

SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET FOREIGN_KEY_CHECKS = 0;
SET time_zone = '+00:00';
SET NAMES utf8mb4;

DROP TABLE IF EXISTS `site_photo`, `reflection`, `team_member`, `asset`, `job`, `user`,
  `quotes`, `orders`, `messages`, `products`, `adverts`, `users`;

-- =====================================================================
-- PART 1: awesum_brands tables
-- =====================================================================

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(160) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `company` varchar(160) DEFAULT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `adverts` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(160) NOT NULL,
  `description` text NOT NULL,
  `media_type` varchar(20) NOT NULL DEFAULT 'image',
  `media_url` varchar(500) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `products` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(160) NOT NULL,
  `category` varchar(80) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(12,2) NOT NULL,
  `image` longtext DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `messages` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference_code` varchar(32) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(160) NOT NULL,
  `email` varchar(255) NOT NULL,
  `text` text NOT NULL,
  `reply` text DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'Unread',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `replied_at` timestamp NULL DEFAULT NULL,
  `sender_role` varchar(20) NOT NULL DEFAULT 'user',
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference_code` (`reference_code`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `orders` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference_code` varchar(32) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`items`)),
  `total` decimal(12,2) NOT NULL,
  `address` varchar(500) NOT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'Payment pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference_code` (`reference_code`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `quotes` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference_code` varchar(32) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `description` text NOT NULL,
  `category` varchar(80) NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL,
  `notes` text DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'Awaiting Review',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `response` text DEFAULT NULL,
  `design_image` longtext DEFAULT NULL,
  `draft_image` longtext DEFAULT NULL,
  `client_decision` varchar(20) DEFAULT NULL,
  `client_reason` text DEFAULT NULL,
  `client_changes` text DEFAULT NULL,
  `client_idea_image` longtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference_code` (`reference_code`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `quotes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- PART 2: awesum_db tables  (job gets the new order_id link)
-- =====================================================================

CREATE TABLE `user` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `role` varchar(100) NOT NULL DEFAULT 'Installer',
  `email` varchar(150) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `firebase_uid` varchar(191) DEFAULT NULL,
  `auth_provider` varchar(30) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `firebase_uid` (`firebase_uid`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `job` (
  `job_id` int(11) NOT NULL AUTO_INCREMENT,
  `job_code` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `order_id` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('Active','Completed','Cancelled') DEFAULT 'Active',
  `installation_address` varchar(255) NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`job_id`),
  UNIQUE KEY `job_code` (`job_code`),
  KEY `fk_job_user` (`user_id`),
  KEY `fk_job_order` (`order_id`),
  CONSTRAINT `fk_job_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_job_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `asset` (
  `asset_id` int(11) NOT NULL AUTO_INCREMENT,
  `job_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `category` enum('Dispatch','Equipment','Vehicle') NOT NULL,
  `status` enum('In Use','Out for Service') DEFAULT 'In Use',
  `quantity` int(11) DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`asset_id`),
  KEY `fk_asset_job` (`job_id`),
  CONSTRAINT `fk_asset_job` FOREIGN KEY (`job_id`) REFERENCES `job` (`job_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `reflection` (
  `reflection_id` int(11) NOT NULL AUTO_INCREMENT,
  `job_id` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `team_must_return` tinyint(1) DEFAULT 0,
  `submitted` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`reflection_id`),
  UNIQUE KEY `job_id` (`job_id`),
  CONSTRAINT `fk_reflection_job` FOREIGN KEY (`job_id`) REFERENCES `job` (`job_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `site_photo` (
  `photo_id` int(11) NOT NULL AUTO_INCREMENT,
  `job_id` int(11) NOT NULL,
  `file_url` varchar(500) NOT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`photo_id`),
  KEY `fk_photo_job` (`job_id`),
  CONSTRAINT `fk_photo_job` FOREIGN KEY (`job_id`) REFERENCES `job` (`job_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `team_member` (
  `member_id` int(11) NOT NULL AUTO_INCREMENT,
  `job_id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `role` varchar(100) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `linked_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`member_id`),
  KEY `fk_member_job` (`job_id`),
  KEY `fk_member_user` (`linked_user_id`),
  CONSTRAINT `fk_member_job` FOREIGN KEY (`job_id`) REFERENCES `job` (`job_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_member_user` FOREIGN KEY (`linked_user_id`) REFERENCES `user` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================================
-- DATA: awesum_brands
-- =====================================================================

INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `company`, `role`, `created_at`) VALUES
(1, 'Mpilonhle Memela', 'mpilonhlebmemela@gmail.com', '$2y$10$BJMocipWO6/Ub2UG2zcUAe39DG/G5S79v5UIkv/6/BszZNJGngYwC', NULL, 'admin', '2026-09-03 12:45:39'),
(2, 'sbali Mkhize', 'sbali@gmail.com', '$2y$10$dB48GjXCdPeG3CgH386XGO5DoDsvy22u2Vq60JDskrmnTjO0HfGwK', NULL, 'user', '2026-09-03 12:53:37');

INSERT INTO `messages` (`id`, `reference_code`, `user_id`, `name`, `email`, `text`, `reply`, `status`, `created_at`, `replied_at`, `sender_role`) VALUES
(7, 'MSG-A400ABF9', 2, 'sbali Mkhize', 'sbali@gmail.com', 'hi', NULL, 'Unread', '2026-09-04 14:07:22', NULL, 'user');

-- Hanover image omitted (too large to include) -> NULL
INSERT INTO `products` (`id`, `name`, `category`, `description`, `price`, `image`, `active`, `created_at`) VALUES
(1, 'Gift card', 'Business Cards', 'this is cool', 300.00, NULL, 0, '2026-09-03 13:31:54'),
(2, 'Hanover', 'Business Cards', 'Tufle', 850.00, NULL, 0, '2026-09-03 14:13:34');

-- =====================================================================
-- DATA: awesum_db
-- =====================================================================

INSERT INTO `user` (`user_id`, `name`, `role`, `email`, `password`, `firebase_uid`, `auth_provider`, `created_at`) VALUES
(1,'Sipho Dlamini','Lead Installer','sipho@awesum.co.za',NULL,NULL,NULL,'2026-09-03 20:09:21'),
(2,'lungelo','Installer','clivexab@gmail.com',NULL,'TjPC69pHIMcVpMEytuxTEl6U3ne2','password','2026-09-03 22:57:59'),
(3,'Sphumelele Xaba','Installer','clivexabsc@gmail.com',NULL,'tXROMWcLnJRL0yxBQVRdQuj8OBH2','google.com','2026-09-03 23:36:22');

INSERT INTO `job` (`job_id`, `job_code`, `user_id`, `order_id`, `status`, `installation_address`, `start_time`, `end_time`, `created_at`) VALUES
(1,'JOB-2026-0083',1,NULL,'Active','14 Rivonia Rd, Sandton, 2196','2026-08-29 07:30:00','2026-08-29 15:45:00','2026-09-03 20:09:21');

INSERT INTO `asset` (`asset_id`, `job_id`, `name`, `category`, `status`, `quantity`, `notes`, `created_at`) VALUES
(1,1,'Paste Brush Set (Large)','Dispatch','In Use',1,NULL,'2026-09-03 20:09:21'),
(2,1,'Scaffold Section A','Equipment','In Use',1,NULL,'2026-09-03 20:09:21'),
(3,1,'Van — GP 45 XY (White)','Vehicle','Out for Service',1,NULL,'2026-09-03 20:09:21'),
(4,1,'Lift Platform #3','Equipment','In Use',1,NULL,'2026-09-03 20:09:21'),
(5,1,'Adhesive Roller Handles x4','Dispatch','In Use',4,NULL,'2026-09-03 20:09:21'),
(6,1,'Safety Harness Set','Equipment','In Use',1,NULL,'2026-09-03 20:09:21'),
(7,1,'hilux','Vehicle','In Use',1,'for flyers','2026-09-03 22:58:54');

INSERT INTO `reflection` (`reflection_id`, `job_id`, `notes`, `team_must_return`, `submitted`, `created_at`) VALUES
(1,1,'the job did go well',1,1,'2026-09-03 23:00:35');

INSERT INTO `site_photo` (`photo_id`, `job_id`, `file_url`, `caption`, `uploaded_at`) VALUES
(1,1,'uploads/job1_1788476408_6a99fbf837931.jpg','complete','2026-09-03 23:00:08');

INSERT INTO `team_member` (`member_id`, `job_id`, `full_name`, `role`, `phone`, `linked_user_id`, `created_at`) VALUES
(1,1,'Sipho Dlamini','Lead Installer','+27 71 000 0000',NULL,'2026-09-03 20:09:21'),
(2,1,'Thandeka Mokoena','Technician',NULL,NULL,'2026-09-03 20:09:21'),
(3,1,'Luca Ferreira','Driver / Rigger',NULL,NULL,'2026-09-03 20:09:21'),
(4,1,'mpilo','manage','0671364414',NULL,'2026-09-03 22:59:33');

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- Link a job to an order once orders exist:
--   UPDATE job SET order_id = 1 WHERE job_id = 1;
-- =====================================================================
