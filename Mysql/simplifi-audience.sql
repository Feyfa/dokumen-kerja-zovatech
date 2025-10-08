use emm_sandbox;
--
select * from leadspeek_media;
select archived, leadspeek_type, leadspeek_api_id, start_billing_date, created_at, leadspeek_users.* from leadspeek_users where leadspeek_type = 'simplifi';
--
set @company_id := 164, @leadspeek_api_id = '83769694', @leadspeek_api_id_like = '%83769694%'; -- di jidan ach
set @company_id := 464, @leadspeek_api_id = '4613914', @leadspeek_api_id_like = '%4613914%'; -- di emm3
set @company_id := 164, @leadspeek_api_id = '4592336', @leadspeek_api_id_like = '%4592336%'; -- Dimas Waiting Charge
select @company_id, @leadspeek_api_id, @leadspeek_api_id_like;

select payment_status, users.* from users where CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) = 'fisikamodern00+dimas@gmail.com';
select payment_status, users.* from users where CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) = 'fisikamodern00+jidanach@gmail.com';
select payment_status, users.* from users where CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) = 'fisikamodern00+jidanagencyemm3@gmail.com';
select * from companies where id = 464;

select * from topup_agencies where company_id = @company_id order by id asc;
select * from leadspeek_invoices where company_id = @company_id and invoice_type = 'agency' order by id asc;

select * from leadspeek_invoices where leadspeek_api_id = @leadspeek_api_id and invoice_type = 'campaign' order by id asc;
select * from user_logs where description like @leadspeek_api_id_like order by id desc;
select * from user_logs where description like @leadspeek_api_id_like and action in ('Campaign activate', 'Campaign Stopped', 'Campaign failed payment', 'Campaign auto topup', 'Campaign auto topup failed payment', 'Increase Daily Budget From Financial Settings', 'Increase Daily Budget When Campaign Active') order by created_at desc;
--
select 
	active,
	disabled,
	active_user,
	archived, 
	leadspeek_type, 
	leadspeek_api_id, 
	daily_budget,
	monthly_budget,
	max_bid,
	agency_markup,
	frequency_capping_impressions,
	simplifi_selected_media,
	simplifi_selected_campaign,
	simplifi_selected_audience,
	leadspeek_campaignsid,
	leadspeek_organizationid,
	goal_type,
	goal_value,
	trysera,
	audience_status,
	updated_at,
	created_at,
	start_billing_date,
	lp_enddate,
	admin_notify_to,
	paymentterm,
	leadspeek_users.* 
from leadspeek_users
where 
	leadspeek_type = 'simplifi' and 
	leadspeek_api_id = @leadspeek_api_id;

select is_marketing_services_agreement_developer, user_type, users.* from users where company_id = 22;
select * from users where CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) = 'fisikamodern00+adminroot1update@gmail.com';

select * from leadspeek_users where leadspeek_api_id = '4592336';
select * from leadspeek_invoices where leadspeek_api_id = '4592336';

update leadspeek_users
set 
	leadspeek_api_id = '4612361',
	leadspeek_campaignsid = '4612361'
where id = 1141;

select * from leadspeek_media;
select lp_enddate from leadspeek_users where lp_enddate is not null;
--
select id, price_lead, platform_price_lead, root_price_lead from leadspeek_reports where root_price_lead <> 0 and root_price_lead <> 0.1 order by created_at desc limit 1;
select * from topup_campaigns where leadspeek_api_id = 26778152;
--
SELECT
	id,
	company_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) as setting_name,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_value), '8e651522e38256f2') USING utf8mb4) as setting_value,
	setting_name,
	setting_value
