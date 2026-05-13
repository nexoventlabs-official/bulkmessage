# Bulk WhatsApp Campaign — Project Audit & Reference

## Overview

Laravel 10 application for sending bulk WhatsApp messages to Tamil Nadu voters via Meta's WhatsApp Business API. The admin panel allows managing WhatsApp Business accounts, creating message templates, selecting target assemblies, and dispatching high-throughput campaigns with real-time tracking.

**Stack:** PHP 8.1+ · Laravel 10 · MongoDB Atlas (primary DB) · MySQL (read-only voter data) · Redis (queues + live stats) · Cloudinary (media) · Meta WhatsApp Business API v19.0 · TailwindCSS CDN · Alpine.js · Font Awesome 6

---

## Architecture

```
┌──────────────┐     ┌─────────────┐     ┌─────────────────────┐
│  Blade UI    │────▶│  Laravel     │────▶│  MongoDB Atlas      │
│  + Alpine.js │     │  Controllers │     │  (accounts,         │
│  + Tailwind  │     │  + Services  │     │   templates,        │
└──────────────┘     └──────┬──────┘     │   campaigns, logs,  │
                            │             │   assemblies, admins)│
                            │             └─────────────────────┘
                            │
                     ┌──────▼──────┐     ┌─────────────────────┐
                     │  Redis      │     │  MySQL (Cloudways)   │
                     │  - Queues   │     │  - 234 assembly      │
                     │  - Live     │     │    voter tables      │
                     │    stats    │     │  - Read-only         │
                     └─────────────┘     └─────────────────────┘
                            │
                     ┌──────▼──────┐     ┌─────────────────────┐
                     │  Queue      │────▶│  Meta WhatsApp API   │
                     │  Worker     │     │  - Template submit   │
                     │  (Redis)    │     │  - Message send      │
                     └─────────────┘     │  - Webhook callbacks │
                                         └─────────────────────┘
                                                  │
                                         ┌────────▼────────────┐
                                         │  Cloudinary          │
                                         │  - Template media    │
                                         │    (images/videos)   │
                                         └─────────────────────┘
```

---

## Database Design

### MongoDB Collections

| Collection          | Purpose                                     |
|---------------------|---------------------------------------------|
| `admins`            | Super admin auth (email/password)            |
| `whatsapp_accounts` | Meta WhatsApp Business API credentials       |
| `templates`         | Message templates (draft→pending→approved)   |
| `campaigns`         | Campaign config, stats, status               |
| `message_logs`      | Per-message delivery tracking                |
| `assemblies`        | 234 Tamil Nadu assembly constituencies       |

### MySQL (Read-only)

- **Host:** 168.144.86.114 (Cloudways)
- **Database:** `embdbkxrkg`
- 234 assembly tables containing voter data (name, EPIC/voter ID, gender, age, mobile number, address, booth info)
- Table names mapped via `assemblies:sync-tables` command
- Mobile column auto-detected per table via pattern matching

---

## Models

| Model              | Collection           | Key Fields                                                                 |
|--------------------|----------------------|---------------------------------------------------------------------------|
| `Admin`            | `admins`             | name, email, password, role, is_active                                     |
| `WhatsAppAccount`  | `whatsapp_accounts`  | name, mobile_number, meta_app_id, meta_app_secret (encrypted), meta_access_token (encrypted), meta_phone_number_id, meta_waba_id, meta_verify_token, connection_status |
| `Template`         | `templates`          | account_id, name, meta_template_name, language, category, header_type, header_media_url, body_text, footer_text, buttons[], status (draft/pending/approved/rejected), meta_template_id |
| `Campaign`         | `campaigns`          | account_id, template_id, name, assemblies[], total_voters, total_with_mobile, total_sent/delivered/read/failed/skipped, status, is_test, test_numbers[], rate_per_second, batch_size |
| `MessageLog`       | `message_logs`       | campaign_id, account_id, assembly_no, voter_id, mobile_number, whatsapp_message_id, status, error_message, sent_at/delivered_at/read_at |
| `Assembly`         | `assemblies`         | ac_no, constituency, district, zone, table_name, total_voters, has_mobile_data |

---

## Services

### `MetaWhatsAppService`
- **`checkConnection()`** — Verify API credentials via phone number endpoint
- **`submitTemplate()`** — Submit template to Meta for approval with resumable upload for image headers (`createHeaderHandle()`)
- **`checkTemplateStatus()`** — Poll Meta for template approval status
- **`sendTemplateMessage()`** — Routes to `sendAsMediaMessage()` for templates with media headers, or sends as normal template for text-only
- **`sendAsMediaMessage()`** — **Workaround:** Sends image/video with body text as caption instead of template API (Meta silently drops template messages with image headers on this account)
- **`normalizePhoneNumber()`** — Converts Indian mobile numbers to E.164 (91XXXXXXXXXX)

