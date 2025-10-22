# Neue Features - Berufsmesse Verwaltung

## Übersicht der implementierten Features

### 1. ✅ Klassenfeld für Schüler
**Datenbank:**
- Neues Feld `class` (VARCHAR 50) in der `users` Tabelle
- Beispieldaten mit Klassenzuordnungen (10A, 10B, 11A)

**Formulare:**
- Registrierungsformular (`register.php`) erweitert um Klassenfeld
- Anzeige der Klasse in der Benutzerliste

---

### 2. ✅ Admin Dashboard mit Tabs und Benutzersuche

**Location:** `pages/admin-dashboard.php`

**Features:**
- **Tab 1 - Übersicht:** 
  - Statistiken (Schüler, Aussteller, Anmeldungen)
  - Beliebteste Aussteller (Top 5 Chart)
  - Verteilung nach Zeitslot (Balkendiagramm)
  - Letzte Anmeldungen (Tabelle)

- **Tab 2 - Benutzersuche:**
  - Filteroptionen:
    - Nach Name (Vor- oder Nachname)
    - Nach Klasse
    - Nach Rolle (Schüler/Admin)
    - Nach Status (mit/ohne Anmeldung)
  - Live-Suche mit Debounce
  - Anzeige von Anmeldungszahlen
  - Zusammenfassung der Suchergebnisse

**API Endpoint:** `api/search-users.php`

**Technische Details:**
- JavaScript Tab-Switching
- AJAX-basierte Suche
- Responsive Tabelle mit allen Benutzerinformationen
- Farbcodierte Status-Badges

---

### 3. ✅ Zeitplan/Kalender-Ansicht für Schüler

**Location:** `pages/schedule.php`

**Features:**
- **Kalenderansicht mit 3 Zeitslots:**
  - Slot 1: 09:00 - 10:30 Uhr (Blau)
  - Slot 2: 10:45 - 12:15 Uhr (Grün)
  - Slot 3: 13:00 - 14:30 Uhr (Orange)

- **Für jeden Slot:**
  - Aussteller-Name und Beschreibung
  - Rauminformationen (Raumnummer, Gebäude, Stockwerk)
  - Registrierungstyp (Manuell/Automatisch)
  - Call-to-Action für leere Slots

- **Zusatzfunktionen:**
  - Druckfunktion für den Zeitplan
  - Legende mit Erklärungen
  - Responsive Grid-Layout
  - Farbcodierung nach Zeitslot

**Navigation:** Neuer Menüpunkt "Zeitplan" in der Sidebar

---

### 4. ✅ Raum-Zuteilungssystem mit Drag & Drop

**Location:** `pages/admin-rooms.php`

**Datenbank:**
- Neue Tabelle `rooms`:
  - `room_number` (z.B. "A101")
  - `room_name` (z.B. "Workshopraum 1")
  - `building` (z.B. "Hauptgebäude")
  - `capacity` (Max. Personen)
  - `floor` (Stockwerk)
- Fremdschlüssel `room_id` in `exhibitors` Tabelle
- 8 Beispielräume bereits angelegt

**Features:**
- **Zwei-Spalten-Layout:**
  - Links: Nicht zugeordnete Aussteller
  - Rechts: Räume mit zugeordneten Ausstellern

- **Drag & Drop Funktionalität:**
  - Aussteller auf Räume ziehen
  - Zwischen Räumen verschieben
  - Zurück zu "nicht zugeordnet" verschieben
  - Visuelle Feedback beim Ziehen

- **Statistiken:**
  - Gesamtzahl Aussteller
  - Zugeordnete Aussteller
  - Nicht zugeordnete Aussteller

- **Aktionen:**
  - Einzelne Zuordnung entfernen
  - Alle Zuordnungen löschen
  - Ansicht aktualisieren

**API Endpoints:**
- `api/assign-room.php` - Aussteller Raum zuordnen/entfernen
- `api/clear-room-assignments.php` - Alle Zuordnungen löschen

**Technische Details:**
- HTML5 Drag & Drop API
- Echtzeit-Speicherung in Datenbank
- Toast-Benachrichtigungen bei Erfolg/Fehler
- Responsive Design

**Navigation:** Neuer Admin-Menüpunkt "Raum-Zuteilung"

