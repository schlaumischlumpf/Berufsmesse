<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

header('Content-Type: application/json');

// Nur für Admins oder Benutzer mit Raum-Bearbeitung
if (!isAdmin() && !hasPermission('raeume_bearbeiten')) {
    echo json_encode(['success' => false, 'message' => 'Keine Berechtigung']);
    exit;
}

try {
    // JSON Input abrufen
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['exhibitor_id'])) {
        echo json_encode(['success' => false, 'message' => 'Aussteller-ID fehlt']);
        exit;
    }
    
    $exhibitorId = (int)$input['exhibitor_id'];
    $roomId = isset($input['room_id']) && $input['room_id'] !== null ? (int)$input['room_id'] : null;
    
    $db = getDB();
    
    // Aussteller existiert?
    $stmt = $db->prepare("SELECT id, name FROM exhibitors WHERE id = ?");
    $stmt->execute([$exhibitorId]);
    $exhibitor = $stmt->fetch();
    
    if (!$exhibitor) {
        echo json_encode(['success' => false, 'message' => 'Aussteller nicht gefunden']);
        exit;
    }
    
    // Wenn room_id gesetzt ist, prüfen ob Raum existiert
    if ($roomId !== null) {
        $stmt = $db->prepare("SELECT id, room_number, room_name FROM rooms WHERE id = ?");
        $stmt->execute([$roomId]);
        $room = $stmt->fetch();
        
        if (!$room) {
            echo json_encode(['success' => false, 'message' => 'Raum nicht gefunden']);
            exit;
        }
    }
    
    // Zuordnung aktualisieren
    $stmt = $db->prepare("UPDATE exhibitors SET room_id = ? WHERE id = ?");
    $stmt->execute([$roomId, $exhibitorId]);
    
    if ($roomId === null) {
        $message = 'Raum-Zuordnung wurde entfernt';
    } else {
        $message = 'Aussteller wurde Raum zugeordnet';
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'exhibitor' => $exhibitor['name'],
        'room' => $roomId ? $room['room_number'] : null
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Fehler beim Zuordnen: ' . $e->getMessage()
    ]);
}
