<?php
// Admin Dashboard - Premium Command Center Design

// Auto-Assign Ergebnis anzeigen
$autoAssignMessage = null;
if (isset($_GET['auto_assign']) && $_GET['auto_assign'] === 'done') {
    if (isset($_SESSION['auto_assign_success'])) {
        $autoAssignMessage = [
            'type' => 'success',
            'count' => $_SESSION['auto_assign_count'],
            'students' => $_SESSION['auto_assign_students'],
            'errors' => $_SESSION['auto_assign_errors'] ?? []
        ];
        unset($_SESSION['auto_assign_success'], $_SESSION['auto_assign_count'], $_SESSION['auto_assign_students'], $_SESSION['auto_assign_errors']);
    } elseif (isset($_SESSION['auto_assign_error'])) {
        $autoAssignMessage = [
            'type' => 'error',
            'message' => $_SESSION['auto_assign_error']
        ];
        unset($_SESSION['auto_assign_error']);
    }
}

// Statistiken berechnen
$stats = [];

// Gesamtzahl Schüler
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'");
$stats['total_students'] = $stmt->fetch()['count'];

// Gesamtzahl Aussteller
$stmt = $db->query("SELECT COUNT(*) as count FROM exhibitors WHERE active = 1");
$stats['total_exhibitors'] = $stmt->fetch()['count'];

// Gesamtzahl Anmeldungen
$stmt = $db->query("SELECT COUNT(*) as count FROM registrations");
$stats['total_registrations'] = $stmt->fetch()['count'];

// Schüler mit Anmeldungen
$stmt = $db->query("SELECT COUNT(DISTINCT user_id) as count FROM registrations");
$stats['students_registered'] = $stmt->fetch()['count'];

// Schüler ohne Anmeldungen
$stats['students_not_registered'] = $stats['total_students'] - $stats['students_registered'];

