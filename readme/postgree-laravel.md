# PostgreSQL & Laravel Guide

## 0. Install PostgreSQL di Windows 11 + WAMP

### Tujuan

Menginstall PostgreSQL di Windows 11 dan menghubungkannya dengan:
- pgAdmin (desktop UI)
- DBeaver (database client)
- Adminer (web UI seperti phpMyAdmin)
- Laravel / PHP

### Environment yang Digunakan

| Komponen   | Versi/Nilai |
|-----------|------------|
| OS        | Windows 11 |
| Server    | WAMP       |
| PostgreSQL| v16        |

### 1. Download PostgreSQL

Buka website: https://www.enterprisedb.com/downloads/postgres-postgresql-downloads

Pilih dan download:
- Versi: **PostgreSQL 16**
- Platform: **Windows x86-64 installer** (postgresql-16.x-windows-x64.exe)

### 2. Install PostgreSQL

Jalankan installer dan ikuti wizard instalasi dengan konfigurasi:

| Parameter | Nilai |
|-----------|-------|
| Installation Directory | `C:\Program Files\PostgreSQL\16` |
| Port | `5432` |
| Password (user postgres) | `root` (atau sesuai preferensi) |
| Locale | Default |
| Components | ✓ PostgreSQL Server, ✓ pgAdmin 4, ✓ Command Line Tools, ✓ Stack Builder |
| Data Directory | Default |

### 3. Verifikasi Instalasi

Setelah selesai, akan terinstall:
- PostgreSQL Server
- pgAdmin 4
- Stack Builder

### 4. Membuka pgAdmin

Buka aplikasi **pgAdmin 4**. Di panel kiri akan muncul:

```
Servers
 └ PostgreSQL 16
     └ Databases
```

### 5. Membuat Database

1. Klik kanan pada **Databases**
2. Pilih **Create → Database**
3. Isi:
   - **Name**: `belajar`
   - **Owner**: `postgres`

### 6. Membuat Table

1. Masuk ke: **Databases → belajar → Schemas → public → Tables**
2. Klik kanan **Tables**
3. Pilih **Create → Table**

### 7. Menghubungkan PostgreSQL ke DBeaver

1. Buka **DBeaver**
2. Pilih **Create connection → PostgreSQL**
3. Isi konfigurasi:

| Parameter | Nilai |
|-----------|-------|
| Host | `localhost` |
| Port | `5432` |
| Database | `belajar` |
| Username | `postgres` |
| Password | `root` |

> Jika driver belum ada, DBeaver akan download otomatis.

### 8. Mengaktifkan Extension PostgreSQL di PHP (WAMP)

Agar PHP dan Laravel bisa connect ke PostgreSQL:

1. Buka: **WAMP → PHP → php.ini**
2. Cari:
   ```ini
   ;extension=pgsql
   ;extension=pdo_pgsql
   ```
3. Ubah menjadi:
   ```ini
   extension=pgsql
   extension=pdo_pgsql
   ```
4. Restart WAMP: **Restart All Services**

### 9. Menggunakan PostgreSQL di Web (Adminer)

1. Download dari: https://www.adminer.org/ (Adminer 5.x .php)
2. Simpan ke: `C:\wamp64\www\adminer.php`
3. Buka di browser: http://localhost/adminer.php
4. Login dengan:
   - **System**: PostgreSQL
   - **Server**: localhost
   - **Username**: postgres
   - **Password**: root
   - **Database**: belajar

### 10. Struktur PostgreSQL

PostgreSQL memiliki struktur hirarki:

```
Server
 └ Database
     └ Schema
         └ Table
```

**Contoh:**
```
PostgreSQL 16
 └ belajar
     └ public
         ├ barang
         └ products
```

**Query table:**
```sql
select * from products;

-- atau dengan prefix schema
select * from public.products;
```

### 11. Tools yang Digunakan

| Tool    | Fungsi |
|---------|--------|
| pgAdmin | GUI resmi PostgreSQL |
| DBeaver | Client database untuk developer |
| Adminer | Web UI seperti phpMyAdmin |
| Laravel | Framework backend |

### Hasil Akhir Setup

- **PostgreSQL Server 16** berjalan di: `localhost:5432`
- Akses database melalui: pgAdmin, DBeaver, Adminer
- Laravel bisa connect menggunakan driver: `pdo_pgsql`

---


## 1. Cara Query Laravel Menggunakan Lebih dari 1 Database

Laravel mendukung penggunaan lebih dari satu koneksi database dalam satu aplikasi.
Misalnya:
- PostgreSQL sebagai database utama
- MySQL sebagai database tambahan (secondary / legacy database)

### Query Menggunakan Database Default

Jika tidak menentukan connection, Laravel akan menggunakan database default yang diatur di file `.env`.

```php
User::where('email', $email)->first();
```

Jika konfigurasi `.env`:
```
DB_CONNECTION=pgsql
```

Maka query tersebut akan dijalankan menggunakan PostgreSQL.

### Query Menggunakan Database Connection Lain

Jika ingin menjalankan query menggunakan database lain, gunakan method `on()`.

