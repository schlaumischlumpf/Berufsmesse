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
            error_log("Datenbankverbindung fehlgeschlagen: " . $e->getMessage());
            http_response_code(503);
            die("Dienst vorübergehend nicht verfügbar.");
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
define('DEFAULT_CAPACITY_DIVISOR', 3); // Standard-Divisor für Raumkapazität

/**
 * Gibt alle slot_number-Werte zurück, die als managed markiert sind.
 * Statischer Cache pro Request. Fallback auf [1,3,5] vor Migration.
 * @return int[]
 */
function getManagedSlotNumbers(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    try {
        $db    = getDB();
        $stmt  = $db->query("SELECT slot_number FROM timeslots WHERE is_managed = 1 AND (is_break = 0 OR is_break IS NULL) ORDER BY slot_number ASC");
        $cache = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        if (!empty($cache)) return $cache;
    } catch (Exception $e) { /* Spalte noch nicht vorhanden */ }
    $cache = [1, 3, 5];
    return $cache;
}

function getManagedSlotCount(): int {
    return count(getManagedSlotNumbers());
}

/**
 * Gibt "IN (1,3,5)" zurück — nur Integer, SQL-Injection-sicher für direkte Einbettung.
 */
function getManagedSlotsSqlIn(): string {
    return 'IN (' . implode(',', getManagedSlotNumbers()) . ')';
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

/**
 * Prüft ob der User ein globaler Admin ODER ein Schul-Admin ist.
 * Schul-Admins haben Admin-Rechte, aber nur für ihre Schule.
 */
function isAdminOrSchoolAdmin(): bool {
    return isAdmin() || isSchoolAdmin();
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
        // Schulspezifische Login-URL wenn Slug vorhanden
        $slug = $_GET['school_slug'] ?? null;
        if ($slug) {
            $loginUrl = BASE_URL . htmlspecialchars($slug) . '/login.php';
        } else {
            $loginUrl = BASE_URL . 'login.php';
        }
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

// ─── CSRF ────────────────────────────────────────────────────────────────────

function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(?string $token): bool {
    return !empty($_SESSION['csrf_token'])
        && !empty($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Bricht mit HTTP 403 ab wenn der CSRF-Token fehlt oder ungültig ist.
 * Formulare:  $_POST['csrf_token']
 * JSON-APIs:  HTTP-Header 'X-CSRF-Token'
 */
function requireCsrf(): void {
    $token = $_POST['csrf_token']
          ?? $_SERVER['HTTP_X_CSRF_TOKEN']
          ?? null;
    if (!verifyCsrfToken($token)) {
        http_response_code(403);
        if (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Ungültige Anfrage (CSRF).']);
        } else {
            echo 'Ungültige Anfrage (CSRF-Token fehlt oder abgelaufen).';
        }
        exit;
    }
}

// ─────────────────────────────────────────────────────────────────────────────

function formatDate($date) {
    return date('d.m.Y', strtotime($date));
}

function formatDateTime($datetime) {
    return date('d.m.Y H:i', strtotime($datetime));
}

function getSetting($key, $default = null) {
    $editionKeys = ['registration_start', 'registration_end', 'event_date', 'max_registrations_per_student'];
    if (in_array($key, $editionKeys)) {
        $edition = getActiveEdition();
        $val     = $edition[$key] ?? null;
        return $val !== null ? $val : $default;
    }
    try {
        $db   = getDB();
        // NOTE: settings are currently global (not per-school). [SCHOOL ISOLATION PENDING]
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function updateSetting($key, $value) {
    $editionKeys = ['registration_start', 'registration_end', 'event_date', 'max_registrations_per_student'];
    if (in_array($key, $editionKeys)) {
        $db   = getDB();
        $stmt = $db->prepare("UPDATE messe_editions SET `$key` = ? WHERE id = ?");
        return $stmt->execute([$value, getActiveEditionId()]);
    }
    $db   = getDB();
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
    
    // Server-seitiger MIME-Typ-Check
    $allowedMimes = [
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'ppt'  => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
    ];
    if (function_exists('finfo_open')) {
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        $expected = $allowedMimes[$extension] ?? null;
        if ($expected === null || $mimeType !== $expected) {
            return ['success' => false, 'message' => 'Dateityp (MIME) nicht erlaubt.'];
        }
    }
    if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
        if (@getimagesize($file['tmp_name']) === false) {
            return ['success' => false, 'message' => 'Datei ist kein gültiges Bild.'];
        }
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
function getRoomSlotCapacity($roomId, $timeslotId, $priority = 2) {
    $db = getDB();
    
    // Erst prüfen ob spezifische Kapazität definiert ist
    $stmt = $db->prepare("SELECT capacity FROM room_slot_capacities WHERE room_id = ? AND timeslot_id = ?");
    $stmt->execute([$roomId, $timeslotId]);
    $result = $stmt->fetch();
    
    if ($result) {
        $customCapacity = intval($result['capacity']);
        
        // Bei Priorität 1 (hoch): Volle Raumkapazität nutzen
        if ($priority == 1) {
            $stmt = $db->prepare("SELECT capacity FROM rooms WHERE id = ?");
            $stmt->execute([$roomId]);
            $room = $stmt->fetch();
            return $room ? max($customCapacity, intval($room['capacity'])) : $customCapacity;
        }
        
        return $customCapacity;
    }
    
    // Fallback: Standard ist 25, bei Priorität 1 die volle Raumkapazität
    $stmt = $db->prepare("SELECT capacity FROM rooms WHERE id = ?");
    $stmt->execute([$roomId]);
    $room = $stmt->fetch();

    if ($room && $room['capacity']) {
        $roomCapacity = intval($room['capacity']);
        
        // Bei Priorität 1 (hohe Nachfrage): Volle Raumkapazität
        if ($priority == 1) {
            return $roomCapacity;
        }
        
        // Standard: Max 25 Schüler pro Slot (oder Raumkapazität wenn kleiner)
        return min(25, $roomCapacity);
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
    $stmt = $db->query("SELECT id FROM timeslots WHERE slot_number " . getManagedSlotsSqlIn() . "");
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
        'orga_team_sehen'                 => ['aussteller_sehen'],
        'orga_team_bearbeiten'            => ['orga_team_sehen'],
        // Branchen
        'branchen_bearbeiten'             => ['branchen_sehen'],
        // Anwesenheit
        'attendance_bearbeiten'           => ['qr_codes_sehen'],
        'aussteller_dokumente_verwalten'  => ['aussteller_sehen'],
        // Branchen (in Aussteller-Tab)
        'branchen_verwalten'              => ['branchen_sehen'],
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
            'branchen_sehen'                 => 'Branchen-Liste im Admin-Bereich einsehen',
            'branchen_verwalten'             => 'Branchen anlegen, bearbeiten und löschen (erfordert: branchen_sehen)',
        ],
        'Branchen-Verwaltung' => [
            'branchen_sehen'      => 'Branchen/Kategorien im Aussteller-Bereich einsehen',
            'branchen_bearbeiten' => 'Branchen anlegen, bearbeiten und löschen (erfordert: branchen_sehen)',
        ],
        'Orga-Team' => [
            'orga_team_sehen'      => 'Orga-Team Tab auf der Aussteller-Seite einsehen (erfordert: aussteller_sehen)',
            'orga_team_bearbeiten' => 'Orga-Mitglieder Ausstellern zuweisen/entfernen (erfordert: orga_team_sehen)',
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
        'Anwesenheit' => [
            'attendance_bearbeiten' => 'Anwesenheit manuell eintragen/entfernen (erfordert: qr_codes_sehen)',
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

/**
 * Prüft ob eine IP-Adresse in einem CIDR-Bereich liegt (IPv4 und IPv6).
 */
function ipInRange(string $ip, string $cidr): bool {
    if (strpos($cidr, '/') === false) {
        return $ip === $cidr;
    }

    [$subnet, $bits] = explode('/', $cidr, 2);
    $bits = (int)$bits;

    $ipBin = @inet_pton($ip);
    $subnetBin = @inet_pton($subnet);

    if ($ipBin === false || $subnetBin === false) {
        return false;
    }

    // Beide müssen die gleiche Adressfamilie haben
    if (strlen($ipBin) !== strlen($subnetBin)) {
        return false;
    }

    // Bitweise Maske berechnen und vergleichen
    $ipHex = bin2hex($ipBin);
    $subnetHex = bin2hex($subnetBin);
    $ipBits = '';
    $subnetBits = '';
    for ($i = 0; $i < strlen($ipHex); $i++) {
        $ipBits .= str_pad(base_convert($ipHex[$i], 16, 2), 4, '0', STR_PAD_LEFT);
        $subnetBits .= str_pad(base_convert($subnetHex[$i], 16, 2), 4, '0', STR_PAD_LEFT);
    }

    return substr($ipBits, 0, $bits) === substr($subnetBits, 0, $bits);
}

/**
 * Ermittelt die echte Client-IP-Adresse, auch hinter Reverse-Proxies.
 */
function getClientIp(): string {
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';

    // IPv4-mapped IPv6 normalisieren (z.B. ::ffff:192.168.1.1 -> 192.168.1.1)
    $normalizeIp = function(string $ip): string {
        if (preg_match('/^::ffff:(\d+\.\d+\.\d+\.\d+)$/i', $ip, $matches)) {
            return $matches[1];
        }
        return $ip;
    };

    $remoteAddr = $normalizeIp($remoteAddr);

    // Prüfen ob REMOTE_ADDR ein Trusted Proxy ist
    $isTrustedProxy = false;
    $trustedProxies = defined('TRUSTED_PROXIES') ? TRUSTED_PROXIES : [];
    foreach ($trustedProxies as $proxy) {
        if (ipInRange($remoteAddr, $proxy)) {
            $isTrustedProxy = true;
            break;
        }
    }

    // Nur wenn Trusted Proxy: Proxy-Header auswerten
    if ($isTrustedProxy) {
        $headersToCheck = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
        ];

        foreach ($headersToCheck as $header) {
            if (empty($_SERVER[$header])) {
                continue;
            }

            if ($header === 'HTTP_X_FORWARDED_FOR') {
                // Kommaseparierte Liste: ersten Nicht-Proxy-Eintrag verwenden
                $ips = array_map('trim', explode(',', $_SERVER[$header]));
                foreach ($ips as $ip) {
                    $ip = $normalizeIp($ip);
                    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                        error_log('getClientIp: Ungültige IP in ' . $header . ': ' . $ip);
                        continue;
                    }
                    // Trusted-Proxy-IPs überspringen
                    $isProxy = false;
                    foreach ($trustedProxies as $proxy) {
                        if (ipInRange($ip, $proxy)) {
                            $isProxy = true;
                            break;
                        }
                    }
                    if (!$isProxy) {
                        return $ip;
                    }
                }
            } else {
                $ip = $normalizeIp(trim($_SERVER[$header]));
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                } else {
                    error_log('getClientIp: Ungültige IP in ' . $header . ': ' . $_SERVER[$header]);
                }
            }
        }
    }

    // Fallback auf REMOTE_ADDR
    if ($remoteAddr !== '' && filter_var($remoteAddr, FILTER_VALIDATE_IP)) {
        return $remoteAddr;
    }

    return '0.0.0.0';
}

// Audit Log System (Issue #21)
// $schoolId: -1 = auto-detect from URL context; null = system-wide (login events); int = explicit
function logAuditAction($action, $details = '', $severity = 'info', ?int $schoolId = -1) {
    try {
        $db = getDB();
        $userId = $_SESSION['user_id'] ?? null;
        $username = $_SESSION['username'] ?? 'System';
        $ipAddress = getClientIp();

        // Sanitize severity – only allow known values
        $allowedSeverities = ['info', 'warning', 'error'];
        $severity = in_array($severity, $allowedSeverities, true) ? $severity : 'info';

        // [SCHOOL ISOLATION] Resolve school_id
        if ($schoolId === -1) {
            $ctxSchool = getCurrentSchool();
            if ($ctxSchool) {
                $schoolId = (int)$ctxSchool['id'];
            } else {
                $raw = $_SESSION['school_id'] ?? null;
                $schoolId = $raw !== null ? (int)$raw : null;
            }
        }

        $stmt = $db->prepare("
            INSERT INTO audit_logs (user_id, username, action, details, severity, ip_address, school_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $username, $action, $details, $severity, $ipAddress, $schoolId]);
    } catch (Exception $e) {
        error_log('Audit Log Error: ' . $e->getMessage());
    }
}

/**
 * Lädt alle aktiven, nicht abgelaufenen Ankündigungen für die gegebene Rolle.
 * Gibt leeres Array zurück wenn die Tabelle noch nicht existiert.
 */
function getActiveAnnouncements(string $role): array {
    try {
        $db   = getDB();
        $now  = date('Y-m-d H:i:s');
        $stmt = $db->prepare("
            SELECT id, title, body, type, target_role
            FROM   announcements
            WHERE  is_active   = 1
              AND  (expires_at IS NULL OR expires_at > ?)
              AND  (target_role = 'all' OR target_role = ?)
            ORDER BY created_at DESC
        ");
        $stmt->execute([$now, $role]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Logs a caught exception or error to the audit log with severity = 'error'.
 * Also calls PHP's error_log() so server logs remain unchanged.
 *
 * @param Throwable $e         The caught exception or error.
 * @param string    $context   Short label for where the error occurred, e.g. 'Registrierung', 'Admin-Benutzer'.
 * @param string    $extraInfo Optional additional detail string to append.
 */
function logErrorToAudit(Throwable $e, string $context = 'Unbekannt', string $extraInfo = ''): void {
    // Always preserve the original error_log behaviour
    error_log('[' . $context . '] ' . get_class($e) . ': ' . $e->getMessage());

    $details = '[' . $context . '] '
        . get_class($e) . ': '
        . $e->getMessage()
        . ' | Datei: ' . basename($e->getFile())
        . ':' . $e->getLine();

    if ($extraInfo !== '') {
        $details .= ' | Info: ' . $extraInfo;
    }

    // Truncate details to prevent oversized DB entries
    $details = mb_substr($details, 0, 2000);

    logAuditAction('error', $details, 'error');
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
    $db = getDB();

    // Gruppen-Zuordnung speichern
    $stmt = $db->prepare("INSERT IGNORE INTO user_permission_groups (user_id, group_id) VALUES (?, ?)");
    $stmt->execute([$userId, $groupId]);

    // Alle Berechtigungen der Gruppe anwenden
    $permissions = getPermissionGroupPermissions($groupId);
    foreach ($permissions as $permission) {
        grantPermission($userId, $permission);
    }
}

// Lade zugeordnete Gruppen eines Benutzers
function getUserPermissionGroups($userId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT group_id FROM user_permission_groups WHERE user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// ==========================================
// Exhibitor-Orga-Team Functions
// ==========================================

/**
 * Checks if a user is assigned to an exhibitor's orga team
 */
function isExhibitorOrgaMember($userId, $exhibitorId) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT COUNT(*) FROM exhibitor_orga_team WHERE user_id = ? AND exhibitor_id = ?");
        $stmt->execute([$userId, $exhibitorId]);
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Gets all exhibitor IDs assigned to an orga team member
 */
function getOrgaExhibitors($userId) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT exhibitor_id FROM exhibitor_orga_team WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Gets all orga team members for a specific exhibitor
 */
function getExhibitorOrgaMembers($exhibitorId) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT u.id, u.username, u.firstname, u.lastname, u.email, eot.assigned_at
            FROM exhibitor_orga_team eot
            JOIN users u ON eot.user_id = u.id
            WHERE eot.exhibitor_id = ?
            ORDER BY u.lastname, u.firstname
        ");
        $stmt->execute([$exhibitorId]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Assigns a user to an exhibitor's orga team
 */
function assignExhibitorOrgaMember($userId, $exhibitorId) {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT IGNORE INTO exhibitor_orga_team (user_id, exhibitor_id) VALUES (?, ?)");
        $stmt->execute([$userId, $exhibitorId]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Removes a user from an exhibitor's orga team
 */
function removeExhibitorOrgaMember($userId, $exhibitorId) {
    try {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM exhibitor_orga_team WHERE user_id = ? AND exhibitor_id = ?");
        $stmt->execute([$userId, $exhibitorId]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Checks if user can access QR codes for an exhibitor
 * Admins and regular orga can access all, exhibitor-orga can only access their assigned ones
 */
// Seitenpasswort-Schutz prüfen
function checkSitePassword() {
    // Prüfen ob Seitenpasswort aktiviert ist
    if (getSetting('site_password_enabled', '0') !== '1') {
        return;
    }

    $sitePassword = getSetting('site_password', '');
    if (empty($sitePassword)) {
        return;
    }

    // Bereits authentifiziert?
    if (isset($_SESSION['site_authenticated']) && $_SESSION['site_authenticated'] === true) {
        return;
    }

    // Redirect zur Passwort-Eingabeseite
    $returnUrl = $_SERVER['REQUEST_URI'] ?? '';
    header('Location: ' . BASE_URL . 'site-auth.php?redirect=' . urlencode($returnUrl));
    exit();
}

function canAccessExhibitorQR($userId, $exhibitorId) {
    // Admins can access everything
    if (isAdmin()) {
        return true;
    }

    // Regular orga (with qr_codes_verwalten permission) can access everything
    if (hasPermission('qr_codes_verwalten')) {
        return true;
    }

    // Exhibitor-specific orga can only access their assigned exhibitors
    return isExhibitorOrgaMember($userId, $exhibitorId);
}

// ─── Messe-Editionen (Mehrjährigkeit) ────────────────────────────────────────

function getActiveEditionId(): int {
    // [SCHOOL ISOLATION] Effektive School-ID bestimmen:
    // 1. URL-Kontext (getCurrentSchool) — innerhalb index.php
    // 2. _prev_school_id — letzte besuchte Schule (für standalone APIs)
    // 3. school_id — Login-Schule (für normale User)
    $ctxSchool = getCurrentSchool();
    $effectiveSchoolId = $ctxSchool
        ? (int)$ctxSchool['id']
        : ($_SESSION['_prev_school_id'] ?? $_SESSION['school_id'] ?? null);

    // Cache nur gültig wenn für die gleiche Schule
    if (isset($_SESSION['active_edition_id']) && isset($_SESSION['_edition_school_id'])
        && $_SESSION['_edition_school_id'] == $effectiveSchoolId) {
        return (int)$_SESSION['active_edition_id'];
    }

    try {
        $db = getDB();
        if ($effectiveSchoolId) {
            $stmt = $db->prepare("SELECT id FROM messe_editions WHERE status = 'active' AND school_id = ? LIMIT 1");
            $stmt->execute([$effectiveSchoolId]);
            $row = $stmt->fetch();
            if ($row) {
                $_SESSION['active_edition_id']  = (int)$row['id'];
                $_SESSION['_edition_school_id'] = $effectiveSchoolId;
                return (int)$row['id'];
            }
        }
        // Fallback: Nur wenn kein Schulkontext vorhanden (sollte selten sein)
        $stmt = $db->query("SELECT id FROM messe_editions WHERE status = 'active' LIMIT 1");
        $row  = $stmt->fetch();
        if ($row) {
            $_SESSION['active_edition_id']  = (int)$row['id'];
            $_SESSION['_edition_school_id'] = $effectiveSchoolId;
            return (int)$row['id'];
        }
    } catch (Exception $e) { /* Tabelle existiert noch nicht */ }
    return 1; // Fallback
}

function getActiveEdition(): array {
    try {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM messe_editions WHERE id = ?");
        $stmt->execute([getActiveEditionId()]);
        $row  = $stmt->fetch();
        if ($row) return $row;
    } catch (Exception $e) { }
    return [
        'id'                            => 1,
        'name'                          => 'Berufsmesse',
        'year'                          => (int)date('Y'),
        'status'                        => 'active',
        'registration_start'            => getSetting('registration_start'),
        'registration_end'              => getSetting('registration_end'),
        'event_date'                    => getSetting('event_date'),
        'max_registrations_per_student' => (int)getSetting('max_registrations_per_student', 3),
    ];
}

function invalidateEditionCache(): void {
    unset($_SESSION['active_edition_id'], $_SESSION['_edition_school_id']);
}

// ─── Multi-Schulen ───────────────────────────────────────────────────────────

/**
 * Liest den school_slug aus URL (GET) oder Session.
 * Gibt die vollständige Schul-Row zurück oder null.
 */
function getCurrentSchool(): ?array {
    static $cache = null;
    if ($cache !== null) return $cache ?: null;

    // URL ist die einzige Quelle für den Schulkontext — kein Session-Fallback
    $slug = $_GET['school_slug'] ?? null;
    if (!$slug) {
        $cache = false;
        return null;
    }
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM schools WHERE slug = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        if ($row) {
            $_SESSION['school_slug'] = $row['slug'];
            $cache = $row;
            return $row;
        }
    } catch (Exception $e) { /* Tabelle existiert noch nicht */ }
    $cache = false;
    return null;
}

/**
 * Gibt die aktive edition_id für eine bestimmte Schule zurück.
 * Falls school_id = NULL → globales Fallback (wie bisher).
 */
function getActiveEditionIdForSchool(int $schoolId): int {
    try {
        $db   = getDB();
        $stmt = $db->prepare("SELECT id FROM messe_editions WHERE status = 'active' AND school_id = ? LIMIT 1");
        $stmt->execute([$schoolId]);
        $row = $stmt->fetch();
        if ($row) return (int)$row['id'];
    } catch (Exception $e) { }
    return getActiveEditionId(); // Fallback
}

/**
 * Generiert eine schulspezifische URL.
 * schoolUrl('index.php?page=dashboard') → /gymnasium-muster/index.php?page=dashboard
 */
function schoolUrl(string $path = ''): string {
    $school = getCurrentSchool();
    if ($school) {
        return BASE_URL . htmlspecialchars($school['slug']) . '/' . ltrim($path, '/');
    }
    return BASE_URL . ltrim($path, '/');
}

/**
 * Prüft ob der eingeloggte User Zugriff auf die aktuelle Schule hat.
 * Admins haben immer Zugriff.
 * Aussteller haben Zugriff auf Schulen, bei denen sie Aussteller sind.
 * Schüler/Lehrer/Orga nur auf ihre eigene Schule.
 */
function hasSchoolAccess(): bool {
    if (isAdmin()) return true;
    $school = getCurrentSchool();
    if (!$school) return true; // Kein Schulkontext → überall erlaubt

    $role = $_SESSION['role'] ?? '';

    if ($role === 'exhibitor') {
        // Aussteller: Prüfe über exhibitor_users → exhibitors → messe_editions → school_id
        try {
            $db   = getDB();
            $stmt = $db->prepare("
                SELECT 1 FROM exhibitor_users eu
                JOIN exhibitors e ON eu.exhibitor_id = e.id
                JOIN messe_editions me ON e.edition_id = me.id
                WHERE eu.user_id = ? AND me.school_id = ?
                LIMIT 1
            ");
            $stmt->execute([$_SESSION['user_id'], $school['id']]);
            return (bool)$stmt->fetch();
        } catch (Exception $e) { return false; }
    }

    // Schüler, Lehrer, Orga, school_admin
    // [SCHOOL ISOLATION] Compare against the login-time school, not the URL-derived one
    return (int)($_SESSION['user_school_id'] ?? 0) === (int)$school['id'];
}

/**
 * Prüft ob der User ein Schul-Admin ist (für die aktuelle Schule).
 */
function isSchoolAdmin(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'school_admin';
}

/**
 * Checks that an exhibitor belongs to the given school.
 * Pass $schoolId = null to skip the check (super-admin bypass).
 * Returns true if the exhibitor is valid for this school.
 */
function exhibitorBelongsToSchool(int $exhibitorId, ?int $schoolId): bool {
    if ($schoolId === null) return true; // super-admin: no restriction
    try {
        $db   = getDB();
        $stmt = $db->prepare("
            SELECT 1 FROM exhibitors e
            JOIN messe_editions me ON e.edition_id = me.id
            WHERE e.id = ? AND me.school_id = ?
            LIMIT 1
        ");
        $stmt->execute([$exhibitorId, $schoolId]);
        return (bool)$stmt->fetchColumn();
    } catch (Exception $e) { return false; }
}

/**
 * Prüft ob der User ein Aussteller ist.
 */
function isExhibitor(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'exhibitor';
}

/**
 * Erfordert globale Admin-Rechte ODER Schul-Admin-Rechte.
 * Schul-Admins werden nur durchgelassen wenn sie Zugriff auf die aktuelle Schule haben.
 */
function requireSchoolAdminOrAdmin(): void {
    requireLogin();
    if (isAdmin()) return;
    if (isSchoolAdmin() && hasSchoolAccess()) return;
    header('Location: ' . BASE_URL . 'index.php');
    exit();
}

/**
 * Gibt die IDs aller Aussteller zurück, die einem User zugeordnet sind.
 */
function getExhibitorIdsForUser(int $userId): array {
    try {
        $db   = getDB();
        $stmt = $db->prepare("SELECT exhibitor_id FROM exhibitor_users WHERE user_id = ?");
        $stmt->execute([$userId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    } catch (Exception $e) { return []; }
}

/**
 * Creates a users row with role='exhibitor' if the username doesn't exist yet,
 * then creates or refreshes the exhibitor_users link with an invite token.
 * Returns ['success'=>true, 'user_id'=>N, 'token'=>'...'] or ['success'=>false, 'error'=>'...']
 */
function createOrLinkExhibitorAccount(
    int    $exhibitorId,
    string $username,
    string $firstname,
    string $lastname,
    string $email = ''
): array {
    try {
        $db = getDB();

        // 1. Find or create the user account
        $stmt = $db->prepare("SELECT id, password, firstname, lastname FROM users WHERE username = ? AND role = 'exhibitor' LIMIT 1");
        $stmt->execute([$username]);
        $existing = $stmt->fetch();

        $accountAlreadyActive = false;

        if ($existing) {
            $userId = (int)$existing['id'];
            // Prüfen ob Vorname und Nachname übereinstimmen
            if (strtolower(trim($existing['firstname'])) === strtolower(trim($firstname))
                && strtolower(trim($existing['lastname'])) === strtolower(trim($lastname))) {
                // Account existiert mit gleichen Daten — kein neues Passwort nötig
                if ($existing['password'] !== null && $existing['password'] !== '') {
                    $accountAlreadyActive = true;
                }
            }
        } else {
            // Check username is not taken by any role
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                return ['success' => false, 'error' => "Benutzername '$username' ist bereits vergeben."];
            }
            $stmt = $db->prepare(
                "INSERT INTO users (username, firstname, lastname, email, role, password, school_id, edition_id)
                 VALUES (?, ?, ?, ?, 'exhibitor', NULL, NULL, NULL)"
            );
            $stmt->execute([$username, $firstname, $lastname, $email ?: null]);
            $userId = (int)$db->lastInsertId();
        }

        // 2. Generate invite token für ALLE Einladungen (auch Re-Invites)
        $token   = bin2hex(random_bytes(32)); // 64 hex chars
        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));

        // 3. Upsert exhibitor_users link
        $stmt = $db->prepare("
            INSERT INTO exhibitor_users
                (user_id, exhibitor_id, can_edit_profile, can_manage_documents,
                 invite_token, invite_accepted, invite_expires)
            VALUES (?, ?, 1, 1, ?, 0, ?)
            ON DUPLICATE KEY UPDATE
                invite_token    = VALUES(invite_token),
                invite_accepted = 0,
                invite_expires  = VALUES(invite_expires),
                status          = 'active',
                cancelled_at    = NULL,
                cancel_reason   = NULL
        ");
        $stmt->execute([$userId, $exhibitorId, $token, $expires]);

        return [
            'success' => true,
            'user_id' => $userId,
            'token' => $token,
            'already_active' => $accountAlreadyActive,
            'requires_confirmation' => true
        ];
    } catch (Exception $e) {
        logErrorToAudit($e, 'createOrLinkExhibitorAccount');
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Verteilt alle Schüler eines Ausstellers auf alternative Aussteller mit Kapazität.
 * Gibt die Anzahl der umverteilten Schüler zurück.
 */
function redistributeStudentsFromExhibitor(int $exhibitorId, int $editionId): int {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT r.id, r.user_id, r.timeslot_id, t.slot_number
        FROM registrations r
        JOIN timeslots t ON r.timeslot_id = t.id
        WHERE r.exhibitor_id = ? AND r.timeslot_id IS NOT NULL AND r.edition_id = ?
    ");
    $stmt->execute([$exhibitorId, $editionId]);
    $affectedRegistrations = $stmt->fetchAll();

    $redistributedCount = 0;
    foreach ($affectedRegistrations as $reg) {
        $studentId = $reg['user_id'];
        $timeslotId = $reg['timeslot_id'];

        $stmt = $db->prepare("
            SELECT e.id, e.name, e.room_id,
                   COUNT(DISTINCT r2.user_id) as current_count
            FROM exhibitors e
            LEFT JOIN registrations r2 ON e.id = r2.exhibitor_id AND r2.timeslot_id = ?
            WHERE e.active = 1 AND e.id != ? AND e.room_id IS NOT NULL AND e.edition_id = ?
              AND e.id NOT IN (SELECT exhibitor_id FROM registrations WHERE user_id = ? AND edition_id = ?)
            GROUP BY e.id, e.name, e.room_id
            ORDER BY current_count ASC, RAND()
            LIMIT 1
        ");
        $stmt->execute([$timeslotId, $exhibitorId, $editionId, $studentId, $editionId]);
        $newExhibitor = $stmt->fetch();

        if ($newExhibitor) {
            $slotCapacity = getRoomSlotCapacity($newExhibitor['room_id'], $timeslotId);
            if ($slotCapacity > 0 && $newExhibitor['current_count'] < $slotCapacity) {
                try {
                    $stmt = $db->prepare("UPDATE registrations SET exhibitor_id = ? WHERE id = ?");
                    if ($stmt->execute([$newExhibitor['id'], $reg['id']])) {
                        $redistributedCount++;
                    }
                } catch (PDOException $e) {
                    logErrorToAudit($e, 'redistributeStudents');
                }
            }
        }
    }

    // Verbleibende Registrierungen (ohne Umverteilung) löschen
    $db->prepare("DELETE FROM registrations WHERE exhibitor_id = ? AND edition_id = ?")->execute([$exhibitorId, $editionId]);

    return $redistributedCount;
}

/**
 * Setzt den Status einer Aussteller-Verknüpfung (Absage/Entfernung).
 * Deaktiviert den Aussteller und verteilt Schüler um.
 */
function cancelExhibitorParticipation(int $exhibitorId, int $userId, string $status, string $reason = ''): array {
    try {
        $db = getDB();

        // Prüfe ob die Verknüpfung existiert
        $stmt = $db->prepare("SELECT eu.id, e.name, e.edition_id FROM exhibitor_users eu JOIN exhibitors e ON eu.exhibitor_id = e.id WHERE eu.exhibitor_id = ? AND eu.user_id = ? AND eu.status = 'active'");
        $stmt->execute([$exhibitorId, $userId]);
        $link = $stmt->fetch();
        if (!$link) {
            return ['success' => false, 'error' => 'Keine aktive Verknüpfung gefunden.'];
        }

        // Status aktualisieren
        $stmt = $db->prepare("UPDATE exhibitor_users SET status = ?, cancelled_at = NOW(), cancel_reason = ? WHERE exhibitor_id = ? AND user_id = ?");
        $stmt->execute([$status, $reason ?: null, $exhibitorId, $userId]);

        // Aussteller deaktivieren wenn keine aktiven Verknüpfungen mehr
        $stmt = $db->prepare("SELECT COUNT(*) FROM exhibitor_users WHERE exhibitor_id = ? AND status = 'active'");
        $stmt->execute([$exhibitorId]);
        $activeLinks = (int)$stmt->fetchColumn();

        $redistributed = 0;
        if ($activeLinks === 0) {
            // Aussteller deaktivieren
            $db->prepare("UPDATE exhibitors SET active = 0 WHERE id = ?")->execute([$exhibitorId]);
            // Schüler umverteilen
            $redistributed = redistributeStudentsFromExhibitor($exhibitorId, (int)$link['edition_id']);
        }

        return ['success' => true, 'name' => $link['name'], 'redistributed' => $redistributed, 'deactivated' => ($activeLinks === 0)];
    } catch (Exception $e) {
        logErrorToAudit($e, 'cancelExhibitorParticipation');
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Prüft ob die Messe innerhalb 1 Woche stattfindet (Bestätigungspflicht für Absagen).
 */
function isWithinOneWeekOfEvent(int $editionId): bool {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT start_date FROM messe_editions WHERE id = ?");
        $stmt->execute([$editionId]);
        $edition = $stmt->fetch();
        if (!$edition || !$edition['start_date']) return false;

        $startDate = strtotime($edition['start_date']);
        $now = time();
        $oneWeek = 7 * 24 * 60 * 60;

        return ($startDate - $now) <= $oneWeek;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Erstellt eine Login-Benachrichtigung für einen User.
 */
function createLoginNotification(int $userId, string $message, string $type, ?int $schoolId = null, ?int $relatedId = null, ?string $actionUrl = null): bool {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO login_notifications (user_id, school_id, message, type, related_id, action_url)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$userId, $schoolId, $message, $type, $relatedId, $actionUrl]);
    } catch (Exception $e) {
        logErrorToAudit($e, 'createLoginNotification');
        return false;
    }
}

/**
 * Erstellt Benachrichtigungen für alle Admins/Orga-Team einer Schule.
 */
function notifySchoolAdmins(int $schoolId, string $message, string $type, ?int $relatedId = null, ?string $actionUrl = null): int {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT DISTINCT u.id FROM users u
            LEFT JOIN user_permissions up ON u.id = up.user_id
            WHERE u.school_id = ?
              AND u.is_active = 1
              AND (u.role IN ('admin', 'school_admin', 'orga')
                   OR up.permission IN ('aussteller_sehen', 'aussteller_bearbeiten'))
        ");
        $stmt->execute([$schoolId]);
        $users = $stmt->fetchAll();

        $count = 0;
        foreach ($users as $user) {
            if (createLoginNotification((int)$user['id'], $message, $type, $schoolId, $relatedId, $actionUrl)) {
                $count++;
            }
        }
        return $count;
    } catch (Exception $e) {
        logErrorToAudit($e, 'notifySchoolAdmins');
        return 0;
    }
}

/**
 * Erstellt einen Absage-Antrag (mit oder ohne Bestätigungspflicht).
 * Gibt ['success' => true, 'requires_confirmation' => bool, 'request_id' => int|null] zurück.
 */
function createCancellationRequest(int $exhibitorId, int $userId, int $schoolId, string $requestedBy, string $reason = ''): array {
    try {
        $db = getDB();

        // Hole Edition-ID des Ausstellers
        $stmt = $db->prepare("SELECT edition_id, name FROM exhibitors WHERE id = ?");
        $stmt->execute([$exhibitorId]);
        $exhibitor = $stmt->fetch();
        if (!$exhibitor) {
            return ['success' => false, 'error' => 'Aussteller nicht gefunden.'];
        }

        $editionId = (int)$exhibitor['edition_id'];
        $exhibitorName = $exhibitor['name'];
        $requiresConfirmation = isWithinOneWeekOfEvent($editionId);

        if (!$requiresConfirmation) {
            // Direkte Absage ohne Bestätigung
            $status = $requestedBy === 'exhibitor' ? 'cancelled_by_exhibitor' : 'cancelled_by_school';
            $result = cancelExhibitorParticipation($exhibitorId, $userId, $status, $reason);

            if ($result['success']) {
                // Benachrichtigungen erstellen
                if ($requestedBy === 'exhibitor') {
                    notifySchoolAdmins($schoolId, "Aussteller '$exhibitorName' hat die Teilnahme abgesagt.", 'exhibitor_cancelled', $exhibitorId);
                } else {
                    createLoginNotification($userId, "Die Schule hat Ihre Teilnahme für '$exhibitorName' beendet.", 'school_cancelled', $schoolId, $exhibitorId);
                }
            }

            return array_merge($result, ['requires_confirmation' => false, 'request_id' => null]);
        }

        // Bestätigung erforderlich - Absage-Antrag erstellen
        $stmt = $db->prepare("
            INSERT INTO cancellation_requests (exhibitor_id, user_id, school_id, requested_by, reason)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$exhibitorId, $userId, $schoolId, $requestedBy, $reason ?: null]);
        $requestId = (int)$db->lastInsertId();

        // Benachrichtigungen für die Gegenseite erstellen
        if ($requestedBy === 'exhibitor') {
            $message = "Aussteller '$exhibitorName' möchte die Teilnahme absagen und bittet um Bestätigung.";
            notifySchoolAdmins($schoolId, $message, 'cancellation_request', $requestId);
        } else {
            $message = "Die Schule möchte Ihre Teilnahme für '$exhibitorName' beenden und bittet um Bestätigung.";
            createLoginNotification($userId, $message, 'cancellation_request', $schoolId, $requestId);
        }

        logAuditAction('absage_antrag_erstellt', "Absage-Antrag für '$exhibitorName' von $requestedBy erstellt (ID: $requestId)");

        return ['success' => true, 'requires_confirmation' => true, 'request_id' => $requestId, 'name' => $exhibitorName];
    } catch (Exception $e) {
        logErrorToAudit($e, 'createCancellationRequest');
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Bestätigt oder lehnt einen Absage-Antrag ab.
 */
function confirmCancellationRequest(int $requestId, int $confirmingUserId, bool $approve): array {
    try {
        $db = getDB();

        $stmt = $db->prepare("
            SELECT cr.*, e.name as exhibitor_name, e.edition_id
            FROM cancellation_requests cr
            JOIN exhibitors e ON cr.exhibitor_id = e.id
            WHERE cr.id = ? AND cr.status = 'pending'
        ");
        $stmt->execute([$requestId]);
        $request = $stmt->fetch();

        if (!$request) {
            return ['success' => false, 'error' => 'Absage-Antrag nicht gefunden oder bereits bearbeitet.'];
        }

        $newStatus = $approve ? 'confirmed' : 'rejected';
        $stmt = $db->prepare("
            UPDATE cancellation_requests
            SET status = ?, confirmed_at = NOW(), confirmed_by = ?
            WHERE id = ?
        ");
        $stmt->execute([$newStatus, $confirmingUserId, $requestId]);

        if ($approve) {
            // Absage durchführen
            $cancelStatus = $request['requested_by'] === 'exhibitor' ? 'cancelled_by_exhibitor' : 'cancelled_by_school';
            $result = cancelExhibitorParticipation((int)$request['exhibitor_id'], (int)$request['user_id'], $cancelStatus, $request['reason'] ?? '');

            // Benachrichtigungen erstellen
            if ($request['requested_by'] === 'exhibitor') {
                createLoginNotification((int)$request['user_id'], "Ihre Absage für '{$request['exhibitor_name']}' wurde von der Schule bestätigt.", 'exhibitor_cancelled', (int)$request['school_id'], (int)$request['exhibitor_id']);
            } else {
                notifySchoolAdmins((int)$request['school_id'], "Die Absage von '{$request['exhibitor_name']}' wurde bestätigt.", 'school_cancelled', (int)$request['exhibitor_id']);
            }

            logAuditAction('absage_bestaetigt', "Absage-Antrag #{$requestId} für '{$request['exhibitor_name']}' bestätigt");
            return array_merge($result, ['confirmed' => true]);
        } else {
            // Absage abgelehnt
            if ($request['requested_by'] === 'exhibitor') {
                createLoginNotification((int)$request['user_id'], "Ihre Absage für '{$request['exhibitor_name']}' wurde von der Schule abgelehnt.", 'info', (int)$request['school_id']);
            } else {
                notifySchoolAdmins((int)$request['school_id'], "Der Aussteller hat die Absage für '{$request['exhibitor_name']}' abgelehnt.", 'info');
            }

            logAuditAction('absage_abgelehnt', "Absage-Antrag #{$requestId} für '{$request['exhibitor_name']}' abgelehnt");
            return ['success' => true, 'confirmed' => false, 'rejected' => true];
        }
    } catch (Exception $e) {
        logErrorToAudit($e, 'confirmCancellationRequest');
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Nimmt eine Einladung an (für bereits existierende Accounts ohne Passwort-Änderung).
 * Verwendet entweder Token oder direkte Bestätigung.
 */
function acceptExhibitorInvitation(int $userId, int $exhibitorId, ?string $token = null): array {
    try {
        $db = getDB();

        // Hole Einladung
        if ($token) {
            $stmt = $db->prepare("
                SELECT eu.*, e.name FROM exhibitor_users eu
                JOIN exhibitors e ON eu.exhibitor_id = e.id
                WHERE eu.invite_token = ? AND eu.user_id = ? AND eu.exhibitor_id = ?
                  AND (eu.invite_expires IS NULL OR eu.invite_expires > NOW())
                LIMIT 1
            ");
            $stmt->execute([$token, $userId, $exhibitorId]);
        } else {
            $stmt = $db->prepare("
                SELECT eu.*, e.name FROM exhibitor_users eu
                JOIN exhibitors e ON eu.exhibitor_id = e.id
                WHERE eu.user_id = ? AND eu.exhibitor_id = ?
                  AND eu.invite_accepted = 0
                LIMIT 1
            ");
            $stmt->execute([$userId, $exhibitorId]);
        }

        $invite = $stmt->fetch();
        if (!$invite) {
            return ['success' => false, 'error' => 'Einladung nicht gefunden oder abgelaufen.'];
        }

        // Einladung annehmen
        $stmt = $db->prepare("
            UPDATE exhibitor_users
            SET invite_accepted = 1, invite_token = NULL, invite_expires = NULL
            WHERE user_id = ? AND exhibitor_id = ?
        ");
        $stmt->execute([$userId, $exhibitorId]);

        // Aussteller aktivieren
        $db->prepare("UPDATE exhibitors SET active = 1 WHERE id = ?")->execute([$exhibitorId]);

        logAuditAction('einladung_angenommen', "Aussteller '{$invite['name']}' (ID: $exhibitorId) Einladung von User #$userId angenommen");

        return ['success' => true, 'name' => $invite['name']];
    } catch (Exception $e) {
        logErrorToAudit($e, 'acceptExhibitorInvitation');
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Generiert einen URL-sicheren Slug aus einem Schulnamen.
 * Prüft Eindeutigkeit in der DB.
 */
function generateSchoolSlug(string $name): string {
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');

    $db = getDB();
    $baseSlug = $slug;
    $counter = 1;
    while (true) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM schools WHERE slug = ?");
        $stmt->execute([$slug]);
        if ($stmt->fetchColumn() == 0) break;
        $slug = $baseSlug . '-' . $counter++;
    }
    return $slug;
}

// ─────────────────────────────────────────────────────────────────────────────
?>
