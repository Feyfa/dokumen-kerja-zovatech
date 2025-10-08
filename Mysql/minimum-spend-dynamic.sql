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
	and CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) = 'rootminspend';
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
	'fisikamodern00+jidanagencyemm10@gmail.com'
-- 	,'fisikamodern00+emm@gmail.com'
-- 	,'fisikamodern00+jidanagencyemm1@gmail.com'
-- 	,'fisikamodern00+jidanagencyemm7@gmail.com'
-- 	,'fisikamodern00+jidanagencyemm8@gmail.com'
-- 	'fisikamodern00+jidanagencydom1@gmail.com',
-- 	,'fisikamodern00+jidanagencyemm9@gmail.com'
-- 	,'fisikamodern00+jidanagencyemm10@gmail.com'
-- 	'fisikamodern00+jidanach@gmail.com'
);
select * from companies where id = 753;
select * from company_settings where company_id = 753;
--
select * from user_logs where action like '%Minimum Spend Plan%' order by id desc;
select * from user_logs where action like '%update campaign%';
--
select * from minimum_spend_list;
truncate minimum_spend_list;
select * from companies where id = 753;
-- untuk submonth 1 bulan atau 2 bulan kemudian
SET @last_payment_update := '2025-08-20 03:28:36';
SET @last_invoice_minspend := '2025-10-20 16:01:08';
SELECT 
	DATE_ADD(@last_payment_update, INTERVAL 1 MONTH) as last_payment_update_next_1_month,
	DATE_ADD(@last_payment_update, INTERVAL 2 MONTH) as last_payment_update_next_2_month,
	DATE_ADD(@last_invoice_minspend, INTERVAL 1 MONTH) as last_invoice_minspend_next_1_month,
	DATE_ADD(@last_invoice_minspend, INTERVAL 2 MONTH) as last_invoice_minspend_next_2_month;

-- 
select * from global_settings where setting_name = 'root_min_spend_developer_api';
-- 
select * from jobs;
--
select * from topup_agencies where company_id = 753 order by id asc;
select * from leadspeek_invoices where invoice_type = 'agency' and company_id = 753 order by id asc;
--
DELETE FROM topup_agencies WHERE company_id = 745;
DELETE FROM leadspeek_invoices WHERE invoice_type = 'agency' AND company_id = 745;
--
UPDATE company_settings
SET setting_value = TO_BASE64(AES_ENCRYPT('{"enabled":"T","startobilldays":"60","startobillmonths":"2","minspend_first_month":"49","minspend_second_month":"99","minspend":"149","minspend_logic_change_date":"2025-04-16","excludecompanyid":"872,977,962,1007,1023,1025,3150,2326,2724,1715,1547,3634,2450"}', '8e651522e38256f2'))
WHERE
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) = 'rootminspend' and
	company_id = 22;
-- 
select * from jobs;
--
select * from agreement_files;
--
select tfa_active, users.* from users 
where 
	CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) in (
		'fisikamodern00+jidanagencyemm9@gmail.com',
		'fisikamodern00@gmail.com'
	) and user_type = 'userdownline';
--
select * from companies 
where id in (
	select company_id from users 
	where 
		CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) in (
			'fisikamodern00+jidanagencyemm9@gmail.com'
		) and user_type = 'userdownline'
);

select * from company_settings
where company_id in (
	select company_id from users 
	where 
		CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) in (
			'fisikamodern00+jidanagencyemm9@gmail.com'
		) and user_type = 'userdownline'
);

ID : 5 | Plan Name : No Changes, value remains Plan New Agency | Months : No Changes, value remains Month 1 = $40, Month 2 = $80, Month 3 = $120, Month 4 = $160, Month 5 = $200, Flat Month = $349
ID : 5 | Plan Name : No Changes, value remains Plan New Agency | Months : Update values from Month 1 = $40, Month 2 = $80, Month 3 = $120, Month 4 = $160, Month 5 = $200, Flat Month = $349 to Flat Month = $349


INSERT INTO agreement_files (name, url)
VALUES (
  'minimum_spend_v2',
  'https://emmspaces.nyc3.digitaloceanspaces.com/docs/Updated%208-19-25%20_%20Agency%20Marketing%20Services%20Agreement%20(4874-9036-4350.4).html'
);

select * from jobs order by id desc;
select * from agreement_files;

select * from user_logs order by id desc limit 10;


select * from global_settings;
select * from minimum_spend_list;

{"first_time_charge":3500,"minimum_spend":[{"month":1,"amount":149},{"month":2,"amount":399},{"month":3,"amount":699},{"month":4,"amount":999},{"month":5,"amount":1499}]}

select last_payment_update, users.* from users where DAY(last_payment_update) = 01;


select * from companies;


select 
	u.plan_minspend_id,
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
	DATE(u.created_at) >= '2025-08-21';

select * from users where user_type = 'userdownline' and active = 'T' and DATE(u.created_at) >= '2025-08-21' and company_parent = 60


