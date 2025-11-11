select * from pixel_lead_records order by id desc;

select * from leadspeek_users where leadspeek_api_id = 81151983;

select * from report_analytics;

select * from pixel_lead_records where DATE(created_at) >= '2025-11-11';
select * from pixel_lead_records where DATE(date_fire) >= '2025-11-11';
select * from report_analytics where DATE(date) >= '2025-11-11';

select * from pixel_lead_records as plr
where 
	DATE_FORMAT(plr.created_at,"%Y%m%d") >= '20251111'
	and DATE_FORMAT(plr.created_at,"%Y%m%d") <= '20251111'
	and pixel_status <> 'invalid_label';



-- mode all
-- untuk total visitor adalah count dari hasil ini
select 
    COUNT(CASE WHEN plr.campaign_status = 'running' THEN 1 END) AS total_running,
    COUNT(CASE WHEN plr.campaign_status = 'stopped' THEN 1 END) AS total_stopped,
    COUNT(CASE WHEN plr.campaign_status = 'paused' THEN 1 END) AS total_paused,
    COUNT(CASE WHEN plr.campaign_status = 'paused_on_run' THEN 1 END) AS total_paused_on_run
from pixel_lead_records as plr
join leadspeek_users as lu on plr.leadspeek_api_id = lu.leadspeek_api_id
join users as u on lu.user_id = u.id
where 
	plr.visitor_id IS NOT NULL 
	and plr.visitor_id <> ''
	and u.company_root_id = 22
	and DATE_FORMAT(plr.created_at,"%Y%m%d") >= '20251101'
	and DATE_FORMAT(plr.created_at,"%Y%m%d") <= '20251106';
	
-- mode grouping
-- untuk total visitor adalah count dari hasil ini
select 
	plr.visitor_id,		
	COUNT(DISTINCT CASE WHEN plr.campaign_status = 'running' THEN plr.campaign_status END) AS total_running,
	COUNT(DISTINCT CASE WHEN plr.campaign_status = 'stopped' THEN plr.campaign_status END) AS total_stopped,
	COUNT(DISTINCT CASE WHEN plr.campaign_status = 'paused' THEN plr.campaign_status END) AS total_paused,
	COUNT(DISTINCT CASE WHEN plr.campaign_status = 'paused_on_run' THEN plr.campaign_status END) AS total_paused_on_run
	
    COUNT(CASE WHEN plr.campaign_status = 'running' THEN 1 END) AS total_running,
    COUNT(CASE WHEN plr.campaign_status = 'stopped' THEN 1 END) AS total_stopped,
    COUNT(CASE WHEN plr.campaign_status = 'paused' THEN 1 END) AS total_paused,
    COUNT(CASE WHEN plr.campaign_status = 'paused_on_run' THEN 1 END) AS total_paused_on_run
from pixel_lead_records as plr
join leadspeek_users as lu on plr.leadspeek_api_id = lu.leadspeek_api_id
join users as u on lu.user_id = u.id
where 
	plr.visitor_id IS NOT NULL 
	and plr.visitor_id <> ''
	and DATE_FORMAT(plr.created_at,"%Y%m%d") >= '20251105' 
	and DATE_FORMAT(plr.created_at,"%Y%m%d") <= '20251105' 
	and u.company_root_id = 22
	and plr.visitor_id = 'ksTEM01xcnccA3N1mKEZ0iJ21762335434114'
group by visitor_id;

-- count empty
select COUNT(*)
from pixel_lead_records as plr
join leadspeek_users as lu on plr.leadspeek_api_id = lu.leadspeek_api_id
join users as u on lu.user_id = u.id
where 
	DATE_FORMAT(plr.created_at,"%Y%m%d") >= '20251105' 
	and DATE_FORMAT(plr.created_at,"%Y%m%d") <= '20251105' 
	and u.company_root_id = 22
	and (plr.visitor_id = '' or plr.visitor_id is null);

-- count feedback
select COUNT(*)
from pixel_lead_records as plr
join leadspeek_users as lu on plr.leadspeek_api_id = lu.leadspeek_api_id
join users as u on lu.user_id = u.id
where 
	DATE_FORMAT(plr.created_at,"%Y%m%d") >= '20251105' 
	and DATE_FORMAT(plr.created_at,"%Y%m%d") <= '20251105' 
-- 	and u.company_root_id = 22
	and plr.lead_fire > 0 
	and plr.visitor_id <> '' 
	and plr.visitor_id is not null;

select * from pixel_lead_records;



