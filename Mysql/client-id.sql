use emm_sandbox;
-- 
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
update topup_agencies set balance_amount = 1, topup_status = 'progress' where total_amount = 1 AND company_id = @company_id_jidanach;
update topup_agencies set balance_amount = 2, topup_status = 'queue' where total_amount = 2 AND company_id = @company_id_jidanach;
update topup_agencies set balance_amount = 3, topup_status = 'queue' where total_amount = 3 AND company_id = @company_id_jidanach;
update topup_agencies set balance_amount = 4, topup_status = 'queue' where total_amount = 4 AND company_id = @company_id_jidanach;
update topup_agencies set balance_amount = 5, topup_status = 'queue' where total_amount = 5 AND company_id = @company_id_jidanach;
update topup_agencies set balance_amount = 10, topup_status = 'queue' where total_amount = 10 AND company_id = @company_id_jidanach;
delete FROM topup_agencies WHERE company_id = @company_id_jidanach;
delete FROM leadspeek_invoices WHERE invoice_type = 'clean_id' AND company_id = @company_id_jidanach;
delete from report_analytics where leadspeek_type = 'clean_id';
delete from failed_lead_records where leadspeek_type = 'clean_id';



--


select * from clean_id_errors; -- untuk menampung error
select * from clean_id_export; -- untuk download
select * from clean_id_file; -- untuk container md5 atau sekali process api
select * from clean_id_md5; -- untuk list md5 sekali process api
select * from clean_id_result; -- untuk result basic
select * from clean_id_result_advance_1; -- untuk result advanced 1
select * from clean_id_result_advance_2; -- untuk result advanced 2
select * from clean_id_result_advance_3; -- untuk result advanced 3
select * from topup_cleanids; -- untuk topup_cleandids
--
SET @company_id_jidanach := 164;
SELECT * FROM `topup_agencies` where company_id = @company_id_jidanach order by id asc;
SELECT * FROM `leadspeek_invoices` where invoice_type = 'agency' and company_id = @company_id_jidanach order by id asc;
select * from leadspeek_invoices where invoice_type = 'clean_id' and company_id = @company_id_jidanach order by id asc;

select * from leadspeek_invoices order by id desc;

select * from leadspeek_invoices where id = 3345;

select * from report_analytics order by id desc;
select * from failed_lead_records;

select * from user_logs where target_user_id = 279 order by id desc limit 10;
--
select * from jobs order by id desc;
select * from failed_jobs;
select * from failed_lead_records where leadspeek_type = 'clean_id';
select * from report_analytics where leadspeek_type = 'clean_id' order by id desc;
--
select * from persons where FIND_IN_SET(id, @person_ids);
select * from persons where id = 7832;
select CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) as email, person_emails.* from person_emails where email_encrypt in (
	"78e63b99eaa18437f46b56cea8e7b220"
	"8bc64c9c289d18a05dd9652422208f05",
	"8dba134974fd2681332f7194e4e17711",
	"6d30f2e60dcdab0ce437e9c4066b1082",
	"df19e366cfa7f01166b4929fb35fc37f",
	"1600844aa311fd8df30c0e6b2cc3ad51",
	"697f116c75fbeaf384e33df8f86516e4",
	"512ab114b277e3b4aad9fb5eb9b0cdf8",
	"3a60885ca65f5be99d14e17933220aaa",
	"d18fa68148cf6bdb08db8c8228d0616d",
	"77923622fd09a3f6ef187d82971ba3cd",
	"3d06deaa536581946ba9dd665232fe09",
	"7cf9e71b7cef1edcb3c786b8f04b4b92",
	"cf75592604f42a98883f86918100fdf3",
    "e1f8fc7ce39f7799b0542416aa3eceb2",
    "d264abd0a46ce4d6a9c1a1fc9deaf2f7",
    "1bd323891a53cda51c80d729f3b15455",
    "78347e6ed4cd3fed7fb7a1e8d53538aa",
    "93b76b90c2f8e1d433832d0a00c999f9",
    "a72acb561a6b078895468c756c7a6efa",
	"dd2124afa8ab78aae179b61ae8604bb5"
) order by id desc;
select *, CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4)  from person_emails where person_id = 7793;
SET @person_ids := '7885,7880,7878,7875,7872,7869,7859,7858,7857,7856,7855,7854,7853,7850,7849,7848,7847,7844,7843,7842,7841,7840,7839,7838,7836,7827,7818,7803';
select @person_ids;
select * from person_names where FIND_IN_SET(person_id, @person_ids);
select * from person_phones where FIND_IN_SET(person_id, @person_ids);
select * from person_addresses where FIND_IN_SET(person_id, @person_ids);
select * from person_advance_1 where FIND_IN_SET(person_id, @person_ids);
select * from person_advance_2 where FIND_IN_SET(person_id, @person_ids);
select * from person_advance_3 where FIND_IN_SET(person_id, @person_ids);
--
delete from persons where FIND_IN_SET(id, @person_ids);
delete from person_emails where FIND_IN_SET(person_id, @person_ids);
delete from person_names where FIND_IN_SET(person_id, @person_ids);
delete from person_phones where FIND_IN_SET(person_id, @person_ids);
delete from person_addresses where FIND_IN_SET(person_id, @person_ids);
delete from person_advance_1 where FIND_IN_SET(person_id, @person_ids);
delete from person_advance_2 where FIND_IN_SET(person_id, @person_ids);
delete from person_advance_3 where FIND_IN_SET(person_id, @person_ids);
--
SET @company_id_jidanach := 164;
select amount, last_balance_amount, users.* from users where CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) = 'fisikamodern00+jidanach@gmail.com';
select *, cost_cleanid_advanced from users where company_id in (@company_id_jidanach) and user_type = 'userdownline';
--
select * from failed_lead_records where leadspeek_api_id = '1234567890' order by id desc;
select 
	id,
	company_id ,
	user_api_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(token), '8e651522e38256f2') USING utf8mb4) as token,
	expired_at,
	created_at,
	updated_at
