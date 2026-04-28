select * from migrations;

select * from contacts;
select * from contact_lists;
select * from contact_list_contacts;

select * from module_jobs;
select * from jobs;

select * from campaigns;

select * from audit_logs order by id desc;

select * from users;
select * from organizations;
select * from organization_credit_balances;
select * from campaigns;
select * from contacts order by id asc;
select * from credit_transactions;
select * from webhook_events we ;

select * from integrations_field_mappings;

select * from organization_integrations;
select * from campaign_integrations where campaign_id = '019dc558-4a17-7033-bac8-2ae12150e101';
select * from modules;

select * from modules;
select * from module_jobs;


select * from integration_push_logs;
select * from campaign_integrations;

select * from integration_field_mappings;

select * from export_templates;
select * from export_templates where id = '019db443-6d71-718c-b785-1c7e07c6b2f5'

-- user_3CJ3sadWfYOORa4sQc3XjdBILit



UPDATE users 
SET admin_role = 'admin' 
WHERE id = '019dcd2b-494c-7358-9b94-651e5437fc0b';



select * from do_not_serve_entries;

select * from module_jobs;


select id, metadata->>'campaign_name' as campaign_name 
from module_jobs 
where module = 'site_id';


select * from suppression_lists;
select * from suppression_list_entries;