```php
User::on('mysql_secondary')
    ->where('email', $email)
    ->first();
```

Connection `mysql_secondary` harus sudah didefinisikan di file `config/database.php`.

### Contoh Konfigurasi Multiple Database

**File `.env`:**
```
DB_CONNECTION=pgsql

MYSQL_SECONDARY_HOST=127.0.0.1
MYSQL_SECONDARY_DATABASE=legacy_db
MYSQL_SECONDARY_USERNAME=root
MYSQL_SECONDARY_PASSWORD=secret
```

**File `config/database.php`:**
```php
'mysql_secondary' => [
    'driver' => 'mysql',
    'host' => env('MYSQL_SECONDARY_HOST'),
    'database' => env('MYSQL_SECONDARY_DATABASE'),
    'username' => env('MYSQL_SECONDARY_USERNAME'),
    'password' => env('MYSQL_SECONDARY_PASSWORD'),
],
```

### Kesimpulan

- Query tanpa menentukan connection → menggunakan database default
- Query dengan `on('connection_name')` → menggunakan database tertentu

---

## 2. Cara Menggunakan JSONB di PostgreSQL

Contoh penggunaan JSONB pada PostgreSQL dengan Laravel.

### Membuat Migration

Buat migration:
```bash
php artisan make:migration create_users_table
```

Isi migration:
```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->json('settings'); // otomatis menjadi JSONB di PostgreSQL
    $table->timestamps();
});
```

Walaupun menggunakan tipe `json`, Laravel biasanya akan menggunakan tipe `JSONB` ketika database yang digunakan adalah PostgreSQL.

Jalankan migration:
```bash
php artisan migrate
```

### Struktur Table di PostgreSQL

```
users
-----
id
name
settings JSONB
created_at
updated_at
```

### Membuat Model

Tambahkan casting JSON supaya otomatis menjadi array:

```php
class User extends Model
{
    protected $casts = [
        'settings' => 'array',
    ];
}
```

Fungsi casting ini:
- Otomatis `json_encode()` saat insert/update
- Otomatis decode JSON saat mengambil data

### Insert Data JSON

```php
User::create([
    'name' => 'Jidan',
    'settings' => [
        'theme' => 'dark',
        'language' => 'id',
        'notifications' => true
    ]
]);
```

Data akan tersimpan di PostgreSQL sebagai JSONB:
```json
{
  "theme": "dark",
  "language": "id",
  "notifications": true
}
```

### Mengambil Data JSON

```php
$user = User::first();
$user->settings;
```

Hasil yang didapat sudah berupa array PHP:
```php
[
  "theme" => "dark",
  "language" => "id",
  "notifications" => true
]
```

### Query Berdasarkan JSON Field

```php
User::where('settings->theme', 'dark')->get();
```

Laravel akan menghasilkan query PostgreSQL:
```sql
SELECT *
FROM users
WHERE settings->>'theme' = 'dark';
```

### Query JSON Nested

```php
User::where('settings->preferences->theme', 'dark')->get();
```

### Query JSON Array

```php
User::whereJsonContains('settings->roles', 'admin')->get();
```

Query PostgreSQL yang dihasilkan:
```sql
settings->'roles' ? 'admin'
```

### Update JSON

**Update seluruh field JSON:**
```php
$user = User::find(1);

$user->settings = [
  'theme' => 'light',
  'language' => 'en',
  'notifications' => false
];

$user->save();
```

**Update sebagian JSON:**
```php
User::where('id', 1)->update([
  'settings->theme' => 'light'
]);
```

### Membuat Index JSONB (Optional)

Jika data sangat banyak, disarankan membuat index JSONB:

```php
DB::statement(
  'CREATE INDEX idx_users_settings ON users USING GIN (settings)'
);
```

Index GIN ini memungkinkan query JSONB tetap cepat meskipun jumlah data sangat besar.

### Kesimpulan

JSONB di PostgreSQL sangat powerful untuk menyimpan data semi-structured seperti:
- user settings
- metadata
- configuration
- dynamic fields

---

## 3. PostgreSQL sebagai Queue Driver

PostgreSQL dapat digunakan sebagai driver queue di Laravel.

Laravel memiliki driver queue bernama `database`. Driver ini menyimpan job queue ke dalam tabel database dan dapat menggunakan database apa pun yang didukung oleh Laravel, seperti:
- MySQL
- PostgreSQL
- SQLite
- SQL Server

### Driver Queue yang Tersedia

| Driver       | Keterangan                        |
|--------------|----------------------------------|
| sync         | Job dijalankan langsung (tidak async) |
| database     | Job disimpan di tabel database   |
| redis        | Job disimpan di Redis            |
| sqs          | Menggunakan AWS SQS              |
| beanstalkd   | Menggunakan server queue Beanstalkd |

Driver yang paling sering digunakan di production:
- database
- redis

### Setup PostgreSQL sebagai Queue Driver

Atur konfigurasi di file `.env`:
```
QUEUE_CONNECTION=database
```

Jika database utama aplikasi menggunakan PostgreSQL:
```
DB_CONNECTION=pgsql
```

