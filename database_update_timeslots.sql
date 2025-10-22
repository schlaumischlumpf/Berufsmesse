-- Update für neue 5-Slot-Struktur
-- Führen Sie dies aus, um von alten auf neue 5 Zeitslots zu wechseln

USE berufsmesse;

-- WICHTIG: Alle bestehenden Zeitslots löschen
-- Registrierungen werden durch ON DELETE CASCADE automatisch gelöscht
DELETE FROM timeslots;

-- AUTO_INCREMENT zurücksetzen
ALTER TABLE timeslots AUTO_INCREMENT = 1;

-- Neue 5 Zeitslots einfügen
-- Slot 1, 3, 5: Feste Zuteilung (automatisch) 
-- Slot 2, 4: Freie Wahl vor Ort (nicht in System)
INSERT INTO timeslots (slot_number, slot_name, start_time, end_time) VALUES
(1, 'Slot 1 (Feste Zuteilung)', '09:00:00', '09:30:00'),
(2, 'Slot 2 (Freie Wahl)', '09:40:00', '10:10:00'),
(3, 'Slot 3 (Feste Zuteilung)', '10:40:00', '11:10:00'),
(4, 'Slot 4 (Freie Wahl)', '11:20:00', '11:50:00'),
(5, 'Slot 5 (Feste Zuteilung)', '12:20:00', '12:50:00');

-- Max Anmeldungen auf 3 setzen (nur für Slot 1, 3, 5)
UPDATE settings SET setting_value = '3' WHERE setting_key = 'max_registrations_per_student';

SELECT 'Zeitslots erfolgreich auf 5-Slot-System aktualisiert!' AS Status;
SELECT 'WICHTIG: Alle alten Registrierungen wurden gelöscht!' AS Hinweis;
SELECT 'Slot 2 und 4 sind zur freien Wahl vor Ort und werden NICHT im System verwaltet.' AS Info;
