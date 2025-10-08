-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:8889
-- Generation Time: Oct 08, 2025 at 04:41 PM
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
-- Table structure for table `leadspeek_business_lists`
--

CREATE TABLE `leadspeek_business_lists` (
  `id` bigint(20) NOT NULL,
  `slug` varchar(50) DEFAULT NULL,
  `name` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `leadspeek_business_lists`
--

INSERT INTO `leadspeek_business_lists` (`id`, `slug`, `name`, `created_at`, `updated_at`) VALUES
(1, 'advertising_and_marketing', 'Advertising & Marketing', '2025-10-08 16:32:14', '2025-10-08 16:32:14'),
(2, 'aerospace_and_defense', 'Aerospace & Defense', '2025-10-08 16:32:14', '2025-10-08 16:32:14'),
(3, 'agriculture', 'Agriculture', '2025-10-08 16:32:14', '2025-10-08 16:32:14'),
(4, 'automotive', 'Automotive', '2025-10-08 16:32:14', '2025-10-08 16:32:14'),
(5, 'biotech_and_life_sciences', 'Biotech & Life Sciences', '2025-10-08 16:32:14', '2025-10-08 16:32:14'),
(6, 'business_services', 'Business Services', '2025-10-08 16:32:14', '2025-10-08 16:32:14'),
(7, 'construction', 'Construction', '2025-10-08 16:32:14', '2025-10-08 16:32:14'),
(8, 'consumer_goods', 'Consumer Goods', '2025-10-08 16:32:14', '2025-10-08 16:32:14'),
(9, 'consulting', 'Consulting', '2025-10-08 16:32:14', '2025-10-08 16:32:14'),
(10, 'education', 'Education', '2025-10-08 16:32:14', '2025-10-08 16:32:14'),
(11, 'energy_and_utilities', 'Energy & Utilities', '2025-10-08 16:32:14', '2025-10-08 16:32:14'),
(12, 'engineering', 'Engineering', '2025-10-08 16:32:14', '2025-10-08 16:32:14'),
(13, 'entertainment_and_media', 'Entertainment & Media', '2025-10-08 16:32:14', '2025-10-08 16:32:14'),
(14, 'financial_services', 'Financial Services', '2025-10-08 16:32:14', '2025-10-08 16:32:14'),
(15, 'food_and_beverage', 'Food & Beverage', '2025-10-08 16:32:14', '2025-10-08 16:32:14'),
(16, 'government_and_public_sector', 'Government & Public Sector', '2025-10-08 16:32:14', '2025-10-08 16:32:14'),
(17, 'healthcare_and_medical', 'Healthcare & Medical', '2025-10-08 16:32:14', '2025-10-08 16:32:14'),
(18, 'hospitality_and_travel', 'Hospitality & Travel', '2025-10-08 16:32:14', '2025-10-08 16:32:14'),
(19, 'insurance', 'Insurance', '2025-10-08 16:32:14', '2025-10-08 16:32:14'),
(20, 'internet_and_software', 'Internet & Software', '2025-10-08 16:32:14', '2025-10-08 16:32:14'),
(21, 'it_and_managed_services', 'IT & Managed Services', '2025-10-08 16:32:14', '2025-10-08 16:32:14'),
(22, 'legal', 'Legal', '2025-10-08 16:32:14', '2025-10-08 16:32:14'),
(23, 'logistics_and_supply_chain', 'Logistics & Supply Chain', '2025-10-08 16:32:14', '2025-10-08 16:32:14'),
(24, 'manufacturing', 'Manufacturing', '2025-10-08 16:32:14', '2025-10-08 16:32:14'),
(25, 'nonprofit_and_ngos', 'Nonprofit & NGOs', '2025-10-08 16:32:14', '2025-10-08 16:32:14'),
(26, 'pharmaceuticals', 'Pharmaceuticals', '2025-10-08 16:32:14', '2025-10-08 16:32:14'),
(27, 'real_estate', 'Real Estate', '2025-10-08 16:32:14', '2025-10-08 16:32:14'),
(28, 'restaurants', 'Restaurants', '2025-10-08 16:32:14', '2025-10-08 16:32:14'),
(29, 'retail_and_ecommerce', 'Retail & Ecommerce', '2025-10-08 16:32:14', '2025-10-08 16:32:14'),
(30, 'telecommunications', 'Telecommunications', '2025-10-08 16:32:14', '2025-10-08 16:32:14'),
(31, 'transportation', 'Transportation', '2025-10-08 16:32:14', '2025-10-08 16:32:14'),
(32, 'wholesale_and_distribution', 'Wholesale & Distribution', '2025-10-08 16:32:14', '2025-10-08 16:32:14');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `leadspeek_business_lists`
--
ALTER TABLE `leadspeek_business_lists`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `leadspeek_business_lists`
--
ALTER TABLE `leadspeek_business_lists`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
