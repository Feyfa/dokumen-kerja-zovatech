<?php

namespace App\Services;

use App\Jobs\CleanIDJob;
use App\Models\MasterFeature;
use App\Models\ServicesAgreement;
use Exception;
use Carbon\Carbon;
use App\Services\BigDBM;
use App\Models\CleanIDResult;
use App\Models\CleanIDResultB2B;
use App\Models\CleanIDMd5;
use App\Models\CleanIdAdvance;
use App\Models\CleanIdAdvance2;
use App\Models\CleanIdAdvance3;
use App\Models\PersonEmail;
use App\Models\PersonAddress;
use App\Models\PersonAdvance;
use App\Models\PersonAdvance2;
use App\Models\PersonAdvance3;
use App\Models\PersonPhone;
use App\Models\PersonB2B;
use App\Models\PersonName;
use App\Models\Person;
use App\Models\OptoutList;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Jobs\CleanIDMd5StreamJob;
use App\Models\CleanIDError;
use App\Models\CleanIDExport;
use App\Models\CleanIDFile;
use App\Models\LeadspeekInvoice;
use App\Models\LeadspeekUser;
use App\Models\TopupAgency;
use App\Models\TopupCleanId;
use App\Models\User;
use App\Services\OpenApi\OpenApiWebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Closure;
use Illuminate\Support\Facades\App;

class CleanID
{
    protected $controller; 
    protected $bigDBM;
    protected $wattData;
    // private $ttlCacheRedis = 10800; // 3 jam
    private $ttlCacheRedis = 300; // 5 menit

    public function __construct(Controller $controller, BigDBM $bigDBM, WattData $wattData) 
    {
        $this->controller = $controller;
        $this->bigDBM = $bigDBM;
        $this->wattData = $wattData;
    }

    public function format_phone(string $phone_no) 
    {
        return preg_replace(
            "/.*(\d{3})[^\d]{0,7}(\d{3})[^\d]{0,7}(\d{4})/",
            '($1) $2-$3',
            $phone_no
        );
    }

    public function generateReportUniqueNumber()
    {
        $randomCode = mt_rand(100000,999999) . time();
        while(CleanIDMd5::where('id','=',$randomCode)->count() > 0) 
        {
            $randomCode = mt_rand(100000,999999) . time();
        }
        return $randomCode;
    }


    // ==================================================================================
    // PROVIDER BIGDBM START
    // ==================================================================================
    /**
     * function merupakan bagian kecil dari function dataExistOnDB, tujuannya hanya ambil data dari database person advanced
     */
    public function process_person_advance_onDB($file_id, $md5_id, $person_id) 
    {
        // info(__FUNCTION__, ['file_id' => $file_id, 'md5_id' => $md5_id, 'person_id' => $person_id]);
        date_default_timezone_set('America/Chicago');

        $personadvance = PersonAdvance::where('person_id','=',$person_id)->where('source', 'bigdbm')->first();

        if ($personadvance) 
        {
            $data_advance = $personadvance->only([
                        "gender", "gender_aux", "age_aux","birth_year_aux", "generation", "marital_status", 
                        "income_household", "income_midpts_household", "net_worth_household", "net_worth_midpt_household", "discretionary_income",
                        "credit_midpts", "credit_range", "occupation_category", "occupation_detail", "occupation_type",
                        "voter", "urbanicity",
                        "mobile_phone_1", "mobile_phone_2", "mobile_phone_3", "mobile_phone_1_dnc", "mobile_phone_2_dnc", "mobile_phone_3_dnc",
                        "tax_bill_mailing_address", "tax_bill_mailing_city", "tax_bill_mailing_county", "tax_bill_mailing_fips",
                        "tax_bill_mailing_state", "tax_bill_mailing_zip", "tax_bill_mailing_zip4",
                        "num_adults_household", "num_children_household", "num_persons_household",
                        "child_aged_0_3_household", "child_aged_4_6_household", "child_aged_7_9_household",
                        "child_aged_10_12_household", "child_aged_13_18_household", "children_household",
                        "has_email", "has_phone",
                        "magazine_subscriber", "charity_interest",
                        "likely_charitable_donor", "donor_affinity",
                        "dwelling_type", "home_owner", "home_owner_ordinal", "length_of_residence",
                        "home_price", "home_value", "median_home_value", "land_sqft", "living_sqft",
                        "yr_built_orig", "yr_built_range", "lot_number",
                        "legal_description", "garage_sqft", "subdivision", "zoning_code",
                        "cooking", "gardening", "music", "diy", "books", "travel_vacation",
                        "health_beauty_products", "pet_owner", "photography", "fitness", "epicurean",
                        "cbsa", "census_block", "census_block_group", "census_tract",
                        "aerobics", "african_american_affinity", "amex_card", "antiques",
                        "apparel_accessory_affinity", "apparel_affinity", "arts_and_crafts", "asian_affinity",
                        "auto_affinity", "auto_racing_affinity", "aviation_affinity",
                        "bank_card", "bargain_hunter_affinity", "baseball", "baseball_affinity", "basketball",
                        "basketball_affinity", "beauty_affinity", "bible_affinity", "Bird_watching",
                        "birds_affinity", "blue_collar", "boating_sailing", "boating_sailing_affinity",
                        "business_affinity", "camping_hiking", "camping_hiking_climbing_affinity", "cars_interest",
                        "cat_owner", "catalog_affinity", "cigars", "classical_music", "coins", "collectibles_affinity",
                        "college_affinity", "computers_affinity", "continuity_program_affinity", "cooking_affinity",
                        "cosmetics", "country_music", "crafts_affinity", "credit_card", "credit_repair_affinity", "crochet_affinity"
                    ]);

            $data_advance['file_id'] = $file_id;
            $data_advance['md5_id'] = $md5_id;
            $data_advance['created_at'] = Carbon::now();
            $data_advance['updated_at'] = Carbon::now();

            // Simpan ke CleanIdAdvance
            CleanIdAdvance::create($data_advance);

        }

        $personadvance2 = PersonAdvance2::where('person_id','=',$person_id)->where('source', 'bigdbm')->first();

        if ($personadvance2) 
        {
            $data_advance2 = $personadvance2->only([
                        "diet_affinity", "dieting", "do_it_yourself_affinity", "dog_owner", "doll_collector", "education", "education_ordinal",
                        "education_seekers_affinity", "ego_affinity", "entertainment_interest", "figurines_collector", "fine_arts_collector",
                        "fishing", "fishing_affinity", "fitness_affinity", "football", "football_affinity", "gambling", "gambling_affinity",
                        "games_affinity", "gardening_affinity", "generation_ordinal", "golf", "golf_affinity", "gourmet_affinity", 
                        "grandparents_affinity", "health_affinity", "healthy_living", "healthy_living_interest", "high_tech_affinity",
                        "hispanic_affinity", "hockey", "hockey_affinity", "home_decor", "home_improvement_interest", "home_office_affinity", 
                        "home_study", "hunting", "hunting_affinity", "jazz", "kids_apparel_affinity", "knit_affinity", "knitting_quilting_sewing",
                        "luxury_life", "married", "median_income", "mens_apparel_affinity", "mens_fashion_affinity", "mortgage_age", 
                        "mortgage_amount", "mortgage_loan_type", "mortgage_refi_age", "mortgage_refi_amount", "mortgage_refi_type", "motor_racing",
                        "motorcycles", "motorcycles_affinity", "movies", "nascar", "needlepoint_affinity", "new_credit_offered_household",
                        "num_credit_lines_household", "num_generations_household", "outdoors", "outdoors_affinity", "owns_investments",
                        "owns_mutual_funds", "owns_stocks_and_bonds", "owns_swimming_pool", "personal_finance_affinity", "personality",
                        "plates_collector", "pop_density", "premium_amex_card", "premium_card", "premium_income_household",
                        "premium_income_midpt_household", "quilt_affinity", "religious_music", "rhythm_and_blues", "rock_music", "running", 
                        "rv", "scuba", "self_improvement", "sewing_affinity", "single_family_dwelling", "snow_skiing", "snow_skiing_affinity",
                        "soccer", "soccer_affinity", "soft_rock", "soho_business", "sports_memoribilia_collector", "stamps", "sweepstakes_affinity",
                        "tennis", "tennis_affinity", "tobacco_affinity", "travel_affinity", "travel_cruise_affinity", "travel_cruises", 
                        "travel_personal", "travel_rv_affinity", "travel_US_affinity", "truck_owner", "trucks_affinity", "tv_movies_affinity",
                        "veteran_household", "walking", "weight_lifting", "wildlife_affinity", "womens_apparel_affinity", "Womens_fashion_affinity", 
                        "woodworking", "male_aux", "political_contributor_aux", "political_party_aux", "financial_power", "mortgage_open_1st_intent",
                        "mortgage_open_2nd_intent", "mortgage_new_intent", "mortgage_refinance_intent", "automotive_loan_intent", "bank_card_intent",
                        "personal_loan_intent", "retail_card_intent", "student_loan_cons_intent", "student_loan_intent", "qtr3_baths", "ac_type", "acres"
                    ]);

            $data_advance2['file_id'] = $file_id;
            $data_advance2['md5_id'] = $md5_id;
            $data_advance2['created_at'] = Carbon::now();
            $data_advance2['updated_at'] = Carbon::now();

            // Simpan ke CleanIdAdvance
            CleanIdAdvance2::create($data_advance2);

        }

        $personadvance3 = PersonAdvance3::where('person_id','=',$person_id)->where('source', 'bigdbm')->first();

        if ($personadvance3) 
        {
            $data_advance3 = $personadvance3->only([
                        "additions_square_feet", "assess_val_impr", "assess_val_lnd", "assess_val_prop", "bldg_style", "bsmt_sqft", "bsmt_type",
                        "build_sqft_assess", "business", "combined_statistical_area", "metropolitan_division", "middle", "middle_2", "mkt_ip_perc",
                        "mobile_home", "mrtg_due", "mrtg_intrate", "mrtg_refi", "mrtg_term", "mrtg_type", "mrtg2_amt", "mrtg2_date", 
                        "mrtg2_deed_type", "mrtg2_due", "mrtg2_equity", "mrtg2_intrate", "mrtg2_inttype", "mrtg2_refi", "mrtg2_term", "mrtg2_type", 
                        "mrtg3_amt", "mrtg3_date", "mrtg3_deed_type", "mrtg3_due", "mrtg3_equity", "mrtg3_inttype", "mrtg3_refi", "mrtg3_term", 
                        "mrtg3_type", "msa_code", "number_bedrooms", "number_bldgs", "number_fireplace", "number_park_spaces", "number_rooms", 
                        "own_biz", "owner_occupied", "ownership_relation", "owner_type_description", "estimated_value", "ext_type", 
                        "finish_square_feet2", "fireplace", "first", "first_2", "found_type", "fr_feet", "fuel_type", "full_baths", "half_baths",
                        "garage_type", "grnd_sqft", "heat_type", "hmstd", "impr_appr_val", "imprval", "imprval_type", "land_appr_val", "landval",
                        "landval_type", "last", "last_2", "lat", "lender_name", "lender2_name", "lender3_name", "loan_amt", "loan_date", 
                        "loan_to_val", "lon", "markval", "markval_type", "patio_porch", "patio_square_feet", "pool", "porch_square_feet", 
                        "previous_assessed_value", "prop_type", "rate_type", "rec_date", "roof_covtype", "roof_shapetype", "sale_amt", "sale_amt_pr", 
                        "sale_date", "sale_type_pr", "sales_type", "sell_name", "sewer_type", "site_quality", "std_address", "std_city", "std_state", 
                        "std_zip", "std_zip4", "stories_number", "suffix", "suffix_2", "tax_yr", "tax_improvement_percent", "title_co", 
                        "tot_baths_est", "ttl_appr_val", "ttl_bld_sqft", "ttl_tax", "unit_number", "vet_exempt", "water_type"
                    ]);

            $data_advance3['file_id'] = $file_id;
            $data_advance3['md5_id'] = $md5_id;
            $data_advance3['created_at'] = Carbon::now();
            $data_advance3['updated_at'] = Carbon::now();

            // Simpan ke CleanIdAdvance
            CleanIdAdvance3::create($data_advance3);
        }
        
        $result = 'success';
        $message = 'Advance information Found From Our Database';
        $sts = 'found';
        /** IF BIG BDM MD5 HAVE RESULT */
        
        return ['res' => $result, 'msg' => $message, 'sts' => $sts];
    }

    /**
     * function merupakan bagian kecil dari function dataExistOnDB, tujuannya hanya ambil data dari database person b2b
     */
    public function process_person_b2b_onDB($file_id, $md5_id, $person_id) 
    {
        // info(__FUNCTION__, ['file_id' => $file_id, 'md5_id' => $md5_id, 'person_id' => $person_id]);
        date_default_timezone_set('America/Chicago');

        $person_b2b = PersonB2B::where('person_id','=',$person_id)->where('source', 'bigdbm')->first();

        if ($person_b2b) 
        {
            $data_b2b = $person_b2b->only([
                "employee_id", "company_name", "company_website", "phone_1_employee", "phone_1_company",
                "num_employees", "sales_volume", "year_founded", "job_title", "level", "job_function",
                "headquarters_branch", "naics_code", "last_seen_date", "linked_in"
            ]);

            $data_b2b['file_id'] = $file_id;
            $data_b2b['md5_id'] = $md5_id;
            $data_b2b['created_at'] = Carbon::now();
            $data_b2b['updated_at'] = Carbon::now();

            // Simpan ke CleanIdb2b
            CleanIDResultB2B::create($data_b2b);
        }
        
        $result = 'success';
        $message = 'B2B information Found From Our Database';
        $sts = 'found';
        /** IF BIG BDM MD5 HAVE RESULT */
        
        return ['res' => $result, 'msg' => $message, 'sts' => $sts];
    }

    /**
     * function ini terjadi ketika data di person utama belom ada, maka hit lagi dari bigdbm
     */
    public function process_BIGDBM_towerdata($file_id,$md5_id,$dataflow,$md5param)
    {
        info(__FUNCTION__, ['file_id' => $file_id, 'md5_id' => $md5_id, 'dataflow' => $dataflow, 'md5param' => $md5param]);
        $executionTimeList = [];
        date_default_timezone_set('America/Chicago');

        $status = "";
        $_FirstName = "";
        $_LastName = "";
        $_Email = "";
        $_Email2 = "";
        $_IP = "";
        $_Source = "";
        $_OptInDate = date('Y-m-d H:i:s');
        $_ClickDate = date('Y-m-d H:i:s');
        $_Referer = "";
        $_Phone = "";
        $_Phone2 = "";
        $_Address1 = "";
        $_Address2 = "";
        $_City = "";
        $_State = "";
        $_Zipcode = "";
        $_Description = $dataflow . "process_BIGDBM_towerdata";
        $leadspeektype = 'clean_id';
        $trackBigBDM = "BDMTOWERDATA";

        Log::info("Start Check BigBDM MD5");
        $startBigbdmMD5Time = microtime(true);

        /* GET CLEAN ID FILE */
        $cleanIdFile = CleanIDFile::where('id', $file_id)->first();
        $leadspeek_api_id = $cleanIdFile->clean_api_id ?? null;
        /* GET CLEAN ID FILE */

        $is_advance = false;
        $bigBDM_MD5 = $this->bigDBM->GetDataByMD5($file_id,$md5param,'basic',$is_advance);

        if (is_object($bigBDM_MD5) && isset($bigBDM_MD5->isError) && !empty($bigBDM_MD5->isError)) 
        {
            throw new \Exception("API Error: " . ($bigBDM_MD5->message ?? 'Unknown error'));
        }

        $endBigbdmMD5Time = microtime(true);

        // convert epochtime to date format ('Y-m-d H:i:s')
        $startBigbdmMD5Date = Carbon::createFromTimestamp($startBigbdmMD5Time)->setTimezone('America/Chicago')->format('Y-m-d H:i:s');
        $endBigbdmMD5Date = Carbon::createFromTimestamp($endBigbdmMD5Time)->setTimezone('America/Chicago')->format('Y-m-d H:i:s');
        // convert epochtime to date format ('Y-m-d H:i:s')

        $totalBigbdmMD5Time = $endBigbdmMD5Time - $startBigbdmMD5Time;
        $totalBigbdmMD5Time = number_format($totalBigbdmMD5Time,2,'.','');

        $executionTimeList['bigBDM_MD5'] = [
            'start_execution_time' => $startBigbdmMD5Date,
            'end_execution_time' => $endBigbdmMD5Date,
            'total_execution_time' => $totalBigbdmMD5Time,
        ];

        /* IF BIG BDM MD5 HAVE RESULT */
        if (count((array)$bigBDM_MD5) > 0) 
        {
            Log::info("BigBDM Have result");

            $trackBigBDM = $trackBigBDM . "->MD5";
            /** REPORT ANALYTIC */
            // $this->controller->UpsertReportAnalytics($file_id, $leadspeektype, 'bigbdmemail');
            /** REPORT ANALYTIC */

            /* BUILD DATA */
            // info('BUILD DATA 1', ['bigBDM_MD5' => $bigBDM_MD5]);
            foreach ($bigBDM_MD5 as $rd => $a) 
            {
                $uniqueID = (isset($a[0]->Id))?$a[0]->Id:'';

                $bigEmail = (isset($a[0]->Email))?$a[0]->Email:'';
                $bigEmail = explode(",",$bigEmail);

                $bigPhone = (isset($a[0]->Phone))?$a[0]->Phone:'';
                $bigPhone = explode(",",$bigPhone);

                $_FirstName = (isset($a[0]->First_Name))?$a[0]->First_Name:'';
                $_LastName = (isset($a[0]->Last_Name))?$a[0]->Last_Name:'';
                //$_Email = $bigEmail[0];
                //$_Email2 = (isset($bigEmail[1]))?$bigEmail[1]:'';
                $_Phone = $bigPhone[0];
                $_Phone2 = (isset($bigPhone[1]))?$bigPhone[1]:'';
                // $_Address1 = (isset($a[0]->Address))?$a[0]->Address:'';
                if(isset($a[0]->Addr_Primary))
                {
                    $_Address1 = (isset($a[0]->Addr_Primary))?$a[0]->Addr_Primary:'';
                }
                else
                {
                    $_Address1 = (isset($a[0]->Address))?$a[0]->Address:'';
                }
                if(isset($a[0]->Addr_Secondary))
                {
                    $_Address2 = (isset($a[0]->Addr_Secondary))?$a[0]->Addr_Secondary:'';
                }
                $_City =  (isset($a[0]->City))?$a[0]->City:'';
                $_State = (isset($a[0]->State))?$a[0]->State:'';
                $_Zipcode = (isset($a[0]->Zip))?$a[0]->Zip:'';
            }
            // info('BUILD DATA 2', [
            //     'bigEmail' => $bigEmail,
            //     'bigPhone' => $bigPhone,
            //     '_FirstName' => $_FirstName,
            //     '_LastName' => $_LastName,
            //     '_Phone' => $_Phone,
            //     '_Phone2' => $_Phone2,
            //     '_Address1' => $_Address1,
            //     '_Address2' => $_Address2,
            //     '_City' => $_City,
            //     '_State' => $_State,
            //     '_Zipcode' => $_Zipcode,
            // ]);
            /* BUILD DATA */

            /** INSERT INTO DATABASE PERSON */
            // info("INSERT INTO DATABASE PERSON");
            $newPerson = Person::create([
                'uniqueID' => $uniqueID,
                'firstName' => $_FirstName,
                'middleName' => '',
                'lastName' => $_LastName,
                'age' => '0',
                'identityScore' => '0',
                'lastEntry' => date('Y-m-d H:i:s'),
            ]);
            $newPersonID = $newPerson->id;
            /** INSERT INTO DATABASE PERSON */

            /** SEPARATE BETWEEN YAHOO/AOL AND OTHER EMAIL */
            $filteredEmails = [];
            $otherEmails = [];
            foreach ($bigEmail as $index => $email) 
            {
                if (strpos($email, 'yahoo.com') !== false || strpos($email, 'aol.com') !== false) 
                {
                    $filteredEmails[] = $email;
                } 
                else 
                {
                    $otherEmails[] = $email;
                }
            }
            // info("SEPARATE BETWEEN YAHOO/AOL AND OTHER EMAIL", ['bigEmail' => $bigEmail,'filteredEmails' => $filteredEmails,'otherEmails' => $otherEmails,]);
            /** SEPARATE BETWEEN YAHOO/AOL AND OTHER EMAIL */
            
            Log::info("BigBDM Have result - CHECK ZERO BOUNCE");

            /** NEW METHOD TO CHECK AND GET EMAIL */
            // info("NEW METHOD TO CHECK AND GET EMAIL");
            foreach($otherEmails as $index => $be) 
            {
                // info("START CHECK ZEROBOUNCE otherEmails ($index)");
                if (trim($be) != "") 
                {
                    $tmpEmail = strtolower(trim($be));
                    $tmpMd5 = md5($tmpEmail);

                    /* CARA BARU PAKAI ZEROBOUNCE */
                    $startZbValidationTime = microtime(true);

                    $param = [
                        'clean_file_id' => $file_id,
                        'leadspeek_api_id' => $leadspeek_api_id,
                        'leadspeek_type' => $leadspeektype,
                        'md5param' => $tmpMd5,
                    ];
                    $zbcheck = $this->controller->true_list_validation($tmpEmail,$param);
                    // info("NEW METHOD TO CHECK AND GET EMAIL", ['zbcheck' => $zbcheck]);

                    $endZbValidationTime = microtime(true);

                    // convert epochtime to date format ('Y-m-d H:i:s')
                    $startZbValidationDate = Carbon::createFromTimestamp($startZbValidationTime)->setTimezone('America/Chicago')->format('Y-m-d H:i:s');
                    $endZbValidationDate = Carbon::createFromTimestamp($endZbValidationTime)->setTimezone('America/Chicago')->format('Y-m-d H:i:s');
                    // convert epochtime to date format ('Y-m-d H:i:s')

                    $totalZbValidationTime = $endZbValidationTime - $startZbValidationTime;
                    $totalZbValidationTime = number_format($totalZbValidationTime,2,'.','');

                    $executionTimeList['zb_validation'] = [
                        'start_execution_time' => $startZbValidationDate,
                        'end_execution_time' => $endZbValidationDate,
                        'total_execution_time' => $totalZbValidationTime
                    ];

                    // Handle both TrueList and ZeroBounce responses
                    $isValid = false;
                    $validationStatus = '';
                    $apiType = '';
                    
                    // Check TrueList response format
                    if (isset($zbcheck->emails[0]->email_state)) 
                    {
                        $validationStatus = $zbcheck->emails[0]->email_state;
                        $apiType = 'truelist';
                        $isValid = ($zbcheck->emails[0]->email_state == "ok");
                    }
                    // Check ZeroBounce response format
                    elseif (isset($zbcheck->status)) 
                    {
                        $validationStatus = $zbcheck->status;
                        $apiType = 'zerobounce';
                        $isValid = !($zbcheck->status == "invalid" || $zbcheck->status == "catch-all" || $zbcheck->status == "abuse" || $zbcheck->status == "do_not_mail" || $zbcheck->status == "spamtrap");
                    }
                     
                    if ($validationStatus !== '') 
                    {
                        if (!$isValid)
                        {
                            /** PUT IT ON OPTOUT LIST */
                            $createoptout = OptoutList::create([
                                'email' => $tmpEmail,
                                'emailmd5' => md5($tmpEmail),
                                'blockedcategory' => 'zbnotvalid',
                                'description' => ucfirst($apiType) . ' Status: ' . $validationStatus . '|Email' . $index . 'fromBigMD5',
                            ]);
                            /** PUT IT ON OPTOUT LIST */ 

                            if (isset($leadspeek_api_id) && isset($leadspeektype) && isset($tmpMd5)) 
                            {
                                $this->controller->UpsertFailedLeadRecord([
                                    'function' => __FUNCTION__,
                                    'type' => 'blocked',
                                    'blocked_type' => $apiType,
                                    'description' => ucfirst($apiType) . ' Status: ' . $validationStatus . '|Email' . $index . 'fromBigMD5',
                                    'clean_file_id' => $file_id,
                                    'leadspeek_api_id' => $leadspeek_api_id,
                                    'email_encrypt' => $tmpMd5,
                                    'leadspeek_type' => $leadspeektype,
                                    'email' => $tmpEmail,
                                    'status' => $validationStatus,
                                ]);
                            }

                            $trackBigBDM = $trackBigBDM . "->Email" . $index . "ZBFailed";
                        }
                        else
                        {
                            $newpersonemail = PersonEmail::create([
                                'person_id' => $newPersonID,
                                'email' => $tmpEmail,
                                'email_encrypt' => $tmpMd5,
                                'permission' => 'T',
                                'zbvalidate' => date('Y-m-d H:i:s'),
                            ]);

                            if ($_Email ==  "") 
                            {
                                $_Email = $tmpEmail;
                            }
                            else if ($_Email2 == "") 
                            {
                                $_Email2 = $tmpEmail;
                            }

                            $trackBigBDM = $trackBigBDM . "->Email" . $index . "ZBSuccess";
                        }
                        /** REPORT ANALYTIC */
                        // $this->controller->UpsertReportAnalytics($leadspeek_api_id, $leadspeektype, $apiType . '_details', $validationStatus);
                        /** REPORT ANALYTIC */
                    }
                    else
                    {
                        $trackBigBDM = $trackBigBDM . "->Email" . $index . "ZBNotValidate";
                    }
                }
            }
            /** NEW METHOD TO CHECK AND GET EMAIL */

            /** CHECK IF STANDARD EMAIL NOT GET ANY VALID EMAIL */
            // info("CHECK IF STANDARD EMAIL NOT GET ANY VALID EMAIL", ['_Email' => $_Email]);
            if (trim($_Email) == '') 
            {
                /** NEW METHOD TO CHECK AND GET EMAIL */
                // info("NEW METHOD TO CHECK AND GET EMAIL");
                foreach($filteredEmails as $index => $be) 
                {
                    // info("START CHECK ZEROBOUNCE filteredEmails ($index)");
                    if (trim($be) != "") 
                    {
                        $tmpEmail = strtolower(trim($be));
                        $tmpMd5 = md5($tmpEmail);

                        $startZbValidationTime = microtime(true);

                        $param = [
                            'clean_file_id' => $file_id,
                            'leadspeek_api_id' => $leadspeek_api_id,
                            'leadspeek_type' => $leadspeektype,
                            'md5param' => $tmpMd5,
                        ];
                        $zbcheck = $this->controller->true_list_validation($tmpEmail,$param);
                        // info("NEW METHOD TO CHECK AND GET EMAIL", ['zbcheck' => $zbcheck]);

                        $endZbValidationTime = microtime(true);

                        // convert epochtime to date format ('Y-m-d H:i:s')
                        $startZbValidationDate = Carbon::createFromTimestamp($startZbValidationTime)->setTimezone('America/Chicago')->format('Y-m-d H:i:s');
                        $endZbValidationDate = Carbon::createFromTimestamp($endZbValidationTime)->setTimezone('America/Chicago')->format('Y-m-d H:i:s');
                        // convert epochtime to date format ('Y-m-d H:i:s')

                        $totalZbValidationTime = $endZbValidationTime - $startZbValidationTime;
                        $totalZbValidationTime = number_format($totalZbValidationTime,2,'.','');

                        $executionTimeList['zb_validation'] = [
                            'start_execution_time' => $startZbValidationDate,
                            'end_execution_time' => $endZbValidationDate,
                            'total_execution_time' => $totalZbValidationTime
                        ];

                        // Handle both TrueList and ZeroBounce responses
                        $isValid = false;
                        $validationStatus = '';
                        $apiType = '';

                        // Check TrueList response format
                        if (isset($zbcheck->emails[0]->email_state)) 
                        {
                            $validationStatus = $zbcheck->emails[0]->email_state;
                            $apiType = 'truelist';
                            $isValid = ($zbcheck->emails[0]->email_state == "ok");
                        }
                        // Check ZeroBounce response format
                        elseif (isset($zbcheck->status)) 
                        {
                            $validationStatus = $zbcheck->status;
                            $apiType = 'zerobounce';
                            $isValid = !($zbcheck->status == "invalid" || $zbcheck->status == "catch-all" || $zbcheck->status == "abuse" || $zbcheck->status == "do_not_mail" || $zbcheck->status == "spamtrap");
                        }
                        
                        if ($validationStatus !== '') 
                        {
                            if (!$isValid) 
                            {
                                /** PUT IT ON OPTOUT LIST */
                                $createoptout = OptoutList::create([
                                    'email' => $tmpEmail,
                                    'emailmd5' => md5($tmpEmail),
                                    'blockedcategory' => 'zbnotvalid',
                                    'description' => ucfirst($apiType) . ' Status: ' . $validationStatus . '|Email' . $index . 'fromBigMD5',
                                ]);
                                /** PUT IT ON OPTOUT LIST */

                                if (isset($leadspeek_api_id) && isset($leadspeektype) && isset($tmpMd5)) 
                                {
                                    $this->controller->UpsertFailedLeadRecord([
                                        'function' => __FUNCTION__,
                                        'type' => 'blocked',
                                        'blocked_type' => $apiType,
                                        'description' => ucfirst($apiType) . ' Status: ' . $validationStatus . '|Email' . $index . 'fromBigMD5',
                                        'clean_file_id' => $file_id,
                                        'leadspeek_api_id' => $leadspeek_api_id,
                                        'email_encrypt' => $tmpMd5,
                                        'leadspeek_type' => $leadspeektype,
                                        'email' => $tmpEmail,
                                        'status' => $validationStatus,
                                    ]);
                                }

                                $trackBigBDM = $trackBigBDM . "->Email" . $index . "ZBFailed";
                            }
                            else
                            {
                                $newpersonemail = PersonEmail::create([
                                    'person_id' => $newPersonID,
                                    'email' => $tmpEmail,
                                    'email_encrypt' => $tmpMd5,
                                    'permission' => 'T',
                                    'zbvalidate' => date('Y-m-d H:i:s'),
                                ]);

                                if ($_Email ==  "") 
                                {
                                    $_Email = $tmpEmail;
                                    break;
                                }
                                else if ($_Email2 == "") 
                                {
                                    $_Email2 = $tmpEmail;
                                }

                                $trackBigBDM = $trackBigBDM . "->Email" . $index . "ZBSuccess";
                            }
                            /** REPORT ANALYTIC */
                            // $this->controller->UpsertReportAnalytics($leadspeek_api_id, $leadspeektype, $apiType . '_details', $validationStatus);
                            /** REPORT ANALYTIC */
                        }
                        else
                        {
                            $trackBigBDM = $trackBigBDM . "->Email" . $index . "ZBNotValidate";
                        }
                    }
                }
                /** NEW METHOD TO CHECK AND GET EMAIL */
            }
            /** CHECK IF STANDARD EMAIL NOT GET ANY VALID EMAIL */

            if (trim($_Email) == "" && trim($_Email2) == "") 
            {
                /** REPORT ANALYTIC */
                // $this->controller->UpsertReportAnalytics($file_id,$leadspeektype,'zerobouncefailed');
                $trackBigBDM = $trackBigBDM . "->Email1andEmail2NotValid";
                /** REPORT ANALYTIC */

                Log::info("BigBDM Have result - CHECK ZERO BOUNCE - Email 1 and Email 2 NOT VALID");

                // /* RELEASE LOCK PROCESS FOR GETDATAMATCH */
                // Log::info("RELEASE GETDATAMATCH LOCK Process DATA BIG BDM - Email 1 and Email 2 NOT VALID CampaignID #" . $leadspeek_api_id);
                // $this->releaseLock($initLock);
                // /* RELEASE LOCK PROCESS FOR GETDATAMATCH */

                /* WRITE UPSER FAILED LEAD RECORD */
                $this->controller->UpsertFailedLeadRecord([
                    'function' => 'process_BDM_TowerData',
                    'type' => 'blocked',
                    'blocked_type' => 'zerobounce',
                    'description' => 'blocked in truelist fetch bigBDM_MD5 function process_BDM_TowerData',
                    'clean_file_id' => $file_id,
                    'email_encrypt' => $md5param,
                    'leadspeek_type' => $leadspeektype,
                    'leadspeek_api_id' => $leadspeek_api_id,
                ]);
                /* WRITE UPSER FAILED LEAD RECORD */

                /* CHECK REQUIRE EMAIL */
                $require_email = CleanIDFile::where('id', $file_id)->value('require_email');
                /* CHECK REQUIRE EMAIL */

                if($require_email === 'T')
                {
                    /* DELETE PERSON BECAUSE ZEROBOUNCE */
                    Person::where('id', $newPersonID)->where('source', 'bigdbm')->delete();
                    /* DELETE PERSON BECAUSE ZEROBOUNCE */
                    
                    $status = 'not_found';
                    $msg_description = 'Basic information Not Found Because Truelist Not Valid From BIGDBM';
    
                    return [
                        'status' => $status,
                        'msg_description' => $msg_description
                    ];
                }
            } 
            else 
            {
                /** REPORT ANALYTIC */
                // $this->controller->UpsertReportAnalytics($file_id,$leadspeektype,'zerobounce');
                $trackBigBDM = $trackBigBDM . "->Email1orEmail2Valid";
                /** REPORT ANALYTIC */
                Log::info("BigBDM Have result - CHECK ZERO BOUNCE - Email 1 and Email 2 VALID");

                // /* RELEASE LOCK PROCESS FOR GETDATAMATCH */
                // Log::info("RELEASE GETDATAMATCH LOCK Process DATA BIG BDM - Email 1 and Email 2 VALID CampaignID #" . $leadspeek_api_id);
                // $this->releaseLock($initLock);
                // /* RELEASE LOCK PROCESS FOR GETDATAMATCH */
            }

            Log::info("BigBDM BDM_TOWERDATA CHECK PHONE1");
            if (trim($_Phone) != "") 
            {
                /** INSERT PERSON_PHONES */
                $newpersonphone = PersonPhone::create([
                    'person_id' => $newPersonID,
                    'number' => $this->format_phone($_Phone),
                    'type' => 'user',
                    'isConnected' => 'T',
                    'firstReportedDate' => date('Y-m-d'),
                    'lastReportedDate' => date('Y-m-d'),
                    'permission' => 'F',
                ]);
                /** INSERT PERSON_PHONES */
            }

            Log::info("BigBDM BDM_TOWERDATA CHECK PHONE2");
            if (trim($_Phone2) != "") 
            {
                /** INSERT PERSON_PHONES */
                $newpersonphone = PersonPhone::create([
                    'person_id' => $newPersonID,
                    'number' => $this->format_phone($_Phone2),
                    'type' => 'user',
                    'isConnected' => 'T',
                    'firstReportedDate' => date('Y-m-d'),
                    'lastReportedDate' => date('Y-m-d'),
                    'permission' => 'F',
                ]);
                /** INSERT PERSON_PHONES */
            }

            Log::info("BigBDM BDM_TOWERDATA CHECK Address");
            /** INSERT INTO PERSON_ADDRESSES */
            $newpersonaddress = PersonAddress::create([
                'person_id' => $newPersonID,
                'street' => $_Address1,
                'unit' => $_Address2,
                'city' => $_City,
                'state' => $_State,
                'zip' => $_Zipcode,
                'fullAddress' => $_Address1 . ' ' . $_City . ',' . $_State . ' ' . $_Zipcode,
                'firstReportedDate' => date('Y-m-d'),
                'lastReportedDate' => date('Y-m-d'),
            ]);
            /** INSERT INTO PERSON_ADDRESSES */

            Log::info("BigBDM Have result - ReportID #{$uniqueID}");
            
            /** INSERT KE TABLE CLIENTID */
            CleanIDResult::create([
                'file_id' => $file_id,
                'md5_id' => $md5_id,
                'bigdbm_id' => $uniqueID,
                'first_name' => $_FirstName,
                'last_name' => $_LastName,
                'city' => $_City,
                'state' => $_State,
                'zip' => $_Zipcode,
                'address' => $_Address1,
                'address2' => $_Address2,
                'phone' => $_Phone,
                'phone2' => $_Phone2,
                'email' => $_Email,
                'email2' => $_Email2,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
            /** INSERT KE TABLE CLIENTID */

            $status = 'found';
            $msg_description = 'Basic information Found From BIGDBM';
        }
        else 
        {
            $status = 'not_found';
            $msg_description = 'Basic information Not Found From BIGDBM';
        }
        /* IF BIG BDM MD5 HAVE RESULT */

        return [
            'status' => $status,
            'msg_description' => $msg_description,
        ];
    }
    
    /**
     * function ini terjadi ketika data di person utama belom ada dan di person advance belom ada, maka hit lagi dari bigdbm
     */
    public function process_BIGDBM_advance($file_id,$md5_id,$dataflow,$md5param)
    {
        // info(__FUNCTION__, ['file_id' => $file_id, 'md5_id' => $md5_id, 'dataflow' => $dataflow, 'md5param' => $md5param]);
        $executionTimeList = [];

        date_default_timezone_set('America/Chicago');

        $new = array();
        $leadspeek_type = 'clean_id';
        $status = "";
        $msg_description = "";

        //BASIC INFORMATION
        $_ID = "";
        $_FirstName = "";
        $_LastName = "";
        $_Email = "";
        $_Email2 = "";
        $_Email3 = "";
        $_Phone = "";
        $_Phone2 = "";
        $_Address1 = "";
        $_Address2 = "";
        $_City = "";
        $_State = "";
        $_Zipcode = "";
        //BASIC INFORMATION

        //ADVANCE INFORMATION

            //identification
                $_gender_aux = "";
                $_age_aux = "";
                $_birth_year_aux = "";
                $_generation = "";
                $_marital_status = "";
            //identification
            
            //financial information
                $_household_income = "";
                $_median_household_income = "";
                $_household_net_worth = "";
                $_median_household_net_worth = "";
                $_discretionary_income = "";
                $_credit_score_median = "";
                $_credit_score_range = "";
            //financial information

            //occupation
                $_occupation_category = "";
                $_occupation_type = "";
                $_occupation_detail = "";
            //occupation
            
            //miscellaneous
                $_voter = "";
                $_urbanicity = "";
            //miscellaneous

            //contact information
                $_mobile_phone = "";
                $_mobile_phone2 = "";
                $_mobile_phone3 = "";
                $_mobile_phone_dnc = "";
                $_mobile_phone_dnc2 = "";
                $_mobile_phone_dnc3 = "";
                $_tax_bill_mailing_address = "";
                $_tax_bill_mailing_city = "";
                $_tax_bill_mailing_county = "";
                $_tax_bill_mailing_fips = "";
                $_tax_bill_mailing_state = "";
                $_tax_bill_mailing_zip = "";
                $_tax_bill_mailing_zip4 = "";
            //contact information

            //household information
                $_num_adults_household= "";
                $_num_children_household = "";
                $_num_persons_household = "";
                $_child_aged_0_3_household = "";
                $_child_aged_4_6_household = "";
                $_child_aged_7_9_household = "";
                $_child_aged_10_12_household = "";
                $_child_aged_13_18_household = "";
                $_children_household = "";
            //household information

            //marketing indicators
                $_has_email = "";
                $_has_phone = "";
                $_magazine_subscriber = "";
                $_charity_interest = "";
                $_likely_charitable_donor = "";            
            //marketing indicators

            //house and real estate
                $_dwelling_type = "";
                $_home_owner = "";
                $_home_owner_ordinal = "";
                $_length_of_residence = "";
                $_home_price = "";
                $_home_value = "";
                $_median_home_value = "";
                $_living_sqft = "";
                $_year_built_original = "";
                $_year_built_range = "";
                $_lot_number = "";
                $_legal_description = "";
                $_land_sqft = "";
                $_garage_sqft = "";
                $_subdivision = "";
                $_zoning_code = "";
            //house and real estate

            //interest and affinities
                $_cooking = "";
                $_gardening = "";
                $_music = "";
                $_diy = "";
                $_books = "";
                $_travel_vacation = "";
                $_health_beauty_products = "";
                $_pet_owner = "";
                $_photography = "";
                $_fitness = "";
                $_epicurean = "";
            //interest and affinities

            //location and census data
                $_cbsa = "";
                $_census_block = "";
                $_census_block_group = "";
                $_census_tract = "";
            //location and census data

            //unregistered row
                $_aerobics = "";
                $_african_american_affinity = "";
                $_amex_card = "";
                $_antiques = "";
                $_apparel_accessory_affinity = "";
                $_apparel_affinity = "";
                $_arts_and_crafts = "";
                $_asian_affinity = "";
                $_auto_affinity = "";
                $_auto_racing_affinity = "";
                $_aviation_affinity = "";
                $_bank_card = "";
                $_bargain_hunter_affinity = "";
                $_baseball = "";
                $_baseball_affinity = "";
                $_basketball = "";
                $_basketball_affinity = "";
                $_beauty_affinity = "";
                $_bible_affinity = "";
                $_bird_watching = "";
                $_birds_affinity = "";
                $_blue_collar = "";
                $_boating_sailing = "";
                $_boating_sailing_affinity = "";
                $_business_affinity = "";
                $_camping_hiking = "";
                $_camping_hiking_climbing_affinity = "";
                $_cars_interest = "";
                $_cat_owner = "";
                $_catalog_affinity = "";
                $_cigars = "";
                $_classical_music = "";
                $_coins = "";
                $_collectibles_affinity = "";
                $_college_affinity = "";
                $_computers_affinity = "";
                $_continuity_program_affinity = "";
                $_cooking_affinity = "";
                $_cosmetics = "";
                $_country_music = "";
                $_crafts_affinity = "";
                $_credit_card = "";
                $_credit_repair_affinity = "";
                $_crochet_affinity = "";
                $_diet_affinity = "";
                $_dieting = "";
                $_do_it_yourself_affinity = "";
                $_dog_owner = "";
                $_doll_collector = "";
                $_donor_affinity = "";
                $_education = "";
                $_education_ordinal = "";
                $_education_seekers_affinity = "";
                $_ego_affinity = "";
                $_entertainment_interest = "";
                $_figurines_collector = "";
                $_fine_arts_collector = "";
                $_fishing = "";
                $_fishing_affinity = "";
                $_fitness_affinity = "";
                $_football = "";
                $_football_affinity = "";
                $_gambling = "";
                $_gambling_affinity = "";
                $_games_affinity = "";
                $_gardening_affinity = "";
                $_gender = "";
                $_generation_ordinal = "";
                $_golf = "";
                $_golf_affinity = "";
                $_gourmet_affinity = "";
                $_grandparents_affinity = "";
                $_health_affinity = "";
                $_healthy_living = "";
                $_healthy_living_interest = "";
                $_high_tech_affinity = "";
                $_hispanic_affinity = "";
                $_hockey = "";
                $_hockey_affinity = "";
                $_home_decor = "";
                $_home_improvement_interest = "";
                $_home_office_affinity = "";
                $_home_study = "";
                $_hunting = "";
                $_hunting_affinity = "";
                $_jazz = "";
                $_kids_apparel_affinity = "";
                $_knit_affinity = "";
                $_knitting_quilting_sewing = "";
                $_luxury_life = "";
                $_married = "";
                $_median_income = "";
                $_mens_apparel_affinity = "";
                $_mens_fashion_affinity = "";
                $_mortgage_age = "";
                $_mortgage_amount = "";
                $_mortgage_loan_type = "";
                $_mortgage_refi_age = "";
                $_mortgage_refi_amount = "";
                $_mortgage_refi_type = "";
                $_motor_racing = "";
                $_motorcycles = "";
                $_motorcycles_affinity = "";
                $_movies = "";
                $_nascar = "";
                $_needlepoint_affinity = "";
                $_new_credit_offered_household = "";
                $_num_credit_lines_household = "";
                $_num_generations_household = "";
                $_outdoors = "";
                $_outdoors_affinity = "";
                $_owns_investments = "";
                $_owns_mutual_funds = "";
                $_owns_stocks_and_bonds = "";
                $_owns_swimming_pool = "";
                $_personal_finance_affinity = "";
                $_personality = "";
                $_plates_collector = "";
                $_pop_density = "";
                $_premium_amex_card = "";
                $_premium_card = "";
                $_premium_income_household = "";
                $_premium_income_midpt_household = "";
                $_quilt_affinity = "";
                $_religious_music = "";
                $_rhythm_and_blues = "";
                $_rock_music = "";
                $_running = "";
                $_rv = "";
                $_scuba = "";
                $_self_improvement = "";
                $_sewing_affinity = "";
                $_single_family_dwelling = "";
                $_snow_skiing = "";
                $_snow_skiing_affinity = "";
                $_soccer = "";
                $_soccer_affinity = "";
                $_soft_rock = "";
                $_soho_business = "";
                $_sports_memoribilia_collector = "";
                $_stamps = "";
                $_sweepstakes_affinity = "";
                $_tennis = "";
                $_tennis_affinity = "";
                $_tobacco_affinity = "";
                $_travel_affinity = "";
                $_travel_cruise_affinity = "";
                $_travel_cruises = "";
                $_travel_personal = "";
                $_travel_rv_affinity = "";
                $_travel_us_affinity = "";
                $_truck_owner = "";
                $_trucks_affinity = "";
                $_tv_movies_affinity = "";
                $_veteran_household = "";
                $_walking = "";
                $_weight_lifting = "";
                $_wildlife_affinity = "";
                $_womens_apparel_affinity = "";
                $_womens_fashion_affinity = "";
                $_woodworking = "";
                $_male_aux = "";
                $_political_contributor_aux = "";
                $_political_party_aux = "";
                $_financial_power = "";
                $_mortgage_open_1st_intent = "";
                $_mortgage_open_2nd_intent = "";
                $_mortgage_new_intent = "";
                $_mortgage_refinance_intent = "";
                $_automotive_loan_intent = "";
                $_bank_card_intent = "";
                $_personal_loan_intent = "";
                $_retail_card_intent = "";
                $_student_loan_cons_intent = "";
                $_student_loan_intent = "";
                $_3qtr_baths = "";
                $_ac_type = "";
                $_acres = "";
                $_additions_square_feet = "";
                $_assess_val_impr = "";
                $_assess_val_lnd = "";
                $_assess_val_prop = "";
                $_bldg_style = "";
                $_bsmt_sqft = "";
                $_bsmt_type = "";
                $_build_sqft_assess = "";
                $_business = "";
                $_combined_statistical_area = "";
                $_metropolitan_division = "";
                $_middle = "";
                $_middle_2 = "";
                $_mkt_ip_perc = "";
                $_mobile_home = "";

                $_mrtg_due = "";
                $_mrtg_intrate = "";
                $_mrtg_refi = "";
                $_mrtg_term = "";
                $_mrtg_type = "";

                $_mrtg2_amt = "";
                $_mrtg2_date = "";
                $_mrtg2_deed_type = "";
                $_mrtg2_due = "";
                $_mrtg2_equity = "";
                $_mrtg2_intrate = "";
                $_mrtg2_inttype = "";
                $_mrtg2_refi = "";
                $_mrtg2_term = "";
                $_mrtg2_type = "";

                $_mrtg3_amt = "";
                $_mrtg3_date = "";
                $_mrtg3_deed_type = "";
                $_mrtg3_due = "";
                $_mrtg3_equity = "";
                $_mrtg3_inttype = "";
                $_mrtg3_refi = "";
                $_mrtg3_term = "";
                $_mrtg3_type = "";

                $_msa_code = "";
                $_number_bedrooms = "";
                $_number_bldgs = "";
                $_number_fireplace = "";
                $_number_park_spaces = "";
                $_number_rooms = "";
                $_own_biz = "";
                $_owner_occupied = "";
                $_ownership_relation = "";
                $_owner_type_description = "";
                $_estimated_value = "";
                $_ext_type = "";
                $_finish_square_feet2 = "";
                $_fireplace = "";
                $_first = "";
                $_first_2 = "";
                $_found_type = "";
                $_fr_feet = "";
                $_fuel_type = "";
                $_full_baths = "";
                $_half_baths = "";
                $_garage_type = "";
                $_grnd_sqft = "";
                $_heat_type = "";
                $_hmstd = "";
                $_impr_appr_val = "";
                $_imprval = "";
                $_imprval_type = "";
                $_land_appr_val = "";
                $_landval = "";
                $_landval_type = "";
                $_last = "";
                $_last_2 = "";
                $_lat = "";
                $_lender_name = "";
                $_lender2_name = "";
                $_lender3_name = "";
                $_loan_amt = "";
                $_loan_date = "";
                $_loan_to_val = "";
                $_lon = "";
                $_markval = "";
                $_markval_type = "";
                $_patio_porch = "";
                $_patio_square_feet = "";
                $_pool = "";
                $_porch_square_feet = "";
                $_previous_assessed_value = "";
                $_prop_type = "";
                $_rate_type = "";
                $_rec_date = "";
                $_roof_covtype = "";
                $_roof_shapetype = "";
                $_sale_amt = "";
                $_sale_amt_pr = "";
                $_sale_date = "";
                $_sale_type_pr = "";
                $_sales_type = "";
                $_sell_name = "";
                $_sewer_type = "";
                $_site_quality = "";
                $_std_address = "";
                $_std_city = "";
                $_std_state = "";
                $_std_zip = "";
                $_std_zip4 = "";
                $_stories_number = "";
                $_suffix = "";
                $_suffix_2 = "";
                $_tax_yr = "";
                $_tax_improvement_percent = "";
                $_title_co = "";
                $_tot_baths_est = "";
                $_ttl_appr_val = "";
                $_ttl_bld_sqft = "";
                $_ttl_tax = "";
                $_unit_number = "";
                $_vet_exempt = "";
                $_water_type = "";

            //unregistered row

        //ADVANCE INFORMATION

        $_Referer = "";
        $_IP = "";
        $_Source = "";
        $_OptInDate = date('Y-m-d H:i:s');
        $_ClickDate = date('Y-m-d H:i:s');

        $_Description = $dataflow . "dataNotExistOnDBBDMTD|";

        $trackBigBDM = "BDMADVANCE";

        // Log::info("Start Check BigBDM MD5 Advance");
        $startBigbdmMD5Time = microtime(true);

        /* GET CLEAN ID FILE */
        $cleanIdFile = CleanIDFile::where('id', $file_id)->first();
        $leadspeek_api_id = $cleanIdFile->clean_api_id ?? null;
        /* GET CLEAN ID FILE */

        $is_advance = true;
        $bigBDM_MD5_advance = $this->bigDBM->GetDataByMD5($file_id, $md5param, 'advanced', $is_advance);

        if (is_object($bigBDM_MD5_advance) && isset($bigBDM_MD5_advance->isError) && !empty($bigBDM_MD5_advance->isError)) 
        {
            throw new \Exception("API Error: " . ($bigBDM_MD5_advance->message ?? 'Unknown error'));
        }

        $endBigbdmMD5Time = microtime(true);

        // convert epochtime to date format ('Y-m-d H:i:s')
        $startBigbdmMD5Date = Carbon::createFromTimestamp($startBigbdmMD5Time)->setTimezone('America/Chicago')->format('Y-m-d H:i:s');
        $endBigbdmMD5Date = Carbon::createFromTimestamp($endBigbdmMD5Time)->setTimezone('America/Chicago')->format('Y-m-d H:i:s');
        // convert epochtime to date format ('Y-m-d H:i:s')

        $totalBigbdmMD5Time = $endBigbdmMD5Time - $startBigbdmMD5Time;
        $totalBigbdmMD5Time = number_format($totalBigbdmMD5Time,2,'.','');

        $executionTimeList['bigBDM_MD5_advance'] = [
            'start_execution_time' => $startBigbdmMD5Date,
            'end_execution_time' => $endBigbdmMD5Date,
            'total_execution_time' => $totalBigbdmMD5Time,
        ];

        // info("bigBDM_MD5_advance", ['bigBDM_MD5_advance' => $bigBDM_MD5_advance]);
        if (count((array)$bigBDM_MD5_advance) > 0) 
        {
            Log::info("BigBDM Have result");
            
            $trackBigBDM = $trackBigBDM . "->MD5Advance";
            /** REPORT ANALYTIC */
            // $this->controller->UpsertReportAnalytics($file_id,$leadspeek_type,'bigbdmemail');
            /** REPORT ANALYTIC */

            /* BUILD DATA */
            foreach ($bigBDM_MD5_advance as $rd => $a) 
            {
                //BASIC INFORMATION
                    $_FirstName = (isset($a[0]->First_Name))?$a[0]->First_Name:'';
                    $_LastName = (isset($a[0]->Last_Name))?$a[0]->Last_Name:'';

                    // $_Address1 = (isset($a[0]->Address))?$a[0]->Address:'';
                    if(isset($a[0]->Addr_Primary))
                    {
                        $_Address1 = (isset($a[0]->Addr_Primary))?$a[0]->Addr_Primary:'';
                    }
                    else
                    {
                        $_Address1 = (isset($a[0]->Address))?$a[0]->Address:'';
                    }
                    if(isset($a[0]->Addr_Secondary))
                    {
                        $_Address2 = (isset($a[0]->Addr_Secondary))?$a[0]->Addr_Secondary:'';
                    }
                    $_City =  (isset($a[0]->City))?$a[0]->City:'';
                    $_State = (isset($a[0]->State))?$a[0]->State:'';
                    $_Zipcode = (isset($a[0]->Zip))?$a[0]->Zip:'';

                    //process email
                    $email1 = isset($a[0]->Email_1) ? $a[0]->Email_1 : '';
                    $email2 = isset($a[0]->Email_2) ? $a[0]->Email_2 : '';
                    $email3 = isset($a[0]->Email_3) ? $a[0]->Email_3 : '';
                    $email_array = array_filter([$email1, $email2, $email3]); 
                    $bigEmail = $email_array;
                    //process email                
                //BASIC INFORMATION

                //ADVANCE INFORMATION

                    //identification
                        $_gender_aux = (isset($a[0]->Gender_aux))?$a[0]->Gender_aux:'';
                        $_age_aux = (isset($a[0]->Age_aux)) ? $a[0]->Age_aux : '';
                        $_birth_year_aux = (isset($a[0]->Birth_Year_aux)) ? $a[0]->Birth_Year_aux : '';
                        $_generation = (isset($a[0]->Generation)) ? $a[0]->Generation : '';
                        $_marital_status = (isset($a[0]->Marital_Status))?$a[0]->Marital_Status:'';
                    //identification
                    
                    //financial information
                        $_household_income = (isset($a[0]->Income_HH))?$a[0]->Income_HH:'';
                        $_median_household_income = (isset($a[0]->Income_Midpts_HH))?$a[0]->Income_Midpts_HH:'';
                        $_household_net_worth = (isset($a[0]->Net_Worth_HH))?$a[0]->Net_Worth_HH:'';
                        $_median_household_net_worth = (isset($a[0]->Net_Worth_Midpt_HH))?$a[0]->Net_Worth_Midpt_HH:'';
                        $_discretionary_income = (isset($a[0]->Discretionary_Income)) ? $a[0]->Discretionary_Income : '';
                        $_credit_score_median = (isset($a[0]->Credit_Midpts)) ? $a[0]->Credit_Midpts : '';
                        $_credit_score_range = (isset($a[0]->Credit_Range)) ? $a[0]->Credit_Range : '';
                    //financial information

                    //occupation
                        $_occupation_category = (isset($a[0]->Occupation_Category)) ? $a[0]->Occupation_Category : '';
                        $_occupation_type =(isset($a[0]->Occupation_Type)) ? $a[0]->Occupation_Type : '';
                        $_occupation_detail = (isset($a[0]->Occupation_Detail)) ? $a[0]->Occupation_Detail : '';
                    //occupation
                    
                    //miscellaneous
                        $_voter = (isset($a[0]->Voter)) ? $a[0]->Voter : '';
                        $_urbanicity = (isset($a[0]->Urbanicity)) ? $a[0]->Urbanicity : '';
                    //miscellaneous

                    //contact information

                        $_mobile_phone = (isset($a[0]->Mobile_Phone_1))?$a[0]->Mobile_Phone_1:'';
                        $_mobile_phone2 = (isset($a[0]->Mobile_Phone_2))?$a[0]->Mobile_Phone_2:'';
                        $_mobile_phone3 = (isset($a[0]->Mobile_Phone_3))?$a[0]->Mobile_Phone_3:'';
                        $_mobile_phone_dnc = (isset($a[0]->Mobile_Phone_1_DNC))?$a[0]->Mobile_Phone_1_DNC:'';
                        $_mobile_phone_dnc2 = (isset($a[0]->Mobile_Phone_2_DNC))?$a[0]->Mobile_Phone_2_DNC:'';
                        $_mobile_phone_dnc3 = (isset($a[0]->Mobile_Phone_3_DNC))?$a[0]->Mobile_Phone_3_DNC:'';

                        $_tax_bill_mailing_address = (isset($a[0]->TaxBillMailingAddress))?$a[0]->TaxBillMailingAddress:'';
                        $_tax_bill_mailing_city = (isset($a[0]->TaxBillMailingCity))?$a[0]->TaxBillMailingCity:'';
                        $_tax_bill_mailing_county = (isset($a[0]->TaxBillMailingCounty))?$a[0]->TaxBillMailingCounty:'';
                        $_tax_bill_mailing_fips = (isset($a[0]->TaxBillMailingFIPs))?$a[0]->TaxBillMailingFIPs:'';
                        $_tax_bill_mailing_state = (isset($a[0]->TaxBillMailingState))?$a[0]->TaxBillMailingState:'';
                        $_tax_bill_mailing_zip = (isset($a[0]->TaxBillMailingZip))?$a[0]->TaxBillMailingZip:'';
                        $_tax_bill_mailing_zip4 = (isset($a[0]->TaxBillMailingZip4))?$a[0]->TaxBillMailingZip4:'';
                    //contact information

                    //household information
                        $_num_adults_household= (isset($a[0]->Num_Adults_HH))?$a[0]->Num_Adults_HH:'';
                        $_num_children_household = (isset($a[0]->Num_Children_HH))?$a[0]->Num_Children_HH:'';
                        $_num_persons_household = (isset($a[0]->Num_Persons_HH))?$a[0]->Num_Persons_HH:'';
                        $_child_aged_0_3_household = (isset($a[0]->Child_Aged_0_3_HH))?$a[0]->Child_Aged_0_3_HH:'';
                        $_child_aged_4_6_household = (isset($a[0]->Child_Aged_4_6_HH))?$a[0]->Child_Aged_4_6_HH:'';
                        $_child_aged_7_9_household = (isset($a[0]->Child_Aged_7_9_HH))?$a[0]->Child_Aged_7_9_HH:'';
                        $_child_aged_10_12_household = (isset($a[0]->Child_Aged_10_12_HH))?$a[0]->Child_Aged_10_12_HH:'';
                        $_child_aged_13_18_household = (isset($a[0]->Child_Aged_13_18_HH))?$a[0]->Child_Aged_13_18_HH:'';
                        $_children_household = (isset($a[0]->Children_HH))?$a[0]->Children_HH:'';
                    //household information

                    //marketing indicators
                        $_has_email = (isset($a[0]->HasEmail))?$a[0]->HasEmail:'';
                        $_has_phone = (isset($a[0]->HasPhone))?$a[0]->HasPhone:'';
                        $_magazine_subscriber = (isset($a[0]->Magazine_Subscriber))?$a[0]->Magazine_Subscriber:'';
                        $_charity_interest = (isset($a[0]->Charity_Interest))?$a[0]->Charity_Interest:'';
                        $_likely_charitable_donor = (isset($a[0]->Likely_Charitable_Donor))?$a[0]->Likely_Charitable_Donor:'';     
                    //marketing indicators

                    //house and real estate
                        $_dwelling_type = (isset($a[0]->Dwelling_Type))?$a[0]->Dwelling_Type:'';     
                        $_home_owner = (isset($a[0]->Home_Owner))?$a[0]->Home_Owner:'';     
                        $_home_owner_ordinal = (isset($a[0]->Home_Owner_Ordinal))?$a[0]->Home_Owner_Ordinal:'';     
                        $_length_of_residence = (isset($a[0]->Length_of_Residence))?$a[0]->Length_of_Residence:'';     
                        $_home_price = (isset($a[0]->Home_Price))?$a[0]->Home_Price:'';     
                        $_home_value = (isset($a[0]->Home_Value))?$a[0]->Home_Value:'';     
                        $_median_home_value = (isset($a[0]->Median_Home_Value))?$a[0]->Median_Home_Value:'';  
                        $_living_sqft = (isset($a[0]->LIVING_SQFT))?$a[0]->LIVING_SQFT:'';  
                        $_year_built_original = (isset($a[0]->YR_BUILT_ORIG))?$a[0]->YR_BUILT_ORIG:'';  
                        $_year_built_range = (isset($a[0]->YR_BUILT_RANGE))?$a[0]->YR_BUILT_RANGE:'';  
                        $_lot_number = (isset($a[0]->LotNumber))?$a[0]->LotNumber:'';  
                        $_legal_description = (isset($a[0]->LegalDescription))?$a[0]->LegalDescription:'';  
                        $_land_sqft = (isset($a[0]->LAND_SQFT))?$a[0]->LAND_SQFT:'';  
                        $_garage_sqft = (isset($a[0]->GAR_SQFT))?$a[0]->GAR_SQFT:'';  
                        $_subdivision = (isset($a[0]->SUBDIVISION))?$a[0]->SUBDIVISION:'';  
                        $_zoning_code = (isset($a[0]->ZONING_CODE))?$a[0]->ZONING_CODE:'';  
                    //house and real estate

                    //interest and affinities
                        $_cooking = (isset($a[0]->Cooking))?$a[0]->Cooking:'';  
                        $_gardening = (isset($a[0]->Gardening))?$a[0]->Gardening:''; 
                        $_music = (isset($a[0]->Music))?$a[0]->Music:''; 
                        $_diy =  (isset($a[0]->DIY))?$a[0]->DIY:''; 
                        $_books = (isset($a[0]->Books))?$a[0]->Books:''; 
                        $_travel_vacation = (isset($a[0]->Travel_Vacation))?$a[0]->Travel_Vacation:''; 
                        $_health_beauty_products = (isset($a[0]->Health_Beauty_Products))?$a[0]->Health_Beauty_Products:''; 
                        $_pet_owner = (isset($a[0]->Pet_Owner))?$a[0]->Pet_Owner:''; 
                        $_photography = (isset($a[0]->Photography))?$a[0]->Photography:''; 
                        $_fitness = (isset($a[0]->Fitness))?$a[0]->Fitness:''; 
                        $_epicurean = (isset($a[0]->Epicurean))?$a[0]->Epicurean:''; 
                    //interest and affinities

                    //location and census data
                        $_cbsa = (isset($a[0]->Cbsa))?$a[0]->Cbsa:''; 
                        $_census_block = (isset($a[0]->Census_Block))?$a[0]->Census_Block:''; 
                        $_census_block_group = (isset($a[0]->Census_Block_Group))?$a[0]->Census_Block_Group:''; 
                        $_census_tract = (isset($a[0]->Census_Tract))?$a[0]->Census_Tract:''; 
                    //location and census data

                //ADVANCE INFORMATION

                //unregistered row
                    $_aerobics = (isset($a[0]->Aerobics)) ? $a[0]->Aerobics : '';
                    $_african_american_affinity = (isset($a[0]->African_American_Affinity)) ? $a[0]->African_American_Affinity : '';
                    $_amex_card = (isset($a[0]->AMEX_Card)) ? $a[0]->AMEX_Card : '';
                    $_antiques = (isset($a[0]->Antiques)) ? $a[0]->Antiques : '';
                    $_apparel_accessory_affinity = (isset($a[0]->Apparel_Accessory_Affinity)) ? $a[0]->Apparel_Accessory_Affinity : '';
                    $_apparel_affinity = (isset($a[0]->Apparel_Affinity)) ? $a[0]->Apparel_Affinity : '';
                    $_arts_and_crafts = (isset($a[0]->Arts_and_Crafts)) ? $a[0]->Arts_and_Crafts : '';
                    $_asian_affinity = (isset($a[0]->Asian_Affinity)) ? $a[0]->Asian_Affinity : '';
                    $_auto_affinity = (isset($a[0]->Auto_Affinity)) ? $a[0]->Auto_Affinity : '';
                    $_auto_racing_affinity = (isset($a[0]->Auto_Racing_Affinity)) ? $a[0]->Auto_Racing_Affinity : '';
                    $_aviation_affinity = (isset($a[0]->Aviation_Affinity)) ? $a[0]->Aviation_Affinity : '';
                    $_bank_card = (isset($a[0]->Bank_Card)) ? $a[0]->Bank_Card : '';
                    $_bargain_hunter_affinity = (isset($a[0]->Bargain_Hunter_Affinity)) ? $a[0]->Bargain_Hunter_Affinity : '';
                    $_baseball = (isset($a[0]->Baseball)) ? $a[0]->Baseball : '';
                    $_baseball_affinity = (isset($a[0]->Baseball_Affinity)) ? $a[0]->Baseball_Affinity : '';
                    $_basketball = (isset($a[0]->Basketball)) ? $a[0]->Basketball : '';
                    $_basketball_affinity = (isset($a[0]->Basketball_Affinity)) ? $a[0]->Basketball_Affinity : '';
                    $_beauty_affinity = (isset($a[0]->Beauty_Affinity)) ? $a[0]->Beauty_Affinity : '';
                    $_bible_affinity = (isset($a[0]->Bible_Affinity)) ? $a[0]->Bible_Affinity : '';
                    $_bird_watching = (isset($a[0]->Bird_watching)) ? $a[0]->Bird_watching : '';
                    $_birds_affinity = (isset($a[0]->Birds_Affinity)) ? $a[0]->Birds_Affinity : '';
                    $_blue_collar = (isset($a[0]->Blue_Collar)) ? $a[0]->Blue_Collar : '';
                    $_boating_sailing = (isset($a[0]->Boating_Sailing)) ? $a[0]->Boating_Sailing : '';
                    $_boating_sailing_affinity = (isset($a[0]->Boating_Sailing_Affinity)) ? $a[0]->Boating_Sailing_Affinity : '';
                    $_business_affinity = (isset($a[0]->Business_Affinity)) ? $a[0]->Business_Affinity : '';
                    $_camping_hiking = (isset($a[0]->Camping_Hiking)) ? $a[0]->Camping_Hiking : '';
                    $_camping_hiking_climbing_affinity = (isset($a[0]->Camping_Hiking_Climbing_Affinity)) ? $a[0]->Camping_Hiking_Climbing_Affinity : '';
                    $_cars_interest = (isset($a[0]->Cars_Interest)) ? $a[0]->Cars_Interest : '';
                    $_cat_owner = (isset($a[0]->Cat_Owner)) ? $a[0]->Cat_Owner : '';
                    $_catalog_affinity = (isset($a[0]->Catalog_Affinity)) ? $a[0]->Catalog_Affinity : '';
                    $_cigars = (isset($a[0]->Cigars)) ? $a[0]->Cigars : '';
                    $_classical_music = (isset($a[0]->Classical_Music)) ? $a[0]->Classical_Music : '';
                    $_coins = (isset($a[0]->Coins)) ? $a[0]->Coins : '';
                    $_collectibles_affinity = (isset($a[0]->Collectibles_Affinity)) ? $a[0]->Collectibles_Affinity : '';
                    $_college_affinity = (isset($a[0]->College_Affinity)) ? $a[0]->College_Affinity : '';
                    $_computers_affinity = (isset($a[0]->Computers_Affinity)) ? $a[0]->Computers_Affinity : '';
                    $_continuity_program_affinity = (isset($a[0]->Continuity_Program_Affinity)) ? $a[0]->Continuity_Program_Affinity : '';
                    $_cooking_affinity = (isset($a[0]->Cooking_Affinity)) ? $a[0]->Cooking_Affinity : '';
                    $_cosmetics = (isset($a[0]->Cosmetics)) ? $a[0]->Cosmetics : '';
                    $_country_music = (isset($a[0]->Country_Music)) ? $a[0]->Country_Music : '';
                    $_crafts_affinity = (isset($a[0]->Crafts_Affinity)) ? $a[0]->Crafts_Affinity : '';
                    $_credit_card = (isset($a[0]->Credit_Card)) ? $a[0]->Credit_Card : '';
                    $_credit_repair_affinity = (isset($a[0]->Credit_Repair_Affinity)) ? $a[0]->Credit_Repair_Affinity : '';
                    $_crochet_affinity = (isset($a[0]->Crochet_Affinity)) ? $a[0]->Crochet_Affinity : '';
                    $_diet_affinity = (isset($a[0]->Diet_Affinity)) ? $a[0]->Diet_Affinity : '';
                    $_dieting = (isset($a[0]->Dieting)) ? $a[0]->Dieting : '';
                    $_do_it_yourself_affinity = (isset($a[0]->Do_it_Yourself_Affinity)) ? $a[0]->Do_it_Yourself_Affinity : '';
                    $_dog_owner = (isset($a[0]->Dog_Owner)) ? $a[0]->Dog_Owner : '';
                    $_doll_collector = (isset($a[0]->Doll_Collector)) ? $a[0]->Doll_Collector : '';
                    $_donor_affinity = (isset($a[0]->Donor_Affinity)) ? $a[0]->Donor_Affinity : '';
                    $_education = (isset($a[0]->Education)) ? $a[0]->Education : '';
                    $_education_ordinal = (isset($a[0]->Education_Ordinal)) ? $a[0]->Education_Ordinal : '';
                    $_education_seekers_affinity = (isset($a[0]->Education_Seekers_Affinity)) ? $a[0]->Education_Seekers_Affinity : '';
                    $_ego_affinity = (isset($a[0]->EGO_Affinity)) ? $a[0]->EGO_Affinity : '';
                    $_entertainment_interest = (isset($a[0]->Entertainment_Interest)) ? $a[0]->Entertainment_Interest : '';
                    $_figurines_collector = (isset($a[0]->Figurines_Collector)) ? $a[0]->Figurines_Collector : '';
                    $_fine_arts_collector = (isset($a[0]->Fine_Arts_Collector)) ? $a[0]->Fine_Arts_Collector : '';
                    $_fishing = (isset($a[0]->Fishing)) ? $a[0]->Fishing : '';
                    $_fishing_affinity = (isset($a[0]->Fishing_Affinity)) ? $a[0]->Fishing_Affinity : '';
                    $_football = (isset($a[0]->Football)) ? $a[0]->Football : '';
                    $_football_affinity = (isset($a[0]->Football_Affinity)) ? $a[0]->Football_Affinity : '';
                    $_gambling = (isset($a[0]->Gambling)) ? $a[0]->Gambling : '';
                    $_gambling_affinity = (isset($a[0]->Gambling_Affinity)) ? $a[0]->Gambling_Affinity : '';
                    $_games_affinity = (isset($a[0]->Games_Affinity)) ? $a[0]->Games_Affinity : '';
                    $_gardening_affinity = (isset($a[0]->Gardening_Affinity)) ? $a[0]->Gardening_Affinity : '';
                    $_generation_ordinal = (isset($a[0]->Generation_Ordinal)) ? $a[0]->Generation_Ordinal : '';
                    $_golf = (isset($a[0]->Golf)) ? $a[0]->Golf : '';
                    $_golf_affinity = (isset($a[0]->Golf_Affinity)) ? $a[0]->Golf_Affinity : '';
                    $_gourmet_affinity = (isset($a[0]->Gourmet_Affinity)) ? $a[0]->Gourmet_Affinity : '';
                    $_grandparents_affinity = (isset($a[0]->Grandparents_Affinity)) ? $a[0]->Grandparents_Affinity : '';
                    $_health_affinity = (isset($a[0]->Health_Affinity)) ? $a[0]->Health_Affinity : '';
                    $_healthy_living = (isset($a[0]->Healthy_Living)) ? $a[0]->Healthy_Living : '';
                    $_healthy_living_interest = (isset($a[0]->Healthy_Living_Interest)) ? $a[0]->Healthy_Living_Interest : '';
                    $_high_tech_affinity = (isset($a[0]->High_Tech_Affinity)) ? $a[0]->High_Tech_Affinity : '';
                    $_hispanic_affinity = (isset($a[0]->Hispanic_Affinity)) ? $a[0]->Hispanic_Affinity : '';
                    $_hockey = (isset($a[0]->Hockey)) ? $a[0]->Hockey : '';
                    $_hockey_affinity = (isset($a[0]->Hockey_Affinity)) ? $a[0]->Hockey_Affinity : '';
                    $_home_decor = (isset($a[0]->Home_Decor)) ? $a[0]->Home_Decor : '';
                    $_home_improvement_interest = (isset($a[0]->Home_Improvement_Interest)) ? $a[0]->Home_Improvement_Interest : '';
                    $_home_office_affinity = (isset($a[0]->Home_Office_Affinity)) ? $a[0]->Home_Office_Affinity : '';
                    $_home_study = (isset($a[0]->Home_Study)) ? $a[0]->Home_Study : '';
                    $_hunting = (isset($a[0]->Hunting)) ? $a[0]->Hunting : '';
                    $_hunting_affinity = (isset($a[0]->Hunting_Affinity)) ? $a[0]->Hunting_Affinity : '';
                    $_jazz = (isset($a[0]->Jazz)) ? $a[0]->Jazz : '';
                    $_kids_apparel_affinity = (isset($a[0]->Kids_Apparel_Affinity)) ? $a[0]->Kids_Apparel_Affinity : '';
                    $_knit_affinity = (isset($a[0]->Knit_Affinity)) ? $a[0]->Knit_Affinity : '';
                    $_knitting_quilting_sewing = (isset($a[0]->Knitting_Quilting_Sewing)) ? $a[0]->Knitting_Quilting_Sewing : '';
                    $_luxury_life = (isset($a[0]->Luxury_Life)) ? $a[0]->Luxury_Life : '';
                    $_married = (isset($a[0]->Married)) ? $a[0]->Married : '';
                    $_median_income = (isset($a[0]->Median_Income)) ? $a[0]->Median_Income : '';
                    $_mens_apparel_affinity = (isset($a[0]->Mens_Apparel_Affinity)) ? $a[0]->Mens_Apparel_Affinity : '';
                    $_mens_fashion_affinity = (isset($a[0]->Mens_Fashion_Affinity)) ? $a[0]->Mens_Fashion_Affinity : '';
                    $_mortgage_age = (isset($a[0]->Mortgage_Age)) ? $a[0]->Mortgage_Age : '';
                    $_mortgage_amount = (isset($a[0]->Mortgage_Amount)) ? $a[0]->Mortgage_Amount : '';
                    $_mortgage_loan_type = (isset($a[0]->Mortgage_Loan_Type)) ? $a[0]->Mortgage_Loan_Type : '';
                    $_mortgage_refi_age = (isset($a[0]->Mortgage_Refi_Age)) ? $a[0]->Mortgage_Refi_Age : '';
                    $_mortgage_refi_amount = (isset($a[0]->Mortgage_Refi_Amount)) ? $a[0]->Mortgage_Refi_Amount : '';
                    $_mortgage_refi_type = (isset($a[0]->Mortgage_Refi_Type)) ? $a[0]->Mortgage_Refi_Type : '';
                    $_motor_racing = (isset($a[0]->Motor_Racing)) ? $a[0]->Motor_Racing : '';
                    $_motorcycles = (isset($a[0]->Motorcycles)) ? $a[0]->Motorcycles : '';
                    $_motorcycles_affinity = (isset($a[0]->Motorcycles_Affinity)) ? $a[0]->Motorcycles_Affinity : '';
                    $_movies = (isset($a[0]->Movies)) ? $a[0]->Movies : '';
                    $_nascar = (isset($a[0]->NASCAR)) ? $a[0]->NASCAR : '';
                    $_needlepoint_affinity = (isset($a[0]->Needlepoint_Affinity)) ? $a[0]->Needlepoint_Affinity : '';
                    $_new_credit_offered_household = (isset($a[0]->New_Credit_Offered_HH)) ? $a[0]->New_Credit_Offered_HH : '';
                    $_num_credit_lines_household = (isset($a[0]->Num_Credit_Lines_HH)) ? $a[0]->Num_Credit_Lines_HH : '';
                    $_num_generations_household = (isset($a[0]->Num_Generations_HH)) ? $a[0]->Num_Generations_HH : '';
                    $_outdoors = (isset($a[0]->Outdoors)) ? $a[0]->Outdoors : '';
                    $_outdoors_affinity = (isset($a[0]->Outdoors_Affinity)) ? $a[0]->Outdoors_Affinity : '';
                    $_owns_investments = (isset($a[0]->Owns_Investments)) ? $a[0]->Owns_Investments : '';
                    $_owns_mutual_funds = (isset($a[0]->Owns_Mutual_Funds)) ? $a[0]->Owns_Mutual_Funds : '';
                    $_owns_stocks_and_bonds = (isset($a[0]->Owns_Stocks_And_Bonds)) ? $a[0]->Owns_Stocks_And_Bonds : '';
                    $_owns_swimming_pool = (isset($a[0]->Owns_Swimming_Pool)) ? $a[0]->Owns_Swimming_Pool : '';
                    $_personal_finance_affinity = (isset($a[0]->Personal_Finance_Affinity)) ? $a[0]->Personal_Finance_Affinity : '';
                    $_personality = (isset($a[0]->Personality)) ? $a[0]->Personality : '';
                    $_plates_collector = (isset($a[0]->Plates_Collector)) ? $a[0]->Plates_Collector : '';
                    $_pop_density = (isset($a[0]->Pop_Density)) ? $a[0]->Pop_Density : '';
                    $_premium_amex_card = (isset($a[0]->Premium_AMEX_Card)) ? $a[0]->Premium_AMEX_Card : '';
                    $_premium_card = (isset($a[0]->Premium_Card)) ? $a[0]->Premium_Card : '';
                    $_premium_income_household = (isset($a[0]->Premium_Income_HH)) ? $a[0]->Premium_Income_HH : '';
                    $_premium_income_midpt_household = (isset($a[0]->Premium_Income_Midpt_HH)) ? $a[0]->Premium_Income_Midpt_HH : '';
                    $_quilt_affinity = (isset($a[0]->Quilt_Affinity)) ? $a[0]->Quilt_Affinity : '';
                    $_religious_music = (isset($a[0]->Religious_Music)) ? $a[0]->Religious_Music : '';
                    $_rhythm_and_blues = (isset($a[0]->Rhythm_and_Blues)) ? $a[0]->Rhythm_and_Blues : '';
                    $_rock_music = (isset($a[0]->Rock_Music)) ? $a[0]->Rock_Music : '';
                    $_running = (isset($a[0]->Running)) ? $a[0]->Running : '';
                    $_rv = (isset($a[0]->RV)) ? $a[0]->RV : '';
                    $_scuba = (isset($a[0]->Scuba)) ? $a[0]->Scuba : '';
                    $_self_improvement = (isset($a[0]->Self_Improvement)) ? $a[0]->Self_Improvement : '';
                    $_sewing_affinity = (isset($a[0]->Sewing_Affinity)) ? $a[0]->Sewing_Affinity : '';
                    $_single_family_dwelling = (isset($a[0]->Single_Family_Dwelling)) ? $a[0]->Single_Family_Dwelling : '';
                    $_snow_skiing = (isset($a[0]->Snow_Skiing)) ? $a[0]->Snow_Skiing : '';
                    $_snow_skiing_affinity = (isset($a[0]->Snow_Skiing_Affinity)) ? $a[0]->Snow_Skiing_Affinity : '';
                    $_soccer = (isset($a[0]->Soccer)) ? $a[0]->Soccer : '';
                    $_soccer_affinity = (isset($a[0]->Soccer_Affinity)) ? $a[0]->Soccer_Affinity : '';
                    $_soft_rock = (isset($a[0]->Soft_Rock)) ? $a[0]->Soft_Rock : '';
                    $_soho_business = (isset($a[0]->SOHO_Business)) ? $a[0]->SOHO_Business : '';
                    $_sports_memoribilia_collector = (isset($a[0]->Sports_Memoribilia_Collector)) ? $a[0]->Sports_Memoribilia_Collector : '';
                    $_stamps = (isset($a[0]->Stamps)) ? $a[0]->Stamps : '';
                    $_sweepstakes_affinity = (isset($a[0]->Sweepstakes_Affinity)) ? $a[0]->Sweepstakes_Affinity : '';
                    $_tennis = (isset($a[0]->Tennis)) ? $a[0]->Tennis : '';
                    $_tennis_affinity = (isset($a[0]->Tennis_Affinity)) ? $a[0]->Tennis_Affinity : '';
                    $_tobacco_affinity = (isset($a[0]->Tobacco_Affinity)) ? $a[0]->Tobacco_Affinity : '';
                    $_travel_affinity = (isset($a[0]->Travel_Affinity)) ? $a[0]->Travel_Affinity : '';
                    $_travel_cruise_affinity = (isset($a[0]->Travel_Cruise_Affinity)) ? $a[0]->Travel_Cruise_Affinity : '';
                    $_travel_cruises = (isset($a[0]->Travel_Cruises)) ? $a[0]->Travel_Cruises : '';
                    $_travel_personal = (isset($a[0]->Travel_Personal)) ? $a[0]->Travel_Personal : '';
                    $_travel_rv_affinity = (isset($a[0]->Travel_RV_Affinity)) ? $a[0]->Travel_RV_Affinity : '';
                    $_travel_us_affinity = (isset($a[0]->Travel_US_Affinity)) ? $a[0]->Travel_US_Affinity : '';
                    $_truck_owner = (isset($a[0]->Truck_Owner)) ? $a[0]->Truck_Owner : '';
                    $_trucks_affinity = (isset($a[0]->Trucks_Affinity)) ? $a[0]->Trucks_Affinity : '';
                    $_tv_movies_affinity = (isset($a[0]->TV_Movies_Affinity)) ? $a[0]->TV_Movies_Affinity : '';
                    $_veteran_household = (isset($a[0]->Veteran_HH)) ? $a[0]->Veteran_HH : '';
                    $_walking = (isset($a[0]->Walking)) ? $a[0]->Walking : '';
                    $_weight_lifting = (isset($a[0]->Weight_Lifting)) ? $a[0]->Weight_Lifting : '';
                    $_wildlife_affinity = (isset($a[0]->Wildlife_Affinity)) ? $a[0]->Wildlife_Affinity : '';
                    $_womens_apparel_affinity = (isset($a[0]->Womens_Apparel_Affinity)) ? $a[0]->Womens_Apparel_Affinity : '';
                    $_womens_fashion_affinity = (isset($a[0]->Womens_Fashion_Affinity)) ? $a[0]->Womens_Fashion_Affinity : '';
                    $_woodworking = (isset($a[0]->Woodworking)) ? $a[0]->Woodworking : '';
                    $_gender = (isset($a[0]->Gender)) ? $a[0]->Gender : '';
                    $_male_aux = (isset($a[0]->Male_aux)) ? $a[0]->Male_aux : '';
                    $_political_contributor_aux = (isset($a[0]->Political_Contributor_aux)) ? $a[0]->Political_Contributor_aux : '';
                    $_political_party_aux = (isset($a[0]->Political_Party_aux)) ? $a[0]->Political_Party_aux : '';
                    $_financial_power = (isset($a[0]->Financial_Power)) ? $a[0]->Financial_Power : '';
                    $_mortgage_open_1st_intent = (isset($a[0]->Mortgage_Open1st_Intent)) ? $a[0]->Mortgage_Open1st_Intent : '';
                    $_mortgage_open_2nd_intent = (isset($a[0]->Mortgage_Open2nd_Intent)) ? $a[0]->Mortgage_Open2nd_Intent : '';
                    $_mortgage_new_intent = (isset($a[0]->Mortgage_New_Intent)) ? $a[0]->Mortgage_New_Intent : '';
                    $_mortgage_refinance_intent = (isset($a[0]->Mortgage_Refinance_Intent)) ? $a[0]->Mortgage_Refinance_Intent : '';
                    $_automotive_loan_intent = (isset($a[0]->Automotive_Loan_Intent)) ? $a[0]->Automotive_Loan_Intent : '';
                    $_bank_card_intent = (isset($a[0]->Bank_Card_Intent)) ? $a[0]->Bank_Card_Intent : '';
                    $_personal_loan_intent = (isset($a[0]->Personal_Loan_Intent)) ? $a[0]->Personal_Loan_Intent : '';
                    $_retail_card_intent = (isset($a[0]->Retail_Card_Intent)) ? $a[0]->Retail_Card_Intent : '';
                    $_student_loan_cons_intent = (isset($a[0]->Student_Loan_Cons_Intent)) ? $a[0]->Student_Loan_Cons_Intent : '';
                    $_student_loan_intent = (isset($a[0]->Student_Loan_Intent)) ? $a[0]->Student_Loan_Intent : '';
                    $_3qtr_baths = (isset($a[0]) && property_exists($a[0], '3QTR_BATHS')) ? $a[0]->{'3QTR_BATHS'} : '';
                    $_ac_type = (isset($a[0]->AC_TYPE)) ? $a[0]->AC_TYPE : '';
                    $_acres = (isset($a[0]->ACRES)) ? $a[0]->ACRES : '';
                    $_additions_square_feet = (isset($a[0]->AdditionsSquareFeet)) ? $a[0]->AdditionsSquareFeet : '';
                    $_assess_val_impr = (isset($a[0]->ASSESS_VAL_IMPR)) ? $a[0]->ASSESS_VAL_IMPR : '';
                    $_assess_val_lnd = (isset($a[0]->ASSESS_VAL_LND)) ? $a[0]->ASSESS_VAL_LND : '';
                    $_assess_val_prop = (isset($a[0]->ASSESS_VAL_PROP)) ? $a[0]->ASSESS_VAL_PROP : '';
                    $_bldg_style = (isset($a[0]->BLDG_STYLE)) ? $a[0]->BLDG_STYLE : '';
                    $_bsmt_sqft = (isset($a[0]->BSMT_SQFT)) ? $a[0]->BSMT_SQFT : '';
                    $_bsmt_type = (isset($a[0]->BSMT_TYPE)) ? $a[0]->BSMT_TYPE : '';
                    $_build_sqft_assess = (isset($a[0]->BUILD_SQFT_ASSESS)) ? $a[0]->BUILD_SQFT_ASSESS : '';
                    $_business = (isset($a[0]->BUSINESS)) ? $a[0]->BUSINESS : '';
                    $_combined_statistical_area = (isset($a[0]->CombinedStatisticalArea)) ? $a[0]->CombinedStatisticalArea : '';
                    $_metropolitan_division = (isset($a[0]->MetropolitanDivision)) ? $a[0]->MetropolitanDivision : '';
                    $_middle = (isset($a[0]->MIDDLE)) ? $a[0]->MIDDLE : '';
                    $_middle_2 = (isset($a[0]->MIDDLE2)) ? $a[0]->MIDDLE2 : '';
                    $_mkt_ip_perc = (isset($a[0]->Mkt_Ip_Perc)) ? $a[0]->Mkt_Ip_Perc : '';
                    $_mobile_home = (isset($a[0]->MOBILE_HOME)) ? $a[0]->MOBILE_HOME : '';
                    $_mrtg_due = (isset($a[0]->MRTG_DUE)) ? $a[0]->MRTG_DUE : '';
                    $_mrtg_intrate = (isset($a[0]->MRTG_INTRATE)) ? $a[0]->MRTG_INTRATE : '';
                    $_mrtg_refi = (isset($a[0]->MRTG_REFI)) ? $a[0]->MRTG_REFI : '';
                    $_mrtg_term = (isset($a[0]->MRTG_TERM)) ? $a[0]->MRTG_TERM : '';
                    $_mrtg_type = (isset($a[0]->MRTG_TYPE)) ? $a[0]->MRTG_TYPE : '';
                    $_mrtg2_amt = (isset($a[0]->MRTG2_AMT)) ? $a[0]->MRTG2_AMT : '';
                    $_mrtg2_date = (isset($a[0]->MRTG2_DATE)) ? $a[0]->MRTG2_DATE : '';
                    $_mrtg2_deed_type = (isset($a[0]->MRTG2_DEED_TYPE)) ? $a[0]->MRTG2_DEED_TYPE : '';
                    $_mrtg2_due = (isset($a[0]->MRTG2_DUE)) ? $a[0]->MRTG2_DUE : '';
                    $_mrtg2_equity = (isset($a[0]->MRTG2_EQUITY)) ? $a[0]->MRTG2_EQUITY : '';
                    $_mrtg2_intrate = (isset($a[0]->MRTG2_INTRATE)) ? $a[0]->MRTG2_INTRATE : '';
                    $_mrtg2_inttype = (isset($a[0]->MRTG2_INTTYPE)) ? $a[0]->MRTG2_INTTYPE : '';
                    $_mrtg2_refi = (isset($a[0]->MRTG2_REFI)) ? $a[0]->MRTG2_REFI : '';
                    $_mrtg2_term = (isset($a[0]->MRTG2_TERM)) ? $a[0]->MRTG2_TERM : '';
                    $_mrtg2_type = (isset($a[0]->MRTG2_TYPE)) ? $a[0]->MRTG2_TYPE : '';
                    $_mrtg3_amt = (isset($a[0]->MRTG3_AMT)) ? $a[0]->MRTG3_AMT : '';
                    $_mrtg3_date = (isset($a[0]->MRTG3_DATE)) ? $a[0]->MRTG3_DATE : '';
                    $_mrtg3_deed_type = (isset($a[0]->MRTG3_DEED_TYPE)) ? $a[0]->MRTG3_DEED_TYPE : '';
                    $_mrtg3_due = (isset($a[0]->MRTG3_DUE)) ? $a[0]->MRTG3_DUE : '';
                    $_mrtg3_equity = (isset($a[0]->MRTG3_EQUITY)) ? $a[0]->MRTG3_EQUITY : '';
                    $_mrtg3_inttype = (isset($a[0]->MRTG3_INTTYPE)) ? $a[0]->MRTG3_INTTYPE : '';
                    $_mrtg3_refi = (isset($a[0]->MRTG3_REFI)) ? $a[0]->MRTG3_REFI : '';
                    $_mrtg3_term = (isset($a[0]->MRTG3_TERM)) ? $a[0]->MRTG3_TERM : '';
                    $_mrtg3_type = (isset($a[0]->MRTG3_TYPE)) ? $a[0]->MRTG3_TYPE : '';
                    $_msa_code = (isset($a[0]->MSACode)) ? $a[0]->MSACode : '';
                    $_number_bedrooms = (isset($a[0]->NMBR_BEDROOMS)) ? $a[0]->NMBR_BEDROOMS : '';
                    $_number_bldgs = (isset($a[0]->NMBR_BLDGS)) ? $a[0]->NMBR_BLDGS : '';
                    $_number_fireplace = (isset($a[0]->NMBR_FIREPLACE)) ? $a[0]->NMBR_FIREPLACE : '';
                    $_number_park_spaces = (isset($a[0]->NMBR_PARK_SPACES)) ? $a[0]->NMBR_PARK_SPACES : '';
                    $_number_rooms = (isset($a[0]->number_rooms)) ? $a[0]->number_rooms : '';
                    $_own_biz = (isset($a[0]->OWN_BIZ)) ? $a[0]->OWN_BIZ : '';
                    $_owner_occupied = (isset($a[0]->OWNER_OCCUPIED)) ? $a[0]->OWNER_OCCUPIED : '';
                    $_ownership_relation = (isset($a[0]->OwnershipRelation)) ? $a[0]->OwnershipRelation : '';
                    $_owner_type_description = (isset($a[0]->OwnerTypeDescription)) ? $a[0]->OwnerTypeDescription : '';
                    $_estimated_value = (isset($a[0]->EstimatedValue)) ? $a[0]->EstimatedValue : '';
                    $_ext_type = (isset($a[0]->EXT_TYPE)) ? $a[0]->EXT_TYPE : '';
                    $_finish_square_feet2 = (isset($a[0]->FinishSquareFeet2)) ? $a[0]->FinishSquareFeet2 : '';
                    $_fireplace = (isset($a[0]->fireplace)) ? $a[0]->fireplace : '';
                    $_first = (isset($a[0]->FIRST)) ? $a[0]->FIRST : '';
                    $_first_2 = (isset($a[0]->FIRST2)) ? $a[0]->FIRST2 : '';
                    $_found_type = (isset($a[0]->FOUND_TYPE)) ? $a[0]->FOUND_TYPE : '';
                    $_fr_feet = (isset($a[0]->FR_FEET)) ? $a[0]->FR_FEET : '';
                    $_fuel_type = (isset($a[0]->FUEL_TYPE)) ? $a[0]->FUEL_TYPE : '';
                    $_full_baths = (isset($a[0]->FULL_BATHS)) ? $a[0]->FULL_BATHS : '';
                    $_half_baths = (isset($a[0]->HALF_BATHS)) ? $a[0]->HALF_BATHS : '';
                    $_garage_type = (isset($a[0]->GAR_TYPE)) ? $a[0]->GAR_TYPE : '';
                    $_grnd_sqft = (isset($a[0]->GRND_SQFT)) ? $a[0]->GRND_SQFT : '';
                    $_heat_type = (isset($a[0]->HEAT_TYPE)) ? $a[0]->HEAT_TYPE : '';
                    $_hmstd = (isset($a[0]->HMSTD)) ? $a[0]->HMSTD : '';
                    $_impr_appr_val = (isset($a[0]->IMPR_APPR_VAL)) ? $a[0]->IMPR_APPR_VAL : '';
                    $_imprval = (isset($a[0]->IMPRVAL)) ? $a[0]->IMPRVAL : '';
                    $_imprval_type = (isset($a[0]->IMPRVAL_TYPE)) ? $a[0]->IMPRVAL_TYPE : '';
                    $_land_appr_val = (isset($a[0]->LAND_APPR_VAL)) ? $a[0]->LAND_APPR_VAL : '';
                    $_landval = (isset($a[0]->LANDVAL)) ? $a[0]->LANDVAL : '';
                    $_landval_type = (isset($a[0]->LANDVAL_TYPE)) ? $a[0]->LANDVAL_TYPE : '';
                    $_last = (isset($a[0]->LAST)) ? $a[0]->LAST : '';
                    $_last_2 = (isset($a[0]->LAST2)) ? $a[0]->LAST2 : '';
                    $_lat = (isset($a[0]->LAT)) ? $a[0]->LAT : '';
                    $_lender_name = (isset($a[0]->LENDER_NAME)) ? $a[0]->LENDER_NAME : '';
                    $_lender2_name = (isset($a[0]->LENDER2_NAME)) ? $a[0]->LENDER2_NAME : '';
                    $_lender3_name = (isset($a[0]->LENDER3_NAME)) ? $a[0]->LENDER3_NAME : '';
                    $_loan_amt = (isset($a[0]->LOAN_AMT)) ? $a[0]->LOAN_AMT : '';
                    $_loan_date = (isset($a[0]->LOAN_DATE)) ? $a[0]->LOAN_DATE : '';
                    $_loan_to_val = (isset($a[0]->LOAN_TO_VAL)) ? $a[0]->LOAN_TO_VAL : '';
                    $_lon = (isset($a[0]->LON)) ? $a[0]->LON : '';
                    $_markval = (isset($a[0]->MARKVAL)) ? $a[0]->MARKVAL : '';
                    $_markval_type = (isset($a[0]->MARKVAL_TYPE)) ? $a[0]->MARKVAL_TYPE : '';
                    $_patio_porch = (isset($a[0]->PatioPorch)) ? $a[0]->PatioPorch : '';
                    $_patio_square_feet = (isset($a[0]->PatioSquareFeet)) ? $a[0]->PatioSquareFeet : '';
                    $_pool = (isset($a[0]->POOL)) ? $a[0]->POOL : '';
                    $_porch_square_feet = (isset($a[0]->PorchSquareFeet)) ? $a[0]->PorchSquareFeet : '';
                    $_previous_assessed_value = (isset($a[0]->PreviousAssessedValue)) ? $a[0]->PreviousAssessedValue : '';
                    $_prop_type = (isset($a[0]->PROP_TYPE)) ? $a[0]->PROP_TYPE : '';
                    $_rate_type = (isset($a[0]->RATE_TYPE)) ? $a[0]->RATE_TYPE : '';
                    $_rec_date = (isset($a[0]->REC_DATE)) ? $a[0]->REC_DATE : '';
                    $_roof_covtype = (isset($a[0]->ROOF_COVTYPE)) ? $a[0]->ROOF_COVTYPE : '';
                    $_roof_shapetype = (isset($a[0]->ROOF_SHAPETYPE)) ? $a[0]->ROOF_SHAPETYPE : '';
                    $_sale_amt = (isset($a[0]->SALE_AMT)) ? $a[0]->SALE_AMT : '';
                    $_sale_amt_pr = (isset($a[0]->SALE_AMT_PR)) ? $a[0]->SALE_AMT_PR : '';
                    $_sale_date = (isset($a[0]->SALE_DATE)) ? $a[0]->SALE_DATE : '';
                    $_sale_type_pr = (isset($a[0]->SALE_TYPE_PR)) ? $a[0]->SALE_TYPE_PR : '';
                    $_sales_type = (isset($a[0]->SALES_TYPE)) ? $a[0]->SALES_TYPE : '';
                    $_sell_name = (isset($a[0]->SELL_NAME)) ? $a[0]->SELL_NAME : '';
                    $_sewer_type = (isset($a[0]->SEWER_TYPE)) ? $a[0]->SEWER_TYPE : '';
                    $_site_quality = (isset($a[0]->SITE_QUALITY)) ? $a[0]->SITE_QUALITY : '';
                    $_std_address = (isset($a[0]->STD_ADDRESS)) ? $a[0]->STD_ADDRESS : '';
                    $_std_city = (isset($a[0]->STD_CITY)) ? $a[0]->STD_CITY : '';
                    $_std_state = (isset($a[0]->STD_STATE)) ? $a[0]->STD_STATE : '';
                    $_std_zip = (isset($a[0]->STD_ZIP)) ? $a[0]->STD_ZIP : '';
                    $_std_zip4 = (isset($a[0]->STD_ZIP4)) ? $a[0]->STD_ZIP4 : '';
                    $_stories_number = (isset($a[0]->STORIES_NMBR)) ? $a[0]->STORIES_NMBR : '';
                    $_suffix = (isset($a[0]->SUFFIX)) ? $a[0]->SUFFIX : '';
                    $_suffix_2 = (isset($a[0]->SUFFIX2)) ? $a[0]->SUFFIX2 : '';
                    $_tax_yr = (isset($a[0]->TAX_YR)) ? $a[0]->TAX_YR : '';
                    $_tax_improvement_percent = (isset($a[0]->TaxImprovementPercent)) ? $a[0]->TaxImprovementPercent : '';
                    $_title_co = (isset($a[0]->TITLE_CO)) ? $a[0]->TITLE_CO : '';
                    $_tot_baths_est = (isset($a[0]->TOT_BATHS_EST)) ? $a[0]->TOT_BATHS_EST : '';
                    $_ttl_appr_val = (isset($a[0]->TTL_APPR_VAL)) ? $a[0]->TTL_APPR_VAL : '';
                    $_ttl_bld_sqft = (isset($a[0]->TTL_BLD_SQFT)) ? $a[0]->TTL_BLD_SQFT : '';
                    $_ttl_tax = (isset($a[0]->TTL_TAX)) ? $a[0]->TTL_TAX : '';
                    $_unit_number = (isset($a[0]->UNIT_NMBR)) ? $a[0]->UNIT_NMBR : '';
                    $_vet_exempt = (isset($a[0]->VET_EXEMPT)) ? $a[0]->VET_EXEMPT : '';
                    $_water_type = (isset($a[0]->WATER_TYPE)) ? $a[0]->WATER_TYPE : '';
                //unregistered row
            }
            /* BUILD DATA */

            $uniqueID = uniqid();
            /** INSERT INTO DATABASE PERSON */
            // info('INSERT INTO DATABASE PERSON');
            $newPerson = Person::create([
                'uniqueID' => $uniqueID,
                'firstName' => $_FirstName,
                'middleName' => '',
                'lastName' => $_LastName,
                'age' => '0',
                'identityScore' => '0',
                'lastEntry' => date('Y-m-d H:i:s'),
            ]);
            $newPersonID = $newPerson->id;
            /** INSERT INTO DATABASE PERSON */

            /** SEPARATE BETWEEN YAHOO/AOL AND OTHER EMAIL */
            $filteredEmails = [];
            $otherEmails = [];
            foreach ($bigEmail as $index => $email) 
            {
                if (strpos($email, 'yahoo.com') !== false || strpos($email, 'aol.com') !== false) 
                {
                    $filteredEmails[] = $email;
                } 
                else 
                {
                    $otherEmails[] = $email;
                }
            }
            // info("process_BIGDBM_advance 1.1", ['otherEmails' => $otherEmails, 'filteredEmails' => $filteredEmails]);
            /** SEPARATE BETWEEN YAHOO/AOL AND OTHER EMAIL */

            Log::info("BigBDM Have result - CHECK ZERO BOUNCE");
            /** NEW METHOD TO CHECK AND GET EMAIL */
            foreach($otherEmails as $index => $be) 
            {
                if (trim($be) != "") 
                {
                    $tmpEmail = strtolower(trim($be));
                    $tmpMd5 = md5($tmpEmail);

                    $startZbValidationTime = microtime(true);

                    $param = [
                        'clean_file_id' => $file_id,
                        'leadspeek_api_id' => $leadspeek_api_id,
                        'leadspeek_type' => $leadspeek_type,
                        'md5param' => $tmpMd5,
                    ];
                    $zbcheck = $this->controller->true_list_validation($tmpEmail,$param);
                    // info('zbcheck1', ['zbcheck' => $zbcheck]);

                    $endZbValidationTime = microtime(true);

                    // convert epochtime to date format ('Y-m-d H:i:s')
                    $startZbValidationDate = Carbon::createFromTimestamp($startZbValidationTime)->setTimezone('America/Chicago')->format('Y-m-d H:i:s');
                    $endZbValidationDate = Carbon::createFromTimestamp($endZbValidationTime)->setTimezone('America/Chicago')->format('Y-m-d H:i:s');
                    // convert epochtime to date format ('Y-m-d H:i:s')

                    $totalZbValidationTime = $endZbValidationTime - $startZbValidationTime;
                    $totalZbValidationTime = number_format($totalZbValidationTime,2,'.','');

                    $executionTimeList['zb_validation'] = [
                        'start_execution_time' => $startZbValidationDate,
                        'end_execution_time' => $endZbValidationDate,
                        'total_execution_time' => $totalZbValidationTime
                    ];

                    // Handle both TrueList and ZeroBounce responses
                    $isValid = false;
                    $validationStatus = '';
                    $apiType = '';
                    
                    // Check TrueList response format
                    if (isset($zbcheck->emails[0]->email_state)) 
                    {
                        $validationStatus = $zbcheck->emails[0]->email_state;
                        $apiType = 'truelist';
                        $isValid = ($zbcheck->emails[0]->email_state == "ok");
                    }
                    // Check ZeroBounce response format
                    elseif (isset($zbcheck->status)) 
                    {
                        $validationStatus = $zbcheck->status;
                        $apiType = 'zerobounce';
                        $isValid = !($zbcheck->status == "invalid" || $zbcheck->status == "catch-all" || $zbcheck->status == "abuse" || $zbcheck->status == "do_not_mail" || $zbcheck->status == "spamtrap");
                    }

                    if ($validationStatus !== '') 
                    {
                        if (!$isValid) 
                        {
                            /** PUT IT ON OPTOUT LIST */
                            $createoptout = OptoutList::create([
                                'email' => $tmpEmail,
                                'emailmd5' => md5($tmpEmail),
                                'blockedcategory' => 'zbnotvalid',
                                'description' => ucfirst($apiType) . ' Status: ' . $validationStatus . '|Email' . $index . 'fromBigMD5',
                            ]);
                            /** PUT IT ON OPTOUT LIST */

                            if (isset($leadspeek_api_id) && isset($leadspeektype) && isset($tmpMd5)) 
                            {
                                $this->controller->UpsertFailedLeadRecord([
                                    'function' => __FUNCTION__,
                                    'type' => 'blocked',
                                    'blocked_type' => $apiType,
                                    'description' => ucfirst($apiType) . ' Status: ' . $validationStatus . '|Email' . $index . 'fromBigMD5',
                                    'clean_file_id' => $file_id,
                                    'leadspeek_api_id' => $leadspeek_api_id,
                                    'email_encrypt' => $tmpMd5,
                                    'leadspeek_type' => $leadspeektype,
                                    'email' => $tmpEmail,
                                    'status' => $validationStatus,
                                ]);
                            }

                            $trackBigBDM = $trackBigBDM . "->Email" . $index . "ZBFailed";
                        }
                        else
                        {
                            $newpersonemail = PersonEmail::create([
                                'person_id' => $newPersonID,
                                'email' => $tmpEmail,
                                'email_encrypt' => $tmpMd5,
                                'permission' => 'T',
                                'zbvalidate' => date('Y-m-d H:i:s'),
                            ]);

                            if ($_Email ==  "") 
                            {
                                $_Email = $tmpEmail;
                            }
                            else if ($_Email2 == "") 
                            {
                                $_Email2 = $tmpEmail;
                            }

                            $trackBigBDM = $trackBigBDM . "->Email" . $index . "ZBSuccess";
                        }
                        /** REPORT ANALYTIC */
                        // $this->controller->UpsertReportAnalytics($leadspeek_api_id, 'enhance', $apiType . '_details', $validationStatus);
                        /** REPORT ANALYTIC */
                    }
                    else
                    {
                        $trackBigBDM = $trackBigBDM . "->Email" . $index . "ZBNotValidate";
                    }
                }
            }
            /** NEW METHOD TO CHECK AND GET EMAIL */

            /** CHECK IF STANDARD EMAIL NOT GET ANY VALID EMAIL */
            if (trim($_Email) == '') 
            {
                /** NEW METHOD TO CHECK AND GET EMAIL */
                foreach($filteredEmails as $index => $be) 
                {
                    if (trim($be) != "") 
                    {
                        $tmpEmail = strtolower(trim($be));
                        $tmpMd5 = md5($tmpEmail);

                        $startZbValidationTime = microtime(true);
                        $param = [
                            'clean_file_id' => $file_id,
                            'leadspeek_api_id' => $leadspeek_api_id,
                            'leadspeek_type' => $leadspeek_type,
                            'md5param' => $tmpMd5,
                        ];
                        $zbcheck = $this->controller->true_list_validation($tmpEmail,$param);
                        // info('zbcheck2', ['zbcheck' => $zbcheck]);

                        $endZbValidationTime = microtime(true);

                        // convert epochtime to date format ('Y-m-d H:i:s')
                        $startZbValidationDate = Carbon::createFromTimestamp($startZbValidationTime)->setTimezone('America/Chicago')->format('Y-m-d H:i:s');
                        $endZbValidationDate = Carbon::createFromTimestamp($endZbValidationTime)->setTimezone('America/Chicago')->format('Y-m-d H:i:s');
                        // convert epochtime to date format ('Y-m-d H:i:s')

                        $totalZbValidationTime = $endZbValidationTime - $startZbValidationTime;
                        $totalZbValidationTime = number_format($totalZbValidationTime,2,'.','');

                        $executionTimeList['zb_validation'] = [
                            'start_execution_time' => $startZbValidationDate,
                            'end_execution_time' => $endZbValidationDate,
                            'total_execution_time' => $totalZbValidationTime
                        ];

                        // Handle both TrueList and ZeroBounce responses
                        $isValid = false;
                        $validationStatus = '';
                        $apiType = '';
                        
                        // Check TrueList response format
                        if (isset($zbcheck->emails[0]->email_state)) 
                        {
                            $validationStatus = $zbcheck->emails[0]->email_state;
                            $apiType = 'truelist';
                            $isValid = ($zbcheck->emails[0]->email_state == "ok");
                        }
                        // Check ZeroBounce response format
                        elseif (isset($zbcheck->status)) 
                        {
                            $validationStatus = $zbcheck->status;
                            $apiType = 'zerobounce';
                            $isValid = !($zbcheck->status == "invalid" || $zbcheck->status == "catch-all" || $zbcheck->status == "abuse" || $zbcheck->status == "do_not_mail" || $zbcheck->status == "spamtrap");
                        }

                        if ($validationStatus !== '')
                        {
                            if (!$isValid) 
                            {
                                /** PUT IT ON OPTOUT LIST */
                                $createoptout = OptoutList::create([
                                    'email' => $tmpEmail,
                                    'emailmd5' => md5($tmpEmail),
                                    'blockedcategory' => 'zbnotvalid',
                                    'description' => ucfirst($apiType) . ' Status: ' . $validationStatus . '|Email' . $index . 'fromBigMD5',
                                ]);
                                /** PUT IT ON OPTOUT LIST */

                                if (isset($leadspeek_api_id) && isset($leadspeektype) && isset($tmpMd5)) 
                                {
                                    $this->controller->UpsertFailedLeadRecord([
                                        'function' => __FUNCTION__,
                                        'type' => 'blocked',
                                        'blocked_type' => $apiType,
                                        'description' => ucfirst($apiType) . ' Status: ' . $validationStatus . '|Email' . $index . 'fromBigMD5',
                                        'clean_file_id' => $file_id,
                                        'leadspeek_api_id' => $leadspeek_api_id,
                                        'email_encrypt' => $tmpMd5,
                                        'leadspeek_type' => $leadspeektype,
                                        'email' => $tmpEmail,
                                        'status' => $validationStatus,
                                    ]);
                                }

                                $trackBigBDM = $trackBigBDM . "->Email" . $index . "ZBFailed";
                            }
                            else
                            {
                                $newpersonemail = PersonEmail::create([
                                    'person_id' => $newPersonID,
                                    'email' => $tmpEmail,
                                    'email_encrypt' => $tmpMd5,
                                    'permission' => 'T',
                                    'zbvalidate' => date('Y-m-d H:i:s'),
                                ]);

                                if ($_Email ==  "") 
                                {
                                    $_Email = $tmpEmail;
                                    break;
                                }
                                else if ($_Email2 == "") 
                                {
                                    $_Email2 = $tmpEmail;
                                }

                                $trackBigBDM = $trackBigBDM . "->Email" . $index . "ZBSuccess";
                            }
                            /** REPORT ANALYTIC */
                            // $this->controller->UpsertReportAnalytics($leadspeek_api_id, 'enhance', $apiType . '_details', $validationStatus);
                            /** REPORT ANALYTIC */
                        }
                        else
                        {
                            $trackBigBDM = $trackBigBDM . "->Email" . $index . "ZBNotValidate";
                        }
                    }
                }
                /** NEW METHOD TO CHECK AND GET EMAIL */
            }
            /** CHECK IF STANDARD EMAIL NOT GET ANY VALID EMAIL */

            if (trim($_Email) == "" && trim($_Email2) == "") 
            {
                /** REPORT ANALYTIC */
                // $this->controller->UpsertReportAnalytics($file_id,$leadspeek_type,'zerobouncefailed');
                $trackBigBDM = $trackBigBDM . "->Email1andEmail2NotValid";
                /** REPORT ANALYTIC */

                Log::info("BigBDM Have result - CHECK ZERO BOUNCE - Email 1 and Email 2 NOT VALID");
                // /* RELEASE LOCK PROCESS FOR GETDATAMATCH */
                // Log::info("RELEASE GETDATAMATCH LOCK Process DATA BIG BDM - Email 1 and Email 2 NOT VALID CampaignID #" . $leadspeek_api_id);
                // $this->releaseLock($initLock);
                // /* RELEASE LOCK PROCESS FOR GETDATAMATCH */

                /* WRITE UPSER FAILED LEAD RECORD */
                $this->controller->UpsertFailedLeadRecord([
                    'function' => 'process_BDM_advance',
                    'type' => 'blocked',
                    'blocked_type' => 'zerobounce',
                    'description' => 'blocked in truelist fetch bigBDM_MD5 function process_BDM_local_custom',
                    'clean_file_id' => $file_id,
                    'leadspeek_api_id' => $leadspeek_api_id,
                    'email_encrypt' => $md5param,
                    'leadspeek_type' => $leadspeek_type,
                ]);
                /* WRITE UPSER FAILED LEAD RECORD */

                /* CHECK REQUIRE EMAIL */
                $require_email = CleanIDFile::where('id', $file_id)->value('require_email');
                /* CHECK REQUIRE EMAIL */

                if($require_email === 'T')
                {
                    /* DELETE PERSON KETIKA SEMUA EMAIL ZEROBOUNCE TIDAK VALID */
                    Person::where('id', $newPersonID)->where('source', 'bigdbm')->delete();
                    /* DELETE PERSON KETIKA SEMUA EMAIL ZEROBOUNCE TIDAK VALID */
                    
                    $status = "not_found";
                    $msg_description = "Advanced information Not Found Because Truelist Not Valid From BIGDBM";
                    
                    return [
                        'status' => $status,
                        'msg_description' => $msg_description,
                    ];
                }
            }
            else
            {
                /** REPORT ANALYTIC */
                // $this->controller->UpsertReportAnalytics($file_id,$leadspeek_type,'zerobounce');
                $trackBigBDM = $trackBigBDM . "->Email1orEmail2Valid";
                /** REPORT ANALYTIC */
                Log::info("BigBDM Have result - CHECK TRUE LIST - Email 1 or Email 2 VALID");

                // /* RELEASE LOCK PROCESS FOR GETDATAMATCH */
                // Log::info("RELEASE GETDATAMATCH LOCK Process DATA BIG BDM - Email 1 and Email 2 VALID CampaignID #" . $leadspeek_api_id);
                // $this->releaseLock($initLock);
                // /* RELEASE LOCK PROCESS FOR GETDATAMATCH */
            }

            Log::info("BigBDM BDM_ADVANCE CHECK PHONE1"); 
            if (trim($_mobile_phone) != "") 
            {
                /** INSERT PERSON_PHONES */
                $newpersonphone = PersonPhone::create([
                    'person_id' => $newPersonID,
                    'number' => $this->format_phone($_mobile_phone),
                    'type' => 'user',
                    'isConnected' => 'T',
                    'firstReportedDate' => date('Y-m-d'),
                    'lastReportedDate' => date('Y-m-d'),
                    'permission' => 'F',
                ]);
                /** INSERT PERSON_PHONES */
            }

            Log::info("BigBDM BDM_ADVANCE CHECK PHONE2");
            if (trim($_mobile_phone2) != "")
            {
                /** INSERT PERSON_PHONES */
                $newpersonphone = PersonPhone::create([
                    'person_id' => $newPersonID,
                    'number' => $this->format_phone($_mobile_phone2),
                    'type' => 'user',
                    'isConnected' => 'T',
                    'firstReportedDate' => date('Y-m-d'),
                    'lastReportedDate' => date('Y-m-d'),
                    'permission' => 'F',
                ]);
                /** INSERT PERSON_PHONES */
            }

            Log::info("BigBDM BDM_ADVANCE CHECK PHONE3");
            if (trim($_mobile_phone3) != "")
            {
                /** INSERT PERSON_PHONES */
                $newpersonphone = PersonPhone::create([
                    'person_id' => $newPersonID,
                    'number' => $this->format_phone($_mobile_phone3),
                    'type' => 'user',
                    'isConnected' => 'T',
                    'firstReportedDate' => date('Y-m-d'),
                    'lastReportedDate' => date('Y-m-d'),
                    'permission' => 'F',
                ]);
                /** INSERT PERSON_PHONES */
            }

            Log::info("BigBDM BDM_ADVANCE CHECK Address");
            /** INSERT INTO PERSON_ADDRESSES */
            $newpersonaddress = PersonAddress::create([
                'person_id' => $newPersonID,
                'street' => $_Address1,
                'unit' => $_Address2,
                'city' => $_City,
                'state' => $_State,
                'zip' => $_Zipcode,
                'fullAddress' => $_Address1 . ' ' . $_City . ',' . $_State . ' ' . $_Zipcode,
                'firstReportedDate' => date('Y-m-d'),
                'lastReportedDate' => date('Y-m-d'),
            ]);
            /** INSERT INTO PERSON_ADDRESSES */

            /* TAKE TWO PHONES FROM THE 3 MOBILE PHONES */
            Log::info('TAKE TWO PHONES FROM THE 3 MOBILE PHONES');
            if (trim($_mobile_phone) != "" || trim($_mobile_phone2) != "" || trim($_mobile_phone3) != "")
            {
                // taruh paling atas phone yang tidak kosong
                $_Phones = array_filter([
                    $_mobile_phone, 
                    $_mobile_phone2, 
                    $_mobile_phone3
                ], function($value) {
                    return trim($value) !== '';
                });
                $_Phone = $_Phones[0] ?? "";
                $_Phone2 = $_Phones[1] ?? "";
                // taruh paling atas phone yang tidak kosong
            }
            /* TAKE TWO PHONES FROM THE 3 MOBILE PHONES */
                
            /** INSERT INTO CLEAN ID RESULT */
            // info("INSERT INTO CLEAN ID RESULT");
            CleanIDResult::create([
                'file_id' => $file_id,
                'md5_id' => $md5_id,
                'bigdbm_id' => $uniqueID,
                'first_name' => $_FirstName,
                'last_name' => $_LastName,
                'city' => $_City,
                'state' => $_State,
                'zip' => $_Zipcode,
                'address' => $_Address1,
                'address2' => $_Address2,
                'phone' => $_Phone,
                'phone2' => $_Phone2,
                'email' => $_Email,
                'email2' => $_Email2,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
            /** END INSERT CLEAN ID RESULT */

            //INSERT PERSON ADVANCE 1 
            // info("INSERT PERSON ADVANCE 1");
            $newpersonadvance1 = PersonAdvance::create([
                'person_id' => $newPersonID,
                'gender_aux' => $_gender_aux,
                'gender' => $_gender,
                'age_aux' => $_age_aux,
                'birth_year_aux' => $_birth_year_aux,
                'generation' => $_generation,
                'marital_status' => $_marital_status,
                'income_household' => $_household_income,
                'income_midpts_household' => $_median_household_income,
                'net_worth_household' => $_household_net_worth,
                'net_worth_midpt_household' => $_median_household_net_worth,
                'discretionary_income' => $_discretionary_income,
                'credit_midpts' => $_credit_score_median,
                'credit_range' => $_credit_score_range,
                'occupation_category' => $_occupation_category,
                'occupation_detail' => $_occupation_detail,
                'occupation_type' => $_occupation_type,
                'voter' => $_voter,
                'urbanicity' => $_urbanicity,
                'mobile_phone_1' => $_mobile_phone,
                'mobile_phone_2' => $_mobile_phone2,
                'mobile_phone_3' => $_mobile_phone3,
                'mobile_phone_1_dnc' => $_mobile_phone_dnc,
                'mobile_phone_2_dnc' => $_mobile_phone_dnc2,
                'mobile_phone_3_dnc' => $_mobile_phone_dnc3,
                'tax_bill_mailing_address' => $_tax_bill_mailing_address,
                'tax_bill_mailing_city' => $_tax_bill_mailing_city,
                'tax_bill_mailing_county' => $_tax_bill_mailing_county,
                'tax_bill_mailing_fips' => $_tax_bill_mailing_fips,
                'tax_bill_mailing_state' => $_tax_bill_mailing_state,
                'tax_bill_mailing_zip' => $_tax_bill_mailing_zip,
                'tax_bill_mailing_zip4' => $_tax_bill_mailing_zip4,
                'num_adults_household' => $_num_adults_household,
                'num_children_household' => $_num_children_household,
                'num_persons_household' => $_num_persons_household,
                'child_aged_0_3_household' => $_child_aged_0_3_household,
                'child_aged_4_6_household' => $_child_aged_4_6_household,
                'child_aged_7_9_household' => $_child_aged_7_9_household,
                'child_aged_10_12_household' => $_child_aged_10_12_household,
                'child_aged_13_18_household' => $_child_aged_13_18_household,
                'children_household' => $_children_household,
                'has_email' => $_has_email,
                'has_phone' => $_has_phone,
                'magazine_subscriber' => $_magazine_subscriber,
                'charity_interest' => $_charity_interest,
                'likely_charitable_donor' => $_likely_charitable_donor,
                'donor_affinity' => $_donor_affinity,
                'dwelling_type' => $_dwelling_type,
                'home_owner' => $_home_owner,
                'home_owner_ordinal' => $_home_owner_ordinal,
                'length_of_residence' => $_length_of_residence,
                'home_price' => $_home_price,
                'home_value' => $_home_value,
                'median_home_value' => $_median_home_value,
                'living_sqft' => $_living_sqft,
                'yr_built_orig' => $_year_built_original,
                'yr_built_range' => $_year_built_range,
                'lot_number' => $_lot_number,
                'legal_description' => $_legal_description,
                'land_sqft' => $_land_sqft,
                'garage_sqft' => $_garage_sqft,
                'subdivision' => $_subdivision,
                'zoning_code' => $_zoning_code,
                'cooking' => $_cooking,
                'gardening' => $_gardening,
                'music' => $_music,
                'diy' => $_diy,
                'books' => $_books,
                'travel_vacation' => $_travel_vacation,
                'health_beauty_products' => $_health_beauty_products,
                'pet_owner' => $_pet_owner,
                'photography' => $_photography,
                'fitness' => $_fitness,
                'epicurean' => $_epicurean,
                'cbsa' => $_cbsa,
                'census_block' => $_census_block,
                'census_block_group' => $_census_block_group,
                'census_tract' => $_census_tract,
                
                'aerobics' => $_aerobics,
                'african_american_affinity' => $_african_american_affinity ,
                'amex_card' => $_amex_card,
                'antiques' => $_antiques,
                'apparel_accessory_affinity' => $_apparel_accessory_affinity,
                'apparel_affinity' => $_apparel_affinity,
                'arts_and_crafts' => $_arts_and_crafts,
                'asian_affinity' => $_asian_affinity,
                'auto_affinity' => $_auto_affinity,
                'auto_racing_affinity' => $_auto_racing_affinity,
                'aviation_affinity' => $_aviation_affinity,
                'bank_card' => $_bank_card,
                'bargain_hunter_affinity' => $_bargain_hunter_affinity,
                'baseball' => $_baseball,
                'baseball_affinity' => $_baseball_affinity,
                'basketball' => $_basketball,
                'basketball_affinity' => $_basketball_affinity,
                'beauty_affinity' => $_beauty_affinity,
                'bible_affinity' => $_bible_affinity,
                'bird_watching' => $_bird_watching,
                'birds_affinity' => $_birds_affinity,
                'blue_collar' => $_blue_collar,
                'boating_sailing' => $_boating_sailing,
                'boating_sailing_affinity' => $_boating_sailing_affinity,
                'business_affinity' => $_business_affinity,
                'camping_hiking' => $_camping_hiking,
                'camping_hiking_climbing_affinity' => $_camping_hiking_climbing_affinity,
                'cars_interest' => $_cars_interest,
                'cat_owner' => $_cat_owner,
                'catalog_affinity' => $_catalog_affinity,
                'cigars' => $_cigars,
                'classical_music' => $_classical_music,
                'coins' => $_coins,
                'collectibles_affinity' => $_collectibles_affinity,
                'college_affinity' => $_college_affinity,
                'computers_affinity' => $_computers_affinity,
                'continuity_program_affinity' => $_continuity_program_affinity,
                'cooking_affinity' => $_cooking_affinity,
                'cosmetics' => $_cosmetics,
                'country_music' => $_country_music,
                'crafts_affinity' => $_crafts_affinity,
                'credit_card' => $_credit_card,
                'credit_repair_affinity' => $_credit_repair_affinity,
                'crochet_affinity' => $_crochet_affinity,
            ]);
            //INSERT PERSON ADVANCE 1

            //INSERT PERSON ADVANCE 2
            // info("INSERT PERSON ADVANCE 2");
            $newpersonadvance2 = PersonAdvance2::create([
                'person_id' => $newPersonID,
                'diet_affinity' => $_diet_affinity,
                'dieting' => $_dieting,
                'do_it_yourself_affinity' => $_do_it_yourself_affinity,
                'dog_owner' => $_dog_owner,
                'doll_collector' => $_doll_collector,
                'education' => $_education,
                'education_ordinal' => $_education_ordinal,
                'education_seekers_affinity' => $_education_seekers_affinity,
                'ego_affinity' => $_ego_affinity,
                'entertainment_interest' => $_entertainment_interest,
                'figurines_collector' => $_figurines_collector,
                'fine_arts_collector' => $_fine_arts_collector,
                'fishing' => $_fishing,
                'fishing_affinity' => $_fishing_affinity,
                'fitness_affinity' => $_fitness_affinity,
                'football' => $_football,
                'football_affinity' => $_football_affinity,
                'gambling' => $_gambling,
                'gambling_affinity' => $_gambling_affinity,
                'games_affinity' => $_games_affinity,
                'gardening_affinity' => $_gardening_affinity,
                'generation_ordinal' => $_generation_ordinal,
                'golf' => $_golf,
                'golf_affinity' => $_golf_affinity,
                'gourmet_affinity' => $_gourmet_affinity,
                'grandparents_affinity' => $_grandparents_affinity,
                'health_affinity' => $_health_affinity,
                'healthy_living' => $_healthy_living,
                'healthy_living_interest' => $_healthy_living_interest,
                'high_tech_affinity' => $_high_tech_affinity,
                'hispanic_affinity' => $_hispanic_affinity,
                'hockey' => $_hockey,
                'hockey_affinity' => $_hockey_affinity,
                'home_decor' => $_home_decor,
                'home_improvement_interest' => $_home_improvement_interest,
                'home_office_affinity' => $_home_office_affinity,
                'home_study' => $_home_study,
                'hunting' => $_hunting,
                'hunting_affinity' => $_hunting_affinity,
                'jazz' => $_jazz,
                'kids_apparel_affinity' => $_kids_apparel_affinity,
                'knit_affinity' => $_knit_affinity,
                'knitting_quilting_sewing' => $_knitting_quilting_sewing,
                'luxury_life' => $_luxury_life,
                'married' => $_married,
                'median_income' => $_median_income,
                'mens_apparel_affinity' => $_mens_apparel_affinity,
                'mens_fashion_affinity' => $_mens_fashion_affinity,
                'mortgage_age' => $_mortgage_age,
                'mortgage_amount' => $_mortgage_amount,
                'mortgage_loan_type' => $_mortgage_loan_type,
                'mortgage_refi_age' => $_mortgage_refi_age,
                'mortgage_refi_amount' => $_mortgage_refi_amount,
                'mortgage_refi_type' => $_mortgage_refi_type,
                'motor_racing' => $_motor_racing,
                'motorcycles' => $_motorcycles,
                'motorcycles_affinity' => $_motorcycles_affinity,
                'movies' => $_movies,
                'nascar' => $_nascar,
                'needlepoint_affinity' => $_needlepoint_affinity,
                'new_credit_offered_household' => $_new_credit_offered_household,
                'num_credit_lines_household' => $_num_credit_lines_household,
                'num_generations_household' => $_num_generations_household,
                'outdoors' => $_outdoors,
                'outdoors_affinity' => $_outdoors_affinity,
                'owns_investments' => $_owns_investments,
                'owns_mutual_funds' => $_owns_mutual_funds,
                'owns_stocks_and_bonds' => $_owns_stocks_and_bonds,
                'owns_swimming_pool' => $_owns_swimming_pool,
                'personal_finance_affinity' => $_personal_finance_affinity,
                'personality' => $_personality,
                'plates_collector' => $_plates_collector,
                'pop_density' => $_pop_density,
                'premium_amex_card' => $_premium_amex_card,
                'premium_card' => $_premium_card,
                'premium_income_household' => $_premium_income_household,
                'premium_income_midpt_household' => $_premium_income_midpt_household,
                'quilt_affinity' => $_quilt_affinity,
                'religious_music' => $_religious_music,
                'rhythm_and_blues' => $_rhythm_and_blues,
                'rock_music' => $_rock_music,
                'running' => $_running,
                'rv' => $_rv,
                'scuba' => $_scuba,
                'self_improvement' => $_self_improvement,
                'sewing_affinity' => $_sewing_affinity,
                'single_family_dwelling' => $_single_family_dwelling,
                'snow_skiing' => $_snow_skiing,
                'snow_skiing_affinity' => $_snow_skiing_affinity,
                'soccer' => $_soccer,
                'soccer_affinity' => $_soccer_affinity,
                'soft_rock' => $_soft_rock,
                'soho_business' => $_soho_business,
                'sports_memoribilia_collector' => $_sports_memoribilia_collector,
                'stamps' => $_stamps,
                'sweepstakes_affinity' => $_sweepstakes_affinity,
                'tennis' => $_tennis,
                'tennis_affinity' => $_tennis_affinity,
                'tobacco_affinity' => $_tobacco_affinity,
                'travel_affinity' => $_travel_affinity,
                'travel_cruise_affinity' => $_travel_cruise_affinity,
                'travel_cruises' => $_travel_cruises,
                'travel_personal' => $_travel_personal,
                'travel_rv_affinity' => $_travel_rv_affinity,
                'travel_us_affinity' => $_travel_us_affinity,
                'truck_owner' => $_truck_owner,
                'trucks_affinity' => $_trucks_affinity,
                'tv_movies_affinity' => $_tv_movies_affinity,
                'veteran_household' => $_veteran_household,
                'walking' => $_walking,
                'weight_lifting' => $_weight_lifting,
                'wildlife_affinity' => $_wildlife_affinity,
                'womens_apparel_affinity' => $_womens_apparel_affinity,
                'womens_fashion_affinity' => $_womens_fashion_affinity,
                'woodworking' => $_woodworking,
                'male_aux' => $_male_aux,
                'political_contributor_aux' => $_political_contributor_aux,
                'political_party_aux' => $_political_party_aux,
                'financial_power' => $_financial_power,
                'mortgage_open_1st_intent' => $_mortgage_open_1st_intent,
                'mortgage_open_2nd_intent' => $_mortgage_open_2nd_intent,
                'mortgage_new_intent' => $_mortgage_new_intent,
                'mortgage_refinance_intent' => $_mortgage_refinance_intent,
                'automotive_loan_intent' => $_automotive_loan_intent,
                'bank_card_intent' => $_bank_card_intent,
                'personal_loan_intent' => $_personal_loan_intent,
                'retail_card_intent' => $_retail_card_intent,
                'student_loan_cons_intent' => $_student_loan_cons_intent,
                'student_loan_intent' => $_student_loan_intent,
                'qtr3_baths' => $_3qtr_baths,
                'ac_type' => $_ac_type,
                'acres' => $_acres,
            ]);
            //INSERT PERSON ADVANCE 2

            //INSERT PERSON ADVANCE 3
            // info("INSERT PERSON ADVANCE 3");
            $newpersonadvance3 = PersonAdvance3::create([
                'person_id' => $newPersonID,
                'additions_square_feet' => $_additions_square_feet,
                'assess_val_impr' => $_assess_val_impr,
                'assess_val_lnd' => $_assess_val_lnd,
                'assess_val_prop' => $_assess_val_prop,
                'bldg_style' => $_bldg_style,
                'bsmt_sqft' => $_bsmt_sqft,
                'bsmt_type' => $_bsmt_type,
                'build_sqft_assess' => $_build_sqft_assess,
                'business' => $_business,
                'combined_statistical_area' => $_combined_statistical_area,
                'metropolitan_division' => $_metropolitan_division,
                'middle' => $_middle,
                'middle_2' => $_middle_2,
                'mkt_ip_perc' => $_mkt_ip_perc,
                'mobile_home' => $_mobile_home,

                'mrtg_due' => $_mrtg_due,
                'mrtg_intrate' => $_mrtg_intrate,
                'mrtg_refi' => $_mrtg_refi,
                'mrtg_term' => $_mrtg_term,
                'mrtg_type' => $_mrtg_type,

                'mrtg2_amt' => $_mrtg2_amt,
                'mrtg2_date' => $_mrtg2_date,
                'mrtg2_deed_type' => $_mrtg2_deed_type,
                'mrtg2_due' => $_mrtg2_due,
                'mrtg2_equity' => $_mrtg2_equity,
                'mrtg2_intrate' => $_mrtg2_intrate,
                'mrtg2_inttype' => $_mrtg2_inttype,
                'mrtg2_refi' => $_mrtg2_refi,
                'mrtg2_term' => $_mrtg2_term,
                'mrtg2_type' => $_mrtg2_type,

                'mrtg3_amt' => $_mrtg3_amt,
                'mrtg3_date' => $_mrtg3_date,
                'mrtg3_deed_type' => $_mrtg3_deed_type,
                'mrtg3_due' => $_mrtg3_due,
                'mrtg3_equity' => $_mrtg3_equity,
                'mrtg3_inttype' => $_mrtg3_inttype,
                'mrtg3_refi' => $_mrtg3_refi,
                'mrtg3_term' => $_mrtg3_term,
                'mrtg3_type' => $_mrtg3_type,

                'msa_code' => $_msa_code,
                'number_bedrooms' => $_number_bedrooms,
                'number_bldgs' => $_number_bldgs,
                'number_fireplace' => $_number_fireplace,
                'number_park_spaces' => $_number_park_spaces,
                'number_rooms' => $_number_rooms,
                'own_biz' => $_own_biz,
                'owner_occupied' => $_owner_occupied,
                'ownership_relation' => $_ownership_relation,
                'owner_type_description' => $_owner_type_description,
                'estimated_value' => $_estimated_value,
                'ext_type' => $_ext_type,
                'finish_square_feet2' => $_finish_square_feet2,
                'fireplace' => $_fireplace,
                'first' => $_first,
                'first_2' => $_first_2,
                'found_type' => $_found_type,
                'fr_feet' => $_fr_feet,
                'fuel_type' => $_fuel_type,
                'full_baths' => $_full_baths,
                'half_baths' => $_half_baths,
                'garage_type' => $_garage_type,
                'grnd_sqft' => $_grnd_sqft,
                'heat_type' => $_heat_type,
                'hmstd' => $_hmstd,
                'impr_appr_val' => $_impr_appr_val,
                'imprval' => $_imprval,
                'imprval_type' => $_imprval_type,
                'land_appr_val' => $_land_appr_val,
                'landval' => $_landval,
                'landval_type' => $_landval_type,
                'last' => $_last,
                'last_2' => $_last_2,
                'lat' => $_lat,
                'lender_name' => $_lender_name,
                'lender2_name' => $_lender2_name,
                'lender3_name' => $_lender3_name,
                'loan_amt' => $_loan_amt,
                'loan_date' => $_loan_date,
                'loan_to_val' => $_loan_to_val,
                'lon' => $_lon,
                'markval' => $_markval,
                'markval_type' => $_markval_type,
                'patio_porch' => $_patio_porch,
                'patio_square_feet' => $_patio_square_feet,
                'pool' => $_pool,
                'porch_square_feet' => $_porch_square_feet,
                'previous_assessed_value' => $_previous_assessed_value,
                'prop_type' => $_prop_type,
                'rate_type' => $_rate_type,
                'rec_date' => $_rec_date,
                'roof_covtype' => $_roof_covtype,
                'roof_shapetype' => $_roof_shapetype,
                'sale_amt' => $_sale_amt,
                'sale_amt_pr' => $_sale_amt_pr,
                'sale_date' => $_sale_date,
                'sale_type_pr' => $_sale_type_pr,
                'sales_type' => $_sales_type,
                'sell_name' => $_sell_name,
                'sewer_type' => $_sewer_type,
                'site_quality' => $_site_quality,
                'std_address' => $_std_address,
                'std_city' => $_std_city,
                'std_state' => $_std_state,
                'std_zip' => $_std_zip,
                'std_zip4' => $_std_zip4,
                'stories_number' => $_stories_number,
                'suffix' => $_suffix,
                'suffix_2' => $_suffix_2,
                'tax_yr' => $_tax_yr,
                'tax_improvement_percent' => $_tax_improvement_percent,
                'title_co' => $_title_co,
                'tot_baths_est' => $_tot_baths_est,
                'ttl_appr_val' => $_ttl_appr_val,
                'ttl_bld_sqft' => $_ttl_bld_sqft,
                'ttl_tax' => $_ttl_tax,
                'unit_number' => $_unit_number,
                'vet_exempt' => $_vet_exempt,
                'water_type' => $_water_type,
            ]);
            //INSERT PERSON ADVANCE 3

            //INSERT PERSON ADVANCE 
                // /** HR TEMPORARY NOT SAVE 27 Jan 2025 */
                // $newpersonadvance = PersonAdvance::create([
                //     'person_id' => $newPersonID,
                //     'gender_aux' => $_gender_aux,
                //     'gender' => $_gender,
                //     'age_aux' => $_age_aux,
                //     'birth_year_aux' => $_birth_year_aux,
                //     'generation' => $_generation,
                //     'income_household' => $_household_income,
                //     'income_midpts_household' => $_median_household_income,
                //     'net_worth_household' => $_household_net_worth,
                //     'net_worth_midpt_household' => $_median_household_net_worth,
                //     'discretionary_income' => $_discretionary_income,
                //     'credit_midpts' => $_credit_score_median,
                //     'credit_range' => $_credit_score_range,
                //     'occupation_category' => $_occupation_category,
                //     'occupation_detail' => $_occupation_detail,
                //     'occupation_type' => $_occupation_type,
                //     'voter' => $_voter,
                //     'urbanicity' => $_urbanicity,
                //     'mobile_phone_1' => $_mobile_phone,
                //     'mobile_phone_2' => $_mobile_phone2,
                //     'mobile_phone_3' => $_mobile_phone3,
                //     'mobile_phone_1_dnc' => $_mobile_phone_dnc,
                //     'mobile_phone_2_dnc' => $_mobile_phone_dnc2,
                //     'mobile_phone_3_dnc' => $_mobile_phone_dnc3,
                //     'tax_bill_mailing_address' => $_tax_bill_mailing_address,
                //     'tax_bill_mailing_city' => $_tax_bill_mailing_city,
                //     'tax_bill_mailing_county' => $_tax_bill_mailing_county,
                //     'tax_bill_mailing_fips' => $_tax_bill_mailing_fips,
                //     'tax_bill_mailing_state' => $_tax_bill_mailing_state,
                //     'tax_bill_mailing_zip' => $_tax_bill_mailing_zip,
                //     'tax_bill_mailing_zip4' => $_tax_bill_mailing_zip4,
                //     'num_adults_household' => $_num_adults_household,
                //     'num_children_household' => $_num_children_household,
                //     'num_persons_household' => $_num_persons_household,
                //     'child_aged_0_3_household' => $_child_aged_0_3_household,
                //     'child_aged_4_6_household' => $_child_aged_4_6_household,
                //     'child_aged_7_9_household' => $_child_aged_7_9_household,
                //     'child_aged_10_12_household' => $_child_aged_10_12_household,
                //     'child_aged_13_18_household' => $_child_aged_13_18_household,
                //     'children_household' => $_children_household,
                //     'has_email' => $_has_email,
                //     'has_phone' => $_has_phone,
                //     'magazine_subscriber' => $_magazine_subscriber,
                //     'charity_interest' => $_charity_interest,
                //     'likely_charitable_donor' => $_likely_charitable_donor,
                //     'donor_affinity' => $_donor_affinity,
                //     'dwelling_type' => $_dwelling_type,
                //     'home_owner' => $_home_owner,
                //     'home_owner_ordinal' => $_home_owner_ordinal,
                //     'length_of_residence' => $_length_of_residence,
                //     'home_price' => $_home_price,
                //     'home_value' => $_home_value,
                //     'median_home_value' => $_median_home_value,
                //     'living_sqft' => $_living_sqft,
                //     'yr_built_orig' => $_year_built_original,
                //     'yr_built_range' => $_year_built_range,
                //     'lot_number' => $_lot_number,
                //     'legal_description' => $_legal_description,
                //     'land_sqft' => $_land_sqft,
                //     'garage_sqft' => $_garage_sqft,
                //     'subdivision' => $_subdivision,
                //     'zoning_code' => $_zoning_code,
                //     'cooking' => $_cooking,
                //     'gardening' => $_gardening,
                //     'music' => $_music,
                //     'diy' => $_diy,
                //     'books' => $_books,
                //     'travel_vacation' => $_travel_vacation,
                //     'health_beauty_products' => $_health_beauty_products,
                //     'pet_owner' => $_pet_owner,
                //     'photography' => $_photography,
                //     'fitness' => $_fitness,
                //     'epicurean' => $_epicurean,
                //     'cbsa' => $_cbsa,
                //     'census_block' => $_census_block,
                //     'census_block_group' => $_census_block_group,
                //     'census_tract' => $_census_tract,
                    
                //     'aerobics' => $_aerobics,
                //     'african_american_affinity' => $_african_american_affinity ,
                //     'amex_card' => $_amex_card,
                //     'antiques' => $_antiques,
                //     'apparel_accessory_affinity' => $_apparel_accessory_affinity,
                //     'apparel_affinity' => $_apparel_affinity,
                //     'arts_and_crafts' => $_arts_and_crafts,
                //     'asian_affinity' => $_asian_affinity,
                //     'auto_affinity' => $_auto_affinity,
                //     'auto_racing_affinity' => $_auto_racing_affinity,
                //     'aviation_affinity' => $_aviation_affinity,
                //     'bank_card' => $_bank_card,
                //     'bargain_hunter_affinity' => $_bargain_hunter_affinity,
                //     'baseball' => $_baseball,
                //     'baseball_affinity' => $_baseball_affinity,
                //     'basketball' => $_basketball,
                //     'basketball_affinity' => $_basketball_affinity,
                //     'beauty_affinity' => $_beauty_affinity,
                //     'bible_affinity' => $_bible_affinity,
                //     'bird_watching' => $_bird_watching,
                //     'birds_affinity' => $_birds_affinity,
                //     'blue_collar' => $_blue_collar,
                //     'boating_sailing' => $_boating_sailing,
                //     'boating_sailing_affinity' => $_boating_sailing_affinity,
                //     'business_affinity' => $_business_affinity,
                //     'camping_hiking' => $_camping_hiking,
                //     'camping_hiking_climbing_affinity' => $_camping_hiking_climbing_affinity,
                //     'cars_interest' => $_cars_interest,
                //     'cat_owner' => $_cat_owner,
                //     'catalog_affinity' => $_catalog_affinity,
                //     'cigars' => $_cigars,
                //     'classical_music' => $_classical_music,
                //     'coins' => $_coins,
                //     'collectibles_affinity' => $_collectibles_affinity,
                //     'college_affinity' => $_college_affinity,
                //     'computers_affinity' => $_computers_affinity,
                //     'continuity_program_affinity' => $_continuity_program_affinity,
                //     'cooking_affinity' => $_cooking_affinity,
                //     'cosmetics' => $_cosmetics,
                //     'country_music' => $_country_music,
                //     'crafts_affinity' => $_crafts_affinity,
                //     'credit_card' => $_credit_card,
                //     'credit_repair_affinity' => $_credit_repair_affinity,
                //     'crochet_affinity' => $_crochet_affinity,
                //     'diet_affinity' => $_diet_affinity,
                //     'dieting' => $_dieting,
                //     'do_it_yourself_affinity' => $_do_it_yourself_affinity,
                //     'dog_owner' => $_dog_owner,
                //     'doll_collector' => $_doll_collector,
                //     'education' => $_education,
                //     'education_ordinal' => $_education_ordinal,
                //     'education_seekers_affinity' => $_education_seekers_affinity,
                //     'ego_affinity' => $_ego_affinity,
                //     'entertainment_interest' => $_entertainment_interest,
                //     'figurines_collector' => $_figurines_collector,
                //     'fine_arts_collector' => $_fine_arts_collector,
                //     'fishing' => $_fishing,
                //     'fishing_affinity' => $_fishing_affinity,
                //     'fitness_affinity' => $_fitness_affinity,
                //     'football' => $_football,
                //     'football_affinity' => $_football_affinity,
                //     'gambling' => $_gambling,
                //     'gambling_affinity' => $_gambling_affinity,
                //     'games_affinity' => $_games_affinity,
                //     'gardening_affinity' => $_gardening_affinity,
                //     'generation_ordinal' => $_generation_ordinal,
                //     'golf' => $_golf,
                //     'golf_affinity' => $_golf_affinity,
                //     'gourmet_affinity' => $_gourmet_affinity,
                //     'grandparents_affinity' => $_grandparents_affinity,
                //     'health_affinity' => $_health_affinity,
                //     'healthy_living' => $_healthy_living,
                //     'healthy_living_interest' => $_healthy_living_interest,
                //     'high_tech_affinity' => $_high_tech_affinity,
                //     'hispanic_affinity' => $_hispanic_affinity,
                //     'hockey' => $_hockey,
                //     'hockey_affinity' => $_hockey_affinity,
                //     'home_decor' => $_home_decor,
                //     'home_improvement_interest' => $_home_improvement_interest,
                //     'home_office_affinity' => $_home_office_affinity,
                //     'home_study' => $_home_study,
                //     'hunting' => $_hunting,
                //     'hunting_affinity' => $_hunting_affinity,
                //     'jazz' => $_jazz,
                //     'kids_apparel_affinity' => $_kids_apparel_affinity,
                //     'knit_affinity' => $_knit_affinity,
                //     'knitting_quilting_sewing' => $_knitting_quilting_sewing,
                //     'luxury_life' => $_luxury_life,
                //     'married' => $_married,
                //     'marital_status' => $_marital_status,
                //     'median_income' => $_median_income,
                //     'mens_apparel_affinity' => $_mens_apparel_affinity,
                //     'mens_fashion_affinity' => $_mens_fashion_affinity,
                //     'mortgage_age' => $_mortgage_age,
                //     'mortgage_amount' => $_mortgage_amount,
                //     'mortgage_loan_type' => $_mortgage_loan_type,
                //     'mortgage_refi_age' => $_mortgage_refi_age,
                //     'mortgage_refi_amount' => $_mortgage_refi_amount,
                //     'mortgage_refi_type' => $_mortgage_refi_type,
                //     'motor_racing' => $_motor_racing,
                //     'motorcycles' => $_motorcycles,
                //     'motorcycles_affinity' => $_motorcycles_affinity,
                //     'movies' => $_movies,
                //     'nascar' => $_nascar,
                //     'needlepoint_affinity' => $_needlepoint_affinity,
                //     'new_credit_offered_household' => $_new_credit_offered_household,
                //     'num_credit_lines_household' => $_num_credit_lines_household,
                //     'num_generations_household' => $_num_generations_household,
                //     'outdoors' => $_outdoors,
                //     'outdoors_affinity' => $_outdoors_affinity,
                //     'owns_investments' => $_owns_investments,
                //     'owns_mutual_funds' => $_owns_mutual_funds,
                //     'owns_stocks_and_bonds' => $_owns_stocks_and_bonds,
                //     'owns_swimming_pool' => $_owns_swimming_pool,
                //     'personal_finance_affinity' => $_personal_finance_affinity,
                //     'personality' => $_personality,
                //     'plates_collector' => $_plates_collector,
                //     'pop_density' => $_pop_density,
                //     'premium_amex_card' => $_premium_amex_card,
                //     'premium_card' => $_premium_card,
                //     'premium_income_household' => $_premium_income_household,
                //     'premium_income_midpt_household' => $_premium_income_midpt_household,
                //     'quilt_affinity' => $_quilt_affinity,
                //     'religious_music' => $_religious_music,
                //     'rhythm_and_blues' => $_rhythm_and_blues,
                //     'rock_music' => $_rock_music,
                //     'running' => $_running,
                //     'rv' => $_rv,
                //     'scuba' => $_scuba,
                //     'self_improvement' => $_self_improvement,
                //     'sewing_affinity' => $_sewing_affinity,
                //     'single_family_dwelling' => $_single_family_dwelling,
                //     'snow_skiing' => $_snow_skiing,
                //     'snow_skiing_affinity' => $_snow_skiing_affinity,
                //     'soccer' => $_soccer,
                //     'soccer_affinity' => $_soccer_affinity,
                //     'soft_rock' => $_soft_rock,
                //     'soho_business' => $_soho_business,
                //     'sports_memoribilia_collector' => $_sports_memoribilia_collector,
                //     'stamps' => $_stamps,
                //     'sweepstakes_affinity' => $_sweepstakes_affinity,
                //     'tennis' => $_tennis,
                //     'tennis_affinity' => $_tennis_affinity,
                //     'tobacco_affinity' => $_tobacco_affinity,
                //     'travel_affinity' => $_travel_affinity,
                //     'travel_cruise_affinity' => $_travel_cruise_affinity,
                //     'travel_cruises' => $_travel_cruises,
                //     'travel_personal' => $_travel_personal,
                //     'travel_rv_affinity' => $_travel_rv_affinity,
                //     'travel_us_affinity' => $_travel_us_affinity,
                //     'truck_owner' => $_truck_owner,
                //     'trucks_affinity' => $_trucks_affinity,
                //     'tv_movies_affinity' => $_tv_movies_affinity,
                //     'veteran_household' => $_veteran_household,
                //     'walking' => $_walking,
                //     'weight_lifting' => $_weight_lifting,
                //     'wildlife_affinity' => $_wildlife_affinity,
                //     'womens_apparel_affinity' => $_womens_apparel_affinity,
                //     'womens_fashion_affinity' => $_womens_fashion_affinity,
                //     'woodworking' => $_woodworking,
                //     'male_aux' => $_male_aux,
                //     'political_contributor_aux' => $_political_contributor_aux,
                //     'political_party_aux' => $_political_party_aux,
                //     'financial_power' => $_financial_power,
                //     'mortgage_open_1st_intent' => $_mortgage_open_1st_intent,
                //     'mortgage_open_2nd_intent' => $_mortgage_open_2nd_intent,
                //     'mortgage_new_intent' => $_mortgage_new_intent,
                //     'mortgage_refinance_intent' => $_mortgage_refinance_intent,
                //     'automotive_loan_intent' => $_automotive_loan_intent,
                //     'bank_card_intent' => $_bank_card_intent,
                //     'personal_loan_intent' => $_personal_loan_intent,
                //     'retail_card_intent' => $_retail_card_intent,
                //     'student_loan_cons_intent' => $_student_loan_cons_intent,
                //     'student_loan_intent' => $_student_loan_intent,
                //     'qtr3_baths' => $_3qtr_baths,
                //     'ac_type' => $_ac_type,
                //     'acres' => $_acres,
                //     'additions_square_feet' => $_additions_square_feet,
                //     'assess_val_impr' => $_assess_val_impr,
                //     'assess_val_lnd' => $_assess_val_lnd,
                //     'assess_val_prop' => $_assess_val_prop,
                //     'bldg_style' => $_bldg_style,
                //     'bsmt_sqft' => $_bsmt_sqft,
                //     'bsmt_type' => $_bsmt_type,
                //     'build_sqft_assess' => $_build_sqft_assess,
                //     'business' => $_business,
                //     'combined_statistical_area' => $_combined_statistical_area,
                //     'metropolitan_division' => $_metropolitan_division,
                //     'middle' => $_middle,
                //     'middle_2' => $_middle_2,
                //     'mkt_ip_perc' => $_mkt_ip_perc,
                //     'mobile_home' => $_mobile_home,

                //     'mrtg_due' => $_mrtg_due,
                //     'mrtg_intrate' => $_mrtg_intrate,
                //     'mrtg_refi' => $_mrtg_refi,
                //     'mrtg_term' => $_mrtg_term,
                //     'mrtg_type' => $_mrtg_type,

                //     'mrtg2_amt' => $_mrtg2_amt,
                //     'mrtg2_date' => $_mrtg2_date,
                //     'mrtg2_deed_type' => $_mrtg2_deed_type,
                //     'mrtg2_due' => $_mrtg2_due,
                //     'mrtg2_equity' => $_mrtg2_equity,
                //     'mrtg2_intrate' => $_mrtg2_intrate,
                //     'mrtg2_inttype' => $_mrtg2_inttype,
                //     'mrtg2_refi' => $_mrtg2_refi,
                //     'mrtg2_term' => $_mrtg2_term,
                //     'mrtg2_type' => $_mrtg2_type,

                //     'mrtg3_amt' => $_mrtg3_amt,
                //     'mrtg3_date' => $_mrtg3_date,
                //     'mrtg3_deed_type' => $_mrtg3_deed_type,
                //     'mrtg3_due' => $_mrtg3_due,
                //     'mrtg3_equity' => $_mrtg3_equity,
                //     'mrtg3_inttype' => $_mrtg3_inttype,
                //     'mrtg3_refi' => $_mrtg3_refi,
                //     'mrtg3_term' => $_mrtg3_term,
                //     'mrtg3_type' => $_mrtg3_type,

                //     'msa_code' => $_msa_code,
                //     'number_bedrooms' => $_number_bedrooms,
                //     'number_bldgs' => $_number_bldgs,
                //     'number_fireplace' => $_number_fireplace,
                //     'number_park_spaces' => $_number_park_spaces,
                //     'number_rooms' => $_number_rooms,
                //     'own_biz' => $_own_biz,
                //     'owner_occupied' => $_owner_occupied,
                //     'ownership_relation' => $_ownership_relation,
                //     'owner_type_description' => $_owner_type_description,
                //     'estimated_value' => $_estimated_value,
                //     'ext_type' => $_ext_type,
                //     'finish_square_feet2' => $_finish_square_feet2,
                //     'fireplace' => $_fireplace,
                //     'first' => $_first,
                //     'first_2' => $_first_2,
                //     'found_type' => $_found_type,
                //     'fr_feet' => $_fr_feet,
                //     'fuel_type' => $_fuel_type,
                //     'full_baths' => $_full_baths,
                //     'half_baths' => $_half_baths,
                //     'garage_type' => $_garage_type,
                //     'grnd_sqft' => $_grnd_sqft,
                //     'heat_type' => $_heat_type,
                //     'hmstd' => $_hmstd,
                //     'impr_appr_val' => $_impr_appr_val,
                //     'imprval' => $_imprval,
                //     'imprval_type' => $_imprval_type,
                //     'land_appr_val' => $_land_appr_val,
                //     'landval' => $_landval,
                //     'landval_type' => $_landval_type,
                //     'last' => $_last,
                //     'last_2' => $_last_2,
                //     'lat' => $_lat,
                //     'lender_name' => $_lender_name,
                //     'lender2_name' => $_lender2_name,
                //     'lender3_name' => $_lender3_name,
                //     'loan_amt' => $_loan_amt,
                //     'loan_date' => $_loan_date,
                //     'loan_to_val' => $_loan_to_val,
                //     'lon' => $_lon,
                //     'markval' => $_markval,
                //     'markval_type' => $_markval_type,
                //     'patio_porch' => $_patio_porch,
                //     'patio_square_feet' => $_patio_square_feet,
                //     'pool' => $_pool,
                //     'porch_square_feet' => $_porch_square_feet,
                //     'previous_assessed_value' => $_previous_assessed_value,
                //     'prop_type' => $_prop_type,
                //     'rate_type' => $_rate_type,
                //     'rec_date' => $_rec_date,
                //     'roof_covtype' => $_roof_covtype,
                //     'roof_shapetype' => $_roof_shapetype,
                //     'sale_amt' => $_sale_amt,
                //     'sale_amt_pr' => $_sale_amt_pr,
                //     'sale_date' => $_sale_date,
                //     'sale_type_pr' => $_sale_type_pr,
                //     'sales_type' => $_sales_type,
                //     'sell_name' => $_sell_name,
                //     'sewer_type' => $_sewer_type,
                //     'site_quality' => $_site_quality,
                //     'std_address' => $_std_address,
                //     'std_city' => $_std_city,
                //     'std_state' => $_std_state,
                //     'std_zip' => $_std_zip,
                //     'std_zip4' => $_std_zip4,
                //     'stories_number' => $_stories_number,
                //     'suffix' => $_suffix,
                //     'suffix_2' => $_suffix_2,
                //     'tax_yr' => $_tax_yr,
                //     'tax_improvement_percent' => $_tax_improvement_percent,
                //     'title_co' => $_title_co,
                //     'tot_baths_est' => $_tot_baths_est,
                //     'ttl_appr_val' => $_ttl_appr_val,
                //     'ttl_bld_sqft' => $_ttl_bld_sqft,
                //     'ttl_tax' => $_ttl_tax,
                //     'unit_number' => $_unit_number,
                //     'vet_exempt' => $_vet_exempt,
                //     'water_type' => $_water_type,
                // ]);
                /** HR TEMPORARY NOT SAVE 27 Jan 2025 */
            //INSERT PERSON ADVANCE

            //INSERT CLEAN ID RESULT ADVANCE 1 
            $newCleanIdAdvance1 = CleanIdAdvance::create([
                'file_id' => $file_id,
                'md5_id' => $md5_id,
                'gender_aux' => $_gender_aux,
                'gender' => $_gender,
                'age_aux' => $_age_aux,
                'birth_year_aux' => $_birth_year_aux,
                'generation' => $_generation,
                'marital_status' => $_marital_status,
                'income_household' => $_household_income,
                'income_midpts_household' => $_median_household_income,
                'net_worth_household' => $_household_net_worth,
                'net_worth_midpt_household' => $_median_household_net_worth,
                'discretionary_income' => $_discretionary_income,
                'credit_midpts' => $_credit_score_median,
                'credit_range' => $_credit_score_range,
                'occupation_category' => $_occupation_category,
                'occupation_detail' => $_occupation_detail,
                'occupation_type' => $_occupation_type,
                'voter' => $_voter,
                'urbanicity' => $_urbanicity,
                'mobile_phone_1' => $_mobile_phone,
                'mobile_phone_2' => $_mobile_phone2,
                'mobile_phone_3' => $_mobile_phone3,
                'mobile_phone_1_dnc' => $_mobile_phone_dnc,
                'mobile_phone_2_dnc' => $_mobile_phone_dnc2,
                'mobile_phone_3_dnc' => $_mobile_phone_dnc3,
                'tax_bill_mailing_address' => $_tax_bill_mailing_address,
                'tax_bill_mailing_city' => $_tax_bill_mailing_city,
                'tax_bill_mailing_county' => $_tax_bill_mailing_county,
                'tax_bill_mailing_fips' => $_tax_bill_mailing_fips,
                'tax_bill_mailing_state' => $_tax_bill_mailing_state,
                'tax_bill_mailing_zip' => $_tax_bill_mailing_zip,
                'tax_bill_mailing_zip4' => $_tax_bill_mailing_zip4,
                'num_adults_household' => $_num_adults_household,
                'num_children_household' => $_num_children_household,
                'num_persons_household' => $_num_persons_household,
                'child_aged_0_3_household' => $_child_aged_0_3_household,
                'child_aged_4_6_household' => $_child_aged_4_6_household,
                'child_aged_7_9_household' => $_child_aged_7_9_household,
                'child_aged_10_12_household' => $_child_aged_10_12_household,
                'child_aged_13_18_household' => $_child_aged_13_18_household,
                'children_household' => $_children_household,
                'has_email' => $_has_email,
                'has_phone' => $_has_phone,
                'magazine_subscriber' => $_magazine_subscriber,
                'charity_interest' => $_charity_interest,
                'likely_charitable_donor' => $_likely_charitable_donor,
                'donor_affinity' => $_donor_affinity,
                'dwelling_type' => $_dwelling_type,
                'home_owner' => $_home_owner,
                'home_owner_ordinal' => $_home_owner_ordinal,
                'length_of_residence' => $_length_of_residence,
                'home_price' => $_home_price,
                'home_value' => $_home_value,
                'median_home_value' => $_median_home_value,
                'living_sqft' => $_living_sqft,
                'yr_built_orig' => $_year_built_original,
                'yr_built_range' => $_year_built_range,
                'lot_number' => $_lot_number,
                'legal_description' => $_legal_description,
                'land_sqft' => $_land_sqft,
                'garage_sqft' => $_garage_sqft,
                'subdivision' => $_subdivision,
                'zoning_code' => $_zoning_code,
                'cooking' => $_cooking,
                'gardening' => $_gardening,
                'music' => $_music,
                'diy' => $_diy,
                'books' => $_books,
                'travel_vacation' => $_travel_vacation,
                'health_beauty_products' => $_health_beauty_products,
                'pet_owner' => $_pet_owner,
                'photography' => $_photography,
                'fitness' => $_fitness,
                'epicurean' => $_epicurean,
                'cbsa' => $_cbsa,
                'census_block' => $_census_block,
                'census_block_group' => $_census_block_group,
                'census_tract' => $_census_tract,
                
                'aerobics' => $_aerobics,
                'african_american_affinity' => $_african_american_affinity ,
                'amex_card' => $_amex_card,
                'antiques' => $_antiques,
                'apparel_accessory_affinity' => $_apparel_accessory_affinity,
                'apparel_affinity' => $_apparel_affinity,
                'arts_and_crafts' => $_arts_and_crafts,
                'asian_affinity' => $_asian_affinity,
                'auto_affinity' => $_auto_affinity,
                'auto_racing_affinity' => $_auto_racing_affinity,
                'aviation_affinity' => $_aviation_affinity,
                'bank_card' => $_bank_card,
                'bargain_hunter_affinity' => $_bargain_hunter_affinity,
                'baseball' => $_baseball,
                'baseball_affinity' => $_baseball_affinity,
                'basketball' => $_basketball,
                'basketball_affinity' => $_basketball_affinity,
                'beauty_affinity' => $_beauty_affinity,
                'bible_affinity' => $_bible_affinity,
                'bird_watching' => $_bird_watching,
                'birds_affinity' => $_birds_affinity,
                'blue_collar' => $_blue_collar,
                'boating_sailing' => $_boating_sailing,
                'boating_sailing_affinity' => $_boating_sailing_affinity,
                'business_affinity' => $_business_affinity,
                'camping_hiking' => $_camping_hiking,
                'camping_hiking_climbing_affinity' => $_camping_hiking_climbing_affinity,
                'cars_interest' => $_cars_interest,
                'cat_owner' => $_cat_owner,
                'catalog_affinity' => $_catalog_affinity,
                'cigars' => $_cigars,
                'classical_music' => $_classical_music,
                'coins' => $_coins,
                'collectibles_affinity' => $_collectibles_affinity,
                'college_affinity' => $_college_affinity,
                'computers_affinity' => $_computers_affinity,
                'continuity_program_affinity' => $_continuity_program_affinity,
                'cooking_affinity' => $_cooking_affinity,
                'cosmetics' => $_cosmetics,
                'country_music' => $_country_music,
                'crafts_affinity' => $_crafts_affinity,
                'credit_card' => $_credit_card,
                'credit_repair_affinity' => $_credit_repair_affinity,
                'crochet_affinity' => $_crochet_affinity,
            ]);
            // info("INSERT CLEAN ID RESULT ADVANCE 1", ['newCleanIdAdvance1' => $newCleanIdAdvance1]);
            //INSERT CLEAN ID RESULT ADVANCE 1

            //INSERT CLEAN ID RESULT ADVANCE 2
            $newCleanIdAdvance2 = CleanIdAdvance2::create([
                'file_id' => $file_id,
                'md5_id' => $md5_id,
                'diet_affinity' => $_diet_affinity,
                'dieting' => $_dieting,
                'do_it_yourself_affinity' => $_do_it_yourself_affinity,
                'dog_owner' => $_dog_owner,
                'doll_collector' => $_doll_collector,
                'education' => $_education,
                'education_ordinal' => $_education_ordinal,
                'education_seekers_affinity' => $_education_seekers_affinity,
                'ego_affinity' => $_ego_affinity,
                'entertainment_interest' => $_entertainment_interest,
                'figurines_collector' => $_figurines_collector,
                'fine_arts_collector' => $_fine_arts_collector,
                'fishing' => $_fishing,
                'fishing_affinity' => $_fishing_affinity,
                'fitness_affinity' => $_fitness_affinity,
                'football' => $_football,
                'football_affinity' => $_football_affinity,
                'gambling' => $_gambling,
                'gambling_affinity' => $_gambling_affinity,
                'games_affinity' => $_games_affinity,
                'gardening_affinity' => $_gardening_affinity,
                'generation_ordinal' => $_generation_ordinal,
                'golf' => $_golf,
                'golf_affinity' => $_golf_affinity,
                'gourmet_affinity' => $_gourmet_affinity,
                'grandparents_affinity' => $_grandparents_affinity,
                'health_affinity' => $_health_affinity,
                'healthy_living' => $_healthy_living,
                'healthy_living_interest' => $_healthy_living_interest,
                'high_tech_affinity' => $_high_tech_affinity,
                'hispanic_affinity' => $_hispanic_affinity,
                'hockey' => $_hockey,
                'hockey_affinity' => $_hockey_affinity,
                'home_decor' => $_home_decor,
                'home_improvement_interest' => $_home_improvement_interest,
                'home_office_affinity' => $_home_office_affinity,
                'home_study' => $_home_study,
                'hunting' => $_hunting,
                'hunting_affinity' => $_hunting_affinity,
                'jazz' => $_jazz,
                'kids_apparel_affinity' => $_kids_apparel_affinity,
                'knit_affinity' => $_knit_affinity,
                'knitting_quilting_sewing' => $_knitting_quilting_sewing,
                'luxury_life' => $_luxury_life,
                'married' => $_married,
                'median_income' => $_median_income,
                'mens_apparel_affinity' => $_mens_apparel_affinity,
                'mens_fashion_affinity' => $_mens_fashion_affinity,
                'mortgage_age' => $_mortgage_age,
                'mortgage_amount' => $_mortgage_amount,
                'mortgage_loan_type' => $_mortgage_loan_type,
                'mortgage_refi_age' => $_mortgage_refi_age,
                'mortgage_refi_amount' => $_mortgage_refi_amount,
                'mortgage_refi_type' => $_mortgage_refi_type,
                'motor_racing' => $_motor_racing,
                'motorcycles' => $_motorcycles,
                'motorcycles_affinity' => $_motorcycles_affinity,
                'movies' => $_movies,
                'nascar' => $_nascar,
                'needlepoint_affinity' => $_needlepoint_affinity,
                'new_credit_offered_household' => $_new_credit_offered_household,
                'num_credit_lines_household' => $_num_credit_lines_household,
                'num_generations_household' => $_num_generations_household,
                'outdoors' => $_outdoors,
                'outdoors_affinity' => $_outdoors_affinity,
                'owns_investments' => $_owns_investments,
                'owns_mutual_funds' => $_owns_mutual_funds,
                'owns_stocks_and_bonds' => $_owns_stocks_and_bonds,
                'owns_swimming_pool' => $_owns_swimming_pool,
                'personal_finance_affinity' => $_personal_finance_affinity,
                'personality' => $_personality,
                'plates_collector' => $_plates_collector,
                'pop_density' => $_pop_density,
                'premium_amex_card' => $_premium_amex_card,
                'premium_card' => $_premium_card,
                'premium_income_household' => $_premium_income_household,
                'premium_income_midpt_household' => $_premium_income_midpt_household,
                'quilt_affinity' => $_quilt_affinity,
                'religious_music' => $_religious_music,
                'rhythm_and_blues' => $_rhythm_and_blues,
                'rock_music' => $_rock_music,
                'running' => $_running,
                'rv' => $_rv,
                'scuba' => $_scuba,
                'self_improvement' => $_self_improvement,
                'sewing_affinity' => $_sewing_affinity,
                'single_family_dwelling' => $_single_family_dwelling,
                'snow_skiing' => $_snow_skiing,
                'snow_skiing_affinity' => $_snow_skiing_affinity,
                'soccer' => $_soccer,
                'soccer_affinity' => $_soccer_affinity,
                'soft_rock' => $_soft_rock,
                'soho_business' => $_soho_business,
                'sports_memoribilia_collector' => $_sports_memoribilia_collector,
                'stamps' => $_stamps,
                'sweepstakes_affinity' => $_sweepstakes_affinity,
                'tennis' => $_tennis,
                'tennis_affinity' => $_tennis_affinity,
                'tobacco_affinity' => $_tobacco_affinity,
                'travel_affinity' => $_travel_affinity,
                'travel_cruise_affinity' => $_travel_cruise_affinity,
                'travel_cruises' => $_travel_cruises,
                'travel_personal' => $_travel_personal,
                'travel_rv_affinity' => $_travel_rv_affinity,
                'travel_us_affinity' => $_travel_us_affinity,
                'truck_owner' => $_truck_owner,
                'trucks_affinity' => $_trucks_affinity,
                'tv_movies_affinity' => $_tv_movies_affinity,
                'veteran_household' => $_veteran_household,
                'walking' => $_walking,
                'weight_lifting' => $_weight_lifting,
                'wildlife_affinity' => $_wildlife_affinity,
                'womens_apparel_affinity' => $_womens_apparel_affinity,
                'womens_fashion_affinity' => $_womens_fashion_affinity,
                'woodworking' => $_woodworking,
                'male_aux' => $_male_aux,
                'political_contributor_aux' => $_political_contributor_aux,
                'political_party_aux' => $_political_party_aux,
                'financial_power' => $_financial_power,
                'mortgage_open_1st_intent' => $_mortgage_open_1st_intent,
                'mortgage_open_2nd_intent' => $_mortgage_open_2nd_intent,
                'mortgage_new_intent' => $_mortgage_new_intent,
                'mortgage_refinance_intent' => $_mortgage_refinance_intent,
                'automotive_loan_intent' => $_automotive_loan_intent,
                'bank_card_intent' => $_bank_card_intent,
                'personal_loan_intent' => $_personal_loan_intent,
                'retail_card_intent' => $_retail_card_intent,
                'student_loan_cons_intent' => $_student_loan_cons_intent,
                'student_loan_intent' => $_student_loan_intent,
                'qtr3_baths' => $_3qtr_baths,
                'ac_type' => $_ac_type,
                'acres' => $_acres,
            ]);
            // info("INSERT CLEAN ID RESULT ADVANCE 2", ['newCleanIdAdvance2' => $newCleanIdAdvance2]);
            //INSERT CLEAN ID RESULT ADVANCE 2

            //INSERT CLEAN ID RESULT ADVANCE 3
            $newCleanIdAdvance3 = CleanIdAdvance3::create([
                'file_id' => $file_id,
                'md5_id' => $md5_id,
                'additions_square_feet' => $_additions_square_feet,
                'assess_val_impr' => $_assess_val_impr,
                'assess_val_lnd' => $_assess_val_lnd,
                'assess_val_prop' => $_assess_val_prop,
                'bldg_style' => $_bldg_style,
                'bsmt_sqft' => $_bsmt_sqft,
                'bsmt_type' => $_bsmt_type,
                'build_sqft_assess' => $_build_sqft_assess,
                'business' => $_business,
                'combined_statistical_area' => $_combined_statistical_area,
                'metropolitan_division' => $_metropolitan_division,
                'middle' => $_middle,
                'middle_2' => $_middle_2,
                'mkt_ip_perc' => $_mkt_ip_perc,
                'mobile_home' => $_mobile_home,

                'mrtg_due' => $_mrtg_due,
                'mrtg_intrate' => $_mrtg_intrate,
                'mrtg_refi' => $_mrtg_refi,
                'mrtg_term' => $_mrtg_term,
                'mrtg_type' => $_mrtg_type,

                'mrtg2_amt' => $_mrtg2_amt,
                'mrtg2_date' => $_mrtg2_date,
                'mrtg2_deed_type' => $_mrtg2_deed_type,
                'mrtg2_due' => $_mrtg2_due,
                'mrtg2_equity' => $_mrtg2_equity,
                'mrtg2_intrate' => $_mrtg2_intrate,
                'mrtg2_inttype' => $_mrtg2_inttype,
                'mrtg2_refi' => $_mrtg2_refi,
                'mrtg2_term' => $_mrtg2_term,
                'mrtg2_type' => $_mrtg2_type,

                'mrtg3_amt' => $_mrtg3_amt,
                'mrtg3_date' => $_mrtg3_date,
                'mrtg3_deed_type' => $_mrtg3_deed_type,
                'mrtg3_due' => $_mrtg3_due,
                'mrtg3_equity' => $_mrtg3_equity,
                'mrtg3_inttype' => $_mrtg3_inttype,
                'mrtg3_refi' => $_mrtg3_refi,
                'mrtg3_term' => $_mrtg3_term,
                'mrtg3_type' => $_mrtg3_type,

                'msa_code' => $_msa_code,
                'number_bedrooms' => $_number_bedrooms,
                'number_bldgs' => $_number_bldgs,
                'number_fireplace' => $_number_fireplace,
                'number_park_spaces' => $_number_park_spaces,
                'number_rooms' => $_number_rooms,
                'own_biz' => $_own_biz,
                'owner_occupied' => $_owner_occupied,
                'ownership_relation' => $_ownership_relation,
                'owner_type_description' => $_owner_type_description,
                'estimated_value' => $_estimated_value,
                'ext_type' => $_ext_type,
                'finish_square_feet2' => $_finish_square_feet2,
                'fireplace' => $_fireplace,
                'first' => $_first,
                'first_2' => $_first_2,
                'found_type' => $_found_type,
                'fr_feet' => $_fr_feet,
                'fuel_type' => $_fuel_type,
                'full_baths' => $_full_baths,
                'half_baths' => $_half_baths,
                'garage_type' => $_garage_type,
                'grnd_sqft' => $_grnd_sqft,
                'heat_type' => $_heat_type,
                'hmstd' => $_hmstd,
                'impr_appr_val' => $_impr_appr_val,
                'imprval' => $_imprval,
                'imprval_type' => $_imprval_type,
                'land_appr_val' => $_land_appr_val,
                'landval' => $_landval,
                'landval_type' => $_landval_type,
                'last' => $_last,
                'last_2' => $_last_2,
                'lat' => $_lat,
                'lender_name' => $_lender_name,
                'lender2_name' => $_lender2_name,
                'lender3_name' => $_lender3_name,
                'loan_amt' => $_loan_amt,
                'loan_date' => $_loan_date,
                'loan_to_val' => $_loan_to_val,
                'lon' => $_lon,
                'markval' => $_markval,
                'markval_type' => $_markval_type,
                'patio_porch' => $_patio_porch,
                'patio_square_feet' => $_patio_square_feet,
                'pool' => $_pool,
                'porch_square_feet' => $_porch_square_feet,
                'previous_assessed_value' => $_previous_assessed_value,
                'prop_type' => $_prop_type,
                'rate_type' => $_rate_type,
                'rec_date' => $_rec_date,
                'roof_covtype' => $_roof_covtype,
                'roof_shapetype' => $_roof_shapetype,
                'sale_amt' => $_sale_amt,
                'sale_amt_pr' => $_sale_amt_pr,
                'sale_date' => $_sale_date,
                'sale_type_pr' => $_sale_type_pr,
                'sales_type' => $_sales_type,
                'sell_name' => $_sell_name,
                'sewer_type' => $_sewer_type,
                'site_quality' => $_site_quality,
                'std_address' => $_std_address,
                'std_city' => $_std_city,
                'std_state' => $_std_state,
                'std_zip' => $_std_zip,
                'std_zip4' => $_std_zip4,
                'stories_number' => $_stories_number,
                'suffix' => $_suffix,
                'suffix_2' => $_suffix_2,
                'tax_yr' => $_tax_yr,
                'tax_improvement_percent' => $_tax_improvement_percent,
                'title_co' => $_title_co,
                'tot_baths_est' => $_tot_baths_est,
                'ttl_appr_val' => $_ttl_appr_val,
                'ttl_bld_sqft' => $_ttl_bld_sqft,
                'ttl_tax' => $_ttl_tax,
                'unit_number' => $_unit_number,
                'vet_exempt' => $_vet_exempt,
                'water_type' => $_water_type,
            ]);
            // info("INSERT CLEAN ID RESULT ADVANCE 3", ['newCleanIdAdvance2' => $newCleanIdAdvance2]);
            //INSERT CLEAN ID RESULT ADVANCE 3
            /** IF BIG BDM MD5 HAVE RESULT */

            $status = 'found';
            $msg_description = 'Advance information Found From BIGDBM';
        }
        else 
        {
            $status = 'not_found';
            $msg_description = 'Advanced information Not Found From BIGDBM';
        }

        return [
            'status' => $status,
            'msg_description' => $msg_description,
        ];
    }

    /**
     * function ini merupakan bagian kecil dari function dataNotExistOnDBBIG.
     * function ini merupakan bagian kecil dari function dataExistOnDB.
     * dijalankan ketika data person email sudah ada, namun sudah duration nya sudah lewat 7 hari
     * dijalankan ketika data person email sudah ada, namun data seperti detail advanced nya belom ada
     */
    public function process_BIGDBM_advance_exist($file_id, $md5_id, $newPersonID, $md5param = "", $is_advance = false) 
    {    
        // info(__FUNCTION__, ['file_id' => $file_id, 'md5_id' => $md5_id, 'newPersonID' => $newPersonID, 'md5param' => $md5param, 'is_advance' => $is_advance]);
        $executionTimeList = [];
        
        date_default_timezone_set('America/Chicago');

        //BASIC INFORMATION
        $_ID = "";
        //BASIC INFORMATION

        //ADVANCE INFORMATION
            //identification
                $_gender_aux = "";
                $_age_aux = "";
                $_birth_year_aux = "";
                $_generation = "";
                $_marital_status = "";
            //identification
            //financial information
                $_household_income = "";
                $_median_household_income = "";
                $_household_net_worth = "";
                $_median_household_net_worth = "";
                $_discretionary_income = "";
                $_credit_score_median = "";
                $_credit_score_range = "";
            //financial information
            //occupation
                $_occupation_category = "";
                $_occupation_type = "";
                $_occupation_detail = "";
            //occupation
            //miscellaneous
                $_voter = "";
                $_urbanicity = "";
            //miscellaneous
            //contact information
                $_mobile_phone = "";
                $_mobile_phone2 = "";
                $_mobile_phone3 = "";
                $_mobile_phone_dnc = "";
                $_mobile_phone_dnc2 = "";
                $_mobile_phone_dnc3 = "";
                $_tax_bill_mailing_address = "";
                $_tax_bill_mailing_city = "";
                $_tax_bill_mailing_county = "";
                $_tax_bill_mailing_fips = "";
                $_tax_bill_mailing_state = "";
                $_tax_bill_mailing_zip = "";
                $_tax_bill_mailing_zip4 = "";
            //contact information
            //household information
                $_num_adults_household= "";
                $_num_children_household = "";
                $_num_persons_household = "";
                $_child_aged_0_3_household = "";
                $_child_aged_4_6_household = "";
                $_child_aged_7_9_household = "";
                $_child_aged_10_12_household = "";
                $_child_aged_13_18_household = "";
                $_children_household = "";
            //household information
            //marketing indicators
                $_has_email = "";
                $_has_phone = "";
                $_magazine_subscriber = "";
                $_charity_interest = "";
                $_likely_charitable_donor = "";            
            //marketing indicators
            //house and real estate
                $_dwelling_type = "";
                $_home_owner = "";
                $_home_owner_ordinal = "";
                $_length_of_residence = "";
                $_home_price = "";
                $_home_value = "";
                $_median_home_value = "";
                $_living_sqft = "";
                $_year_built_original = "";
                $_year_built_range = "";
                $_lot_number = "";
                $_legal_description = "";
                $_land_sqft = "";
                $_garage_sqft = "";
                $_subdivision = "";
                $_zoning_code = "";
            //house and real estate
            //interest and affinities
                $_cooking = "";
                $_gardening = "";
                $_music = "";
                $_diy = "";
                $_books = "";
                $_travel_vacation = "";
                $_health_beauty_products = "";
                $_pet_owner = "";
                $_photography = "";
                $_fitness = "";
                $_epicurean = "";
            //interest and affinities
            //location and census data
                $_cbsa = "";
                $_census_block = "";
                $_census_block_group = "";
                $_census_tract = "";
            //location and census data
            //unregistered row
                $_aerobics = "";
                $_african_american_affinity = "";
                $_amex_card = "";
                $_antiques = "";
                $_apparel_accessory_affinity = "";
                $_apparel_affinity = "";
                $_arts_and_crafts = "";
                $_asian_affinity = "";
                $_auto_affinity = "";
                $_auto_racing_affinity = "";
                $_aviation_affinity = "";
                $_bank_card = "";
                $_bargain_hunter_affinity = "";
                $_baseball = "";
                $_baseball_affinity = "";
                $_basketball = "";
                $_basketball_affinity = "";
                $_beauty_affinity = "";
                $_bible_affinity = "";
                $_bird_watching = "";
                $_birds_affinity = "";
                $_blue_collar = "";
                $_boating_sailing = "";
                $_boating_sailing_affinity = "";
                $_business_affinity = "";
                $_camping_hiking = "";
                $_camping_hiking_climbing_affinity = "";
                $_cars_interest = "";
                $_cat_owner = "";
                $_catalog_affinity = "";
                $_cigars = "";
                $_classical_music = "";
                $_coins = "";
                $_collectibles_affinity = "";
                $_college_affinity = "";
                $_computers_affinity = "";
                $_continuity_program_affinity = "";
                $_cooking_affinity = "";
                $_cosmetics = "";
                $_country_music = "";
                $_crafts_affinity = "";
                $_credit_card = "";
                $_credit_repair_affinity = "";
                $_crochet_affinity = "";
                $_diet_affinity = "";
                $_dieting = "";
                $_do_it_yourself_affinity = "";
                $_dog_owner = "";
                $_doll_collector = "";
                $_donor_affinity = "";
                $_education = "";
                $_education_ordinal = "";
                $_education_seekers_affinity = "";
                $_ego_affinity = "";
                $_entertainment_interest = "";
                $_figurines_collector = "";
                $_fine_arts_collector = "";
                $_fishing = "";
                $_fishing_affinity = "";
                $_fitness_affinity = "";
                $_football = "";
                $_football_affinity = "";
                $_gambling = "";
                $_gambling_affinity = "";
                $_games_affinity = "";
                $_gardening_affinity = "";
                $_gender = "";
                $_generation_ordinal = "";
                $_golf = "";
                $_golf_affinity = "";
                $_gourmet_affinity = "";
                $_grandparents_affinity = "";
                $_health_affinity = "";
                $_healthy_living = "";
                $_healthy_living_interest = "";
                $_high_tech_affinity = "";
                $_hispanic_affinity = "";
                $_hockey = "";
                $_hockey_affinity = "";
                $_home_decor = "";
                $_home_improvement_interest = "";
                $_home_office_affinity = "";
                $_home_study = "";
                $_hunting = "";
                $_hunting_affinity = "";
                $_jazz = "";
                $_kids_apparel_affinity = "";
                $_knit_affinity = "";
                $_knitting_quilting_sewing = "";
                $_luxury_life = "";
                $_married = "";
                $_median_income = "";
                $_mens_apparel_affinity = "";
                $_mens_fashion_affinity = "";
                $_mortgage_age = "";
                $_mortgage_amount = "";
                $_mortgage_loan_type = "";
                $_mortgage_refi_age = "";
                $_mortgage_refi_amount = "";
                $_mortgage_refi_type = "";
                $_motor_racing = "";
                $_motorcycles = "";
                $_motorcycles_affinity = "";
                $_movies = "";
                $_nascar = "";
                $_needlepoint_affinity = "";
                $_new_credit_offered_household = "";
                $_num_credit_lines_household = "";
                $_num_generations_household = "";
                $_outdoors = "";
                $_outdoors_affinity = "";
                $_owns_investments = "";
                $_owns_mutual_funds = "";
                $_owns_stocks_and_bonds = "";
                $_owns_swimming_pool = "";
                $_personal_finance_affinity = "";
                $_personality = "";
                $_plates_collector = "";
                $_pop_density = "";
                $_premium_amex_card = "";
                $_premium_card = "";
                $_premium_income_household = "";
                $_premium_income_midpt_household = "";
                $_quilt_affinity = "";
                $_religious_music = "";
                $_rhythm_and_blues = "";
                $_rock_music = "";
                $_running = "";
                $_rv = "";
                $_scuba = "";
                $_self_improvement = "";
                $_sewing_affinity = "";
                $_single_family_dwelling = "";
                $_snow_skiing = "";
                $_snow_skiing_affinity = "";
                $_soccer = "";
                $_soccer_affinity = "";
                $_soft_rock = "";
                $_soho_business = "";
                $_sports_memoribilia_collector = "";
                $_stamps = "";
                $_sweepstakes_affinity = "";
                $_tennis = "";
                $_tennis_affinity = "";
                $_tobacco_affinity = "";
                $_travel_affinity = "";
                $_travel_cruise_affinity = "";
                $_travel_cruises = "";
                $_travel_personal = "";
                $_travel_rv_affinity = "";
                $_travel_us_affinity = "";
                $_truck_owner = "";
                $_trucks_affinity = "";
                $_tv_movies_affinity = "";
                $_veteran_household = "";
                $_walking = "";
                $_weight_lifting = "";
                $_wildlife_affinity = "";
                $_womens_apparel_affinity = "";
                $_womens_fashion_affinity = "";
                $_woodworking = "";
                $_political_contributor_aux = "";
                $_political_party_aux = "";
                $_financial_power = "";
                $_mortgage_open_1st_intent = "";
                $_mortgage_open_2nd_intent = "";
                $_mortgage_new_intent = "";
                $_mortgage_refinance_intent = "";
                $_automotive_loan_intent = "";
                $_bank_card_intent = "";
                $_personal_loan_intent = "";
                $_retail_card_intent = "";
                $_student_loan_cons_intent = "";
                $_student_loan_intent = "";
                $_3qtr_baths = "";
                $_ac_type = "";
                $_acres = "";
                $_additions_square_feet = "";
                $_assess_val_impr = "";
                $_assess_val_lnd = "";
                $_assess_val_prop = "";
                $_bldg_style = "";
                $_bsmt_sqft = "";
                $_bsmt_type = "";
                $_build_sqft_assess = "";
                $_business = "";
                $_combined_statistical_area = "";
                $_metropolitan_division = "";
                $_middle = "";
                $_middle_2 = "";
                $_mkt_ip_perc = "";
                $_mobile_home = "";

                $_mrtg_due = "";
                $_mrtg_intrate = "";
                $_mrtg_refi = "";
                $_mrtg_term = "";
                $_mrtg_type = "";

                $_mrtg2_amt = "";
                $_mrtg2_date = "";
                $_mrtg2_deed_type = "";
                $_mrtg2_due = "";
                $_mrtg2_equity = "";
                $_mrtg2_intrate = "";
                $_mrtg2_inttype = "";
                $_mrtg2_refi = "";
                $_mrtg2_term = "";
                $_mrtg2_type = "";

                $_mrtg3_amt = "";
                $_mrtg3_date = "";
                $_mrtg3_deed_type = "";
                $_mrtg3_due = "";
                $_mrtg3_equity = "";
                $_mrtg3_inttype = "";
                $_mrtg3_refi = "";
                $_mrtg3_term = "";
                $_mrtg3_type = "";

                $_msa_code = "";
                $_number_bedrooms = "";
                $_number_bldgs = "";
                $_number_fireplace = "";
                $_number_park_spaces = "";
                $_number_rooms = "";
                $_own_biz = "";
                $_owner_occupied = "";
                $_ownership_relation = "";
                $_owner_type_description = "";
                $_estimated_value = "";
                $_ext_type = "";
                $_finish_square_feet2 = "";
                $_fireplace = "";
                $_first = "";
                $_first_2 = "";
                $_found_type = "";
                $_fr_feet = "";
                $_fuel_type = "";
                $_full_baths = "";
                $_half_baths = "";
                $_garage_type = "";
                $_grnd_sqft = "";
                $_heat_type = "";
                $_hmstd = "";
                $_impr_appr_val = "";
                $_imprval = "";
                $_imprval_type = "";
                $_land_appr_val = "";
                $_landval = "";
                $_landval_type = "";
                $_last = "";
                $_last_2 = "";
                $_lat = "";
                $_lender_name = "";
                $_lender2_name = "";
                $_lender3_name = "";
                $_loan_amt = "";
                $_loan_date = "";
                $_loan_to_val = "";
                $_lon = "";
                $_markval = "";
                $_markval_type = "";
                $_patio_porch = "";
                $_patio_square_feet = "";
                $_pool = "";
                $_porch_square_feet = "";
                $_previous_assessed_value = "";
                $_prop_type = "";
                $_rate_type = "";
                $_rec_date = "";
                $_roof_covtype = "";
                $_roof_shapetype = "";
                $_sale_amt = "";
                $_sale_amt_pr = "";
                $_sale_date = "";
                $_sale_type_pr = "";
                $_sales_type = "";
                $_sell_name = "";
                $_sewer_type = "";
                $_site_quality = "";
                $_std_address = "";
                $_std_city = "";
                $_std_state = "";
                $_std_zip = "";
                $_std_zip4 = "";
                $_stories_number = "";
                $_suffix = "";
                $_suffix_2 = "";
                $_tax_yr = "";
                $_tax_improvement_percent = "";
                $_title_co = "";
                $_tot_baths_est = "";
                $_ttl_appr_val = "";
                $_ttl_bld_sqft = "";
                $_ttl_tax = "";
                $_unit_number = "";
                $_vet_exempt = "";
                $_water_type = "";
            //unregistered row
        //ADVANCE INFORMATION

        // $trackBigBDM = "BDMADVANCEEXIST";

        Log::info("Start Check BigBDM MD5 Advance exist");
        $startBigbdmMD5Time = microtime(true);

        $bigBDM_MD5_advance = $this->bigDBM->GetDataByMD5($file_id, $md5param,'advanced',$is_advance);

        if (is_object($bigBDM_MD5_advance) && isset($bigBDM_MD5_advance->isError) && !empty($bigBDM_MD5_advance->isError)) 
        {
            CleanIDResult::where('file_id', $file_id)->where('md5_id', $md5_id)->delete();
            throw new \Exception("API Error: " . ($bigBDM_MD5_advance->message ?? 'Unknown error'));
        }

        $endBigbdmMD5Time = microtime(true);

        // convert epochtime to date format ('Y-m-d H:i:s')
        $startBigbdmMD5Date = Carbon::createFromTimestamp($startBigbdmMD5Time)->setTimezone('America/Chicago')->format('Y-m-d H:i:s');
        $endBigbdmMD5Date = Carbon::createFromTimestamp($endBigbdmMD5Time)->setTimezone('America/Chicago')->format('Y-m-d H:i:s');
        // convert epochtime to date format ('Y-m-d H:i:s')

        $totalBigbdmMD5Time = $endBigbdmMD5Time - $startBigbdmMD5Time;
        $totalBigbdmMD5Time = number_format($totalBigbdmMD5Time,2,'.','');

        $executionTimeList['bigBDM_MD5_advance'] = [
            'start_execution_time' => $startBigbdmMD5Date,
            'end_execution_time' => $endBigbdmMD5Date,
            'total_execution_time' => $totalBigbdmMD5Time,
        ];

        /** IF BIG BDM MD5 HAVE RESULT */
        if (count((array)$bigBDM_MD5_advance) > 0) 
        {
            Log::info("BigBDM Have result Advance");
            
            // $trackBigBDM = $trackBigBDM . "->MD5AdvanceExist";
            /** REPORT ANALYTIC */
            // $this->controller->UpsertReportAnalytics($file_id,'clean_id','bigbdmemail');
            /** REPORT ANALYTIC */

            foreach ($bigBDM_MD5_advance as $rd => $a) 
            {
                //// BASIC INFORMATION
                    $bigEmail = (isset($a[0]->Email))?$a[0]->Email:'';
                    $bigEmail = explode(",",$bigEmail);

                    $bigPhone = (isset($a[0]->Phone))?$a[0]->Phone:'';
                    $bigPhone = explode(",",$bigPhone);

                    $_FirstName = (isset($a[0]->First_Name))?$a[0]->First_Name:'';
                    $_LastName = (isset($a[0]->Last_Name))?$a[0]->Last_Name:'';
                    $_Email = $bigEmail[0];
                    $_Email2 = (isset($bigEmail[1]))?$bigEmail[1]:'';
                    $_Phone = $bigPhone[0];
                    $_Phone2 = (isset($bigPhone[1]))?$bigPhone[1]:'';
                    // $_Address1 = (isset($a[0]->Address))?$a[0]->Address:'';
                    if(isset($a[0]->Addr_Primary))
                    {
                        $_Address1 = (isset($a[0]->Addr_Primary))?$a[0]->Addr_Primary:'';
                    }
                    else
                    {
                        $_Address1 = (isset($a[0]->Address))?$a[0]->Address:'';
                    }
                    if(isset($a[0]->Addr_Secondary))
                    {
                        $_Address2 = (isset($a[0]->Addr_Secondary))?$a[0]->Addr_Secondary:'';
                    }
                    $_City =  (isset($a[0]->City))?$a[0]->City:'';
                    $_State = (isset($a[0]->State))?$a[0]->State:'';
                    $_Zipcode = (isset($a[0]->Zip))?$a[0]->Zip:'';
                    $_bigdbm_id = (isset($a[0]->Id))?$a[0]->Id:'';
                //// BASIC INFORMATION

                //ADVANCE INFORMATION

                    //identification
                        $_gender_aux = (isset($a[0]->Gender_aux))?$a[0]->Gender_aux:'';
                        $_age_aux = (isset($a[0]->Age_aux)) ? $a[0]->Age_aux : '';
                        $_birth_year_aux = (isset($a[0]->Birth_Year_aux)) ? $a[0]->Birth_Year_aux : '';
                        $_generation = (isset($a[0]->Generation)) ? $a[0]->Generation : '';
                        $_marital_status = (isset($a[0]->Marital_Status))?$a[0]->Marital_Status:'';
                    //identification
                    
                    //financial information
                        $_household_income = (isset($a[0]->Income_HH))?$a[0]->Income_HH:'';
                        $_median_household_income = (isset($a[0]->Income_Midpts_HH))?$a[0]->Income_Midpts_HH:'';
                        $_household_net_worth = (isset($a[0]->Net_Worth_HH))?$a[0]->Net_Worth_HH:'';
                        $_median_household_net_worth = (isset($a[0]->Net_Worth_Midpt_HH))?$a[0]->Net_Worth_Midpt_HH:'';
                        $_discretionary_income = (isset($a[0]->Discretionary_Income)) ? $a[0]->Discretionary_Income : '';
                        $_credit_score_median = (isset($a[0]->Credit_Midpts)) ? $a[0]->Credit_Midpts : '';
                        $_credit_score_range = (isset($a[0]->Credit_Range)) ? $a[0]->Credit_Range : '';
                    //financial information

                    //occupation
                        $_occupation_category = (isset($a[0]->Occupation_Category)) ? $a[0]->Occupation_Category : '';
                        $_occupation_type =(isset($a[0]->Occupation_Type)) ? $a[0]->Occupation_Type : '';
                        $_occupation_detail = (isset($a[0]->Occupation_Detail)) ? $a[0]->Occupation_Detail : '';
                    //occupation
                    
                    //miscellaneous
                        $_voter = (isset($a[0]->Voter)) ? $a[0]->Voter : '';
                        $_urbanicity = (isset($a[0]->Urbanicity)) ? $a[0]->Urbanicity : '';
                    //miscellaneous

                    //contact information

                        $_mobile_phone = (isset($a[0]->Mobile_Phone_1))?$a[0]->Mobile_Phone_1:'';
                        $_mobile_phone2 = (isset($a[0]->Mobile_Phone_2))?$a[0]->Mobile_Phone_2:'';
                        $_mobile_phone3 = (isset($a[0]->Mobile_Phone_3))?$a[0]->Mobile_Phone_3:'';
                        $_mobile_phone_dnc = (isset($a[0]->Mobile_Phone_1_DNC))?$a[0]->Mobile_Phone_1_DNC:'';
                        $_mobile_phone_dnc2 = (isset($a[0]->Mobile_Phone_2_DNC))?$a[0]->Mobile_Phone_2_DNC:'';
                        $_mobile_phone_dnc3 = (isset($a[0]->Mobile_Phone_3_DNC))?$a[0]->Mobile_Phone_3_DNC:'';

                        $_tax_bill_mailing_address = (isset($a[0]->TaxBillMailingAddress))?$a[0]->TaxBillMailingAddress:'';
                        $_tax_bill_mailing_city = (isset($a[0]->TaxBillMailingCity))?$a[0]->TaxBillMailingCity:'';
                        $_tax_bill_mailing_county = (isset($a[0]->TaxBillMailingCounty))?$a[0]->TaxBillMailingCounty:'';
                        $_tax_bill_mailing_fips = (isset($a[0]->TaxBillMailingFIPs))?$a[0]->TaxBillMailingFIPs:'';
                        $_tax_bill_mailing_state = (isset($a[0]->TaxBillMailingState))?$a[0]->TaxBillMailingState:'';
                        $_tax_bill_mailing_zip = (isset($a[0]->TaxBillMailingZip))?$a[0]->TaxBillMailingZip:'';
                        $_tax_bill_mailing_zip4 = (isset($a[0]->TaxBillMailingZip4))?$a[0]->TaxBillMailingZip4:'';
                    //contact information

                    //household information
                        $_num_adults_household= (isset($a[0]->Num_Adults_HH))?$a[0]->Num_Adults_HH:'';
                        $_num_children_household = (isset($a[0]->Num_Children_HH))?$a[0]->Num_Children_HH:'';
                        $_num_persons_household = (isset($a[0]->Num_Persons_HH))?$a[0]->Num_Persons_HH:'';
                        $_child_aged_0_3_household = (isset($a[0]->Child_Aged_0_3_HH))?$a[0]->Child_Aged_0_3_HH:'';
                        $_child_aged_4_6_household = (isset($a[0]->Child_Aged_4_6_HH))?$a[0]->Child_Aged_4_6_HH:'';
                        $_child_aged_7_9_household = (isset($a[0]->Child_Aged_7_9_HH))?$a[0]->Child_Aged_7_9_HH:'';
                        $_child_aged_10_12_household = (isset($a[0]->Child_Aged_10_12_HH))?$a[0]->Child_Aged_10_12_HH:'';
                        $_child_aged_13_18_household = (isset($a[0]->Child_Aged_13_18_HH))?$a[0]->Child_Aged_13_18_HH:'';
                        $_children_household = (isset($a[0]->Children_HH))?$a[0]->Children_HH:'';
                    //household information

                    //marketing indicators
                        $_has_email = (isset($a[0]->HasEmail))?$a[0]->HasEmail:'';
                        $_has_phone = (isset($a[0]->HasPhone))?$a[0]->HasPhone:'';
                        $_magazine_subscriber = (isset($a[0]->Magazine_Subscriber))?$a[0]->Magazine_Subscriber:'';
                        $_charity_interest = (isset($a[0]->Charity_Interest))?$a[0]->Charity_Interest:'';
                        $_likely_charitable_donor = (isset($a[0]->Likely_Charitable_Donor))?$a[0]->Likely_Charitable_Donor:'';     
                    //marketing indicators

                    //house and real estate
                        $_dwelling_type = (isset($a[0]->Dwelling_Type))?$a[0]->Dwelling_Type:'';     
                        $_home_owner = (isset($a[0]->Home_Owner))?$a[0]->Home_Owner:'';     
                        $_home_owner_ordinal = (isset($a[0]->Home_Owner_Ordinal))?$a[0]->Home_Owner_Ordinal:'';     
                        $_length_of_residence = (isset($a[0]->Length_of_Residence))?$a[0]->Length_of_Residence:'';     
                        $_home_price = (isset($a[0]->Home_Price))?$a[0]->Home_Price:'';     
                        $_home_value = (isset($a[0]->Home_Value))?$a[0]->Home_Value:'';     
                        $_median_home_value = (isset($a[0]->Median_Home_Value))?$a[0]->Median_Home_Value:'';  
                        $_living_sqft = (isset($a[0]->LIVING_SQFT))?$a[0]->LIVING_SQFT:'';  
                        $_year_built_original = (isset($a[0]->YR_BUILT_ORIG))?$a[0]->YR_BUILT_ORIG:'';  
                        $_year_built_range = (isset($a[0]->YR_BUILT_RANGE))?$a[0]->YR_BUILT_RANGE:'';  
                        $_lot_number = (isset($a[0]->LotNumber))?$a[0]->LotNumber:'';  
                        $_legal_description = (isset($a[0]->LegalDescription))?$a[0]->LegalDescription:'';  
                        $_land_sqft = (isset($a[0]->LAND_SQFT))?$a[0]->LAND_SQFT:'';  
                        $_garage_sqft = (isset($a[0]->GAR_SQFT))?$a[0]->GAR_SQFT:'';  
                        $_subdivision = (isset($a[0]->SUBDIVISION))?$a[0]->SUBDIVISION:'';  
                        $_zoning_code = (isset($a[0]->ZONING_CODE))?$a[0]->ZONING_CODE:'';  
                    //house and real estate

                    //interest and affinities
                        $_cooking = (isset($a[0]->Cooking))?$a[0]->Cooking:'';  
                        $_gardening = (isset($a[0]->Gardening))?$a[0]->Gardening:''; 
                        $_music = (isset($a[0]->Music))?$a[0]->Music:''; 
                        $_diy =  (isset($a[0]->DIY))?$a[0]->DIY:''; 
                        $_books = (isset($a[0]->Books))?$a[0]->Books:''; 
                        $_travel_vacation = (isset($a[0]->Travel_Vacation))?$a[0]->Travel_Vacation:''; 
                        $_health_beauty_products = (isset($a[0]->Health_Beauty_Products))?$a[0]->Health_Beauty_Products:''; 
                        $_pet_owner = (isset($a[0]->Pet_Owner))?$a[0]->Pet_Owner:''; 
                        $_photography = (isset($a[0]->Photography))?$a[0]->Photography:''; 
                        $_fitness = (isset($a[0]->Fitness))?$a[0]->Fitness:''; 
                        $_epicurean = (isset($a[0]->Epicurean))?$a[0]->Epicurean:''; 
                    //interest and affinities

                    //location and census data
                        $_cbsa = (isset($a[0]->Cbsa))?$a[0]->Cbsa:''; 
                        $_census_block = (isset($a[0]->Census_Block))?$a[0]->Census_Block:''; 
                        $_census_block_group = (isset($a[0]->Census_Block_Group))?$a[0]->Census_Block_Group:''; 
                        $_census_tract = (isset($a[0]->Census_Tract))?$a[0]->Census_Tract:''; 
                    //location and census data

                //ADVANCE INFORMATION

                //unregistered row
                    $_aerobics = (isset($a[0]->Aerobics)) ? $a[0]->Aerobics : '';
                    $_african_american_affinity = (isset($a[0]->African_American_Affinity)) ? $a[0]->African_American_Affinity : '';
                    $_amex_card = (isset($a[0]->AMEX_Card)) ? $a[0]->AMEX_Card : '';
                    $_antiques = (isset($a[0]->Antiques)) ? $a[0]->Antiques : '';
                    $_apparel_accessory_affinity = (isset($a[0]->Apparel_Accessory_Affinity)) ? $a[0]->Apparel_Accessory_Affinity : '';
                    $_apparel_affinity = (isset($a[0]->Apparel_Affinity)) ? $a[0]->Apparel_Affinity : '';
                    $_arts_and_crafts = (isset($a[0]->Arts_and_Crafts)) ? $a[0]->Arts_and_Crafts : '';
                    $_asian_affinity = (isset($a[0]->Asian_Affinity)) ? $a[0]->Asian_Affinity : '';
                    $_auto_affinity = (isset($a[0]->Auto_Affinity)) ? $a[0]->Auto_Affinity : '';
                    $_auto_racing_affinity = (isset($a[0]->Auto_Racing_Affinity)) ? $a[0]->Auto_Racing_Affinity : '';
                    $_aviation_affinity = (isset($a[0]->Aviation_Affinity)) ? $a[0]->Aviation_Affinity : '';
                    $_bank_card = (isset($a[0]->Bank_Card)) ? $a[0]->Bank_Card : '';
                    $_bargain_hunter_affinity = (isset($a[0]->Bargain_Hunter_Affinity)) ? $a[0]->Bargain_Hunter_Affinity : '';
                    $_baseball = (isset($a[0]->Baseball)) ? $a[0]->Baseball : '';
                    $_baseball_affinity = (isset($a[0]->Baseball_Affinity)) ? $a[0]->Baseball_Affinity : '';
                    $_basketball = (isset($a[0]->Basketball)) ? $a[0]->Basketball : '';
                    $_basketball_affinity = (isset($a[0]->Basketball_Affinity)) ? $a[0]->Basketball_Affinity : '';
                    $_beauty_affinity = (isset($a[0]->Beauty_Affinity)) ? $a[0]->Beauty_Affinity : '';
                    $_bible_affinity = (isset($a[0]->Bible_Affinity)) ? $a[0]->Bible_Affinity : '';
                    $_bird_watching = (isset($a[0]->Bird_watching)) ? $a[0]->Bird_watching : '';
                    $_birds_affinity = (isset($a[0]->Birds_Affinity)) ? $a[0]->Birds_Affinity : '';
                    $_blue_collar = (isset($a[0]->Blue_Collar)) ? $a[0]->Blue_Collar : '';
                    $_boating_sailing = (isset($a[0]->Boating_Sailing)) ? $a[0]->Boating_Sailing : '';
                    $_boating_sailing_affinity = (isset($a[0]->Boating_Sailing_Affinity)) ? $a[0]->Boating_Sailing_Affinity : '';
                    $_business_affinity = (isset($a[0]->Business_Affinity)) ? $a[0]->Business_Affinity : '';
                    $_camping_hiking = (isset($a[0]->Camping_Hiking)) ? $a[0]->Camping_Hiking : '';
                    $_camping_hiking_climbing_affinity = (isset($a[0]->Camping_Hiking_Climbing_Affinity)) ? $a[0]->Camping_Hiking_Climbing_Affinity : '';
                    $_cars_interest = (isset($a[0]->Cars_Interest)) ? $a[0]->Cars_Interest : '';
                    $_cat_owner = (isset($a[0]->Cat_Owner)) ? $a[0]->Cat_Owner : '';
                    $_catalog_affinity = (isset($a[0]->Catalog_Affinity)) ? $a[0]->Catalog_Affinity : '';
                    $_cigars = (isset($a[0]->Cigars)) ? $a[0]->Cigars : '';
                    $_classical_music = (isset($a[0]->Classical_Music)) ? $a[0]->Classical_Music : '';
                    $_coins = (isset($a[0]->Coins)) ? $a[0]->Coins : '';
                    $_collectibles_affinity = (isset($a[0]->Collectibles_Affinity)) ? $a[0]->Collectibles_Affinity : '';
                    $_college_affinity = (isset($a[0]->College_Affinity)) ? $a[0]->College_Affinity : '';
                    $_computers_affinity = (isset($a[0]->Computers_Affinity)) ? $a[0]->Computers_Affinity : '';
                    $_continuity_program_affinity = (isset($a[0]->Continuity_Program_Affinity)) ? $a[0]->Continuity_Program_Affinity : '';
                    $_cooking_affinity = (isset($a[0]->Cooking_Affinity)) ? $a[0]->Cooking_Affinity : '';
                    $_cosmetics = (isset($a[0]->Cosmetics)) ? $a[0]->Cosmetics : '';
                    $_country_music = (isset($a[0]->Country_Music)) ? $a[0]->Country_Music : '';
                    $_crafts_affinity = (isset($a[0]->Crafts_Affinity)) ? $a[0]->Crafts_Affinity : '';
                    $_credit_card = (isset($a[0]->Credit_Card)) ? $a[0]->Credit_Card : '';
                    $_credit_repair_affinity = (isset($a[0]->Credit_Repair_Affinity)) ? $a[0]->Credit_Repair_Affinity : '';
                    $_crochet_affinity = (isset($a[0]->Crochet_Affinity)) ? $a[0]->Crochet_Affinity : '';
                    $_diet_affinity = (isset($a[0]->Diet_Affinity)) ? $a[0]->Diet_Affinity : '';
                    $_dieting = (isset($a[0]->Dieting)) ? $a[0]->Dieting : '';
                    $_do_it_yourself_affinity = (isset($a[0]->Do_it_Yourself_Affinity)) ? $a[0]->Do_it_Yourself_Affinity : '';
                    $_dog_owner = (isset($a[0]->Dog_Owner)) ? $a[0]->Dog_Owner : '';
                    $_doll_collector = (isset($a[0]->Doll_Collector)) ? $a[0]->Doll_Collector : '';
                    $_donor_affinity = (isset($a[0]->Donor_Affinity)) ? $a[0]->Donor_Affinity : '';
                    $_education = (isset($a[0]->Education)) ? $a[0]->Education : '';
                    $_education_ordinal = (isset($a[0]->Education_Ordinal)) ? $a[0]->Education_Ordinal : '';
                    $_education_seekers_affinity = (isset($a[0]->Education_Seekers_Affinity)) ? $a[0]->Education_Seekers_Affinity : '';
                    $_ego_affinity = (isset($a[0]->EGO_Affinity)) ? $a[0]->EGO_Affinity : '';
                    $_entertainment_interest = (isset($a[0]->Entertainment_Interest)) ? $a[0]->Entertainment_Interest : '';
                    $_figurines_collector = (isset($a[0]->Figurines_Collector)) ? $a[0]->Figurines_Collector : '';
                    $_fine_arts_collector = (isset($a[0]->Fine_Arts_Collector)) ? $a[0]->Fine_Arts_Collector : '';
                    $_fishing = (isset($a[0]->Fishing)) ? $a[0]->Fishing : '';
                    $_fishing_affinity = (isset($a[0]->Fishing_Affinity)) ? $a[0]->Fishing_Affinity : '';
                    $_football = (isset($a[0]->Football)) ? $a[0]->Football : '';
                    $_football_affinity = (isset($a[0]->Football_Affinity)) ? $a[0]->Football_Affinity : '';
                    $_gambling = (isset($a[0]->Gambling)) ? $a[0]->Gambling : '';
                    $_gambling_affinity = (isset($a[0]->Gambling_Affinity)) ? $a[0]->Gambling_Affinity : '';
                    $_games_affinity = (isset($a[0]->Games_Affinity)) ? $a[0]->Games_Affinity : '';
                    $_gardening_affinity = (isset($a[0]->Gardening_Affinity)) ? $a[0]->Gardening_Affinity : '';
                    $_generation_ordinal = (isset($a[0]->Generation_Ordinal)) ? $a[0]->Generation_Ordinal : '';
                    $_golf = (isset($a[0]->Golf)) ? $a[0]->Golf : '';
                    $_golf_affinity = (isset($a[0]->Golf_Affinity)) ? $a[0]->Golf_Affinity : '';
                    $_gourmet_affinity = (isset($a[0]->Gourmet_Affinity)) ? $a[0]->Gourmet_Affinity : '';
                    $_grandparents_affinity = (isset($a[0]->Grandparents_Affinity)) ? $a[0]->Grandparents_Affinity : '';
                    $_health_affinity = (isset($a[0]->Health_Affinity)) ? $a[0]->Health_Affinity : '';
                    $_healthy_living = (isset($a[0]->Healthy_Living)) ? $a[0]->Healthy_Living : '';
                    $_healthy_living_interest = (isset($a[0]->Healthy_Living_Interest)) ? $a[0]->Healthy_Living_Interest : '';
                    $_high_tech_affinity = (isset($a[0]->High_Tech_Affinity)) ? $a[0]->High_Tech_Affinity : '';
                    $_hispanic_affinity = (isset($a[0]->Hispanic_Affinity)) ? $a[0]->Hispanic_Affinity : '';
                    $_hockey = (isset($a[0]->Hockey)) ? $a[0]->Hockey : '';
                    $_hockey_affinity = (isset($a[0]->Hockey_Affinity)) ? $a[0]->Hockey_Affinity : '';
                    $_home_decor = (isset($a[0]->Home_Decor)) ? $a[0]->Home_Decor : '';
                    $_home_improvement_interest = (isset($a[0]->Home_Improvement_Interest)) ? $a[0]->Home_Improvement_Interest : '';
                    $_home_office_affinity = (isset($a[0]->Home_Office_Affinity)) ? $a[0]->Home_Office_Affinity : '';
                    $_home_study = (isset($a[0]->Home_Study)) ? $a[0]->Home_Study : '';
                    $_hunting = (isset($a[0]->Hunting)) ? $a[0]->Hunting : '';
                    $_hunting_affinity = (isset($a[0]->Hunting_Affinity)) ? $a[0]->Hunting_Affinity : '';
                    $_jazz = (isset($a[0]->Jazz)) ? $a[0]->Jazz : '';
                    $_kids_apparel_affinity = (isset($a[0]->Kids_Apparel_Affinity)) ? $a[0]->Kids_Apparel_Affinity : '';
                    $_knit_affinity = (isset($a[0]->Knit_Affinity)) ? $a[0]->Knit_Affinity : '';
                    $_knitting_quilting_sewing = (isset($a[0]->Knitting_Quilting_Sewing)) ? $a[0]->Knitting_Quilting_Sewing : '';
                    $_luxury_life = (isset($a[0]->Luxury_Life)) ? $a[0]->Luxury_Life : '';
                    $_married = (isset($a[0]->Married)) ? $a[0]->Married : '';
                    $_median_income = (isset($a[0]->Median_Income)) ? $a[0]->Median_Income : '';
                    $_mens_apparel_affinity = (isset($a[0]->Mens_Apparel_Affinity)) ? $a[0]->Mens_Apparel_Affinity : '';
                    $_mens_fashion_affinity = (isset($a[0]->Mens_Fashion_Affinity)) ? $a[0]->Mens_Fashion_Affinity : '';
                    $_mortgage_age = (isset($a[0]->Mortgage_Age)) ? $a[0]->Mortgage_Age : '';
                    $_mortgage_amount = (isset($a[0]->Mortgage_Amount)) ? $a[0]->Mortgage_Amount : '';
                    $_mortgage_loan_type = (isset($a[0]->Mortgage_Loan_Type)) ? $a[0]->Mortgage_Loan_Type : '';
                    $_mortgage_refi_age = (isset($a[0]->Mortgage_Refi_Age)) ? $a[0]->Mortgage_Refi_Age : '';
                    $_mortgage_refi_amount = (isset($a[0]->Mortgage_Refi_Amount)) ? $a[0]->Mortgage_Refi_Amount : '';
                    $_mortgage_refi_type = (isset($a[0]->Mortgage_Refi_Type)) ? $a[0]->Mortgage_Refi_Type : '';
                    $_motor_racing = (isset($a[0]->Motor_Racing)) ? $a[0]->Motor_Racing : '';
                    $_motorcycles = (isset($a[0]->Motorcycles)) ? $a[0]->Motorcycles : '';
                    $_motorcycles_affinity = (isset($a[0]->Motorcycles_Affinity)) ? $a[0]->Motorcycles_Affinity : '';
                    $_movies = (isset($a[0]->Movies)) ? $a[0]->Movies : '';
                    $_nascar = (isset($a[0]->NASCAR)) ? $a[0]->NASCAR : '';
                    $_needlepoint_affinity = (isset($a[0]->Needlepoint_Affinity)) ? $a[0]->Needlepoint_Affinity : '';
                    $_new_credit_offered_household = (isset($a[0]->New_Credit_Offered_HH)) ? $a[0]->New_Credit_Offered_HH : '';
                    $_num_credit_lines_household = (isset($a[0]->Num_Credit_Lines_HH)) ? $a[0]->Num_Credit_Lines_HH : '';
                    $_num_generations_household = (isset($a[0]->Num_Generations_HH)) ? $a[0]->Num_Generations_HH : '';
                    $_outdoors = (isset($a[0]->Outdoors)) ? $a[0]->Outdoors : '';
                    $_outdoors_affinity = (isset($a[0]->Outdoors_Affinity)) ? $a[0]->Outdoors_Affinity : '';
                    $_owns_investments = (isset($a[0]->Owns_Investments)) ? $a[0]->Owns_Investments : '';
                    $_owns_mutual_funds = (isset($a[0]->Owns_Mutual_Funds)) ? $a[0]->Owns_Mutual_Funds : '';
                    $_owns_stocks_and_bonds = (isset($a[0]->Owns_Stocks_And_Bonds)) ? $a[0]->Owns_Stocks_And_Bonds : '';
                    $_owns_swimming_pool = (isset($a[0]->Owns_Swimming_Pool)) ? $a[0]->Owns_Swimming_Pool : '';
                    $_personal_finance_affinity = (isset($a[0]->Personal_Finance_Affinity)) ? $a[0]->Personal_Finance_Affinity : '';
                    $_personality = (isset($a[0]->Personality)) ? $a[0]->Personality : '';
                    $_plates_collector = (isset($a[0]->Plates_Collector)) ? $a[0]->Plates_Collector : '';
                    $_pop_density = (isset($a[0]->Pop_Density)) ? $a[0]->Pop_Density : '';
                    $_premium_amex_card = (isset($a[0]->Premium_AMEX_Card)) ? $a[0]->Premium_AMEX_Card : '';
                    $_premium_card = (isset($a[0]->Premium_Card)) ? $a[0]->Premium_Card : '';
                    $_premium_income_household = (isset($a[0]->Premium_Income_HH)) ? $a[0]->Premium_Income_HH : '';
                    $_premium_income_midpt_household = (isset($a[0]->Premium_Income_Midpt_HH)) ? $a[0]->Premium_Income_Midpt_HH : '';
                    $_quilt_affinity = (isset($a[0]->Quilt_Affinity)) ? $a[0]->Quilt_Affinity : '';
                    $_religious_music = (isset($a[0]->Religious_Music)) ? $a[0]->Religious_Music : '';
                    $_rhythm_and_blues = (isset($a[0]->Rhythm_and_Blues)) ? $a[0]->Rhythm_and_Blues : '';
                    $_rock_music = (isset($a[0]->Rock_Music)) ? $a[0]->Rock_Music : '';
                    $_running = (isset($a[0]->Running)) ? $a[0]->Running : '';
                    $_rv = (isset($a[0]->RV)) ? $a[0]->RV : '';
                    $_scuba = (isset($a[0]->Scuba)) ? $a[0]->Scuba : '';
                    $_self_improvement = (isset($a[0]->Self_Improvement)) ? $a[0]->Self_Improvement : '';
                    $_sewing_affinity = (isset($a[0]->Sewing_Affinity)) ? $a[0]->Sewing_Affinity : '';
                    $_single_family_dwelling = (isset($a[0]->Single_Family_Dwelling)) ? $a[0]->Single_Family_Dwelling : '';
                    $_snow_skiing = (isset($a[0]->Snow_Skiing)) ? $a[0]->Snow_Skiing : '';
                    $_snow_skiing_affinity = (isset($a[0]->Snow_Skiing_Affinity)) ? $a[0]->Snow_Skiing_Affinity : '';
                    $_soccer = (isset($a[0]->Soccer)) ? $a[0]->Soccer : '';
                    $_soccer_affinity = (isset($a[0]->Soccer_Affinity)) ? $a[0]->Soccer_Affinity : '';
                    $_soft_rock = (isset($a[0]->Soft_Rock)) ? $a[0]->Soft_Rock : '';
                    $_soho_business = (isset($a[0]->SOHO_Business)) ? $a[0]->SOHO_Business : '';
                    $_sports_memoribilia_collector = (isset($a[0]->Sports_Memoribilia_Collector)) ? $a[0]->Sports_Memoribilia_Collector : '';
                    $_stamps = (isset($a[0]->Stamps)) ? $a[0]->Stamps : '';
                    $_sweepstakes_affinity = (isset($a[0]->Sweepstakes_Affinity)) ? $a[0]->Sweepstakes_Affinity : '';
                    $_tennis = (isset($a[0]->Tennis)) ? $a[0]->Tennis : '';
                    $_tennis_affinity = (isset($a[0]->Tennis_Affinity)) ? $a[0]->Tennis_Affinity : '';
                    $_tobacco_affinity = (isset($a[0]->Tobacco_Affinity)) ? $a[0]->Tobacco_Affinity : '';
                    $_travel_affinity = (isset($a[0]->Travel_Affinity)) ? $a[0]->Travel_Affinity : '';
                    $_travel_cruise_affinity = (isset($a[0]->Travel_Cruise_Affinity)) ? $a[0]->Travel_Cruise_Affinity : '';
                    $_travel_cruises = (isset($a[0]->Travel_Cruises)) ? $a[0]->Travel_Cruises : '';
                    $_travel_personal = (isset($a[0]->Travel_Personal)) ? $a[0]->Travel_Personal : '';
                    $_travel_rv_affinity = (isset($a[0]->Travel_RV_Affinity)) ? $a[0]->Travel_RV_Affinity : '';
                    $_travel_us_affinity = (isset($a[0]->Travel_US_Affinity)) ? $a[0]->Travel_US_Affinity : '';
                    $_truck_owner = (isset($a[0]->Truck_Owner)) ? $a[0]->Truck_Owner : '';
                    $_trucks_affinity = (isset($a[0]->Trucks_Affinity)) ? $a[0]->Trucks_Affinity : '';
                    $_tv_movies_affinity = (isset($a[0]->TV_Movies_Affinity)) ? $a[0]->TV_Movies_Affinity : '';
                    $_veteran_household = (isset($a[0]->Veteran_HH)) ? $a[0]->Veteran_HH : '';
                    $_walking = (isset($a[0]->Walking)) ? $a[0]->Walking : '';
                    $_weight_lifting = (isset($a[0]->Weight_Lifting)) ? $a[0]->Weight_Lifting : '';
                    $_wildlife_affinity = (isset($a[0]->Wildlife_Affinity)) ? $a[0]->Wildlife_Affinity : '';
                    $_womens_apparel_affinity = (isset($a[0]->Womens_Apparel_Affinity)) ? $a[0]->Womens_Apparel_Affinity : '';
                    $_womens_fashion_affinity = (isset($a[0]->Womens_Fashion_Affinity)) ? $a[0]->Womens_Fashion_Affinity : '';
                    $_woodworking = (isset($a[0]->Woodworking)) ? $a[0]->Woodworking : '';
                    $_gender = (isset($a[0]->Gender)) ? $a[0]->Gender : '';
                    $_male_aux = (isset($a[0]->Male_aux)) ? $a[0]->Male_aux : '';
                    $_political_contributor_aux = (isset($a[0]->Political_Contributor_aux)) ? $a[0]->Political_Contributor_aux : '';
                    $_political_party_aux = (isset($a[0]->Political_Party_aux)) ? $a[0]->Political_Party_aux : '';
                    $_financial_power = (isset($a[0]->Financial_Power)) ? $a[0]->Financial_Power : '';
                    $_mortgage_open_1st_intent = (isset($a[0]->Mortgage_Open1st_Intent)) ? $a[0]->Mortgage_Open1st_Intent : '';
                    $_mortgage_open_2nd_intent = (isset($a[0]->Mortgage_Open2nd_Intent)) ? $a[0]->Mortgage_Open2nd_Intent : '';
                    $_mortgage_new_intent = (isset($a[0]->Mortgage_New_Intent)) ? $a[0]->Mortgage_New_Intent : '';
                    $_mortgage_refinance_intent = (isset($a[0]->Mortgage_Refinance_Intent)) ? $a[0]->Mortgage_Refinance_Intent : '';
                    $_automotive_loan_intent = (isset($a[0]->Automotive_Loan_Intent)) ? $a[0]->Automotive_Loan_Intent : '';
                    $_bank_card_intent = (isset($a[0]->Bank_Card_Intent)) ? $a[0]->Bank_Card_Intent : '';
                    $_personal_loan_intent = (isset($a[0]->Personal_Loan_Intent)) ? $a[0]->Personal_Loan_Intent : '';
                    $_retail_card_intent = (isset($a[0]->Retail_Card_Intent)) ? $a[0]->Retail_Card_Intent : '';
                    $_student_loan_cons_intent = (isset($a[0]->Student_Loan_Cons_Intent)) ? $a[0]->Student_Loan_Cons_Intent : '';
                    $_student_loan_intent = (isset($a[0]->Student_Loan_Intent)) ? $a[0]->Student_Loan_Intent : '';
                    $_3qtr_baths = (isset($a[0]) && property_exists($a[0], '3QTR_BATHS')) ? $a[0]->{'3QTR_BATHS'} : '';
                    $_ac_type = (isset($a[0]->AC_TYPE)) ? $a[0]->AC_TYPE : '';
                    $_acres = (isset($a[0]->ACRES)) ? $a[0]->ACRES : '';
                    $_additions_square_feet = (isset($a[0]->AdditionsSquareFeet)) ? $a[0]->AdditionsSquareFeet : '';
                    $_assess_val_impr = (isset($a[0]->ASSESS_VAL_IMPR)) ? $a[0]->ASSESS_VAL_IMPR : '';
                    $_assess_val_lnd = (isset($a[0]->ASSESS_VAL_LND)) ? $a[0]->ASSESS_VAL_LND : '';
                    $_assess_val_prop = (isset($a[0]->ASSESS_VAL_PROP)) ? $a[0]->ASSESS_VAL_PROP : '';
                    $_bldg_style = (isset($a[0]->BLDG_STYLE)) ? $a[0]->BLDG_STYLE : '';
                    $_bsmt_sqft = (isset($a[0]->BSMT_SQFT)) ? $a[0]->BSMT_SQFT : '';
                    $_bsmt_type = (isset($a[0]->BSMT_TYPE)) ? $a[0]->BSMT_TYPE : '';
                    $_build_sqft_assess = (isset($a[0]->BUILD_SQFT_ASSESS)) ? $a[0]->BUILD_SQFT_ASSESS : '';
                    $_business = (isset($a[0]->BUSINESS)) ? $a[0]->BUSINESS : '';
                    $_combined_statistical_area = (isset($a[0]->CombinedStatisticalArea)) ? $a[0]->CombinedStatisticalArea : '';
                    $_metropolitan_division = (isset($a[0]->MetropolitanDivision)) ? $a[0]->MetropolitanDivision : '';
                    $_middle = (isset($a[0]->MIDDLE)) ? $a[0]->MIDDLE : '';
                    $_middle_2 = (isset($a[0]->MIDDLE2)) ? $a[0]->MIDDLE2 : '';
                    $_mkt_ip_perc = (isset($a[0]->Mkt_Ip_Perc)) ? $a[0]->Mkt_Ip_Perc : '';
                    $_mobile_home = (isset($a[0]->MOBILE_HOME)) ? $a[0]->MOBILE_HOME : '';
                    $_mrtg_due = (isset($a[0]->MRTG_DUE)) ? $a[0]->MRTG_DUE : '';
                    $_mrtg_intrate = (isset($a[0]->MRTG_INTRATE)) ? $a[0]->MRTG_INTRATE : '';
                    $_mrtg_refi = (isset($a[0]->MRTG_REFI)) ? $a[0]->MRTG_REFI : '';
                    $_mrtg_term = (isset($a[0]->MRTG_TERM)) ? $a[0]->MRTG_TERM : '';
                    $_mrtg_type = (isset($a[0]->MRTG_TYPE)) ? $a[0]->MRTG_TYPE : '';
                    $_mrtg2_amt = (isset($a[0]->MRTG2_AMT)) ? $a[0]->MRTG2_AMT : '';
                    $_mrtg2_date = (isset($a[0]->MRTG2_DATE)) ? $a[0]->MRTG2_DATE : '';
                    $_mrtg2_deed_type = (isset($a[0]->MRTG2_DEED_TYPE)) ? $a[0]->MRTG2_DEED_TYPE : '';
                    $_mrtg2_due = (isset($a[0]->MRTG2_DUE)) ? $a[0]->MRTG2_DUE : '';
                    $_mrtg2_equity = (isset($a[0]->MRTG2_EQUITY)) ? $a[0]->MRTG2_EQUITY : '';
                    $_mrtg2_intrate = (isset($a[0]->MRTG2_INTRATE)) ? $a[0]->MRTG2_INTRATE : '';
                    $_mrtg2_inttype = (isset($a[0]->MRTG2_INTTYPE)) ? $a[0]->MRTG2_INTTYPE : '';
                    $_mrtg2_refi = (isset($a[0]->MRTG2_REFI)) ? $a[0]->MRTG2_REFI : '';
                    $_mrtg2_term = (isset($a[0]->MRTG2_TERM)) ? $a[0]->MRTG2_TERM : '';
                    $_mrtg2_type = (isset($a[0]->MRTG2_TYPE)) ? $a[0]->MRTG2_TYPE : '';
                    $_mrtg3_amt = (isset($a[0]->MRTG3_AMT)) ? $a[0]->MRTG3_AMT : '';
                    $_mrtg3_date = (isset($a[0]->MRTG3_DATE)) ? $a[0]->MRTG3_DATE : '';
                    $_mrtg3_deed_type = (isset($a[0]->MRTG3_DEED_TYPE)) ? $a[0]->MRTG3_DEED_TYPE : '';
                    $_mrtg3_due = (isset($a[0]->MRTG3_DUE)) ? $a[0]->MRTG3_DUE : '';
                    $_mrtg3_equity = (isset($a[0]->MRTG3_EQUITY)) ? $a[0]->MRTG3_EQUITY : '';
                    $_mrtg3_inttype = (isset($a[0]->MRTG3_INTTYPE)) ? $a[0]->MRTG3_INTTYPE : '';
                    $_mrtg3_refi = (isset($a[0]->MRTG3_REFI)) ? $a[0]->MRTG3_REFI : '';
                    $_mrtg3_term = (isset($a[0]->MRTG3_TERM)) ? $a[0]->MRTG3_TERM : '';
                    $_mrtg3_type = (isset($a[0]->MRTG3_TYPE)) ? $a[0]->MRTG3_TYPE : '';
                    $_msa_code = (isset($a[0]->MSACode)) ? $a[0]->MSACode : '';
                    $_number_bedrooms = (isset($a[0]->NMBR_BEDROOMS)) ? $a[0]->NMBR_BEDROOMS : '';
                    $_number_bldgs = (isset($a[0]->NMBR_BLDGS)) ? $a[0]->NMBR_BLDGS : '';
                    $_number_fireplace = (isset($a[0]->NMBR_FIREPLACE)) ? $a[0]->NMBR_FIREPLACE : '';
                    $_number_park_spaces = (isset($a[0]->NMBR_PARK_SPACES)) ? $a[0]->NMBR_PARK_SPACES : '';
                    $_number_rooms = (isset($a[0]->number_rooms)) ? $a[0]->number_rooms : '';
                    $_own_biz = (isset($a[0]->OWN_BIZ)) ? $a[0]->OWN_BIZ : '';
                    $_owner_occupied = (isset($a[0]->OWNER_OCCUPIED)) ? $a[0]->OWNER_OCCUPIED : '';
                    $_ownership_relation = (isset($a[0]->OwnershipRelation)) ? $a[0]->OwnershipRelation : '';
                    $_owner_type_description = (isset($a[0]->OwnerTypeDescription)) ? $a[0]->OwnerTypeDescription : '';
                    $_estimated_value = (isset($a[0]->EstimatedValue)) ? $a[0]->EstimatedValue : '';
                    $_ext_type = (isset($a[0]->EXT_TYPE)) ? $a[0]->EXT_TYPE : '';
                    $_finish_square_feet2 = (isset($a[0]->FinishSquareFeet2)) ? $a[0]->FinishSquareFeet2 : '';
                    $_fireplace = (isset($a[0]->fireplace)) ? $a[0]->fireplace : '';
                    $_first = (isset($a[0]->FIRST)) ? $a[0]->FIRST : '';
                    $_first_2 = (isset($a[0]->FIRST2)) ? $a[0]->FIRST2 : '';
                    $_found_type = (isset($a[0]->FOUND_TYPE)) ? $a[0]->FOUND_TYPE : '';
                    $_fr_feet = (isset($a[0]->FR_FEET)) ? $a[0]->FR_FEET : '';
                    $_fuel_type = (isset($a[0]->FUEL_TYPE)) ? $a[0]->FUEL_TYPE : '';
                    $_full_baths = (isset($a[0]->FULL_BATHS)) ? $a[0]->FULL_BATHS : '';
                    $_half_baths = (isset($a[0]->HALF_BATHS)) ? $a[0]->HALF_BATHS : '';
                    $_garage_type = (isset($a[0]->GAR_TYPE)) ? $a[0]->GAR_TYPE : '';
                    $_grnd_sqft = (isset($a[0]->GRND_SQFT)) ? $a[0]->GRND_SQFT : '';
                    $_heat_type = (isset($a[0]->HEAT_TYPE)) ? $a[0]->HEAT_TYPE : '';
                    $_hmstd = (isset($a[0]->HMSTD)) ? $a[0]->HMSTD : '';
                    $_impr_appr_val = (isset($a[0]->IMPR_APPR_VAL)) ? $a[0]->IMPR_APPR_VAL : '';
                    $_imprval = (isset($a[0]->IMPRVAL)) ? $a[0]->IMPRVAL : '';
                    $_imprval_type = (isset($a[0]->IMPRVAL_TYPE)) ? $a[0]->IMPRVAL_TYPE : '';
                    $_land_appr_val = (isset($a[0]->LAND_APPR_VAL)) ? $a[0]->LAND_APPR_VAL : '';
                    $_landval = (isset($a[0]->LANDVAL)) ? $a[0]->LANDVAL : '';
                    $_landval_type = (isset($a[0]->LANDVAL_TYPE)) ? $a[0]->LANDVAL_TYPE : '';
                    $_last = (isset($a[0]->LAST)) ? $a[0]->LAST : '';
                    $_last_2 = (isset($a[0]->LAST2)) ? $a[0]->LAST2 : '';
                    $_lat = (isset($a[0]->LAT)) ? $a[0]->LAT : '';
                    $_lender_name = (isset($a[0]->LENDER_NAME)) ? $a[0]->LENDER_NAME : '';
                    $_lender2_name = (isset($a[0]->LENDER2_NAME)) ? $a[0]->LENDER2_NAME : '';
                    $_lender3_name = (isset($a[0]->LENDER3_NAME)) ? $a[0]->LENDER3_NAME : '';
                    $_loan_amt = (isset($a[0]->LOAN_AMT)) ? $a[0]->LOAN_AMT : '';
                    $_loan_date = (isset($a[0]->LOAN_DATE)) ? $a[0]->LOAN_DATE : '';
                    $_loan_to_val = (isset($a[0]->LOAN_TO_VAL)) ? $a[0]->LOAN_TO_VAL : '';
                    $_lon = (isset($a[0]->LON)) ? $a[0]->LON : '';
                    $_markval = (isset($a[0]->MARKVAL)) ? $a[0]->MARKVAL : '';
                    $_markval_type = (isset($a[0]->MARKVAL_TYPE)) ? $a[0]->MARKVAL_TYPE : '';
                    $_patio_porch = (isset($a[0]->PatioPorch)) ? $a[0]->PatioPorch : '';
                    $_patio_square_feet = (isset($a[0]->PatioSquareFeet)) ? $a[0]->PatioSquareFeet : '';
                    $_pool = (isset($a[0]->POOL)) ? $a[0]->POOL : '';
                    $_porch_square_feet = (isset($a[0]->PorchSquareFeet)) ? $a[0]->PorchSquareFeet : '';
                    $_previous_assessed_value = (isset($a[0]->PreviousAssessedValue)) ? $a[0]->PreviousAssessedValue : '';
                    $_prop_type = (isset($a[0]->PROP_TYPE)) ? $a[0]->PROP_TYPE : '';
                    $_rate_type = (isset($a[0]->RATE_TYPE)) ? $a[0]->RATE_TYPE : '';
                    $_rec_date = (isset($a[0]->REC_DATE)) ? $a[0]->REC_DATE : '';
                    $_roof_covtype = (isset($a[0]->ROOF_COVTYPE)) ? $a[0]->ROOF_COVTYPE : '';
                    $_roof_shapetype = (isset($a[0]->ROOF_SHAPETYPE)) ? $a[0]->ROOF_SHAPETYPE : '';
                    $_sale_amt = (isset($a[0]->SALE_AMT)) ? $a[0]->SALE_AMT : '';
                    $_sale_amt_pr = (isset($a[0]->SALE_AMT_PR)) ? $a[0]->SALE_AMT_PR : '';
                    $_sale_date = (isset($a[0]->SALE_DATE)) ? $a[0]->SALE_DATE : '';
                    $_sale_type_pr = (isset($a[0]->SALE_TYPE_PR)) ? $a[0]->SALE_TYPE_PR : '';
                    $_sales_type = (isset($a[0]->SALES_TYPE)) ? $a[0]->SALES_TYPE : '';
                    $_sell_name = (isset($a[0]->SELL_NAME)) ? $a[0]->SELL_NAME : '';
                    $_sewer_type = (isset($a[0]->SEWER_TYPE)) ? $a[0]->SEWER_TYPE : '';
                    $_site_quality = (isset($a[0]->SITE_QUALITY)) ? $a[0]->SITE_QUALITY : '';
                    $_std_address = (isset($a[0]->STD_ADDRESS)) ? $a[0]->STD_ADDRESS : '';
                    $_std_city = (isset($a[0]->STD_CITY)) ? $a[0]->STD_CITY : '';
                    $_std_state = (isset($a[0]->STD_STATE)) ? $a[0]->STD_STATE : '';
                    $_std_zip = (isset($a[0]->STD_ZIP)) ? $a[0]->STD_ZIP : '';
                    $_std_zip4 = (isset($a[0]->STD_ZIP4)) ? $a[0]->STD_ZIP4 : '';
                    $_stories_number = (isset($a[0]->STORIES_NMBR)) ? $a[0]->STORIES_NMBR : '';
                    $_suffix = (isset($a[0]->SUFFIX)) ? $a[0]->SUFFIX : '';
                    $_suffix_2 = (isset($a[0]->SUFFIX2)) ? $a[0]->SUFFIX2 : '';
                    $_tax_yr = (isset($a[0]->TAX_YR)) ? $a[0]->TAX_YR : '';
                    $_tax_improvement_percent = (isset($a[0]->TaxImprovementPercent)) ? $a[0]->TaxImprovementPercent : '';
                    $_title_co = (isset($a[0]->TITLE_CO)) ? $a[0]->TITLE_CO : '';
                    $_tot_baths_est = (isset($a[0]->TOT_BATHS_EST)) ? $a[0]->TOT_BATHS_EST : '';
                    $_ttl_appr_val = (isset($a[0]->TTL_APPR_VAL)) ? $a[0]->TTL_APPR_VAL : '';
                    $_ttl_bld_sqft = (isset($a[0]->TTL_BLD_SQFT)) ? $a[0]->TTL_BLD_SQFT : '';
                    $_ttl_tax = (isset($a[0]->TTL_TAX)) ? $a[0]->TTL_TAX : '';
                    $_unit_number = (isset($a[0]->UNIT_NMBR)) ? $a[0]->UNIT_NMBR : '';
                    $_vet_exempt = (isset($a[0]->VET_EXEMPT)) ? $a[0]->VET_EXEMPT : '';
                    $_water_type = (isset($a[0]->WATER_TYPE)) ? $a[0]->WATER_TYPE : '';
        
                //unregistered row
            }

            /** INSERT KE TABLE CLIENTID */
            // CleanIDResult::create([
            //     'file_id' => $file_id,
            //     'md5_id' => $md5_id,
            //     'bigdbm_id' => $_bigdbm_id,
            //     'first_name' => $_FirstName,
            //     'last_name' => $_LastName,
            //     'city' => $_City,
            //     'state' => $_State,
            //     'zip' => $_Zipcode,
            //     'address' => $_Address1,
            //     'address2' => $_Address2,
            //     'phone' => $_Phone,
            //     'phone2' => $_Phone2,
            //     'email' => $_Email,
            //     'email2' => $_Email2,
            //     'created_at' => Carbon::now(),
            //     'updated_at' => Carbon::now(),
            // ]);

            //INSERT CLEAN ID RESULT ADVANCE 1 
            CleanIdAdvance::create([
                'file_id' => $file_id,
                'md5_id' => $md5_id,
                'gender_aux' => $_gender_aux,
                'gender' => $_gender,
                'age_aux' => $_age_aux,
                'birth_year_aux' => $_birth_year_aux,
                'generation' => $_generation,
                'marital_status' => $_marital_status,
                'income_household' => $_household_income,
                'income_midpts_household' => $_median_household_income,
                'net_worth_household' => $_household_net_worth,
                'net_worth_midpt_household' => $_median_household_net_worth,
                'discretionary_income' => $_discretionary_income,
                'credit_midpts' => $_credit_score_median,
                'credit_range' => $_credit_score_range,
                'occupation_category' => $_occupation_category,
                'occupation_detail' => $_occupation_detail,
                'occupation_type' => $_occupation_type,
                'voter' => $_voter,
                'urbanicity' => $_urbanicity,
                'mobile_phone_1' => $_mobile_phone,
                'mobile_phone_2' => $_mobile_phone2,
                'mobile_phone_3' => $_mobile_phone3,
                'mobile_phone_1_dnc' => $_mobile_phone_dnc,
                'mobile_phone_2_dnc' => $_mobile_phone_dnc2,
                'mobile_phone_3_dnc' => $_mobile_phone_dnc3,
                'tax_bill_mailing_address' => $_tax_bill_mailing_address,
                'tax_bill_mailing_city' => $_tax_bill_mailing_city,
                'tax_bill_mailing_county' => $_tax_bill_mailing_county,
                'tax_bill_mailing_fips' => $_tax_bill_mailing_fips,
                'tax_bill_mailing_state' => $_tax_bill_mailing_state,
                'tax_bill_mailing_zip' => $_tax_bill_mailing_zip,
                'tax_bill_mailing_zip4' => $_tax_bill_mailing_zip4,
                'num_adults_household' => $_num_adults_household,
                'num_children_household' => $_num_children_household,
                'num_persons_household' => $_num_persons_household,
                'child_aged_0_3_household' => $_child_aged_0_3_household,
                'child_aged_4_6_household' => $_child_aged_4_6_household,
                'child_aged_7_9_household' => $_child_aged_7_9_household,
                'child_aged_10_12_household' => $_child_aged_10_12_household,
                'child_aged_13_18_household' => $_child_aged_13_18_household,
                'children_household' => $_children_household,
                'has_email' => $_has_email,
                'has_phone' => $_has_phone,
                'magazine_subscriber' => $_magazine_subscriber,
                'charity_interest' => $_charity_interest,
                'likely_charitable_donor' => $_likely_charitable_donor,
                'donor_affinity' => $_donor_affinity,
                'dwelling_type' => $_dwelling_type,
                'home_owner' => $_home_owner,
                'home_owner_ordinal' => $_home_owner_ordinal,
                'length_of_residence' => $_length_of_residence,
                'home_price' => $_home_price,
                'home_value' => $_home_value,
                'median_home_value' => $_median_home_value,
                'living_sqft' => $_living_sqft,
                'yr_built_orig' => $_year_built_original,
                'yr_built_range' => $_year_built_range,
                'lot_number' => $_lot_number,
                'legal_description' => $_legal_description,
                'land_sqft' => $_land_sqft,
                'garage_sqft' => $_garage_sqft,
                'subdivision' => $_subdivision,
                'zoning_code' => $_zoning_code,
                'cooking' => $_cooking,
                'gardening' => $_gardening,
                'music' => $_music,
                'diy' => $_diy,
                'books' => $_books,
                'travel_vacation' => $_travel_vacation,
                'health_beauty_products' => $_health_beauty_products,
                'pet_owner' => $_pet_owner,
                'photography' => $_photography,
                'fitness' => $_fitness,
                'epicurean' => $_epicurean,
                'cbsa' => $_cbsa,
                'census_block' => $_census_block,
                'census_block_group' => $_census_block_group,
                'census_tract' => $_census_tract,
                
                'aerobics' => $_aerobics,
                'african_american_affinity' => $_african_american_affinity ,
                'amex_card' => $_amex_card,
                'antiques' => $_antiques,
                'apparel_accessory_affinity' => $_apparel_accessory_affinity,
                'apparel_affinity' => $_apparel_affinity,
                'arts_and_crafts' => $_arts_and_crafts,
                'asian_affinity' => $_asian_affinity,
                'auto_affinity' => $_auto_affinity,
                'auto_racing_affinity' => $_auto_racing_affinity,
                'aviation_affinity' => $_aviation_affinity,
                'bank_card' => $_bank_card,
                'bargain_hunter_affinity' => $_bargain_hunter_affinity,
                'baseball' => $_baseball,
                'baseball_affinity' => $_baseball_affinity,
                'basketball' => $_basketball,
                'basketball_affinity' => $_basketball_affinity,
                'beauty_affinity' => $_beauty_affinity,
                'bible_affinity' => $_bible_affinity,
                'bird_watching' => $_bird_watching,
                'birds_affinity' => $_birds_affinity,
                'blue_collar' => $_blue_collar,
                'boating_sailing' => $_boating_sailing,
                'boating_sailing_affinity' => $_boating_sailing_affinity,
                'business_affinity' => $_business_affinity,
                'camping_hiking' => $_camping_hiking,
                'camping_hiking_climbing_affinity' => $_camping_hiking_climbing_affinity,
                'cars_interest' => $_cars_interest,
                'cat_owner' => $_cat_owner,
                'catalog_affinity' => $_catalog_affinity,
                'cigars' => $_cigars,
                'classical_music' => $_classical_music,
                'coins' => $_coins,
                'collectibles_affinity' => $_collectibles_affinity,
                'college_affinity' => $_college_affinity,
                'computers_affinity' => $_computers_affinity,
                'continuity_program_affinity' => $_continuity_program_affinity,
                'cooking_affinity' => $_cooking_affinity,
                'cosmetics' => $_cosmetics,
                'country_music' => $_country_music,
                'crafts_affinity' => $_crafts_affinity,
                'credit_card' => $_credit_card,
                'credit_repair_affinity' => $_credit_repair_affinity,
                'crochet_affinity' => $_crochet_affinity,
            ]);
            //INSERT CLEAN ID RESULT ADVANCE 1

            //INSERT CLEAN ID RESULT ADVANCE 2
            CleanIdAdvance2::create([
                'file_id' => $file_id,
                'md5_id' => $md5_id,
                'diet_affinity' => $_diet_affinity,
                'dieting' => $_dieting,
                'do_it_yourself_affinity' => $_do_it_yourself_affinity,
                'dog_owner' => $_dog_owner,
                'doll_collector' => $_doll_collector,
                'education' => $_education,
                'education_ordinal' => $_education_ordinal,
                'education_seekers_affinity' => $_education_seekers_affinity,
                'ego_affinity' => $_ego_affinity,
                'entertainment_interest' => $_entertainment_interest,
                'figurines_collector' => $_figurines_collector,
                'fine_arts_collector' => $_fine_arts_collector,
                'fishing' => $_fishing,
                'fishing_affinity' => $_fishing_affinity,
                'fitness_affinity' => $_fitness_affinity,
                'football' => $_football,
                'football_affinity' => $_football_affinity,
                'gambling' => $_gambling,
                'gambling_affinity' => $_gambling_affinity,
                'games_affinity' => $_games_affinity,
                'gardening_affinity' => $_gardening_affinity,
                'generation_ordinal' => $_generation_ordinal,
                'golf' => $_golf,
                'golf_affinity' => $_golf_affinity,
                'gourmet_affinity' => $_gourmet_affinity,
                'grandparents_affinity' => $_grandparents_affinity,
                'health_affinity' => $_health_affinity,
                'healthy_living' => $_healthy_living,
                'healthy_living_interest' => $_healthy_living_interest,
                'high_tech_affinity' => $_high_tech_affinity,
                'hispanic_affinity' => $_hispanic_affinity,
                'hockey' => $_hockey,
                'hockey_affinity' => $_hockey_affinity,
                'home_decor' => $_home_decor,
                'home_improvement_interest' => $_home_improvement_interest,
                'home_office_affinity' => $_home_office_affinity,
                'home_study' => $_home_study,
                'hunting' => $_hunting,
                'hunting_affinity' => $_hunting_affinity,
                'jazz' => $_jazz,
                'kids_apparel_affinity' => $_kids_apparel_affinity,
                'knit_affinity' => $_knit_affinity,
                'knitting_quilting_sewing' => $_knitting_quilting_sewing,
                'luxury_life' => $_luxury_life,
                'married' => $_married,
                'median_income' => $_median_income,
                'mens_apparel_affinity' => $_mens_apparel_affinity,
                'mens_fashion_affinity' => $_mens_fashion_affinity,
                'mortgage_age' => $_mortgage_age,
                'mortgage_amount' => $_mortgage_amount,
                'mortgage_loan_type' => $_mortgage_loan_type,
                'mortgage_refi_age' => $_mortgage_refi_age,
                'mortgage_refi_amount' => $_mortgage_refi_amount,
                'mortgage_refi_type' => $_mortgage_refi_type,
                'motor_racing' => $_motor_racing,
                'motorcycles' => $_motorcycles,
                'motorcycles_affinity' => $_motorcycles_affinity,
                'movies' => $_movies,
                'nascar' => $_nascar,
                'needlepoint_affinity' => $_needlepoint_affinity,
                'new_credit_offered_household' => $_new_credit_offered_household,
                'num_credit_lines_household' => $_num_credit_lines_household,
                'num_generations_household' => $_num_generations_household,
                'outdoors' => $_outdoors,
                'outdoors_affinity' => $_outdoors_affinity,
                'owns_investments' => $_owns_investments,
                'owns_mutual_funds' => $_owns_mutual_funds,
                'owns_stocks_and_bonds' => $_owns_stocks_and_bonds,
                'owns_swimming_pool' => $_owns_swimming_pool,
                'personal_finance_affinity' => $_personal_finance_affinity,
                'personality' => $_personality,
                'plates_collector' => $_plates_collector,
                'pop_density' => $_pop_density,
                'premium_amex_card' => $_premium_amex_card,
                'premium_card' => $_premium_card,
                'premium_income_household' => $_premium_income_household,
                'premium_income_midpt_household' => $_premium_income_midpt_household,
                'quilt_affinity' => $_quilt_affinity,
                'religious_music' => $_religious_music,
                'rhythm_and_blues' => $_rhythm_and_blues,
                'rock_music' => $_rock_music,
                'running' => $_running,
                'rv' => $_rv,
                'scuba' => $_scuba,
                'self_improvement' => $_self_improvement,
                'sewing_affinity' => $_sewing_affinity,
                'single_family_dwelling' => $_single_family_dwelling,
                'snow_skiing' => $_snow_skiing,
                'snow_skiing_affinity' => $_snow_skiing_affinity,
                'soccer' => $_soccer,
                'soccer_affinity' => $_soccer_affinity,
                'soft_rock' => $_soft_rock,
                'soho_business' => $_soho_business,
                'sports_memoribilia_collector' => $_sports_memoribilia_collector,
                'stamps' => $_stamps,
                'sweepstakes_affinity' => $_sweepstakes_affinity,
                'tennis' => $_tennis,
                'tennis_affinity' => $_tennis_affinity,
                'tobacco_affinity' => $_tobacco_affinity,
                'travel_affinity' => $_travel_affinity,
                'travel_cruise_affinity' => $_travel_cruise_affinity,
                'travel_cruises' => $_travel_cruises,
                'travel_personal' => $_travel_personal,
                'travel_rv_affinity' => $_travel_rv_affinity,
                'travel_us_affinity' => $_travel_us_affinity,
                'truck_owner' => $_truck_owner,
                'trucks_affinity' => $_trucks_affinity,
                'tv_movies_affinity' => $_tv_movies_affinity,
                'veteran_household' => $_veteran_household,
                'walking' => $_walking,
                'weight_lifting' => $_weight_lifting,
                'wildlife_affinity' => $_wildlife_affinity,
                'womens_apparel_affinity' => $_womens_apparel_affinity,
                'womens_fashion_affinity' => $_womens_fashion_affinity,
                'woodworking' => $_woodworking,
                'male_aux' => $_male_aux,
                'political_contributor_aux' => $_political_contributor_aux,
                'political_party_aux' => $_political_party_aux,
                'financial_power' => $_financial_power,
                'mortgage_open_1st_intent' => $_mortgage_open_1st_intent,
                'mortgage_open_2nd_intent' => $_mortgage_open_2nd_intent,
                'mortgage_new_intent' => $_mortgage_new_intent,
                'mortgage_refinance_intent' => $_mortgage_refinance_intent,
                'automotive_loan_intent' => $_automotive_loan_intent,
                'bank_card_intent' => $_bank_card_intent,
                'personal_loan_intent' => $_personal_loan_intent,
                'retail_card_intent' => $_retail_card_intent,
                'student_loan_cons_intent' => $_student_loan_cons_intent,
                'student_loan_intent' => $_student_loan_intent,
                'qtr3_baths' => $_3qtr_baths,
                'ac_type' => $_ac_type,
                'acres' => $_acres,
            ]);
            //INSERT CLEAN ID RESULT ADVANCE 2

            //INSERT CLEAN ID RESULT ADVANCE 3
            CleanIdAdvance3::create([
                'file_id' => $file_id,
                'md5_id' => $md5_id,
                'additions_square_feet' => $_additions_square_feet,
                'assess_val_impr' => $_assess_val_impr,
                'assess_val_lnd' => $_assess_val_lnd,
                'assess_val_prop' => $_assess_val_prop,
                'bldg_style' => $_bldg_style,
                'bsmt_sqft' => $_bsmt_sqft,
                'bsmt_type' => $_bsmt_type,
                'build_sqft_assess' => $_build_sqft_assess,
                'business' => $_business,
                'combined_statistical_area' => $_combined_statistical_area,
                'metropolitan_division' => $_metropolitan_division,
                'middle' => $_middle,
                'middle_2' => $_middle_2,
                'mkt_ip_perc' => $_mkt_ip_perc,
                'mobile_home' => $_mobile_home,

                'mrtg_due' => $_mrtg_due,
                'mrtg_intrate' => $_mrtg_intrate,
                'mrtg_refi' => $_mrtg_refi,
                'mrtg_term' => $_mrtg_term,
                'mrtg_type' => $_mrtg_type,

                'mrtg2_amt' => $_mrtg2_amt,
                'mrtg2_date' => $_mrtg2_date,
                'mrtg2_deed_type' => $_mrtg2_deed_type,
                'mrtg2_due' => $_mrtg2_due,
                'mrtg2_equity' => $_mrtg2_equity,
                'mrtg2_intrate' => $_mrtg2_intrate,
                'mrtg2_inttype' => $_mrtg2_inttype,
                'mrtg2_refi' => $_mrtg2_refi,
                'mrtg2_term' => $_mrtg2_term,
                'mrtg2_type' => $_mrtg2_type,

                'mrtg3_amt' => $_mrtg3_amt,
                'mrtg3_date' => $_mrtg3_date,
                'mrtg3_deed_type' => $_mrtg3_deed_type,
                'mrtg3_due' => $_mrtg3_due,
                'mrtg3_equity' => $_mrtg3_equity,
                'mrtg3_inttype' => $_mrtg3_inttype,
                'mrtg3_refi' => $_mrtg3_refi,
                'mrtg3_term' => $_mrtg3_term,
                'mrtg3_type' => $_mrtg3_type,

                'msa_code' => $_msa_code,
                'number_bedrooms' => $_number_bedrooms,
                'number_bldgs' => $_number_bldgs,
                'number_fireplace' => $_number_fireplace,
                'number_park_spaces' => $_number_park_spaces,
                'number_rooms' => $_number_rooms,
                'own_biz' => $_own_biz,
                'owner_occupied' => $_owner_occupied,
                'ownership_relation' => $_ownership_relation,
                'owner_type_description' => $_owner_type_description,
                'estimated_value' => $_estimated_value,
                'ext_type' => $_ext_type,
                'finish_square_feet2' => $_finish_square_feet2,
                'fireplace' => $_fireplace,
                'first' => $_first,
                'first_2' => $_first_2,
                'found_type' => $_found_type,
                'fr_feet' => $_fr_feet,
                'fuel_type' => $_fuel_type,
                'full_baths' => $_full_baths,
                'half_baths' => $_half_baths,
                'garage_type' => $_garage_type,
                'grnd_sqft' => $_grnd_sqft,
                'heat_type' => $_heat_type,
                'hmstd' => $_hmstd,
                'impr_appr_val' => $_impr_appr_val,
                'imprval' => $_imprval,
                'imprval_type' => $_imprval_type,
                'land_appr_val' => $_land_appr_val,
                'landval' => $_landval,
                'landval_type' => $_landval_type,
                'last' => $_last,
                'last_2' => $_last_2,
                'lat' => $_lat,
                'lender_name' => $_lender_name,
                'lender2_name' => $_lender2_name,
                'lender3_name' => $_lender3_name,
                'loan_amt' => $_loan_amt,
                'loan_date' => $_loan_date,
                'loan_to_val' => $_loan_to_val,
                'lon' => $_lon,
                'markval' => $_markval,
                'markval_type' => $_markval_type,
                'patio_porch' => $_patio_porch,
                'patio_square_feet' => $_patio_square_feet,
                'pool' => $_pool,
                'porch_square_feet' => $_porch_square_feet,
                'previous_assessed_value' => $_previous_assessed_value,
                'prop_type' => $_prop_type,
                'rate_type' => $_rate_type,
                'rec_date' => $_rec_date,
                'roof_covtype' => $_roof_covtype,
                'roof_shapetype' => $_roof_shapetype,
                'sale_amt' => $_sale_amt,
                'sale_amt_pr' => $_sale_amt_pr,
                'sale_date' => $_sale_date,
                'sale_type_pr' => $_sale_type_pr,
                'sales_type' => $_sales_type,
                'sell_name' => $_sell_name,
                'sewer_type' => $_sewer_type,
                'site_quality' => $_site_quality,
                'std_address' => $_std_address,
                'std_city' => $_std_city,
                'std_state' => $_std_state,
                'std_zip' => $_std_zip,
                'std_zip4' => $_std_zip4,
                'stories_number' => $_stories_number,
                'suffix' => $_suffix,
                'suffix_2' => $_suffix_2,
                'tax_yr' => $_tax_yr,
                'tax_improvement_percent' => $_tax_improvement_percent,
                'title_co' => $_title_co,
                'tot_baths_est' => $_tot_baths_est,
                'ttl_appr_val' => $_ttl_appr_val,
                'ttl_bld_sqft' => $_ttl_bld_sqft,
                'ttl_tax' => $_ttl_tax,
                'unit_number' => $_unit_number,
                'vet_exempt' => $_vet_exempt,
                'water_type' => $_water_type,
            ]);
            //INSERT CLEAN ID RESULT ADVANCE 3

            //INSERT PERSON ADVANCE 1 
            PersonAdvance::create([
                'person_id' => $newPersonID,
                'gender_aux' => $_gender_aux,
                'gender' => $_gender,
                'age_aux' => $_age_aux,
                'birth_year_aux' => $_birth_year_aux,
                'generation' => $_generation,
                'marital_status' => $_marital_status,
                'income_household' => $_household_income,
                'income_midpts_household' => $_median_household_income,
                'net_worth_household' => $_household_net_worth,
                'net_worth_midpt_household' => $_median_household_net_worth,
                'discretionary_income' => $_discretionary_income,
                'credit_midpts' => $_credit_score_median,
                'credit_range' => $_credit_score_range,
                'occupation_category' => $_occupation_category,
                'occupation_detail' => $_occupation_detail,
                'occupation_type' => $_occupation_type,
                'voter' => $_voter,
                'urbanicity' => $_urbanicity,
                'mobile_phone_1' => $_mobile_phone,
                'mobile_phone_2' => $_mobile_phone2,
                'mobile_phone_3' => $_mobile_phone3,
                'mobile_phone_1_dnc' => $_mobile_phone_dnc,
                'mobile_phone_2_dnc' => $_mobile_phone_dnc2,
                'mobile_phone_3_dnc' => $_mobile_phone_dnc3,
                'tax_bill_mailing_address' => $_tax_bill_mailing_address,
                'tax_bill_mailing_city' => $_tax_bill_mailing_city,
                'tax_bill_mailing_county' => $_tax_bill_mailing_county,
                'tax_bill_mailing_fips' => $_tax_bill_mailing_fips,
                'tax_bill_mailing_state' => $_tax_bill_mailing_state,
                'tax_bill_mailing_zip' => $_tax_bill_mailing_zip,
                'tax_bill_mailing_zip4' => $_tax_bill_mailing_zip4,
                'num_adults_household' => $_num_adults_household,
                'num_children_household' => $_num_children_household,
                'num_persons_household' => $_num_persons_household,
                'child_aged_0_3_household' => $_child_aged_0_3_household,
                'child_aged_4_6_household' => $_child_aged_4_6_household,
                'child_aged_7_9_household' => $_child_aged_7_9_household,
                'child_aged_10_12_household' => $_child_aged_10_12_household,
                'child_aged_13_18_household' => $_child_aged_13_18_household,
                'children_household' => $_children_household,
                'has_email' => $_has_email,
                'has_phone' => $_has_phone,
                'magazine_subscriber' => $_magazine_subscriber,
                'charity_interest' => $_charity_interest,
                'likely_charitable_donor' => $_likely_charitable_donor,
                'donor_affinity' => $_donor_affinity,
                'dwelling_type' => $_dwelling_type,
                'home_owner' => $_home_owner,
                'home_owner_ordinal' => $_home_owner_ordinal,
                'length_of_residence' => $_length_of_residence,
                'home_price' => $_home_price,
                'home_value' => $_home_value,
                'median_home_value' => $_median_home_value,
                'living_sqft' => $_living_sqft,
                'yr_built_orig' => $_year_built_original,
                'yr_built_range' => $_year_built_range,
                'lot_number' => $_lot_number,
                'legal_description' => $_legal_description,
                'land_sqft' => $_land_sqft,
                'garage_sqft' => $_garage_sqft,
                'subdivision' => $_subdivision,
                'zoning_code' => $_zoning_code,
                'cooking' => $_cooking,
                'gardening' => $_gardening,
                'music' => $_music,
                'diy' => $_diy,
                'books' => $_books,
                'travel_vacation' => $_travel_vacation,
                'health_beauty_products' => $_health_beauty_products,
                'pet_owner' => $_pet_owner,
                'photography' => $_photography,
                'fitness' => $_fitness,
                'epicurean' => $_epicurean,
                'cbsa' => $_cbsa,
                'census_block' => $_census_block,
                'census_block_group' => $_census_block_group,
                'census_tract' => $_census_tract,
                
                'aerobics' => $_aerobics,
                'african_american_affinity' => $_african_american_affinity ,
                'amex_card' => $_amex_card,
                'antiques' => $_antiques,
                'apparel_accessory_affinity' => $_apparel_accessory_affinity,
                'apparel_affinity' => $_apparel_affinity,
                'arts_and_crafts' => $_arts_and_crafts,
                'asian_affinity' => $_asian_affinity,
                'auto_affinity' => $_auto_affinity,
                'auto_racing_affinity' => $_auto_racing_affinity,
                'aviation_affinity' => $_aviation_affinity,
                'bank_card' => $_bank_card,
                'bargain_hunter_affinity' => $_bargain_hunter_affinity,
                'baseball' => $_baseball,
                'baseball_affinity' => $_baseball_affinity,
                'basketball' => $_basketball,
                'basketball_affinity' => $_basketball_affinity,
                'beauty_affinity' => $_beauty_affinity,
                'bible_affinity' => $_bible_affinity,
                'bird_watching' => $_bird_watching,
                'birds_affinity' => $_birds_affinity,
                'blue_collar' => $_blue_collar,
                'boating_sailing' => $_boating_sailing,
                'boating_sailing_affinity' => $_boating_sailing_affinity,
                'business_affinity' => $_business_affinity,
                'camping_hiking' => $_camping_hiking,
                'camping_hiking_climbing_affinity' => $_camping_hiking_climbing_affinity,
                'cars_interest' => $_cars_interest,
                'cat_owner' => $_cat_owner,
                'catalog_affinity' => $_catalog_affinity,
                'cigars' => $_cigars,
                'classical_music' => $_classical_music,
                'coins' => $_coins,
                'collectibles_affinity' => $_collectibles_affinity,
                'college_affinity' => $_college_affinity,
                'computers_affinity' => $_computers_affinity,
                'continuity_program_affinity' => $_continuity_program_affinity,
                'cooking_affinity' => $_cooking_affinity,
                'cosmetics' => $_cosmetics,
                'country_music' => $_country_music,
                'crafts_affinity' => $_crafts_affinity,
                'credit_card' => $_credit_card,
                'credit_repair_affinity' => $_credit_repair_affinity,
                'crochet_affinity' => $_crochet_affinity,
            ]);
            //INSERT PERSON ADVANCE 1

            //INSERT PERSON ADVANCE 2
            PersonAdvance2::create([
                'person_id' => $newPersonID,
                'diet_affinity' => $_diet_affinity,
                'dieting' => $_dieting,
                'do_it_yourself_affinity' => $_do_it_yourself_affinity,
                'dog_owner' => $_dog_owner,
                'doll_collector' => $_doll_collector,
                'education' => $_education,
                'education_ordinal' => $_education_ordinal,
                'education_seekers_affinity' => $_education_seekers_affinity,
                'ego_affinity' => $_ego_affinity,
                'entertainment_interest' => $_entertainment_interest,
                'figurines_collector' => $_figurines_collector,
                'fine_arts_collector' => $_fine_arts_collector,
                'fishing' => $_fishing,
                'fishing_affinity' => $_fishing_affinity,
                'fitness_affinity' => $_fitness_affinity,
                'football' => $_football,
                'football_affinity' => $_football_affinity,
                'gambling' => $_gambling,
                'gambling_affinity' => $_gambling_affinity,
                'games_affinity' => $_games_affinity,
                'gardening_affinity' => $_gardening_affinity,
                'generation_ordinal' => $_generation_ordinal,
                'golf' => $_golf,
                'golf_affinity' => $_golf_affinity,
                'gourmet_affinity' => $_gourmet_affinity,
                'grandparents_affinity' => $_grandparents_affinity,
                'health_affinity' => $_health_affinity,
                'healthy_living' => $_healthy_living,
                'healthy_living_interest' => $_healthy_living_interest,
                'high_tech_affinity' => $_high_tech_affinity,
                'hispanic_affinity' => $_hispanic_affinity,
                'hockey' => $_hockey,
                'hockey_affinity' => $_hockey_affinity,
                'home_decor' => $_home_decor,
                'home_improvement_interest' => $_home_improvement_interest,
                'home_office_affinity' => $_home_office_affinity,
                'home_study' => $_home_study,
                'hunting' => $_hunting,
                'hunting_affinity' => $_hunting_affinity,
                'jazz' => $_jazz,
                'kids_apparel_affinity' => $_kids_apparel_affinity,
                'knit_affinity' => $_knit_affinity,
                'knitting_quilting_sewing' => $_knitting_quilting_sewing,
                'luxury_life' => $_luxury_life,
                'married' => $_married,
                'median_income' => $_median_income,
                'mens_apparel_affinity' => $_mens_apparel_affinity,
                'mens_fashion_affinity' => $_mens_fashion_affinity,
                'mortgage_age' => $_mortgage_age,
                'mortgage_amount' => $_mortgage_amount,
                'mortgage_loan_type' => $_mortgage_loan_type,
                'mortgage_refi_age' => $_mortgage_refi_age,
                'mortgage_refi_amount' => $_mortgage_refi_amount,
                'mortgage_refi_type' => $_mortgage_refi_type,
                'motor_racing' => $_motor_racing,
                'motorcycles' => $_motorcycles,
                'motorcycles_affinity' => $_motorcycles_affinity,
                'movies' => $_movies,
                'nascar' => $_nascar,
                'needlepoint_affinity' => $_needlepoint_affinity,
                'new_credit_offered_household' => $_new_credit_offered_household,
                'num_credit_lines_household' => $_num_credit_lines_household,
                'num_generations_household' => $_num_generations_household,
                'outdoors' => $_outdoors,
                'outdoors_affinity' => $_outdoors_affinity,
                'owns_investments' => $_owns_investments,
                'owns_mutual_funds' => $_owns_mutual_funds,
                'owns_stocks_and_bonds' => $_owns_stocks_and_bonds,
                'owns_swimming_pool' => $_owns_swimming_pool,
                'personal_finance_affinity' => $_personal_finance_affinity,
                'personality' => $_personality,
                'plates_collector' => $_plates_collector,
                'pop_density' => $_pop_density,
                'premium_amex_card' => $_premium_amex_card,
                'premium_card' => $_premium_card,
                'premium_income_household' => $_premium_income_household,
                'premium_income_midpt_household' => $_premium_income_midpt_household,
                'quilt_affinity' => $_quilt_affinity,
                'religious_music' => $_religious_music,
                'rhythm_and_blues' => $_rhythm_and_blues,
                'rock_music' => $_rock_music,
                'running' => $_running,
                'rv' => $_rv,
                'scuba' => $_scuba,
                'self_improvement' => $_self_improvement,
                'sewing_affinity' => $_sewing_affinity,
                'single_family_dwelling' => $_single_family_dwelling,
                'snow_skiing' => $_snow_skiing,
                'snow_skiing_affinity' => $_snow_skiing_affinity,
                'soccer' => $_soccer,
                'soccer_affinity' => $_soccer_affinity,
                'soft_rock' => $_soft_rock,
                'soho_business' => $_soho_business,
                'sports_memoribilia_collector' => $_sports_memoribilia_collector,
                'stamps' => $_stamps,
                'sweepstakes_affinity' => $_sweepstakes_affinity,
                'tennis' => $_tennis,
                'tennis_affinity' => $_tennis_affinity,
                'tobacco_affinity' => $_tobacco_affinity,
                'travel_affinity' => $_travel_affinity,
                'travel_cruise_affinity' => $_travel_cruise_affinity,
                'travel_cruises' => $_travel_cruises,
                'travel_personal' => $_travel_personal,
                'travel_rv_affinity' => $_travel_rv_affinity,
                'travel_us_affinity' => $_travel_us_affinity,
                'truck_owner' => $_truck_owner,
                'trucks_affinity' => $_trucks_affinity,
                'tv_movies_affinity' => $_tv_movies_affinity,
                'veteran_household' => $_veteran_household,
                'walking' => $_walking,
                'weight_lifting' => $_weight_lifting,
                'wildlife_affinity' => $_wildlife_affinity,
                'womens_apparel_affinity' => $_womens_apparel_affinity,
                'womens_fashion_affinity' => $_womens_fashion_affinity,
                'woodworking' => $_woodworking,
                'male_aux' => $_male_aux,
                'political_contributor_aux' => $_political_contributor_aux,
                'political_party_aux' => $_political_party_aux,
                'financial_power' => $_financial_power,
                'mortgage_open_1st_intent' => $_mortgage_open_1st_intent,
                'mortgage_open_2nd_intent' => $_mortgage_open_2nd_intent,
                'mortgage_new_intent' => $_mortgage_new_intent,
                'mortgage_refinance_intent' => $_mortgage_refinance_intent,
                'automotive_loan_intent' => $_automotive_loan_intent,
                'bank_card_intent' => $_bank_card_intent,
                'personal_loan_intent' => $_personal_loan_intent,
                'retail_card_intent' => $_retail_card_intent,
                'student_loan_cons_intent' => $_student_loan_cons_intent,
                'student_loan_intent' => $_student_loan_intent,
                'qtr3_baths' => $_3qtr_baths,
                'ac_type' => $_ac_type,
                'acres' => $_acres,
            ]);
            //INSERT PERSON ADVANCE 2

            //INSERT PERSON ADVANCE 3
            PersonAdvance3::create([
                'person_id' => $newPersonID,
                'additions_square_feet' => $_additions_square_feet,
                'assess_val_impr' => $_assess_val_impr,
                'assess_val_lnd' => $_assess_val_lnd,
                'assess_val_prop' => $_assess_val_prop,
                'bldg_style' => $_bldg_style,
                'bsmt_sqft' => $_bsmt_sqft,
                'bsmt_type' => $_bsmt_type,
                'build_sqft_assess' => $_build_sqft_assess,
                'business' => $_business,
                'combined_statistical_area' => $_combined_statistical_area,
                'metropolitan_division' => $_metropolitan_division,
                'middle' => $_middle,
                'middle_2' => $_middle_2,
                'mkt_ip_perc' => $_mkt_ip_perc,
                'mobile_home' => $_mobile_home,

                'mrtg_due' => $_mrtg_due,
                'mrtg_intrate' => $_mrtg_intrate,
                'mrtg_refi' => $_mrtg_refi,
                'mrtg_term' => $_mrtg_term,
                'mrtg_type' => $_mrtg_type,

                'mrtg2_amt' => $_mrtg2_amt,
                'mrtg2_date' => $_mrtg2_date,
                'mrtg2_deed_type' => $_mrtg2_deed_type,
                'mrtg2_due' => $_mrtg2_due,
                'mrtg2_equity' => $_mrtg2_equity,
                'mrtg2_intrate' => $_mrtg2_intrate,
                'mrtg2_inttype' => $_mrtg2_inttype,
                'mrtg2_refi' => $_mrtg2_refi,
                'mrtg2_term' => $_mrtg2_term,
                'mrtg2_type' => $_mrtg2_type,

                'mrtg3_amt' => $_mrtg3_amt,
                'mrtg3_date' => $_mrtg3_date,
                'mrtg3_deed_type' => $_mrtg3_deed_type,
                'mrtg3_due' => $_mrtg3_due,
                'mrtg3_equity' => $_mrtg3_equity,
                'mrtg3_inttype' => $_mrtg3_inttype,
                'mrtg3_refi' => $_mrtg3_refi,
                'mrtg3_term' => $_mrtg3_term,
                'mrtg3_type' => $_mrtg3_type,

                'msa_code' => $_msa_code,
                'number_bedrooms' => $_number_bedrooms,
                'number_bldgs' => $_number_bldgs,
                'number_fireplace' => $_number_fireplace,
                'number_park_spaces' => $_number_park_spaces,
                'number_rooms' => $_number_rooms,
                'own_biz' => $_own_biz,
                'owner_occupied' => $_owner_occupied,
                'ownership_relation' => $_ownership_relation,
                'owner_type_description' => $_owner_type_description,
                'estimated_value' => $_estimated_value,
                'ext_type' => $_ext_type,
                'finish_square_feet2' => $_finish_square_feet2,
                'fireplace' => $_fireplace,
                'first' => $_first,
                'first_2' => $_first_2,
                'found_type' => $_found_type,
                'fr_feet' => $_fr_feet,
                'fuel_type' => $_fuel_type,
                'full_baths' => $_full_baths,
                'half_baths' => $_half_baths,
                'garage_type' => $_garage_type,
                'grnd_sqft' => $_grnd_sqft,
                'heat_type' => $_heat_type,
                'hmstd' => $_hmstd,
                'impr_appr_val' => $_impr_appr_val,
                'imprval' => $_imprval,
                'imprval_type' => $_imprval_type,
                'land_appr_val' => $_land_appr_val,
                'landval' => $_landval,
                'landval_type' => $_landval_type,
                'last' => $_last,
                'last_2' => $_last_2,
                'lat' => $_lat,
                'lender_name' => $_lender_name,
                'lender2_name' => $_lender2_name,
                'lender3_name' => $_lender3_name,
                'loan_amt' => $_loan_amt,
                'loan_date' => $_loan_date,
                'loan_to_val' => $_loan_to_val,
                'lon' => $_lon,
                'markval' => $_markval,
                'markval_type' => $_markval_type,
                'patio_porch' => $_patio_porch,
                'patio_square_feet' => $_patio_square_feet,
                'pool' => $_pool,
                'porch_square_feet' => $_porch_square_feet,
                'previous_assessed_value' => $_previous_assessed_value,
                'prop_type' => $_prop_type,
                'rate_type' => $_rate_type,
                'rec_date' => $_rec_date,
                'roof_covtype' => $_roof_covtype,
                'roof_shapetype' => $_roof_shapetype,
                'sale_amt' => $_sale_amt,
                'sale_amt_pr' => $_sale_amt_pr,
                'sale_date' => $_sale_date,
                'sale_type_pr' => $_sale_type_pr,
                'sales_type' => $_sales_type,
                'sell_name' => $_sell_name,
                'sewer_type' => $_sewer_type,
                'site_quality' => $_site_quality,
                'std_address' => $_std_address,
                'std_city' => $_std_city,
                'std_state' => $_std_state,
                'std_zip' => $_std_zip,
                'std_zip4' => $_std_zip4,
                'stories_number' => $_stories_number,
                'suffix' => $_suffix,
                'suffix_2' => $_suffix_2,
                'tax_yr' => $_tax_yr,
                'tax_improvement_percent' => $_tax_improvement_percent,
                'title_co' => $_title_co,
                'tot_baths_est' => $_tot_baths_est,
                'ttl_appr_val' => $_ttl_appr_val,
                'ttl_bld_sqft' => $_ttl_bld_sqft,
                'ttl_tax' => $_ttl_tax,
                'unit_number' => $_unit_number,
                'vet_exempt' => $_vet_exempt,
                'water_type' => $_water_type,
            ]);
            //INSERT PERSON ADVANCE 3

            Log::info("BigBDM Advance Have result");
            $result = 'success';
            $message = 'Advance information Found From BIGDBM';
            $sts = 'found';
            /** IF BIG BDM MD5 HAVE RESULT */
        }
        else 
        {
            Log::info("BigBDM DATA ADVANCE NOT FOUND");
            $result = 'error';
            $message = 'Advance information Not Found From BIGDBM';
            $sts = 'not_found';
        }

        return ['res' => $result, 'msg' => $message, 'sts' => $sts];
    }

    /**
     * function ini merupakan bagian kecil dari function dataNotExistOnDBBIG.
     * function ini merupakan bagian kecil dari function dataExistOnDB.
     * dijalankan ketika data person email sudah ada, namun sudah duration nya sudah lewat 7 hari
     * dijalankan ketika data person email sudah ada, namun data detail b2b nya belom ada
     */
    public function process_BIGDBM_b2b_exist($file_id, $md5_id, $newPersonID, $md5param = "") 
    {        
        // info(__FUNCTION__, ['file_id' => $file_id, 'md5_id' => $md5_id, 'newPersonID' => $newPersonID, 'md5Param' => $md5param]);
        date_default_timezone_set('America/Chicago');

        // b2b field 
        $_employee_id = '';
        $_company_name = '';
        $_company_website = '';
        $_phone_1_employee = '';
        $_phone_1_company = '';
        $_num_employees = '';
        $_sales_volume = '';
        $_year_founded = '';
        $_job_title = '';
        $_level = '';
        $_job_function = '';
        $_headquarters_branch = '';
        $_naics_code = '';
        $_last_seen_date = '';
        $_linked_in = '';
        // b2b field 
        
        // $trackBigBDM = "BDMB2B";

        Log::info("Start Check BigBDM B2B");
        $startBigbdmMD5Time = microtime(true);

        $bigDBM_B2B = $this->bigDBM->GetDataB2bByMD5($file_id, $md5param);
        // $bigDBM_B2B = $this->bigDBM_B2B($md5param,$leadspeek_api_id,$leadspeektype);

        if (is_object($bigDBM_B2B) && isset($bigDBM_B2B->isError) && !empty($bigDBM_B2B->isError)) 
        {
            CleanIDResult::where('file_id', $file_id)->where('md5_id', $md5_id)->delete();
            CleanIdAdvance::where('file_id', $file_id)->where('md5_id', $md5_id)->delete();
            CleanIdAdvance2::where('file_id', $file_id)->where('md5_id', $md5_id)->delete();
            CleanIdAdvance3::where('file_id', $file_id)->where('md5_id', $md5_id)->delete();
            throw new \Exception("API Error: " . ($bigDBM_B2B->message ?? 'Unknown error'));
        }

        $endBigbdmMD5Time = microtime(true);

        // convert epochtime to date format ('Y-m-d H:i:s')
        $startBigbdmMD5Date = Carbon::createFromTimestamp($startBigbdmMD5Time)->setTimezone('America/Chicago')->format('Y-m-d H:i:s');
        $endBigbdmMD5Date = Carbon::createFromTimestamp($endBigbdmMD5Time)->setTimezone('America/Chicago')->format('Y-m-d H:i:s');
        // convert epochtime to date format ('Y-m-d H:i:s')

        $totalBigbdmMD5Time = $endBigbdmMD5Time - $startBigbdmMD5Time;
        $totalBigbdmMD5Time = number_format($totalBigbdmMD5Time,2,'.','');

        $executionTimeList['bigBDM_MD5_B2B'] = [
            'start_execution_time' => $startBigbdmMD5Date,
            'end_execution_time' => $endBigbdmMD5Date,
            'total_execution_time' => $totalBigbdmMD5Time,
        ];

        /** IF BIG BDM MD5 HAVE RESULT */
        if (count((array)$bigDBM_B2B) > 0) 
        {
            Log::info("BigBDM Have result B2B");
            
            // $trackBigBDM = $trackBigBDM . "->B2B";
            /** REPORT ANALYTIC */
            // $this->controller->UpsertReportAnalytics($file_id,'clean_id','bigbdmemail');
            /** REPORT ANALYTIC */

            foreach ($bigDBM_B2B as $rd => $a) 
            {    
                $_FirstName = (isset($a[0]['First_Name']))?$a[0]['First_Name']:'';
                $_LastName = (isset($a[0]['Last_Name']))?$a[0]['Last_Name']:'';
                $bigEmail = (isset($a[0]['B2B_Email']))?$a[0]['B2B_Email']:'';
                $bigEmail = explode(",",$bigEmail);
                $_Address1 = (isset($a[0]['Address']))?$a[0]['Address']:'';
                $_City =  (isset($a[0]['City']))?$a[0]['City']:'';
                $_State = (isset($a[0]['State']))?$a[0]['State']:'';
                $_Zipcode = (isset($a[0]['Zip5']))?$a[0]['Zip5']:'';

                $_employee_id = (isset($a[0]['EmployeeID']))?$a[0]['EmployeeID']:'';
                $_company_name = (isset($a[0]['Company_Name']))?$a[0]['Company_Name']:'';
                $_company_website = (isset($a[0]['Company_Website']))?$a[0]['Company_Website']:'';
                $_phone_1_employee = (isset($a[0]['Phone1_Employee']))?$a[0]['Phone1_Employee']:'';
                $_phone_1_company = (isset($a[0]['Phone1_Company']))?$a[0]['Phone1_Company']:'';
                $_num_employees = (isset($a[0]['Num_Employees']))?$a[0]['Num_Employees']:'';
                $_sales_volume = (isset($a[0]['Sales_Volume']))?$a[0]['Sales_Volume']:'';
                $_year_founded = (isset($a[0]['Year_Founded']))?$a[0]['Year_Founded']:0;  
                $_job_title = (isset($a[0]['Job_Title']))?$a[0]['Job_Title']:'';
                $_level = (isset($a[0]['Level']))?$a[0]['Level']:'';
                $_job_function = (isset($a[0]['Job_Function']))?$a[0]['Job_Function']:'';
                $_headquarters_branch = (isset($a[0]['Headquarters_Branch']))?$a[0]['Headquarters_Branch']:0;
                $_naics_code = (isset($a[0]['NAICS_CODE']))?$a[0]['NAICS_CODE']:'';
                $_last_seen_date = (isset($a[0]['LastSeenDate']))?$a[0]['LastSeenDate']:'';
                $_linked_in = (isset($a[0]['LinkedIn']))?$a[0]['LinkedIn']:'';

            }

            /** INSERT INTO PERSON_B2B */
            PersonB2B::create([
                'person_id' => $newPersonID,
                'employee_id' => $_employee_id,
                'company_name' => $_company_name,
                'company_website' => $_company_website,
                'phone_1_employee' => $_phone_1_employee,
                'phone_1_company' => $_phone_1_company,
                'num_employees' => $_num_employees,
                'sales_volume' => $_sales_volume,
                'year_founded' => $_year_founded,
                'job_title' => $_job_title,
                'level' => $_level,
                'job_function' => $_job_function,
                'headquarters_branch' => $_headquarters_branch,
                'naics_code' => $_naics_code,
                'last_seen_date' => $_last_seen_date,
                'linked_in' => $_linked_in,
            ]);

            //INSERT Clean ID B2B
            CleanIDResultB2B::create([
                'file_id' => $file_id,
                'md5_id' => $md5_id,
                'employee_id' => $_employee_id,
                'company_name' => $_company_name,
                'company_website' => $_company_website,
                'phone_1_employee' => $_phone_1_employee,
                'phone_1_company' => $_phone_1_company,
                'num_employees' => $_num_employees,
                'sales_volume' => $_sales_volume,
                'year_founded' => $_year_founded,
                'job_title' => $_job_title,
                'level' => $_level,
                'job_function' => $_job_function,
                'headquarters_branch' => $_headquarters_branch,
                'naics_code' => $_naics_code,
                'last_seen_date' => $_last_seen_date,
                'linked_in' => $_linked_in,
            ]);
            //INSERT Clean ID B2B

            Log::info("BigBDM B2B Have result");
            $result = 'success';
            $message = 'B2B information Found From BIGDBM';
            $sts = 'found';
            /** IF BIG BDM MD5 HAVE RESULT */
        } 
        else 
        {
            Log::info("BigBDM DATA ADVANCE NOT FOUND");
            $result = 'error';
            $message = 'B2B information Not Found From BIGDBM';
            $sts = 'not_found';
        }

        return ['res' => $result, 'msg' => $message, 'sts' => $sts];   
    }

    /**
     * function ini terjadi jika data person email, advanced, dan lain lainnya sudah di di database dan waktunya masih kurang dari 7 hari
     */
    public function dataExistOnDB($file_id,$md5_id,$personEmail,$persondata,$dataflow = "",$md5param = "",$module = [], $is_advance = false) 
    {
        // info(__FUNCTION__, ['file_id' => $file_id, 'md5_id' => $md5_id, 'personEmail' => $personEmail, 'persondata' => $persondata, 'dataflow' => $dataflow, 'md5param' => $md5param, 'module' => $module, 'is_advance' => $is_advance]);
        $executionTimeList = [];
        date_default_timezone_set('America/Chicago');

        $new = array();
        $personID = $persondata['id'];
        $_ID = $this->generateReportUniqueNumber();
        $_FirstName = $persondata['firstName'];
        $_LastName = $persondata['lastName'];
        $_UniqueID = $persondata['uniqueID'];
        $_Email = $personEmail;
        $_Email2 = "";
        $_IP = "";
        $_Source = "";
        $_OptInDate = date('Y-m-d H:i:s');
        $_ClickDate = date('Y-m-d H:i:s');
        $_Referer = "";
        $_Phone = "";
        $_Phone2 = "";
        $_Address1 = "";
        $_Address2 = "";
        $_City = "";
        $_State = "";
        $_Zipcode = "";
        $keyword = "";
        $_Description = $dataflow . "dataExistOnDB|" ;
        $trackBigBDM = "DATAEXISTONDB";

        /** GET PHONE DATA */
        //$personPhone = PersonPhone::where('person_id','=',$personID)->where('permission','=','T')->limit(2)->get();
        $_cacheKey = "cleanid_bigdbm_personPhone_" . $personID;
        $personPhone = $this->cacheGetResult($_cacheKey);
        if(is_null($personPhone)) 
        {
            $personPhone = PersonPhone::where('person_id','=',$personID)->where('source', 'bigdbm')->limit(2)->get();

            if(!empty($personPhone)) 
            {
                $personPhone = $this->cacheQueryResult($_cacheKey,$this->ttlCacheRedis,function () use ($personPhone) {
                    return $personPhone;
                });
            } 
            else 
            {
                if($this->cacheHasResult($_cacheKey)) 
                {
                    $this->cacheForget($_cacheKey);
                }
            }
        }
        if (count($personPhone) > 0) 
        {
            $_Phone = $personPhone[0]['number'];
            $_Phone2 = (isset($personPhone[1]['number']) && $personPhone[1]['number'] != "")?$personPhone[1]['number']:"";
        }
        /** GET PHONE DATA */

        /** GET ADDRESS DATA */
        //$personAddress = PersonAddress::where('person_id','=',$personID)->first();
        $_cacheKey = "cleanid_bigdbm_personAddress_" . $personID;
        $personAddress = $this->cacheGetResult($_cacheKey);
        if(is_null($personAddress)) 
        {
            $personAddress = PersonAddress::where('person_id','=',$personID)->where('source', 'bigdbm')->first();

            if(!empty($personAddress)) 
            {
                $personAddress = $this->cacheQueryResult($_cacheKey,$this->ttlCacheRedis,function () use ($personAddress) {
                    return $personAddress;
                });
            } 
            else 
            {
                if($this->cacheHasResult($_cacheKey)) 
                {
                    $this->cacheForget($_cacheKey);
                }
            }
        }
        //if (count($personAddress) > 0) {
        if ($personAddress) 
        {
            $_Address1 = $personAddress->street;
            $_Address2 = $personAddress->unit;
            $_City = $personAddress->city;
            $_State = $personAddress->state;
            $_Zipcode = $personAddress->zip;
        }
        /** GET ADDRESS DATA */

        /** GET SECOND EMAIL DATA */
        //$personEmail = PersonEmail::select('id','email')->where('person_id','=',$personID)->whereEncrypted('email','<>',$personEmail)->first();
        $_cacheKey = "cleanid_bigdbm_personEmail_" . $personID . '_' . $personEmail;
        $personEmail = $this->cacheGetResult($_cacheKey);
        if(is_null($personEmail)) 
        {
            $personEmail = PersonEmail::select('id','email')->where('person_id','=',$personID)->where('source', 'bigdbm')->whereEncrypted('email','<>',$_Email)->first();

            if(!empty($personEmail)) 
            {
                $personEmail = $this->cacheQueryResult($_cacheKey,$this->ttlCacheRedis,function () use ($personEmail) {
                    return $personEmail;
                });
            } 
            else 
            {
                if($this->cacheHasResult($_cacheKey)) 
                {
                    $this->cacheForget($_cacheKey);
                }
            }
        }
        //if (count($personEmail) > 0) {
        if ($personEmail) 
        {
            $_Email2 = $personEmail->email;
        }
        /** GET SECOND EMAIL DATA */

        /** INSERT INTO CLEAN ID RESULT */
        CleanIDResult::create([
            'file_id' => $file_id,
            'md5_id' => $md5_id,
            'bigdbm_id' => $_UniqueID,
            'first_name' => $_FirstName,
            'last_name' => $_LastName,
            'city' => $_City,
            'state' => $_State,
            'zip' => $_Zipcode,
            'address' => $_Address1,
            'address2' => $_Address2,
            'phone' => $_Phone,
            'phone2' => $_Phone2,
            'email' => $_Email,
            'email2' => $_Email2,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        /** END INSERT CLEAN ID RESULT */

        $msg_description = 'Basic information Found From Our DB';
        $status = 'found';

        /* WHEN MODULE ENHANCE INCLUDE */
        if (in_array('advanced', $module) && $is_advance) 
        {
            $person_advance = PersonAdvance::where('person_id', $personID)->where('source', 'bigdbm')->first();
            
            if ($person_advance) 
            {
                // info('WHEN MODULE ENHANCE INCLUDE IF');
                $res = $this->process_person_advance_onDB($file_id, $md5_id, $personID);
                $msg = $res['msg'] ?? '';
                if ($msg)
                {
                    $msg_description .= '|' . $msg;
                }
            } 
            else 
            { 
                // info('WHEN MODULE ENHANCE INCLUDE WHEN ELSE');
                $processBDM = $this->process_BIGDBM_advance_exist($file_id, $md5_id, $personID, $md5param, $is_advance);
                $msg = $res['msg'] ?? '';
                if ($msg)
                {
                    $msg_description .= '|' . $msg;
                }
            }
        }
        /* WHEN MODULE ENHANCE INCLUDE */

        /* WHEN MODULE B2B INCLUDE */
        // if(in_array('b2b', $module)) 
        // {
        //     $person_b2b = PersonB2B::where('person_id', $personID)->where('source', 'bigdbm')->first();
        //     if ($person_b2b) 
        //     {
        //         $res = $this->process_person_b2b_onDB($file_id, $md5_id, $personID);
        //         $msg = $res['msg'] ?? '';
        //         if ($msg)
        //         {
        //             $msg_description .= '|' . $msg;
        //         }
        //     } 
        //     else 
        //     { 
        //         $processBDM = $this->process_BIGDBM_b2b_exist($file_id, $md5_id, $personID, $md5param, $is_advance);
        //         $msg = $res['msg'] ?? '';
        //         if ($msg)
        //         {
        //             $msg_description .= '|' . $msg;
        //         }
        //     }
        // }
        /* WHEN MODULE B2B INCLUDE */

        // /** UPDATE FOR DESCRIPTION ON FAILED RECORD */
        //     $frupdate = FailedRecord::find($failedRecordID);
        //     $frupdate->description = $frupdate->description . '|' . $trackBigBDM;
        //     $frupdate->save();
        // /** UPDATE FOR DESCRIPTION ON FAILED RECORD */

        return [
            'status' => $status,
            'msg_description' => $msg_description,
        ];
    }

    /**
     * function ini terjadi ketika data person email sudah ada, namun sudah duration nya sudah lewat 7 hari
     * function ini terjadi ketika data person email sudah ada, namun data seperti advanced atau b2b nya belom ada
     */
    public function dataNotExistOnDBBIG($file_id,$md5_id,$personID="",$dataflow="",$md5param="",$module=[], $is_advance) 
    {
        // info(__FUNCTION__, ['file_id' => $file_id, 'md5_id' => $md5_id, 'personID' => $personID, 'dataflow' => $dataflow, 'md5param' => $md5param, 'module' => $module, 'is_advance' => $is_advance]);

        date_default_timezone_set('America/Chicago');

        $new = array();
        $leadspeektype = "clean_id";

        $_ID = "";
        $_FirstName = "";
        $_LastName = "";
        $_Email = "";
        $_Email2 = "";
        $_IP = "";
        $_Source = "";
        $_OptInDate = date('Y-m-d H:i:s');
        $_ClickDate = date('Y-m-d H:i:s');
        $_Referer = "";
        $_Phone = "";
        $_Phone2 = "";
        $_Address1 = "";
        $_Address2 = "";
        $_City = "";
        $_State = "";
        $_Zipcode = "";
        $keyword = "";
        $_Description = $dataflow . "dataNotExistOnDBBIG";
        $newPersonID = "";
        $msg_description = "";
        $status = "";
        $trackBigBDM = "NOTEXISTONDBBIG";

        /* GET CLEAN ID FILE */
        $cleanIdFile = CleanIDFile::where('id', $file_id)->first();
        $leadspeek_api_id = $cleanIdFile->clean_api_id ?? null;
        /* GET CLEAN ID FILE */

        /** CHECK WITH BIG BDM MD5 */
        // info('dataNotExistOnDBBIG 1.1',['module'=>$module, 'is_advance'=>$is_advance,]);
        $type = '';
        $bigBDM_MD5 = $this->bigDBM->GetDataByMD5($file_id, $md5param, $type);
        // info(['file_id' => $file_id, 'md5param' => $md5param]);
        // info('', ['bigBDM_MD5' => $bigBDM_MD5]);
        /** CHECK WITH BIG BDM MD5 */

        if (is_object($bigBDM_MD5) && isset($bigBDM_MD5->isError) && !empty($bigBDM_MD5->isError)) 
        {
            throw new \Exception("API Error: " . ($bigBDM_MD5->message ?? 'Unknown error'));
        }

        if(count((array)$bigBDM_MD5) > 0) 
        {
            Log::info('BIGBDM Have Result');
            /** REPORT ANALYTIC */
            // $this->controller->UpsertReportAnalytics($file_id,$leadspeektype,'bigbdmemail');
            /** REPORT ANALYTIC */
            $trackBigBDM = $trackBigBDM . "->MD5";

            foreach ($bigBDM_MD5 as $rd => $a) 
            {
                $_bigdbm_id = (isset($a[0]->Id))?$a[0]->Id:'';
                $bigEmail = (isset($a[0]->Email))?$a[0]->Email:'';
                $bigEmail = explode(",",$bigEmail);

                $bigPhone = (isset($a[0]->Phone))?$a[0]->Phone:'';
                $bigPhone = explode(",",$bigPhone);

                $_FirstName = (isset($a[0]->First_Name))?$a[0]->First_Name:'';
                $_LastName = (isset($a[0]->Last_Name))?$a[0]->Last_Name:'';
                $_Email = $bigEmail[0];
                $_Email2 = (isset($bigEmail[1]))?$bigEmail[1]:'';
                $_Phone = $bigPhone[0];
                $_Phone2 = (isset($bigPhone[1]))?$bigPhone[1]:'';
                // $_Address1 = (isset($a[0]->Address))?$a[0]->Address:'';
                if(isset($a[0]->Addr_Primary))
                {
                    $_Address1 = (isset($a[0]->Addr_Primary))?$a[0]->Addr_Primary:'';
                }
                else
                {
                    $_Address1 = (isset($a[0]->Address))?$a[0]->Address:'';
                }
                if(isset($a[0]->Addr_Secondary))
                {
                    $_Address2 = (isset($a[0]->Addr_Secondary))?$a[0]->Addr_Secondary:'';
                }
                $_City =  (isset($a[0]->City))?$a[0]->City:'';
                $_State = (isset($a[0]->State))?$a[0]->State:'';
                $_Zipcode = (isset($a[0]->Zip))?$a[0]->Zip:'';
            }

            if ($personID != "") 
            {
                /** CLEAN UP CURRENT DATABASE AND UPDATE NEW ONE */
                $pad = PersonAddress::where('person_id','=',$personID)->where('source', 'bigdbm')->delete();
                $pname = PersonName::where('person_id','=',$personID)->where('source', 'bigdbm')->delete();
                $pphone = PersonPhone::where('person_id','=',$personID)->where('source', 'bigdbm')->delete();
                $pemail = PersonEmail::where('person_id','=',$personID)->where('source', 'bigdbm')->delete();
                $p = Person::where('id','=',$personID)->where('source', 'bigdbm')->delete();
                /** CLEAN UP CURRENT DATABASE AND UPDATE NEW ONE */
            }

            $uniqueID = uniqid();
            /** INSERT INTO DATABASE PERSON */
            // info('INSERT INTO DATABASE PERSON');
            $newPerson = Person::create([
                'uniqueID' => $uniqueID,
                'firstName' => $_FirstName,
                'middleName' => '',
                'lastName' => $_LastName,
                'age' => '0',
                'identityScore' => '0',
                'lastEntry' => date('Y-m-d H:i:s'),
            ]);
            $newPersonID = $newPerson->id;
            /** INSERT INTO DATABASE PERSON */
            // info('', ['_Email' => $_Email,'_Email2' => $_Email2,]);
            if (trim($_Email) != '') 
            {
                $tmpEmail = strtolower(trim($_Email));
                $tmpMd5 = md5($tmpEmail);

                /* CARA BARU PAKAI TRUELIST */
                $param = [
                    'clean_file_id' => $file_id,
                    'leadspeek_api_id' => $leadspeek_api_id,
                    'leadspeek_type' => $leadspeektype,
                    'md5param' => $tmpMd5,
                ];
                $zbcheck = $this->controller->true_list_validation($tmpEmail,$param);
                // info(['zbcheck1' => $zbcheck]);
                $isValid = false;
                $validationStatus = '';
                $apiType = '';

                if (isset($zbcheck->emails[0]->email_state)) 
                {
                    $validationStatus = $zbcheck->emails[0]->email_state;
                    $apiType = 'truelist';
                    $isValid = ($zbcheck->emails[0]->email_state == "ok");
                } 
                elseif (isset($zbcheck->status)) 
                {
                    $validationStatus = $zbcheck->status;
                    $apiType = 'zerobounce';
                    $isValid = !($zbcheck->status == "invalid" || $zbcheck->status == "catch-all" || $zbcheck->status == "abuse" || $zbcheck->status == "do_not_mail" || $zbcheck->status == "spamtrap");
                }

                if ($validationStatus !== '') 
                {
                    if (!$isValid) 
                    {
                        /** PUT IT ON OPTOUT LIST */
                        $createoptout = OptoutList::create([
                            'email' => $tmpEmail,
                            'emailmd5' => md5($tmpEmail),
                            'blockedcategory' => 'zbnotvalid',
                            'description' => ucfirst($apiType) . ' Status: ' . $validationStatus . '|Email1fromBigDBMNotExist',
                        ]);
                        /** PUT IT ON OPTOUT LIST */
                        $_Email = "";

                        if (isset($leadspeek_api_id) && isset($leadspeektype) && isset($tmpMd5)) 
                        {
                            $this->controller->UpsertFailedLeadRecord([
                                'function' => __FUNCTION__,
                                'type' => 'blocked',
                                'blocked_type' => $apiType,
                                'description' => ucfirst($apiType) . ' Status: ' . $validationStatus . '|Email1fromBigPIIDataNotExist',
                                'clean_file_id' => $file_id,
                                'leadspeek_api_id' => $leadspeek_api_id,
                                'email_encrypt' => $tmpMd5,
                                'leadspeek_type' => $leadspeektype,
                                'email' => $tmpEmail,
                                'status' => $validationStatus,
                            ]);
                        }

                        /** REPORT ANALYTIC */
                        // $this->controller->UpsertReportAnalytics($leadspeek_api_id,$leadspeektype,'zerobouncefailed');
                        /** REPORT ANALYTIC */
                        
                        $trackBigBDM = $trackBigBDM . "->Email1ZBFailed";
                    }
                    else
                    {
                        // info('INSERT INTO DATABASE PERSON EMAIL');
                        $newpersonemail = PersonEmail::create([
                            'person_id' => $newPersonID,
                            'email' => $tmpEmail,
                            'email_encrypt' => $tmpMd5,
                            'permission' => 'T',
                            'zbvalidate' => date('Y-m-d H:i:s'),
                        ]);
                        $_Email = $tmpEmail;

                        /** REPORT ANALYTIC */
                        // $this->controller->UpsertReportAnalytics($leadspeek_api_id,$leadspeektype,'zerobounce');
                        /** REPORT ANALYTIC */
                        
                        $trackBigBDM = $trackBigBDM . "->Email1ZBSuccess";
                    }
                    /** REPORT ANALYTIC */
                    // $this->controller->UpsertReportAnalytics($leadspeek_api_id, $leadspeektype, $apiType . '_details', $validationStatus);
                    /** REPORT ANALYTIC */
                }
                else
                {
                    // info('INSERT INTO DATABASE PERSON EMAIL');
                    $newpersonemail = PersonEmail::create([
                        'person_id' => $newPersonID,
                        'email' => $tmpEmail,
                        'email_encrypt' => $tmpMd5,
                        'permission' => 'T',
                        'zbvalidate' => null,
                    ]);
                    $_Email = $tmpEmail;
                    $trackBigBDM = $trackBigBDM . "->Email1ZBNotValidate";
                }
                /* CARA BARU PAKAI TRUELIST */
            }

            if (trim($_Email2) != '') 
            {
                $tmpEmail = strtolower(trim($_Email2));
                $tmpMd5 = md5($tmpEmail);

                $param = [
                    'clean_file_id' => $file_id,
                    'leadspeek_api_id' => $leadspeek_api_id,
                    'leadspeek_type' => $leadspeektype,
                    'md5param' => $tmpMd5,
                ];
                $zbcheck = $this->controller->true_list_validation($tmpEmail,$param);
                // info(['zbcheck2' => $zbcheck]);
                $isValid = false;
                $validationStatus = '';
                $apiType = '';

                if (isset($zbcheck->emails[0]->email_state)) 
                {
                    $validationStatus = $zbcheck->emails[0]->email_state;
                    $apiType = 'truelist';
                    $isValid = ($zbcheck->emails[0]->email_state == "ok");
                } 
                elseif (isset($zbcheck->status)) 
                {
                    $validationStatus = $zbcheck->status;
                    $apiType = 'zerobounce';
                    $isValid = !($zbcheck->status == "invalid" || $zbcheck->status == "catch-all" || $zbcheck->status == "abuse" || $zbcheck->status == "do_not_mail" || $zbcheck->status == "spamtrap");
                }

                if ($validationStatus !== '') 
                {
                    if (!$isValid) 
                    {   
                        /** PUT IT ON OPTOUT LIST */
                        $createoptout = OptoutList::create([
                            'email' => $tmpEmail,
                            'emailmd5' => md5($tmpEmail),
                            'blockedcategory' => 'zbnotvalid',
                            'description' => ucfirst($apiType) . ' Status: ' . $validationStatus . '|Email2fromBigDBMNotExist',
                        ]);
                        /** PUT IT ON OPTOUT LIST */
                        $_Email2 = "";

                        if (isset($leadspeek_api_id) && isset($leadspeektype) && isset($tmpMd5)) 
                        {
                            $this->controller->UpsertFailedLeadRecord([
                                'function' => __FUNCTION__,
                                'type' => 'blocked',
                                'blocked_type' => $apiType,
                                'description' => ucfirst($apiType) . ' Status: ' . $validationStatus . '|Email2fromBigDBMNotExist',
                                'clean_file_id' => $file_id,
                                'leadspeek_api_id' => $leadspeek_api_id,
                                'email_encrypt' => $tmpMd5,
                                'leadspeek_type' => $leadspeektype,
                                'email' => $tmpEmail,
                                'status' => $validationStatus,
                            ]);                        
                        }

                        /** REPORT ANALYTIC */
                        //$this->UpsertReportAnalytics($leadspeek_api_id,$leadspeektype,'zerobouncefailed');
                        /** REPORT ANALYTIC */

                        $trackBigBDM = $trackBigBDM . "->Email2ZBFailed";
                    }
                    else
                    {
                        // info('INSERT INTO DATABASE PERSON EMAIL');
                        $newpersonemail = PersonEmail::create([
                            'person_id' => $newPersonID,
                            'email' => $tmpEmail,
                            'email_encrypt' => $tmpMd5,
                            'permission' => 'T',
                            'zbvalidate' => date('Y-m-d H:i:s'),
                        ]);
                        $_Email2 = $tmpEmail;

                        /** REPORT ANALYTIC */
                        //$this->UpsertReportAnalytics($leadspeek_api_id,$leadspeektype,'zerobounce');
                        /** REPORT ANALYTIC */

                        $trackBigBDM = $trackBigBDM . "->Email2ZBSuccess";
                    }
                    /** REPORT ANALYTIC */
                    // $this->controller->UpsertReportAnalytics($leadspeek_api_id, $leadspeektype, $apiType . '_details', $validationStatus);
                    /** REPORT ANALYTIC */
                }
                else
                {
                    // info('INSERT INTO DATABASE PERSON EMAIL');
                    $newpersonemail = PersonEmail::create([
                        'person_id' => $newPersonID,
                        'email' => $tmpEmail,
                        'email_encrypt' => $tmpMd5,
                        'permission' => 'T',
                        'zbvalidate' => null,
                    ]);
                    $_Email2 = $tmpEmail;
                    $trackBigBDM = $trackBigBDM . "->Email2ZBNotValidate";
                }
            }

            if (trim($_Email) == "" && trim($_Email2) != "") 
            {
                $_Email = $_Email2;
                $_Email2 = "";
            }

            if (trim($_Email) == "" && trim($_Email2) == "") 
            {
                /** REPORT ANALYTIC */
                // $this->controller->UpsertReportAnalytics($file_id,$leadspeektype,'zerobouncefailed');
                $trackBigBDM = $trackBigBDM . "->Email1andEmail2NotValid";
                /** REPORT ANALYTIC */

                // /* WRITE UPSER FAILED LEAD RECORD */
                $this->controller->UpsertFailedLeadRecord([
                    'function' => 'dataNotExistOnDBBIG',
                    'type' => 'blocked',
                    'blocked_type' => 'zerobounce',
                    'description' => 'blocked in truelist fetch bigBDM_MD5 function dataNotExistOnDBBIG',
                    'clean_file_id' => $file_id,
                    'email_encrypt' => $md5param,
                    'leadspeek_type' => $leadspeektype,
                    'leadspeek_api_id' => $leadspeek_api_id,
                ]);
                // /* WRITE UPSER FAILED LEAD RECORD */

                /* CHECK REQUIRE EMAIL */
                $require_email = CleanIDFile::where('id', $file_id)->value('require_email');
                /* CHECK REQUIRE EMAIL */

                if($require_email === 'T')
                {
                    /* DELETE PERSON BECAUSE ZEROBOUNCE */
                    Person::where('id', $newPersonID)->where('source', 'bigdbm')->delete();
                    /* DELETE PERSON BECAUSE ZEROBOUNCE */
                    
                    $status = 'not_found';
                    $msg_description = (($is_advance === true) ? "Advanced" : "Basic") . "information Not Found Because Truelist Not Valid From BIGDBM";
    
                    return [
                        'status' => $status,
                        'msg_description' => $msg_description
                    ];
                }
            } 
            else 
            {
                /** REPORT ANALYTIC */
                // $this->controller->UpsertReportAnalytics($file_id,$leadspeektype,'zerobounce');
                $trackBigBDM = $trackBigBDM . "->Email1orEmail2Valid";
                /** REPORT ANALYTIC */
            }

            if (trim($_Phone) != "") 
            {
                /** INSERT PERSON_PHONES */
                $newpersonphone = PersonPhone::create([
                    'person_id' => $newPersonID,
                    'number' => $this->format_phone($_Phone),
                    'type' => 'user',
                    'isConnected' => 'T',
                    'firstReportedDate' => date('Y-m-d'),
                    'lastReportedDate' => date('Y-m-d'),
                    'permission' => 'F',
                ]);
                /** INSERT PERSON_PHONES */
            }

            if (trim($_Phone2) != "") 
            {
                /** INSERT PERSON_PHONES */
                $newpersonphone = PersonPhone::create([
                    'person_id' => $newPersonID,
                    'number' => $this->format_phone($_Phone2),
                    'type' => 'user',
                    'isConnected' => 'T',
                    'firstReportedDate' => date('Y-m-d'),
                    'lastReportedDate' => date('Y-m-d'),
                    'permission' => 'F',
                ]);
                /** INSERT PERSON_PHONES */
            }

            /** INSERT INTO PERSON_ADDRESSES */
            $newpersonaddress = PersonAddress::create([
                'person_id' => $newPersonID,
                'street' => $_Address1,
                'unit' => $_Address2,
                'city' => $_City,
                'state' => $_State,
                'zip' => $_Zipcode,
                'fullAddress' => $_Address1 . ' ' . $_City . ',' . $_State . ' ' . $_Zipcode,
                'firstReportedDate' => date('Y-m-d'),
                'lastReportedDate' => date('Y-m-d'),
            ]);
            /** INSERT INTO PERSON_ADDRESSES */

            /** INSERT INTO CLEAN ID RESULT */
            CleanIDResult::create([
                'file_id' => $file_id,
                'md5_id' => $md5_id,
                'bigdbm_id' => $_bigdbm_id,
                'first_name' => $_FirstName,
                'last_name' => $_LastName,
                'city' => $_City,
                'state' => $_State,
                'zip' => $_Zipcode,
                'address' => $_Address1,
                'address2' => $_Address2,
                'phone' => $_Phone,
                'phone2' => $_Phone2,
                'email' => $_Email,
                'email2' => $_Email2,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
            /** END INSERT CLEAN ID RESULT */

            /* JIKA ADVANCED HIT LAGI KE BIGDBM */
            if((in_array('advanced', $module)) && $is_advance)
            {
                $this->process_BIGDBM_advance_exist($file_id, $md5_id, $newPersonID, $md5param, $is_advance);
            }
            /* JIKA ADVANCED HIT LAGI KE BIGDBM */

            /* JIKA B2B HIT LAGI KE BIGDBM */
            // if (in_array('b2b', $module)) 
            // {
            //     $this->process_BIGDBM_b2b_exist($file_id, $md5_id, $newPersonID, $md5param);
            // }
            /* JIKA B2B HIT LAGI KE BIGDBM */

            $msg_description = 'Basic information Found From BIGDBM';
            $status = 'found';
        } 
        else 
        {
            Log::info('BIGBDM Not Have Result');
            //// data not found
            $msg_description = 'Basic information Not Found From BIGDBM';
            $status = 'not_found';
        }

        // /** UPDATE FOR DESCRIPTION ON FAILED RECORD */
        // $frupdate = FailedRecord::find($failedRecordID);
        // $frupdate->description = $frupdate->description . '|' . $trackBigBDM;
        // $frupdate->save();
        // /** UPDATE FOR DESCRIPTION ON FAILED RECORD */

        return [
            'status' => $status,
            'msg_description' => $msg_description,
        ];
    }

    /**
     * process pengurangan balance yang telah di booking
     */
    public function process_decrement_balance_wallet($file_id, $cost_cleanid)
    {
        // info(__FUNCTION__, ['file_id' => $file_id, 'cost_cleanid' => $cost_cleanid]);
        DB::transaction(function () use ($file_id, $cost_cleanid) {
            // info('decrement balance amount in topup_cleanids when found');
            $remainingCostCleanId = 0;
            $topupCleanId = TopupCleanId::where('file_id','=',$file_id)
                                        ->where('balance_amount','>',0)
                                        ->lockForUpdate()
                                        ->orderBy('id','asc')
                                        ->get();
            foreach($topupCleanId as $topup)
            {
                if($remainingCostCleanId == 0)
                {
                    // info('decrement balance amount 1.1');
                    if($topup->balance_amount >= $cost_cleanid)
                    {
                        // info('decrement balance amount 1.2');
                        $topup->balance_amount -= $cost_cleanid;
                        $remainingCostCleanId = 0;
                        $topup->save();
                    }
                    else
                    {
                        // info('decrement balance amount 1.3');
                        $remainingCostCleanId = $cost_cleanid - $topup->balance_amount;
                        $topup->balance_amount = 0;
                        $topup->save();
                    }
                }
                else 
                {
                    // info('decrement balance amount 2.1');
                    if($topup->balance_amount >= $remainingCostCleanId)
                    {
                        // info('decrement balance amount 2.2');
                        $topup->balance_amount -= $remainingCostCleanId;
                        $remainingCostCleanId = 0;
                        $topup->save();
                    }
                    else
                    {
                        // info('decrement balance amount 2.3');
                        $remainingCostCleanId -= $topup->balance_amount;
                        $topup->balance_amount = 0;
                        $topup->save();
                    }
                }

                if($remainingCostCleanId == 0)
                {
                    // info('decrement balance amount 3.1');
                    break;
                }
            }
        });
    }
    // ==================================================================================
    // PROVIDER BIGDBM END
    // ==================================================================================




    // ==================================================================================
    // PROVIDER WATTDATA START
    // ==================================================================================
    /**
     * function ini terjadi jika data person email, advanced, dan lain lainnya sudah di di database dan waktunya masih kurang dari 7 hari
     */
    public function dataExistOnDB_wattData($file_id,$md5_id,$personEmail,$persondata)
    {
        // for($i=0; $i<20; $i++)$this->debugging(0);
        $this->debugging(0, "===== dataExistOnDB_wattData Start =====", ['get_defined_vars' => get_defined_vars()]);
        
        /* SETUP */
        date_default_timezone_set('America/Chicago');
        $personID = $persondata['id'];
        $_FirstName = $persondata['firstName'];
        $_LastName = $persondata['lastName'];
        $_person_id_wattdata = $persondata['person_id_wattdata'];
        $_Email = $personEmail;
        $_Email2 = "";
        $_Phone = "";
        $_Phone2 = "";
        $_Address1 = "";
        $_Address2 = "";
        $_City = "";
        $_State = "";
        $_Zipcode = "";
        // $this->debugging(0, "dataExistOnDB_wattData -> setup 1.1");
        /* SETUP */

        /** GET PHONE DATA */
        $_cacheKey = "cleanid_wattdata_personPhone_{$personID}";
        $personPhone = $this->cacheGetResult($_cacheKey);
        if(is_null($personPhone)) 
        {
            $personPhone = PersonPhone::where('person_id','=',$personID)->where('source', 'wattdata')->orderBy('id','desc')->limit(2)->get();

            if(!empty($personPhone)) 
            {
                $personPhone = $this->cacheQueryResult($_cacheKey,$this->ttlCacheRedis,function () use ($personPhone) {
                    return $personPhone;
                });
            } 
            else 
            {
                if($this->cacheHasResult($_cacheKey)) 
                {
                    $this->cacheForget($_cacheKey);
                }
            }
        }
        // $this->debugging(0, "dataExistOnDB_wattData -> get phone data 1.2", ['personPhone' => $personPhone]);
        if (count($personPhone) > 0) 
        {
            $_Phone = (isset($personPhone[0]['number']) && $personPhone[0]['number'] != "")?$personPhone[0]['number']:"";
            $_Phone2 = (isset($personPhone[1]['number']) && $personPhone[1]['number'] != "")?$personPhone[1]['number']:"";
        }
        // $this->debugging(0, "dataExistOnDB_wattData -> get phone data 1.3", ['_Phone' => $_Phone, '_Phone2' => $_Phone2]);
        /** GET PHONE DATA */

        /** GET ADDRESS DATA */
        $_cacheKey = "cleanid_wattdata_personAddress_{$personID}";
        $personAddress = $this->cacheGetResult($_cacheKey);
        if(is_null($personAddress)) 
        {
            $personAddress = PersonAddress::where('person_id','=',$personID)->where('source', 'wattdata')->orderBy('id','desc')->limit(2)->get();

            if(!empty($personAddress)) 
            {
                $personAddress = $this->cacheQueryResult($_cacheKey,$this->ttlCacheRedis,function () use ($personAddress) {
                    return $personAddress;
                });
            } 
            else 
            {
                if($this->cacheHasResult($_cacheKey)) 
                {
                    $this->cacheForget($_cacheKey);
                }
            }
        }
        // $this->debugging(0, "dataExistOnDB_wattData -> get address data 1.4", ['personAddress' => $personAddress]);
        if (count($personAddress) > 0)
        {
            $_Address1 = (isset($personAddress[0]['fullAddress']) && $personAddress[0]['fullAddress'] != "") ? $personAddress[0]['fullAddress'] : "";
            $_City = (isset($personAddress[0]['city']) && $personAddress[0]['city'] != "") ? $personAddress[0]['city'] : "";
            $_State = (isset($personAddress[0]['state']) && $personAddress[0]['state'] != "") ? $personAddress[0]['state'] : "";
            $_Zipcode = (isset($personAddress[0]['zip']) && $personAddress[0]['zip'] != "") ? $personAddress[0]['zip'] : "";

            $_Address2 = (isset($personAddress[1]['fullAddress']) && $personAddress[1]['fullAddress'] != "") ? $personAddress[1]['fullAddress'] : "";
        }
        // $this->debugging(0, "dataExistOnDB_wattData -> get address data 1.5", ['_Address1' => $_Address1, '_City' => $_City, '_State' => $_State, '_Zipcode' => $_Zipcode, '_Address2' => $_Address2]);
        /** GET ADDRESS DATA */

        /** GET SECOND EMAIL DATA */
        $_cacheKey = "cleanid_wattdata_personEmail_{$personID}_{$personEmail}";
        $personEmail = $this->cacheGetResult($_cacheKey);
        if(is_null($personEmail)) 
        {
            $personEmail = PersonEmail::select('id','email')->where('person_id','=',$personID)->where('source', 'wattdata')->whereEncrypted('email','<>',$_Email)->first();

            if(!empty($personEmail)) 
            {
                $personEmail = $this->cacheQueryResult($_cacheKey,$this->ttlCacheRedis,function () use ($personEmail) {
                    return $personEmail;
                });
            } 
            else 
            {
                if($this->cacheHasResult($_cacheKey))
                {
                    $this->cacheForget($_cacheKey);
                }
            }
        }
        // $this->debugging(0, "dataExistOnDB_wattData -> get second email data 1.6", ['personEmail' => $personEmail]);
        if ($personEmail) 
        {
            $_Email2 = $personEmail->email;
        }
        // $this->debugging(0, "dataExistOnDB_wattData -> get second email data 1.7", ['_Email2' => $_Email2]);
        /** GET SECOND EMAIL DATA */

        /** INSERT INTO CLEAN ID RESULT */
        // $this->debugging(0, "dataExistOnDB_wattData -> insert clean id result 1.8");
        CleanIDResult::create([
            'file_id' => $file_id,
            'md5_id' => $md5_id,
            'bigdbm_id' => null,
            'wattdata_id' => $_person_id_wattdata,
            'first_name' => $_FirstName,
            'last_name' => $_LastName,
            'city' => $_City,
            'state' => $_State,
            'zip' => $_Zipcode,
            'address' => $_Address1,
            'address2' => $_Address2,
            'phone' => $_Phone,
            'phone2' => $_Phone2,
            'email' => $_Email,
            'email2' => $_Email2,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        /** END INSERT CLEAN ID RESULT */

        // for($i=0; $i<20; $i++)$this->debugging(0);
        // $this->debugging(0, "===== dataExistOnDB_wattData End =====");
        
        $status = 'found';
        $msg_description = 'Basic information Found From Our DB';
        return ['status' => $status, 'msg_description' => $msg_description,];
    }
    public function dataNotExistOnDB_wattData($file_id,$md5_id,$md5param,$emailHashType,$phone,$address,$maid,$personID)
    {
        // for($i=0; $i<20; $i++)$this->debugging(0);
        $this->debugging(0, "===== dataNotExistOnDB_wattData Start =====", ['get_defined_vars' => get_defined_vars()]);

        /* SETUP */
        date_default_timezone_set('America/Chicago');
        $leadspeektype = "clean_id";
        $_wattdata_id = null;
        $_FirstName = "";
        $_LastName = "";
        $_Email = "";
        $_Email2 = "";
        $_Phone = "";
        $_Phone2 = "";
        $_Address1 = "";
        $_Street1 = "";
        $_City1 = "";
        $_State1 = "";
        $_Zipcode1 = "";
        $_Address2 = "";
        $_Street2 = "";
        $_City2 = "";
        $_State2 = "";
        $_Zipcode2 = "";
        $newPersonID = "";
        $msg_description = "";
        $status = "";
        // $this->debugging(0, "dataNotExistOnDB_wattData -> setup 1.1");
        /* SETUP */

        /* GET CLEAN ID FILE */
        $cleanIdFile = CleanIDFile::where('id', $file_id)->first();
        $leadspeek_api_id = $cleanIdFile->clean_api_id ?? null;
        // $this->debugging(0, "dataNotExistOnDB_wattData -> get clean id file 1.2", ['cleanIdFile' => $cleanIdFile]);
        /* GET CLEAN ID FILE */

        /** CHECK WITH WATT DATA */
        $param_resolve_identities = array_filter([
            !empty($md5param) ? ['id_type' => 'email', 'hash_type' => $emailHashType, 'value' => [$md5param]] : null,
            !empty($phone) ? ['id_type' => 'phone', 'hash_type' => 'plaintext', 'value' => [$phone]] : null,
            !empty($address) ? ['id_type' => 'address', 'hash_type' => 'plaintext', 'value' => [$address]] : null,
            !empty($maid) ? ['id_type' => 'maid', 'hash_type' => 'plaintext', 'value' => [$maid]] : null,
        ]);
        // $this->debugging(0, "dataNotExistOnDB_wattData -> get resolve identities 1.3", ['param_resolve_identities' => $param_resolve_identities]);
        $resolve_identities = $this->wattData->get_resolve_identities($param_resolve_identities, $file_id);
        if(($resolve_identities['status'] ?? "") === 'error')
        {
            // Handle error
            throw new \Exception(($resolve_identities['message'] ?? "Something went wrong with get_resolve_identities function"));
        }
        // $this->debugging(0, "dataNotExistOnDB_wattData -> get resolve identities 1.4", ['resolve_identities' => $resolve_identities]);
        $identities = isset($resolve_identities['data']['identities']) ? $resolve_identities['data']['identities'] : [];
        
        // jika kosong
        if(!is_array($identities) || count($identities) == 0)
        {
            // $this->debugging(0, "dataNotExistOnDB_wattData -> jika kosong 1.5");
            $msg_description = 'Basic Information Not Found From WattData #1';
            $status = 'not_found';
            return ['status' => $status, 'msg_description' => $msg_description];
        }
        /** CHECK WITH WATT DATA */

        /* GET PERSON DETAILS */
        $person_ids = array_column($identities, 'person_id');
        // $this->debugging(0, "dataNotExistOnDB_wattData -> get person ids 1.6", ['person_ids' => $person_ids]);
        $get_person = $this->wattData->get_person($person_ids, $file_id);
        if(($get_person['status'] ?? "") === 'error')
        {
            throw new \Exception(($get_person['message'] ?? "Something went wrong with get_person function"));
        }
        // $this->debugging(0, "dataNotExistOnDB_wattData -> get get_person 1.7", ['get_person' => $get_person]);
        $profiles = isset($get_person['data']['profiles']) ? $get_person['data']['profiles'] : [];
        
        // jika kosong
        if(!is_array($profiles) || count($profiles) == 0)
        {
            // $this->debugging(0, "dataNotExistOnDB_wattData -> jika kosong 1.6");
            $msg_description = 'Basic Information Not Found From WattData #2';
            $status = 'not_found';
            return ['status' => $status, 'msg_description' => $msg_description];
        }

        // ambil top profile berdasarkan jumlah item terbanyak di name, email, phone, address
        // Ambil full_name dari database
        $cleanIdMd5 = CleanIDMd5::find($md5_id);
        $fullName = $cleanIdMd5->full_name ?? null;
        
        $getTopProfile = $this->wattData->getTopProfile($identities, $profiles, $fullName);
        $topProfile = $getTopProfile['topProfile'] ?? [];
        $multipleProfileFound = $getTopProfile['multiple_profile_found'] ?? 0;
        
        // Jika topProfile null, return not found
        if(empty($topProfile))
        {
            $msg_description = 'Basic Information Not Found From WattData #3';
            $status = 'not_found';
            return ['status' => $status, 'msg_description' => $msg_description];
        }

        // Update multiple_profile_found di database
        CleanIDMd5::where('id', $md5_id)->update(['multiple_profile_found' => $multipleProfileFound]);
        // $this->debugging(0, "dataNotExistOnDB_wattData -> get top profile 1.8", ['topProfile' => $topProfile, 'multiple_profile_found' => $multipleProfileFound]);
        /* GET PERSON DETAILS */

        /* GET DATA */
        /*
            $topProfile = [
                "person_id" => "37046338",
                "domains" => [
                    "email" => ["wlcountrytime@aol.com", "wlcountrytime@yahoo.com", "misssuew@gmail.com"],
                    "phone" => ["+12343608891", "+13303714333", "+13304843999"]
                    "name" => ["Susan Wilson"],
                    "address" => ["354 Fohl St SW, Canton, OH 44706-4357"],
                ]
            ],
        */
        $_wattdata_id = isset($topProfile['person_id']) ? $topProfile['person_id'] : null;

        $_Email = isset($topProfile['domains']['email'][0]) ? $topProfile['domains']['email'][0] : "";
        $_Email2 = isset($topProfile['domains']['email'][1]) ? $topProfile['domains']['email'][1] : "";

        $_Phone = isset($topProfile['domains']['phone'][0]) ? $topProfile['domains']['phone'][0] : "";
        $_Phone2 = isset($topProfile['domains']['phone'][1]) ? $topProfile['domains']['phone'][1] : "";

        $_name = isset($topProfile['domains']['name'][0]) ? $topProfile['domains']['name'][0] : "";
        $_name_array = !empty($_name) ? explode(' ', $_name) : [];
        $_FirstName = isset($_name_array[0]) ? $_name_array[0] : "";
        $_LastName = isset($_name_array[1]) ? $_name_array[1] : "";

        $_Address1 = isset($topProfile['domains']['address'][0]) ? $topProfile['domains']['address'][0] : ""; 
        $_parse_address1 = $this->wattData->parse_us_address($_Address1);
        $_Street1 = isset($_parse_address1['street']) ? $_parse_address1['street'] : "";
        $_City1 = isset($_parse_address1['city']) ? $_parse_address1['city'] : "";
        $_State1 = isset($_parse_address1['state']) ? $_parse_address1['state'] : "";
        $_Zipcode1 = isset($_parse_address1['zip']) ? $_parse_address1['zip'] : "";

        $_Address2 = isset($topProfile['domains']['address'][1]) ? $topProfile['domains']['address'][1] : ""; 
        $_parse_address2 = $this->wattData->parse_us_address($_Address2);
        $_Street2 = isset($_parse_address2['street']) ? $_parse_address2['street'] : "";
        $_City2 = isset($_parse_address2['city']) ? $_parse_address2['city'] : "";
        $_State2 = isset($_parse_address2['state']) ? $_parse_address2['state'] : "";
        $_Zipcode2 = isset($_parse_address2['zip']) ? $_parse_address2['zip'] : "";
        // $this->debugging(0, "dataNotExistOnDB_wattData -> get data 1.9", ['_FirstName' => $_FirstName, '_LastName' => $_LastName, '_Email' => $_Email, '_Email2' => $_Email2, '_Phone' => $_Phone, '_Phone2' => $_Phone2, '_Address1' => $_Address1, '_Address2' => $_Address2, '_City1' => $_City1, '_State1' => $_State1, '_Zipcode1' => $_Zipcode1, '_City2' => $_City2, '_State2' => $_State2, '_Zipcode2' => $_Zipcode2]);
        /* GET DATA */

        if ($personID != "") 
        {
            // $this->debugging(0, "dataNotExistOnDB_wattData -> if 1.10", ['personID' => $personID]);
            /** CLEAN UP CURRENT DATABASE AND UPDATE NEW ONE */
            PersonAddress::where('person_id','=',$personID)->where('source', 'wattdata')->delete();
            PersonName::where('person_id','=',$personID)->where('source', 'wattdata')->delete();
            PersonPhone::where('person_id','=',$personID)->where('source', 'wattdata')->delete();
            PersonEmail::where('person_id','=',$personID)->where('source', 'wattdata')->delete();
            Person::where('id','=',$personID)->where('source', 'wattdata')->delete();
            /** CLEAN UP CURRENT DATABASE AND UPDATE NEW ONE */
        }

        /** INSERT INTO DATABASE PERSON */
        // $this->debugging(0, "dataNotExistOnDB_wattData -> insert new person 1.11");
        $newPerson = Person::create([
            'source' => 'wattdata',
            'person_id_wattdata' => $_wattdata_id,
            'uniqueID' => '',
            'firstName' => $_FirstName,
            'middleName' => '',
            'lastName' => $_LastName,
            'age' => '0',
            'identityScore' => '0',
            'lastEntry' => date('Y-m-d H:i:s'),
        ]);
        $newPersonID = $newPerson->id;
        // $this->debugging(0, "dataNotExistOnDB_wattData -> insert new person 1.12", ['newPerson' => $newPerson, 'newPersonID' => $newPersonID]);
        /** INSERT INTO DATABASE PERSON */

        /** INSERT PERSON_EMAILS */
        if (trim($_Email) != '') 
        {
            // $this->debugging(0, "dataNotExistOnDB_wattData -> start insert person email 1.13");
            $tmpEmail = strtolower(trim($_Email));
            $tmpMd5 = md5($tmpEmail);

            /* CARA BARU PAKAI TRUELIST */
            $param = [
                'clean_file_id' => $file_id,
                'leadspeek_api_id' => $leadspeek_api_id,
                'leadspeek_type' => $leadspeektype,
                'md5param' => $tmpMd5,
            ];
            $zbcheck = $this->controller->true_list_validation($tmpEmail,$param);
            // $this->debugging(0, "dataNotExistOnDB_wattData -> get zbcheck 1.14", ['zbcheck' => $zbcheck, 'param' => $param]);
            $isValid = false;
            $validationStatus = '';
            $apiType = '';

            if (isset($zbcheck->emails[0]->email_state)) 
            {
                $validationStatus = $zbcheck->emails[0]->email_state;
                $apiType = 'truelist';
                $isValid = ($zbcheck->emails[0]->email_state == "ok");
            } 
            elseif (isset($zbcheck->status)) 
            {
                $validationStatus = $zbcheck->status;
                $apiType = 'zerobounce';
                $isValid = !($zbcheck->status == "invalid" || $zbcheck->status == "catch-all" || $zbcheck->status == "abuse" || $zbcheck->status == "do_not_mail" || $zbcheck->status == "spamtrap");
            }
            
            // $this->debugging(0, "dataNotExistOnDB_wattData -> get validation status 1.15", ['validationStatus' => $validationStatus, 'apiType' => $apiType, 'isValid' => $isValid]);
            
            if ($validationStatus !== '') 
            {
                if (!$isValid) 
                {
                    // $this->debugging(0, "dataNotExistOnDB_wattData -> zerobounce not valid 1.16");
                    /** PUT IT ON OPTOUT LIST */
                    $createoptout = OptoutList::create([
                        'email' => $tmpEmail,
                        'emailmd5' => md5($tmpEmail),
                        'blockedcategory' => 'zbnotvalid',
                        'description' => ucfirst($apiType) . ' Status: ' . $validationStatus . '|Email1fromBigDBMNotExist',
                    ]);
                    /** PUT IT ON OPTOUT LIST */
                    $_Email = "";

                    if (isset($leadspeek_api_id) && isset($leadspeektype) && isset($tmpMd5)) 
                    {
                        $this->controller->UpsertFailedLeadRecord([
                            'function' => __FUNCTION__,
                            'type' => 'blocked',
                            'blocked_type' => $apiType,
                            'description' => ucfirst($apiType) . ' Status: ' . $validationStatus . '|Email1fromBigPIIDataNotExist',
                            'clean_file_id' => $file_id,
                            'leadspeek_api_id' => $leadspeek_api_id,
                            'email_encrypt' => $tmpMd5,
                            'leadspeek_type' => $leadspeektype,
                            'email' => $tmpEmail,
                            'status' => $validationStatus,
                        ]);
                    }
                }
                else
                {
                    // $this->debugging(0, "dataNotExistOnDB_wattData -> zerobounce valid 1.17");
                    $newpersonemail = PersonEmail::create([
                        'source' => 'wattdata',
                        'person_id' => $newPersonID,
                        'email' => $tmpEmail,
                        'email_encrypt' => $tmpMd5,
                        'permission' => 'T',
                        'zbvalidate' => date('Y-m-d H:i:s'),
                    ]);
                    // $this->debugging(0, "dataNotExistOnDB_wattData -> insert person email 1.18", ['newpersonemail' => $newpersonemail]);
                    $_Email = $tmpEmail;
                }
            }
            else
            {
                // $this->debugging(0, "dataNotExistOnDB_wattData -> zerobounce not validate 1.19");
                $newpersonemail = PersonEmail::create([
                    'source' => 'wattdata',
                    'person_id' => $newPersonID,
                    'email' => $tmpEmail,
                    'email_encrypt' => $tmpMd5,
                    'permission' => 'T',
                    'zbvalidate' => null,
                ]);
                $_Email = $tmpEmail;
                // $this->debugging(0, "dataNotExistOnDB_wattData -> insert person email 1.20", ['newpersonemail' => $newpersonemail]);
            }
            /* CARA BARU PAKAI TRUELIST */
        }
        if (trim($_Email2) != '') 
        {
            // $this->debugging(0, "dataNotExistOnDB_wattData -> startinsert person email 1.21");
            $tmpEmail = strtolower(trim($_Email2));
            $tmpMd5 = md5($tmpEmail);

            $param = [
                'clean_file_id' => $file_id,
                'leadspeek_api_id' => $leadspeek_api_id,
                'leadspeek_type' => $leadspeektype,
                'md5param' => $tmpMd5,
            ];
            $zbcheck = $this->controller->true_list_validation($tmpEmail,$param);
            // $this->debugging(0, "dataNotExistOnDB_wattData -> get zbcheck 1.22", ['zbcheck' => $zbcheck, 'param' => $param]);
            $isValid = false;
            $validationStatus = '';
            $apiType = '';

            if (isset($zbcheck->emails[0]->email_state)) 
            {
                $validationStatus = $zbcheck->emails[0]->email_state;
                $apiType = 'truelist';
                $isValid = ($zbcheck->emails[0]->email_state == "ok");
            } 
            elseif (isset($zbcheck->status)) 
            {
                $validationStatus = $zbcheck->status;
                $apiType = 'zerobounce';
                $isValid = !($zbcheck->status == "invalid" || $zbcheck->status == "catch-all" || $zbcheck->status == "abuse" || $zbcheck->status == "do_not_mail" || $zbcheck->status == "spamtrap");
            }

            // $this->debugging(0, "dataNotExistOnDB_wattData -> get validation status 1.23", ['validationStatus' => $validationStatus, 'apiType' => $apiType, 'isValid' => $isValid]);

            if ($validationStatus !== '') 
            {
                if (!$isValid) 
                {   
                    // $this->debugging(0, "dataNotExistOnDB_wattData -> zerobounce not valid 1.24");
                    /** PUT IT ON OPTOUT LIST */
                    $createoptout = OptoutList::create([
                        'email' => $tmpEmail,
                        'emailmd5' => md5($tmpEmail),
                        'blockedcategory' => 'zbnotvalid',
                        'description' => ucfirst($apiType) . ' Status: ' . $validationStatus . '|Email2fromBigDBMNotExist',
                    ]);
                    /** PUT IT ON OPTOUT LIST */
                    $_Email2 = "";

                    if (isset($leadspeek_api_id) && isset($leadspeektype) && isset($tmpMd5)) 
                    {
                        $this->controller->UpsertFailedLeadRecord([
                            'function' => __FUNCTION__,
                            'type' => 'blocked',
                            'blocked_type' => $apiType,
                            'description' => ucfirst($apiType) . ' Status: ' . $validationStatus . '|Email2fromBigDBMNotExist',
                            'clean_file_id' => $file_id,
                            'leadspeek_api_id' => $leadspeek_api_id,
                            'email_encrypt' => $tmpMd5,
                            'leadspeek_type' => $leadspeektype,
                            'email' => $tmpEmail,
                            'status' => $validationStatus,
                        ]);                        
                    }
                }
                else
                {
                    // $this->debugging(0, "dataNotExistOnDB_wattData -> zerobounce valid 1.25");
                    $newpersonemail = PersonEmail::create([
                        'source' => 'wattdata',
                        'person_id' => $newPersonID,
                        'email' => $tmpEmail,
                        'email_encrypt' => $tmpMd5,
                        'permission' => 'T',
                        'zbvalidate' => date('Y-m-d H:i:s'),
                    ]);
                    $_Email2 = $tmpEmail;
                    // $this->debugging(0, "dataNotExistOnDB_wattData -> insert person email 1.26", ['newpersonemail' => $newpersonemail]);
                }
            }
            else
            {
                // $this->debugging(0, "dataNotExistOnDB_wattData -> zerobounce valid 1.27");
                $newpersonemail = PersonEmail::create([
                    'source' => 'wattdata',
                    'person_id' => $newPersonID,
                    'email' => $tmpEmail,
                    'email_encrypt' => $tmpMd5,
                    'permission' => 'T',
                    'zbvalidate' => null,
                ]);
                $_Email2 = $tmpEmail;
                // $this->debugging(0, "dataNotExistOnDB_wattData -> insert person email 1.28", ['newpersonemail' => $newpersonemail]);
            }
        }
        if (trim($_Email) == "" && trim($_Email2) != "") 
        {
            // $this->debugging(0, "dataNotExistOnDB_wattData -> if 1.29");
            $_Email = $_Email2;
            $_Email2 = "";
        }
        if (trim($_Email) == "" && trim($_Email2) == "") 
        {
            // $this->debugging(0, "dataNotExistOnDB_wattData -> email1 and email2 empty 1.30");
            /* WRITE UPSER FAILED LEAD RECORD */
            $this->controller->UpsertFailedLeadRecord([
                'function' => __FUNCTION__,
                'type' => 'blocked',
                'blocked_type' => 'zerobounce',
                'description' => 'blocked in truelist fetch bigBDM_MD5 function dataNotExistOnDBBIG',
                'clean_file_id' => $file_id,
                'email_encrypt' => $md5param,
                'leadspeek_type' => $leadspeektype,
                'leadspeek_api_id' => $leadspeek_api_id,
            ]);
            /* WRITE UPSER FAILED LEAD RECORD */

            /* CHECK REQUIRE EMAIL */
            $require_email = CleanIDFile::where('id', $file_id)->value('require_email');
            // $this->debugging(0, "dataNotExistOnDB_wattData -> get require email 1.31", ['require_email' => $require_email]);
            /* CHECK REQUIRE EMAIL */

            if($require_email === 'T')
            {
                /* DELETE PERSON BECAUSE ZEROBOUNCE */
                // $this->debugging(0, "dataNotExistOnDB_wattData -> delete person 1.32");
                Person::where('id', $newPersonID)->where('source', 'wattdata')->delete();
                /* DELETE PERSON BECAUSE ZEROBOUNCE */
                
                $status = 'not_found';
                $msg_description = "Basic information Not Found Because Truelist Not Valid From WattData";
                return ['status' => $status, 'msg_description' => $msg_description];
            }
        }
        /** INSERT PERSON_EMAILS */

        /** INSERT PERSON_PHONES */
        if (trim($_Phone) != "") 
        {
            // $this->debugging(0, "dataNotExistOnDB_wattData -> insert phone 1.33");
            $newpersonphone = PersonPhone::create([
                'source' => 'wattdata',
                'person_id' => $newPersonID,
                'number' => $_Phone,
                'type' => 'user',
                'isConnected' => 'T',
                'firstReportedDate' => date('Y-m-d'),
                'lastReportedDate' => date('Y-m-d'),
                'permission' => 'F',
            ]);
        }
        if (trim($_Phone2) != "") 
        {
            // $this->debugging(0, "dataNotExistOnDB_wattData -> insert phone 1.34");
            $newpersonphone = PersonPhone::create([
                'source' => 'wattdata',
                'person_id' => $newPersonID,
                'number' => $_Phone2,
                'type' => 'user',
                'isConnected' => 'T',
                'firstReportedDate' => date('Y-m-d'),
                'lastReportedDate' => date('Y-m-d'),
                'permission' => 'F',
            ]);
        }
        /** INSERT PERSON_PHONES */

        /* INSERT PERSON_ADDRESSES */
        if (trim($_Address1) != "") 
        {
            // $this->debugging(0, "dataNotExistOnDB_wattData -> insert address 1.35");
            PersonAddress::create([
                'source' => 'wattdata',
                'person_id' => $newPersonID,
                'street' => $_Street1,
                'unit' => '',
                'city' => $_City1,
                'state' => $_State1,
                'zip' => $_Zipcode1,
                'fullAddress' => $_Address1,
                'firstReportedDate' => date('Y-m-d'),
                'lastReportedDate' => date('Y-m-d'),
            ]);
        }
        if (trim($_Address2) != "") 
        {
            // $this->debugging(0, "dataNotExistOnDB_wattData -> insert address 1.36");
            PersonAddress::create([
                'source' => 'wattdata',
                'person_id' => $newPersonID,
                'street' => $_Street2,
                'unit' => '',
                'city' => $_City2,
                'state' => $_State2,
                'zip' => $_Zipcode2,
                'fullAddress' => $_Address2,
                'firstReportedDate' => date('Y-m-d'),
                'lastReportedDate' => date('Y-m-d'),
            ]);
        }
        /* INSERT PERSON_ADDRESSES */

        /** INSERT INTO CLEAN ID RESULT */
        // $this->debugging(0, "dataNotExistOnDB_wattData -> insert clean id result 1.37");
        CleanIDResult::create([
            'file_id' => $file_id,
            'md5_id' => $md5_id,
            'bigdbm_id' => null,
            'wattdata_id' => $_wattdata_id,
            'first_name' => $_FirstName,
            'last_name' => $_LastName,
            'city' => $_City1,
            'state' => $_State1,
            'zip' => $_Zipcode1,
            'address' => $_Address1,
            'address2' => $_Address2,
            'phone' => $_Phone,
            'phone2' => $_Phone2,
            'email' => $_Email,
            'email2' => $_Email2,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        /** END INSERT CLEAN ID RESULT */

        // for($i=0; $i<20; $i++)$this->debugging(0);
        // $this->debugging(0, "===== dataNotExistOnDB_wattData End =====");

        $status = 'found';
        $msg_description = 'Basic information Found From WattData';
        return ['status' => $status,'msg_description' => $msg_description];
    }
    public function process_WattData_basic($file_id,$md5_id,$md5param,$emailHashType,$phone,$address,$maid)
    {
        // for($i=0; $i<20; $i++)$this->debugging(0);
        $this->debugging(0, "===== process_WattData_basic Start =====", ['get_defined_vars' => get_defined_vars()]);
        
        /* SETUP */
        date_default_timezone_set('America/Chicago');
        $status = "";
        $_FirstName = "";
        $_LastName = "";
        $_Email = "";
        $_Email2 = "";
        $_Phone = "";
        $_Phone2 = "";
        $_Address1 = "";
        $_Street1 = "";
        $_City1 = "";
        $_State1 = "";
        $_Zipcode1 = "";
        $_Address2 = "";
        $_Street2 = "";
        $_City2 = "";
        $_State2 = "";
        $_Zipcode2 = "";
        $leadspeektype = 'clean_id';
        $trackBigBDM = "process_WattData_basic";
        // $this->debugging(0, "process_WattData_basic -> setup 1.1");
        $isOnlyEmail = empty($phone) && empty($address) && empty($maid);
        /* SETUP */

        /* GET CLEAN ID FILE */
        $cleanIdFile = CleanIDFile::where('id', $file_id)->first();
        $leadspeek_api_id = $cleanIdFile->clean_api_id ?? null;
        // $this->debugging(0, "process_WattData_basic -> get cleanIdFile 1.2", ['cleanIdFile' => $cleanIdFile]);
        /* GET CLEAN ID FILE */

        /** CHECK WITH WATT DATA */
        $param_resolve_identities = array_filter([
            !empty($md5param) ? ['id_type' => 'email', 'hash_type' => $emailHashType, 'value' => [$md5param]] : null,
            !empty($phone) ? ['id_type' => 'phone', 'hash_type' => 'plaintext', 'value' => [$phone]] : null,
            !empty($address) ? ['id_type' => 'address', 'hash_type' => 'plaintext', 'value' => [$address]] : null,
            !empty($maid) ? ['id_type' => 'maid', 'hash_type' => 'plaintext', 'value' => [$maid]] : null,
        ]);
        // $this->debugging(0, "process_WattData_basic -> get resolve_identities 1.3", ['param_resolve_identities' => $param_resolve_identities]);
        $resolve_identities = $this->wattData->get_resolve_identities($param_resolve_identities, $file_id);
        if(($resolve_identities['status'] ?? "") === 'error')
        {
            // Handle error
            throw new \Exception(($resolve_identities['message'] ?? "Something went wrong with get_resolve_identities function"));
        }
        // $this->debugging(0, "process_WattData_basic -> get resolve_identities 1.4", ['resolve_identities' => $resolve_identities]);
        $identities = isset($resolve_identities['data']['identities']) ? $resolve_identities['data']['identities'] : [];
        
        // jika kosong
        if(!is_array($identities) || count($identities) == 0)
        {
            // $this->debugging(0, "process_WattData_basic -> jika kosong 1.5");
            $msg_description = 'Basic Information Not Found From WattData #1';
            $status = 'not_found';
            return ['status' => $status, 'msg_description' => $msg_description];
        }
        /** CHECK WITH WATT DATA 

        /* GET PERSON DETAILS */
        $person_ids = array_column($identities, 'person_id');
        // $this->debugging(0, "process_WattData_basic -> get person_ids 1.6", ['person_ids' => $person_ids]);
        $get_person = $this->wattData->get_person($person_ids, $file_id);
        if(($get_person['status'] ?? "") === 'error')
        {
            throw new \Exception(($get_person['message'] ?? "Something went wrong with get_person function"));
        }
        // $this->debugging(0, "process_WattData_basic -> get get_person 1.7", ['get_person' => $get_person]);
        $profiles = isset($get_person['data']['profiles']) ? $get_person['data']['profiles'] : [];
        
        // jika kosong
        if(!is_array($profiles) || count($profiles) == 0)
        {
            // $this->debugging(0, "process_WattData_basic -> jika kosong 1.8");
            $msg_description = 'Basic Information Not Found From WattData #2';
            $status = 'not_found';
            return ['status' => $status, 'msg_description' => $msg_description];
        }

        // ambil top profile berdasarkan jumlah item terbanyak di name, email, phone, address
        // Ambil full_name dari database
        $cleanIdMd5 = CleanIDMd5::find($md5_id);
        $fullName = $cleanIdMd5->full_name ?? null;
        
        $getTopProfile = $this->wattData->getTopProfile($identities, $profiles, $fullName);
        $topProfile = $getTopProfile['topProfile'] ?? [];
        $multipleProfileFound = $getTopProfile['multiple_profile_found'] ?? 0;
        
        // Jika topProfile null, return not found
        if(empty($topProfile))
        {
            $msg_description = 'Basic Information Not Found From WattData #3';
            $status = 'not_found';
            return ['status' => $status, 'msg_description' => $msg_description];
        }

        // Update multiple_profile_found di database
        CleanIDMd5::where('id', $md5_id)->update(['multiple_profile_found' => $multipleProfileFound]);
        // $this->debugging(0, "process_WattData_basic -> get top profile 1.9", ['topProfile' => $topProfile, 'multiple_profile_found' => $multipleProfileFound]);
        /* GET PERSON DETAILS */

        /* GET DATA */
        /*
            $topProfile = [
                "person_id" => "37046338",
                "domains" => [
                    "email" => ["wlcountrytime@aol.com", "wlcountrytime@yahoo.com", "misssuew@gmail.com"],
                    "phone" => ["+12343608891", "+13303714333", "+13304843999"]
                    "name" => ["Susan Wilson"],
                    "address" => ["354 Fohl St SW, Canton, OH 44706-4357"],
                ]
            ],
        */
        $_wattdata_id = isset($topProfile['person_id']) ? $topProfile['person_id'] : null;

        $bigEmail = isset($topProfile['domains']['email']) ? $topProfile['domains']['email'] : [];

        $_Phone = isset($topProfile['domains']['phone'][0]) ? $topProfile['domains']['phone'][0] : "";
        $_Phone2 = isset($topProfile['domains']['phone'][1]) ? $topProfile['domains']['phone'][1] : "";

        $_name = isset($topProfile['domains']['name'][0]) ? $topProfile['domains']['name'][0] : "";
        $_name_array = !empty($_name) ? explode(' ', $_name) : [];
        $_FirstName = isset($_name_array[0]) ? $_name_array[0] : "";
        $_LastName = isset($_name_array[1]) ? $_name_array[1] : "";

        $_Address1 = isset($topProfile['domains']['address'][0]) ? $topProfile['domains']['address'][0] : ""; 
        $_parse_address1 = $this->wattData->parse_us_address($_Address1);
        $_Street1 = isset($_parse_address1['street']) ? $_parse_address1['street'] : "";
        $_City1 = isset($_parse_address1['city']) ? $_parse_address1['city'] : "";
        $_State1 = isset($_parse_address1['state']) ? $_parse_address1['state'] : "";
        $_Zipcode1 = isset($_parse_address1['zip']) ? $_parse_address1['zip'] : "";

        $_Address2 = isset($topProfile['domains']['address'][1]) ? $topProfile['domains']['address'][1] : ""; 
        $_parse_address2 = $this->wattData->parse_us_address($_Address2);
        $_Street2 = isset($_parse_address2['street']) ? $_parse_address2['street'] : "";
        $_City2 = isset($_parse_address2['city']) ? $_parse_address2['city'] : "";
        $_State2 = isset($_parse_address2['state']) ? $_parse_address2['state'] : "";
        $_Zipcode2 = isset($_parse_address2['zip']) ? $_parse_address2['zip'] : "";
        // $this->debugging(0, "process_WattData_basic -> get data 1.10", ['_FirstName' => $_FirstName, '_LastName' => $_LastName, '_Email' => $_Email, '_Email2' => $_Email2, '_Phone' => $_Phone, '_Phone2' => $_Phone2, '_Address1' => $_Address1, '_Address2' => $_Address2, '_City1' => $_City1, '_State1' => $_State1, '_Zipcode1' => $_Zipcode1, '_City2' => $_City2, '_State2' => $_State2, '_Zipcode2' => $_Zipcode2]);
        /* GET DATA */

        /** INSERT INTO DATABASE PERSON */
        $newPerson = [];
        $newPersonID = null;
        if($isOnlyEmail)
        {
            $newPerson = Person::create([
                'source' => 'wattdata',
                'person_id_wattdata' => $_wattdata_id,
                'uniqueID' => '',
                'firstName' => $_FirstName,
                'middleName' => '',
                'lastName' => $_LastName,
                'age' => '0',
                'identityScore' => '0',
                'lastEntry' => date('Y-m-d H:i:s'),
            ]);
            $newPersonID = $newPerson->id;
        }
        // $this->debugging(0, "process_WattData_basic -> insert new person 1.11", ['newPerson' => $newPerson, 'newPersonID' => $newPersonID]);
        /** INSERT INTO DATABASE PERSON */

        /** SEPARATE BETWEEN YAHOO/AOL AND OTHER EMAIL */
        // $this->debugging(0, "process_WattData_basic -> separate between yahoo/aol and other email 1.12");
        $filteredEmails = [];
        $otherEmails = [];
        foreach ($bigEmail as $email) 
        {
            if (strpos($email, 'yahoo.com') !== false || strpos($email, 'aol.com') !== false) 
            {
                // $this->debugging(0, "process_WattData_basic -> separate between yahoo/aol and other email if 1.13", ['email' => $email]);
                $filteredEmails[] = $email;
            } 
            else 
            {
                // $this->debugging(0, "process_WattData_basic -> separate between yahoo/aol and other email else 1.14", ['email' => $email]);
                $otherEmails[] = $email;
            }
        }
        // $this->debugging(0, "process_WattData_basic -> separate between yahoo/aol and other email 1.15", ['filteredEmails' => $filteredEmails, 'otherEmails' => $otherEmails]);
        /** SEPARATE BETWEEN YAHOO/AOL AND OTHER EMAIL */

        // $this->debugging(0, "process_WattData_basic -> wattdata have result 1.16");

        /** NEW METHOD TO CHECK AND GET EMAIL */
        // $this->debugging(0, "process_WattData_basic -> new method to check and get email 1.17");
        foreach($otherEmails as $index => $be) 
        {
            // $this->debugging(0, "process_WattData_basic -> new method to check and get email 1.17.1 ($index)");
            if (trim($be) != "") 
            {
                $tmpEmail = strtolower(trim($be));
                $tmpMd5 = md5($tmpEmail);

                /* CARA BARU PAKAI ZEROBOUNCE */
                $param = [
                    'clean_file_id' => $file_id,
                    'leadspeek_api_id' => $leadspeek_api_id,
                    'leadspeek_type' => $leadspeektype,
                    'md5param' => $tmpMd5,
                ];
                $zbcheck = $this->controller->true_list_validation($tmpEmail,$param);
                // $this->debugging(0, "process_WattData_basic -> new method to check and get email 1.18", ['zbcheck' => $zbcheck]);

                // Handle both TrueList and ZeroBounce responses
                $isValid = false;
                $validationStatus = '';
                $apiType = '';
                
                // Check TrueList response format
                if (isset($zbcheck->emails[0]->email_state)) 
                {
                    $validationStatus = $zbcheck->emails[0]->email_state;
                    $apiType = 'truelist';
                    $isValid = ($zbcheck->emails[0]->email_state == "ok");
                }
                // Check ZeroBounce response format
                elseif (isset($zbcheck->status)) 
                {
                    $validationStatus = $zbcheck->status;
                    $apiType = 'zerobounce';
                    $isValid = !($zbcheck->status == "invalid" || $zbcheck->status == "catch-all" || $zbcheck->status == "abuse" || $zbcheck->status == "do_not_mail" || $zbcheck->status == "spamtrap");
                }

                // $this->debugging(0, "process_WattData_basic -> new method to check and get email 1.19", ['validationStatus' => $validationStatus, 'apiType' => $apiType, 'isValid' => $isValid]);
                    
                if ($validationStatus !== '') 
                {
                    if (!$isValid)
                    {
                        // $this->debugging(0, "process_WattData_basic -> new method to check and get email - zerobounce not valid 1.20");
                        /** PUT IT ON OPTOUT LIST */
                        $createoptout = OptoutList::create([
                            'email' => $tmpEmail,
                            'emailmd5' => md5($tmpEmail),
                            'blockedcategory' => 'zbnotvalid',
                            'description' => ucfirst($apiType) . ' Status: ' . $validationStatus . '|Email' . $index . 'fromWattData',
                        ]);
                        /** PUT IT ON OPTOUT LIST */ 

                        if (isset($leadspeek_api_id) && isset($leadspeektype) && isset($tmpMd5)) 
                        {
                            $this->controller->UpsertFailedLeadRecord([
                                'function' => __FUNCTION__,
                                'type' => 'blocked',
                                'blocked_type' => $apiType,
                                'description' => ucfirst($apiType) . ' Status: ' . $validationStatus . '|Email' . $index . 'fromWattData',
                                'clean_file_id' => $file_id,
                                'leadspeek_api_id' => $leadspeek_api_id,
                                'email_encrypt' => $tmpMd5,
                                'leadspeek_type' => $leadspeektype,
                                'email' => $tmpEmail,
                                'status' => $validationStatus,
                            ]);
                        }

                        $trackBigBDM = $trackBigBDM . "->Email" . $index . "ZBFailed";
                    }
                    else
                    {
                        // $this->debugging(0, "process_WattData_basic -> new method to check and get email - zerobounce valid 1.21");
                        if($isOnlyEmail && !empty($newPersonID))
                        {
                            $newpersonemail = PersonEmail::create([
                                'source' => 'wattdata',
                                'person_id' => $newPersonID,
                                'email' => $tmpEmail,
                                'email_encrypt' => $tmpMd5,
                                'permission' => 'T',
                                'zbvalidate' => date('Y-m-d H:i:s'),
                            ]);
                        }

                        if ($_Email ==  "") 
                        {
                            $_Email = $tmpEmail;
                        }
                        else if ($_Email2 == "") 
                        {
                            $_Email2 = $tmpEmail;
                        }

                        $trackBigBDM = $trackBigBDM . "->Email" . $index . "ZBSuccess";
                    }
                    /** REPORT ANALYTIC */
                    // $this->controller->UpsertReportAnalytics($leadspeek_api_id, $leadspeektype, $apiType . '_details', $validationStatus);
                    /** REPORT ANALYTIC */
                }
                else
                {
                    $trackBigBDM = $trackBigBDM . "->Email" . $index . "ZBNotValidate";
                }
            }
        }
        /** NEW METHOD TO CHECK AND GET EMAIL */

        /** CHECK IF STANDARD EMAIL NOT GET ANY VALID EMAIL */
        // $this->debugging(0, "process_WattData_basic -> check if standard email not get any valid email 1.22");
        if (trim($_Email) == '')
        {
            /** NEW METHOD TO CHECK AND GET EMAIL */
            // $this->debugging(0, "process_WattData_basic -> new method to check and get email 1.23");
            foreach($filteredEmails as $index => $be) 
            {
                // $this->debugging(0, "process_WattData_basic -> new method to check and get email 1.23.1 ($index)");
                if (trim($be) != "") 
                {
                    $tmpEmail = strtolower(trim($be));
                    $tmpMd5 = md5($tmpEmail);

                    $param = [
                        'clean_file_id' => $file_id,
                        'leadspeek_api_id' => $leadspeek_api_id,
                        'leadspeek_type' => $leadspeektype,
                        'md5param' => $tmpMd5,
                    ];
                    $zbcheck = $this->controller->true_list_validation($tmpEmail,$param);
                    // $this->debugging(0, "process_WattData_basic -> new method to check and get email 1.24", ['zbcheck' => $zbcheck]);

                    // Handle both TrueList and ZeroBounce responses
                    $isValid = false;
                    $validationStatus = '';
                    $apiType = '';

                    // Check TrueList response format
                    if (isset($zbcheck->emails[0]->email_state)) 
                    {
                        $validationStatus = $zbcheck->emails[0]->email_state;
                        $apiType = 'truelist';
                        $isValid = ($zbcheck->emails[0]->email_state == "ok");
                    }
                    // Check ZeroBounce response format
                    elseif (isset($zbcheck->status)) 
                    {
                        $validationStatus = $zbcheck->status;
                        $apiType = 'zerobounce';
                        $isValid = !($zbcheck->status == "invalid" || $zbcheck->status == "catch-all" || $zbcheck->status == "abuse" || $zbcheck->status == "do_not_mail" || $zbcheck->status == "spamtrap");
                    }

                    // $this->debugging(0, "process_WattData_basic -> new method to check and get email 1.25", ['validationStatus' => $validationStatus, 'apiType' => $apiType, 'isValid' => $isValid]);
                    
                    if ($validationStatus !== '') 
                    {
                        if (!$isValid) 
                        {
                            // $this->debugging(0, "process_WattData_basic -> new method to check and get email - zerobounce not valid 1.26");
                            /** PUT IT ON OPTOUT LIST */
                            $createoptout = OptoutList::create([
                                'email' => $tmpEmail,
                                'emailmd5' => md5($tmpEmail),
                                'blockedcategory' => 'zbnotvalid',
                                'description' => ucfirst($apiType) . ' Status: ' . $validationStatus . '|Email' . $index . 'fromWattData',
                            ]);
                            /** PUT IT ON OPTOUT LIST */

                            if (isset($leadspeek_api_id) && isset($leadspeektype) && isset($tmpMd5)) 
                            {
                                $this->controller->UpsertFailedLeadRecord([
                                    'function' => __FUNCTION__,
                                    'type' => 'blocked',
                                    'blocked_type' => $apiType,
                                    'description' => ucfirst($apiType) . ' Status: ' . $validationStatus . '|Email' . $index . 'fromWattData',
                                    'clean_file_id' => $file_id,
                                    'leadspeek_api_id' => $leadspeek_api_id,
                                    'email_encrypt' => $tmpMd5,
                                    'leadspeek_type' => $leadspeektype,
                                    'email' => $tmpEmail,
                                    'status' => $validationStatus,
                                ]);
                            }

                            $trackBigBDM = $trackBigBDM . "->Email" . $index . "ZBFailed";
                        }
                        else
                        {
                            // $this->debugging(0, "process_WattData_basic -> new method to check and get email - zerobounce valid 1.27");
                            if($isOnlyEmail && !empty($newPersonID))
                            {
                                $newpersonemail = PersonEmail::create([
                                    'source' => 'wattdata',
                                    'person_id' => $newPersonID,
                                    'email' => $tmpEmail,
                                    'email_encrypt' => $tmpMd5,
                                    'permission' => 'T',
                                    'zbvalidate' => date('Y-m-d H:i:s'),
                                ]);
                            }

                            if ($_Email ==  "") 
                            {
                                $_Email = $tmpEmail;
                                break;
                            }
                            else if ($_Email2 == "") 
                            {
                                $_Email2 = $tmpEmail;
                            }

                            $trackBigBDM = $trackBigBDM . "->Email" . $index . "ZBSuccess";
                        }
                        /** REPORT ANALYTIC */
                        // $this->controller->UpsertReportAnalytics($leadspeek_api_id, $leadspeektype, $apiType . '_details', $validationStatus);
                        /** REPORT ANALYTIC */
                    }
                    else
                    {
                        $trackBigBDM = $trackBigBDM . "->Email" . $index . "ZBNotValidate";
                    }
                }
            }
            /** NEW METHOD TO CHECK AND GET EMAIL */
        }
        /** CHECK IF STANDARD EMAIL NOT GET ANY VALID EMAIL */

        /* CHECK WHEN 2 EMAIL VALID OR NOT */
        // $this->debugging(0, "process_WattData_basic -> check when 2 email valid or not 1.28");
        if (trim($_Email) == "" && trim($_Email2) == "")
        {
            /** REPORT ANALYTIC */
            // $this->controller->UpsertReportAnalytics($file_id,$leadspeektype,'zerobouncefailed');
            $trackBigBDM = $trackBigBDM . "->Email1andEmail2NotValid";
            /** REPORT ANALYTIC */

            // $this->debugging(0, "process_WattData_basic -> have result - check zero bounce - email 1 and email 2 not valid 1.28.1");

            /* WRITE UPSER FAILED LEAD RECORD */
            $this->controller->UpsertFailedLeadRecord([
                'function' => __FUNCTION__,
                'type' => 'blocked',
                'blocked_type' => 'zerobounce',
                'description' => 'blocked in truelist fetch WattData function ' . __FUNCTION__,
                'clean_file_id' => $file_id,
                'email_encrypt' => $md5param,
                'leadspeek_type' => $leadspeektype,
                'leadspeek_api_id' => $leadspeek_api_id,
            ]);
            /* WRITE UPSER FAILED LEAD RECORD */

            /* CHECK REQUIRE EMAIL */
            $require_email = CleanIDFile::where('id', $file_id)->value('require_email');
            // $this->debugging(0, "process_WattData_basic -> check require email 1.29", ['require_email' => $require_email]);
            /* CHECK REQUIRE EMAIL */

            if($require_email === 'T')
            {
                /* DELETE PERSON BECAUSE ZEROBOUNCE */
                // $this->debugging(0, "process_WattData_basic -> check require email if 1.30");
                if($isOnlyEmail && !empty($newPersonID))
                {
                    Person::where('id', $newPersonID)->where('source', 'wattdata')->delete();
                }
                /* DELETE PERSON BECAUSE ZEROBOUNCE */
                
                $status = 'not_found';
                $msg_description = 'Basic information Not Found Because Truelist Not Valid From WattData';

                return ['status' => $status, 'msg_description' => $msg_description];
            }
        }
        else 
        {
            /** REPORT ANALYTIC */
            // $this->controller->UpsertReportAnalytics($file_id,$leadspeektype,'zerobounce');
            $trackBigBDM = $trackBigBDM . "->Email1orEmail2Valid";
            /** REPORT ANALYTIC */

            // $this->debugging(0, "process_WattData_basic -> have result - check zero bounce - email 1 and email 2 valid 1.31");
        }
        /* CHECK WHEN 2 EMAIL VALID OR NOT */

        /** INSERT PERSON_PHONES */
        // $this->debugging(0, "process_WattData_basic -> check phone 1 1.32");
        if ($isOnlyEmail && !empty($newPersonID) && trim($_Phone) != "") 
        {
            // $this->debugging(0, "process_WattData_basic -> insert phone 1 1.33");
            /** INSERT PERSON_PHONES */
            $newpersonphone = PersonPhone::create([
                'source' => 'wattdata',
                'person_id' => $newPersonID,
                'number' => $_Phone,
                'type' => 'user',
                'isConnected' => 'T',
                'firstReportedDate' => date('Y-m-d'),
                'lastReportedDate' => date('Y-m-d'),
                'permission' => 'F',
            ]);
            /** INSERT PERSON_PHONES */
        }
        // $this->debugging(0, "process_WattData_basic -> check phone 2 1.34");
        if ($isOnlyEmail && !empty($newPersonID) && trim($_Phone2) != "") 
        {
            // $this->debugging(0, "process_WattData_basic -> insert phone 2 1.35");
            /** INSERT PERSON_PHONES */
            $newpersonphone = PersonPhone::create([
                'source' => 'wattdata',
                'person_id' => $newPersonID,
                'number' => $_Phone2,
                'type' => 'user',
                'isConnected' => 'T',
                'firstReportedDate' => date('Y-m-d'),
                'lastReportedDate' => date('Y-m-d'),
                'permission' => 'F',
            ]);
            /** INSERT PERSON_PHONES */
        }
        /** INSERT PERSON_PHONES */

        /* INSERT PERSON_ADDRESSES */
        // $this->debugging(0, "process_WattData_basic -> check address 1.36");
        if ($isOnlyEmail && !empty($newPersonID) && trim($_Address1) != "") 
        {
            // $this->debugging(0, "process_WattData_basic -> insert address 1 1.37");
            PersonAddress::create([
                'source' => 'wattdata',
                'person_id' => $newPersonID,
                'street' => $_Street1,
                'unit' => '',
                'city' => $_City1,
                'state' => $_State1,
                'zip' => $_Zipcode1,
                'fullAddress' => $_Address1,
                'firstReportedDate' => date('Y-m-d'),
                'lastReportedDate' => date('Y-m-d'),
            ]);
        }
        // $this->debugging(0, "process_WattData_basic -> check address 2 1.38");
        if ($isOnlyEmail && !empty($newPersonID) && trim($_Address2) != "") 
        {
            // $this->debugging(0, "process_WattData_basic -> insert address 2 1.39");
            PersonAddress::create([
                'source' => 'wattdata',
                'person_id' => $newPersonID,
                'street' => $_Street2,
                'unit' => '',
                'city' => $_City2,
                'state' => $_State2,
                'zip' => $_Zipcode2,
                'fullAddress' => $_Address2,
                'firstReportedDate' => date('Y-m-d'),
                'lastReportedDate' => date('Y-m-d'),
            ]);
        }
        /* INSERT PERSON_ADDRESSES */

        // $this->debugging(0, "process_WattData_basic -> have result - watt id #{$_wattdata_id} 1.40");

        /** INSERT KE TABLE CLIENTID */
        // $this->debugging(0, "process_WattData_basic -> insert clean id result 1.41");
        CleanIDResult::create([
            'file_id' => $file_id,
            'md5_id' => $md5_id,
            'bigdbm_id' => null,
            'wattdata_id' => $_wattdata_id,
            'first_name' => $_FirstName,
            'last_name' => $_LastName,
            'city' => $_City1,
            'state' => $_State1,
            'zip' => $_Zipcode1,
            'address' => $_Address1,
            'address2' => $_Address2,
            'phone' => $_Phone,
            'phone2' => $_Phone2,
            'email' => $_Email,
            'email2' => $_Email2,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        /** INSERT KE TABLE CLIENTID */

        // for($i=0; $i<20; $i++)$this->debugging(0);
        // $this->debugging(0, "===== process_WattData_basic End =====");

        $status = 'found';
        $msg_description = 'Basic information Found From WattData';
        return ['status' => $status,'msg_description' => $msg_description];
    }
    // ==================================================================================
    // PROVIDER WATTDATA END
    // ==================================================================================

    /**
     * process refund dari yang telah di booking ke topup agency jika masih ada sisa dan process clean id selesai, dan pastikan topup_status nya yang bukan done
     */
    public function process_refund_balance_wallet($file_id, $company_id, $leadspeek_invoices_id)
    {
        // info('process_refund_balance_wallet', ['file_id' => $file_id, 'company_id' => $company_id, 'leadspeek_invoices_id' => $leadspeek_invoices_id]);
        DB::transaction(function () use ($file_id, $company_id, $leadspeek_invoices_id) {
            // info('check if balance amount in topup_cleanids exists, when exists refund to topup_agencies');
            $topupCleanId = TopupCleanId::where('file_id','=',$file_id)
                                        ->where('balance_amount','>',0)
                                        ->where('topup_status','<>','done')
                                        ->lockForUpdate()
                                        ->orderBy('id','asc')
                                        ->get();
            $balanceAmount = $topupCleanId->sum('balance_amount');
            $topupAgencyIds = [];

            if($balanceAmount > 0) // jika balance amount > 0, maka refund ke data wallet agency, dan ubah status topup_status menjadi done
            {
                // info('process_refund_balance_wallet 1.1');
                // update balance amount 
                foreach($topupCleanId as $topup)
                {
                    // update status to progress
                    $topup->topup_status = 'progress';
                    $topup->save();

                    // process mengembalikan amount dari topup clean id ke topup agency
                    // info('process_refund_balance_wallet 1.2');
                    $topupAgency = TopupAgency::where('id', $topup->topup_agencies_id)
                                              ->whereNull('expired_at')
                                              ->first();
                    if(!empty($topupAgency))
                    {
                        // info('process_refund_balance_wallet 1.3');
                        $topupAgencyIds[] = $topupAgency->id;
                        $topupAgency->balance_amount += $topup->balance_amount;
                        $topupAgency->save();
                    }

                    // update status to done
                    $topup->topup_status = 'done';
                    $topup->save();
                }
                // update balance amount

                // topup status yang awalnya done mungkin bisa diubah menjadi progress atau queue sesuai kondisi
                if(count($topupAgencyIds) > 0)
                {
                    // info('process_refund_balance_wallet 1.4');
                    $topupAgencyProgress = TopupAgency::where('company_id','=',$company_id)
                                                      ->where('topup_status','=','progress')
                                                      ->whereNull('expired_at')
                                                      ->first();
                    if(!empty($topupAgencyProgress))
                    {
                        // info('process_refund_balance_wallet 1.5');
                        $topupAgencyProgress->topup_status = 'queue';
                        $topupAgencyProgress->save();
                    }

                    foreach($topupAgencyIds as $index => $id)
                    {
                        // info('process_refund_balance_wallet 1.6');
                        TopupAgency::where('id', $id)
                                   ->update(['topup_status' => ($index == 0) ? 'progress' : 'queue']);
                    }
                }
                // topup status yang awalnya done mungkin bisa diubah menjadi progress atau queue sesuai kondisi

                // update platform_total_amount invoice charge clean id
                // info('process_refund_balance_wallet 1.7');
                $leadspeekInvoice = LeadspeekInvoice::where('id', $leadspeek_invoices_id)
                                                    ->first();
                if(!empty($leadspeekInvoice))
                {
                    // info('process_refund_balance_wallet 1.8');
                    $leadspeekInvoice->platform_total_amount -= $balanceAmount;
                    $leadspeekInvoice->platform_total_amount = (float) number_format($leadspeekInvoice->platform_total_amount,2,'.','');
                    $leadspeekInvoice->save();
                }
                // update platform_total_amount invoice charge clean id
            }
            else // jika balance amount = 0, maka process refund sebenarnya ngga terjadi, namun tetap mengubah topup_status menjadi done
            {
                TopupCleanId::where('file_id','=',$file_id)
                    ->where('balance_amount','=',0)
                    ->where('topup_status','<>','done')
                    ->update(['topup_status' => 'done']);
            }
        });
    }

    /**
     * Validasi header CSV berdasarkan source provider.
     * @param Request $request
     * @param string $sourceProvider 'bigdbm' | 'wattdata'
     * @return array ['status' => 'success'|'error', 'message' => '']
     */
    private function validateHeadersCSV(Request $request, string $sourceProvider)
    {
        // info('validateHeadersCSV 1.1', ['request' => $request->all(), 'sourceProvider' => $sourceProvider]);

        // ambil file request
        $fileRequest = $request->file('file');

        // ambil expected header berdasarkan source provider
        $expected = ($sourceProvider === 'wattdata') ? ['email','phone','address','maid','full_name'] : ['email'];

        // buka file dan ambil header
        $localPath = $fileRequest->getRealPath();
        $fh = @fopen($localPath, 'r');
        if(!$fh){
            return ['status' => 'error', 'message' => 'Unable to read uploaded file for header validation.'];
        }

        // baca header menggunakan fgetcsv (lebih aman untuk handle quotes)
        $header = fgetcsv($fh);
        // info('validateHeadersCSV 1.2', ['header' => $header]);
        fclose($fh);

        // validasi header
        if($header === false || $header === null){
            return ['status' => 'error', 'message' => 'CSV is empty or unreadable.'];
        }

        // trim setiap value header + hapus BOM dari kolom pertama
        $header = array_map('trim', $header);
        $header[0] = ltrim($header[0], "\xEF\xBB\xBF"); // hapus BOM jika ada di kolom pertama
        // info('validateHeadersCSV 1.3', ['header' => $header, 'expected' => $expected, 'check' => $header !== $expected]);
        
        if($header !== $expected){
            // info('validateHeadersCSV 1.4 header not match expected', ['header' => $header, 'expected' => $expected]);
            return ['status' => 'error', 'message' => 'Invalid CSV header order. Expected: ' . implode(' | ', $expected)];
        }

        return ['status' => 'success', 'message' => ''];
    }

    /**
     * validasi request sebelum running clean id
     */
    public function validateRequestUploadCleanID(Request $request)
    {
        info("validateRequestUploadCleanID 1.1", ['request' => $request->all()]);
        /* VARIABLE */
        $hasFile = $request->hasFile('file') && $request->file('file')->isValid();
        $hasText = $request->filled('raw_text');
        $clientID = $request->userid;
        $user = User::select('users.*','companies.company_name')
                    ->leftjoin('companies','companies.id','=','users.company_id')
                    ->where('users.id','=',$clientID)
                    ->where('users.user_type','=','userdownline')
                    ->where('users.active','=','T')
                    ->first();
        if(empty($user)){
            // info("validateRequestUploadCleanID 1.1");
            return ['status' => 'error', 'status_code' => 404, 'message' => 'Agency not found'];
        }
        /* VARIABLE */


        /* VALIDATION ONLYL EMM */
        $systemid = config('services.application.systemid');
        $company_root_id = $user->company_root_id ?? '';
        if($company_root_id != $systemid){
            // info("validateRequestUploadCleanID 1.2");
            return ['status' => 'error', 'status_code' => 400, 'message' => 'You do not have access to this resource.'];
        }
        /* VALIDATION ONLYL EMM */


        /* VALIDATION ONLY ONE TYPE */
        if($hasFile && $hasText){
            // info("validateRequestUploadCleanID 1.3");
            return ['status' => 'error', 'status_code' => 400, 'message' => 'Please provide either a file OR text input, not both.'];
        }
        if(!$hasFile && !$hasText){
            // info("validateRequestUploadCleanID 1.4");
            return ['status' => 'error', 'status_code' => 400, 'message' => 'You must provide a file OR text input.'];
        }
        /* VALIDATION ONLY ONE TYPE */


        /* GET SERVICE AGREEMENT DATA WALLET */
        $userID = $user['id'] ?? null;
        $featureDataWalletId = MasterFeature::where('slug', 'data_wallet')->value('id');
        $serviceAgreement = ServicesAgreement::where('user_id','=',$userID)->where('feature_id','=',$featureDataWalletId)->first();
        $agencyAgreeDataWallet = (isset($serviceAgreement->status) && $serviceAgreement->status == 'T') ? $serviceAgreement->status : 'F';
        // info('validationPaymentUploadCleanID 1.0', ['agencyAgreeDataWallet' => $agencyAgreeDataWallet]);
        if($agencyAgreeDataWallet != 'T')
            return ['status' => 'error', 'status_code' => 400, 'message' => 'You have not agreed to the Data Wallet terms. Please review and accept the agreement before using this feature.', 'error_type' => 'data_wallet_agreement_required'];
        /* GET SERVICE AGREEMENT DATA WALLET */


        /* VALIDATION PAYMENT STATUS AGENCY FAILED */
        $paymentStatus = $user['payment_status'] ?? '';
        if($paymentStatus == 'failed')
            return ['status' => 'error', 'status_code' => 400, 'message' => "You can't upload a new Clean ID file because your payment status is currently marked as failed. Please resolve the payment issue before continuing."];
        /* VALIDATION PAYMENT STATUS AGENCY FAILED */


        /* GET COST CLEAN ID AGENCY */
        $module = $request->module;
        $moduleArray = !empty($module) ? explode(',', $module) : [];
        $company_id = $user['company_id'] ?? null;
        $company_root_id = $user['company_root_id'] ?? null;
        
        $rootcompanysetting = $this->controller->getcompanysetting($company_root_id, 'rootcostagency');
        $companysetting = $this->controller->getcompanysetting($company_id, 'costagency');
        $cost_cleanid = (isset($companysetting->clean->CleanCostperleadAdvanced)) ? ($companysetting->clean->CleanCostperleadAdvanced) : ($rootcompanysetting->clean->CleanCostperleadAdvanced) ;
        // $cost_cleanid = (isset($companysetting->clean->CleanCostperlead)) ?
        //                 ($companysetting->clean->CleanCostperlead) :
        //                 ($rootcompanysetting->clean->CleanCostperlead) ;
        // if(in_array('advanced', $moduleArray))
        //     $cost_cleanid = (isset($companysetting->clean->CleanCostperleadAdvanced)) ?
        //                     ($companysetting->clean->CleanCostperleadAdvanced) :
        //                     ($rootcompanysetting->clean->CleanCostperleadAdvanced) ;
        // info('validationPaymentUploadCleanID 1.1', ['cost_cleanid' => $cost_cleanid, 'countLines' => $countLines, 'moduleArray' => $moduleArray]);
        /* GET COST CLEAN ID AGENCY */


        /* GET COUNT LINE OR FILE CONTENT */
        $filename = 'filenamedefault';
        $filedownload_url = '';
        $countLines = 0;
        $path = '';
        $source_provider = $request->source_provider ?? 'bigdbm'; // 'bigdbm' | 'wattdata'
        // info('validateRequestUploadCleanID', ['source_provider' => $source_provider]);
        if($hasText) // validation total md5 maximum 100 when upload type text  
        {
            $fileContent = $request->raw_text ?? '';
            $filename = $request->file_name ?? "filenamedefault";
            $lines = array_map('trim', explode(',', $fileContent));
            $lines = array_filter($lines, fn($line) => $line !== '');
            $countLines = count($lines);
            if($countLines > 100){
                // info("validateRequestUploadCleanID 1.5");
                return ['status' => 'error', 'status_code' => 400, 'message' => 'The maximum allowed md5 total is 100'];
            }
        }
        elseif($hasFile) // count lines when upload type file
        {
            try 
            {
                /* configuration for space */
                $fileRequest = $request->file('file');
                $fileExtension = $fileRequest->getClientOriginalExtension();
                $filename = $fileRequest->getClientOriginalName();
                $fileSize = $fileRequest->getSize();
                $fileSizeMB = round($fileSize / 1024 / 1024, 2);
                $epochTime = Carbon::now()->valueOf();
                $randomString = Str::random(10);
                $tmpfile = "cleanid_{$clientID}_{$randomString}_{$epochTime}.{$fileExtension}";
                /* configuration for space */


                /* validation extension */
                if($fileExtension != 'csv'){
                    return ['status' => 'error', 'status_code' => 400, 'message' => 'Only CSV files are allowed.'];
                }
                if($fileSizeMB > 30){
                    return ['status' => 'error', 'status_code' => 400, 'message' => 'File size must be 30MB or less.'];
                }
                /* validation extension */


                /* validate header order based on source_provider */
                $headerCheck = $this->validateHeadersCSV($request, $source_provider);
                if($headerCheck['status'] === 'error'){
                    return ['status' => 'error', 'status_code' => 400, 'message' => $headerCheck['message']];
                }
                /* validate header order based on source_provider */


                /* upload to do spaces */
                $startTime = microtime(true);
                $path = Storage::disk('spaces')->putFileAs('tools/cleanid', $fileRequest, $tmpfile);
                $filedownload_url = Storage::disk('spaces')->url($path);
                $filedownload_url = str_replace('digitaloceanspaces', 'cdn.digitaloceanspaces', $filedownload_url);
                $endTime = microtime(true);
                $diffTime = $endTime - $startTime;

                // get file content by streaming to save memory and count lines
                // info("validateRequestUploadCleanID try 1.6", ['diffTime' => $diffTime, 'filedownload_url' => $filedownload_url]);
                $countLines = 0;
                $stream = @fopen($filedownload_url, 'r');
                if(!$stream){
                    // info("validateRequestUploadCleanID 1.7");
                    throw new \Exception("Failed to open file from CDN: {$filedownload_url}");
                }

                // skip header (baris pertama)
                fgets($stream);

                // count
                while(($line = fgets($stream)) !== false){
                    $line = trim($line);
                    if ($line === '') continue;
                    $countLines++;
                }
                
                // close
                fclose($stream);
                /* upload to do spaces */
            }
            catch(\Exception $e)
            {
                $this->deleteStorageFileCleanID($path);
                $message = $e->getMessage();
                // info("validateRequestUploadCleanID catch 1.9", ['message' => $message]);
                return ['status' => 'error', 'status_code' => 500, 'message' => $message];
            }
        }
        /* GET COUNT LINE OR FILE CONTENT */


        /* VALIDATION WHEN COUNTLINES 0*/
        // info('validationPaymentUploadCleanID 1.3.0', ['countLines' => $countLines, 'hasFile' => $hasFile, 'hasText' => $hasText]);
        if($countLines == 0){
            if($hasFile){
                $this->deleteStorageFileCleanID($path);
            }
            $message = ($hasText) ? 'No valid data found in the provided text input.' : 'The CSV file contains no data rows. Please upload a file with at least one data entry after the header.';
            return ['status' => 'error', 'status_code' => 400, 'message' => $message];
        }
        /* VALIDATION WHEN COUNTLINES 0*/


        /* CREATE CLEAN ID FILE */
        $file_type = $request->file_type ?? 'Manual';
        $source_type = $request->source_type ?? 'ui';
        $phone_number = filter_var(($request->phone_number ?? false), FILTER_VALIDATE_BOOLEAN) ? 'T' : 'F';
        $home_address = filter_var(($request->home_address ?? false), FILTER_VALIDATE_BOOLEAN) ? 'T' : 'F';
        $require_email = filter_var(($request->require_email ?? false), FILTER_VALIDATE_BOOLEAN) ? 'T' : 'F';
        $advance_information = (isset($request->advance_information) && is_array($request->advance_information)) ? implode(',', $request->advance_information) : null;
        $clean_api_id = $this->generateCleanApiId();
        // info('validationPaymentUploadCleanID 1.3', ['advance_information' => $advance_information]);
        $fileModel = CleanIDFile::create([
            'clean_api_id' => $clean_api_id,
            'file_name' => $filename,
            'user_id' => $userID,
            'company_id' => $company_id,
            'status' => 'pending',
            'file_type' => $file_type,
            'source_type' => $source_type,
            'entries' => $countLines,
            'platform_cost_leads' => $cost_cleanid,
            'module' => $module,
            'phoneenabled' => $phone_number,
            'homeaddressenabled' => $home_address,
            'require_email' => $require_email,
            'advance_information' => $advance_information,
            'source_provider' => $source_provider,
        ]);
        $fileID = $fileModel->id;
        // info('validationPaymentUploadCleanID 1.2', ['fileID' => $fileID, 'fileModel' => $fileModel]);
        /* CREATE CLEAN ID FILE */


        return [
            'status' => 'success', 
            'status_code' => 200, 
            'file_id' => $fileID,
            'countLines' => $countLines, 
            'filename' => $filename,
            'filedownload_url' => $filedownload_url, 
            'clean_api_id' => $clean_api_id,
            'path' => $path, 
            'user' => $user->toArray()
        ];
    }

    /**
     * process untuk queue clean id
     */
    public function processQueueCleanID(Request $request, array $user, ?string $file_id, float $cost_cleanid, string $filedownload_url, string $path, int $countLines)
    {
        info("processQueueCleanID 1.1", ['request' => $request->all(), 'file_id' => $file_id, 'cost_cleanid' => $cost_cleanid]);
        /* VARIABLE */
        $app_url = $request->app_url ?? '';
        $hasFile = $request->hasFile('file') && $request->file('file')->isValid();
        $hasText = $request->filled('raw_text');
        $companyID = $user['company_id'] ?? null;
        $chunkSize = 10;
        $source_provider = $request->source_provider ?? 'bigdbm'; // 'bigdbm' | 'wattdata'
        /* VARIABLE */

        /* PROCESS INSERT MD5 TO CLEANIDMD5 */
        if($hasText) // jika type nya text
        {
            $fileContent = $request->raw_text ?? '';
            $lines = array_map('trim', explode(',', $fileContent));
            $lines = array_filter($lines, fn($line) => $line !== '');
            $now = now();
            $dataToInsert = [];
            foreach($lines as $line) 
            {
                $line = trim($line);
                if($line !== '') 
                {
                    // Jika $line adalah email, convert ke lowercase, hapus plus addressing, dan hapus titik
                    if(filter_var($line, FILTER_VALIDATE_EMAIL)){
                        $line = strtolower($line);
                        // Hapus plus addressing (e.g., jidan+testing@gmail.com -> jidan@gmail.com)
                        if(strpos($line, '+') !== false){
                            $parts = explode('@', $line);
                            if(count($parts) == 2){
                                $localPart = explode('+', $parts[0])[0];
                                $line = $localPart . '@' . $parts[1];
                            }
                        }
                        // Hapus titik sebelum @ (e.g., jidan.test@gmail.com -> jidantest@gmail.com)
                        $parts = explode('@', $line);
                        if(count($parts) == 2){
                            $localPart = str_replace('.', '', $parts[0]);
                            $line = $localPart . '@' . $parts[1];
                        }
                    }
                    $dataToInsert[] = [
                        'file_id' => $file_id,
                        'md5' => $line,
                        'status' => 'processing',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
            // info('processQueueCleanID hasText 1.2', ['dataToInsert' => $dataToInsert]);
            CleanIDMd5::insert($dataToInsert);

            /* CALCULATE TOTAL JOB */
            $countData = CleanIDMd5::select('clean_id_md5.id', 'clean_id_md5.file_id', 'clean_id_md5.md5', 'clean_id_file.module', 'clean_id_file.advance_information', 'clean_id_file.user_id')
                                   ->join('clean_id_file', 'clean_id_file.id', '=', 'clean_id_md5.file_id')
                                   ->where('clean_id_md5.file_id', $file_id)
                                   ->where('clean_id_md5.status', 'processing')
                                   ->orderBy('clean_id_md5.id', 'asc')
                                   ->count();
            $totalJobs = (int) ceil($countData / $chunkSize);
            // info('processQueueCleanID 1.3', ['countData' => $countData, 'totalJobs' => $totalJobs]);
            /* CALCULATE TOTAL JOB */

            /* UPDATE CLEAN ID FILE */
            CleanIDFile::where('id', $file_id)->update(['total_jobs' => $totalJobs, 'jobs_completed' => 0]);
            /* UPDATE CLEAN ID FILE */

            /* CHUNK MD5 PER 10 */
            CleanIDMd5::select('clean_id_md5.id','clean_id_md5.file_id','clean_id_md5.md5','clean_id_md5.phone','clean_id_md5.address','clean_id_md5.maid','clean_id_file.module','clean_id_file.advance_information','clean_id_file.user_id')
                      ->join('clean_id_file', 'clean_id_file.id', '=', 'clean_id_md5.file_id')
                      ->where('clean_id_md5.file_id', $file_id)
                      ->where('clean_id_md5.status', 'processing')
                      ->orderBy('clean_id_md5.id', 'asc')
                      ->chunk($chunkSize, function ($chunk) use ($source_provider, $companyID, $file_id, $cost_cleanid, $app_url) {
                            // info('processQueueCleanID chunkjob 1.4', ['chunk' => $chunk]);
                            CleanIDJob::dispatch($chunk->toArray(), $source_provider, $companyID, $file_id, $cost_cleanid, $app_url, 'manual')
                                      ->onQueue('clean_id')
                                      ->onConnection('redis');
                      });
            /* CHUNK MD5 PER 10 */
        }
        elseif($hasFile) // jika type nya file
        {
            // info('processQueueCleanID hasFile CleanIDMd5StreamJob 2.1');
            CleanIDMd5StreamJob::dispatch($source_provider, $companyID, $file_id, $cost_cleanid, $filedownload_url, $path, $countLines, $chunkSize, $app_url)
                               ->onQueue('clean_id_md5_stream')
                               ->onConnection('redis');
        }
        /* PROCESS INSERT MD5 TO CLEANIDMD5 */

        return ['status' => 'success', 'status_code' => 200, 'message' => 'success process queue clean id'];
    }

    /**
     * untuk menghapus file di storage
     */
    public function deleteStorageFileCleanID(string $path)
    {
        // info('CleanID -> deleteStorageFileCleanID -> start', ['path' => $path, 'exists' => Storage::disk('spaces')->exists($path)]);
        try
        {
            if(Storage::disk('spaces')->exists($path))
            {
                $result = Storage::disk('spaces')->delete($path);
                // info('CleanID -> deleteStorageFileCleanID -> success', ['path' => $path, 'result' => $result]);
            }
        }
        catch(\Exception $e)
        {
            // info('CleanID -> deleteStorageFileCleanID -> error', ['path' => $path, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Untuk Menghapus Key Redis
     */
    public function cacheForget(string $cacheKey)
    {
        try{
            return Cache::store('redis')->forget($cacheKey);
        }catch(\Exception $e){
            return null;
        }
    }

    /**
     * Untuk Mengecek Apakah Key Ini Ada Atau Tidak Di Redis, Jika Ada True, Jika Tidak Ada False
     */
    public function cacheHasResult(string $cacheKey)
    {
        try{
            return Cache::store('redis')->has($cacheKey);
        }catch(\Exception $e){
            return null;
        }
    }

    /**
     * Untuk Mengambil Value Dari Key Redis
     */
    public function cacheGetResult(string $cachekey)
    {
        try{
            return Cache::store('redis')->get($cachekey);
        }catch(\Exception $e){
            return null;
        }
    }

    /**
     * Ji
     */
    public function cacheQueryResult(string $cacheKey, int $ttl, Closure $query)
    {
        try{
            return Cache::store('redis')->remember($cacheKey, $ttl, $query);
        }catch(\Exception $e){
            Log::info("cacheQueryResult error : {$e->getMessage()}");
            return null;
        }
    }

    /**
     * untuk sending email invoice clean id
     */
    public function sendEmailInvoiceCleanID(string $company_id_agency, string $clean_file_id, string $leadspeek_invoices_id, string $cost_cleanid, string $statusPayment)
    {
        try
        {
            // info("CleanID -> sendEmailInvoiceCleanID -> start", ['company_id_agency' => $company_id_agency, 'clean_file_id' => $clean_file_id, 'leadspeek_invoices_id' => $leadspeek_invoices_id, 'cost_cleanid' => $cost_cleanid, 'url_download' => $url_download]);

            /* FIND ROOT */
            $agency = User::where('company_id', $company_id_agency)->where('user_type', 'userdownline')->first();
            $agency_id = $agency->id ?? "";
            $agency_email = $agency->email ?? "";
            $agency_name = $agency->name ?? "";
            $company_root_id = $agency->company_root_id ?? "";
            $company_parent = $agency->company_parent ?? "";
            // info("CleanID -> sendEmailInvoiceCleanID -> FIND ROOT", ['agency' => $agency]);
            /* FIND ROOT */

            /* FIND LEADSPEEK INVOICE */
            $leadspeekInvoice = LeadspeekInvoice::where('id', $leadspeek_invoices_id)->first();
            /* FIND LEADSPEEK INVOICE */
            
            /* FIND CLEAN ID FILE */
            $cleanIdFile = CleanIDFile::select('clean_id_file.*', 'clean_id_export.app_url')
                ->leftJoin('clean_id_export', 'clean_id_export.file_id', '=', 'clean_id_file.id')
                ->where('clean_id_file.id', $clean_file_id)
                ->first();

            $clean_api_id = $cleanIdFile->clean_api_id ?? "";
            $title = $cleanIdFile->file_name ?? "";
            $name = "{$agency_name} - {$title}";
            $type = $cleanIdFile->file_type ?? "";
            $date = Carbon::parse($cleanIdFile->created_at)->format('d-m-Y');
            $invoice_number = $leadspeekInvoice->invoice_number ?? "";
            $total_entries = $cleanIdFile->entries ?? 0;
            $total_found = $cleanIdFile->entries_found ?? 0;
            $total_not_found = $cleanIdFile->entries_not_found ?? 0;
            $total_duplicate = $cleanIdFile->entries_duplicate ?? 0;
            $cost_cleanid = number_format($cost_cleanid,2,'.','');
            $agency_cost = number_format($cost_cleanid * $total_found,2,'.','');
            $app_url = $cleanIdFile->app_url ?? "";
            $url_download = "{$app_url}/configuration/clean-id/download-result/{$company_id_agency}/{$clean_file_id}/true";
            // info("CleanID -> sendEmailInvoiceCleanID -> FIND CLEAN ID FILE");
            /* FIND CLEAN ID FILE */
    
            /* SETUP DETAILS */
            $controller = App::make(Controller::class);
            
            $AdminDefault = $controller->get_default_admin($company_root_id);
            $AdminDefaultEmail = (isset($AdminDefault[0]['email']))?$AdminDefault[0]['email']:'';
            $rootCompanyInfo = $controller->getCompanyRootInfo($company_root_id);
            $defaultdomain = $controller->getDefaultDomainEmail($company_root_id);

            $titleEmail = "Invoice Clean ID for {$agency_name} - {$title} #{$clean_api_id} ({$date})";
            if($statusPayment == 'failed'){
                $titleEmail = "Failed Payment - $titleEmail";
            }

            $details = [
                'leadspeek_type' => 'clean_id',
                'clean_api_id' => $clean_api_id,
                'name' => $name,
                'title' => $title,
                'type' => $type,
                'date' => $date,
                'url_download' => $url_download,
                'invoice_number' => $invoice_number,
                'total_entries' => $total_entries,
                'total_found' => $total_found,
                'total_not_found' => $total_not_found,
                'total_duplicate' => $total_duplicate,
                'cost_per_contact' => $cost_cleanid,
                'agency_cost' => $agency_cost,
                'agencyname' => $rootCompanyInfo['company_name'] ?? "",
                'payment_status' => $statusPayment,
                'defaultadmin' => $AdminDefaultEmail,
            ];
            $attachement = [];
            $from = [
                'address' => "noreply@$defaultdomain",
                'name' => 'Invoice',
                'replyto' => "support@$defaultdomain",
            ];
            // info("CleanID -> sendEmailInvoiceCleanID -> SETUP DETAILS", ['details' => $details]);
            /* SETUP DETAILS */
            
            $controller->send_email([$agency_email], $titleEmail, $details, $attachement, 'emails.tryseramatchlistcharge', $from, $company_parent, true);
        }
        catch(\Exception $e)
        {
            Log::error("sendEmailInvoiceCleanID error : {$e->getMessage()}");
        }
    }

    /**
     * untuk charge agency clean id, $file_download_url hanya ada ketika file type nya upload
     */
    public function chargeCleanID(string $clean_file_id, string $file_download_url = "")
    {
        info("chargeCleanID 1.1 - start");

        /* PROCESS LOCK WITH REDIS */
        $cleanIDFile = CleanIDFIle::where('id', $clean_file_id)->first();
        $file_type = $cleanIDFile->file_type ?? '';
        $clean_api_id = $cleanIDFile->clean_api_id ?? '';
        $company_id = $cleanIDFile->company_id ?? null;
        
        $lockKey = "clean_id_charge_{$clean_file_id}_{$clean_api_id}";
        $lock = Cache::store('redis')->lock($lockKey, 100); // TTL 100 detik
        if(!$lock->get()){
            // info("chargeCleanID 1.2 - LOCKED sudah ada proses lain yg lagi nge-charge clean_file_id : {$clean_file_id} | clean_api_id : {$clean_api_id} | company_id : {$company_id} | lockKey : {$lockKey}");
            return;
        }
        /* PROCESS LOCK WITH REDIS */

        try 
        {
            /* VARIABLE CHARGE */
            $payment_intent = "";
            $errorstripe = "";

            $platform_cost_leads = $cleanIDFile->platform_cost_leads ?? 0;
            $total_md5_found = CleanIDMd5::where('file_id', $clean_file_id)->where('status', 'found')->count();
            $total_md5_not_found = CleanIDMd5::where('file_id', $clean_file_id)->where('status', 'not_found')->count();
            $total_md5_duplicate = CleanIDMd5::where('file_id', $clean_file_id)->where('status', 'duplicate')->count();
            CleanIDFile::where('id', $clean_file_id)->update(['entries_found' => $total_md5_found,'entries_not_found' => $total_md5_not_found,'entries_duplicate' => $total_md5_duplicate]);
            $total_cost = number_format($total_md5_found * $platform_cost_leads,2,'.','');
            
            $campaign_paymentterm = "clean_id";
            $topupAgencyExists = false;
            $remainingPlatformfee = 0;
            $statusPayment = "";
            $statusPaymentClient = "";
            $errorstripeClient = "";
            $topup_agencies_id = "";
            $leadspeek_invoices_id = "";
            $chargeStatusWithPrepaid = "";
            $applicationFeeAmount = 0;

            $chkUser = User::select('users.*','companies.company_name')
                ->leftjoin('companies','companies.id','=','users.company_id')
                ->where('users.company_id','=',$company_id)
                ->where('users.company_parent','<>',$company_id)
                ->where('users.user_type','=','userdownline')
                ->get();
            $agency_id = isset($chkUser[0]['id']) ? $chkUser[0]['id'] : null;
            $agency_stop_continual = isset($chkUser[0]['stopcontinual']) ? $chkUser[0]['stopcontinual'] : 'F';
            $agency_amount = isset($chkUser[0]['amount']) ? $chkUser[0]['amount'] : 0;
            $agency_custom_amount = isset($chkUser[0]['custom_amount']) ? $chkUser[0]['custom_amount'] : 'F';
            $agency_payment_type = "credit_card";
            $agency_ip_user = "";
            $agency_timezone = "";

            // $agency_last_balance_amount = $user['last_balance_amount'] ?? 0; // dulu 10% auto topup data wallet itu acuan dari last_balance_amount
            $agency_last_balance_amount = $agency_amount; // sekarang 10% auto topup data wallet acuan nya dari amount yang di setup
            $agency_balance_threshold = ($agency_last_balance_amount * 10 / 100);
            $agency_balance_threshold = (float) number_format($agency_balance_threshold,2,'.','');

            $company_root_id = isset($chkUser[0]['company_root_id']) ? $chkUser[0]['company_root_id'] : null;
            $stripeseckey = config('services.stripe.secret');
            $stripepublish = $this->controller->getcompanysetting($company_root_id,'rootstripe');
            if ($stripepublish != '') {
                $stripeseckey = (isset($stripepublish->secretkey))?$stripepublish->secretkey:"";
            }

            $defaultInvoice = "Agency Charge Clean ID #{$clean_api_id}";
            $transferGroup = "AI_CLEANID_{$agency_id}_{$clean_api_id}_" . uniqid();
            $leadspeek_api_id = $clean_api_id;
            $agency_name = isset($chkUser[0]['name']) ? $chkUser[0]['name'] : "";
            $client_name = "";
            $campaign_name = "";

            $dataCharge = [
                'charge_type' => 'cleanid',
                'file_id' => $clean_file_id,
                'cost_cleanid' => $platform_cost_leads,
            ];
            /* VARIABLE CHARGE */


            /* PROCESS CHARGE WITH CLEANID */
            // info("chargeCleanID 1.3 - process_charge_agency_wallet", ['company_id' => $company_id, 'total_cost' => $total_cost, 'campaign_paymentterm' => $campaign_paymentterm, 'topupAgencyExists' => $topupAgencyExists, 'remainingPlatformfee' => $remainingPlatformfee, 'statusPayment' => $statusPayment, 'statusPaymentClient' => $statusPaymentClient, 'errorstripeClient' => $errorstripeClient, 'topup_agencies_id' => $topup_agencies_id, 'leadspeek_invoices_id' => $leadspeek_invoices_id, 'chargeStatusWithPrepaid' => $chargeStatusWithPrepaid, 'applicationFeeAmount' => $applicationFeeAmount, 'agency_stop_continual' => $agency_stop_continual, 'agency_amount' => $agency_amount, 'agency_custom_amount' => $agency_custom_amount, 'agency_payment_type' => $agency_payment_type, 'agency_ip_user' => $agency_ip_user, 'agency_timezone' => $agency_timezone, 'agency_balance_threshold' => $agency_balance_threshold, 'stripeseckey' => $stripeseckey, 'chkUser' => $chkUser, 'defaultInvoice' => $defaultInvoice, 'transferGroup' => $transferGroup, 'leadspeek_api_id' => $leadspeek_api_id, 'agency_name' => $agency_name, 'client_name' => $client_name, 'campaign_name' => $campaign_name, 'dataCharge' => $dataCharge]);
            $this->controller->process_charge_agency_wallet($company_id,$total_cost,$campaign_paymentterm,$topupAgencyExists, $remainingPlatformfee, $statusPayment, $statusPaymentClient, $errorstripeClient, $topup_agencies_id, $leadspeek_invoices_id, $chargeStatusWithPrepaid, $applicationFeeAmount, $agency_stop_continual, $agency_amount, $agency_custom_amount, $agency_payment_type, $agency_ip_user, $agency_timezone, $agency_balance_threshold, $stripeseckey, $chkUser, $defaultInvoice, $transferGroup, $leadspeek_api_id, $agency_name, $client_name, $campaign_name, $dataCharge);
            /* PROCESS CHARGE WITH CLEANID */


            /* PROCESS CHARGE WITH STRIPE */
            if(!$topupAgencyExists)
            {
                $resultCharge = $this->controller->process_charge_agency_stripeinfo($stripeseckey,$chkUser[0]['customer_payment_id'],$total_cost,$chkUser[0]['email'],$chkUser[0]['customer_card_id'],$defaultInvoice,$transferGroup,$leadspeek_api_id,$agency_name,$client_name,$campaign_name,$chkUser[0]['company_root_id']);
                $payment_intent = (isset($resultCharge['payment_intent'])) ? $resultCharge['payment_intent'] : '';
                $statusPayment = (isset($resultCharge['statusPayment'])) ? $resultCharge['statusPayment'] : '';
                $errorstripe = (isset($resultCharge['errorstripe'])) ? $resultCharge['errorstripe'] : '';
                // info("chargeCleanID 1.4 - process_charge_agency_stripeinfo", ['resultCharge' => $resultCharge]);
            }
            /* PROCESS CHARGE WITH STRIPE */


            /* PROCESS TRANSFER COMMISSION SALES */
            // info("chargeCleanID 1.5 - BEFORE PROCESS TRANSFER COMMISSION SALES", ['statusPayment' => $statusPayment, 'chargeStatusWithPrepaid' => $chargeStatusWithPrepaid, 'topupAgencyExists' => $topupAgencyExists, 'applicationFeeAmount' => $applicationFeeAmount]);
            if(($statusPayment != 'failed' && $chargeStatusWithPrepaid != 'failed') && (!$topupAgencyExists || $applicationFeeAmount > 0))
            {
                // info("chargeCleanID 1.6 - START PROCESS TRANSFER COMMISSION SALES");
                $amount = $total_cost;
                // info('amount before', ['amount' => $amount, 'applicationFeeAmount' => $applicationFeeAmount]);
                if($topupAgencyExists && $applicationFeeAmount > 0)
                    $amount = $applicationFeeAmount;
                // info('amount after', ['amount' => $amount, 'applicationFeeAmount' => $applicationFeeAmount]);

                $sourceTransaction = ""; 
                if(is_object($payment_intent) && isset($payment_intent->charges->data[0]->id) && $payment_intent->charges->data[0]->id != '')
                    $sourceTransaction = $payment_intent->charges->data[0]->id;
                // info('', ['sourceTransaction' => $sourceTransaction]);

                $dataCustomCommissionSales = ['type' => 'clean_id'];
                $transferCommissionSales = $this->controller->transfer_commission_sales($company_id,$amount,$leadspeek_api_id,date('Y-m-d 00:00:00'),date('Y-m-d 23:59:59'),$stripeseckey,$amount,"",$dataCustomCommissionSales,$transferGroup,$sourceTransaction);
                $transferCommissionSales = json_decode($transferCommissionSales);
                $srID = $transferCommissionSales->srID ?? 0;
                $aeID = $transferCommissionSales->aeID ?? 0;
                $arID = $transferCommissionSales->arID ?? 0;
                $srFee = $transferCommissionSales->srFee ?? 0;
                $aeFee = $transferCommissionSales->aeFee ?? 0;
                $arFee = $transferCommissionSales->arFee ?? 0;
                $srTransferID = $transferCommissionSales->srTransferID ?? '';
                $aeTransferID = $transferCommissionSales->aeTransferID ?? '';
                $arTransferID = $transferCommissionSales->arTransferID ?? '';
            }
            /* PROCESS TRANSFER COMMISSION SALES */


            /* CHECK IF AGENCY PAYMENT FAILED OR NOT FAILED */
            if($statusPayment == 'failed')
            {
                // info("chargeCleanID 1.7 - CHECK IF AGENCY PAYMENT FAILED OR NOT FAILED (IF)");
                $updateUser = User::find($agency_id);
                
                $failedTotalAmount = $total_cost;
                if($topupAgencyExists && $remainingPlatformfee > 0)
                {
                    $failedTotalAmount = $remainingPlatformfee;
                }

                $failedCampaignID = $clean_api_id;
                if(trim($updateUser->failed_total_amount) != '') 
                {
                    $failedTotalAmount = $updateUser->failed_total_amount . '|' . $failedTotalAmount;
                }
                if(trim($updateUser->failed_campaignid) != '') 
                {
                    $failedCampaignID = $updateUser->failed_campaignid . '|' . $failedCampaignID;
                }

                if(!$topupAgencyExists || $remainingPlatformfee > 0)
                {
                    $updateUser->failed_total_amount = $failedTotalAmount;
                    $updateUser->failed_campaignid = $failedCampaignID;
                }

                $updateUser->payment_status = 'failed';
                $updateUser->save();

                // pakai agency prepaid && chargeCampaignWithPrepaid = 'paid'
                if($topupAgencyExists && $chargeStatusWithPrepaid == 'paid')
                {
                    // info("chargeCleanID 1.8 - pakai agency prepaid && chargeCampaignWithPrepaid = 'paid'");
                    $statusPayment = 'paid';
                }
            }
            else
            {
                // info("chargeCleanID 1.9 - CHECK IF AGENCY PAYMENT FAILED OR NOT FAILED (ELSE)");
                // clear payment status client
                $updateUser = User::where('id', $agency_id)->first();
                if($updateUser && empty($updateUser->failed_campaignid) && empty($updateUser->failed_total_amount))
                {
                    $updateUser->payment_status = '';
                    $updateUser->save();
                }
                // clear payment status client
            }
            /* CHECK IF AGENCY PAYMENT FAILED OR NOT FAILED */


            /* CREATE OR UPDATE LEADSPEEK INVOICES */
            $createdAtCleanFile = Carbon::parse($cleanIDFile->created_at)->format('Ymd');
            if(!empty($leadspeek_invoices_id))
            {
                $invoice_number = "{$createdAtCleanFile}-{$clean_api_id}-{$leadspeek_invoices_id}";
                // info("chargeCleanID 1.10 - CREATE OR UPDATE LEADSPEEK INVOICES (UPDATE)");
                LeadspeekInvoice::where('id', $leadspeek_invoices_id)
                    ->update([
                        'leadspeek_api_id' => $clean_api_id,
                        'company_id' => $company_id,
                        'user_id' => $agency_id,
                        'clean_file_id' => $clean_file_id,
                        'invoice_type' => 'clean_id',
                        'status' => $statusPayment,
                        'invoice_number' => $invoice_number,
                        'platform_cost_leads' => $platform_cost_leads,
                        'platform_total_amount' => $total_cost,
                        'sr_id' => $srID ?? 0,
                        'sr_fee' => $srFee ?? 0,
                        'sr_transfer_id' => $srTransferID ?? "",
                        'ae_id' => $aeID ?? 0,
                        'ae_fee' => $aeFee ?? 0,
                        'ae_transfer_id' => $aeTransferID ?? "",
                        'ar_id' => $arID ?? 0,
                        'ar_fee' => $arFee ?? 0,
                        'ar_transfer_id' => $arTransferID ?? "",
                        'active' => 'T',
                    ]);
            }
            else
            {
                // info("chargeCleanID 1.11 - CREATE OR UPDATE LEADSPEEK INVOICES (UPDATE)");
                $leadspeekInvoice = LeadspeekInvoice::create([
                    'leadspeek_api_id' => $clean_api_id,
                    'invoice_type' => 'clean_id',
                    'clean_file_id' => $clean_file_id,
                    'company_id' => $company_id,
                    'user_id' => $agency_id,
                    'status' => $statusPayment,
                    'invoice_date' => date('Y-m-d'),
                    'invoice_start' => date('Y-m-d'),
                    'invoice_end' => date('Y-m-d'),
                    'platform_cost_leads' => $platform_cost_leads,
                    'platform_total_amount' => $total_cost,
                    'sr_id' => $srID ?? 0,
                    'sr_fee' => $srFee ?? 0,
                    'sr_transfer_id' => $srTransferID ?? "",
                    'ae_id' => $aeID ?? 0,
                    'ae_fee' => $aeFee ?? 0,
                    'ae_transfer_id' => $aeTransferID ?? "",
                    'ar_id' => $arID ?? 0,
                    'ar_fee' => $arFee ?? 0,
                    'ar_transfer_id' => $arTransferID ?? "",
                    'active' => 'T',
                ]);
                $leadspeek_invoices_id = $leadspeekInvoice->id;

                $invoice_number = "{$createdAtCleanFile}-{$clean_api_id}-{$leadspeek_invoices_id}";
                LeadspeekInvoice::where('id', $leadspeek_invoices_id)->update(['invoice_number' => $invoice_number]);
            }
            /* CREATE OR UPDATE LEADSPEEK INVOICES */


            /* SEND EMAIL CLEAN ID */
            // info("chargeCleanID 1.12 - sendEmailInvoiceCleanID");
            $this->sendEmailInvoiceCleanID($company_id, $clean_file_id, $leadspeek_invoices_id, $platform_cost_leads, $statusPayment);
            /* SEND EMAIL CLEAN ID */


            /* USER LOG CHARGE CLEAN ID */
            // info("chargeCleanID 1.13 - userLogChargeCleanID");
            $this->userLogChargeCleanID($clean_file_id, $leadspeek_invoices_id, $total_cost, $statusPayment);
            /* USER LOG CHARGE CLEAN ID */


            /* SEND WEBHOOK CLEAN ID */
            // info("chargeCleanID 1.14 - sendWebhookCleanID");
            // $this->sendWebhookCleanID($clean_file_id, $total_cost, $statusPayment); // ini nanti kalo cleanid sudah berjalan normal dan ada yang minta webhook invoice nya
            /* SEND WEBHOOK CLEAN ID */
        }
        catch(\Throwable $e)
        {
            $message = $e->getMessage();
            info('chargeCleanID catch', ['error' => $message]);
            /* LOG ERROR TO CLEAN ID ERROR */
            CleanIDError::create([
                'file_id' => $clean_file_id ?? null,
                'name' => 'chargeCleanID',
                'exception' => $e->getMessage(),
                'code' => $e->getCode(),
                'trace' => $e->getTraceAsString(),
            ]);
            /* LOG ERROR TO CLEAN ID ERROR */
        }
        finally
        {   
            info('chargeCleanID finally');
            optional($lock)->release(); // pastikan lock dilepas
            CleanIDFile::where('id', $clean_file_id)->update(['status' => 'done']);  // pastikan status nya dirubah menjadi done
            if(strtolower($file_type) == 'upload'){
                CleanIDExport::where('file_id', $clean_file_id)->update(['status' => 'done', 'file_download' => $file_download_url]); // jika type nya upload, pastikan status nya dirubah menjadi done dan file_download diisi
            }
        }
    }

    /**
     * untuk log user, setelah charge clean id
     */
    public function userLogChargeCleanID(string $clean_file_id, string $leadspeek_invoices_id, string $total_cost, string $statusPayment)
    {
        // info("public function userLogChargeCleanID");
        try 
        {
            $cleanIDFile = CleanIDFile::where('id', $clean_file_id)->first();
            $clean_api_id = $cleanIDFile->clean_api_id ?? '';
            $user_id = $cleanIDFile->user_id ?? null;
            $company_id = $cleanIDFile->company_id ?? null;
            $platform_cost_leads = $cleanIDFile->platform_cost_leads ?? 0;
            $source_type = $cleanIDFile->source_type ?? '';
            $file_type = $cleanIDFile->file_type ?? '';
            $file_name = $cleanIDFile->file_name ?? '';
            $advance_information = $cleanIDFile->advance_information ?? '';
            $total_entries = $cleanIDFile->entries ?? 0;
            $total_entries_found = $cleanIDFile->entries_found ?? 0;
            $total_entries_not_found = $cleanIDFile->entries_not_found ?? 0;
            $total_entries_duplicate = $cleanIDFile->entries_duplicate ?? 0;
            $ipAddress = "";
            $statusPayment = ($statusPayment == 'failed') ? 'failed' : 'paid';

            $action = "Clean ID Charge";
            if($statusPayment == 'failed'){
                $action .= " Failed Payment";
            }

            $descLists = [
                "payment status : {$statusPayment}",
                "source type : {$source_type}",
                "file type : {$file_type}",
                "file name : {$file_name}",
                "clean api id : {$clean_api_id}",
                "company id : " . ($company_id ?? ""),              
                "advanced information : {$advance_information}",
                "cost per contact : {$platform_cost_leads}",
                "total cost agency : {$total_cost}",
                "invoice id : {$leadspeek_invoices_id}",
                "total entries : {$total_entries}",
                "total entries found : {$total_entries_found}",
                "total entries not found : {$total_entries_not_found}",
                "total entries duplicate : {$total_entries_duplicate}",
            ];
            $desc = implode(" | ", $descLists);
            $this->controller->logUserAction($user_id,$action,$desc,$ipAddress,$user_id);
        }
        catch(\Exception $e)
        {
            /* LOG ERROR TO CLEAN ID ERROR */
            $message = $e->getMessage();
            info('userLogChargeCleanID catch', ['error' => $message]);
            CleanIDError::create([
                'file_id' => $clean_file_id ?? null,
                'name' => 'userLogChargeCleanID',
                'exception' => $e->getMessage(),
                'code' => $e->getCode(),
                'trace' => $e->getTraceAsString(),
            ]);
            /* LOG ERROR TO CLEAN ID ERROR */
        }
    }

    /**
     * untuk send webhook invoice clean id
     */
    public function sendWebhookCleanID(string $clean_file_id, string $total_cost, string $statusPayment)
    {
        // info("public function sendWebhookCleanID");
        try 
        {
            $cleanIDFile = CleanIDFile::where('id', $clean_file_id)->first();
            $company_id = $cleanIDFile->company_id ?? null;
            $clean_api_id = $cleanIDFile->clean_api_id ?? '';
            $platform_cost_leads = $cleanIDFile->platform_cost_leads ?? 0;
            $total_entries = $cleanIDFile->entries ?? 0;

            $webhookEvent = ($statusPayment == 'failed') ? 'invoice.payment_failed' : 'invoice.succeeded';
            $webhookSendPayload = [
                'type' => $webhookEvent,
                'created' => Carbon::now()->timestamp,
                'data' => [
                    'clean' => [
                        'id' => (int) $clean_api_id,
                        'name' => $usrInfo['campaign_name'] ?? null,
                        'payment_term' => null,
                    ],
                    'client' => [
                        'id' => null,
                        'name' => null,
                        'email' => null,
                    ],
                    'pricing' => [
                        'client' => [
                            'setup_fee' => null,
                            'campaign_fee' => null,
                            'cost_per_lead' => null,
                            'total_leads' => null,
                            'total_amount' => null,
                        ],
                        'agency' => [
                            'setup_fee' => null,
                            'campaign_fee' => null,
                            'cost_per_lead' => (float) $platform_cost_leads,
                            'total_leads' => (int) $total_entries,
                            'total_amount' => (float) $total_cost,
                        ]
                    ]
                ],
            ];
            // info(['webhookSendPayload' => $webhookSendPayload]);
            $openApiWebhookService = App::make(OpenApiWebhookService::class);
            $openApiWebhookService->sendWebhookEndpoint($company_id, $webhookEvent, $webhookSendPayload);
        }
        catch(\Exception $e)
        {
            $message = $e->getMessage();
            info('sendWebhookCleanID catch', ['error' => $message]);
            /* LOG ERROR TO CLEAN ID ERROR */
            CleanIDError::create([
                'file_id' => $clean_file_id ?? null,
                'name' => 'sendWebhookCleanID',
                'exception' => $e->getMessage(),
                'code' => $e->getCode(),
                'trace' => $e->getTraceAsString(),
            ]);
            /* LOG ERROR TO CLEAN ID ERROR */
        }
    }

    /**
     * untuk generate clean api id, dan kalau bisa id nya unik dari leadspeek_api_id dan clean_api_id
     */
    private function generateCleanApiId()
    {
        $randomCode = mt_rand(1000000000,9999999999);
        while(
            LeadspeekUser::where('leadspeek_api_id', $randomCode)->exists() ||
            CleanIDFile::where('clean_api_id', $randomCode)->exists()
        ) {
            $randomCode = mt_rand(1000000000,9999999999);
        }
        return $randomCode;
    }

    private function debugging(float $sleep = 0.5, string $title = "", array $data = [])
    {
        $microsleep = $sleep * 1000000;
        if(count($data) > 0){
            Log::info($title, $data); 
        }else{
            Log::info($title); 
        }    
        usleep($microsleep);
    }
}
