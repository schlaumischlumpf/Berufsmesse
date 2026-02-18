-- Migrations für verbleibende Issues
-- Datum: 05.11.2025
-- Hinweis: Diese Datei muss manuell in der Datenbank ausgeführt werden

-- ===========================================================================
-- Issue #9: Aussteller-Informationen Sichtbarkeit
-- ===========================================================================
-- Fügt JSON-Feld für sichtbare Felder hinzu
ALTER TABLE exhibitors 
ADD COLUMN IF NOT EXISTS visible_fields JSON DEFAULT NULL COMMENT 'Definiert welche Felder für Schüler sichtbar sind';

-- Standard-Werte setzen (alle Felder außer Kontaktdaten)
UPDATE exhibitors 
SET visible_fields = JSON_ARRAY('name', 'short_description', 'description', 'category', 'website')
WHERE visible_fields IS NULL;

-- ===========================================================================
-- Neue Felder für erweiterte Aussteller-Infos
-- ===========================================================================
ALTER TABLE exhibitors 
ADD COLUMN IF NOT EXISTS jobs TEXT DEFAULT NULL COMMENT 'Typische Berufe/Tätigkeiten im Unternehmen';

ALTER TABLE exhibitors 
ADD COLUMN IF NOT EXISTS features TEXT DEFAULT NULL COMMENT 'Besonderheiten des Unternehmens';

ALTER TABLE exhibitors 
ADD COLUMN IF NOT EXISTS offer_types VARCHAR(255) DEFAULT NULL COMMENT 'Angebote: Ausbildung, Studium, Praktikum etc.';

-- ===========================================================================
-- Feature: Angebotstypen als JSON speichern
-- ===========================================================================
ALTER TABLE exhibitors MODIFY COLUMN offer_types TEXT DEFAULT NULL 
  COMMENT 'JSON: {selected: [...], custom: "..."}';

-- ===========================================================================
-- Feature: Branchen/Kategorien dynamisch verwalten
-- ===========================================================================
CREATE TABLE IF NOT EXISTS industries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
  COMMENT='Verwaltbare Branchen/Kategorien fuer Aussteller';

INSERT IGNORE INTO industries (name, sort_order) VALUES
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

-- ===========================================================================
-- Logo-Upload für Aussteller
-- ===========================================================================
ALTER TABLE exhibitors 
ADD COLUMN IF NOT EXISTS logo VARCHAR(255) DEFAULT NULL COMMENT 'Pfad zum Logo-Bild des Ausstellers';

