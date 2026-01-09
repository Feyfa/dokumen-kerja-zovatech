UPDATE leadspeek_users SET active = 'F', disabled = 'T', active_user = 'F' WHERE leadspeek_api_id = 42817863;

SELECT
	id,person_id,lp_user_id,company_id,user_id,topup_id,leadspeek_api_id,invoice_id,
   CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) as email,
   CONVERT(AES_DECRYPT(FROM_bASE64(email2), '8e651522e38256f2') USING utf8mb4) as email2,
   original_md5,ipaddress,
   CONVERT(AES_DECRYPT(FROM_bASE64(source), '8e651522e38256f2') USING utf8mb4) as source,
   optindate,clickdate,
	CONVERT(AES_DECRYPT(FROM_bASE64(referer), '8e651522e38256f2') USING utf8mb4) as referer,
	CONVERT(AES_DECRYPT(FROM_bASE64(phone), '8e651522e38256f2') USING utf8mb4) as phone,
	CONVERT(AES_DECRYPT(FROM_bASE64(phone2), '8e651522e38256f2') USING utf8mb4) as phone2,
	CONVERT(AES_DECRYPT(FROM_bASE64(firstname), '8e651522e38256f2') USING utf8mb4) as firstname,
	CONVERT(AES_DECRYPT(FROM_bASE64(lastname), '8e651522e38256f2') USING utf8mb4) as lastname,
	CONVERT(AES_DECRYPT(FROM_bASE64(address1), '8e651522e38256f2') USING utf8mb4) as address1,
	CONVERT(AES_DECRYPT(FROM_bASE64(address2), '8e651522e38256f2') USING utf8mb4) as address2,
	CONVERT(AES_DECRYPT(FROM_bASE64(city), '8e651522e38256f2') USING utf8mb4) as city,
	CONVERT(AES_DECRYPT(FROM_bASE64(state), '8e651522e38256f2') USING utf8mb4) as state,
	CONVERT(AES_DECRYPT(FROM_bASE64(zipcode), '8e651522e38256f2') USING utf8mb4) as zipcode,
	price_lead,platform_price_lead,root_price_lead,keyword,description,active,lead_from,updated_at,created_at,encrypted,total_execution_time
FROM `leadspeek_reports` where leadspeek_api_id in ("42817863", "58191283", "73212713");

select * from person_emails where email_encrypt = '8dba134974fd2681332f7194e4e17711';

select * from leadspeek_reports where user_id = 282 and original_md5 = '8dba134974fd2681332f7194e4e17711';
select * from jobs;
select count(*) from persons;
select count(*) from persons_backup;

select
	(SELECT COUNT(*) FROM persons) AS persons,
    (SELECT COUNT(*) FROM persons_backup_1) AS persons_backup_1,
    (SELECT COUNT(*) FROM person_emails) AS person_emails,
    (SELECT COUNT(*) FROM person_emails_backup_1) AS person_emails_backup_1,
    (SELECT COUNT(*) FROM person_phones) AS person_phones,
    (SELECT COUNT(*) FROM person_phones_backup_1) AS person_phones_backup_1,
    (SELECT COUNT(*) FROM person_addresses) AS person_addresses,
    (SELECT COUNT(*) FROM person_addresses_backup_1) AS person_addresses_backup_1;  
--   



UPDATE leadspeek_reports 
SET leadspeek_api_id = 42817863, lp_user_id = 1240
WHERE id = 1;

SELECT 
    leadspeek_reports.id,
    leadspeek_reports.clickdate,
    leadspeek_reports.created_at
FROM 
    leadspeek_reports
LEFT JOIN 
    leadspeek_users ON leadspeek_reports.lp_user_id = leadspeek_users.id
WHERE 
    leadspeek_users.applyreidentificationall = 'T'
    AND leadspeek_users.archived = 'F'
    AND leadspeek_reports.company_id = 165
    AND leadspeek_reports.original_md5 = 'jidanganteng'
ORDER BY 
    leadspeek_reports.clickdate desc;
    
select * from leadspeek_reports where leadspeek_api_id = '42817863';

select * from person_emails where email_encrypt = '8dba134974fd2681332f7194e4e17711';

select * from jobs order by created_at desc;

