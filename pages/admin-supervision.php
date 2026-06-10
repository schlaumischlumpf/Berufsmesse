<?php
// Aufsichtsplan-Verwaltung (QR-Check-in 2.0)
// Weist Lehrkräfte je Raum und Zeitslot zu (manuell durch Admin gepflegt).

// Berechtigungsprüfung
if (!isAdminOrSchoolAdmin() && !hasPermission('aufsichtsplan_sehen')) {
    die('Keine Berechtigung zum Anzeigen dieser Seite');
}
$canEdit = isAdminOrSchoolAdmin() || hasPermission('aufsichtsplan_verwalten');

$schoolId = $school ? (int)$school['id'] : null;

// Räume und Zeitslots der aktiven Edition laden
$stmt = $db->prepare("SELECT * FROM rooms WHERE edition_id = ? ORDER BY room_number");
$stmt->execute([$activeEditionId]);
$rooms = $stmt->fetchAll();

$stmt = $db->prepare("SELECT * FROM timeslots WHERE edition_id = ? AND is_break = 0 ORDER BY start_time ASC, slot_number ASC");
$stmt->execute([$activeEditionId]);
$timeslots = $stmt->fetchAll();

// Lehrkräfte der Schule laden
if ($schoolId !== null) {
    $stmt = $db->prepare("SELECT id, firstname, lastname, class FROM users
                          WHERE role = 'teacher' AND school_id = ? ORDER BY lastname, firstname");
    $stmt->execute([$schoolId]);
} else {
    $stmt = $db->prepare("SELECT id, firstname, lastname, class FROM users
                          WHERE role = 'teacher' ORDER BY lastname, firstname");
    $stmt->execute();
}
$teachers = $stmt->fetchAll();
$teacherById = [];
foreach ($teachers as $t) {
    $teacherById[$t['id']] = trim($t['firstname'] . ' ' . $t['lastname']);
}

// Exhibitor pro Raum (für Kontextanzeige) — Name des Ausstellers, der diesem Raum zugeordnet ist
$roomExhibitor = [];
$stmt = $db->prepare("SELECT room_id, GROUP_CONCAT(name SEPARATOR ', ') AS names
                      FROM exhibitors WHERE edition_id = ? AND room_id IS NOT NULL GROUP BY room_id");
$stmt->execute([$activeEditionId]);
foreach ($stmt->fetchAll() as $row) {
    $roomExhibitor[$row['room_id']] = $row['names'];
}

// ── POST-Aktionen ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit) {
    requireCsrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'assign') {
            $teacherId  = intval($_POST['teacher_id'] ?? 0);
            $roomId     = intval($_POST['room_id'] ?? 0);
            $timeslotId = intval($_POST['timeslot_id'] ?? 0);

            if ($teacherId && $roomId && $timeslotId && isset($teacherById[$teacherId])) {
                // Ist der Lehrer in diesem Slot schon einem (anderen) Raum zugewiesen?
                $chk = $db->prepare("SELECT room_id FROM teacher_room_assignments
                                     WHERE teacher_id = ? AND timeslot_id = ? AND edition_id = ?");
                $chk->execute([$teacherId, $timeslotId, $activeEditionId]);
                $existingRoom = $chk->fetchColumn();

                if ($existingRoom && (int)$existingRoom !== $roomId) {
                    $message = ['type' => 'error', 'text' => htmlspecialchars($teacherById[$teacherId])
                        . ' ist in diesem Zeitslot bereits einem anderen Raum zugewiesen. Bitte dort zuerst entfernen.'];
                } elseif (!$existingRoom) {
                    $ins = $db->prepare("INSERT INTO teacher_room_assignments
                        (teacher_id, room_id, timeslot_id, edition_id, assigned_by)
                        VALUES (?, ?, ?, ?, ?)");
                    $ins->execute([$teacherId, $roomId, $timeslotId, $activeEditionId, $_SESSION['user_id']]);
                    logAuditAction('aufsicht_zugewiesen',
                        "Lehrer #$teacherId → Raum #$roomId, Slot #$timeslotId");
                    $message = ['type' => 'success', 'text' => 'Aufsicht zugewiesen.'];
                } else {
                    $message = ['type' => 'success', 'text' => 'Bereits zugewiesen.'];
                }
            }
        } elseif ($action === 'remove') {
            $assignmentId = intval($_POST['assignment_id'] ?? 0);
            // Nur Zuweisungen der aktiven Edition löschen
            $del = $db->prepare("DELETE FROM teacher_room_assignments WHERE id = ? AND edition_id = ?");
            $del->execute([$assignmentId, $activeEditionId]);
            logAuditAction('aufsicht_entfernt', "Zuweisung #$assignmentId entfernt");
            $message = ['type' => 'success', 'text' => 'Aufsicht entfernt.'];
        } elseif ($action === 'assign_all_slots') {
            $teacherId = intval($_POST['teacher_id'] ?? 0);
            $roomId    = intval($_POST['room_id'] ?? 0);
            if ($teacherId && $roomId && isset($teacherById[$teacherId])) {
                $added = 0; $skipped = 0;
                $chk = $db->prepare("SELECT COUNT(*) FROM teacher_room_assignments
                                     WHERE teacher_id = ? AND timeslot_id = ? AND edition_id = ?");
                $ins = $db->prepare("INSERT INTO teacher_room_assignments
                    (teacher_id, room_id, timeslot_id, edition_id, assigned_by)
                    VALUES (?, ?, ?, ?, ?)");
                foreach ($timeslots as $ts) {
                    $chk->execute([$teacherId, $ts['id'], $activeEditionId]);
                    if ((int)$chk->fetchColumn() > 0) { $skipped++; continue; }
                    $ins->execute([$teacherId, $roomId, $ts['id'], $activeEditionId, $_SESSION['user_id']]);
                    $added++;
                }
                logAuditAction('aufsicht_ganztags',
                    "Lehrer #$teacherId → Raum #$roomId für alle Slots ($added neu, $skipped übersprungen)");
                $message = ['type' => 'success', 'text' => "Ganztags-Aufsicht: $added Slots zugewiesen"
                    . ($skipped ? ", $skipped übersprungen (Konflikt)" : '') . '.'];
            }
        }
    } catch (Exception $e) {
        logErrorToAudit($e, 'Aufsichtsplan');
        $message = ['type' => 'error', 'text' => 'Fehler: ' . $e->getMessage()];
    }
}

// ── Aktuelle Zuweisungen laden ───────────────────────────────────────────────
$assignments = []; // [room_id][timeslot_id] = [ {id, teacher_id} ... ]
$slotTeacherCount = []; // [timeslot_id][teacher_id] = count (für Konfliktanzeige – durch UNIQUE eigentlich max 1)
$stmt = $db->prepare("SELECT id, teacher_id, room_id, timeslot_id FROM teacher_room_assignments WHERE edition_id = ?");
$stmt->execute([$activeEditionId]);
foreach ($stmt->fetchAll() as $a) {
    $assignments[$a['room_id']][$a['timeslot_id']][] = $a;
    $slotTeacherCount[$a['timeslot_id']][$a['teacher_id']] =
        ($slotTeacherCount[$a['timeslot_id']][$a['teacher_id']] ?? 0) + 1;
}

// Räume ohne Aufsicht je Slot ermitteln
$unsupervised = []; // [timeslot_id] = [room_name, ...]
foreach ($timeslots as $ts) {
    foreach ($rooms as $r) {
        if (empty($assignments[$r['id']][$ts['id']])) {
            $unsupervised[$ts['id']][] = $r['room_number'];
        }
    }
}

$csrf = generateCsrfToken();
?>
<div class="space-y-6">
    <?php if (isset($message)): ?>
        <div class="<?php echo $message['type'] === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : 'bg-red-50 border-red-200 text-red-700'; ?> border p-4 rounded-lg flex items-center">
            <i class="fas <?php echo $message['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-3"></i>
            <p><?php echo $message['text']; ?></p>
        </div>
    <?php endif; ?>

    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h2 class="text-xl font-semibold text-gray-800">Aufsichtsplan</h2>
            <p class="text-sm text-gray-500 mt-1">Lehrkräfte je Raum und Zeitslot — Grundlage für den Lehrer-Check-in.</p>
        </div>
        <a href="<?php echo schoolUrl('api/generate-supervision-pdf.php'); ?>" target="_blank"
           class="btn btn-secondary">
            <i class="fas fa-file-pdf"></i><span>Als PDF</span>
        </a>
    </div>

    <?php if (empty($rooms) || empty($timeslots)): ?>
        <div class="bg-amber-50 border border-amber-200 p-4 rounded-lg text-amber-800 text-sm">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            Es sind noch keine Räume oder Zeitslots für die aktive Edition angelegt. Lege diese zuerst an.
        </div>
    <?php else: ?>

    <?php if (empty($teachers)): ?>
        <div class="bg-amber-50 border border-amber-200 p-4 rounded-lg text-amber-800 text-sm">
            <i class="fas fa-info-circle mr-2"></i>
            Es sind keine Lehrkräfte für diese Schule angelegt. Lege Benutzer mit der Rolle „Lehrer“ an.
        </div>
    <?php endif; ?>

    <div class="card p-0 overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="text-left px-4 py-3 font-semibold text-gray-700 sticky left-0 bg-gray-50 z-10">Raum</th>
                    <?php foreach ($timeslots as $ts): ?>
                        <th class="text-left px-4 py-3 font-semibold text-gray-700 whitespace-nowrap">
                            <?php echo htmlspecialchars($ts['slot_name']); ?>
                            <?php if (!empty($ts['start_time'])): ?>
                                <span class="block text-xs font-normal text-gray-400">
                                    <?php echo substr($ts['start_time'], 0, 5); ?>
                                </span>
                            <?php endif; ?>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rooms as $r): ?>
                    <tr class="border-b border-gray-100 align-top">
                        <td class="px-4 py-3 sticky left-0 bg-white z-10">
                            <div class="font-semibold text-gray-800 whitespace-nowrap">
                                <?php echo htmlspecialchars($r['room_number']); ?>
                                <?php if (!empty($r['room_name'])): ?>
                                    <span class="text-gray-400 font-normal">· <?php echo htmlspecialchars($r['room_name']); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($roomExhibitor[$r['id']])): ?>
                                <div class="text-xs text-gray-400 mt-0.5 max-w-[180px] truncate" title="<?php echo htmlspecialchars($roomExhibitor[$r['id']]); ?>">
                                    <?php echo htmlspecialchars($roomExhibitor[$r['id']]); ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($canEdit && !empty($teachers)): ?>
                            <form method="post" class="mt-2 flex items-center gap-1">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                <input type="hidden" name="action" value="assign_all_slots">
                                <input type="hidden" name="room_id" value="<?php echo $r['id']; ?>">
                                <select name="teacher_id" class="text-xs border border-gray-200 rounded-md py-1 px-1 max-w-[120px]" required>
                                    <option value="">Ganztags…</option>
                                    <?php foreach ($teachers as $t): ?>
                                        <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($teacherById[$t['id']]); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="text-xs px-2 py-1 rounded-md bg-gray-100 hover:bg-gray-200 text-gray-600" title="Lehrer für alle Slots dieses Raums setzen">
                                    <i class="fas fa-arrows-left-right-to-line"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                        <?php foreach ($timeslots as $ts): ?>
                            <td class="px-4 py-3 min-w-[180px]">
                                <?php if (!empty($assignments[$r['id']][$ts['id']])): ?>
                                    <div class="flex flex-wrap gap-1 mb-2">
                                        <?php foreach ($assignments[$r['id']][$ts['id']] as $a): ?>
                                            <?php $tName = $teacherById[$a['teacher_id']] ?? ('Lehrer #' . $a['teacher_id']); ?>
                                            <span class="badge badge-mint inline-flex items-center gap-1">
                                                <?php echo htmlspecialchars($tName); ?>
                                                <?php if ($canEdit): ?>
                                                <form method="post" class="inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                                    <input type="hidden" name="action" value="remove">
                                                    <input type="hidden" name="assignment_id" value="<?php echo $a['id']; ?>">
                                                    <button type="submit" class="text-emerald-700 hover:text-red-600" title="Entfernen">
                                                        <i class="fas fa-times text-[10px]"></i>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-xs text-gray-300 mb-2"><i class="fas fa-user-slash mr-1"></i>keine Aufsicht</div>
                                <?php endif; ?>

                                <?php if ($canEdit && !empty($teachers)): ?>
                                <form method="post" class="flex items-center gap-1">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                    <input type="hidden" name="action" value="assign">
                                    <input type="hidden" name="room_id" value="<?php echo $r['id']; ?>">
                                    <input type="hidden" name="timeslot_id" value="<?php echo $ts['id']; ?>">
                                    <select name="teacher_id" class="text-xs border border-gray-200 rounded-md py-1 px-1 w-full" required>
                                        <option value="">+ Lehrer…</option>
                                        <?php foreach ($teachers as $t): ?>
                                            <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($teacherById[$t['id']]); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="text-xs px-2 py-1 rounded-md bg-emerald-100 hover:bg-emerald-200 text-emerald-700">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Räume ohne Aufsicht -->
    <div class="card p-5">
        <h3 class="text-lg font-semibold text-gray-800 mb-3">
            <i class="fas fa-triangle-exclamation text-amber-500 mr-2"></i>Räume ohne Aufsicht
        </h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <?php foreach ($timeslots as $ts): ?>
                <div class="border border-gray-100 rounded-lg p-3">
                    <div class="font-medium text-gray-700 text-sm mb-1"><?php echo htmlspecialchars($ts['slot_name']); ?></div>
                    <?php if (empty($unsupervised[$ts['id']])): ?>
                        <div class="text-xs text-emerald-600"><i class="fas fa-check mr-1"></i>Alle Räume betreut</div>
                    <?php else: ?>
                        <div class="text-xs text-gray-500">
                            <?php echo htmlspecialchars(implode(', ', $unsupervised[$ts['id']])); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
