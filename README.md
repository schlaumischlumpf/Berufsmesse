# Berufsmesse Verwaltungssystem

Eine moderne Webanwendung zur Verwaltung und Zuteilung von Schülern zu Ausstellern auf einer Berufsmesse.

## 🎯 Features

### Für Schüler:
- ✅ Sichere Anmeldung mit Benutzername und Passwort
- 📋 Übersicht aller Aussteller im modernen Card-Design
- 🔍 Detailansicht mit Informationen, Dokumenten und Kontaktdaten
- ✏️ Einschreibung für Aussteller mit automatischer Slot-Verteilung
- 📊 Übersicht der eigenen Anmeldungen
- 📱 Voll responsive für Desktop, Tablet und Mobile

### Für Administratoren:
- 📊 Umfangreiches Dashboard mit Statistiken
- ➕ Aussteller hinzufügen, bearbeiten und löschen
- 📄 Dokumente für Aussteller hochladen und verwalten
- ⚙️ Einstellungen für Einschreibezeitraum konfigurieren
- 🤖 Automatische Zuteilung von Schülern ohne Anmeldung
- 📈 Echtzeit-Statistiken und Übersichten

### Technische Features:
- 🎨 Modernes Design mit TailwindCSS
- ✨ Smooth Animationen und Übergänge
- 🔒 Sicheres Session-Management
- 💾 MySQL Datenbank
- 📱 Mobile-First Responsive Design
- ⚡ Schnelle Performance

## 📋 Voraussetzungen

- XAMPP (oder ähnlicher LAMP/WAMP Stack)
  - Apache Webserver
  - PHP 7.4 oder höher
  - MySQL 5.7 oder höher
- Moderner Webbrowser (Chrome, Firefox, Safari, Edge)

## 🚀 Installation

### 1. XAMPP starten
- Starten Sie Apache und MySQL über das XAMPP Control Panel

### 2. Datenbank einrichten
1. Öffnen Sie phpMyAdmin: `http://localhost/phpmyadmin`
2. Erstellen Sie eine neue Datenbank namens `berufsmesse`
3. Importieren Sie die Datei `database.sql`:
   - Klicken Sie auf die Datenbank `berufsmesse`
   - Gehen Sie zum Tab "SQL"
   - Kopieren Sie den Inhalt von `database.sql` und führen Sie ihn aus
   - ODER: Nutzen Sie den Import-Tab und laden Sie die `database.sql` Datei hoch

### 3. Konfiguration anpassen (optional)
Öffnen Sie `config.php` und passen Sie bei Bedarf folgende Einstellungen an:
- `DB_HOST` - Datenbank Host (Standard: localhost)
- `DB_USER` - Datenbank Benutzer (Standard: root)
- `DB_PASS` - Datenbank Passwort (Standard: leer)
- `DB_NAME` - Datenbank Name (Standard: berufsmesse)

### 4. Upload-Verzeichnis
Das Upload-Verzeichnis wird automatisch erstellt. Stellen Sie sicher, dass der Webserver Schreibrechte hat:
```
chmod 777 uploads/
```

### 5. Anwendung aufrufen
Öffnen Sie Ihren Browser und navigieren Sie zu:
```
http://localhost/berufsmesse/
```

## 🔐 Standard-Zugangsdaten

### Administrator
- **Benutzername:** admin
- **Passwort:** admin123

### Test-Schüler
- **Benutzername:** max.mueller
- **Passwort:** student123

- **Benutzername:** anna.schmidt
- **Passwort:** student123

- **Benutzername:** tom.weber
- **Passwort:** student123

> ⚠️ **Wichtig:** Ändern Sie diese Passwörter nach der ersten Anmeldung!

## 📖 Benutzung

### Als Schüler:

1. **Anmelden**
   - Geben Sie Ihren Benutzername und Passwort ein
   - Klicken Sie auf "Anmelden"

2. **Aussteller durchsuchen**
   - Klicken Sie in der Sidebar auf "Aussteller"
   - Durchsuchen Sie die verfügbaren Aussteller
   - Klicken Sie auf eine Card für mehr Informationen

3. **Für Aussteller einschreiben**
   - Klicken Sie in der Sidebar auf "Einschreibung"
   - Wählen Sie einen Aussteller aus
   - Klicken Sie auf "Einschreiben"
   - Das System verteilt Sie automatisch gleichmäßig auf die Zeitslots

4. **Anmeldungen ansehen**
   - Klicken Sie in der Sidebar auf "Meine Anmeldungen"
   - Sehen Sie alle Ihre gebuchten Termine

