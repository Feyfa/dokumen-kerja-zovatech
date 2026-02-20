set @md5 := 'd7fbcb2bc68876df8f0cc41659e7d352';
set @person_id := (select person_id from person_emails where email_encrypt = @md5);
select * from person_emails where email_encrypt = @md5;
delete from person_emails where email_encrypt = @md5;
delete from persons where id = @person_id;
delete from person_phones where person_id = @person_id;
delete from person_names where person_id = @person_id;
delete from person_addresses where person_id = @person_id;
delete from person_advance_1 where person_id = @person_id;
delete from person_advance_2 where person_id = @person_id;
delete from person_advance_3 where person_id = @person_id;
delete from person_b2b where person_id = @person_id;

select * from optout_lists order by id desc limit 10;

select * from optout_lists where emailmd5 = @md5;
select * from suppression_lists where emailmd5 = @md5;
select * from jobs;