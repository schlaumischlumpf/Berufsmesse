<?php
/**
 * Raum-Roster (QR-Check-in 2.0)
 * Liefert für einen Raum + Zeitslot die eingecheckten und noch fehlenden
 * Schüler. Wird vom Lehrer-Scanner per Polling abgefragt.
 */
require_once '../config.php';
require_once '../functions.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !(isTeacher() || isAdminOrSchoolAdmin())) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Keine Berechtigung']);
    exit;
}

try {
    $db = getDB();
    $activeEditionId = getActiveEditionId();
    $roomId     = (int)($_GET['room_id'] ?? 0);
    $timeslotId = (int)($_GET['timeslot_id'] ?? 0);

    if (!$roomId || !$timeslotId) {
        echo json_encode(['success' => false, 'message' => 'Raum oder Zeitslot fehlt']);
        exit;
    }

    // Aussteller des Raums
    $stmt = $db->prepare("SELECT id, name FROM exhibitors WHERE room_id = ? AND edition_id = ? AND active = 1 LIMIT 1");
    $stmt->execute([$roomId, $activeEditionId]);
    $exhibitor = $stmt->fetch();
    if (!$exhibitor) {
        echo json_encode(['success' => true, 'exhibitor' => null, 'present' => [], 'missing' => [],
            'message' => 'Kein Aussteller in diesem Raum']);
        exit;
    }
    $exhibitorId = (int)$exhibitor['id'];

    // Eingecheckt
    $stmt = $db->prepare("
        SELECT u.id, u.firstname, u.lastname, u.class,
               a.checked_in_at, a.checkin_method, a.wrong_room
        FROM attendance a JOIN users u ON a.user_id = u.id
        WHERE a.exhibitor_id = ? AND a.timeslot_id = ? AND a.edition_id = ?
        ORDER BY a.checked_in_at DESC");
    $stmt->execute([$exhibitorId, $timeslotId, $activeEditionId]);
    $present = [];
    $presentIds = [];
    foreach ($stmt->fetchAll() as $row) {
        $presentIds[(int)$row['id']] = true;
        $present[] = [
            'id' => (int)$row['id'],
            'name' => trim($row['firstname'] . ' ' . $row['lastname']),
            'class' => $row['class'],
            'time' => $row['checked_in_at'] ? date('H:i', strtotime($row['checked_in_at'])) : '',
            'method' => $row['checkin_method'],
            'wrong_room' => (bool)$row['wrong_room'],
        ];
    }

    // Registriert (Soll) → daraus Fehlende ableiten
    $stmt = $db->prepare("
        SELECT u.id, u.firstname, u.lastname, u.class
        FROM registrations r JOIN users u ON r.user_id = u.id
        WHERE r.exhibitor_id = ? AND r.timeslot_id = ? AND r.edition_id = ?
        ORDER BY u.lastname, u.firstname");
    $stmt->execute([$exhibitorId, $timeslotId, $activeEditionId]);
    $missing = [];
    foreach ($stmt->fetchAll() as $row) {
        if (isset($presentIds[(int)$row['id']])) continue;
        $missing[] = [
            'id' => (int)$row['id'],
            'name' => trim($row['firstname'] . ' ' . $row['lastname']),
            'class' => $row['class'],
        ];
    }

    echo json_encode([
        'success' => true,
        'exhibitor' => $exhibitor['name'],
        'present' => $present,
        'missing' => $missing,
        'present_count' => count($present),
        'expected_count' => count($present) + count($missing),
    ]);

} catch (Exception $e) {
    logErrorToAudit($e, 'API-RoomRoster');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ein interner Fehler ist aufgetreten.']);
}
