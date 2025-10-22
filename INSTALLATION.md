# Installation & Update Anleitung

## Für neue Installationen

1. Öffnen Sie **phpMyAdmin** in Ihrem Browser (z.B. `http://localhost/phpmyadmin`)
2. Erstellen Sie eine neue Datenbank namens `berufsmesse`
3. Wählen Sie die Datenbank aus
4. Klicken Sie auf den Tab **"SQL"**
5. Öffnen Sie die Datei `database.sql` und kopieren Sie den gesamten Inhalt
6. Fügen Sie den Inhalt in das SQL-Fenster ein und klicken Sie auf **"Ausführen"**

## Für bestehende Installationen (Update)

**⚠️ WICHTIG: Wenn Sie den Fehler "Column not found: room_id" erhalten, führen Sie diese Schritte aus:**

1. Öffnen Sie **phpMyAdmin** in Ihrem Browser
2. Wählen Sie Ihre `berufsmesse` Datenbank aus
3. Klicken Sie auf den Tab **"SQL"**
4. Öffnen Sie die Datei `database_update.sql` und kopieren Sie den gesamten Inhalt
5. Fügen Sie den Inhalt in das SQL-Fenster ein und klicken Sie auf **"Ausführen"**

### Was wird aktualisiert?

Das Update-Skript (`database_update.sql`) fügt folgende neue Funktionen hinzu:

- ✅ **Klassenfeld** für Benutzer (z.B. "10a", "11b")
- ✅ **Raum-System** mit Raumverwaltung
- ✅ **Raum-Zuteilung** für Aussteller
- ✅ **Beispiel-Räume** werden automatisch eingefügt

### Sicherheitshinweis

Das Update-Skript verwendet `CREATE TABLE IF NOT EXISTS` und `ALTER TABLE ... ADD COLUMN IF NOT EXISTS`, sodass es:
- ✅ Sicher auf bestehenden Datenbanken ausgeführt werden kann
- ✅ Keine Daten löscht oder überschreibt
- ✅ Nur fehlende Spalten und Tabellen hinzufügt

## Konfiguration

Stellen Sie sicher, dass die Datenbankverbindung in `config/database.php` korrekt konfiguriert ist:

```php
$host = 'localhost';
$dbname = 'berufsmesse';
$username = 'root';
$password = '';
```

## Standard-Login nach Installation

Nach der Installation können Sie sich mit folgenden Daten anmelden:

**Administrator:**
- E-Mail: `admin@schule.de`
- Passwort: `admin123`

**Test-Schüler:**
- E-Mail: `max.mustermann@schule.de`
- Passwort: `schueler123`

**⚠️ WICHTIG:** Ändern Sie diese Passwörter nach der ersten Anmeldung!

## Probleme?

Falls nach dem Update noch Fehler auftreten:

1. Leeren Sie den Browser-Cache (Strg + F5)
2. Überprüfen Sie, ob alle SQL-Befehle erfolgreich ausgeführt wurden
3. Kontrollieren Sie in phpMyAdmin:
   - Tabelle `rooms` existiert
   - Tabelle `exhibitors` hat die Spalte `room_id`
   - Tabelle `users` hat die Spalte `class`

## Design-Update

Die Anwendung wurde mit folgenden Design-Verbesserungen aktualisiert:

### ✅ Farben zurück
- Statistik-Karten nutzen nun farbige Gradienten (Blau, Lila, Grün, Rot)
- Tab-Navigation verwendet blaue Akzente
- Admin-Menü ist in Kategorien strukturiert

### ✅ Keine Schatten mehr
- Alle Boxen und Karten sind jetzt **gefüllt ohne Schatten**
- Fokus auf klare Abgrenzungen durch farbige Linien (border-l-4)
- Moderneres, flacheres Design

### ✅ Strukturiertes Admin-Menü
Das Admin-Menü ist jetzt in 3 Bereiche gegliedert:
1. **Übersicht** - Dashboard mit Statistiken
2. **Verwaltung** - Aussteller und Räume
3. **System** - Einstellungen

### ✅ Erweitertes Dashboard
Das Admin-Dashboard hat jetzt 5 Tabs:
1. **Statistiken** - Übersicht und Charts
2. **Anmeldungen** - Neueste Registrierungen
3. **Benutzer** - Suchfunktion mit Filtern
4. **Aussteller** - Übersicht mit Anmeldezahlen
5. **Räume** - Raumübersicht mit Belegung

---

**Viel Erfolg mit Ihrer Berufsmesse-Anwendung! 🎓**
