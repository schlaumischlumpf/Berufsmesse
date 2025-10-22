<?php
// Admin Dashboard mit Statistiken

// Auto-Assign durchführen wenn aufgerufen
if (isset($_GET['auto_assign']) && $_GET['auto_assign'] === 'run') {
    // API aufrufen
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, BASE_URL . 'api/auto-assign-incomplete.php');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Cookie: ' . session_name() . '=' . session_id()
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($result && $result['success']) {
        $_SESSION['auto_assign_success'] = true;
        $_SESSION['auto_assign_count'] = $result['assigned'];
        $_SESSION['auto_assign_students'] = $result['statistics']['incomplete_registrations'] ?? 0;
        $_SESSION['auto_assign_errors'] = $result['errors'] ?? [];
    } else {
        $_SESSION['auto_assign_error'] = $result['message'] ?? 'Unbekannter Fehler';
    }
    
    header('Location: ?page=admin-dashboard&auto_assign=done');
    exit;
}

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
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Total Students -->
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-sm mb-1">Gesamt Schüler</p>
                    <p class="text-3xl font-bold"><?php echo $stats['total_students']; ?></p>
                </div>
                <div class="w-14 h-14 bg-white/20 rounded-lg flex items-center justify-center">
                    <i class="fas fa-user-graduate text-2xl"></i>
                </div>
            </div>
        </div>

        <!-- Total Exhibitors -->
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-purple-100 text-sm mb-1">Aktive Aussteller</p>
                    <p class="text-3xl font-bold"><?php echo $stats['total_exhibitors']; ?></p>
                </div>
                <div class="w-14 h-14 bg-white/20 rounded-lg flex items-center justify-center">
                    <i class="fas fa-building text-2xl"></i>
                </div>
            </div>
        </div>

        <!-- Registered Students -->
        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-100 text-sm mb-1">Angemeldete Schüler</p>
                    <p class="text-3xl font-bold"><?php echo $stats['students_registered']; ?></p>
                    <p class="text-xs text-green-100 mt-1">
                        <?php echo $stats['total_students'] > 0 ? round(($stats['students_registered'] / $stats['total_students']) * 100) : 0; ?>% Beteiligung
                    </p>
                </div>
                <div class="w-14 h-14 bg-white/20 rounded-lg flex items-center justify-center">
                    <i class="fas fa-check-circle text-2xl"></i>
                </div>
            </div>
        </div>

        <!-- Not Registered -->
        <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-xl p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-red-100 text-sm mb-1">Ohne Anmeldung</p>
                    <p class="text-3xl font-bold"><?php echo $stats['students_not_registered']; ?></p>
                    <p class="text-xs text-red-100 mt-1">Benötigen Zuteilung</p>
                </div>
                <div class="w-14 h-14 bg-white/20 rounded-lg flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="bg-white rounded-xl overflow-hidden">
        <div class="border-b border-gray-200">
            <nav class="flex -mb-px flex-wrap">
                <button onclick="switchTab('statistics')" id="tab-statistics" class="tab-button active px-6 py-4 text-sm font-semibold border-b-2 border-blue-600 text-blue-600 hover:text-blue-700 transition">
                    <i class="fas fa-chart-bar mr-2"></i>Statistiken
                </button>
                <button onclick="switchTab('registrations')" id="tab-registrations" class="tab-button px-6 py-4 text-sm font-semibold border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 transition">
                    <i class="fas fa-clipboard-list mr-2"></i>Anmeldungen
                </button>
                <button onclick="switchTab('users')" id="tab-users" class="tab-button px-6 py-4 text-sm font-semibold border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 transition">
                    <i class="fas fa-users mr-2"></i>Benutzersuche
                </button>
                <button onclick="switchTab('exhibitors')" id="tab-exhibitors" class="tab-button px-6 py-4 text-sm font-semibold border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 transition">
                    <i class="fas fa-building mr-2"></i>Aussteller
                </button>
                <button onclick="switchTab('rooms')" id="tab-rooms" class="tab-button px-6 py-4 text-sm font-semibold border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 transition">
                    <i class="fas fa-map-marker-alt mr-2"></i>Räume
                </button>
            </nav>
        </div>

        <!-- Tab Content: Statistics -->
        <div id="tab-content-statistics" class="tab-content p-6">
            <!-- Auto-Assignment Tool -->
            <div class="mb-6 bg-gradient-to-r from-orange-500 to-red-500 rounded-xl p-6 text-white">
                <div class="flex items-start justify-between flex-wrap gap-4">
                    <div class="flex-1">
                        <h3 class="text-xl font-bold mb-2 flex items-center">
                            <i class="fas fa-magic mr-3"></i>
                            Automatische Zuteilung
                        </h3>
                        <p class="text-sm text-orange-100 mb-3">
                            Verteilt Schüler, die sich nicht für alle 3 Slots registriert haben, automatisch auf die Aussteller mit den wenigsten Teilnehmern.
                        </p>
                        <div class="flex flex-wrap gap-2 text-xs">
                            <span class="bg-white/20 px-3 py-1 rounded-full">
                                <i class="fas fa-check mr-1"></i>Nur Slots 1, 3, 5
                            </span>
                            <span class="bg-white/20 px-3 py-1 rounded-full">
                                <i class="fas fa-balance-scale mr-1"></i>Gleichmäßige Verteilung
                            </span>
                            <span class="bg-white/20 px-3 py-1 rounded-full">
                                <i class="fas fa-shield-alt mr-1"></i>Kapazitätsprüfung
                            </span>
                        </div>
                    </div>
                    <div class="flex-shrink-0">
                        <button onclick="runAutoAssign()" id="autoAssignBtn" 
                                class="bg-white text-orange-600 px-6 py-3 rounded-lg font-bold hover:bg-orange-50 transition shadow-lg">
                            <i class="fas fa-play-circle mr-2"></i>Jetzt ausführen
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Charts Row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Top Exhibitors -->
                <div>
                    <h3 class="text-xl font-bold text-gray-800 mb-6 flex items-center">
                        <i class="fas fa-trophy text-gray-600 mr-3"></i>
                        Beliebteste Aussteller
                    </h3>
                    <div class="space-y-4">
                        <?php foreach ($topExhibitors as $index => $exhibitor): 
                            $maxReg = $topExhibitors[0]['registrations'] ?: 1;
                            $percentage = ($exhibitor['registrations'] / $maxReg) * 100;
                        ?>
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center space-x-3 flex-1 min-w-0">
                                    <span class="flex-shrink-0 w-6 h-6 bg-gray-700 text-white rounded-full flex items-center justify-center text-xs font-bold">
                                        <?php echo $index + 1; ?>
                                    </span>
                                    <span class="font-semibold text-gray-800 truncate">
                                        <?php echo htmlspecialchars($exhibitor['name']); ?>
                                    </span>
                                </div>
                                <span class="ml-2 font-bold text-gray-700 flex-shrink-0">
                                    <?php echo $exhibitor['registrations']; ?>
                                </span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2 border border-gray-200">
                                <div class="bg-gray-600 h-2 rounded-full" 
                                     style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Slot Distribution -->
                <div>
                    <h3 class="text-xl font-bold text-gray-800 mb-6 flex items-center">
                        <i class="fas fa-chart-pie text-gray-600 mr-3"></i>
                        Verteilung nach Zeitslot
                    </h3>
                    <p class="text-sm text-gray-600 mb-4">
                        <i class="fas fa-info-circle text-blue-500 mr-1"></i>
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
                            1 => 'bg-blue-600',
                            3 => 'bg-green-600',
                            5 => 'bg-red-600'
                        ];
                        
                        $slotIndex = 1;
                        foreach ($assignedSlots as $slot): 
                            $percentage = $totalSlotRegs > 0 ? ($slot['registrations'] / $totalSlotRegs) * 100 : 0;
                            $colorClass = $slotColors[$slot['slot_number']] ?? 'bg-gray-600';
                        ?>
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="font-semibold text-gray-800">
                                    Slot <?php echo $slotIndex; ?>
                                    <?php if (!empty($slot['start_time']) && !empty($slot['end_time'])): ?>
                                        <span class="text-sm text-gray-500 font-normal ml-2">
                                            (<?php echo substr($slot['start_time'], 0, 5); ?> - <?php echo substr($slot['end_time'], 0, 5); ?>)
                                        </span>
                                    <?php endif; ?>
                                </span>
                                <span class="text-sm text-gray-600">
                                    <?php echo $slot['registrations']; ?> (<?php echo round($percentage); ?>%)
                                </span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-3 border border-gray-200">
                                <div class="<?php echo $colorClass; ?> h-3 rounded-full transition-all duration-500" 
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
            <h3 class="text-xl font-bold text-gray-800 mb-6 flex items-center">
                <i class="fas fa-clipboard-list text-gray-700 mr-3"></i>
                Letzte Anmeldungen
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b-2 border-gray-200 bg-gray-50">
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

        <!-- Tab Content: User Search -->
        <div id="tab-content-users" class="tab-content p-6 hidden">
            <!-- Search Form -->
            <form id="userSearchForm" class="mb-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-search mr-1"></i>Name
                    </label>
                    <input type="text" id="search_name" placeholder="Vor- oder Nachname" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-school mr-1"></i>Klasse
                    </label>
                    <input type="text" id="search_class" placeholder="z.B. 10A" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-user-tag mr-1"></i>Rolle
                    </label>
                    <select id="search_role" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">Alle</option>
                        <option value="student">Schüler</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-clipboard-check mr-1"></i>Status
                    </label>
                    <select id="search_status" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">Alle</option>
                        <option value="registered">Mit Anmeldung</option>
                        <option value="not_registered">Ohne Anmeldung</option>
                    </select>
                </div>
            </form>

            <!-- Results -->
            <div id="userSearchResults"></div>
        </div>

        <!-- Tab Content: Exhibitors -->
        <div id="tab-content-exhibitors" class="tab-content p-6 hidden">
            <div class="mb-6 flex flex-wrap justify-between items-center gap-4">
                <h3 class="text-xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-building text-blue-600 mr-3"></i>
                    Aussteller-Verwaltung
                </h3>
                <div class="flex gap-2">
                    <a href="?page=admin-exhibitors" target="_blank" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center">
                        <i class="fas fa-edit mr-2"></i>Bearbeiten & Verwalten
                    </a>
                </div>
            </div>
            
            <div class="bg-blue-50 border-l-4 border-blue-500 rounded-lg p-4 mb-6">
                <p class="text-blue-800 text-sm">
                    <i class="fas fa-info-circle mr-2"></i>
                    <strong>Tipp:</strong> Zum Hinzufügen, Bearbeiten oder Löschen von Ausstellern klicken Sie auf "Bearbeiten & Verwalten".
                </p>
            </div>
            
            <!-- Exhibitor List -->
            <div id="exhibitorsList" class="space-y-3">
                <?php
                $stmt = $db->query("SELECT e.*, COUNT(DISTINCT r.user_id) as registered_count, rm.room_number, rm.room_name 
                                   FROM exhibitors e 
                                   LEFT JOIN registrations r ON e.id = r.exhibitor_id 
                                   LEFT JOIN rooms rm ON e.room_id = rm.id
                                   WHERE e.active = 1 
                                   GROUP BY e.id 
                                   ORDER BY e.name ASC");
                $exhibitors = $stmt->fetchAll();
                foreach ($exhibitors as $ex):
                    $percentage = $ex['total_slots'] > 0 ? ($ex['registered_count'] / $ex['total_slots'] * 100) : 0;
                ?>
                <div class="bg-blue-50 border-l-4 border-blue-600 p-4 rounded-r-lg">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <h4 class="font-bold text-gray-800"><?php echo htmlspecialchars($ex['name']); ?></h4>
                            <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($ex['short_description'] ?? ''); ?></p>
                            <div class="mt-2 flex items-center gap-4 text-sm">
                                <span class="text-gray-600">
                                    <i class="fas fa-users mr-1"></i><?php echo $ex['registered_count']; ?> / <?php echo $ex['total_slots']; ?> Plätze
                                </span>
                                <?php if ($ex['room_number']): ?>
                                <span class="text-gray-600">
                                    <i class="fas fa-door-open mr-1"></i><?php echo htmlspecialchars($ex['room_number']); ?>
                                    <?php if ($ex['room_name']): ?>
                                        - <?php echo htmlspecialchars($ex['room_name']); ?>
                                    <?php endif; ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            <!-- Progress -->
                            <div class="mt-2 w-full bg-white rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo min($percentage, 100); ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Tab Content: Rooms -->
        <div id="tab-content-rooms" class="tab-content p-6 hidden">
            <div class="mb-6 flex flex-wrap justify-between items-center gap-4">
                <h3 class="text-xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-map-marker-alt text-green-600 mr-3"></i>
                    Raum-Verwaltung
                </h3>
                <div class="flex gap-2">
                    <a href="?page=admin-rooms" target="_blank" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center">
                        <i class="fas fa-map-marked-alt mr-2"></i>Drag & Drop Zuteilung
                    </a>
                </div>
            </div>
            
            <div class="bg-green-50 border-l-4 border-green-500 rounded-lg p-4 mb-6">
                <p class="text-green-800 text-sm">
                    <i class="fas fa-info-circle mr-2"></i>
                    <strong>Tipp:</strong> Zum Zuweisen von Ausstellern zu Räumen per Drag & Drop klicken Sie auf "Drag & Drop Zuteilung".
                </p>
            </div>
            
            <!-- Rooms List -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php
                $stmt = $db->query("SELECT r.*, COUNT(e.id) as exhibitor_count 
                                   FROM rooms r 
                                   LEFT JOIN exhibitors e ON r.id = e.room_id AND e.active = 1
                                   GROUP BY r.id 
                                   ORDER BY r.building, r.room_number");
                $rooms = $stmt->fetchAll();
                foreach ($rooms as $room):
                ?>
                <div class="bg-green-50 border-l-4 border-green-600 p-4 rounded-r-lg">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <h4 class="font-bold text-gray-800">
                                <?php echo htmlspecialchars($room['room_number']); ?>
                                <?php if ($room['room_name']): ?>
                                    - <?php echo htmlspecialchars($room['room_name']); ?>
                                <?php endif; ?>
                            </h4>
                            <div class="mt-2 space-y-1 text-sm text-gray-600">
                                <?php if ($room['building']): ?>
                                <div><i class="fas fa-building w-4 mr-2"></i><?php echo htmlspecialchars($room['building']); ?></div>
                                <?php endif; ?>
                                <?php if ($room['floor']): ?>
                                <div><i class="fas fa-layer-group w-4 mr-2"></i><?php echo $room['floor']; ?>. Stock</div>
                                <?php endif; ?>
                                <div><i class="fas fa-users w-4 mr-2"></i>Kapazität: <?php echo $room['capacity']; ?> Personen</div>
                                <div>
                                    <i class="fas fa-store w-4 mr-2"></i>
                                    <span class="font-semibold"><?php echo $room['exhibitor_count']; ?></span> Aussteller zugeordnet
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <a href="?page=admin-exhibitors" target="_blank" class="bg-white border-2 border-blue-300 rounded-xl p-6 hover:border-blue-500 hover:shadow-md transition">
            <div class="flex items-center justify-between mb-4">
                <i class="fas fa-building text-3xl text-blue-600"></i>
                <i class="fas fa-external-link-alt text-xl text-blue-400"></i>
            </div>
            <h4 class="font-bold text-lg mb-1 text-gray-800">Aussteller verwalten</h4>
            <p class="text-sm text-gray-600">Hinzufügen, bearbeiten, löschen</p>
        </a>

        <a href="?page=admin-rooms" target="_blank" class="bg-white border-2 border-green-300 rounded-xl p-6 hover:border-green-500 hover:shadow-md transition">
            <div class="flex items-center justify-between mb-4">
                <i class="fas fa-map-marker-alt text-3xl text-green-600"></i>
                <i class="fas fa-external-link-alt text-xl text-green-400"></i>
            </div>
            <h4 class="font-bold text-lg mb-1 text-gray-800">Raum-Zuteilung</h4>
            <p class="text-sm text-gray-600">Drag & Drop Zuweisungen</p>
        </a>

        <a href="?page=admin-settings" class="bg-white border-2 border-gray-300 rounded-xl p-6 hover:border-gray-500 hover:shadow-md transition">
            <div class="flex items-center justify-between mb-4">
                <i class="fas fa-cog text-3xl text-gray-700"></i>
                <i class="fas fa-arrow-right text-xl text-gray-400"></i>
            </div>
            <h4 class="font-bold text-lg mb-1 text-gray-800">Einstellungen</h4>
            <p class="text-sm text-gray-600">Zeiträume und Parameter konfigurieren</p>
        </a>

        <button onclick="runAutoAssignment()" class="bg-gradient-to-br from-gray-700 to-gray-800 rounded-xl p-6 text-white hover:from-gray-800 hover:to-gray-900 transition text-left">
            <div class="flex items-center justify-between mb-4">
                <i class="fas fa-robot text-3xl"></i>
                <i class="fas fa-play text-xl"></i>
            </div>
            <h4 class="font-bold text-lg mb-1">Auto-Zuteilung</h4>
            <p class="text-sm text-gray-300">Schüler automatisch zuteilen</p>
        </button>
    </div>
</div>

<script>
function runAutoAssignment() {
    if (confirm('Möchten Sie die automatische Zuteilung starten? Alle Schüler ohne Anmeldung werden automatisch zugeteilt.')) {
        window.location.href = 'api/auto-assign.php';
    }
}

// Tab Switching
function switchTab(tabName) {
    // Update buttons
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('active', 'border-blue-600', 'text-blue-600');
        btn.classList.add('border-transparent', 'text-gray-500');
    });
    
    document.getElementById('tab-' + tabName).classList.add('active', 'border-blue-600', 'text-blue-600');
    document.getElementById('tab-' + tabName).classList.remove('border-transparent', 'text-gray-500');
    
    // Update content
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    document.getElementById('tab-content-' + tabName).classList.remove('hidden');
    
    // Load user search on first open
    if (tabName === 'users' && !document.getElementById('userSearchResults').innerHTML) {
        searchUsers();
    }
}

