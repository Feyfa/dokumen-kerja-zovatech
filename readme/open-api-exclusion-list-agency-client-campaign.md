# OpenAPI Exclusion List Root, Agency, Client, Campaign

Tanggal update: 2026-04-20

Dokumen ini merangkum perubahan final untuk fitur OpenAPI Exclusion List di:

- `EMM-SANDBOX-APP`
- `EMM-SANDBOX-API`
- `sitesettings-api-docs`

Fokus perubahan:

- penambahan dan perapihan endpoint OpenAPI Exclusion List untuk root, agency, client, dan campaign
- dukungan input create lewat upload CSV atau JSON `emails`
- refactor logic OpenAPI Exclusion List ke service
- migrasi proses upload exclusion list di API menjadi async queue Redis
- progress tracking yang aman untuk job async
- failure tracking memakai `job_progress.error_message` dan `job_progress_chunks`
- access scope campaign/client agar hanya resource milik agency token yang bisa diakses

## Ringkasan Besar

Endpoint create Exclusion List sekarang menerima salah satu dari dua input:

1. Upload CSV file
   - public OpenAPI request memakai `multipart/form-data`
   - APP meneruskan ke API memakai Guzzle `multipart`

2. Send Emails Array
   - public OpenAPI request memakai `application/json`
   - APP meneruskan ke API memakai Guzzle `json`

Kedua input tidak boleh dikirim bersamaan.

Batas maksimum tetap 10,000 email.

## Endpoint OpenAPI

### Root

```text
POST /api/v1/developer/root/exclusion-list/create
GET /api/v1/developer/root/exclusion-list/status
DELETE /api/v1/developer/root/exclusion-list/purge
```

Root tidak membutuhkan `root_id` di request body karena root didapat dari bearer token.

### Agency

```text
POST /api/v1/developer/agency/exclusion-list/create
GET /api/v1/developer/agency/exclusion-list/status
DELETE /api/v1/developer/agency/exclusion-list/purge
```

Agency tidak membutuhkan `agency_id` di request body karena agency didapat dari bearer token.

### Client

```text
POST /api/v1/developer/client/exclusion-list/create
GET /api/v1/developer/client/exclusion-list/status
DELETE /api/v1/developer/client/exclusion-list/purge
```

Create, status, dan purge membutuhkan `client_id`.

Client harus berada di bawah agency token yang sedang dipakai.

### Campaign

```text
POST /api/v1/developer/campaign/exclusion-list/create
GET /api/v1/developer/campaign/exclusion-list/status
DELETE /api/v1/developer/campaign/exclusion-list/purge
```

Create, status, dan purge membutuhkan `campaign_id`.

Campaign harus berada di bawah agency token yang sedang dipakai.

## Request Create

### Upload CSV File

Gunakan `multipart/form-data`.

Root:

```text
csv_file: File_example.csv
```

Agency:

```text
csv_file: File_example.csv
```

Client:

```text
csv_file: File_example.csv
client_id: 192
```

Campaign:

```text
csv_file: File_example.csv
campaign_id: 861487
```

Catatan:

- `csv_file` harus benar-benar file upload, bukan URL/path.
- file harus `.csv`.
- maksimum 10,000 row.
- jangan kirim `csv_file` bersamaan dengan `emails`.

### Send Emails Array

Gunakan `application/json`.

Root:

```json
{
    "emails": [
        "john@example.com",
        "jane@example.com"
    ]
}
```

Agency:

```json
{
    "emails": [
        "john@example.com",
        "jane@example.com"
    ]
}
```

Client:

```json
{
    "client_id": 192,
    "emails": [
        "john@example.com",
        "jane@example.com"
    ]
}
```

Campaign:

```json
{
    "campaign_id": 861487,
    "emails": [
        "john@example.com",
        "jane@example.com"
    ]
}
```

Catatan:

- `emails` harus array.
- maksimum 10,000 item.
- jangan kirim `emails` bersamaan dengan `csv_file`.
- raw `emails` dihapus dari request sebelum log supaya log tidak penuh dan tidak menyimpan data email mentah.

## Response Status

Status endpoint mengembalikan data dari `job_progress`.

`jobProgress` sekarang mencakup:

```text
queue
progress
error
```

`jobDone` tetap khusus:

```text
done
```

Jika job gagal permanen setelah retry habis, response status dapat memuat:

```json
{
    "status": "error",
    "error_message": "..."
}
```

Catatan:

- top-level response status endpoint tetap `success` jika request status berhasil.
- detail gagal job ada di `data.jobProgress[*].status` dan `data.jobProgress[*].error_message`.

## EMM-SANDBOX-APP

Project lokal:

```text
/Users/jidan/Documents/CODE EMM/EMM-SANDBOX
```

