<?php
/**
 * Lehrer-Check-in API (QR-Check-in 2.0)
 *
 * Ein Aufsichtslehrer scannt den persönlichen QR-Code eines Schülers (oder
 * sucht ihn manuell) und markiert ihn für seinen Raum/Slot als anwesend.
 *
 * Eingabe (JSON oder POST):
 *   student_token   ODER  student_user_id   (manuell)
 *   room_id, timeslot_id
 *   force = 1   (Bestätigung „Trotzdem einchecken" bei falschem Raum)
 *
 * Antworten enthalten ein `status`-Feld:
 *   checked_in | already | requires_confirmation | error
 */
require_once '../config.php';
require_once '../functions.php';

header('Content-Type: application/json');

function respond(array $payload): void {
    echo json_encode($payload);
    exit;
}

if (!isLoggedIn() || !(isTeacher() || isAdminOrSchoolAdmin())) {
    http_response_code(403);
    respond(['success' => false, 'status' => 'error', 'message' => 'Keine Berechtigung']);
}

requireCsrf();

$db = getDB();
$activeEditionId = getActiveEditionId();
$teacherId = (int)$_SESSION['user_id'];
$ip = getClientIp();

// Lehrer-Check-in muss für die Schule aktiviert sein
$schoolId = getCurrentSchoolId();
if (getSchoolSetting($schoolId, 'checkin_teacher_scan_enabled') !== '1') {
    respond(['success' => false, 'status' => 'error', 'message' => 'Der Lehrer-Check-in ist für diese Schule nicht aktiviert.']);
}

