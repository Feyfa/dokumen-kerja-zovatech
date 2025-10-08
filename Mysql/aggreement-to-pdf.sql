set @user_id := 754;
set @company_id := 595;
select * from users where CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) = 'fisikamodern00+jidanagencyemm5@gmail.com';
select * from users where id = @user_id;
select * from companies where id = @company_id;
select * from company_settings where company_id = @company_id;