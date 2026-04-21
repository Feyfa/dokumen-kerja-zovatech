# OpenAPI Exclusion List Root, Agency, Client, Campaign

Tanggal update: 2026-04-21

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

Batas maksimum dibedakan berdasarkan metode input:

- CSV file: maksimal 10,000 row non-empty.
- JSON `emails`: maksimal 1,000 item.

Alasan business rule: JSON `emails` dipakai untuk payload kecil. Untuk bulk upload, developer harus memakai CSV file.

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
- file harus berisi minimal satu row non-empty. CSV kosong atau hanya row kosong akan langsung ditolak API dengan `422`.
- maksimum 10,000 row non-empty. Validasi maksimum CSV dilakukan langsung oleh API controller sebelum dispatch queue.
- invalid email di CSV tidak menolak upload. Row invalid akan di-skip saat insert job.
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

- key `emails` wajib ada dan harus list array JSON.
- JSON object/associative array seperti `"emails": {"a": "john@example.com"}` ditolak dengan `422`.
- setiap item `emails` harus scalar/string-like. Nested object/array di dalam `emails` ditolak dengan `422`.
- maksimum 1,000 item.
- untuk list lebih besar, gunakan upload CSV.
- `emails: []` atau semua item kosong akan ditolak oleh EMM-SANDBOX-API dengan `422`.
- invalid email di array tidak menolak request. Item invalid akan di-skip saat insert job.
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

Jika job gagal permanen setelah retry habis, database `job_progress` menyimpan:

```json
{
    "status": "error",
    "error_message": "..."
}
```

Catatan:

- top-level response status endpoint tetap `success` jika request status berhasil.
- response `suppressionprogress()` APP saat ini menampilkan `data.jobProgress[*].status = error`, tetapi belum menyertakan `error_message` karena field tersebut belum dipilih di query status APP.
- detail gagal job tetap tersimpan di database `job_progress.error_message`.

## EMM-SANDBOX-APP

Project lokal:

```text
C:\wamp64\www\EMM-SANDBOX-APP
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
data/app/Models/JobProgressChunk.php
front/src/components/SidebarPlugin/SideBar.vue
front/src/components/Tree/NodeTree.vue
front/src/pages/Modules/Auth/ConfigApp/Client.vue
front/src/pages/Modules/Auth/ConfigApp/OptOutList.vue
front/src/pages/Modules/Auth/Leedspeek/V1Client.vue
front/src/pages/Modules/Auth/LeedspeekB2b/ClientV1.vue
front/src/pages/Modules/Auth/LeedspeekEnhance/ClientV1.vue
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
- validasi JSON `emails` sebagai `present`, `array`, dan maksimal 1,000 item
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
- validasi JSON `emails` sebagai `present`, `array`, dan maksimal 1,000 item
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
- validasi JSON `emails` sebagai `present`, `array`, dan maksimal 1,000 item
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
- validasi JSON `emails` sebagai `present`, `array`, dan maksimal 1,000 item
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

- `jobProgress` berisi status `queue`, `progress`, dan `error`
- `jobDone` tetap berisi status `done`
- response status APP saat ini tidak men-select `error_message`, sehingga detail error job masih tersimpan di database/API tetapi belum ikut tampil di response `suppressionprogress()`.

Ini mencegah status upload yang masih queue atau gagal permanen terlihat seperti `File Upload not found`.

### Purge Exclusion List

Purge root, agency, client, dan campaign sekarang ikut membersihkan tracking upload:

- hapus data exclusion/optout yang sesuai scope.
- hapus `job_progress` dengan status `done` dan `error`.
- hapus child rows di `job_progress_chunks`.
- jika job progress berstatus `error` dan masih punya `path`, file source di Spaces ikut dicoba dihapus.
- jika tidak ada record untuk dipurge, response tetap `success` dengan title `No Records to Purge`.

Model `data/app/Models/JobProgressChunk.php` ditambahkan di APP supaya controller dapat membersihkan child rows saat purge.

### UI Exclusion List

Beberapa UI upload exclusion list ikut disesuaikan:

- polling `checkStatusFileUpload()` yang sebelumnya 5 detik dibuat menjadi 3 detik pada UI terkait.
- polling status dipanggil langsung setelah upload success, bukan menunggu delay manual.
- jika upload gagal, UI menampilkan message error dari response API jika tersedia.
- teks bantuan upload diposisikan sebelum area progress/done agar progress list tetap mudah dibaca.
- separator antara progress dan done dibuat conditional agar tidak dobel saat progress sudah kosong.
- tombol konfirmasi purge memakai `customClass` SweetAlert agar styling konsisten.

## EMM-SANDBOX-API

Project lokal:

```text
C:\wamp64\www\EMM-SANDBOX-API
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
app/Jobs/Concerns/MarksExclusionJob.php
app/Models/JobProgress.php
app/Models/JobProgressChunk.php
config/queue.php
```

### Normalisasi Input

Di API, input create dinormalisasi menjadi file sebelum masuk ke flow chunk job.

Jika request membawa file CSV:

- API memakai file upload yang dikirim.
- API validasi file upload, extension `.csv`, dan minimal satu row non-empty.
- CSV kosong atau hanya row kosong langsung return `422` sebelum dispatch queue.
- CSV lebih dari 10,000 row non-empty langsung return `422` sebelum dispatch queue.
- API tidak validasi format email per-row di controller.
- chunk job tetap melakukan cleanup row kosong dan unique row sebelum dispatch insert jobs.

Jika request membawa `emails` array:

- API validasi `emails` sebagai required list array maksimal 1,000 item.
- API menolak JSON object/associative array dan nested object/array di dalam `emails`.
- API membuat temporary CSV.
- isi `emails` non-empty ditulis line-by-line ke file.
- jika array kosong atau semua item kosong setelah trim, API return `422`.
- API tidak validasi format email per-item di controller.
- setelah upload ke Spaces selesai, temporary file dihapus.

Helper di `UploadController.php`:

```text
resolveExclusionUploadInput()
resolveUploadedExclusionFile()
resolveEmailArrayExclusionFile()
removeEmailsFromRequest()
```

Tujuan helper:

- menerima satu sumber input saja, CSV atau `emails`
- validasi file upload CSV dan extension `.csv`
- validasi CSV punya minimal satu row non-empty sebelum dispatch queue
- validasi CSV maksimal 10,000 row non-empty sebelum dispatch queue
- validasi `emails` sebagai required list array maksimal 1,000 item
- menolak JSON object/associative array untuk `emails`
- menolak nested object/array di dalam `emails`
- menolak `emails` array kosong atau semua item kosong
- membuat temporary CSV untuk `emails` array
- menghapus raw `emails` dari request setelah dinormalisasi

Validasi format email sengaja dipindah ke insert job. Invalid email tidak lagi membuat upload gagal total.

### Validasi dan Skip Invalid Email

Insert job yang melakukan validasi email:

```text
InsertCsvJob
InsertCsvClientJob
InsertCsvOptoutJob
```

Behavior insert:

- trim dan lowercase row sebelum validasi.
- validasi memakai `FILTER_VALIDATE_EMAIL`.
- row invalid, row kosong, dan header seperti `email` akan di-skip.
- hanya valid email yang masuk ke `suppression_lists` atau `optout_lists`.
- `markChunkExclusionAsDone()` memakai jumlah row valid yang berhasil diproses.
- status akhir tetap `done` dan `percentage = 100` ketika semua chunk selesai, walaupun `current_row < total_row`.
- setelah semua chunk selesai, insert job terakhir mencoba menghapus file source di Spaces memakai `job_progress.path`.

Contoh CSV:

```csv
email
valid@example.com
salah-email
other@example.com
```

Expected result:

```text
total_row = 4
current_row = 2
status = done
percentage = 100
```

Jika semua row non-empty invalid, job tetap bisa selesai:

```text
current_row = 0
total_row > 0
status = done
percentage = 100
```

### Empty Input Handling

CSV kosong:

```text
POST upload CSV kosong
=> 422
=> The file must contain at least one non-empty row.
```

JSON `emails` kosong:

```json
{
    "emails": []
}
```

Response:

```text
422
The emails field must contain at least one non-empty value.
```

JSON `emails` lebih dari 1,000 item:

```text
422
Maximum 1000 records are allowed via emails array. For larger lists, please use CSV file upload.
```

CSV lebih dari 10,000 row non-empty:

```text
422
Maximum 10000 records are allowed.
```

JSON `emails` berisi item kosong semua:

```json
{
    "emails": ["", "   "]
}
```

Response:

```text
422
The emails field must contain at least one non-empty value.
```

JSON `emails` berbentuk object/associative array:

```json
{
    "emails": {
        "a": "john@example.com"
    }
}
```

Response:

```text
422
The emails field must be a list array.
```

JSON `emails` berisi nested object/array:

```json
{
    "emails": [
        "john@example.com",
        {
            "a": "jane@example.com"
        }
    ]
}
```

Response:

```text
422
Each emails item must be a string.
```

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
- chunk rows dibuat lebih dulu dengan status `queue`, lalu insert job mengubahnya menjadi `progress` saat mulai insert dan menjadi `done` atau `error` saat terminal.
- completion dihitung dari jumlah chunk yang benar-benar selesai
- update parent progress memakai `lockForUpdate()`
- parent `job_progress` disinkronkan ulang dari `job_progress_chunks` pada setiap update chunk terminal, bukan lagi hanya memakai increment `current_chunk + 1`.
- jika insert job terpanggil ulang untuk chunk yang sudah `done` atau `error`, parent tetap disinkronkan ulang dari child chunks supaya progress tidak drift.
- `current_row` menghitung row valid yang diproses insert job, sehingga bisa lebih kecil dari `total_row` jika ada invalid row yang di-skip.
- `percentage` tetap `100` saat semua chunk selesai dan status menjadi `done`.

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
path
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
- `path` menyimpan lokasi file source di Spaces agar file bisa dihapus setelah semua chunk selesai atau saat purge job error.

### Table job_progress_chunks

Table baru sebagai child/detail dari `job_progress`.

DDL referensi:

```sql
CREATE TABLE job_progress_chunks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_progress_id BIGINT UNSIGNED NOT NULL,
    chunk_index INT UNSIGNED NOT NULL,
    row_count INT NOT NULL DEFAULT 0,
    status VARCHAR(32) NOT NULL DEFAULT 'queue',
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

