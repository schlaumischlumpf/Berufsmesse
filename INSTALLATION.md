# Berufsmesse - Installation & Setup

## 🚀 Erste Schritte nach dem Deployment

### 1. Datenbank-Migrationen ausführen

**WICHTIG:** Nach dem ersten Deployment müssen die Datenbank-Migrationen ausgeführt werden!

#### Option A: Automatisches Setup (Empfohlen)

1. Als Administrator einloggen
2. Im Browser aufrufen: `http://ihre-domain.de/setup.php`
3. Das Setup-Skript führt alle notwendigen Migrationen automatisch aus
4. Nach erfolgreichem Abschluss kann `setup.php` aus Sicherheitsgründen gelöscht werden

#### Option B: Manuelle Migration

Führen Sie die SQL-Befehle aus `migrations.sql` in Ihrer Datenbank aus:

```bash
mysql -u username -p berufsmesse < migrations.sql
```

### 2. Durchgeführte Änderungen

Das Setup fügt folgende Datenbank-Änderungen hinzu:

1. **exhibitors.visible_fields** (JSON)
   - Spalte zur Steuerung der Feldvisibilität für Schüler

2. **room_slot_capacities** (Tabelle)
   - Slot-spezifische Raumkapazitäten

3. **user_permissions** (Tabelle)
   - Granulares Berechtigungssystem

4. **users.email** (VARCHAR)
   - E-Mail-Spalte für Benutzer

### 3. Fehlerbehebung

#### Fehler: "Table 'berufsmesse.user_permissions' doesn't exist"

**Ursache:** Die Datenbank-Migrationen wurden nicht ausgeführt.

**Lösung:** 
1. Rufen Sie `setup.php` im Browser auf (als Admin eingeloggt)
2. ODER führen Sie `migrations.sql` manuell aus

#### Fehler: "Column 'email' not found"

**Ursache:** Die users.email Spalte wurde nicht hinzugefügt.

**Lösung:** 
1. Führen Sie `setup.php` aus
2. ODER führen Sie manuell aus:
   ```sql
   ALTER TABLE users ADD COLUMN email VARCHAR(255) DEFAULT NULL AFTER username;
   ```

#### Fehler: "Column 'visible_fields' not found"

**Ursache:** Die exhibitors.visible_fields Spalte wurde nicht hinzugefügt.

**Lösung:**
1. Führen Sie `setup.php` aus
2. ODER führen Sie manuell aus:
   ```sql
   ALTER TABLE exhibitors ADD COLUMN visible_fields JSON DEFAULT NULL;
   ```

## 📋 Neue Features

### Admin-Bereich

1. **QR-Code Generator** (`?page=admin-settings`)
   - Konfigurierbarer QR-Code für Vor-Ort-Anmeldung
   - Download in verschiedenen Größen

2. **Nutzerverwaltung** (`?page=admin-users`)
   - Benutzer erstellen/löschen
   - Passwörter zurücksetzen
   - Unterstützt: Admin, Lehrer, Schüler

3. **Slot-Kapazitäten** (`?page=admin-room-capacities`)
   - Individuelle Kapazitäten pro Raum und Zeitslot
   - Ersetzt feste Kapazitätsdivision

4. **Berechtigungen** (`?page=admin-permissions`)
   - 6 granulare Berechtigungen
   - Nur für Admins und spezielle Rollen

5. **Druckfunktion** (`?page=admin-print`)
   - 3 Ansichten: Gesamt, Klasse, Raum
   - Druckoptimiertes Layout

6. **Aussteller-Sichtbarkeit** (`?page=admin-exhibitors`)
   - 8 konfigurierbare Felder pro Aussteller
   - Kontaktdaten standardmäßig ausgeblendet

### Lehrer-Bereich

1. **Lehrer-Dashboard** (`?page=teacher-dashboard`)
   - Klassenübersicht mit Statistiken
   - Schnellzugriff auf Klassenlisten

2. **Klassenlisten** (`?page=teacher-class-list`)
   - Detaillierte Schüleransicht pro Klasse
   - Status-Tracking (vollständig/unvollständig)

## 🔐 Sicherheit

Nach dem Setup empfohlen:

1. ✅ `setup.php` löschen (oder Zugriff beschränken)
2. ✅ Alle Admin-Passwörter ändern
3. ✅ HTTPS in Production verwenden
4. ✅ Datenbank-Backups einrichten

## 📞 Support

Bei Problemen siehe `CHANGES.md` für detaillierte Dokumentation aller Features.

### Verfügbare Berechtigungen

- `manage_exhibitors` - Aussteller verwalten
- `manage_rooms` - Räume verwalten
- `manage_settings` - Einstellungen ändern
- `manage_users` - Benutzer verwalten
- `view_reports` - Berichte ansehen
- `auto_assign` - Auto-Zuteilung nutzen

## ✅ Checkliste nach Installation

- [ ] `setup.php` ausgeführt
- [ ] Alle Migrations erfolgreich
- [ ] Admin-Login funktioniert
- [ ] QR-Code URL konfiguriert
- [ ] Einschreibezeiten gesetzt
- [ ] Aussteller angelegt
- [ ] Räume zugewiesen
- [ ] Testnutzer erstellt
- [ ] Auto-Assign getestet
- [ ] Druckfunktion getestet
- [ ] `setup.php` gelöscht (optional)
