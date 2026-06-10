# Agent Logs — Implementierung Multi-Schulen, Aussteller-Accounts, Darkmode, QR-Kamera, Mobile-Fix

**Basiert auf:** `agent_instructions.md`  
**Startdatum:** 2026-03-13

---

## Übersicht der Phasen

| Phase | Beschreibung | Status |
|-------|-------------|--------|
| 1 | Datenbank & Grundgerüst | ✅ Abgeschlossen |
| 2 | Multi-Schulen-Kern | ✅ Abgeschlossen |
| 3 | Aussteller-Accounts | ✅ Abgeschlossen |
| 4 | Darkmode | ✅ Abgeschlossen |
| 5 | QR-Kamera + Bugfix | ✅ Abgeschlossen |

---

## Änderungsprotokoll

### Phase 1: Datenbank & Grundgerüst

#### 1.1 `update_schema.sql` — Migration 19: Multi-Schulen
- ✅ Neue Tabelle `schools` mit slug, logo, address, contact_email, is_active
- ✅ Standardschule (`slug=standard`) wird automatisch angelegt
- ✅ `school_id` Spalte zu `messe_editions`, `users`, `settings`, `announcements` hinzugefügt
- ✅ Bestehende Daten der Standardschule (id=1) zugeordnet
- ✅ Neue Tabelle `exhibitor_users` (N:M Verknüpfung User ↔ Aussteller)
- ✅ Neue Tabelle `equipment_options` (Ausstattungsoptionen pro Schule)
- ✅ Neue Tabelle `exhibitor_equipment_requests` (Ausstattungsanfragen)
- ✅ UNIQUE-Constraints: `unique_username_school`, `unique_key_school`

#### 1.2 `.htaccess` — URL-Rewriting
- ✅ `RewriteEngine On` aktiviert
- ✅ Statische Assets (`assets/`, `uploads/`, `fpdf/`) durchlassen
- ✅ Root (`/`) → `schools.php` (Landingpage)
- ✅ `/{slug}/index.php` → `index.php?school_slug={slug}`
- ✅ `/{slug}/login.php` → `login.php?school_slug={slug}`
- ✅ `/{slug}/api/*.php` → `api/*.php?school_slug={slug}`
- ✅ Kurzform `/{slug}/` → `index.php?school_slug={slug}`

#### 1.3 `functions.php` — Neue Multi-School-Funktionen
- ✅ `getCurrentSchool()` — Schule aus URL/Session laden
- ✅ `getActiveEditionIdForSchool($schoolId)` — Edition pro Schule
- ✅ `schoolUrl($path)` — Schulspezifische URL generieren
- ✅ `hasSchoolAccess()` — Zugriffsrechte prüfen (Admin/Aussteller/Schüler)
- ✅ `isSchoolAdmin()` — Schul-Admin Rollencheck
- ✅ `isExhibitor()` — Aussteller Rollencheck
- ✅ `requireSchoolAdminOrAdmin()` — Admin oder Schul-Admin erforderlich
- ✅ `getExhibitorIdsForUser($userId)` — Aussteller-IDs eines Users
- ✅ `generateSchoolSlug($name)` — Slug-Generierung mit Eindeutigkeit

#### 1.4 `config.php` — Kamera-Permission
- ✅ `camera=()` → `camera=(self)` geändert (für QR-Scanner)

---

### Phase 2: Multi-Schulen-Kern

#### 2.1 `schools.php` — Landingpage
- ✅ Neue Datei erstellt: Listet alle aktiven Schulen mit Logo, Name, Adresse
- ✅ Link zum globalen Admin/Aussteller-Login
- ✅ Responsive Grid-Layout

#### 2.2 `login.php` — Schulspezifischer Login
- ✅ `getCurrentSchool()` Integration: Schulkontext aus URL laden
- ✅ Login-Query angepasst: schulspezifisch vs. global
- ✅ `school_id` wird in Session gespeichert
- ✅ Post-Login-Redirect berücksichtigt Schulkontext
- ✅ Titel zeigt Schulnamen an

