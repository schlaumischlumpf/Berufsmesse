<?php
require_once 'config.php';

class Database {
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        try {
            $this->conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch(PDOException $e) {
            die("Datenbankverbindung fehlgeschlagen: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->conn;
    }
    
    // Verhindere Klonen
    private function __clone() {}
    
    // Verhindere Unserialisierung
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

// Konstanten für Kapazitätsberechnung
define('MANAGED_SLOTS_COUNT', 3); // Anzahl der verwalteten Slots (1, 3, 5)
define('DEFAULT_CAPACITY_DIVISOR', 3); // Standard-Divisor für Raumkapazität

// Hilfsfunktionen
function getDB() {
    return Database::getInstance()->getConnection();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isTeacher() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'teacher';
}

function isOrga() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'orga';
}

function requireLogin() {
    if (!isLoggedIn()) {
        // Aktuelle URL merken, damit nach Login dorthin zurückgeleitet wird
        $returnUrl = $_SERVER['REQUEST_URI'] ?? '';
        $loginUrl = BASE_URL . 'login.php';
        if (!empty($returnUrl) && $returnUrl !== '/') {
            $loginUrl .= '?redirect=' . urlencode($returnUrl);
        }
        header('Location: ' . $loginUrl);
        exit();
    }

    // Enforce password change if required by DB flag
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT must_change_password FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $res = $stmt->fetch();
        if ($res && $res['must_change_password']) {
            $currentScript = basename($_SERVER['PHP_SELF']);
            // Allow the user to access only the password change page
            if ($currentScript !== 'change-password.php') {
                $_SESSION['force_password_change'] = true;
                header('Location: ' . BASE_URL . 'change-password.php');
                exit();
            }
        }
    } catch (Exception $e) {
        // If DB is temporarily unavailable, do not block access but log
        error_log('requireLogin DB check failed: ' . $e->getMessage());
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . BASE_URL . 'index.php');
        exit();
    }
}

function requireTeacherOrAdmin() {
    requireLogin();
    if (!isTeacher() && !isAdmin()) {
        header('Location: ' . BASE_URL . 'index.php');
        exit();
    }
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function formatDate($date) {
    return date('d.m.Y', strtotime($date));
}

function formatDateTime($datetime) {
    return date('d.m.Y H:i', strtotime($datetime));
}

function getSetting($key, $default = null) {
    $db = getDB();
    $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    return $result ? $result['setting_value'] : $default;
}

function updateSetting($key, $value) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                          ON DUPLICATE KEY UPDATE setting_value = ?");
    return $stmt->execute([$key, $value, $value]);
}

function isRegistrationOpen() {
    $start = getSetting('registration_start');
    $end = getSetting('registration_end');
    $now = date('Y-m-d H:i:s');
    
    return ($now >= $start && $now <= $end);
}

function getRegistrationStatus() {
    $start = getSetting('registration_start');
    $end = getSetting('registration_end');
    $now = date('Y-m-d H:i:s');
    
    if ($now < $start) {
        return 'upcoming';
    } elseif ($now > $end) {
        return 'closed';
    } else {
        return 'open';
    }
}

function uploadFile($file, $exhibitorId) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Fehler beim Upload'];
    }
    
    // Dateigröße prüfen
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'Datei zu groß (max. 10 MB)'];
    }
    
    // Dateityp prüfen
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_FILE_TYPES)) {
        return ['success' => false, 'message' => 'Dateityp nicht erlaubt'];
    }
    
    // Einzigartigen Dateinamen generieren
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $filepath = UPLOAD_DIR . $filename;
    
    // Datei verschieben
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => false, 'message' => 'Fehler beim Speichern der Datei'];
    }
    
    // In Datenbank eintragen
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO exhibitor_documents (exhibitor_id, filename, original_name, file_type, file_size) 
                          VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $exhibitorId,
        $filename,
        $file['name'],
        $extension,
        $file['size']
    ]);
    
    return ['success' => true, 'filename' => $filename];
}

function deleteFile($documentId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT filename FROM exhibitor_documents WHERE id = ?");
    $stmt->execute([$documentId]);
    $doc = $stmt->fetch();
    
    if ($doc) {
        $filepath = UPLOAD_DIR . $doc['filename'];
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        
        $stmt = $db->prepare("DELETE FROM exhibitor_documents WHERE id = ?");
        return $stmt->execute([$documentId]);
    }
    
    return false;
}

