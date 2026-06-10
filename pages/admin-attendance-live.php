<?php
// Live-Anwesenheits-Dashboard (QR-Check-in 2.0)
if (!isAdminOrSchoolAdmin() && !hasPermission('qr_codes_sehen') && !hasPermission('attendance_bearbeiten')) {
    die('Keine Berechtigung zum Anzeigen dieser Seite');
}

$stmt = $db->prepare("SELECT id, slot_name, slot_number, start_time, end_time
                      FROM timeslots WHERE edition_id = ? AND is_break = 0
                      ORDER BY start_time ASC, slot_number ASC");
$stmt->execute([$activeEditionId]);
$timeslots = $stmt->fetchAll();

// Aktuellen Slot per Uhrzeit
$eventDate = getSetting('event_date');
$currentSlotId = null; $nowTs = time();
foreach ($timeslots as $ts) {
    if ($eventDate && !empty($ts['start_time']) && !empty($ts['end_time'])) {
        $s = strtotime("$eventDate " . $ts['start_time']);
        $e = strtotime("$eventDate " . $ts['end_time']);
        if ($s !== false && $e !== false && $nowTs >= $s - 1800 && $nowTs <= $e + 1800) { $currentSlotId = (int)$ts['id']; break; }
    }
}
if (!$currentSlotId && !empty($timeslots)) $currentSlotId = (int)$timeslots[0]['id'];

// POST: Fehlende an Klassenlehrer melden
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_missing'])) {
    requireCsrf();
    $tsId = (int)$_POST['timeslot_id'];
    $stmt = $db->prepare("
        SELECT u.class, u.firstname, u.lastname, e.name AS exhibitor
        FROM registrations rg
        JOIN users u ON rg.user_id = u.id
        JOIN exhibitors e ON rg.exhibitor_id = e.id
        WHERE rg.timeslot_id = ? AND rg.edition_id = ?
          AND NOT EXISTS (SELECT 1 FROM attendance a WHERE a.user_id = rg.user_id AND a.exhibitor_id = rg.exhibitor_id AND a.timeslot_id = rg.timeslot_id AND a.edition_id = rg.edition_id)
        ORDER BY u.class, u.lastname, u.firstname");
    $stmt->execute([$tsId, $activeEditionId]);
    $missingRows = $stmt->fetchAll();
    $slotName = '';
    foreach ($timeslots as $ts) if ((int)$ts['id'] === $tsId) $slotName = $ts['slot_name'];

    if (empty($missingRows)) {
        $message = ['type' => 'success', 'text' => 'Keine fehlenden Schüler in diesem Slot.'];
    } else {
        $byClass = [];
        foreach ($missingRows as $m) {
            $c = $m['class'] ?: 'Ohne Klasse';
            $byClass[$c][] = trim($m['firstname'] . ' ' . $m['lastname']) . ' (' . $m['exhibitor'] . ')';
        }
        $body = "Fehlende Schüler – $slotName:\n";
        foreach ($byClass as $c => $names) {
            $body .= "\nKlasse $c:\n- " . implode("\n- ", $names) . "\n";
        }
        notifyTeachers('Fehlende Schüler: ' . $slotName, $body);
        logAuditAction('fehlende_gemeldet', count($missingRows) . " fehlende Schüler in $slotName an Lehrkräfte gemeldet", 'warning');
        $message = ['type' => 'success', 'text' => count($missingRows) . ' fehlende Schüler an die Lehrkräfte gemeldet (In-App-Ankündigung).'];
    }
}

$csrf = generateCsrfToken();
$apiLive   = schoolUrl('api/attendance-live.php');
$apiRoster = schoolUrl('api/room-roster.php');
?>
<div class="space-y-6" id="liveApp"
     data-api-live="<?php echo htmlspecialchars($apiLive); ?>"
     data-api-roster="<?php echo htmlspecialchars($apiRoster); ?>">

    <?php if (isset($message)): ?>
        <div class="<?php echo $message['type'] === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : 'bg-red-50 border-red-200 text-red-700'; ?> border p-4 rounded-lg flex items-center">
            <i class="fas <?php echo $message['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-3"></i>
            <p><?php echo htmlspecialchars($message['text']); ?></p>
        </div>
    <?php endif; ?>

    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h2 class="text-xl font-semibold text-gray-800"><i class="fas fa-satellite-dish text-emerald-500 mr-2"></i>Live-Anwesenheit</h2>
            <p class="text-sm text-gray-500 mt-1">Echtzeit-Überblick je Raum. Aktualisiert automatisch.</p>
        </div>
        <div class="flex items-end gap-2">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Zeitslot</label>
                <select id="slotSelect" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
                    <?php foreach ($timeslots as $ts): ?>
                        <option value="<?php echo (int)$ts['id']; ?>" <?php echo (int)$ts['id'] === $currentSlotId ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ts['slot_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <form method="POST" onsubmit="this.querySelector('[name=timeslot_id]').value=document.getElementById('slotSelect').value;">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                <input type="hidden" name="timeslot_id" value="">
                <button type="submit" name="report_missing" class="px-4 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600 text-sm font-medium">
                    <i class="fas fa-bell mr-1"></i>Fehlende melden
                </button>
            </form>
            <a id="exportLink" href="#" target="_blank" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm font-medium">
                <i class="fas fa-file-csv mr-1"></i>Export
            </a>
        </div>
    </div>

    <!-- Gesamtstatistik -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="card p-4 text-center"><div class="text-2xl font-bold text-emerald-600" id="statPresent">–</div><div class="text-xs text-gray-500">anwesend</div></div>
        <div class="card p-4 text-center"><div class="text-2xl font-bold text-gray-700" id="statExpected">–</div><div class="text-xs text-gray-500">erwartet</div></div>
        <div class="card p-4 text-center"><div class="text-2xl font-bold text-red-500" id="statMissing">–</div><div class="text-xs text-gray-500">fehlend</div></div>
        <div class="card p-4 text-center"><div class="text-2xl font-bold text-amber-500" id="statWrong">–</div><div class="text-xs text-gray-500">falscher Raum</div></div>
    </div>

    <div id="roomTiles" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3"></div>
    <p id="liveUpdated" class="text-xs text-gray-400 text-center"></p>
</div>

<!-- Detail-Modal -->
<div id="roomDetailModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
    <div class="bg-white rounded-xl shadow-xl max-w-lg w-full max-h-[85vh] flex flex-col">
        <div class="flex items-center justify-between p-4 border-b border-gray-100">
            <h3 id="detailTitle" class="font-bold text-gray-800"></h3>
            <button onclick="document.getElementById('roomDetailModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
        </div>
        <div id="detailBody" class="p-4 overflow-y-auto"></div>
    </div>
</div>

<script>
(function() {
    const app = document.getElementById('liveApp');
    const API_LIVE = app.dataset.apiLive, API_ROSTER = app.dataset.apiRoster;
    const slotSelect = document.getElementById('slotSelect');
    const tiles = document.getElementById('roomTiles');
    function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}

    function colorFor(pct){ if(pct>=90) return 'border-emerald-300 bg-emerald-50'; if(pct>=60) return 'border-amber-300 bg-amber-50'; return 'border-red-300 bg-red-50'; }

    function updateExport(){
        const sep = API_LIVE.includes('?') ? '&' : '?';
        document.getElementById('exportLink').href =
            '<?php echo schoolUrl('api/export-attendance.php'); ?>' + (('<?php echo schoolUrl('api/export-attendance.php'); ?>').includes('?')?'&':'?') + 'timeslot_id=' + slotSelect.value;
    }

    function load(){
        const url = API_LIVE + (API_LIVE.includes('?')?'&':'?') + 'timeslot_id=' + slotSelect.value;
        fetch(url, {headers:{'Accept':'application/json'}}).then(r=>r.json()).then(res=>{
            if(!res.success) return;
            document.getElementById('statPresent').textContent = res.total_present;
            document.getElementById('statExpected').textContent = res.total_expected;
            document.getElementById('statMissing').textContent = res.total_missing;
            document.getElementById('statWrong').textContent = res.total_wrong;
            tiles.innerHTML = (res.rooms||[]).map(r=>`
                <button class="card p-4 text-left border-2 ${colorFor(r.pct)}" onclick="showDetail(${r.room_id}, '${esc(r.room)}', '${esc(r.exhibitor)}')">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="font-semibold text-gray-800">${esc(r.room||'—')}</div>
                            <div class="text-xs text-gray-500">${esc(r.exhibitor)}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-lg font-bold text-gray-800">${r.present}/${r.expected}</div>
                            ${r.wrong>0?`<span class="badge badge-peach">${r.wrong} falsch</span>`:''}
                        </div>
                    </div>
                </button>`).join('') || '<p class="text-sm text-gray-400">Keine Räume mit Ausstellern.</p>';
            document.getElementById('liveUpdated').textContent = 'Aktualisiert: ' + new Date().toLocaleTimeString();
        });
    }

    // Detail: personenscharfe Anwesend-/Fehlt-Liste über room-roster (room_id + Slot)
    window.showDetail = function(roomId, room, exhibitor){
        document.getElementById('detailTitle').textContent = room + ' · ' + exhibitor;
        const body = document.getElementById('detailBody');
        body.innerHTML = '<p class="text-sm text-gray-400">Lädt…</p>';
        document.getElementById('roomDetailModal').classList.remove('hidden');
        const url = API_ROSTER + (API_ROSTER.includes('?')?'&':'?') + 'room_id=' + roomId + '&timeslot_id=' + slotSelect.value;
        fetch(url, {headers:{'Accept':'application/json'}}).then(r=>r.json()).then(res=>{
            if(!res.success){ body.innerHTML = '<p class="text-sm text-red-500">Konnte Details nicht laden.</p>'; return; }
            const present = (res.present||[]).map(p=>`<div class="flex justify-between text-sm py-1">
                <span>${esc(p.name)} <span class="text-gray-400">${esc(p.class||'')}</span>
                ${p.wrong_room?'<span class="badge badge-peach ml-1">falsch</span>':''}
                ${p.method==='manual'?'<span class="text-gray-300 text-xs">manuell</span>':(p.method==='teacher_scan'?'<span class="text-gray-300 text-xs">Lehrer</span>':'')}</span>
                <span class="text-emerald-600 text-xs">${esc(p.time||'')}</span></div>`).join('') || '<p class="text-xs text-gray-400">Niemand anwesend.</p>';
            const missing = (res.missing||[]).map(m=>`<div class="text-sm py-1">${esc(m.name)} <span class="text-gray-400">${esc(m.class||'')}</span></div>`).join('') || '<p class="text-xs text-emerald-600">Alle da.</p>';
            body.innerHTML = `<h4 class="text-sm font-semibold text-emerald-700 mb-1">Anwesend (${(res.present||[]).length})</h4>${present}
                <h4 class="text-sm font-semibold text-red-600 mt-4 mb-1">Fehlt noch (${(res.missing||[]).length})</h4>${missing}`;
        });
    };

    slotSelect.addEventListener('change', ()=>{ updateExport(); load(); });
    updateExport(); load(); setInterval(load, 20000);
})();
</script>
