--
SET @company_id_jidanagencyemm4 = 587;
select @company_id_jidanagencyemm4;
SELECT *, active FROM `users` where CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) = 'fisikamodern00+jidanagencyemm4@gmail.com';
select *, active from users where company_id  = @company_id_jidanagencyemm4;
select * from companies where id = @company_id_jidanagencyemm4;
select * from company_settings where company_id = @company_id_jidanagencyemm4;
select * from company_stripes where company_id = @company_id_jidanagencyemm4;

select *, archived, leadspeek_type from leadspeek_users where company_id = @company_id_jidanagencyemm4;
select *, active from users where company_parent = @company_id_jidanagencyemm4;

select * from topup_agencies where company_id = 464;
select * from leadspeek_invoices where company_id = 464 and invoice_type = 'agency';
--
SELECT 
	* ,
	CONVERT(AES_DECRYPT(FROM_BASE64(acc_connect_id), '8e651522e38256f2') USING utf8mb4) AS acc_connect_id,
	CONVERT(AES_DECRYPT(FROM_BASE64(acc_email), '8e651522e38256f2') USING utf8mb4) AS acc_email,
	CONVERT(AES_DECRYPT(FROM_BASE64(acc_ba_id), '8e651522e38256f2') USING utf8mb4) AS acc_ba_id,
	status_acc
FROM `users` where CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) = 'fisikamodern00+jidansales@gmail.com';
--
SET @id_jidansales2 = 739;
select @id_jidansales2;
SELECT 
	* ,
	CONVERT(AES_DECRYPT(FROM_BASE64(email), '8e651522e38256f2') USING utf8mb4) AS email,
	CONVERT(AES_DECRYPT(FROM_BASE64(acc_connect_id), '8e651522e38256f2') USING utf8mb4) AS acc_connect_id,
	CONVERT(AES_DECRYPT(FROM_BASE64(acc_email), '8e651522e38256f2') USING utf8mb4) AS acc_email,
	CONVERT(AES_DECRYPT(FROM_BASE64(acc_ba_id), '8e651522e38256f2') USING utf8mb4) AS acc_ba_id,
	status_acc
FROM `users` where CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) in ('fisikamodern00+jidansales2@gmail.com','fisikamodern00+jidansales3@gmail.com') order by id desc;
select * from users where id = @id_jidansales2;
--
select * from global_auth_tokens;
--
SET @company_id_ahmad= 170;
select @company_id_ahmad;
SELECT *, customer_payment_id, customer_card_id FROM `users` where CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) = 'fisikamodern00+ahmad@gmail.com';
--
SELECT 
	id, company_id,
	CONVERT(AES_DECRYPT(FROM_BASE64(acc_connect_id), '8e651522e38256f2') USING utf8mb4) AS acc_connect_id,
	CONVERT(AES_DECRYPT(FROM_BASE64(acc_prod_id), '8e651522e38256f2') USING utf8mb4) AS acc_prod_id,
	CONVERT(AES_DECRYPT(FROM_BASE64(acc_email), '8e651522e38256f2') USING utf8mb4) AS acc_email,
	CONVERT(AES_DECRYPT(FROM_BASE64(acc_ba_id), '8e651522e38256f2') USING utf8mb4) AS acc_ba_id,
	CONVERT(AES_DECRYPT(FROM_BASE64(acc_holder_name), '8e651522e38256f2') USING utf8mb4) AS acc_holder_name,
	CONVERT(AES_DECRYPT(FROM_BASE64(ba_name), '8e651522e38256f2') USING utf8mb4) AS ba_name,
	CONVERT(AES_DECRYPT(FROM_BASE64(ba_route), '8e651522e38256f2') USING utf8mb4) AS ba_route,
	CONVERT(AES_DECRYPT(FROM_BASE64(package_id), '8e651522e38256f2') USING utf8mb4) AS package_id,
	CONVERT(AES_DECRYPT(FROM_BASE64(subscription_id), '8e651522e38256f2') USING utf8mb4) AS subscription_id,
	CONVERT(AES_DECRYPT(FROM_BASE64(subscription_item_id), '8e651522e38256f2') USING utf8mb4) AS subscription_item_id,
	plan_date_created, plan_next_date, status_acc,
	ipaddress, created_at, updated_at
FROM company_stripes
WHERE company_id IN (169, 164, @company_id_jidanagencyemm4);
--
SET @leadspeek_api_id1 = '64172624';
select @leadspeek_api_id1;
select *, paymentterm from leadspeek_users where leadspeek_api_id = @leadspeek_api_id1;
select * from topup_campaigns where leadspeek_api_id = @leadspeek_api_id1;
select * from leadspeek_reports where leadspeek_api_id = @leadspeek_api_id1;
select * from leadspeek_invoices where leadspeek_api_id = @leadspeek_api_id1;
update leadspeek_users set active = 'F', disabled = 'T', active_user = 'F' where leadspeek_api_id = @leadspeek_api_id1;
--
SET @leadspeek_api_id2 = '39042399';
select @leadspeek_api_id2;
select * from leadspeek_users where leadspeek_api_id = @leadspeek_api_id2;
select * from topup_campaigns where leadspeek_api_id = @leadspeek_api_id2;
select * from leadspeek_reports where leadspeek_api_id = @leadspeek_api_id2;
select * from leadspeek_invoices where leadspeek_api_id = @leadspeek_api_id2;
update leadspeek_users set active = 'T', disabled = 'F', active_user = 'T' where leadspeek_api_id = @leadspeek_api_id2;
--
SELECT
	id,
	company_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) as setting_name,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_value), '8e651522e38256f2') USING utf8mb4) as setting_value,
	setting_name,
	setting_value
FROM company_settings
WHERE company_id = 22;
--
select * from users where CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) = 'fisikamodern00+dimas@gmail.com';
select * from users where id = 282;










