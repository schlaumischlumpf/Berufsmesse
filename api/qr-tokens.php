<?php
/**
 * QR-Code Token generieren/abrufen (Issue #15)
 * Generiert für jeden Aussteller pro Slot einen temporären QR-Token
 */
require_once '../config.php';
require_once '../functions.php';

header('Content-Type: application/json');

if (!isAdmin() && !hasPermission('qr_codes_erstellen')) {
    echo json_encode(['success' => false, 'message' => 'Keine Berechtigung']);
    exit;
}

$db = getDB();
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? $_GET['action'] ?? 'generate';

try {
    if ($action === 'generate') {
        // QR-Tokens für alle Aussteller und Slots generieren/erneuern
        $exhibitorId = intval($data['exhibitor_id'] ?? 0);
        $timeslotId = intval($data['timeslot_id'] ?? 0);

        $eventDate     = getSetting('event_date');
        $validityAfter = intval(getSetting('qr_validity_after', 15));

        if ($exhibitorId && $timeslotId) {
            // Einzelnen Token generieren (6 Zeichen)
            $token = bin2hex(random_bytes(3));

            $tsStmt = $db->prepare("SELECT end_time FROM timeslots WHERE id = ?");
            $tsStmt->execute([$timeslotId]);
            $tsData = $tsStmt->fetch(PDO::FETCH_ASSOC);

            if ($eventDate && $tsData && !empty($tsData['end_time'])) {
                $tsEnd = strtotime("$eventDate " . $tsData['end_time']);
                $expiresAt = $tsEnd !== false
                    ? date('Y-m-d H:i:s', $tsEnd + $validityAfter * 60)
                    : date('Y-m-d H:i:s', strtotime('+24 hours'));
            } else {
                $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
            }
            
            $stmt = $db->prepare("
                INSERT INTO qr_tokens (exhibitor_id, timeslot_id, token, expires_at) 
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at), created_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$exhibitorId, $timeslotId, $token, $expiresAt]);
            
            echo json_encode(['success' => true, 'token' => $token, 'expires_at' => $expiresAt]);
        } else {
            // Alle Tokens generieren
            $stmt = $db->query("SELECT id FROM exhibitors WHERE active = 1");
            $exhibitors = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $stmt = $db->query("SELECT id, end_time FROM timeslots ORDER BY slot_number ASC");
            $timeslots = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $generated = 0;
            foreach ($exhibitors as $exId) {
                foreach ($timeslots as $ts) {
                    $token = bin2hex(random_bytes(3)); // 6 Zeichen
                    if ($eventDate && !empty($ts['end_time'])) {
                        $tsEnd = strtotime("$eventDate " . $ts['end_time']);
                        $expiresAt = $tsEnd !== false
                            ? date('Y-m-d H:i:s', $tsEnd + $validityAfter * 60)
                            : date('Y-m-d H:i:s', strtotime('+24 hours'));
                    } else {
                        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
                    }
                    
                    $stmt = $db->prepare("
                        INSERT INTO qr_tokens (exhibitor_id, timeslot_id, token, expires_at) 
                        VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at), created_at = CURRENT_TIMESTAMP
                    ");
                    $stmt->execute([$exId, $ts['id'], $token, $expiresAt]);
                    $generated++;
                }
            }
            
            echo json_encode(['success' => true, 'generated' => $generated]);
        }
    } elseif ($action === 'list') {
        // Alle Tokens abrufen
        $stmt = $db->query("
            SELECT qt.*, e.name as exhibitor_name, t.slot_name, t.slot_number
            FROM qr_tokens qt
            JOIN exhibitors e ON qt.exhibitor_id = e.id
            JOIN timeslots t ON qt.timeslot_id = t.id
            WHERE e.active = 1
            ORDER BY e.name, t.slot_number
        ");
        $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'tokens' => $tokens]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Unbekannte Aktion']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
