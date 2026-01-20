<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\App;
use App\Http\Controllers\Controller;
use App\Jobs\ChunCsvOptoutJob;
use App\Jobs\ChunkCsvClientJob;
use App\Jobs\ChunkCsvJob;
use App\Jobs\CleanIDExportChunkJob;
use App\Jobs\SimplifiAudienceAddressableStatusCheckJob;
use App\Models\JobProgress;
use App\Models\LeadspeekCustomer;
use App\Models\LeadspeekMedia;
use App\Models\LeadspeekUser;
use App\Models\LeadspeekMediaCampaign;
use App\Services\CleanID;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Http\Handlers\CustomResumableJSUploadHandler;
use Pion\Laravel\ChunkUpload\Receiver\FileReceiver;
use Pion\Laravel\ChunkUpload\Exceptions\ChunkSaveException;
use ZipArchive;
use App\Jobs\CleanIDJob;
use App\Jobs\RetryMD5CleanIDJob;
use App\Models\CleanIDFile;
use App\Models\CleanIDMd5;
use App\Models\CleanIDExport;
use App\Jobs\CleanIDExportJob;
use App\Jobs\PredictFetchReportJob;
use App\Models\LeadspeekInvoice;
use App\Models\MasterFeature;
use App\Models\ServicesAgreement;
use App\Models\TopupAgency;
use App\Models\User;
use App\Services\WattData;
use Illuminate\Support\Facades\Cache;

class UploadController extends Controller
{
    public function suppressionupload(Request $request) 
    {
        $leadspeek_apiID = $request->leadspeekID;
        $campaigntype = (isset($request->campaigntype))?$request->campaigntype:'campaign';

        /* CONFIGURATION FOR SPACES */
        $filenameOri = $request->file('suppressionfile')->getClientOriginalName();
        $filename = pathinfo($filenameOri, PATHINFO_FILENAME);
        $extension = $request->file('suppressionfile')->getClientOriginalExtension();
        $tmpfile = Carbon::now()->valueOf() . '_leadspeek_' . $leadspeek_apiID . '.' . $extension; 
        /* CONFIGURATION FOR SPACES */

        /* GET LEADSPEEK USER */
        $leaduser = LeadspeekUser::select('leadspeek_users.id','users.company_id','users.company_parent','leadspeek_users.leadspeek_api_id')
                                 ->join('users','leadspeek_users.user_id','=','users.id')
                                 ->where('leadspeek_users.id','=',$leadspeek_apiID)
                                 ->get();
        /* GET LEADSPEEK USER */
        
        $path = "";
        $filedownload_url = "";

        try
        {
            /* UPLOAD TO DO SPACES */
            $path = Storage::disk('spaces')->putFileAs('suppressionlist', $request->file('suppressionfile'), $tmpfile);
            $filedownload_url = Storage::disk('spaces')->url($path);
            $filedownload_url = str_replace('digitaloceanspaces', 'cdn.digitaloceanspaces', $filedownload_url);
            /* UPLOAD TO DO SPACES */
            info('', ['filedownload_url' => $filedownload_url]);
            return response()->json(['result' => 'success']);
        }
        catch(\Exception $e)
        {
            $message = $e->getMessage();
            $leadspeek_api_id = isset($leaduser[0]['leadspeek_api_id'])?$leaduser[0]['leadspeek_api_id']:$leadspeek_apiID;

            // send email
            $details = [
                'leadspeek_api_id' => $leadspeek_api_id,
                'errormsg'  => $message,
            ];

            $from = [
                'address' => 'noreply@sitesettingsapi.com',
                'name' => 'support',
                'replyto' => 'noreply@sitesettingsapi.com',
            ];

            $this->send_email(array('serverlogs@sitesettingsapi.com'),'ERROR UPLOAD CSV suppressionupload (1) #' . $leadspeek_api_id,$details,array(),'emails.uploadcsverrorlog',$from,'');
            // send email

            return response()->json(['error' => $message], 500);
        }

        if (count($leaduser) > 0) {
            $epochTime = Carbon::now()->timestamp;
            $numberRandom = $this->generateUniqueNumber();

            /* CREATE JOB PROGRESS */
            $jobProgress = JobProgress::create([
                'job_id' => $numberRandom,
                'upload_at' => $epochTime,
                'lead_userid' => $leadspeek_apiID,
                'company_id' => $leaduser[0]['company_id'],
                'suppression_type' => $campaigntype,
                'leadspeek_api_id' => $leaduser[0]['leadspeek_api_id'],
                'filename' => $filenameOri,
                'status' => 'queue',
            ]);
            /* CREATE JOB PROGRESS */

            /* RUNNING QUEUE */
            ChunkCsvJob::dispatch($numberRandom, [
                'filedownload_url' => $filedownload_url,
                'path' => $path,
                'upload_at' => $epochTime,
                'filename' => $filenameOri,
                'lead_userid' => $leadspeek_apiID,
                'leadspeek_api_id' => $leaduser[0]['leadspeek_api_id'],
                'company_id' => $leaduser[0]['company_id'],
                'suppression_type' => $campaigntype,
            ]);
            /* RUNNING QUEUE */
        }

        return response()->json([
            'result' => 'success',
            'filename' => $filedownload_url,
        ]);
    }

