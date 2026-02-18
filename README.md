# Berufsmesse

A PHP/MySQL web application for organizing career fairs — exhibitor management, student registration, room allocation, QR-code check-in, and admin dashboard.

---

## Table of Contents

- [Features](#features)
- [Quick Start (Docker)](#quick-start-docker)
- [Environment Variables](#environment-variables)
- [First Run — Admin Account](#first-run--admin-account)
- [Database & Migrations](#database--migrations)
- [phpMyAdmin (optional)](#phpmyadmin-optional)
- [Production & HTTPS](#production--https)
- [Updating](#updating)
- [Backup & Restore](#backup--restore)
- [Local Development (no Docker)](#local-development-no-docker)
- [Directory Structure](#directory-structure)

---

## Features

| Area | Functionality |
|---|---|
| **Exhibitors** | CRUD, logo upload, documents, offer types, dynamic industries |
| **Student Frontend** | Exhibitor list with filters & search, detail views, offer badges |
| **Registration** | Students pick up to 3 exhibitors, automatic slot assignment |
| **Admin Dashboard** | Statistics, room plan, manual assignment, assignment reset |
| **Rooms** | Management, slot-specific capacities |
| **Users** | Roles (admin / teacher / orga / student), granular permissions, groups |
| **QR Code** | Check-in page, token management |
| **Reports** | PDF export (room schedules, class lists, personal schedules) |
| **Settings** | Registration period, event date, industry CRUD, QR-code URL |
| **Audit Log** | Full history of all admin actions |

---

## Quick Start (Docker)

### Prerequisites

- [Docker Engine](https://docs.docker.com/engine/install/) ≥ 24
- [Docker Compose](https://docs.docker.com/compose/install/) ≥ 2 (included in recent Docker versions as `docker compose`)

### 1. Clone the repository

```bash
git clone https://github.com/schlaumischlumpf/Berufsmesse.git
cd Berufsmesse
```

### 2. Configure environment

```bash
cp .env.example .env
```

Open `.env` and **set secure passwords**:

```dotenv
DB_ROOT_PASS=my_secure_root_password
DB_USER=berufsmesse
DB_PASS=my_secure_app_password
DB_NAME=berufsmesse
APP_PORT=9000
BASE_URL=/
```

> ⚠️ **Never** commit the `.env` file to version control.

### 3. Build and start

```bash
docker compose up -d --build
```

On first start the following happens automatically:
1. The `db` container (MariaDB) starts and creates the database.
2. The `app` container waits for the database and then runs `database-init.sql` — this creates **all** tables and applies every migration.
3. Apache starts and the application is ready.

### 4. Open in browser

```
http://localhost:9000
```

> On first access you will need to create an admin account — see [First Run](#first-run--admin-account).

---

## Environment Variables

| Variable | Default | Description |
|---|---|---|
| `DB_HOST` | `db` | Database hostname (always `db` in Docker) |
| `DB_USER` | `berufsmesse` | Database user |
| `DB_PASS` | *(empty)* | **Required.** Database password |
| `DB_ROOT_PASS` | *(empty)* | **Required.** MariaDB root password |
| `DB_NAME` | `berufsmesse` | Database name |
| `APP_PORT` | `9000` | Host port for the web application |
| `BASE_URL` | `/` | Base URL, e.g. `/berufsmesse/` for subdirectory |
| `APP_ENV` | `production` | Set to `development` to enable PHP error display |
| `PMA_PORT` | `8080` | Host port for phpMyAdmin (only with `--profile tools`) |

---

## First Run — Admin Account

After the first `docker compose up`:

1. Open `http://localhost:9000/register.php`
2. Register with a username and password (choose role **Administrator**)
3. If you registered as a student, promote the account via SQL:

```bash
docker compose exec db mysql -u"berufsmesse" -p"YOUR_DB_PASS" berufsmesse \
  -e "UPDATE users SET role='admin' WHERE username='your_username';"
```

4. Log in at `http://localhost:9000`
5. Go to **Settings → System Settings** and configure the registration period and event date.

> ⚠️ Delete or protect `register.php` before going to production.

---

## Database & Migrations

### Automatic (recommended)

`database-init.sql` runs **automatically** on every container start via `docker-entrypoint.sh`. It:

- Creates the database and all tables (`CREATE TABLE IF NOT EXISTS`)
- Seeds default data (timeslots, industries, settings)
- Applies all schema migrations (adds missing columns, modifies types)
- Migrates old permissions to the new granular system

Every statement is **idempotent** — safe to run repeatedly.

### Manual

To apply migrations manually:

```bash
docker compose exec app mysql -h db \
  -u"berufsmesse" -p"YOUR_DB_PASS" berufsmesse < database-init.sql
```

Or import via phpMyAdmin (see below).

### Files

| File | Purpose |
|---|---|
| `database-init.sql` | **Main file.** Complete schema creation + all migrations. Run this. |
| `migrations.sql` | Legacy migration file (kept for backward compatibility). |
| `migration_allow_null_timeslot.sql` | Legacy standalone migration (now included in `database-init.sql`). |

---

## phpMyAdmin (optional)

phpMyAdmin is included as an optional service (profile `tools`) and is **not** started by default.

```bash
# Start phpMyAdmin
docker compose --profile tools up -d

# Access at:
# http://localhost:8080
```

> ⚠️ Do not run phpMyAdmin permanently in production. Use only for maintenance.

---

## Production & HTTPS

### Reverse Proxy (recommended)

Use a reverse proxy like **Caddy**, **Nginx Proxy Manager**, or **Traefik** in front of the app container.

Example with **Caddy** (`Caddyfile` on the host):

```caddy
berufsmesse.example.com {
    reverse_proxy localhost:9000
}
```

Caddy automatically obtains TLS certificates via Let's Encrypt.

### Enable secure cookies

Once HTTPS is active, set the secure cookie flag in `config.php`:

```php
ini_set('session.cookie_secure', 1);
```

### Firewall

In production only port 443 (HTTPS) and optionally 80 (HTTP → redirect) should be publicly reachable. Port 9000 and 8080 should **not** be exposed to the internet.

---

## Updating

```bash
# 1. Pull latest code
git pull origin main

# 2. Rebuild and restart
docker compose up -d --build

# database-init.sql runs automatically on restart.
```

> Uploads (logos, documents) and database data are persisted via Docker volumes.

---

## Backup & Restore

### Database backup

```bash
docker compose exec db sh -c \
  'MYSQL_PWD="${MYSQL_PASSWORD}" mysqldump -u"${MYSQL_USER}" "${MYSQL_DATABASE}"' \
  > backup_$(date +%Y%m%d_%H%M%S).sql
```

### Database restore

```bash
docker compose exec -T db sh -c \
  'MYSQL_PWD="${MYSQL_PASSWORD}" mysql -u"${MYSQL_USER}" "${MYSQL_DATABASE}"' \
  < backup_file.sql
```

### Uploads backup (logos & documents)

```bash
docker run --rm \
  -v berufsmesse_uploads:/data \
  -v $(pwd):/backup \
  alpine tar czf /backup/uploads_backup.tar.gz -C /data .
```

### Uploads restore

```bash
docker run --rm \
  -v berufsmesse_uploads:/data \
  -v $(pwd):/backup \
  alpine sh -c "cd /data && tar xzf /backup/uploads_backup.tar.gz"
```

---

## Local Development (no Docker)

### Prerequisites

- PHP ≥ 8.1 with extensions `pdo_mysql`, `mbstring`, `gd`, `zip`
- MariaDB ≥ 10.6 or MySQL ≥ 8.0
- Apache with `mod_rewrite` (or another web server)

### Setup

```bash
# 1. Clone the repository
git clone https://github.com/schlaumischlumpf/Berufsmesse.git
cd Berufsmesse

# 2. Create the database and all tables
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS berufsmesse CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p berufsmesse < database-init.sql

# 3. Set environment variables (or edit config.php directly)
export DB_HOST=127.0.0.1
export DB_USER=root
export DB_PASS=your_password
export DB_NAME=berufsmesse
export APP_ENV=development

# 4. Start PHP's built-in server (development only)
php -S localhost:8000
```

Open `http://localhost:8000` in your browser.

---

## Directory Structure

```
Berufsmesse/
├── api/                       # JSON API endpoints (AJAX)
├── assets/                    # CSS, JS, images
├── fpdf/                      # FPDF library for PDF export
├── pages/                     # Page modules (loaded via ?page=)
│   ├── admin-dashboard.php
│   ├── admin-exhibitors.php
│   ├── admin-rooms.php
│   ├── admin-settings.php     # includes industry CRUD
│   ├── admin-users.php
│   ├── admin-permissions.php
│   ├── admin-registrations.php
│   ├── admin-room-capacities.php
│   ├── admin-audit-logs.php
│   ├── admin-qr-codes.php
│   ├── admin-print.php
│   ├── admin-print-export.php
│   ├── teacher-dashboard.php
│   ├── teacher-class-list.php
│   ├── dashboard.php
│   ├── exhibitors.php
│   ├── registration.php
│   ├── my-registrations.php
│   ├── schedule.php
│   ├── print-view.php
│   └── qr-checkin.php
├── uploads/                   # Uploaded files (persisted via Docker volume)
├── .env.example               # Environment variable template
├── compose.yaml               # Docker Compose configuration
├── config.php                 # App configuration (reads env vars)
├── database-init.sql          # Complete DB schema + all migrations (idempotent)
├── docker-entrypoint.sh       # Waits for DB, runs database-init.sql
├── Dockerfile                 # PHP/Apache image
├── functions.php              # Helper functions (DB, permissions, audit log, etc.)
├── index.php                  # Entry point / router
├── login.php                  # Login page
├── register.php               # Registration page (dev/setup only)
├── change-password.php        # Forced password change
├── logout.php                 # Logout handler
├── setup.php                  # Web-based migration tool (admin only)
├── migrations.sql             # Legacy migrations (superseded by database-init.sql)
└── migration_allow_null_timeslot.sql  # Legacy migration (included in database-init.sql)
```
