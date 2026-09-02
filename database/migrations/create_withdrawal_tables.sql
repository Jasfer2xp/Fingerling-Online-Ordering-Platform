-- Create withdrawals table
CREATE TABLE `withdrawals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `method` enum('gcash','paypal') NOT NULL,
  `account_info` varchar(255) NOT NULL,
  `status` enum('pending','completed','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `supplier_id` (`supplier_id`),
  CONSTRAINT `withdrawals_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create withdrawal_appeals table
CREATE TABLE `withdrawal_appeals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `withdrawal_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `reason` text NOT NULL,
  `proof_file` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `withdrawal_id` (`withdrawal_id`),
  KEY `supplier_id` (`supplier_id`),
  CONSTRAINT `withdrawal_appeals_ibfk_1` FOREIGN KEY (`withdrawal_id`) REFERENCES `withdrawals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `withdrawal_appeals_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;