### `CampaignService`
- **`createCampaign()`** — Creates campaign with voter count calculation (test mode skips MySQL query)
- **`startCampaign()`** — Dispatches `ProcessCampaignBatch` jobs to Redis queue; handles test mode with direct test numbers
- **`pauseCampaign()` / `resumeCampaign()`** — Redis-based pause flag, re-dispatch from last checkpoint
- **`getLiveStats()`** — Read real-time counters from Redis
- **`syncStatsToDb()`** — Flush Redis counters back to MongoDB; auto-complete campaign when all processed

### `VoterService`
- **`getAllAssemblies()`** / **`getAssembliesGrouped()`** — Cached assembly lists from MongoDB
- **`getVoterMobilesFromAssembly()`** — Paginated fetch from MySQL with auto-detected mobile column
- **`countVotersWithMobile()`** — Cached voter count per assembly
- **`detectMobileColumn()`** — Pattern-matching to find mobile column across varied table schemas

---

## Jobs

### `ProcessCampaignBatch`
- Queue: `campaign_messages` (Redis)
- Accepts: campaignId, assemblyNo, offset, limit, isTestMode, testNumbers
- Rate limiting: configurable per-second throttle (default 80 msg/sec)
- Pause-aware: checks Redis flag mid-batch, saves checkpoint
- Bulk-inserts message logs every 100 records
- Auto-retries on Meta rate-limit errors (codes 130429, 131048, 131026)

---

## Controllers

| Controller                  | Routes                          | Purpose                                    |
|-----------------------------|---------------------------------|--------------------------------------------|
| `AuthController`            | `/login`, `/logout`             | Admin login/logout via `admin` guard        |
| `DashboardController`       | `/dashboard`                    | Stats overview + recent campaigns           |
| `WhatsAppAccountController` | `/accounts` (CRUD)              | Manage Meta API credentials                 |
| `TemplateController`        | `/accounts/{id}/templates/*`    | Create, preview, submit, check status, delete templates |
| `CampaignController`        | `/campaigns/*`                  | Create, show, start, pause, resume, live stats |
| `VoterController`           | `/voters`                       | Browse voter data, assembly stats           |
| `WebhookController`         | `/api/webhook` (GET/POST)       | Meta webhook verification + delivery status updates |

---

## Artisan Commands

| Command                       | Description                                          |
|-------------------------------|------------------------------------------------------|
| `admin:create`                | Create/update super admin account                    |
| `assemblies:sync-tables`      | Map MySQL table names to MongoDB assembly records    |
| `assemblies:sync-counts`      | Sync voter counts from MySQL into MongoDB assemblies |
| `campaign:audit {id?}`        | Show campaign performance metrics and error analysis |

---

## Scheduled Tasks (Console Kernel)

- **Every minute:** Sync running campaign stats from Redis → MongoDB
- **Every 5 minutes:** Check pending template statuses from Meta API

---

## Routes

### Web (protected by `admin.auth` middleware)

```
GET  /dashboard                                  → DashboardController@index
GET  /accounts                                   → WhatsAppAccountController@index
POST /accounts                                   → WhatsAppAccountController@store
GET  /accounts/{id}                              → WhatsAppAccountController@show
GET  /accounts/{id}/edit                         → WhatsAppAccountController@edit
PUT  /accounts/{id}                              → WhatsAppAccountController@update
DELETE /accounts/{id}                            → WhatsAppAccountController@destroy
POST /accounts/{id}/check-connection             → WhatsAppAccountController@checkConnection

GET  /accounts/{aId}/templates/create            → TemplateController@create
POST /accounts/{aId}/templates                   → TemplateController@store
GET  /accounts/{aId}/templates/{tId}/preview     → TemplateController@preview
POST /accounts/{aId}/templates/{tId}/submit      → TemplateController@submitForApproval
GET  /accounts/{aId}/templates/{tId}/status       → TemplateController@checkStatus (AJAX)
DELETE /accounts/{aId}/templates/{tId}            → TemplateController@destroy

GET  /voters                                     → VoterController@index
GET  /voters/assembly-stats                      → VoterController@assemblyStats (AJAX)

GET  /campaigns                                  → CampaignController@index
GET  /accounts/{aId}/templates/{tId}/campaign    → CampaignController@create
POST /campaigns                                  → CampaignController@store
GET  /campaigns/{id}                             → CampaignController@show
POST /campaigns/{id}/start                       → CampaignController@start
POST /campaigns/{id}/pause                       → CampaignController@pause
POST /campaigns/{id}/resume                      → CampaignController@resume
GET  /campaigns/{id}/live-stats                  → CampaignController@liveStats (AJAX)
POST /campaigns/voter-count                      → CampaignController@getVoterCount (AJAX)
```

