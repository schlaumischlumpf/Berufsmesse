-- Update Script für bestehende Datenbank
-- Führen Sie dieses Script aus, wenn Sie die Datenbank bereits haben
-- und nur die neuen Features hinzufügen möchten

USE berufsmesse;

-- 1. Klasse-Feld zur users Tabelle hinzufügen (falls nicht vorhanden)
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS class VARCHAR(50) AFTER lastname;

-- 2. Räume-Tabelle erstellen (falls nicht vorhanden)
CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_number VARCHAR(50) NOT NULL,
    room_name VARCHAR(100),
    building VARCHAR(50),
    capacity INT DEFAULT 30,
    floor INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. room_id Spalte zu exhibitors hinzufügen (falls nicht vorhanden)
ALTER TABLE exhibitors 
ADD COLUMN IF NOT EXISTS room_id INT AFTER total_slots,
ADD CONSTRAINT IF NOT EXISTS fk_exhibitor_room 
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL;

-- 4. Beispiel-Räume hinzufügen (nur wenn Tabelle leer ist)
INSERT INTO rooms (room_number, room_name, building, capacity, floor)
SELECT * FROM (
    SELECT 'A101' as room_number, 'Workshopraum 1' as room_name, 'Hauptgebäude' as building, 30 as capacity, 1 as floor UNION ALL
    SELECT 'A102', 'Workshopraum 2', 'Hauptgebäude', 25, 1 UNION ALL
    SELECT 'A103', 'Workshopraum 3', 'Hauptgebäude', 35, 1 UNION ALL
    SELECT 'A104', 'Workshopraum 4', 'Hauptgebäude', 30, 1 UNION ALL
    SELECT 'B201', 'Aula Nord', 'Nebengebäude', 40, 2 UNION ALL
    SELECT 'B202', 'Aula Süd', 'Nebengebäude', 40, 2 UNION ALL
    SELECT 'C301', 'Seminarraum 1', 'Altbau', 20, 3 UNION ALL
    SELECT 'C302', 'Seminarraum 2', 'Altbau', 20, 3
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM rooms LIMIT 1);

-- 5. Klassen für bestehende Schüler setzen (Beispiel)
UPDATE users SET class = '10A' WHERE username = 'max.mueller' AND class IS NULL;
UPDATE users SET class = '10B' WHERE username = 'anna.schmidt' AND class IS NULL;
UPDATE users SET class = '11A' WHERE username = 'tom.weber' AND class IS NULL;

-- Fertig!
SELECT 'Update erfolgreich abgeschlossen!' as Status;
