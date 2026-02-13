<?php
/**
 * Datenbank-Setup-Skript
 * Führt alle notwendigen Migrationen aus
 * 
 * WICHTIG: Dieses Skript einmalig nach dem ersten Deployment ausführen!
 */

session_start();
require_once 'config.php';
require_once 'functions.php';

// Nur Admins dürfen Setup ausführen
if (!isLoggedIn() || !isAdmin()) {
    die('Nur Administratoren können das Setup ausführen.');
}

$db = getDB();
$errors = [];
$success = [];

// Migration 1: exhibitors.visible_fields Spalte hinzufügen
try {
    $stmt = $db->query("SHOW COLUMNS FROM exhibitors LIKE 'visible_fields'");
    if ($stmt->rowCount() === 0) {
        $db->exec("ALTER TABLE exhibitors ADD COLUMN visible_fields JSON DEFAULT NULL COMMENT 'Definiert welche Felder für Schüler sichtbar sind'");
        $success[] = "Spalte 'exhibitors.visible_fields' erfolgreich hinzugefügt";
        
        // Standard-Werte setzen
        $db->exec("UPDATE exhibitors SET visible_fields = JSON_ARRAY('name', 'short_description', 'description', 'category', 'website') WHERE visible_fields IS NULL");
        $success[] = "Standard-Werte für 'visible_fields' gesetzt";
    } else {
        $success[] = "Spalte 'exhibitors.visible_fields' existiert bereits";
    }
} catch (PDOException $e) {
    $errors[] = "Fehler bei exhibitors.visible_fields: " . $e->getMessage();
}

