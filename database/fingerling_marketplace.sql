-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 15, 2025 at 12:08 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `fingerling_marketplace`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `role` varchar(100) DEFAULT 'admin',
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permissions`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `user_id`, `full_name`, `role`, `permissions`, `created_at`) VALUES
(1, 1, 'System Administrator', 'super_admin', NULL, '2025-08-12 14:01:19'),
(3, 8, 'System Administrator', 'super_admin', '{\"users\": true, \"suppliers\": true, \"orders\": true, \"products\": true, \"reports\": true, \"settings\": true}', '2025-08-14 08:41:50');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `type` enum('info','warning','urgent') DEFAULT 'info',
  `target_audience` enum('all','customers','suppliers') DEFAULT 'all',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `content`, `type`, `target_audience`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Welcome to Fingerling Online Ordering Platform', 'Welcome to our platform! We connect fish farmers with quality fingerling suppliers across the Philippines.', 'info', 'all', 'active', '2025-08-14 09:08:01', '2025-08-14 09:08:01'),
(2, 'New Payment Methods Available', 'We now accept GCash and PayMaya for faster transactions!', 'info', 'customers', 'active', '2025-08-14 09:08:01', '2025-08-14 09:08:01'),
(3, 'Supplier Guidelines Updated', 'Please review the updated supplier guidelines for better service quality.', 'warning', 'suppliers', 'active', '2025-08-14 09:08:01', '2025-08-14 09:08:01');

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `table_name` varchar(100) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `price_per_piece` decimal(10,2) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Freshwater Fish', 'Fish species that live in freshwater environments', 'active', '2025-08-14 09:08:01', '2025-08-14 09:08:01'),
(2, 'Marine Fish', 'Fish species that live in saltwater environments', 'active', '2025-08-14 09:08:01', '2025-08-14 09:08:01'),
(3, 'Brackish Fish', 'Fish species that live in brackish water environments', 'active', '2025-08-14 09:08:01', '2025-08-14 09:08:01');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `phone` varchar(20) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `user_id`, `first_name`, `last_name`, `contact_number`, `address`, `barangay`, `city`, `province`, `created_at`, `updated_at`, `phone`, `postal_code`) VALUES
(1, 2, 'jenny', 'pagara', '09929292', 'sddad', 'labo', 'mynila city', 'asasa', '2025-08-12 14:04:18', '2025-08-12 14:04:18', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `customer_addresses`
--

CREATE TABLE `customer_addresses` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `label` varchar(100) NOT NULL,
  `recipient_name` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address_line_1` varchar(255) NOT NULL,
  `address_line_2` varchar(255) DEFAULT NULL,
  `barangay` varchar(100) NOT NULL,
  `city` varchar(100) NOT NULL,
  `province` varchar(100) NOT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `species_id` int(11) NOT NULL,
  `size_category` varchar(50) DEFAULT NULL,
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `price_per_piece` decimal(10,2) NOT NULL,
  `minimum_order` int(11) DEFAULT 1,
  `availability_status` enum('available','out_of_stock','discontinued') DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `supplier_id`, `species_id`, `size_category`, `stock_quantity`, `price_per_piece`, `minimum_order`, `availability_status`, `created_at`, `updated_at`) VALUES