### API (no auth — webhook)

```
GET  /api/webhook   → WebhookController@verify   (Meta verification challenge)
POST /api/webhook   → WebhookController@receive   (Delivery status + template status updates)
```

---

## Blade Views

```
layouts/app.blade.php           — Master layout (sidebar + header + flash messages)
auth/login.blade.php            — Login page
dashboard.blade.php             — Stats cards + recent campaigns
accounts/index.blade.php        — List WhatsApp accounts
accounts/create.blade.php       — Add new account form
accounts/edit.blade.php         — Edit account form
accounts/show.blade.php         — Account detail + templates list + webhook URL
templates/create.blade.php      — Create template (header media upload, body, footer, buttons)
templates/preview.blade.php     — Template preview + status polling + submit/campaign actions
campaigns/index.blade.php       — List all campaigns
campaigns/create.blade.php      — Select assemblies + test mode toggle + create campaign
campaigns/show.blade.php        — Live campaign dashboard (auto-polling stats)
voters/index.blade.php          — Browse voter tables with search/filter
```

---

## Configuration

### Environment Variables (.env)

| Variable                        | Purpose                           |
|---------------------------------|-----------------------------------|
| `APP_URL`                       | Public URL (ngrok for webhooks)   |
| `MONGO_DB_DSN`                  | MongoDB Atlas connection string   |
| `MONGO_DB_DATABASE`             | MongoDB database name             |
| `MYSQL_DB_HOST/PORT/DATABASE/USERNAME/PASSWORD` | MySQL voter DB |
| `REDIS_HOST/PORT`               | Redis for queues + cache          |
| `QUEUE_CONNECTION`              | `redis`                           |
| `CLOUDINARY_URL`                | Cloudinary credentials            |
| `CAMPAIGN_BATCH_SIZE`           | Voters per batch (default 500)    |
| `CAMPAIGN_RATE_LIMIT_PER_SECOND`| Messages/sec (default 80)         |
| `SUPER_ADMIN_EMAIL/PASSWORD`    | Default admin credentials         |

### Database Connections (config/database.php)

- `mongodb` — Default connection (MongoDB Atlas)
- `mysql_voters` — Read-only voter data (Cloudways MySQL)
- `redis` — Queue + cache (local)

### Auth (config/auth.php)

- Default guard: `admin` (session-based)
- Provider: `admins` → `App\Models\Admin` (MongoDB)
- Middleware alias: `admin.auth` → `AdminAuth`

---

## Key Workflows

### 1. Setup
```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan admin:create --email=admin@bulkcampaign.com --password=admin123456
php artisan assemblies:sync-tables    # Map MySQL tables → assemblies
php artisan assemblies:sync-counts    # Sync voter counts
```

### 2. Running the App
```bash
php artisan serve                                                            # Web server
php artisan queue:work redis --queue=campaign_messages --tries=3 --timeout=600  # Queue worker (REQUIRED for campaigns)
redis-server                                                                 # Redis (must be running)
```

### 3. Campaign Flow
1. **Add WhatsApp Account** — Enter Meta API credentials (App ID, App Secret, Access Token, Phone Number ID, WABA ID)
2. **Create Template** — Design message with optional image header, body text, footer, buttons → auto-submitted to Meta
3. **Wait for Approval** — Template status polled via AJAX every 3s on preview page; also checked via scheduled task every 5min
4. **Create Campaign** — Select assemblies (or enable test mode with specific numbers) → review voter counts
5. **Start Campaign** — Dispatches batched jobs to Redis queue → queue worker sends messages
6. **Monitor** — Live stats dashboard with auto-refresh (sent/delivered/failed/skipped/speed)

### 4. Test Mode
- Checkbox in campaign creation to enable test mode
- Comma-separated phone numbers (default: `918903162114`)
- Bypasses assembly selection and MySQL voter queries
- Sends only to specified test numbers

---

## Known Issues & Workarounds

### Meta Template Messages with Image Headers Don't Deliver
- **Problem:** Meta API returns 200 "accepted" for template messages with IMAGE headers but never delivers them to recipients. Text-only templates (e.g., `hello_world`) and regular image messages deliver fine.
- **Root Cause:** Meta silently drops template messages with image headers on this WhatsApp Business Account. The `parameter_format: POSITIONAL` on newer templates may be a factor.
- **Workaround:** `sendAsMediaMessage()` in `MetaWhatsAppService` sends templates with image/video/document headers as **regular media messages with body text as caption** instead of using the template API. This delivers successfully.
- **Impact:** Recipients get image + text as a single message, but it's not a "template message" in Meta's tracking. This means no 24-hour window bypass for marketing — the recipient must have an active conversation or the business must have an approved template for non-template messages to go through.

