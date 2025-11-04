select * from clean_id_file;
select * from module_settings;

select start_billing_date, leadspeek_users.* from leadspeek_users where leadspeek_api_id in (81151983,27092730,46389124,27215395);
select * from leadspeek_reports where leadspeek_api_id in (81151983,27092730,46389124,27215395);
select * from leadspeek_invoices where leadspeek_api_id in (81151983,27092730,46389124,27215395);

select * from leadspeek_invoices where invoice_type = 'campaign' and user_id = 282 and payment_term = 'Monthly' order by id desc limit 100;

select * from jobs;
select * from failed_jobs;
select * from failed_lead_records where `function` = 'ProcessInvoiceMonthlyJob' limit 100;

-- start_billing_date = 2025-11-02 22:35:16 , 