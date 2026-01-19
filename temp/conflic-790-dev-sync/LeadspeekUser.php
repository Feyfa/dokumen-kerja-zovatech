<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\HasApiTokens;
use ESolution\DBEncryption\Traits\EncryptedAttribute;

class LeadspeekUser extends Model
{
    use HasApiTokens,HasFactory, EncryptedAttribute;
    protected $table = 'leadspeek_users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'module_id',
        'company_id',
        'group_company_id',
        'user_id',
        'leadspeek_api_id',
        'topupoptions',
        'leadsbuy',
        'last_invoice_id',
        'campaign_name',
        'campaign_startdate',
        'campaign_enddate',
        'ori_campaign_startdate',
        'ori_campaign_enddate',
        'timezone',
        'url_code',
        'url_code_thankyou',
        'url_code_ads',
        'report_type',
        'report_sent_to',
        'admin_notify_to',
        'leads_amount_notification',
        'total_leads',
        'ongoing_leads',
        'lifetime_cost',
        'last_lead_check',
        'last_lead_added',
        'start_billing_date',
        'spreadsheet_id',  
        'filename', 
        'leadspeek_api_id',
        'filename',
        'report_frequency_id',
        'report_frequency',
        'report_frequency_unit',
        'suppresionlist_filename',
        'lp_limit_leads',
        'enable_minimum_limit_leads',
        'minimum_limit_leads',
        'lp_limit_freq',
        'leadspeek_type',
        'leadspeek_locator_zip',
        'leadspeek_locator_desc',
        'leadspeek_locator_keyword',
        'leadspeek_locator_keyword_contextual',
        'leadspeek_locator_state',
        'leadspeek_locator_state_exclude',
        'leadspeek_locator_state_simplifi',
        'leadspeek_locator_state_type',
        'leadspeek_locator_city',
        'leadspeek_locator_city_simplifi',
        'leadspeek_locator_require',
        'leadspeek_organizationid',
        'leadspeek_campaignsid',
        'paymentterm',
        'continual_buy_options',
        'questionnaire_answers',
        'embeddedcode_crawl',
        'embedded_status',
        'embedded_fire_status',
        'gtminstalled',
        'hide_phone',
        'active',
        'disabled',
        'active_user',
        'paused_dueresetcon',
        'last_lead_pause',
        'last_lead_start',
        'cost_perlead',
        'platformfee',
        'lp_max_lead_month',
        'lp_min_cost_month',
        'lp_limit_leads',
        'file_url',
        'national_targeting',
        'location_target',
        'phoneenabled',
        'homeaddressenabled',
        'reidentification_type',
        'applyreidentificationall',
        'require_email',
        'advance_information',
        'campaign_information_type',
        'campaign_information_type_local',
        'trysera',
        'archived',
        'ghl_tags',
        'ghl_remove_tags',
        'ghl_status_keyword_to_tags',
        'ghl_is_active',
        'mbp_groups',
        'mbp_remove_groups',
        'mbp_states',
        'mbp_zip',
        'mbp_options',
        'mbp_is_active',
        'zap_webhook',
        'zap_webhook_label',
        'zap_is_active',
        'zap_tags',
        'agencyzoom_is_active',
        'agencyzoom_webhook',
        'sendjim_is_active',
        'sendjim_tags',
        'sendjim_quicksend_is_active',
        'sendjim_quicksend_templates',
        'stopcontinual',
        'last_removed_keyword',
        'bigdbm_count_test',
        'bigdbm_count_result',
        'bigdbm_count_play_status',
        'campaign_link_id',
        'last_queue_count_test',
        'last_queue_count_play',
        'last_queue_count_history',
        'is_send_email_prepaid',
        'last_balance_leads',
        'embedded_play',
        'spreadsheet_owner',
        'lp_invoice_date',
        'simplifi_selected_campaign',
        'simplifi_selected_audience',
        'simplifi_selected_media',
        'audience_status',
        'ads_upload_status',
        'retargeting_audience_simplifi_id',
        'addressable_audience_simplifi_id',
        'frequency_capping_impressions',
        'frequency_capping_hours',
        'max_bid',
        'monthly_budget',
        'daily_budget',
        'goal_type',
        'goal_value',
        'cpa_view_thru_per',
        'cpa_click_thru_per',
        'view_attribution_window',
        'click_attribution_window',
        'device_type',
        'destination_url',
        'agency_markup',
        'media_type',
        'leadspeek_latitude',
        'leadspeek_longitude',
        'leadspeek_radius',
        'leadspeek_radius_unit',
    ];

    /**
     * The attributes that should be encrypted on save.
     *
     * @var array
     */
    protected $encryptable = [
        'company_name',
        'name',
        'email',
        'phonenum',
        'campaign_name',
        'url_code',
        'url_code_thankyou',
        'url_code_ads',
        'embedded_status',
        'file_url',
        'spreadsheet_owner',
    ];

    protected $casts = [
        //'last_lead_added' => 'datetime:m-d-Y',
    ];

    public function companies() {
        return $this->hasMany(Company::class,'id','company_id');
    }

    public function users() {
        return $this->hasMany(User::class,'id','user_id');
    }
}