### Als Administrator:

1. **Dashboard**
   - Übersicht über alle Statistiken
   - Letzte Anmeldungen
   - Beliebte Aussteller

2. **Aussteller verwalten**
   - Neue Aussteller hinzufügen
   - Bestehende Aussteller bearbeiten
   - Dokumente hochladen
   - Aussteller löschen

3. **Einstellungen**
   - Einschreibezeitraum festlegen
   - Veranstaltungsdatum einstellen
   - Maximale Einschreibungen konfigurieren

4. **Automatische Zuteilung**
   - Klicken Sie im Dashboard auf "Auto-Zuteilung"
   - Das System teilt alle Schüler ohne Anmeldung automatisch zu
   - Schüler werden gleichmäßig auf unterbesetzte Aussteller verteilt

## 🎨 Design & Responsive

Die Anwendung nutzt TailwindCSS und ist vollständig responsive:

- **Desktop** (≥1024px): Volle Sidebar, Multi-Column Layouts
- **Tablet** (768px - 1023px): Optimierte Layouts, ausklappbare Sidebar
- **Mobile** (< 768px): Mobile-First Design, Touch-optimiert, Hamburger-Menü

## 🔧 Technische Details

### Dateistruktur
```
berufsmesse/
├── api/                      # API Endpunkte
│   ├── auto-assign.php      # Automatische Zuteilung
│   ├── get-exhibitor.php    # Aussteller-Details laden
│   └── get-documents.php    # Dokumente laden
├── pages/                    # Seiten-Komponenten
│   ├── exhibitors.php       # Aussteller-Übersicht
│   ├── registration.php     # Einschreibung
│   ├── my-registrations.php # Meine Anmeldungen
│   ├── admin-dashboard.php  # Admin Dashboard
│   ├── admin-exhibitors.php # Aussteller-Verwaltung
│   └── admin-settings.php   # Einstellungen
├── uploads/                  # Upload-Verzeichnis für Dokumente
├── config.php               # Konfigurationsdatei
├── functions.php            # Hilfsfunktionen
├── database.sql             # Datenbank-Schema
├── index.php                # Hauptseite
├── login.php                # Login-Seite
├── logout.php               # Logout
└── README.md                # Diese Datei
```

### Datenbank-Schema

**Tabellen:**
- `users` - Benutzer (Schüler und Admins)
- `exhibitors` - Aussteller
- `exhibitor_documents` - Dokumente der Aussteller
- `timeslots` - Zeitslots (3 fixe Slots)
- `registrations` - Anmeldungen
- `settings` - System-Einstellungen

### Sicherheit

- 🔐 Passwort-Hashing mit `password_hash()`
- 🛡️ SQL Injection Schutz via PDO Prepared Statements
- 🚫 XSS-Schutz durch `htmlspecialchars()`
- 🔒 Session-basierte Authentifizierung
- ✅ Input-Validierung und Sanitization
- 📁 Sichere Datei-Uploads mit Typ- und Größenprüfung

## 🤝 Support & Wartung

### Neue Benutzer hinzufügen
Führen Sie in phpMyAdmin aus:
```sql
-- Neuer Schüler (Passwort: student123)
INSERT INTO users (username, password, firstname, lastname, role) VALUES
('neuer.schueler', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Vorname', 'Nachname', 'student');
```

### Passwort zurücksetzen
```sql
-- Passwort auf "neupass123" setzen
UPDATE users SET password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' WHERE username = 'admin';
```

### Datenbank-Backup
Exportieren Sie regelmäßig die Datenbank über phpMyAdmin:
1. Wählen Sie die Datenbank `berufsmesse`
2. Klicken Sie auf "Exportieren"
3. Wählen Sie "Schnell" und "SQL"
4. Klicken Sie auf "OK"

## 📝 Changelog

### Version 1.0.0 (2025-10-18)
- ✨ Initiale Version
- ✅ Login-System
- ✅ Aussteller-Verwaltung
- ✅ Einschreibungssystem
- ✅ Automatische Slot-Verteilung
- ✅ Admin-Dashboard
- ✅ Responsive Design
- ✅ Automatische Zuteilung

## 📄 Lizenz

Diese Software wurde für schulische Zwecke entwickelt.

## 👨‍💻 Entwickler

Entwickelt mit ❤️ für die Berufsmesse

---

**Viel Erfolg bei Ihrer Berufsmesse! 🎓**
