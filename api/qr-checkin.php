<?php
/**
 * QR-Code Check-In API (Issue #15)
 * Schüler scannen QR-Code und werden als anwesend markiert
 */
session_start();
require_once '../config.php';
require_once '../functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Nicht eingeloggt']);
    exit;
}

$db = getDB();
$data = json_decode(file_get_contents('php://input'), true);
$token = $data['token'] ?? $_GET['token'] ?? '';

if (empty($token)) {
    echo json_encode(['success' => false, 'message' => 'Kein QR-Token angegeben']);
    exit;
}

try {
    // Token validieren
    $stmt = $db->prepare("
        SELECT qt.*, e.name as exhibitor_name, t.slot_name, t.slot_number
        FROM qr_tokens qt
        JOIN exhibitors e ON qt.exhibitor_id = e.id
        JOIN timeslots t ON qt.timeslot_id = t.id
        WHERE qt.token = ? AND (qt.expires_at IS NULL OR qt.expires_at > NOW())
    ");
    $stmt->execute([$token]);
    $qrToken = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$qrToken) {
        echo json_encode(['success' => false, 'message' => 'Ungültiger oder abgelaufener QR-Code']);
        exit;
    }
    
    $userId = $_SESSION['user_id'];
    $exhibitorId = $qrToken['exhibitor_id'];
    $timeslotId = $qrToken['timeslot_id'];
    $slotNumber = $qrToken['slot_number'];

    // Für Slots 1, 3, 5 (feste Zuteilung): Prüfen ob registriert
    // Für Slots 2, 4 (freie Wahl): Keine Registrierung erforderlich
    if (in_array($slotNumber, [1, 3, 5])) {
        $stmt = $db->prepare("
            SELECT id FROM registrations
            WHERE user_id = ? AND exhibitor_id = ? AND timeslot_id = ?
        ");
        $stmt->execute([$userId, $exhibitorId, $timeslotId]);
        $registration = $stmt->fetch();

        if (!$registration) {
            echo json_encode([
                'success' => false,
                'message' => 'Du bist nicht für ' . $qrToken['exhibitor_name'] . ' in ' . $qrToken['slot_name'] . ' registriert.'
            ]);
            exit;
        }
    }
    
    // Prüfen ob bereits eingecheckt
    $stmt = $db->prepare("
        SELECT id FROM attendance 
        WHERE user_id = ? AND exhibitor_id = ? AND timeslot_id = ?
    ");
    $stmt->execute([$userId, $exhibitorId, $timeslotId]);
    
    if ($stmt->fetch()) {
        echo json_encode([
            'success' => true, 
            'message' => 'Du bist bereits als anwesend markiert bei ' . $qrToken['exhibitor_name'] . '.',
            'already_checked_in' => true
        ]);
        exit;
    }
    
    // Anwesenheit eintragen
    $stmt = $db->prepare("
        INSERT INTO attendance (user_id, exhibitor_id, timeslot_id, qr_token) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $exhibitorId, $timeslotId, $token]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Anwesenheit erfolgreich bestätigt für ' . $qrToken['exhibitor_name'] . ' (' . $qrToken['slot_name'] . ')',
        'exhibitor' => $qrToken['exhibitor_name'],
        'slot' => $qrToken['slot_name']
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
}
