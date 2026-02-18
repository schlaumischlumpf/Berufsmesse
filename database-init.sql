-- ============================================================================
-- Berufsmesse – Vollständige Datenbank-Initialisierung & Migration
-- ============================================================================
-- Diese Datei erstellt alle Tabellen und führt sämtliche Migrationen
-- idempotent aus. Sicher bei wiederholtem Ausführen.
--
-- In Docker wird diese Datei automatisch beim Container-Start ausgeführt.
-- Die Datenbank selbst wird durch Docker Compose (MYSQL_DATABASE) erzeugt.
--
-- Lokale Nutzung (als root):
--   mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS berufsmesse CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
--   mysql -u root -p berufsmesse < database-init.sql
-- ============================================================================

-- ============================================================================
-- 1. Basis-Tabellen (Kern-Schema)
-- ============================================================================

-- Benutzer
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(100) NOT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `password` VARCHAR(255) NOT NULL,
    `firstname` VARCHAR(100) NOT NULL,
    `lastname` VARCHAR(100) NOT NULL,
    `role` VARCHAR(50) NOT NULL DEFAULT 'student',
    `class` VARCHAR(50) DEFAULT NULL,
    `must_change_password` TINYINT(1) DEFAULT 0 COMMENT 'Erzwingt Passwortaenderung beim naechsten Login',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Benutzer-Konten';

-- Räume
CREATE TABLE IF NOT EXISTS `rooms` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `room_number` VARCHAR(50) NOT NULL,
    `room_name` VARCHAR(100) DEFAULT NULL,
    `building` VARCHAR(100) DEFAULT NULL,
    `floor` VARCHAR(20) DEFAULT NULL,
    `capacity` INT DEFAULT NULL,
    `equipment` VARCHAR(500) DEFAULT NULL COMMENT 'Raumausstattung (z.B. Beamer, Smartboard)',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_room_number` (`room_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Raeume fuer die Veranstaltung';

-- Zeitslots
CREATE TABLE IF NOT EXISTS `timeslots` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `slot_number` INT NOT NULL,
    `slot_name` VARCHAR(100) NOT NULL,
    `start_time` TIME DEFAULT NULL,
    `end_time` TIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_slot_number` (`slot_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Zeitslots fuer die Veranstaltung';

-- Standard-Zeitslots einfügen (falls leer)
INSERT IGNORE INTO `timeslots` (`slot_number`, `slot_name`, `start_time`, `end_time`) VALUES
    (1, 'Slot 1', '08:30', '09:15'),
    (2, 'Pause 1', '09:15', '09:30'),
    (3, 'Slot 2', '09:30', '10:15'),
    (4, 'Pause 2', '10:15', '10:30'),
    (5, 'Slot 3', '10:30', '11:15');

