use emm_sandbox;
-- 
select 
	CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) as email,
	password,
	tfa_active,
	is_onboard_charged,
	amount_onboard_charged,
	users.* 
from users 
where CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) in (
	'fisikamodern00@gmail.com',
	'fisikamodern00+jidanagencyemm19@gmail.com',
	'fisikamodern00+jidanagencyemm20@gmail.com',
	'fisikamodern00+jidanagencyemm21@gmail.com',
	'fisikamodern00+jidanagencyemm22@gmail.com',
	'fisikamodern00+jidanagencyemm23@gmail.com',
	'fisikamodern00+jidanagencyemm24@gmail.com',
	'fisikamodern00+jidanagencyemm25@gmail.com',
	'fisikamodern00+jidanagencyemm26@gmail.com',
	'fisikamodern00+jidanagencyemm27@gmail.com',
	'fisikamodern00+jidanagencyemm28@gmail.com',
	'fisikamodern00+jidanagencyemm29@gmail.com',
	'fisikamodern00+jidanagencyemm30@gmail.com',
	'fisikamodern00+jidanagencyemm31@gmail.com',
	'fisikamodern00+jidanagencyemm33@gmail.com'
);

select CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) as email, users.* from users where company_id = 765;

select * from agreement_files;

select * from company_agreement_files where name = 'minimum_spend_v2';

select * from user_logs order by id desc limit 10;

SELECT
	id,
	company_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) as setting_name,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_value), '8e651522e38256f2') USING utf8mb4) as setting_value,
	setting_name,
	setting_value
FROM company_settings
WHERE company_id = 767

SELECT
	id,
	company_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) as setting_name,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_value), '8e651522e38256f2') USING utf8mb4) as setting_value,
	setting_name,
	setting_value
FROM company_settings
WHERE CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) = 'rootonboardingagency'