FROM company_settings
WHERE CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) = 'rootfee';
select simplifi_organizationid,companies.* from companies where id in (164, 165, 464);
--
select * from jobs order by id desc;
select * from users where CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) = 'fisikamodern00+dimas@gmail.com';
select * from users where CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) = 'fisikamodern00+jidanach@gmail.com';
select * from users where CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) = 'fisikamodern00+jidanachdom@gmail.com';
select * from users where CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) = 'fisikamodern00+a7@gmail.com';
select * from companies where id = 786;
select * from company_settings where company_id = 786;
select * from companies where id = 568;
select * from user_logs order by id desc limit 20;
select 

	id,
	CONVERT(AES_DECRYPT(FROM_bASE64(custom_commission), '8e651522e38256f2') USING utf8mb4) as custom_commission,
	CONVERT(AES_DECRYPT(FROM_bASE64(custom_commission_fixed), '8e651522e38256f2') USING utf8mb4) as custom_commission_fixed,
	custom_commission_enabled,
	custom_commission_type
from users where CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) = 'fisikamodern00+jidansales@gmail.com';

UPDATE users
SET custom_commission = TO_BASE64(AES_ENCRYPT('{"sr":{"siteid":"0.08","searchid":"0.13","simplifiid":"0.18"},"ae":{"siteid":"0.08","searchid":"0.13","simplifiid":"0.18"},"ar":{"siteid":"0.08","searchid":"0.13","simplifiid":"0.18"}}', '8e651522e38256f2'))
where id = 621 and CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) = 'fisikamodern00+jidansales@gmail.com';
--
select id, CONVERT(AES_DECRYPT(FROM_bASE64(company_name), '8e651522e38256f2') USING utf8mb4) as company_name, simplifi_organizationid from companies where id = 165;
select id, CONVERT(AES_DECRYPT(FROM_bASE64(company_name), '8e651522e38256f2') USING utf8mb4) as company_name, simplifi_organizationid from companies where id = 164;
select id, CONVERT(AES_DECRYPT(FROM_bASE64(company_name), '8e651522e38256f2') USING utf8mb4) as company_name, simplifi_organizationid from companies where id = 22;
-- 
select id, leadspeek_api_id, leadspeek_type, leadspeek_api_id, start_billing_date, created_at, admin_notify_to, active, disabled, active_user from leadspeek_users where leadspeek_api_id = 4592647;
select * from user_logs where action = 'Campaign activate' and description like '%4592647%';
select * from user_logs where description like '%4592647%';
-- 
INSERT INTO `user_logs` 
(`id`, `user_id`, `user_ip`, `action`, `description`, `updated_at`, `created_at`, `target_user_id`) 
VALUES 
(
	NULL, 
	'53', 
	'2001:448a:9090:226c:7d8e:10ca:126b:d42f|Asia/Jakarta', 
	'Campaign activate', 
	'campaign id: 4590088 | campaign type: enhance | payment term: weekly | setup fee: $0.00 | campaign fee: $0.00 | cost per contact: $2.30 | contact per day: 2 | client cost: $32.20 | agency cost: $16.10', '2025-05-09 01:55:48', '2025-05-09 01:55:48', '282'
);
select  leadspeek_type, leadspeek_users.* from leadspeek_users where leadspeek_api_id in (4594905);
select * from jobs;
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
	company_id = 22 
	and CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) = 'rootfee';
select start_billing_date from leadspeek_users;


select * from jobs order by id desc;
select * from user_logs order by id desc;

select * from leadspeek_report;

select NOW();


select DATE(DATE_ADD('2023-05-13 09:59:59', INTERVAL 1 MONTH))

select * from leadspeek_users where leadspeek_api_id = 4612361 and lp_enddate is not null and lp_enddate <> '';

select * from leadspeek_reports where leadspeek_api_id = 43919974;

select * from failed_lead_records;

select * from master_features;
select * from feature_users;
select * from services_agreement where feature_id in (2,3) and user_id = 279 order by feature_id asc;


INSERT INTO emm_sandbox.master_features
(slug, name, is_beta, apply_to_all_agency, created_at, updated_at)
VALUES('simplifi_module', 'Simplifi Module', 'T', 'F', '2025-09-18 00:00:00', '2025-09-18 00:00:00');