-- Aussteller
CREATE TABLE IF NOT EXISTS `exhibitors` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `short_description` TEXT DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `category` VARCHAR(100) DEFAULT NULL,
    `contact_person` VARCHAR(255) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `website` VARCHAR(500) DEFAULT NULL,
    `room_id` INT DEFAULT NULL,
    `active` TINYINT(1) DEFAULT 1,
    `visible_fields` JSON DEFAULT NULL COMMENT 'Definiert welche Felder fuer Schueler sichtbar sind',
    `logo` VARCHAR(255) DEFAULT NULL COMMENT 'Pfad zum Logo-Bild des Ausstellers',
    `offer_types` TEXT DEFAULT NULL COMMENT 'JSON: Angebote (Ausbildung, Studium, Praktikum etc.)',
    `jobs` TEXT DEFAULT NULL COMMENT 'Typische Berufe/Taetigkeiten im Unternehmen',
    `features` TEXT DEFAULT NULL COMMENT 'Besonderheiten des Unternehmens',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_category` (`category`),
    INDEX `idx_active` (`active`),
    FOREIGN KEY (`room_id`) REFERENCES `rooms`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Aussteller / Unternehmen';

-- Anmeldungen
CREATE TABLE IF NOT EXISTS `registrations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `exhibitor_id` INT NOT NULL,
    `timeslot_id` INT DEFAULT NULL,
    `registration_type` VARCHAR(50) DEFAULT 'manual',
    `priority` INT DEFAULT 0 COMMENT 'Prioritaet der Anmeldung (1=hoch, 2=mittel, 3=niedrig, 0=keine)',
    `registered_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_user_exhibitor` (`user_id`, `exhibitor_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_exhibitor_id` (`exhibitor_id`),
    INDEX `idx_timeslot_id` (`timeslot_id`),
    INDEX `idx_user_timeslot` (`user_id`, `timeslot_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`exhibitor_id`) REFERENCES `exhibitors`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`timeslot_id`) REFERENCES `timeslots`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Schueler-Anmeldungen zu Ausstellern';

-- Einstellungen (Key-Value-Store)
CREATE TABLE IF NOT EXISTS `settings` (
    `setting_key` VARCHAR(100) NOT NULL PRIMARY KEY,
    `setting_value` TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='System-Einstellungen (Key-Value)';

-- Standard-Einstellungen einfügen
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
    ('registration_start', '2026-01-01 00:00:00'),
    ('registration_end', '2026-12-31 23:59:59'),
    ('max_registrations_per_student', '3'),
    ('auto_close_registration', '1');

-- Aussteller-Dokumente
CREATE TABLE IF NOT EXISTS `exhibitor_documents` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `exhibitor_id` INT NOT NULL,
    `filename` VARCHAR(255) NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `file_type` VARCHAR(50) NOT NULL,
    `file_size` INT NOT NULL,
    `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_exhibitor_id` (`exhibitor_id`),
    FOREIGN KEY (`exhibitor_id`) REFERENCES `exhibitors`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Hochgeladene Dokumente von Ausstellern';

-- ============================================================================
-- 2. Erweiterte Tabellen
-- ============================================================================

-- Branchen / Kategorien (dynamisch verwaltbar)
CREATE TABLE IF NOT EXISTS `industries` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `sort_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Verwaltbare Branchen/Kategorien fuer Aussteller';

-- Standard-Branchen einfügen
INSERT IGNORE INTO `industries` (`name`, `sort_order`) VALUES
    ('Automobilindustrie', 1),
    ('Handwerk', 2),
    ('Gesundheitswesen', 3),
    ('IT & Software', 4),
    ('Dienstleistung', 5),
    ('Öffentlicher Dienst', 6),
    ('Bildung', 7),
    ('Gastronomie & Hotellerie', 8),
    ('Handel & Verkauf', 9),
    ('Sonstiges', 99);

-- Slot-spezifische Raumkapazitäten
CREATE TABLE IF NOT EXISTS `room_slot_capacities` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `room_id` INT NOT NULL,
    `timeslot_id` INT NOT NULL,
    `capacity` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_room_slot` (`room_id`, `timeslot_id`),
    INDEX `idx_room_id` (`room_id`),
    INDEX `idx_timeslot_id` (`timeslot_id`),
    FOREIGN KEY (`room_id`) REFERENCES `rooms`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`timeslot_id`) REFERENCES `timeslots`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Slot-spezifische Raumkapazitaeten';

-- Granulare Benutzerberechtigungen
CREATE TABLE IF NOT EXISTS `user_permissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `permission` VARCHAR(50) NOT NULL,
    `granted_by` INT DEFAULT NULL COMMENT 'Admin der die Berechtigung erteilt hat',
    `granted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_user_permission` (`user_id`, `permission`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_permission` (`permission`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`granted_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Granulare Benutzerberechtigungen';

-- Audit Logs
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT DEFAULT NULL,
    `username` VARCHAR(100) NOT NULL,
    `action` VARCHAR(255) NOT NULL,
    `details` TEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Audit Logs fuer alle Nutzeraktionen';

-- Anwesenheitsprüfung (QR-Code Check-in)
CREATE TABLE IF NOT EXISTS `attendance` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `exhibitor_id` INT NOT NULL,
    `timeslot_id` INT NOT NULL,
    `qr_token` VARCHAR(64) NOT NULL COMMENT 'Temporaerer QR-Token fuer diesen Slot',
    `checked_in_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_attendance` (`user_id`, `exhibitor_id`, `timeslot_id`),
    INDEX `idx_qr_token` (`qr_token`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`exhibitor_id`) REFERENCES `exhibitors`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`timeslot_id`) REFERENCES `timeslots`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Anwesenheitspruefung per QR-Code';

-- Temporäre QR-Codes
CREATE TABLE IF NOT EXISTS `qr_tokens` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `exhibitor_id` INT NOT NULL,
    `timeslot_id` INT NOT NULL,
    `token` VARCHAR(64) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY `unique_exhibitor_slot_token` (`exhibitor_id`, `timeslot_id`),
    INDEX `idx_token` (`token`),
    FOREIGN KEY (`exhibitor_id`) REFERENCES `exhibitors`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`timeslot_id`) REFERENCES `timeslots`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Temporaere QR-Codes fuer Anwesenheitspruefung';

-- Berechtigungsgruppen
CREATE TABLE IF NOT EXISTS `permission_groups` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_group_name` (`name`),
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Berechtigungsgruppen';

CREATE TABLE IF NOT EXISTS `permission_group_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `group_id` INT NOT NULL,
    `permission` VARCHAR(50) NOT NULL,
    UNIQUE KEY `unique_group_permission` (`group_id`, `permission`),
    FOREIGN KEY (`group_id`) REFERENCES `permission_groups`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Berechtigungen in Gruppen';

-- ============================================================================
-- 3. Migrationen für bestehende Installationen
-- ============================================================================
-- Die folgenden Befehle sind sicher bei wiederholtem Ausführen (idempotent).
-- Sie stellen sicher, dass ältere Installationen auf den aktuellen Stand
-- gebracht werden.

-- Sicherstellen dass users.email existiert
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `email` VARCHAR(255) DEFAULT NULL AFTER `username`;

-- Sicherstellen dass users.must_change_password existiert
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `must_change_password` TINYINT(1) DEFAULT 0 COMMENT 'Erzwingt Passwortaenderung beim naechsten Login';

-- Sicherstellen dass users.role VARCHAR ist (ältere Versionen hatten ENUM)
ALTER TABLE `users` MODIFY `role` VARCHAR(50) NOT NULL DEFAULT 'student';

-- Sicherstellen dass exhibitors erweiterte Felder hat
ALTER TABLE `exhibitors` ADD COLUMN IF NOT EXISTS `visible_fields` JSON DEFAULT NULL COMMENT 'Definiert welche Felder fuer Schueler sichtbar sind';
ALTER TABLE `exhibitors` ADD COLUMN IF NOT EXISTS `logo` VARCHAR(255) DEFAULT NULL COMMENT 'Pfad zum Logo-Bild des Ausstellers';
ALTER TABLE `exhibitors` ADD COLUMN IF NOT EXISTS `offer_types` TEXT DEFAULT NULL COMMENT 'JSON: Angebote (Ausbildung, Studium, Praktikum etc.)';
ALTER TABLE `exhibitors` ADD COLUMN IF NOT EXISTS `jobs` TEXT DEFAULT NULL COMMENT 'Typische Berufe/Taetigkeiten im Unternehmen';
ALTER TABLE `exhibitors` ADD COLUMN IF NOT EXISTS `features` TEXT DEFAULT NULL COMMENT 'Besonderheiten des Unternehmens';

-- Standard-Werte für visible_fields setzen (falls noch leer)
UPDATE `exhibitors`
SET `visible_fields` = JSON_ARRAY('name', 'short_description', 'description', 'category', 'website')
WHERE `visible_fields` IS NULL;

-- Sicherstellen dass rooms.equipment existiert
ALTER TABLE `rooms` ADD COLUMN IF NOT EXISTS `equipment` VARCHAR(500) DEFAULT NULL COMMENT 'Raumausstattung (z.B. Beamer, Smartboard)' AFTER `capacity`;

-- Sicherstellen dass registrations.priority existiert
ALTER TABLE `registrations` ADD COLUMN IF NOT EXISTS `priority` INT DEFAULT 0 COMMENT 'Prioritaet der Anmeldung (1=hoch, 2=mittel, 3=niedrig, 0=keine)';

-- timeslot_id in registrations darf NULL sein (deferred slot assignment)
ALTER TABLE `registrations` MODIFY `timeslot_id` INT DEFAULT NULL;

-- ============================================================================
-- 4. Migration: Alte Berechtigungen → Neue granulare Berechtigungen
-- ============================================================================
-- Alte Berechtigungen in neue granulare Berechtigungen migrieren.
-- Verwendet INSERT IGNORE, sodass bereits migrierte Berechtigungen
-- nicht dupliziert werden.

-- manage_exhibitors → aussteller_*
INSERT IGNORE INTO `user_permissions` (`user_id`, `permission`, `granted_by`)
    SELECT `user_id`, 'aussteller_sehen', `granted_by` FROM `user_permissions` WHERE `permission` = 'manage_exhibitors';
INSERT IGNORE INTO `user_permissions` (`user_id`, `permission`, `granted_by`)
    SELECT `user_id`, 'aussteller_erstellen', `granted_by` FROM `user_permissions` WHERE `permission` = 'manage_exhibitors';
INSERT IGNORE INTO `user_permissions` (`user_id`, `permission`, `granted_by`)
    SELECT `user_id`, 'aussteller_bearbeiten', `granted_by` FROM `user_permissions` WHERE `permission` = 'manage_exhibitors';
INSERT IGNORE INTO `user_permissions` (`user_id`, `permission`, `granted_by`)
    SELECT `user_id`, 'aussteller_loeschen', `granted_by` FROM `user_permissions` WHERE `permission` = 'manage_exhibitors';
INSERT IGNORE INTO `user_permissions` (`user_id`, `permission`, `granted_by`)
    SELECT `user_id`, 'aussteller_dokumente_verwalten', `granted_by` FROM `user_permissions` WHERE `permission` = 'manage_exhibitors';

-- manage_rooms → raeume_* + kapazitaeten_*
INSERT IGNORE INTO `user_permissions` (`user_id`, `permission`, `granted_by`)
    SELECT `user_id`, 'raeume_sehen', `granted_by` FROM `user_permissions` WHERE `permission` = 'manage_rooms';
INSERT IGNORE INTO `user_permissions` (`user_id`, `permission`, `granted_by`)
    SELECT `user_id`, 'raeume_erstellen', `granted_by` FROM `user_permissions` WHERE `permission` = 'manage_rooms';
INSERT IGNORE INTO `user_permissions` (`user_id`, `permission`, `granted_by`)
    SELECT `user_id`, 'raeume_bearbeiten', `granted_by` FROM `user_permissions` WHERE `permission` = 'manage_rooms';
INSERT IGNORE INTO `user_permissions` (`user_id`, `permission`, `granted_by`)
    SELECT `user_id`, 'raeume_loeschen', `granted_by` FROM `user_permissions` WHERE `permission` = 'manage_rooms';
INSERT IGNORE INTO `user_permissions` (`user_id`, `permission`, `granted_by`)
    SELECT `user_id`, 'kapazitaeten_sehen', `granted_by` FROM `user_permissions` WHERE `permission` = 'manage_rooms';
INSERT IGNORE INTO `user_permissions` (`user_id`, `permission`, `granted_by`)
    SELECT `user_id`, 'kapazitaeten_bearbeiten', `granted_by` FROM `user_permissions` WHERE `permission` = 'manage_rooms';

-- manage_settings → einstellungen_*
INSERT IGNORE INTO `user_permissions` (`user_id`, `permission`, `granted_by`)
    SELECT `user_id`, 'einstellungen_sehen', `granted_by` FROM `user_permissions` WHERE `permission` = 'manage_settings';
INSERT IGNORE INTO `user_permissions` (`user_id`, `permission`, `granted_by`)
    SELECT `user_id`, 'einstellungen_bearbeiten', `granted_by` FROM `user_permissions` WHERE `permission` = 'manage_settings';

-- manage_users → benutzer_*
INSERT IGNORE INTO `user_permissions` (`user_id`, `permission`, `granted_by`)
    SELECT `user_id`, 'benutzer_sehen', `granted_by` FROM `user_permissions` WHERE `permission` = 'manage_users';
INSERT IGNORE INTO `user_permissions` (`user_id`, `permission`, `granted_by`)
    SELECT `user_id`, 'benutzer_erstellen', `granted_by` FROM `user_permissions` WHERE `permission` = 'manage_users';
INSERT IGNORE INTO `user_permissions` (`user_id`, `permission`, `granted_by`)
    SELECT `user_id`, 'benutzer_bearbeiten', `granted_by` FROM `user_permissions` WHERE `permission` = 'manage_users';
INSERT IGNORE INTO `user_permissions` (`user_id`, `permission`, `granted_by`)
    SELECT `user_id`, 'benutzer_loeschen', `granted_by` FROM `user_permissions` WHERE `permission` = 'manage_users';
INSERT IGNORE INTO `user_permissions` (`user_id`, `permission`, `granted_by`)
    SELECT `user_id`, 'benutzer_importieren', `granted_by` FROM `user_permissions` WHERE `permission` = 'manage_users';
INSERT IGNORE INTO `user_permissions` (`user_id`, `permission`, `granted_by`)
    SELECT `user_id`, 'benutzer_passwort_zuruecksetzen', `granted_by` FROM `user_permissions` WHERE `permission` = 'manage_users';

-- view_reports → berichte_*
INSERT IGNORE INTO `user_permissions` (`user_id`, `permission`, `granted_by`)
    SELECT `user_id`, 'berichte_sehen', `granted_by` FROM `user_permissions` WHERE `permission` = 'view_reports';
INSERT IGNORE INTO `user_permissions` (`user_id`, `permission`, `granted_by`)
    SELECT `user_id`, 'berichte_drucken', `granted_by` FROM `user_permissions` WHERE `permission` = 'view_reports';

-- auto_assign → zuteilung_*
INSERT IGNORE INTO `user_permissions` (`user_id`, `permission`, `granted_by`)
    SELECT `user_id`, 'dashboard_sehen', `granted_by` FROM `user_permissions` WHERE `permission` = 'auto_assign';
INSERT IGNORE INTO `user_permissions` (`user_id`, `permission`, `granted_by`)
    SELECT `user_id`, 'zuteilung_ausfuehren', `granted_by` FROM `user_permissions` WHERE `permission` = 'auto_assign';
INSERT IGNORE INTO `user_permissions` (`user_id`, `permission`, `granted_by`)
    SELECT `user_id`, 'zuteilung_zuruecksetzen', `granted_by` FROM `user_permissions` WHERE `permission` = 'auto_assign';

-- view_rooms → raeume_sehen
INSERT IGNORE INTO `user_permissions` (`user_id`, `permission`, `granted_by`)
    SELECT `user_id`, 'raeume_sehen', `granted_by` FROM `user_permissions` WHERE `permission` = 'view_rooms';

-- manage_qr_codes → qr_codes_*
INSERT IGNORE INTO `user_permissions` (`user_id`, `permission`, `granted_by`)
    SELECT `user_id`, 'qr_codes_sehen', `granted_by` FROM `user_permissions` WHERE `permission` = 'manage_qr_codes';
INSERT IGNORE INTO `user_permissions` (`user_id`, `permission`, `granted_by`)
    SELECT `user_id`, 'qr_codes_erstellen', `granted_by` FROM `user_permissions` WHERE `permission` = 'manage_qr_codes';

-- view_audit_logs → audit_logs_sehen
INSERT IGNORE INTO `user_permissions` (`user_id`, `permission`, `granted_by`)
    SELECT `user_id`, 'audit_logs_sehen', `granted_by` FROM `user_permissions` WHERE `permission` = 'view_audit_logs';

-- Alte Berechtigungen entfernen
DELETE FROM `user_permissions` WHERE `permission` IN (
    'manage_exhibitors', 'manage_rooms', 'manage_settings', 'manage_users',
    'view_reports', 'auto_assign', 'view_rooms', 'manage_qr_codes', 'view_audit_logs'
);

-- Gleiches für permission_group_items
INSERT IGNORE INTO `permission_group_items` (`group_id`, `permission`)
    SELECT `group_id`, 'aussteller_sehen' FROM `permission_group_items` WHERE `permission` = 'manage_exhibitors';
INSERT IGNORE INTO `permission_group_items` (`group_id`, `permission`)
    SELECT `group_id`, 'aussteller_erstellen' FROM `permission_group_items` WHERE `permission` = 'manage_exhibitors';
INSERT IGNORE INTO `permission_group_items` (`group_id`, `permission`)
    SELECT `group_id`, 'aussteller_bearbeiten' FROM `permission_group_items` WHERE `permission` = 'manage_exhibitors';
INSERT IGNORE INTO `permission_group_items` (`group_id`, `permission`)
    SELECT `group_id`, 'aussteller_loeschen' FROM `permission_group_items` WHERE `permission` = 'manage_exhibitors';
INSERT IGNORE INTO `permission_group_items` (`group_id`, `permission`)
    SELECT `group_id`, 'aussteller_dokumente_verwalten' FROM `permission_group_items` WHERE `permission` = 'manage_exhibitors';
INSERT IGNORE INTO `permission_group_items` (`group_id`, `permission`)
    SELECT `group_id`, 'raeume_sehen' FROM `permission_group_items` WHERE `permission` = 'manage_rooms';
INSERT IGNORE INTO `permission_group_items` (`group_id`, `permission`)
    SELECT `group_id`, 'raeume_erstellen' FROM `permission_group_items` WHERE `permission` = 'manage_rooms';
INSERT IGNORE INTO `permission_group_items` (`group_id`, `permission`)
    SELECT `group_id`, 'raeume_bearbeiten' FROM `permission_group_items` WHERE `permission` = 'manage_rooms';
INSERT IGNORE INTO `permission_group_items` (`group_id`, `permission`)
    SELECT `group_id`, 'raeume_loeschen' FROM `permission_group_items` WHERE `permission` = 'manage_rooms';
INSERT IGNORE INTO `permission_group_items` (`group_id`, `permission`)
    SELECT `group_id`, 'kapazitaeten_sehen' FROM `permission_group_items` WHERE `permission` = 'manage_rooms';
INSERT IGNORE INTO `permission_group_items` (`group_id`, `permission`)
    SELECT `group_id`, 'kapazitaeten_bearbeiten' FROM `permission_group_items` WHERE `permission` = 'manage_rooms';
INSERT IGNORE INTO `permission_group_items` (`group_id`, `permission`)
    SELECT `group_id`, 'einstellungen_sehen' FROM `permission_group_items` WHERE `permission` = 'manage_settings';
INSERT IGNORE INTO `permission_group_items` (`group_id`, `permission`)
    SELECT `group_id`, 'einstellungen_bearbeiten' FROM `permission_group_items` WHERE `permission` = 'manage_settings';
INSERT IGNORE INTO `permission_group_items` (`group_id`, `permission`)
    SELECT `group_id`, 'benutzer_sehen' FROM `permission_group_items` WHERE `permission` = 'manage_users';
INSERT IGNORE INTO `permission_group_items` (`group_id`, `permission`)
    SELECT `group_id`, 'benutzer_erstellen' FROM `permission_group_items` WHERE `permission` = 'manage_users';
INSERT IGNORE INTO `permission_group_items` (`group_id`, `permission`)
    SELECT `group_id`, 'benutzer_bearbeiten' FROM `permission_group_items` WHERE `permission` = 'manage_users';
INSERT IGNORE INTO `permission_group_items` (`group_id`, `permission`)
    SELECT `group_id`, 'benutzer_loeschen' FROM `permission_group_items` WHERE `permission` = 'manage_users';
INSERT IGNORE INTO `permission_group_items` (`group_id`, `permission`)
    SELECT `group_id`, 'benutzer_importieren' FROM `permission_group_items` WHERE `permission` = 'manage_users';
INSERT IGNORE INTO `permission_group_items` (`group_id`, `permission`)
    SELECT `group_id`, 'benutzer_passwort_zuruecksetzen' FROM `permission_group_items` WHERE `permission` = 'manage_users';
INSERT IGNORE INTO `permission_group_items` (`group_id`, `permission`)
    SELECT `group_id`, 'berichte_sehen' FROM `permission_group_items` WHERE `permission` = 'view_reports';
INSERT IGNORE INTO `permission_group_items` (`group_id`, `permission`)
    SELECT `group_id`, 'berichte_drucken' FROM `permission_group_items` WHERE `permission` = 'view_reports';
INSERT IGNORE INTO `permission_group_items` (`group_id`, `permission`)
    SELECT `group_id`, 'dashboard_sehen' FROM `permission_group_items` WHERE `permission` = 'auto_assign';
INSERT IGNORE INTO `permission_group_items` (`group_id`, `permission`)
    SELECT `group_id`, 'zuteilung_ausfuehren' FROM `permission_group_items` WHERE `permission` = 'auto_assign';
INSERT IGNORE INTO `permission_group_items` (`group_id`, `permission`)
    SELECT `group_id`, 'zuteilung_zuruecksetzen' FROM `permission_group_items` WHERE `permission` = 'auto_assign';
INSERT IGNORE INTO `permission_group_items` (`group_id`, `permission`)
    SELECT `group_id`, 'raeume_sehen' FROM `permission_group_items` WHERE `permission` = 'view_rooms';
INSERT IGNORE INTO `permission_group_items` (`group_id`, `permission`)
    SELECT `group_id`, 'qr_codes_sehen' FROM `permission_group_items` WHERE `permission` = 'manage_qr_codes';
INSERT IGNORE INTO `permission_group_items` (`group_id`, `permission`)
    SELECT `group_id`, 'qr_codes_erstellen' FROM `permission_group_items` WHERE `permission` = 'manage_qr_codes';
INSERT IGNORE INTO `permission_group_items` (`group_id`, `permission`)
    SELECT `group_id`, 'audit_logs_sehen' FROM `permission_group_items` WHERE `permission` = 'view_audit_logs';

DELETE FROM `permission_group_items` WHERE `permission` IN (
    'manage_exhibitors', 'manage_rooms', 'manage_settings', 'manage_users',
    'view_reports', 'auto_assign', 'view_rooms', 'manage_qr_codes', 'view_audit_logs'
);

-- ============================================================================
-- Fertig. Alle Tabellen und Migrationen wurden angewendet.
-- ============================================================================
