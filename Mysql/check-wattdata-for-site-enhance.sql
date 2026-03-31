select * from jobs;

select * from leadspeek_reports order by created_at desc limit 1000;

select * from failed_lead_records
where `function` = 'getWattBasicInfo'
order by created_at desc
limit 20;

set @md5 := '7724702c27ec7ef274dceb2de46ca5e7'; -- b2b
-- set @md5 := 'd7fbcb2bc68876df8f0cc41659e7d352'; -- b2c
-- set @md5 := '8bc64c9c289d18a05dd9652422208f05'; -- b2c
set @person_id := (select person_id from person_emails where email_encrypt = @md5);
set @key := '8e651522e38256f2';

select @person_id; -- 10089

select
    id,
    person_id,
    source,
    CONVERT(AES_DECRYPT(FROM_BASE64(email), @key) USING utf8mb4) as email,
    email_encrypt,
    permission,
    zbvalidate,
    updated_at,
    created_at
from person_emails
order by created_at desc limit 50;

-- person_emails
select
    id,
    person_id,
    source,
    CONVERT(AES_DECRYPT(FROM_BASE64(email), @key) USING utf8mb4) as email,
    email_encrypt,
    permission,
    zbvalidate,
    updated_at,
    created_at
from person_emails
where email_encrypt = @md5;

-- persons
select
    id,
    source,
    person_id_wattdata,
    uniqueID,
    CONVERT(AES_DECRYPT(FROM_BASE64(firstName), @key) USING utf8mb4) as firstName,
    middleName,
    CONVERT(AES_DECRYPT(FROM_BASE64(lastName), @key) USING utf8mb4) as lastName,
    age,
    identityScore,
    lastEntry,
    updated_at,
    created_at
from persons
where id = @person_id;

-- person_phones
select
    id,
    person_id,
    source,
    CONVERT(AES_DECRYPT(FROM_BASE64(number), @key) USING utf8mb4) as number,
    type,
    isConnected,
    firstReportedDate,
    lastReportedDate,
    permission,
    updated_at,
    created_at
from person_phones
where person_id = @person_id;

-- person_names
select
    id,
    person_id,
    source,
    CONVERT(AES_DECRYPT(FROM_BASE64(first_name), @key) USING utf8mb4) as first_name,
    middle_name,
    CONVERT(AES_DECRYPT(FROM_BASE64(last_name), @key) USING utf8mb4) as last_name,
    endato_result,
    updated_at,
    created_at
from person_names
where person_id = @person_id;

-- person_addresses
select
    id,
    person_id,
    source,
    CONVERT(AES_DECRYPT(FROM_BASE64(street), @key) USING utf8mb4) as street,
    CONVERT(AES_DECRYPT(FROM_BASE64(unit), @key) USING utf8mb4) as unit,
    CONVERT(AES_DECRYPT(FROM_BASE64(city), @key) USING utf8mb4) as city,
    CONVERT(AES_DECRYPT(FROM_BASE64(state), @key) USING utf8mb4) as state,
    CONVERT(AES_DECRYPT(FROM_BASE64(zip), @key) USING utf8mb4) as zip,
    CONVERT(AES_DECRYPT(FROM_BASE64(fullAddress), @key) USING utf8mb4) as fullAddress,
    firstReportedDate,
    lastReportedDate,
    updated_at,
    created_at
from person_addresses
where person_id = @person_id;

select * from person_advance_1 where person_id = @person_id;
select * from person_advance_2 where person_id = @person_id;
select * from person_advance_3 where person_id = @person_id;
select * from person_b2b where person_id = @person_id;

select 
	CONVERT(AES_DECRYPT(FROM_BASE64(email), @key) USING utf8mb4) as email,
	flr.* 
from failed_lead_records as flr 
where created_at > '2026-03-06 05:07:00' order by created_at desc limit 100;


UPDATE person_addresses
set street = TO_BASE64(AES_ENCRYPT('1870 Eagle Ridge Dr Apt 10', '8e651522e38256f2'))
where id = 14654;


select * from leadspeek_reports where leadspeek_api_id = 84140504 order by created_at desc;
select * from jobs order by id desc;

select * from module_settings;

select * from report_analytics where created_at > '2026-03-09 00:02:58' order by id desc limit 5;

select CONVERT(AES_DECRYPT(FROM_BASE64(token), '8e651522e38256f2') USING utf8mb4) as token, oat.* from open_api_tokens oat where company_id = 164;

select 
	id,
	company_root_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) as email_decrypt,
	email,
	emailmd5,
	filename,
	blockedcategory,
	description,
	updated_at,
	created_at
from optout_lists
order by id desc limit 100;



select  
	id,
	lead_userid,
	company_id,
	leadspeek_api_id,
	suppression_type,
	CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) as email_decrypt,
	email,
	emailmd5,
	reidentification_date,
	updated_at,
	created_at
from suppression_lists
order by id desc limit 100;













SELECT 
	id,
	company_root_id,
	email,
	CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) as email_dec,
	emailmd5,
	filename,
	blockedcategory,
	description,
	updated_at,
	created_at
FROM `optout_lists` 
order by id desc limit 100;

SELECT
	* 
FROM `suppression_lists` 
where suppression_type = 'agency'
order by id desc limit 100;



select * from jobs;
select * from clean_id_md5 where md5 in ('meghankelsey@gmail.com', 'ec76c246b9602f11fec9a29b5addaa9c') order by id desc;
select * from failed_lead_records order by id desc limit 10;

select * from person_emails where CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) = 'meghankelsey@gmail.com' order by id desc;



