-- id ori = 2893

select  audience_status , leadspeek_users.* from leadspeek_users where leadspeek_api_id = 4618382;
select * from leadspeek_invoices where leadspeek_api_id = 4618382;
select * from user_logs where description like "%4618382%";

select * from users where CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) = 'fisikamodern00+jidanagencydom4@gmail.com';



UPDATE company_settings SET setting_value = TO_BASE64(AES_ENCRYPT('{"local":{"name":"Site ID DOMS","url":"siteiddom"},"locator":{"name":"Search ID DOMS","url":"searchiddom"},"enhance":{"name":"Enhance ID DOMS","url":"enhanceiddom"},"b2b":{"name":"B2B ID DOMS","url":"b2biddom"}}', '8e651522e38256f2')) 
WHERE company_id = 39 and CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) = 'rootcustomsidebarleadmenu';

SELECT
	id,
	company_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) as setting_name,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_value), '8e651522e38256f2') USING utf8mb4) as setting_value,
	setting_name,
	setting_value
FROM company_settings
where company_id in (22,39) and CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) = 'rootcustomsidebarleadmenu';

SELECT
	id,
	company_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) as setting_name,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_value), '8e651522e38256f2') USING utf8mb4) as setting_value,
	setting_name,
	setting_value
FROM company_settings
where company_id in (164) and CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) = 'customsidebarleadmenu';

SELECT
	id,
	company_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) as setting_name,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_value), '8e651522e38256f2') USING utf8mb4) as setting_value,
	setting_name,
	setting_value
FROM company_settings
where company_id in (464) and CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) = 'customsidebarleadmenu';

SELECT
	id,
	company_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) as setting_name,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_value), '8e651522e38256f2') USING utf8mb4) as setting_value,
	setting_name,
	setting_value
FROM company_settings
where company_id in (169) and CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) = 'customsidebarleadmenu';

SELECT
	id,
	company_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) as setting_name,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_value), '8e651522e38256f2') USING utf8mb4) as setting_value,
	setting_name,
	setting_value
FROM company_settings
where company_id in (179) and CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) = 'customsidebarleadmenu';

SELECT
	id,
	company_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) as setting_name,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_value), '8e651522e38256f2') USING utf8mb4) as setting_value,
	setting_name,
	setting_value
FROM company_settings
where company_id in (814) and CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) = 'customsidebarleadmenu';

select * from feature_users;
select * from master_features;
select * from services_agreement where user_id = 285 order by id desc;

INSERT INTO emm_sandbox.feature_users
(company_id, is_beta, created_at, updated_at)
VALUES(799, 'T', '2025-03-27 15:42:19', '2025-03-27 15:42:19');


select
TO_BASE64(AES_ENCRYPT('{"local":{"name":"Site AI","url":"siteai"},"locator":{"name":"Search AI","url":"searchai"},"enhance":{"name":"Search 2.0","url":"search20"},"b2b":{"name":"B2B AI","url":"b2bai"}}', '8e651522e38256f2')) as dominator,
TO_BASE64(AES_ENCRYPT('{"local":{"name":"Site Leads","url":"site-leads"},"locator":{"name":"Search Leads","url":"search-leads"},"enhance":{"name":"Search 2.0","url":"enhanced-leads"},"b2b":{"name":"B2B Leads","url":"b2b-leads"}}', '8e651522e38256f2')) as mobile;


--