// Migration 2: room_slot_capacities Tabelle erstellen
try {
    $stmt = $db->query("SHOW TABLES LIKE 'room_slot_capacities'");
    if ($stmt->rowCount() === 0) {
        $db->exec("
            CREATE TABLE room_slot_capacities (
                id INT AUTO_INCREMENT PRIMARY KEY,
                room_id INT NOT NULL,
                timeslot_id INT NOT NULL,
                capacity INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
                FOREIGN KEY (timeslot_id) REFERENCES timeslots(id) ON DELETE CASCADE,
                UNIQUE KEY unique_room_slot (room_id, timeslot_id),
                INDEX idx_room_id (room_id),
                INDEX idx_timeslot_id (timeslot_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Slot-spezifische Raumkapazitäten'
        ");
        $success[] = "Tabelle 'room_slot_capacities' erfolgreich erstellt";
    } else {
        $success[] = "Tabelle 'room_slot_capacities' existiert bereits";
    }
} catch (PDOException $e) {
    $errors[] = "Fehler bei room_slot_capacities: " . $e->getMessage();
}

// Migration 3: user_permissions Tabelle erstellen
try {
    $stmt = $db->query("SHOW TABLES LIKE 'user_permissions'");
    if ($stmt->rowCount() === 0) {
        $db->exec("
            CREATE TABLE user_permissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                permission VARCHAR(50) NOT NULL,
                granted_by INT DEFAULT NULL COMMENT 'Admin der die Berechtigung erteilt hat',
                granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL,
                UNIQUE KEY unique_user_permission (user_id, permission),
                INDEX idx_user_id (user_id),
                INDEX idx_permission (permission)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Granulare Benutzerberechtigungen'
        ");
        $success[] = "Tabelle 'user_permissions' erfolgreich erstellt";
    } else {
        $success[] = "Tabelle 'user_permissions' existiert bereits";
    }
} catch (PDOException $e) {
    $errors[] = "Fehler bei user_permissions: " . $e->getMessage();
}

// Migration 4: users.email Spalte hinzufügen (optional)
try {
    $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'email'");
    if ($stmt->rowCount() === 0) {
        $db->exec("ALTER TABLE users ADD COLUMN email VARCHAR(255) DEFAULT NULL AFTER username");
        $success[] = "Spalte 'users.email' erfolgreich hinzugefügt";
    } else {
        $success[] = "Spalte 'users.email' existiert bereits";
    }
} catch (PDOException $e) {
    $errors[] = "Fehler bei users.email: " . $e->getMessage();
}

// Migration 5: rooms.equipment Spalte hinzufügen (Issue #17)
try {
    $stmt = $db->query("SHOW COLUMNS FROM rooms LIKE 'equipment'");
    if ($stmt->rowCount() === 0) {
        $db->exec("ALTER TABLE rooms ADD COLUMN equipment VARCHAR(500) DEFAULT NULL COMMENT 'Raumausstattung (z.B. Beamer, Smartboard)' AFTER capacity");
        $success[] = "Spalte 'rooms.equipment' erfolgreich hinzugefügt";
    } else {
        $success[] = "Spalte 'rooms.equipment' existiert bereits";
    }
} catch (PDOException $e) {
    $errors[] = "Fehler bei rooms.equipment: " . $e->getMessage();
}

// Migration 6: registrations.priority Spalte hinzufügen (Issue #16)
try {
    $stmt = $db->query("SHOW COLUMNS FROM registrations LIKE 'priority'");
    if ($stmt->rowCount() === 0) {
        $db->exec("ALTER TABLE registrations ADD COLUMN priority INT DEFAULT 0 COMMENT 'Priorität der Anmeldung (1=hoch, 2=mittel, 3=niedrig, 0=keine)' AFTER registration_type");
        $success[] = "Spalte 'registrations.priority' erfolgreich hinzugefügt";
    } else {
        $success[] = "Spalte 'registrations.priority' existiert bereits";
    }
} catch (PDOException $e) {
    $errors[] = "Fehler bei registrations.priority: " . $e->getMessage();
}

// Migration 7: attendance Tabelle für QR-Code Anwesenheitsprüfung erstellen (Issue #15)
try {
    $stmt = $db->query("SHOW TABLES LIKE 'attendance'");
    if ($stmt->rowCount() === 0) {
        $db->exec("
            CREATE TABLE attendance (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                exhibitor_id INT NOT NULL,
                timeslot_id INT NOT NULL,
                qr_token VARCHAR(64) NOT NULL COMMENT 'Temporärer QR-Token für diesen Slot',
                checked_in_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (exhibitor_id) REFERENCES exhibitors(id) ON DELETE CASCADE,
                FOREIGN KEY (timeslot_id) REFERENCES timeslots(id) ON DELETE CASCADE,
                UNIQUE KEY unique_attendance (user_id, exhibitor_id, timeslot_id),
                INDEX idx_qr_token (qr_token)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Anwesenheitsprüfung per QR-Code'
        ");
        $success[] = "Tabelle 'attendance' erfolgreich erstellt";
    } else {
        $success[] = "Tabelle 'attendance' existiert bereits";
    }
} catch (PDOException $e) {
    $errors[] = "Fehler bei attendance: " . $e->getMessage();
}

// Migration 8: qr_tokens Tabelle für temporäre QR-Codes (Issue #15)
try {
    $stmt = $db->query("SHOW TABLES LIKE 'qr_tokens'");
    if ($stmt->rowCount() === 0) {
        $db->exec("
            CREATE TABLE qr_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                exhibitor_id INT NOT NULL,
                timeslot_id INT NOT NULL,
                token VARCHAR(64) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at TIMESTAMP NULL DEFAULT NULL,
                FOREIGN KEY (exhibitor_id) REFERENCES exhibitors(id) ON DELETE CASCADE,
                FOREIGN KEY (timeslot_id) REFERENCES timeslots(id) ON DELETE CASCADE,
                UNIQUE KEY unique_exhibitor_slot_token (exhibitor_id, timeslot_id),
                INDEX idx_token (token)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Temporäre QR-Codes für Anwesenheitsprüfung'
        ");
        $success[] = "Tabelle 'qr_tokens' erfolgreich erstellt";
    } else {
        $success[] = "Tabelle 'qr_tokens' existiert bereits";
    }
} catch (PDOException $e) {
    $errors[] = "Fehler bei qr_tokens: " . $e->getMessage();
}

// Migration 9: settings für Einschreibeschluss-Automatik (Issue #12)
try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = 'auto_close_registration'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $db->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('auto_close_registration', '1')");
        $success[] = "Einstellung 'auto_close_registration' hinzugefügt";
    } else {
        $success[] = "Einstellung 'auto_close_registration' existiert bereits";
    }
} catch (PDOException $e) {
    $errors[] = "Fehler bei auto_close_registration setting: " . $e->getMessage();
}

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
                                Das Setup ist abgeschlossen. Sie können diese Seite nun schließen und zum Dashboard zurückkehren.
                            </p>
                            <p class="text-sm text-blue-800 mt-2">
                                <strong>Hinweis:</strong> Diese Setup-Datei kann nach erfolgreichem Abschluss aus Sicherheitsgründen gelöscht werden.
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

                <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                    <h4 class="font-semibold text-gray-800 mb-3">Durchgeführte Migrationen:</h4>
                    <ul class="text-sm text-gray-700 space-y-2">
                        <li>
                            <i class="fas fa-table text-gray-500 mr-2"></i>
                            <strong>exhibitors.visible_fields</strong> - JSON-Spalte für Feldvisibilität
                        </li>
                        <li>
                            <i class="fas fa-table text-gray-500 mr-2"></i>
                            <strong>room_slot_capacities</strong> - Tabelle für slot-spezifische Raumkapazitäten
                        </li>
                        <li>
                            <i class="fas fa-table text-gray-500 mr-2"></i>
                            <strong>user_permissions</strong> - Tabelle für granulare Benutzerberechtigungen
                        </li>
                        <li>
                            <i class="fas fa-table text-gray-500 mr-2"></i>
                            <strong>users.email</strong> - E-Mail-Spalte für Benutzer
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
