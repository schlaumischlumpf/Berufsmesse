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
