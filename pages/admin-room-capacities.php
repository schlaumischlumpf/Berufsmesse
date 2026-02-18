<?php
// Admin Room Capacity Management (Issue #4)

// Alle Räume laden
$stmt = $db->query("SELECT * FROM rooms ORDER BY building, floor, room_number");
$rooms = $stmt->fetchAll();

// Alle Timeslots laden
$stmt = $db->query("SELECT * FROM timeslots ORDER BY slot_number");
$timeslots = $stmt->fetchAll();

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_capacities'])) {
    $db->beginTransaction();
    try {
        // Alle vorhandenen Einträge löschen
        $db->exec("DELETE FROM room_slot_capacities");
        
        // Neue Kapazitäten speichern
        $stmt = $db->prepare("INSERT INTO room_slot_capacities (room_id, timeslot_id, capacity) VALUES (?, ?, ?)");
        
        foreach ($_POST['capacity'] as $roomId => $timeslotData) {
            foreach ($timeslotData as $timeslotId => $capacity) {
                $cap = intval($capacity);
                if ($cap > 0) {
                    $stmt->execute([$roomId, $timeslotId, $cap]);
                }
            }
        }
        
        $db->commit();
        logAuditAction('kapazitaeten_geaendert', 'Raumkapazitäten pro Zeitslot aktualisiert');
        $message = ['type' => 'success', 'text' => 'Kapazitäten erfolgreich gespeichert'];
    } catch (Exception $e) {
        $db->rollBack();
        $message = ['type' => 'error', 'text' => 'Fehler beim Speichern: ' . $e->getMessage()];
    }
}

// Aktuelle Kapazitäten laden
$capacities = [];
$stmt = $db->query("SELECT * FROM room_slot_capacities");
foreach ($stmt->fetchAll() as $cap) {
    $capacities[$cap['room_id']][$cap['timeslot_id']] = $cap['capacity'];
}
?>

<div class="space-y-6">
    <?php if (isset($message)): ?>
    <div class="animate-pulse">
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

    <!-- Header -->
    <div class="bg-white rounded-xl p-6 border-l-4 border-indigo-600">
        <div>
            <h2 class="text-2xl font-bold text-gray-800 mb-2">
                <i class="fas fa-table text-indigo-600 mr-3"></i>
                Raumkapazitäten pro Zeitslot
            </h2>
            <p class="text-gray-600">Definiere die individuelle Kapazitäten für jeden Raum und Zeitslot</p>
        </div>
    </div>

    <!-- Info Box -->
    <div class="bg-blue-50 border-l-4 border-blue-500 p-6 rounded-lg">
        <div class="flex items-start">
            <i class="fas fa-info-circle text-blue-500 text-xl mr-3 mt-1"></i>
            <div>
                <h3 class="font-bold text-blue-900 mb-2">Hinweise zur Nutzung</h3>
                <ul class="text-sm text-blue-800 space-y-1">
                    <li><i class="fas fa-check mr-2"></i>Die Standardkapazität entspricht der vollen Raumkapazität für jeden Slot</li>
                    <li><i class="fas fa-check mr-2"></i>Du kannst für jeden Slot individuelle Werte festlegen</li>
                    <li><i class="fas fa-check mr-2"></i>Leere Felder verwenden die Standardkapazität</li>
                    <li><i class="fas fa-check mr-2"></i>Diese Einstellungen beeinflussen die automatische Zuteilung</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Capacity Form -->
    <form method="POST" class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase border-b-2 border-gray-300 sticky left-0 bg-gray-100 z-10">
                                Raum
                            </th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase border-b-2 border-gray-300">
                                Standard<br>Kapazität
                            </th>
                            <?php foreach ($timeslots as $slot): ?>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase border-b-2 border-gray-300">
                                    <?php echo htmlspecialchars($slot['slot_name']); ?><br>
                                    <span class="text-xs font-normal"><?php echo date('H:i', strtotime($slot['start_time'])); ?>-<?php echo date('H:i', strtotime($slot['end_time'])); ?></span>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rooms as $room): 
                            $defaultCapacity = $room['capacity'] ? floor(intval($room['capacity']) / DEFAULT_CAPACITY_DIVISOR) : 0;
                        ?>
                        <tr class="border-b border-gray-200 hover:bg-gray-50">
                            <td class="px-4 py-3 font-semibold text-gray-800 sticky left-0 bg-white hover:bg-gray-50 border-r border-gray-200">
                                <div class="flex items-center">
                                    <i class="fas fa-door-open text-indigo-600 mr-2"></i>
                                    <div>
                                        <div><?php echo htmlspecialchars($room['room_number']); ?></div>
                                        <?php if ($room['room_name']): ?>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($room['room_name']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-block px-3 py-1 bg-gray-100 text-gray-800 rounded-full font-semibold">
                                    <?php echo $defaultCapacity; ?>
                                </span>
                            </td>
                            <?php foreach ($timeslots as $slot): 
                                $currentValue = $capacities[$room['id']][$slot['id']] ?? '';
                            ?>
                                <td class="px-4 py-3 text-center">
                                    <input type="number" 
                                           name="capacity[<?php echo $room['id']; ?>][<?php echo $slot['id']; ?>]" 
                                           value="<?php echo $currentValue; ?>"
                                           placeholder="<?php echo $defaultCapacity; ?>"
                                           min="0" 
                                           class="w-20 px-3 py-2 border border-gray-300 rounded-lg text-center focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Actions -->
        <div class="bg-gray-50 px-6 py-4 border-t border-gray-200 flex justify-between items-center">
            <div class="text-sm text-gray-600">
                <i class="fas fa-info-circle mr-2"></i>
                Änderungen werden sofort auf die automatische Zuteilung angewendet
            </div>
            <div class="flex gap-3">
                <button type="button" 
                        onclick="resetToDefaults()"
                        class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-semibold">
                    <i class="fas fa-undo mr-2"></i>Auf Standard zurücksetzen
                </button>
                <button type="submit" 
                        name="save_capacities"
                        class="px-6 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition font-semibold shadow-lg">
                    <i class="fas fa-save mr-2"></i>Speichern
                </button>
            </div>
        </div>
    </form>

    <!-- Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-sm mb-1">Räume gesamt</p>
                    <p class="text-3xl font-bold"><?php echo count($rooms); ?></p>
                </div>
                <i class="fas fa-door-open text-3xl opacity-80"></i>
            </div>
        </div>

        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-100 text-sm mb-1">Zeitslots</p>
                    <p class="text-3xl font-bold"><?php echo count($timeslots); ?></p>
                </div>
                <i class="fas fa-clock text-3xl opacity-80"></i>
            </div>
        </div>

        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-purple-100 text-sm mb-1">Konfigurationen</p>
                    <p class="text-3xl font-bold"><?php echo count($capacities); ?></p>
                </div>
                <i class="fas fa-cog text-3xl opacity-80"></i>
            </div>
        </div>
    </div>
</div>

<script>
function resetToDefaults() {
    if (confirm('Möchtest Du wirklich alle Werte auf die Standardwerte zurücksetzen?')) {
        document.querySelectorAll('input[type="number"]').forEach(input => {
            input.value = '';
        });
    }
}

// Auto-save Warnung bei Verlassen
let formChanged = false;
document.querySelectorAll('input[type="number"]').forEach(input => {
    input.addEventListener('change', () => {
        formChanged = true;
    });
});

window.addEventListener('beforeunload', (e) => {
    if (formChanged) {
        e.preventDefault();
        e.returnValue = '';
    }
});

document.querySelector('form').addEventListener('submit', () => {
    formChanged = false;
});
</script>
