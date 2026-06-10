<?php
/**
 * Datenbank-Setup / Migrations-Runner
 *
 * Fuehrt alle ausstehenden Schema-Migrationen aus dem Verzeichnis migrations/
 * aus (siehe migrations.php) und legt fuer frische Installationen die noetigen
 * Standard-Datensaetze an (Standardschule, erste Edition, Berechtigungsgruppen).
 *
 * WICHTIG: Schema-Aenderungen erfolgen ausschliesslich ueber neue
 * Migrationsdateien (migrations/NNN_*.sql) — nicht mehr direkt in dieser Datei.
 */

require_once 'config.php';
require_once 'functions.php';
require_once 'migrations.php';

// Nur Admins duerfen Setup ausfuehren
if (!isLoggedIn() || !isAdmin()) {
    die('Nur Administratoren können das Setup ausführen.');
}

$db = getDB();

// ── 1. Ausstehende Migrationen ausfuehren ────────────────────────────────────
$migration = runPendingMigrations($db);
$success = [];
$errors  = $migration['errors'];

foreach ($migration['applied'] as $m) {
    $success[] = "Migration angewendet: $m";
}
foreach ($migration['skipped'] as $m) {
    $success[] = "Migration bereits vorhanden: $m";
}

// ── 2. Standard-Datensaetze fuer frische Installationen (idempotent) ──────────
if (empty($errors)) {
    try {
        // Standardschule
        $cnt = (int)$db->query("SELECT COUNT(*) FROM schools")->fetchColumn();
        if ($cnt === 0) {
            $db->exec("INSERT INTO schools (name, slug, is_active) VALUES ('Standardschule', 'standard', 1)");
            $success[] = "Standardschule angelegt";
        }

        // Erste Messe-Edition aus globalen Einstellungen ableiten
        $cnt = (int)$db->query("SELECT COUNT(*) FROM messe_editions")->fetchColumn();
        if ($cnt === 0) {
            $rs = $db->query("SELECT setting_key, setting_value FROM settings
                              WHERE setting_key IN ('registration_start','registration_end',
                                                    'event_date','max_registrations_per_student')")
                     ->fetchAll(PDO::FETCH_KEY_PAIR);
            $year = !empty($rs['event_date']) ? (int)date('Y', strtotime($rs['event_date'])) : (int)date('Y');
            $stmt = $db->prepare("INSERT INTO messe_editions
                (name, year, status, registration_start, registration_end, event_date, max_registrations_per_student, school_id)
                VALUES (?, ?, 'active', ?, ?, ?, ?, 1)");
            $stmt->execute([
                'Berufsmesse ' . $year, $year,
                $rs['registration_start'] ?? null,
                $rs['registration_end']   ?? null,
                $rs['event_date']         ?? null,
                (int)($rs['max_registrations_per_student'] ?? 3),
            ]);
            $success[] = "Erste Messe-Edition angelegt";
        }

        // Einschreibeschluss-Automatik
        $stmt = $db->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = 'auto_close_registration'");
        $stmt->execute();
        if ((int)$stmt->fetchColumn() === 0) {
            $db->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('auto_close_registration', '1')");
            $success[] = "Einstellung 'auto_close_registration' angelegt";
        }

        // Voreingestellte Berechtigungsgruppen
        $cnt = (int)$db->query("SELECT COUNT(*) FROM permission_groups")->fetchColumn();
        if ($cnt === 0) {
            $db->exec("INSERT INTO permission_groups (name, description) VALUES ('Lehrer Standard', 'Standard-Berechtigungen für Lehrkräfte')");
            $gid = (int)$db->lastInsertId();
            $db->prepare("INSERT INTO permission_group_items (group_id, permission) VALUES (?, ?), (?, ?)")
               ->execute([$gid, 'view_reports', $gid, 'view_rooms']);

            $db->exec("INSERT INTO permission_groups (name, description) VALUES ('Orga-Team', 'Erweiterte Berechtigungen für das Organisationsteam')");
            $gid = (int)$db->lastInsertId();
            $db->prepare("INSERT INTO permission_group_items (group_id, permission) VALUES (?, ?), (?, ?), (?, ?), (?, ?), (?, ?)")
               ->execute([$gid, 'manage_exhibitors', $gid, 'manage_rooms', $gid, 'view_reports', $gid, 'view_rooms', $gid, 'manage_qr_codes']);

            $db->exec("INSERT INTO permission_groups (name, description) VALUES ('Vollzugriff', 'Alle verfügbaren Berechtigungen')");
            $gid = (int)$db->lastInsertId();
            $db->prepare("INSERT INTO permission_group_items (group_id, permission) VALUES (?, ?), (?, ?), (?, ?), (?, ?), (?, ?), (?, ?), (?, ?), (?, ?), (?, ?)")
               ->execute([$gid, 'manage_exhibitors', $gid, 'manage_rooms', $gid, 'manage_settings', $gid, 'manage_users', $gid, 'view_reports', $gid, 'auto_assign', $gid, 'view_rooms', $gid, 'manage_qr_codes', $gid, 'view_audit_logs']);

            $success[] = "Voreingestellte Berechtigungsgruppen angelegt";
        }
    } catch (PDOException $e) {
        $errors[] = "Fehler beim Anlegen der Standard-Datensätze: " . $e->getMessage();
    }
}

// Setup-Aktion protokollieren
logAuditAction(
    'Datenbank-Setup ausgeführt',
    'Angewendet: ' . (implode(', ', $migration['applied']) ?: 'keine')
        . ($errors ? ' | Fehler: ' . count($errors) : ''),
    $errors ? 'warning' : 'info'
);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datenbank Setup - Berufsmesse</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="max-w-3xl w-full bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-6 py-4">
                <h1 class="text-2xl font-bold">
                    <i class="fas fa-database mr-3"></i>
                    Datenbank Setup
                </h1>
            </div>

            <div class="p-6 space-y-4">
                <?php if (!empty($success)): ?>
                    <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded">
                        <div class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 text-xl mr-3 mt-1"></i>
                            <div class="flex-1">
                                <h3 class="font-bold text-green-900 mb-2">Erfolgreich</h3>
                                <ul class="text-sm text-green-800 space-y-1">
                                    <?php foreach ($success as $msg): ?>
                                        <li><i class="fas fa-check mr-2"></i><?php echo htmlspecialchars($msg); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle text-red-500 text-xl mr-3 mt-1"></i>
                            <div class="flex-1">
                                <h3 class="font-bold text-red-900 mb-2">Fehler</h3>
                                <ul class="text-sm text-red-800 space-y-1">
                                    <?php foreach ($errors as $error): ?>
                                        <li><i class="fas fa-times mr-2"></i><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle text-blue-500 text-xl mr-3 mt-1"></i>
                        <div class="flex-1">
                            <h3 class="font-bold text-blue-900 mb-2">Information</h3>
                            <p class="text-sm text-blue-800">
                                Das Setup wendet ausstehende Migrationen aus dem Verzeichnis
                                <code>migrations/</code> an. Bereits angewendete Migrationen werden
                                übersprungen. Du kannst diese Seite jederzeit erneut aufrufen.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                    <a href="setup.php" class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-semibold">
                        <i class="fas fa-redo mr-2"></i>Setup erneut ausführen
                    </a>
                    <a href="index.php" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold">
                        <i class="fas fa-arrow-right mr-2"></i>Zum Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