    public function ClientOptout(Request $request) 
    {
        $ClientCompanyID = $request->ClientCompanyID;

        /* CONFIGURATION FOR SPACES */
        $filenameOri = $request->file('clientoptoutfile')->getClientOriginalName();
        $filename = pathinfo($filenameOri, PATHINFO_FILENAME);
        $extension = $request->file('clientoptoutfile')->getClientOriginalExtension();
        $tmpfile = Carbon::now()->valueOf() . '_company_' . $ClientCompanyID . '.' . $extension; 
        /* CONFIGURATION FOR SPACES */

        $path = "";
        $filedownload_url = "";

        try
        {
            /* UPLOAD TO DO SPACES */
            $path = Storage::disk('spaces')->putFileAs('tools/optout', $request->file('clientoptoutfile'), $tmpfile);
            $filedownload_url = Storage::disk('spaces')->url($path);
            $filedownload_url = str_replace('digitaloceanspaces', 'cdn.digitaloceanspaces', $filedownload_url);
            /* UPLOAD TO DO SPACES */
        }
        catch (\Exception $e)
        {
            $message = $e->getMessage();

            // send email
            $details = [
                'company_id' => $ClientCompanyID,
                'errormsg'  => $message,
            ];

            $from = [
                'address' => 'noreply@sitesettingsapi.com',
                'name' => 'support',
                'replyto' => 'noreply@sitesettingsapi.com',
            ];

            $this->send_email(array('serverlogs@sitesettingsapi.com'),'ERROR UPLOAD CSV ClientOptout (1) #' . $ClientCompanyID,$details,array(),'emails.uploadcsverrorlog',$from,'');
            // send email

            return response()->json(['error' => $message], 500);
        }

        $epochTime = Carbon::now()->timestamp;
        $numberRandom = $this->generateUniqueNumber();
        
        /* JOBPROGRESS */
        $jobProgress = JobProgress::create([
                'job_id' => $numberRandom,
                'upload_at' => $epochTime,
                'lead_userid' => '',
                'company_id' => $ClientCompanyID,
                'suppression_type' => 'client',
                'leadspeek_api_id' => '',
                'filename' => $filenameOri,
                'status' => 'queue',
            ]);
        /* JOBPROGRESS */

        /* RUNNING QUEUE */
        ChunkCsvClientJob::dispatch($numberRandom, [
            'upload_at' => $epochTime,
            'filedownload_url' => $filedownload_url,
            'path' => $path,
            'company_id' => $ClientCompanyID,
            'suppression_type' => 'client',
            'filename' => $filenameOri,
        ]);
        /* RUNNING QUEUE */

        return response()->json([
            'result' => 'success',
            'filename' => $filedownload_url,
        ]);
    }

    public function optout(Request $request)
    {
        $companyRootId = (isset($request->companyRootId))?$request->companyRootId:'';

        /* CONFIGURATION FOR SPACES */
        $filenameOri = $request->file('optoutfile')->getClientOriginalName();
        $filename = pathinfo($filenameOri, PATHINFO_FILENAME);
        $extension = $request->file('optoutfile')->getClientOriginalExtension();
        $tmpfile = Carbon::now()->valueOf() . 'optoutlist.' . $extension;
        /* CONFIGURATION FOR SPACES */

        $path = "";
        $filedownload_url = "";

        try
        {
            /* UPLOAD TO DO SPACES */
            $path = Storage::disk('spaces')->putFileAs('tools/optout', $request->file('optoutfile'), $tmpfile);
            $filedownload_url = Storage::disk('spaces')->url($path);
            $filedownload_url = str_replace('digitaloceanspaces', 'cdn.digitaloceanspaces', $filedownload_url);
            /* UPLOAD TO DO SPACES */
        }
        catch(\Exception $e)
        {
            $message = $e->getMessage();

            // send email
            $details = [
                'company_id' => $companyRootId,
                'errormsg'  => $message,
            ];

            $from = [
                'address' => 'noreply@sitesettingsapi.com',
                'name' => 'support',
                'replyto' => 'noreply@sitesettingsapi.com',
            ];

            $this->send_email(array('serverlogs@sitesettingsapi.com'),'ERROR UPLOAD CSV optout (1) #' . $companyRootId,$details,array(),'emails.uploadcsverrorlog',$from,'');
            // send email

            return response()->json(['error' => $message], 500);
        }


        $epochTime = Carbon::now()->timestamp;
        $numberRandom = $this->generateUniqueNumber();

        /* JOBPROGRESS */
        $jobProgress = JobProgress::create([
            'job_id' => $numberRandom,
            'upload_at' => $epochTime,
            'lead_userid' => '',
            'company_id' => $companyRootId,
            'suppression_type' => '',
            'leadspeek_api_id' => '',
            'filename' => $filenameOri,
            'status' => 'queue',
        ]);
        /* JOBPROGRESS */

        /* RUNNING QUEUE */
        ChunCsvOptoutJob::dispatch($numberRandom, [
            'upload_at' => $epochTime,
            'filedownload_url' => $filedownload_url,
            'path' => $path,
            'filename' => $filenameOri,
            'company_root_id' => $companyRootId,
        ]);
        /* RUNNING QUEUE */

        return response()->json([
            'result' => 'success',
            'filename' => $filedownload_url,
        ]);
    }

