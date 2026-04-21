select * from job_progress where created_at > '2026-04-16 03:14:54' order by created_at desc;
select * from job_progress_chunks order by id asc;

select * from suppression_lists where leadspeek_api_id = 50849139 and suppression_type = 'campaign' order by id desc; -- campaign
select * from suppression_lists where company_id = 165 and suppression_type = 'client' order by id desc; -- client
select * from suppression_lists where company_id = 164 and suppression_type = 'agency' order by id desc; -- agency
select * from optout_lists where company_root_id = 22 order by id desc; 

select * from jobs;
select * from failed_jobs;

