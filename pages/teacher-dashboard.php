<?php
// Lehrer Dashboard (Issue #8)

// Alle Klassen abrufen
$stmt = $db->query("SELECT DISTINCT class FROM users WHERE role = 'student' AND class IS NOT NULL AND class != '' ORDER BY class");
$classes = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Statistiken
$stats = [];

// Gesamtzahl Schüler
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'");
$stats['total_students'] = $stmt->fetch()['count'];

// Schüler mit vollständigen Anmeldungen (alle 3 Slots)
$stmt = $db->query("
    SELECT COUNT(DISTINCT user_id) as count
    FROM (
        SELECT r.user_id, COUNT(DISTINCT t.slot_number) as slot_count
        FROM registrations r
        JOIN timeslots t ON r.timeslot_id = t.id
        JOIN users u ON r.user_id = u.id
        WHERE t.slot_number IN (1, 3, 5) AND u.role = 'student'
        GROUP BY r.user_id
        HAVING slot_count = 3
    ) as complete_registrations
");
$stats['complete_students'] = $stmt->fetch()['count'];

// Schüler mit unvollständigen Anmeldungen
$stats['incomplete_students'] = $stats['total_students'] - $stats['complete_students'];

// Schüler ohne Anmeldungen
$stmt = $db->query("
    SELECT COUNT(*) as count 
    FROM users u
    WHERE u.role = 'student' 
    AND u.id NOT IN (SELECT DISTINCT user_id FROM registrations)
");
$stats['no_registrations'] = $stmt->fetch()['count'];

$completionRate = $stats['total_students'] > 0 ? round(($stats['complete_students'] / $stats['total_students']) * 100) : 0;
?>

<style>
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.animate-fadeInUp {
    animation: fadeInUp 0.5s ease-out forwards;
}

.stat-card {
    position: relative;
    overflow: hidden;
}

.stat-card::after {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 100%;
    height: 100%;
    background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
    transform: rotate(-20deg);
}

.stat-card:hover {
    transform: translateY(-4px) scale(1.02);
}

.class-card {
    transition: all 0.3s ease;
}

.class-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 40px -12px rgba(0,0,0,0.15);
}

.progress-ring {
    transition: stroke-dashoffset 0.5s ease-in-out;
}

.table-row {
    transition: all 0.2s ease;
}

.table-row:hover {
    background: linear-gradient(90deg, rgba(34, 197, 94, 0.05) 0%, rgba(16, 185, 129, 0.05) 100%);
    transform: translateX(4px);
}
</style>

<div class="space-y-8">
    <!-- Hero Header -->
    <div class="bg-gradient-to-br from-emerald-600 via-teal-600 to-cyan-700 rounded-3xl p-8 shadow-2xl relative overflow-hidden animate-fadeInUp">
        <!-- Background Pattern -->
        <div class="absolute inset-0 opacity-10">
            <div class="absolute top-0 left-0 w-full h-full" style="background-image: url('data:image/svg+xml,%3Csvg width=\"60\" height=\"60\" viewBox=\"0 0 60 60\" xmlns=\"http://www.w3.org/2000/svg\"%3E%3Cg fill=\"none\" fill-rule=\"evenodd\"%3E%3Cg fill=\"%23ffffff\" fill-opacity=\"0.4\"%3E%3Cpath d=\"M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\"/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"></div>
        </div>
        
        <!-- Floating Decoration -->
        <div class="absolute top-0 right-0 w-64 h-64 bg-white/10 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2"></div>
        
        <div class="relative flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
            <div class="flex items-center gap-5">
                <div class="w-20 h-20 bg-white/20 backdrop-blur-sm rounded-3xl flex items-center justify-center shadow-xl">
                    <i class="fas fa-chalkboard-teacher text-white text-3xl"></i>
                </div>
                <div>
                    <h2 class="text-3xl md:text-4xl font-extrabold text-white font-display mb-1">Lehrer Dashboard</h2>
                    <p class="text-emerald-100 text-lg">Übersicht über Schüleranmeldungen und Klassenpläne</p>
                </div>
            </div>
            <div class="bg-white/10 backdrop-blur-sm rounded-2xl px-6 py-4 text-right border border-white/20">
                <div class="text-sm text-emerald-100 mb-1">Angemeldet als</div>
                <div class="text-xl font-bold text-white">
                    <?php echo htmlspecialchars($_SESSION['firstname'] . ' ' . $_SESSION['lastname']); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 animate-fadeInUp" style="animation-delay: 100ms;">
        <div class="stat-card bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl p-6 text-white shadow-xl shadow-blue-500/20 transition-all duration-300">
            <div class="relative flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-sm font-medium mb-1">Gesamt Schüler</p>
                    <p class="text-4xl font-extrabold"><?php echo $stats['total_students']; ?></p>
                </div>
                <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm">
                    <i class="fas fa-user-graduate text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="stat-card bg-gradient-to-br from-emerald-500 to-green-600 rounded-2xl p-6 text-white shadow-xl shadow-emerald-500/20 transition-all duration-300">
            <div class="relative flex items-center justify-between">
                <div>
                    <p class="text-emerald-100 text-sm font-medium mb-1">Vollständig</p>
                    <p class="text-4xl font-extrabold"><?php echo $stats['complete_students']; ?></p>
                    <p class="text-sm text-emerald-100 mt-1"><?php echo $completionRate; ?>% Abdeckung</p>
                </div>
                <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm">
                    <i class="fas fa-check-circle text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="stat-card bg-gradient-to-br from-amber-500 to-orange-500 rounded-2xl p-6 text-white shadow-xl shadow-amber-500/20 transition-all duration-300">
            <div class="relative flex items-center justify-between">
                <div>
                    <p class="text-amber-100 text-sm font-medium mb-1">Unvollständig</p>
                    <p class="text-4xl font-extrabold"><?php echo $stats['incomplete_students']; ?></p>
                    <p class="text-sm text-amber-100 mt-1">Fehlen Slots</p>
                </div>
                <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm">
                    <i class="fas fa-exclamation-triangle text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="stat-card bg-gradient-to-br from-red-500 to-rose-600 rounded-2xl p-6 text-white shadow-xl shadow-red-500/20 transition-all duration-300">
            <div class="relative flex items-center justify-between">
                <div>
                    <p class="text-red-100 text-sm font-medium mb-1">Ohne Anmeldung</p>
                    <p class="text-4xl font-extrabold"><?php echo $stats['no_registrations']; ?></p>
                    <p class="text-sm text-red-100 mt-1">Benötigen Hilfe</p>
                </div>
                <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm">
                    <i class="fas fa-user-times text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Class Overview -->
    <div class="bg-white rounded-3xl shadow-xl overflow-hidden border border-gray-100 animate-fadeInUp" style="animation-delay: 200ms;">
        <div class="bg-gradient-to-r from-emerald-600 via-teal-600 to-cyan-600 text-white px-8 py-6 relative overflow-hidden">
            <div class="absolute inset-0 bg-white/10 opacity-30" style="background-image: url('data:image/svg+xml,%3Csvg width=\"40\" height=\"40\" viewBox=\"0 0 40 40\" xmlns=\"http://www.w3.org/2000/svg\"%3E%3Cg fill=\"%23ffffff\" fill-opacity=\"0.3\" fill-rule=\"evenodd\"%3E%3Cpath d=\"M0 40L40 0H20L0 20M40 40V20L20 40\"/%3E%3C/g%3E%3C/svg%3E');"></div>
            <div class="relative flex items-center gap-4">
                <div class="w-14 h-14 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center">
                    <i class="fas fa-users text-2xl"></i>
                </div>
                <div>
                    <h3 class="text-2xl font-extrabold font-display">Klassenübersicht</h3>
                    <p class="text-emerald-100"><?php echo count($classes); ?> Klassen registriert</p>
                </div>
            </div>
        </div>

        <div class="p-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <?php foreach ($classes as $index => $class): 
                    // Statistiken pro Klasse
                    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'student' AND class = ?");
                    $stmt->execute([$class]);
                    $classTotal = $stmt->fetch()['count'];
                    
                    // Vollständig angemeldet
                    $stmt = $db->prepare("
                        SELECT COUNT(DISTINCT user_id) as count
                        FROM (
                            SELECT r.user_id, COUNT(DISTINCT t.slot_number) as slot_count
                            FROM registrations r
                            JOIN timeslots t ON r.timeslot_id = t.id
                            JOIN users u ON r.user_id = u.id
                            WHERE t.slot_number IN (1, 3, 5) AND u.role = 'student' AND u.class = ?
                            GROUP BY r.user_id
                            HAVING slot_count = 3
                        ) as complete
                    ");
                    $stmt->execute([$class]);
                    $classComplete = $stmt->fetch()['count'];
                    
                    $percentage = $classTotal > 0 ? round(($classComplete / $classTotal) * 100) : 0;
                    $colorConfig = $percentage >= 80 ? ['emerald', 'from-emerald-500 to-green-600'] : ($percentage >= 50 ? ['amber', 'from-amber-500 to-orange-500'] : ['red', 'from-red-500 to-rose-500']);
                ?>
                <div class="class-card bg-gradient-to-br from-white to-gray-50 rounded-2xl border border-gray-200 overflow-hidden shadow-sm hover:border-<?php echo $colorConfig[0]; ?>-300" style="animation-delay: <?php echo ($index * 50) + 200; ?>ms;">
                    <!-- Progress Bar Header -->
                    <div class="h-2 bg-gray-100 relative overflow-hidden">
                        <div class="absolute inset-y-0 left-0 bg-gradient-to-r <?php echo $colorConfig[1]; ?> transition-all duration-500" style="width: <?php echo $percentage; ?>%;"></div>
                    </div>
                    
                    <div class="p-5">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 bg-gradient-to-br <?php echo $colorConfig[1]; ?> rounded-xl flex items-center justify-center shadow-lg text-white font-bold">
                                    <?php echo htmlspecialchars($class); ?>
                                </div>
                                <div>
                                    <h4 class="font-bold text-gray-800">Klasse <?php echo htmlspecialchars($class); ?></h4>
                                    <p class="text-xs text-gray-500"><?php echo $classTotal; ?> Schüler</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="inline-flex items-center px-3 py-1.5 rounded-xl text-sm font-bold bg-<?php echo $colorConfig[0]; ?>-50 text-<?php echo $colorConfig[0]; ?>-700 border border-<?php echo $colorConfig[0]; ?>-200">
                                    <?php echo $percentage; ?>%
                                </span>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-3 gap-3 text-center mb-4">
                            <div class="bg-gray-50 rounded-xl py-2 px-3">
                                <div class="text-lg font-bold text-gray-800"><?php echo $classTotal; ?></div>
                                <div class="text-xs text-gray-500">Gesamt</div>
                            </div>
                            <div class="bg-emerald-50 rounded-xl py-2 px-3">
                                <div class="text-lg font-bold text-emerald-600"><?php echo $classComplete; ?></div>
                                <div class="text-xs text-emerald-700">Fertig</div>
                            </div>
                            <div class="bg-red-50 rounded-xl py-2 px-3">
                                <div class="text-lg font-bold text-red-600"><?php echo $classTotal - $classComplete; ?></div>
                                <div class="text-xs text-red-700">Offen</div>
                            </div>
                        </div>
                        
                        <div class="flex gap-2">
                            <a href="?page=teacher-class-list&class=<?php echo urlencode($class); ?>" 
                               class="flex-1 text-center px-4 py-2.5 bg-gradient-to-r from-blue-500 to-indigo-500 text-white rounded-xl hover:shadow-lg hover:shadow-blue-500/30 transition-all text-sm font-bold">
                                <i class="fas fa-list mr-1.5"></i>Liste
                            </a>
                            <a href="?page=admin-print&type=class&class=<?php echo urlencode($class); ?>" 
                               target="_blank"
                               class="flex-1 text-center px-4 py-2.5 bg-gradient-to-r from-slate-600 to-gray-700 text-white rounded-xl hover:shadow-lg transition-all text-sm font-bold">
                                <i class="fas fa-print mr-1.5"></i>Drucken
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="bg-white rounded-3xl shadow-xl overflow-hidden border border-gray-100 animate-fadeInUp" style="animation-delay: 300ms;">
        <div class="bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 text-white px-8 py-6 relative overflow-hidden">
            <div class="absolute inset-0 bg-white/10 opacity-30" style="background-image: url('data:image/svg+xml,%3Csvg width=\"40\" height=\"40\" viewBox=\"0 0 40 40\" xmlns=\"http://www.w3.org/2000/svg\"%3E%3Cg fill=\"%23ffffff\" fill-opacity=\"0.3\" fill-rule=\"evenodd\"%3E%3Cpath d=\"M0 40L40 0H20L0 20M40 40V20L20 40\"/%3E%3C/g%3E%3C/svg%3E');"></div>
            <div class="relative flex items-center gap-4">
                <div class="w-14 h-14 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center">
                    <i class="fas fa-history text-2xl"></i>
                </div>
                <div>
                    <h3 class="text-2xl font-extrabold font-display">Letzte Anmeldungen</h3>
                    <p class="text-blue-100">Aktuelle Schüleraktivitäten</p>
                </div>
            </div>
        </div>

        <div class="p-6">
            <?php
            $stmt = $db->query("
                SELECT r.*, u.firstname, u.lastname, u.class, e.name as exhibitor_name, t.slot_name
                FROM registrations r
                JOIN users u ON r.user_id = u.id
                JOIN exhibitors e ON r.exhibitor_id = e.id
                JOIN timeslots t ON r.timeslot_id = t.id
                WHERE u.role = 'student'
                ORDER BY r.registered_at DESC
                LIMIT 20
            ");
            $recentRegistrations = $stmt->fetchAll();
            ?>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gradient-to-r from-gray-50 to-slate-100 border-b border-gray-200">
                        <tr>
                            <th class="px-5 py-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Schüler</th>
                            <th class="px-5 py-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Klasse</th>
                            <th class="px-5 py-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Aussteller</th>
                            <th class="px-5 py-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Zeitslot</th>
                            <th class="px-5 py-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Zeitpunkt</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($recentRegistrations as $index => $reg): ?>
                        <tr class="table-row">
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-500 rounded-xl flex items-center justify-center text-white font-bold text-xs shadow-md">
                                        <?php echo strtoupper(substr($reg['firstname'], 0, 1) . substr($reg['lastname'], 0, 1)); ?>
                                    </div>
                                    <span class="font-semibold text-gray-800"><?php echo htmlspecialchars($reg['firstname'] . ' ' . $reg['lastname']); ?></span>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <span class="inline-flex items-center px-3 py-1.5 rounded-xl text-xs font-bold bg-gray-100 text-gray-700">
                                    <?php echo htmlspecialchars($reg['class'] ?: '-'); ?>
                                </span>
                            </td>
                            <td class="px-5 py-4 font-medium text-gray-800">
                                <?php echo htmlspecialchars($reg['exhibitor_name']); ?>
                            </td>
                            <td class="px-5 py-4">
                                <span class="inline-flex items-center px-3 py-1.5 rounded-xl text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                    <i class="far fa-clock mr-1.5"></i><?php echo htmlspecialchars($reg['slot_name']); ?>
                                </span>
                            </td>
                            <td class="px-5 py-4 text-sm text-gray-500">
                                <i class="far fa-calendar-alt mr-1 text-gray-400"></i>
                                <?php echo formatDateTime($reg['registered_at']); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Tips Box -->
    <div class="bg-gradient-to-br from-amber-50 to-orange-50 border border-amber-200 rounded-3xl p-8 shadow-lg animate-fadeInUp" style="animation-delay: 400ms;">
        <div class="flex items-start gap-5">
            <div class="w-16 h-16 bg-gradient-to-br from-amber-400 to-orange-500 rounded-2xl flex items-center justify-center shadow-lg flex-shrink-0">
                <i class="fas fa-lightbulb text-white text-2xl"></i>
            </div>
            <div>
                <h4 class="font-extrabold text-xl text-amber-900 font-display mb-4">💡 Tipps für Lehrer</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="flex items-start gap-3 bg-white/60 rounded-xl p-4 border border-amber-100">
                        <i class="fas fa-check-circle text-amber-500 mt-0.5"></i>
                        <span class="text-sm text-amber-800">Nutzen Sie die Klassenlisten für den Überblick</span>
                    </div>
                    <div class="flex items-start gap-3 bg-white/60 rounded-xl p-4 border border-amber-100">
                        <i class="fas fa-check-circle text-amber-500 mt-0.5"></i>
                        <span class="text-sm text-amber-800">Drucken Sie Pläne für den Unterricht aus</span>
                    </div>
                    <div class="flex items-start gap-3 bg-white/60 rounded-xl p-4 border border-amber-100">
                        <i class="fas fa-check-circle text-amber-500 mt-0.5"></i>
                        <span class="text-sm text-amber-800">Sprechen Sie Schüler mit fehlenden Anmeldungen an</span>
                    </div>
                    <div class="flex items-start gap-3 bg-white/60 rounded-xl p-4 border border-amber-100">
                        <i class="fas fa-check-circle text-amber-500 mt-0.5"></i>
                        <span class="text-sm text-amber-800">Bei Fragen kontaktieren Sie die Administratoren</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
