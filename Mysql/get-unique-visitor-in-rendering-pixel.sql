use emm_sandbox;
set @leadspeek_api_id := '72770339';
SET @date_now := '2025-12-02 23:00:00';
select * from pixel_lead_records where created_at >= @date_now and leadspeek_api_id = @leadspeek_api_id order by id desc;
select  leadspeek_api_id, keyword, custom_params, lr.* from leadspeek_reports as lr where created_at >= @date_now and leadspeek_api_id = @leadspeek_api_id order by created_at desc;
select  leadspeek_api_id, keyword, custom_params, lr.* from leadspeek_reports as lr where leadspeek_api_id = @leadspeek_api_id order by created_at desc;
select  leadspeek_api_id, keyword, custom_params, lr.* from leadspeek_reports as lr where created_at >= @date_now and leadspeek_api_id = @leadspeek_api_id order by created_at desc;

select  leadspeek_api_id, keyword, lr.* from leadspeek_reports as lr where leadspeek_api_id = '81151983' order by id desc;

select * from leadspeek_reports where leadspeek_api_id = 81151983 order by created_at desc;
select * from module_settings;
select * from report_analytics;

select * from pixel_lead_records where DATE(created_at) >= '2025-11-11';
select * from pixel_lead_records where DATE(date_fire) >= '2025-11-11';
select * from report_analytics where DATE(date) >= '2025-11-11';

select * from pixel_lead_records as plr
where 
	DATE_FORMAT(plr.created_at,"%Y%m%d") >= '20251111'
	and DATE_FORMAT(plr.created_at,"%Y%m%d") <= '20251111'
	and pixel_status <> 'invalid_label';

select * from pixel_lead_records where lead_fire > 0 order by id desc limit 10;

select * from person_emails where email_encrypt = '8bc64c9c289d18a05dd9652422208f05';
select * from jobs;

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

select * from pixel_lead_records where pixel_status = 'invalid_label';

select * from clean_id_export;







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

delete FROM topup_agencies WHERE company_id = @company_id_jidanach;
delete FROM leadspeek_invoices WHERE invoice_type = 'clean_id' AND company_id = @company_id_jidanach;
delete from report_analytics where leadspeek_type = 'clean_id';
delete from failed_lead_records where leadspeek_type = 'clean_id';

select * from topup_agencies where company_id = @company_id_jidanach;

-- ===============UPDATE STATUS CAMPAIGN===============
update leadspeek_users set active = 'T', disabled = 'F', active_user = 'T' where leadspeek_api_id = @leadspeek_api_id;
-- ===============UPDATE STATUS CAMPAIGN===============

-- ===============SELECT===============
select start_billing_date, lp_invoice_date,active,disabled,active_user,leadspeek_users.* from leadspeek_users where leadspeek_api_id = @leadspeek_api_id;
select * from topup_campaigns where leadspeek_api_id = @leadspeek_api_id order by id asc;
select * from leadspeek_invoices where leadspeek_api_id = @leadspeek_api_id order by id asc;
select * from leadspeek_reports where leadspeek_api_id = @leadspeek_api_id order by id asc;

select * from topup_agencies where company_id = (select company_id from leadspeek_users where leadspeek_api_id = @leadspeek_api_id) order by id asc;
select * from leadspeek_invoices where company_id = (select company_id from leadspeek_users where leadspeek_api_id = @leadspeek_api_id) and invoice_type = 'agency' order by id asc;
-- ===============SELECT===============

-- ===============VARIABLE===============
SET @total_leads := 200;
SET @leadspeek_api_id := '65129841';

set @cost_per_contact := 0.1;
set @total_cost_agency := @cost_per_contact * @total_leads;

SET @last_id_topup_agencies := (
    SELECT id
    FROM topup_agencies
    WHERE company_id = (
        SELECT company_id
        FROM leadspeek_users
        WHERE CAST(leadspeek_api_id AS CHAR) = CAST(@leadspeek_api_id AS CHAR)
        LIMIT 1
    )
    ORDER BY id DESC
    LIMIT 1
);

select @total_leads, @leadspeek_api_id, @cost_per_contact, @total_cost_agency, @last_id_topup_agencies;
-- ===============VARIABLE===============


