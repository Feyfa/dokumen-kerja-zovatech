const result = "9,998.50" * 50;
console.log(result);


CREATE TABLE `user_agreements` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `company_id` BIGINT UNSIGNED NULL,
  `document_type` VARCHAR(100) NOT NULL,
  `agreement_file_id` BIGINT UNSIGNED NOT NULL,
  `agreed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` VARCHAR(64) NULL,
  `source` VARCHAR(50) NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_agreement_version` (`user_id`, `document_type`, `agreement_file_id`),
  KEY `idx_user_agreements_user` (`user_id`),
  KEY `idx_user_agreements_company` (`company_id`),
  KEY `idx_user_agreements_document` (`document_type`),
  KEY `idx_user_agreements_file` (`agreement_file_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;