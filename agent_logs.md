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

