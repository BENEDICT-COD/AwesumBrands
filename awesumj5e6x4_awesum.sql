-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 17, 2026 at 10:37 PM
-- Server version: 10.11.19-MariaDB
-- PHP Version: 8.4.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `awesumj5e6x4_awesum`
--

-- --------------------------------------------------------

--
-- Table structure for table `adverts`
--

CREATE TABLE `adverts` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(160) NOT NULL,
  `description` text NOT NULL,
  `media_type` varchar(20) NOT NULL DEFAULT 'image',
  `media_url` varchar(500) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `asset`
--

CREATE TABLE `asset` (
  `asset_id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `category` enum('Dispatch','Equipment','Vehicle') NOT NULL,
  `status` enum('In Use','Out for Service') DEFAULT 'In Use',
  `quantity` int(11) DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `asset`
--

INSERT INTO `asset` (`asset_id`, `job_id`, `name`, `category`, `status`, `quantity`, `notes`, `created_at`) VALUES
(1, 1, 'van', 'Vehicle', 'Out for Service', 1, 'card', '2026-10-12 08:44:46'),
(2, 1, 'tablet', 'Equipment', 'In Use', 1, 'for signing', '2026-10-12 08:45:19'),
(3, 7, 'nisssan', 'Vehicle', 'In Use', 1, '', '2026-10-13 11:53:45');

-- --------------------------------------------------------

--
-- Table structure for table `job`
--

CREATE TABLE `job` (
  `job_id` int(11) NOT NULL,
  `job_code` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `order_id` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('Active','Completed','Cancelled') DEFAULT 'Active',
  `installation_address` varchar(255) NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job`
--

INSERT INTO `job` (`job_id`, `job_code`, `user_id`, `order_id`, `status`, `installation_address`, `start_time`, `end_time`, `created_at`) VALUES
(1, 'JOB-ORD-9B9E2266', 3, 3, 'Completed', '447 anton lembede street', '2026-10-12 08:43:31', NULL, '2026-10-12 08:43:31'),
(2, 'JOB-ORD-E2C398BB', 3, 2, 'Active', '447 anton lembede street', '2026-10-12 14:28:48', NULL, '2026-10-12 14:28:48'),
(3, 'JOB-ORD-A9D8EE3B', 1, 4, 'Active', '447 Anton Lembede street', '2026-10-12 15:25:45', NULL, '2026-10-12 15:25:45'),
(4, 'JOB-ORD-E917B611', 1, 5, 'Active', 'BB1569', '2026-10-13 00:02:13', NULL, '2026-10-13 00:02:13'),
(5, 'JOB-ORD-B81C3AE8', 1, 6, 'Active', 'A3445 Rabuli Road', '2026-10-13 01:22:36', NULL, '2026-10-13 01:22:36'),
(6, 'JOB-ORD-EE473BC5', 1, 9, 'Active', '447 Anton Lembede', '2026-10-13 02:07:15', NULL, '2026-10-13 02:07:15'),
(7, 'JOB-ORD-734C9691', 1, 13, 'Completed', '447 smith durban', '2026-10-13 11:49:47', '2026-10-13 13:53:00', '2026-10-13 11:49:47');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `reference_code` varchar(32) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(160) NOT NULL,
  `email` varchar(255) NOT NULL,
  `text` text NOT NULL,
  `reply` text DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'Unread',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `replied_at` timestamp NULL DEFAULT NULL,
  `sender_role` varchar(20) NOT NULL DEFAULT 'user'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `reference_code` varchar(32) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`items`)),
  `total` decimal(12,2) NOT NULL,
  `address` varchar(500) NOT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'Payment pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `reference_code`, `user_id`, `items`, `total`, `address`, `status`, `created_at`) VALUES
