# Standardizing Invoice Descriptions in Stripe

## Ringkasan Conversation

Task ini fokus pada standardisasi `description` dan `metadata` Stripe untuk campaign billing.

Masalah awal:
- Description Stripe saat ini memakai format legacy seperti `#20260312-1306-Dimas #89729342`.
- Format itu sulit dibaca finance/admin karena tidak jelas angka apa yang merepresentasikan campaign ID, campaign name, campaign type, atau event billing.
- Stripe `description` tetap berguna untuk tampilan manusia di dashboard.
- Stripe `metadata` lebih cocok untuk data terstruktur yang bisa dipakai untuk search, filter, report, debugging, webhook, dan audit.

Keputusan utama:
- Gunakan `description` dan `metadata` sekaligus.
- `description` dibuat human-readable dengan format `key: value | key: value`.
- Legacy invoice string lama tetap dipertahankan, tetapi dipindah ke bagian paling belakang.
- `metadata` hanya menyimpan empat key utama untuk campaign billing.
- Scope awal hanya campaign billing, bukan semua transaksi Stripe.

## Format Final

### Description

```text
Event: {event label} | Campaign ID: {campaign id} | Campaign Name: {campaign name} | Campaign Type: {campaign type display name} | {legacy invoice prefix}
```

Contoh:

```text
Event: Campaign Weekly Billing | Campaign ID: 94330480 | Campaign Name: asda | Campaign Type: Site ACH | #20260312-1306-Dimas #94330480
```

### Metadata

```php
'metadata' => [
    'event' => 'campaign_weekly_billing',
    'campaign_id' => '94330480',
    'campaign_name' => 'asda',
    'campaign_type' => 'Site ACH',
]
```

Semua value metadata harus dikirim sebagai string.

## Event List

| Description Event | Metadata Event |
| --- | --- |
| Campaign Weekly Billing | campaign_weekly_billing |
| Campaign Monthly Billing | campaign_monthly_billing |
| Campaign Prepaid Top-Up Continual | campaign_prepaid_top_up_continual |
| Campaign Prepaid Top-Up One Time | campaign_prepaid_top_up_one_time |
| Campaign Prepaid Renewal Continual | campaign_prepaid_renewal_continual |
| Campaign Prepaid Monthly Fee | campaign_prepaid_monthly_fee |
| Campaign Simplifi Budget Charge | campaign_simplifi_budget_charge |

## Campaign Type

`Campaign Type` tidak boleh hardcode seperti `Site ACH`, `Enhance ACH`, `B2B ACH`, atau `Simplifi ACH`.

Nilainya harus diambil dari konfigurasi General Setting bagian `Select Your Product Names`.

Precedence:
1. Pakai product/module name override milik agency jika ada.
2. Jika agency belum override, pakai product/module name dari root.
3. Jika root tidak ada, fallback ke default sistem.

Setting yang relevan:
- Root product name: `rootcustomsidebarleadmenu`.
- Agency product name override: `customsidebarleadmenu`.
- Raw campaign type tetap berasal dari `leadspeek_type`, misalnya `local`, `locator`, `enhance`, `b2b`, atau `simplifi`.

## Scope Implementasi

In scope:
- Campaign billing payment yang membuat Stripe PaymentIntent.
- Normal client charge.
- Charge client dengan `application_fee_amount`.
- Flow agency data wallet yang tetap membuat client charge melalui `charge_client_with_app_fee_amount`.
- Cron/queue campaign billing di `EMM-SANDBOX-API`.

Out of scope:
- Card setup.
- Manual top-up agency data wallet yang bukan campaign billing.
- Refund.
- Customer setup.
- Non-campaign invoice.
- Perubahan UI.
- Perubahan business logic fee, wallet, transfer, atau commission.

## Entry Point yang Perlu Diperhatikan

Di `EMM-SANDBOX-APP`:
- `data/app/Http/Controllers/LeadspeekController.php`
  - `chargeClient`
  - PaymentIntent direct dengan `application_fee_amount`
  - Data wallet path yang menyiapkan `$dataChargeClient`
- `data/app/Http/Controllers/Controller.php`
  - `check_agency_stripeinfo`
  - `process_charge_agency_wallet`
  - `charge_client_with_app_fee_amount`

Di `EMM-SANDBOX-API`:
- `app/Http/Controllers/WebhookController.php`
  - `chargeClient`
  - PaymentIntent direct dengan `application_fee_amount`
  - Data wallet path yang menyiapkan `$dataChargeClient`
- `app/Http/Controllers/MarketingController.php`
  - `processinvoice`
  - `processinvoicemonthly`
  - `createInvoice`
