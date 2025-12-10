SELECT * FROM leadspeek_users WHERE leadspeek_api_id = 72770339;\

select * from companies_integration_settings where company_id = 165;

select * from leadspeek_invoices where leadspeek_api_id = '72770339' order by id desc;

select * from leadspeek_reports lr where leadspeek_api_id = '81151983' and lr.created_at >= '2025-11-24 03:41:17';

-- 2025-11-24 03:41:17

select * from leadspeek_reports where leadspeek_api_id = '72770339';



-- getLifeTimeTotalLeads
SELECT 
	leadspeek_reports.platform_price_lead,
	leadspeek_reports.price_lead
--     COUNT(*) AS total_leads_last_billing,
--     SUM(leadspeek_reports.platform_price_lead) AS agency_total_cost_since_last_billing,
--     SUM(leadspeek_reports.price_lead) AS client_total_cost_since_last_billing
FROM leadspeek_reports
LEFT JOIN leadspeek_users 
       ON leadspeek_reports.leadspeek_api_id = leadspeek_users.leadspeek_api_id
LEFT JOIN users 
       ON leadspeek_users.user_id = users.id
WHERE leadspeek_users.archived = 'F'
  AND users.active = 'T'
  AND leadspeek_reports.active = 'T'
  AND leadspeek_users.company_id = 164
  AND leadspeek_users.leadspeek_type = 'local'
  and leadspeek_users.user_id = 1072;
--   AND leadspeek_reports.created_at >= '2025-11-30 22:53:41


-- getLastBillingTotalLeads 1.1
select leadspeek_invoices.leadspeek_api_id, leadspeek_invoices.created_at from leadspeek_invoices
left join leadspeek_users on leadspeek_invoices.leadspeek_api_id = leadspeek_users.leadspeek_api_id
left join users on leadspeek_invoices.user_id = users.id
where
	leadspeek_users.archived = 'F' and 
	users.active = 'T' and
	leadspeek_users.company_id = 164 and 
	leadspeek_users.leadspeek_type = 'local' and
	leadspeek_users.user_id = '282' and
-- 	leadspeek_users.leadspeek_api_id = '47730235'  
	leadspeek_invoices.status = 'paid'
order by created_at desc;



-- getLastBillingTotalLeads 1.2
SELECT 
-- 	leadspeek_reports.platform_price_lead,
-- 	leadspeek_reports.price_lead
--     COUNT(*) AS total_leads_last_billing,
--     SUM(leadspeek_reports.platform_price_lead) AS agency_total_cost_since_last_billing,
--     SUM(leadspeek_reports.price_lead) AS client_total_cost_since_last_billing
FROM leadspeek_reports
LEFT JOIN leadspeek_users 
       ON leadspeek_reports.leadspeek_api_id = leadspeek_users.leadspeek_api_id
LEFT JOIN users 
       ON leadspeek_users.user_id = users.id
WHERE leadspeek_users.archived = 'F'
  AND users.active = 'T'
  AND leadspeek_reports.active = 'T'
  AND leadspeek_users.company_id = 164
  AND leadspeek_users.leadspeek_type = 'local'
  and leadspeek_users.user_id = 1072;
--   AND leadspeek_reports.created_at >= '2025-11-30 22:53:41



select id, clickdate from leadspeek_reports where leadspeek_api_id = 29954795;

	