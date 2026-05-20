# Developer Handoff — QMS Archive System

> **Stack:** Laravel 12 · Inertia.js v2 · Vue 3 · Tailwind CSS v3 · PhpWord 1.4 · Laravel Reverb
> **PHP:** 8.2 · **DB:** MySQL 8.0+ (`archive_system`) · **Auth:** Username + password (role: `admin` | `user`)

---

## 1. Unfinished Features

### 1.1 Automatic Backup

**What exists:**
- `SystemSetting` model stores `auto_backup` (boolean), `backup_frequency` (daily/weekly/monthly), and `storage_location` (local/external/cloud).
- `BackupController::saveSettings()` persists those preferences to the DB.
- `BackupService::createBackup()` fully implements the backup logic (ZIP creation, manifest).
- The Settings → Backup tab UI lets admins toggle auto-backup and pick frequency.

**What is missing:**
- There is **no Artisan command** that reads the `auto_backup` flag and calls `BackupService::createBackup()`.
- There is **no schedule entry** for automatic backups in `routes/console.php`. The only scheduled command today is `document-preview:clean` (daily at 02:00).
- There is **no queue worker involvement** — backups are currently created synchronously on demand only.

**Steps to implement:**
1. Create command: `php artisan make:command RunAutoBackup`
   - In `handle()`, check `SystemSetting::instance()->auto_backup`; if false, exit early.
   - Call `app(BackupService::class)->createBackup()`.
   - Log via `ActivityLogService`.
2. Register in `routes/console.php`:
   ```php
   Schedule::command('backup:run')
       ->when(fn () => SystemSetting::instance()->auto_backup)
       ->daily()   // or weekly()/monthly() based on backup_frequency
       ->withoutOverlapping();
   ```
   Since frequency is dynamic, read `backup_frequency` from settings and branch accordingly, or run daily and skip inside the command if the frequency hasn't elapsed.
3. In production, ensure the scheduler runs via a cron entry:
   ```
   * * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
   ```
4. Optionally push backup creation to a queued job (`app/Jobs/RunBackupJob.php`) to avoid blocking the request in large installations.

---

### 1.2 E-Signature Placement in OFI / DCR / CAR Documents

**What exists:**
- `SettingsController::uploadSignature()` accepts an image (jpg/jpeg/png/webp ≤ 2 MB) and stores it at `public/signatures/`.
- `SystemSetting::e_signature_path` holds the relative path.
- The Settings → Signature tab lets admins upload/remove the signature image.

**What is missing — Frontend:**
- There is **no drag-and-drop placement UI** in any of the record views (`OFIForm`, `DCRController`, `CarRecordController`).
- The user cannot position the signature at a specific coordinate on the form before exporting.
- No draggable overlay or signature placement canvas exists in `resources/js/Pages/`.

**Steps to implement (frontend):**
1. In each record view (OFI, DCR, CAR), add a preview of the form layout (can be a scaled image or a positioned div grid matching the DOCX template).
2. Render the signature image as a draggable element using the HTML Drag and Drop API or a library like `vue-draggable-next`.
3. Capture the final `x` / `y` offset (as percentages or fixed units matching DOCX dimensions) when the user drops the signature.
4. Send those coordinates along with the existing form data to the backend on download/publish.

**What is missing — Backend:**
- `OFIFormGenerator`, `DCRFormGenerator`, and `CARFormGenerator` use `PhpOffice\PhpWord\TemplateProcessor` with text-only `setValue()` calls — no image injection.
- The signature is never read from disk or embedded into any exported DOCX.

**Steps to implement (backend):**
1. Add a `${e_signature}` placeholder to each DOCX template in `storage/qms_templates/`.
2. In each generator's `generate()` method, call `setImageValue()` after setting text placeholders:
   ```php
   $signaturePath = Storage::disk('public')->path($settings->e_signature_path);
   if ($settings->e_signature_path && file_exists($signaturePath)) {
       $processor->setImageValue('e_signature', [
           'path'   => $signaturePath,
           'width'  => 100,  // px — adjust to match template cell
           'height' => 50,
           'ratio'  => false,
       ]);
   }
   ```
3. Accept the drag coordinates from the frontend and, if positional placement (not placeholder-based) is required, switch to `PhpWord` section/shape absolute positioning instead of `TemplateProcessor`.

---

### 1.3 Forgot Password

**What exists:**
- `LoginController` handles only login and logout — no password reset routes exist.
- `UsersController::resetPassword()` lets an **admin** reset any user's password (admin-only, `can:admin-only` middleware).
- `MAIL_MAILER=log` in `.env` — no real email transport configured.
- Two notification classes exist (`RecordSubmittedNotification`, `RecordDecisionNotification`) but no Mailable for password reset.

**What is missing:**
There is **no self-service forgot-password flow** at all. Users who forget their password must contact an admin.