    public function audianceupload(Request $request)
    {
        info(__FUNCTION__, ['all' => $request->all()]);
        /* VALIDATION */
        $audiance_name_ori = isset($request->audiance_name_ori) ? $request->audiance_name_ori : '';
        $audiance_name = isset($request->audiance_name) ? $request->audiance_name : '';
        $user_id = isset($request->user_id) ? $request->user_id : '';
        
        // $simplifi_id = isset($request->simplifi_id) ? $request->simplifi_id : '';
        if(empty($audiance_name))
            return response(['result' => 'error', 'message' => 'audiance name required'], 422);
        if(empty($user_id))
            return response()->json(['result' => 'error', 'message' => 'user id required'], 422);
        if(!$request->hasFile('file'))
            return response()->json(['result' => 'error', 'message' => 'File is required.'], 422);
        if(!$request->file('file')->isValid())
            return response()->json(['result' => 'error', 'message' => 'File upload failed.'], 422);

        // validation extension
        $file = $request->file('file');
        $allowedExtensions = ['csv'];
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, $allowedExtensions)) 
            return response()->json(['error' => 'Only CSV files are allowed.'], 422);
        // validation extension

        // validation size
        $maxSizeInBytes = 1024 * 1024; // 1 MB
        if ($file->getSize() > $maxSizeInBytes)
            return response()->json(['error' => 'File size must be 1MB or less.'], 422);
        // validation size

        // validation format csv
        $totalAddress = 0;
        if(($handle = fopen($file->getPathname(), 'r')) !== false) 
        {
            // validation format csv must be address, city, state, zip
            $header = fgetcsv($handle, 0, ','); // ambil baris pertama
            $expectedHeader1 = ['address', 'city', 'state', 'zip'];
            $expectedHeader2 = ['address', 'city', 'state', 'zip', 'latitude'];
            $expectedHeader3 = ['address', 'city', 'state', 'zip', 'latitude', 'longitude'];
            $headerLower = array_map('strtolower', $header);
            info('', ['header' => $header]);
            if ($headerLower !== $expectedHeader1 && $headerLower !== $expectedHeader2 && $headerLower !== $expectedHeader3)
            {
                fclose($handle);
                return response()->json(['status' => 'error', 'message' => 'format csv header must be: address, city, state, zip. optional add latitude, longitude'], 422);
            }
            // validation format csv must be address, city, state, zip
                
            // validation at least one address found and count address
            while (($row = fgetcsv($handle, 0, ',')) !== false) 
            {
                $row = array_map('trim', $row); // buang spasi kiri/kanan
                info('', ['row' => $row]);
                if (count(array_filter($row)) > 0) 
                {
                    $totalAddress++;
                }
            }

            fclose($handle);
            if($totalAddress == 0)
                return response()->json(['result' => 'error', 'message' => 'minimum address must be 1'], 422);
            // validation at least one address found and count address
        }
        // validation format csv
        /* VALIDATION */

        // audiance_123_muhammad_jidan_12345678.csv
        $now = Carbon::now()->valueOf();
        $audiance_name_format = strtolower(str_replace(' ', '_', $audiance_name));
        $tmpfile = "audiance_{$user_id}_{$audiance_name_format}_{$now}.{$extension}";

        try
        {
            /* UPLOAD TO DO SPACES */
            $path = Storage::disk('spaces')->putFileAs('users/media', $file, $tmpfile);
            info('', ['path' => $path, 'tmpfile' => $tmpfile]);
            $filedownload_url = Storage::disk('spaces')->url($path);
            info('', ['filedownload_url' => $filedownload_url]);
            $filedownload_url = str_replace('digitaloceanspaces', 'cdn.digitaloceanspaces', $filedownload_url);
            info('', ['filedownload_url' => $filedownload_url]);
            /* UPLOAD TO DO SPACES */

            /* SAVE TO DATABASE */
            $media = LeadspeekMedia::create([
                'user_id' => $user_id,
                'simplifi_id' => null,
                'media_name_ori' => $audiance_name_ori,
                'media_name' => $audiance_name,
                'total_address' => $totalAddress,
                'url' => $filedownload_url,
                'simplifi_status' => 'queue'
            ]);
            /* SAVE TO DATABASE */

            // SimplifiAudienceAddressableStatusCheckJob::dispatch($media->id)->onQueue('simplifi_addressable_check_status');

            return response()->json(['result' => 'success','media_id' => $media->id]);
        }
        catch(\Exception $e) 
        {
            $message = $e->getMessage();
            info('error', ['message' => $message]);
            return response()->json(['result' => 'error', 'message' => $message], 500);
        }
    }

    /**
     * Chunked media upload - combines mediaupload logic from EMM-SANDBOX-API with ChunkUpload
     * Handles images, HTML5 ZIP files, and videos with full validation
     */
    /**
     * Chunked media upload - combines mediaupload logic from EMM-SANDBOX-API with ChunkUpload
     * Handles images, HTML5 ZIP files, and videos with full validation
     */
    public function mediaupload(Request $request)
    {
        // Set execution time limit to 15 minutes (900 seconds) for large file uploads
        set_time_limit(900);
        
        // Validate required fields
        // Note: user_id and upload_folder come from query params (Resumable.js)
        $request->validate([
            'user_id' => 'required|integer',
            'upload_folder' => 'nullable|string',
            'video_dimensions' => 'nullable|string|regex:/^\d+x\d+$/' // Format: "1920x1080"
        ]);

        $userId = $request->input('user_id');
        $uploadFolder = trim($request->input('upload_folder', 'users/media'), '/');
        $videoDimensions = $request->input('video_dimensions'); // Format: "1920x1080" or null

        // Initialize chunk receiver with custom handler to support concurrent uploads
        // Use custom handler that includes upload_id in chunk file names to prevent race conditions
        $receiver = new FileReceiver('file', $request, CustomResumableJSUploadHandler::class);

        if (!$receiver->isUploaded()) {
            info('[MediaUpload] File not uploaded - returning error');
            return response()->json([
                'result' => 'error',
                'message' => 'File not uploaded'
            ], 400);
        }

        try {
            $fileReceived = $receiver->receive();
        } catch (ChunkSaveException $e) {
            // Handle chunk save exceptions (e.g., "Failed to open output stream", file locking issues)
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();
            
            Log::error('[MediaUpload] ChunkSaveException caught', [
                'code' => $errorCode,
                'message' => $errorMessage,
                'upload_id' => $request->input('upload_id'),
                'user_id' => $userId,
                'trace' => $e->getTraceAsString()
            ]);
            
            // Provide user-friendly error message
            if ($errorCode === 102 || strpos($errorMessage, 'Failed to open output stream') !== false) {
                return response()->json([
                    'result' => 'error',
                    'message' => 'Upload failed due to concurrent file access. Please try uploading again or wait a moment before retrying.'
                ], 500);
            }
            
            // Generic error message for other chunk save exceptions
            return response()->json([
                'result' => 'error',
                'message' => 'Upload failed. Please try again.'
            ], 500);
        } catch (\Exception $e) {
            Log::error('[MediaUpload] Unexpected exception during chunk upload', [
                'message' => $e->getMessage(),
                'upload_id' => $request->input('upload_id'),
                'user_id' => $userId,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'result' => 'error',
                'message' => 'An unexpected error occurred during upload. Please try again.'
            ], 500);
        }

        // Early validation on first chunk (before file is complete)
        // This allows us to reject invalid files early and stop the upload process
        if (!$fileReceived->isFinished()) {
            // Get file info from first chunk
            $file = $fileReceived->getFile();
            $extension = strtolower($file->getClientOriginalExtension());
            $file_name = $file->getClientOriginalName();
            
            // Get total file size from request headers (Content-Length or Resumable.js)
            $totalFileSize = $request->header('Content-Length');
            if (!$totalFileSize) {
                // Try to get from Resumable.js headers
                $totalFileSize = $request->header('X-File-Size');
            }
            $file_size_in_bytes = $totalFileSize ? (int)$totalFileSize : null;
            
            // Basic validation: file extension and type
            $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif']);
            $isZip = $extension === 'zip';
            $isVideo = in_array($extension, ['mp4', 'mov', 'mpeg']);
            
            // Reject unsupported file types early
            if (!$isImage && !$isZip && !$isVideo) {
                return response()->json([
                    'result' => 'error',
                    'message' => 'Unsupported file type. Only images (JPG, PNG, GIF), ZIP (HTML5), and videos (MP4, MOV, MPEG) are allowed.'
                ], 422);
            }
            
            // Basic file size check (if we have the total file size)
            if ($file_size_in_bytes) {
                if ($isZip && $file_size_in_bytes > 10 * 1024 * 1024) {
                    return response()->json([
                        'result' => 'error',
                        'message' => 'HTML5 ZIP file size exceeds the maximum limit of 10 MB.'
                    ], 422);
                }
                if ($isImage && $file_size_in_bytes > 200 * 1024 * 1024) {
                    return response()->json([
                        'result' => 'error',
                        'message' => 'Image file size exceeds the maximum limit of 200 MB.'
                    ], 422);
                }
                if ($isVideo && $file_size_in_bytes > 200 * 1024 * 1024) {
                    return response()->json([
                        'result' => 'error',
                        'message' => 'Video file size exceeds the maximum limit of 200 MB.'
                    ], 422);
                }
            }
            
            // Return progress for ongoing uploads
            $handler = $fileReceived->handler();
            $percentageDone = $handler->getPercentageDone();
            
            return response()->json([
                'done' => $percentageDone,
                'status' => true
            ]);
        }

        // If upload is complete, process the file with full validation
        if ($fileReceived->isFinished()) {
            $file = $fileReceived->getFile();
            $extension = strtolower($file->getClientOriginalExtension());
            $file_name = $file->getClientOriginalName();
            $file_name_without_extension = pathinfo($file_name, PATHINFO_FILENAME);
            $file_size_in_bytes = $file->getSize();

            // Determine file type and validate accordingly
            $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif']);
            $isZip = $extension === 'zip';
            $isVideo = in_array($extension, ['mp4', 'mov', 'mpeg']);

            // Validate extension based on file type
            if ($isImage) {
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                if (!in_array($extension, $allowed_extensions)) {
                    unlink($file->getPathname());
                    return response()->json([
                        'result' => 'error',
                        'message' => 'Only JPG, PNG and GIF files are allowed.'
                    ], 422);
                }
            } elseif ($isZip) {
                // HTML5 ZIP files - validate MIME type
                if ($file->getMimeType() !== 'application/zip') {
                    unlink($file->getPathname());
                    return response()->json([
                        'result' => 'error',
                        'message' => 'Invalid ZIP file.'
                    ], 422);
                }
            } elseif ($isVideo) {
                $allowed_extensions = ['mp4', 'mov', 'mpeg'];
                $allowed_mime_types = ['video/mp4', 'video/quicktime', 'video/mpeg'];
                if (!in_array($extension, $allowed_extensions) || !in_array($file->getMimeType(), $allowed_mime_types)) {
                    unlink($file->getPathname());
                    return response()->json([
                        'result' => 'error',
                        'message' => 'Only MP4, MOV, and MPEG video files are allowed.'
                    ], 422);
                }
            } else {
                unlink($file->getPathname());
                return response()->json([
                    'result' => 'error',
                    'message' => 'Unsupported file type. Only images (JPG, PNG, GIF), ZIP (HTML5), and videos (MP4, MOV, MPEG) are allowed.'
                ], 422);
            }

            // Image-specific validation (dimensions)
            $width = null;
            $height = null;
            $dimension_string = null;

            if ($isImage) {
                $image_info = @getimagesize($file->getPathname());
                if ($image_info === false) {
                    unlink($file->getPathname());
                    return response()->json([
                        'result' => 'error',
                        'message' => 'Invalid image file.'
                    ], 422);
                }
                $width = $image_info[0];
                $height = $image_info[1];
                $dimension_string = $width . 'x' . $height;

                $allowed_dimensions = [
                    '728x90', '160x600', '120x600', '120x160', '120x240', '120x60', '120x90', '125x125',
                    '160x90', '180x150', '180x90', '200x90', '234x60', '240x400', '300x100', '300x850',
                    '336x280', '450x50', '468x15', '468x60', '468x728', '728x15', '88x31', '700x500',
                    '120x20', '168x28', '216x36', '320x480', '480x320', '768x1024', '1024x768', '320x250',
                    '970x250', '200x200', '250x250', '320x50', '980x120', '980x240', '1250x300', '1250x600',
                    '320x320', '250x360', '250x600', '250x800', '468x250', '600x315', '1200x628', '970x90',
                    '640x100', '300x400', '468x400', '1400x400', '1920x1080', '1080x1920', '1224x340',
                    '1920x576', '3840x1080', '1280x360', '840x400', '300x250', '300x600', '300x50',
                    '500x500', '640x640', '1024x1024'
                ];

                if (!in_array($dimension_string, $allowed_dimensions)) {
                    unlink($file->getPathname());
                    return response()->json([
                        'result' => 'error',
                        'message' => 'Invalid image dimension: ' . $dimension_string
                    ], 422);
                }
            } elseif ($isVideo && $videoDimensions) {
                // Use video dimensions from frontend
                $dimension_string = $videoDimensions;
            } elseif ($isZip) {
                // Extract and validate HTML5 ZIP dimensions
                $html5Dimensions = $this->getHtml5Dimensions($file->getPathname());
                if ($html5Dimensions) {
                    $dimension_string = $html5Dimensions;
                    info('[MediaUpload] Extracted HTML5 dimensions', [
                        'dimensions' => $dimension_string
                    ]);
                    
                    // Validate HTML5 dimensions against allowed_dimensions (same as Image)
                    $allowed_dimensions = [
                        '728x90', '160x600', '120x600', '120x160', '120x240', '120x60', '120x90', '125x125',
                        '160x90', '180x150', '180x90', '200x90', '234x60', '240x400', '300x100', '300x850',
                        '336x280', '450x50', '468x15', '468x60', '468x728', '728x15', '88x31', '700x500',
                        '120x20', '168x28', '216x36', '320x480', '480x320', '768x1024', '1024x768', '320x250',
                        '970x250', '200x200', '250x250', '320x50', '980x120', '980x240', '1250x300', '1250x600',
                        '320x320', '250x360', '250x600', '250x800', '468x250', '600x315', '1200x628', '970x90',
                        '640x100', '300x400', '468x400', '1400x400', '1920x1080', '1080x1920', '1224x340',
                        '1920x576', '3840x1080', '1280x360', '840x400', '300x250', '300x600', '300x50',
                        '500x500', '640x640', '1024x1024'
                    ];
                    
                    if (!in_array($dimension_string, $allowed_dimensions)) {
                        unlink($file->getPathname());
                        return response()->json([
                            'result' => 'error',
                            'message' => 'Invalid HTML5 dimension: ' . $dimension_string . '. Please use a valid ad size.'
                        ], 422);
                    }
                } else {
                    // If dimensions cannot be extracted, still allow upload but log warning
                    info('[MediaUpload] Could not extract HTML5 dimensions, proceeding without dimension validation');
                }
                
                // Validate HTML5 ZIP structure and content
                $html5Validation = $this->validateHtml5Zip($file->getPathname());
                if (!$html5Validation['valid']) {
                    unlink($file->getPathname());
                    return response()->json([
                        'result' => 'error',
                        'message' => $html5Validation['message']
                    ], 422);
                }
            }

            // File size validation (different limits per type)
            if ($isImage) {
                $maxSizeInBytes = 200 * 1024 * 1024; // 200 MB for images
            } elseif ($isZip) {
                $maxSizeInBytes = 10 * 1024 * 1024; // 10 MB for HTML5 ZIP files
            } elseif ($isVideo) {
                $maxSizeInBytes = 200 * 1024 * 1024; // 200 MB for videos
            } else {
                $maxSizeInBytes = 200 * 1024 * 1024; // Default 200 MB
            }

            if ($file_size_in_bytes > $maxSizeInBytes) {
                $maxSizeMB = round($maxSizeInBytes / (1024 * 1024));
                unlink($file->getPathname());
                return response()->json([
                    'result' => 'error',
                    'message' => "Maximum file size is {$maxSizeMB}MB."
                ], 422);
            }

            // Validation remaining storage space for user
            $total_used_storage = LeadspeekMedia::where('user_id', (int) $userId)->where('is_deleted', false)->sum('size_file'); // byte
            $max_storage_limit = 500 * 1024 * 1024; // 500 MB in byte

            if (($total_used_storage + $file_size_in_bytes) > $max_storage_limit) {
                unlink($file->getPathname());
                return response()->json([
                    'result' => 'error',
                    'message' => 'You have exceeded your total storage limit of 500MB. Please delete some files before uploading new ones.'
                ], 422);
            }

            // Generate unique filename
            // Sanitize filename to handle non-ASCII characters (Japanese, Korean, etc.)
            $now = Carbon::now()->valueOf();
            $file_name_format = $this->sanitizeFileName($file_name_without_extension);
            $tmpfile = "media_{$userId}_{$file_name_format}_{$now}.{$extension}";

            try {
                /* UPLOAD TO DO SPACES */
                $path = Storage::disk('spaces')->putFileAs($uploadFolder, $file, $tmpfile);
                $filedownload_url = Storage::disk('spaces')->url($path);
                $filedownload_url = str_replace('digitaloceanspaces', 'cdn.digitaloceanspaces', $filedownload_url);
                /* UPLOAD TO DO SPACES */

                // Save to database
                $media = LeadspeekMedia::create([
                    'user_id' => $userId,
                    'simplifi_id' => null,
                    'media_name_ori' => $file_name,
                    'media_name' => $file_name,
                    'size_file' => $file_size_in_bytes,
                    'dimension_img' => $dimension_string, // Image: extracted from getimagesize, HTML5: extracted from index.html, Video: from frontend
                    'url' => $filedownload_url,
                ]);

                // Clean up temporary merged file from local storage after successful upload
                $tempFilePath = $file->getPathname();
                if (file_exists($tempFilePath)) {
                    try {
                        unlink($tempFilePath);
                        Log::info('[MediaUpload] Temporary file cleaned up after successful upload', [
                            'file_path' => $tempFilePath,
                            'user_id' => $userId,
                            'media_id' => $media->id
                        ]);
                    } catch (\Exception $cleanupException) {
                        Log::warning('[MediaUpload] Failed to cleanup temporary file', [
                            'file_path' => $tempFilePath,
                            'error' => $cleanupException->getMessage(),
                            'user_id' => $userId
                        ]);
                    }
                }

                return response()->json([
                    'result' => 'success',
                    'message' => 'File uploaded successfully',
                    'url' => $filedownload_url,
                    'path' => $path,
                    'media_id' => $media->id
                ]);
            } catch (\Exception $e) {
                // Clean up temporary merged file on error
                $tempFilePath = $file->getPathname();
                if (file_exists($tempFilePath)) {
                    try {
                        unlink($tempFilePath);
                        Log::info('[MediaUpload] Temporary file cleaned up after upload error', [
                            'file_path' => $tempFilePath,
                            'user_id' => $userId,
                            'error' => $e->getMessage()
                        ]);
                    } catch (\Exception $cleanupException) {
                        Log::warning('[MediaUpload] Failed to cleanup temporary file after error', [
                            'file_path' => $tempFilePath,
                            'cleanup_error' => $cleanupException->getMessage(),
                            'upload_error' => $e->getMessage(),
                            'user_id' => $userId
                        ]);
                    }
                }
                
                Log::error('[MediaUpload] Upload to Spaces failed', [
                    'user_id' => $userId,
                    'file_name' => $file_name,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                return response()->json([
                    'result' => 'error',
                    'message' => $e->getMessage()
                ], 500);
            }
        }

        // Return upload progress
        $handler = $fileReceived->handler();
        $percentageDone = $handler->getPercentageDone();
                
        return response()->json([
            'done' => $percentageDone,
            'status' => true
        ]);
    }
    
    /**
     * Extract dimensions from HTML5 ZIP file
     * Reads index.html and extracts dimensions from meta tag, canvas, or CSS
     */
    private function getHtml5Dimensions($zipPath)
    {
        try {
            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                info('[MediaUpload] Failed to open ZIP file for dimension extraction');
                return null;
            }
            
            // Look for index.html (case-insensitive)
            $indexHtmlName = null;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $fileName = $zip->getNameIndex($i);
                if (strtolower(basename($fileName)) === 'index.html') {
                    $indexHtmlName = $fileName;
                    break;
                }
            }
            
            if (!$indexHtmlName) {
                $zip->close();
                info('[MediaUpload] index.html not found in ZIP');
                return null;
            }
            
            $htmlContent = $zip->getFromName($indexHtmlName);
            $zip->close();
            
            if (!$htmlContent) {
                info('[MediaUpload] Failed to read index.html from ZIP');
                return null;
            }
            
            // Try to extract dimensions from meta tag: <meta name="ad.size" content="width=300,height=250">
            if (preg_match('/<meta\s+name=["\']ad\.size["\']\s+content=["\']width=(\d+),height=(\d+)["\']/i', $htmlContent, $matches)) {
                $width = (int)$matches[1];
                $height = (int)$matches[2];
                return $width . 'x' . $height;
            }
            
            // Try to extract from canvas element: <canvas width="300" height="250">
            if (preg_match('/<canvas[^>]*width=["\']?(\d+)["\']?[^>]*height=["\']?(\d+)["\']?/i', $htmlContent, $matches)) {
                $width = (int)$matches[1];
                $height = (int)$matches[2];
                return $width . 'x' . $height;
            }
            
            // Try to extract from CSS: width: 300px; height: 250px;
            if (preg_match('/width\s*:\s*(\d+)px/i', $htmlContent, $widthMatch) &&
                preg_match('/height\s*:\s*(\d+)px/i', $htmlContent, $heightMatch)) {
                $width = (int)$widthMatch[1];
                $height = (int)$heightMatch[1];
                return $width . 'x' . $height;
            }
            
            info('[MediaUpload] Could not extract dimensions from HTML5 index.html');
            return null;
        } catch (\Exception $e) {
            info('[MediaUpload] Error extracting HTML5 dimensions: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Validate HTML5 ZIP structure and content
     * Checks for index.html, DOCTYPE, html/body tags, and clickMacro
     */
    private function validateHtml5Zip($zipPath)
    {
        try {
            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                return [
                    'valid' => false,
                    'message' => 'Invalid ZIP file. Cannot open file.'
                ];
            }
            
            // Check if index.html exists
            $indexHtmlName = null;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $fileName = $zip->getNameIndex($i);
                if (strtolower(basename($fileName)) === 'index.html') {
                    $indexHtmlName = $fileName;
                    break;
                }
            }
            
            if (!$indexHtmlName) {
                $zip->close();
                return [
                    'valid' => false,
                    'message' => 'HTML5 ZIP must contain an index.html file.'
                ];
            }
            
            // Read index.html content
            $htmlContent = $zip->getFromName($indexHtmlName);
            $zip->close();
            
            if (!$htmlContent) {
                return [
                    'valid' => false,
                    'message' => 'Failed to read index.html from ZIP file.'
                ];
            }
            
            // Convert to lowercase for case-insensitive checks
            $htmlLower = strtolower($htmlContent);
            
            // Check for DOCTYPE html
            if (!preg_match('/<!doctype\s+html/i', $htmlContent)) {
                return [
                    'valid' => false,
                    'message' => 'index.html must include <!DOCTYPE html> declaration.'
                ];
            }
            
            // Check for <html> tag
            if (strpos($htmlLower, '<html') === false) {
                return [
                    'valid' => false,
                    'message' => 'index.html must include <html> tag.'
                ];
            }
            
            // Check for <body> tag
            if (strpos($htmlLower, '<body') === false) {
                return [
                    'valid' => false,
                    'message' => 'index.html must include <body> tag.'
                ];
            }
            
            // Check for {{clickMacro}} (required for Simpli.fi)
            if (strpos($htmlContent, '{{clickMacro}}') === false) {
                return [
                    'valid' => false,
                    'message' => 'index.html must include {{clickMacro}} for click tracking.'
                ];
            }
            
            // Check external references (max 10 allowed)
            // Count external URLs (http://, https://, //)
            $externalRefs = preg_match_all('/(https?:\/\/|\/\/)[^\s\'"<>]+/i', $htmlContent, $matches);
            if ($externalRefs > 10) {
                return [
                    'valid' => false,
                    'message' => 'HTML5 ad cannot exceed 10 external references. Found: ' . $externalRefs
                ];
            }
            
            return [
                'valid' => true,
                'message' => 'HTML5 ZIP validation passed.'
            ];
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'message' => 'Error validating HTML5 ZIP: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Sanitize filename to handle non-ASCII characters (Japanese, Korean, Chinese, etc.)
     * Converts non-ASCII characters to ASCII-safe equivalents or removes them
     * 
     * @param string $fileName
     * @return string
     */
    private function sanitizeFileName($fileName)
    {
        // First, try to transliterate non-ASCII characters to ASCII
        // This handles Japanese, Korean, Chinese, Arabic, etc.
        if (function_exists('iconv')) {
            // Try transliteration first (converts こんにちは to konnichiha)
            $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $fileName);
            if ($transliterated !== false && $transliterated !== '') {
                $fileName = $transliterated;
            } else {
                // If transliteration fails, remove non-ASCII characters
                $fileName = @iconv('UTF-8', 'ASCII//IGNORE', $fileName);
            }
        } else {
            // Fallback: remove non-ASCII characters if iconv is not available
            $fileName = preg_replace('/[^\x00-\x7F]/u', '', $fileName);
        }
        
        // Remove or replace problematic characters for file systems
        // Keep only alphanumeric, spaces, hyphens, underscores, and dots
        $fileName = preg_replace('/[^a-zA-Z0-9\s._-]/', '', $fileName);
        
        // Replace spaces with underscores
        $fileName = str_replace(' ', '_', $fileName);
        
        // Remove multiple consecutive underscores, dots, or hyphens
        $fileName = preg_replace('/[._-]{2,}/', '_', $fileName);
        
        // Trim underscores, dots, and hyphens from start and end
        $fileName = trim($fileName, '._-');
        
        // Convert to lowercase for consistency
        $fileName = strtolower($fileName);
        
        // If filename is empty after sanitization, use a default name
        if (empty($fileName)) {
            $fileName = 'file';
        }
        
        // Limit filename length to 200 characters (to prevent filesystem issues)
        if (strlen($fileName) > 200) {
            $fileName = substr($fileName, 0, 200);
        }
        
        return $fileName;
    }

    public function mediadelete(Request $request)
    {
        $mediaIds = $request->input('media_ids') !== null ? explode(',', $request->input('media_ids')) : [];

        if (empty($mediaIds) || !is_array($mediaIds)) {
            return response()->json([
                'result' => 'error',
                'message' => 'media_ids must be a non-empty array'
            ], 422);
        }

        $check_applied_on_campaign = LeadspeekMediaCampaign::whereIn('media_id', $mediaIds)->count();
        if ($check_applied_on_campaign > 0) {
            return response()->json([
                'result' => 'error',
                'message' => 'One or more media IDs are currently applied to any campaigns and cannot be deleted unless they are removed from the campaigns first.'
            ], 422);
        }

        try {
            $deleted = [];
            $notFound = [];
            foreach ($mediaIds as $media_id) {
                $media = LeadspeekMedia::find($media_id);
                
                if (!$media) {
                    info('mediadelete_not_found', ['media_id' => $media_id]);
                    $notFound[] = $media_id;
                    continue;
                }

                // Extract file path from the URL
                $fileUrl = $media->url;
                $parsedUrl = parse_url($fileUrl, PHP_URL_PATH);
                $filePath = ltrim($parsedUrl, '/'); // users/media/filename.png

                // Delete file from Spaces
                if (Storage::disk('spaces')->exists($filePath)) {
                    Storage::disk('spaces')->delete($filePath);
                }

                // Delete database record
                $media->delete();
                $deleted[] = $media_id;
            }

            return response()->json([
                'result' => 'success',
                'deleted' => $deleted,
                'not_found' => $notFound
            ]);
        } catch (\Exception $e) {
            info('mediadelete_error', ['message' => $e->getMessage()]);
            return response()->json([
                'result' => 'error',
                'message' => 'Failed to delete media.'
            ], 500);
        }
    }

    /* =============== PREDICT ID =============== */
    public function predictCustomerUpload(Request $request)
    {
        info(__FUNCTION__, ['all' => $request->all()]);
        /* VALIDATION */
        $customer_list_name = isset($request->customer_list_name) ? $request->customer_list_name : '';
        $user_id = isset($request->user_id) ? $request->user_id : '';

        if(empty($customer_list_name))
            return response(['result' => 'error', 'message' => 'customer list name required'], 422);
        if(empty($user_id))
            return response()->json(['result' => 'error', 'message' => 'user id required'], 422);
        if(!$request->hasFile('file'))
            return response()->json(['result' => 'error', 'message' => 'File is required.'], 422);
        if(!$request->file('file')->isValid())
            return response()->json(['result' => 'error', 'message' => 'File upload failed.'], 422);

        // validation extension
        $file = $request->file('file');
        $allowedExtensions = ['csv'];
        $extension = strtolower($file->getClientOriginalExtension());
        if(!in_array($extension, $allowedExtensions)) 
            return response()->json(['error' => 'Only CSV files are allowed.'], 422);
        // validation extension

        // validation size
        $maxSizeInBytes = 1024 * 1024; // 1 MB
        $file_size_in_bytes = $file->getSize();
        info('', ['file_size_in_bytes' => $file_size_in_bytes]);
        if($file_size_in_bytes > $maxSizeInBytes)
            return response()->json(['error' => 'File size must be 1MB or less.'], 422);
        // validation size

        // validation format csv
        $totalCustomer = 0;
        if(($handle = fopen($file->getPathname(), 'r')) !== false)
        {
            // validation format csv must be firstname, lastname, email
            $header = fgetcsv($handle, 0, ','); // ambil baris pertama
            $expectedHeader1 = ['name', 'address', 'email', 'phone_number'];
            $headerLower = array_map('strtolower', $header);
            info('', ['header' => $header]);
            if($headerLower !== $expectedHeader1)
            {
                fclose($handle);
                return response()->json(['status' => 'error', 'message' => 'format csv header must be: name, address, email, phone_number'], 422);
            }
            // validation format csv must be firstname, lastname, email

            // validation at least one address found and count address
            while(($row = fgetcsv($handle, 0, ',')) !== false) 
            {
                $row = array_map('trim', $row); // buang spasi kiri/kanan
                info('', ['row' => $row]);
                if (count(array_filter($row)) > 0) 
                {
                    $totalCustomer++;
                }
            }

            fclose($handle);
            // info('', ['totalCustomer' => $totalCustomer]);
            if($totalCustomer < 50)
                return response()->json(['result' => 'error', 'message' => 'minimum customer must be 50'], 422);
            // validation at least one address found and count address
        }
        // validation format csv
        /* VALIDATION */

        $now = Carbon::now()->valueOf();
        $customer_list_name_format = strtolower(str_replace(' ', '_', $customer_list_name));
        $tmpfile = "customer_{$user_id}_{$customer_list_name_format}_{$now}.{$extension}";

        try 
        {
            /* UPLOAD TO DO SPACES */
            $path = Storage::disk('spaces')->putFileAs('users/predictid', $file, $tmpfile);
            info('', ['path' => $path, 'tmpfile' => $tmpfile]);
            $filedownload_url = Storage::disk('spaces')->url($path);
            info('', ['filedownload_url' => $filedownload_url]);
            $filedownload_url = str_replace('digitaloceanspaces', 'cdn.digitaloceanspaces', $filedownload_url);
            info('', ['filedownload_url' => $filedownload_url]);
            /* UPLOAD TO DO SPACES */

            /* SAVE TO DATABASE */
            $customer = LeadspeekCustomer::create([
                'user_id' => $user_id,
                'customer_list_name' => $customer_list_name,
                'total_customer' => $totalCustomer,
                'url' => $filedownload_url,
                'size_file' => $file_size_in_bytes
            ]);
            /* SAVE TO DATABASE */

            return response()->json(['result' => 'success','customer_id' => $customer->id]);
        }
        catch(\Exception $e)
        {
            $message = $e->getMessage();
            info('error', ['message' => $message]);
            return response()->json(['result' => 'error', 'message' => $message], 500);
        }
    }

    public function predictFetchReport(Request $request)
    {
        /* GET CAMPAIGN */
        $leadspeek_api_id = $request->leadspeek_api_id ?? "";
        $user_id_client = $request->user_id_client ?? "";
        $company_id_agency = $request->company_id_agency ?? "";
        $campaign = LeadspeekUser::where('leadspeek_api_id', $leadspeek_api_id)
            ->where('user_id', $user_id_client)
            ->where('company_id', $company_id_agency)
            ->where('leadspeek_type', 'predict')
            ->where('archived', 'F')
            ->first();
        if(empty($campaign)){
            return response()->json(['result' => 'error', 'message' => 'campaign not found'], 422);
        }
        /* GET CAMPAIGN */
        
        /* CREATE DISPATCH JOB */
        PredictFetchReportJob::dispatch($leadspeek_api_id, true)
            ->onQueue('predict_fetch_report')
            ->onConnection('redis');
        /* CREATE DISPATCH JOB */

        return response()->json(['result' => 'success', 'message' => 'fetch report job created']);
    }
    /* =============== PREDICT ID =============== */

    public function generateUniqueNumber()
    {
        do {
            $number = mt_rand(1, 1000000000);
        } while (JobProgress::where('job_id')->exists());

        return $number;
    }

    /* =============== CLEAN ID =============== */
    public function upload_cleanid(Request $request)
    {
        info('upload_cleanid 1.1', ['all' => $request->all()]);
        $cleanid = App::make(CleanID::class);

        /* VALIDATION REQUEST */
        $response = $cleanid->validateRequestUploadCleanID($request);
        info('upload_cleanid validateRequestUploadCleanID 1.1', ['response' => $response]);
        if(($response['status'] ?? '') == 'error'){
            $resCode = $response['status_code'] ?? 500;
            $resMessage = $response['message'] ?? 'Something went wrong';
            return response()->json(['error' => $resMessage], $resCode);
        }
        $file_id = $response['file_id'] ?? null;
        $cost_cleanid = (float) ($response['cost_cleanid'] ?? 0);
        $clean_api_id = $response['clean_api_id'] ?? null;
        $countLines = (int) ($response['countLines'] ?? 0);
        $filename = $response['filename'] ?? '';
        $filedownload_url = $response['filedownload_url'] ?? '';
        $path = $response['path'] ?? '';
        $user = $response['user'] ?? [];
        /* VALIDATION REQUEST */

        /* PROCESS QUEUE CLEAN ID */
        $response = $cleanid->processQueueCleanID($request, $user, $file_id, $cost_cleanid, $filedownload_url, $path, $countLines);
        info('upload_cleanid processQueueCleanID 1.3', ['response' => $response]);
        if(($response['status'] ?? '') == 'error'){
            $errorType = $response['error_type'] ?? '';
            $resCode = $response['status_code'] ?? 500;
            $resMessage = $response['message'] ?? 'Something went wrong';
            return response()->json(['error' => $resMessage, 'error_type' => $errorType], $resCode);
        }
        /* PROCESS QUEUE CLEAN ID */

        return response()->json([
            'message' => 'CSV processed',
            'count' => $countLines,
            'file_id' => $file_id,
            'file_name' => $filename,
            'total_entries' => $countLines,
            'clean_api_id' => $clean_api_id,
            'cost_cleanid' => $cost_cleanid,
        ], 200);
    }
    /* =============== CLEAN ID =============== */
}
