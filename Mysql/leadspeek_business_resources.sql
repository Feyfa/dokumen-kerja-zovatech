-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:8889
-- Generation Time: Oct 10, 2025 at 03:04 AM
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
-- Table structure for table `leadspeek_business_resources`
--

CREATE TABLE `leadspeek_business_resources` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `active` tinyint(3) UNSIGNED DEFAULT '1',
  `type` enum('industry','crm','campaign_type','customer_list_source') DEFAULT NULL,
  `slug` varchar(50) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `label` varchar(255) DEFAULT NULL,
  `description` text,
  `price` float DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `leadspeek_business_resources`
--

INSERT INTO `leadspeek_business_resources` (`id`, `active`, `type`, `slug`, `name`, `label`, `description`, `price`, `created_at`, `updated_at`) VALUES
(1, 1, 'industry', 'other', 'Other', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(2, 1, 'industry', 'advertising_and_marketing', 'Advertising & Marketing', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(3, 1, 'industry', 'aerospace_and_defense', 'Aerospace & Defense', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(4, 1, 'industry', 'agriculture', 'Agriculture', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(5, 1, 'industry', 'automotive', 'Automotive', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(6, 1, 'industry', 'biotech_and_life_sciences', 'Biotech & Life Sciences', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(7, 1, 'industry', 'business_services', 'Business Services', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(8, 1, 'industry', 'construction', 'Construction', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(9, 1, 'industry', 'consumer_goods', 'Consumer Goods', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(10, 1, 'industry', 'consulting', 'Consulting', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(11, 1, 'industry', 'education', 'Education', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(12, 1, 'industry', 'energy_and_utilities', 'Energy & Utilities', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(13, 1, 'industry', 'engineering', 'Engineering', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(14, 1, 'industry', 'entertainment_and_media', 'Entertainment & Media', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(15, 1, 'industry', 'financial_services', 'Financial Services', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(16, 1, 'industry', 'food_and_beverage', 'Food & Beverage', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(17, 1, 'industry', 'government_and_public_sector', 'Government & Public Sector', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(18, 1, 'industry', 'healthcare_and_medical', 'Healthcare & Medical', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(19, 1, 'industry', 'hospitality_and_travel', 'Hospitality & Travel', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(20, 1, 'industry', 'insurance', 'Insurance', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(21, 1, 'industry', 'internet_and_software', 'Internet & Software', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(22, 1, 'industry', 'it_and_managed_services', 'IT & Managed Services', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(23, 1, 'industry', 'legal', 'Legal', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(24, 1, 'industry', 'logistics_and_supply_chain', 'Logistics & Supply Chain', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(25, 1, 'industry', 'manufacturing', 'Manufacturing', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(26, 1, 'industry', 'nonprofit_and_ngos', 'Nonprofit & NGOs', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(27, 1, 'industry', 'pharmaceuticals', 'Pharmaceuticals', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(28, 1, 'industry', 'real_estate', 'Real Estate', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(29, 1, 'industry', 'restaurants', 'Restaurants', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(30, 1, 'industry', 'retail_and_ecommerce', 'Retail & Ecommerce', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(31, 1, 'industry', 'telecommunications', 'Telecommunications', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(32, 1, 'industry', 'transportation', 'Transportation', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(33, 1, 'industry', 'wholesale_and_distribution', 'Wholesale & Distribution', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(34, 1, 'crm', 'hubspot', 'HubSpot', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(35, 1, 'crm', 'salesforce', 'Salesforce', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(36, 1, 'crm', 'zoho_crm', 'Zoho CRM', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(37, 1, 'crm', 'pipedrive', 'Pipedrive', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(38, 1, 'crm', 'freshsales', 'Freshsales', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(39, 1, 'crm', 'copper', 'Copper', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(40, 1, 'crm', 'insightly', 'Insightly', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(41, 1, 'crm', 'keap_infusionsoft', 'Keap (Infusionsoft)', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(42, 1, 'crm', 'activecampaign', 'ActiveCampaign', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(43, 1, 'crm', 'mailchimp', 'Mailchimp', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(44, 1, 'crm', 'close', 'Close', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(45, 1, 'crm', 'gohighlevel', 'GoHighLevel', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(46, 1, 'crm', 'monday_sales_crm', 'Monday Sales CRM', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(47, 1, 'crm', 'nutshell', 'Nutshell', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(48, 1, 'crm', 'sugarcrm', 'SugarCRM', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(49, 1, 'campaign_type', 'b2c', 'B2C', 'Individual Consumers ($349 per month)', 'Includes an Ideal Customer Profile of your target customers, plus daily updates on individuals that match it. (B2C)', 349, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(50, 1, 'campaign_type', 'b2b', 'B2B', 'Business Customers ($899 per month)', 'Includes an Ideal Customer Profile of your target businesses, plus daily updates on companies that match it. (B2B)', 899, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(51, 1, 'customer_list_source', 'local', 'Local List', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34'),
(52, 1, 'customer_list_source', 'crm', 'CRM', NULL, NULL, NULL, '2025-10-09 13:54:34', '2025-10-09 13:54:34');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `leadspeek_business_resources`
--
ALTER TABLE `leadspeek_business_resources`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `leadspeek_business_resources`
--
ALTER TABLE `leadspeek_business_resources`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
