# Bulk WhatsApp Campaign Manager

A complete bulk WhatsApp messaging system using **Meta WhatsApp Business API**, built with **Laravel 10**, **MongoDB Atlas**, **MySQL** (read-only voter data), **Redis**, and **Cloudinary**.

## Features

- **Super Admin Panel** — Modern dashboard with sidebar navigation, stats cards, real-time monitoring
- **Multiple WhatsApp Accounts** — Add unlimited Meta Business API credentials with live connection checks
- **Template Management** — Create templates with image/video headers, body text, footer, buttons → auto-submit to Meta for approval
- **Assembly-based Targeting** — Select from 234 Tamil Nadu assembly constituencies grouped by zone/district
- **Bulk Campaign Engine** — Redis-queued batch processing with configurable rate limiting
- **Test Mode** — Send campaigns to specific test numbers without selecting assemblies
- **Live Monitoring** — Real-time stats dashboard with auto-refresh (sent/delivered/failed/skipped/speed)
- **Pause/Resume** — Full campaign lifecycle control with checkpoint resume
- **Webhook Integration** — Auto-update template approvals & message delivery statuses from Meta
- **Rate Limiting** — Configurable msg/sec with auto-throttle on Meta rate limits
- **Fault Tolerance** — Auto-retry (3 attempts), checkpoint resume, bulk log inserts, no data loss
- **Media Message Workaround** — Templates with image/video headers sent as media messages with caption (bypasses Meta's silent delivery failure)

## Tech Stack

| Component | Technology |
|-----------|------------|
| **Framework** | Laravel 10 (PHP 8.1+) |
| **Primary Database** | MongoDB Atlas (accounts, templates, campaigns, logs, assemblies) |
| **Voter Database** | MySQL on Cloudways (234 assembly tables, read-only) |
| **Queue & Cache** | Redis (job queues, live campaign stats, caching) |
| **Media Storage** | Cloudinary (template header images/videos) |
| **WhatsApp API** | Meta Graph API v19.0 |
| **Frontend** | Blade + TailwindCSS CDN + Alpine.js + Font Awesome 6 |

## Architecture

```
Browser (Admin)  →  Laravel Controllers  →  Services  →  Meta WhatsApp API
                                         →  MongoDB Atlas (primary DB)
                                         →  MySQL (voter data, read-only)
                                         →  Redis (queues + live stats)
                                         →  Cloudinary (media storage)
```

> See [architecture.md](architecture.md) for detailed diagrams and data flows.

## Requirements

- PHP 8.1+ with extensions: `mongodb`, `redis`, `pdo_mysql`, `gd`, `curl`
- Composer
- Redis 6.0+
- MongoDB Atlas account (or local MongoDB 6.0+)
- MySQL 5.7+ (voter data)
- Cloudinary account
- Meta Developer account with WhatsApp Business API access

## Installation

### 1. Install Dependencies

```bash
cd bulk-campaign
composer install
```

### 2. Environment Setup

```bash
cp .env.example .env
php artisan key:generate
```

### 3. Configure `.env`

```env
# MongoDB Atlas
MONGO_DB_DSN=mongodb+srv://user:password@cluster.mongodb.net/?appName=YourApp
MONGO_DB_DATABASE=bulk_campaign

# MySQL (READ-ONLY voter database)
MYSQL_DB_HOST=your-mysql-host
MYSQL_DB_PORT=3306
MYSQL_DB_DATABASE=voters_db
MYSQL_DB_USERNAME=root
MYSQL_DB_PASSWORD=

# Redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Cloudinary
CLOUDINARY_URL=cloudinary://API_KEY:API_SECRET@CLOUD_NAME
CLOUDINARY_UPLOAD_PRESET=bulk_campaign

# Queue
QUEUE_CONNECTION=redis

# Campaign Settings
CAMPAIGN_BATCH_SIZE=500
CAMPAIGN_RATE_LIMIT_PER_SECOND=80

# Super Admin Login
SUPER_ADMIN_EMAIL=admin@bulkcampaign.com
SUPER_ADMIN_PASSWORD=admin123456
```

### 4. Create Admin & Sync Data

```bash
# Create super admin account
php artisan admin:create --email=admin@bulkcampaign.com --password=admin123456

# Map MySQL voter tables to assembly records
php artisan assemblies:sync-tables

# Sync voter counts from MySQL into MongoDB
php artisan assemblies:sync-counts
```

### 5. Start the Application

You need **3 terminal windows** running simultaneously:

```bash
# Terminal 1: Web server
php artisan serve

# Terminal 2: Queue worker (REQUIRED for campaigns to send)
php artisan queue:work redis --queue=campaign_messages --tries=3 --timeout=600

# Terminal 3: Scheduler (for auto status checks & stats sync)
php artisan schedule:work
```

### 6. Access

Open `http://localhost:8000` and login with your admin credentials.

## Usage Guide

### Adding a WhatsApp Account

1. Go to **WhatsApp Accounts** → **Add Account**
2. Enter your Meta API credentials:
   - **App ID** — From Meta Developer Console
   - **App Secret** — From Meta Developer Console
   - **Access Token** — Permanent token from System Users
   - **Phone Number ID** — From WhatsApp > API Setup
   - **WABA ID** — From WhatsApp > API Setup
3. Connection is verified automatically on save

### Creating a Template

1. Go to **Account Detail** → **Create Template**
2. Fill in: name, language, category, header (text/image/video), body text, footer, buttons
3. Template is auto-submitted to Meta on save
4. Status updates via webhook or polling (approved/rejected)

### Running a Campaign

1. From an approved template, click **Create Campaign**
2. Select target assemblies OR enable **Test Mode** with specific phone numbers
3. Click **Create Campaign** → Review → **Start Campaign**
4. Monitor live progress on the campaign dashboard
5. Use **Pause/Resume** controls as needed

### Test Mode

- Enable the **Test Mode** checkbox on campaign creation
- Enter comma-separated phone numbers (with country code, e.g., `918903162114`)
- Campaign sends only to those numbers — no MySQL queries, no assembly selection needed

## Artisan Commands

| Command | Description |
|---------|-------------|
| `php artisan admin:create` | Create or update a super admin account |
| `php artisan assemblies:sync-tables` | Detect and map MySQL table names to assembly records |
| `php artisan assemblies:sync-counts` | Sync voter counts from MySQL into MongoDB assemblies |
| `php artisan campaign:audit {id?}` | Audit campaign performance — send rates, delivery stats, errors |

## Meta Webhook Setup

1. In **Meta Developer Console** → **WhatsApp** → **Configuration**
2. Set Webhook URL:
   ```
   https://yourdomain.com/api/webhook
   ```
   For local development, use ngrok:
   ```
   https://your-id.ngrok-free.app/api/webhook
   ```
3. Set the **Verify Token** — shown on the account detail page in the admin panel
4. Subscribe to: `messages`, `message_template_status_update`

The webhook handles:
- **Message delivery status** — sent → delivered → read → failed
- **Template status updates** — pending → approved / rejected

## MySQL Voter Tables

The system expects MySQL tables corresponding to 234 Tamil Nadu assembly constituencies. Table names are auto-detected via the `assemblies:sync-tables` command using patterns like `ac_1`, `ac_2`, `assembly_1`, constituency name, etc.

Each table should have:
- **A primary/serial column** — Auto-detected from: `id`, `voter_id`, `sl_no`, `serial_no`
- **A mobile number column** — Auto-detected from: `mobile`, `phone`, `mobile_no`, `phone_number`, `MOBILE_NUMBER`, etc.

No schema changes needed — column detection is fully automatic with fuzzy matching.

## Campaign Performance

| Metric | Value |
|--------|-------|
| Messages per second | ~80 (configurable via `CAMPAIGN_RATE_LIMIT_PER_SECOND`) |
| Batch size | 500 voters per job (configurable via `CAMPAIGN_BATCH_SIZE`) |
| Max concurrent workers | 10 |
| Auto-retry on Meta rate limit | Yes (3 attempts, 60s delay) |
| Checkpoint resume on pause | Yes |
| Stats sync | Redis → MongoDB every minute via scheduler |

### Scaling with Multiple Workers

```bash
# Run 5 parallel workers for ~400 msg/sec
# PowerShell:
1..5 | ForEach-Object { Start-Process php -ArgumentList "artisan","queue:work","redis","--queue=campaign_messages","--tries=3","--timeout=600" }

# Bash/Linux:
for i in $(seq 1 5); do
    php artisan queue:work redis --queue=campaign_messages --tries=3 --timeout=600 &
done
```

## Project Structure

```
bulk-campaign/
├── app/
│   ├── Console/Commands/       # admin:create, assemblies:sync-*, campaign:audit
│   ├── Http/
│   │   ├── Controllers/        # Auth, Dashboard, Account, Template, Campaign, Voter, Webhook
│   │   └── Middleware/          # AdminAuth
│   ├── Jobs/                   # ProcessCampaignBatch
│   ├── Models/                 # Admin, WhatsAppAccount, Template, Campaign, MessageLog, Assembly
│   ├── Providers/              # App, Auth, Event, Route
│   └── Services/               # MetaWhatsAppService, CampaignService, VoterService
├── config/                     # app, auth, database (mongodb + mysql_voters + redis)
├── resources/views/            # Blade templates (layouts, auth, dashboard, accounts, templates, campaigns, voters)
├── routes/
│   ├── web.php                 # All admin panel routes (protected by admin.auth)
│   └── api.php                 # Meta webhook routes (GET verify + POST receive)
├── .env.example                # Environment template
├── architecture.md             # Detailed system architecture diagrams
├── claude.md                   # Full project audit & reference
└── README.md                   # This file
```

## Known Issues

- **Image header templates not delivering** — Meta silently drops template messages with image/video headers on some accounts. The system automatically sends these as media messages with caption instead. See `MetaWhatsAppService::sendAsMediaMessage()`.
- **Queue worker required** — Campaigns won't send without an active queue worker. Always run `php artisan queue:work redis --queue=campaign_messages --tries=3 --timeout=600`.
- **Voter counts showing 0** — Run `php artisan assemblies:sync-counts` to populate counts from MySQL.
- **Ngrok URL changes** — Update `APP_URL` in `.env` when your ngrok tunnel restarts, then reconfigure webhook in Meta console.

## Troubleshooting

| Problem | Solution |
|---------|----------|
| Campaign stuck at 0 sent | Start the queue worker in a separate terminal |
| Template stays "pending" | Check Meta Developer Console; ensure webhook is configured |
| "No mobile column found" | Run `assemblies:sync-tables` to re-detect table schemas |
| Webhook not receiving | Verify `APP_URL` matches your public URL; check ngrok is running |
| Connection check fails | Verify Meta API credentials; ensure access token is not expired |
| Voter counts are 0 | Run `php artisan assemblies:sync-counts` |
| Campaign errors in log | Run `php artisan campaign:audit` for detailed error analysis |

## Security

- Meta Access Token and App Secret are **encrypted at rest** in MongoDB
- Admin passwords hashed with bcrypt
- CSRF protection on all web forms
- All dashboard routes protected by `admin.auth` middleware
- Webhook verify token auto-generated per account
- Sensitive model fields hidden from serialization

## License

MIT
