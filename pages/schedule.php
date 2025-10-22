<?php
// Zeitplan/Tagesablauf für Schüler - Timeline Ansicht

// Registrierungen des Benutzers mit allen Informationen abrufen
$stmt = $db->prepare("
    SELECT 
        r.*,
        e.name as exhibitor_name,
        e.short_description,
        e.room_id,
        rm.room_number,
        rm.room_name,
        rm.building,
        rm.floor,
        t.slot_number,
        t.slot_name,
        t.start_time,
        t.end_time
    FROM registrations r
    JOIN exhibitors e ON r.exhibitor_id = e.id
    JOIN timeslots t ON r.timeslot_id = t.id
    LEFT JOIN rooms rm ON e.room_id = rm.id
    WHERE r.user_id = ?
    ORDER BY t.slot_number ASC
");
$stmt->execute([$_SESSION['user_id']]);
$registrations = $stmt->fetchAll();

// Tagesablauf mit Pausen definieren
$timeline = [
    ['time' => '08:45', 'type' => 'arrival', 'label' => 'Ankunft & Begrüßung', 'icon' => 'fa-door-open', 'color' => 'gray'],
    ['time' => '09:00', 'type' => 'slot', 'slot_number' => 1, 'label' => 'Slot 1 (Feste Zuteilung)', 'icon' => 'fa-clipboard-check', 'color' => 'blue', 'assigned' => true],
    ['time' => '09:30', 'type' => 'break', 'label' => 'Kurze Pause', 'icon' => 'fa-exchange-alt', 'color' => 'green', 'duration' => '10 Min.', 'description' => 'Austausch & Ausstellersuche'],
    ['time' => '09:40', 'type' => 'slot', 'slot_number' => 2, 'label' => 'Slot 2 (Freie Wahl)', 'icon' => 'fa-hand-pointer', 'color' => 'purple', 'assigned' => false],
    ['time' => '10:10', 'type' => 'break', 'label' => 'Essenspause', 'icon' => 'fa-utensils', 'color' => 'orange', 'duration' => '30 Min.', 'description' => 'Zeit für Speisen & Getränke'],
    ['time' => '10:40', 'type' => 'slot', 'slot_number' => 3, 'label' => 'Slot 3 (Feste Zuteilung)', 'icon' => 'fa-clipboard-check', 'color' => 'blue', 'assigned' => true],
    ['time' => '11:10', 'type' => 'break', 'label' => 'Kurze Pause', 'icon' => 'fa-exchange-alt', 'color' => 'green', 'duration' => '10 Min.', 'description' => 'Austausch & Ausstellersuche'],
    ['time' => '11:20', 'type' => 'slot', 'slot_number' => 4, 'label' => 'Slot 4 (Freie Wahl)', 'icon' => 'fa-hand-pointer', 'color' => 'purple', 'assigned' => false],
    ['time' => '11:50', 'type' => 'break', 'label' => 'Essenspause', 'icon' => 'fa-utensils', 'color' => 'orange', 'duration' => '30 Min.', 'description' => 'Zeit für Speisen & Getränke'],
    ['time' => '12:20', 'type' => 'slot', 'slot_number' => 5, 'label' => 'Slot 5 (Feste Zuteilung)', 'icon' => 'fa-clipboard-check', 'color' => 'blue', 'assigned' => true],
    ['time' => '12:50', 'type' => 'end', 'label' => 'Ende & Verabschiedung', 'icon' => 'fa-flag-checkered', 'color' => 'gray']
];

// Registrierungen nach Slot-Nummer gruppieren
$registrationsBySlot = [];
foreach ($registrations as $reg) {
    $registrationsBySlot[$reg['slot_number']] = $reg;
}
?>

<div class="space-y-6 max-w-4xl mx-auto">
    <!-- Header -->
    <div class="bg-gradient-to-r from-blue-600 to-blue-700 rounded-xl p-6 text-white">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h2 class="text-3xl font-bold mb-2">
                    <i class="fas fa-calendar-day mr-3"></i>
                    Mein Tagesablauf
                </h2>
                <p class="text-blue-100">Dein persönlicher Zeitplan für die Berufsmesse</p>
            </div>
            <div class="text-right bg-white/20 rounded-lg px-6 py-4">
                <div class="text-4xl font-bold">
                    <?php echo count($registrations); ?>/3
                </div>
                <div class="text-sm text-blue-100">Feste Zuteilungen</div>
                <div class="text-xs text-blue-200 mt-1">+ 2 freie Slots vor Ort</div>
            </div>
        </div>
    </div>

    <?php if (count($registrations) === 0): ?>
    <!-- No Registrations Message -->
    <div class="bg-yellow-50 border-l-4 border-yellow-500 rounded-lg p-6">
        <div class="flex items-start">
            <i class="fas fa-exclamation-triangle text-yellow-500 text-3xl mr-4"></i>
            <div>
                <h3 class="font-bold text-yellow-800 text-lg mb-2">Noch keine festen Zuteilungen</h3>
                <p class="text-yellow-700 mb-4">
                    Du hast noch keine Zuteilungen für die Slots 1, 3 und 5. Diese werden entweder durch deine eigene Anmeldung oder durch die automatische Zuteilung eines Administrators vergeben.
                </p>
                <p class="text-yellow-700 mb-4">
                    <strong>Wichtig:</strong> Die Slots 2 und 4 sind zur freien Wahl vor Ort und werden nicht im System verwaltet.
                </p>
                <a href="?page=registration" class="inline-flex items-center px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition font-semibold">
                    <i class="fas fa-clipboard-list mr-2"></i>
                    Zur Anmeldung
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Timeline -->
    <div class="bg-white rounded-xl p-6 border-l-4 border-blue-600">
        <h3 class="text-xl font-bold text-gray-800 mb-6 flex items-center">
            <i class="fas fa-clock text-blue-600 mr-3"></i>
            Tagesablauf
        </h3>

        <div class="relative">
            <!-- Vertical Line -->
            <div class="absolute left-8 top-0 bottom-0 w-1 bg-gray-200"></div>

            <!-- Timeline Items -->
            <div class="space-y-6">
                <?php foreach ($timeline as $index => $item): 
                    $isSlot = $item['type'] === 'slot';
                    $registration = $isSlot && isset($registrationsBySlot[$item['slot_number']]) 
                        ? $registrationsBySlot[$item['slot_number']] 
                        : null;
                    
                    // Color mapping
                    $colorMap = [
                        'blue' => ['bg' => 'bg-blue-500', 'light' => 'bg-blue-50', 'text' => 'text-blue-800', 'border' => 'border-blue-500'],
                        'purple' => ['bg' => 'bg-purple-500', 'light' => 'bg-purple-50', 'text' => 'text-purple-800', 'border' => 'border-purple-500'],
                        'red' => ['bg' => 'bg-red-500', 'light' => 'bg-red-50', 'text' => 'text-red-800', 'border' => 'border-red-500'],
                        'green' => ['bg' => 'bg-green-500', 'light' => 'bg-green-50', 'text' => 'text-green-800', 'border' => 'border-green-500'],
                        'orange' => ['bg' => 'bg-orange-500', 'light' => 'bg-orange-50', 'text' => 'text-orange-800', 'border' => 'border-orange-500'],
                        'gray' => ['bg' => 'bg-gray-500', 'light' => 'bg-gray-50', 'text' => 'text-gray-800', 'border' => 'border-gray-500']
                    ];
                    $colors = $colorMap[$item['color']];
                ?>

                <div class="relative pl-20">
                    <!-- Time Badge -->
                    <div class="absolute left-0 top-0 flex items-center">
                        <div class="w-16 h-16 <?php echo $colors['bg']; ?> rounded-full flex items-center justify-center text-white shadow-lg relative z-10">
                            <i class="fas <?php echo $item['icon']; ?> text-xl"></i>
                        </div>
                    </div>

                    <!-- Content Card -->
                    <div class="<?php echo $colors['light']; ?> border-2 <?php echo $colors['border']; ?> rounded-xl p-5">
                        <!-- Time and Title -->
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <span class="text-2xl font-bold <?php echo $colors['text']; ?>"><?php echo $item['time']; ?> Uhr</span>
                                <?php if (isset($item['duration'])): ?>
                                    <span class="ml-3 text-sm <?php echo $colors['text']; ?> opacity-75">(<?php echo $item['duration']; ?>)</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <h4 class="text-xl font-bold text-gray-800 mb-3">
                            <?php echo $item['label']; ?>
                        </h4>

                        <?php if ($isSlot): ?>
                            <?php if (isset($item['assigned']) && $item['assigned'] === false): ?>
                                <!-- Free Choice Slot -->
                                <div class="bg-white border-2 border-dashed <?php echo $colors['border']; ?> rounded-lg p-6 text-center">
                                    <div class="mb-4">
                                        <i class="fas fa-hand-pointer text-5xl <?php echo $colors['text']; ?> mb-3"></i>
                                    </div>
                                    <h5 class="text-lg font-bold text-gray-900 mb-2">Freie Auswahl vor Ort</h5>
                                    <p class="text-gray-700 mb-3">
                                        Dieser Zeitslot ist <strong>nicht fest zugeteilt</strong>. Du kannst spontan vor Ort entscheiden, welchen Aussteller du besuchen möchtest.
                                    </p>
                                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-sm text-blue-800">
                                        <i class="fas fa-info-circle mr-2"></i>
                                        <strong>Tipp:</strong> Nutze die 10-minütigen Pausen davor zum Austausch und zur Ausstellersuche!
                                    </div>
                                </div>

                            <?php elseif ($registration): ?>
                                <!-- Slot with Registration -->
                                <div class="space-y-4">
                                    <!-- Exhibitor -->
                                    <div class="bg-white rounded-lg p-4 border <?php echo $colors['border']; ?>">
                                        <div class="flex items-start mb-2">
                                            <i class="fas fa-building <?php echo $colors['text']; ?> mr-3 mt-1 text-xl"></i>
                                            <div class="flex-1">
                                                <h5 class="font-bold text-gray-900 text-lg mb-1">
                                                    <?php echo htmlspecialchars($registration['exhibitor_name']); ?>
                                                </h5>
                                                <?php if ($registration['short_description']): ?>
                                                    <p class="text-sm text-gray-600">
                                                        <?php echo htmlspecialchars($registration['short_description']); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Room Info -->
                                    <?php if ($registration['room_id']): ?>
                                        <div class="bg-white rounded-lg p-4 border <?php echo $colors['border']; ?>">
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div class="flex items-center">
                                                    <div class="w-10 h-10 <?php echo $colors['light']; ?> <?php echo $colors['border']; ?> border-2 rounded-lg flex items-center justify-center mr-3">
                                                        <i class="fas fa-door-open <?php echo $colors['text']; ?>"></i>
                                                    </div>
                                                    <div>
                                                        <p class="text-xs text-gray-600">Raum</p>
                                                        <p class="font-bold text-gray-900">
                                                            <?php echo htmlspecialchars($registration['room_number']); ?>
                                                            <?php if ($registration['room_name']): ?>
                                                                <span class="text-sm font-normal text-gray-600">- <?php echo htmlspecialchars($registration['room_name']); ?></span>
                                                            <?php endif; ?>
                                                        </p>
                                                    </div>
                                                </div>
                                                <div class="flex items-center">
                                                    <div class="w-10 h-10 <?php echo $colors['light']; ?> <?php echo $colors['border']; ?> border-2 rounded-lg flex items-center justify-center mr-3">
                                                        <i class="fas fa-building <?php echo $colors['text']; ?>"></i>
                                                    </div>
                                                    <div>
                                                        <p class="text-xs text-gray-600">Gebäude</p>
                                                        <p class="font-bold text-gray-900">
                                                            <?php echo htmlspecialchars($registration['building'] ?: 'Nicht angegeben'); ?>
                                                            <?php if ($registration['floor']): ?>
                                                                <span class="text-sm font-normal text-gray-600">• <?php echo $registration['floor']; ?>. Stock</span>
                                                            <?php endif; ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="bg-yellow-50 border border-yellow-300 rounded-lg p-3">
                                            <div class="flex items-center text-yellow-800">
                                                <i class="fas fa-info-circle mr-2"></i>
                                                <span class="text-sm font-medium">Rauminformation wird noch bekannt gegeben</span>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Registration Type Badge -->
                                    <div class="flex items-center justify-between">
                                        <?php if ($registration['registration_type'] === 'automatic'): ?>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-orange-100 text-orange-800 border border-orange-300">
                                                <i class="fas fa-robot mr-2"></i>Automatisch zugeteilt
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800 border border-green-300">
                                                <i class="fas fa-user mr-2"></i>Manuell angemeldet
                                            </span>
                                        <?php endif; ?>
                                        <span class="text-sm text-gray-600">
                                            <i class="fas fa-clock mr-1"></i>
                                            <?php 
                                            $duration = (strtotime($registration['end_time']) - strtotime($registration['start_time'])) / 60;
                                            echo round($duration) . ' Min.';
                                            ?>
                                        </span>
                                    </div>
                                </div>

                            <?php else: ?>
                                <!-- Slot without Registration -->
                                <div class="bg-white border-2 border-dashed <?php echo $colors['border']; ?> rounded-lg p-4 text-center">
                                    <i class="fas fa-calendar-times text-4xl text-gray-300 mb-3"></i>
                                    <p class="text-gray-600 font-medium mb-2">Keine Anmeldung für diesen Zeitslot</p>
                                    <p class="text-sm text-gray-500">Warte auf die automatische Zuteilung oder melde dich selbst an.</p>
                                </div>
                            <?php endif; ?>

                        <?php elseif ($item['type'] === 'break'): ?>
                            <!-- Break -->
                            <div class="text-center py-3">
                                <div class="inline-flex items-center justify-center w-16 h-16 <?php echo $colors['light']; ?> rounded-full mb-3">
                                    <i class="fas <?php echo $item['icon']; ?> text-3xl <?php echo $colors['text']; ?>"></i>
                                </div>
                                <p class="text-gray-700 font-medium mb-1">
                                    <?php echo isset($item['description']) ? $item['description'] : 'Zeit für eine Pause'; ?>
                                </p>
                                <?php if (strpos($item['label'], 'Kurze') !== false): ?>
                                <p class="text-sm text-gray-600">
                                    <i class="fas fa-lightbulb text-yellow-500 mr-1"></i>
                                    Perfekt, um sich mit anderen auszutauschen und neue Aussteller zu finden
                                </p>
                                <?php endif; ?>
                            </div>

                        <?php else: ?>
                            <!-- Arrival / End -->
                            <div class="text-center py-2">
                                <p class="text-gray-600">
                                    <?php if ($item['type'] === 'arrival'): ?>
                                        Bitte finde dich pünktlich ein. Die Veranstaltung beginnt um 09:00 Uhr.
                                    <?php else: ?>
                                        Vielen Dank für deine Teilnahme! Wir hoffen, du hattest einen informativen Tag.
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Info Box -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Feste Zuteilungen -->
        <div class="bg-blue-50 border-l-4 border-blue-500 rounded-lg p-6">
            <h3 class="font-bold text-blue-900 text-lg mb-3 flex items-center">
                <i class="fas fa-clipboard-check text-blue-600 mr-2"></i>
                Feste Zuteilungen
            </h3>
            <p class="text-blue-800 mb-3">
                <strong>Slot 1, 3 und 5</strong> sind dir fest zugeteilt. Diese Termine findest du oben in deinem Zeitplan mit allen Details zu Aussteller, Raum und Zeitraum.
            </p>
            <ul class="space-y-2 text-blue-800 text-sm">
                <li class="flex items-start">
                    <i class="fas fa-arrow-right text-blue-600 mr-2 mt-1"></i>
                    <span>Sei pünktlich bei deinen zugeteilten Slots</span>
                </li>
                <li class="flex items-start">
                    <i class="fas fa-arrow-right text-blue-600 mr-2 mt-1"></i>
                    <span>Prüfe vorher Raum und Gebäude</span>
                </li>
            </ul>
        </div>

        <!-- Freie Wahl -->
        <div class="bg-purple-50 border-l-4 border-purple-500 rounded-lg p-6">
            <h3 class="font-bold text-purple-900 text-lg mb-3 flex items-center">
                <i class="fas fa-hand-pointer text-purple-600 mr-2"></i>
                Freie Wahl vor Ort
            </h3>
            <p class="text-purple-800 mb-3">
                <strong>Slot 2 und 4</strong> kannst du spontan vor Ort entscheiden. Diese Slots werden nicht im System verwaltet.
            </p>
            <ul class="space-y-2 text-purple-800 text-sm">
                <li class="flex items-start">
                    <i class="fas fa-arrow-right text-purple-600 mr-2 mt-1"></i>
                    <span>Nutze die 10-Min-Pausen zur Ausstellersuche</span>
                </li>
                <li class="flex items-start">
                    <i class="fas fa-arrow-right text-purple-600 mr-2 mt-1"></i>
                    <span>Tausche dich mit anderen aus</span>
                </li>
            </ul>
        </div>
    </div>

    <!-- Tipps -->
    <div class="bg-green-50 border-l-4 border-green-500 rounded-lg p-6">
        <h3 class="font-bold text-green-900 text-lg mb-3 flex items-center">
            <i class="fas fa-lightbulb text-green-600 mr-2"></i>
            Tipps für den Tag
        </h3>
        <ul class="space-y-2 text-green-800">
            <li class="flex items-start">
                <i class="fas fa-check-circle text-green-600 mr-3 mt-1"></i>
                <span><strong>10-Min-Pausen:</strong> Perfekt zum Austausch mit anderen und zur Orientierung</span>
            </li>
            <li class="flex items-start">
                <i class="fas fa-check-circle text-green-600 mr-3 mt-1"></i>
                <span><strong>30-Min-Pausen:</strong> Zeit für Essen, Trinken und Entspannung</span>
            </li>
            <li class="flex items-start">
                <i class="fas fa-check-circle text-green-600 mr-3 mt-1"></i>
                <span>Plane genug Zeit ein, um zwischen den Räumen zu wechseln</span>
            </li>
            <li class="flex items-start">
                <i class="fas fa-check-circle text-green-600 mr-3 mt-1"></i>
                <span>Bereite Fragen vor und bringe einen Notizblock mit</span>
            </li>
        </ul>
    </div>

    <!-- Action Buttons -->
    <div class="flex flex-wrap gap-3 justify-center">
        <button onclick="window.print()" class="px-6 py-3 bg-gray-800 text-white rounded-lg hover:bg-gray-900 transition font-semibold flex items-center">
            <i class="fas fa-print mr-2"></i>
            Zeitplan drucken
        </button>
        <?php if (count($registrations) < 3): ?>
        <a href="?page=registration" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold flex items-center">
            <i class="fas fa-plus mr-2"></i>
            Weitere Anmeldungen (<?php echo count($registrations); ?>/3)
        </a>
        <?php endif; ?>
        <a href="?page=exhibitors" class="px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition font-semibold flex items-center">
            <i class="fas fa-search mr-2"></i>
            Aussteller entdecken
        </a>
    </div>
</div>

<style>
@media print {
    .sidebar, .mobile-menu-button, button, nav, header {
        display: none !important;
    }
    
    body {
        margin: 0 !important;
        padding: 20px !important;
    }
    
    main {
        margin-left: 0 !important;
    }
    
    @page {
        margin: 1.5cm;
    }
}
</style>
