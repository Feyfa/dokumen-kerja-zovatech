select 
	lp_limit_leads,
	leadsbuy,
	lu.* 
from leadspeek_users as lu 
where leadspeek_api_id = '81309418';

select 
	* 
from leadspeek_invoices li 
where leadspeek_api_id = '81309418';


select * from open_api_tokens
where CONVERT(AES_DECRYPT(FROM_bASE64(token), '8e651522e38256f2') USING utf8mb4) in (
	'5fcbd3aadad072aacebfedfea04061a787f63a8fe2d3dddea719986ad2d4a26926037bcbe85701ed11ffcce983dcd8be8ec672ccff49abba1921c921e200da1a919038bee7a6915f0afe8887cc32521fe36fc32125b9e0a5887dc4'
);

SELECT users.* FROM users WHERE CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) = 'fisikamodern00@gmail.com';

CREATE TABLE subscription_modules (
	id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	company_id BIGINT UNSIGNED DEFAULT NULL,
	module ENUM('predict','clean','audience') DEFAULT NULL,
	status ENUM('active','canceled') DEFAULT NULL,
	first_billing_date DATETIME DEFAULT NULL,
	start_date DATETIME DEFAULT NULL,
	next_billing_date DATETIME DEFAULT NULL,
	created_at TIMESTAMP DEFAULT NULL,
	updated_at TIMESTAMP DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;