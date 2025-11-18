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
-- 	'fisikamodern00+jidanagencyemm19@gmail.com',
-- 	'fisikamodern00+jidanagencyemm20@gmail.com',
-- 	'fisikamodern00+jidanagencyemm21@gmail.com',
-- 	'fisikamodern00+jidanagencyemm22@gmail.com',
-- 	'fisikamodern00+jidanagencyemm23@gmail.com',
-- 	'fisikamodern00+jidanagencyemm24@gmail.com',
-- 	'fisikamodern00+jidanagencyemm25@gmail.com',
-- 	'fisikamodern00+jidanagencyemm26@gmail.com',
-- 	'fisikamodern00+jidanagencyemm27@gmail.com',
-- 	'fisikamodern00+jidanagencyemm28@gmail.com',
-- 	'fisikamodern00+jidanagencyemm29@gmail.com',
-- 	'fisikamodern00+jidanagencyemm30@gmail.com',
-- 	'fisikamodern00+jidanagencyemm31@gmail.com',
-- 	'fisikamodern00+jidanagencyemm33@gmail.com',
	'fisikamodern00+jidanagencyemm42@gmail.com',
	'fisikamodern00+jidanagencydom7@gmail.com',
	'fisikamodern00+jidanagencyemm43@gmail.com',
	'fisikamodern00+jidanagencyemm44@gmail.com',
	'fisikamodern00+jidanagencyemm50@gmail.com'
);

select CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) as email, users.* from users where company_id = 765;

select * from agreement_files;

select * from company_agreement_files where company_id = 870;

select * from user_logs order by id desc limit 10;

select * from jobs;

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

select * from oauth_access_tokens order by created_at desc limit 5;

select 
	CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) as email_dec,
	email,
	password,
	tfa_active,
	users.* 
from users 
where CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) in (
	'chou@yopmail.com'
);

select 
	CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) as email_dec,
	user_type,
	company_id,
	password,
	tfa_active,
	users.* 
from users 
where company_id = 164 and active = 'T';






select 
	CONVERT(AES_DECRYPT(FROM_bASE64(url_code), '8e651522e38256f2') USING utf8mb4) as url_code,
	lu.* 
from leadspeek_users as lu
where leadspeek_api_id = 83117508;

select 
	CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) as email,
	user_type,
	company_id,
	company_parent,
	company_root_id,
	active,
	"============" as divider,
	users.* 
from users 
where company_id in (164) and active = 'T';


$chkEmailExist = User::where('email',Encrypter::encrypt($chkusrname))
 ->where(function ($query) use ($ownedcompanyid) {
    $query->where(function ($query) use ($ownedcompanyid) {
        $query->whereIn('user_type',['user','userdownline'])
              ->where('company_id',$ownedcompanyid)
              ->where('active','T');
    })->orWhere(function ($query) use ($ownedcompanyid) {
        $query->where('user_type','client')
              ->where('company_parent',$ownedcompanyid)
              ->where('active','T');
    });
 })
 ->get();


-- fisikamodern00@gmail.com
-- harrison+bestsales@exactmatchmarketing.com
-- daniel+testsales1041524@danielswick.com
-- jedundun314+root@gmail.com
-- fisikamodern00+juukid@gmail.com
-- fisikamodern00+jidansales@gmail.com
-- fisikamodern00+jidanroot2@gmail.com
-- fisikamodern00+jidansales2@gmail.com
-- fisikamodern00+jidansales3@gmail.com
-- fisikamodern00+jidansales4@gmail.com
-- fisikamodern00+adminroot1update@gmail.com
-- fisikamodern00+jidansales5@gmail.com
-- fisikamodern00+jidansales6update@gmail.com
-- fisikamodern00+rootdom@gmail.com
-- fisikamodern00+jidandomsales@gmail.com

SET @ownedcompanyid := 164, @idsys = 22, @email := 'chou@yopmail.com';
select 
	user_type,
	company_id,
	company_parent,
	company_root_id,
	active,
	"============" as divider,
	users.* 
from users 
where 
	active = 'T' and
	CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) = @email and
	(
	 	(
	 		-- check email platform/domain itu sendiri, sudah dipakai oleh agency, admin agency, client belum
	 		user_type in ('userdownline', 'user', 'client') and 
			(company_id = @ownedcompanyid or company_parent = @ownedcompanyid)
		)
		or
		(
			-- check email ini sudah dipakai di root atau sales belom
			user_type in ('userdownline', 'user', 'sales') and
			company_id = @idsys
		)
	);

select domain from companies where id = 22;






SELECT * FROM transkrip_nilai WHERE nim = '2301001';


UPDATE mahasiswa
SET alamat = 'Bandung'
WHERE nim = '2301001';

2025-11-10 - 2025-11-11


