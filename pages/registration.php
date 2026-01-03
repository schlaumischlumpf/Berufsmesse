<?php
// Einschreibungsseite mit automatischer gleichmäßiger Verteilung

$regStatus = getRegistrationStatus();
$regStart = getSetting('registration_start');
$regEnd = getSetting('registration_end');

// Nur verwaltete Timeslots laden (Slots 1, 3, 5) - Slots 2 und 4 sind freie Wahl vor Ort
$stmt = $db->query("SELECT * FROM timeslots WHERE slot_number IN (1, 3, 5) ORDER BY slot_number ASC");
$timeslots = $stmt->fetchAll();

// Prüfen ob Benutzer bereits für alle Slots registriert ist
$stmt = $db->prepare("SELECT COUNT(*) as count FROM registrations WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$userRegCount = $stmt->fetch()['count'];
$maxRegistrations = intval(getSetting('max_registrations_per_student', 3));

// Handle Registration Form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    if ($regStatus !== 'open') {
        $message = ['type' => 'error', 'text' => 'Die Einschreibung ist derzeit nicht möglich.'];
    } elseif ($userRegCount >= $maxRegistrations) {
        $message = ['type' => 'error', 'text' => 'Sie haben bereits die maximale Anzahl an Einschreibungen erreicht.'];
    } else {
        $exhibitorId = intval($_POST['exhibitor_id']);
        
        // Verfügbare Slots für diesen Aussteller ermitteln
        $availableSlots = getAvailableSlots($db, $exhibitorId, $_SESSION['user_id']);
        
        if (empty($availableSlots)) {
            $message = ['type' => 'error', 'text' => 'Für diesen Aussteller sind keine Plätze mehr verfügbar.'];
        } else {
            // Den Slot mit den wenigsten Anmeldungen wählen (automatische gleichmäßige Verteilung)
            $selectedSlot = $availableSlots[0]['timeslot_id'];
            
            try {
                $stmt = $db->prepare("INSERT INTO registrations (user_id, exhibitor_id, timeslot_id, registration_type) VALUES (?, ?, ?, 'manual')");
                $stmt->execute([$_SESSION['user_id'], $exhibitorId, $selectedSlot]);
                
                $message = ['type' => 'success', 'text' => 'Erfolgreich eingeschrieben! Sie wurden automatisch dem Slot mit den wenigsten Teilnehmern zugewiesen.'];
                
                // Counter aktualisieren
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM registrations WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $userRegCount = $stmt->fetch()['count'];
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $message = ['type' => 'error', 'text' => 'Sie sind bereits für einen Aussteller in diesem Zeitslot angemeldet.'];
                } else {
                    $message = ['type' => 'error', 'text' => 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.'];
                }
            }
        }
    }
}

