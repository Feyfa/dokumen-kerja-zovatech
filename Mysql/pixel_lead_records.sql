-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:8889
-- Generation Time: Nov 04, 2025 at 05:56 PM
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
-- Table structure for table `pixel_lead_records`
--

CREATE TABLE `pixel_lead_records` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `leadspeek_api_id` varchar(20) DEFAULT NULL,
  `lead_fire` smallint(6) DEFAULT '0',
  `md5_list` text,
  `visitor_id` varchar(100) DEFAULT NULL,
  `visitor_data` text,
  `function` varchar(100) DEFAULT NULL,
  `url` text,
  `ip_address` varchar(50) DEFAULT NULL,
  `date` datetime DEFAULT NULL,
  `timezone` varchar(50) DEFAULT NULL,
  `screen_width` smallint(5) UNSIGNED DEFAULT NULL,
  `screen_height` smallint(5) UNSIGNED DEFAULT NULL,
  `viewport_width` smallint(5) UNSIGNED DEFAULT NULL,
  `viewport_height` smallint(5) UNSIGNED DEFAULT NULL,
  `pixel_ratio` float DEFAULT NULL,
  `device_type` varchar(50) DEFAULT NULL,
  `campaign_status` enum('stopped','paused','paused_on_run','running') DEFAULT 'stopped',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `pixel_lead_records`
--
ALTER TABLE `pixel_lead_records`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `pixel_lead_records`
--
ALTER TABLE `pixel_lead_records`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
