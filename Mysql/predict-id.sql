use emm_sandbox;
SET GLOBAL sql_mode = '';
--
select 
	CONVERT(AES_DECRYPT(FROM_bASE64(url_code), '8e651522e38256f2') USING utf8mb4) as url_code_dec, 
	url_code, 
	leadspeek_users.* 
from leadspeek_users 
where leadspeek_api_id = 83769694;
--
SELECT * FROM `leadspeek_audience_campaign`;
--
SELECT * FROM `leadspeek_media`;

-- select 
-- 	id,
-- 	leadspeek_api_id,
-- 	CONVERT(AES_DECRYPT(FROM_bASE64(website), '8e651522e38256f2') USING utf8mb4) as website,
-- 	business_type_id,
-- 	CONVERT(AES_DECRYPT(FROM_bASE64(business_name), '8e651522e38256f2') USING utf8mb4) as business_name,
-- 	business_industry_id,
-- 	CONVERT(AES_DECRYPT(FROM_bASE64(business_specify), '8e651522e38256f2') USING utf8mb4) as business_specify,
-- 	CONVERT(AES_DECRYPT(FROM_bASE64(business_description), '8e651522e38256f2') USING utf8mb4) as business_description,
-- 	CONVERT(AES_DECRYPT(FROM_bASE64(business_competitors), '8e651522e38256f2') USING utf8mb4) as business_competitors,
-- 	upload_customer_list_ids,
-- 	crm_id,
-- 	CONVERT(AES_DECRYPT(FROM_bASE64(crm_key), '8e651522e38256f2') USING utf8mb4) as crm_key,
-- 	created_at,
-- 	updated_at
-- from leadspeek_business;


select * from user_logs order by id desc limit 10;

select * from leadspeek_customer_campaigns where leadspeek_api_id in ('14820931');

select 
	DATEDIFF('2026-02-02', '2026-01-26') as result_1;
-- 	DATEDIFF('2026-01-11','2026-01-11') as result_2, -- di ui 2026-01-01 - 2026-01-07, ke charge nya tanggal 08 
-- 	DATEDIFF('2026-01-15','2026-01-08') as result_3, -- di ui 2026-01-08 - 2026-01-14,  ke charge nya tanggal 15
-- 	DATEDIFF('2026-01-22','2026-01-15') as result_4, -- di ui 2026-01-15 - 2026-01-21,  ke charge nya tanggal 22
-- 	DATEDIFF('2026-01-28','2026-01-22') as result_5; -- di ui 2026-01-22 - 2026-01-28,  ke charge nya tanggal 29



select * from leadspeek_business_resources;
select * from leadspeek_customers;


select 
	active,
	disabled,
	active_user, 
	CONVERT(AES_DECRYPT(FROM_bASE64(campaign_name), '8e651522e38256f2') USING utf8mb4) as campaign_name, 
	leadspeek_type,
	leadspeek_api_id,
	national_targeting,
	leadspeek_locator_state,
	leadspeek_locator_zip,
	leadspeek_users.* 
from leadspeek_users where leadspeek_type = 'predict';
select * from leadspeek_business;

select active,disabled,active_user, CONVERT(AES_DECRYPT(FROM_bASE64(campaign_name), '8e651522e38256f2') USING utf8mb4), leadspeek_type, leadspeek_users.* from leadspeek_users where leadspeek_type = 'simplifi' limit 1;
select active,disabled,active_user, CONVERT(AES_DECRYPT(FROM_bASE64(campaign_name), '8e651522e38256f2') USING utf8mb4), leadspeek_type, leadspeek_users.* from leadspeek_users where leadspeek_type = 'predict';
select * from leadspeek_business where leadspeek_api_id = 10122196;


set @leadspeek_api_id_delete := '9585489606,7087376074';
delete from leadspeek_users where FIND_IN_SET(leadspeek_api_id, @leadspeek_api_id_delete);
delete from leadspeek_business where FIND_IN_SET(leadspeek_api_id, @leadspeek_api_id_delete);
delete from leadspeek_customer_campaigns where FIND_IN_SET(leadspeek_api_id, @leadspeek_api_id_delete);
delete from leadspeek_predict_reports WHERE FIND_IN_SET(leadspeek_api_id COLLATE utf8mb4_general_ci, @leadspeek_api_id_delete COLLATE utf8mb4_general_ci);

select * from master_features;
select * from feature_users;

select * from services_agreement
where 
	feature_id in (4,5,6) and 
	user_id in (select id from users where company_id in (164) and user_type in ('user','userdownline'));

select * from subscription_modules;

