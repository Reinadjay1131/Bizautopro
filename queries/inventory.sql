-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 15, 2025 at 11:42 PM
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
  `barcode` varchar(100) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `reorder_level` int(11) NOT NULL DEFAULT 10,
  `price` decimal(10,2) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `product_name`, `sku`, `barcode`, `quantity`, `reorder_level`, `price`, `supplier_id`, `last_updated`) VALUES
(1, 'Premium Laptop', 'LP1001', NULL, 48, 10, 999.99, 1, '2025-07-14 13:41:42'),
(2, 'Business Laptop', 'LP2001', NULL, 27, 5, 799.99, 1, '2025-07-14 09:41:43'),
(3, '27\" 4K Monitor', 'MN2704K', NULL, 25, 5, 349.99, 3, '2025-07-14 09:38:48'),
(4, '32\" Curved Monitor', 'MN32CURVE', NULL, 15, 3, 499.99, 3, '2025-07-14 09:38:48'),
(5, 'Wireless Keyboard', 'KB-WRLS', NULL, 94, 20, 49.99, 2, '2025-07-14 13:41:42'),
(6, 'Ergonomic Mouse', 'MS-ERGO', NULL, 75, 15, 39.99, 2, '2025-07-14 09:38:48'),
(7, 'HDMI Cable 2m', 'CAB-HDMI2', NULL, 200, 50, 12.99, 5, '2025-07-14 09:38:48'),
(8, 'USB-C Cable 1m', 'CAB-USBC1', NULL, 150, 40, 9.99, 5, '2025-07-14 09:38:48'),
(9, '16GB RAM Module', 'MEM-16GB', NULL, 39, 10, 89.99, 4, '2025-07-14 09:41:18'),
(10, '1TB SSD', 'SSD-1TB', NULL, 33, 8, 129.99, 4, '2025-07-14 09:42:06'),
(11, 'Webcam HD', 'CAM-HD', NULL, 60, 12, 59.99, 2, '2025-07-14 09:38:48'),
(12, 'Noise Cancelling Headphones', 'HP-NOISE', NULL, 45, 10, 149.99, 1, '2025-07-14 09:38:48');

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
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
