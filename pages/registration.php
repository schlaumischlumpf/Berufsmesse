<?php
// Einschreibungsseite - Premium Registration Experience

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

// Progress percentage
$progress = ($maxRegistrations > 0) ? ($userRegCount / $maxRegistrations * 100) : 0;
?>

<div class="max-w-5xl mx-auto space-y-8">
    <!-- Hero Header with Status -->
    <div class="relative overflow-hidden bg-gradient-to-br <?php 
        if ($regStatus === 'open') echo 'from-primary-500 via-emerald-500 to-teal-600';
        elseif ($regStatus === 'upcoming') echo 'from-amber-500 via-orange-500 to-yellow-600';
        else echo 'from-gray-500 via-slate-500 to-gray-600';
    ?> rounded-3xl p-8 text-white shadow-2xl">
        <div class="absolute inset-0 bg-black/5"></div>
        <div class="absolute top-0 right-0 w-96 h-96 bg-white/10 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2"></div>
        <div class="absolute bottom-0 left-0 w-72 h-72 bg-white/10 rounded-full blur-3xl translate-y-1/2 -translate-x-1/2"></div>
        
        <div class="relative">
            <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-6">
                <div class="flex-1">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-16 h-16 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center shadow-lg">
                            <?php if ($regStatus === 'open'): ?>
                                <i class="fas fa-door-open text-3xl"></i>
                            <?php elseif ($regStatus === 'upcoming'): ?>
                                <i class="fas fa-hourglass-half text-3xl"></i>
                            <?php else: ?>
                                <i class="fas fa-lock text-3xl"></i>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h1 class="text-3xl font-extrabold font-display">Einschreibung</h1>
                            <p class="text-white/80">
                                <?php if ($regStatus === 'open'): ?>
                                    Wähle deine Aussteller für die Berufsmesse
                                <?php elseif ($regStatus === 'upcoming'): ?>
                                    Startet am <?php echo formatDateTime($regStart); ?>
                                <?php else: ?>
                                    Endete am <?php echo formatDateTime($regEnd); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    
                    <?php if ($regStatus === 'open'): ?>
                    <div class="mt-6 p-4 bg-white/10 backdrop-blur-sm rounded-2xl">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-sm font-medium text-white/90">Dein Fortschritt</span>
                            <span class="px-3 py-1 bg-white/20 rounded-full text-sm font-bold">
                                <?php echo $userRegCount; ?> / <?php echo $maxRegistrations; ?>
                            </span>
                        </div>
                        <div class="h-3 bg-white/20 rounded-full overflow-hidden">
                            <div class="h-full bg-white rounded-full transition-all duration-700 ease-out relative overflow-hidden" style="width: <?php echo min($progress, 100); ?>%">
                                <div class="absolute inset-0 shimmer-effect"></div>
                            </div>
                        </div>
                        <p class="text-sm text-white/70 mt-2">
                            <?php if ($userRegCount >= $maxRegistrations): ?>
                                <i class="fas fa-check-circle mr-1"></i> Alle Einschreibungen abgeschlossen!
                            <?php else: ?>
                                <i class="fas fa-info-circle mr-1"></i> Noch <?php echo $maxRegistrations - $userRegCount; ?> Plätze verfügbar
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Progress Circle -->
                <div class="flex-shrink-0 hidden lg:block">
                    <div class="relative w-36 h-36">
                        <svg class="w-full h-full transform -rotate-90" viewBox="0 0 100 100">
                            <circle cx="50" cy="50" r="45" stroke="rgba(255,255,255,0.2)" stroke-width="8" fill="none"/>
                            <circle cx="50" cy="50" r="45" stroke="white" stroke-width="8" fill="none"
                                    stroke-dasharray="<?php echo 2 * 3.14159 * 45; ?>"
                                    stroke-dashoffset="<?php echo 2 * 3.14159 * 45 * (1 - $progress / 100); ?>"
                                    stroke-linecap="round"
                                    class="transition-all duration-1000 ease-out"/>
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-4xl font-bold"><?php echo $userRegCount; ?></span>
                            <span class="text-xs text-white/70">von <?php echo $maxRegistrations; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Message Alert -->
    <?php if (isset($message)): ?>
    <div class="transform transition-all duration-500 animate-slideUp">
        <?php if ($message['type'] === 'success'): ?>
            <div class="bg-gradient-to-r from-primary-50 to-emerald-50 border border-primary-200 p-5 rounded-2xl shadow-lg">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-primary-100 rounded-xl flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-check-circle text-primary-500 text-xl"></i>
                    </div>
                    <div>
                        <h4 class="font-bold text-primary-800 mb-1">Erfolg!</h4>
                        <p class="text-primary-700 text-sm"><?php echo $message['text']; ?></p>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-gradient-to-r from-red-50 to-rose-50 border border-red-200 p-5 rounded-2xl shadow-lg">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-exclamation-circle text-red-500 text-xl"></i>
                    </div>
                    <div>
                        <h4 class="font-bold text-red-800 mb-1">Fehler</h4>
                        <p class="text-red-700 text-sm"><?php echo $message['text']; ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Exhibitors Grid -->
    <div class="bg-white rounded-3xl shadow-xl border border-gray-100 overflow-hidden">
        <div class="p-6 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-800 flex items-center gap-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-primary-500 to-emerald-500 rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-clipboard-list text-white"></i>
                    </div>
                    Verfügbare Aussteller
                </h2>
                <span class="px-3 py-1 bg-gray-100 text-gray-600 text-sm font-medium rounded-full">
                    <?php echo count($exhibitorsWithSlots); ?> verfügbar
                </span>
            </div>
        </div>

        <div class="p-6">
            <?php if (empty($exhibitorsWithSlots)): ?>
                <div class="text-center py-16">
                    <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-inbox text-5xl text-gray-300"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-400 mb-2">Keine Aussteller verfügbar</h3>
                    <p class="text-gray-500 text-sm">Derzeit sind keine Aussteller mit freien Plätzen vorhanden.</p>
                </div>
            <?php else: ?>
                <div class="grid gap-4">
                    <?php 
                    $gradients = [
                        'from-primary-500 to-emerald-500',
                        'from-purple-500 to-pink-500',
                        'from-blue-500 to-cyan-500',
                        'from-orange-500 to-amber-500',
                        'from-rose-500 to-red-500',
                        'from-teal-500 to-green-500'
                    ];
                    foreach ($exhibitorsWithSlots as $index => $exhibitor): 
                        $gradient = $gradients[$index % count($gradients)];
                    ?>
                    <div class="group relative bg-gradient-to-br from-white to-gray-50 rounded-2xl border border-gray-100 overflow-hidden hover:shadow-xl hover:border-primary-200 transition-all duration-300">
                        <!-- Gradient Accent -->
                        <div class="absolute left-0 top-0 bottom-0 w-1.5 bg-gradient-to-b <?php echo $gradient; ?> rounded-l-2xl"></div>
                        
                        <div class="p-5 pl-6">
                            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                                <div class="flex items-start gap-4 flex-1">
                                    <!-- Company Icon -->
                                    <div class="w-14 h-14 bg-gradient-to-br <?php echo $gradient; ?> rounded-2xl flex items-center justify-center shadow-lg flex-shrink-0 group-hover:scale-110 transition-transform duration-300">
                                        <i class="fas fa-building text-white text-xl"></i>
                                    </div>
                                    
                                    <div class="flex-1 min-w-0">
                                        <h4 class="font-bold text-gray-800 text-lg mb-1 truncate">
                                            <?php echo htmlspecialchars($exhibitor['name']); ?>
                                        </h4>
                                        <p class="text-sm text-gray-500 mb-3 line-clamp-2">
                                            <?php echo htmlspecialchars(substr($exhibitor['short_description'] ?? '', 0, 120)); ?>...
                                        </p>
                                        
                                        <!-- Tags -->
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="inline-flex items-center px-3 py-1 rounded-full bg-primary-50 text-primary-700 text-xs font-semibold">
                                                <i class="fas fa-users mr-1.5"></i>
                                                <?php echo $exhibitor['available_slots_count']; ?> Plätze frei
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Actions -->
                                <div class="flex items-center gap-3 lg:flex-shrink-0">
                                    <button onclick="openExhibitorModal(<?php echo $exhibitor['id']; ?>)" 
                                            class="px-4 py-2.5 bg-gray-100 text-gray-600 rounded-xl hover:bg-gray-200 transition-all duration-200 text-sm font-medium flex items-center gap-2">
                                        <i class="fas fa-info-circle"></i>
                                        <span>Details</span>
                                    </button>
                                    
                                    <?php if ($regStatus === 'open' && $userRegCount < $maxRegistrations): ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="exhibitor_id" value="<?php echo $exhibitor['id']; ?>">
                                        <button type="submit" 
                                                name="register" 
                                                class="px-5 py-2.5 bg-gradient-to-r from-primary-500 to-emerald-500 text-white rounded-xl hover:shadow-lg hover:scale-105 transition-all duration-200 font-semibold text-sm flex items-center gap-2"
                                                onclick="return confirm('Möchten Sie sich für diesen Aussteller einschreiben?')">
                                            <i class="fas fa-plus"></i>
                                            <span>Einschreiben</span>
                                        </button>
                                    </form>
                                    <?php elseif ($regStatus === 'open' && $userRegCount >= $maxRegistrations): ?>
                                    <span class="px-4 py-2.5 bg-gray-100 text-gray-400 rounded-xl text-sm font-medium cursor-not-allowed flex items-center gap-2">
                                        <i class="fas fa-ban"></i>
                                        <span>Limit erreicht</span>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Info Card -->
    <div class="bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-100 rounded-3xl p-6 shadow-lg">
        <h4 class="font-bold text-blue-900 mb-4 flex items-center gap-3 text-lg">
            <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-lightbulb text-blue-500"></i>
            </div>
            Wie funktioniert die Einschreibung?
        </h4>
        <div class="grid md:grid-cols-3 gap-4">
            <div class="bg-white/60 backdrop-blur-sm rounded-2xl p-4 border border-blue-100">
                <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mb-3">
                    <span class="text-blue-600 font-bold">1</span>
                </div>
                <h5 class="font-semibold text-blue-800 mb-1 text-sm">Auswählen</h5>
                <p class="text-xs text-blue-600">Wähle bis zu <?php echo $maxRegistrations; ?> Aussteller, die dich interessieren.</p>
            </div>
            <div class="bg-white/60 backdrop-blur-sm rounded-2xl p-4 border border-blue-100">
                <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mb-3">
                    <span class="text-blue-600 font-bold">2</span>
                </div>
                <h5 class="font-semibold text-blue-800 mb-1 text-sm">Automatische Zuweisung</h5>
                <p class="text-xs text-blue-600">Das System weist dir automatisch den optimalen Zeitslot zu.</p>
            </div>
            <div class="bg-white/60 backdrop-blur-sm rounded-2xl p-4 border border-blue-100">
                <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mb-3">
                    <span class="text-blue-600 font-bold">3</span>
                </div>
                <h5 class="font-semibold text-blue-800 mb-1 text-sm">Freie Slots</h5>
                <p class="text-xs text-blue-600">Slots 2 & 4 sind für freie Wahl vor Ort reserviert.</p>
            </div>
        </div>
    </div>
