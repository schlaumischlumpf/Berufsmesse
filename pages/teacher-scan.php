<?php
// Lehrer-Scanner (QR-Check-in 2.0)
if (!isTeacher() && !isAdminOrSchoolAdmin()) {
    die('Keine Berechtigung zum Anzeigen dieser Seite');
}

$schoolId = getCurrentSchoolId();
$teacherScanEnabled = getSchoolSetting($schoolId, 'checkin_teacher_scan_enabled') === '1';

// Zeitslots der Edition
$stmt = $db->prepare("SELECT id, slot_name, slot_number, start_time, end_time
                      FROM timeslots WHERE edition_id = ? AND is_break = 0
                      ORDER BY start_time ASC, slot_number ASC");
$stmt->execute([$activeEditionId]);
$timeslots = $stmt->fetchAll();

// Räume der Edition (nur mit aktivem Aussteller sinnvoll, aber alle anbieten)
$stmt = $db->prepare("SELECT r.id, r.room_number, r.room_name,
                             (SELECT name FROM exhibitors e WHERE e.room_id = r.id AND e.edition_id = ? AND e.active = 1 LIMIT 1) AS exhibitor
                      FROM rooms r WHERE r.edition_id = ? ORDER BY r.room_number");
$stmt->execute([$activeEditionId, $activeEditionId]);
$rooms = $stmt->fetchAll();

// Aufsichtszuweisungen dieses Lehrers (Slot → Raum)
$assignmentMap = [];
$stmt = $db->prepare("SELECT timeslot_id, room_id FROM teacher_room_assignments WHERE teacher_id = ? AND edition_id = ?");
$stmt->execute([$_SESSION['user_id'], $activeEditionId]);
foreach ($stmt->fetchAll() as $a) {
    $assignmentMap[(int)$a['timeslot_id']] = (int)$a['room_id'];
}

// Aktuellen Slot per Uhrzeit bestimmen
$eventDate = getSetting('event_date');
$currentSlotId = null;
$nowTs = time();
foreach ($timeslots as $ts) {
    if ($eventDate && !empty($ts['start_time']) && !empty($ts['end_time'])) {
        $s = strtotime("$eventDate " . $ts['start_time']);
        $e = strtotime("$eventDate " . $ts['end_time']);
        if ($s !== false && $e !== false && $nowTs >= $s - 1800 && $nowTs <= $e + 1800) {
            $currentSlotId = (int)$ts['id'];
            break;
        }
    }
}
if (!$currentSlotId && !empty($timeslots)) {
    $currentSlotId = (int)$timeslots[0]['id'];
}

$csrf = generateCsrfToken();
$apiCheckin = schoolUrl('api/teacher-checkin.php');
$apiRoster  = schoolUrl('api/room-roster.php');
$apiSearch  = schoolUrl('api/student-search.php');
?>
<div class="space-y-6 max-w-3xl mx-auto" id="scanApp"
     data-csrf="<?php echo htmlspecialchars($csrf); ?>"
     data-api-checkin="<?php echo htmlspecialchars($apiCheckin); ?>"
     data-api-roster="<?php echo htmlspecialchars($apiRoster); ?>"
     data-api-search="<?php echo htmlspecialchars($apiSearch); ?>"
     data-assignments='<?php echo json_encode($assignmentMap); ?>'
     data-current-slot="<?php echo (int)$currentSlotId; ?>">

    <div>
        <h2 class="text-xl font-semibold text-gray-800"><i class="fas fa-qrcode text-emerald-500 mr-2"></i>Lehrer-Check-in</h2>
        <p class="text-sm text-gray-500 mt-1">Scanne die persönlichen QR-Codes der Schüler für deinen Raum.</p>
    </div>

    <?php if (!$teacherScanEnabled): ?>
        <div class="bg-amber-50 border border-amber-200 text-amber-800 p-4 rounded-lg text-sm">
            <i class="fas fa-triangle-exclamation mr-2"></i>
            Der Lehrer-Check-in ist für diese Schule derzeit nicht aktiviert. Bitte in den Einstellungen aktivieren.
        </div>
    <?php elseif (empty($timeslots) || empty($rooms)): ?>
        <div class="bg-amber-50 border border-amber-200 text-amber-800 p-4 rounded-lg text-sm">
            <i class="fas fa-info-circle mr-2"></i>Keine Räume/Zeitslots für die aktive Edition vorhanden.
        </div>
    <?php else: ?>

    <!-- Slot & Raum -->
    <div class="card p-5 space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1">Zeitslot</label>
                <select id="slotSelect" class="w-full border border-gray-200 rounded-lg px-3 py-2">
                    <?php foreach ($timeslots as $ts): ?>
                        <option value="<?php echo (int)$ts['id']; ?>" <?php echo (int)$ts['id'] === $currentSlotId ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ts['slot_name']); ?>
                            <?php echo !empty($ts['start_time']) ? '(' . substr($ts['start_time'], 0, 5) . ')' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1">Raum <span id="roomOverrideBadge" class="hidden text-xs text-amber-600 font-normal">· abweichend</span></label>
                <select id="roomSelect" class="w-full border border-gray-200 rounded-lg px-3 py-2">
                    <option value="">– Raum wählen –</option>
                    <?php foreach ($rooms as $r): ?>
                        <option value="<?php echo (int)$r['id']; ?>">
                            <?php echo htmlspecialchars($r['room_number'] . (!empty($r['exhibitor']) ? ' · ' . $r['exhibitor'] : '')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <p id="assignmentHint" class="text-xs text-gray-400"></p>
    </div>

    <!-- Scanner -->
    <div class="card p-5">
        <div id="qr-camera-container" class="flex justify-center items-center min-h-[220px] bg-gray-50 rounded-xl border-2 border-dashed border-gray-200">
            <div class="text-center text-gray-400 p-6"><i class="fas fa-camera text-4xl mb-3"></i><p class="text-sm">Wähle Raum & Slot und starte die Kamera.</p></div>
        </div>
        <div class="flex justify-center mt-3">
            <button id="toggleCameraBtn" type="button" class="btn btn-primary"><i class="fas fa-camera"></i><span>Kamera starten</span></button>
        </div>
        <p id="qr-camera-status" class="text-xs text-gray-400 text-center mt-2"></p>
    </div>

    <!-- Ergebnis-Karte -->
    <div id="resultCard" class="hidden rounded-xl p-5 border text-center"></div>

    <!-- Manueller Check-in -->
    <div class="card p-5">
        <h3 class="text-sm font-semibold text-gray-800 mb-2"><i class="fas fa-keyboard mr-2 text-gray-400"></i>Manueller Check-in</h3>
        <input id="manualSearch" type="text" placeholder="Name oder Klasse suchen…" autocomplete="off"
               class="w-full border border-gray-200 rounded-lg px-3 py-2">
        <div id="manualResults" class="mt-2 space-y-1"></div>
    </div>

    <!-- Live-Roster -->
    <div class="card p-0 overflow-hidden">
        <div class="flex border-b border-gray-100">
            <button class="roster-tab flex-1 px-4 py-3 text-sm font-medium border-b-2 border-emerald-500 text-emerald-600" data-tab="present">
                Eingecheckt (<span id="presentCount">0</span>/<span id="expectedCount">0</span>)
            </button>
            <button class="roster-tab flex-1 px-4 py-3 text-sm font-medium border-b-2 border-transparent text-gray-500" data-tab="missing">
                Fehlt noch (<span id="missingCount">0</span>)
            </button>
        </div>
        <div id="rosterPresent" class="p-4 space-y-1 max-h-72 overflow-y-auto"></div>
        <div id="rosterMissing" class="p-4 space-y-1 max-h-72 overflow-y-auto hidden"></div>
    </div>
    <?php endif; ?>
</div>

<?php if ($teacherScanEnabled && !empty($timeslots) && !empty($rooms)): ?>
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
<script src="<?php echo BASE_URL; ?>assets/js/qr-camera.js"></script>
<script>
(function() {
    const app = document.getElementById('scanApp');
    const CSRF = app.dataset.csrf;
    const API = { checkin: app.dataset.apiCheckin, roster: app.dataset.apiRoster, search: app.dataset.apiSearch };
    const assignments = JSON.parse(app.dataset.assignments || '{}');

    const slotSelect = document.getElementById('slotSelect');
    const roomSelect = document.getElementById('roomSelect');
    const assignmentHint = document.getElementById('assignmentHint');
    const overrideBadge = document.getElementById('roomOverrideBadge');
    const resultCard = document.getElementById('resultCard');
    const statusEl = document.getElementById('qr-camera-status');

    let cameraActive = false;
    let processing = false;
    let lastToken = '';
    let lastTokenTime = 0;
    let pendingConfirm = null; // {token|userId, manual}

    function applyAssignmentDefault() {
        const slot = slotSelect.value;
        const assignedRoom = assignments[slot];
        if (assignedRoom && !roomSelect.dataset.touched) {
            roomSelect.value = String(assignedRoom);
        }
        updateOverrideState();
    }
    function updateOverrideState() {
        const slot = slotSelect.value;
        const assignedRoom = assignments[slot];
        if (assignedRoom) {
            assignmentHint.textContent = 'Dir zugewiesener Raum für diesen Slot ist vorausgewählt.';
            overrideBadge.classList.toggle('hidden', String(assignedRoom) === roomSelect.value);
        } else {
            assignmentHint.textContent = 'Für diesen Slot bist du keinem Raum zugewiesen — bitte Raum wählen (Override).';
            overrideBadge.classList.add('hidden');
        }
    }
    slotSelect.addEventListener('change', () => { roomSelect.dataset.touched = ''; applyAssignmentDefault(); loadRoster(); });
    roomSelect.addEventListener('change', () => { roomSelect.dataset.touched = '1'; updateOverrideState(); loadRoster(); });

    function postCheckin(payload) {
        if (!roomSelect.value) { showResult({status:'error', message:'Bitte zuerst einen Raum wählen.'}); return; }
        payload.room_id = parseInt(roomSelect.value, 10);
        payload.timeslot_id = parseInt(slotSelect.value, 10);
        processing = true;
        fetch(API.checkin, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify(payload)
        }).then(r => r.json()).then(res => {
            showResult(res);
            if (res.success && res.status === 'checked_in') { loadRoster(); }
            if (res.status === 'requires_confirmation') {
                pendingConfirm = payload;
            }
        }).catch(() => showResult({status:'error', message:'Netzwerkfehler.'}))
          .finally(() => { processing = false; });
    }

    function showResult(res) {
        resultCard.classList.remove('hidden');
        const s = res.status || (res.success ? 'checked_in' : 'error');
        let cls = 'bg-gray-50 border-gray-200 text-gray-700';
        let icon = 'fa-info-circle';
        let html = '';
        const studentLine = res.student ? `<div class="text-lg font-bold">${esc(res.student.name)}${res.student.class ? ' · ' + esc(res.student.class) : ''}</div>` : '';
        if (s === 'checked_in') {
            cls = res.wrong_room ? 'bg-amber-50 border-amber-300 text-amber-800' : 'bg-emerald-50 border-emerald-300 text-emerald-800';
            icon = res.wrong_room ? 'fa-triangle-exclamation' : 'fa-circle-check';
            html = studentLine + `<div class="text-sm mt-1">${esc(res.message || '')}</div>`;
        } else if (s === 'already') {
            cls = 'bg-sky-50 border-sky-300 text-sky-800'; icon = 'fa-clock';
            html = studentLine + `<div class="text-sm mt-1">${esc(res.message || 'Bereits anwesend.')}</div>`;
        } else if (s === 'requires_confirmation') {
            cls = 'bg-amber-50 border-amber-300 text-amber-900'; icon = 'fa-triangle-exclamation';
            html = studentLine + `<div class="text-sm mt-1">${esc(res.message || '')}</div>
                <div class="flex justify-center gap-2 mt-3">
                    <button id="forceBtn" class="px-4 py-2 bg-amber-500 text-white rounded-lg text-sm font-medium">Trotzdem einchecken</button>
                    <button id="rejectBtn" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm">Abweisen</button>
                </div>`;
        } else {
            cls = 'bg-red-50 border-red-300 text-red-800'; icon = 'fa-circle-xmark';
            html = `<div class="text-sm">${esc(res.message || 'Fehler.')}</div>`;
        }
        resultCard.className = 'rounded-xl p-5 border text-center ' + cls;
        resultCard.innerHTML = `<i class="fas ${icon} text-3xl mb-2"></i>` + html;

        const forceBtn = document.getElementById('forceBtn');
        if (forceBtn) {
            forceBtn.addEventListener('click', () => {
                if (pendingConfirm) { const p = Object.assign({}, pendingConfirm, {force: 1}); pendingConfirm = null; postCheckin(p); }
            });
            document.getElementById('rejectBtn').addEventListener('click', () => {
                pendingConfirm = null; resultCard.classList.add('hidden');
            });
        }
        // Auto-Hide bei klaren Erfolgen
        if (s === 'checked_in' || s === 'already') {
            setTimeout(() => { resultCard.classList.add('hidden'); }, 4000);
        }
    }

    function handleScan(data) {
        let token = data;
        try { const u = new URL(data); const t = u.searchParams.get('token'); if (t) token = t; } catch (e) {}
        const now = Date.now();
        if (token === lastToken && now - lastTokenTime < 3000) return; // Doppel-Scan ignorieren
        lastToken = token; lastTokenTime = now;
        if (processing) return;
        postCheckin({ student_token: token });
    }

    // Kamera
    document.getElementById('toggleCameraBtn').addEventListener('click', function() {
        const container = document.getElementById('qr-camera-container');
        const btn = this;
        if (cameraActive) {
            QRCameraScanner.stop(); cameraActive = false;
            container.innerHTML = '<div class="text-center text-gray-400 p-6"><i class="fas fa-camera text-4xl mb-3"></i><p class="text-sm">Kamera gestoppt.</p></div>';
            btn.querySelector('span').textContent = 'Kamera starten'; statusEl.textContent = '';
        } else {
            statusEl.textContent = 'Kamera wird gestartet…';
            QRCameraScanner.init({ container: container, onScan: handleScan,
                onError: msg => { statusEl.textContent = msg; } });
            cameraActive = true; btn.querySelector('span').textContent = 'Kamera stoppen';
            statusEl.textContent = 'Kamera aktiv — Schüler-QR in den Rahmen halten.';
        }
    });
    window.addEventListener('beforeunload', () => { if (cameraActive) QRCameraScanner.stop(); });

    // Manuelle Suche
    const manualSearch = document.getElementById('manualSearch');
    const manualResults = document.getElementById('manualResults');
    let searchTimer = null;
    manualSearch.addEventListener('input', function() {
        clearTimeout(searchTimer);
        const q = this.value.trim();
        if (q.length < 2) { manualResults.innerHTML = ''; return; }
        searchTimer = setTimeout(() => {
            fetch(API.search + (API.search.includes('?') ? '&' : '?') + 'q=' + encodeURIComponent(q), { headers: {'Accept':'application/json'} })
                .then(r => r.json()).then(res => {
                    manualResults.innerHTML = '';
                    (res.users || []).forEach(u => {
                        const b = document.createElement('button');
                        b.className = 'w-full text-left px-3 py-2 rounded-lg hover:bg-emerald-50 text-sm flex justify-between';
                        b.innerHTML = `<span>${esc(u.name)}</span><span class="text-gray-400">${esc(u.class||'')}</span>`;
                        b.addEventListener('click', () => { postCheckin({ student_user_id: u.id }); manualSearch.value=''; manualResults.innerHTML=''; });
                        manualResults.appendChild(b);
                    });
                });
        }, 250);
    });

    // Roster
    const rosterPresent = document.getElementById('rosterPresent');
    const rosterMissing = document.getElementById('rosterMissing');
    function loadRoster() {
        if (!roomSelect.value) return;
        const url = API.roster + (API.roster.includes('?') ? '&' : '?') + 'room_id=' + roomSelect.value + '&timeslot_id=' + slotSelect.value;
        fetch(url, { headers: {'Accept':'application/json'} }).then(r => r.json()).then(res => {
            if (!res.success) return;
            document.getElementById('presentCount').textContent = res.present_count || 0;
            document.getElementById('expectedCount').textContent = res.expected_count || 0;
            document.getElementById('missingCount').textContent = (res.missing || []).length;
            rosterPresent.innerHTML = (res.present || []).map(p =>
                `<div class="flex justify-between items-center text-sm py-1">
                    <span>${esc(p.name)} <span class="text-gray-400">${esc(p.class||'')}</span>
                    ${p.wrong_room ? '<span class="badge badge-peach ml-1">Falscher Raum</span>' : ''}
                    ${p.method === 'manual' ? '<span class="text-gray-300 text-xs ml-1">manuell</span>' : ''}</span>
                    <span class="text-emerald-600 text-xs">${esc(p.time||'')}</span>
                </div>`).join('') || '<p class="text-xs text-gray-400">Noch niemand eingecheckt.</p>';
            rosterMissing.innerHTML = (res.missing || []).map(m =>
                `<div class="text-sm py-1">${esc(m.name)} <span class="text-gray-400">${esc(m.class||'')}</span></div>`
                ).join('') || '<p class="text-xs text-emerald-600">Alle registrierten Schüler sind da.</p>';
        });
    }
    document.querySelectorAll('.roster-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.roster-tab').forEach(t => { t.classList.remove('border-emerald-500','text-emerald-600'); t.classList.add('border-transparent','text-gray-500'); });
            this.classList.add('border-emerald-500','text-emerald-600'); this.classList.remove('border-transparent','text-gray-500');
            rosterPresent.classList.toggle('hidden', this.dataset.tab !== 'present');
            rosterMissing.classList.toggle('hidden', this.dataset.tab !== 'missing');
        });
    });

    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

    applyAssignmentDefault();
    loadRoster();
    setInterval(loadRoster, 15000);
})();
</script>
<?php endif; ?>
