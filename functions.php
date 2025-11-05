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

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'login.php');
        exit();
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
    
    // Fallback: Standard-Kapazität (Raumkapazität / 3)
    $stmt = $db->prepare("SELECT capacity FROM rooms WHERE id = ?");
    $stmt->execute([$roomId]);
    $room = $stmt->fetch();
    
    if ($room && $room['capacity']) {
        return floor(intval($room['capacity']) / 3);
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

function grantPermission($userId, $permission) {
    if (!isAdmin()) {
        return false;
    }
    
    $db = getDB();
    $stmt = $db->prepare("INSERT IGNORE INTO user_permissions (user_id, permission, granted_by) VALUES (?, ?, ?)");
    return $stmt->execute([$userId, $permission, $_SESSION['user_id']]);
}

function revokePermission($userId, $permission) {
    if (!isAdmin()) {
        return false;
    }
    
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM user_permissions WHERE user_id = ? AND permission = ?");
    return $stmt->execute([$userId, $permission]);
}

// Verfügbare Berechtigungen
function getAvailablePermissions() {
    return [
        'manage_exhibitors' => 'Aussteller verwalten (erstellen/bearbeiten/löschen, Räume zuordnen)',
        'manage_rooms' => 'Räume verwalten',
        'manage_settings' => 'Einstellungen verwalten (Einschreibezeiten, Event-Datum)',
        'manage_users' => 'Benutzer verwalten (Passwörter zurücksetzen, Accounts erstellen/löschen)',
        'view_reports' => 'Berichte ansehen und drucken',
        'auto_assign' => 'Automatische Zuteilung durchführen'
    ];
}
?>
