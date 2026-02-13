<?php
// Admin Dashboard mit Statistiken

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

// Schüler mit Anmeldungen (nur role='student', nicht Lehrer/Admins - Issue #14)
$stmt = $db->query("SELECT COUNT(DISTINCT r.user_id) as count FROM registrations r JOIN users u ON r.user_id = u.id WHERE u.role = 'student'");
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
?>

<div class="space-y-6">
    <!-- Auto-Assign Result Message -->
    <?php if ($autoAssignMessage): ?>
        <?php if ($autoAssignMessage['type'] === 'success'): ?>
            <div class="bg-green-50 border-l-4 border-green-500 p-6 rounded-lg animate-pulse">
                <div class="flex items-start">
                    <i class="fas fa-check-circle text-green-500 text-3xl mr-4"></i>
                    <div class="flex-1">
                        <h3 class="font-bold text-green-800 text-lg mb-2">Automatische Zuteilung erfolgreich!</h3>
                        <p class="text-green-700 mb-2">
                            <strong><?php echo $autoAssignMessage['count']; ?></strong> Anmeldungen wurden für 
                            <strong><?php echo $autoAssignMessage['students']; ?></strong> Schüler erstellt.
                        </p>
                        <?php if (!empty($autoAssignMessage['errors'])): ?>
                            <div class="mt-3 p-3 bg-yellow-50 rounded border border-yellow-200">
                                <p class="text-sm font-semibold text-yellow-800 mb-1">Hinweise:</p>
                                <ul class="text-xs text-yellow-700 space-y-1">
                                    <?php foreach ($autoAssignMessage['errors'] as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-6 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-500 text-2xl mr-3"></i>
                    <div>
                        <h3 class="font-bold text-red-800 mb-1">Fehler bei der automatischen Zuteilung</h3>
                        <p class="text-red-700"><?php echo htmlspecialchars($autoAssignMessage['message']); ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Header Stats -->
    <div class="bg-gradient-to-r from-emerald-500 to-green-600 rounded-xl p-6 text-white mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold mb-2">
                    <i class="fas fa-shield-alt mr-2"></i>
                    Administrator Dashboard
                </h2>
                <p class="text-emerald-100">Zentrale Steuerung der Berufsmesse</p>
            </div>
            <button onclick="startGuidedTour()" class="bg-white bg-opacity-20 hover:bg-opacity-30 text-white px-4 py-2 rounded-lg font-medium transition">
                <i class="fas fa-play-circle mr-2"></i>Tour starten
            </button>
        </div>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Total Students -->
        <div class="bg-white rounded-xl border border-gray-100 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm mb-1">Gesamt Schüler</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_students']; ?></p>
                </div>
                <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center">
                    <i class="fas fa-user-graduate text-blue-500 text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Total Exhibitors -->
        <div class="bg-white rounded-xl border border-gray-100 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm mb-1">Aktive Aussteller</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_exhibitors']; ?></p>
                </div>
                <div class="w-12 h-12 bg-purple-50 rounded-xl flex items-center justify-center">
                    <i class="fas fa-building text-purple-500 text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Registered Students -->
        <div class="bg-white rounded-xl border border-gray-100 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm mb-1">Angemeldete Schüler</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['students_registered']; ?></p>
                    <p class="text-xs text-emerald-600 mt-1">
                        <?php echo $stats['total_students'] > 0 ? round(($stats['students_registered'] / $stats['total_students']) * 100) : 0; ?>% Beteiligung
                    </p>
                </div>
                <div class="w-12 h-12 bg-emerald-50 rounded-xl flex items-center justify-center">
                    <i class="fas fa-check-circle text-emerald-500 text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Not Registered -->
        <div class="bg-white rounded-xl border border-gray-100 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm mb-1">Ohne Anmeldung</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['students_not_registered']; ?></p>
                    <p class="text-xs text-red-500 mt-1">Benötigen Zuteilung</p>
                </div>
                <div class="w-12 h-12 bg-red-50 rounded-xl flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-red-500 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
        <div class="border-b border-gray-100">
            <nav class="flex -mb-px flex-wrap">
                <button onclick="switchTab('statistics')" id="tab-statistics" class="tab-button active px-6 py-4 text-sm font-medium border-b-2 border-emerald-500 text-emerald-600 hover:text-emerald-700 transition">
                    <i class="fas fa-chart-bar mr-2"></i>Übersicht
                </button>
                <button onclick="switchTab('registrations')" id="tab-registrations" class="tab-button px-6 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 transition">
                    <i class="fas fa-clipboard-list mr-2"></i>Letzte Anmeldungen
                </button>
            </nav>
        </div>

        <!-- Tab Content: Statistics -->
        <div id="tab-content-statistics" class="tab-content p-6">
            <!-- Auto-Assignment Tool -->
            <div class="mb-6 bg-gray-50 border border-gray-200 rounded-xl p-5">
                <div class="flex items-start justify-between flex-wrap gap-4">
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2 flex items-center">
                            <i class="fas fa-magic text-amber-500 mr-3"></i>
                            Automatische Zuteilung
                        </h3>
                        <p class="text-sm text-gray-600 mb-3">
                            Verteilt Schüler, die sich nicht für alle 3 Slots registriert haben, automatisch auf die Aussteller mit den wenigsten Teilnehmern.
                        </p>
                        <div class="flex flex-wrap gap-2 text-xs">
                            <span class="bg-white border border-gray-200 px-3 py-1 rounded-full text-gray-600">
                                <i class="fas fa-check mr-1 text-emerald-500"></i>Nur Slots 1, 3, 5
                            </span>
                            <span class="bg-white border border-gray-200 px-3 py-1 rounded-full text-gray-600">
                                <i class="fas fa-balance-scale mr-1 text-blue-500"></i>Gleichmäßige Verteilung
                            </span>
                        </div>
                    </div>
                    <div class="flex-shrink-0">
                        <button onclick="runAutoAssign()" id="autoAssignBtn" 
                                class="bg-amber-500 text-white px-5 py-2.5 rounded-lg font-medium hover:bg-amber-600 transition">
                            <i class="fas fa-play-circle mr-2"></i>Ausführen
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Print Tool -->
            <div class="mb-6 bg-gray-50 border border-gray-200 rounded-xl p-5">
                <div class="flex items-start justify-between flex-wrap gap-4">
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2 flex items-center">
                            <i class="fas fa-print text-indigo-500 mr-3"></i>
                            Pläne drucken
                        </h3>
                        <p class="text-sm text-gray-600">
                            Drucke verschiedene Übersichten: Gesamtplan, Klassenpläne oder Raumpläne
                        </p>
                    </div>
                    <div class="flex-shrink-0">
                        <a href="?page=admin-print" 
                           class="bg-indigo-500 text-white px-5 py-2.5 rounded-lg font-medium hover:bg-indigo-600 transition inline-block">
                            <i class="fas fa-file-pdf mr-2"></i>Zur Druckansicht
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Charts Row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Top Exhibitors -->
                <div class="bg-gray-50 border border-gray-100 rounded-xl p-5">
                    <h3 class="text-base font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-trophy text-amber-500 mr-2"></i>
                        Beliebteste Aussteller
                    </h3>
                    <div class="space-y-3">
                        <?php foreach ($topExhibitors as $index => $exhibitor): 
                            $maxReg = $topExhibitors[0]['registrations'] ?: 1;
                            $percentage = ($exhibitor['registrations'] / $maxReg) * 100;
                        ?>
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <div class="flex items-center space-x-2 flex-1 min-w-0">
                                    <span class="flex-shrink-0 w-5 h-5 bg-gray-200 text-gray-600 rounded-full flex items-center justify-center text-xs font-medium">
                                        <?php echo $index + 1; ?>
                                    </span>
                                    <span class="font-medium text-gray-700 text-sm truncate">
                                        <?php echo htmlspecialchars($exhibitor['name']); ?>
                                    </span>
                                </div>
                                <span class="ml-2 font-semibold text-gray-600 text-sm flex-shrink-0">
                                    <?php echo $exhibitor['registrations']; ?>
                                </span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-1.5">
                                <div class="bg-emerald-500 h-1.5 rounded-full transition-all" 
                                     style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Slot Distribution -->
                <div class="bg-gray-50 border border-gray-100 rounded-xl p-5">
                    <h3 class="text-base font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-chart-pie text-blue-500 mr-2"></i>
                        Verteilung nach Zeitslot
                    </h3>
                    <p class="text-xs text-gray-500 mb-3">
                        <i class="fas fa-info-circle text-blue-500 mr-1"></i>
                        Nur feste Zuteilungen (Slot 1, 3, 5)
                    </p>
                    <div class="space-y-3">
                        <?php 
                        // Nur Slots mit festen Zuteilungen (1, 3, 5)
                        $assignedSlots = array_filter($slotStats, function($s) { 
                            return in_array($s['slot_number'], [1, 3, 5]); 
                        });
                        $totalSlotRegs = array_sum(array_column($assignedSlots, 'registrations')) ?: 1;
                        
                        // Farben für die 3 festen Slots
                        $slotColors = [
                            1 => 'bg-blue-500',
                            3 => 'bg-emerald-500',
                            5 => 'bg-amber-500'
                        ];
                        
                        $slotIndex = 1;
                        foreach ($assignedSlots as $slot): 
                            $percentage = $totalSlotRegs > 0 ? ($slot['registrations'] / $totalSlotRegs) * 100 : 0;
                            $colorClass = $slotColors[$slot['slot_number']] ?? 'bg-gray-500';
                        ?>
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="font-medium text-gray-700 text-sm">
                                    Slot <?php echo $slotIndex; ?>
                                    <?php if (!empty($slot['start_time']) && !empty($slot['end_time'])): ?>
                                        <span class="text-xs text-gray-400 font-normal ml-1">
                                            (<?php echo substr($slot['start_time'], 0, 5); ?> - <?php echo substr($slot['end_time'], 0, 5); ?>)
                                        </span>
                                    <?php endif; ?>
                                </span>
                                <span class="text-xs text-gray-500">
                                    <?php echo $slot['registrations']; ?> (<?php echo round($percentage); ?>%)
                                </span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-1.5">
                                <div class="<?php echo $colorClass; ?> h-1.5 rounded-full transition-all" 
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
            <h3 class="text-base font-semibold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-clipboard-list text-gray-500 mr-2"></i>
                Letzte Anmeldungen
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50">
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Schüler</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Aussteller</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Zeitslot</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Typ</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Zeitpunkt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentRegistrations as $reg): ?>
                        <tr class="border-b border-gray-100 hover:bg-gray-50 transition">
                            <td class="py-3 px-4">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center mr-3">
                                        <span class="text-gray-700 font-semibold text-sm">
                                            <?php echo strtoupper(substr($reg['firstname'], 0, 1) . substr($reg['lastname'], 0, 1)); ?>
                                        </span>
                                    </div>
                                    <span class="font-medium text-gray-800">
                                        <?php echo htmlspecialchars($reg['firstname'] . ' ' . $reg['lastname']); ?>
                                    </span>
                                </div>
                            </td>
                            <td class="py-3 px-4 text-gray-700">
                                <?php echo htmlspecialchars($reg['exhibitor_name']); ?>
                            </td>
                            <td class="py-3 px-4">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-800 border border-gray-300">
                                    <?php echo htmlspecialchars($reg['slot_name']); ?>
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <?php if ($reg['registration_type'] === 'automatic'): ?>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gray-200 text-gray-800 border border-gray-300">
                                        <i class="fas fa-robot mr-1"></i>Auto
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-white text-gray-800 border border-gray-300">
                                        <i class="fas fa-user mr-1"></i>Manuell
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4 text-sm text-gray-600">
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

