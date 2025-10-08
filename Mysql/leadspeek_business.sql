-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:8889
-- Generation Time: Oct 08, 2025 at 04:40 PM
-- Server version: 5.7.39
-- PHP Version: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4_general_ci */;

--
-- Database: `emm_sandbox`
--

-- --------------------------------------------------------

--
-- Table structure for table `leadspeek_business`
--

CREATE TABLE `leadspeek_business` (
  `id` bigint(20) NOT NULL,
  `leadspeek_api_id` varchar(20) CHARACTER SET utf8mb4_general_ci DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `business_type` enum('b2c','b2b') DEFAULT 'b2c',
  `business_name` varchar(255) DEFAULT NULL,
  `business_id` tinyint(3) UNSIGNED DEFAULT NULL,
  `business_specify` varchar(255) DEFAULT NULL,
  `business_description` text,
  `business_competitors` text,
  `upload_customer_list` varchar(50) DEFAULT NULL,
  `crm_id` tinyint(3) UNSIGNED DEFAULT NULL,
  `crm_key` text,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `leadspeek_business`
--
ALTER TABLE `leadspeek_business`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `leadspeek_business`
--
ALTER TABLE `leadspeek_business`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