#### 2.3 `index.php` — Routing + Navigation
- ✅ Neue Routes: `admin-schools`, `admin-equipment`, `exhibitor-*` (5 Seiten)
- ✅ Exhibitor-Navigation in Sidebar
- ✅ Admin-Schools Navigation Link
- ✅ Darkmode-Toggle in Sidebar
- ✅ Darkmode JS eingebunden (vor Content, Flash-Vermeidung)
- ✅ Fallback-Dashboard für Aussteller: `exhibitor-dashboard.php`

#### 2.4 `pages/admin-schools.php` — Schulverwaltung
- ✅ CRUD: Erstellen, Bearbeiten, (De)Aktivieren
- ✅ Tabelle: Name, Slug, Kontakt, Editionen-Anzahl, Status, Aktionen
- ✅ Audit-Logging bei Statusänderungen

---

### Phase 3: Aussteller-Accounts

#### 3.1 `pages/exhibitor-dashboard.php`
- ✅ Statistik-Karten: Unternehmen, Schulen, Anmeldungen
- ✅ Gruppierung nach Schulen
- ✅ Quick-Links zu Profil, Slots, Dokumenten pro Unternehmen

#### 3.2 `pages/exhibitor-slots.php`
- ✅ Slot-Übersicht mit registrierten Schülern
- ✅ Anwesenheitsstatus (Check-in ja/nein)
- ✅ Gruppierung nach Timeslot

#### 3.3 `pages/exhibitor-profile.php`
- ✅ Beschreibung und Website bearbeitbar
- ✅ Name und Raum nur lesbar (Admin-gesteuert)
- ✅ Zugriffsprüfung über `getExhibitorIdsForUser()`

#### 3.4 `pages/exhibitor-equipment.php`
- ✅ Neue Anfragen mit Dropdown + Freitext + Anzahl
- ✅ Status-Anzeige (Offen/Genehmigt/Abgelehnt)
- ✅ Löschen von offenen Anfragen

#### 3.5 `pages/exhibitor-documents.php`
- ✅ Datei-Upload mit Größen/Typ-Prüfung
- ✅ Sichtbarkeit für Schüler togglen
- ✅ Download-Link und Lösch-Funktion

#### 3.6 `pages/admin-equipment.php`
- ✅ Ausstattungsoptionen CRUD (Name, Beschreibung, Reihenfolge)
- ✅ Anfragen-Verwaltung: Genehmigen/Ablehnen
- ✅ Schulspezifisch via `getCurrentSchool()`

#### 3.7 API-Endpunkte
- ✅ `api/schools.php` — GET (Liste), POST (Erstellen)
- ✅ `api/exhibitor-equipment.php` — GET (Anfragen laden), POST (Anfrage erstellen)
- ✅ CSRF-Schutz und Zugriffsprüfung auf allen Endpunkten

---

### Phase 4: Darkmode

#### 4.1 `assets/js/darkmode.js`
- ✅ localStorage-basierte Präferenz-Speicherung
- ✅ OS-Setting als Default (`prefers-color-scheme: dark`)
- ✅ `toggleDarkmode()` global verfügbar
- ✅ Automatische Icon/Label-Aktualisierung
- ✅ Frühes Laden zur Flash-Vermeidung

#### 4.2 `assets/css/design-system.css` — Dark-Variablen
- ✅ `html.dark` Block: Invertierte Grau-Skala
- ✅ Pastellfarben abgedunkelt
- ✅ Schatten für dunklen Hintergrund angepasst
- ✅ Backgrounds, Borders, Text-Farben überschrieben
- ✅ Sidebar, Tables, Modals, Status-Badges gestylt

#### 4.3 Toggle in Navigation
- ✅ Button `#darkmode-toggle` in Sidebar (vor Hilfe-Button)
- ✅ Dynamisches Icon (🌙 / ☀️) und Label (Dunkel / Hellmodus)

