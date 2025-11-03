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

<div class="max-w-4xl mx-auto">
    <!-- Status Banner -->
    <div class="mb-6">
        <?php if ($regStatus === 'open'): ?>
            <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 text-2xl mr-4"></i>
                    <div class="flex-1">
                        <h3 class="font-semibold text-green-800">Einschreibung ist geöffnet</h3>
                        <p class="text-sm text-green-700 mt-1">
                            Noch bis zum <?php echo formatDateTime($regEnd); ?>
                        </p>
                    </div>
                </div>
            </div>
        <?php elseif ($regStatus === 'upcoming'): ?>
            <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-clock text-yellow-500 text-2xl mr-4"></i>
                    <div class="flex-1">
                        <h3 class="font-semibold text-yellow-800">Einschreibung startet bald</h3>
                        <p class="text-sm text-yellow-700 mt-1">
                            Ab dem <?php echo formatDateTime($regStart); ?>
                        </p>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-lock text-red-500 text-2xl mr-4"></i>
                    <div class="flex-1">
                        <h3 class="font-semibold text-red-800">Einschreibung ist geschlossen</h3>
                        <p class="text-sm text-red-700 mt-1">
                            Die Einschreibefrist ist am <?php echo formatDateTime($regEnd); ?> abgelaufen.
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Fortschrittsanzeige -->
    <div class="bg-white rounded-xl shadow-md p-6 mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Ihr Fortschritt</h3>
        <div class="flex items-center justify-between mb-2">
            <span class="text-sm text-gray-600">Einschreibungen</span>
            <span class="text-sm font-semibold text-gray-800">
                <?php echo $userRegCount; ?> / <?php echo $maxRegistrations; ?>
            </span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-3">
            <?php 
            $progress = ($maxRegistrations > 0) ? ($userRegCount / $maxRegistrations * 100) : 0;
            ?>
            <div class="bg-blue-600 h-3 rounded-full transition-all duration-500" 
                 style="width: <?php echo min($progress, 100); ?>%"></div>
        </div>
        <p class="text-xs text-gray-500 mt-2">
            <?php if ($userRegCount >= $maxRegistrations): ?>
                Sie haben alle verfügbaren Einschreibungen genutzt.
            <?php else: ?>
                Sie können sich noch für <?php echo $maxRegistrations - $userRegCount; ?> weitere(n) Aussteller einschreiben.
            <?php endif; ?>
        </p>
    </div>

    <?php if (isset($message)): ?>
    <div class="mb-6 animate-pulse">
        <?php if ($message['type'] === 'success'): ?>
            <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 mr-3"></i>
                    <p class="text-green-700"><?php echo $message['text']; ?></p>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                    <p class="text-red-700"><?php echo $message['text']; ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Aussteller Liste -->
    <div class="bg-white rounded-xl shadow-md p-6">
        <h3 class="text-xl font-bold text-gray-800 mb-6 flex items-center">
            <i class="fas fa-clipboard-list text-blue-600 mr-3"></i>
            Verfügbare Aussteller
        </h3>

        <?php if (empty($exhibitorsWithSlots)): ?>
            <div class="text-center py-12">
                <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-500 text-lg">Derzeit sind keine Aussteller mit verfügbaren Plätzen vorhanden.</p>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($exhibitorsWithSlots as $exhibitor): ?>
                <div class="border border-gray-200 rounded-lg p-4 hover:border-blue-300 hover:shadow-md transition">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="flex-1">
                            <h4 class="font-semibold text-gray-800 text-lg mb-1">
                                <?php echo htmlspecialchars($exhibitor['name']); ?>
                            </h4>
                            <p class="text-sm text-gray-600 mb-2">
                                <?php echo htmlspecialchars(substr($exhibitor['short_description'] ?? '', 0, 100)); ?>...
                            </p>
                            <div class="flex flex-wrap gap-2 text-sm">
                                <span class="inline-flex items-center px-3 py-1 rounded-full bg-green-100 text-green-800">
                                    <i class="fas fa-check-circle mr-1"></i>
                                    <?php echo $exhibitor['available_slots_count']; ?> Plätze verfügbar
                                </span>
                                <span class="inline-flex items-center px-3 py-1 rounded-full bg-blue-100 text-blue-800">
                                    <i class="fas fa-users mr-1"></i>
                                    Automatische Zuteilung
                                </span>
                            </div>
                        </div>
                        
                        <div class="flex flex-col sm:flex-row gap-2">
                            <button onclick="openExhibitorModal(<?php echo $exhibitor['id']; ?>)" 
                                    class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                                <i class="fas fa-info-circle mr-2"></i>Details
                            </button>
                            
                            <?php if ($regStatus === 'open' && $userRegCount < $maxRegistrations): ?>
                            <form method="POST" class="inline">
                                <input type="hidden" name="exhibitor_id" value="<?php echo $exhibitor['id']; ?>">
                                <button type="submit" 
                                        name="register" 
                                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold whitespace-nowrap"
                                        onclick="return confirm('Möchten Sie sich für diesen Aussteller einschreiben? Sie werden automatisch dem Slot mit den wenigsten Teilnehmern zugewiesen.')">
                                    <i class="fas fa-check mr-2"></i>Einschreiben
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
    <div class="mt-6 bg-blue-50 border border-blue-200 rounded-xl p-6">
        <h4 class="font-semibold text-blue-900 mb-3 flex items-center">
            <i class="fas fa-info-circle mr-2"></i>
            Wie funktioniert die Einschreibung?
        </h4>
        <ul class="space-y-2 text-sm text-blue-800">
            <li class="flex items-start">
                <i class="fas fa-check text-blue-600 mr-2 mt-1"></i>
                <span>Sie können sich für bis zu <?php echo $maxRegistrations; ?> Aussteller einschreiben.</span>
            </li>
            <li class="flex items-start">
                <i class="fas fa-check text-blue-600 mr-2 mt-1"></i>
                <span>Die Einschreibung gilt für die <strong>Slots 1, 3 und 5</strong> (09:00-09:30, 10:40-11:10, 12:20-12:50).</span>
            </li>
            <li class="flex items-start">
                <i class="fas fa-check text-blue-600 mr-2 mt-1"></i>
                <span><strong>Slots 2 und 4 sind zur freien Wahl vor Ort</strong> - keine vorherige Anmeldung erforderlich.</span>
            </li>
            <li class="flex items-start">
                <i class="fas fa-check text-blue-600 mr-2 mt-1"></i>
                <span>Das System verteilt Sie automatisch auf den Zeitslot mit den wenigsten Anmeldungen für den gewählten Aussteller.</span>
            </li>
            <li class="flex items-start">
                <i class="fas fa-check text-blue-600 mr-2 mt-1"></i>
                <span>Bei Fristversäumnis erfolgt eine automatische Zuteilung zu Ausstellern mit freien Plätzen.</span>
            </li>
        </ul>
    </div>
