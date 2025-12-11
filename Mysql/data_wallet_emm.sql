use emm_sandbox;
-- 
SET @company_id := '164', @leadspeek_api_id := '37362347';
select @company_id, @leadspeek_api_id;
-- 
update topup_agencies set balance_amount = 20, topup_status = 'progress' where total_amount = 20 AND company_id = @company_id;
update topup_agencies set balance_amount = 30, topup_status = 'queue' where total_amount = 30 AND company_id = @company_id;
update topup_agencies set balance_amount = 50, topup_status = 'progress' where total_amount = 50 AND company_id = @company_id;
--
select amount, last_balance_amount, users.* from users where CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) = 'fisikamodern00+jidanagencyemm3@gmail.com'; -- id = 618
select 
	amount, 
	stopcontinual,
	last_balance_amount,
	custom_amount,
	company_id,
	'============' as 'divider',
	users.* 
from users 
where company_id in (@company_id) and user_type = 'userdownline';
--
SELECT * FROM `topup_agencies` where company_id = @company_id order by id asc;
SELECT * FROM `leadspeek_invoices` where invoice_type = 'agency' and company_id = @company_id order by id asc;

select * from leadspeek_users where leadspeek_api_id = @leadspeek_api_id;
select * from topup_campaigns where leadspeek_api_id = @leadspeek_api_id;
select * from leadspeek_invoices where invoice_type = 'campaign' and leadspeek_api_id = @leadspeek_api_id;
update leadspeek_users set active = 'F', disabled = 'T', active_user = 'F' where leadspeek_api_id = @leadspeek_api_id;

select * from leadspeek_reports where leadspeek_api_id = @leadspeek_api_id;
select * from user_logs order by id desc limit 50;
select distinct action from user_logs order by action asc;

select * from module_settings;

select * from jobs;


SELECT 
    tc.id,
    tc.cost_perlead,
    tc.total_leads,
    tc.topup_status,
    li.customer_payment_id,
    li.id as invoice_id,
    li.leadspeek_api_id,
    li.invoice_type
FROM topup_campaigns AS tc
LEFT JOIN leadspeek_invoices AS li 
	ON (li.leadspeek_api_id = tc.leadspeek_api_id AND li.topup_campaign_id = tc.id)
    OR (li.leadspeek_api_id = tc.leadspeek_api_id and li.topup_campaign_id IS null AND li.cost_leads = tc.cost_perlead AND li.total_leads = tc.total_leads)
WHERE tc.leadspeek_api_id = '23024637'
 AND tc.topup_status <> 'done'
ORDER BY tc.id DESC;



select * from leadspeek_invoices where invoice_type = 'campaign' and topup_agencies_id in (
	SELECT id FROM `topup_agencies` where company_id = @company_id
);
--
select amount, stopcontinual, last_balance_amount, users.* from users where CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) = 'fisikamodern00+jidanach@gmail.com'; -- id = 279
--
select * from users where CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) = 'fisikamodern00+dimas@gmail.com'; -- id = 282
--
SET @leadspeek_api_id := '62919136 ';
select @leadspeek_api_id;
select active, disabled, active_user, leadspeek_type, leadspeek_users.* from leadspeek_users where leadspeek_api_id = @leadspeek_api_id;
update leadspeek_users set active = 'F', disabled = 'T', active_user = 'F' where leadspeek_api_id = @leadspeek_api_id;

select * from topup_campaigns where leadspeek_api_id = @leadspeek_api_id order by id asc;
select * from leadspeek_reports where leadspeek_api_id = @leadspeek_api_id;
select * from leadspeek_invoices where leadspeek_api_id = @leadspeek_api_id and invoice_type = 'campaign';
--
SELECT * FROM jobs;
SELECT * FROM failed_jobs;
--
select * from services_agreement 
where 
	user_id = 972 and 
	feature_id = (
		select id from master_features where slug = 'data_wallet'
	);

select * from services_agreement 
where 
	user_id = 618 and 
	feature_id = (
		select id from master_features where slug = 'data_wallet'
	);
--
select 
	CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4),
	password,
	tfa_active,
	users.* 
from users 
where company_id = 770 and user_type = 'userdownline';
-- CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) in ('fisikamodern00+jidanagencyemm41@gmail.com','fisikamodern00@gmail.com');


select active, disabled, active_user, lu.* from leadspeek_users as lu where leadspeek_api_id = 75935502;

INSERT INTO emm_sandbox.agreement_files
(name, url, created_at, updated_at)
VALUES('minimum_spend_v3', 'https://emmspaces.nyc3.cdn.digitaloceanspaces.com/docs/Updated%2010-07-25%20_%20Agency%20Marketing%20Services%20Agreement.html', NOW(), NOW());

select * from agreement_files;
select * from company_agreement_files where name = 'minimum_spend_v2';
select * from company_agreement_files order by id desc;
select * from user_logs order by id desc;
select * from services_agreement where user_id = 972 order by id desc;

select * from jobs order by id desc;