---

### Phase 5: QR-Kamera + Bugfix

#### 5.1 `assets/js/qr-camera.js`
- ✅ Kamera-API mit `facingMode: 'environment'` (Rückkamera)
- ✅ `jsQR`-Integration für Frame-Analyse
- ✅ Scan-Overlay mit Corner-Markern
- ✅ Duplikat-Schutz (3-Sekunden-Cooldown)
- ✅ `QRCameraScanner.init()` / `QRCameraScanner.stop()` API

#### 5.2 `pages/qr-checkin.php` — Kamera-Modus
- ✅ Kamera-Toggle-Button (Start/Stopp)
- ✅ Visuelles Feedback bei Scan
- ✅ Automatische Token-Extraktion aus URLs
- ✅ jsQR CDN eingebunden
- ✅ Fallback auf manuelle Eingabe bleibt erhalten

#### 5.3 `pages/dashboard.php` — Tagesplan Mobile-Fix
- ✅ `truncate` → `break-words leading-tight` (Textumbruch statt Abschneiden)
- ✅ Responsive Gaps: `gap-2 sm:gap-4`
- ✅ Responsive Padding: `p-3 sm:p-4`
- ✅ Icon auf Mobile ausgeblendet: `hidden sm:flex`
- ✅ Responsive Font-Größen: `text-xs sm:text-sm`
- ✅ Slot-Badge kompakt auf Mobile: nur Nummer, "Slot X" ab sm
- ✅ Pausen-Items ebenfalls responsiv gemacht

---

### Phase 6: Bugfix — Schulfilter für Super-Admins in Einschreibungsverwaltung

#### 6.1 `pages/admin-registrations.php` — Schulfilter für alle Admin-Rollen

**Problem:** `$regSchoolId` wurde nur für `school_admin`-Rollen gesetzt. Super-Admins (Rolle `admin`) erhielten `null` und sahen damit Einschreibungen **aller Schulen** schulübergreifend.

**Ursache:** Die Zeile
```php
$regSchoolId = isSchoolAdmin() ? ($_SESSION['school_id'] ?? null) : null;
```
ließ Super-Admins ohne Schulfilter — weder beim Laden der Schüler noch beim Anzeigen der Einschreibungen.

**Lösung:** `$regSchoolId` wird jetzt primär über `getCurrentSchool()` (URL-Kontext, d. h. den aktuellen `/{school-slug}/`-Pfad) ermittelt. Als Fallback dient weiterhin `$_SESSION['school_id']` für Schul-Admins. Dadurch sind Super-Admins auf den Schulkontext der aktuellen URL beschränkt — genau wie Schul-Admins.

```php
$currentSchool = getCurrentSchool();
$regSchoolId = $currentSchool ? (int)$currentSchool['id'] : (isSchoolAdmin() ? ($_SESSION['school_id'] ?? null) : null);
```

- ✅ Schüler-Dropdown nur noch mit Schülern der aktuellen Schule
- ✅ Einschreibungsliste gefiltert nach aktueller Schule
- ✅ Schulzugehörigkeitsprüfung bei Anmelden/Abmelden gilt nun auch für Super-Admins


---

# QR-Check-in 2.0, Tour-Neubau & UI-Refresh (Start: 2026-06-10)

**Basiert auf:** Überarbeitungsplan vom 10.06.2026

| Phase | Beschreibung | Status |
|-------|-------------|--------|
| 1 | Migrations-System + QR2-Schema + Helper | ✅ Abgeschlossen & verifiziert |
| 2 | Aufsichtsplan + Check-in-Settings + Generalprobe-Reset | ✅ Abgeschlossen & verifiziert |
| 3 | Lehrer-Scanner + APIs + Schüler-QR + lokale QR-Gen | 🚧 In Arbeit |
| 4 | Live-Dashboard, Export, Benachrichtigungen, Report | ⏳ Offen |
| 5 | Tour-Engine + Rollen-Touren | ⏳ Offen |
| 6 | UI-Refresh | ⏳ Offen |