(1, 'ORD-B3D6DCB5', 2, '[{\"name\":\"business cards\",\"price\":1,\"quantity\":1,\"lineTotal\":1}]', 1.15, '447 anton lembede street', 'Payment pending', '2026-10-11 22:01:43'),
(2, 'ORD-E2C398BB', 2, '[{\"name\":\"business cards\",\"price\":1,\"quantity\":1,\"lineTotal\":1}]', 1.15, '447 anton lembede street', 'Payment pending', '2026-10-11 22:03:18'),
(3, 'ORD-9B9E2266', 2, '[{\"name\":\"business cards\",\"price\":1,\"quantity\":1,\"lineTotal\":1}]', 1.15, '447 anton lembede street', 'Payment pending', '2026-10-12 07:58:47'),
(4, 'ORD-A9D8EE3B', 2, '[{\"name\":\"business cards\",\"price\":1,\"quantity\":1,\"lineTotal\":1}]', 1.15, '447 Anton Lembede street', 'Payment pending', '2026-10-12 15:24:47'),
(5, 'ORD-E917B611', 3, '[{\"name\":\"business cards\",\"price\":1,\"quantity\":3,\"lineTotal\":3}]', 3.45, 'BB1569', 'Payment pending', '2026-10-12 16:28:09'),
(6, 'ORD-B81C3AE8', 2, '[{\"name\":\"Tshirt branding\",\"price\":50,\"quantity\":1,\"lineTotal\":50}]', 57.50, 'A3445 Rabuli Road', 'Payment received', '2026-10-13 01:19:35'),
(7, 'ORD-5034DB46', 2, '[{\"name\":\"Tshirt branding\",\"price\":50,\"quantity\":4,\"lineTotal\":200}]', 230.00, '447 anton lembede street', 'Payment received', '2026-10-13 01:22:36'),
(8, 'ORD-8CF2F142', 2, '[{\"name\":\"Tshirt branding\",\"price\":50,\"quantity\":3,\"lineTotal\":150}]', 172.50, '447 anton lembede street', 'Payment received', '2026-10-13 01:23:48'),
(9, 'ORD-EE473BC5', 2, '[{\"name\":\"business cards\",\"price\":1,\"quantity\":1,\"lineTotal\":1}]', 1.15, '447 Anton Lembede', 'Payment pending', '2026-10-13 01:59:06'),
(10, 'ORD-71DAC478', 2, '[{\"name\":\"business cards\",\"price\":20,\"quantity\":1,\"lineTotal\":20}]', 23.00, '447 Anton Lembede street', 'Payment pending', '2026-10-13 08:32:21'),
(11, 'ORD-E9A75F4C', 2, '[{\"name\":\"business cards\",\"price\":20,\"quantity\":2,\"lineTotal\":40}]', 46.00, '447 Anton Lembede', 'Payment pending', '2026-10-13 08:34:47'),
(12, 'ORD-16265EAE', 2, '[{\"name\":\"business cards\",\"price\":20,\"quantity\":2,\"lineTotal\":40}]', 46.00, '447 smith street', 'Payment pending', '2026-10-13 08:41:35'),
(13, 'ORD-734C9691', 4, '[{\"name\":\"business cards\",\"price\":20,\"quantity\":1,\"lineTotal\":20}]', 23.00, '447 smith durban', 'Payment received', '2026-10-13 11:35:42'),
(14, 'ORD-4D99AABF', 2, '[{\"name\":\"business cards\",\"price\":20,\"quantity\":1,\"lineTotal\":20}]', 23.00, '447 anton lembede street', 'Payment pending', '2026-10-16 12:07:22');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(160) NOT NULL,
  `category` varchar(80) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(12,2) NOT NULL,
  `image` longtext DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `category`, `description`, `price`, `image`, `active`, `created_at`) VALUES
(1, 'business cards', 'Business Cards', 'we design the business cards just the way you prefer it to be done', 1.00, 'uploads/images/f18f466e2470cb96a17d985e15507eba.png', 0, '2026-10-11 21:58:07'),
(2, 'Tshirt branding', 'T-Shirts', 'Elevate your everyday streetwear with our latest signature drop. Crafted from ultra-soft, heavy-weight 100% organic cotton, this tee features a relaxed, vintage boxy fit designed for effortless style', 50.00, 'uploads/images/6e80c65059d5b17265a21b1412a6a900.jpg', 1, '2026-10-13 01:19:00'),
(3, 'business cards', 'Business Cards', 'we deign business cards', 20.00, 'uploads/images/e54a68ff3253f01e6860baebfb775e32.png', 1, '2026-10-13 02:03:28');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(160) NOT NULL,
  `category` varchar(40) NOT NULL,
  `image` varchar(500) NOT NULL,
  `size` varchar(10) NOT NULL DEFAULT 'narrow',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quotes`
--

CREATE TABLE `quotes` (
  `id` bigint(20) UNSIGNED NOT NULL,
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
  `client_idea_image` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `quotes`
--

INSERT INTO `quotes` (`id`, `reference_code`, `user_id`, `description`, `category`, `quantity`, `notes`, `status`, `created_at`, `response`, `design_image`, `draft_image`, `client_decision`, `client_reason`, `client_changes`, `client_idea_image`) VALUES
(1, 'Q-104FCCC9', 2, 'dkfjdfdehjfkefiuerfherwjkfewrifkweubjfedjk', 'Signage', 100, 'sckdsjchdcdjhfcdwikeuhfwiedfuewfewuidfwegfewfuiewfhwe', 'Awaiting Review', '2026-10-11 22:23:06', NULL, 'uploads/images/61f7d549706caf3d8236b4cf1b510666.jpg', NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `rate_limits`
--

CREATE TABLE `rate_limits` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `bucket` varchar(120) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rate_limits`
--

