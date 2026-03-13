# Implementierungsprompt: Berufsmesse — Multi-Schulen, Aussteller-Accounts, Darkmode, QR-Kamera, Mobile-Fix

**Datum:** März 2026  
**Scope:** Feature D (Multi-Schulen-Architektur) + Feature E (Aussteller-Accounts) + Feature F (QR-Kamera-Scanner) + Feature G (Darkmode) + Bugfix (Tagesplan Mobile)

---

## Kontext

PHP/MySQL-Anwendung, kein Framework, plain PDO. Routing über `index.php?page=xyz`
mit `include`. Globale Funktionen in `functions.php`. Tailwind CDN. Fehler-Logging
via `logErrorToAudit($e, 'Kontext')`. Zugriffssteuerung via `isAdmin()` /
`hasPermission('key')`. Bereits implementiert: Messe-Editionen (`messe_editions` mit
`edition_id` auf allen Tabellen), CSRF-Schutz, QR-Tokens (ohne Kamera),
granulares Berechtigungssystem (`user_permissions`).

**Bestehende Rollen:** `admin`, `student`, `teacher`, `orga`  
**Bestehende Tabellen:** `users`, `exhibitors`, `registrations`, `timeslots`, `rooms`,
`room_slot_capacities`, `attendance`, `qr_tokens`, `exhibitor_documents`,
`exhibitor_orga_team`, `messe_editions`, `announcements`, `audit_logs`,
`login_attempts`, `settings`, `industries`, `permission_groups`,
`permission_group_items`, `user_permissions`, `user_permission_groups`

**Absolute Regeln (nie anfassen):**
- `compose.yaml`, `Dockerfile`, `.dockerignore`, `.github/`
- Bestehende CSRF-Logik, Audit-Logging, fpdf-Bibliothek

---

## Architektur-Überblick: Multi-Schulen

### URL-Schema

```
example.com/                          → Landingpage mit Schulauswahl
example.com/{schul-slug}/             → Login-Seite der Schule
example.com/{schul-slug}/index.php?page=dashboard  → Dashboard
example.com/{schul-slug}/login.php    → Login
example.com/{schul-slug}/api/...      → API-Endpunkte
```

Der `{schul-slug}` ist ein URL-freundlicher Kurzname (z.B. `gymnasium-muster`).
Globale Admins und Aussteller loggen sich über `example.com/login.php` (ohne Schul-Slug) ein und sehen ein Schul-Auswahl-Dashboard.

### Rollen-Hierarchie (NEU)

| Rolle | Scope | Beschreibung |
|---|---|---|
| `admin` | Global | Sieht und verwaltet ALLE Schulen. Kann Schulen erstellen, Schul-Admins ernennen. Hat Zugriff auf alles. |
| `school_admin` | Pro Schule | Verwaltet genau eine Schule (Aussteller, Räume, Schüler, Einstellungen, Editionen). Kein Zugriff auf andere Schulen. |
| `exhibitor` | Pro Unternehmen, schulübergreifend | Sieht eigene Aussteller-Infos für alle Schulen, bei denen das Unternehmen ausstellt. Globaler Login. |
| `teacher` | Pro Schule + Edition | Wie bisher, an Schule und Edition gebunden. |
| `orga` | Pro Schule + Edition | Wie bisher, an Schule und Edition gebunden. |
| `student` | Pro Schule + Edition | Wie bisher, an Schule und Edition gebunden. |

---

## Übersicht: betroffene Dateien

### Neu erstellen

| Datei | Grund |
|---|---|
| `schools.php` | Landingpage: Schulauswahl (öffentlich) |
| `pages/admin-schools.php` | Admin: Schulen-Verwaltung (CRUD) |
| `pages/exhibitor-dashboard.php` | Aussteller: Dashboard mit Schulübersicht |
| `pages/exhibitor-slots.php` | Aussteller: Slot-Anmeldungen pro Schule |
| `pages/exhibitor-profile.php` | Aussteller: Eigenes Profil bearbeiten |
| `pages/exhibitor-equipment.php` | Aussteller: Ausstattungsanfragen |
| `pages/exhibitor-documents.php` | Aussteller: Eigene Dokumente verwalten |
| `pages/admin-equipment.php` | Schuladmin: Ausstattungs-Optionen pflegen |
| `api/schools.php` | API: Schul-CRUD |
| `api/exhibitor-equipment.php` | API: Ausstattungsanfragen speichern |
| `assets/js/darkmode.js` | Darkmode-Toggle-Logik |
| `assets/js/qr-camera.js` | Kamera-basierter QR-Scanner |
| `.htaccess` | URL-Rewriting für `/{schul-slug}/` |

### Ändern

`functions.php` · `config.php` · `login.php` · `logout.php` · `index.php` ·
`site-auth.php` · `setup.php` · `update_schema.sql` · `register.php` ·
`change-password.php` ·
`pages/admin-dashboard.php` · `pages/admin-settings.php` ·
`pages/admin-exhibitors.php` · `pages/admin-users.php` ·
`pages/admin-editions.php` · `pages/admin-permissions.php` ·
`pages/admin-rooms.php` · `pages/admin-room-capacities.php` ·
`pages/admin-registrations.php` · `pages/admin-attendance.php` ·
`pages/admin-qr-codes.php` · `pages/admin-print.php` ·
`pages/admin-print-export.php` · `pages/admin-announcements.php` ·
`pages/admin-audit-logs.php` ·
`pages/dashboard.php` · `pages/exhibitors.php` · `pages/registration.php` ·
`pages/my-registrations.php` · `pages/schedule.php` ·
`pages/teacher-dashboard.php` · `pages/teacher-class-list.php` ·
`pages/print-view.php` · `pages/qr-checkin.php` ·
`assets/css/design-system.css` ·
alle `api/*.php`-Dateien (school_id-Kontext)

---

## 1. Datenbank-Migrationen (`update_schema.sql`)

### 1a) Tabelle `schools` (NEU)

```sql
CREATE TABLE IF NOT EXISTS `schools` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL COMMENT 'Anzeigename der Schule',
  `slug` VARCHAR(100) NOT NULL COMMENT 'URL-Slug, z.B. gymnasium-muster',
  `logo` VARCHAR(255) DEFAULT NULL COMMENT 'Pfad zum Schullogo',
  `address` VARCHAR(500) DEFAULT NULL,
  `contact_email` VARCHAR(255) DEFAULT NULL,
  `contact_phone` VARCHAR(50) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` INT(11) DEFAULT NULL COMMENT 'Admin der die Schule erstellt hat',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_slug` (`slug`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Schulen (Mandanten)';
```

**Migration bestehender Daten:** Eine Standardschule anlegen und alle bestehenden Daten zuordnen:

```sql
INSERT INTO `schools` (name, slug, is_active)
SELECT 'Standardschule', 'standard', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM schools LIMIT 1);
```

### 1b) `school_id` auf alle relevanten Tabellen

Spalte `school_id INT(11) NOT NULL DEFAULT 1` hinzufügen auf:
- `messe_editions` — jede Schule hat ihre eigenen Editionen
- `users` — Schüler/Lehrer/Orga gehören zu einer Schule (admin/exhibitor: `school_id = NULL`)
- `settings` — Einstellungen pro Schule
- `announcements` — Ankündigungen pro Schule
- `industries` — Branchen können pro Schule gepflegt werden (optional global)

**NICHT** auf: `exhibitors`, `rooms`, `timeslots`, `registrations`, `attendance`, `qr_tokens`, `exhibitor_documents`, `room_slot_capacities` — diese bleiben an `edition_id` gebunden. Editionen gehören zu Schulen, also ergibt sich der School-Scope transitiv.

