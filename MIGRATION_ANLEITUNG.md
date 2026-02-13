# Datenbank-Migration ausführen

## Problem
Die Registrierung schlägt fehl, weil `timeslot_id` in der Tabelle `registrations` als NOT NULL definiert ist.

## Lösung
Führe eine der folgenden SQL-Dateien in deiner Datenbank aus:

### Option 1: Nur die neue Migration
```sql
-- Führe migration_allow_null_timeslot.sql aus
```

### Option 2: Alle Migrationen
```sql
-- Führe migrations.sql aus (enthält alle Migrationen inkl. der neuen)
```

## Was wird geändert?
1. `timeslot_id` erlaubt jetzt NULL-Werte
2. Die alte UNIQUE-Constraint `(user_id, timeslot_id)` wird entfernt
3. Eine neue UNIQUE-Constraint `(user_id, exhibitor_id)` wird hinzugefügt
   - Verhindert, dass sich ein User mehrfach für denselben Aussteller anmeldet

## In phpMyAdmin
1. Öffne phpMyAdmin
2. Wähle die Datenbank `berufsmesse`
3. Klicke auf "SQL"
4. Kopiere den Inhalt von `migration_allow_null_timeslot.sql`
5. Füge ihn ein und klicke auf "OK"

## Testen
Nach der Migration sollten Registrierungen ohne Slot-Zuteilung möglich sein.