Catatan:

- default `job_progress_chunks.status` harus `queue`, bukan `done`.
- table sebaiknya memakai InnoDB agar transaction dan `lockForUpdate()` efektif saat worker insert berjalan paralel.

### Exclusion Queue Progress Trait

Trait baru:

```text
app/Jobs/Concerns/MarksExclusionJob.php
```

Helper utama:

```text
markChunkExclusionAsDone()
markChunkExclusionAsFailed()
markInsertExclusionAsProgress()
markInsertExclusionAsFailed()
```

Tanggung jawab `markInsertExclusionAsProgress()`:

- dipakai oleh insert job sebelum proses insert email ke database.
- update chunk terkait di `job_progress_chunks` dari `queue` menjadi `progress`.
- isi `started_at` agar chunk yang sedang diproses bisa dibedakan dari chunk yang belum diambil worker.
- tidak mengubah chunk yang sudah terminal (`done` atau `error`).
- tidak menghitung chunk sebagai selesai dan tidak mengubah counter parent `job_progress`.

Tanggung jawab `markChunkExclusionAsDone()`:

- dipakai oleh insert job ketika chunk berhasil diproses.
- update chunk terkait di `job_progress_chunks` menjadi `done`.
- lock chunk terkait dengan `lockForUpdate()`.
- sinkronisasi ulang `job_progress.current_chunk` dari jumlah child chunk berstatus `done` atau `error`.
- sinkronisasi ulang `job_progress.current_row` dari jumlah row valid pada child chunk berstatus `done`.
- jika semua chunk sudah selesai, parent `job_progress` menjadi `done` dan `percentage = 100`.
- menghapus file source di Spaces saat chunk terminal terakhir selesai.

