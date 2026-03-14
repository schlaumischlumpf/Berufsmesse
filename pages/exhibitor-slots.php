<?php
/**
 * Aussteller-Slots: Anmeldungen pro Slot und Schule anzeigen
 */
if (!isExhibitor() && !isAdmin()) die('Keine Berechtigung');

$db = getDB();
$userId = $_SESSION['user_id'];
$ids = isAdmin() ? [] : getExhibitorIdsForUser($userId);
$exhibitorId = (int)($_GET['exhibitor_id'] ?? 0);

// Auto-default: if no exhibitor_id given, use the first one linked to this user
if (!isAdmin() && !in_array($exhibitorId, $ids)) {
    if (!empty($ids)) {
        $defaultId = $ids[0];
        $page = $_GET['page'] ?? 'exhibitor-slots';
        header("Location: ?page={$page}&exhibitor_id={$defaultId}");
        exit;
    } else {
        echo '<div class="p-4 bg-red-50 text-red-700 rounded-xl">Kein Aussteller-Konto verknüpft. Bitte wende dich an den Administrator.</div>';
        return;
    }
}

// Aussteller laden
$stmt = $db->prepare("
    SELECT e.*,
           r.room_number, r.room_name,
           me.name as edition_name, me.year as edition_year,
           s.name as school_name
    FROM exhibitors e
    LEFT JOIN rooms r ON e.room_id = r.id
    LEFT JOIN messe_editions me ON e.edition_id = me.id
    LEFT JOIN schools s ON me.school_id = s.id
    WHERE e.id = ?
");
$stmt->execute([$exhibitorId]);
$exhibitor = $stmt->fetch();

if (!$exhibitor) {
    echo '<div class="p-4 bg-red-50 text-red-700 rounded-xl">Aussteller nicht gefunden.</div>';
    return;
}

// Timeslots laden
$stmt = $db->prepare("SELECT t.* FROM timeslots t WHERE t.edition_id = ? ORDER BY t.slot_number");
$stmt->execute([$exhibitor['edition_id']]);
$timeslots = $stmt->fetchAll();

// Registrierungen pro Slot laden (nur Admins erhalten Namen/Klasse)
if (isAdminOrSchoolAdmin()) {
    $regSql = "SELECT r.*, t.slot_name, t.start_time, t.end_time, t.slot_number,
                      u.firstname, u.lastname, u.class
               FROM registrations r
               JOIN timeslots t ON r.timeslot_id = t.id
               JOIN users u ON r.user_id = u.id
               WHERE r.exhibitor_id = ? AND r.edition_id = ?
               ORDER BY t.slot_number, u.class, u.lastname";
} else {
    $regSql = "SELECT r.user_id, r.timeslot_id,
                      t.slot_name, t.start_time, t.end_time, t.slot_number
               FROM registrations r
               JOIN timeslots t ON r.timeslot_id = t.id
               WHERE r.exhibitor_id = ? AND r.edition_id = ?
               ORDER BY t.slot_number";
}
$stmt = $db->prepare($regSql);
$stmt->execute([$exhibitorId, $exhibitor['edition_id']]);
$registrations = $stmt->fetchAll();

// Nach Slot gruppieren
$bySlot = [];
foreach ($registrations as $reg) {
    $bySlot[$reg['slot_number']][] = $reg;
}

// Anwesenheit laden
$stmt = $db->prepare("SELECT user_id, timeslot_id FROM attendance WHERE exhibitor_id = ? AND edition_id = ?");
$stmt->execute([$exhibitorId, $exhibitor['edition_id']]);
$attended = [];
foreach ($stmt->fetchAll() as $att) {
    $attended[$att['user_id'] . '-' . $att['timeslot_id']] = true;
}
?>

<div class="mb-6">
    <div class="flex items-center gap-2 mb-1">
        <a href="?page=exhibitor-dashboard" class="text-gray-400 hover:text-gray-600 transition-colors">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h2 class="text-xl font-bold text-gray-800">
            <i class="fas fa-calendar-alt mr-2" style="color: var(--color-pastel-lavender);"></i>
            Slot-Anmeldungen — <?php echo htmlspecialchars($exhibitor['name']); ?>
        </h2>
    </div>
    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($exhibitor['school_name'] ?? 'Unbekannte Schule'); ?> · <?php echo htmlspecialchars($exhibitor['edition_name'] ?? ''); ?> · <?php echo count($registrations); ?> Anmeldungen</p>
</div>

<?php foreach ($timeslots as $ts): ?>
<?php $slotRegs = $bySlot[$ts['slot_number']] ?? []; ?>
<div class="bg-white rounded-xl border border-gray-200 p-5 mb-4">
    <div class="flex items-center justify-between mb-3">
        <h3 class="font-semibold text-gray-800 flex items-center gap-2">
            <span class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center text-blue-600 text-sm font-bold"><?php echo $ts['slot_number']; ?></span>
            <?php echo htmlspecialchars($ts['slot_name']); ?>
            <span class="text-xs text-gray-400 font-normal"><?php echo $ts['start_time']; ?> – <?php echo $ts['end_time']; ?></span>
        </h3>
        <span class="text-xs font-medium px-2 py-1 rounded-full <?php echo count($slotRegs) > 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500'; ?>">
            <?php echo count($slotRegs); ?> SuS
        </span>
    </div>
    
    <?php if (empty($slotRegs)): ?>
        <p class="text-sm text-gray-400 italic">Keine Anmeldungen für diesen Slot.</p>
    <?php elseif (isAdminOrSchoolAdmin()): ?>
        <!-- Full table: only admins see names -->
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="text-left px-3 py-2 font-medium text-gray-600">Name</th>
                        <th class="text-left px-3 py-2 font-medium text-gray-600">Klasse</th>
                        <th class="text-center px-3 py-2 font-medium text-gray-600">Anwesend</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($slotRegs as $reg): ?>
                    <?php $isPresent = isset($attended[$reg['user_id'] . '-' . $ts['id']]); ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2"><?php echo htmlspecialchars($reg['lastname'] . ', ' . $reg['firstname']); ?></td>
                        <td class="px-3 py-2 text-gray-500"><?php echo htmlspecialchars($reg['class'] ?? '—'); ?></td>
                        <td class="px-3 py-2 text-center">
                            <?php if ($isPresent): ?>
                                <i class="fas fa-check-circle text-emerald-500"></i>
                            <?php else: ?>
                                <i class="fas fa-minus-circle text-gray-300"></i>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <!-- Exhibitor view: counts only, no names -->
        <?php
        $presentCount = count(array_filter($slotRegs, fn($r) => isset($attended[$r['user_id'] . '-' . $ts['id']])));
        $totalCount   = count($slotRegs);
        ?>
        <div class="flex items-center gap-6 py-2">
            <div class="text-center">
                <p class="text-2xl font-bold text-gray-800"><?php echo $totalCount; ?></p>
                <p class="text-xs text-gray-400 mt-0.5">Angemeldet</p>
            </div>
            <div class="text-center">
                <p class="text-2xl font-bold text-emerald-600"><?php echo $presentCount; ?></p>
                <p class="text-xs text-gray-400 mt-0.5">Anwesend</p>
            </div>
            <div class="text-center">
                <p class="text-2xl font-bold text-amber-500"><?php echo $totalCount - $presentCount; ?></p>
                <p class="text-xs text-gray-400 mt-0.5">Ausstehend</p>
            </div>
        </div>
        <p class="text-xs text-gray-400 mt-1 italic">
            <i class="fas fa-lock mr-1"></i>
            Schülernamen sind nur für Administratoren sichtbar.
        </p>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<?php if (empty($timeslots)): ?>
<div class="text-center py-8 text-gray-500">
    <i class="fas fa-calendar text-4xl mb-3 text-gray-300"></i>
    <p>Keine Timeslots für diese Edition vorhanden.</p>
</div>
<?php endif; ?>
