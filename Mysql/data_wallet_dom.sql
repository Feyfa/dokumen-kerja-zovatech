--
SELECT * FROM `topup_agencies` where company_id = 532;
--
SELECT * FROM `leadspeek_invoices` where company_id = 532;
--
SELECT * FROM `users` where CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) = 'fisikamodern00+jidanachdom@gmail.com';
--
SELECT * FROM `users` where CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) = 'fisikamodern00+dimasdom@gmail.com';
--
SELECT * FROM `users` where CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) = 'fisikamodern00+jidanagencydom1@gmail.com';
--
SELECT * FROM `leadspeek_users` where leadspeek_api_id = 96154572;
--
SELECT * FROM `topup_campaigns` where leadspeek_api_id = 96154572 order by id asc;
--
SELECT * FROM `leadspeek_reports` where leadspeek_api_id = 96154572;
--
SELECT * FROM `leadspeek_invoices` where leadspeek_api_id = 96154572;
--
SELECT * FROM jobs;
select * from lead_list_queue;
--
SELECT * FROM failed_jobs;
--

















