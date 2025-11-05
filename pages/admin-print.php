<?php
// Admin Druckfunktion für verschiedene Pläne

// Verschiedene Druckoptionen
$printType = $_GET['type'] ?? 'all';
$filterClass = $_GET['class'] ?? '';
$filterRoom = $_GET['room'] ?? '';

// Basisdaten laden
if ($printType === 'all' || $printType === 'class') {
    // Alle Registrierungen mit Schülerdaten
    $query = "
        SELECT 
            u.firstname, u.lastname, u.class,
            e.name as exhibitor_name,
            t.slot_name, t.slot_number, t.start_time, t.end_time,
            r.room_number
        FROM registrations reg
        JOIN users u ON reg.user_id = u.id
        JOIN exhibitors e ON reg.exhibitor_id = e.id
        JOIN timeslots t ON reg.timeslot_id = t.id
        LEFT JOIN rooms r ON e.room_id = r.id
        WHERE u.role = 'student'
    ";
    
    if ($filterClass) {
        $query .= " AND u.class = " . $db->quote($filterClass);
    }
    
    $query .= " ORDER BY u.class, u.lastname, u.firstname, t.slot_number";
    
    $stmt = $db->query($query);
    $registrations = $stmt->fetchAll();
} elseif ($printType === 'rooms') {
    // Raum-basierte Ansicht
    $query = "
        SELECT 
            r.room_number, r.room_name, r.building,
            e.name as exhibitor_name,
            t.slot_name, t.slot_number, t.start_time, t.end_time,
            u.firstname, u.lastname, u.class
        FROM registrations reg
        JOIN users u ON reg.user_id = u.id
        JOIN exhibitors e ON reg.exhibitor_id = e.id
        JOIN timeslots t ON reg.timeslot_id = t.id
        JOIN rooms r ON e.room_id = r.id
    ";
    
    if ($filterRoom) {
        $query .= " WHERE r.id = " . intval($filterRoom);
    }
    
    $query .= " ORDER BY r.room_number, t.slot_number, u.lastname, u.firstname";
    
    $stmt = $db->query($query);
    $registrations = $stmt->fetchAll();
}

// Alle Klassen für Filter
$stmt = $db->query("SELECT DISTINCT class FROM users WHERE role = 'student' AND class IS NOT NULL AND class != '' ORDER BY class");
$classes = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Alle Räume für Filter
$stmt = $db->query("SELECT id, room_number, room_name FROM rooms ORDER BY room_number");
$rooms = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Berufsmesse - Druckansicht</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            .page-break { page-break-after: always; }
        }
    </style>