</div>

<!-- Modal für Aussteller-Details (gleich wie in exhibitors.php) -->
<div id="exhibitorModal" class="modal fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4" onclick="closeModalOnBackdrop(event)">
    <div class="modal-content bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden" onclick="event.stopPropagation()">
        <!-- Modal Header -->
        <div class="sticky top-0 bg-blue-600 text-white px-6 py-4 flex items-center justify-between z-10">
            <h2 id="modalTitle" class="text-2xl font-bold">Aussteller Details</h2>
            <button onclick="closeExhibitorModal()" class="text-white hover:bg-white/20 rounded-lg p-2 transition">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <!-- Tabs -->
        <div class="border-b border-gray-200 bg-white sticky top-[72px] z-10">
            <nav class="flex overflow-x-auto">
                <button onclick="switchTab('info')" id="tab-info" class="tab-button px-6 py-4 font-semibold text-blue-600 border-b-2 border-blue-600 whitespace-nowrap">
                    <i class="fas fa-info-circle mr-2"></i>Informationen
                </button>
                <button onclick="switchTab('documents')" id="tab-documents" class="tab-button px-6 py-4 font-semibold text-gray-500 hover:text-gray-700 border-b-2 border-transparent whitespace-nowrap">
                    <i class="fas fa-file-download mr-2"></i>Dokumente
                </button>
                <button onclick="switchTab('contact')" id="tab-contact" class="tab-button px-6 py-4 font-semibold text-gray-500 hover:text-gray-700 border-b-2 border-transparent whitespace-nowrap">
                    <i class="fas fa-address-card mr-2"></i>Kontakt
                </button>
            </nav>
        </div>

        <!-- Modal Body -->
        <div id="modalBody" class="p-6 overflow-y-auto" style="max-height: calc(90vh - 200px);">
            <!-- Content wird per JavaScript geladen -->
        </div>
    </div>
</div>

<script>
let currentExhibitorId = null;

function openExhibitorModal(exhibitorId) {
    currentExhibitorId = exhibitorId;
    const modal = document.getElementById('exhibitorModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    
    // Animation
    setTimeout(() => {
        modal.style.opacity = '1';
        modal.querySelector('.modal-content').style.transform = 'scale(1)';
    }, 10);
    
    // Daten laden
    loadExhibitorData(exhibitorId, 'info');
    document.body.style.overflow = 'hidden';
}

function closeExhibitorModal() {
    const modal = document.getElementById('exhibitorModal');
    modal.style.opacity = '0';
    modal.querySelector('.modal-content').style.transform = 'scale(0.95)';
    
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

function switchTab(tabName) {
    // Tab-Buttons aktualisieren
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('text-blue-600', 'border-blue-600');
        btn.classList.add('text-gray-500', 'border-transparent');
    });
    
    const activeTab = document.getElementById(`tab-${tabName}`);
    activeTab.classList.remove('text-gray-500', 'border-transparent');
    activeTab.classList.add('text-blue-600', 'border-blue-600');
    
    // Content laden
    loadExhibitorData(currentExhibitorId, tabName);
}

function loadExhibitorData(exhibitorId, tab) {
    const modalBody = document.getElementById('modalBody');
    modalBody.innerHTML = '<div class="flex items-center justify-center py-12"><i class="fas fa-spinner fa-spin text-4xl text-blue-600"></i></div>';
    
    fetch(`api/get-exhibitor.php?id=${exhibitorId}&tab=${tab}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('modalTitle').textContent = data.exhibitor.name;
                modalBody.innerHTML = data.content;
            } else {
                modalBody.innerHTML = '<div class="text-center py-12 text-red-600">Fehler beim Laden der Daten</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            modalBody.innerHTML = '<div class="text-center py-12 text-red-600">Fehler beim Laden der Daten</div>';
        });
}

// ESC-Taste zum Schließen
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeExhibitorModal();
    }
});

// Initial Modal Style
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('exhibitorModal');
    if (modal) {
        modal.style.opacity = '0';
        modal.querySelector('.modal-content').style.transform = 'scale(0.95)';
    }
});
</script>
