# 🚀 SCHNELLSTART-ANLEITUNG

## Installation in 5 Schritten:

### 1️⃣ XAMPP starten
- Öffnen Sie das XAMPP Control Panel
- Starten Sie **Apache**
- Starten Sie **MySQL**

### 2️⃣ Datenbank erstellen
- Öffnen Sie: http://localhost/phpmyadmin
- Klicken Sie links auf **"Neu"**
- Datenbankname: **berufsmesse**
- Kollation: **utf8mb4_unicode_ci**
- Klicken Sie auf **"Anlegen"**

### 3️⃣ Datenbank importieren
- Klicken Sie auf die Datenbank **berufsmesse**
- Gehen Sie zum Tab **"SQL"**
- Öffnen Sie die Datei **database.sql** in einem Texteditor
- Kopieren Sie den gesamten Inhalt
- Fügen Sie ihn in das SQL-Feld ein
- Klicken Sie auf **"OK"**

### 4️⃣ Anwendung öffnen
- Öffnen Sie Ihren Browser
- Geben Sie ein: **http://localhost/berufsmesse/**
- Sie sollten die Login-Seite sehen

### 5️⃣ Anmelden
**Als Admin:**
- Benutzername: `admin`
- Passwort: `admin123`

**Als Schüler (Test):**
- Benutzername: `max.mueller`
- Passwort: `student123`

---

## ✅ Funktionstest

Nach der Anmeldung sollten Sie:
- ✅ Das Dashboard sehen
- ✅ Die Sidebar auf der linken Seite sehen
- ✅ Zwischen verschiedenen Seiten wechseln können

**Als Admin zusätzlich:**
- ✅ Zugriff auf Admin-Bereich haben
- ✅ Aussteller verwalten können
- ✅ Einstellungen ändern können

---

## 🆘 Probleme?

### Fehler: "Datenbankverbindung fehlgeschlagen"
**Lösung:**
1. Prüfen Sie ob MySQL in XAMPP läuft
2. Öffnen Sie `config.php`
3. Prüfen Sie die Datenbankdaten:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');  // Standard: leer
   define('DB_NAME', 'berufsmesse');
   ```

### Fehler: "Page not found" / Seite nicht gefunden
**Lösung:**
- Prüfen Sie ob die Dateien im richtigen Verzeichnis sind: `C:\xampp\htdocs\berufsmesse\`
- Prüfen Sie die URL: `http://localhost/berufsmesse/` (mit Slash am Ende)

### Upload funktioniert nicht
**Lösung:**
1. Erstellen Sie das Verzeichnis `uploads/` falls nicht vorhanden
2. Rechtklick auf den Ordner → Eigenschaften → Sicherheit
3. Geben Sie "Vollzugriff" für alle Benutzer

### Design wird nicht angezeigt
**Lösung:**
- Prüfen Sie Ihre Internetverbindung (TailwindCSS wird von CDN geladen)
- Alternativ: Laden Sie TailwindCSS lokal herunter

---

## 📱 Erste Schritte

### Als Administrator:

1. **Aussteller hinzufügen**
   - Gehen Sie zu "Aussteller verwalten"
   - Klicken Sie auf "Neuer Aussteller"
   - Füllen Sie das Formular aus
   - Speichern Sie

2. **Einschreibezeitraum festlegen**
   - Gehen Sie zu "Einstellungen"
   - Setzen Sie Start- und Enddatum
   - Speichern Sie

3. **Dokumente hochladen**
   - Gehen Sie zu "Aussteller verwalten"
   - Klicken Sie bei einem Aussteller auf "Dokumente"
   - Laden Sie Dateien hoch

### Als Schüler:

1. **Aussteller ansehen**
   - Klicken Sie auf "Aussteller"
   - Durchsuchen Sie die Karten
   - Klicken Sie für Details

2. **Einschreiben**
   - Klicken Sie auf "Einschreibung"
   - Wählen Sie einen Aussteller
   - Klicken Sie auf "Einschreiben"

3. **Anmeldungen prüfen**
   - Klicken Sie auf "Meine Anmeldungen"
   - Sehen Sie Ihre gebuchten Termine

---

## 🎯 Wichtige Funktionen

### Automatische Slot-Verteilung
- Schüler werden automatisch dem Slot mit den wenigsten Teilnehmern zugewiesen
- Gewährleistet gleichmäßige Verteilung
- Keine manuelle Slot-Auswahl nötig

### Automatische Zuteilung (Admin)
- Schüler ohne Anmeldung werden automatisch zugeteilt
- Findet unterbesetzte Aussteller
- Ein Klick im Admin-Dashboard: "Auto-Zuteilung"

### Responsive Design
- Funktioniert auf Desktop, Tablet und Handy
- Mobile Sidebar über Hamburger-Menü
- Touch-optimiert

---

## 📞 Support

Bei Problemen:
1. Lesen Sie die vollständige **README.md**
2. Prüfen Sie die **Fehlermeldungen** im Browser
3. Prüfen Sie die **PHP-Fehler** in: `C:\xampp\apache\logs\error.log`

---

**Viel Erfolg! 🎓**