```sql
-- Stored Procedure (wiederverwendbar)
DROP PROCEDURE IF EXISTS add_school_id;
DELIMITER //
CREATE PROCEDURE add_school_id(IN p_table VARCHAR(64))
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=p_table AND COLUMN_NAME='school_id') THEN
        SET @s = CONCAT('ALTER TABLE `',p_table,'` ADD COLUMN `school_id` INT(11) DEFAULT NULL, ADD KEY `idx_school_id` (`school_id`)');
        PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END //
DELIMITER ;

CALL add_school_id('messe_editions');
CALL add_school_id('users');
CALL add_school_id('settings');
CALL add_school_id('announcements');

-- Bestehende Daten der Standardschule zuordnen
UPDATE messe_editions SET school_id = 1 WHERE school_id IS NULL;
UPDATE users SET school_id = 1 WHERE role NOT IN ('admin') AND school_id IS NULL;
UPDATE settings SET school_id = 1 WHERE school_id IS NULL;
UPDATE announcements SET school_id = 1 WHERE school_id IS NULL;

DROP PROCEDURE IF EXISTS add_school_id;
```

### 1c) Tabelle `exhibitor_users` (NEU) — Aussteller-Account-Verknüpfung

```sql
CREATE TABLE IF NOT EXISTS `exhibitor_users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL COMMENT 'User mit role=exhibitor',
  `exhibitor_id` INT(11) NOT NULL COMMENT 'Verknüpftes Unternehmen (exhibitors.id)',
  `can_edit_profile` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Darf Unternehmensprofil bearbeiten',
  `can_manage_documents` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Darf Dokumente verwalten',
  `assigned_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_exhibitor` (`user_id`, `exhibitor_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_exhibitor_id` (`exhibitor_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`exhibitor_id`) REFERENCES `exhibitors`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Verknüpfung: Aussteller-User ↔ Unternehmen (N:M)';
```

### 1d) Tabelle `equipment_options` (NEU) — Pro Schule konfigurierbare Ausstattung

```sql
CREATE TABLE IF NOT EXISTS `equipment_options` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `school_id` INT(11) NOT NULL,
  `name` VARCHAR(150) NOT NULL COMMENT 'z.B. Beamer, Strom, WLAN, Tische',
  `description` VARCHAR(500) DEFAULT NULL,
  `sort_order` INT(11) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_school_active` (`school_id`, `is_active`),
  FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Ausstattungsoptionen pro Schule (Checkboxen für Aussteller)';
```

### 1e) Tabelle `exhibitor_equipment_requests` (NEU)

```sql
CREATE TABLE IF NOT EXISTS `exhibitor_equipment_requests` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `exhibitor_id` INT(11) NOT NULL,
  `edition_id` INT(11) NOT NULL,
  `equipment_option_id` INT(11) DEFAULT NULL COMMENT 'NULL bei Freitext',
  `custom_text` TEXT DEFAULT NULL COMMENT 'Freitext-Anfrage',
  `quantity` INT(11) DEFAULT 1,
  `status` ENUM('pending','approved','denied') NOT NULL DEFAULT 'pending',
  `admin_notes` TEXT DEFAULT NULL,
  `requested_by` INT(11) DEFAULT NULL COMMENT 'User der die Anfrage gestellt hat',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_exhibitor_edition` (`exhibitor_id`, `edition_id`),
  FOREIGN KEY (`exhibitor_id`) REFERENCES `exhibitors`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`equipment_option_id`) REFERENCES `equipment_options`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`requested_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Ausstattungsanfragen von Ausstellern';
```

### 1f) `users`-Tabelle erweitern

```sql
-- Rolle 'exhibitor' und 'school_admin' als gültige Werte
-- (role ist VARCHAR(50), kein ENUM, also kein ALTER nötig)

-- UNIQUE-Constraint anpassen: username + school_id (statt username + edition_id)
-- Admins und Aussteller haben school_id = NULL → global eindeutig
-- Schüler/Lehrer etc. haben school_id gesetzt → pro Schule eindeutig
-- ACHTUNG: Bestehenden Constraint `unique_username_edition` beibehalten für Abwärtskompatibilität,
-- neuen Constraint hinzufügen:

SET @uidx = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND INDEX_NAME='unique_username_school');
SET @s = IF(@uidx = 0,
    'ALTER TABLE `users` ADD UNIQUE KEY `unique_username_school` (`username`, `school_id`)',
    'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
```

### 1g) `settings`-Tabelle: Zusammengesetzter Key

```sql
-- Settings werden pro Schule: UNIQUE(setting_key, school_id)
-- Bestehender UNIQUE auf setting_key muss angepasst werden
SET @idx = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='settings' AND INDEX_NAME='setting_key');
SET @s = IF(@idx > 0, 'ALTER TABLE `settings` DROP INDEX `setting_key`', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @uidx = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='settings' AND INDEX_NAME='unique_key_school');
SET @s = IF(@uidx = 0,
    'ALTER TABLE `settings` ADD UNIQUE KEY `unique_key_school` (`setting_key`, `school_id`)',
    'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
```

### 1h) Tabelle `user_darkmode_preferences` — NICHT NÖTIG

Darkmode wird clientseitig via `localStorage` gespeichert. Keine DB-Tabelle nötig.

---

## 2. URL-Rewriting (`.htaccess`)

Erstelle bzw. ergänze die `.htaccess` im Projektroot:

```apache
RewriteEngine On

# Statische Assets durchlassen
RewriteRule ^assets/ - [L]
RewriteRule ^uploads/ - [L]
RewriteRule ^fpdf/ - [L]

# Landingpage (Root ohne Slug)
RewriteRule ^$ schools.php [L]

# Globaler Login (ohne Schul-Slug, für Admins und Aussteller)
RewriteRule ^login\.php$ login.php [L]

# Schul-Slug-Routing
# /{slug}/login.php → login.php?school_slug={slug}
RewriteRule ^([a-z0-9-]+)/login\.php$ login.php?school_slug=$1 [L,QSA]

# /{slug}/register.php → register.php?school_slug={slug}
RewriteRule ^([a-z0-9-]+)/register\.php$ register.php?school_slug=$1 [L,QSA]

# /{slug}/site-auth.php → site-auth.php?school_slug={slug}
RewriteRule ^([a-z0-9-]+)/site-auth\.php$ site-auth.php?school_slug=$1 [L,QSA]

# /{slug}/index.php?page=... → index.php?school_slug={slug}&page=...
RewriteRule ^([a-z0-9-]+)/index\.php$ index.php?school_slug=$1 [L,QSA]

# /{slug}/api/{file}.php → api/{file}.php?school_slug={slug}
RewriteRule ^([a-z0-9-]+)/api/(.+\.php)$ api/$2?school_slug=$1 [L,QSA]

# /{slug}/ → index.php?school_slug={slug} (Kurzform)
RewriteRule ^([a-z0-9-]+)/?$ index.php?school_slug=$1 [L,QSA]

# /{slug}/change-password.php
RewriteRule ^([a-z0-9-]+)/change-password\.php$ change-password.php?school_slug=$1 [L,QSA]

# /{slug}/logout.php
RewriteRule ^([a-z0-9-]+)/logout\.php$ logout.php?school_slug=$1 [L,QSA]
```

---

## 3. `functions.php` — Neue und geänderte Funktionen

### 3a) Schul-Kontext-Funktionen (NEU)

Direkt nach den bestehenden Edition-Funktionen einfügen:

```php
// ─── MULTI-SCHOOL ────────────────────────────────────────────────────────────

/**
 * Ermittelt die aktuelle Schule aus dem URL-Slug.
 * Gibt das School-Array zurück oder null wenn kein Slug.
 * Speichert Ergebnis in $_SESSION['current_school'].
 */
function getCurrentSchool(): ?array {
    // Aus Session laden wenn vorhanden
    if (isset($_SESSION['current_school'])) {
        return $_SESSION['current_school'];
    }
    
    $slug = $_GET['school_slug'] ?? null;
    if (!$slug) return null;
    
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM schools WHERE slug = ? AND is_active = 1");
        $stmt->execute([trim($slug)]);
        $school = $stmt->fetch();
        if ($school) {
            $_SESSION['current_school'] = $school;
            return $school;
        }
    } catch (Exception $e) {
        logErrorToAudit($e, 'getCurrentSchool');
    }
    return null;
}

/**
 * Gibt die aktuelle school_id zurück. 0 = kein Schulkontext (global).
 */
function getCurrentSchoolId(): int {
    $school = getCurrentSchool();
    return $school ? (int)$school['id'] : 0;
}

/**
 * Gibt den URL-Slug der aktuellen Schule zurück oder ''.
 */
function getCurrentSchoolSlug(): string {
    $school = getCurrentSchool();
    return $school ? $school['slug'] : '';
}

/**
 * Erzeugt eine URL mit Schul-Slug-Prefix.
 * schoolUrl('index.php?page=dashboard') → '/gymnasium-muster/index.php?page=dashboard'
 * Ohne Schulkontext: '/index.php?page=dashboard'
 */
function schoolUrl(string $path): string {
    $slug = getCurrentSchoolSlug();
    $base = rtrim(BASE_URL, '/');
    if ($slug) {
        return $base . '/' . $slug . '/' . ltrim($path, '/');
    }
    return $base . '/' . ltrim($path, '/');
}

/**
 * Prüft ob der aktuelle User Zugang zur aktuellen Schule hat.
 * Admins haben immer Zugang. Schul-Admins nur zu ihrer Schule.
 * Studenten/Lehrer/Orga nur zu ihrer Schule.
 * Aussteller: Zugang wenn ihr Unternehmen an dieser Schule ausstellt.
 */
function hasSchoolAccess(?int $schoolId = null): bool {
    if (!isLoggedIn()) return false;
    $schoolId = $schoolId ?? getCurrentSchoolId();
    
    // Globale Admins haben überall Zugang
    if (isAdmin()) return true;
    
    $role = $_SESSION['role'] ?? '';
    
    // Aussteller: Prüfe ob Unternehmen an der Schule ausstellt
    if ($role === 'exhibitor') {
        return exhibitorHasSchoolAccess($_SESSION['user_id'], $schoolId);
    }
    
    // Alle anderen: school_id muss übereinstimmen
    $userSchoolId = $_SESSION['school_id'] ?? 0;
    return (int)$userSchoolId === (int)$schoolId;
}

/**
 * Prüft ob ein Aussteller-User Zugang zu einer bestimmten Schule hat.
 * Bedingung: Mindestens einer seiner verknüpften Aussteller hat eine
 * aktive Edition in dieser Schule.
 */
function exhibitorHasSchoolAccess(int $userId, int $schoolId): bool {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM exhibitor_users eu
            JOIN exhibitors e ON eu.exhibitor_id = e.id
            JOIN messe_editions me ON e.edition_id = me.id
            WHERE eu.user_id = ? AND me.school_id = ? AND e.active = 1
        ");
        $stmt->execute([$userId, $schoolId]);
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) { return false; }
}

