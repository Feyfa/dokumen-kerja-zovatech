use emm_sandbox;
--
SELECT
	id,
	company_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) as setting_name,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_value), '8e651522e38256f2') USING utf8mb4) as setting_value,
	setting_name,
	setting_value
FROM company_settings
WHERE 
	company_id = 22 
	and CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) = 'rootghlapikey';
--
select 
	CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) as email_dec,
	plan_minspend_id, 
	month_developer_minspend, 
	is_marketing_services_agreement_developer, 
	api_mode,
	company_id, 
	last_payment_update, 
	last_invoice_minspend, 
	trial_end_date, 
	amount, 
	last_balance_amount, 
	customer_card_id,
	user_type,
	users.* 
from users 
where CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) in (
-- 	'fisikamodern00+jidanagencyemm5@gmail.com'
-- 	,'fisikamodern00+jidanagencyemm6@gmail.com',
-- 	,'fisikamodern00+jidanagencyemm7@gmail.com'
-- 	,'fisikamodern00+jidanagencyemm8@gmail.com'
-- 	'fisikamodern00+jidanagencydom1@gmail.com',
-- 	,'fisikamodern00+jidanagencyemm9@gmail.com'
-- 	,'fisikamodern00+jidanagencyemm10@gmail.com'
	'muhammadjidan703@gmail.com'
);
--
select * from users 
where 
	CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) in (
		'muhammadjidan703@gmail.com'
	) and user_type = 'userdownline';
--
select * from companies 
where id in (
	select company_id from users 
	where 
		CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) in (
			'muhammadjidan703@gmail.com'
		) and user_type = 'userdownline'
);
--
select * from company_settings
where company_id in (
	select company_id from users 
	where 
		CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) in (
			'muhammadjidan703@gmail.com'
		) and user_type = 'userdownline'
);
--
select * from companies where id in (755,754);
select * from company_settings where company_id in (755,754);
--
select * from topup_campaigns where leadspeek_api_id = 44694142;


select * from companies_integration_settings where company_id = 165 and integration_slug = 'gohighlevel';
select * from global_settings where setting_name in ('custom_fields_advance_gohighlevel', 'custom_fields_b2b_gohighlevel') and company_id = 165;
SELECT
	id,
	company_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) as setting_name,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_value), '8e651522e38256f2') USING utf8mb4) as setting_value,
	setting_name,
	setting_value
FROM company_settings
WHERE 
	company_id = 165;
select * from global_settings where setting_name in ('ghl_custom_fields_create'); -- ini adalah list untuk menampilkan custom field di ui integration
select * from global_settings where setting_name in ('custom_fields_b2b_gohighlevel','custom_fields_advance_gohighlevel') and company_id = 165; -- ini adalah list untuk menampilkan custom field di ui integration



select 
	u_client.id as client_id,
	u_client.company_id as client_company_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(u_agency.email), '8e651522e38256f2') USING utf8mb4) as email_agency,
	u_client.user_type,
	CONVERT(AES_DECRYPT(FROM_bASE64(u_client.email), '8e651522e38256f2') USING utf8mb4) as email_client,
	CONVERT(AES_DECRYPT(FROM_bASE64(sat.token), '8e651522e38256f2') USING utf8mb4) as client_token_sso
from users as u_agency
join users as u_client on u_agency.company_id = u_client.company_parent
join sso_access_tokens as sat on sat.user_id = u_client.id
where
	CONVERT(AES_DECRYPT(FROM_bASE64(u_agency.email), '8e651522e38256f2') USING utf8mb4) = 'fisikamodern00+jidanach@gmail.com' and
	u_agency.user_type = 'userdownline' and 
	u_client.user_type = 'client' and 
	u_client.active = 'T'
group by client_id;

select * from sso_access_tokens where user_id = 933;

select 
	u.id as agency_id,
	u.company_id as agency_company_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(u.name), '8e651522e38256f2') USING utf8mb4) as agency_name,
	CONVERT(AES_DECRYPT(FROM_bASE64(u.email), '8e651522e38256f2') USING utf8mb4) as agency_email,
	u.created_at
from company_agreement_files as caf
join users as u on u.company_id = caf.company_id
where
	u.user_type = 'userdownline' and 
	u.active = 'T' and 
	caf.name = 'minimum_spend_v2'; 


select * from company_settings where company_id = 165;

select * from leadspeek_reports where leadspeek_api_id = 75935502;


29484235, 8bc64c9c289d18a05dd9652422208f05 -- enhance
75935502, 17a36690749847a48664619852704e43 -- b2b

select * from person_emails where email_encrypt in ('17a36690749847a48664619852704e43','78e63b99eaa18437f46b56cea8e7b220');


select * from companies_integration_settings where company_id = 165  and integration_slug = 'gohighlevel';
select * from global_settings where setting_name = 'ghl_custom_fields_create' or (setting_name = 'custom_fields_b2b_gohighlevel' and company_id = 165);




