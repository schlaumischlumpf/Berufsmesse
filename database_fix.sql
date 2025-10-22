-- Reparatur-Script für bestehende Datenbank
-- Führen Sie dies aus, wenn die Tabellen bereits existieren
-- Dieses Script fügt fehlende Spalten hinzu ohne Daten zu löschen

USE berufsmesse;

-- Füge room_id Spalte zu exhibitors hinzu (falls nicht vorhanden)
ALTER TABLE exhibitors 
ADD COLUMN IF NOT EXISTS room_id INT AFTER total_slots;

-- Füge Foreign Key für room_id hinzu (nur wenn nicht bereits vorhanden)
-- Falls bereits vorhanden, wird Fehler ignoriert
SET @fk_exists = (SELECT COUNT(*) 
                  FROM information_schema.TABLE_CONSTRAINTS 
                  WHERE CONSTRAINT_NAME = 'fk_exhibitor_room' 
                  AND TABLE_SCHEMA = 'berufsmesse');

SET @sql = IF(@fk_exists = 0, 
    'ALTER TABLE exhibitors ADD CONSTRAINT fk_exhibitor_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL',
    'SELECT "Foreign Key bereits vorhanden" AS Info');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Füge class Spalte zu users hinzu (falls nicht vorhanden)
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS class VARCHAR(50) AFTER lastname;

-- Aktualisiere bestehende Aussteller mit Raum-Zuweisungen (falls Räume existieren)
UPDATE exhibitors SET room_id = 1 WHERE name = 'Daimler AG' AND room_id IS NULL;
UPDATE exhibitors SET room_id = 2 WHERE name = 'Bosch GmbH' AND room_id IS NULL;
UPDATE exhibitors SET room_id = 5 WHERE name = 'Sparkasse' AND room_id IS NULL;
UPDATE exhibitors SET room_id = 3 WHERE name = 'Siemens AG' AND room_id IS NULL;
UPDATE exhibitors SET room_id = 4 WHERE name = 'Deutsche Bahn' AND room_id IS NULL;
UPDATE exhibitors SET room_id = 6 WHERE name = 'SAP SE' AND room_id IS NULL;

-- Erfolgreiche Ausführung
SELECT 'Datenbank erfolgreich aktualisiert!' AS Status;
