# OpenAPI Exclusion List Agency, Client, Campaign

Tanggal: 2026-04-17

Dokumen ini merangkum perubahan untuk fitur OpenAPI Exclusion List, terutama:

- penambahan Exclusion List Agency di OpenAPI
- dukungan input `emails` array selain upload CSV
- penyesuaian logic forwarding dari EMM-SANDBOX-APP ke EMM-SANDBOX-API
- normalisasi input di EMM-SANDBOX-API agar flow job existing tetap dipakai
- update dokumentasi OpenAPI untuk Agency, Client, dan Campaign

## Ringkasan Besar

Sebelumnya endpoint Exclusion List hanya menerima file CSV. Setelah perubahan ini, endpoint create bisa menerima salah satu dari dua cara:

1. Upload CSV file
   - public OpenAPI request memakai `multipart/form-data`
   - APP meneruskan ke API memakai Guzzle `multipart`

2. Send Emails Array
   - public OpenAPI request memakai `application/json`
   - APP meneruskan ke API memakai Guzzle `json`

Kedua cara tidak boleh dikirim bersamaan. Maksimum tetap 10,000 record.

## Endpoint OpenAPI

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

Create tetap membutuhkan `client_id`.

### Campaign

```text
POST /api/v1/developer/campaign/exclusion-list/create
GET /api/v1/developer/campaign/exclusion-list/status
DELETE /api/v1/developer/campaign/exclusion-list/purge
```

Create tetap membutuhkan `campaign_id`.

## Request Create

### Upload CSV File

Gunakan `multipart/form-data`.

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

- `emails` harus array of email strings.
- maksimum 10,000 item.
- jangan kirim `emails` bersamaan dengan `csv_file`.

## EMM-SANDBOX-APP

Project:

```text
C:\wamp64\www\EMM-SANDBOX-APP
```

File yang berubah:

```text
data/routes/api.php
data/app/Http/Controllers/OpenApiController.php
data/app/Http/Controllers/LeadspeekController.php
data/app/Services/OpenApi/OpenApiValidationService.php
data/app/Services/OpenApi/OpenApiAgencyExclusionListService.php
```

### Route

Ditambahkan route OpenAPI untuk Agency Exclusion List:

```text
POST /developer/agency/exclusion-list/create
GET /developer/agency/exclusion-list/status
DELETE /developer/agency/exclusion-list/purge
```

### OpenApiAgencyExclusionListService

Service baru dibuat untuk Agency Exclusion List.

Tanggung jawab service:

- validasi token agency
- resolve agency dari token
- create exclusion list agency
- check status agency
- purge agency
- validasi input create
- forward request ke EMM-SANDBOX-API
- handle error response dari API

Agency create tidak membutuhkan `agency_id` karena agency diambil dari token.

### OpenApiController

Create Exclusion List untuk root/campaign/client/agency sekarang mendukung:

- `csv_file` upload
- `emails` JSON array

Untuk CSV:

- APP menerima `multipart/form-data`
- APP meneruskan ke API memakai Guzzle `multipart`

Untuk emails array:

- APP menerima `application/json`
- APP meneruskan ke API memakai Guzzle `json`

Log request tidak menyimpan raw `emails`. Yang disimpan hanya jumlahnya, misalnya `emails_count`, supaya log tidak penuh dan tidak menyimpan data email mentah.

### OpenApiValidationService

Pesan error untuk `validationTokenOnlyAgency` diperbaiki supaya lebih sesuai konteks.

### LeadspeekController

Ada perbaikan di purge agency:

- sebelumnya query agency purge berisiko terlalu luas
- sekarang agency purge difilter dengan `company_id`

Ini mencegah purge agency menghapus data agency lain.

## EMM-SANDBOX-API

Project:

```text
C:\wamp64\www\EMM-SANDBOX-API
```

File yang berubah:

```text
app/Http/Controllers/UploadController.php
```

### Normalisasi Input

Di API, input create dinormalisasi menjadi file path sebelum masuk ke flow job existing.

