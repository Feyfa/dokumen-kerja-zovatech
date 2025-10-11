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

select * from leadspeek_business;

select * from leadspeek_business_resources;

select * from leadspeek_customers;

select active,disabled,active_user, CONVERT(AES_DECRYPT(FROM_bASE64(campaign_name), '8e651522e38256f2') USING utf8mb4), leadspeek_type, leadspeek_users.* from leadspeek_users where leadspeek_type = 'simplifi' limit 1;

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


