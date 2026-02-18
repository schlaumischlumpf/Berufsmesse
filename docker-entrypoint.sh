#!/bin/sh
set -e

# ─────────────────────────────────────────────
# Berufsmesse – Docker Entrypoint
# Wartet auf MariaDB/MySQL und führt Migrationen
# automatisch beim ersten Start aus.
# ─────────────────────────────────────────────

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
DB_NAME="${DB_NAME:-berufsmesse}"

echo "[entrypoint] Warte auf Datenbankverbindung zu ${DB_HOST}..."

# Warte bis die Datenbank erreichbar ist (max. 60 Sekunden)
MAX_TRIES=30
TRIES=0
until mysql -h"${DB_HOST}" -u"${DB_USER}" -p"${DB_PASS}" -e "SELECT 1" >/dev/null 2>&1; do
    TRIES=$((TRIES + 1))
    if [ "$TRIES" -ge "$MAX_TRIES" ]; then
        echo "[entrypoint] FEHLER: Datenbankverbindung nach ${MAX_TRIES} Versuchen fehlgeschlagen. Abbruch."
        exit 1
    fi
    echo "[entrypoint] Datenbankverbindung nicht verfügbar – erneuter Versuch in 2s... (${TRIES}/${MAX_TRIES})"
    sleep 2
done

echo "[entrypoint] Datenbankverbindung hergestellt."

# ─────────────────────────────────────────────
# Migrationen ausführen
# Die SQL-Datei verwendet IF NOT EXISTS / IF EXISTS,
# daher ist idempotentes Ausführen sicher.
# ─────────────────────────────────────────────
MIGRATION_FILE="/var/www/html/migrations.sql"
MIGRATION_FLAG="/var/www/html/uploads/.migrations_applied"

if [ -f "$MIGRATION_FILE" ]; then
    echo "[entrypoint] Führe Migrationen aus: ${MIGRATION_FILE}"
    mysql -h"${DB_HOST}" -u"${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" < "$MIGRATION_FILE" && \
        touch "$MIGRATION_FLAG" && \
        echo "[entrypoint] Migrationen erfolgreich ausgeführt." || \
        echo "[entrypoint] WARNUNG: Migrationen teilweise fehlgeschlagen (evtl. bereits angewendet)."
else
    echo "[entrypoint] Keine migrations.sql gefunden – überspringe."
fi

# ─────────────────────────────────────────────
# Apache starten
# ─────────────────────────────────────────────
echo "[entrypoint] Starte Apache..."
exec "$@"
