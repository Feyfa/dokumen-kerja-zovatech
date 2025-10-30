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
		'fisikamodern00+jidanagencyemm54@gmail.com',
		'fisikamodern00+jidanach@gmail.com'
	);

set @now_chicago := '2025-10-30 05:12:03';
select 
	DATE_ADD(@now_chicago, INTERVAL 2 MONTH) as last_invoice_minspend_1,
	DATE_ADD(LAST_DAY(@now_chicago) + INTERVAL 1 DAY, INTERVAL 2 MONTH) as last_invoice_minspend_1;

SET @last_payment_update := '2025-10-30 05:05:06';
SELECT 
	@last_payment_update as last_payment_update,
	DATE_ADD(@last_payment_update, INTERVAL 1 MONTH) as last_payment_update_next_1_month,
	DATE_ADD(@last_payment_update, INTERVAL 2 MONTH) as last_payment_update_next_2_month;