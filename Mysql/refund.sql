use emm_sandbox;

set @leadspeek_api_id_1 := '81151983';
select * from leadspeek_users where leadspeek_api_id = @leadspeek_api_id_1 order by id asc;
select * from topup_campaigns where leadspeek_api_id = @leadspeek_api_id_1 order by id asc;
select * from leadspeek_invoices where leadspeek_api_id = @leadspeek_api_id_1 and invoice_type = 'campaign' order by id asc;
select * from leadspeek_reports where leadspeek_api_id = @leadspeek_api_id_1 order by id asc;
update leadspeek_users set active = 'T', disabled = 'F', active_user = 'T' where leadspeek_api_id = @leadspeek_api_id_1; -- run
update leadspeek_users set active = 'F', disabled = 'T', active_user = 'F' where leadspeek_api_id = @leadspeek_api_id_1; -- stop

set @company_id_1 := '164';
select * from topup_agencies where company_id = @company_id_1;
select * from leadspeek_invoices where company_id = @company_id_1 and invoice_type = 'agency';

set @company_id_2 := '464';
select * from topup_agencies where company_id = @company_id_2;
select * from leadspeek_invoices where company_id = @company_id_2 and invoice_type = 'agency';

select * from jobs;