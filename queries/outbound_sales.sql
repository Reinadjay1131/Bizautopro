-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 14, 2025 at 04:58 PM
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
(3, 2, 'Business Laptop', 'LP2001', 2, 799.99, 1, '2025-07-14 09:41:18'),
(4, 5, 'Wireless Keyboard', 'KB-WRLS', 6, 49.99, 1, '2025-07-14 13:41:42'),
(5, 1, 'Premium Laptop', 'LP1001', 2, 999.99, 1, '2025-07-14 13:41:42');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `outbound_sales`
--
ALTER TABLE `outbound_sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `outbound_sales`
--
ALTER TABLE `outbound_sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `outbound_sales`
--
ALTER TABLE `outbound_sales`
  ADD CONSTRAINT `outbound_sales_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory` (`id`),
  ADD CONSTRAINT `outbound_sales_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