**Steps to implement:**
1. **Configure Gmail SMTP** in `.env`:
   ```
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.gmail.com
   MAIL_PORT=587
   MAIL_ENCRYPTION=tls
   MAIL_USERNAME=your-gmail@gmail.com
   MAIL_PASSWORD=your-app-password   # Gmail App Password, not account password
   MAIL_FROM_ADDRESS=your-gmail@gmail.com
   MAIL_FROM_NAME="${APP_NAME}"
   ```
2. **Add a `pin_reset_tokens` table** via migration with columns: `email` (string), `pin` (string, hashed), `created_at` (timestamp). Laravel's built-in `password_reset_tokens` table can also be reused.
3. **Create the flow:**
   - `GET  /forgot-password` → show email input form
   - `POST /forgot-password` → generate a 6-digit PIN, hash it, store in token table, send via `Mail::to($user)->send(new ForgotPasswordMail($pin))`
   - `GET  /reset-password` → show PIN + new password form
   - `POST /reset-password` → verify PIN hash, check expiry (15 min recommended), update `users.password`, delete token
4. **Create Mailable:** `php artisan make:mail ForgotPasswordMail --markdown=emails.forgot-password`
5. **Add routes** in `routes/web.php` inside the guest-only group (alongside existing login routes).
6. **Note:** Login uses `username`, not `email`. Users do have an `email` column, so email-based reset is feasible — just look up the user by email to find their account.

---

## 2. Other Recommended Improvements

### 2.1 Code Quality
- **No TODO / FIXME comments** found anywhere in the codebase — the code is clean.
- **No stubbed controller methods** — all routes are fully implemented.

### 2.2 Test Coverage
Tests exist in `tests/Feature/` covering:
- `BackupTest`, `BackupRestoreTest`
- `RecordApprovalTest`
- `DocumentControllerTest`, `DocumentUploadDestroyTest`
- `OfiDynamicFieldsTest`, `DcrDynamicFieldsTest`, `CarDynamicFieldsTest`
- `QmsTemplateInfrastructureTest`
- `SettingsSystemTest`

**Gaps worth noting:**
- No tests for `LoginController` (login success/failure, account lockout).
- No tests for `NotificationController`.
- No tests for `PerformanceController` or `ManualController`.
- No tests for `InboxController` (admin approval inbox).

### 2.3 Security Concerns
- **E-signature is an image only** — it is not cryptographically signed. Anyone who can upload a signature image can impersonate the authority. Consider adding audit trail metadata (who embedded it, when) to published documents.
- **No two-factor authentication** — admin accounts are high-value targets with no 2FA option.
- **Default `.env.example` credentials** (`admin@qms.local` / `admin123`) — ensure these are changed on any staging or production deployment.
- **Rate limiting** is applied only to `/login` (6 attempts/min). File operations (backup, restore, upload) have no rate limits.
- **Backup restore** accepts any uploaded ZIP without verifying it originated from this system beyond checking for a `manifest.json`. A crafted ZIP could overwrite arbitrary paths within the `private` disk.

### 2.4 Performance Concerns
- All operations are **synchronous** — DOCX generation, backup creation, and file preview generation all block the HTTP request. For large installations, move these to queued jobs. `QUEUE_CONNECTION=database` is already configured and the `jobs` table migration already exists.
- Document previews are cached to disk (30-day TTL, cleaned nightly at 02:00) — well-designed.
- `OfiRecord`, `DcrRecord`, `CarRecord` store all form data as a JSON `data` column. Querying inside that JSON is not indexed — add generated columns if filtering/searching inside form data is ever needed.

### 2.5 Minor Issues
- **`REVERB_HOST` in `.env.example`** has an inline comment (`# ⚠️ Change to...`) — some parsers may include the comment as part of the value. Strip it in production `.env`.
- **No soft deletes** anywhere — records are permanently deleted or status-flagged. Intentional per current design but worth documenting for future decisions.

---

## 3. Environment Setup

### 3.1 Required PHP Extensions
PHP 8.2 is required. The following extensions must be enabled:

| Extension | Required by |
|-----------|------------|
| `pdo_mysql` | MySQL database driver |
| `zip` | PhpWord DOCX generation + backup ZIP creation |
| `xml` | PhpWord (XML-based DOCX internals) |
| `fileinfo` | Laravel file validation |
| `mbstring` | Laravel string handling |
| `openssl` | Laravel encryption, HTTPS |
| `bcmath` | Laravel general use |
| `curl` | Reverb WebSocket connections |
| `gd` or `imagick` | Profile photo + signature image processing |

### 3.2 Required `.env` Keys

