-- set @md5 := '7724702c27ec7ef274dceb2de46ca5e7'; -- b2b
-- set @md5 := '8bc64c9c289d18a05dd9652422208f05'; -- b2c
-- set @md5 := 'd7fbcb2bc68876df8f0cc41659e7d352'; -- b2c
-- set @md5 := '6ccbb5fa86ef8c1abb84f20314b322b0'; -- b2c
set @md5 := 'dc6637f4655a86f01212c3a608c2c749'; -- b2c
set @person_id := (select person_id from person_emails where email_encrypt = @md5);
set @key := '8e651522e38256f2';

select @person_id;

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
order by created_at desc limit 5;

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
where created_at > '2026-03-08 21:00:23' order by created_at desc limit 10;

select * from report_analytics where leadspeek_api_id = 88834454;

select * from leadspeek_reports where leadspeek_api_id = 4243136449;

select * from leadspeek_users where leadspeek_api_id = 95411278;

select * from jobs order by created_at desc limit 100;

select * from failed_lead_records order by id desc limit 10;

select * from module_settings;

select tfa_active, tfa_type, users.* from users where id = 65;


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
order by id desc limit 10;

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
order by id desc limit 10;


select * from clean_id_md5 where md5 = 'meghankelsey@gmail.com' order by id desc limit 10;
select * from failed_lead_records where email_encrypt = 'meghankelsey@gmail.com' order by id desc limit 20;

select * from global_settings order by id desc limit 5;

select * from bigdbm_count_history;

select * from job_progress where company_id = 22;





select * from job_progress where created_at > '2026-04-16 03:14:54' order by created_at desc;
select * from job_progress_chunks order by id asc;

select * from suppression_lists where leadspeek_api_id = 2599288139 and suppression_type = 'campaign' order by id desc; -- campaign
select * from suppression_lists where company_id = 582 and suppression_type = 'client' order by id desc; -- client
select * from suppression_lists where company_id = 581 and suppression_type = 'agency' order by id desc; -- agency
select * from optout_lists where company_root_id = 22 order by id desc; 

select * from jobs;
select * from failed_jobs where failed_at > '2026-03-16 03:27:04' order by failed_at desc;

select * from open_api_tokens where CONVERT(AES_DECRYPT(FROM_bASE64(token), '8e651522e38256f2') USING utf8mb4) in (
	'c0fd5529d91f518e911540f3316b2cf7c85e9a4cef9c9bcf28bc260f106fbd02950672d97fc1a1a983f630c5eafe8b3a72854b4180384ba1e46a0e5b890099bbb87dea71a5ae54eeec11e1d374e2d5e03b490bc5486adcdb7845c4b20a5a',
	'd0cff02e56ac1ec2d0a2ae8c998dc1d8d1f7a9ce0c90fd1291da56605d8e37ee95bd2edb7742100518a36732ec8f9252a369e848f284710b857b9d037a614ffc0050e89098fc235f918f77c3c36968b73cd1b729bbb411e1d3a36914'
);
















