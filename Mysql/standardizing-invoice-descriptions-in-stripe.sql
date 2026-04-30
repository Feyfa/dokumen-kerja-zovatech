set @leadspeek_api_id := '18986942' , @company_id_agency := 164, @email = 'fisikamodern00+agency1777446094@gmail.com';

select active, disabled, active_user, leadspeek_users.* from leadspeek_users where leadspeek_api_id = @leadspeek_api_id;
select * from topup_campaigns where leadspeek_api_id = @leadspeek_api_id;
select * from leadspeek_invoices where leadspeek_api_id = @leadspeek_api_id and invoice_type = 'campaign';
select * from leadspeek_reports where leadspeek_api_id = @leadspeek_api_id;

select * from topup_agencies where company_id = @company_id_agency;
select * from leadspeek_invoices where company_id = @company_id_agency and invoice_type = 'agency';

select last_payment_update, last_invoice_minspend, users.* from users where CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) = @email and user_type = 'userdownline';

SET @last_payment_update := '2026-04-29 02:02:39';
SELECT 
	@last_payment_update as last_payment_update,
	DATE_ADD(@last_payment_update, INTERVAL 1 MONTH) as last_payment_update_next_1_month,
	DATE_ADD(@last_payment_update, INTERVAL 2 MONTH) as last_payment_update_next_2_month;