// User Search Function
function searchUsers() {
    const name = document.getElementById('search_name').value;
    const classValue = document.getElementById('search_class').value;
    const role = document.getElementById('search_role').value;
    const status = document.getElementById('search_status').value;
    
    fetch('api/search-users.php?' + new URLSearchParams({
        name: name,
        class: classValue,
        role: role,
        status: status
    }))
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayUserResults(data.users);
        } else {
            document.getElementById('userSearchResults').innerHTML = 
                '<div class="text-center py-8 text-gray-500">' +
                '<i class="fas fa-exclamation-circle text-4xl mb-3"></i>' +
                '<p>Fehler beim Laden der Benutzer</p>' +
                '</div>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

function displayUserResults(users) {
    const resultsDiv = document.getElementById('userSearchResults');
    
    if (users.length === 0) {
        resultsDiv.innerHTML = 
            '<div class="text-center py-8 text-gray-500">' +
            '<i class="fas fa-users text-4xl mb-3"></i>' +
            '<p>Keine Benutzer gefunden</p>' +
            '</div>';
        return;
    }
    
    let html = '<div class="overflow-x-auto"><table class="w-full">';
    html += '<thead><tr class="border-b border-gray-200">';
    html += '<th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Name</th>';
    html += '<th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Benutzername</th>';
    html += '<th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Klasse</th>';
    html += '<th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Rolle</th>';
    html += '<th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Anmeldungen</th>';
    html += '<th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Status</th>';
    html += '</tr></thead><tbody>';
    
    users.forEach(user => {
        html += '<tr class="border-b border-gray-100 hover:bg-gray-50 transition">';
        
        // Name
        html += '<td class="py-3 px-4"><div class="flex items-center">';
        html += '<div class="w-8 h-8 ' + (user.role === 'admin' ? 'bg-red-100' : 'bg-blue-100') + ' rounded-full flex items-center justify-center mr-3">';
        html += '<span class="' + (user.role === 'admin' ? 'text-red-600' : 'text-blue-600') + ' font-semibold text-sm">';
        html += user.initials + '</span></div>';
        html += '<span class="font-medium text-gray-800">' + user.fullname + '</span>';
        html += '</div></td>';
        
        // Username
        html += '<td class="py-3 px-4 text-gray-700">' + user.username + '</td>';
        
        // Class
        html += '<td class="py-3 px-4 text-gray-700">' + (user.class || '-') + '</td>';
        
        // Role
        html += '<td class="py-3 px-4">';
        if (user.role === 'admin') {
            html += '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800">';
            html += '<i class="fas fa-crown mr-1"></i>Admin</span>';
        } else {
            html += '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">';
            html += '<i class="fas fa-user-graduate mr-1"></i>Schüler</span>';
        }
        html += '</td>';
        
        // Registration count
        html += '<td class="py-3 px-4"><span class="font-semibold text-gray-800">' + user.registration_count + '</span> / 3</td>';
        
        // Status
        html += '<td class="py-3 px-4">';
        if (user.registration_count > 0) {
            html += '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">';
            html += '<i class="fas fa-check mr-1"></i>Angemeldet</span>';
        } else {
            html += '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800">';
            html += '<i class="fas fa-times mr-1"></i>Keine Anmeldung</span>';
        }
        html += '</td>';
        
        html += '</tr>';
    });
    
    html += '</tbody></table></div>';
    
    // Add summary
    const totalUsers = users.length;
    const withRegistrations = users.filter(u => u.registration_count > 0).length;
    const withoutRegistrations = totalUsers - withRegistrations;
    
    html += '<div class="mt-6 grid grid-cols-3 gap-4">';
    html += '<div class="bg-blue-50 rounded-lg p-4 text-center">';
    html += '<div class="text-2xl font-bold text-blue-600">' + totalUsers + '</div>';
    html += '<div class="text-sm text-blue-800 mt-1">Gefunden</div>';
    html += '</div>';
    html += '<div class="bg-green-50 rounded-lg p-4 text-center">';
    html += '<div class="text-2xl font-bold text-green-600">' + withRegistrations + '</div>';
    html += '<div class="text-sm text-green-800 mt-1">Mit Anmeldung</div>';
    html += '</div>';
    html += '<div class="bg-red-50 rounded-lg p-4 text-center">';
    html += '<div class="text-2xl font-bold text-red-600">' + withoutRegistrations + '</div>';
    html += '<div class="text-sm text-red-800 mt-1">Ohne Anmeldung</div>';
    html += '</div>';
    html += '</div>';
    
    resultsDiv.innerHTML = html;
}

// Event listeners for search
document.getElementById('search_name').addEventListener('input', debounce(searchUsers, 500));
document.getElementById('search_class').addEventListener('input', debounce(searchUsers, 500));
document.getElementById('search_role').addEventListener('change', searchUsers);
document.getElementById('search_status').addEventListener('change', searchUsers);

// Debounce function
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
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
    
    // Redirect zur Verarbeitung
    window.location.href = '?page=admin-dashboard&auto_assign=run';
}
</script>