select
TO_BASE64(AES_ENCRYPT('{"local":{"Monthly":{"LeadspeekCostperlead":"0","LeadspeekCostperleadAdvanced":"1.5","LeadspeekMinCostMonth":"0","LeadspeekPlatformFee":"0"},"OneTime":{"LeadspeekCostperlead":"0","LeadspeekCostperleadAdvanced":"1.5","LeadspeekMinCostMonth":"0","LeadspeekPlatformFee":"0"},"Weekly":{"LeadspeekCostperlead":"0","LeadspeekCostperleadAdvanced":"1.5","LeadspeekMinCostMonth":"0","LeadspeekPlatformFee":"0"},"Prepaid":{"LeadspeekCostperlead":"0","LeadspeekCostperleadAdvanced":"1.5","LeadspeekMinCostMonth":"0","LeadspeekPlatformFee":"0"}},"locator":{"Monthly":{"LocatorCostperlead":"0","LocatorMinCostMonth":"0","LocatorPlatformFee":"0"},"OneTime":{"LocatorCostperlead":"0","LocatorMinCostMonth":"0","LocatorPlatformFee":"0"},"Weekly":{"LocatorCostperlead":"0","LocatorMinCostMonth":"0","LocatorPlatformFee":"0"},"Prepaid":{"LocatorCostperlead":"0","LocatorMinCostMonth":"0","LocatorPlatformFee":"0"}},"locatorlead":{"FirstName_LastName":"1","FirstName_LastName_MailingAddress":"1","FirstName_LastName_MailingAddress_Phone":"1"},"enhance":{"Monthly":{"EnhanceCostperlead":"0","EnhanceMinCostMonth":"0","EnhancePlatformFee":"0"},"OneTime":{"EnhanceCostperlead":"0","EnhanceMinCostMonth":"0","EnhancePlatformFee":"0"},"Weekly":{"EnhanceCostperlead":"0","EnhanceMinCostMonth":"0","EnhancePlatformFee":"0"},"Prepaid":{"EnhanceCostperlead":"0","EnhanceMinCostMonth":"0","EnhancePlatformFee":"0"}},"b2b":{"Monthly":{"B2bCostperlead":"1.49","B2bMinCostMonth":"0","B2bPlatformFee":"0"},"OneTime":{"B2bCostperlead":"1.49","B2bMinCostMonth":"0","B2bPlatformFee":"0"},"Weekly":{"B2bCostperlead":"1.49","B2bMinCostMonth":"0","B2bPlatformFee":"0"},"Prepaid":{"B2bCostperlead":"1.49","B2bMinCostMonth":"0","B2bPlatformFee":"0"}},"simplifi":{"Prepaid":{"SimplifiMaxBid":"12","SimplifiDailyBudget":"5","SimplifiAgencyMarkup":"0"}}}', '8e651522e38256f2')) as rootagencydefaultprice_simplifi,
TO_BASE64(AES_ENCRYPT('{"defaultfreeplan":"T","defaultpaymentterm":"Prepaid","defaultagencypercentagecommission":"0.10","clientcaplead":"","clientcapleadpercentage":"50","clientminleadday":"","deadlinedistribution":"21","maxBid":{"minimum":"8"},"dailyBudget":{"minimum":"5"}}', '8e651522e38256f2')) as rootsetting_simplifi,
TO_BASE64(AES_ENCRYPT('{"local":{"Monthly":{"LeadspeekCostperlead":"1.5","LeadspeekMinCostMonth":"0","LeadspeekPlatformFee":"0","LeadspeekCostperleadAdvanced":"2.39"},"OneTime":{"LeadspeekCostperlead":"2.1","LeadspeekMinCostMonth":"0","LeadspeekPlatformFee":"0","LeadspeekCostperleadAdvanced":"2.59"},"Weekly":{"LeadspeekCostperlead":"1.5","LeadspeekMinCostMonth":"0","LeadspeekPlatformFee":"0","LeadspeekCostperleadAdvanced":"2.29"},"Prepaid":{"LeadspeekCostperlead":"1.5","LeadspeekMinCostMonth":"0","LeadspeekPlatformFee":"0","LeadspeekCostperleadAdvanced":"2.49"}},"locator":{"Monthly":{"LocatorCostperlead":"2.2","LocatorMinCostMonth":"0","LocatorPlatformFee":"0"},"OneTime":{"LocatorCostperlead":"2.2","LocatorMinCostMonth":"0","LocatorPlatformFee":"0"},"Weekly":{"LocatorCostperlead":"2.1","LocatorMinCostMonth":"0","LocatorPlatformFee":"0"},"Prepaid":{"LocatorCostperlead":"2.2","LocatorMinCostMonth":"0","LocatorPlatformFee":"0"}},"locatorlead":{"FirstName_LastName":"1","FirstName_LastName_MailingAddress":"1","FirstName_LastName_MailingAddress_Phone":"2.2"},"enhance":{"Monthly":{"EnhanceCostperlead":"3.2","EnhanceMinCostMonth":"0","EnhancePlatformFee":"0"},"OneTime":{"EnhanceCostperlead":"2.3","EnhanceMinCostMonth":"0","EnhancePlatformFee":"0"},"Weekly":{"EnhanceCostperlead":"3.1","EnhanceMinCostMonth":"0","EnhancePlatformFee":"0"},"Prepaid":{"EnhanceCostperlead":"3.3","EnhanceMinCostMonth":"0","EnhancePlatformFee":"0"}},"b2b":{"Weekly":{"B2bCostperlead":"4.1","B2bMinCostMonth":"0","B2bPlatformFee":"0"},"Monthly":{"B2bCostperlead":"4.2","B2bMinCostMonth":"0","B2bPlatformFee":"0"},"OneTime":{"B2bCostperlead":"1.49","B2bMinCostMonth":"0","B2bPlatformFee":"0"},"Prepaid":{"B2bCostperlead":"4.3","B2bMinCostMonth":"0","B2bPlatformFee":"0"}},"simplifi":{"Prepaid":{"SimplifiMaxBid":"20","SimplifiDailyBudget":"5","SimplifiAgencyMarkup":"0"}}}', '8e651522e38256f2')) as agencydefaultprice_simplifi,
TO_BASE64(AES_ENCRYPT('{"local":{"Monthly":{"LeadspeekCostperlead":"0","LeadspeekCostperleadAdvanced":"1.5","LeadspeekMinCostMonth":"0","LeadspeekPlatformFee":"0"},"OneTime":{"LeadspeekCostperlead":"0","LeadspeekCostperleadAdvanced":"1.5","LeadspeekMinCostMonth":"0","LeadspeekPlatformFee":"0"},"Weekly":{"LeadspeekCostperlead":"0","LeadspeekCostperleadAdvanced":"1.5","LeadspeekMinCostMonth":"0","LeadspeekPlatformFee":"0"},"Prepaid":{"LeadspeekCostperlead":"0","LeadspeekCostperleadAdvanced":"1.5","LeadspeekMinCostMonth":"0","LeadspeekPlatformFee":"0"}},"locator":{"Monthly":{"LocatorCostperlead":"0","LocatorMinCostMonth":"0","LocatorPlatformFee":"0"},"OneTime":{"LocatorCostperlead":"0","LocatorMinCostMonth":"0","LocatorPlatformFee":"0"},"Weekly":{"LocatorCostperlead":"0","LocatorMinCostMonth":"0","LocatorPlatformFee":"0"},"Prepaid":{"LocatorCostperlead":"0","LocatorMinCostMonth":"0","LocatorPlatformFee":"0"}},"locatorlead":{"FirstName_LastName":"1","FirstName_LastName_MailingAddress":"1","FirstName_LastName_MailingAddress_Phone":"1"},"enhance":{"Monthly":{"EnhanceCostperlead":"0","EnhanceMinCostMonth":"0","EnhancePlatformFee":"0"},"OneTime":{"EnhanceCostperlead":"0","EnhanceMinCostMonth":"0","EnhancePlatformFee":"0"},"Weekly":{"EnhanceCostperlead":"0","EnhanceMinCostMonth":"0","EnhancePlatformFee":"0"},"Prepaid":{"EnhanceCostperlead":"0","EnhanceMinCostMonth":"0","EnhancePlatformFee":"0"}},"b2b":{"Monthly":{"B2bCostperlead":"1.49","B2bMinCostMonth":"0","B2bPlatformFee":"0"},"OneTime":{"B2bCostperlead":"1.49","B2bMinCostMonth":"0","B2bPlatformFee":"0"},"Weekly":{"B2bCostperlead":"1.49","B2bMinCostMonth":"0","B2bPlatformFee":"0"},"Prepaid":{"B2bCostperlead":"1.49","B2bMinCostMonth":"0","B2bPlatformFee":"0"}},"simplifi":{"Prepaid":{"SimplifiMaxBid":"12","SimplifiDailyBudget":"5","SimplifiAgencyMarkup":"0"}}}', '8e651522e38256f2')) as rootagencydefaultprice_biasa,
TO_BASE64(AES_ENCRYPT('{"local":{"Monthly":{"LeadspeekCostperlead":"1.5","LeadspeekMinCostMonth":"0","LeadspeekPlatformFee":"0","LeadspeekCostperleadAdvanced":"2.39"},"OneTime":{"LeadspeekCostperlead":"2.1","LeadspeekMinCostMonth":"0","LeadspeekPlatformFee":"0","LeadspeekCostperleadAdvanced":"2.59"},"Weekly":{"LeadspeekCostperlead":"1.5","LeadspeekMinCostMonth":"0","LeadspeekPlatformFee":"0","LeadspeekCostperleadAdvanced":"2.29"},"Prepaid":{"LeadspeekCostperlead":"1.5","LeadspeekMinCostMonth":"0","LeadspeekPlatformFee":"0","LeadspeekCostperleadAdvanced":"2.49"}},"locator":{"Monthly":{"LocatorCostperlead":"2.2","LocatorMinCostMonth":"0","LocatorPlatformFee":"0"},"OneTime":{"LocatorCostperlead":"2.2","LocatorMinCostMonth":"0","LocatorPlatformFee":"0"},"Weekly":{"LocatorCostperlead":"2.1","LocatorMinCostMonth":"0","LocatorPlatformFee":"0"},"Prepaid":{"LocatorCostperlead":"2.2","LocatorMinCostMonth":"0","LocatorPlatformFee":"0"}},"locatorlead":{"FirstName_LastName":"1","FirstName_LastName_MailingAddress":"1","FirstName_LastName_MailingAddress_Phone":"2.2"},"enhance":{"Monthly":{"EnhanceCostperlead":"3.2","EnhanceMinCostMonth":"0","EnhancePlatformFee":"0"},"OneTime":{"EnhanceCostperlead":"2.3","EnhanceMinCostMonth":"0","EnhancePlatformFee":"0"},"Weekly":{"EnhanceCostperlead":"3.1","EnhanceMinCostMonth":"0","EnhancePlatformFee":"0"},"Prepaid":{"EnhanceCostperlead":"3.3","EnhanceMinCostMonth":"0","EnhancePlatformFee":"0"}},"b2b":{"Weekly":{"B2bCostperlead":"4.1","B2bMinCostMonth":"0","B2bPlatformFee":"0"},"Monthly":{"B2bCostperlead":"4.2","B2bMinCostMonth":"0","B2bPlatformFee":"0"},"OneTime":{"B2bCostperlead":"1.49","B2bMinCostMonth":"0","B2bPlatformFee":"0"},"Prepaid":{"B2bCostperlead":"4.3","B2bMinCostMonth":"0","B2bPlatformFee":"0"}}}', '8e651522e38256f2')) as agencydefaultprice_biasa