select * from user_logs where created_at > '2026-02-03 22:12:16' order by id desc;
select * from user_logs order by id desc;
select * from user_logs where action like '%marketing service%' order by id desc;

select * from topup_agencies where company_id = 164;

select * from leadspeek_invoices 
where (invoice_type in ('agency','agency_subscription') and company_id = 164)
order by id desc;

set @leadspeek_api_id := 83428362;
select start_billing_date,lp_invoice_date,lu.* from leadspeek_users as lu where leadspeek_type = 'predict' and leadspeek_api_id in (@leadspeek_api_id);
select * from leadspeek_business where leadspeek_api_id in (@leadspeek_api_id);
select * from leadspeek_customer_campaigns where leadspeek_api_id in (@leadspeek_api_id);
select * from leadspeek_predict_reports where leadspeek_api_id = @leadspeek_api_id;
select * from leadspeek_predict_report_files where report_id = '';
select * from leadspeek_predict_report_bundles;
select * from leadspeek_predict_reports_bundle_items;
select * from leadspeek_customers;

DELETE from leadspeek_business where leadspeek_api_id in (SELECT leadspeek_api_id FROM leadspeek_users where leadspeek_type = 'predict' and company_id = 164);
DELETE from leadspeek_customer_campaigns where leadspeek_api_id in (SELECT leadspeek_api_id FROM leadspeek_users where leadspeek_type = 'predict' and company_id = 164);
DELETE from leadspeek_predict_reports where leadspeek_api_id in (SELECT leadspeek_api_id FROM leadspeek_users where leadspeek_type = 'predict' and company_id = 164);
DELETE from leadspeek_invoices where leadspeek_api_id in (SELECT leadspeek_api_id FROM leadspeek_users where leadspeek_type = 'predict' and company_id = 164);
DELETE FROM leadspeek_users where leadspeek_type = 'predict' and company_id = 164;

select DATE_ADD('2026-02-03', INTERVAL 1 MONTH) as test;

select * from jobs order by id desc;

SELECT
	id,
	company_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) as setting_name,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_value), '8e651522e38256f2') USING utf8mb4) as setting_value,
	setting_name,
	setting_value
FROM company_settings
WHERE company_id = 164;

resumePrepaidCampaignsStopContinualTopup



update leadspeek_users set active = 'F', disabled = 'T', active_user = 'F' where leadspeek_api_id in (13274584,11490044);


















-- variables
set @leadspeek_api_id := '15499465';
set @user_id_agency := (
    select id from users where company_id = (
        select company_id from leadspeek_users where leadspeek_api_id = @leadspeek_api_id
    ) and user_type = 'userdownline'
);
set @company_id_agency := (
    select company_id from users where id = @user_id_agency
);
set @email_agency := (
	select CONVERT(AES_DECRYPT(FROM_bASE64(email), '8e651522e38256f2') USING utf8mb4) from users where id = @user_id_agency
);
-- variables

-- estimate refund topup
SET @total_amount_agency = (
    SELECT 
    	COALESCE(SUM(
	        GREATEST(0, 
	            (tc.total_leads - COALESCE((
	                SELECT COUNT(*) 
	                FROM leadspeek_reports lr 
	                WHERE lr.topup_id = tc.id 
	                  AND lr.leadspeek_api_id = @leadspeek_api_id
	            ), 0)) * 
	            COALESCE(li.platform_cost_leads, tc.platform_price, 0)
	        )
	    ), 0)
    FROM topup_campaigns tc
    LEFT JOIN leadspeek_invoices li ON li.topup_campaigns_id = tc.id 
        AND li.invoice_type = 'campaign' 
        AND li.payment_term = 'Prepaid'
        AND li.leadspeek_api_id = tc.leadspeek_api_id
    WHERE tc.leadspeek_api_id = @leadspeek_api_id
      AND tc.topup_status <> 'done'
);
SET @total_amount_agency = ROUND(@total_amount_agency, 2);
-- estimate refund topup

-- Determine topup_status: 'queue' if there's a progress topup, else 'progress'
SET @topup_status = (
    SELECT CASE 
        WHEN EXISTS (
            SELECT 1 
            FROM topup_agencies 
            WHERE company_id = @company_id_agency 
              AND topup_status = 'progress' 
              AND expired_at IS NULL
        ) THEN 'queue'
        ELSE 'progress'
    END
);
-- Determine topup_status: 'queue' if there's a progress topup, else 'progress'

-- select items
SELECT 
    @leadspeek_api_id AS 'Leadspeek API ID',
    @user_id_agency AS 'User ID Agency',
    @company_id_agency AS 'Company ID Agency',
    @email_agency as 'Email Agency',
    @total_amount_agency AS 'Total Amount Agency',
    @topup_status AS 'Topup Status'
