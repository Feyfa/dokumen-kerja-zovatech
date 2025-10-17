use emm_sandbox;
--
select
	integration_slug,
	CONVERT(AES_DECRYPT(FROM_bASE64(api_key), '8e651522e38256f2') USING utf8mb4) as api_key,
	cis.*
from companies_integration_settings as cis
where
	company_id = 165;

SELECT 
	lu.leadspeek_api_id,
	lu.zap_is_active,
	lu.zap_tags,
	lu.zap_webhook,
	lu.agencyzoom_webhook,
	lu.agencyzoom_is_active,
	lu.*
FROM `leadspeek_users` as lu 
where 
	leadspeek_api_id in (
		81087324, -- custom
		52863172 -- default
	);

select 
	CONVERT(AES_DECRYPT(FROM_bASE64(oat.token), '8e651522e38256f2') USING utf8mb4) as token,
	oat.*
from open_api_tokens as oat
where CONVERT(AES_DECRYPT(FROM_bASE64(token), '8e651522e38256f2') USING utf8mb4) in (
	'03d9e838a0445c4b075f6a34fc73806c4f7e4219320bec2b4d3756430e16b4a400137bde648161ff5a33065b39803f4f4976af56682d54285433f038676b4f6e127d3b24c869cc25595125fd20ccfac63dbc9d23a0724a4e1a3ee5f3',
	'2cd0926b01ea5c593d42d6babeaee05e59ada22c5c1941e0c5284133e5ba92a8a576ddf93c1ae998ff3b52e9ed42655fb197bbcd4976abe327214e60fd2a8fd710c73daf08828856ad391976541b00d1282a57ee3d70f60a0f9d6aeb',
	'ffe1584abef8c2c2f0f32bd4883f3bd65f7fb74a2078840ef62456795d4016e9ad42717b9455b4b2382d61f4b7f4ec1298094fc63cafbc23470029da50e3b5361997b2296eadb3e11c06626fd6365e85de736f37bec42f21501002e5bcb8d645',
	'7a1c201b94418184f568a1612a1c67ca66fc87045013634a22510df14d479d9236dbf4d4df8adb111d261286edc21220168a7d913a3e8611e1f2e51c9256cf1a91ca08389d0149336c9af63af4079fb81da393427b472c89c3029ce22bd37a31cf29',
	'a18613a300a6cb54a8e4d784e7c1102c33fefa1e3fd29ecd1364e9f7f5656b302e11f605c30de192460844d0574f7770eedc319486c262b67b5017f70057ff9e5595ed469f4f960bdeba8301f81f6c7f9c21a3c74372b30277550795b8a7c40be97de9d3'
);

select * from integration_list;


SELECT
	id,
	company_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) as setting_name,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_value), '8e651522e38256f2') USING utf8mb4) as setting_value,
	setting_name,
	setting_value
FROM company_settings
WHERE company_id = 899;

SELECT
	id,
	company_id,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) as setting_name,
	CONVERT(AES_DECRYPT(FROM_bASE64(setting_value), '8e651522e38256f2') USING utf8mb4) as setting_value,
	setting_name,
	setting_value
FROM company_settings
where CONVERT(AES_DECRYPT(FROM_bASE64(setting_name), '8e651522e38256f2') USING utf8mb4) = 'gohlcustomfields';