SELECT
	id,
	company_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) as setting_name,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_value), '8e651522e38256f2') USING utf8mb4) as setting_value,
	setting_name,
	setting_value
FROM company_settings
where company_id in (22,39) and CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) in ('rootcustomsidebarleadmenu');

SELECT
	id,
	company_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) as setting_name,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_value), '8e651522e38256f2') USING utf8mb4) as setting_value,
	setting_name,
	setting_value
FROM company_settings
where company_id in (22) and CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) in ('rootsetting','rootagencydefaultprice');

SELECT
	id,
	company_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) as setting_name,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_value), '8e651522e38256f2') USING utf8mb4) as setting_value,
	setting_name,
	setting_value
FROM company_settings
where company_id in (22) and CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) in ('rootagencydefaultprice','rootsetting','rootcostagency');

SELECT
	id,
	company_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) as setting_name,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_value), '8e651522e38256f2') USING utf8mb4) as setting_value,
	setting_name,
	setting_value
FROM company_settings
where company_id in (39) and CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) in ('rootagencydefaultprice','rootsetting','rootcostagency');


SELECT
	id,
	company_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) as setting_name,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_value), '8e651522e38256f2') USING utf8mb4) as setting_value,
	setting_name,
	setting_value
FROM company_settings
where company_id in (164) and CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) in ('agencydefaultprice','costagency');