</head>
<body class="bg-white p-8">
    <!-- Header & Controls (No Print) -->
    <div class="no-print mb-6 bg-gray-100 p-6 rounded-lg">
        <div class="flex justify-between items-center mb-4">
            <h1 class="text-2xl font-bold text-gray-800">
                <i class="fas fa-print mr-2"></i>Druckansicht
            </h1>
            <div class="flex gap-3">
                <button onclick="window.print()" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition">
                    <i class="fas fa-print mr-2"></i>Drucken
                </button>
                <a href="?page=admin-dashboard" class="bg-gray-600 text-white px-6 py-2 rounded-lg hover:bg-gray-700 transition">
                    <i class="fas fa-arrow-left mr-2"></i>Zurück
                </a>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Drucktyp</label>
                <select onchange="window.location.href='?page=admin-print&type='+this.value" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    <option value="all" <?php echo $printType === 'all' ? 'selected' : ''; ?>>Gesamte Veranstaltung</option>
                    <option value="class" <?php echo $printType === 'class' ? 'selected' : ''; ?>>Nach Klasse</option>
                    <option value="rooms" <?php echo $printType === 'rooms' ? 'selected' : ''; ?>>Nach Raum</option>
                </select>
            </div>
            
            <?php if ($printType === 'class'): ?>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Klasse filtern</label>
                <select onchange="window.location.href='?page=admin-print&type=class&class='+this.value" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    <option value="">Alle Klassen</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?php echo htmlspecialchars($class); ?>" <?php echo $filterClass === $class ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($class); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <?php if ($printType === 'rooms'): ?>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Raum filtern</label>
                <select onchange="window.location.href='?page=admin-print&type=rooms&room='+this.value" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    <option value="">Alle Räume</option>
                    <?php foreach ($rooms as $room): ?>
                        <option value="<?php echo $room['id']; ?>" <?php echo $filterRoom == $room['id'] ? 'selected' : ''; ?>>
                            Raum <?php echo htmlspecialchars($room['room_number']); ?>
                            <?php echo $room['room_name'] ? ' - ' . htmlspecialchars($room['room_name']) : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Print Content -->
    <div class="print-content">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Berufsmesse <?php echo date('Y'); ?></h1>
            <p class="text-lg text-gray-600">
                <?php 
                if ($printType === 'all') echo 'Gesamte Veranstaltung';
                elseif ($printType === 'class') echo $filterClass ? "Klasse: $filterClass" : 'Alle Klassen';
                elseif ($printType === 'rooms') {
                    if ($filterRoom) {
                        $roomInfo = array_filter($rooms, fn($r) => $r['id'] == $filterRoom)[0];
                        echo 'Raum ' . htmlspecialchars($roomInfo['room_number']);
                    } else {
                        echo 'Alle Räume';
                    }
                }
                ?>
            </p>
            <p class="text-sm text-gray-500">Erstellt am <?php echo formatDateTime(date('Y-m-d H:i:s')); ?></p>
        </div>

        <?php if ($printType === 'all' || $printType === 'class'): ?>
            <!-- Gruppiert nach Klasse -->
            <?php
            $groupedByClass = [];
            foreach ($registrations as $reg) {
                $class = $reg['class'] ?: 'Keine Klasse';
                if (!isset($groupedByClass[$class])) {
                    $groupedByClass[$class] = [];
                }
                $studentKey = $reg['lastname'] . ', ' . $reg['firstname'];
                if (!isset($groupedByClass[$class][$studentKey])) {
                    $groupedByClass[$class][$studentKey] = [];
                }
                $groupedByClass[$class][$studentKey][] = $reg;
            }
            ksort($groupedByClass);
            ?>

            <?php foreach ($groupedByClass as $class => $students): ?>
                <div class="mb-8 page-break">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4 border-b-2 border-gray-300 pb-2">
                        <?php echo htmlspecialchars($class); ?>
                    </h2>
                    
                    <?php foreach ($students as $studentName => $regs): ?>
                        <div class="mb-6 bg-gray-50 p-4 rounded">
                            <h3 class="font-bold text-lg text-gray-900 mb-3"><?php echo htmlspecialchars($studentName); ?></h3>
                            <table class="w-full text-sm">
                                <thead class="bg-gray-200">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Zeitslot</th>
                                        <th class="px-3 py-2 text-left">Zeit</th>
                                        <th class="px-3 py-2 text-left">Aussteller</th>
                                        <th class="px-3 py-2 text-left">Raum</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white">
                                    <?php 
                                    usort($regs, fn($a, $b) => $a['slot_number'] <=> $b['slot_number']);
                                    foreach ($regs as $reg): 
                                    ?>
                                        <tr class="border-b border-gray-200">
                                            <td class="px-3 py-2 font-semibold"><?php echo htmlspecialchars($reg['slot_name']); ?></td>
                                            <td class="px-3 py-2"><?php echo date('H:i', strtotime($reg['start_time'])) . ' - ' . date('H:i', strtotime($reg['end_time'])); ?></td>
                                            <td class="px-3 py-2"><?php echo htmlspecialchars($reg['exhibitor_name']); ?></td>
                                            <td class="px-3 py-2"><?php echo htmlspecialchars($reg['room_number'] ?: '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

        <?php elseif ($printType === 'rooms'): ?>
            <!-- Gruppiert nach Raum und Zeitslot -->
            <?php
            $groupedByRoom = [];
            foreach ($registrations as $reg) {
                $roomKey = $reg['room_number'];
                $slotKey = $reg['slot_number'];
                if (!isset($groupedByRoom[$roomKey])) {
                    $groupedByRoom[$roomKey] = [];
                }
                if (!isset($groupedByRoom[$roomKey][$slotKey])) {
                    $groupedByRoom[$roomKey][$slotKey] = [
                        'slot_info' => $reg,
                        'students' => []
                    ];
                }
                $groupedByRoom[$roomKey][$slotKey]['students'][] = $reg;
            }
            ksort($groupedByRoom);
            ?>

            <?php foreach ($groupedByRoom as $roomNum => $slots): ?>
                <div class="mb-8 page-break">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4 border-b-2 border-gray-300 pb-2">
                        Raum <?php echo htmlspecialchars($roomNum); ?>
                    </h2>
                    
                    <?php 
                    ksort($slots);
                    foreach ($slots as $slotNum => $slotData): 
                        $info = $slotData['slot_info'];
                    ?>
                        <div class="mb-6">
                            <h3 class="font-bold text-lg text-gray-900 mb-2 bg-blue-100 p-2 rounded">
                                <?php echo htmlspecialchars($info['slot_name']); ?> 
                                (<?php echo date('H:i', strtotime($info['start_time'])) . ' - ' . date('H:i', strtotime($info['end_time'])); ?>)
                                - <?php echo htmlspecialchars($info['exhibitor_name']); ?>
                            </h3>
                            <table class="w-full text-sm">
                                <thead class="bg-gray-200">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Nr.</th>
                                        <th class="px-3 py-2 text-left">Name</th>
                                        <th class="px-3 py-2 text-left">Klasse</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white">
                                    <?php 
                                    usort($slotData['students'], fn($a, $b) => strcmp($a['lastname'], $b['lastname']));
                                    foreach ($slotData['students'] as $idx => $student): 
                                    ?>
                                        <tr class="border-b border-gray-200">
                                            <td class="px-3 py-2 font-semibold"><?php echo $idx + 1; ?></td>
                                            <td class="px-3 py-2"><?php echo htmlspecialchars($student['lastname'] . ', ' . $student['firstname']); ?></td>
                                            <td class="px-3 py-2"><?php echo htmlspecialchars($student['class'] ?: '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <div class="mt-8 text-center text-sm text-gray-500 border-t border-gray-300 pt-4">
        <p>Berufsmesse <?php echo date('Y'); ?> - Erstellt am <?php echo formatDateTime(date('Y-m-d H:i:s')); ?></p>
    </div>
</body>
</html>
