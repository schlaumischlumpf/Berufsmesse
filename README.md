# Berufsmesse

Eine PHP/MySQL-Webanwendung zur Organisation und Durchführung von Berufsmessen – inklusive Aussteller-Verwaltung, Schüler-Einschreibung, Raumzuteilung, QR-Code-Check-in und Admin-Dashboard.

---

## Inhaltsverzeichnis

- [Features](#features)
- [Schnellstart mit Docker](#schnellstart-mit-docker)
- [Umgebungsvariablen](#umgebungsvariablen)
- [Erster Start – Admin-Konto & Setup](#erster-start--admin-konto--setup)
- [Datenbank & Migrationen](#datenbank--migrationen)
- [phpMyAdmin (optional)](#phpmyadmin-optional)
- [Produktionsbetrieb & HTTPS](#produktionsbetrieb--https)
- [Anwendung aktualisieren](#anwendung-aktualisieren)
- [Backup & Wiederherstellung](#backup--wiederherstellung)
- [Lokale Entwicklung (ohne Docker)](#lokale-entwicklung-ohne-docker)
- [Verzeichnisstruktur](#verzeichnisstruktur)

---

## Features

| Bereich | Funktion |
|---|---|
| **Aussteller** | Anlegen, Bearbeiten, Logo-Upload, Dokumente, Angebotstypen (Ausbildung, Studium …), dynamische Branchen |
| **Schüler-Frontend** | Aussteller-Übersicht mit Branchenfilter & Suche, Angebots-Badges, Detailansicht |
| **Einschreibung** | Schüler wählen bis zu 3 Aussteller, automatische Slot-Zuteilung |
| **Admin-Dashboard** | Statistiken, Raumplan, manuelle Zuteilung, Zuteilung zurücksetzen |
| **Räume** | Verwaltung, slot-spezifische Kapazitäten |
| **Benutzer** | Rollen (admin / teacher / orga / student), granulares Berechtigungssystem, Gruppen |
| **QR-Code** | Check-in-Seite, Token-Verwaltung |
| **Berichte** | PDF-Export (Raumpläne, Klassenlisten, persönliche Pläne) |
| **Einstellungen** | Einschreibezeitraum, Veranstaltungsdatum, Branchen-CRUD, QR-Code-URL |
| **Audit-Log** | Protokollierung aller Admin-Aktionen |

---

## Schnellstart mit Docker

### Voraussetzungen

- [Docker Engine](https://docs.docker.com/engine/install/) ≥ 24
- [Docker Compose](https://docs.docker.com/compose/install/) ≥ 2 (in neueren Docker-Versionen als `docker compose` enthalten)

### 1. Repository klonen

```bash
git clone https://github.com/schlaumischlumpf/Berufsmesse.git
cd Berufsmesse
```

### 2. Umgebungsvariablen einrichten

```bash
cp .env.example .env
```

Öffne `.env` in einem Editor und **setze sichere Passwörter**:

```dotenv
DB_ROOT_PASS=mein_sicheres_root_passwort
DB_USER=berufsmesse
DB_PASS=mein_sicheres_app_passwort
DB_NAME=berufsmesse
APP_PORT=9000
BASE_URL=/
```

> ⚠️ Committe die `.env`-Datei **niemals** in ein Git-Repository.

### 3. Container bauen und starten

```bash
docker compose up -d --build
```

Beim ersten Start passiert automatisch Folgendes:
1. Der `db`-Container (MariaDB) startet und erstellt die Datenbank.
2. Der `app`-Container wartet auf die Datenbank und führt danach automatisch `database-init.sql` aus – damit werden **alle** Tabellen erstellt und sämtliche Migrationen angewendet.
3. Apache startet und die Anwendung ist erreichbar.

### 4. Anwendung aufrufen

```
http://localhost:9000
```

> Beim ersten Aufruf muss ein Admin-Konto angelegt werden – siehe [Erster Start](#erster-start--admin-konto--setup).

---

## Umgebungsvariablen

| Variable | Standard | Beschreibung |
|---|---|---|
| `DB_HOST` | `db` | Hostname der Datenbank (in Docker immer `db`) |
| `DB_USER` | `berufsmesse` | Datenbankbenutzer |
| `DB_PASS` | *(leer)* | **Pflichtfeld.** Datenbankpasswort |
| `DB_ROOT_PASS` | *(leer)* | **Pflichtfeld.** MariaDB-Root-Passwort |
| `DB_NAME` | `berufsmesse` | Datenbankname |
| `APP_PORT` | `9000` | Host-Port für die Webanwendung |
| `BASE_URL` | `/` | Basis-URL, z. B. `/berufsmesse/` bei Unterverzeichnis |
| `APP_ENV` | `production` | `development` aktiviert PHP-Fehleranzeige |
| `PMA_PORT` | `8080` | Host-Port für phpMyAdmin (nur mit `--profile tools`) |

---

## Erster Start – Admin-Konto & Setup

Nach dem ersten `docker compose up` musst du ein Admin-Konto anlegen:

1. Öffne `http://localhost:9000/register.php`
2. Registriere dich mit einem Benutzernamen und Passwort (Rolle **Administrator** wählen)
3. Falls du dich als Schüler registriert hast, setze die Rolle manuell auf `admin` per SQL:

```bash
docker compose exec db mysql -u"berufsmesse" -p"DEIN_DB_PASS" berufsmesse \
  -e "UPDATE users SET role='admin' WHERE username='dein_benutzername';"
```

4. Logge dich auf `http://localhost:9000` ein.
5. Navigiere zu **Einstellungen → System-Einstellungen** und konfiguriere den Einschreibezeitraum und das Veranstaltungsdatum.

> ⚠️ Lösche oder schütze `register.php` vor dem Produktionsbetrieb.

---

## Datenbank & Migrationen

### Automatisch (empfohlen)

`database-init.sql` wird bei jedem Container-Start **automatisch** über `docker-entrypoint.sh` ausgeführt. Sie:

- Erstellt die Datenbank und alle Tabellen (`CREATE TABLE IF NOT EXISTS`)
- Füllt Standarddaten ein (Zeitslots, Branchen, Einstellungen)
- Wendet alle Schema-Migrationen an (fehlende Spalten, Typ-Änderungen)
- Migriert alte Berechtigungen in das neue granulare System

Alle Statements sind **idempotent** – wiederholtes Ausführen ist sicher.

### Manuelle Ausführung

Falls du Migrationen manuell anwenden möchtest:

```bash
docker compose exec app mysql -h db \
  -u"berufsmesse" -p"DEIN_DB_PASS" berufsmesse < database-init.sql
```

Oder über phpMyAdmin (siehe unten).

### Dateien

| Datei | Zweck |
|---|---|
| `database-init.sql` | **Hauptdatei.** Vollständige Schema-Erstellung + alle Migrationen. Diese ausführen. |
| `migrations.sql` | Ältere Migrationsdatei (aus Kompatibilitätsgründen beibehalten). |
| `migration_allow_null_timeslot.sql` | Ältere Einzelmigration (jetzt in `database-init.sql` enthalten). |

---

## phpMyAdmin (optional)

phpMyAdmin ist als optionaler Service mit dem `tools`-Profil enthalten und wird standardmäßig **nicht** gestartet.

```bash
# phpMyAdmin zusätzlich starten
docker compose --profile tools up -d

# Erreichbar unter:
# http://localhost:8080
```

> ⚠️ Starte phpMyAdmin nicht dauerhaft in Produktion. Nutze es nur für Wartungsarbeiten und stoppe es danach wieder.

---

## Produktionsbetrieb & HTTPS

### Mit einem Reverse Proxy (empfohlen)

Für HTTPS-Betrieb empfiehlt sich ein Reverse Proxy wie **Caddy**, **Nginx Proxy Manager** oder **Traefik** vor dem App-Container.

Beispiel mit **Caddy** (`Caddyfile` auf dem Host-System):

```caddy
berufsmesse.example.com {
    reverse_proxy localhost:9000
}
```

Caddy übernimmt automatisch die TLS-Zertifikate via Let's Encrypt.

### Secure Cookies aktivieren

Sobald HTTPS aktiv ist, aktiviere das Secure-Cookie-Flag in `config.php`:

```php
ini_set('session.cookie_secure', 1);
```

### Firewall

In Produktion sollte nur Port 443 (HTTPS) und ggf. 80 (HTTP → Redirect) öffentlich erreichbar sein. Port 9000 und 8080 sollten **nicht** direkt im Internet exponiert werden.

---

## Anwendung aktualisieren

```bash
# 1. Neuen Code holen
git pull origin main

# 2. Image neu bauen und Container neu starten
docker compose up -d --build

# database-init.sql wird beim Neustart automatisch angewendet.
```

> Uploads (Logos, Dokumente) und Datenbankdaten bleiben durch Docker-Volumes erhalten.

---

## Backup & Wiederherstellung

### Datenbank-Backup

```bash
docker compose exec db sh -c \
  'MYSQL_PWD="${MYSQL_PASSWORD}" mysqldump -u"${MYSQL_USER}" "${MYSQL_DATABASE}"' \
  > backup_$(date +%Y%m%d_%H%M%S).sql
```

### Datenbank-Wiederherstellung

```bash
docker compose exec -T db sh -c \
  'MYSQL_PWD="${MYSQL_PASSWORD}" mysql -u"${MYSQL_USER}" "${MYSQL_DATABASE}"' \
  < backup_datei.sql
```

### Uploads-Backup (Logos & Dokumente)

```bash
docker run --rm \
  -v berufsmesse_uploads:/data \
  -v $(pwd):/backup \
  alpine tar czf /backup/uploads_backup.tar.gz -C /data .
```

### Uploads-Wiederherstellung

```bash
docker run --rm \
  -v berufsmesse_uploads:/data \
  -v $(pwd):/backup \
  alpine sh -c "cd /data && tar xzf /backup/uploads_backup.tar.gz"
```

---

## Lokale Entwicklung (ohne Docker)

### Voraussetzungen

- PHP ≥ 8.1 mit den Extensions `pdo_mysql`, `mbstring`, `gd`, `zip`
- MariaDB ≥ 10.6 oder MySQL ≥ 8.0
- Apache mit `mod_rewrite` (oder ein alternativer Webserver)

### Setup

```bash
# 1. Repository klonen
git clone https://github.com/schlaumischlumpf/Berufsmesse.git
cd Berufsmesse

# 2. Datenbank und alle Tabellen erstellen
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS berufsmesse CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p berufsmesse < database-init.sql

# 3. Umgebungsvariablen setzen (oder config.php direkt anpassen)
export DB_HOST=127.0.0.1
export DB_USER=root
export DB_PASS=dein_passwort
export DB_NAME=berufsmesse
export APP_ENV=development

# 4. Eingebetteten PHP-Server starten (nur für Entwicklung)
php -S localhost:8000
```

Öffne `http://localhost:8000` im Browser.

---

## Verzeichnisstruktur

```
Berufsmesse/
├── api/                       # JSON-APIs (AJAX-Endpunkte)
├── assets/                    # CSS, JS, Bilder
├── fpdf/                      # FPDF-Bibliothek für PDF-Export
├── pages/                     # Seitenmodule (per ?page= geladen)
│   ├── admin-dashboard.php
│   ├── admin-exhibitors.php
│   ├── admin-rooms.php
│   ├── admin-settings.php     # inkl. Branchen-CRUD
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
├── uploads/                   # Hochgeladene Dateien (per Docker Volume persistiert)
├── .env.example               # Vorlage für Umgebungsvariablen
├── compose.yaml               # Docker Compose Konfiguration
├── config.php                 # App-Konfiguration (liest Env-Variablen)
├── database-init.sql          # Vollständiges DB-Schema + alle Migrationen (idempotent)
├── docker-entrypoint.sh       # Wartet auf DB, führt database-init.sql aus
├── Dockerfile                 # PHP/Apache Image
├── functions.php              # Hilfsfunktionen (DB, Berechtigungen, Audit-Log …)
├── index.php                  # Einstiegspunkt / Router
├── login.php                  # Login-Seite
├── register.php               # Registrierungsseite (nur für Entwicklung/Setup)
├── change-password.php        # Erzwungene Passwortänderung
├── logout.php                 # Logout-Handler
├── setup.php                  # Webbasiertes Migrationstool (nur für Admins)
├── migrations.sql             # Ältere Migrationen (ersetzt durch database-init.sql)
└── migration_allow_null_timeslot.sql  # Ältere Migration (in database-init.sql enthalten)
```