Jika request membawa file CSV:

- API memakai file upload yang dikirim.
- API validasi file CSV dan limit row.

Jika request membawa `emails` array:

- API membuat temporary CSV.
- isi `emails` ditulis line-by-line ke file.
- setelah itu flow existing tetap dipakai.

Dengan cara ini, job existing tidak perlu dibuat ulang menjadi records-based job.

### Helper Baru

Helper yang ditambahkan di `UploadController.php`:

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
- menjaga flow existing tetap memakai file path
- menghapus `emails` dari request setelah dinormalisasi supaya tidak terus terbawa di memory/log

## Dokumentasi OpenAPI

Project:

```text
C:\builds\sitesettings-api-docs
```

File yang berubah atau dibuat:

```text
src/views/agency/exclusion-list/AgencyCreate.vue
src/views/agency/exclusion-list/ClientCreate.vue
src/views/agency/exclusion-list/CampaignCreate.vue
src/views/agency/exclusion-list/AgencyCekStatus.vue
src/views/agency/exclusion-list/AgencyPurge.vue
src/components/app/SidebarComponent.vue
src/router/agency.js
```

### Create Docs

Docs create untuk Agency, Client, dan Campaign dibuat seragam.

Struktur halaman:

```text
1. Upload CSV File
2. Send Emails Array
```

Setiap section menampilkan:

- parameter di kiri
- endpoint route bar di kanan
- header
- request
- response

Docs menggunakan `DocsPageLayout`, sehingga muncul navigasi `ON THIS PAGE`.

## Validasi Yang Sudah Dilakukan

### PHP Syntax Check

Berhasil:

```text
php -l data/app/Http/Controllers/OpenApiController.php
php -l data/app/Services/OpenApi/OpenApiAgencyExclusionListService.php
php -l data/app/Http/Controllers/LeadspeekController.php
php -l data/app/Services/OpenApi/OpenApiValidationService.php
php -l app/Http/Controllers/UploadController.php
```

Catatan:

- muncul warning Xdebug lokal: `xdebug.log could not be opened`
- warning tersebut bukan error aplikasi

### Diff Check

Berhasil:

```text
git diff --check
```

Dijalankan di:

```text
EMM-SANDBOX-APP
EMM-SANDBOX-API
sitesettings-api-docs
```

Catatan:

- warning line ending LF/CRLF muncul di Windows
- warning tersebut bukan error logic

### Docs Build

Berhasil:

```text
npm run build
```

Dijalankan di:

```text
C:\builds\sitesettings-api-docs
```

Catatan:

- warning Browserslist/caniuse-lite masih muncul
- warning chunk size masih muncul
- keduanya warning existing build, bukan error dari perubahan fitur ini

## Catatan Belum Dilakukan

Functional HTTP test belum dijalankan langsung ke endpoint dengan token real.

Test manual yang disarankan:

1. Agency create dengan CSV.
2. Agency create dengan JSON `emails`.
3. Client create dengan CSV plus `client_id`.
4. Client create dengan JSON `client_id` plus `emails`.
5. Campaign create dengan CSV plus `campaign_id`.
6. Campaign create dengan JSON `campaign_id` plus `emails`.
7. Coba request yang mengirim `csv_file` dan `emails` bersamaan, harus ditolak.
8. Coba request lebih dari 10,000 records/items, harus ditolak.
9. Coba purge agency dan pastikan hanya data agency terkait yang terkena.

## Kesimpulan

Secara syntax, build, dan review logic, perubahan sudah siap untuk diuji manual.

Flow akhir:

```text
OpenAPI public endpoint
    -> EMM-SANDBOX-APP validation and forwarding
    -> EMM-SANDBOX-API normalize CSV/emails to file path
    -> existing exclusion list job flow
```

Pendekatan ini menjaga logic utama tetap reusable dan membuat UI atau aplikasi lain di masa depan cukup mengirim salah satu input:

- file CSV
- JSON `emails` array