// Aussteller nach Beliebtheit
$stmt = $db->query("
    SELECT e.name, COUNT(r.id) as registrations
    FROM exhibitors e
    LEFT JOIN registrations r ON e.id = r.exhibitor_id
    WHERE e.active = 1
    GROUP BY e.id
    ORDER BY registrations DESC
    LIMIT 5
");
$topExhibitors = $stmt->fetchAll();

// Registrierungen pro Zeitslot
$stmt = $db->query("
    SELECT 
        t.slot_name, 
        t.slot_number, 
        t.start_time,
        t.end_time,
        COUNT(r.id) as registrations
    FROM timeslots t
    LEFT JOIN registrations r ON t.id = r.timeslot_id
    GROUP BY t.id, t.slot_name, t.slot_number, t.start_time, t.end_time
    ORDER BY t.slot_number ASC
");
$slotStats = $stmt->fetchAll();

// Letzte Registrierungen
$stmt = $db->query("
    SELECT r.*, u.firstname, u.lastname, e.name as exhibitor_name, t.slot_name
    FROM registrations r
    JOIN users u ON r.user_id = u.id
    JOIN exhibitors e ON r.exhibitor_id = e.id
    JOIN timeslots t ON r.timeslot_id = t.id
    ORDER BY r.registered_at DESC
    LIMIT 10
");
$recentRegistrations = $stmt->fetchAll();

// Calculate participation rate
$participationRate = $stats['total_students'] > 0 ? round(($stats['students_registered'] / $stats['total_students']) * 100) : 0;
?>

<div class="space-y-8">
    <!-- Hero Header -->
    <div class="relative overflow-hidden bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 rounded-3xl p-8 text-white shadow-2xl">
        <div class="absolute inset-0 bg-[url('data:image/svg+xml,%3Csvg width=\"60\" height=\"60\" viewBox=\"0 0 60 60\" xmlns=\"http://www.w3.org/2000/svg\"%3E%3Cg fill=\"none\" fill-rule=\"evenodd\"%3E%3Cg fill=\"%23ffffff\" fill-opacity=\"0.03\"%3E%3Cpath d=\"M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\"/%3E%3C/g%3E%3C/g%3E%3C/svg%3E')] opacity-50"></div>
        <div class="absolute top-0 right-0 w-96 h-96 bg-primary-500/20 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2"></div>
        <div class="absolute bottom-0 left-0 w-72 h-72 bg-purple-500/20 rounded-full blur-3xl translate-y-1/2 -translate-x-1/2"></div>
        
        <div class="relative">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                <div>
                    <div class="flex items-center gap-4 mb-2">
                        <div class="w-14 h-14 bg-gradient-to-br from-primary-500 to-emerald-500 rounded-2xl flex items-center justify-center shadow-lg shadow-primary-500/30">
                            <i class="fas fa-tachometer-alt text-2xl"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl font-extrabold font-display">Admin Dashboard</h1>
                            <p class="text-white/60">Willkommen zurück, <?php echo htmlspecialchars($_SESSION['firstname'] ?? 'Admin'); ?>!</p>
                        </div>
                    </div>
                </div>
                
                <div class="flex items-center gap-3">
                    <span class="px-4 py-2 bg-white/10 backdrop-blur-sm rounded-xl text-sm font-medium flex items-center gap-2">
                        <span class="w-2 h-2 bg-primary-400 rounded-full animate-pulse"></span>
                        Live-Daten
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Auto-Assign Result Message -->
    <?php if ($autoAssignMessage): ?>
        <?php if ($autoAssignMessage['type'] === 'success'): ?>
            <div class="bg-gradient-to-r from-primary-50 to-emerald-50 border border-primary-200 p-6 rounded-2xl shadow-lg animate-slideUp">
                <div class="flex items-start gap-4">
                    <div class="w-14 h-14 bg-primary-100 rounded-2xl flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-check-circle text-primary-500 text-2xl"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-bold text-primary-800 text-lg mb-2">Automatische Zuteilung erfolgreich!</h3>
                        <p class="text-primary-700 mb-2">
                            <strong><?php echo $autoAssignMessage['count']; ?></strong> Anmeldungen wurden für 
                            <strong><?php echo $autoAssignMessage['students']; ?></strong> Schüler erstellt.
                        </p>
                        <?php if (!empty($autoAssignMessage['errors'])): ?>
                            <div class="mt-3 p-4 bg-amber-50 rounded-xl border border-amber-200">
                                <p class="text-sm font-semibold text-amber-800 mb-2">Hinweise:</p>
                                <ul class="text-xs text-amber-700 space-y-1">
                                    <?php foreach ($autoAssignMessage['errors'] as $error): ?>
                                        <li class="flex items-start gap-2">
                                            <i class="fas fa-info-circle mt-0.5"></i>
                                            <?php echo htmlspecialchars($error); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-gradient-to-r from-red-50 to-rose-50 border border-red-200 p-6 rounded-2xl shadow-lg animate-slideUp">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 bg-red-100 rounded-2xl flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-exclamation-circle text-red-500 text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-red-800 text-lg mb-1">Fehler bei der automatischen Zuteilung</h3>
                        <p class="text-red-700"><?php echo htmlspecialchars($autoAssignMessage['message']); ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
        <!-- Total Students -->
        <div class="group bg-white rounded-2xl shadow-lg border border-gray-100 p-6 hover:shadow-xl hover:border-blue-200 transition-all duration-300 overflow-hidden relative">
            <div class="absolute top-0 right-0 w-32 h-32 bg-blue-50 rounded-full -translate-y-1/2 translate-x-1/2 group-hover:scale-150 transition-transform duration-500"></div>
            <div class="relative">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-cyan-500 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-500/30 group-hover:scale-110 transition-transform duration-300">
                        <i class="fas fa-user-graduate text-white text-xl"></i>
                    </div>
                    <span class="text-3xl font-extrabold text-gray-900"><?php echo $stats['total_students']; ?></span>
                </div>
                <h3 class="text-gray-500 font-medium">Gesamt Schüler</h3>
            </div>
        </div>

        <!-- Total Exhibitors -->
        <div class="group bg-white rounded-2xl shadow-lg border border-gray-100 p-6 hover:shadow-xl hover:border-purple-200 transition-all duration-300 overflow-hidden relative">
            <div class="absolute top-0 right-0 w-32 h-32 bg-purple-50 rounded-full -translate-y-1/2 translate-x-1/2 group-hover:scale-150 transition-transform duration-500"></div>
            <div class="relative">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-14 h-14 bg-gradient-to-br from-purple-500 to-pink-500 rounded-2xl flex items-center justify-center shadow-lg shadow-purple-500/30 group-hover:scale-110 transition-transform duration-300">
                        <i class="fas fa-building text-white text-xl"></i>
                    </div>
                    <span class="text-3xl font-extrabold text-gray-900"><?php echo $stats['total_exhibitors']; ?></span>
                </div>
                <h3 class="text-gray-500 font-medium">Aktive Aussteller</h3>
            </div>
        </div>

        <!-- Registered Students -->
        <div class="group bg-white rounded-2xl shadow-lg border border-gray-100 p-6 hover:shadow-xl hover:border-primary-200 transition-all duration-300 overflow-hidden relative">
            <div class="absolute top-0 right-0 w-32 h-32 bg-primary-50 rounded-full -translate-y-1/2 translate-x-1/2 group-hover:scale-150 transition-transform duration-500"></div>
            <div class="relative">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-14 h-14 bg-gradient-to-br from-primary-500 to-emerald-500 rounded-2xl flex items-center justify-center shadow-lg shadow-primary-500/30 group-hover:scale-110 transition-transform duration-300">
                        <i class="fas fa-check-circle text-white text-xl"></i>
                    </div>
                    <span class="text-3xl font-extrabold text-gray-900"><?php echo $stats['students_registered']; ?></span>
                </div>
                <h3 class="text-gray-500 font-medium">Angemeldete Schüler</h3>
                <div class="mt-2 flex items-center gap-2">
                    <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-primary-500 to-emerald-500 rounded-full" style="width: <?php echo $participationRate; ?>%"></div>
                    </div>
                    <span class="text-xs font-bold text-primary-600"><?php echo $participationRate; ?>%</span>
                </div>
            </div>
        </div>

        <!-- Not Registered -->
        <div class="group bg-white rounded-2xl shadow-lg border border-gray-100 p-6 hover:shadow-xl hover:border-red-200 transition-all duration-300 overflow-hidden relative">
            <div class="absolute top-0 right-0 w-32 h-32 bg-red-50 rounded-full -translate-y-1/2 translate-x-1/2 group-hover:scale-150 transition-transform duration-500"></div>
            <div class="relative">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-14 h-14 bg-gradient-to-br from-red-500 to-rose-500 rounded-2xl flex items-center justify-center shadow-lg shadow-red-500/30 group-hover:scale-110 transition-transform duration-300">
                        <i class="fas fa-exclamation-triangle text-white text-xl"></i>
                    </div>
                    <span class="text-3xl font-extrabold text-gray-900"><?php echo $stats['students_not_registered']; ?></span>
                </div>
                <h3 class="text-gray-500 font-medium">Ohne Anmeldung</h3>
                <?php if ($stats['students_not_registered'] > 0): ?>
                <span class="inline-flex items-center mt-2 text-xs font-semibold text-red-600 bg-red-50 px-2 py-1 rounded-lg">
                    <i class="fas fa-arrow-right mr-1"></i> Zuteilung erforderlich
                </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <!-- Auto-Assignment Tool -->
        <div class="bg-gradient-to-br from-amber-50 to-orange-50 rounded-2xl border border-amber-100 p-6 shadow-lg hover:shadow-xl transition-all duration-300">
            <div class="flex items-start gap-5">
                <div class="w-16 h-16 bg-gradient-to-br from-amber-500 to-orange-500 rounded-2xl flex items-center justify-center shadow-lg shadow-amber-500/30 flex-shrink-0">
                    <i class="fas fa-magic text-white text-2xl"></i>
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-bold text-gray-800 mb-2">Automatische Zuteilung</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        Verteilt Schüler automatisch auf Aussteller mit den wenigsten Teilnehmern für gleichmäßige Gruppen.
                    </p>
                    <div class="flex flex-wrap gap-2 mb-4">
                        <span class="bg-white/80 backdrop-blur-sm px-3 py-1.5 rounded-lg text-xs font-medium text-gray-700 border border-amber-200">
                            <i class="fas fa-check text-primary-500 mr-1"></i>Slots 1, 3, 5
                        </span>
                        <span class="bg-white/80 backdrop-blur-sm px-3 py-1.5 rounded-lg text-xs font-medium text-gray-700 border border-amber-200">
                            <i class="fas fa-balance-scale text-blue-500 mr-1"></i>Gleichmäßig
                        </span>
                    </div>
                    <button onclick="runAutoAssign()" id="autoAssignBtn" 
                            class="px-5 py-2.5 bg-gradient-to-r from-amber-500 to-orange-500 text-white rounded-xl font-semibold hover:shadow-lg hover:scale-105 transition-all duration-300 flex items-center gap-2">
                        <i class="fas fa-play-circle"></i>
                        <span>Ausführen</span>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Print Tool -->
        <div class="bg-gradient-to-br from-indigo-50 to-purple-50 rounded-2xl border border-indigo-100 p-6 shadow-lg hover:shadow-xl transition-all duration-300">
            <div class="flex items-start gap-5">
                <div class="w-16 h-16 bg-gradient-to-br from-indigo-500 to-purple-500 rounded-2xl flex items-center justify-center shadow-lg shadow-indigo-500/30 flex-shrink-0">
                    <i class="fas fa-print text-white text-2xl"></i>
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-bold text-gray-800 mb-2">Pläne drucken</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        Erstellen Sie druckbare Übersichten für Gesamtpläne, Klassenpläne oder Raumpläne.
                    </p>
                    <a href="?page=admin-print" 
                       class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-indigo-500 to-purple-500 text-white rounded-xl font-semibold hover:shadow-lg hover:scale-105 transition-all duration-300">
                        <i class="fas fa-file-pdf"></i>
                        <span>Zur Druckansicht</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="bg-white rounded-3xl shadow-xl border border-gray-100 overflow-hidden">
        <div class="border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
            <nav class="flex -mb-px flex-wrap p-2 gap-2">
                <button onclick="switchTab('statistics')" id="tab-statistics" class="tab-button px-5 py-3 text-sm font-semibold rounded-xl bg-gradient-to-r from-primary-500 to-emerald-500 text-white shadow-lg transition-all duration-300">
                    <i class="fas fa-chart-bar mr-2"></i>Übersicht
                </button>
                <button onclick="switchTab('registrations')" id="tab-registrations" class="tab-button px-5 py-3 text-sm font-semibold rounded-xl text-gray-500 hover:bg-gray-100 transition-all duration-300">
                    <i class="fas fa-clipboard-list mr-2"></i>Letzte Anmeldungen
                </button>
            </nav>
        </div>

        <!-- Tab Content: Statistics -->
        <div id="tab-content-statistics" class="tab-content p-6">
            <!-- Charts Row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Top Exhibitors -->
                <div class="bg-gradient-to-br from-gray-50 to-white rounded-2xl border border-gray-100 p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-5 flex items-center gap-3">
                        <div class="w-10 h-10 bg-gradient-to-br from-amber-500 to-orange-500 rounded-xl flex items-center justify-center shadow-md">
                            <i class="fas fa-trophy text-white"></i>
                        </div>
                        Beliebteste Aussteller
                    </h3>
                    <div class="space-y-4">
                        <?php foreach ($topExhibitors as $index => $exhibitor): 
                            $maxReg = $topExhibitors[0]['registrations'] ?: 1;
                            $percentage = ($exhibitor['registrations'] / $maxReg) * 100;
                            $colors = [
                                0 => 'from-amber-500 to-orange-500',
                                1 => 'from-gray-400 to-gray-500',
                                2 => 'from-amber-700 to-amber-800',
                                3 => 'from-blue-400 to-blue-500',
                                4 => 'from-purple-400 to-purple-500'
                            ];
                            $color = $colors[$index] ?? 'from-gray-400 to-gray-500';
                        ?>
                        <div class="group">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-3 flex-1 min-w-0">
                                    <span class="w-8 h-8 bg-gradient-to-br <?php echo $color; ?> text-white rounded-lg flex items-center justify-center text-sm font-bold shadow-sm">
                                        <?php echo $index + 1; ?>
                                    </span>
                                    <span class="font-medium text-gray-700 truncate group-hover:text-gray-900 transition-colors">
                                        <?php echo htmlspecialchars($exhibitor['name']); ?>
                                    </span>
                                </div>
                                <span class="ml-3 px-3 py-1 bg-gray-100 rounded-lg font-bold text-gray-700 text-sm flex-shrink-0">
                                    <?php echo $exhibitor['registrations']; ?>
                                </span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2.5 overflow-hidden">
                                <div class="bg-gradient-to-r <?php echo $color; ?> h-full rounded-full transition-all duration-700 ease-out" 
                                     style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Slot Distribution -->
                <div class="bg-gradient-to-br from-gray-50 to-white rounded-2xl border border-gray-100 p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-5 flex items-center gap-3">
                        <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-cyan-500 rounded-xl flex items-center justify-center shadow-md">
                            <i class="fas fa-chart-pie text-white"></i>
                        </div>
                        Verteilung nach Zeitslot
                    </h3>
                    <p class="text-xs text-gray-500 mb-4 flex items-center gap-2 bg-blue-50 rounded-lg px-3 py-2">
                        <i class="fas fa-info-circle text-blue-500"></i>
                        Nur feste Zuteilungen (Slot 1, 3, 5)
                    </p>
                    <div class="space-y-4">
                        <?php 
                        // Nur Slots mit festen Zuteilungen (1, 3, 5)
                        $assignedSlots = array_filter($slotStats, function($s) { 
                            return in_array($s['slot_number'], [1, 3, 5]); 
                        });
                        $totalSlotRegs = array_sum(array_column($assignedSlots, 'registrations')) ?: 1;
                        
                        // Farben für die 3 festen Slots
                        $slotColors = [
                            1 => 'from-blue-500 to-cyan-500',
                            3 => 'from-primary-500 to-emerald-500',
                            5 => 'from-amber-500 to-orange-500'
                        ];
                        
                        $slotIndex = 1;
                        foreach ($assignedSlots as $slot): 
                            $percentage = $totalSlotRegs > 0 ? ($slot['registrations'] / $totalSlotRegs) * 100 : 0;
                            $colorClass = $slotColors[$slot['slot_number']] ?? 'from-gray-400 to-gray-500';
                        ?>
                        <div class="group">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-3">
                                    <span class="w-8 h-8 bg-gradient-to-br <?php echo $colorClass; ?> text-white rounded-lg flex items-center justify-center text-sm font-bold shadow-sm">
                                        <?php echo $slotIndex; ?>
                                    </span>
                                    <span class="font-medium text-gray-700">
                                        Slot <?php echo $slotIndex; ?>
                                        <?php if (!empty($slot['start_time']) && !empty($slot['end_time'])): ?>
                                            <span class="text-xs text-gray-400 font-normal ml-1">
                                                (<?php echo substr($slot['start_time'], 0, 5); ?> - <?php echo substr($slot['end_time'], 0, 5); ?>)
                                            </span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-bold text-gray-700"><?php echo $slot['registrations']; ?></span>
                                    <span class="px-2 py-0.5 bg-gray-100 rounded-lg text-xs text-gray-500"><?php echo round($percentage); ?>%</span>
                                </div>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2.5 overflow-hidden">
                                <div class="bg-gradient-to-r <?php echo $colorClass; ?> h-full rounded-full transition-all duration-700 ease-out" 
                                     style="width: <?php echo min($percentage, 100); ?>%"></div>
                            </div>
                        </div>
                        <?php 
                            $slotIndex++;
                        endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Content: Registrations -->
        <div id="tab-content-registrations" class="tab-content p-6 hidden">
            <h3 class="text-lg font-bold text-gray-800 mb-5 flex items-center gap-3">
                <div class="w-10 h-10 bg-gradient-to-br from-gray-600 to-gray-700 rounded-xl flex items-center justify-center shadow-md">
                    <i class="fas fa-clipboard-list text-white"></i>
                </div>
                Letzte Anmeldungen
            </h3>
            <div class="overflow-x-auto rounded-2xl border border-gray-100">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gradient-to-r from-gray-50 to-white">
                            <th class="text-left py-4 px-5 text-sm font-bold text-gray-700">Schüler</th>
                            <th class="text-left py-4 px-5 text-sm font-bold text-gray-700">Aussteller</th>
                            <th class="text-left py-4 px-5 text-sm font-bold text-gray-700">Zeitslot</th>
                            <th class="text-left py-4 px-5 text-sm font-bold text-gray-700">Typ</th>
                            <th class="text-left py-4 px-5 text-sm font-bold text-gray-700">Zeitpunkt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentRegistrations as $index => $reg): ?>
                        <tr class="border-t border-gray-100 hover:bg-gray-50/50 transition-colors" style="animation-delay: <?php echo $index * 50; ?>ms;">
                            <td class="py-4 px-5">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-gradient-to-br from-gray-200 to-gray-300 rounded-xl flex items-center justify-center shadow-sm">
                                        <span class="text-gray-700 font-bold text-sm">
                                            <?php echo strtoupper(substr($reg['firstname'], 0, 1) . substr($reg['lastname'], 0, 1)); ?>
                                        </span>
                                    </div>
                                    <span class="font-medium text-gray-800">
                                        <?php echo htmlspecialchars($reg['firstname'] . ' ' . $reg['lastname']); ?>
                                    </span>
                                </div>
                            </td>
                            <td class="py-4 px-5 text-gray-700 font-medium">
                                <?php echo htmlspecialchars($reg['exhibitor_name']); ?>
                            </td>
                            <td class="py-4 px-5">
                                <span class="inline-flex items-center px-3 py-1.5 rounded-xl text-xs font-semibold bg-gray-100 text-gray-700">
                                    <?php echo htmlspecialchars($reg['slot_name']); ?>
                                </span>
                            </td>
                            <td class="py-4 px-5">
                                <?php if ($reg['registration_type'] === 'automatic'): ?>
                                    <span class="inline-flex items-center px-3 py-1.5 rounded-xl text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-100">
                                        <i class="fas fa-robot mr-1.5"></i>Auto
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-3 py-1.5 rounded-xl text-xs font-semibold bg-primary-50 text-primary-700 border border-primary-100">
                                        <i class="fas fa-user mr-1.5"></i>Manuell
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-4 px-5 text-sm text-gray-500">
                                <?php echo formatDateTime($reg['registered_at']); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
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
</style>

<script>
// Tab Switching
function switchTab(tabName) {
    // Update buttons
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('bg-gradient-to-r', 'from-primary-500', 'to-emerald-500', 'text-white', 'shadow-lg');
        btn.classList.add('text-gray-500', 'hover:bg-gray-100');
    });
    
    const activeTab = document.getElementById('tab-' + tabName);
    activeTab.classList.add('bg-gradient-to-r', 'from-primary-500', 'to-emerald-500', 'text-white', 'shadow-lg');
    activeTab.classList.remove('text-gray-500', 'hover:bg-gray-100');
    
    // Update content
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    document.getElementById('tab-content-' + tabName).classList.remove('hidden');
}

// Auto-Assignment Function
function runAutoAssign() {
    if (!confirm('Möchten Sie die automatische Zuteilung wirklich durchführen?\n\nDies wird alle Schüler, die noch nicht für alle 3 Slots registriert sind, automatisch auf Aussteller verteilen.')) {
        return;
    }
    
    const btn = document.getElementById('autoAssignBtn');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Verarbeite...';
    btn.classList.add('opacity-75', 'cursor-not-allowed');
    
    // Redirect zur Verarbeitung
    window.location.href = '?page=admin-dashboard&auto_assign=run';
}
</script>
