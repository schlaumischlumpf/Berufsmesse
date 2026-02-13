-- Migration: Erlaube NULL für timeslot_id und entferne UNIQUE constraint
-- Datum: 2026-01-15
-- Beschreibung: Ermöglicht Registrierungen ohne sofortige Slot-Zuteilung

-- WICHTIG: MySQL erlaubt nicht das Löschen eines Index, wenn dieser von
-- einer Fremdschlüssel-Constraint verwendet wird. Daher erstellen wir
-- zuerst einen separaten Index auf `timeslot_id`, dann entfernen wir die
-- alte UNIQUE-Constraint, ändern die Spalte und fügen die neue UNIQUE-Constraint hinzu.

-- 0. Erstelle separate Indexe, falls noch nicht vorhanden
ALTER TABLE `registrations` ADD INDEX `idx_timeslot_only` (`timeslot_id`);
ALTER TABLE `registrations` ADD INDEX `idx_user_only` (`user_id`);

-- 1. Entferne die alte UNIQUE constraint (user_id, timeslot_id)
ALTER TABLE `registrations` DROP INDEX `unique_user_timeslot`;

-- 2. Ändere timeslot_id zu NULL erlaubend
ALTER TABLE `registrations` MODIFY `timeslot_id` int(11) DEFAULT NULL;

-- 3. Füge neue UNIQUE constraint hinzu: Ein User kann sich nur einmal pro Aussteller anmelden
ALTER TABLE `registrations` ADD UNIQUE KEY `unique_user_exhibitor` (`user_id`, `exhibitor_id`);

-- 4. Optional: Füge einen Index für bessere Performance bei Slot-Queries hinzu (falls benötigt)
ALTER TABLE `registrations` ADD INDEX `idx_user_timeslot` (`user_id`, `timeslot_id`);

-- Hinweis: Einige ALTER-Befehle (ADD INDEX) schlagen fehl wenn der Index bereits existiert.
-- Bei Problemen in phpMyAdmin entferne vorher doppelte Indexe oder passe die Namen an.
