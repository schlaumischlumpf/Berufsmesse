-- ============================================================================
-- Berufsmesse Datenbank Schema Update
-- Erstellt: 2026-02-19
-- Zweck: Erstellt alle fehlenden Tabellen und Spalten, behält bestehende Daten
-- ============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- ============================================================================
-- Tabellen erstellen (nur wenn sie nicht existieren)
-- ============================================================================

-- Tabelle: attendance
CREATE TABLE IF NOT EXISTS `attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `exhibitor_id` int(11) NOT NULL,
  `timeslot_id` int(11) NOT NULL,
  `qr_token` varchar(12) DEFAULT NULL,
  `checked_in_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_exhibitor_timeslot` (`user_id`,`exhibitor_id`,`timeslot_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_exhibitor_id` (`exhibitor_id`),
  KEY `idx_timeslot_id` (`timeslot_id`),
  KEY `idx_checked_in_at` (`checked_in_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Anwesenheit von Schülern bei Ausstellern (QR-Check-In)';

-- Tabelle: audit_logs
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(100) NOT NULL,
  `action` varchar(255) NOT NULL,
  `severity` ENUM('info', 'warning', 'error') NOT NULL DEFAULT 'info' COMMENT 'Schweregrad des Log-Eintrags',
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_severity` (`severity`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit Logs für alle Nutzeraktionen';

-- Tabelle: exhibitors
CREATE TABLE IF NOT EXISTS `exhibitors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `short_description` varchar(500) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `total_slots` int(11) DEFAULT 25,
  `room_id` int(11) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `visible_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Definiert welche Felder für Schüler sichtbar sind' CHECK (json_valid(`visible_fields`)),
  `jobs` text DEFAULT NULL COMMENT 'Typische Berufe/Tätigkeiten im Unternehmen',
  `features` text DEFAULT NULL COMMENT 'Besonderheiten des Unternehmens',
  `offer_types` text DEFAULT NULL COMMENT 'JSON: {selected: [...], custom: "..."}',
  `equipment` varchar(500) DEFAULT NULL COMMENT 'Benötigte Ausstattung (z.B. Beamer, WLAN)',
  PRIMARY KEY (`id`),
  KEY `fk_exhibitor_room` (`room_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabelle: exhibitor_documents
