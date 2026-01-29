-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jan 28, 2026 at 12:31 PM
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
-- Table structure for table `leadspeek_invoices`
--

DROP TABLE IF EXISTS `leadspeek_invoices`;
CREATE TABLE IF NOT EXISTS `leadspeek_invoices` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `invoice_type` enum('campaign','agency','clean_id','campaign_cost_month','agency_subscription') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'campaign',
  `topup_agencies_id` bigint DEFAULT NULL,
  `topup_campaigns_id` bigint DEFAULT NULL,
  `budget_plan_id` varchar(20) DEFAULT NULL,
  `clean_file_id` bigint DEFAULT NULL,
  `payment_type` enum('credit_card','bank_account','refund_campaign','minimum_spend') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'credit_card',
  `company_id` bigint NOT NULL,
  `user_id` bigint NOT NULL,
  `leadspeek_api_id` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `subscription_type` enum('initial','renewal') DEFAULT NULL,
  `subscription_details` text,
  `invoice_number` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `payment_term` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `onetimefee` double DEFAULT '0',
  `platform_onetimefee` double DEFAULT '0',
  `min_leads` int DEFAULT NULL,
  `exceed_leads` int DEFAULT NULL,
  `total_leads` int DEFAULT NULL,
  `min_cost` double DEFAULT NULL,
  `platform_min_cost` double DEFAULT '0',
  `cost_leads` double DEFAULT NULL,
  `platform_cost_leads` double DEFAULT '0',
  `frequency_capping_impressions` tinyint UNSIGNED DEFAULT '0',
  `frequency_capping_hours` tinyint UNSIGNED DEFAULT '0',
  `max_bid` float DEFAULT '0',
  `monthly_budget` float DEFAULT '0',
  `daily_budget` float DEFAULT '0',
  `goal_type` enum('none','cpc','ctr','cpa','') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `goal_value` float DEFAULT '0',
  `agency_markup` int UNSIGNED DEFAULT '0',
  `total_amount` double DEFAULT NULL,
  `platform_total_amount` double DEFAULT '0',
  `root_total_amount` double DEFAULT '0',
  `status` enum('paid','pending','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `status_ach` enum('paid','pending','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `customer_payment_id` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `customer_stripe_id` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `customer_card_id` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `platform_customer_payment_id` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `error_payment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `error_ach_payment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `platform_error_payment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `invoice_date` date NOT NULL,
  `invoice_start` date NOT NULL,
  `invoice_end` date NOT NULL,
  `sent_to` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `sr_id` int NOT NULL DEFAULT '0',
  `sr_fee` double NOT NULL DEFAULT '0',
  `sr_transfer_id` varchar(100) DEFAULT NULL,
  `ae_id` int NOT NULL DEFAULT '0',
  `ae_fee` double NOT NULL DEFAULT '0',
  `ae_transfer_id` varchar(100) DEFAULT NULL,
  `ar_id` int NOT NULL DEFAULT '0',
  `ar_fee` double NOT NULL DEFAULT '0',
  `ar_transfer_id` varchar(100) DEFAULT NULL,
  `campaigns_paused` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `active` enum('T','F') NOT NULL DEFAULT 'T',
  `updated_at` timestamp NOT NULL,
  `created_at` timestamp NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3579 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `leadspeek_invoices`
--

INSERT INTO `leadspeek_invoices` (`invoice_type`, `topup_agencies_id`, `topup_campaigns_id`, `budget_plan_id`, `clean_file_id`, `payment_type`, `company_id`, `user_id`, `leadspeek_api_id`, `subscription_type`, `subscription_details`, `invoice_number`, `payment_term`, `onetimefee`, `platform_onetimefee`, `min_leads`, `exceed_leads`, `total_leads`, `min_cost`, `platform_min_cost`, `cost_leads`, `platform_cost_leads`, `frequency_capping_impressions`, `frequency_capping_hours`, `max_bid`, `monthly_budget`, `daily_budget`, `goal_type`, `goal_value`, `agency_markup`, `total_amount`, `platform_total_amount`, `root_total_amount`, `status`, `status_ach`, `customer_payment_id`, `customer_stripe_id`, `customer_card_id`, `platform_customer_payment_id`, `error_payment`, `error_ach_payment`, `platform_error_payment`, `invoice_date`, `invoice_start`, `invoice_end`, `sent_to`, `sr_id`, `sr_fee`, `sr_transfer_id`, `ae_id`, `ae_fee`, `ae_transfer_id`, `ar_id`, `ar_fee`, `ar_transfer_id`, `campaigns_paused`, `active`, `updated_at`, `created_at`) VALUES
('agency', 478, NULL, NULL, NULL, 'credit_card', 164, 279, '3577', 'renewal', '[{\"module\":\"predict\",\"price\":300,\"quota\":1000}]', '20260128 - 3572', '', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 0, 0, 0, 250, 0, 'paid', NULL, '', 'cus_QxbuvRUbFWqHeA', 'card_1SrsNTRwyPH0xNSi9lyQ76ks', 'pi_3SuUGCRwyPH0xNSi0e3PqMQK', '', NULL, '', '2026-01-28', '2026-01-28', '2026-01-28', 'fisikamodern00+jidanach@gmail.com', 0, 0, '', 0, 0, '', 0, 0, '', NULL, 'T', '2026-01-28 10:10:11', '2026-01-28 10:10:11'),
('agency_subscription', 478, NULL, NULL, NULL, 'credit_card', 164, 279, '', 'renewal', '[{\"module\":\"predict\",\"price\":300,\"quota\":1000}]', '20260128 - 3572', '', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 0, 0, 0, 250, 0, 'paid', NULL, '', 'cus_QxbuvRUbFWqHeA', 'card_1SrsNTRwyPH0xNSi9lyQ76ks', 'pi_3SuUGCRwyPH0xNSi0e3PqMQK', '', NULL, '', '2026-01-28', '2026-01-28', '2026-01-28', 'fisikamodern00+jidanach@gmail.com', 0, 0, '', 0, 0, '', 0, 0, '', NULL, 'T', '2026-01-28 10:44:48', '2026-01-28 10:44:48'),
('agency_subscription', 478, NULL, NULL, NULL, 'credit_card', 164, 279, '', 'initial', '[{\"module\":\"predict\",\"price\":300,\"quota\":1000},{\"module\":\"clean\",\"price\":149,\"quota\":1000},{\"module\":\"audience\",\"price\":149,\"quota\":1000}]', '20260128 - 3572', '', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, 0, 0, 0, 250, 0, 'paid', NULL, '', 'cus_QxbuvRUbFWqHeA', 'card_1SrsNTRwyPH0xNSi9lyQ76ks', 'pi_3SuUGCRwyPH0xNSi0e3PqMQK', '', NULL, '', '2026-01-28', '2026-01-28', '2026-01-28', 'fisikamodern00+jidanach@gmail.com', 0, 0, '', 0, 0, '', 0, 0, '', NULL, 'T', '2026-01-28 10:05:33', '2026-01-28 10:05:33');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