## Phase 1 — Migrations-System & QR2-Schema

- **`migrations.php`** (neu): PDO-sicherer Runner. Legt `schema_migrations` an, führt `migrations/*.sql`
  in Reihenfolge aus, überspringt bereits angewandte, bricht bei Fehler ab. Statement-Splitter ohne
  DELIMITER/Stored-Procedures (idempotenz über `IF NOT EXISTS` + `information_schema`/`PREPARE`).
- **`migrations/001_baseline.sql`** (neu): eingefrorenes Basis-Schema, aus dem realen Live-Schema
  (MariaDB 10.9) generiert und mit `CREATE TABLE IF NOT EXISTS` idempotent gemacht (27 Tabellen).
- **`migrations/002_qr2.sql`** (neu): `school_settings`, `student_qr_tokens`, `teacher_room_assignments`,
  `checkin_attempts`; `attendance` erweitert um `checkin_method`/`checked_in_by`/`actual_room_id`/`wrong_room`;
  `attendance.qr_token` vereinheitlicht auf `VARCHAR(64) NULL`.
- **`setup.php`** (umgebaut): nutzt jetzt den Migrations-Runner + idempotentes Seeding (Standardschule,
  erste Edition, Berechtigungsgruppen).
- **`functions.php`**: `getSchoolSettingDefaults()`, `getCurrentSchoolId()`, `getSchoolSetting()`,
  `updateSchoolSetting()` (Fallback school_settings → globale settings → Default).
- **Verifiziert:** Runner 2× gegen Live-DB (idempotent), Schema geprüft, Helper-Roundtrip ok.

## Phase 2 — Aufsichtsplan & Check-in-Einstellungen

- **`pages/admin-supervision.php`** (neu): Matrix Räume × Slots, Lehrer-Zuweisung pro Zelle,
  Ganztags-Schnellzuweisung, „Räume ohne Aufsicht"-Übersicht, Konfliktschutz (ein Lehrer je Slot
  nur ein Raum, DB-Constraint `uq_teacher_slot`). Permission `aufsichtsplan_sehen`/`aufsichtsplan_verwalten`.
- **`pages/admin-settings.php`**: neuer Tab „Check-in" — Modi-Toggles (Selbst-/Lehrer-Scan, pro Schule),
  Zeitfenster für Lehrer-Scans, Generalprobe-Reset (Schulname-Bestätigung) + Schüler-Token-Neugenerierung.
- **`functions.php`**: Permissions `aufsichtsplan_sehen`/`aufsichtsplan_verwalten` + Abhängigkeit registriert.
- **`index.php`**: Router-Cases + Nav-Einträge (Aufsichtsplan, Live-Anwesenheit) ergänzt.
- **Verifiziert:** Render-Test (Matrix + Check-in-Tab), Assign-POST, Konfliktablehnung, php -l aller Dateien.

## Offener Architektur-Punkt (Phase 3)

Container hat **kein GD, kein Imagick, kein Composer**. Geplante `chillerlan/php-qrcode` nicht nutzbar.
→ Lokale QR-Generierung muss dependency-frei erfolgen (z. B. gebündelter Pure-PHP-Encoder → SVG/FPDF-Rects).

## Phase 3 — Lehrer-Scanner, APIs, Schüler-QR & lokale QR-Generierung

- **Lokale QR-Generierung** (kein GD/Composer): `lib/qrcode.php` (MIT, Kazuhiko Arase, pure PHP)
  + Helper `lib/qr.php` → `qrSvg()` (Web) und `qrDrawFpdf()` (PDF, Vektor-Rechtecke).
  Ablösung von qrserver.com in `admin-qr-codes.php` + `admin-settings.php`; neuer Endpunkt
  `api/qr-image.php` (SVG). **Hinweis:** Encoder ist die Referenz-Implementierung des QR-Erfinders;
  finale Scannbarkeit sollte einmal an einem echten Gerät geprüft werden.