select SUM(pixelfire) from report_analytics
where 
	DATE_FORMAT(date,"%Y%m%d") >= '20251110' and
	DATE_FORMAT(date,"%Y%m%d") >= ''
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
use emm_sandbox;
SET @company_id_jidanach := 164;
	
TRUNCATE TABLE clean_id_errors;
TRUNCATE TABLE clean_id_export;
TRUNCATE TABLE clean_id_file;
TRUNCATE TABLE clean_id_md5;
TRUNCATE TABLE clean_id_result;
TRUNCATE TABLE clean_id_result_advance_1;
TRUNCATE TABLE clean_id_result_advance_2;
TRUNCATE TABLE clean_id_result_advance_3;
TRUNCATE TABLE topup_cleanids;

delete FROM topup_agencies WHERE company_id = @company_id_jidanach;
delete FROM leadspeek_invoices WHERE invoice_type = 'clean_id' AND company_id = @company_id_jidanach;
delete from report_analytics where leadspeek_type = 'clean_id';
delete from failed_lead_records where leadspeek_type = 'clean_id';

ALTER TABLE `clean_id_file`
ADD COLUMN `clean_api_id` VARCHAR(20) DEFAULT NULL AFTER `id`;


-- 43937231 ini yang bener di buat dari ui
-- 92830739,17726346 ini yang bug dari open api
update leadspeek_users set active = 'F', disabled = 'T', active_user = 'F' where leadspeek_api_id in (43937231,92830739,17726346);
select * from topup_campaigns where leadspeek_api_id in (43937231,92830739,17726346);
select * from leadspeek_invoices where leadspeek_api_id in (43937231,92830739,17726346);



-- https://muhammadjidan.emmsandbox.com/sso?token=9bc9fd58a3d4aca4749a73f97c9f2f0844b2546814e41eb1b5c6e90ca468edb879948c96221b408f6193acb4a14c45c752b4b4db9343b5dc28abb4acd49b6cbbc756972e199ad3e86438c975d41a597dd59b2d50aa82da185133180edd62e8272f42b561578fd5eb3774ea4a34
-- https://jidanach.emmsandbox.com/sso?token=7b53c5732f96d1eca16289003e452fde875128a0935fc4c74a7fa6def20c93a8902053c907bb76b901dc2dbe788c9c048ac0cef1f0e8d9b886f61c9d299566ffddae61a39ed1ea5606e894e4e9d4dded7ff9da0f6b67673b2c3e829ec99a02e97f25442ecdc8719a8beab1da
-- http://jidanach.emmsandbox.com:8080/sso?token=7b53c5732f96d1eca16289003e452fde875128a0935fc4c74a7fa6def20c93a8902053c907bb76b901dc2dbe788c9c048ac0cef1f0e8d9b886f61c9d299566ffddae61a39ed1ea5606e894e4e9d4dded7ff9da0f6b67673b2c3e829ec99a02e97f25442ecdc8719a8beab1da
-- https://jidanach.emmsandbox.com/sso?token=d9bc611acf806fd833770de617408c5a4a22072d3453a6619993174e8b4ff992a2c2ec93ffd2699773287a9dda9c68ffcd13059514aa0e89e9ecd005c7869016ce5c2c31f40a3ff4482ab18419328b2309bee85ca62b8a981880b02a0a892b03b5da2f05b8143e43876377078181
select * 
from sso_access_tokens 
where
	CONVERT(AES_DECRYPT(FROM_bASE64(token),'8e651522e38256f2') USING utf8mb4) = 'd9bc611acf806fd833770de617408c5a4a22072d3453a6619993174e8b4ff992a2c2ec93ffd2699773287a9dda9c68ffcd13059514aa0e89e9ecd005c7869016ce5c2c31f40a3ff4482ab18419328b2309bee85ca62b8a981880b02a0a892b03b5da2f05b8143e43876377078181'
	and user_id = 279;

select *,CONVERT(AES_DECRYPT(FROM_bASE64(token),'8e651522e38256f2') USING utf8mb4) from open_api_tokens where company_id = 22;

select * from companies_integration_settings where company_id = 165 and integration_slug = 'gohighlevel';
SELECT
	id,
	company_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) as setting_name,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_value), '8e651522e38256f2') USING utf8mb4) as setting_value,
	setting_name,
	setting_value
FROM company_settings
WHERE company_id = 165 and CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) = 'gohlcustomfields';
select * from global_settings where company_id = 165 and setting_name like '%gohighlevel%';


select * from leadspeek_users where company_id = 164 and user_id = 282 and archived = 'F' and leadspeek_type = 'local' order by id desc;


select * from module_settings where company_id = 164;
select * from failed_lead_records where blocked_type = 'googlesheet' and leadspeek_api_id = 45636124 order by id desc;
select * from jobs;
select * from failed_jobs;


select * from topup_agencies where company_id = 164;
select * from leadspeek_invoices where topup_agencies_id in ( 
	select id from topup_agencies where company_id = 164
) and invoice_type = 'campaign';


