// Helper-Funktion für Raumkapazität pro Slot (Issue #4)
function getRoomSlotCapacity($roomId, $timeslotId) {
    $db = getDB();
    
    // Erst prüfen ob spezifische Kapazität definiert ist
    $stmt = $db->prepare("SELECT capacity FROM room_slot_capacities WHERE room_id = ? AND timeslot_id = ?");
    $stmt->execute([$roomId, $timeslotId]);
    $result = $stmt->fetch();
    
    if ($result) {
        return intval($result['capacity']);
    }
    
    // Fallback: Jeder Slot bekommt die volle Raumkapazität
    $stmt = $db->prepare("SELECT capacity FROM rooms WHERE id = ?");
    $stmt->execute([$roomId]);
    $room = $stmt->fetch();

    if ($room && $room['capacity']) {
        $capacity = intval($room['capacity']);
        // Jeder Slot bekommt die volle Raumkapazität (nicht geteilt)
        return $capacity;
    }
    
    return 0;
}

// Helper-Funktion für Gesamt-Kapazität eines Ausstellers (über alle Slots)
function getExhibitorTotalCapacity($exhibitorId) {
    $db = getDB();
    
    // Raum des Ausstellers ermitteln
    $stmt = $db->prepare("SELECT room_id FROM exhibitors WHERE id = ?");
    $stmt->execute([$exhibitorId]);
    $exhibitor = $stmt->fetch();
    
    if (!$exhibitor || !$exhibitor['room_id']) {
        return 0;
    }
    
    // Alle verwalteten Zeitslots (1, 3, 5)
    $stmt = $db->query("SELECT id FROM timeslots WHERE slot_number IN (1, 3, 5)");
    $timeslots = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $totalCapacity = 0;
    foreach ($timeslots as $timeslotId) {
        $totalCapacity += getRoomSlotCapacity($exhibitor['room_id'], $timeslotId);
    }
    
    return $totalCapacity;
}

// Berechtigungssystem (Issue #10)
function hasPermission($permission) {
    // Admins haben immer alle Berechtigungen
    if (isAdmin()) {
        return true;
    }
    
    if (!isLoggedIn()) {
        return false;
    }
    
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM user_permissions WHERE user_id = ? AND permission = ?");
    $stmt->execute([$_SESSION['user_id'], $permission]);
    return $stmt->fetchColumn() > 0;
}

function hasAnyPermission(...$permissions) {
    foreach ($permissions as $permission) {
        if (hasPermission($permission)) {
            return true;
        }
    }
    return false;
}

function requirePermission($permission) {
    if (!hasPermission($permission)) {
        die('Keine Berechtigung für diese Aktion');
    }
}

