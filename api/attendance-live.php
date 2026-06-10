<?php
/**
 * Live-Anwesenheit (QR-Check-in 2.0)
 * Liefert pro Raum/Aussteller die Anwesenheitszahlen für einen Slot + Gesamtstatistik.
 * Wird vom Live-Dashboard per Polling abgefragt.
 */
require_once '../config.php';
require_once '../functions.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !(isAdminOrSchoolAdmin() || hasPermission('qr_codes_sehen') || hasPermission('attendance_bearbeiten'))) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Keine Berechtigung']);
    exit;
}

try {
    $db = getDB();
    $activeEditionId = getActiveEditionId();
    $timeslotId = (int)($_GET['timeslot_id'] ?? 0);
    if (!$timeslotId) {
        echo json_encode(['success' => false, 'message' => 'Kein Slot angegeben']);
        exit;
    }

    $stmt = $db->prepare("
        SELECT e.id, e.name, r.id AS room_id, r.room_number, r.room_name,
            (SELECT COUNT(*) FROM attendance a WHERE a.exhibitor_id = e.id AND a.timeslot_id = ? AND a.edition_id = ?) AS present,
            (SELECT COUNT(*) FROM registrations rg WHERE rg.exhibitor_id = e.id AND rg.timeslot_id = ? AND rg.edition_id = ?) AS registered,
            (SELECT COUNT(*) FROM attendance a WHERE a.exhibitor_id = e.id AND a.timeslot_id = ? AND a.edition_id = ? AND a.wrong_room = 1) AS wrong
        FROM exhibitors e
        LEFT JOIN rooms r ON e.room_id = r.id
        WHERE e.edition_id = ? AND e.active = 1 AND e.room_id IS NOT NULL
        ORDER BY r.room_number");
    $stmt->execute([$timeslotId, $activeEditionId, $timeslotId, $activeEditionId, $timeslotId, $activeEditionId, $activeEditionId]);

    $rooms = [];
    $totalPresent = 0; $totalRegistered = 0; $totalWrong = 0;
    foreach ($stmt->fetchAll() as $row) {
        $present = (int)$row['present'];
        $registered = (int)$row['registered'];
        $expected = max($registered, $present);
        $pct = $expected > 0 ? round($present / $expected * 100) : 0;
        $totalPresent += $present;
        $totalRegistered += $registered;
        $totalWrong += (int)$row['wrong'];
        $rooms[] = [
            'exhibitor_id' => (int)$row['id'],
            'room_id' => (int)$row['room_id'],
            'exhibitor' => $row['name'],
            'room' => trim(($row['room_number'] ?? '') . (!empty($row['room_name']) ? ' · ' . $row['room_name'] : '')),
            'present' => $present,
            'expected' => $expected,
            'wrong' => (int)$row['wrong'],
            'pct' => $pct,
        ];
    }

    echo json_encode([
        'success' => true,
        'rooms' => $rooms,
        'total_present' => $totalPresent,
        'total_expected' => max($totalRegistered, $totalPresent),
        'total_missing' => max(0, max($totalRegistered, $totalPresent) - $totalPresent),
        'total_wrong' => $totalWrong,
    ]);

} catch (Exception $e) {
    logErrorToAudit($e, 'API-AttendanceLive');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ein interner Fehler ist aufgetreten.']);
}
