<?php
// Zeitplan/Tagesablauf für Schüler - Premium Timeline Design

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

// Tagesablauf definieren
$timeline = [
    ['time' => '08:45', 'end' => '09:00', 'type' => 'arrival', 'label' => 'Ankunft & Begrüßung', 'icon' => 'fa-door-open', 'color' => 'gray', 'gradient' => 'from-gray-400 to-gray-500'],
    ['time' => '09:00', 'end' => '09:30', 'type' => 'slot', 'slot_number' => 1, 'label' => 'Slot 1', 'sublabel' => 'Feste Zuteilung', 'icon' => 'fa-clipboard-check', 'color' => 'blue', 'gradient' => 'from-blue-500 to-blue-600', 'assigned' => true],
    ['time' => '09:30', 'end' => '09:40', 'type' => 'break', 'label' => 'Kurze Pause', 'icon' => 'fa-coffee', 'color' => 'green', 'gradient' => 'from-emerald-400 to-emerald-500', 'description' => 'Austausch & Ausstellersuche'],
    ['time' => '09:40', 'end' => '10:10', 'type' => 'slot', 'slot_number' => 2, 'label' => 'Slot 2', 'sublabel' => 'Freie Wahl', 'icon' => 'fa-hand-pointer', 'color' => 'purple', 'gradient' => 'from-purple-500 to-purple-600', 'assigned' => false],
    ['time' => '10:10', 'end' => '10:40', 'type' => 'break', 'label' => 'Essenspause', 'icon' => 'fa-utensils', 'color' => 'orange', 'gradient' => 'from-orange-400 to-orange-500', 'description' => 'Zeit für Speisen & Getränke'],
    ['time' => '10:40', 'end' => '11:10', 'type' => 'slot', 'slot_number' => 3, 'label' => 'Slot 3', 'sublabel' => 'Feste Zuteilung', 'icon' => 'fa-clipboard-check', 'color' => 'blue', 'gradient' => 'from-blue-500 to-blue-600', 'assigned' => true],
    ['time' => '11:10', 'end' => '11:20', 'type' => 'break', 'label' => 'Kurze Pause', 'icon' => 'fa-coffee', 'color' => 'green', 'gradient' => 'from-emerald-400 to-emerald-500', 'description' => 'Austausch & Ausstellersuche'],
    ['time' => '11:20', 'end' => '11:50', 'type' => 'slot', 'slot_number' => 4, 'label' => 'Slot 4', 'sublabel' => 'Freie Wahl', 'icon' => 'fa-hand-pointer', 'color' => 'purple', 'gradient' => 'from-purple-500 to-purple-600', 'assigned' => false],
    ['time' => '11:50', 'end' => '12:20', 'type' => 'break', 'label' => 'Essenspause', 'icon' => 'fa-utensils', 'color' => 'orange', 'gradient' => 'from-orange-400 to-orange-500', 'description' => 'Zeit für Speisen & Getränke'],
    ['time' => '12:20', 'end' => '12:50', 'type' => 'slot', 'slot_number' => 5, 'label' => 'Slot 5', 'sublabel' => 'Feste Zuteilung', 'icon' => 'fa-clipboard-check', 'color' => 'blue', 'gradient' => 'from-blue-500 to-blue-600', 'assigned' => true],
    ['time' => '12:50', 'end' => '13:00', 'type' => 'end', 'label' => 'Ende & Verabschiedung', 'icon' => 'fa-flag-checkered', 'color' => 'gray', 'gradient' => 'from-gray-400 to-gray-500']
];

// Registrierungen nach Slot-Nummer gruppieren
$registrationsBySlot = [];
foreach ($registrations as $reg) {
    $registrationsBySlot[$reg['slot_number']] = $reg;
}
?>

