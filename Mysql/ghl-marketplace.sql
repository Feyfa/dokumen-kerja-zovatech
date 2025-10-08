use emm_sandbox;
--
select * from users where CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) in ('fisikamodern00+jidanach@gmail.com','fisikamodern00+jidanagencyemm1@gmail.com','fisikamodern00+jidanagencyemm2@gmail.com','fisikamodern00+jidanagencyemm3@gmail.com') and user_type = 'userdownline';
set @id := 279;
set @company_id = 164;
select * from users where id = @id;
select * from companies where id = @company_id;
select * from company_settings where company_id = @company_id;
--
select * from global_auth_tokens where user_id = @id;
select * from oauth_access_tokens where user_id = @id;
--
delete from users where id = @id;
delete from companies where id = @company_id;
delete from company_settings where company_id = @company_id;

-- $2y$10$3e2yPoML.ZaGGVB8/drAg.e11ZJj5uYn254ucyjC6eioTj0M3UoQq
-- 755
select * from sso_access_tokens where user_id = 797;
select 
	u.id,
	u.company_id,
	u.company_parent,
	u.company_root_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(u.email), '8e651522e38256f2') USING utf8mb4) as email,
	u.user_type,
	c.ghl_company_id,
	c.ghl_tokens,
	c.ghl_custom_menus,
	c.ghl_credentials_id,
	g.setting_name,
	g.setting_value
from users as u
join companies as c on c.id = u.company_id
left join global_settings g on g.id = c.ghl_credentials_id
where u.user_type in ('userdownline') and CONVERT(AES_DECRYPT(FROM_bASE64(u.email), '8e651522e38256f2') USING utf8mb4) in (
	'muhammadjidan703@gmail.com', 
	'support@exactmatchmarketing.com'
);
select * from users where id in (906);
select * from companies where id in (164);
select * FROM company_settings WHERE company_id in (735);
SELECT
	id,
	company_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) as setting_name,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_value), '8e651522e38256f2') USING utf8mb4) as setting_value,
	setting_name,
	setting_value
FROM company_settings
WHERE company_id in (639);
--
DELETE FROM users WHERE id IN (906);
DELETE FROM companies WHERE id IN (740);
DELETE FROM company_settings WHERE company_id IN (740);
--
select * from global_settings where setting_name in ('credentials_ghl_emm_private', 'credentials_ghl_emm_public') order by id asc;
select * from jobs;
--
select * from sso_access_tokens;
select *, CONVERT(AES_DECRYPT(FROM_bASE64(token), '8e651522e38256f2') USING utf8mb4) as token from sso_access_tokens
where CONVERT(AES_DECRYPT(FROM_bASE64(token), '8e651522e38256f2') USING utf8mb4) in (
	'0f5636ac760c142b319ffebff53b58e56c89d87ce5b3e481c92f359111901e22650ee3ab07d8fd789a8552b7df86402cfcbce7112bcbe86d8f92349020c1c5acbb4f53704dcaf90c7abeba3323004961105a236475e40b1a4aebe33af44b4fe77d657951c2f783b5c53c6a572c43',
	'a11884ed65459ddd5a36804f95c5f8c523ebfc266555dc99057a79d0d6b247a434ef69825fde4f71280cb28f47dec8ea334c27abdea292b365d730b8b737a2b8a5ffdc055b354be4ab79e6f6495832fc5417cee088aa5d51ed3eb6f2f5c1e1a53c560e2f3ca58cd553d06f8d57',
	'715917ef2fbcddcd431b67206bbf628eecd445ce561dbba1029d9da1c2adcd41ca84cb0570032942ad77cbe7df65a5af3987e178f848c6ccf7fd2692420a0fd7bcba092eafafe7f6cf0a1c9018cea1035a7db6f219d6a223f561e589f31f53acbe79a63d7545',
);
select *, CONVERT(AES_DECRYPT(FROM_bASE64(token), '8e651522e38256f2') USING utf8mb4) as token from sso_access_tokens
where CONVERT(AES_DECRYPT(FROM_bASE64(token), '8e651522e38256f2') USING utf8mb4) in (
	'ae09e4cf47f279a6b7716034f02689cd6fe985605fc78b4a1b371cec896b7ac1e7a4c6a29d1aaf419c4aa9a93ecff8edd61563641403e17c7de557b8a51930193bda8861bc05fbef89e989e787141671b393df2d952c498bdb23c62c4e1ee4b8b0dee555d73f',
	'faadd5eb5308c175621175bc9719d876be6f98824ae7de1169c8390233361781ac3bfa864631cbde6aad0c0d27f9afebe4658e6d3ad54b4675ba681e161bc419748588e50841b486f704a235776bebfbd722960d7089223cb970329602bdb292317641e0f710d70f8cc5'
);


