<?php
/**
 * Anwesenheits-Export als CSV (QR-Check-in 2.0)
 * Filter: timeslot_id, class (optional). Spalten inkl. Methode, Uhrzeit,
 * falscher Raum, eingecheckt von.
 */
require_once '../config.php';
require_once '../functions.php';

if (!isLoggedIn() || !(isAdminOrSchoolAdmin() || hasPermission('berichte_sehen') || hasPermission('qr_codes_sehen') || hasPermission('attendance_bearbeiten'))) {
    http_response_code(403);
    die('Keine Berechtigung');
}

try {
    $db = getDB();
    $activeEditionId = getActiveEditionId();
    $schoolId = getCurrentSchoolId();
    $timeslotId = (int)($_GET['timeslot_id'] ?? 0);
    $class = trim($_GET['class'] ?? '');

    $sql = "
        SELECT u.class, u.firstname, u.lastname,
               e.name AS exhibitor, rm.room_number,
               t.slot_name, a.checkin_method, a.wrong_room, a.checked_in_at,
               ci.firstname AS ci_first, ci.lastname AS ci_last
        FROM attendance a
        JOIN users u ON a.user_id = u.id
        JOIN exhibitors e ON a.exhibitor_id = e.id
        JOIN timeslots t ON a.timeslot_id = t.id
        LEFT JOIN rooms rm ON a.actual_room_id = rm.id
        LEFT JOIN users ci ON a.checked_in_by = ci.id
        WHERE a.edition_id = ?";
    $params = [$activeEditionId];
    if ($schoolId !== null) { $sql .= " AND u.school_id = ?"; $params[] = $schoolId; }
    if ($timeslotId) { $sql .= " AND a.timeslot_id = ?"; $params[] = $timeslotId; }
    if ($class !== '') { $sql .= " AND u.class = ?"; $params[] = $class; }
    $sql .= " ORDER BY t.slot_number, u.class, u.lastname, u.firstname";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $methodLabels = ['self_scan' => 'Selbst-Scan', 'teacher_scan' => 'Lehrer-Scan', 'manual' => 'Manuell'];

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="anwesenheit_' . date('Y-m-d_His') . '.csv"');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM für Excel
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Klasse', 'Nachname', 'Vorname', 'Aussteller', 'Raum', 'Slot', 'Methode', 'Falscher Raum', 'Uhrzeit', 'Eingecheckt von'], ';');
    foreach ($stmt->fetchAll() as $r) {
        fputcsv($out, [
            $r['class'],
            $r['lastname'],
            $r['firstname'],
            $r['exhibitor'],
            $r['room_number'],
            $r['slot_name'],
            $methodLabels[$r['checkin_method']] ?? $r['checkin_method'],
            $r['wrong_room'] ? 'ja' : '',
            $r['checked_in_at'] ? date('d.m.Y H:i', strtotime($r['checked_in_at'])) : '',
            trim(($r['ci_first'] ?? '') . ' ' . ($r['ci_last'] ?? '')),
        ], ';');
    }
    fclose($out);

} catch (Exception $e) {
    logErrorToAudit($e, 'API-ExportAttendance');
    http_response_code(500);
    die('Fehler beim Export.');
}