The project uses **MySQL** (`DB_CONNECTION=mysql`). Note that `.env.example` still defaults to `sqlite` (Laravel's upstream default) — ignore that; configure MySQL as shown below.

```bash
# Application
APP_NAME="QMS Archive System"
APP_ENV=local           # Change to 'production' on server
APP_KEY=               # Generated by: php artisan key:generate
APP_DEBUG=false         # Set false in production
APP_URL=http://localhost # Set to public domain in production
APP_TIMEZONE=Asia/Manila

# Database — MySQL (already in use)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=archive_system
DB_USERNAME=root
DB_PASSWORD=            # Set your MySQL root/user password

# Queue / Session / Cache (all database-driven, no extra services needed)
QUEUE_CONNECTION=database
SESSION_DRIVER=database
CACHE_STORE=database

# Broadcasting (Reverb WebSocket — self-hosted, no external account needed)
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=          # Any unique string
REVERB_APP_KEY=         # Any unique string
REVERB_APP_SECRET=      # Long random secret — keep private
REVERB_HOST="localhost" # Server public IP or domain in production
REVERB_PORT=8080
REVERB_SCHEME=http      # Change to 'https' in production

# Frontend WebSocket config (must match REVERB_* values above)
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

# Document preview (LibreOffice)
LIBREOFFICE_BINARY=soffice  # Full path on Windows: "C:/Program Files/LibreOffice/program/soffice.exe"
DOCUMENT_PREVIEW_DISK=private
DOCUMENT_PREVIEW_DIRECTORY=previews
DOCUMENT_PREVIEW_CACHE_TTL_DAYS=30
DOCUMENT_PREVIEW_CONVERSION_TIMEOUT=120

# Mail (currently 'log' — configure SMTP for Forgot Password feature)
MAIL_MAILER=log
# MAIL_MAILER=smtp
# MAIL_HOST=smtp.gmail.com
# MAIL_PORT=587
# MAIL_USERNAME=your@gmail.com
# MAIL_PASSWORD=your-app-password
# MAIL_FROM_ADDRESS=your@gmail.com
# MAIL_FROM_NAME="${APP_NAME}"

# Seeder credentials (used by DatabaseSeeder to create first admin)
ADMIN_EMAIL=admin@qms.local   # Change before production
ADMIN_PASSWORD=admin123       # Change before production
```

### 3.3 First-Time Setup

```bash
# 1. Install dependencies
composer install
npm install

# 2. Configure environment
cp .env.example .env
# Edit .env: set DB_PASSWORD, REVERB_*, APP_KEY, etc.

# 3. Generate app key
php artisan key:generate

# 4. Create MySQL database
# In MySQL: CREATE DATABASE archive_system;

# 5. Run migrations and seeders
php artisan migrate --seed

# 6. Create symlink for public storage (profile photos, signatures, logos)
php artisan storage:link

# 7. Build frontend assets
npm run build
```

### 3.4 Running for Local Development

```bash
composer run dev
```

This starts four processes concurrently:

| Process | Command | Purpose |
|---------|---------|---------|
| Web server | `php artisan serve` | HTTP on `http://localhost:8000` |
| Queue worker | `php artisan queue:listen --tries=1 --timeout=0` | Processes queued jobs (notifications) |
| Log viewer | `php artisan pail --timeout=0` | Streams application logs to terminal |
| Vite | `npm run dev` | Hot-reloads Vue/Tailwind assets |

**Reverb WebSocket server** is not included — start separately if real-time notifications are needed:

```bash
php artisan reverb:start
```

### 3.5 Running the Scheduler

```bash
# Test locally
php artisan schedule:run

# Production — add to server crontab
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

Currently scheduled:
- `document-preview:clean` — daily at 02:00, cleans preview cache files older than 30 days.

### 3.6 Running Tests

```bash
# All tests
php artisan test --compact

# Specific file
php artisan test --compact tests/Feature/BackupTest.php

# Filter by name
php artisan test --compact --filter=testAdminCanCreateBackup
```

### 3.7 Code Formatting

After modifying any PHP files, run Pint before committing:

```bash
vendor/bin/pint --dirty --format agent
```

---

## 4. Application Overview (Quick Reference)

### Key Directories
```
app/
  Http/Controllers/Auth/   — LoginController only (no password reset)
  Http/Controllers/        — 19 feature controllers
  Models/                  — 13 models; OfiRecord/DcrRecord/CarRecord store form data as JSON
  Services/                — OFIFormGenerator, DCRFormGenerator, CARFormGenerator,
                             BackupService, ActivityLogService
  Console/Commands/        — CleanDocumentPreviewCache (only custom Artisan command)

resources/js/
  Pages/                   — Inertia page components (Vue 3)
  Components/              — Shared UI components

routes/
  web.php                  — All routes (no api.php)
  console.php              — Artisan commands + scheduler
  channels.php             — Reverb broadcast channels

storage/
  qms_templates/           — DOCX templates for OFI, DCR, CAR (PhpWord TemplateProcessor)
  app/private/documents/   — Published record documents
  app/backups/             — Backup ZIP files
```

### Record Workflow State Machine
```
[DRAFT] → submitForApproval() → [PENDING] → approve() → [APPROVED] → publish() → [PUBLISHED]
                                           → reject()  → [REJECTED] → (user edits and resubmits)

Resolution status (approved records only, forward-only):
OPEN → ONGOING → CLOSED
```

### Default Admin Login
- **Username:** set via `ADMIN_EMAIL` env value (default: `admin@qms.local`)
- **Password:** `admin123` (from `ADMIN_PASSWORD` env — **change before production**)

---

*Generated: 2026-05-20*