<div class="max-w-5xl mx-auto space-y-8">
    <!-- Page Header - Hero Section -->
    <div class="relative overflow-hidden bg-gradient-to-br from-blue-600 via-purple-600 to-pink-600 rounded-3xl p-8 text-white shadow-2xl">
        <div class="absolute inset-0 bg-black/10"></div>
        <div class="absolute top-0 right-0 w-96 h-96 bg-white/10 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2"></div>
        <div class="absolute bottom-0 left-0 w-64 h-64 bg-white/10 rounded-full blur-3xl translate-y-1/2 -translate-x-1/2"></div>
        
        <div class="relative">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                <div>
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-16 h-16 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center shadow-lg">
                            <i class="fas fa-calendar-alt text-3xl"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl font-extrabold font-display">Dein Zeitplan</h1>
                            <p class="text-white/80">Persönlicher Tagesablauf für die Berufsmesse</p>
                        </div>
                    </div>
                </div>
                
                <div class="flex items-center gap-4">
                    <div class="text-right">
                        <div class="text-sm text-white/70 font-medium">Veranstaltungsdatum</div>
                        <div class="text-xl font-bold"><?php echo formatDate(date('Y-m-d')); ?></div>
                    </div>
                    <button onclick="window.print()" class="px-6 py-3 bg-white/20 backdrop-blur-sm hover:bg-white/30 rounded-2xl font-semibold flex items-center gap-2 transition-all duration-300 hover:scale-105">
                        <i class="fas fa-print"></i>
                        <span>Drucken</span>
                    </button>
                </div>
            </div>
            
            <!-- Quick Stats -->
            <div class="grid grid-cols-3 gap-4 mt-8">
                <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-4 text-center">
                    <div class="text-3xl font-bold"><?php echo count($registrations); ?></div>
                    <div class="text-sm text-white/70">Termine gebucht</div>
                </div>
                <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-4 text-center">
                    <div class="text-3xl font-bold">5</div>
                    <div class="text-sm text-white/70">Zeitslots</div>
                </div>
                <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-4 text-center">
                    <div class="text-3xl font-bold">4:15</div>
                    <div class="text-sm text-white/70">Stunden</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Timeline -->
    <div class="bg-white rounded-3xl shadow-xl border border-gray-100 overflow-hidden">
        <div class="p-6 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
            <h2 class="text-xl font-bold text-gray-800 flex items-center gap-3">
                <i class="fas fa-stream text-primary-500"></i>
                Tagesablauf
            </h2>
        </div>
        
        <div class="p-6">
            <div class="relative">
                <!-- Timeline Line -->
                <div class="absolute left-8 top-0 bottom-0 w-0.5 bg-gradient-to-b from-gray-200 via-primary-300 to-gray-200"></div>
                
                <!-- Timeline Items -->
                <div class="space-y-6">
                    <?php foreach ($timeline as $index => $item): 
                        $hasRegistration = isset($item['slot_number']) && isset($registrationsBySlot[$item['slot_number']]);
                        $reg = $hasRegistration ? $registrationsBySlot[$item['slot_number']] : null;
                    ?>
                    <div class="relative flex gap-6 group" style="animation-delay: <?php echo $index * 100; ?>ms;">
                        <!-- Timeline Marker -->
                        <div class="relative z-10 flex-shrink-0">
                            <div class="w-16 h-16 rounded-2xl bg-gradient-to-br <?php echo $item['gradient']; ?> flex items-center justify-center shadow-lg group-hover:scale-110 group-hover:shadow-xl transition-all duration-300">
                                <i class="fas <?php echo $item['icon']; ?> text-white text-xl"></i>
                            </div>
                            <?php if ($hasRegistration): ?>
                            <div class="absolute -top-1 -right-1 w-6 h-6 bg-primary-500 rounded-full flex items-center justify-center border-2 border-white shadow-lg">
                                <i class="fas fa-check text-white text-xs"></i>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Content Card -->
                        <div class="flex-1 bg-gradient-to-br from-white to-gray-50 rounded-2xl border border-gray-100 p-5 group-hover:border-primary-200 group-hover:shadow-lg transition-all duration-300 <?php echo $hasRegistration ? 'ring-2 ring-primary-100' : ''; ?>">
                            <!-- Time Badge -->
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center gap-3">
                                    <span class="px-3 py-1 bg-gradient-to-r <?php echo $item['gradient']; ?> text-white text-sm font-bold rounded-lg shadow-sm">
                                        <?php echo $item['time']; ?>
                                    </span>
                                    <span class="text-gray-400 text-sm">→</span>
                                    <span class="text-gray-500 text-sm font-medium"><?php echo $item['end']; ?></span>
                                </div>
                                <?php if (isset($item['sublabel'])): ?>
                                <span class="px-3 py-1 bg-gray-100 text-gray-600 text-xs font-semibold rounded-lg">
                                    <?php echo $item['sublabel']; ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Title -->
                            <h3 class="text-lg font-bold text-gray-800 mb-2"><?php echo $item['label']; ?></h3>
                            
                            <?php if ($item['type'] === 'break'): ?>
                                <p class="text-gray-500 text-sm flex items-center gap-2">
                                    <i class="fas fa-info-circle text-gray-400"></i>
                                    <?php echo $item['description']; ?>
                                </p>
                            <?php elseif ($item['type'] === 'slot'): ?>
                                <?php if ($hasRegistration): ?>
                                    <!-- Registration Info Card -->
                                    <div class="mt-4 p-4 bg-gradient-to-r from-primary-50 to-emerald-50 rounded-xl border border-primary-100">
                                        <div class="flex items-start gap-4">
                                            <div class="w-12 h-12 bg-white rounded-xl flex items-center justify-center shadow-sm border border-primary-100">
                                                <i class="fas fa-building text-primary-500"></i>
                                            </div>
                                            <div class="flex-1">
                                                <h4 class="font-bold text-gray-800 mb-1"><?php echo htmlspecialchars($reg['exhibitor_name']); ?></h4>
                                                <div class="flex flex-wrap items-center gap-3 text-sm text-gray-600">
                                                    <span class="flex items-center gap-1.5 px-2 py-1 bg-white rounded-lg">
                                                        <i class="fas fa-map-marker-alt text-red-400"></i>
                                                        <?php echo htmlspecialchars($reg['room_name'] ?? $reg['room_number'] ?? 'TBA'); ?>
                                                    </span>
                                                    <?php if ($reg['building']): ?>
                                                    <span class="flex items-center gap-1.5 px-2 py-1 bg-white rounded-lg">
                                                        <i class="fas fa-building text-blue-400"></i>
                                                        <?php echo htmlspecialchars($reg['building']); ?>
                                                    </span>
                                                    <?php endif; ?>
                                                    <?php if ($reg['floor']): ?>
                                                    <span class="flex items-center gap-1.5 px-2 py-1 bg-white rounded-lg">
                                                        <i class="fas fa-layer-group text-purple-400"></i>
                                                        <?php echo htmlspecialchars($reg['floor']); ?>. Etage
                                                    </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <a href="?page=exhibitors&id=<?php echo $reg['exhibitor_id']; ?>" class="w-10 h-10 bg-white hover:bg-primary-50 rounded-xl flex items-center justify-center shadow-sm border border-gray-100 hover:border-primary-200 transition-all duration-200" title="Details anzeigen">
                                                <i class="fas fa-arrow-right text-gray-400 hover:text-primary-500"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php elseif ($item['assigned']): ?>
                                    <div class="mt-3 p-3 bg-amber-50 rounded-xl border border-amber-100 text-amber-700 text-sm flex items-center gap-2">
                                        <i class="fas fa-clock"></i>
                                        <span>Noch keine Zuteilung erhalten. Bitte warten Sie auf die automatische Zuweisung.</span>
                                    </div>
                                <?php else: ?>
                                    <div class="mt-3 p-3 bg-purple-50 rounded-xl border border-purple-100 text-purple-700 text-sm flex items-center gap-2">
                                        <i class="fas fa-walking"></i>
                                        <span>Freie Wahl vor Ort - Besuche einen Aussteller deiner Wahl!</span>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Legend Card -->
    <div class="bg-white rounded-3xl shadow-lg border border-gray-100 p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-info-circle text-blue-500"></i>
            Legende
        </h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center shadow-md">
                    <i class="fas fa-clipboard-check text-white text-sm"></i>
                </div>
                <span class="text-sm text-gray-600">Feste Zuteilung</span>
            </div>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-500 to-purple-600 flex items-center justify-center shadow-md">
                    <i class="fas fa-hand-pointer text-white text-sm"></i>
                </div>
                <span class="text-sm text-gray-600">Freie Wahl</span>
            </div>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-500 flex items-center justify-center shadow-md">
                    <i class="fas fa-coffee text-white text-sm"></i>
                </div>
                <span class="text-sm text-gray-600">Pause</span>
            </div>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-orange-400 to-orange-500 flex items-center justify-center shadow-md">
                    <i class="fas fa-utensils text-white text-sm"></i>
                </div>
                <span class="text-sm text-gray-600">Essenspause</span>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .sidebar, .mobile-menu-button, button:not(.print-btn), nav, header, .no-print {
        display: none !important;
    }
    
    body {
        margin: 0 !important;
        padding: 20px !important;
        background: white !important;
    }
    
    main {
        margin-left: 0 !important;
    }
    
    .bg-gradient-to-br {
        background: #f3f4f6 !important;
        color: #1f2937 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    @page {
        margin: 1.5cm;
    }
}

/* Timeline Animation */
@keyframes slideInLeft {
    from {
        opacity: 0;
        transform: translateX(-30px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.group {
    animation: slideInLeft 0.5s ease-out forwards;
    opacity: 0;
}
</style>