SELECT
	id,
	company_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) as setting_name,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_value), '8e651522e38256f2') USING utf8mb4) as setting_value,
	setting_name,
	setting_value
FROM company_settings
where company_id in (169) and CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) in ('agencydefaultprice','costagency');

SELECT
	id,
	company_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) as setting_name,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_value), '8e651522e38256f2') USING utf8mb4) as setting_value,
	setting_name,
	setting_value
FROM company_settings
where company_id in (568) and CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) in ('agencydefaultprice','costagency');


SELECT
	id,
	company_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) as setting_name,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_value), '8e651522e38256f2') USING utf8mb4) as setting_value,
	setting_name,
	setting_value
FROM company_settings
where company_id in (165);


select * from jobs;
select * from feature_users;
select * from master_features;

select * from users where CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) in ('fisikamodern00+a16@gmail.com');
select * from users where CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) in ('fisikamodern00+jidanagencyemm25@gmail.com');


UPDATE company_settings SET setting_value = TO_BASE64(AES_ENCRYPT('{"DefaultModules":[{"type":"local","status":true},{"type":"locator","status":false},{"type":"enhance","status":true},{"type":"b2b","status":true}]}', '8e651522e38256f2')) 
WHERE company_id = 164 and CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) = 'agencydefaultmodules';