Maka Laravel akan menyimpan queue job ke tabel `jobs` yang berada di database PostgreSQL.

---

## 4. Create, Alter Table Melalui Migration

Contoh simulasi perubahan struktur table menggunakan Laravel Migration.

### HARI 1 — Membuat Table invoices

Buat migration:
```bash
php artisan make:migration create_invoices_table
```

Isi migration:
```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
```

Jalankan migration:
```bash
php artisan migrate --path=database/migrations/xxxx_xx_xx_xxxxxx_create_invoices_table.php
```

**Struktur table:**
```
invoices
---------
id
user_id
created_at
updated_at
```

### HARI 2 — Menambahkan Column company_id

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->integer('company_id')->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('company_id');
        });
    }
};
```

**Struktur table:**
```
invoices
---------
id
user_id
company_id
created_at
updated_at
```

### HARI 3 — Mengubah Tipe Data Column

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->change();
            $table->unsignedBigInteger('company_id')->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->integer('user_id')->change();
            $table->integer('company_id')->change();
        });
    }
};
```

**Struktur table:**
```
invoices
---------
id
user_id BIGINT UNSIGNED
company_id BIGINT UNSIGNED
created_at
updated_at
```

### HARI 4 — Menghapus Column company_id

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('company_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable();
        });
    }
};
```

**Struktur table:**
```
invoices
---------
id
user_id BIGINT UNSIGNED
created_at
updated_at
```

### Hasil Akhir Folder Migration

```
database/migrations

2026_03_10_000001_create_invoices_table.php
2026_03_11_000002_add_company_id_to_invoices_table.php
2026_03_12_000003_change_user_company_type_in_invoices.php
2026_03_13_000004_drop_company_id_from_invoices.php
```

File migration ini menjadi HISTORY perubahan struktur database.

### Setup Database untuk Developer Baru

Jika developer baru clone project:
```bash
git clone project
```

Cukup jalankan:
```bash
php artisan migrate
```

Laravel akan menjalankan semua migration secara berurutan sesuai timestamp.

### Keuntungan Menggunakan Migration

1. Struktur database terdokumentasi dengan jelas
2. History perubahan database tersimpan
3. Developer baru mudah melakukan setup database
4. Struktur database bisa dipahami oleh tools AI (Copilot / Cursor)
5. Perubahan database lebih terkontrol dalam version control

---

## 5. Handling Migration Files Banyak

Di project Laravel yang berjalan lama (3–5 tahun), jumlah migration memang bisa menjadi banyak:
- 300 file
- 500 file
- bahkan >1000 file

Ini sebenarnya **normal** dan tidak memperlambat aplikasi, karena migration hanya dijalankan saat menjalankan command:

```bash
php artisan migrate
```

Setelah migration selesai dijalankan, aplikasi Laravel tidak menggunakan file migration tersebut lagi saat runtime.

### Mengapa Migration Banyak Tidak Menjadi Masalah

Migration hanya digunakan saat:
- Setup database
- Perubahan schema database

**Bukan** saat aplikasi berjalan.

Jadi walaupun ada 1000 migration files, performa aplikasi tetap normal.

### Solusi: Schema Dump

Laravel menyediakan fitur:
```bash
php artisan schema:dump
```

Command ini membuat snapshot struktur database saat ini. File yang dihasilkan biasanya:
```
database/schema/pgsql-schema.sql   (untuk PostgreSQL)
```

Isi file ini adalah SQL schema lengkap database:
```sql
CREATE TABLE users (...)
CREATE TABLE invoices (...)
CREATE TABLE orders (...)
```

### Setelah Melakukan Schema Dump

Migration lama bisa dihapus dan digantikan oleh schema snapshot.

**Sebelum dump:**
```
001_create_users
002_create_orders
003_add_status_orders
...
350_add_index_orders
```

**Sesudah dump:**
```
database/schema/pgsql-schema.sql
351_add_payment_to_orders
352_add_discount_to_orders
```

### Developer Baru Setup Database

Jika developer baru clone project lalu menjalankan:
```bash
php artisan migrate
```

Laravel akan:
1. Load schema dump terlebih dahulu
2. Menjalankan migration setelah schema dump

Jadi developer tidak perlu menjalankan ratusan migration lama.

### Struktur Project

```
database
 ├ migrations
 │   ├ 2026_01_add_feature_a
 │   ├ 2026_02_add_feature_b
 │   └ 2026_03_add_feature_c
 │
 └ schema
     └ pgsql-schema.sql
```

### Workflow Umum

**Saat membuat fitur baru:**
```bash
php artisan make:migration add_status_to_invoices
```

Edit migration → lalu jalankan:
```bash
php artisan migrate
```

**Jika migration sudah terlalu banyak** (misalnya >200 file):
```bash
php artisan schema:dump
```

### Kesimpulan

Strategi umum yang dipakai tim Laravel:

1. Setiap perubahan database → buat migration
2. Jika migration sudah terlalu banyak → lakukan schema dump

Dengan cara ini migration tetap terkontrol walaupun project sudah berjalan bertahun-tahun.