select *, CONVERT(AES_DECRYPT(FROM_bASE64(token), '8e651522e38256f2') USING utf8mb4) as token from open_api_tokens 
where CONVERT(AES_DECRYPT(FROM_bASE64(token), '8e651522e38256f2') USING utf8mb4) in (
	'03d9e838a0445c4b075f6a34fc73806c4f7e4219320bec2b4d3756430e16b4a400137bde648161ff5a33065b39803f4f4976af56682d54285433f038676b4f6e127d3b24c869cc25595125fd20ccfac63dbc9d23a0724a4e1a3ee5f3',
	'2cd0926b01ea5c593d42d6babeaee05e59ada22c5c1941e0c5284133e5ba92a8a576ddf93c1ae998ff3b52e9ed42655fb197bbcd4976abe327214e60fd2a8fd710c73daf08828856ad391976541b00d1282a57ee3d70f60a0f9d6aeb',
	'ffe1584abef8c2c2f0f32bd4883f3bd65f7fb74a2078840ef62456795d4016e9ad42717b9455b4b2382d61f4b7f4ec1298094fc63cafbc23470029da50e3b5361997b2296eadb3e11c06626fd6365e85de736f37bec42f21501002e5bcb8d645',
	'7a1c201b94418184f568a1612a1c67ca66fc87045013634a22510df14d479d9236dbf4d4df8adb111d261286edc21220168a7d913a3e8611e1f2e51c9256cf1a91ca08389d0149336c9af63af4079fb81da393427b472c89c3029ce22bd37a31cf29',
	'a18613a300a6cb54a8e4d784e7c1102c33fefa1e3fd29ecd1364e9f7f5656b302e11f605c30de192460844d0574f7770eedc319486c262b67b5017f70057ff9e5595ed469f4f960bdeba8301f81f6c7f9c21a3c74372b30277550795b8a7c40be97de9d3'
);
select * from open_api_tokens where id = 283;
--
select * from users where CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) in ('fisikamodern00+timothy@gmail.com','fisikamodern00+kalimasad@gmail.com');
select * from companies_integration_settings where company_id in (566,660);
select * from companies where id in (566,660);
--
select * from user_logs 
where (action like '%Gohigh%' or action like '%connector%') and DATE(created_at) = '2025-07-31'
order by id ;
--

select * from users where CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) = 'fisikamodern00+kalimasada5@gmail.com' and user_type = 'client';
select *, tfa_active from users where id = 837;
select *, CONVERT(AES_DECRYPT(FROM_bASE64(company_name), '8e651522e38256f2') USING utf8mb4) from companies where id = 165;
select * from companies where id = 0;

select active, CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) as email from users where user_type = 'client' and company_parent = 164 and company_id is null;


select * from users where CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) = 'fisikamodern00+ferry2_update@gmail.com';
select *, tfa_active from users where id = 834;
select *, CONVERT(AES_DECRYPT(FROM_bASE64(company_name), '8e651522e38256f2') USING utf8mb4) from companies where id = 674;

select * from sso_access_tokens;
--
select * from users where id = 860;
select * from companies where id = 694;
select * from company_settings where company_id = 694;
select * from leadspeek_users where user_id = 860;

select CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) as email_dec, user_type, users.* from users where company_id = 736;

select tfa_active, users.* from users where CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) = 'fisikamodern00+alexandrwang@gmail.com';
select CONVERT(AES_DECRYPT(FROM_bASE64(companies.company_name), '8e651522e38256f2') USING utf8mb4) as company_name, companies.* from companies where id  = 742;
select * from company_settings where company_id = 741;



SELECT
	id,
	company_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) as setting_name,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_value), '8e651522e38256f2') USING utf8mb4) as setting_value,
	setting_name,
	setting_value
FROM company_settings
WHERE 
		(
			company_id = 22 and CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) = 'rootsmtp'
		) or
		(
			company_id = 164 and CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) = 'customsmtpmenu'
		);

SELECT
	id,
	company_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) as setting_name,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_value), '8e651522e38256f2') USING utf8mb4) as setting_value,
	setting_name,
	setting_value
FROM company_settings
WHERE company_id = 164 and CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) = 'customsmtpmenu';


select * from users where company_id = 22;
select * from users where company_id = 164;
select * from users where company_id = 165;

select companies_integration_settings.* from companies_integration_settings where company_id = 742 and integration_slug = 'gohighlevel';


SELECT id
FROM (
    SELECT 'MJ Sub Account 2' AS id
    UNION ALL
    SELECT 'Rafeyfa Zulfiyani' AS id
) AS t
ORDER BY id ASC;


select * from jobs;

select * from companies where id in (
	select company_id from users where company_parent = 164 and user_type = 'client' and active = 'T'
);

select * from companies where id = 164;
select * from global_settings where company_id = 22;
select 
	CONVERT(AES_DECRYPT(FROM_bASE64(token), '8e651522e38256f2') USING utf8mb4) as token, 
	sat.* 
from sso_access_tokens as sat 
where 
	user_id = 279  
	and CONVERT(AES_DECRYPT(FROM_bASE64(token), '8e651522e38256f2') USING utf8mb4) in (
		'8e4781c04d288bd0c1a0c697a0d1b3c738fd46567a7e1bbf2bfff54fb6db016218e26b274e913c1a9632f9163a27eb3dae0ff508bc9815c5884141d57f0b37050a5b0e222472d421755afbe74e64d00537cc82ac0663fbd3efc2dccc5ab714e7f8a62f58610362418e'
	);


select CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) as email, u.* from users as u 
where
	CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) in (
		'fisikamodern00+jidanach@gmail.com',
		'fisikamodern00+fena@gmail.com',
		'harrison+bestsales@exactmatchmarketing.com',
		'fisikamodern00+adminroot1update@gmail.com',
		'fisikamodern00+dimas@gmail.com',
		'fisikamodern00+juukid@gmail.com',
		'fisikamodern00+a1759205606@gmail.com'
	) and 
	(
		(
			user_type = 'client' and
			company_parent <> 164
		) or
		(
			user_type <> 'client'
		)
	);

SELECT
	id,
	company_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) as setting_name,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_value), '8e651522e38256f2') USING utf8mb4) as setting_value,
	setting_name,
	setting_value
FROM company_settings
where company_id in (22,39) and CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) = 'rootsetting';

select * from companies where id = 165;

SELECT
	id,
	company_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) as setting_name,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_value), '8e651522e38256f2') USING utf8mb4) as setting_value,
	setting_name,
	setting_value
FROM company_settings
where CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) = 'customsmtpmenu' and company_id in (
	164,169
	-- 	select company_id from users where company_parent is null
);

select * from users where CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) in ('support@exactmatchmarketing.com','fisikamodern00+sandboxexampleghl@gmail.com');


select CONVERT(AES_DECRYPT(FROM_bASE64(company_name), '8e651522e38256f2') USING utf8mb4) as company_name from companies where id = 167;

update users
SET 
	phonenum = TO_BASE64(AES_ENCRYPT('081322992122', '8e651522e38256f2')),
	phone_country_code = TO_BASE64(AES_ENCRYPT('ID', '8e651522e38256f2')),
	phone_country_calling_code = TO_BASE64(AES_ENCRYPT('1', '8e651522e38256f2'))
where company_id = 165;

select 
	CONVERT(AES_DECRYPT(FROM_bASE64(phonenum), '8e651522e38256f2') USING utf8mb4) as phonenum,
	CONVERT(AES_DECRYPT(FROM_bASE64(phone_country_code), '8e651522e38256f2') USING utf8mb4) as phone_country_code,
	CONVERT(AES_DECRYPT(FROM_bASE64(phone_country_calling_code), '8e651522e38256f2') USING utf8mb4) as phone_country_calling_code,
	users.* 
from users where company_id = 165;


select CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) as email, users.* from users 
where 
	company_parent = 164 and
	user_type = 'client' and
	CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) in ('fisikamodern00+exactmatchmarketing@gmail.com','support@exactmatchmarketing.com','fisikamodern00+leadboosterghl@gmail.com','fisikamodern00+sandboxexampleghl@gmail.com');

select * from company_settings where company_id in (
	select company_id from users 
	where 
		company_parent = 164 and
		user_type = 'client' and
		CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) in ('fisikamodern00+exactmatchmarketing@gmail.com','support@exactmatchmarketing.com','fisikamodern00+leadboosterghl@gmail.com','fisikamodern00+sandboxexampleghl@gmail.com')
);

select * from companies where id in (
	select company_id from users 
	where 
		company_parent = 164 and
		user_type = 'client' and
		CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) in ('fisikamodern00+exactmatchmarketing@gmail.com','support@exactmatchmarketing.com','fisikamodern00+leadboosterghl@gmail.com','fisikamodern00+sandboxexampleghl@gmail.com')
);





select CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) as email, users.* from users
where 
	company_parent = 164 and
	user_type = 'client' and
	CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) like '%fisikamodern00+a%';

select * from company_settings where company_id in (
	select company_id from users 
	where 
		company_parent = 164 and
		user_type = 'client' and
		CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) like '%fisikamodern00+a%'
);

select * from companies where id in (
	select company_id from users 
	where 
		company_parent = 164 and
		user_type = 'client' and
		CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) like '%fisikamodern00+a%'
);

select * from jobs;

select *, CONVERT(AES_DECRYPT(FROM_bASE64(token), '8e651522e38256f2') USING utf8mb4) as token from sso_access_tokens
where CONVERT(AES_DECRYPT(FROM_bASE64(token), '8e651522e38256f2') USING utf8mb4) in ('ec59cb813e161b44773319adf68ca322dabe0114669c12c918c11ae9ea6ab36b10097296bc36b85946238bff0f418184447767417f15d58aa3070dc1a933d6f1bc07e146b184a5dda4c5e3fd289821e4a4562e3b5fba5c12acccdf192fe23f2b35ca7f24bb');






SELECT
	id,
	company_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) as setting_name,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_value), '8e651522e38256f2') USING utf8mb4) as setting_value,
	setting_name,
	setting_value
FROM company_settings
where company_id in (566);

select * from companies_integration_settings where company_id = 566;

select * from global_settings where company_id = 566;

select * from module_settings;





