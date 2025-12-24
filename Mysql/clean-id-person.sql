use emm_sandbox;
set @source := 'wattdata', @now_timestamp = '2025-12-17 00:00:00';

SELECT
	id,source,person_id_wattdata,uniqueID,
	CONVERT(AES_DECRYPT(FROM_bASE64(firstName), '8e651522e38256f2') USING utf8mb4) as firstName,
	CONVERT(AES_DECRYPT(FROM_bASE64(middleName), '8e651522e38256f2') USING utf8mb4) as middleName,
	CONVERT(AES_DECRYPT(FROM_bASE64(lastName), '8e651522e38256f2') USING utf8mb4) as lastName,
	age,identityScore,lastEntry,updated_at,created_at
FROM persons 
where source = @source and created_at >= @now_timestamp 
order by id desc;

SELECT CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) as email, person_emails.* FROM person_emails where source = @source and created_at >= @now_timestamp order by id desc;
SELECT CONVERT(AES_DECRYPT(FROM_bASE64(number), '8e651522e38256f2') USING utf8mb4) as number, person_phones.* FROM person_phones where source = @source and created_at >= @now_timestamp order by id desc;

SELECT 
	id,person_id,source,
	CONVERT(AES_DECRYPT(FROM_bASE64(street), '8e651522e38256f2') USING utf8mb4) as street,
	CONVERT(AES_DECRYPT(FROM_bASE64(unit), '8e651522e38256f2') USING utf8mb4) as unit,
	CONVERT(AES_DECRYPT(FROM_bASE64(city), '8e651522e38256f2') USING utf8mb4) as city,
	CONVERT(AES_DECRYPT(FROM_bASE64(state), '8e651522e38256f2') USING utf8mb4) as state,
	CONVERT(AES_DECRYPT(FROM_bASE64(zip), '8e651522e38256f2') USING utf8mb4) as zip,
	CONVERT(AES_DECRYPT(FROM_bASE64(fullAddress), '8e651522e38256f2') USING utf8mb4) as fullAddress,
	firstReportedDate,lastReportedDate,updated_at,created_at
FROM person_addresses 
where source = @source and created_at >= @now_timestamp
order by id desc;

SELECT * FROM person_names where source = @source and created_at >= @now_timestamp order by id desc;
SELECT * FROM person_advance_1 where source = @source and created_at >= @now_timestamp order by id desc;
SELECT * FROM person_advance_2 where source = @source and created_at >= @now_timestamp order by id desc;
SELECT * FROM person_advance_3 where source = @source and created_at >= @now_timestamp order by id desc;
SELECT * FROM person_b2b where source = @source and created_at >= @now_timestamp order by id desc;

select created_at, flr.* from failed_lead_records as flr where leadspeek_type = 'clean_id' order by created_at desc limit 20;
select * from failed_lead_records order by id desc limit 100;
select * from jobs;
select * from failed_jobs;
select * from user_logs where created_at >= @now_timestamp order by id desc limit 20;


SELECT 
	id,person_id,source,
	CONVERT(AES_DECRYPT(FROM_bASE64(street), '8e651522e38256f2') USING utf8mb4) as street,
	CONVERT(AES_DECRYPT(FROM_bASE64(unit), '8e651522e38256f2') USING utf8mb4) as unit,
	CONVERT(AES_DECRYPT(FROM_bASE64(city), '8e651522e38256f2') USING utf8mb4) as city,
	CONVERT(AES_DECRYPT(FROM_bASE64(state), '8e651522e38256f2') USING utf8mb4) as state,
	CONVERT(AES_DECRYPT(FROM_bASE64(zip), '8e651522e38256f2') USING utf8mb4) as zip,
	CONVERT(AES_DECRYPT(FROM_bASE64(fullAddress), '8e651522e38256f2') USING utf8mb4) as fullAddress,
	firstReportedDate,lastReportedDate,updated_at,created_at
FROM person_addresses 
where id = 14752
order by id desc;

select * from person_addresses where person_id = 7981;
select 
	CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) as email, 
	person_emails.* 
from person_emails 
where 
	CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) in (
		"leonardwest@hotmail.com"
-- 		"wizardofozfan@gmail.com",
-- 		"litlelori1968@gmail.com",
-- 		"walterzaldivar0610@yahoo.com",
-- 		"stanrmatthews@yahoo.com",
-- 		"garymacelwee@gmail.com",
-- 		"a172c778@gmail.com",
-- 		"miyatayy@gmail.com",
-- 		"judyhuber525@yahoo.com",
-- 		"corishearer.410@hotmail.com",
-- 		"lakechelanvet@yahoo.com",
-- 		"jsmith1038@aol.com",
-- 		"huangj@tampabay.rr.com",
-- 		"saradakodavati@gmail.com",
-- 		"ariepugh518@gmail.com",
-- 		"mpauley@montini.org",
-- 		"monicmack@yahoo.com",
-- 		"dan@dumperdan.com",
-- 		"jacqualine236@gmail.com",
-- 		"nitkhanna1@gmail.com"
	) and source = 'bigdbm'
order by id desc;


select 
	CONVERT(AES_DECRYPT(FROM_bASE64(token), '8e651522e38256f2') USING utf8mb4) as token_dec,
	sso_access_tokens.*
from sso_access_tokens
order by created_at desc;




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
select * from person_emails where id = 10172 and person_id = 7927;


select 
	CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) as email, 
	person_emails.* 
from person_emails order by id asc;