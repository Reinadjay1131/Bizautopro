-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 14, 2025 at 11:48 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bizautopro`
--

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `sku` varchar(50) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `reorder_level` int(11) NOT NULL DEFAULT 10,
  `price` decimal(10,2) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `product_name`, `sku`, `quantity`, `reorder_level`, `price`, `supplier_id`, `last_updated`) VALUES
(1, 'Premium Laptop', 'LP1001', 50, 10, 999.99, 1, '2025-07-14 09:38:48'),
(2, 'Business Laptop', 'LP2001', 27, 5, 799.99, 1, '2025-07-14 09:41:43'),
(3, '27\" 4K Monitor', 'MN2704K', 25, 5, 349.99, 3, '2025-07-14 09:38:48'),
(4, '32\" Curved Monitor', 'MN32CURVE', 15, 3, 499.99, 3, '2025-07-14 09:38:48'),
(5, 'Wireless Keyboard', 'KB-WRLS', 100, 20, 49.99, 2, '2025-07-14 09:38:48'),
(6, 'Ergonomic Mouse', 'MS-ERGO', 75, 15, 39.99, 2, '2025-07-14 09:38:48'),
(7, 'HDMI Cable 2m', 'CAB-HDMI2', 200, 50, 12.99, 5, '2025-07-14 09:38:48'),
(8, 'USB-C Cable 1m', 'CAB-USBC1', 150, 40, 9.99, 5, '2025-07-14 09:38:48'),
(9, '16GB RAM Module', 'MEM-16GB', 39, 10, 89.99, 4, '2025-07-14 09:41:18'),
(10, '1TB SSD', 'SSD-1TB', 33, 8, 129.99, 4, '2025-07-14 09:42:06'),
(11, 'Webcam HD', 'CAM-HD', 60, 12, 59.99, 2, '2025-07-14 09:38:48'),
(12, 'Noise Cancelling Headphones', 'HP-NOISE', 45, 10, 149.99, 1, '2025-07-14 09:38:48');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_transactions`
--

CREATE TABLE `inventory_transactions` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `type` enum('addition','deduction') NOT NULL,
  `reason` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_transactions`
--

INSERT INTO `inventory_transactions` (`id`, `item_id`, `user_id`, `quantity`, `price`, `type`, `reason`, `created_at`) VALUES
(1, 9, 1, 1, 89.99, '', '', '2025-07-14 10:41:18'),
(2, 10, 1, 1, 129.99, '', '', '2025-07-14 10:41:18'),
(3, 2, 1, 2, 799.99, '', '', '2025-07-14 10:41:18'),
(4, 2, 1, 1, 0.00, '', '', '2025-07-14 10:41:43'),
(5, 10, 1, 1, 0.00, '', '', '2025-07-14 10:42:06');

-- --------------------------------------------------------

--
-- Table structure for table `leads`
--

CREATE TABLE `leads` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `company` varchar(255) DEFAULT NULL,
  `source` enum('website','referral','social','event','cold_call') NOT NULL,
  `website` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('new','contacted','qualified','lost') NOT NULL DEFAULT 'new',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `assigned_to` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `score` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `outbound_damaged`
--

CREATE TABLE `outbound_damaged` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `sku` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `deduction_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `price` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `outbound_damaged`
--