<script>
// Start guided tour
function startGuidedTour() {
    const userRole = '<?php echo $_SESSION["role"] ?? "admin"; ?>';
    
    if (typeof GuidedTour !== 'undefined') {
        // Generate role-based steps
        const steps = typeof generateTourSteps !== 'undefined' 
            ? generateTourSteps(userRole)
            : (window.berufsmesseTourSteps || []);
        
        const tour = new GuidedTour({
            steps: steps,
            role: userRole,
            onComplete: () => {
                if (typeof showToast !== 'undefined') {
                    showToast('Tour abgeschlossen!', 'success');
                }
            },
            onSkip: () => {
                if (typeof showToast !== 'undefined') {
                    showToast('Tour übersprungen', 'info');
                }
            }
        });
        tour.reset(); // Allow restart
        tour.start();
    } else {
        console.warn('GuidedTour nicht geladen');
    }
}

// Auto-start tour if start_tour parameter is present
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('start_tour') === '1') {
        // Remove parameter from URL
        window.history.replaceState({}, '', window.location.pathname + '?page=admin-dashboard');
        // Start tour after a short delay
        setTimeout(() => {
            startGuidedTour();
        }, 500);
    }
});

// Tab Switching
function switchTab(tabName) {
    // Update buttons
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('active', 'border-emerald-500', 'text-emerald-600');
        btn.classList.add('border-transparent', 'text-gray-500');
    });
    
    document.getElementById('tab-' + tabName).classList.add('active', 'border-emerald-500', 'text-emerald-600');
    document.getElementById('tab-' + tabName).classList.remove('border-transparent', 'text-gray-500');
    
    // Update content
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    document.getElementById('tab-content-' + tabName).classList.remove('hidden');
}

// Auto-Assignment Function
function runAutoAssign() {
    if (!confirm('Möchtest Du die automatische Zuteilung wirklich durchführen?\n\nDies wird alle Schüler, die noch nicht für alle 3 Slots registriert sind, automatisch auf Aussteller verteilen.')) {
        return;
    }
    
    const btn = document.getElementById('autoAssignBtn');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Verarbeite...';
    
    // Redirect zur Verarbeitung
    window.location.href = '?page=admin-dashboard&auto_assign=run';
}
</script>