- **`api/teacher-checkin.php`** (neu): Lehrer-Scan/manuell, Schul-/Token-/Zeitfenster-Prüfung,
  Frei-Slot-Auto-Einschreibung (INSERT IGNORE wg. `unique_user_exhibitor`), Falsch-Raum-Bestätigung
  (`requires_confirmation` → `force`), Rate-Limiting (`checkin_attempts`), Override-Audit.
  **7 Szenarien end-to-end getestet** (registriert/already/falsch-Raum/force/Bad-Token/Frei-Slot/manuell).
- **`api/room-roster.php`** (neu): Live-Roster (anwesend/fehlt) je Raum+Slot.
- **`api/student-search.php`** (neu): lehrer-taugliche Schülersuche (school/edition-scoped).
- **`pages/teacher-scan.php`** (neu): mobil-first Scanner (jsQR + qr-camera.js), Slot-Autoerkennung,
  Raum-Override (Session), Ergebnis-Karte (grün/gelb/blau/rot), manueller Check-in, Live-Roster (Polling 15 s).
- **`api/qr-checkin.php`** (Selbst-Scan): prüft `checkin_self_scan_enabled`, schreibt `checkin_method='self_scan'` + `actual_room_id`.
- **`functions.php`**: `getOrCreateStudentToken`, `getStudentByToken`, `isCheckinRateLimited`, `recordCheckinAttempt`.
- **Schüler-QR**: Dashboard-Karte „Mein QR-Code" (`pages/dashboard.php`), persönliches PDF
  (`api/generate-personal-pdf.php`) + **Klassensatz** `api/generate-class-qr-cards.php` (Entry-Points:
  Lehrer-Klassenliste + Admin-QR-Seite). Alle QR-Anzeigen nur bei aktivem Lehrer-Check-in.
- **`index.php`**: Nav-Eintrag „Check-in Scanner" (Lehrer, wenn aktiv).
- **Verifiziert:** php -l aller Dateien, API-Szenarien, SVG- + beide PDF-Generatoren (`%PDF`), QR-Matrix.

## Phase 4 — Live-Dashboard, Export, Benachrichtigungen, Auswertung

- **`pages/admin-attendance-live.php`** (neu): Live-Dashboard mit Raum-Kacheln (Ampelfarben, Falsch-Raum-Badge),
  Slot-Auswahl (Default = aktueller Slot), Gesamtstatistik, Detail-Modal (anwesend/fehlt via `api/room-roster.php`),
  Polling alle 20 s. Button „Fehlende melden" → `notifyTeachers()`. Behebt den in Phase 2 angelegten Nav-Link.
- **`api/attendance-live.php`** (neu): JSON-Aggregat je Raum (present/expected/wrong) + Gesamtsummen.
- **`api/export-attendance.php`** (neu): CSV-Export (Methode, Uhrzeit, falscher Raum, eingecheckt von), Filter Slot/Klasse.
- **`functions.php`**: `notifyTeachers($title,$body)` — In-App-Ankündigung an Lehrkräfte (gekapselt, E-Mail später andockbar).
- **`pages/admin-attendance-report.php`** (neu): Auswertung — Quote gesamt/Slot/Klasse, Aussteller-Ranking,
  Methoden-Verteilung, Falsch-Raum-Quote, No-Show-Liste; CSV-Komplettexport.
- **`index.php`**: Nav-Eintrag „Auswertung".
- **Verifiziert (echte Daten, Edition 1):** Live-API 12 Räume 183/243 anwesend, Report-KPIs+Ranking,
  CSV 18 KB, `notifyTeachers` legt Lehrer-Ankündigung an, php -l aller Dateien.

## Phase 5 — Tour-Engine, Rollen-Touren & Fortschritt

