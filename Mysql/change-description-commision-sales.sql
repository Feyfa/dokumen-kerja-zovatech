use emm_sandbox;
SET GLOBAL sql_mode = '';

set @salt := '8e651522e38256f2';


set @leadspeek_api_id := '29484235';
select * from topup_campaigns where leadspeek_api_id = @leadspeek_api_id order by created_at asc;
select * from leadspeek_invoices where leadspeek_api_id = @leadspeek_api_id order by created_at asc;
select * from leadspeek_reports where leadspeek_api_id = @leadspeek_api_id;

set @leadspeek_api_id2 := '38625712';
select active, disabled, active_user, leadspeek_users.* from leadspeek_users where leadspeek_api_id = @leadspeek_api_id2;
select * from leadspeek_reports where leadspeek_api_id = @leadspeek_api_id2;
select * from leadspeek_invoices where leadspeek_api_id = @leadspeek_api_id2;

set @leadspeek_api_id3 := '36498979';
select active, disabled, active_user, leadspeek_users.* from leadspeek_users where leadspeek_api_id = @leadspeek_api_id3;
select * from topup_campaigns where leadspeek_api_id = @leadspeek_api_id3 order by created_at asc;
select * from leadspeek_reports where leadspeek_api_id = @leadspeek_api_id3;
select * from leadspeek_invoices where leadspeek_api_id = @leadspeek_api_id3;

set @leadspeek_api_id4 := '73125285';
select active, disabled, active_user, leadspeek_users.* from leadspeek_users where leadspeek_api_id = @leadspeek_api_id4;
select * from topup_campaigns where leadspeek_api_id = @leadspeek_api_id4 order by created_at asc;
select * from leadspeek_reports where leadspeek_api_id = @leadspeek_api_id4;
select * from leadspeek_invoices where leadspeek_api_id = @leadspeek_api_id4;

SELECT 
    CONVERT(AES_DECRYPT(FROM_BASE64(users.custom_commission),  @salt) USING utf8mb4) AS custom_commission,
    CONVERT(AES_DECRYPT(FROM_BASE64(users.custom_commission_fixed), @salt) USING utf8mb4) AS custom_commission_fixed,
    users.custom_commission_enabled,
    users.custom_commission_type,
    company_sales.id,
    company_sales.sales_id,
    company_sales.sales_title,
    users.company_root_id,
    CONVERT(AES_DECRYPT(FROM_BASE64(companies.company_name), @salt) USING utf8mb4) AS company_name,
    CONVERT(AES_DECRYPT(FROM_BASE64(users.acc_connect_id), @salt) USING utf8mb4) AS accconnectid,
    CONVERT(AES_DECRYPT(FROM_BASE64(users.name), @salt) USING utf8mb4) AS name,
    CONVERT(AES_DECRYPT(FROM_BASE64(users.email), @salt) USING utf8mb4) AS email
FROM company_sales
JOIN users ON company_sales.sales_id = users.id
JOIN companies ON company_sales.company_id = companies.id
WHERE 
	company_sales.company_id = 164
	AND users.active = 'T'
  	AND users.user_type = 'sales'
  	AND users.status_acc = 'completed';
select * from company_sales where company_id = 164;


SET @company_id := 164;
SELECT * FROM `topup_agencies` where company_id = @company_id order by id asc;
SELECT * FROM `leadspeek_invoices` where invoice_type = 'agency' and company_id = @company_id order by id asc;

SET @company_id2 := 464;
SELECT * FROM `topup_agencies` where company_id = @company_id2 order by id asc;
SELECT * FROM `leadspeek_invoices` where invoice_type = 'agency' and company_id = @company_id2 order by id asc;

select * from users where company_id = @company_id2;

-- DATABASE YANG DITAMBAH
ALTER TABLE `leadspeek_invoices`
ADD COLUMN `sr_transfer_id` VARCHAR(100) DEFAULT NULL AFTER `sr_fee`,
ADD COLUMN `ae_transfer_id` VARCHAR(100) DEFAULT NULL AFTER `ae_fee`,
ADD COLUMN `ar_transfer_id` VARCHAR(100) DEFAULT NULL AFTER `ar_fee`;
-- DATABASE YANG DITAMBAH

select * from leadspeek_users where company_id = @company_id2;

select * from users where company_id = 164 and user_type = 'userdownline';
select * from jobs;
select DATE_ADD('2025-10-21 11:32:40', interval 1 month) as nex_month;


select * from module_settings;



