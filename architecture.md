# Bulk WhatsApp Campaign — System Architecture

## High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              ADMIN BROWSER                                  │
│                                                                             │
│   ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐   │
│   │Dashboard │  │Accounts  │  │Templates │  │Campaigns │  │ Voters   │   │
│   └────┬─────┘  └────┬─────┘  └────┬─────┘  └────┬─────┘  └────┬─────┘   │
│        │              │              │              │              │         │
│        └──────────────┴──────────────┴──────┬───────┴──────────────┘         │
│                                             │                               │
│                    TailwindCSS CDN · Alpine.js · Font Awesome 6             │
└─────────────────────────────────────────────┬───────────────────────────────┘
                                              │ HTTP (GET/POST)
                                              │ AJAX (live-stats, voter-count, template-status)
                                              ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                         LARAVEL 10 APPLICATION                              │
│                                                                             │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │                        HTTP LAYER                                   │    │
│  │                                                                     │    │
│  │  ┌─────────────┐  ┌──────────────────────────────────────────┐     │    │
│  │  │ Middleware   │  │           Controllers                    │     │    │
│  │  │             │  │                                          │     │    │
│  │  │ AdminAuth   │  │  AuthController        (login/logout)   │     │    │
│  │  │ CSRF        │  │  DashboardController   (stats overview) │     │    │
│  │  │ Session     │  │  WhatsAppAccountController (CRUD)       │     │    │
│  │  │ Throttle    │  │  TemplateController    (create/submit)  │     │    │
│  │  └─────────────┘  │  CampaignController   (create/start)   │     │    │
│  │                    │  VoterController       (browse data)   │     │    │
│  │                    │  WebhookController     (Meta callbacks)│     │    │
│  │                    └──────────────────────────────────────────┘     │    │
│  └────────────────────────────────────┬────────────────────────────────┘    │
│                                       │                                     │
│  ┌────────────────────────────────────▼────────────────────────────────┐    │
│  │                       SERVICE LAYER                                 │    │
│  │                                                                     │    │
│  │  ┌─────────────────────┐  ┌──────────────────┐  ┌──────────────┐  │    │
│  │  │ MetaWhatsAppService │  │ CampaignService  │  │ VoterService │  │    │
│  │  │                     │  │                  │  │              │  │    │
│  │  │ • checkConnection() │  │ • createCampaign │  │ • getAll     │  │    │
│  │  │ • submitTemplate()  │  │ • startCampaign  │  │   Assemblies │  │    │
│  │  │ • checkTemplateStatus│ │ • pauseCampaign  │  │ • getVoter   │  │    │
│  │  │ • sendTemplateMsg() │  │ • resumeCampaign │  │   Mobiles    │  │    │
│  │  │ • sendAsMediaMsg()  │  │ • getLiveStats   │  │ • countVoters│  │    │
│  │  │ • createHeaderHandle│  │ • syncStatsToDb  │  │ • detectCol  │  │    │
│  │  │ • normalizePhone()  │  │                  │  │              │  │    │
│  │  └──────────┬──────────┘  └────────┬─────────┘  └──────┬───────┘  │    │
│  └─────────────┼──────────────────────┼────────────────────┼──────────┘    │
│                │                      │                    │                │
│  ┌─────────────▼──────────────────────▼────────────────────▼──────────┐    │
│  │                        MODEL LAYER (Eloquent)                      │    │
│  │                                                                     │    │
│  │  Admin · WhatsAppAccount · Template · Campaign · MessageLog · Assembly  │
│  └─────────────────────────────────────────────────────────────────────┘    │
│                                                                             │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │                     BACKGROUND JOBS                                 │    │
│  │                                                                     │    │
│  │  ProcessCampaignBatch (Redis queue: campaign_messages)              │    │
│  │  • Fetches voter batch from MySQL (or test numbers)                 │    │
│  │  • Rate-limited message sending (configurable msg/sec)              │    │
│  │  • Pause-aware with Redis flag checks                               │    │
│  │  • Bulk-inserts message logs every 100 records                      │    │
│  │  • Auto-retries on Meta rate-limit errors                           │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
│                                                                             │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │                   SCHEDULED TASKS (Console Kernel)                  │    │
│  │                                                                     │    │
│  │  Every 1 min  → Sync running campaign stats (Redis → MongoDB)       │    │
│  │  Every 5 min  → Check pending template statuses (Meta API poll)     │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────────────┘
          │                    │                    │                │
          ▼                    ▼                    ▼                ▼