File yang berubah atau dibuat:

```text
data/app/Http/Controllers/OpenApiController.php
data/app/Http/Controllers/LeadspeekController.php
data/app/Services/OpenApi/OpenApiValidationService.php
data/app/Services/OpenApi/OpenApiAgencyExclusionListService.php
data/app/Services/OpenApi/OpenApiRootExclusionListService.php
data/app/Services/OpenApi/OpenApiCampaignExclusionListService.php
data/app/Services/OpenApi/OpenApiClientExclusionListService.php
```

### OpenApiController

Controller Exclusion List dibuat lebih tipis.

Tiap endpoint sekarang melakukan:

1. ambil company id dari bearer token
2. validasi token root/agency
3. panggil service sesuai type exclusion
4. insert log
5. return response

Area controller dipisahkan dengan comment:

```php
// =======EXCLUSION ROOT START=======
// =======EXCLUSION ROOT END=======

// =======EXCLUSION CAMPAIGN START=======
// =======EXCLUSION CAMPAIGN END=======

// =======EXCLUSION CLIENT START=======
// =======EXCLUSION CLIENT END=======

// =======EXCLUSION AGENCY START=======
// =======EXCLUSION AGENCY END=======
```

### OpenApiValidationService

Validasi token dipisahkan:

```text
validationTokenOnlyRoot()
validationTokenOnlyAgency()
```

Root endpoint hanya boleh dipakai oleh root token.

Agency, client, dan campaign endpoint hanya boleh dipakai oleh agency token.

### OpenApiAgencyExclusionListService

Service untuk agency exclusion list.

Tanggung jawab:

- resolve agency dari token
- create agency exclusion list
- status agency exclusion list
- purge agency exclusion list
- validasi input create
- validasi CSV
- forward request ke EMM-SANDBOX-API
- format error response dari API
- hapus raw `emails` dari request sebelum log

### OpenApiRootExclusionListService

Service untuk root exclusion list.

Tanggung jawab:

- resolve root dari token
- create root exclusion list ke endpoint `/api/tools/optout/upload`
- status root exclusion list
- purge root exclusion list melalui `ToolController::purgeOptout()`
- validasi input create
- validasi CSV
- format error response dari API
- hapus raw `emails` dari request sebelum log

### OpenApiCampaignExclusionListService

Service untuk campaign exclusion list.

Tanggung jawab:

- validasi `campaign_id`
- memastikan campaign milik agency token yang sedang dipakai
- create campaign exclusion list ke endpoint `/api/leadspeek/suppressionlist/upload`
- status campaign exclusion list melalui `suppressionprogress`
- purge campaign exclusion list melalui `suppressionpurge`
- validasi input create
- validasi CSV
- format error response dari API
- hapus raw `emails` dari request sebelum log

Access scope campaign:

```text
leadspeek_users.leadspeek_api_id = campaign_id
leadspeek_users.company_id = company_id dari agency token
users.company_parent = company_id dari agency token
users.user_type = client
users.active = T
```

Jika campaign ada tetapi bukan milik agency token tersebut, response tetap dianggap `Campaign Not Found`.

### OpenApiClientExclusionListService

Service untuk client exclusion list.

Tanggung jawab:

- validasi `client_id`
- memastikan client milik agency token yang sedang dipakai
- create client exclusion list ke endpoint `/api/tools/optout-client/upload`
- status client exclusion list melalui `suppressionprogress`
- purge client exclusion list melalui `suppressionpurge`
- validasi input create
- validasi CSV
- format error response dari API
- hapus raw `emails` dari request sebelum log

Access scope client:

```text
users.id = client_id
users.company_parent = company_id dari agency token
users.user_type = client
users.active = T
```

Jika client ada tetapi bukan milik agency token tersebut, response tetap dianggap `Client Not Found`.

### LeadspeekController

`suppressionprogress()` diperbarui supaya status endpoint dapat menampilkan:

- `queue`
- `progress`
- `error`
- `done`

Perubahan penting:

- select `error_message`
- `jobProgress` berisi status `queue`, `progress`, dan `error`
- `jobDone` tetap berisi status `done`

Ini mencegah status upload yang masih queue atau gagal permanen terlihat seperti `File Upload not found`.

## EMM-SANDBOX-API

Project lokal:

```text
/Users/jidan/Documents/CODE EMM/EMM-SANDBOX-API
```

File yang berubah atau dibuat:

```text
app/Http/Controllers/UploadController.php
app/Jobs/ChunkCsvJob.php
app/Jobs/ChunkCsvClientJob.php
app/Jobs/ChunCsvOptoutJob.php
app/Jobs/InsertCsvJob.php
app/Jobs/InsertCsvClientJob.php
app/Jobs/InsertCsvOptoutJob.php
app/Jobs/Concerns/MarksExclusionJobProgressFailed.php
app/Models/JobProgress.php
app/Models/JobProgressChunk.php
config/queue.php
```

### Normalisasi Input

Di API, input create dinormalisasi menjadi file sebelum masuk ke flow chunk job.

Jika request membawa file CSV:

- API memakai file upload yang dikirim.
- API validasi file CSV dan limit row.

Jika request membawa `emails` array:

- API membuat temporary CSV.
- isi `emails` ditulis line-by-line ke file.
- setelah upload ke Spaces selesai, temporary file dihapus.

Helper di `UploadController.php`:

```text
resolveExclusionUploadInput()
resolveUploadedExclusionFile()
resolveEmailArrayExclusionFile()
removeEmailsFromRequest()
validateExclusionCsvFile()
```

Tujuan helper:

- menerima satu sumber input saja, CSV atau `emails`
- validasi batas maksimum 10,000 records
- validasi format email untuk `emails` array
- membuat temporary CSV untuk `emails` array
- menghapus raw `emails` dari request setelah dinormalisasi

### Redis Queue

Exclusion list upload sekarang memakai Redis queue.

Queue names:

```text
exclusion-list-chunk
exclusion-list-insert
```

Dispatch chunk job:

```text
onConnection('redis')
onQueue('exclusion-list-chunk')
```

Dispatch insert job:

```text
onConnection('redis')
onQueue('exclusion-list-insert')
```

Supervisor command yang disarankan:

```text
php artisan queue:work redis --queue=exclusion-list-chunk,exclusion-list-insert
```

Job setting:

```php
public $tries = 3;
public $timeout = 600;
public $backoff = 30;
```

Redis queue config:

```php
'retry_after' => 1200
```

### Progress Tracking

Sebelumnya job lama didesain seolah berjalan sync/berurutan.

Bug halus lama:

```php
if ($this->data['loopCount'] == $this->data['numChunks']) {
    status = done
}
```

Di async queue, chunk tidak selalu selesai berurutan.

Perbaikan:

- tracking parent memakai `job_progress.id`
- field legacy `job_id` diisi `0`
- tracking per chunk memakai table `job_progress_chunks`
- unique key: `(job_progress_id, chunk_index)`
- completion dihitung dari jumlah chunk yang benar-benar selesai
- update parent progress memakai `lockForUpdate()`

### Table job_progress

Kolom penting:

```text
id
job_id
lead_userid
company_id
leadspeek_api_id
suppression_type
filename
status
error_message
percentage
total_row
total_chunk
current_row
current_chunk
upload_at
done_at
created_at
updated_at
```

Query manual yang perlu ada:

```sql
ALTER TABLE job_progress
ADD COLUMN error_message TEXT NULL AFTER status;
```

Catatan:

- `job_id` adalah legacy field.
- Untuk tracking queue baru, gunakan `job_progress.id`.
- Upload baru dibuat dengan `job_id = 0`.

### Table job_progress_chunks

Table baru sebagai child/detail dari `job_progress`.

DDL referensi:

```sql
CREATE TABLE job_progress_chunks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_progress_id BIGINT UNSIGNED NOT NULL,
    chunk_index INT UNSIGNED NOT NULL,
    row_count INT NOT NULL DEFAULT 0,
    status VARCHAR(32) NOT NULL DEFAULT 'done',
    started_at INT UNSIGNED NULL,
    finished_at INT UNSIGNED NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY job_progress_chunks_progress_chunk_unique (job_progress_id, chunk_index),
    KEY job_progress_chunks_job_progress_id_index (job_progress_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

### Failed Queue Handling

Trait baru:

```text
app/Jobs/Concerns/MarksExclusionJobProgressFailed.php
```

Tanggung jawab:

- update `job_progress.status = error`
- isi `job_progress.error_message`
- isi `job_progress.done_at`
- untuk insert job, simpan detail gagal ke `job_progress_chunks`

Semua job exclusion sudah punya `failed()` handler:

```text
ChunkCsvJob
ChunkCsvClientJob
ChunCsvOptoutJob
InsertCsvJob
InsertCsvClientJob
InsertCsvOptoutJob
```

Jika insert DB gagal:

- exception dilempar ulang
- queue retry berjalan
- chunk tidak ditandai done

Jika job gagal permanen setelah retry habis:

- parent job progress menjadi `error`
- error message disimpan
- status endpoint dapat menampilkan detail error

### Cleanup File Spaces

Jika delete file di Spaces gagal setelah insert jobs sudah didispatch:

- error tetap dikirim/log
- job tidak dilempar ulang

Alasan:

- insert jobs sudah telanjur didispatch
- retry chunk job pada titik ini bisa menyebabkan dispatch ulang insert jobs

## sitesettings-api-docs

Project lokal lama:

```text
/Users/jidan/Documents/CODE VUE/sitesettings-api-docs
```

Perubahan docs sebelumnya mencakup halaman:

```text
src/views/agency/exclusion-list/AgencyCreate.vue
src/views/agency/exclusion-list/ClientCreate.vue
src/views/agency/exclusion-list/CampaignCreate.vue
src/views/agency/exclusion-list/AgencyCekStatus.vue
src/views/agency/exclusion-list/AgencyPurge.vue
src/components/app/SidebarComponent.vue
src/router/agency.js
```

Catatan:

- Setelah perubahan queue async terbaru, docs Vue tidak di-build ulang.
- Sesuai rule kerja terakhir, tidak menjalankan build Vue.

## Validasi Yang Sudah Dilakukan

### PHP Syntax Check

Menggunakan PHP 7.4:

```text
/opt/homebrew/opt/php@7.4/bin/php
```

APP:

```text
php -l data/app/Http/Controllers/OpenApiController.php
php -l data/app/Http/Controllers/LeadspeekController.php
php -l data/app/Services/OpenApi/OpenApiValidationService.php
php -l data/app/Services/OpenApi/OpenApiAgencyExclusionListService.php
php -l data/app/Services/OpenApi/OpenApiRootExclusionListService.php
php -l data/app/Services/OpenApi/OpenApiCampaignExclusionListService.php
php -l data/app/Services/OpenApi/OpenApiClientExclusionListService.php
```

API:

```text
php -l app/Http/Controllers/UploadController.php
php -l app/Jobs/ChunkCsvJob.php
php -l app/Jobs/ChunkCsvClientJob.php
php -l app/Jobs/ChunCsvOptoutJob.php
php -l app/Jobs/InsertCsvJob.php
php -l app/Jobs/InsertCsvClientJob.php
php -l app/Jobs/InsertCsvOptoutJob.php
php -l app/Jobs/Concerns/MarksExclusionJobProgressFailed.php
php -l app/Models/JobProgress.php
php -l app/Models/JobProgressChunk.php
```

Semua syntax check berhasil.

### Diff Check

Berhasil:

```text
git diff --check
```

Dijalankan di:

```text
EMM-SANDBOX-APP
EMM-SANDBOX-API
```

### Build

Tidak menjalankan build Vue setelah perubahan terbaru.

Alasan:

- perubahan terbaru tidak menyentuh Vue
- user meminta jangan build jika ada perubahan Vue

## Catatan Belum Dilakukan

Functional HTTP test belum dijalankan langsung ke endpoint dengan token real.

Test manual yang disarankan:

1. Root create dengan CSV.
2. Root create dengan JSON `emails`.
3. Root status saat job `queue`, `progress`, `done`, dan `error`.
4. Root purge.
5. Agency create dengan CSV.
6. Agency create dengan JSON `emails`.
7. Agency status saat job `queue`, `progress`, `done`, dan `error`.
8. Agency purge.
9. Client create dengan CSV plus `client_id`.
10. Client create dengan JSON `client_id` plus `emails`.
11. Client status.
12. Client purge.
13. Campaign create dengan CSV plus `campaign_id`.
14. Campaign create dengan JSON `campaign_id` plus `emails`.
15. Campaign status.
16. Campaign purge.
17. Request yang mengirim `csv_file` dan `emails` bersamaan harus ditolak.
18. Request lebih dari 10,000 records/items harus ditolak.
19. Invalid CSV email harus ditolak.
20. Agency token mencoba akses client milik agency lain harus `Client Not Found`.
21. Agency token mencoba akses campaign milik agency lain harus `Campaign Not Found`.

## Commit Message Rekomendasi

APP:

```text
JD-TASK-EMM-923 refactor OpenAPI exclusion list services
```

API:

```text
JD-TASK-EMM-923 add async exclusion list queue handling
```

## Kesimpulan

Secara syntax dan review logic, perubahan sudah aman untuk masuk tahap manual smoke test.

Flow akhir:

```text
OpenAPI public endpoint
    -> EMM-SANDBOX-APP validation, service layer, access scope, forwarding
    -> EMM-SANDBOX-API normalize CSV/emails to file
    -> Redis queue exclusion-list-chunk
    -> Redis queue exclusion-list-insert
    -> job_progress + job_progress_chunks tracking
    -> status endpoint menampilkan queue/progress/error/done
```

Pendekatan ini menjaga endpoint public tetap sama, sambil membuat proses upload exclusion list lebih aman untuk async queue.
    