SELECT
	id,
	company_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) as setting_name,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_value), '8e651522e38256f2') USING utf8mb4) as setting_value,
	setting_name,
	setting_value
FROM company_settings
where 
	company_id in (22,39)
	and CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) in ('rootdefaultmodules','rootcustomsidebarleadmenu');

SELECT
	id,
	company_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) as setting_name,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_value), '8e651522e38256f2') USING utf8mb4) as setting_value,
	setting_name,
	setting_value
FROM company_settings
where 
	company_id in (164)
	;-- and CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) in ('agencysidebar','costagency','customsidebarleadmenu');

SELECT
	id,
	company_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) as setting_name,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_value), '8e651522e38256f2') USING utf8mb4) as setting_value,
	setting_name,
	setting_value
FROM company_settings
where 
	company_id in (165)
	
	
SELECT
	id,
	company_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) as setting_name,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_value), '8e651522e38256f2') USING utf8mb4) as setting_value,
	setting_name,
	setting_value
FROM company_settings
where 
	company_id in (164)
	;-- and CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) in ('agencysidebar')
	
select * from feature_users;
select * from services_agreement where user_id = 977;
	
SELECT
	id,
	company_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) as setting_name,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_value), '8e651522e38256f2') USING utf8mb4) as setting_value,
	setting_name,
	setting_value
FROM company_settings
where 
	company_id in (824);


select * from jobs;