(1, 1, 6, 'fry', 174, 42.94, 46, 'available', '2025-08-14 07:47:43', '2025-08-14 07:47:43'),
(2, 1, 6, 'juvenile', 305, 50.06, 24, 'available', '2025-08-14 07:47:43', '2025-08-14 07:47:43'),
(3, 1, 6, 'adult', 405, 34.01, 29, 'available', '2025-08-14 07:47:43', '2025-08-14 07:47:43'),
(4, 1, 7, 'fry', 396, 43.04, 21, 'available', '2025-08-14 07:47:43', '2025-08-14 07:47:43'),
(5, 1, 7, 'juvenile', 276, 22.01, 16, 'available', '2025-08-14 07:47:43', '2025-08-14 07:47:43'),
(6, 1, 7, 'adult', 199, 23.01, 23, 'available', '2025-08-14 07:47:43', '2025-08-14 07:47:43'),
(7, 1, 8, 'fry', 328, 17.68, 23, 'available', '2025-08-14 07:47:43', '2025-08-14 07:47:43'),
(8, 1, 8, 'juvenile', 942, 44.95, 22, 'available', '2025-08-14 07:47:43', '2025-08-14 07:47:43'),
(9, 1, 8, 'adult', 185, 28.01, 25, 'available', '2025-08-14 07:47:43', '2025-08-14 07:47:43'),
(10, 1, 9, 'fry', 161, 8.32, 10, 'available', '2025-08-14 07:47:43', '2025-08-14 07:47:43'),
(11, 1, 9, 'juvenile', 818, 27.54, 15, 'available', '2025-08-14 07:47:43', '2025-08-14 07:47:43'),
(12, 1, 9, 'adult', 834, 42.17, 43, 'available', '2025-08-14 07:47:43', '2025-08-14 07:47:43'),
(13, 2, 6, 'fry', 474, 47.67, 20, 'available', '2025-08-14 07:47:43', '2025-08-14 07:47:43'),
(14, 2, 6, 'juvenile', 371, 14.18, 39, 'available', '2025-08-14 07:47:43', '2025-08-14 07:47:43'),
(15, 2, 6, 'adult', 986, 33.58, 41, 'available', '2025-08-14 07:47:43', '2025-08-14 07:47:43'),
(16, 2, 7, 'fry', 369, 10.65, 36, 'available', '2025-08-14 07:47:43', '2025-08-14 07:47:43'),
(17, 2, 7, 'juvenile', 796, 7.56, 33, 'available', '2025-08-14 07:47:43', '2025-08-14 07:47:43'),
(18, 2, 7, 'adult', 483, 49.75, 11, 'available', '2025-08-14 07:47:43', '2025-08-14 07:47:43'),
(19, 2, 8, 'fry', 351, 5.19, 49, 'available', '2025-08-14 07:47:43', '2025-08-14 07:47:43'),
(20, 2, 8, 'juvenile', 620, 32.32, 18, 'available', '2025-08-14 07:47:43', '2025-08-14 07:47:43'),
(21, 2, 8, 'adult', 386, 30.80, 26, 'available', '2025-08-14 07:47:43', '2025-08-14 07:47:43'),
(22, 2, 9, 'fry', 675, 7.34, 14, 'available', '2025-08-14 07:47:43', '2025-08-14 07:47:43'),
(23, 2, 9, 'juvenile', 699, 49.25, 34, 'available', '2025-08-14 07:47:43', '2025-08-14 07:47:43'),
(24, 2, 9, 'adult', 739, 50.57, 35, 'available', '2025-08-14 07:47:43', '2025-08-14 07:47:43'),
(25, 3, 6, 'fry', 764, 47.95, 33, 'available', '2025-08-14 07:47:43', '2025-08-14 07:47:43'),
(26, 3, 6, 'juvenile', 959, 48.17, 46, 'available', '2025-08-14 07:47:43', '2025-08-14 07:47:43'),
(27, 3, 6, 'adult', 360, 10.14, 31, 'available', '2025-08-14 07:47:43', '2025-08-14 07:47:43'),
(28, 3, 7, 'fry', 449, 5.62, 11, 'available', '2025-08-14 07:47:43', '2025-08-14 07:47:43'),
(29, 3, 7, 'juvenile', 102, 17.90, 44, 'available', '2025-08-14 07:47:43', '2025-08-14 07:47:43'),
(30, 3, 7, 'adult', 208, 35.06, 20, 'available', '2025-08-14 07:47:43', '2025-08-14 07:47:43'),
(31, 3, 8, 'fry', 568, 49.05, 15, 'available', '2025-08-14 07:47:43', '2025-08-14 07:47:43'),
(32, 3, 8, 'juvenile', 973, 7.34, 47, 'available', '2025-08-14 07:47:43', '2025-08-14 07:47:43'),
(33, 3, 8, 'adult', 567, 35.09, 10, 'available', '2025-08-14 07:47:43', '2025-08-14 07:47:43'),
(34, 3, 9, 'fry', 326, 28.33, 21, 'available', '2025-08-14 07:47:43', '2025-08-14 07:47:43'),
(35, 3, 9, 'juvenile', 481, 28.40, 48, 'available', '2025-08-14 07:47:43', '2025-08-14 07:47:43'),
(36, 3, 9, 'adult', 220, 46.89, 10, 'available', '2025-08-14 07:47:43', '2025-08-14 07:47:43'),
(43, 6, 1, 'fingerling', 5000, 2.50, 300, 'available', '2025-08-14 12:57:01', '2025-08-14 12:57:01'),
(44, 6, 1, 'juvenile', 3000, 5.00, 200, 'available', '2025-08-14 12:57:01', '2025-08-14 12:57:01'),
(45, 6, 2, 'fingerling', 7000, 3.25, 400, 'available', '2025-08-14 12:57:01', '2025-08-14 12:57:01'),
(46, 6, 3, 'fingerling', 9000, 4.00, 500, 'available', '2025-08-14 12:57:01', '2025-08-14 12:57:01');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('order','payment','system','promotion') DEFAULT 'system',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','confirmed','preparing','out_for_delivery','delivered','cancelled') DEFAULT 'pending',
  `delivery_address` text DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `delivery_worker` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tracking_number` varchar(50) DEFAULT NULL,
  `expected_delivery_date` date DEFAULT NULL,
  `delivery_phone` varchar(20) DEFAULT NULL,
  `delivery_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_tracking`
--

CREATE TABLE `order_tracking` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `status` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `payment_method` enum('gcash','paypal') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `reference_number` varchar(255) DEFAULT NULL,
  `status` enum('pending','completed','failed','refunded') DEFAULT 'pending',
  `payment_date` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `refunds`
--

CREATE TABLE `refunds` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected','processed') DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `description`, `updated_at`) VALUES
(1, 'site_name', 'Fingerling Online Ordering Platform', 'Website name', '2025-08-12 14:01:19'),
(2, 'site_email', 'info@fingerling.com', 'Contact email', '2025-08-12 14:01:19'),
(3, 'gcash_enabled', '1', 'Enable GCash payments', '2025-08-12 14:01:19'),
(4, 'paypal_enabled', '1', 'Enable PayPal payments', '2025-08-12 14:01:19'),
(5, 'delivery_radius', '50', 'Maximum delivery radius in kilometers', '2025-08-12 14:01:19'),
(6, 'min_order_amount', '500', 'Minimum order amount in PHP', '2025-08-12 14:01:19');

-- --------------------------------------------------------

--
-- Table structure for table `species`
--

CREATE TABLE `species` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `scientific_name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `category_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `species`
--

INSERT INTO `species` (`id`, `name`, `scientific_name`, `description`, `image_url`, `category`, `status`, `created_at`, `category_id`) VALUES
(1, 'Tilapia', 'Oreochromis niloticus', 'Fast-growing freshwater fish, ideal for aquaculture', NULL, 'Freshwater', 'active', '2025-08-12 14:01:19', 1),
(2, 'Bangus (Milkfish)', 'Chanos chanos', 'Popular marine fish in the Philippines', NULL, 'Marine', 'active', '2025-08-12 14:01:19', 2),
(3, 'Catfish', 'Clarias gariepinus', 'Hardy freshwater fish, easy to raise', NULL, 'Freshwater', 'active', '2025-08-12 14:01:19', 1),
(4, 'Carp', 'Cyprinus carpio', 'Common freshwater fish for pond culture', NULL, 'Freshwater', 'active', '2025-08-12 14:01:19', 1),
(5, 'Grouper', 'Epinephelus spp.', 'High-value marine fish', NULL, 'Marine', 'active', '2025-08-12 14:01:19', 2),
(6, 'Tilapia', 'Oreochromis niloticus', NULL, NULL, 'freshwater', 'active', '2025-08-14 07:47:43', 1),
(7, 'Bangus', 'Chanos chanos', NULL, NULL, 'brackish', 'active', '2025-08-14 07:47:43', 3),
(8, 'Catfish', 'Clarias gariepinus', NULL, NULL, 'freshwater', 'active', '2025-08-14 07:47:43', 1),
(9, 'Carp', 'Cyprinus carpio', NULL, NULL, 'freshwater', 'active', '2025-08-14 07:47:43', 1);

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `business_name` varchar(255) NOT NULL,
  `owner_name` varchar(255) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `business_address` text DEFAULT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `business_permit` varchar(255) DEFAULT NULL,
  `certifications` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `rating` decimal(3,2) DEFAULT 0.00,
  `total_ratings` int(11) DEFAULT 0,
  `status` enum('pending','approved','rejected','suspended') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `user_id`, `business_name`, `owner_name`, `contact_number`, `business_address`, `barangay`, `city`, `province`, `latitude`, `longitude`, `business_permit`, `certifications`, `description`, `rating`, `total_ratings`, `status`, `created_at`, `updated_at`) VALUES
(1, 3, 'Manila Bay Aquaculture', 'Juan Dela Cruz', '09171234567', '123 Roxas Blvd', 'Poblacion', 'Laguna', 'Laguna', 14.26910000, 121.41130000, NULL, NULL, 'Premium quality tilapia and bangus fingerlings with over 10 years of experience.', 4.50, 25, 'approved', '2025-08-14 07:47:43', '2025-08-14 09:08:22'),
(2, 4, 'Laguna Fish Farm', 'Maria Santos', '09181234567', '456 National Highway', 'San Jose', 'Batangas City', 'Batangas', 13.75650000, 121.05830000, NULL, NULL, 'Specialized in marine fish fingerlings and aquaculture consulting.', 4.20, 18, 'approved', '2025-08-14 07:47:43', '2025-08-14 09:08:22'),
(3, 5, 'Batangas Fingerlings Co.', 'Pedro Reyes', '09191234567', '789 Coastal Road', 'Bauan', 'Batangas City', 'Batangas', 13.75650000, 121.05830000, NULL, NULL, NULL, 0.00, 0, 'approved', '2025-08-14 07:47:43', '2025-08-14 07:47:43'),
(6, 14, 'Demo Fish Farm', 'John Supplier', '+639123456789', '123 Fish Farm Road', 'Barangay Aquaculture', 'Dagupan', 'Pangasinan', 8.05487776, 123.69903208, 'BFAR-2024-001234', 'Good Aquaculture Practices (GAP) Certification', 'A demo supplier account for testing purposes. We specialize in high-quality fingerlings for aquaculture.', 4.50, 10, 'approved', '2025-08-14 12:57:01', '2025-08-14 13:20:01'),
(7, 15, 'FISHERY', 'jhulla', '09929292', 'LABUYO', 'LABUYO', 'OZAMIZ CITY', 'MISAMIS OCCIDENTAL', 8.06104610, 123.71925470, '', '', 'we sell fresh fish', 0.00, 0, 'pending', '2025-08-15 09:56:32', '2025-08-15 09:56:32');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `user_type` enum('customer','supplier','admin') NOT NULL,
  `google_id` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','pending','rejected') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password_hash`, `user_type`, `google_id`, `status`, `created_at`, `updated_at`) VALUES