┌──────────────┐  ┌─────────────────┐  ┌──────────────┐  ┌──────────────────┐
│  MongoDB     │  │  MySQL           │  │  Redis       │  │  External APIs   │
│  Atlas       │  │  (Cloudways)     │  │  (Local)     │  │                  │
│              │  │                  │  │              │  │  ┌─────────────┐ │
│  Collections:│  │  234 assembly    │  │  • Queue     │  │  │ Meta Graph  │ │
│  • admins    │  │  voter tables    │  │    jobs      │  │  │ API v19.0   │ │
│  • whatsapp_ │  │                  │  │  • Campaign  │  │  │             │ │
│    accounts  │  │  ~50M+ voter     │  │    counters  │  │  │ • Templates │ │
│  • templates │  │  records         │  │    (sent,    │  │  │ • Messages  │ │
│  • campaigns │  │                  │  │    failed,   │  │  │ • Webhooks  │ │
│  • message_  │  │  Read-only       │  │    skipped,  │  │  │ • Media     │ │
│    logs      │  │  access          │  │    delivered) │  │  └─────────────┘ │
│  • assemblies│  │                  │  │  • Pause     │  │                  │
│              │  │                  │  │    flags     │  │  ┌─────────────┐ │
│              │  │                  │  │  • Rate      │  │  │ Cloudinary  │ │
│              │  │                  │  │    tracking  │  │  │             │ │
│              │  │                  │  │  • TTL: 7d   │  │  │ • Template  │ │
│              │  │                  │  │              │  │  │   media     │ │
└──────────────┘  └─────────────────┘  └──────────────┘  │  └─────────────┘ │
                                                          └──────────────────┘

```

---

## Request Flow

### 1. Campaign Message Sending Flow

```
Admin clicks "Start Campaign"
         │
         ▼
CampaignController@start
         │
         ▼
CampaignService::startCampaign()
         │
         ├── Update campaign status → RUNNING
         ├── Initialize Redis counters (sent/failed/skipped/delivered)
         │
         ├── [TEST MODE]
         │   └── Dispatch 1 job with test phone numbers
         │
         └── [NORMAL MODE]
             └── For each assembly:
                 └── For each batch (500 voters):
                     └── Dispatch ProcessCampaignBatch → Redis queue
                                    │
                                    ▼
                          ┌─────────────────────┐
                          │   Queue Worker       │
                          │   (separate process) │
                          └──────────┬──────────┘
                                     │
                                     ▼
                          ProcessCampaignBatch::handle()
                                     │
                          ┌──────────┴──────────┐
                          │                     │
                     [TEST MODE]          [NORMAL MODE]
                          │                     │
                     Use test numbers     Query MySQL for
                     directly              voter mobiles
                          │                     │
                          └──────────┬──────────┘
                                     │
                                     ▼
                          For each voter in batch:
                                     │
                          ┌──────────┴──────────┐
                          │ Check Redis pause    │
                          │ flag before each msg │
                          └──────────┬──────────┘
                                     │
                          ┌──────────┴──────────┐
                          │ Rate limit check     │
                          │ (N msg/sec)          │
                          └──────────┬──────────┘
                                     │
                                     ▼
                   MetaWhatsAppService::sendTemplateMessage()
                                     │
                          ┌──────────┴──────────────┐
                          │                         │
                   [HAS MEDIA HEADER]        [TEXT-ONLY TEMPLATE]
                          │                         │
                          ▼                         ▼
                   sendAsMediaMessage()      Send via Template API
                   (image + caption)         (normal template payload)
                          │                         │
                          └──────────┬──────────────┘
                                     │
                                     ▼
                          Meta Graph API v19.0
                          POST /{phone_number_id}/messages
                                     │
                                     ▼
                          ┌──────────┴──────────┐
                          │                     │
                       SUCCESS               FAILURE
                          │                     │
                   Redis::incr(sent)     Redis::incr(failed)
                          │                     │
                          └──────────┬──────────┘
                                     │
                                     ▼
                          Bulk insert MessageLog
                          (every 100 records)
                                     │
                                     ▼
                          Sync stats → MongoDB
                          (every 5000 messages or at completion)
