use emm_sandbox;
-- 
-- hapus agency
select * from users where CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) in (
	'support@exactmatchmarketing.com',
	'fisikamodern00+dimas@gmail.com'
);
select * from users where id in (861);
select * from companies where id in (695);
select * from company_settings where company_id in (695);

set @search := "fisikamodern00+ferry15@gmail.com";
set @key := "8e651522e38256f2";
select @key;
select 
	agency.id,
	agency.company_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(agency.email), '8e651522e38256f2') USING utf8mb4) as agency_email,
	CONVERT(AES_DECRYPT(FROM_bASE64(client.email), '8e651522e38256f2') USING utf8mb4) as client_email,
	CONVERT(AES_DECRYPT(FROM_bASE64(company_client.company_name), '8e651522e38256f2') USING utf8mb4) as company_name,
	leadspeek_users.leadspeek_api_id
from users as agency
join companies as company_agency on company_agency.id = agency.company_id
join users as client on client.company_parent = agency.company_id
left join companies as company_client on company_client.id = client.company_id
left join leadspeek_users on leadspeek_users.user_id = client.id
where
	agency.company_id = 164 and 
	agency.user_type = 'userdownline' and 
	(
		CONVERT(AES_DECRYPT(FROM_bASE64(company_client.email), @key) USING utf8mb4) like concat('%', @search, '%') or 
		CONVERT(AES_DECRYPT(FROM_bASE64(client.email), @key) USING utf8mb4) like concat('%', @search, '%') or 
		leadspeek_users.leadspeek_api_id like concat('%', @search, '%') or 
		CONVERT(AES_DECRYPT(FROM_bASE64(leadspeek_users.campaign_name), @key) USING utf8mb4) like concat('%', @search, '%')
	)
group by agency.id;