select 
	CONVERT(AES_DECRYPT(FROM_bASE64(phonenum), '8e651522e38256f2') USING utf8mb4),
	CONVERT(AES_DECRYPT(FROM_bASE64(phone_country_code), '8e651522e38256f2') USING utf8mb4),
	CONVERT(AES_DECRYPT(FROM_bASE64(phone_country_calling_code), '8e651522e38256f2') USING utf8mb4),
	CONVERT(AES_DECRYPT(FROM_bASE64(state_name), '8e651522e38256f2') USING utf8mb4),
	CONVERT(AES_DECRYPT(FROM_bASE64(state_code), '8e651522e38256f2') USING utf8mb4),
	CONVERT(AES_DECRYPT(FROM_bASE64(country_code), '8e651522e38256f2') USING utf8mb4),
	CONVERT(AES_DECRYPT(FROM_bASE64(zip), '8e651522e38256f2') USING utf8mb4),
	CONVERT(AES_DECRYPT(FROM_bASE64(city), '8e651522e38256f2') USING utf8mb4),
	CONVERT(AES_DECRYPT(FROM_bASE64(address), '8e651522e38256f2') USING utf8mb4),
	users.* 
from users 
where 
	CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) in ('fisikamodern00+a9@gmail.com','fisikamodern00+a1@gmail.com','fisikamodern00+a2@gmail.com');
select 
	id,
	CONVERT(AES_DECRYPT(FROM_bASE64(company_name), '8e651522e38256f2') USING utf8mb4),
	CONVERT(AES_DECRYPT(FROM_bASE64(company_legalname), '8e651522e38256f2') USING utf8mb4),
	CONVERT(AES_DECRYPT(FROM_bASE64(company_address), '8e651522e38256f2') USING utf8mb4),
	CONVERT(AES_DECRYPT(FROM_bASE64(company_city), '8e651522e38256f2') USING utf8mb4),
	CONVERT(AES_DECRYPT(FROM_bASE64(company_zip), '8e651522e38256f2') USING utf8mb4),
	CONVERT(AES_DECRYPT(FROM_bASE64(company_state_code), '8e651522e38256f2') USING utf8mb4),
	CONVERT(AES_DECRYPT(FROM_bASE64(company_state_name), '8e651522e38256f2') USING utf8mb4),
	CONVERT(AES_DECRYPT(FROM_bASE64(phone_country_code), '8e651522e38256f2') USING utf8mb4),
	CONVERT(AES_DECRYPT(FROM_bASE64(phone_country_calling_code), '8e651522e38256f2') USING utf8mb4),
	CONVERT(AES_DECRYPT(FROM_bASE64(phone), '8e651522e38256f2') USING utf8mb4),
	CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4),
	CONVERT(AES_DECRYPT(FROM_bASE64(company_country_code), '8e651522e38256f2') USING utf8mb4),
	CONVERT(AES_DECRYPT(FROM_bASE64(company_address), '8e651522e38256f2') USING utf8mb4),
	companies.*
from companies 
where id = 796;

select paymentterm_default from companies where id = 164;






select * from users where company_id = 0 order by id desc;


select * 
from users
join companies on companies.id = users.company_id
where 
	users.id = 954 and 
	users.active = 'T' and 
	users.company_parent = 164;

--
UPDATE company_settings
	SET setting_value = TO_BASE64(AES_ENCRYPT('{"local":{"Monthly":{"LeadspeekCostperlead":"0.2","LeadspeekMinCostMonth":"0","LeadspeekPlatformFee":"0","LeadspeekCostperleadAdvanced":"1"},"OneTime":{"LeadspeekCostperlead":"0.10","LeadspeekMinCostMonth":"0","LeadspeekPlatformFee":"0","LeadspeekCostperleadAdvanced":"0.10"},"Weekly":{"LeadspeekCostperlead":"0.2","LeadspeekMinCostMonth":"0","LeadspeekPlatformFee":"0","LeadspeekCostperleadAdvanced":"1"},"Prepaid":{"LeadspeekCostperlead":"0.2","LeadspeekMinCostMonth":"0","LeadspeekPlatformFee":"0","LeadspeekCostperleadAdvanced":"1"}},"locator":{"Monthly":{"LocatorCostperlead":"0.4","LocatorMinCostMonth":"0","LocatorPlatformFee":"0"},"OneTime":{"LocatorCostperlead":"1.3","LocatorMinCostMonth":"0","LocatorPlatformFee":"0"},"Weekly":{"LocatorCostperlead":"0.4","LocatorMinCostMonth":"0","LocatorPlatformFee":"0"},"Prepaid":{"LocatorCostperlead":"0.5","LocatorMinCostMonth":"0","LocatorPlatformFee":"0"}},"locatorlead":{"FirstName_LastName":"5","FirstName_LastName_MailingAddress":"6","FirstName_LastName_MailingAddress_Phone":"1.3"},"enhance":{"Monthly":{"EnhanceCostperlead":"0.45","EnhanceMinCostMonth":"0","EnhancePlatformFee":"0"},"OneTime":{"EnhanceCostperlead":"2.00","EnhanceMinCostMonth":"0","EnhancePlatformFee":"0"},"Weekly":{"EnhanceCostperlead":"0.45","EnhanceMinCostMonth":"0","EnhancePlatformFee":"0"},"Prepaid":{"EnhanceCostperlead":"0.45","EnhanceMinCostMonth":"0","EnhancePlatformFee":"0"}}}', '8e651522e38256f2')) 
WHERE 
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) = 'costagency' and 
	company_id = 164;
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
	company_id = 164 
	and CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) = 'costagency';