- `app/Http/Controllers/Controller.php`
  - `check_agency_stripeinfo`
  - `process_charge_agency_wallet`
  - `charge_client_with_app_fee_amount`
- Jobs/cron yang menjalankan campaign billing dan membuat PaymentIntent.

Catatan:
- `MarketingController.php` memiliki legacy `private function chargeClient`, tetapi function ini sudah tidak dipakai untuk flow aktif campaign billing.
- Flow aktif cron/queue campaign billing di API masuk melalui `WebhookController::chargeClient`.
- Flow aktif weekly/monthly invoice cron di API masuk melalui `MarketingController::createInvoice`.

## Plan Implementasi

1. Buat helper shared untuk membangun Stripe campaign billing identity:
   - `buildCampaignStripeDescription($eventLabel, $campaignId, $campaignName, $campaignTypeName, $legacyInvoice)`
   - `buildCampaignStripeMetadata($eventKey, $campaignId, $campaignName, $campaignTypeName)`
   - `getCampaignTypeDisplayName($companyParentId, $companyRootId, $leadspeekType)`

2. Buat helper untuk menentukan event billing:
   - `Weekly` -> `Campaign Weekly Billing` / `campaign_weekly_billing`
   - `Monthly` -> `Campaign Monthly Billing` / `campaign_monthly_billing`
   - `Prepaid + topupoptions onetime` -> `Campaign Prepaid Top-Up One Time` / `campaign_prepaid_top_up_one_time`
   - `Prepaid + topupoptions continual + user/manual activation top-up` -> `Campaign Prepaid Top-Up Continual` / `campaign_prepaid_top_up_continual`
   - `Prepaid + topupoptions continual + cron/auto-renewal low balance` -> `Campaign Prepaid Renewal Continual` / `campaign_prepaid_renewal_continual`
   - `Prepaid + campaign fee per month cron` -> `Campaign Prepaid Monthly Fee` / `campaign_prepaid_monthly_fee`
   - `simplifi` -> `Campaign Simplifi Budget Charge` / `campaign_simplifi_budget_charge`

3. Update semua PaymentIntent campaign billing agar memakai:
   - description baru,
   - metadata baru,
   - tanpa mengubah `application_fee_amount`,
   - tanpa mengubah amount, transfer, commission, wallet, invoice, atau failure handling.

4. Hapus temporary test metadata:
   - `name`
   - `age`
   - `gender`

5. Pastikan data wallet path membawa description dan metadata yang sama sampai ke `charge_client_with_app_fee_amount`.

## Status Implementasi

Sudah diterapkan di `EMM-SANDBOX-APP`:
- `data/app/Http/Controllers/Controller.php`
- `data/app/Http/Controllers/LeadspeekController.php`

Sudah diterapkan di `EMM-SANDBOX-API`:
- `app/Http/Controllers/Controller.php`
- `app/Http/Controllers/WebhookController.php`
- `app/Http/Controllers/MarketingController.php`
  - `createInvoice`
  - `chargePrepaidCostMonth`

Tidak diubah:
- Legacy `app/Http/Controllers/MarketingController.php::chargeClient`, karena function itu tidak lagi digunakan untuk flow aktif.

## Pending Scope

Standardisasi campaign billing sudah selesai untuk:
- Weekly.
- Monthly.
- Prepaid continual limit day.
- Prepaid continual limit monthly.
- Prepaid one time.
- Simplifi budget.

Masih pending untuk scope non-campaign:
- Agency data wallet.
- Agency Open API.
- Agency onboarding charge.
- Agency minimum spend:
  - `EMM-SANDBOX-API/app/Http/Controllers/MarketingController.php`
  - `minimumspendinvoice`
  - `MinimumSpendNotificationEmail`

## Test Plan

Test di Stripe sandbox:
- Weekly campaign billing.
- Monthly campaign billing.
- Prepaid top-up continual.
- Prepaid top-up one time.
- Prepaid renewal continual.
- Prepaid monthly fee.
- Simplifi budget charge.
- Data wallet agency aktif.
- Agency product name sudah override.
- Agency product name belum override, fallback ke root.

Acceptance criteria:
- Stripe Payment detail menampilkan description sesuai format final.
- Stripe Payment detail menampilkan metadata final dengan empat key.
- Campaign type di Stripe memakai display name dari General Setting.
- Legacy invoice string masih ada di bagian belakang description.
- PaymentIntent tetap sukses seperti sebelumnya.
- `application_fee_amount` dan flow Connect tidak berubah.