### Queue Worker Must Be Running
- Campaigns sit at "Running" with 0 sent if the queue worker isn't started
- Must run `php artisan queue:work redis --queue=campaign_messages --tries=3 --timeout=600` separately

### Voter Counts Show 0 in UI
- Assembly records in MongoDB may not have `total_voters` populated
- Run `php artisan assemblies:sync-counts` to sync from MySQL

### APP_URL Must Match Ngrok
- Webhook URL displayed on account page uses `config('app.url')`
- Must update `APP_URL` in `.env` when ngrok URL changes

---

## File Structure

```
bulk-campaign/
├── app/
│   ├── Console/
│   │   ├── Commands/
│   │   │   ├── AuditCampaign.php           # campaign:audit
│   │   │   ├── CreateSuperAdmin.php        # admin:create
│   │   │   ├── SyncAssemblyTables.php      # assemblies:sync-tables
│   │   │   └── SyncAssemblyVoterCounts.php # assemblies:sync-counts
│   │   └── Kernel.php                      # Scheduled tasks
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AuthController.php
│   │   │   ├── CampaignController.php
│   │   │   ├── DashboardController.php
│   │   │   ├── TemplateController.php
│   │   │   ├── VoterController.php
│   │   │   ├── WebhookController.php
│   │   │   └── WhatsAppAccountController.php
│   │   ├── Kernel.php                      # Middleware registration
│   │   └── Middleware/
│   │       └── AdminAuth.php               # admin.auth middleware
│   ├── Jobs/
│   │   └── ProcessCampaignBatch.php        # Queue job for sending
│   ├── Models/
│   │   ├── Admin.php
│   │   ├── Assembly.php
│   │   ├── Campaign.php
│   │   ├── MessageLog.php
│   │   ├── Template.php
│   │   └── WhatsAppAccount.php
│   ├── Providers/
│   │   ├── AppServiceProvider.php
│   │   ├── AuthServiceProvider.php
│   │   ├── EventServiceProvider.php
│   │   └── RouteServiceProvider.php
│   └── Services/
│       ├── CampaignService.php             # Campaign orchestration
│       ├── MetaWhatsAppService.php         # Meta API integration
│       └── VoterService.php                # MySQL voter data access
├── config/
│   ├── app.php                             # Providers, timezone (Asia/Kolkata)
│   ├── auth.php                            # admin guard + admins provider
│   └── database.php                        # mongodb, mysql_voters, redis
├── resources/views/
│   ├── layouts/app.blade.php               # Master layout
│   ├── auth/login.blade.php
│   ├── dashboard.blade.php
│   ├── accounts/{index,create,edit,show}.blade.php
│   ├── templates/{create,preview}.blade.php
│   ├── campaigns/{index,create,show}.blade.php
│   └── voters/index.blade.php
├── routes/
│   ├── web.php                             # All web routes
│   └── api.php                             # Webhook routes
├── .env / .env.example
├── composer.json
└── README.md
```

---

## Dependencies (composer.json)

| Package                          | Version  | Purpose                       |
|----------------------------------|----------|-------------------------------|
| `laravel/framework`              | ^10.0    | Core framework                |
| `mongodb/laravel-mongodb`        | ^4.0     | MongoDB Eloquent driver       |
| `predis/predis`                  | ^2.2     | Redis client                  |
| `cloudinary-labs/cloudinary-laravel` | ^2.0 | Cloudinary media uploads      |
| `guzzlehttp/guzzle`             | ^7.0     | HTTP client (Meta API calls)  |
| `maatwebsite/excel`             | ^3.1     | Excel import (assembly seeding)|
| `laravel/sanctum`               | ^3.3     | API token auth (available)    |

---

## Security Notes

- Meta Access Token and App Secret are **encrypted** at rest in MongoDB (`encrypt()`/`decrypt()`)
- Admin passwords hashed with bcrypt via `Hash::make()`
- Webhook verify token auto-generated per account (`bulk_` + random 32 chars)
- CSRF protection on all web routes
- `admin.auth` middleware protects all dashboard routes
- API routes (webhook) have no auth but verify token on GET
- Sensitive fields hidden from model serialization (`$hidden`)

---

## Current Account Details

- **WhatsApp Business Number:** +91 97916 59816
- **Phone Number ID:** 1023027240889685
- **WABA ID:** 801737445597563
- **Default Test Number:** 918903162114
- **API Version:** v19.0 (base URL in MetaWhatsAppService)
