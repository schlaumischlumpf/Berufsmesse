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
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="bg-white rounded-xl p-6 border-l-4 border-green-600">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">
                    <i class="fas fa-chalkboard-teacher text-green-600 mr-3"></i>
                    Lehrer Dashboard
                </h2>
                <p class="text-gray-600">Übersicht über Schüleranmeldungen und Klassenpläne</p>
            </div>
            <div class="text-right">
                <div class="text-sm text-gray-600 mb-1">Angemeldet als</div>
                <div class="text-lg font-semibold text-gray-800">
                    <?php echo htmlspecialchars($_SESSION['firstname'] . ' ' . $_SESSION['lastname']); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-sm mb-1">Gesamt Schüler</p>
                    <p class="text-3xl font-bold"><?php echo $stats['total_students']; ?></p>
                </div>
                <i class="fas fa-user-graduate text-3xl opacity-80"></i>
            </div>
        </div>

        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-100 text-sm mb-1">Vollständig angemeldet</p>
                    <p class="text-3xl font-bold"><?php echo $stats['complete_students']; ?></p>
                    <p class="text-xs text-green-100 mt-1">
                        <?php echo $stats['total_students'] > 0 ? round(($stats['complete_students'] / $stats['total_students']) * 100) : 0; ?>% Abdeckung
                    </p>
                </div>
                <i class="fas fa-check-circle text-3xl opacity-80"></i>
            </div>
        </div>

        <div class="bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-xl p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-yellow-100 text-sm mb-1">Unvollständig</p>
                    <p class="text-3xl font-bold"><?php echo $stats['incomplete_students']; ?></p>
                    <p class="text-xs text-yellow-100 mt-1">Fehlen noch Slots</p>
                </div>
                <i class="fas fa-exclamation-triangle text-3xl opacity-80"></i>
            </div>
        </div>

        <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-xl p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-red-100 text-sm mb-1">Ohne Anmeldung</p>
                    <p class="text-3xl font-bold"><?php echo $stats['no_registrations']; ?></p>
                    <p class="text-xs text-red-100 mt-1">Benötigen Beratung</p>
                </div>
                <i class="fas fa-user-times text-3xl opacity-80"></i>
            </div>
        </div>
    </div>

    <!-- Class Overview -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="bg-gradient-to-r from-green-600 to-teal-600 text-white px-6 py-4">
            <h3 class="text-xl font-bold flex items-center">
                <i class="fas fa-users mr-3"></i>
                Klassenübersicht
            </h3>
        </div>

        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($classes as $class): 
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
                    $colorClasses = [
                        'green' => 80,
                        'yellow' => 50,
                        'red' => 0
                    ];
                    $colorClass = 'red';
                    foreach ($colorClasses as $color => $threshold) {
                        if ($percentage >= $threshold) {
                            $colorClass = $color;
                            break;
                        }
                    }
                ?>
                <div class="bg-gray-50 rounded-lg p-4 border-2 border-gray-200 hover:border-<?php echo $colorClass; ?>-400 transition">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-bold text-gray-800 text-lg"><?php echo htmlspecialchars($class); ?></h4>
                        <span class="px-3 py-1 bg-<?php echo $colorClass; ?>-100 text-<?php echo $colorClass; ?>-800 rounded-full text-xs font-semibold">
                            <?php echo $percentage; ?>%
                        </span>
                    </div>
                    <div class="text-sm text-gray-600 space-y-1">
                        <div class="flex justify-between">
                            <span>Gesamt:</span>
                            <span class="font-semibold"><?php echo $classTotal; ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span>Vollständig:</span>
                            <span class="font-semibold text-green-600"><?php echo $classComplete; ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span>Fehlend:</span>
                            <span class="font-semibold text-red-600"><?php echo $classTotal - $classComplete; ?></span>
                        </div>
                    </div>
                    <div class="mt-3 flex gap-2">
                        <a href="?page=teacher-class-list&class=<?php echo urlencode($class); ?>" 
                           class="flex-1 text-center px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-xs font-semibold">
                            <i class="fas fa-list mr-1"></i>Liste
                        </a>
                        <a href="?page=admin-print&type=class&class=<?php echo urlencode($class); ?>" 
                           target="_blank"
                           class="flex-1 text-center px-3 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition text-xs font-semibold">
                            <i class="fas fa-print mr-1"></i>Drucken
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-6 py-4">
            <h3 class="text-xl font-bold flex items-center">
                <i class="fas fa-history mr-3"></i>
                Letzte Anmeldungen
            </h3>
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
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Schüler</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Klasse</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Aussteller</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Zeitslot</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Zeitpunkt</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($recentRegistrations as $reg): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-800">
                                <?php echo htmlspecialchars($reg['firstname'] . ' ' . $reg['lastname']); ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                <?php echo htmlspecialchars($reg['class'] ?: '-'); ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-800">
                                <?php echo htmlspecialchars($reg['exhibitor_name']); ?>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-semibold">
                                    <?php echo htmlspecialchars($reg['slot_name']); ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                <?php echo formatDateTime($reg['registered_at']); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Info Box -->
    <div class="bg-yellow-50 border-l-4 border-yellow-500 p-6 rounded-lg">
        <div class="flex items-start">
            <i class="fas fa-lightbulb text-yellow-500 text-xl mr-3 mt-1"></i>
            <div>
                <h4 class="font-bold text-yellow-900 mb-2">Tipps für Lehrer</h4>
                <ul class="text-sm text-yellow-800 space-y-1">
                    <li><i class="fas fa-check mr-2"></i>Nutzen Sie die Klassenlisten, um den Überblick über Schüleranmeldungen zu behalten</li>
                    <li><i class="fas fa-check mr-2"></i>Drucken Sie die Pläne aus, um sie im Unterricht zu besprechen</li>
                    <li><i class="fas fa-check mr-2"></i>Sprechen Sie Schüler mit fehlenden Anmeldungen an</li>
                    <li><i class="fas fa-check mr-2"></i>Bei Fragen zur Registrierung wenden Sie sich an die Administratoren</li>
                </ul>
            </div>
        </div>
    </div>
</div>