```

### 2. Template Lifecycle

```
Admin creates template in UI
         │
         ▼
TemplateController@store
         │
         ├── Upload media → Cloudinary (if image/video header)
         ├── Save template → MongoDB (status: DRAFT)
         │
         ▼
MetaWhatsAppService::submitTemplate()
         │
         ├── Build component payload (HEADER, BODY, FOOTER, BUTTONS)
         │
         ├── [IF MEDIA HEADER]
         │   └── createHeaderHandle()
         │       ├── Download file from Cloudinary
         │       ├── Step 1: Create upload session (POST /{app_id}/uploads)
         │       └── Step 2: Upload file bytes → get header_handle (h:xxxxx)
         │
         ├── POST /{waba_id}/message_templates → Meta API
         │
         └── Update template status → PENDING
                    │
         ┌─────────┴─────────┐
         │                   │
    [WEBHOOK]          [SCHEDULED TASK]
    Meta sends POST    Poll every 5 min
    to /api/webhook    GET /{template_id}
         │                   │
         └─────────┬─────────┘
                   │
         ┌─────────┴─────────┐
         │                   │
      APPROVED           REJECTED
         │                   │
    Update MongoDB      Update MongoDB
    approved_at = now   rejection_reason
```

### 3. Webhook Processing Flow

```
Meta sends POST /api/webhook
         │
         ▼
WebhookController@receive
         │
         ├── Parse payload.entry[].changes[]
         │
         ├── [field = "messages"]
         │   └── handleMessageStatus()
         │       ├── Find MessageLog by whatsapp_message_id
         │       ├── Update status (delivered/read/failed)
         │       └── Increment Redis delivered counter
         │
         └── [field = "message_template_status_update"]
             └── handleTemplateStatus()
                 ├── Find Template by meta_template_name
                 └── Update status (approved/rejected/pending)
```

---

## Data Flow Diagram

```
                    ┌───────────────────┐
                    │   Cloudinary      │
                    │   (Media CDN)     │
                    └────────┬──────────┘
                             │ Media URLs
                             ▼
┌──────────┐    ┌────────────────────────┐    ┌─────────────────┐
│  MySQL   │───▶│     Laravel App        │───▶│  Meta WhatsApp  │
│  Voters  │    │                        │    │  Business API   │
│  (read)  │    │  MongoDB ◄──► Redis    │    │                 │
└──────────┘    └────────────────────────┘    └────────┬────────┘
                         ▲                             │
                         │                             ▼
                    ┌────┴───┐                  ┌─────────────┐
                    │ Admin  │                  │  WhatsApp   │
                    │Browser │                  │  Recipients │
                    └────────┘                  └─────────────┘
