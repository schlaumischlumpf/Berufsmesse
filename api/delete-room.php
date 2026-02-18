<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

header('Content-Type: application/json');

// Nur für Admins oder Benutzer mit Raum-Löschen
if (!isAdmin() && !hasPermission('raeume_loeschen')) {
    echo json_encode(['success' => false, 'message' => 'Keine Berechtigung']);
    exit;
}

try {
    // JSON Input abrufen
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['room_id'])) {
        echo json_encode(['success' => false, 'message' => 'Raum-ID fehlt']);
        exit;
    }
    
    $roomId = (int)$input['room_id'];
    
    $db = getDB();
    
    // Raum existiert?
    $stmt = $db->prepare("SELECT id, room_number, room_name FROM rooms WHERE id = ?");
    $stmt->execute([$roomId]);
    $room = $stmt->fetch();
    
    if (!$room) {
        echo json_encode(['success' => false, 'message' => 'Raum nicht gefunden']);
        exit;
    }
    
    // Prüfen ob Raum ungenutzt ist (keine Aussteller zugeordnet)
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM exhibitors WHERE room_id = ?");
    $stmt->execute([$roomId]);
    $exhibitorCount = $stmt->fetch()['count'];
    
    if ($exhibitorCount > 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'Dieser Raum kann nicht gelöscht werden, da ihm noch ' . $exhibitorCount . ' Aussteller zugeordnet sind.'
        ]);
        exit;
    }
    
    // Prüfen ob Raum in room_slot_capacities verwendet wird
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM room_slot_capacities WHERE room_id = ?");
    $stmt->execute([$roomId]);
    $capacityCount = $stmt->fetch()['count'];
    
    // Transaktion starten für konsistente Löschung
    $db->beginTransaction();
    
    try {
        // Erst room_slot_capacities löschen (falls vorhanden)
        if ($capacityCount > 0) {
            $stmt = $db->prepare("DELETE FROM room_slot_capacities WHERE room_id = ?");
            $stmt->execute([$roomId]);
        }
        
        // Dann den Raum löschen
        $stmt = $db->prepare("DELETE FROM rooms WHERE id = ?");
        $stmt->execute([$roomId]);
        
        // Transaktion bestätigen
        $db->commit();
        
        $roomName = $room['room_number'];
        if ($room['room_name']) {
            $roomName .= ' - ' . $room['room_name'];
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Raum wurde erfolgreich gelöscht',
            'room' => $roomName
        ]);
        
    } catch (Exception $e) {
        // Rollback bei Fehler
        $db->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Fehler beim Löschen: ' . $e->getMessage()
    ]);
}