from open_api_tokens where company_id= @company_id_jidanach and expired_at = '2752215596';
--
select * from users where CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) = 'fisikamodern00+josep@gmail.com';
SELECT
	id,
	company_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) as setting_name,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_value), '8e651522e38256f2') USING utf8mb4) as setting_value,
	setting_name,
	setting_value
FROM company_settings
WHERE company_id = 622;
--
select * from failed_jobs;
--
select * from services_agreement where user_id = 279 and feature_id = (select id from master_features where slug = 'data_wallet');
-- 
select * from global_settings where setting_name = 'bigdbm_token';

select * from topup_cleanids;

select * from person_emails where email_encrypt in ('8bc64c9c289d18a05dd9652422208f05');


-- id	user_id	file_id	total_jobs	jobs_completed	status	file_temporary	file_download	created_at	updated_at

select * from leadspeek_report

select * from module_settings;

select * from failed_lead_records where status = 'risky';
select * from report_analytics order by id desc;

select CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) as email, person_emails.* from person_emails where email_encrypt in (
	'cef25be7b7d76ccf990dbe19e2254205',
	'd9c785ec9dc2500f1c15015d97bdf661'
);

select {semua kolom di $select}
from `clean_id_file` as `f`
inner join `clean_id_md5` as `m` on `m`.`file_id` = `f`.`id`
left join `clean_id_result` as `r` on `r`.`file_id` = `m`.`file_id` and `r`.`md5_id` = `m`.`id`
left join `clean_id_result_advance_1` as `a1` on `a1`.`file_id` = `m`.`file_id` and `a1`.`md5_id` = `m`.`id`
where `f`.`id` = ?

select * from services_agreement where feature_id = 4 and user_id = 279;
select * from feature_users;
select * from master_features;


select * from user_logs order by id desc limit 100;
                     																															                       | jika failed maka ini ngga muncul                        |	
source type : ui | file type : manual | title/file name : testing | company id : 164 | advanced information : 1,2,3,4 | cost per contact : $ 0.2 | total entries : 100 | clean api id : 1234 | cost agency : $ 10 | invoice id : | 

Clean ID Submit Manual From UI
Clean ID Submit Upload From UI
Clean ID Submit Manual From UI Failed
Clean ID Submit Upload From UI Failed

Clean ID Submit Manual From API
Clean ID Submit Upload From API
Clean ID Submit Manual From API Failed
Clean ID Submit Upload From API Failed

select * from module_settings;

ALTER TABLE `clean_id_export`
ADD COLUMN `app_url` VARCHAR(255) NULL DEFAULT NULL AFTER `status`;


SELECT
	id,
	company_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) as setting_name,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_value), '8e651522e38256f2') USING utf8mb4) as setting_value,
	setting_name,
	setting_value
FROM company_settings
WHERE company_id = 22;






select * from companies_integration_settings where integration_slug = 'gohighlevel' and company_id in (
	select company_id from users where company_parent = 164 and user_type = 'client' and company_id is not null and company_id <> '' and active = 'T'
);

select CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) as email from users where company_id = 904 and user_type = 'client';

select * from module_settings;

select * from jobs;

leonardwest@hotmail.com


52
251

austin.samber@gmail.com








