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
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
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
  `registration_type` enum('manual','automatic') DEFAULT 'manual',
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
  `password` varchar(255) NOT NULL,
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
    IN table_name VARCHAR(128),
    IN column_name VARCHAR(128),
    IN column_definition TEXT
)
BEGIN
    DECLARE column_exists INT DEFAULT 0;

    -- Prüfen ob Spalte existiert
    SELECT COUNT(*) INTO column_exists
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = table_name
      AND COLUMN_NAME = column_name;

    -- Spalte hinzufügen wenn sie nicht existiert
    IF column_exists = 0 THEN
        SET @sql = CONCAT('ALTER TABLE `', table_name, '` ADD COLUMN `', column_name, '` ', column_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        SELECT CONCAT('Column ', column_name, ' added to table ', table_name) AS message;
    ELSE
        SELECT CONCAT('Column ', column_name, ' already exists in table ', table_name) AS message;
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

-- Stored Procedure entfernen
DROP PROCEDURE IF EXISTS add_column_if_not_exists;

-- ============================================================================
-- Foreign Key Constraints hinzufügen (nur wenn sie nicht existieren)
-- ============================================================================

DELIMITER //

-- Stored Procedure zum sicheren Hinzufügen von Foreign Keys
DROP PROCEDURE IF EXISTS add_fk_if_not_exists//
CREATE PROCEDURE add_fk_if_not_exists(
    IN table_name VARCHAR(128),
    IN constraint_name VARCHAR(128),
    IN fk_definition TEXT
)
BEGIN
    DECLARE fk_exists INT DEFAULT 0;

    -- Prüfen ob Foreign Key existiert
    SELECT COUNT(*) INTO fk_exists
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = table_name
      AND CONSTRAINT_NAME = constraint_name
      AND CONSTRAINT_TYPE = 'FOREIGN KEY';

    -- Foreign Key hinzufügen wenn er nicht existiert
    IF fk_exists = 0 THEN
        SET @sql = CONCAT('ALTER TABLE `', table_name, '` ADD CONSTRAINT `', constraint_name, '` ', fk_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        SELECT CONCAT('Foreign Key ', constraint_name, ' added to table ', table_name) AS message;
    ELSE
        SELECT CONCAT('Foreign Key ', constraint_name, ' already exists in table ', table_name) AS message;
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

COMMIT;

SELECT 'Schema update completed successfully!' AS message;
SELECT 'All missing tables and columns have been created.' AS message;
SELECT 'Existing data has been preserved.' AS message;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
