use emm_sandbox;
SET GLOBAL sql_mode = '';
--
select 
	CONVERT(AES_DECRYPT(FROM_bASE64(url_code), '8e651522e38256f2') USING utf8mb4) as url_code_dec, 
	url_code, 
	leadspeek_users.* 
from leadspeek_users 
where leadspeek_api_id = 83769694;
--
SELECT * FROM `leadspeek_audience_campaign`;
--
SELECT * FROM `leadspeek_media`;

-- select 
-- 	id,
-- 	leadspeek_api_id,
-- 	CONVERT(AES_DECRYPT(FROM_bASE64(website), '8e651522e38256f2') USING utf8mb4) as website,
-- 	business_type_id,
-- 	CONVERT(AES_DECRYPT(FROM_bASE64(business_name), '8e651522e38256f2') USING utf8mb4) as business_name,
-- 	business_industry_id,
-- 	CONVERT(AES_DECRYPT(FROM_bASE64(business_specify), '8e651522e38256f2') USING utf8mb4) as business_specify,
-- 	CONVERT(AES_DECRYPT(FROM_bASE64(business_description), '8e651522e38256f2') USING utf8mb4) as business_description,
-- 	CONVERT(AES_DECRYPT(FROM_bASE64(business_competitors), '8e651522e38256f2') USING utf8mb4) as business_competitors,
-- 	upload_customer_list_ids,
-- 	crm_id,
-- 	CONVERT(AES_DECRYPT(FROM_bASE64(crm_key), '8e651522e38256f2') USING utf8mb4) as crm_key,
-- 	created_at,
-- 	updated_at
-- from leadspeek_business;


select * from user_logs order by id desc limit 10;

select * from leadspeek_customer_campaigns where leadspeek_api_id in ('14820931');

select 
	DATEDIFF('2026-02-02', '2026-01-26') as result_1;
-- 	DATEDIFF('2026-01-11','2026-01-11') as result_2, -- di ui 2026-01-01 - 2026-01-07, ke charge nya tanggal 08 
-- 	DATEDIFF('2026-01-15','2026-01-08') as result_3, -- di ui 2026-01-08 - 2026-01-14,  ke charge nya tanggal 15
-- 	DATEDIFF('2026-01-22','2026-01-15') as result_4, -- di ui 2026-01-15 - 2026-01-21,  ke charge nya tanggal 22
-- 	DATEDIFF('2026-01-28','2026-01-22') as result_5; -- di ui 2026-01-22 - 2026-01-28,  ke charge nya tanggal 29



select * from leadspeek_business_resources;
select * from leadspeek_customers;


select 
	active,
	disabled,
	active_user, 
	CONVERT(AES_DECRYPT(FROM_bASE64(campaign_name), '8e651522e38256f2') USING utf8mb4) as campaign_name, 
	leadspeek_type,
	leadspeek_api_id,
	national_targeting,
	leadspeek_locator_state,
	leadspeek_locator_zip,
	leadspeek_users.* 
from leadspeek_users where leadspeek_type = 'predict';
select * from leadspeek_business;

select active,disabled,active_user, CONVERT(AES_DECRYPT(FROM_bASE64(campaign_name), '8e651522e38256f2') USING utf8mb4), leadspeek_type, leadspeek_users.* from leadspeek_users where leadspeek_type = 'simplifi' limit 1;
select active,disabled,active_user, CONVERT(AES_DECRYPT(FROM_bASE64(campaign_name), '8e651522e38256f2') USING utf8mb4), leadspeek_type, leadspeek_users.* from leadspeek_users where leadspeek_type = 'predict';
select * from leadspeek_business where leadspeek_api_id = 10122196;


set @leadspeek_api_id_delete := '9585489606,7087376074';
delete from leadspeek_users where FIND_IN_SET(leadspeek_api_id, @leadspeek_api_id_delete);
delete from leadspeek_business where FIND_IN_SET(leadspeek_api_id, @leadspeek_api_id_delete);
delete from leadspeek_customer_campaigns where FIND_IN_SET(leadspeek_api_id, @leadspeek_api_id_delete);
delete from leadspeek_predict_reports WHERE FIND_IN_SET(leadspeek_api_id COLLATE utf8mb4_general_ci, @leadspeek_api_id_delete COLLATE utf8mb4_general_ci);

select * from master_features;
select * from feature_users;

select * from services_agreement
where 
	feature_id in (4,5,6) and 
	user_id in (select id from users where company_id in (164) and user_type in ('user','userdownline'));

select * from subscription_modules;

select * from user_logs where created_at > '2026-02-03 22:12:16' order by id desc;
select * from user_logs order by id desc;
select * from user_logs where action like '%marketing service%' order by id desc;

select * from topup_agencies where company_id = 164;

select * from leadspeek_invoices 
where (invoice_type in ('agency','agency_subscription') and company_id = 164)
order by id desc;

set @leadspeek_api_id := 83428362;
select start_billing_date,lp_invoice_date,lu.* from leadspeek_users as lu where leadspeek_type = 'predict' and leadspeek_api_id in (@leadspeek_api_id);
select * from leadspeek_business where leadspeek_api_id in (@leadspeek_api_id);
select * from leadspeek_customer_campaigns where leadspeek_api_id in (@leadspeek_api_id);
select * from leadspeek_predict_reports where leadspeek_api_id = @leadspeek_api_id;
select * from leadspeek_predict_report_files where report_id = '';
select * from leadspeek_predict_report_bundles;
select * from leadspeek_predict_reports_bundle_items;
select * from leadspeek_customers;

DELETE from leadspeek_business where leadspeek_api_id in (SELECT leadspeek_api_id FROM leadspeek_users where leadspeek_type = 'predict' and company_id = 164);
DELETE from leadspeek_customer_campaigns where leadspeek_api_id in (SELECT leadspeek_api_id FROM leadspeek_users where leadspeek_type = 'predict' and company_id = 164);
DELETE from leadspeek_predict_reports where leadspeek_api_id in (SELECT leadspeek_api_id FROM leadspeek_users where leadspeek_type = 'predict' and company_id = 164);
DELETE from leadspeek_invoices where leadspeek_api_id in (SELECT leadspeek_api_id FROM leadspeek_users where leadspeek_type = 'predict' and company_id = 164);
DELETE FROM leadspeek_users where leadspeek_type = 'predict' and company_id = 164;

select DATE_ADD('2026-02-03', INTERVAL 1 MONTH) as test;

select * from jobs order by id desc;

SELECT
	id,
	company_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) as setting_name,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_value), '8e651522e38256f2') USING utf8mb4) as setting_value,
	setting_name,
	setting_value
FROM company_settings
WHERE company_id = 164;


