</div>

<!-- Modal für Aussteller-Details -->
<div id="exhibitorModal" class="fixed inset-0 bg-black/60 backdrop-blur-md hidden items-center justify-center z-50 p-4" onclick="closeModalOnBackdrop(event)">
    <div class="modal-content bg-white rounded-3xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden flex flex-col transform scale-95 opacity-0 transition-all duration-300" onclick="event.stopPropagation()">
        <!-- Modal Header -->
        <div class="bg-gradient-to-r from-primary-500 via-emerald-500 to-teal-500 px-6 py-5">
            <div class="flex items-center justify-between">
                <h2 id="modalTitle" class="text-xl font-bold text-white">Details</h2>
                <button onclick="closeExhibitorModal()" class="w-10 h-10 bg-white/20 hover:bg-white/30 rounded-xl flex items-center justify-center transition-all duration-200">
                    <i class="fas fa-times text-white"></i>
                </button>
            </div>
        </div>

        <!-- Modal Body -->
        <div id="modalBody" class="p-6 overflow-y-auto flex-1">
            <div class="flex items-center justify-center py-12">
                <div class="w-16 h-16 bg-gradient-to-br from-primary-500 to-emerald-500 rounded-2xl flex items-center justify-center animate-pulse">
                    <i class="fas fa-spinner fa-spin text-white text-2xl"></i>
                </div>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="bg-gray-50 border-t border-gray-100 px-6 py-4 flex justify-end gap-3">
            <button onclick="closeExhibitorModal()" class="px-5 py-2.5 bg-gray-200 text-gray-700 rounded-xl font-medium hover:bg-gray-300 transition-all duration-200">
                Schließen
            </button>
        </div>
    </div>
</div>

<style>
.shimmer-effect {
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
    animation: shimmer 2s infinite;
}

@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-slideUp {
    animation: slideUp 0.5s ease-out;
}

.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>

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
    modalBody.innerHTML = '<div class="flex items-center justify-center py-12"><div class="w-16 h-16 bg-gradient-to-br from-primary-500 to-emerald-500 rounded-2xl flex items-center justify-center animate-pulse"><i class="fas fa-spinner fa-spin text-white text-2xl"></i></div></div>';
    
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
