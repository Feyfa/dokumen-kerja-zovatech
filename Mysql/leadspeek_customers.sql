-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:8889
-- Generation Time: Oct 10, 2025 at 03:05 AM
-- Server version: 5.7.39
-- PHP Version: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `emm_sandbox`
--

-- --------------------------------------------------------

--
-- Table structure for table `leadspeek_customers`
--

CREATE TABLE `leadspeek_customers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `customer_list_name` varchar(255) DEFAULT NULL,
  `total_customer` int(10) UNSIGNED DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `size_file` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `leadspeek_customers`
--

INSERT INTO `leadspeek_customers` (`id`, `user_id`, `customer_list_name`, `total_customer`, `url`, `size_file`, `created_at`, `updated_at`) VALUES
(5, 282, 'test1', 3, 'https://emmbetaspaces.nyc3.cdn.digitaloceanspaces.com/users/media/customer_282_test1_1760058487841.csv', 147, '2025-10-09 13:08:09', '2025-10-09 13:08:09');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `leadspeek_customers`
--
ALTER TABLE `leadspeek_customers`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `leadspeek_customers`
--
ALTER TABLE `leadspeek_customers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
