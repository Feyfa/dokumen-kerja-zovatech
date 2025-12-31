set @leadspeek_api_id_1 := '76448240';
select * from leadspeek_users where leadspeek_api_id = @leadspeek_api_id_1;
select * from topup_campaigns where leadspeek_api_id = @leadspeek_api_id_1;
select * from leadspeek_invoices where leadspeek_api_id = @leadspeek_api_id_1 and invoice_type = 'campaign';
select * from leadspeek_reports where leadspeek_api_id = @leadspeek_api_id_1;
update leadspeek_users set active = 'T', disabled = 'F', active_user = 'T' where leadspeek_api_id = @leadspeek_api_id_1;

select "====" as divider;

set @leadspeek_api_id_2 := '46827575';
select * from leadspeek_users where leadspeek_api_id = @leadspeek_api_id_2;
select * from topup_campaigns where leadspeek_api_id = @leadspeek_api_id_2;
select * from leadspeek_invoices where leadspeek_api_id = @leadspeek_api_id_2 and invoice_type = 'campaign';
select * from leadspeek_reports where leadspeek_api_id = @leadspeek_api_id_2;
update leadspeek_users set active = 'T', disabled = 'F', active_user = 'T' where leadspeek_api_id = @leadspeek_api_id_2;