function getUserPermissions($userId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT permission FROM user_permissions WHERE user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Abhängigkeitsbaum für Berechtigungen
function getPermissionDependencies() {
    return [
        // Aussteller
        'aussteller_erstellen'            => ['aussteller_sehen'],
        'aussteller_bearbeiten'           => ['aussteller_sehen'],
        'aussteller_loeschen'             => ['aussteller_sehen'],
        'aussteller_dokumente_verwalten'  => ['aussteller_sehen'],
        // Räume
        'raeume_erstellen'                => ['raeume_sehen'],
        'raeume_bearbeiten'               => ['raeume_sehen'],
        'raeume_loeschen'                 => ['raeume_sehen'],
        // Kapazitäten
        'kapazitaeten_sehen'              => ['raeume_sehen'],
        'kapazitaeten_bearbeiten'         => ['kapazitaeten_sehen', 'raeume_sehen'],
        // Benutzer
        'benutzer_erstellen'              => ['benutzer_sehen'],
        'benutzer_bearbeiten'             => ['benutzer_sehen'],
        'benutzer_loeschen'               => ['benutzer_sehen'],
        'benutzer_importieren'            => ['benutzer_sehen'],
        'benutzer_passwort_zuruecksetzen' => ['benutzer_sehen'],
        // Berechtigungen
        'berechtigungen_vergeben'          => ['berechtigungen_sehen'],
        'berechtigungsgruppen_verwalten'   => ['berechtigungen_sehen'],
        // Einstellungen
        'einstellungen_bearbeiten'         => ['einstellungen_sehen'],
        // Berichte
        'berichte_drucken'                 => ['berichte_sehen'],
        // QR-Codes
        'qr_codes_erstellen'               => ['qr_codes_sehen'],
        // Anmeldungen
        'anmeldungen_erstellen'            => ['anmeldungen_sehen'],
        'anmeldungen_loeschen'             => ['anmeldungen_sehen'],
        // Zuteilung
        'zuteilung_ausfuehren'             => ['dashboard_sehen'],
        'zuteilung_zuruecksetzen'          => ['zuteilung_ausfuehren', 'dashboard_sehen'],
    ];
}

function grantPermission($userId, $permission, &$visited = []) {
    if (!isAdmin() && !hasPermission('berechtigungen_vergeben')) {
        return false;
    }

    // Non-admins can only grant permissions they have themselves (checks current session user)
    if (!isAdmin() && !hasPermission($permission)) {
        return false;
    }

    // Prevent infinite recursion
    if (in_array($permission, $visited)) {
        return true;
    }
    $visited[] = $permission;

    $db = getDB();
    $stmt = $db->prepare("INSERT IGNORE INTO user_permissions (user_id, permission, granted_by) VALUES (?, ?, ?)");
    $result = $stmt->execute([$userId, $permission, $_SESSION['user_id']]);

    // Auto-grant dependencies
    $deps = getPermissionDependencies();
    if (isset($deps[$permission])) {
        foreach ($deps[$permission] as $dep) {
            grantPermission($userId, $dep, $visited);
        }
    }

    return $result;
}

function revokePermission($userId, $permission, &$visited = []) {
    if (!isAdmin() && !hasPermission('berechtigungen_vergeben')) {
        return false;
    }

    // Prevent infinite recursion
    if (in_array($permission, $visited)) {
        return true;
    }
    $visited[] = $permission;

    $db = getDB();
    $stmt = $db->prepare("DELETE FROM user_permissions WHERE user_id = ? AND permission = ?");
    $result = $stmt->execute([$userId, $permission]);

    // Auto-revoke higher permissions that depend on this one
    $deps = getPermissionDependencies();
    foreach ($deps as $dependent => $requiredList) {
        if (in_array($permission, $requiredList)) {
            revokePermission($userId, $dependent, $visited);
        }
    }

    return $result;
}

// Verfügbare Berechtigungen (gruppiert nach Seitenbereich)
function getAvailablePermissions() {
    return [
        'Admin-Dashboard' => [
            'dashboard_sehen' => 'Admin-Dashboard mit Statistiken und Übersicht anzeigen',
        ],
        'Aussteller-Verwaltung' => [
            'aussteller_sehen'               => 'Ausstellerliste im Admin-Bereich einsehen',
            'aussteller_erstellen'           => 'Neue Aussteller anlegen (erfordert: aussteller_sehen)',
            'aussteller_bearbeiten'          => 'Bestehende Aussteller bearbeiten (erfordert: aussteller_sehen)',
            'aussteller_loeschen'            => 'Aussteller löschen (erfordert: aussteller_sehen)',
            'aussteller_dokumente_verwalten' => 'Dokumente zu Ausstellern hochladen/löschen (erfordert: aussteller_sehen)',
        ],
        'Raum-Verwaltung' => [
            'raeume_sehen'      => 'Raumliste und Raumplan einsehen',
            'raeume_erstellen'  => 'Neue Räume anlegen (erfordert: raeume_sehen)',
            'raeume_bearbeiten' => 'Räume bearbeiten und Aussteller zuordnen (erfordert: raeume_sehen)',
            'raeume_loeschen'   => 'Räume löschen (erfordert: raeume_sehen)',
        ],
        'Raumkapazitäten' => [
            'kapazitaeten_sehen'      => 'Slot-Kapazitäten der Räume einsehen (erfordert: raeume_sehen)',
            'kapazitaeten_bearbeiten' => 'Slot-Kapazitäten ändern (erfordert: kapazitaeten_sehen)',
        ],
        'Benutzer-Verwaltung' => [
            'benutzer_sehen'                  => 'Benutzerliste einsehen',
            'benutzer_erstellen'              => 'Neue Benutzer anlegen (erfordert: benutzer_sehen)',
            'benutzer_bearbeiten'             => 'Benutzer bearbeiten (erfordert: benutzer_sehen)',
            'benutzer_loeschen'               => 'Benutzer löschen (erfordert: benutzer_sehen)',
            'benutzer_importieren'            => 'Benutzer per CSV importieren (erfordert: benutzer_sehen)',
            'benutzer_passwort_zuruecksetzen' => 'Passwörter zurücksetzen (erfordert: benutzer_sehen)',
        ],
        'Berechtigungen' => [
            'berechtigungen_sehen'           => 'Berechtigungen anderer Benutzer einsehen',
            'berechtigungen_vergeben'         => 'Berechtigungen vergeben/entziehen (erfordert: berechtigungen_sehen)',
            'berechtigungsgruppen_verwalten'  => 'Berechtigungsgruppen erstellen/bearbeiten/löschen (erfordert: berechtigungen_sehen)',
        ],
        'Einstellungen' => [
            'einstellungen_sehen'      => 'Systemeinstellungen einsehen',
            'einstellungen_bearbeiten' => 'Einstellungen ändern (erfordert: einstellungen_sehen)',
        ],
        'Druckzentrale / Berichte' => [
            'berichte_sehen'   => 'Berichte und Druckansichten einsehen',
            'berichte_drucken' => 'PDFs generieren und drucken (erfordert: berichte_sehen)',
        ],
        'QR-Codes' => [
            'qr_codes_sehen'    => 'QR-Code Übersicht einsehen',
            'qr_codes_erstellen' => 'QR-Codes und Tokens generieren (erfordert: qr_codes_sehen)',
        ],
        'Anmeldungen' => [
            'anmeldungen_sehen'    => 'Alle Schüler-Anmeldungen einsehen',
            'anmeldungen_erstellen' => 'Schüler manuell anmelden (erfordert: anmeldungen_sehen)',
            'anmeldungen_loeschen'  => 'Anmeldungen löschen (erfordert: anmeldungen_sehen)',
        ],
        'Automatische Zuteilung' => [
            'zuteilung_ausfuehren'    => 'Automatische Slot-Zuteilung starten (erfordert: dashboard_sehen)',
            'zuteilung_zuruecksetzen' => 'Alle Slot-Zuordnungen zurücksetzen (erfordert: zuteilung_ausfuehren)',
        ],
        'Audit Logs' => [
            'audit_logs_sehen' => 'Audit-Protokolle einsehen',
        ],
    ];
}

// Gibt alle Berechtigungs-Keys als flaches Array zurück
function getAllPermissionKeys() {
    $flat = [];
    foreach (getAvailablePermissions() as $permissions) {
        foreach ($permissions as $key => $description) {
            $flat[$key] = $description;
        }
    }
    return $flat;
}

// Audit Log System (Issue #21)
function logAuditAction($action, $details = '') {
    try {
        $db = getDB();
        $userId = $_SESSION['user_id'] ?? null;
        $username = $_SESSION['username'] ?? 'System';
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        
        $stmt = $db->prepare("
            INSERT INTO audit_logs (user_id, username, action, details, ip_address, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $username, $action, $details, $ipAddress]);
    } catch (Exception $e) {
        error_log('Audit Log Error: ' . $e->getMessage());
    }
}

// Branchen/Kategorien aus DB laden
function getIndustries() {
    try {
        $db = getDB();
        $stmt = $db->query("SELECT id, name, sort_order FROM industries ORDER BY sort_order, name");
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

// Berechtigungsgruppen (Issue #26)
function getPermissionGroups() {
    try {
        $db = getDB();
        $stmt = $db->query("SELECT * FROM permission_groups ORDER BY name ASC");
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function getPermissionGroupPermissions($groupId) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT permission FROM permission_group_items WHERE group_id = ?");
        $stmt->execute([$groupId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        return [];
    }
}

function applyPermissionGroup($userId, $groupId) {
    $permissions = getPermissionGroupPermissions($groupId);
    foreach ($permissions as $permission) {
        grantPermission($userId, $permission);
    }
}
?>
