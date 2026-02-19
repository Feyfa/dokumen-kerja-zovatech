select * from jobs;

select * from leadspeek_reports order by created_at desc limit 1000;

select * from failed_lead_records
where `function` = 'getWattBasicInfo'
order by created_at desc
limit 20;

set @md5 := '84a9a08d9a01205697d5634bec22b531';
select * from person_emails where email_encrypt = @md5;
set @person_id := (select person_id from person_emails where email_encrypt = @md5);
delete from person_emails where email_encrypt = @md5;
delete from persons where id = @person_id;
delete from person_phones where person_id = @person_id;
delete from person_names where person_id = @person_id;
delete from person_addresses where person_id = @person_id;
delete from person_advance_1 where person_id = @person_id;
delete from person_advance_2 where person_id = @person_id;
delete from person_advance_3 where person_id = @person_id;
delete from person_b2b where person_id = @person_id;

select * from persons where id = @person_id;
select * from person_phones where person_id = @person_id;
select * from person_names where person_id = @person_id;
select * from person_addresses where person_id = @person_id;
select * from person_advance_1 where person_id = @person_id;
select * from person_advance_2 where person_id = @person_id;
select * from person_advance_3 where person_id = @person_id;
select * from person_b2b where person_id = @person_id;


