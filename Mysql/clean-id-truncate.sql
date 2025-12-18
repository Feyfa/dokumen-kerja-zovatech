use emm_sandbox;
set @company_id_jidanach := 164;

TRUNCATE TABLE clean_id_errors;
TRUNCATE TABLE clean_id_export;
TRUNCATE TABLE clean_id_file;
TRUNCATE TABLE clean_id_md5;
TRUNCATE TABLE clean_id_result;
TRUNCATE TABLE clean_id_result_advance_1;
TRUNCATE TABLE clean_id_result_advance_2;
TRUNCATE TABLE clean_id_result_advance_3;
TRUNCATE TABLE topup_cleanids;

delete from leadspeek_invoices where invoice_type = 'clean_id' and company_id = @company_id_jidanach order by id asc;
select * from failed_jobs;




set @person_id_delete := '4693,4691,4687,4686,4683,4682,4679,4678,4674,4673,4671,4670,4669,4667,4664,4666,4663,4662,4661,4660';
delete from persons where FIND_IN_SET(id, @person_id_delete);
delete from person_emails where FIND_IN_SET(person_id, @person_id_delete);
delete from person_phones where FIND_IN_SET(person_id, @person_id_delete);
delete from person_addresses where FIND_IN_SET(person_id, @person_id_delete);
delete from person_names where FIND_IN_SET(person_id, @person_id_delete);
delete from person_advance_1 where FIND_IN_SET(person_id, @person_id_delete);
delete from person_advance_2 where FIND_IN_SET(person_id, @person_id_delete);
delete from person_advance_3 where FIND_IN_SET(person_id, @person_id_delete);
delete from person_b2b where FIND_IN_SET(person_id, @person_id_delete);
delete from person_emails where FIND_IN_SET(person_id, @person_id_delete);


select 
	CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) as email, 
	person_emails.* 
from person_emails 
where 
	CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) in (
		"leonardwest@hotmail.com",
		"wizardofozfan@gmail.com",
		"litlelori1968@gmail.com",
		"walterzaldivar0610@yahoo.com",
		"stanrmatthews@yahoo.com",
		"garymacelwee@gmail.com",
		"a172c778@gmail.com",
		"miyatayy@gmail.com",
		"judyhuber525@yahoo.com",
		"corishearer.410@hotmail.com",
		"lakechelanvet@yahoo.com",
		"jsmith1038@aol.com",
		"huangj@tampabay.rr.com",
		"saradakodavati@gmail.com",
		"ariepugh518@gmail.com",
		"mpauley@montini.org",
		"monicmack@yahoo.com",
		"dan@dumperdan.com",
		"jacqualine236@gmail.com",
		"nitkhanna1@gmail.com"
	) and source = 'bigdbm'
order by id desc;

