# QMS Archive System — Deployment Guide

**System:** QMS Archive System
**Institution:** Leyte Normal University — QMS (ISO) Office
**Stack:** Laravel 12 · PHP 8.2 · Vue 3 · Inertia.js v2 · Vite 7 · TailwindCSS 3

---

## Table of Contents

1. [System Requirements](#system-requirements)
2. [Quick Setup](#quick-setup)
3. [Manual Setup](#manual-setup)
4. [Environment Configuration](#environment-configuration)
5. [Running the Application](#running-the-application)
6. [Third-Party Services and Dependencies](#third-party-services-and-dependencies)
7. [Common Deployment Issues](#common-deployment-issues)

---

## System Requirements

| Requirement | Minimum                        | Notes                                                                                 |
| ----------- | ------------------------------ | ------------------------------------------------------------------------------------- |
| PHP         | 8.2                            | Extensions: pdo, pdo_sqlite or pdo_mysql, zip, fileinfo, mbstring, openssl, xml, curl |
| Composer    | 2.x                            | PHP dependency manager                                                                |
| Node.js     | 18+                            | Required for Vite and npm                                                             |
| npm         | 9+                             | Bundled with Node.js                                                                  |
| Database    | SQLite 3 (default) or MySQL 8.0.16+ | SQLite requires no extra setup                                                        |
| LibreOffice | 7+                             | Required for Office→PDF preview conversion                                            |

> LibreOffice must be installed on the server and the `soffice` binary must be accessible. This is **not optional** if document preview of DOCX/XLSX/PPTX files is needed.

> **MySQL version:** The `document_uploads` table includes a `CHECK` constraint that requires **MySQL 8.0.16 or later**. Earlier versions (MySQL 5.7, MySQL 8.0.0–8.0.15) will silently ignore or error on this constraint. Run `mysql --version` on your server before deploying to confirm compatibility.

---

## Quick Setup

The `composer run setup` script automates the full initial setup:

```bash
git clone <repository-url> archive-system
cd archive-system
composer run setup
```

This script runs in sequence:

1. `composer install` — installs PHP dependencies
2. Copies `.env.example` to `.env` if no `.env` exists
3. `php artisan key:generate` — generates `APP_KEY`
4. **Edit `.env`** — set `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, and any other required values before continuing
5. `php artisan migrate --force` — runs all database migrations
6. `npm install` — installs Node.js dependencies
7. `npm run build` — compiles frontend assets

After running setup, **edit `.env`** to configure your environment (database, Reverb, mail, etc.) and then create the storage symlink:

```bash
php artisan storage:link
```

---

## Manual Setup

Use these steps if you need fine-grained control or are deploying to production.

### 1. Clone and install dependencies

```bash
git clone <repository-url> archive-system
cd archive-system
composer install --no-dev --optimize-autoloader   # production
# composer install                                 # development
npm install
```

### 2. Configure environment

```bash
cp .env.example .env
php artisan key:generate
```

> ⚠️ Back up your `APP_KEY` immediately after generation. If this key is lost, all encrypted sessions and data will be invalidated and users will be logged out permanently.

Edit `.env` with your environment values (see [Environment Configuration](#environment-configuration) below).

### 3. Create the database (SQLite)

```bash
touch database/database.sqlite
```

Skip this step if using MySQL — create the database in MySQL instead and set `DB_*` variables.

### 4. Run migrations

```bash
php artisan migrate --force
```

### 5. Create the storage symlink

```bash
php artisan storage:link
```

This links `public/storage` → `storage/app/public` so uploaded files are web-accessible.

Set directory permissions so the web server can write to storage:

```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

> Replace `www-data` with your web server user (e.g. `nginx`, `apache`, or `www`).

### 6. Build frontend assets

```bash
npm run build
```

Re-run this after every `git pull` that changes frontend files.

### 7. Optimize for production (optional but recommended)

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Clear these caches after any `.env` or code changes:

```bash
php artisan optimize:clear
```

---

## Environment Configuration

Copy `.env.example` to `.env` and set the following values.

### Application

```dotenv
APP_NAME="QMS Archive System"
APP_ENV=production          # local | staging | production
APP_KEY=                    # generated by artisan key:generate
APP_DEBUG=false             # set true only in development
APP_URL=https://your-domain.com
```

### Database

**SQLite (default — no server needed):**

```dotenv
DB_CONNECTION=sqlite
# DB_DATABASE defaults to database/database.sqlite
```

**MySQL:**

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=qms_archive
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password
```

### Queue, Session, Cache

These three must all be set to `database` (default). They require no external services.

```dotenv
QUEUE_CONNECTION=database
SESSION_DRIVER=database
CACHE_STORE=database
```

### Real-time Broadcasting (Laravel Reverb)

Reverb is a **self-hosted** WebSocket server bundled with Laravel. No external service or API key is needed — all values are secrets you define yourself.

```dotenv
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=qms-app-id           # any string — used to identify your app
REVERB_APP_KEY=qms-app-key         # any string — used as public key
REVERB_APP_SECRET=qms-app-secret   # any long random string — keep this private

REVERB_HOST=localhost               # or your server's domain/IP
REVERB_PORT=8080
REVERB_SCHEME=http                  # http or https

REVERB_ALLOWED_ORIGINS="localhost"  # comma-separated allowed origins

# These VITE_ variables make Reverb config available to the browser:
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

> After changing any `VITE_*` variable, run `npm run build` again.

### Document Preview (LibreOffice)

```dotenv
LIBREOFFICE_BINARY=soffice          # or full path, e.g. /usr/bin/soffice

# Optional tuning:
DOCUMENT_PREVIEW_DISK=private
DOCUMENT_PREVIEW_DIRECTORY=previews
DOCUMENT_PREVIEW_CACHE_TTL_DAYS=30
DOCUMENT_PREVIEW_CONVERSION_TIMEOUT=120
```

### Mail

The default driver is `log` (writes to `storage/logs/laravel.log`). Configure for real email delivery:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.yourprovider.com
MAIL_PORT=587
MAIL_USERNAME=your@email.com
MAIL_PASSWORD=your_password
MAIL_FROM_ADDRESS="noreply@your-domain.com"
MAIL_FROM_NAME="${APP_NAME}"
```

> The system does not currently send application emails, but mail is available for future use.

### File Storage (AWS S3 — optional)

By default files are stored on the local filesystem (`FILESYSTEM_DISK=local`). To use S3:

```dotenv
FILESYSTEM_DISK=s3

AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
AWS_DEFAULT_REGION=ap-southeast-1
AWS_BUCKET=your-bucket-name
```

Get credentials from the AWS IAM console at https://aws.amazon.com/iam/.

---

## Running the Application

### Development

`composer run dev` starts all required processes concurrently:

```bash
composer run dev
```

This runs:

- `php artisan serve` — Laravel development server on http://localhost:8000
- `php artisan queue:listen --tries=1 --timeout=0` — processes queued jobs
- `php artisan pail --timeout=0` — real-time log viewer
- `npm run dev` — Vite HMR dev server

### Production

In production, run each process separately and use a process manager (Supervisor, systemd, etc.).

**Web server:** Use Nginx or Apache with PHP-FPM pointing the document root to `/public`.

```nginx
# Minimal Nginx config example
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/archive-system/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

**Queue worker** (required for notifications and background jobs):

```bash
php artisan queue:listen --tries=3
# Or with Supervisor for auto-restart:
php artisan queue:work --sleep=3 --tries=3
```

**Reverb WebSocket server** (required for real-time notifications):

```bash
php artisan reverb:start
# Or with a specific host/port:
php artisan reverb:start --host=0.0.0.0 --port=8080
```

> Both the queue worker and Reverb server must be running at all times. Use Supervisor or systemd to keep them alive. If either stops, real-time notifications will stop working.

---

## First-Time Setup After Login

After the application is deployed and running, the **admin must complete these steps before regular users can publish documents**. Without this setup, users can create and save drafts but the Publish and Download DOCX buttons will fail.

---

### Step 1 — Log in as Admin

Navigate to `http://your-domain.com` and log in with the seeded admin credentials:

- **Email:** value of `ADMIN_EMAIL` in your `.env` (default: `admin@qms.local`)
- **Password:** value of `ADMIN_PASSWORD` in your `.env` (default: `admin123`)

> ⚠️ Change the admin password immediately after first login via **Settings → Profile**.

---

### Step 2 — Upload QMS Templates

The system requires a DOCX template for each module (DCR, OFI, CAR) before documents can be generated or published.

1. Go to **Settings** in the sidebar
2. Select the **System** tab
3. Under **Template Management**, select the module (DCR, OFI, or CAR) from the dropdown
4. Click **Upload Template** and select the corresponding `.docx` template file
5. The uploaded template will be set as active automatically
6. Repeat for each module — DCR, OFI, and CAR each need their own template

> The template files are included in the repository under the `templates/` folder:
> - `F-QMS-001_template.docx` — DCR template
> - `F-QMS-006 Corrective Action Request...docx` — CAR template
> - `F-QMS-007_template_fixed_v6.docx` — OFI template
>
> Upload each file to its corresponding module in Settings → System → Template Management.

> Templates must be `.docx` files with `${placeholder}` variables matching the form fields.
> A maximum of 3 templates can be stored per module — the active one is used for generation.

---

### Step 3 — Create User Accounts

Regular users (department representatives, auditors) need accounts to submit records.

1. Go to **Users** in the sidebar
2. Click **Add User**
3. Fill in name, email, username, and assign the role **user**
4. Share the credentials with the respective department staff

> Only admins can create and manage user accounts.

---

### Step 4 — Configure Dynamic Fields (Optional)

Additional custom fields can be added to DCR, OFI, and CAR forms.

1. Go to **Settings → System**
2. Under **Dynamic Fields**, select the module
3. Click **Add Field** and configure the label, type (text, textarea, date), and whether it is required
4. Fields added here will appear in the "Additional Fields" sidebar of the respective form

---

### Step 5 — Configure System Settings (Optional)

1. Go to **Settings → General**
2. Upload the institution logo — appears on the form header
3. Upload the e-signature — used in generated documents
4. Save settings

---

### Step 6 — Verify End-to-End Flow

Before handing off to users, do a quick smoke test:

1. Log in as a regular user
2. Go to **Create Documents → Create CAR Form** (or OFI/DCR)
3. Fill in the required fields and click **Save Draft** — should succeed
4. Click **Download DOCX** — should generate and download the filled template
5. Click **Submit to Admin** — record should appear in the admin Inbox
6. Log back in as admin, go to **Inbox**, approve the record
7. Confirm the document appears under **Documents**

If Step 6 item 4 (Download DOCX) fails with a template error, go back to Step 2 and ensure the correct module template is uploaded and set as active for that module.

---

## Third-Party Services and Dependencies

### 1. Laravel Reverb

- **What it is:** Self-hosted WebSocket server for real-time broadcasting.
- **Used for:** Real-time notifications (record approved/rejected, new submission alerts).
- **How to get it:** Bundled with the application via `laravel/reverb`. No external account or API key needed — configure your own `REVERB_APP_ID/KEY/SECRET`.

### 2. LibreOffice (`soffice`)

- **What it is:** Open-source office suite with a headless conversion mode.
- **Used for:** Converting DOCX/XLSX/PPTX files to PDF for inline document preview.
- **How to install:**

    ```bash
    # Ubuntu/Debian
    sudo apt-get install libreoffice
    # or minimal headless install:
    sudo apt-get install libreoffice-writer libreoffice-calc libreoffice-impress

    # CentOS/RHEL
    sudo yum install libreoffice

    # macOS
    brew install --cask libreoffice
    ```

    Verify: `soffice --version`

    If `soffice` is not on the system PATH, set `LIBREOFFICE_BINARY` to the full binary path (e.g., `/opt/libreoffice7.6/program/soffice`).

### 3. phpoffice/phpword

- **What it is:** PHP library for generating and reading Word (DOCX) files.
- **Used for:** Generating QMS form documents — DCR (R-QMS-013), CAR (R-QMS-017), OFI (R-QMS-018) — from templates.
- **How to get it:** Installed automatically via Composer. No external service or API key needed.

### 4. AWS S3 (optional)

- **What it is:** Amazon cloud object storage.
- **Used for:** Alternative file storage if `FILESYSTEM_DISK=s3`.
- **How to get it:** Create an AWS account at https://aws.amazon.com/, create an IAM user with S3 permissions, and generate an access key pair. Set `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_BUCKET`.

> The system defaults to local disk storage and does not require AWS.

### 5. SMTP Mail Provider (optional)

- **What it is:** Any standard SMTP email provider.
- **Used for:** Sending email notifications (not currently used by the application but configured for future use).
- **How to get it:** Use any provider (Gmail, Mailgun, Postmark, etc.) and configure the `MAIL_*` variables.

---

## Common Deployment Issues

### `APP_KEY` not set

**Symptom:** `No application encryption key has been specified.`

**Fix:**

```bash
php artisan key:generate
```

---

### Blank page or 500 error after deploy

**Likely causes:**

1. Frontend assets not built — run `npm run build`.
2. Config cache stale — run `php artisan optimize:clear`.
3. Missing `.env` — copy from `.env.example` and set `APP_KEY`.
4. Storage not linked — run `php artisan storage:link`.
5. Wrong directory permissions — `storage/` and `bootstrap/cache/` must be writable by the web server.

---

### Document preview fails or shows error

**Symptom:** Preview button shows an error; DOCX/XLSX files cannot be previewed.

**Fix:**

1. Verify LibreOffice is installed: `soffice --version`
2. If installed at a non-standard path, set `LIBREOFFICE_BINARY=/full/path/to/soffice` in `.env`.
3. Ensure the web server process has permission to execute `soffice`.
4. Increase timeout if conversions time out: `DOCUMENT_PREVIEW_CONVERSION_TIMEOUT=300`.

---

### Real-time notifications not working

**Symptom:** Notification bell does not update without page refresh.

**Fix:**

1. Ensure the Reverb server is running: `php artisan reverb:start`
2. Ensure `BROADCAST_CONNECTION=reverb` in `.env`.
3. Ensure `VITE_REVERB_*` variables match your `REVERB_*` values and that assets were rebuilt after any changes: `npm run build`.
4. Check browser console for WebSocket connection errors.
5. Ensure `REVERB_ALLOWED_ORIGINS` includes your frontend domain.

---

### Queue jobs not processing

**Symptom:** Notifications are not sent; published documents are not created.

**Fix:**

1. Ensure the queue worker is running: `php artisan queue:listen`
2. Check `QUEUE_CONNECTION=database` in `.env`.
3. Run `php artisan migrate` to ensure the `jobs` table exists.
4. Check `storage/logs/laravel.log` for job errors.

---

### Database migration errors

**Symptom:** `SQLSTATE` errors during `php artisan migrate`.

**Fix (SQLite):**

- Ensure the file exists: `touch database/database.sqlite`
- Ensure the file is writable by the web server process.

**Fix (MySQL):**

- Verify `DB_*` credentials are correct.
- Ensure the database exists: `CREATE DATABASE qms_archive;`
- Ensure the user has full privileges on the database.

---

### `npm run build` fails

**Symptom:** Vite build error, often about Node version or missing modules.

**Fix:**

1. Verify Node version: `node --version` — must be 18 or higher.
2. Delete `node_modules` and reinstall: `rm -rf node_modules && npm install`
3. Run `npm run build` again.

---

### Uploaded files return 404

**Symptom:** Uploaded documents or profile photos cannot be accessed.

**Fix:**

```bash
php artisan storage:link
```

This creates `public/storage → storage/app/public`. Without this symlink, uploaded files are inaccessible from the browser.

---

_For technical support, contact the system developer or refer to the Laravel documentation at https://laravel.com/docs._