function isSchoolAdmin(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'school_admin';
}

function isExhibitor(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'exhibitor';
}

/**
 * Prüft ob der User Admin-Rechte für die aktuelle Schule hat.
 * Gilt für: admin (global) ODER school_admin (eigene Schule).
 */
function isSchoolAdminOrAdmin(): bool {
    if (isAdmin()) return true;
    if (isSchoolAdmin() && hasSchoolAccess()) return true;
    return false;
}

/**
 * Erzwingt Schulkontext. Bricht ab wenn kein gültiger Slug oder kein Zugang.
 */
function requireSchoolContext(): array {
    $school = getCurrentSchool();
    if (!$school) {
        header('Location: ' . BASE_URL);
        exit;
    }
    if (!hasSchoolAccess((int)$school['id'])) {
        http_response_code(403);
        die('Kein Zugang zu dieser Schule.');
    }
    return $school;
}

/**
 * Alle Schulen laden, die für den aktuellen User sichtbar sind.
 * Admins: alle. Schul-Admins: nur eigene. Aussteller: alle wo sie ausstellen.
 */
function getSchoolsForUser(int $userId, string $role): array {
    $db = getDB();
    
    if ($role === 'admin') {
        $stmt = $db->query("SELECT * FROM schools WHERE is_active = 1 ORDER BY name");
        return $stmt->fetchAll();
    }
    
    if ($role === 'school_admin') {
        $stmt = $db->prepare("SELECT s.* FROM schools s WHERE s.id = (SELECT school_id FROM users WHERE id = ?) AND s.is_active = 1");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    
    if ($role === 'exhibitor') {
        $stmt = $db->prepare("
            SELECT DISTINCT s.* FROM schools s
            JOIN messe_editions me ON me.school_id = s.id
            JOIN exhibitors e ON e.edition_id = me.id
            JOIN exhibitor_users eu ON eu.exhibitor_id = e.id
            WHERE eu.user_id = ? AND s.is_active = 1 AND e.active = 1
            ORDER BY s.name
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    
    // Student/Teacher/Orga: nur eigene Schule
    $stmt = $db->prepare("SELECT s.* FROM schools s JOIN users u ON u.school_id = s.id WHERE u.id = ? AND s.is_active = 1");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

// ─────────────────────────────────────────────────────────────────────────────
```

### 3b) `getSetting()` / `setSetting()` anpassen

Die bestehende `getSetting()`-Funktion muss den Schulkontext berücksichtigen:

```php
// ALT:
function getSetting($key, $default = '') {
    $db = getDB();
    $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    // ...
}

// NEU:
function getSetting($key, $default = '', ?int $schoolId = null) {
    $schoolId = $schoolId ?? getCurrentSchoolId();
    $db = getDB();
    // Zuerst schulspezifisch suchen, dann global (school_id IS NULL)
    $stmt = $db->prepare("
        SELECT setting_value FROM settings
        WHERE setting_key = ? AND (school_id = ? OR school_id IS NULL)
        ORDER BY school_id DESC LIMIT 1
    ");
    $stmt->execute([$key, $schoolId]);
    $row = $stmt->fetch();
    return $row ? $row['setting_value'] : $default;
}

function setSetting($key, $value, ?int $schoolId = null): bool {
    $schoolId = $schoolId ?? getCurrentSchoolId();
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO settings (setting_key, setting_value, school_id)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    return $stmt->execute([$key, $value, $schoolId]);
}
```

### 3c) `getActiveEditionId()` anpassen

```php
// NEU: Edition ist schulspezifisch
function getActiveEditionId(?int $schoolId = null): int {
    $schoolId = $schoolId ?? getCurrentSchoolId();
    $cacheKey = 'active_edition_' . $schoolId;
    
    if (isset($_SESSION[$cacheKey])) {
        return (int)$_SESSION[$cacheKey];
    }
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM messe_editions WHERE status = 'active' AND school_id = ? LIMIT 1");
        $stmt->execute([$schoolId]);
        $row = $stmt->fetch();
        if ($row) {
            $_SESSION[$cacheKey] = (int)$row['id'];
            return (int)$row['id'];
        }
    } catch (Exception $e) { }
    return 0; // Kein Fallback auf 1! 0 = keine Edition aktiv.
}
```

### 3d) `requireAdmin()` erweitern

```php
// NEU: requireSchoolAdminOrAdmin() — für Seiten die Schul-Admin-Level brauchen
function requireSchoolAdminOrAdmin() {
    requireLogin();
    if (!isSchoolAdminOrAdmin()) {
        header('Location: ' . schoolUrl('index.php'));
        exit;
    }
}

// Bestehende requireAdmin() bleibt für rein globale Admin-Seiten (z.B. Schul-Verwaltung)
```

### 3e) Login-Hilfsfunktion für Aussteller

```php
/**
 * Lädt alle Aussteller-IDs für einen Exhibitor-User.
 */
function getExhibitorIdsForUser(int $userId): array {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT exhibitor_id FROM exhibitor_users WHERE user_id = ?");
        $stmt->execute([$userId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    } catch (Exception $e) { return []; }
}
```

---

## 4. `login.php` — Anpassungen

### 4a) Zwei Login-Kontexte

```
/login.php                → Globaler Login (für admin + exhibitor)
/{schul-slug}/login.php   → Schul-Login (für student, teacher, orga, school_admin)
```

**Logik:**

```php
$schoolSlug = $_GET['school_slug'] ?? null;
$school = null;

if ($schoolSlug) {
    // Schul-Kontext: Nur User dieser Schule dürfen sich einloggen
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM schools WHERE slug = ? AND is_active = 1");
    $stmt->execute([$schoolSlug]);
    $school = $stmt->fetch();
    if (!$school) {
        die('Schule nicht gefunden.');
    }
}

// Bei POST: Login prüfen
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($school) {
        // Schul-Login: User muss zur Schule gehören ODER admin/exhibitor sein
        $stmt = $db->prepare("
            SELECT id, username, password, firstname, lastname, role, school_id, edition_id
            FROM users
            WHERE username = ?
              AND (school_id = ? OR role IN ('admin', 'exhibitor'))
        ");
        $stmt->execute([$username, $school['id']]);
    } else {
        // Globaler Login: Nur admin und exhibitor
        $stmt = $db->prepare("
            SELECT id, username, password, firstname, lastname, role, school_id, edition_id
            FROM users
            WHERE username = ? AND role IN ('admin', 'exhibitor')
        ");
        $stmt->execute([$username]);
    }
    
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['school_id'] = $user['school_id'];
        
        // Weiterleitung basierend auf Rolle
        if ($user['role'] === 'admin') {
            // Admin: Zur Schulauswahl oder direkt zur Schule wenn im Schul-Kontext
            if ($school) {
                header('Location: ' . BASE_URL . $school['slug'] . '/index.php?page=dashboard');
            } else {
                header('Location: ' . BASE_URL . 'index.php?page=admin-schools');
            }
        } elseif ($user['role'] === 'exhibitor') {
            // Aussteller: Zum Aussteller-Dashboard
            header('Location: ' . BASE_URL . 'index.php?page=exhibitor-dashboard');
        } elseif ($user['role'] === 'school_admin') {
            // Schul-Admin: Zur eigenen Schule
            $slug = $school ? $school['slug'] : getSchoolSlugById($user['school_id']);
            header('Location: ' . BASE_URL . $slug . '/index.php?page=admin-dashboard');
        } else {
            // Student/Teacher/Orga: Zur Schule
            $slug = $school ? $school['slug'] : getSchoolSlugById($user['school_id']);
            header('Location: ' . BASE_URL . $slug . '/index.php?page=dashboard');
        }
        exit;
    }
}
```

### 4b) Login-Formular anpassen

Auf der Login-Seite den Schulnamen anzeigen wenn im Schul-Kontext:

```php
<?php if ($school): ?>
    <div class="school-badge">
        <?php if ($school['logo']): ?>
            <img src="<?= BASE_URL ?>uploads/<?= htmlspecialchars($school['logo']) ?>" alt="" class="h-12">
        <?php endif; ?>
        <h2><?= htmlspecialchars($school['name']) ?></h2>
    </div>
<?php endif; ?>
```

---

## 5. `index.php` — Routing erweitern

### 5a) Schulkontext laden

Am Anfang von `index.php`, nach den bestehenden Requires:

```php
// Schulkontext aus URL laden
$currentSchool = getCurrentSchool();
$schoolSlug = getCurrentSchoolSlug();

// Seiten die keinen Schulkontext brauchen (globale Admin/Aussteller-Seiten)
$globalPages = ['admin-schools', 'exhibitor-dashboard', 'exhibitor-slots', 'exhibitor-profile', 'exhibitor-equipment', 'exhibitor-documents'];

if (in_array($currentPage, $globalPages)) {
    // Diese Seiten brauchen keinen Schul-Slug
} elseif (!$currentSchool && !isAdmin() && !isExhibitor()) {
    // Kein Schulkontext und kein globaler User → zur Schulauswahl
    header('Location: ' . BASE_URL);
    exit;
}

// Edition-ID jetzt schulspezifisch laden
$activeEditionId = $currentSchool ? getActiveEditionId((int)$currentSchool['id']) : 0;
```

### 5b) Navigation erweitern

Im Navigations-Menü Schul-Kontext-Switch für Admins einbauen:

```php
<?php if (isAdmin()): ?>
    <div class="school-switcher">
        <select onchange="window.location.href=this.value" class="...">
            <?php foreach (getSchoolsForUser($_SESSION['user_id'], $_SESSION['role']) as $s): ?>
                <option value="<?= BASE_URL . $s['slug'] . '/index.php?page=' . $currentPage ?>"
                    <?= ($s['id'] == getCurrentSchoolId()) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($s['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
<?php endif; ?>
```

### 5c) Neue Seiten im Router registrieren

Im bestehenden `switch`/`if`-Block für `$currentPage`:

```php
// Globale Admin-Seiten (nur admin)
case 'admin-schools':
    requireAdmin();
    include 'pages/admin-schools.php';
    break;

// Schulbezogene Admin-Seiten (admin oder school_admin)
// Bestehende admin-* Seiten: requireAdmin() → requireSchoolAdminOrAdmin()
case 'admin-dashboard':
    requireSchoolAdminOrAdmin();
    include 'pages/admin-dashboard.php';
    break;

// Aussteller-Seiten
case 'exhibitor-dashboard':
    requireLogin();
    if (!isExhibitor() && !isAdmin()) { header('Location: ' . BASE_URL); exit; }
    include 'pages/exhibitor-dashboard.php';
    break;

case 'exhibitor-slots':
    requireLogin();
    if (!isExhibitor() && !isAdmin()) { header('Location: ' . BASE_URL); exit; }
    include 'pages/exhibitor-slots.php';
    break;

case 'exhibitor-profile':
    requireLogin();
    if (!isExhibitor() && !isAdmin()) { header('Location: ' . BASE_URL); exit; }
    include 'pages/exhibitor-profile.php';
    break;

case 'exhibitor-equipment':
    requireLogin();
    if (!isExhibitor() && !isAdmin()) { header('Location: ' . BASE_URL); exit; }
    include 'pages/exhibitor-equipment.php';
    break;

case 'exhibitor-documents':
    requireLogin();
    if (!isExhibitor() && !isAdmin()) { header('Location: ' . BASE_URL); exit; }
    include 'pages/exhibitor-documents.php';
    break;

// Schuladmin: Ausstattung pflegen
case 'admin-equipment':
    requireSchoolAdminOrAdmin();
    include 'pages/admin-equipment.php';
    break;
```

---

## 6. `schools.php` — Landingpage (NEU)

Öffentliche Seite ohne Login. Zeigt alle aktiven Schulen als Karten oder Dropdown:

```php
<?php
require_once 'config.php';
require_once 'functions.php';

$db = getDB();
$stmt = $db->query("SELECT id, name, slug, logo, address FROM schools WHERE is_active = 1 ORDER BY name");
$schools = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Berufsmesse — Schule wählen</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/design-system.css">
</head>
<body class="bg-gradient-to-br from-slate-50 to-blue-50 min-h-screen flex items-center justify-center">
    <div class="max-w-2xl w-full mx-auto p-8">
        <h1 class="text-3xl font-bold text-center mb-8">Berufsmesse</h1>
        <p class="text-center text-gray-600 mb-8">Wähle deine Schule, um fortzufahren:</p>
        
        <div class="grid gap-4">
            <?php foreach ($schools as $school): ?>
                <a href="<?= BASE_URL . htmlspecialchars($school['slug']) ?>/"
                   class="card p-6 hover:shadow-lg transition-all duration-300 flex items-center gap-4">
                    <?php if ($school['logo']): ?>
                        <img src="uploads/<?= htmlspecialchars($school['logo']) ?>" alt="" class="h-12 w-12 object-contain">
                    <?php else: ?>
                        <div class="h-12 w-12 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-school text-blue-600"></i>
                        </div>
                    <?php endif; ?>
                    <div>
                        <h2 class="font-semibold text-lg"><?= htmlspecialchars($school['name']) ?></h2>
                        <?php if ($school['address']): ?>
                            <p class="text-sm text-gray-500"><?= htmlspecialchars($school['address']) ?></p>
                        <?php endif; ?>
                    </div>
                    <i class="fas fa-chevron-right ml-auto text-gray-400"></i>
                </a>
            <?php endforeach; ?>
        </div>
        
        <!-- Link für Admins/Aussteller zum globalen Login -->
        <div class="text-center mt-8">
            <a href="<?= BASE_URL ?>login.php" class="text-sm text-gray-500 hover:text-gray-700">
                Admin / Aussteller Login →
            </a>
        </div>
    </div>
</body>
</html>
```

---

## 7. `pages/admin-schools.php` — Schulverwaltung (NEU)

Nur für globale Admins (`requireAdmin()`). CRUD für Schulen:

**Funktionalität:**
- Liste aller Schulen mit Status (aktiv/inaktiv)
- Neue Schule erstellen: Name, Slug (auto-generiert aus Name), Logo, Adresse, Kontakt
- Schule bearbeiten
- Schule deaktivieren (nicht löschen! Soft-Delete via `is_active`)
- Schul-Admin zuweisen: User mit Rolle `school_admin` der Schule zuordnen
- Direkt-Link zur Schule: Button öffnet `/{slug}/index.php?page=admin-dashboard`

**Slug-Generierung:**

```php
function generateSchoolSlug(string $name): string {
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    
    // Eindeutigkeit prüfen
    $db = getDB();
    $baseSlug = $slug;
    $counter = 1;
    while (true) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM schools WHERE slug = ?");
        $stmt->execute([$slug]);
        if ($stmt->fetchColumn() == 0) break;
        $slug = $baseSlug . '-' . $counter++;
    }
    return $slug;
}
```

**UI-Muster:** Wie die bestehende `admin-editions.php` — Tabelle mit Aktions-Buttons, Modal für Erstellen/Bearbeiten. Tailwind + Design-System-Klassen verwenden.

---

## 8. Aussteller-Seiten (NEU)

### 8a) `pages/exhibitor-dashboard.php`

Dashboard für eingeloggte Aussteller. Zeigt:
- Begrüssung mit Firmenname(n)
- Karten pro Schule, bei der man ausstellt (aus `exhibitor_users` → `exhibitors` → `messe_editions` → `schools`)
- Pro Schulkarte: Nächster Termin, Anmeldezahlen-Übersicht, Quick-Links

```php
<?php
// Alle Aussteller dieses Users laden
$exhibitorIds = getExhibitorIdsForUser($_SESSION['user_id']);
if (empty($exhibitorIds)) {
    echo '<div class="alert alert-warning">Kein Aussteller zugeordnet.</div>';
    return;
}

$placeholders = implode(',', array_fill(0, count($exhibitorIds), '?'));

// Schulen mit Editionen laden
$stmt = $db->prepare("
    SELECT DISTINCT s.id as school_id, s.name as school_name, s.slug, s.logo,
           me.id as edition_id, me.name as edition_name, me.event_date, me.status,
           e.id as exhibitor_id, e.name as exhibitor_name
    FROM exhibitor_users eu
    JOIN exhibitors e ON eu.exhibitor_id = e.id
    JOIN messe_editions me ON e.edition_id = me.id
    JOIN schools s ON me.school_id = s.id
    WHERE eu.user_id = ? AND e.active = 1 AND s.is_active = 1
    ORDER BY me.event_date DESC
");
$stmt->execute([$_SESSION['user_id']]);
$schoolExhibitors = $stmt->fetchAll();
// Gruppieren nach Schule für die Darstellung
```

### 8b) `pages/exhibitor-slots.php`

Zeigt pro Aussteller (Unternehmen) und Schule/Edition die Slot-Belegung:

```php
// Parameter: exhibitor_id, edition_id (aus URL)
$exhibitorId = (int)($_GET['exhibitor_id'] ?? 0);
$editionId   = (int)($_GET['edition_id'] ?? 0);

// Berechtigung prüfen: User muss diesem Aussteller zugeordnet sein
$stmt = $db->prepare("SELECT 1 FROM exhibitor_users WHERE user_id = ? AND exhibitor_id = ?");
$stmt->execute([$_SESSION['user_id'], $exhibitorId]);
if (!$stmt->fetch()) { die('Kein Zugang.'); }

// Slots laden mit Anmeldezahlen
$stmt = $db->prepare("
    SELECT t.id, t.slot_number, t.slot_name, t.start_time, t.end_time, t.is_break,
           COUNT(r.id) as total_registrations,
           GROUP_CONCAT(DISTINCT u.class ORDER BY u.class SEPARATOR ', ') as classes
    FROM timeslots t
    LEFT JOIN registrations r ON r.timeslot_id = t.id AND r.exhibitor_id = ?
    LEFT JOIN users u ON r.user_id = u.id
    WHERE t.edition_id = ?
    GROUP BY t.id
    ORDER BY t.slot_number
");
$stmt->execute([$exhibitorId, $editionId]);
$slots = $stmt->fetchAll();

// Pro Slot: Aufschlüsselung nach Klasse
foreach ($slots as &$slot) {
    $stmtClass = $db->prepare("
        SELECT u.class, COUNT(*) as count
        FROM registrations r
        JOIN users u ON r.user_id = u.id
        WHERE r.exhibitor_id = ? AND r.timeslot_id = ?
        GROUP BY u.class ORDER BY u.class
    ");
    $stmtClass->execute([$exhibitorId, $slot['id']]);
    $slot['class_breakdown'] = $stmtClass->fetchAll();
}
```

**Darstellung:** Tabelle oder Karten pro Slot. Pro Slot:
- Zeitfenster + Name
- Gesamtzahl Anmeldungen (gross)
- Aufklappbare Klassen-Aufschlüsselung (Klasse → Anzahl)
- Kapazitäts-Balken (wenn Raumkapazität bekannt)

### 8c) `pages/exhibitor-profile.php`

Aussteller kann eigenes Profil bearbeiten:
- Name, Beschreibung, Kurzbeschreibung
- Kategorie, Website, Kontaktdaten
- Logo hochladen
- Jobs, Features, Angebotstypen (bestehende Felder in `exhibitors`)

**Wichtig:** Nur Felder bearbeiten die zum eigenen Aussteller gehören. CSRF-Token nutzen.

### 8d) `pages/exhibitor-equipment.php`

Ausstattungsanfragen pro Schule/Edition stellen:

```php
// Verfügbare Optionen der Schule laden
$stmt = $db->prepare("
    SELECT * FROM equipment_options
    WHERE school_id = ? AND is_active = 1
    ORDER BY sort_order, name
");
$stmt->execute([$schoolId]);
$options = $stmt->fetchAll();

// Bestehende Anfragen laden
$stmt = $db->prepare("
    SELECT er.*, eo.name as option_name
    FROM exhibitor_equipment_requests er
    LEFT JOIN equipment_options eo ON er.equipment_option_id = eo.id
    WHERE er.exhibitor_id = ? AND er.edition_id = ?
");
$stmt->execute([$exhibitorId, $editionId]);
$existingRequests = $stmt->fetchAll();
```

**UI:**
- Checkboxen für vordefinierte Optionen (aus `equipment_options`)
- Mengenfeld pro Checkbox (z.B. "2x Tische")
- Freitext-Feld für zusätzliche Wünsche
- Speichern-Button → `api/exhibitor-equipment.php`
- Status-Anzeige: Ausstehend / Genehmigt / Abgelehnt (vom Admin gesetzt)

### 8e) `pages/exhibitor-documents.php`

Eigene Dokumente hochladen und verwalten. Nutze die bestehende `uploadFile()`-Funktion
und `exhibitor_documents`-Tabelle:

```php
// Dokumente des Ausstellers laden
$stmt = $db->prepare("
    SELECT * FROM exhibitor_documents WHERE exhibitor_id = ? ORDER BY created_at DESC
");
$stmt->execute([$exhibitorId]);
$documents = $stmt->fetchAll();
```

Upload-Formular, Liste mit Download/Löschen-Buttons.

---

## 9. `pages/admin-equipment.php` — Ausstattungsoptionen verwalten (NEU)

Für Schul-Admins und globale Admins. CRUD für `equipment_options` der aktuellen Schule:

- Liste der Optionen (Name, Beschreibung, Reihenfolge)
- Neue Option hinzufügen
- Bearbeiten, Deaktivieren
- Drag-and-Drop Sortierung (optional, `sort_order`)
- Übersicht aller Ausstattungsanfragen der Aussteller mit Genehmigungs-Workflow

**UI-Muster:** Wie `admin-rooms.php` — Tabelle + Modal.

---

## 10. QR-Code Kamera-Scanner

### 10a) JavaScript-Bibliothek

Verwende `html5-qrcode` (CDN):

```html
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
```

### 10b) `assets/js/qr-camera.js` (NEU)

```javascript
/**
 * QR-Code Kamera-Scanner
 * Kann auf jeder Seite via initQrScanner() gestartet werden.
 */
class QrCameraScanner {
    constructor(containerId, onScanCallback) {
        this.containerId = containerId;
        this.onScan = onScanCallback;
        this.scanner = null;
        this.isScanning = false;
    }
    
    async start() {
        if (this.isScanning) return;
        
        this.scanner = new Html5Qrcode(this.containerId);
        
        try {
            await this.scanner.start(
                { facingMode: "environment" }, // Rückkamera bevorzugen
                {
                    fps: 10,
                    qrbox: { width: 250, height: 250 },
                    aspectRatio: 1.0
                },
                (decodedText) => {
                    // Vibration-Feedback wenn verfügbar
                    if (navigator.vibrate) navigator.vibrate(200);
                    this.onScan(decodedText);
                },
                (errorMessage) => {
                    // Ignorieren — kontinuierliches Scannen
                }
            );
            this.isScanning = true;
        } catch (err) {
            console.error('Kamera-Zugriff fehlgeschlagen:', err);
            alert('Kamera-Zugriff nicht möglich. Bitte Berechtigungen prüfen.');
        }
    }
    
    async stop() {
        if (this.scanner && this.isScanning) {
            await this.scanner.stop();
            this.isScanning = false;
        }
    }
    
    toggle() {
        if (this.isScanning) {
            this.stop();
        } else {
            this.start();
        }
    }
}
```

### 10c) `pages/qr-checkin.php` — Kamera-Modus einbauen

In die bestehende QR-Checkin-Seite einen Kamera-Toggle einbauen:

```php
<!-- Kamera-Scanner Bereich -->
<div class="card p-6 mb-6">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-lg font-bold">QR-Code Scanner</h2>
        <button id="toggleCamera" onclick="toggleQrCamera()" class="btn btn-primary btn-sm">
            <i class="fas fa-camera mr-2"></i>Kamera starten
        </button>
    </div>
    <div id="qr-reader" class="rounded-xl overflow-hidden" style="display:none;"></div>
    <div id="scan-result" class="mt-4" style="display:none;"></div>
</div>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/qr-camera.js"></script>
<script>
let qrScanner = null;

function toggleQrCamera() {
    const readerDiv = document.getElementById('qr-reader');
    const btn = document.getElementById('toggleCamera');
    
    if (!qrScanner) {
        qrScanner = new QrCameraScanner('qr-reader', handleQrScan);
    }
    
    if (qrScanner.isScanning) {
        qrScanner.stop();
        readerDiv.style.display = 'none';
        btn.innerHTML = '<i class="fas fa-camera mr-2"></i>Kamera starten';
    } else {
        readerDiv.style.display = 'block';
        qrScanner.start();
        btn.innerHTML = '<i class="fas fa-stop mr-2"></i>Kamera stoppen';
    }
}

function handleQrScan(decodedText) {
    // Prüfe ob es eine gültige Checkin-URL oder Token ist
    let token = decodedText;
    
    // Falls es eine volle URL ist, Token extrahieren
    if (decodedText.includes('token=')) {
        const url = new URL(decodedText);
        token = url.searchParams.get('token');
    }
    
    if (token) {
        // Scanner pausieren während Checkin verarbeitet wird
        qrScanner.stop();
        
        // AJAX-Checkin durchführen
        fetch('<?= schoolUrl("api/qr-checkin.php") ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': '<?= generateCsrfToken() ?>'
            },
            body: JSON.stringify({ token: token })
        })
        .then(r => r.json())
        .then(data => {
            const resultDiv = document.getElementById('scan-result');
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = data.success
                ? `<div class="alert alert-success">${data.message}</div>`
                : `<div class="alert alert-error">${data.message}</div>`;
            
            // Nach 3 Sekunden Scanner wieder starten
            setTimeout(() => {
                resultDiv.style.display = 'none';
                qrScanner.start();
                document.getElementById('qr-reader').style.display = 'block';
                document.getElementById('toggleCamera').innerHTML = '<i class="fas fa-stop mr-2"></i>Kamera stoppen';
            }, 3000);
        })
        .catch(err => {
            alert('Fehler beim Checkin: ' + err.message);
            qrScanner.start();
        });
    }
}
</script>
```

### 10d) `config.php` — Kamera-Berechtigung erlauben

Die bestehende Permission-Policy blockiert die Kamera. Ändern:

```php
// ALT:
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

// NEU:
header('Permissions-Policy: camera=(self), microphone=(), geolocation=()');
```

---

## 11. Darkmode

### 11a) `assets/js/darkmode.js` (NEU)

```javascript
/**
 * Darkmode mit sanftem Überblenden (300ms Transition)
 */
(function() {
    const STORAGE_KEY = 'berufsmesse-darkmode';
    
    // Beim Laden sofort anwenden (vor Rendering, kein Flash)
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved === 'true') {
        document.documentElement.classList.add('dark');
    }
    
    window.toggleDarkmode = function() {
        const html = document.documentElement;
        
        // Transition aktivieren
        html.style.transition = 'background-color 300ms ease, color 300ms ease';
        document.body.style.transition = 'background-color 300ms ease, color 300ms ease';
        
        html.classList.toggle('dark');
        const isDark = html.classList.contains('dark');
        localStorage.setItem(STORAGE_KEY, isDark);
        
        // Transition nach Animation entfernen
        setTimeout(() => {
            html.style.transition = '';
            document.body.style.transition = '';
        }, 350);
    };
})();
```

### 11b) `assets/css/design-system.css` — Dark-Theme-Variablen

Am Ende der bestehenden `:root`-Variablen einen `html.dark`-Block hinzufügen:

```css
/* ─── DARK MODE ────────────────────────────────────────── */

/* Transition für sanftes Überblenden */
html.dark *,
html.dark *::before,
html.dark *::after {
    transition: background-color 300ms ease, color 300ms ease, border-color 300ms ease, box-shadow 300ms ease;
}

html.dark {
    --color-bg-primary: #0f172a;
    --color-bg-secondary: #1e293b;
    --color-bg-tertiary: #334155;
    --color-text-primary: #f1f5f9;
    --color-text-secondary: #94a3b8;
    --color-text-muted: #64748b;
    --color-border: #334155;
    --color-card-bg: #1e293b;
    --color-input-bg: #1e293b;
    --color-input-border: #475569;
    
    /* Pastell-Farben abgedunkelt */
    --color-mint: #1a3a2e;
    --color-lavender: #2d2444;
    --color-peach: #3d2529;
    --color-sky: #1a2d42;
    --color-butter: #3d3520;
    --color-rose: #3d2033;
    
    color-scheme: dark;
}

html.dark body {
    background-color: var(--color-bg-primary);
    color: var(--color-text-primary);
}

html.dark .card,
html.dark .modal-content {
    background-color: var(--color-card-bg);
    border-color: var(--color-border);
}

html.dark input, html.dark select, html.dark textarea {
    background-color: var(--color-input-bg);
    border-color: var(--color-input-border);
    color: var(--color-text-primary);
}

html.dark .sidebar {
    background-color: var(--color-bg-secondary);
}

html.dark .nav-header,
html.dark .top-bar {
    background-color: var(--color-bg-secondary);
    border-color: var(--color-border);
}

html.dark .text-gray-800 { color: var(--color-text-primary); }
html.dark .text-gray-600, html.dark .text-gray-500 { color: var(--color-text-secondary); }
html.dark .text-gray-400 { color: var(--color-text-muted); }
html.dark .bg-white { background-color: var(--color-card-bg); }
html.dark .bg-gray-50, html.dark .bg-gray-100 { background-color: var(--color-bg-secondary); }
html.dark .border-gray-200, html.dark .border-gray-300 { border-color: var(--color-border); }

/* Tailwind Overrides für Dark */
html.dark .bg-gradient-to-br { background-image: none; background-color: var(--color-bg-primary); }

/* Alert-Farben im Dark Mode */
html.dark .alert-success { background-color: #064e3b; border-color: #065f46; color: #6ee7b7; }
html.dark .alert-warning { background-color: #451a03; border-color: #78350f; color: #fcd34d; }
html.dark .alert-error { background-color: #450a0a; border-color: #7f1d1d; color: #fca5a5; }
html.dark .alert-info { background-color: #0c2340; border-color: #1e3a5f; color: #93c5fd; }
```

### 11c) Toggle-Button in der Navigation

In der oberen Navigation (rechts), VOR dem Benutzer-Menü:

```html
<button onclick="toggleDarkmode()" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" title="Darkmode umschalten">
    <!-- Sonne (sichtbar im Dark Mode) -->
    <svg class="w-5 h-5 hidden dark:block text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"/>
    </svg>
    <!-- Mond (sichtbar im Light Mode) -->
    <svg class="w-5 h-5 block dark:hidden text-gray-600" fill="currentColor" viewBox="0 0 20 20">
        <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/>
    </svg>
</button>
```

**WICHTIG:** Das `darkmode.js`-Script muss im `<head>` geladen werden (vor dem Body-Rendering), damit kein "Flash of Light Mode" entsteht:

```html
<script src="<?= BASE_URL ?>assets/js/darkmode.js"></script>
```

---

## 12. Bugfix: Tagesplan Mobile (dashboard.php)

### Problem
Der Tagesplan im Dashboard schneidet auf Mobile-Geräten den Ausstellernamen ab (CSS `truncate`) und zeigt nicht den vollen Namen an. Das Layout ist zu breit für kleine Screens.

### Lösung: Kompakteres Mobile-Layout

In `pages/dashboard.php` die Timeline-Items responsive machen. Die bestehenden CSS-Klassen anpassen:

```php
<!-- ALT: -->
<div class="timeline-item flex items-center gap-4 p-4 rounded-xl border ...">
    <div class="text-center min-w-[60px]">
        <span class="text-sm font-bold ..."><?php echo $slot['time']; ?></span>
        <span class="block text-xs ..."><?php echo $slot['end']; ?></span>
    </div>
    <div class="w-10 h-10 rounded-lg ... flex items-center justify-center flex-shrink-0 ...">
        <i class="fas ..."></i>
    </div>
    <div class="flex-1 min-w-0">
        <h4 class="font-semibold text-gray-800 truncate">...</h4>
        <p class="text-xs text-gray-500 truncate">...</p>
    </div>
    <div class="text-right">
        <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium ...">
            Slot <?php echo $slot['slot_number']; ?>
        </span>
    </div>
</div>

<!-- NEU: -->
<div class="timeline-item flex items-center gap-2 sm:gap-4 p-3 sm:p-4 rounded-xl border ...">
    <!-- Zeit: kompakter auf Mobile -->
    <div class="text-center min-w-[45px] sm:min-w-[60px]">
        <span class="text-xs sm:text-sm font-bold ..."><?php echo $slot['time']; ?></span>
        <span class="block text-[10px] sm:text-xs ..."><?php echo $slot['end']; ?></span>
    </div>
    <!-- Icon: auf Mobile ausblenden -->
    <div class="w-10 h-10 rounded-lg ... items-center justify-center flex-shrink-0 ... hidden sm:flex">
        <i class="fas ..."></i>
    </div>
    <!-- Content: kein truncate, kleinere Schrift auf Mobile -->
    <div class="flex-1 min-w-0">
        <h4 class="font-semibold text-gray-800 text-xs sm:text-sm leading-tight break-words"><?php echo htmlspecialchars(html_entity_decode($reg['exhibitor_name'], ENT_QUOTES | ENT_HTML5, 'UTF-8')); ?></h4>
        <p class="text-[10px] sm:text-xs text-gray-500">
            <i class="fas fa-map-marker-alt mr-1"></i>
            <?php echo htmlspecialchars($reg['room_number'] ?? 'Raum folgt'); ?>
        </p>
    </div>
    <!-- Slot-Badge: nur Nummer auf Mobile -->
    <div class="text-right flex-shrink-0">
        <span class="inline-flex items-center px-1.5 sm:px-2 py-0.5 sm:py-1 rounded-md text-[10px] sm:text-xs font-medium ...">
            <span class="sm:hidden"><?php echo $slot['slot_number']; ?></span>
            <span class="hidden sm:inline">Slot <?php echo $slot['slot_number']; ?></span>
        </span>
    </div>
</div>
```

**Zusammenfassung der Mobile-Änderungen:**
1. `truncate` entfernen → `break-words` und `leading-tight` verwenden
2. Icon-Spalte auf Mobile ausblenden (`hidden sm:flex`)
3. Kleinere Schriftgrößen auf Mobile (`text-xs sm:text-sm`)
4. Engere Gaps und Paddings (`gap-2 sm:gap-4`, `p-3 sm:p-4`)
5. Slot-Badge zeigt nur Nummer statt "Slot X" (`sm:hidden` / `hidden sm:inline`)

**Pausen-Items ebenfalls anpassen** (gleiche Responsive-Logik).

---

## 13. Alle Admin-Seiten: `requireAdmin()` → `requireSchoolAdminOrAdmin()`

Folgende Seiten brauchen NICHT zwingend globale Admin-Rechte, sondern können auch von Schul-Admins verwaltet werden. In diesen Dateien `requireAdmin()` durch `requireSchoolAdminOrAdmin()` ersetzen:

- `pages/admin-dashboard.php`
- `pages/admin-exhibitors.php`
- `pages/admin-users.php`
- `pages/admin-rooms.php`
- `pages/admin-room-capacities.php`
- `pages/admin-registrations.php`
- `pages/admin-attendance.php`
- `pages/admin-qr-codes.php`
- `pages/admin-print.php`
- `pages/admin-print-export.php`
- `pages/admin-settings.php`
- `pages/admin-editions.php`
- `pages/admin-announcements.php`

**Weiterhin nur für globale Admins** (`requireAdmin()`):
- `pages/admin-schools.php` (NEU)
- `pages/admin-audit-logs.php`
- `pages/admin-permissions.php`

---

## 14. Alle API-Endpunkte: Schulkontext hinzufügen

Jeder API-Endpunkt unter `api/` muss:
1. Den Schulkontext laden: `$school = getCurrentSchool();`
2. Die `edition_id` schulspezifisch laden: `$editionId = getActiveEditionId($school['id']);`
3. Zugriffsrechte prüfen: `hasSchoolAccess()`

Beispiel-Anpassung für `api/qr-checkin.php`:

```php
// Am Anfang hinzufügen:
$school = getCurrentSchool();
if ($school) {
    $activeEditionId = getActiveEditionId((int)$school['id']);
}
// Bestehende Queries: edition_id Parameter verwenden wie bisher
```

---

## 15. Navigations-Menü: Aussteller-Einträge

Im Navigations-Template (in `index.php`) für Aussteller-Rolle eigene Menüeinträge:

```php
<?php if (isExhibitor()): ?>
    <a href="<?= BASE_URL ?>index.php?page=exhibitor-dashboard" class="nav-link">
        <i class="fas fa-tachometer-alt"></i> Dashboard
    </a>
    <a href="<?= BASE_URL ?>index.php?page=exhibitor-profile" class="nav-link">
        <i class="fas fa-building"></i> Unternehmensprofil
    </a>
    <a href="<?= BASE_URL ?>index.php?page=exhibitor-documents" class="nav-link">
        <i class="fas fa-file-alt"></i> Dokumente
    </a>
<?php endif; ?>
```

---

## 16. Implementierungsreihenfolge

Die Features haben Abhängigkeiten. Implementiere in dieser Reihenfolge:

### Phase 1: Datenbank & Grundgerüst
1. `update_schema.sql` — Alle neuen Tabellen und Migrationen (Abschnitt 1)
2. `.htaccess` — URL-Rewriting (Abschnitt 2)
3. `functions.php` — Neue School-Funktionen (Abschnitt 3)
4. `config.php` — Kamera-Permission-Policy anpassen (Abschnitt 10d)

### Phase 2: Multi-Schulen-Kern
5. `schools.php` — Landingpage (Abschnitt 6)
6. `login.php` — Zwei Login-Kontexte (Abschnitt 4)
7. `index.php` — Routing und Schulkontext (Abschnitt 5)
8. `pages/admin-schools.php` — Schulverwaltung (Abschnitt 7)
9. Alle Admin-Seiten: `requireAdmin()` → `requireSchoolAdminOrAdmin()` (Abschnitt 13)
10. Alle API-Endpunkte: Schulkontext (Abschnitt 14)

### Phase 3: Aussteller-Accounts
11. `pages/exhibitor-dashboard.php` (Abschnitt 8a)
12. `pages/exhibitor-slots.php` (Abschnitt 8b)
13. `pages/exhibitor-profile.php` (Abschnitt 8c)
14. `pages/exhibitor-equipment.php` (Abschnitt 8d)
15. `pages/exhibitor-documents.php` (Abschnitt 8e)
16. `pages/admin-equipment.php` (Abschnitt 9)
17. `api/exhibitor-equipment.php` — API für Ausstattungsanfragen
18. Navigation: Aussteller-Einträge (Abschnitt 15)

### Phase 4: Darkmode
19. `assets/js/darkmode.js` (Abschnitt 11a)
20. `assets/css/design-system.css` — Dark-Variablen (Abschnitt 11b)
21. Toggle-Button in Navigation (Abschnitt 11c)

### Phase 5: QR-Kamera + Bugfix
22. `assets/js/qr-camera.js` (Abschnitt 10b)
23. `pages/qr-checkin.php` — Kamera-Modus (Abschnitt 10c)
24. `pages/dashboard.php` — Tagesplan Mobile-Fix (Abschnitt 12)

---

## 17. Wichtige Hinweise für die Implementierung

### URL-Generierung
**ALLE** internen Links müssen die `schoolUrl()`-Funktion verwenden statt hartcodierter Pfade:

```php
// ALT:
<a href="<?= BASE_URL ?>index.php?page=exhibitors">
// NEU:
<a href="<?= schoolUrl('index.php?page=exhibitors') ?>">
```

Das gilt für: Navigation, Formulare (`action="..."`), Redirects (`header('Location: ...')`), AJAX-URLs in JavaScript.

### Session-Management
Nach Login müssen folgende Session-Variablen gesetzt werden:

```php
$_SESSION['user_id']   = $user['id'];
$_SESSION['username']  = $user['username'];
$_SESSION['firstname'] = $user['firstname'];
$_SESSION['lastname']  = $user['lastname'];
$_SESSION['role']      = $user['role'];
$_SESSION['school_id'] = $user['school_id'];  // NEU: NULL für admin/exhibitor
$_SESSION['edition_id']= $user['edition_id']; // Beibehalten für Abwärtskompatibilität
```

### Rückwärtskompatibilität
- Die bestehende `edition_id`-Logik bleibt erhalten. `school_id` wird ZUSÄTZLICH eingeführt.
- Bestehende Daten werden automatisch der Standardschule (id=1) zugeordnet.
- Wenn nur eine Schule existiert, verhält sich die App wie bisher.

### Aussteller: Erstellung durch Admins
Aussteller-User und ihre Verknüpfungen (`exhibitor_users`) werden von Admins/Schul-Admins in `admin-exhibitors.php` erstellt. Dort einen neuen Abschnitt "Aussteller-Accounts" hinzufügen mit:
- Button "Account erstellen" pro Aussteller
- Formular: Username, Passwort, Vorname, Nachname, E-Mail
- Automatisch: `role = 'exhibitor'`, Eintrag in `exhibitor_users`
- Liste bestehender Accounts mit Löschen-Option

### Sicherheit
- Alle neuen Seiten: CSRF-Token verwenden (`requireCsrf()` bei POST)
- Alle DB-Queries: Prepared Statements (kein String-Concat)
- Schulkontext IMMER serverseitig validieren (nicht nur via URL)
- Aussteller dürfen NUR eigene Daten sehen/bearbeiten (via `exhibitor_users`-Check)
- `school_slug` in URLs: Nur `[a-z0-9-]` erlaubt (Regex-Validierung)

### Design-Konsistenz
- Alle neuen Seiten nutzen das bestehende Design-System (`design-system.css`)
- Card-Layouts, Button-Stile, Alert-Boxen aus bestehenden Seiten kopieren
- Tailwind CDN + bestehende Custom-Klassen verwenden
- Darkmode-Kompatibilität: Keine hartcodierten Farben (`bg-white` etc.) in neuen Elementen, stattdessen CSS-Variablen verwenden wo möglich
