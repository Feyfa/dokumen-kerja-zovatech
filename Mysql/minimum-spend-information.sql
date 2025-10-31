select 
	CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) as email,
	api_mode,
	month_developer_minspend,
	plan_minspend_id,
	last_payment_update,
	last_invoice_minspend,
	'=========' as divider,
	users.* 
from users 
where 
	CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) in (
-- 		'fisikamodern00+jidanagencyemm54@gmail.com',
		'fisikamodern00+jidanach@gmail.com',
		'fisikamodern00+jidanagencyemm3@gmail.com',
		'fisikamodern00+jidanagencyemm56@gmail.com'
	);

select * from topup_agencies where company_id = 164;
select * from leadspeek_invoices where company_id = 164 and invoice_type = 'agency';

--

set @now_chicago := '2026-01-29 00:00:00';
select 
	@now_chicago as now,
	DATE_ADD(@now_chicago, INTERVAL 2 MONTH) as last_invoice_minspend_1,
	DATE_ADD(LAST_DAY(@now_chicago) + INTERVAL 1 DAY, INTERVAL 2 MONTH) as last_invoice_minspend_2;
--

SET @last_payment_update := '2025-10-30 05:05:06';
SELECT 
	@last_payment_update as last_payment_update,
	DATE_ADD(@last_payment_update, INTERVAL 1 MONTH) as last_payment_update_next_1_month,
	DATE_ADD(@last_payment_update, INTERVAL 2 MONTH) as last_payment_update_next_2_month;


select DATE_FORMAT('2025-01-15 01:00:00','%Y%m%d%H%i%s') >= '20250115';

SELECT
	id,
	company_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) as setting_name,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_value), '8e651522e38256f2') USING utf8mb4) as setting_value,
	setting_name,
	setting_value
FROM company_settings
WHERE company_id = 22;

select created_at, updated_at, topup_campaigns.* from topup_campaigns where leadspeek_api_id = 45551055;