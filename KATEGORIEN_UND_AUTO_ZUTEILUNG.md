# Neue Features - Kategorien & Auto-Zuteilung

## Zusammenfassung

Diese Aktualisierung fügt zwei wichtige Features hinzu:

### 1. ✅ Aussteller-Kategorien
- **Kategorien**: Automobilindustrie, Handwerk, Gesundheitswesen, IT & Software, Dienstleistung, Öffentlicher Dienst, Bildung, Gastronomie & Hotellerie, Handel & Verkauf, Sonstiges
- **Admin**: Kategorie-Auswahl beim Hinzufügen/Bearbeiten von Ausstellern
- **Schüler**: Such- und Filterfunktion nach Name und Kategorie

### 2. ✅ Automatische Zuteilung
- **Zweck**: Schüler mit unvollständigen Registrierungen automatisch verteilen
- **Algorithmus**: Aussteller mit wenigsten Teilnehmern werden bevorzugt
- **Sicherheit**: Kapazitätsprüfung, keine Doppelzuweisungen

---

## Installation

### Neue Installation
Verwende die aktualisierte `database.sql` - alles ist enthalten.

### Bestehende Installation
**WICHTIG**: Führe dieses SQL-Script aus:
```sql
-- In phpMyAdmin oder MySQL CLI ausführen
source database_add_categories.sql
```

Oder manuell:
```sql
ALTER TABLE exhibitors 
ADD COLUMN IF NOT EXISTS category VARCHAR(100) AFTER short_description;
```

---

## Verwendung

### 📋 Admin: Kategorien vergeben

1. **Dashboard** → Tab **"Aussteller"**
2. Klick auf **"Aussteller-Verwaltung"**
3. Aussteller bearbeiten oder neu erstellen
4. **Kategorie auswählen** (Pflichtfeld)
5. Speichern

**Tipp**: Kategorisiere alle Aussteller vor der Messe!

---

### 🎯 Admin: Automatische Zuteilung starten

1. **Dashboard** → Tab **"Statistiken"**
2. Orange Box: **"Automatische Zuteilung"**
3. Button **"Jetzt ausführen"**
4. Bestätigung
5. Ergebnis wird automatisch angezeigt

**Was passiert?**
- System findet Schüler mit < 3 Registrierungen (Slots 1, 3, 5)
- Verteilt auf Aussteller mit wenigsten Teilnehmern
- Respektiert Kapazitätsgrenzen
- Vermeidet Doppelzuweisungen

**Ergebnis-Anzeige:**
- ✅ Anzahl erstellter Zuweisungen
- 📊 Betroffene Schüler
- ⚠️ Eventuelle Fehler/Warnungen

---

### 🔍 Schüler: Aussteller suchen

1. Seite **"Aussteller"**
2. **Suchfeld**: Name eingeben
3. **Kategorie-Filter**: Kategorie auswählen
4. Beide Filter kombinierbar!

**Beispiel:**
- Suche: "BMW"
- Kategorie: "Automobilindustrie"
- → Zeigt nur Automobilaussteller mit "BMW" im Namen

---

## Neue Dateien

### 📄 `database_add_categories.sql`
Update-Script für bestehende Datenbanken. Fügt das `category`-Feld hinzu.

### 📄 `api/auto-assign-incomplete.php`
API-Endpunkt für die automatische Zuteilung.

**Funktionen:**
- Findet Schüler mit unvollständigen Registrierungen
- Intelligente Verteilung nach Auslastung
- Fehlersammlung und Statistiken

---

## Geänderte Dateien

### `database.sql`
- ➕ Kategorie-Feld in `exhibitors`-Tabelle

### `pages/admin-exhibitors.php`
- ➕ Kategorie-Dropdown im Formular
- ➕ Kategorie-Badge in Aussteller-Liste
- 🔄 PHP-Code aktualisiert (INSERT/UPDATE mit category)

### `pages/admin-dashboard.php`
- ➕ Auto-Zuteilungs-Button (orange Box)
- ➕ Ergebnis-Anzeige mit Statistiken
- ➕ JavaScript-Funktion `runAutoAssign()`

### `pages/exhibitors.php`
- ➕ Filter-Sektion (Suche + Kategorie)
- ➕ Kategorie-Badge auf Aussteller-Karten
- ➕ JavaScript-Funktion `filterExhibitors()`
- ➕ Ergebnis-Info ("X Aussteller gefunden")

---

## Best Practices

### ⏰ Wann Auto-Zuteilung ausführen?
- **1-2 Tage vor der Messe**
- Nach Ablauf der Anmeldefrist
- Wenn viele Schüler unvollständige Registrierungen haben

### 📢 Kommunikation
Informiere Schüler:
- "Anmeldefrist: [Datum]"
- "Danach automatische Zuteilung"
- "Bitte alle 3 Slots selbst auswählen"

### ✅ Nach Auto-Zuteilung prüfen
1. Dashboard → Statistik-Tab
2. "Verteilung nach Zeitslot" ansehen
3. Top-Aussteller prüfen
4. Bei Bedarf manuell nachjustieren

### 🎯 Kategorien-Strategie
- Alle Aussteller kategorisieren
- "Sonstiges" nur für echte Ausnahmen
- Einheitliche Kategorien verwenden

---

## Technische Details

### Datenbank-Schema
```sql
CREATE TABLE exhibitors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    short_description VARCHAR(500),
    category VARCHAR(100),  -- NEU!
    logo VARCHAR(255),
    contact_person VARCHAR(255),
    email VARCHAR(255),
    phone VARCHAR(50),
    website VARCHAR(255),
    total_slots INT DEFAULT 25,
    room_id INT,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL
);
```

### API-Response Format
```json
{
  "success": true,
  "message": "15 Zuweisungen erfolgreich durchgeführt",
  "assigned": 15,
  "errors": [],
  "statistics": {
    "total_students": 100,
    "complete_registrations": 85,
    "incomplete_registrations": 15
  }
}
```

### Kategorie-Liste
```php
$categories = [
    'Automobilindustrie',
    'Handwerk',
    'Gesundheitswesen',
    'IT & Software',
    'Dienstleistung',
    'Öffentlicher Dienst',
    'Bildung',
    'Gastronomie & Hotellerie',
    'Handel & Verkauf',
    'Sonstiges'
];
```

---

## Fehlerbehebung

### ❌ "Kategorie-Feld nicht vorhanden"
**Lösung**: Führe `database_add_categories.sql` aus

### ❌ "Keine Aussteller gefunden" beim Filtern
**Prüfe**:
- Sind Kategorien bei Ausstellern hinterlegt?
- Ist der Filter richtig gesetzt?
- Browser-Konsole auf JS-Fehler prüfen

### ❌ Auto-Zuteilung schlägt fehl
**Prüfe**:
- Sind genug Aussteller mit freier Kapazität vorhanden?
- Sind Aussteller aktiv (`active = 1`)?
- PHP Error-Log ansehen

### ❌ "Kein verfügbarer Aussteller"
**Ursachen**:
- Alle Aussteller ausgebucht
- Alle Aussteller bereits beim Schüler registriert
- Keine aktiven Aussteller

**Lösung**:
- `total_slots` bei Ausstellern erhöhen
- Mehr Aussteller aktivieren

---

## Version

**Version**: 2.1.0  
**Datum**: 2024  
**Features**: Kategorien + Auto-Zuteilung

---

Viel Erfolg! 🚀
