# Agent Logs — Implementierung Multi-Schulen, Aussteller-Accounts, Darkmode, QR-Kamera, Mobile-Fix

**Basiert auf:** `agent_instructions.md`  
**Startdatum:** 2026-03-13

---

## Übersicht der Phasen

| Phase | Beschreibung | Status |
|-------|-------------|--------|
| 1 | Datenbank & Grundgerüst | ⏳ In Arbeit |
| 2 | Multi-Schulen-Kern | ⬜ Ausstehend |
| 3 | Aussteller-Accounts | ⬜ Ausstehend |
| 4 | Darkmode | ⬜ Ausstehend |
| 5 | QR-Kamera + Bugfix | ⬜ Ausstehend |

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

