<?php

namespace App\Jobs;

use Exception;
use Carbon\Carbon;
use App\Services\BigDBM;
use App\Models\CleanIDResult;
use App\Models\CleanIDResultB2B;
use App\Models\CleanIDMd5;
use App\Models\CleanIDFile;
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
use App\Models\User;
use App\Models\OptoutList;
use Illuminate\Support\Facades\DB;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use ESolution\DBEncryption\Encrypter;
use Illuminate\Support\Facades\App;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\Controller;
use App\Models\CleanIDError;
use App\Models\FailedRecord;
use App\Models\LeadspeekInvoice;
use App\Models\TopupAgency;
use App\Models\TopupCleanId;
use App\Services\CleanID;

class CleanIDJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    private $md5Batch;
    private $company_id;
    private $file_id;
    private $cost_cleanid;
    private $leadspeek_invoices_id;
    private $file_type;
    private $batch_id; // variable ini hanya digunakan untuk file type upload
    private $delayRelease = 15; // 1 menit

    public function __construct(array $md5Batch, $company_id, $file_id, $cost_cleanid, $leadspeek_invoices_id, $file_type = 'manual', $batch_id = "")
    {
        $this->md5Batch = $md5Batch;
        $this->company_id = $company_id;
        $this->file_id = $file_id;
        $this->cost_cleanid = $cost_cleanid;
        $this->leadspeek_invoices_id = $leadspeek_invoices_id;
        $this->file_type = $file_type;
        $this->batch_id = $batch_id;
    }

    public function handle(Controller $controller, CleanID $cleanID)
    {
        date_default_timezone_set('America/Chicago');

        $startTime = microtime(true);
        info('==============START CLEANIDJOB==============');
        info('CleanIDJob 1.1', ['file_type' => $this->file_type, 'md5Batch' => $this->md5Batch]);

        /* WHEN FILE TYPE UPLOAD INSERT TO CLEANIDMD5 */
        if ($this->file_type == 'upload')
        {
            info('CleanIDJob if upload 2.1', ['file_type' => $this->file_type, 'md5Batch' => $this->md5Batch]);

            // isinya hanya emaila atau md5 saja di dalam array
            $md5Batch = $this->md5Batch;
            CleanIDMd5::insert($md5Batch);
            
            // update md5Batch dengan data dari database clean_id_md5
            $this->md5Batch = CleanIDMd5::select('clean_id_md5.id', 'clean_id_md5.file_id', 'clean_id_md5.md5', 'clean_id_file.module', 'clean_id_file.advance_information', 'clean_id_file.user_id')
                                        ->join('clean_id_file', 'clean_id_file.id', '=', 'clean_id_md5.file_id')
                                        ->where('clean_id_md5.file_id', $this->file_id)
                                        ->where('clean_id_md5.batch_id', $this->batch_id)
                                        ->where('clean_id_md5.status', 'processing')
                                        ->orderBy('clean_id_md5.id', 'asc')
                                        ->get()
                                        ->toArray();
            info('CleanIDJob if upload 2.2', ['md5Batch' => $this->md5Batch]);
        }
        /* WHEN FILE TYPE UPLOAD INSERT TO CLEANIDMD5 */

        /* UPDATE STATUS CLEAN ID FILE */
        CleanIDFile::where('id', $this->file_id)
                   ->where('status', 'pending')
                   ->update(['status' => 'process']);
        /* UPDATE STATUS CLEAN ID FILE */

        /* PROCESS GET DATA FROM MD5 */
        $md5Batch = $this->md5Batch;
        $seenMd5 = [];
        foreach ($md5Batch as $md5Record) 
        {
            info("");
            info('==============FOREACH CLEANIDJOB START==============');
            // info('START SLEEP 1s'); 
            // sleep(1); 
            // info('END SLEEP 1s');
            // info('IN FOREACH');

            /* VARIABLE */
            $user_id = $md5Record['user_id'];
            $md5 = $md5Record['md5'];
            $md5Final = (filter_var($md5, FILTER_VALIDATE_EMAIL) && preg_match('/^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/', $md5)) ? md5($md5) : $md5;
            info('', ['md5Final' => $md5Final]);
            $file_id = $md5Record['file_id'];
            $md5_id = $md5Record['id'];
            $advance = $md5Record['advance_information'];
            $module_tx = $md5Record['module'];
            $module = array_map('trim', explode(",", $module_tx));
            /* VARIABLE */

            /** REPORT ANALYTIC */
            // $controller->UpsertReportAnalytics($file_id,'clean_id','pixelfire');
            /** REPORT ANALYTIC */

            $dtmd5 = CleanIDMd5::find($md5_id);

            /* CHECK DUPLICATE MD5 */
            if (in_array($md5, $seenMd5)) 
            {
                CleanIDMd5::where('id', $md5_id)->update(['status' => 'duplicate']);
                continue;
            }

            $alreadyExists = CleanIDMd5::where('md5', $md5)
                                       ->where('file_id', $file_id)
                                       ->where('status', '!=', 'processing')
                                       ->exists();
            if ($alreadyExists) 
            {
                CleanIDMd5::where('id', $md5_id)->update(['status' => 'duplicate']);
                continue;
            }
            /* CHECK DUPLICATE MD5 */

            /* SAVE MD5 IN ARRAY BUFFER */
            $seenMd5[] = $md5;
            /* SAVE MD5 IN ARRAY BUFFER */
            
            try
            {
                $dataflow = "";
                
                /* CHECK MD5 EXIST */
                $chkEmailExist = PersonEmail::select('person_emails.email','person_emails.id as emailID','person_emails.permission','p.lastEntry','p.uniqueID','p.firstName','p.lastName','p.id')
                                            ->join('persons as p','person_emails.person_id','=','p.id')
                                            ->where('person_emails.email_encrypt','=',$md5Final)
                                            ->orderBy('person_emails.id','desc')
                                            ->first();
                info('CleanIDJob 1.2', ['chkEmailExist' => $chkEmailExist]);
                $msg_description = '';
                $status = '';
                $is_advance = false;
                if (isset($advance) && !empty($advance) && trim($advance) != '') 
                {
                    $is_advance = true;
                }
                info('',['advance'=> $advance,'is_advance'=> $is_advance,]);
                /* CHECK MD5 EXIST */

                /* PROCESS GET DATA */
                if ($chkEmailExist) 
                {
                    $lastEntry = date('Ymd',strtotime($chkEmailExist->lastEntry));
                    $date1=date_create(date('Ymd'));
                    $date2=date_create($lastEntry);
                    $diff=date_diff($date1,$date2);

                    $persondata['id'] = $chkEmailExist->id;
                    $persondata['emailID'] = $chkEmailExist->emailID;
                    $persondata['uniqueID'] = $chkEmailExist->uniqueID;
                    $persondata['firstName'] = Encrypter::decrypt($chkEmailExist->firstName);
                    $persondata['lastName'] = Encrypter::decrypt($chkEmailExist->lastName);

                    $personEmail = $chkEmailExist->email;
                    $dataflow = $dataflow . 'EmailExistonDB|';
                    
                    info('CleanIDJob 2.1', ['difftime' => $diff->format("%a")]);
                    if ($diff->format("%a") <= 7) // customer exist, permission YES, last Entry < 1 Week
                    {
                        // PAKAI DATA EXIST ON DB
                        $dataflow = $dataflow . 'LastEntryLessSixMonth|';
                        $dataresult = $cleanID->dataExistOnDB($file_id,$md5_id,$personEmail,$persondata,$dataflow,$md5Final,$module,$is_advance);
                        info('CleanIDJob dataExistOnDB 2.2', ['dataresult' => $dataresult]);
                    }
                    else if ($diff->format("%a") > 7) // customer exist, permission YES, last Entry > 1 Week
                    {
                        // GET DATA BIGDBM AND UPDATE TO PERSON
                        $dataresult = $cleanID->dataNotExistOnDBBIG($file_id,$md5_id,$persondata['id'],$dataflow,$md5Final,$module,$is_advance);
                        info('CleanIDJob dataNotExistOnDBBIG 2.3', ['dataresult' => $dataresult]);
                    }
                } 
                else 
                {
                    info("CleanIDJob EmailNotExistOnDB 3.1");
                    $dataflow .= 'EmailNotExistOnDB|';

                    if($is_advance)
                    {
                        $dataresult = $cleanID->process_BIGDBM_advance($file_id,$md5_id,$dataflow,$md5Final);
                        info('CleanIDJob EmailNotExistOnDB 3.2', ['dataresult' => $dataresult]);
                    }
                    else 
                    {
                        $dataresult = $cleanID->process_BIGDBM_towerdata($file_id,$md5_id,$dataflow,$md5Final);
                        info('CleanIDJob EmailNotExistOnDB 3.3', ['dataresult' => $dataresult]);
                    }
                }
                /* PROCESS GET DATA */

                /* UPDATE CleanIDMd5 */
                $msg_description = $dataresult['msg_description'];
                $status = $dataresult['status'];
                info('CleanIDJob 4.1', ['msg_description' => $msg_description, 'status' => $status]);
                CleanIDMd5::where('id', $md5_id)
                          ->update([
                            'status' => $status, 
                            'description' => ($dtmd5->description ? $dtmd5->description . '|' : '') . $msg_description
                          ]);
                /* UPDATE CleanIDMd5 */

                // decrement balance amount in topup_cleanids when found
                if($status == 'found')
                {
                    $cleanID->process_decrement_balance_wallet($this->file_id, $this->cost_cleanid);
                }
                // decrement balance amount in topup_cleanids when found
            }
            catch(\Exception $e) 
            {
                // LOG ERROR TO CLEAN ID ERROR
                CleanIDError::create([
                    'file_id' => $this->file_id,
                    'name' => 'CleanIDJob',
                    'md5' => $md5,
                    'exception' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'trace' => $e->getTraceAsString(),
                ]);
                // LOG ERROR TO CLEAN ID ERROR

                // RETRY DISPATCH
                Log::error("Get Data Clean Id failed for {$md5}: " . $e->getMessage());
                CleanIDFile::where('id', $file_id)->increment('retry_jobs');
                RetryMD5CleanIDJob::dispatch($md5, $file_id, $md5_id, $module, $advance, $this->cost_cleanid, $this->company_id, $this->leadspeek_invoices_id)
                                  ->delay($this->delayRelease)
                                  ->onQueue('retry_clean_id')
                                  ->onConnection('redis');
                // RETRY DISPATCH
            }

            info('==============FOREACH CLEANIDJOB END==============');
        }
        /* PROCESS GET DATA FROM MD5 */

        /* UPDATE JUMLAH JOB YANG SELESAI */
        $file_id = isset($md5Batch[0]['file_id']) ? $md5Batch[0]['file_id'] : null;
        info('CleanIDJob 6.1', ['file_id' => $file_id]);
        if($file_id)
        {
            CleanIDFile::where('id', $file_id)->increment('jobs_completed');
    
            // Cek apakah semua job sudah selesai, update status = done
            $file = CleanIDFile::find($file_id);
            info('CleanIDJob 6.2', ['file' => $file]);
            if ($file->jobs_completed >= $file->total_jobs && $file->retry_jobs == 0)
            {
                info('CleanIDJob 6.3');
                CleanIDFile::where('id', $file_id)->update(['status' => 'done']);
                
                // check if balance amount in topup_cleanids exists, when exists refund to topup_agencies
                $cleanID->process_refund_balance_wallet($this->file_id, $this->company_id, $this->leadspeek_invoices_id);
                // check jika balance amount in topup_cleanids exists, when exists refund to topup_agencies
            }
            // Cek apakah semua job sudah selesai, update status = done
        }
        /* UPDATE JUMLAH JOB YANG SELESAI */

        info('==============END CLEANIDJOB==============');
        $endTime = microtime(true);
        $diffTime = $endTime - $startTime;
        info('CleanIDJob SELESAI', ['diffTime' => $diffTime]);
    }
}
