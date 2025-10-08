use emm_sandbox;
-- 
select * from integration_list;
select * from integration_customs;

select 
	id,
	company_id,
	integration_slug,
	CONVERT(AES_DECRYPT(FROM_bASE64(api_key), '8e651522e38256f2') USING utf8mb4) as api_key,
	CONVERT(AES_DECRYPT(FROM_bASE64(app_id), '8e651522e38256f2') USING utf8mb4) as app_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(password), '8e651522e38256f2') USING utf8mb4) as password,
	enable_sendgrid,
	enable_default_campaign,
	subdomain,
	workspace_id,
	custom_fields,
	version,
	tokens,
	created_at,
	updated_at
from companies_integration_settings 
where company_id = 165;

select * from campaign_informations;

select * from leadspeek_reports where leadspeek_api_id = 43919974;
select * from topup_campaigns where leadspeek_api_id = 43919974;
select * from leadspeek_invoices where leadspeek_api_id = 43919974;

select * from jobs;

select * from leadspeek_reports where leadspeek_api_id = 75935502 order by id desc;
select * from failed_lead_records where leadspeek_api_id = 75935502 order by id desc;
