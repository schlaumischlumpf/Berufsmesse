<?php
/**
 * Schema-Migrations-Runner
 * ------------------------------------------------------------------
 * Fuehrt nummerierte SQL-Dateien aus dem Verzeichnis migrations/ in
 * aufsteigender Reihenfolge aus und merkt sich angewandte Migrationen
 * in der Tabelle `schema_migrations`. Bereits angewandte Migrationen
 * werden uebersprungen; bei einem Fehler wird abgebrochen, damit die
 * Reihenfolge gewahrt bleibt.
 *
 * Die Migrationsdateien enthalten ausschliesslich ueber PDO ausfuehrbare
 * Anweisungen (kein DELIMITER, keine Stored Procedures). Idempotenz wird
 * ueber "IF NOT EXISTS" bzw. information_schema + PREPARE erreicht.
 */

/**
 * Legt die Verwaltungstabelle an, falls sie fehlt.
 */
function ensureSchemaMigrationsTable(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS `schema_migrations` (
        `id`             INT(11)      NOT NULL AUTO_INCREMENT,
        `migration_name` VARCHAR(255) NOT NULL,
        `executed_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_migration_name` (`migration_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
      COMMENT='Angewandte Schema-Migrationen'");
}

/**
 * Liefert die bereits angewandten Migrationsnamen als Set (name => true).
 */
function getAppliedMigrations(PDO $db): array {
    $names = $db->query("SELECT migration_name FROM schema_migrations")->fetchAll(PDO::FETCH_COLUMN);
    return array_fill_keys($names, true);
}

/**
 * Zerlegt eine SQL-Datei in einzelne Anweisungen.
 * - entfernt Block-Kommentare und reine Zeilenkommentare (-- ...)
 * - trennt an Semikolons, die eine Zeile beenden
 *
 * Voraussetzung (durch die Migrationsdateien eingehalten): keine Semikolons
 * innerhalb von String-Literalen, kein DELIMITER, keine Stored Procedures.
 *
 * @return string[] Liste ausfuehrbarer Anweisungen ohne abschliessendes ;
 */
function splitSqlStatements(string $sql): array {
    // Block-Kommentare /* ... */ (inkl. /*! ... */) entfernen
    $sql = preg_replace('#/\*.*?\*/#s', '', $sql);

    $statements = [];
    $buffer = '';
    foreach (preg_split('/\R/', $sql) as $line) {
        $trimmed = ltrim($line);
        // Leerzeilen und reine Zeilenkommentare ueberspringen
        if ($trimmed === '' || strncmp($trimmed, '--', 2) === 0) {
            continue;
        }
        $buffer .= $line . "\n";
        // Anweisung endet, wenn die Zeile (ohne nachgestellten Whitespace) auf ; endet
        if (preg_match('/;\s*$/', $line)) {
            $stmt = rtrim(trim($buffer), "; \t\r\n");
            if ($stmt !== '') {
                $statements[] = $stmt;
            }
            $buffer = '';
        }
    }
    $tail = rtrim(trim($buffer), "; \t\r\n");
    if ($tail !== '') {
        $statements[] = $tail;
    }
    return $statements;
}

/**
 * Fuehrt alle ausstehenden Migrationen aus.
 *
 * @return array{applied:string[],skipped:string[],errors:string[]}
 */
function runPendingMigrations(PDO $db, ?string $dir = null): array {
    $dir = $dir ?? __DIR__ . '/migrations';
    $result = ['applied' => [], 'skipped' => [], 'errors' => []];

    try {
        ensureSchemaMigrationsTable($db);
    } catch (PDOException $e) {
        $result['errors'][] = 'schema_migrations konnte nicht angelegt werden: ' . $e->getMessage();
        return $result;
    }

    $applied = getAppliedMigrations($db);

    $files = glob(rtrim($dir, '/\\') . '/*.sql');
    if ($files === false) {
        $result['errors'][] = "Migrationsverzeichnis nicht lesbar: $dir";
        return $result;
    }
    sort($files, SORT_STRING);

    $insert = $db->prepare("INSERT INTO schema_migrations (migration_name) VALUES (?)");

    foreach ($files as $file) {
        $name = basename($file);
        if (isset($applied[$name])) {
            $result['skipped'][] = $name;
            continue;
        }

        $sql = file_get_contents($file);
        if ($sql === false) {
            $result['errors'][] = "$name konnte nicht gelesen werden";
            break;
        }

        $statements = splitSqlStatements($sql);
        $ok = true;
        foreach ($statements as $i => $stmt) {
            try {
                $db->exec($stmt);
            } catch (PDOException $e) {
                $ok = false;
                $result['errors'][] = "$name [Anweisung " . ($i + 1) . "]: " . $e->getMessage();
                break;
            }
        }

        if (!$ok) {
            // Reihenfolge bewahren: bei Fehler nicht mit spaeteren Migrationen fortfahren
            break;
        }

        $insert->execute([$name]);
        $result['applied'][] = $name;
    }

    return $result;
}