(1, 'admin@fingerling.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NULL, 'active', '2025-08-12 14:01:19', '2025-08-12 14:01:19'),
(2, 'chem@gmail.com', '$2y$10$dZCOukFf5EvquYZrvhohpeWIsZuaHTpTg3PxW4FtzebejlMK9sHFe', 'customer', NULL, 'active', '2025-08-12 14:04:18', '2025-08-12 14:04:18'),
(3, 'manila.bay@example.com', '$2y$10$9uEdpfNI4BaIZ77P9X2HEuXvCMnH7F7olK9dcGVk80SN1WtM12HMy', 'supplier', NULL, 'active', '2025-08-14 07:47:43', '2025-08-14 07:47:43'),
(4, 'laguna.fish@example.com', '$2y$10$yk2tPP1ghFqsXzq.8wBfROxOw1zrkjUvt9KzsX/SGHkPc/wk9RHBe', 'supplier', NULL, 'active', '2025-08-14 07:47:43', '2025-08-14 07:47:43'),
(5, 'batangas.fingerlings@example.com', '$2y$10$NlD1n80w88M4zyerP5vrzuEiGUJH1Vp9VY7WfuMrizk1ZtYoJAcBm', 'supplier', NULL, 'active', '2025-08-14 07:47:43', '2025-08-14 07:47:43'),
(8, 'admin@gmail.com', '$2y$10$OW5QBXuEps8k.fev/xLkNOgwLw3GZeIEBYnW9TfiTqGGNZa/9tgle', 'admin', NULL, 'active', '2025-08-14 08:41:50', '2025-08-14 08:44:52'),
(14, 'supplier@gmail.com', '$2y$10$tIXAtHKPofCNwGcVJfai9Ouv8f8Aidbpuz5EAQNPiCZEby77j0t42', 'supplier', NULL, 'active', '2025-08-14 12:57:01', '2025-08-14 12:57:01'),
(15, 'jhulla@gmail.com', '$2y$10$royLQyRIGgNVTZIu/LKuqOPyjN0Y2Sqqj06d1dowg44YpRj3RJANq', 'supplier', NULL, 'pending', '2025-08-15 09:56:32', '2025-08-15 09:56:32');

-- --------------------------------------------------------

--
-- Table structure for table `user_notification_settings`
--

CREATE TABLE `user_notification_settings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `email_orders` tinyint(1) DEFAULT 1,
  `email_promotions` tinyint(1) DEFAULT 0,
  `sms_orders` tinyint(1) DEFAULT 0,
  `sms_promotions` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` varchar(128) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_sessions`
--

INSERT INTO `user_sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `created_at`, `expires_at`) VALUES
('8ee3f439245aafa062a61fe40966e7095e1f61783fe3aca6b185a0370edacbdb', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-14 08:44:57', '2025-08-14 09:44:57'),
('9778699458e4ae9eb3dc8bc079a853ebc0ca49b011853ae38694adaebd2101db', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-15 09:57:02', '2025-08-15 10:57:02'),
('c5d6c0219bb1bbb57717b1991a502690921d3be83f20bcd797d9c81915abf0d4', 14, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-14 12:57:01', '2025-08-14 13:57:01'),
('facc4bc9f179d48c00a861d5ba4263a94ec248ac3364b2d1b680b51738c98b79', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-14 14:50:27', '2025-08-14 15:50:27');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_customer_product` (`customer_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `customer_addresses`
--
ALTER TABLE `customer_addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_order_feedback` (`order_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_supplier_species_size` (`supplier_id`,`species_id`,`size_category`),
  ADD KEY `species_id` (`species_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD UNIQUE KEY `tracking_number` (`tracking_number`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `inventory_id` (`inventory_id`);

--
-- Indexes for table `order_tracking`
--
ALTER TABLE `order_tracking`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `refunds`
--
ALTER TABLE `refunds`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `species`
--
ALTER TABLE `species`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_species_category` (`category_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `google_id` (`google_id`);

--
-- Indexes for table `user_notification_settings`
--
ALTER TABLE `user_notification_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_settings` (`user_id`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `customer_addresses`
--
ALTER TABLE `customer_addresses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_tracking`
--
ALTER TABLE `order_tracking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `refunds`
--
ALTER TABLE `refunds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `species`
--
ALTER TABLE `species`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `user_notification_settings`
--
ALTER TABLE `user_notification_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admins`
--
ALTER TABLE `admins`
  ADD CONSTRAINT `admins_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `inventory` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `customers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customer_addresses`
--
ALTER TABLE `customer_addresses`
  ADD CONSTRAINT `customer_addresses_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `feedback_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `feedback_ibfk_3` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_ibfk_2` FOREIGN KEY (`species_id`) REFERENCES `species` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_tracking`
--
ALTER TABLE `order_tracking`
  ADD CONSTRAINT `order_tracking_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `refunds`
--
ALTER TABLE `refunds`
  ADD CONSTRAINT `refunds_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `species`
--
ALTER TABLE `species`
  ADD CONSTRAINT `fk_species_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD CONSTRAINT `suppliers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_notification_settings`
--
ALTER TABLE `user_notification_settings`
  ADD CONSTRAINT `user_notification_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