-- ===============TOPUP_CAMPAIGNS===============
INSERT INTO topup_campaigns
(
	user_id,
	lp_user_id,
	company_id,
	leadspeek_api_id,
	leadspeek_type,
	topupoptions,
	advance_information,
	campaign_information_type_local,
	platformfee,
	cost_perlead,
	lp_limit_leads,
	lp_min_cost_month,
	total_leads,
	balance_leads,
	platform_price,
	root_price,
	treshold,
	payment_amount,
	active,
	stop_continue,
	last_cost_perlead,
	last_limit_leads_day,
	topup_status,
	ip_user,
	timezone,
	updated_at,
	created_at
)
SELECT
	user_id,
	lp_user_id,
	company_id,
	leadspeek_api_id,
	leadspeek_type,
	topupoptions,
	advance_information,
	campaign_information_type_local,
	platformfee,
	cost_perlead,
	@total_leads as lp_limit_leads,
	lp_min_cost_month,
	@total_leads AS total_leads, -- diubah manual
	@total_leads AS balance_leads, -- diubah manual
	platform_price,
	root_price,
	@total_leads AS treshold, -- diubah manual
	payment_amount,
	active,
	stop_continue,
	last_cost_perlead,
	last_limit_leads_day,
	CASE WHEN (SELECT COUNT(*) FROM topup_campaigns WHERE leadspeek_api_id = @leadspeek_api_id AND topup_status = 'done') = 0 THEN 'queue' ELSE 'progress' END AS topup_status,
	ip_user,
	timezone,
	NOW(),
	NOW()
FROM topup_campaigns
WHERE leadspeek_api_id = @leadspeek_api_id
ORDER BY id DESC
LIMIT 1;
-- ===============TOPUP_CAMPAIGNS===============


-- ===============LEADSPEEK_INVOICES===============
INSERT INTO leadspeek_invoices (
    id,
    invoice_type,
    topup_agencies_id,
    budget_plan_id,
    payment_type,
    company_id,
    user_id,
    leadspeek_api_id,
    invoice_number,
    payment_term,
    onetimefee,
    platform_onetimefee,
    min_leads,
    exceed_leads,
    total_leads,
    min_cost,
    platform_min_cost,
    cost_leads,
    platform_cost_leads,
    frequency_capping_impressions,
    frequency_capping_hours,
    max_bid,
    monthly_budget,
    daily_budget,
    goal_type,
    goal_value,
    agency_markup,
    total_amount,
    platform_total_amount,
    root_total_amount,
    status,
    customer_payment_id,
    customer_stripe_id,
    customer_card_id,
    platform_customer_payment_id,
    error_payment,
    platform_error_payment,
    invoice_date,
    invoice_start,
    invoice_end,
    sent_to,
    sr_id,
    sr_fee,
    sr_transfer_id,
    ae_id,
    ae_fee,
    ae_transfer_id,
    ar_id,
    ar_fee,
    ar_transfer_id,
    campaigns_paused,
    active,
    updated_at,
    created_at
)
SELECT
    NULL,
    invoice_type,
    topup_agencies_id,
    budget_plan_id,
    payment_type,
    company_id,
    user_id,
    leadspeek_api_id,
    invoice_number, -- ganti yang ini
    payment_term,
    onetimefee,
    platform_onetimefee,
    min_leads,
    exceed_leads,
    @total_leads as total_leads, -- ganti total_leads
    min_cost,
    platform_min_cost,
    cost_leads,
    @cost_per_contact as platform_cost_leads,
    frequency_capping_impressions,
    frequency_capping_hours,
    max_bid,
    monthly_budget,
    daily_budget,
    goal_type,
    goal_value,
    agency_markup,
    total_amount,
    @total_cost_agency as platform_total_amount,
    root_total_amount,
    status,
    customer_payment_id,
    customer_stripe_id,
    customer_card_id,
    platform_customer_payment_id,
    error_payment,
    platform_error_payment,
    DATE(NOW()) as invoice_date,
    DATE(NOW()) as invoice_start,
    DATE(NOW()) as invoice_end,
    sent_to,
    sr_id,
    sr_fee,
    sr_transfer_id,
    ae_id,
    ae_fee,
    ae_transfer_id,
    ar_id,
    ar_fee,
    ar_transfer_id,
    campaigns_paused,
    active,
    NOW(),
    NOW()
FROM leadspeek_invoices
WHERE leadspeek_api_id = @leadspeek_api_id
ORDER BY id DESC
LIMIT 1;
-- ===============LEADSPEEK_INVOICES===============


-- ===============TOPUP_CAMPAIGNS===============
UPDATE topup_agencies
SET balance_amount = balance_amount - @total_cost_agency
WHERE id = @last_id_topup_agencies;
-- ===============TOPUP_CAMPAIGNS===============


select total_leads, lu.* from leadspeek_users as lu where leadspeek_api_id = '33445017';

select created_at, clickdate, ls.* from leadspeek_reports as ls where leadspeek_api_id = '33445017' order by created_at desc;





