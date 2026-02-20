select * from jobs;

select * from leadspeek_reports order by created_at desc limit 1000;

select * from failed_lead_records
where `function` = 'getWattBasicInfo'
order by created_at desc
limit 20;

set @md5 := 'd7fbcb2bc68876df8f0cc41659e7d352';
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
where created_at > '2026-02-19 02:27:58' order by created_at desc limit 100; 