---

## Installation der neuen Features

### 1. Datenbank aktualisieren
```bash
# Führen Sie die aktualisierte database.sql aus
# Diese enthält:
# - Neues 'class' Feld in users Tabelle
# - Neue 'rooms' Tabelle
# - room_id Fremdschlüssel in exhibitors
# - Beispieldaten für Räume und Klassen
```

### 2. Dateien überprüfen
Neue Dateien:
- `pages/schedule.php` - Zeitplan-Ansicht
- `pages/admin-rooms.php` - Raum-Zuteilung
- `api/search-users.php` - Benutzersuche
- `api/assign-room.php` - Raum zuordnen
- `api/clear-room-assignments.php` - Zuordnungen löschen

Geänderte Dateien:
- `database.sql` - Neue Tabellen und Felder
- `register.php` - Klassenfeld hinzugefügt
- `pages/admin-dashboard.php` - Tab-System hinzugefügt
- `index.php` - Neue Menüpunkte

### 3. Berechtigungen
Stellen Sie sicher, dass der Webserver Schreibrechte hat für:
- Datenbank-Operationen
- Keine zusätzlichen Dateiberechtigungen erforderlich

---

## Verwendung

### Für Schüler:
1. **Zeitplan ansehen:**
   - Klicken Sie auf "Zeitplan" in der Navigation
   - Sehen Sie Ihre Anmeldungen mit Rauminformationen
   - Drucken Sie den Zeitplan bei Bedarf aus

### Für Administratoren:

1. **Benutzer suchen:**
   - Admin Dashboard → Tab "Benutzersuche"
   - Filtern Sie nach Name, Klasse, Rolle oder Status
   - Sehen Sie Anmeldungszahlen auf einen Blick

2. **Räume zuteilen:**
   - Navigation → "Raum-Zuteilung"
   - Ziehen Sie Aussteller aus der linken Liste
   - Legen Sie sie in gewünschten Räumen ab
   - Zuordnung wird automatisch gespeichert

3. **Klasseninformationen verwalten:**
   - Bei Schülerregistrierung Klassenfeld ausfüllen
   - Klasse wird in Benutzerlisten angezeigt
   - Filter nach Klasse in der Benutzersuche

---

## Technische Hinweise

### Browser-Kompatibilität:
- Drag & Drop: Alle modernen Browser
- Tab-Navigation: Alle Browser
- Druckfunktion: Alle Browser

### Performance:
- Benutzersuche verwendet Debouncing (500ms)
- Raum-Zuordnungen werden sofort gespeichert
- Keine zusätzlichen Libraries erforderlich

### Sicherheit:
- Alle Admin-Seiten prüfen `isAdmin()`
- API-Endpoints validieren Benutzerrechte
- SQL-Injection-Schutz durch Prepared Statements
- XSS-Schutz durch `htmlspecialchars()`

---

## Zukünftige Erweiterungsmöglichkeiten

1. **Raum-Verwaltung:**
   - CRUD-Operationen für Räume
   - Raumkapazität vs. Anmeldungen anzeigen
   - Raumauslastung visualisieren

2. **Erweiterte Suche:**
   - Export von Benutzerlisten
   - Massen-Aktionen für Benutzer
   - Erweiterte Filter-Optionen

3. **Zeitplan:**
   - iCal/Google Calendar Export
   - QR-Code für mobilen Zugriff
   - Wegbeschreibungen zu Räumen

4. **Statistiken:**
   - Raumauslastung nach Zeitslot
   - Klassen-Statistiken
   - Heat-Map für beliebte Zeiten

---

## Support

Bei Fragen oder Problemen:
- Prüfen Sie die Browser-Konsole auf Fehler
- Stellen Sie sicher, dass die Datenbank aktualisiert wurde
- Testen Sie mit Admin- und Schüler-Accounts
- Überprüfen Sie PHP-Fehlerlog bei Serverproblemen

**Demo-Zugänge:**
- Admin: `admin` / `admin123`
- Schüler: `max.mueller` / `student123` (Klasse 10A)
- Schüler: `anna.schmidt` / `student123` (Klasse 10B)
- Schüler: `tom.weber` / `student123` (Klasse 11A)
