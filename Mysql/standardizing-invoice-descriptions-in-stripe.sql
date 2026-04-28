set @leadspeek_api_id := '94330480' , @company_id_agency := 164;

select * from leadspeek_users where leadspeek_api_id = @leadspeek_api_id;
select * from topup_campaigns where leadspeek_api_id = @leadspeek_api_id;
select * from leadspeek_invoices where leadspeek_api_id = @leadspeek_api_id and invoice_type = 'campaign';
select * from leadspeek_reports where leadspeek_api_id = @leadspeek_api_id;

select * from topup_agencies where company_id = @company_id_agency;
select * from leadspeek_invoices where company_id = @company_id_agency and invoice_type = 'agency';