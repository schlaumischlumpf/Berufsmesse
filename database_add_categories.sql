-- Kategorie-Feld zu Aussteller-Tabelle hinzufügen
-- Datum: 2024
-- Diese Datei fügt das Kategorie-Feld zur exhibitors-Tabelle hinzu

-- Kategorie-Spalte hinzufügen (falls noch nicht vorhanden)
ALTER TABLE exhibitors 
ADD COLUMN IF NOT EXISTS category VARCHAR(100) AFTER short_description;

-- Beispiel-Kategorien für vorhandene Aussteller (optional)
-- UPDATE exhibitors SET category = 'Sonstiges' WHERE category IS NULL;

-- Informationen
SELECT 'Kategorie-Feld erfolgreich hinzugefügt!' as Status;
SELECT 'Verfügbare Kategorien:' as Info;
SELECT '- Automobilindustrie' as Kategorie
UNION SELECT '- Handwerk'
UNION SELECT '- Gesundheitswesen'
UNION SELECT '- IT & Software'
UNION SELECT '- Dienstleistung'
UNION SELECT '- Öffentlicher Dienst'
UNION SELECT '- Bildung'
UNION SELECT '- Gastronomie & Hotellerie'
UNION SELECT '- Handel & Verkauf'
UNION SELECT '- Sonstiges';