// Rate-Limiting
if (isCheckinRateLimited($teacherId, $ip)) {
    http_response_code(429);
    respond(['success' => false, 'status' => 'error', 'message' => 'Zu viele Scan-Versuche. Bitte kurz warten.']);
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$studentToken = trim($data['student_token'] ?? $_POST['student_token'] ?? '');
$studentUserId = (int)($data['student_user_id'] ?? $_POST['student_user_id'] ?? 0);
$roomId     = (int)($data['room_id'] ?? $_POST['room_id'] ?? 0);
$timeslotId = (int)($data['timeslot_id'] ?? $_POST['timeslot_id'] ?? 0);
$force      = !empty($data['force']) || !empty($_POST['force']);
$isManual   = $studentUserId > 0 && $studentToken === '';

try {
    if (!$roomId || !$timeslotId) {
        respond(['success' => false, 'status' => 'error', 'message' => 'Raum oder Zeitslot fehlt.']);
    }

    // Schüler auflösen
    if ($isManual) {
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND role = 'student' LIMIT 1");
        $stmt->execute([$studentUserId]);
        $student = $stmt->fetch() ?: null;
    } else {
        if ($studentToken === '') {
            respond(['success' => false, 'status' => 'error', 'message' => 'Kein Schüler-Code erkannt.']);
        }
        // Versehentlich Raum-QR gescannt?
        if (strncmp($studentToken, 'S-', 2) !== 0) {
            recordCheckinAttempt($teacherId, $ip, false);
            respond(['success' => false, 'status' => 'error', 'message' => 'Das ist kein Schüler-Code. Bitte den persönlichen QR-Code des Schülers scannen.']);
        }
        $student = getStudentByToken($studentToken, $activeEditionId);
    }

    if (!$student) {
        recordCheckinAttempt($teacherId, $ip, false);
        respond(['success' => false, 'status' => 'error', 'message' => 'Ungültiger oder gesperrter Schüler-Code.']);
    }

    // Schulkontext prüfen
    if ($schoolId !== null && (int)($student['school_id'] ?? 0) !== (int)$schoolId) {
        recordCheckinAttempt($teacherId, $ip, false);
        respond(['success' => false, 'status' => 'error', 'message' => 'Dieser Schüler gehört nicht zu deiner Schule.']);
    }

    $studentName = trim($student['firstname'] . ' ' . $student['lastname']);
    $studentClass = $student['class'] ?? '';
    $userId = (int)$student['id'];

    // Slot + Raum laden
    $stmt = $db->prepare("SELECT * FROM timeslots WHERE id = ? AND edition_id = ?");
    $stmt->execute([$timeslotId, $activeEditionId]);
    $slot = $stmt->fetch();
    if (!$slot) {
        respond(['success' => false, 'status' => 'error', 'message' => 'Zeitslot nicht gefunden.']);
    }
    $slotNumber = (int)$slot['slot_number'];

    // Aussteller dieses Raums ermitteln
    $stmt = $db->prepare("SELECT id, name, room_id FROM exhibitors WHERE room_id = ? AND edition_id = ? AND active = 1 LIMIT 1");
    $stmt->execute([$roomId, $activeEditionId]);
    $exhibitor = $stmt->fetch();
    if (!$exhibitor) {
        respond(['success' => false, 'status' => 'error', 'message' => 'Diesem Raum ist kein aktiver Aussteller zugeordnet.']);
    }
    $exhibitorId = (int)$exhibitor['id'];

    // Zeitfenster (Lehrer-Einstellungen)
    $eventDate = getSetting('event_date');
    if (getSchoolSetting($schoolId, 'qr_validity_teacher_enabled') === '1'
        && $eventDate && !empty($slot['start_time']) && !empty($slot['end_time'])) {
        $before = (int)getSchoolSetting($schoolId, 'qr_validity_teacher_before');
        $after  = (int)getSchoolSetting($schoolId, 'qr_validity_teacher_after');
        $now = time();
        $ws = strtotime("$eventDate " . $slot['start_time']);
        $we = strtotime("$eventDate " . $slot['end_time']);
        if ($ws !== false && $we !== false) {
            if ($now < $ws - $before * 60) {
                respond(['success' => false, 'status' => 'error', 'message' => 'Check-in für diesen Slot noch nicht möglich.']);
            }
            if ($now > $we + $after * 60) {
                respond(['success' => false, 'status' => 'error', 'message' => 'Das Zeitfenster für diesen Slot ist abgelaufen.']);
            }
        }
    }

    $isFreeSlot = in_array($slotNumber, [2, 4], true);

    // Bereits anwesend bei diesem Aussteller/Slot?
    $stmt = $db->prepare("SELECT checked_in_at, checkin_method FROM attendance
                          WHERE user_id = ? AND exhibitor_id = ? AND timeslot_id = ? AND edition_id = ?");
    $stmt->execute([$userId, $exhibitorId, $timeslotId, $activeEditionId]);
    if ($already = $stmt->fetch()) {
        recordCheckinAttempt($teacherId, $ip, true);
        $t = $already['checked_in_at'] ? date('H:i', strtotime($already['checked_in_at'])) : '';
        respond([
            'success' => true, 'status' => 'already',
            'student' => ['name' => $studentName, 'class' => $studentClass],
            'exhibitor' => $exhibitor['name'],
            'message' => "Bereits anwesend (seit $t)."
        ]);
    }

    // Registrierung für diesen Aussteller/Slot?
    $stmt = $db->prepare("SELECT id FROM registrations WHERE user_id = ? AND exhibitor_id = ? AND timeslot_id = ? AND edition_id = ?");
    $stmt->execute([$userId, $exhibitorId, $timeslotId, $activeEditionId]);
    $registered = (bool)$stmt->fetch();

    $wrongRoom = 0;

    if (!$registered) {
        if ($isFreeSlot) {
            // Freie Wahl: automatisch einschreiben (Kapazität prüfen)
            $cap = getRoomSlotCapacity($roomId, $timeslotId);
            $stmt = $db->prepare("SELECT COUNT(*) FROM registrations WHERE exhibitor_id = ? AND timeslot_id = ? AND edition_id = ?");
            $stmt->execute([$exhibitorId, $timeslotId, $activeEditionId]);
            if ($cap > 0 && (int)$stmt->fetchColumn() >= $cap) {
                respond(['success' => false, 'status' => 'error', 'message' => 'Kapazität dieses Aussteller-Slots ist erreicht.']);
            }
            // INSERT IGNORE: tolerant gegenüber unique_user_exhibitor (Schüler bereits
            // bei diesem Aussteller in anderem Slot) — Anwesenheit wird trotzdem erfasst.
            $ins = $db->prepare("INSERT IGNORE INTO registrations (user_id, exhibitor_id, timeslot_id, registration_type, edition_id) VALUES (?, ?, ?, 'qr_checkin', ?)");
            $ins->execute([$userId, $exhibitorId, $timeslotId, $activeEditionId]);
        } else {
            // Fester Slot, nicht registriert → falscher Raum
            // Soll-Aussteller/Raum dieses Slots ermitteln
            $stmt = $db->prepare("SELECT e.name AS ex_name, r.room_number, r.room_name
                                  FROM registrations reg
                                  JOIN exhibitors e ON reg.exhibitor_id = e.id
                                  LEFT JOIN rooms r ON e.room_id = r.id
                                  WHERE reg.user_id = ? AND reg.timeslot_id = ? AND reg.edition_id = ? LIMIT 1");
            $stmt->execute([$userId, $timeslotId, $activeEditionId]);
            $soll = $stmt->fetch();

            if (!$force) {
                recordCheckinAttempt($teacherId, $ip, true);
                respond([
                    'success' => false, 'status' => 'requires_confirmation',
                    'student' => ['name' => $studentName, 'class' => $studentClass],
                    'scanned_exhibitor' => $exhibitor['name'],
                    'soll_exhibitor' => $soll['ex_name'] ?? null,
                    'soll_room' => $soll ? trim(($soll['room_number'] ?? '') . ' ' . ($soll['room_name'] ?? '')) : null,
                    'message' => $soll
                        ? "Eigentlich Raum " . trim(($soll['room_number'] ?? '') . ' ' . ($soll['room_name'] ?? '')) . " ({$soll['ex_name']})."
                        : "Für diesen Slot nicht registriert."
                ]);
            }
            $wrongRoom = 1;
        }
    }

    // Anwesenheit eintragen
    $method = $isManual ? 'manual' : 'teacher_scan';
    $ins = $db->prepare("INSERT INTO attendance
        (user_id, exhibitor_id, timeslot_id, checkin_method, checked_in_by, actual_room_id, wrong_room, edition_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $ins->execute([$userId, $exhibitorId, $timeslotId, $method, $teacherId, $roomId, $wrongRoom, $activeEditionId]);

    recordCheckinAttempt($teacherId, $ip, true);

    // Override protokollieren, wenn Lehrer für diesen Raum/Slot nicht eingeteilt ist
    $chk = $db->prepare("SELECT COUNT(*) FROM teacher_room_assignments WHERE teacher_id = ? AND room_id = ? AND timeslot_id = ? AND edition_id = ?");
    $chk->execute([$teacherId, $roomId, $timeslotId, $activeEditionId]);
    $isOverride = (int)$chk->fetchColumn() === 0;

    logAuditAction(
        $isManual ? 'checkin_manuell' : 'checkin_lehrer_scan',
        "Schüler #$userId bei {$exhibitor['name']} (Raum #$roomId, Slot #$timeslotId)"
        . ($wrongRoom ? ' [FALSCHER RAUM]' : '')
        . ($isOverride ? ' [OVERRIDE]' : ''),
        $wrongRoom || $isOverride ? 'warning' : 'info'
    );

    respond([
        'success' => true, 'status' => 'checked_in',
        'student' => ['name' => $studentName, 'class' => $studentClass],
        'exhibitor' => $exhibitor['name'],
        'wrong_room' => (bool)$wrongRoom,
        'override' => $isOverride,
        'message' => $wrongRoom
            ? "$studentName eingecheckt (falscher Raum vermerkt)."
            : "$studentName eingecheckt."
    ]);

} catch (Exception $e) {
    logErrorToAudit($e, 'API-TeacherCheckin');
    http_response_code(500);
    respond(['success' => false, 'status' => 'error', 'message' => 'Ein interner Fehler ist aufgetreten.']);
}