- **`migrations/003_tour.sql`** (neu, angewandt): Tabelle `user_tour_progress` (user_id, tour_key, current_step, completed_at).
- **`api/tour-progress.php`** (neu): GET lädt / POST speichert Fortschritt (CSRF). **End-to-end getestet** (0 → Schritt 3 → completed).
- **`assets/js/tour-engine.js`** (neu): Engine mit Schritt-Typen info/action/navigate, Spotlight-Overlay,
  MutationObserver-Element-Wartelogik, **Skip-Fallback** bei fehlenden Ankern, Esc/X-Abbruch,
  nahtlose seitenübergreifende Fortsetzung (sessionStorage) + Wiederaufnahme-Banner.
- **`assets/js/tours/`** (neu): `student.js`, `teacher.js`, `school-admin.js`, `orga.js`, **`exhibitor.js`**
  (Aussteller-Tour auf Nutzerwunsch). Anker primär über vorhandene `[data-page]`-Nav + robuste IDs.
- **`assets/js/tour-selftest.js`** (neu): prüft referenzierte Anker auf der aktuellen Seite (`?tourtest=1`).
- **`assets/css/tour-engine.css`** (neu): Overlay/Spotlight/Tooltip/Banner/Toast.
- **`index.php`**: TOUR_CONFIG + Skripte eingebunden, „Hilfe & Tour"-Button auf neue Engine umgelenkt,
  Empfehlungs-Banner beim ersten Mal (schließbar), Auto-Resume. `data-tour="my-qr"` am Dashboard-QR.
- **Verifiziert:** Migration angewandt, Progress-API-Roundtrip, `node --check` aller 7 JS-Dateien, php -l.
- **Hinweis:** Das visuelle Tour-Verhalten (Spotlight, Action-Schritte, Resume) sollte im Browser geprüft werden.

## Phase 6 — UI-Refresh

Bewusst inkrementell umgesetzt (wie im Plan: „schrittweise, Seite für Seite beim Anfassen"):

- **Neue Seiten setzen den Stil-Standard:** Aufsichtsplan, Lehrer-Scanner, Live-Anwesenheit und
  Auswertung nutzen durchgehend die Design-System-Klassen (`.card`, `.btn`, `.badge`, tokenisierte
  Tailwind-Utilities) und sind **mobile-first** (Scanner-Flow am Handy optimiert).
- **Dark Mode von Anfang an:** Da `design-system.css` 189 `html.dark`-Regeln für `.card`, `bg-white`,
  `border-gray-*`, `text-gray-*`, Inputs etc. enthält, erben alle neuen Seiten Dark Mode automatisch.
  Für die neuen Tour-Komponenten wurden eigene `html.dark`-Overrides in `tour-engine.css` ergänzt.
- **Lokale, scharfe QR-Darstellung** (SVG) ersetzt die externen Pixel-Bilder von qrserver.com.
- **Bestehende Seiten:** Die Token-/Komponenten-Vereinheitlichung der Altseiten bleibt — wie im Plan
  vorgesehen — ein laufender, risikoarmer Prozess beim jeweiligen Anfassen; kein Big-Bang-Refactor,
  um Regressionen zu vermeiden.

---

## Zusammenfassung & Verifikation

Alle Code-Änderungen wurden gegen die laufende MariaDB-10.9-Instanz geprüft:
- `php -l` für **alle 24** neuen/geänderten PHP-Dateien fehlerfrei.
- `node --check` für alle 7 Tour-JS-Dateien fehlerfrei.
- Migrationen 001–003 idempotent (mehrfach ausgeführt, 0 Fehler).
- Funktionstests: Aufsichtsplan-Zuweisung+Konflikt, Lehrer-Check-in (7 Szenarien), QR-SVG+beide PDFs,
  Live-API (echte Daten), CSV-Export, `notifyTeachers`, Tour-Progress-Roundtrip.

**Noch am echten Gerät/Browser zu prüfen:** QR-Scannbarkeit mit Handykamera; visuelles Tour-Verhalten
(Spotlight, Action-Schritte, seitenübergreifende Fortsetzung).