-- ===========================================================================
-- Issue #4: Raumkapazitäten pro Slot
-- ===========================================================================
-- Neue Tabelle für slot-spezifische Raumkapazitäten
CREATE TABLE IF NOT EXISTS room_slot_capacities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    timeslot_id INT NOT NULL,
    capacity INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (timeslot_id) REFERENCES timeslots(id) ON DELETE CASCADE,
    UNIQUE KEY unique_room_slot (room_id, timeslot_id),
    INDEX idx_room_id (room_id),
    INDEX idx_timeslot_id (timeslot_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Slot-spezifische Raumkapazitäten';

-- ===========================================================================
-- Issue #10: Berechtigungssystem
-- ===========================================================================
-- Neue Tabelle für Benutzerberechtigungen
CREATE TABLE IF NOT EXISTS user_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    permission VARCHAR(50) NOT NULL,
    granted_by INT DEFAULT NULL COMMENT 'Admin der die Berechtigung erteilt hat',
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_user_permission (user_id, permission),
    INDEX idx_user_id (user_id),
    INDEX idx_permission (permission)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Granulare Benutzerberechtigungen';

-- Verfügbare Berechtigungen (Dokumentation):
-- 'manage_exhibitors' - Aussteller erstellen/bearbeiten/löschen, Räume zuordnen
-- 'manage_rooms' - Räume verwalten
-- 'manage_settings' - Einschreibezeiten und Event-Datum ändern
-- 'manage_users' - Passwörter zurücksetzen, Accounts erstellen/löschen
-- 'view_reports' - Pläne drucken/ansehen
-- 'auto_assign' - Automatische Zuteilung nutzen

-- ===========================================================================
-- Optional: E-Mail-Spalte zu users Tabelle hinzufügen
-- ===========================================================================
ALTER TABLE users ADD COLUMN IF NOT EXISTS email VARCHAR(255) DEFAULT NULL AFTER username;

-- ===========================================================================
-- Fix: users.role Spalte von ENUM zu VARCHAR ändern für Flexibilität
-- ===========================================================================
-- Ermöglicht teacher und weitere Rollen ohne Schema-Änderungen
ALTER TABLE users MODIFY role VARCHAR(50) NOT NULL DEFAULT 'student';

-- ===========================================================================
-- Verifikation der Änderungen
-- ===========================================================================
-- Überprüfen der neuen Spalten und Tabellen:
-- SHOW COLUMNS FROM exhibitors LIKE 'visible_fields';
-- SHOW TABLES LIKE 'room_slot_capacities';
-- SHOW TABLES LIKE 'user_permissions';
-- SELECT * FROM room_slot_capacities LIMIT 5;
-- SELECT * FROM user_permissions LIMIT 5;

-- ===========================================================================
-- Issue: Passwortänderung beim ersten Login erzwingen
-- ===========================================================================
ALTER TABLE users ADD COLUMN IF NOT EXISTS must_change_password TINYINT(1) DEFAULT 0 COMMENT 'Erzwingt Passwortänderung beim nächsten Login';

-- Setze must_change_password für alle neuen Benutzer standardmäßig auf 1
-- Bereits bestehende Benutzer müssen ihr Passwort nicht ändern

-- ===========================================================================
-- Migration: Erlaube NULL für timeslot_id in registrations
-- Datum: 15.01.2026
-- Beschreibung: Ermöglicht Registrierungen ohne sofortige Slot-Zuteilung
-- ===========================================================================

-- 0. Erstelle zuerst separate Indexe, damit die FK-Constraint nach dem DROP des UNIQUE-Index
--    weiterhin einen passenden Index referenzieren kann (MySQL-Pflicht)
ALTER TABLE `registrations` ADD INDEX IF NOT EXISTS `idx_timeslot_only` (`timeslot_id`);
ALTER TABLE `registrations` ADD INDEX IF NOT EXISTS `idx_user_only` (`user_id`);

-- 1. Entferne die alte UNIQUE constraint (user_id, timeslot_id)
ALTER TABLE `registrations` DROP INDEX IF EXISTS `unique_user_timeslot`;

-- 2. Ändere timeslot_id zu NULL erlaubend
ALTER TABLE `registrations` MODIFY `timeslot_id` int(11) DEFAULT NULL;

-- 3. Füge neue UNIQUE constraint hinzu: Ein User kann sich nur einmal pro Aussteller anmelden
ALTER TABLE `registrations` ADD UNIQUE KEY `unique_user_exhibitor` (`user_id`, `exhibitor_id`);

-- 4. Optional: Füge einen Index für bessere Performance bei Slot-Queries hinzu
ALTER TABLE `registrations` ADD INDEX IF NOT EXISTS `idx_user_timeslot` (`user_id`, `timeslot_id`);

-- ===========================================================================
-- Issue #21: Audit Logs
-- ===========================================================================
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    username VARCHAR(100) NOT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit Logs für alle Nutzeraktionen';

-- ===========================================================================
-- Issue #26: Berechtigungsgruppen
-- ===========================================================================
CREATE TABLE IF NOT EXISTS permission_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_group_name (name),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Berechtigungsgruppen';

CREATE TABLE IF NOT EXISTS permission_group_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    permission VARCHAR(50) NOT NULL,
    FOREIGN KEY (group_id) REFERENCES permission_groups(id) ON DELETE CASCADE,
    UNIQUE KEY unique_group_permission (group_id, permission)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Berechtigungen in Gruppen';

-- ===========================================================================
-- Neue Rolle "orga" & Granulares Berechtigungssystem
-- ===========================================================================

-- 1. Sicherstellen dass die users.role-Spalte 'orga' akzeptiert (bereits VARCHAR(50), kein Änderungsbedarf)

-- 2. Alte Berechtigungen in neue granulare Berechtigungen migrieren (user_permissions)
-- manage_exhibitors → aussteller_*
INSERT IGNORE INTO user_permissions (user_id, permission, granted_by)
    SELECT user_id, 'aussteller_sehen', granted_by FROM user_permissions WHERE permission = 'manage_exhibitors';
INSERT IGNORE INTO user_permissions (user_id, permission, granted_by)
    SELECT user_id, 'aussteller_erstellen', granted_by FROM user_permissions WHERE permission = 'manage_exhibitors';
INSERT IGNORE INTO user_permissions (user_id, permission, granted_by)
    SELECT user_id, 'aussteller_bearbeiten', granted_by FROM user_permissions WHERE permission = 'manage_exhibitors';
INSERT IGNORE INTO user_permissions (user_id, permission, granted_by)
    SELECT user_id, 'aussteller_loeschen', granted_by FROM user_permissions WHERE permission = 'manage_exhibitors';
INSERT IGNORE INTO user_permissions (user_id, permission, granted_by)
    SELECT user_id, 'aussteller_dokumente_verwalten', granted_by FROM user_permissions WHERE permission = 'manage_exhibitors';

-- manage_rooms → raeume_* + kapazitaeten_*
INSERT IGNORE INTO user_permissions (user_id, permission, granted_by)
    SELECT user_id, 'raeume_sehen', granted_by FROM user_permissions WHERE permission = 'manage_rooms';
INSERT IGNORE INTO user_permissions (user_id, permission, granted_by)
    SELECT user_id, 'raeume_erstellen', granted_by FROM user_permissions WHERE permission = 'manage_rooms';
INSERT IGNORE INTO user_permissions (user_id, permission, granted_by)
    SELECT user_id, 'raeume_bearbeiten', granted_by FROM user_permissions WHERE permission = 'manage_rooms';
INSERT IGNORE INTO user_permissions (user_id, permission, granted_by)
    SELECT user_id, 'raeume_loeschen', granted_by FROM user_permissions WHERE permission = 'manage_rooms';
INSERT IGNORE INTO user_permissions (user_id, permission, granted_by)
    SELECT user_id, 'kapazitaeten_sehen', granted_by FROM user_permissions WHERE permission = 'manage_rooms';
INSERT IGNORE INTO user_permissions (user_id, permission, granted_by)
    SELECT user_id, 'kapazitaeten_bearbeiten', granted_by FROM user_permissions WHERE permission = 'manage_rooms';

-- manage_settings → einstellungen_*
INSERT IGNORE INTO user_permissions (user_id, permission, granted_by)
    SELECT user_id, 'einstellungen_sehen', granted_by FROM user_permissions WHERE permission = 'manage_settings';
INSERT IGNORE INTO user_permissions (user_id, permission, granted_by)
    SELECT user_id, 'einstellungen_bearbeiten', granted_by FROM user_permissions WHERE permission = 'manage_settings';

-- manage_users → benutzer_*
INSERT IGNORE INTO user_permissions (user_id, permission, granted_by)
    SELECT user_id, 'benutzer_sehen', granted_by FROM user_permissions WHERE permission = 'manage_users';
INSERT IGNORE INTO user_permissions (user_id, permission, granted_by)
    SELECT user_id, 'benutzer_erstellen', granted_by FROM user_permissions WHERE permission = 'manage_users';
INSERT IGNORE INTO user_permissions (user_id, permission, granted_by)
    SELECT user_id, 'benutzer_bearbeiten', granted_by FROM user_permissions WHERE permission = 'manage_users';
INSERT IGNORE INTO user_permissions (user_id, permission, granted_by)
    SELECT user_id, 'benutzer_loeschen', granted_by FROM user_permissions WHERE permission = 'manage_users';
INSERT IGNORE INTO user_permissions (user_id, permission, granted_by)
    SELECT user_id, 'benutzer_importieren', granted_by FROM user_permissions WHERE permission = 'manage_users';
INSERT IGNORE INTO user_permissions (user_id, permission, granted_by)
    SELECT user_id, 'benutzer_passwort_zuruecksetzen', granted_by FROM user_permissions WHERE permission = 'manage_users';

-- view_reports → berichte_*
INSERT IGNORE INTO user_permissions (user_id, permission, granted_by)
    SELECT user_id, 'berichte_sehen', granted_by FROM user_permissions WHERE permission = 'view_reports';
INSERT IGNORE INTO user_permissions (user_id, permission, granted_by)
    SELECT user_id, 'berichte_drucken', granted_by FROM user_permissions WHERE permission = 'view_reports';

-- auto_assign → zuteilung_*
INSERT IGNORE INTO user_permissions (user_id, permission, granted_by)
    SELECT user_id, 'dashboard_sehen', granted_by FROM user_permissions WHERE permission = 'auto_assign';
INSERT IGNORE INTO user_permissions (user_id, permission, granted_by)
    SELECT user_id, 'zuteilung_ausfuehren', granted_by FROM user_permissions WHERE permission = 'auto_assign';
INSERT IGNORE INTO user_permissions (user_id, permission, granted_by)
    SELECT user_id, 'zuteilung_zuruecksetzen', granted_by FROM user_permissions WHERE permission = 'auto_assign';

-- view_rooms → raeume_sehen
INSERT IGNORE INTO user_permissions (user_id, permission, granted_by)
    SELECT user_id, 'raeume_sehen', granted_by FROM user_permissions WHERE permission = 'view_rooms';

-- manage_qr_codes → qr_codes_*
INSERT IGNORE INTO user_permissions (user_id, permission, granted_by)
    SELECT user_id, 'qr_codes_sehen', granted_by FROM user_permissions WHERE permission = 'manage_qr_codes';
INSERT IGNORE INTO user_permissions (user_id, permission, granted_by)
    SELECT user_id, 'qr_codes_erstellen', granted_by FROM user_permissions WHERE permission = 'manage_qr_codes';

-- view_audit_logs → audit_logs_sehen
INSERT IGNORE INTO user_permissions (user_id, permission, granted_by)
    SELECT user_id, 'audit_logs_sehen', granted_by FROM user_permissions WHERE permission = 'view_audit_logs';

-- 3. Alte Berechtigungen entfernen
DELETE FROM user_permissions WHERE permission IN (
    'manage_exhibitors', 'manage_rooms', 'manage_settings', 'manage_users',
    'view_reports', 'auto_assign', 'view_rooms', 'manage_qr_codes', 'view_audit_logs'
);

-- 4. Gleiches für permission_group_items
INSERT IGNORE INTO permission_group_items (group_id, permission)
    SELECT group_id, 'aussteller_sehen' FROM permission_group_items WHERE permission = 'manage_exhibitors';
INSERT IGNORE INTO permission_group_items (group_id, permission)
    SELECT group_id, 'aussteller_erstellen' FROM permission_group_items WHERE permission = 'manage_exhibitors';
INSERT IGNORE INTO permission_group_items (group_id, permission)
    SELECT group_id, 'aussteller_bearbeiten' FROM permission_group_items WHERE permission = 'manage_exhibitors';
INSERT IGNORE INTO permission_group_items (group_id, permission)
    SELECT group_id, 'aussteller_loeschen' FROM permission_group_items WHERE permission = 'manage_exhibitors';
INSERT IGNORE INTO permission_group_items (group_id, permission)
    SELECT group_id, 'aussteller_dokumente_verwalten' FROM permission_group_items WHERE permission = 'manage_exhibitors';
INSERT IGNORE INTO permission_group_items (group_id, permission)
    SELECT group_id, 'raeume_sehen' FROM permission_group_items WHERE permission = 'manage_rooms';
INSERT IGNORE INTO permission_group_items (group_id, permission)
    SELECT group_id, 'raeume_erstellen' FROM permission_group_items WHERE permission = 'manage_rooms';
INSERT IGNORE INTO permission_group_items (group_id, permission)
    SELECT group_id, 'raeume_bearbeiten' FROM permission_group_items WHERE permission = 'manage_rooms';
INSERT IGNORE INTO permission_group_items (group_id, permission)
    SELECT group_id, 'raeume_loeschen' FROM permission_group_items WHERE permission = 'manage_rooms';
INSERT IGNORE INTO permission_group_items (group_id, permission)
    SELECT group_id, 'kapazitaeten_sehen' FROM permission_group_items WHERE permission = 'manage_rooms';
INSERT IGNORE INTO permission_group_items (group_id, permission)
    SELECT group_id, 'kapazitaeten_bearbeiten' FROM permission_group_items WHERE permission = 'manage_rooms';
INSERT IGNORE INTO permission_group_items (group_id, permission)
    SELECT group_id, 'einstellungen_sehen' FROM permission_group_items WHERE permission = 'manage_settings';
INSERT IGNORE INTO permission_group_items (group_id, permission)
    SELECT group_id, 'einstellungen_bearbeiten' FROM permission_group_items WHERE permission = 'manage_settings';
INSERT IGNORE INTO permission_group_items (group_id, permission)
    SELECT group_id, 'benutzer_sehen' FROM permission_group_items WHERE permission = 'manage_users';
INSERT IGNORE INTO permission_group_items (group_id, permission)
    SELECT group_id, 'benutzer_erstellen' FROM permission_group_items WHERE permission = 'manage_users';
INSERT IGNORE INTO permission_group_items (group_id, permission)
    SELECT group_id, 'benutzer_bearbeiten' FROM permission_group_items WHERE permission = 'manage_users';
INSERT IGNORE INTO permission_group_items (group_id, permission)
    SELECT group_id, 'benutzer_loeschen' FROM permission_group_items WHERE permission = 'manage_users';
INSERT IGNORE INTO permission_group_items (group_id, permission)
    SELECT group_id, 'benutzer_importieren' FROM permission_group_items WHERE permission = 'manage_users';
INSERT IGNORE INTO permission_group_items (group_id, permission)
    SELECT group_id, 'benutzer_passwort_zuruecksetzen' FROM permission_group_items WHERE permission = 'manage_users';
INSERT IGNORE INTO permission_group_items (group_id, permission)
    SELECT group_id, 'berichte_sehen' FROM permission_group_items WHERE permission = 'view_reports';
INSERT IGNORE INTO permission_group_items (group_id, permission)
    SELECT group_id, 'berichte_drucken' FROM permission_group_items WHERE permission = 'view_reports';
INSERT IGNORE INTO permission_group_items (group_id, permission)
    SELECT group_id, 'dashboard_sehen' FROM permission_group_items WHERE permission = 'auto_assign';
INSERT IGNORE INTO permission_group_items (group_id, permission)
    SELECT group_id, 'zuteilung_ausfuehren' FROM permission_group_items WHERE permission = 'auto_assign';
INSERT IGNORE INTO permission_group_items (group_id, permission)
    SELECT group_id, 'zuteilung_zuruecksetzen' FROM permission_group_items WHERE permission = 'auto_assign';
INSERT IGNORE INTO permission_group_items (group_id, permission)
    SELECT group_id, 'raeume_sehen' FROM permission_group_items WHERE permission = 'view_rooms';
INSERT IGNORE INTO permission_group_items (group_id, permission)
    SELECT group_id, 'qr_codes_sehen' FROM permission_group_items WHERE permission = 'manage_qr_codes';
INSERT IGNORE INTO permission_group_items (group_id, permission)
    SELECT group_id, 'qr_codes_erstellen' FROM permission_group_items WHERE permission = 'manage_qr_codes';
INSERT IGNORE INTO permission_group_items (group_id, permission)
    SELECT group_id, 'audit_logs_sehen' FROM permission_group_items WHERE permission = 'view_audit_logs';

DELETE FROM permission_group_items WHERE permission IN (
    'manage_exhibitors', 'manage_rooms', 'manage_settings', 'manage_users',
    'view_reports', 'auto_assign', 'view_rooms', 'manage_qr_codes', 'view_audit_logs'
);
