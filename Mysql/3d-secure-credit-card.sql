set @leadspeek_api_id := 48184997;

select * from leadspeek_users where leadspeek_api_id = @leadspeek_api_id;
select * from topup_campaigns where leadspeek_api_id = @leadspeek_api_id;
select * from leadspeek_invoices where leadspeek_api_id = @leadspeek_api_id;
select * from leadspeek_reports where leadspeek_api_id = @leadspeek_api_id;