```

---

## Redis Key Structure

All keys are prefixed with `bulk_whatsapp_campaign_database_` (from app config).

```
campaign:{campaign_id}:sent            → INT   (messages sent count)
campaign:{campaign_id}:failed          → INT   (messages failed count)
campaign:{campaign_id}:skipped         → INT   (messages skipped count)
campaign:{campaign_id}:delivered       → INT   (delivery confirmations via webhook)
campaign:{campaign_id}:paused          → 1/nil (pause flag)
campaign:{campaign_id}:current_assembly → INT  (current assembly being processed)
campaign:{campaign_id}:current_offset  → INT   (current batch offset)
campaign:{campaign_id}:rate            → INT   (current messages/minute)
campaign:{campaign_id}:started_at      → ISO   (campaign start timestamp)
campaign:{campaign_id}:last_activity   → ISO   (last processing timestamp)
campaign:{campaign_id}:last_error      → STR   (last error message if job failed)

TTL: 7 days for all campaign keys
```

---

## Authentication & Authorization

```
┌──────────────┐     ┌──────────────┐     ┌──────────────┐
│  Browser     │────▶│  AdminAuth   │────▶│  Controller  │
│  Request     │     │  Middleware   │     │  Action      │
└──────────────┘     └──────┬───────┘     └──────────────┘
                            │
                     Auth::guard('admin')
                     checks session
                            │
                     ┌──────▼───────┐
                     │  MongoDB     │
                     │  admins      │
                     │  collection  │
                     └──────────────┘

Guard:    admin (session-based)
Provider: admins → App\Models\Admin (MongoDB Eloquent)
Roles:    super_admin (single role currently)
```

---

## Deployment Requirements

| Component       | Required | Notes                                    |
|-----------------|----------|------------------------------------------|
| PHP 8.1+        | Yes      | With mongodb, redis, pdo_mysql extensions |
| Composer        | Yes      | Dependency management                    |
| Redis Server    | Yes      | Queue + live stats                       |
| MongoDB Atlas   | Yes      | Primary database (cloud)                 |
| MySQL           | Yes      | Voter data (Cloudways remote)            |
| Cloudinary      | Yes      | Template media storage                   |
| Meta Developer  | Yes      | WhatsApp Business API access             |
| Ngrok (dev)     | Optional | Expose local webhook URL to Meta         |

### Process Supervision (Production)

```
# Web server
php artisan serve --host=0.0.0.0 --port=8000

# Queue worker (MUST be running for campaigns)
php artisan queue:work redis --queue=campaign_messages --tries=3 --timeout=600

# Scheduler (for stats sync + template polling)
* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
```

---

## Rate Limiting & Throughput

```
Configurable via .env:
  CAMPAIGN_RATE_LIMIT_PER_SECOND = 80    (messages/second)
  CAMPAIGN_BATCH_SIZE = 500              (voters per job)
  CAMPAIGN_MAX_CONCURRENT_WORKERS = 10

Enforcement:
  ProcessCampaignBatch tracks sent count per wall-clock second.
  When limit reached, sleeps for remainder of the second.

Meta API Limits:
  Tier 1: 1,000 messages/day
  Tier 2: 10,000 messages/day
  Tier 3: 100,000 messages/day
  Tier 4: Unlimited

  Rate limit errors (130429, 131048, 131026) trigger job retry with 60s delay.
```

---

## Error Handling Strategy

```
┌─────────────────────────────────────────────────────┐
│                   Error Handling                      │
├─────────────────────┬───────────────────────────────┤
│ Layer               │ Strategy                       │
├─────────────────────┼───────────────────────────────┤
│ Controller          │ Try-catch, flash messages      │
│ Service             │ Return arrays {success, error} │
│ Queue Job           │ 3 retries, 60s backoff         │
│ Meta API            │ Retry on rate-limit codes      │
│ MySQL voter query   │ Log + skip assembly            │
│ Webhook             │ Log error, return 200 OK       │
│ Template submission │ Save draft, show error in UI   │
│ Media upload        │ Fallback: skip Cloudinary      │
└─────────────────────┴───────────────────────────────┘

Logging: Laravel Log (storage/logs/laravel.log)
  - All Meta API requests/responses logged at INFO level
  - Errors logged at ERROR level with stack traces
  - Campaign batch failures logged with campaign ID, assembly, offset
```
