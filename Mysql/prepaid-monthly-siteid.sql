use emm_sandbox;

set @leadspeek_api_id := '43919974';
set @leadspeek_api_id := '39294217';

set @lp_invoice_date := '2025-09-08 08:22:55';
select @leadspeek_api_id, @lp_invoice_date;

select * from topup_agencies where company_id = 464 order by id asc;
select * from leadspeek_invoices where company_id = 464 and invoice_type = 'agency' order by id asc;

select 
	CONVERT(AES_DECRYPT(FROM_bASE64(campaign_name), '8e651522e38256f2') USING utf8mb4) as campaign_name,
	active,
	disabled,
	active_user,
	stopcontinual,
	last_balance_leads,
	leadspeek_api_id,
	start_billing_date,
	lp_invoice_date,
	'===============' as pembatas,
	lu.*
from leadspeek_users as lu
where leadspeek_api_id = @leadspeek_api_id;

select * 
from leadspeek_reports
where leadspeek_api_id = @leadspeek_api_id
order by created_at asc;

select invoice_date, leadspeek_invoices.* 
from leadspeek_invoices 
where leadspeek_api_id = @leadspeek_api_id
order by id asc;

select * 
from topup_campaigns
where leadspeek_api_id = @leadspeek_api_id
order by id asc;

select payment_status, users. * from users where CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) = 'fisikamodern00+dimas@gmail.com';
select payment_status, users. * from users where CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) = 'fisikamodern00+jidanagencyemm3@gmail.com';

select DATE(DATE_ADD(@lp_invoice_date, INTERVAL 1 MONTH)) as next_month;

select (DATE(DATE_ADD(NULL, INTERVAL 1 MONTH)) <= "2025-02-02");

-- start_billing_date awal 2025-09-08 00:00:00