Tanggung jawab `markChunkExclusionAsFailed()`:

- dipakai oleh chunk job.
- update `job_progress.status = error`.
- isi `job_progress.error_message`.
- isi `job_progress.done_at`.
- tidak menyentuh `job_progress_chunks`.

Tanggung jawab `markInsertExclusionAsFailed()`:

- dipakai oleh insert job.
- update chunk terkait di `job_progress_chunks` menjadi `error`.
- simpan error detail di `job_progress_chunks.error_message`.
- tidak membuat parent `job_progress` menjadi `error`.
- sinkronisasi ulang parent `job_progress` dari child chunks karena chunk tersebut sudah terminal.
- jika semua chunk sudah terminal, parent `job_progress` menjadi `done` dan `percentage = 100`.

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
- chunk tidak ditandai `done`

Jika chunk job gagal permanen setelah retry habis:

- parent job progress menjadi `error`
- error message disimpan di `job_progress.error_message`

Jika insert job gagal permanen setelah retry habis:

- parent job progress tetap berjalan sebagai best-effort process.
- chunk terkait menjadi `error` di `job_progress_chunks`.
- error message insert disimpan di `job_progress_chunks.error_message`.
- parent job progress tetap dapat selesai `done` jika semua chunk sudah terminal (`done` atau `error`).

### Cleanup File Spaces

File source di Spaces tidak lagi dihapus oleh chunk job setelah insert jobs didispatch.

Cleanup dilakukan pada dua titik:

1. Insert job terminal terakhir menghapus file setelah semua chunk selesai dan parent job menjadi `done`.
2. Purge menghapus file source untuk `job_progress` berstatus `error` yang masih menyimpan `path`.

Jika delete file di Spaces gagal:

- error tetap dikirim/log
- job tidak dilempar ulang

Alasan:

- kegagalan cleanup file tidak boleh mengubah status job yang sudah selesai
- retry cleanup dapat menyebabkan efek samping yang tidak diperlukan

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

Menggunakan PHP lokal WAMP:

```text
php
```

APP:

```text
php -l data/app/Http/Controllers/OpenApiController.php
php -l data/app/Http/Controllers/LeadspeekController.php
php -l data/app/Http/Controllers/ToolController.php
php -l data/app/Services/OpenApi/OpenApiValidationService.php
php -l data/app/Services/OpenApi/OpenApiAgencyExclusionListService.php
php -l data/app/Services/OpenApi/OpenApiRootExclusionListService.php
php -l data/app/Services/OpenApi/OpenApiCampaignExclusionListService.php
php -l data/app/Services/OpenApi/OpenApiClientExclusionListService.php
php -l data/app/Models/JobProgressChunk.php
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
php -l app/Jobs/Concerns/MarksExclusionJob.php
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

- perubahan terbaru menyentuh Vue, tetapi request saat review dokumentasi tidak meminta build.
- belum ada build ulang frontend setelah perubahan polling/UI exclusion list.

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
18. JSON `emails` lebih dari 1,000 item harus ditolak langsung oleh APP/API dengan arahan memakai CSV.
19. CSV lebih dari 10,000 row non-empty harus ditolak langsung oleh API dengan `422`.
20. CSV kosong atau hanya row kosong harus ditolak langsung dengan `422`.
21. JSON `emails: []` atau semua item kosong harus ditolak langsung dengan `422`.
22. Invalid CSV email harus di-skip saat insert, bukan menolak upload.
23. Invalid JSON email harus di-skip saat insert, bukan menolak request.
24. JSON `emails` berbentuk object/associative array harus ditolak dengan `422`.
25. JSON `emails` berisi nested object/array harus ditolak dengan `422`.
26. Agency token mencoba akses client milik agency lain harus `Client Not Found`.
27. Agency token mencoba akses campaign milik agency lain harus `Campaign Not Found`.

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
    