// Funktion zur Ermittlung verfügbarer Slots mit gleichmäßiger Verteilung
function getAvailableSlots($db, $exhibitorId, $userId) {
    // Raum-Kapazität abrufen
    $stmt = $db->prepare("
        SELECT r.capacity 
        FROM exhibitors e 
        LEFT JOIN rooms r ON e.room_id = r.id 
        WHERE e.id = ?
    ");
    $stmt->execute([$exhibitorId]);
    $roomData = $stmt->fetch();
    
    if (!$roomData || !$roomData['capacity']) return [];
    
    $roomCapacity = intval($roomData['capacity']);
    $slotsPerTimeslot = floor($roomCapacity / 3); // Kapazität pro Slot (abgerundet)
    
    if ($slotsPerTimeslot <= 0) return [];
    
    // Nur verwaltete Slots (1, 3, 5) - Slots 2 und 4 sind freie Wahl vor Ort
    $stmt = $db->query("SELECT id, slot_number FROM timeslots WHERE slot_number IN (1, 3, 5) ORDER BY slot_number ASC");
    $timeslots = $stmt->fetchAll();
    
    $availableSlots = [];
    
    foreach ($timeslots as $slot) {
        // Prüfen ob User bereits in diesem Slot registriert ist
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM registrations WHERE user_id = ? AND timeslot_id = ?");
        $stmt->execute([$userId, $slot['id']]);
        $userInSlot = $stmt->fetch()['count'];
        
        if ($userInSlot > 0) {
            continue; // User bereits in diesem Slot registriert
        }
        
        // Anzahl der Registrierungen für diesen Aussteller in diesem Slot
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM registrations WHERE exhibitor_id = ? AND timeslot_id = ?");
        $stmt->execute([$exhibitorId, $slot['id']]);
        $registrations = $stmt->fetch()['count'];
        
        if ($registrations < $slotsPerTimeslot) {
            $availableSlots[] = [
                'timeslot_id' => $slot['id'],
                'registrations' => $registrations,
                'capacity' => $slotsPerTimeslot
            ];
        }
    }
    
    // Nach Anzahl der Registrierungen sortieren (aufsteigend)
    usort($availableSlots, function($a, $b) {
        return $a['registrations'] - $b['registrations'];
    });
    
    return $availableSlots;
}

// Aussteller mit verfügbaren Plätzen laden
$exhibitorsWithSlots = [];
foreach ($exhibitors as $exhibitor) {
    $availableSlots = getAvailableSlots($db, $exhibitor['id'], $_SESSION['user_id']);
    if (!empty($availableSlots)) {
        $exhibitor['available_slots_count'] = array_sum(array_column($availableSlots, 'capacity')) - array_sum(array_column($availableSlots, 'registrations'));
        $exhibitorsWithSlots[] = $exhibitor;
    }
}
?>

<div class="max-w-4xl mx-auto space-y-6">
    <!-- Status Banner -->
    <?php if ($regStatus === 'open'): ?>
        <div class="bg-emerald-50 border border-emerald-200 p-4 rounded-xl">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-emerald-100 rounded-lg flex items-center justify-center mr-3">
                    <i class="fas fa-check-circle text-emerald-600"></i>
                </div>
                <div>
                    <h3 class="font-semibold text-emerald-800 text-sm">Einschreibung geöffnet</h3>
                    <p class="text-xs text-emerald-600">Bis <?php echo formatDateTime($regEnd); ?></p>
                </div>
            </div>
        </div>
    <?php elseif ($regStatus === 'upcoming'): ?>
        <div class="bg-amber-50 border border-amber-200 p-4 rounded-xl">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-amber-100 rounded-lg flex items-center justify-center mr-3">
                    <i class="fas fa-clock text-amber-600"></i>
                </div>
                <div>
                    <h3 class="font-semibold text-amber-800 text-sm">Einschreibung startet bald</h3>
                    <p class="text-xs text-amber-600">Ab <?php echo formatDateTime($regStart); ?></p>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="bg-red-50 border border-red-200 p-4 rounded-xl">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center mr-3">
                    <i class="fas fa-lock text-red-600"></i>
                </div>
                <div>
                    <h3 class="font-semibold text-red-800 text-sm">Einschreibung geschlossen</h3>
                    <p class="text-xs text-red-600">Endete am <?php echo formatDateTime($regEnd); ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Fortschrittsanzeige -->
    <div class="bg-white rounded-xl border border-gray-100 p-5">
        <h3 class="text-sm font-semibold text-gray-800 mb-3">Ihr Fortschritt</h3>
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs text-gray-500">Einschreibungen</span>
            <span class="text-xs font-semibold text-gray-700">
                <?php echo $userRegCount; ?> / <?php echo $maxRegistrations; ?>
            </span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-2">
            <?php 
            $progress = ($maxRegistrations > 0) ? ($userRegCount / $maxRegistrations * 100) : 0;
            ?>
            <div class="bg-emerald-500 h-2 rounded-full transition-all" 
                 style="width: <?php echo min($progress, 100); ?>%"></div>
        </div>
        <p class="text-xs text-gray-400 mt-2">
            <?php if ($userRegCount >= $maxRegistrations): ?>
                Alle Einschreibungen genutzt.
            <?php else: ?>
                Noch <?php echo $maxRegistrations - $userRegCount; ?> verfügbar
            <?php endif; ?>
        </p>
    </div>

    <?php if (isset($message)): ?>
    <div class="animate-pulse">
        <?php if ($message['type'] === 'success'): ?>
            <div class="bg-emerald-50 border border-emerald-200 p-4 rounded-xl">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-emerald-500 mr-3"></i>
                    <p class="text-emerald-700 text-sm"><?php echo $message['text']; ?></p>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-red-50 border border-red-200 p-4 rounded-xl">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                    <p class="text-red-700 text-sm"><?php echo $message['text']; ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Aussteller Liste -->
    <div class="bg-white rounded-xl border border-gray-100 p-5">
        <h3 class="text-base font-semibold text-gray-800 mb-4 flex items-center">
            <i class="fas fa-clipboard-list text-emerald-500 mr-2"></i>
            Verfügbare Aussteller
        </h3>

        <?php if (empty($exhibitorsWithSlots)): ?>
            <div class="text-center py-12">
                <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-500 text-sm">Derzeit sind keine Aussteller mit verfügbaren Plätzen vorhanden.</p>
            </div>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($exhibitorsWithSlots as $exhibitor): ?>
                <div class="border border-gray-100 rounded-xl p-4 hover:border-emerald-200 hover:bg-gray-50 transition">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="flex-1">
                            <h4 class="font-semibold text-gray-800 mb-1">
                                <?php echo htmlspecialchars($exhibitor['name']); ?>
                            </h4>
                            <p class="text-sm text-gray-500 mb-2">
                                <?php echo htmlspecialchars(substr($exhibitor['short_description'] ?? '', 0, 100)); ?>...
                            </p>
                            <div class="flex flex-wrap gap-2 text-xs">
                                <span class="inline-flex items-center px-2 py-1 rounded-md bg-emerald-50 text-emerald-700">
                                    <i class="fas fa-check-circle mr-1"></i>
                                    <?php echo $exhibitor['available_slots_count']; ?> Plätze
                                </span>
                            </div>
                        </div>
                        
                        <div class="flex flex-col sm:flex-row gap-2">
                            <button onclick="openExhibitorModal(<?php echo $exhibitor['id']; ?>)" 
                                    class="px-3 py-1.5 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 transition text-sm">
                                <i class="fas fa-info-circle mr-1"></i>Details
                            </button>
                            
                            <?php if ($regStatus === 'open' && $userRegCount < $maxRegistrations): ?>
                            <form method="POST" class="inline">
                                <input type="hidden" name="exhibitor_id" value="<?php echo $exhibitor['id']; ?>">
                                <button type="submit" 
                                        name="register" 
                                        class="px-3 py-1.5 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition font-medium text-sm"
                                        onclick="return confirm('Möchten Sie sich für diesen Aussteller einschreiben?')">
                                    <i class="fas fa-check mr-1"></i>Einschreiben
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Info Box -->
    <div class="bg-blue-50 border border-blue-100 rounded-xl p-5">
        <h4 class="font-semibold text-blue-900 mb-3 flex items-center text-sm">
            <i class="fas fa-info-circle mr-2"></i>
            Wie funktioniert die Einschreibung?
        </h4>
        <ul class="space-y-2 text-xs text-blue-800">
            <li class="flex items-start">
                <i class="fas fa-check text-blue-500 mr-2 mt-0.5"></i>
                <span>Sie können sich für bis zu <?php echo $maxRegistrations; ?> Aussteller einschreiben.</span>
            </li>
            <li class="flex items-start">
                <i class="fas fa-check text-blue-500 mr-2 mt-0.5"></i>
                <span>Slots 1, 3, 5 sind Pflichtslots. Slots 2 und 4 sind zur freien Wahl vor Ort.</span>
            </li>
            <li class="flex items-start">
                <i class="fas fa-check text-blue-500 mr-2 mt-0.5"></i>
                <span>Das System verteilt Sie automatisch auf den optimalen Zeitslot.</span>
            </li>
        </ul>
    </div>
</div>

<!-- Modal für Aussteller-Details -->
<div id="exhibitorModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50 p-4" onclick="closeModalOnBackdrop(event)">
    <div class="modal-content bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden flex flex-col transform scale-95 opacity-0 transition-all duration-300" onclick="event.stopPropagation()">
        <!-- Modal Header -->
        <div class="bg-white border-b border-gray-100 px-6 py-4 flex items-center justify-between">
            <h2 id="modalTitle" class="text-xl font-bold text-gray-800">Details</h2>
            <button onclick="closeExhibitorModal()" class="text-gray-400 hover:text-gray-600 rounded-lg p-2 hover:bg-gray-100 transition">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <div id="modalBody" class="p-6 overflow-y-auto flex-1">
            <div class="flex items-center justify-center py-12">
                <i class="fas fa-spinner fa-spin text-3xl text-emerald-500"></i>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="bg-gray-50 border-t border-gray-100 px-6 py-4 flex justify-end gap-3">
            <button onclick="closeExhibitorModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-300 transition">
                Schließen
            </button>
        </div>
    </div>
</div>

<script>
function openExhibitorModal(exhibitorId) {
    const modal = document.getElementById('exhibitorModal');
    const content = modal.querySelector('.modal-content');
    
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    
    setTimeout(() => {
        content.classList.remove('scale-95', 'opacity-0');
        content.classList.add('scale-100', 'opacity-100');
    }, 10);
    
    loadExhibitorDetails(exhibitorId);
    document.body.style.overflow = 'hidden';
}

function closeExhibitorModal() {
    const modal = document.getElementById('exhibitorModal');
    const content = modal.querySelector('.modal-content');
    
    content.classList.add('scale-95', 'opacity-0');
    content.classList.remove('scale-100', 'opacity-100');
    
    setTimeout(() => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = 'auto';
    }, 300);
}

function closeModalOnBackdrop(event) {
    if (event.target.id === 'exhibitorModal') {
        closeExhibitorModal();
    }
}

function loadExhibitorDetails(exhibitorId) {
    const modalBody = document.getElementById('modalBody');
    modalBody.innerHTML = '<div class="flex items-center justify-center py-12"><i class="fas fa-spinner fa-spin text-3xl text-emerald-500"></i></div>';
    
    fetch('api/get-exhibitor.php?id=' + exhibitorId + '&tab=details')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('modalTitle').textContent = data.exhibitor.name;
                modalBody.innerHTML = data.content;
            } else {
                modalBody.innerHTML = '<div class="text-center py-12 text-red-500">Fehler beim Laden</div>';
            }
        })
        .catch(error => {
            modalBody.innerHTML = '<div class="text-center py-12 text-red-500">Fehler beim Laden</div>';
        });
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeExhibitorModal();
});
</script>