INSERT INTO `rate_limits` (`id`, `bucket`, `created_at`) VALUES
(2, 'signup-ip:102.252.2.16', '2026-10-12 16:26:59'),
(1, 'signup-ip:169.239.45.27', '2026-10-11 22:01:21'),
(3, 'signup-ip:196.36.175.18', '2026-10-13 08:52:41');

-- --------------------------------------------------------

--
-- Table structure for table `reflection`
--

CREATE TABLE `reflection` (
  `reflection_id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `team_must_return` tinyint(1) DEFAULT 0,
  `submitted` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reflection`
--

INSERT INTO `reflection` (`reflection_id`, `job_id`, `notes`, `team_must_return`, `submitted`, `created_at`) VALUES
(1, 1, 'this was greate delivery', 0, 1, '2026-10-12 08:48:16'),
(2, 7, 'good delivery', 0, 1, '2026-10-13 11:54:46');

-- --------------------------------------------------------

--
-- Table structure for table `site_photo`
--

CREATE TABLE `site_photo` (
  `photo_id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `file_url` varchar(500) NOT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `site_photo`
--

INSERT INTO `site_photo` (`photo_id`, `job_id`, `file_url`, `caption`, `uploaded_at`) VALUES
(1, 1, 'uploads/job1_1791794860_6acc9eacd0e8b.jpg', '', '2026-10-12 08:47:40'),
(2, 1, 'uploads/job1_1791794860_6acc9eacd2350.jpg', '', '2026-10-12 08:47:40'),
(3, 1, 'uploads/job1_1791794860_6acc9eacd3c89.jpg', '', '2026-10-12 08:47:40'),
(4, 1, 'uploads/job1_1791794860_6acc9eacd59bf.jpg', 'done', '2026-10-12 08:47:40'),
(5, 1, 'uploads/job1_1791794860_6acc9eace409c.jpg', 'done', '2026-10-12 08:47:40'),
(6, 1, 'uploads/job1_1791794860_6acc9eacf2c3c.jpg', 'done', '2026-10-12 08:47:40'),
(7, 7, 'uploads/job7_1791892467_6ace1bf3d80cb.jpg', '', '2026-10-13 11:54:27');

-- --------------------------------------------------------

--
-- Table structure for table `team_member`
--

CREATE TABLE `team_member` (
  `member_id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `role` varchar(100) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `linked_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `team_member`
--

INSERT INTO `team_member` (`member_id`, `job_id`, `full_name`, `role`, `phone`, `linked_user_id`, `created_at`) VALUES
(1, 1, 'sipho', 'driver', '0671364414', NULL, '2026-10-12 08:45:50'),
(2, 7, 'sipho', 'driver', '0671364144', NULL, '2026-10-13 11:54:10');

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `user_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `role` varchar(100) NOT NULL DEFAULT 'Installer',
  `email` varchar(150) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `firebase_uid` varchar(191) DEFAULT NULL,
  `auth_provider` varchar(30) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`user_id`, `name`, `role`, `email`, `password`, `firebase_uid`, `auth_provider`, `created_at`) VALUES
(1, 'TheeAfricanTalent', 'Installer', 'clivexab@gmail.com', NULL, 'TjPC69pHIMcVpMEytuxTEl6U3ne2', 'google.com', '2026-10-11 20:08:14'),
(2, 'Sphumelele Xaba', 'Installer', 'clivexabsc@gmail.com', NULL, 'tXROMWcLnJRL0yxBQVRdQuj8OBH2', 'google.com', '2026-10-11 20:11:42'),
(3, 'mpilo', 'Installer', 'mpilo@gmail.com', NULL, 'gDMyKfEGbbTGQn7e7KlEQCEGeJw1', 'password', '2026-10-11 20:13:56');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(160) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `company` varchar(160) DEFAULT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `company`, `role`, `created_at`) VALUES
(1, 'Mpilonhle Memela', 'mpilonhlebmemela@gmail.com', '$2y$10$oHbMu4nmVJsvCKnwkjmE4.CgyHcsZcDwZfC45CgAkdVoeM6i0Wo4G', NULL, 'admin', '2026-09-03 12:45:39'),
(2, 'Sphumelele Clive Xaba', 'clivexab@gmail.com', '$2y$10$pnML7hBprQkF9VX.yGq80u2Buz46pxFBMoSeMofx/cb/xDML0iDUC', 'Future stack', 'user', '2026-10-11 22:01:21'),
(3, 'Lindokuhle Mkhize', 'nonkosi40000@gmail.com', '$2y$10$dYtVZVOe9EyxjAPjxlzMkes.gNCYmj60wEtNOUc9Oh1A2lGvmQyUW', 'BrianzEcho', 'user', '2026-10-12 16:26:59'),
(4, 'UNAM', 'uunastar@gmail.com', '$2y$10$mXhQk80kb78mMt64KKfTPOlX6gqIYbompsLua0aOpMNNCpwCfxKkO', NULL, 'user', '2026-10-13 08:52:41');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `adverts`
--
ALTER TABLE `adverts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `asset`
--
ALTER TABLE `asset`
  ADD PRIMARY KEY (`asset_id`),
  ADD KEY `fk_asset_job` (`job_id`);

--
-- Indexes for table `job`
--
ALTER TABLE `job`
  ADD PRIMARY KEY (`job_id`),
  ADD UNIQUE KEY `job_code` (`job_code`),
  ADD KEY `fk_job_user` (`user_id`),
  ADD KEY `fk_job_order` (`order_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference_code` (`reference_code`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference_code` (`reference_code`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `quotes`
--
ALTER TABLE `quotes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference_code` (`reference_code`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `rate_limits`
--
ALTER TABLE `rate_limits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bucket_time` (`bucket`,`created_at`);

--
-- Indexes for table `reflection`
--
ALTER TABLE `reflection`
  ADD PRIMARY KEY (`reflection_id`),
  ADD UNIQUE KEY `job_id` (`job_id`);

--
-- Indexes for table `site_photo`
--
ALTER TABLE `site_photo`
  ADD PRIMARY KEY (`photo_id`),
  ADD KEY `fk_photo_job` (`job_id`);

--
-- Indexes for table `team_member`
--
ALTER TABLE `team_member`
  ADD PRIMARY KEY (`member_id`),
  ADD KEY `fk_member_job` (`job_id`),
  ADD KEY `fk_member_user` (`linked_user_id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `firebase_uid` (`firebase_uid`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `adverts`
--
ALTER TABLE `adverts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `asset`
--
ALTER TABLE `asset`
  MODIFY `asset_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `job`
--
ALTER TABLE `job`
  MODIFY `job_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quotes`
--
ALTER TABLE `quotes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `rate_limits`
--
ALTER TABLE `rate_limits`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `reflection`
--
ALTER TABLE `reflection`
  MODIFY `reflection_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `site_photo`
--
ALTER TABLE `site_photo`
  MODIFY `photo_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `team_member`
--
ALTER TABLE `team_member`
  MODIFY `member_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `asset`
--
ALTER TABLE `asset`
  ADD CONSTRAINT `fk_asset_job` FOREIGN KEY (`job_id`) REFERENCES `job` (`job_id`) ON DELETE CASCADE;

--
-- Constraints for table `job`
--
ALTER TABLE `job`
  ADD CONSTRAINT `fk_job_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_job_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quotes`
--
ALTER TABLE `quotes`
  ADD CONSTRAINT `quotes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reflection`
--
ALTER TABLE `reflection`
  ADD CONSTRAINT `fk_reflection_job` FOREIGN KEY (`job_id`) REFERENCES `job` (`job_id`) ON DELETE CASCADE;

--
-- Constraints for table `site_photo`
--
ALTER TABLE `site_photo`
  ADD CONSTRAINT `fk_photo_job` FOREIGN KEY (`job_id`) REFERENCES `job` (`job_id`) ON DELETE CASCADE;

--
-- Constraints for table `team_member`
--
ALTER TABLE `team_member`
  ADD CONSTRAINT `fk_member_job` FOREIGN KEY (`job_id`) REFERENCES `job` (`job_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_member_user` FOREIGN KEY (`linked_user_id`) REFERENCES `user` (`user_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