INSERT INTO `outbound_damaged` (`id`, `item_id`, `product_name`, `sku`, `quantity`, `user_id`, `deduction_date`, `price`) VALUES
(1, 2, 'Business Laptop', 'LP2001', 1, 1, '2025-07-14 09:41:43', 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `outbound_internal`
--

CREATE TABLE `outbound_internal` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `sku` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `deduction_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `price` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `outbound_internal`
--

INSERT INTO `outbound_internal` (`id`, `item_id`, `product_name`, `sku`, `quantity`, `user_id`, `deduction_date`, `price`) VALUES
(1, 10, '1TB SSD', 'SSD-1TB', 1, 1, '2025-07-14 09:42:06', 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `outbound_sales`
--

CREATE TABLE `outbound_sales` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `sku` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `user_id` int(11) NOT NULL,
  `deduction_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `outbound_sales`
--

INSERT INTO `outbound_sales` (`id`, `item_id`, `product_name`, `sku`, `quantity`, `price`, `user_id`, `deduction_date`) VALUES
(1, 9, '16GB RAM Module', 'MEM-16GB', 1, 89.99, 1, '2025-07-14 09:41:18'),
(2, 10, '1TB SSD', 'SSD-1TB', 1, 129.99, 1, '2025-07-14 09:41:18'),
(3, 2, 'Business Laptop', 'LP2001', 2, 799.99, 1, '2025-07-14 09:41:18');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `contact_email` varchar(255) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `address` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `name`, `contact_email`, `phone`, `address`, `created_at`) VALUES
(1, 'TechGadgets Inc', 'sales@techgadgets.com', '+1-555-123-4567', '123 Tech Street, Silicon Valley', '2025-07-14 10:38:47'),
(2, 'Peripheral World', 'support@peripheralworld.com', '+1-555-987-6543', '456 Electronics Blvd, New York', '2025-07-14 10:38:47'),
(3, 'Monitor Masters', 'info@monitormasters.com', '+1-555-456-7890', '789 Display Avenue, Chicago', '2025-07-14 10:38:47'),
(4, 'Component King', 'orders@componentking.com', '+1-555-111-2222', '321 Circuit Road, Austin', '2025-07-14 10:38:47'),
(5, 'Cable Solutions', 'service@cablesolutions.com', '+1-555-333-4444', '159 Wire Lane, Boston', '2025-07-14 10:38:47');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `role` enum('admin','manager','employee','sales','inventory_manager') NOT NULL,
  `_created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `verification_token` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `role`, `_created_at`, `verification_token`, `status`) VALUES
(1, 'Fidelia', '$2y$10$SAYtrHB.tyhb5UyuOnBRD.zO/3k4xnhk/jQ2ujTeh5LKj3x5RqLfy', 'nobfundamental101@gmail.com', 'admin', '2025-07-14 10:08:19', NULL, 'approved'),
(2, 'Ebuka', '$2y$10$mIcQJhQRxdItURR9doWqiOuegsuZUR1mDder0Kd5p9JVGwfnDASRS', 'ibeachuhenry@gmail.com', 'manager', '2025-07-14 10:08:19', NULL, 'approved'),
(3, 'Emeka', '$2y$10$qbkmABaxXJoRrjbyvZk6F.tXd9DtR4SMQDnmqF4jSYCzBI94RdCqG', 'henryibeachu2008@gmail.com', 'employee', '2025-07-14 10:08:19', NULL, 'approved');

-- --------------------------------------------------------

--
-- Table structure for table `workflows`
--

CREATE TABLE `workflows` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected','completed','cancelled') NOT NULL DEFAULT 'pending',
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `workflow_history`
--

CREATE TABLE `workflow_history` (
  `id` int(11) NOT NULL,
  `workflow_id` int(11) NOT NULL,
  `lead_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `notes` text DEFAULT NULL,
  `timestamp` datetime NOT NULL DEFAULT current_timestamp(),
  `record_type` enum('workflow','lead') DEFAULT NULL,
  `new_status` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `leads`
--
ALTER TABLE `leads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `outbound_damaged`
--
ALTER TABLE `outbound_damaged`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `outbound_internal`
--
ALTER TABLE `outbound_internal`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `outbound_sales`
--
ALTER TABLE `outbound_sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `workflows`
--
ALTER TABLE `workflows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `workflow_history`
--
ALTER TABLE `workflow_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `workflow_id` (`workflow_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `lead_id` (`lead_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `leads`
--
ALTER TABLE `leads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `outbound_damaged`
--
ALTER TABLE `outbound_damaged`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `outbound_internal`
--
ALTER TABLE `outbound_internal`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `outbound_sales`
--
ALTER TABLE `outbound_sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `workflows`
--
ALTER TABLE `workflows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `workflow_history`
--
ALTER TABLE `workflow_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`);

--
-- Constraints for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD CONSTRAINT `inventory_transactions_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory` (`id`),
  ADD CONSTRAINT `inventory_transactions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `leads`
--
ALTER TABLE `leads`
  ADD CONSTRAINT `leads_ibfk_1` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `leads_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `outbound_damaged`
--
ALTER TABLE `outbound_damaged`
  ADD CONSTRAINT `outbound_damaged_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory` (`id`),
  ADD CONSTRAINT `outbound_damaged_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `outbound_internal`
--
ALTER TABLE `outbound_internal`
  ADD CONSTRAINT `outbound_internal_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory` (`id`),
  ADD CONSTRAINT `outbound_internal_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `outbound_sales`
--
ALTER TABLE `outbound_sales`
  ADD CONSTRAINT `outbound_sales_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory` (`id`),
  ADD CONSTRAINT `outbound_sales_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `workflows`
--
ALTER TABLE `workflows`
  ADD CONSTRAINT `workflows_ibfk_1` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `workflows_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `workflow_history`
--
ALTER TABLE `workflow_history`
  ADD CONSTRAINT `workflow_history_ibfk_1` FOREIGN KEY (`workflow_id`) REFERENCES `workflows` (`id`),
  ADD CONSTRAINT `workflow_history_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `workflow_history_ibfk_3` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
