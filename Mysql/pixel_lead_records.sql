-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Nov 04, 2025 at 12:58 PM
-- Server version: 8.3.0
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

DROP TABLE IF EXISTS `pixel_lead_records`;
CREATE TABLE IF NOT EXISTS `pixel_lead_records` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `leadspeek_api_id` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `visitor_id` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `visitor_data` text COLLATE utf8mb4_general_ci,
  `function` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `ip_address` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `date` datetime DEFAULT NULL,
  `timezone` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `screen_width` smallint UNSIGNED DEFAULT NULL,
  `screen_height` smallint UNSIGNED DEFAULT NULL,
  `viewport_width` smallint UNSIGNED DEFAULT NULL,
  `viewport_height` smallint UNSIGNED DEFAULT NULL,
  `pixel_ratio` float DEFAULT NULL,
  `device_type` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `campaign_status` enum('stopped','paused','paused_on_run','running') COLLATE utf8mb4_general_ci DEFAULT 'stopped',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
