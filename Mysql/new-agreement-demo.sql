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
	'fisikamodern00+dimas@gmail.com',
	'fisikamodern00+ahmad@gmail.com'
);

select 
	CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) as email_dec,
	email,
	password,
	tfa_active,
	users.* 
from users 
where CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) in (
	'fisikamodern00+dimas@gmail.com',
	'fisikamodern00+ahmad@gmail.com'
);






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
where company_id = 22 and active = 'T';


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


SET @ownedcompanyid := '';
SET @idsys := '';

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
	(
		-- check email ini sudah dipakai di platform ini atau belom		
		
	)
	or
	(
		-- check email ini sudah dipakai di root atau sales belom
	)




