-- select items	

-- Insert ke topup_agencies
INSERT INTO topup_agencies (
    user_id,
    company_id,
    stop_continue,
    total_amount,
    balance_amount,
    topup_status,
    ip_user,
    timezone,
    payment_type,
    expired_at,
    created_at,
    updated_at
) VALUES (
    @user_id_agency,                    -- dari campaign->user_id atau request
    @company_id_agency,                 -- dari campaign->company_id
    'F',                         -- stop_continue untuk refund biasanya 'F'
    @total_amount_agency,         -- dari estimate_refund_topup
    @total_amount_agency,         -- balance_amount sama dengan total_amount
    @topup_status,                -- 'queue' jika ada topup lain yang progress, else 'progress'
    '',                    -- dari request->ip_user
    '',                   -- dari request->timezone
    'refund_campaign',           -- payment_type untuk refund
    NULL,                        -- expired_at null
    NOW(),
    NOW()
);

SET @topup_agencies_id = LAST_INSERT_ID();

select @topup_agencies_id AS 'topup_agencies_id';
-- Insert ke topup_agencies


-- Insert ke leadspeek_invoices
INSERT INTO leadspeek_invoices (
    invoice_type,
    topup_agencies_id,
    payment_type,
    company_id,
    user_id,
    leadspeek_api_id,
    invoice_number,
    payment_term,
    onetimefee,
    platform_onetimefee,
    min_leads,
    exceed_leads,
    total_leads,
    min_cost,
    platform_min_cost,
    cost_leads,
    platform_cost_leads,
    total_amount,
    platform_total_amount,
    root_total_amount,
    status,
    customer_payment_id,
    customer_stripe_id,
    customer_card_id,
    platform_customer_payment_id,
    error_payment,
    platform_error_payment,
    invoice_date,
    invoice_start,
    invoice_end,
    sent_to,
    sr_id,
    sr_fee,
    sr_transfer_id,
    ae_id,
    ae_fee,
    ae_transfer_id,
    ar_id,
    ar_fee,
    ar_transfer_id,
    active,
    created_at,
    updated_at
) VALUES (
    'agency',                    -- invoice_type
    @topup_agencies_id,          -- ID dari insert TopupAgency di atas
    'refund_campaign',           -- payment_type
    @company_id_agency,          -- dari campaign
    @user_id_agency,             -- dari campaign
    @leadspeek_api_id,           -- dari request
    '',                          -- invoice_number (akan di-update setelah insert)
    '',                          -- payment_term
    0,                           -- onetimefee
    0,                           -- platform_onetimefee
    0,                           -- min_leads
    0,                           -- exceed_leads
    0,                           -- total_leads
    0,                           -- min_cost
    0,                           -- platform_min_cost
    0,                           -- cost_leads
    0,                           -- platform_cost_leads
    0,                           -- total_amount
    @total_amount_agency,        -- platform_total_amount (hanya agency refund)
    0,                           -- root_total_amount
    'paid',                      -- status
    '',                          -- customer_payment_id
    '',                          -- customer_stripe_id
    '',                          -- customer_card_id
    '',                          -- platform_customer_payment_id
    '',                          -- error_payment
    '',                          -- platform_error_payment
    CURDATE(),                   -- invoice_date
    CURDATE(),                   -- invoice_start
    CURDATE(),                   -- invoice_end
    @email_agency,               -- sent_to
    0,                           -- sr_id
    0,                           -- sr_fee
    '',                          -- sr_transfer_id
    0,                           -- ae_id
    0,                           -- ae_fee
    '',                          -- ae_transfer_id
    0,                           -- ar_id
    0,                           -- ar_fee
    '',                          -- ar_transfer_id
    'T',                         -- active
    NOW(),
    NOW()
);

SET @invoice_id = LAST_INSERT_ID();

select @invoice_id AS 'invoice_id';

UPDATE leadspeek_invoices 
SET invoice_number = CONCAT(DATE_FORMAT(NOW(), '%Y%m%d'), ' - ', @invoice_id)
WHERE id = @invoice_id;
-- Insert ke leadspeek_invoices

-- done topup_campaigns
UPDATE topup_campaigns
SET topup_status = 'done'
WHERE leadspeek_api_id = @leadspeek_api_id AND topup_status <> 'done';
-- done topup_campaigns



UPDATE users 
SET 
    api_mode = 'T',
    is_marketing_services_agreement_developer = 'T'
WHERE id = {user_id_agency};


























