CREATE TABLE IF NOT EXISTS `exhibitor_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `exhibitor_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `visible_for_students` tinyint(1) DEFAULT 0 COMMENT 'Gibt an ob das Dokument für Schüler sichtbar ist',
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `exhibitor_id` (`exhibitor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabelle: industries
CREATE TABLE IF NOT EXISTS `industries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Verwaltbare Branchen/Kategorien fuer Aussteller';

-- Tabelle: qr_tokens
CREATE TABLE IF NOT EXISTS `qr_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `exhibitor_id` int(11) NOT NULL,
  `timeslot_id` int(11) NOT NULL,
  `token` varchar(12) NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_exhibitor_timeslot` (`exhibitor_id`,`timeslot_id`),
  UNIQUE KEY `unique_token` (`token`),
  KEY `idx_token` (`token`),
  KEY `idx_exhibitor_id` (`exhibitor_id`),
  KEY `idx_timeslot_id` (`timeslot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='QR-Code Tokens für Aussteller-Check-In';

-- Tabelle: permission_groups
CREATE TABLE IF NOT EXISTS `permission_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_group_name` (`name`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Berechtigungsgruppen';

-- Tabelle: permission_group_items
CREATE TABLE IF NOT EXISTS `permission_group_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `permission` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_group_permission` (`group_id`,`permission`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Berechtigungen in Gruppen';

-- Tabelle: registrations
CREATE TABLE IF NOT EXISTS `registrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `exhibitor_id` int(11) NOT NULL,
  `timeslot_id` int(11) DEFAULT NULL,
  `registration_type` enum('manual','automatic','qr_checkin') DEFAULT 'manual',
  `priority` int(11) DEFAULT NULL COMMENT 'Priorität der Anmeldung (1 = höchste Priorität)',
  `registered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_exhibitor` (`user_id`,`exhibitor_id`),
  KEY `exhibitor_id` (`exhibitor_id`),
  KEY `timeslot_id` (`timeslot_id`),
  KEY `idx_timeslot_only` (`timeslot_id`),
  KEY `idx_user_only` (`user_id`),
  KEY `idx_user_timeslot` (`user_id`,`timeslot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabelle: rooms
CREATE TABLE IF NOT EXISTS `rooms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_number` varchar(50) NOT NULL,
  `room_name` varchar(100) DEFAULT NULL,
  `building` varchar(50) DEFAULT NULL,
  `capacity` int(11) DEFAULT 30,
  `equipment` varchar(500) DEFAULT NULL COMMENT 'Raumausstattung (z.B. Beamer, Smartboard)',
  `floor` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabelle: room_slot_capacities
CREATE TABLE IF NOT EXISTS `room_slot_capacities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_id` int(11) NOT NULL,
  `timeslot_id` int(11) NOT NULL,
  `capacity` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_room_slot` (`room_id`,`timeslot_id`),
  KEY `idx_room_id` (`room_id`),
  KEY `idx_timeslot_id` (`timeslot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Slot-spezifische Raumkapazitäten';

-- Tabelle: settings
CREATE TABLE IF NOT EXISTS `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabelle: timeslots
CREATE TABLE IF NOT EXISTS `timeslots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slot_number` int(11) NOT NULL,
  `slot_name` varchar(100) NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabelle: users
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `firstname` varchar(100) NOT NULL,
  `lastname` varchar(100) NOT NULL,
  `class` varchar(50) DEFAULT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'student',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `must_change_password` tinyint(1) DEFAULT 0 COMMENT 'Erzwingt Passwortänderung beim nächsten Login',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabelle: user_permissions
CREATE TABLE IF NOT EXISTS `user_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `permission` varchar(50) NOT NULL,
  `granted_by` int(11) DEFAULT NULL COMMENT 'Admin der die Berechtigung erteilt hat',
  `granted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_permission` (`user_id`,`permission`),
  KEY `granted_by` (`granted_by`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_permission` (`permission`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Granulare Benutzerberechtigungen';

-- Tabelle: user_permission_groups
CREATE TABLE IF NOT EXISTS `user_permission_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_group` (`user_id`,`group_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_group_id` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Zuordnung von Berechtigungsgruppen zu Benutzern';

-- Tabelle: exhibitor_orga_team
CREATE TABLE IF NOT EXISTS `exhibitor_orga_team` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `exhibitor_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_exhibitor` (`user_id`,`exhibitor_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_exhibitor_id` (`exhibitor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Orga-Team Zuordnung zu einzelnen Ausstellern';

-- ============================================================================
-- Fehlende Spalten hinzufügen (mit sicherer Stored Procedure)
-- ============================================================================

DELIMITER //

-- Stored Procedure zum sicheren Hinzufügen von Spalten
DROP PROCEDURE IF EXISTS add_column_if_not_exists//
CREATE PROCEDURE add_column_if_not_exists(
    IN p_table VARCHAR(128),
    IN p_column VARCHAR(128),
    IN p_definition TEXT
)
BEGIN
    DECLARE col_exists INT DEFAULT 0;

    -- Prüfen ob Spalte existiert
    SELECT COUNT(*) INTO col_exists
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = p_table
      AND COLUMN_NAME  = p_column;

    -- Spalte hinzufügen wenn sie nicht existiert
    IF col_exists = 0 THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END//

DELIMITER ;

-- Fehlende Spalten hinzufügen
CALL add_column_if_not_exists('exhibitors', 'visible_fields', 'longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT \'Definiert welche Felder für Schüler sichtbar sind\' CHECK (json_valid(`visible_fields`))');
CALL add_column_if_not_exists('exhibitors', 'jobs', 'text DEFAULT NULL COMMENT \'Typische Berufe/Tätigkeiten im Unternehmen\'');
CALL add_column_if_not_exists('exhibitors', 'features', 'text DEFAULT NULL COMMENT \'Besonderheiten des Unternehmens\'');
CALL add_column_if_not_exists('exhibitors', 'offer_types', 'text DEFAULT NULL COMMENT \'JSON: {selected: [...], custom: "..."}\'');
CALL add_column_if_not_exists('exhibitors', 'equipment', 'varchar(500) DEFAULT NULL COMMENT \'Benötigte Ausstattung (z.B. Beamer, WLAN)\'');
CALL add_column_if_not_exists('users', 'must_change_password', 'tinyint(1) DEFAULT 0 COMMENT \'Erzwingt Passwortänderung beim nächsten Login\'');
CALL add_column_if_not_exists('registrations', 'priority', 'int(11) DEFAULT NULL COMMENT \'Priorität der Anmeldung (1 = höchste Priorität)\'');
CALL add_column_if_not_exists('rooms', 'equipment', 'varchar(500) DEFAULT NULL COMMENT \'Raumausstattung (z.B. Beamer, Smartboard)\'');
CALL add_column_if_not_exists('exhibitor_documents', 'visible_for_students', 'tinyint(1) DEFAULT 0 COMMENT \'Gibt an ob das Dokument für Schüler sichtbar ist\'');
CALL add_column_if_not_exists('audit_logs', 'severity', "ENUM('info', 'warning', 'error') NOT NULL DEFAULT 'info' COMMENT 'Schweregrad des Log-Eintrags'");

-- Index für severity hinzufügen (nur wenn nicht vorhanden)
DROP PROCEDURE IF EXISTS add_index_if_not_exists;
DELIMITER //
CREATE PROCEDURE add_index_if_not_exists(
    IN p_table VARCHAR(128),
    IN p_index VARCHAR(128),
    IN p_column VARCHAR(128)
)
BEGIN
    DECLARE idx_exists INT DEFAULT 0;
    SELECT COUNT(*) INTO idx_exists
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = p_table
      AND INDEX_NAME   = p_index;
    IF idx_exists = 0 THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD INDEX `', p_index, '` (`', p_column, '`)');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END//
DELIMITER ;
CALL add_index_if_not_exists('audit_logs', 'idx_severity', 'severity');
DROP PROCEDURE IF EXISTS add_index_if_not_exists;

-- ============================================================================
-- Foreign Key Constraints hinzufügen (nur wenn sie nicht existieren)
-- ============================================================================

DELIMITER //

-- Stored Procedure zum sicheren Hinzufügen von Foreign Keys
DROP PROCEDURE IF EXISTS add_fk_if_not_exists//
CREATE PROCEDURE add_fk_if_not_exists(
    IN p_table VARCHAR(128),
    IN p_constraint VARCHAR(128),
    IN p_definition TEXT
)
BEGIN
    DECLARE fk_exists INT DEFAULT 0;

    -- Prüfen ob Foreign Key existiert
    SELECT COUNT(*) INTO fk_exists
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA     = DATABASE()
      AND TABLE_NAME       = p_table
      AND CONSTRAINT_NAME  = p_constraint
      AND CONSTRAINT_TYPE  = 'FOREIGN KEY';

    -- Foreign Key hinzufügen wenn er nicht existiert
    IF fk_exists = 0 THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD CONSTRAINT `', p_constraint, '` ', p_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END//

DELIMITER ;

-- Foreign Keys hinzufügen
CALL add_fk_if_not_exists('attendance', 'attendance_ibfk_1', 'FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE');
CALL add_fk_if_not_exists('attendance', 'attendance_ibfk_2', 'FOREIGN KEY (`exhibitor_id`) REFERENCES `exhibitors` (`id`) ON DELETE CASCADE');
CALL add_fk_if_not_exists('attendance', 'attendance_ibfk_3', 'FOREIGN KEY (`timeslot_id`) REFERENCES `timeslots` (`id`) ON DELETE CASCADE');

CALL add_fk_if_not_exists('exhibitors', 'fk_exhibitor_room', 'FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL');

CALL add_fk_if_not_exists('exhibitor_documents', 'exhibitor_documents_ibfk_1', 'FOREIGN KEY (`exhibitor_id`) REFERENCES `exhibitors` (`id`) ON DELETE CASCADE');

CALL add_fk_if_not_exists('permission_groups', 'permission_groups_ibfk_1', 'FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL');

CALL add_fk_if_not_exists('permission_group_items', 'permission_group_items_ibfk_1', 'FOREIGN KEY (`group_id`) REFERENCES `permission_groups` (`id`) ON DELETE CASCADE');

CALL add_fk_if_not_exists('qr_tokens', 'qr_tokens_ibfk_1', 'FOREIGN KEY (`exhibitor_id`) REFERENCES `exhibitors` (`id`) ON DELETE CASCADE');
CALL add_fk_if_not_exists('qr_tokens', 'qr_tokens_ibfk_2', 'FOREIGN KEY (`timeslot_id`) REFERENCES `timeslots` (`id`) ON DELETE CASCADE');

CALL add_fk_if_not_exists('registrations', 'registrations_ibfk_1', 'FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE');
CALL add_fk_if_not_exists('registrations', 'registrations_ibfk_2', 'FOREIGN KEY (`exhibitor_id`) REFERENCES `exhibitors` (`id`) ON DELETE CASCADE');
CALL add_fk_if_not_exists('registrations', 'registrations_ibfk_3', 'FOREIGN KEY (`timeslot_id`) REFERENCES `timeslots` (`id`) ON DELETE CASCADE');

CALL add_fk_if_not_exists('room_slot_capacities', 'room_slot_capacities_ibfk_1', 'FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE');
CALL add_fk_if_not_exists('room_slot_capacities', 'room_slot_capacities_ibfk_2', 'FOREIGN KEY (`timeslot_id`) REFERENCES `timeslots` (`id`) ON DELETE CASCADE');

CALL add_fk_if_not_exists('user_permissions', 'user_permissions_ibfk_1', 'FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE');
CALL add_fk_if_not_exists('user_permissions', 'user_permissions_ibfk_2', 'FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL');

CALL add_fk_if_not_exists('user_permission_groups', 'user_permission_groups_ibfk_1', 'FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE');
CALL add_fk_if_not_exists('user_permission_groups', 'user_permission_groups_ibfk_2', 'FOREIGN KEY (`group_id`) REFERENCES `permission_groups` (`id`) ON DELETE CASCADE');

CALL add_fk_if_not_exists('exhibitor_orga_team', 'exhibitor_orga_team_ibfk_1', 'FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE');
CALL add_fk_if_not_exists('exhibitor_orga_team', 'exhibitor_orga_team_ibfk_2', 'FOREIGN KEY (`exhibitor_id`) REFERENCES `exhibitors` (`id`) ON DELETE CASCADE');

-- Stored Procedure entfernen
DROP PROCEDURE IF EXISTS add_fk_if_not_exists;

-- ============================================================================
-- Column Type Updates
-- ============================================================================

-- Update exhibitors.category to TEXT to support JSON array of multiple categories
ALTER TABLE exhibitors MODIFY COLUMN category TEXT;

-- ============================================================================
-- Schema Update erfolgreich abgeschlossen
-- ============================================================================

-- ============================================================================
-- Migration 12: Mehrjährigkeit
-- ============================================================================

CREATE TABLE IF NOT EXISTS `messe_editions` (
  `id`                            INT(11)       NOT NULL AUTO_INCREMENT,
  `name`                          VARCHAR(150)  NOT NULL,
  `year`                          INT(4)        NOT NULL,
  `status`                        ENUM('active','archived') NOT NULL DEFAULT 'archived',
  `registration_start`            DATETIME      DEFAULT NULL,
  `registration_end`              DATETIME      DEFAULT NULL,
  `event_date`                    DATE          DEFAULT NULL,
  `max_registrations_per_student` INT(11)       NOT NULL DEFAULT 3,
  `created_at`                    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `messe_editions` (name, year, status, registration_start, registration_end, event_date, max_registrations_per_student)
SELECT
  CONCAT('Berufsmesse ', YEAR(COALESCE((SELECT setting_value FROM settings WHERE setting_key='event_date' LIMIT 1), NOW()))),
  YEAR(COALESCE((SELECT setting_value FROM settings WHERE setting_key='event_date' LIMIT 1), NOW())),
  'active',
  (SELECT setting_value FROM settings WHERE setting_key='registration_start' LIMIT 1),
  (SELECT setting_value FROM settings WHERE setting_key='registration_end' LIMIT 1),
  (SELECT setting_value FROM settings WHERE setting_key='event_date' LIMIT 1),
  COALESCE((SELECT setting_value FROM settings WHERE setting_key='max_registrations_per_student' LIMIT 1), 3)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM messe_editions LIMIT 1);

DROP PROCEDURE IF EXISTS add_edition_id;
DELIMITER //
CREATE PROCEDURE add_edition_id(IN p_table VARCHAR(64))
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=p_table AND COLUMN_NAME='edition_id') THEN
        SET @s = CONCAT('ALTER TABLE `',p_table,'` ADD COLUMN `edition_id` INT(11) NOT NULL DEFAULT 1, ADD KEY `idx_edition_id` (`edition_id`)');
        PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END //
DELIMITER ;

CALL add_edition_id('registrations'); CALL add_edition_id('exhibitors');
CALL add_edition_id('timeslots');     CALL add_edition_id('rooms');
CALL add_edition_id('room_slot_capacities'); CALL add_edition_id('attendance');
CALL add_edition_id('qr_tokens');    CALL add_edition_id('exhibitor_documents');
CALL add_edition_id('exhibitor_orga_team');
DROP PROCEDURE IF EXISTS add_edition_id;

-- Migration 13: announcements
CREATE TABLE IF NOT EXISTS `announcements` (
  `id` INT(11) NOT NULL AUTO_INCREMENT, `title` VARCHAR(200) NOT NULL,
  `body` TEXT NOT NULL, `type` ENUM('info','warning','success','error') NOT NULL DEFAULT 'info',
  `target_role` ENUM('all','student','teacher','admin') NOT NULL DEFAULT 'all',
  `expires_at` DATETIME DEFAULT NULL, `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` INT(11) NOT NULL, `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `idx_active_role` (`is_active`,`target_role`), KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration 14: timeslots.is_managed
CALL add_column_if_not_exists('timeslots', 'is_managed',
    "TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = fester Zuteilungs-Slot'");
UPDATE `timeslots` SET is_managed = 1 WHERE slot_number IN (1,3,5) AND is_managed = 0;

-- Migration 15: login_attempts
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` INT(11) NOT NULL AUTO_INCREMENT, `username` VARCHAR(100) NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL, `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `idx_username` (`username`), KEY `idx_ip` (`ip_address`), KEY `idx_attempted` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration 16: edition_id zu users (Schüler/Lehrer/Orga sind editionsspezifisch)
CALL add_column_if_not_exists('users', 'edition_id',
    "INT(11) DEFAULT NULL COMMENT 'NULL = globaler Admin, sonst editionsspezifischer Benutzer'");
UPDATE users SET edition_id = (SELECT id FROM messe_editions WHERE status='active' LIMIT 1)
    WHERE role != 'admin' AND edition_id IS NULL;

-- Alle Hilfsprozeduren entfernen
DROP PROCEDURE IF EXISTS add_column_if_not_exists;

-- UNIQUE-Constraint ändern: username + edition_id statt nur username
SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND INDEX_NAME='username');
SET @s = IF(@idx_exists > 0, 'ALTER TABLE `users` DROP INDEX `username`', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @uidx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND INDEX_NAME='unique_username_edition');
SET @s = IF(@uidx_exists = 0,
    'ALTER TABLE `users` ADD UNIQUE KEY `unique_username_edition` (`username`, `edition_id`)',
    'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Migration 17: timeslots.is_break
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='timeslots' AND COLUMN_NAME='is_break');
SET @s = IF(@col_exists = 0,
    "ALTER TABLE `timeslots` ADD COLUMN `is_break` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = Pause, 0 = normaler Slot'",
    'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Migration 18: password-Spalte darf NULL sein (für passwortlose Importe)
SET @pwd_nullable = (SELECT IS_NULLABLE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='password');
SET @s = IF(@pwd_nullable = 'NO',
    'ALTER TABLE `users` MODIFY COLUMN `password` varchar(255) DEFAULT NULL',
    'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================================
-- Migration 19: Multi-Schulen-Architektur
-- ============================================================================

-- Tabelle: schools (Mandanten)
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

-- Standardschule anlegen falls noch keine existiert
INSERT INTO `schools` (name, slug, is_active)
SELECT 'Standardschule', 'standard', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM schools LIMIT 1);

-- school_id auf relevante Tabellen hinzufügen (Stored Procedure)
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

-- Tabelle: exhibitor_users (Aussteller-Account-Verknüpfung N:M)
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

-- Tabelle: equipment_options (Ausstattungsoptionen pro Schule)
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

-- Tabelle: exhibitor_equipment_requests (Ausstattungsanfragen)
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

-- UNIQUE-Constraint: username + school_id
SET @uidx = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND INDEX_NAME='unique_username_school');
SET @s = IF(@uidx = 0,
    'ALTER TABLE `users` ADD UNIQUE KEY `unique_username_school` (`username`, `school_id`)',
    'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Settings: zusammengesetzter Key (setting_key, school_id)
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

-- ============================================================================
-- Migration 20: Add school_id to audit_logs (per-school log scoping)
-- ============================================================================

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='audit_logs' AND COLUMN_NAME='school_id');
SET @s = IF(@col_exists = 0,
    'ALTER TABLE `audit_logs` ADD COLUMN `school_id` INT(11) DEFAULT NULL COMMENT \'Schule zu der der Log-Eintrag gehört (NULL = systemweit)\'',
    'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='audit_logs' AND INDEX_NAME='idx_audit_school_id');
SET @s = IF(@idx_exists = 0,
    'ALTER TABLE `audit_logs` ADD KEY `idx_audit_school_id` (`school_id`)',
    'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================================
-- Migration 21: Add exhibitor invite columns to exhibitor_users
-- ============================================================================

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exhibitor_users' AND COLUMN_NAME = 'invite_token');
SET @s = IF(@col_exists = 0,
    "ALTER TABLE `exhibitor_users`
        ADD COLUMN `invite_token`    VARCHAR(64)  DEFAULT NULL
            COMMENT 'One-time token for exhibitor self-registration',
        ADD COLUMN `invite_accepted` TINYINT(1)   NOT NULL DEFAULT 0
            COMMENT '1 = exhibitor has accepted and set up their profile',
        ADD COLUMN `invite_expires`  DATETIME     DEFAULT NULL,
        ADD UNIQUE KEY `unique_invite_token` (`invite_token`)",
    'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================================
-- Migration 22: Add school_id to exhibitor_orga_team for per-school scoping
-- ============================================================================

-- Step 1: Remove duplicate (user_id, exhibitor_id) rows keeping only the newest
--         so the new 3-column UNIQUE KEY can be created cleanly
SET @s0 = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'exhibitor_orga_team'
      AND COLUMN_NAME  = 'school_id'
);
-- Only de-duplicate if school_id column doesn't exist yet (first run)
SET @dedup = IF(@s0 = 0,
    'DELETE eo1 FROM exhibitor_orga_team eo1
     INNER JOIN exhibitor_orga_team eo2
     WHERE eo1.user_id = eo2.user_id
       AND eo1.exhibitor_id = eo2.exhibitor_id
       AND eo1.id < eo2.id',
    'SELECT 1');
PREPARE stmt FROM @dedup; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Step 2: Add school_id column
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'exhibitor_orga_team'
      AND COLUMN_NAME  = 'school_id'
);
SET @s = IF(@col_exists = 0,
    'ALTER TABLE `exhibitor_orga_team`
     ADD COLUMN `school_id` INT(11) DEFAULT NULL
     COMMENT \'Schule zu der diese Zuweisung gehört\'',
    'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Step 3: Backfill school_id from the exhibitor's edition
SET @s2 = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'exhibitor_orga_team'
      AND COLUMN_NAME  = 'school_id'
);
SET @backfill = IF(@s2 > 0,
    'UPDATE exhibitor_orga_team eo
     JOIN exhibitors e ON eo.exhibitor_id = e.id
     JOIN messe_editions me ON e.edition_id = me.id
     SET eo.school_id = me.school_id
     WHERE eo.school_id IS NULL',
    'SELECT 1');
PREPARE stmt FROM @backfill; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Step 4: Drop old UNIQUE constraint
SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'exhibitor_orga_team'
      AND INDEX_NAME   = 'unique_user_exhibitor'
);
SET @s3 = IF(@idx_exists > 0,
    'ALTER TABLE `exhibitor_orga_team` DROP INDEX `unique_user_exhibitor`',
    'SELECT 1');
PREPARE stmt FROM @s3; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Step 5: Add new school-scoped UNIQUE constraint
SET @idx2_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'exhibitor_orga_team'
      AND INDEX_NAME   = 'unique_user_exhibitor_school'
);
SET @s4 = IF(@idx2_exists = 0,
    'ALTER TABLE `exhibitor_orga_team`
     ADD UNIQUE KEY `unique_user_exhibitor_school`
     (`user_id`, `exhibitor_id`, `school_id`)',
    'SELECT 1');
PREPARE stmt FROM @s4; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================================
-- Migration 23: Add status + cancellation fields to exhibitor_users
-- ============================================================================

SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exhibitor_users' AND COLUMN_NAME = 'status');
SET @s = IF(@col_exists = 0,
    "ALTER TABLE `exhibitor_users`
        ADD COLUMN `status` ENUM('active','cancelled_by_exhibitor','cancelled_by_school','removed_by_admin')
            NOT NULL DEFAULT 'active'
            COMMENT 'Teilnahmestatus der Verknüpfung',
        ADD COLUMN `cancelled_at` DATETIME DEFAULT NULL
            COMMENT 'Zeitpunkt der Absage/Entfernung',
        ADD COLUMN `cancel_reason` VARCHAR(500) DEFAULT NULL
            COMMENT 'Begründung der Absage'",
    'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================================
-- Migration 24: Cancellation requests table (two-step cancellation process)
-- ============================================================================

SET @table_exists = (
    SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cancellation_requests');
SET @s = IF(@table_exists = 0,
    "CREATE TABLE `cancellation_requests` (
        `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
        `exhibitor_id` INT(11) NOT NULL,
        `user_id` INT(11) NOT NULL COMMENT 'Aussteller-User bei exhibitor-Absage',
        `school_id` INT(11) NOT NULL COMMENT 'Betroffene Schule',
        `requested_by` ENUM('exhibitor','school') NOT NULL COMMENT 'Wer hat die Absage beantragt',
        `reason` VARCHAR(500) DEFAULT NULL COMMENT 'Begründung',
        `status` ENUM('pending','confirmed','rejected') NOT NULL DEFAULT 'pending',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `confirmed_at` DATETIME DEFAULT NULL,
        `confirmed_by` INT(11) DEFAULT NULL COMMENT 'User-ID der bestätigenden Person',
        FOREIGN KEY (`exhibitor_id`) REFERENCES `exhibitors`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`confirmed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Absage-Anträge mit Bestätigungspflicht'",
    'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================================
-- Migration 25: Login notifications table
-- ============================================================================

SET @table_exists = (
    SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'login_notifications');
SET @s = IF(@table_exists = 0,
    "CREATE TABLE `login_notifications` (
        `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT(11) NOT NULL COMMENT 'Empfänger der Benachrichtigung',
        `school_id` INT(11) DEFAULT NULL COMMENT 'Betroffene Schule (NULL = alle Schulen des Users)',
        `message` TEXT NOT NULL COMMENT 'Nachrichtentext',
        `type` ENUM('exhibitor_cancelled','school_cancelled','cancellation_request','info') NOT NULL,
        `related_id` INT(11) DEFAULT NULL COMMENT 'ID des verknüpften Datensatzes (z.B. exhibitor_id oder cancellation_request_id)',
        `action_url` VARCHAR(500) DEFAULT NULL COMMENT 'Link zur Aktion (optional)',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `read_at` DATETIME DEFAULT NULL,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
        INDEX `idx_user_unread` (`user_id`, `read_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Login-Benachrichtigungen für User'",
    'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
