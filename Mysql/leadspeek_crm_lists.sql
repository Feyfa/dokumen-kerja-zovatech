-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:8889
-- Generation Time: Oct 08, 2025 at 04:42 PM
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
-- Table structure for table `leadspeek_crm_lists`
--

CREATE TABLE `leadspeek_crm_lists` (
  `id` bigint(20) NOT NULL,
  `slug` varchar(50) DEFAULT NULL,
  `name` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `leadspeek_crm_lists`
--

INSERT INTO `leadspeek_crm_lists` (`id`, `slug`, `name`, `created_at`, `updated_at`) VALUES
(1, 'hubspot', 'HubSpot', '2025-10-08 16:36:18', '2025-10-08 16:36:18'),
(2, 'salesforce', 'Salesforce', '2025-10-08 16:36:18', '2025-10-08 16:36:18'),
(3, 'zoho_crm', 'Zoho CRM', '2025-10-08 16:36:18', '2025-10-08 16:36:18'),
(4, 'pipedrive', 'Pipedrive', '2025-10-08 16:36:18', '2025-10-08 16:36:18'),
(5, 'freshsales', 'Freshsales', '2025-10-08 16:36:18', '2025-10-08 16:36:18'),
(6, 'copper', 'Copper', '2025-10-08 16:36:18', '2025-10-08 16:36:18'),
(7, 'insightly', 'Insightly', '2025-10-08 16:36:18', '2025-10-08 16:36:18'),
(8, 'keap_infusionsoft', 'Keap (Infusionsoft)', '2025-10-08 16:36:18', '2025-10-08 16:36:18'),
(9, 'activecampaign', 'ActiveCampaign', '2025-10-08 16:36:18', '2025-10-08 16:36:18'),
(10, 'mailchimp', 'Mailchimp', '2025-10-08 16:36:18', '2025-10-08 16:36:18'),
(11, 'close', 'Close', '2025-10-08 16:36:18', '2025-10-08 16:36:18'),
(12, 'gohighlevel', 'GoHighLevel', '2025-10-08 16:36:18', '2025-10-08 16:36:18'),
(13, 'monday_sales_crm', 'Monday Sales CRM', '2025-10-08 16:36:18', '2025-10-08 16:36:18'),
(14, 'nutshell', 'Nutshell', '2025-10-08 16:36:18', '2025-10-08 16:36:18'),
(15, 'sugarcrm', 'SugarCRM', '2025-10-08 16:36:18', '2025-10-08 16:36:18');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `leadspeek_crm_lists`
--
ALTER TABLE `leadspeek_crm_lists`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `leadspeek_crm_lists`
--
ALTER TABLE `leadspeek_crm_lists`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
