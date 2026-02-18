-- Migration: Priority-Spalte für Registrierungen
-- Datum: 2026-02-18
-- Beschreibung: Fügt eine priority-Spalte zur registrations-Tabelle hinzu,
--              um bei der automatischen Zuteilung Priorisierung zu ermöglichen

-- ===========================================================================
-- Füge priority-Spalte hinzu
-- ===========================================================================
ALTER TABLE `registrations` 
ADD COLUMN IF NOT EXISTS `priority` INT DEFAULT 2 
COMMENT 'Priorität der Anmeldung (1=hoch, 2=normal, 3=niedrig)';

-- ===========================================================================
-- Optional: Index für bessere Performance bei Sortierung nach Priorität
-- ===========================================================================
ALTER TABLE `registrations` 
ADD INDEX IF NOT EXISTS `idx_priority` (`priority`);

-- ===========================================================================
-- Hinweise zur Verwendung:
-- ===========================================================================
-- - Standardwert ist 2 (normale Priorität)
-- - Niedrigere Werte = höhere Priorität (1 wird zuerst zugewiesen)
-- - Die automatische Zuteilung sortiert nach: priority ASC, registered_at ASC
-- - Bereits existierende Registrierungen erhalten automatisch den Wert 2
