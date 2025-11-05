<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

requireLogin();

$db = getDB();

// Auto-Assign durchführen wenn aufgerufen (VOR jeglichem HTML-Output!)
if (isset($_GET['auto_assign']) && $_GET['auto_assign'] === 'run' && isAdmin()) {
    // Direkt die API-Logik ausführen
    try {
        // Verwaltete Slots (nur 1, 3, 5)
        $managedSlots = [1, 3, 5];
        
        $assignedCount = 0;
        $errors = [];
        
        // Alle aktiven Schüler laden
        $stmt = $db->query("SELECT id FROM users WHERE role = 'student'");
        $students = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($students as $studentId) {
            // Prüfen, für welche verwalteten Slots der Schüler bereits registriert ist
            $stmt = $db->prepare("
                SELECT t.slot_number 
                FROM registrations r
                JOIN timeslots t ON r.timeslot_id = t.id
                WHERE r.user_id = ? AND t.slot_number IN (1, 3, 5)
            ");
            $stmt->execute([$studentId]);
            $registeredSlots = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Anzahl der aktuellen Registrierungen prüfen
            $currentRegCount = count($registeredSlots);
            
            // Wenn Schüler bereits 3 oder mehr Slots hat, überspringen
            if ($currentRegCount >= 3) {
                continue;
            }
            
            // Fehlende Slots ermitteln (nur so viele wie noch fehlen bis 3)
            $missingSlots = array_diff($managedSlots, $registeredSlots);
            $slotsToAssign = array_slice($missingSlots, 0, 3 - $currentRegCount);
            
            if (empty($slotsToAssign)) {
                continue; // Schüler hat alle 3 Slots
            }
            
            // Für jeden fehlenden Slot den Aussteller mit den wenigsten Teilnehmern finden
            foreach ($slotsToAssign as $slotNumber) {
                // Timeslot ID ermitteln
                $stmt = $db->prepare("SELECT id FROM timeslots WHERE slot_number = ?");
                $stmt->execute([$slotNumber]);
                $timeslotId = $stmt->fetchColumn();
                
                if (!$timeslotId) {
                    $errors[] = "Slot $slotNumber nicht gefunden";
                    continue;
                }
                
                // Aussteller mit wenigsten Teilnehmern in diesem Slot finden
                // die noch nicht ihre Kapazität erreicht haben
                // Kapazität kommt vom Raum: capacity / 3 pro Slot
                $stmt = $db->prepare("
                    SELECT e.id, e.name, e.room_id, r.capacity,
                           FLOOR(r.capacity / 3) as slots_per_timeslot,
                           COUNT(DISTINCT reg.user_id) as current_count
                    FROM exhibitors e
                    LEFT JOIN rooms r ON e.room_id = r.id
                    LEFT JOIN registrations reg ON e.id = reg.exhibitor_id AND reg.timeslot_id = ?
                    WHERE e.active = 1 AND e.room_id IS NOT NULL AND r.capacity IS NOT NULL
                    GROUP BY e.id
                    HAVING current_count < FLOOR(r.capacity / 3)
                    ORDER BY current_count ASC, RAND()
                    LIMIT 1
                ");
                $stmt->execute([$timeslotId]);
                $exhibitor = $stmt->fetch();
                
                if (!$exhibitor) {
                    $errors[] = "Kein verfügbarer Aussteller für Slot $slotNumber (Schüler ID: $studentId)";
                    continue;
                }
                
                // Prüfen, ob Schüler bereits bei diesem Aussteller in einem anderen Slot ist
                $stmt = $db->prepare("
                    SELECT COUNT(*) 
                    FROM registrations 
                    WHERE user_id = ? AND exhibitor_id = ?
                ");
                $stmt->execute([$studentId, $exhibitor['id']]);
                $alreadyRegistered = $stmt->fetchColumn();
                
                if ($alreadyRegistered > 0) {
                    // Schüler ist bereits bei diesem Aussteller - nächsten suchen
                    $stmt = $db->prepare("
                        SELECT e.id, e.name, e.room_id, r.capacity,
                               FLOOR(r.capacity / 3) as slots_per_timeslot,
                               COUNT(DISTINCT reg.user_id) as current_count
                        FROM exhibitors e
                        LEFT JOIN rooms r ON e.room_id = r.id
                        LEFT JOIN registrations reg ON e.id = reg.exhibitor_id AND reg.timeslot_id = ?
                        WHERE e.active = 1 
                          AND e.room_id IS NOT NULL 
                          AND r.capacity IS NOT NULL
                          AND e.id NOT IN (
                              SELECT exhibitor_id FROM registrations WHERE user_id = ?
                          )
                        GROUP BY e.id
                        HAVING current_count < FLOOR(r.capacity / 3)
                        ORDER BY current_count ASC, RAND()
                        LIMIT 1
                    ");
                    $stmt->execute([$timeslotId, $studentId]);
                    $exhibitor = $stmt->fetch();
                    
                    if (!$exhibitor) {
                        $errors[] = "Kein alternativer Aussteller für Slot $slotNumber (Schüler ID: $studentId)";
                        continue;
                    }
                }
                
                // Registrierung erstellen
                $stmt = $db->prepare("
                    INSERT INTO registrations (user_id, exhibitor_id, timeslot_id, registration_type)
                    VALUES (?, ?, ?, 'automatic')
                ");
                
                if ($stmt->execute([$studentId, $exhibitor['id'], $timeslotId])) {
                    $assignedCount++;
                } else {
                    $errors[] = "Fehler bei Zuweisung: Schüler $studentId zu " . $exhibitor['name'] . " (Slot $slotNumber)";
                }
            }
        }
        
        // Statistik erstellen
        $stmt = $db->query("
            SELECT COUNT(DISTINCT user_id) as complete
            FROM (
                SELECT r.user_id, COUNT(DISTINCT t.slot_number) as slot_count
                FROM registrations r
                JOIN timeslots t ON r.timeslot_id = t.id
                WHERE t.slot_number IN (1, 3, 5)
                GROUP BY r.user_id
                HAVING slot_count < 3
            ) as incomplete_registrations
        ");
        $incompleteStudents = $stmt->fetchColumn();
        
        $_SESSION['auto_assign_success'] = true;
        $_SESSION['auto_assign_count'] = $assignedCount;
        $_SESSION['auto_assign_students'] = $incompleteStudents;
        $_SESSION['auto_assign_errors'] = $errors;
        
    } catch (Exception $e) {
        $_SESSION['auto_assign_error'] = 'Fehler bei der automatischen Zuteilung: ' . $e->getMessage();
    }
    
    header('Location: ?page=admin-dashboard&auto_assign=done');
    exit;
}

// Aussteller laden
$stmt = $db->query("SELECT * FROM exhibitors WHERE active = 1 ORDER BY name ASC");
$exhibitors = $stmt->fetchAll();

// Einschreibungen des Benutzers laden
$stmt = $db->prepare("SELECT r.*, e.name as exhibitor_name, t.slot_name 
                      FROM registrations r 
                      JOIN exhibitors e ON r.exhibitor_id = e.id 
                      JOIN timeslots t ON r.timeslot_id = t.id 
                      WHERE r.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$userRegistrations = $stmt->fetchAll();

// Registrierungsstatus prüfen
$regStatus = getRegistrationStatus();
$regStart = getSetting('registration_start');
$regEnd = getSetting('registration_end');

$currentPage = $_GET['page'] ?? 'exhibitors';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Berufsmesse - Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .sidebar-transition {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .modal {
            transition: opacity 0.3s ease-in-out, transform 0.3s ease-in-out;
        }
        
        .modal-content {
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.open {
                transform: translateX(0);
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Mobile Menu Button -->
    <button id="mobileMenuBtn" class="md:hidden fixed top-4 left-4 z-50 bg-blue-600 text-white p-3 rounded-lg shadow-lg transition-opacity duration-300">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <aside id="sidebar" class="sidebar sidebar-transition fixed left-0 top-0 h-full bg-white text-gray-800 w-64 shadow-xl z-40 border-r border-gray-200">
        <div class="p-6">
            <!-- Logo -->
            <div class="flex items-center space-x-3 mb-8">
                <div class="w-12 h-12 bg-blue-600 rounded-lg flex items-center justify-center">
                    <i class="fas fa-briefcase text-white text-xl"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-gray-800">Berufsmesse</h1>
                    <p class="text-xs text-gray-500">2025</p>
                </div>
            </div>

            <!-- User Info -->
            <div class="bg-gray-50 rounded-lg p-4 mb-6 border border-gray-200">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center">
                        <span class="font-bold text-sm text-white"><?php echo strtoupper(substr($_SESSION['firstname'], 0, 1) . substr($_SESSION['lastname'], 0, 1)); ?></span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-sm truncate text-gray-800"><?php echo htmlspecialchars($_SESSION['firstname'] . ' ' . $_SESSION['lastname']); ?></p>
                        <p class="text-xs text-gray-600 truncate">
                            <?php 
                            if (isAdmin()) echo 'Administrator';
                            elseif (isTeacher()) echo 'Lehrer';
                            else echo 'Schüler';
                            ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="space-y-2">
                <?php if (!isTeacher()): ?>
                <a href="?page=exhibitors" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-100 transition <?php echo $currentPage === 'exhibitors' ? 'bg-blue-50 text-blue-600' : 'text-gray-700'; ?>">
                    <i class="fas fa-building w-5"></i>
                    <span>Aussteller</span>
                </a>
                
                <a href="?page=registration" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-100 transition <?php echo $currentPage === 'registration' ? 'bg-blue-50 text-blue-600' : 'text-gray-700'; ?>">
                    <i class="fas fa-clipboard-list w-5"></i>
                    <span>Einschreibung</span>
                </a>
                
                <a href="?page=my-registrations" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-100 transition <?php echo $currentPage === 'my-registrations' ? 'bg-blue-50 text-blue-600' : 'text-gray-700'; ?>">
                    <i class="fas fa-check-circle w-5"></i>
                    <span>Meine Anmeldungen</span>
                </a>

                <a href="?page=schedule" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-100 transition <?php echo $currentPage === 'schedule' ? 'bg-blue-50 text-blue-600' : 'text-gray-700'; ?>">
                    <i class="fas fa-calendar-alt w-5"></i>
                    <span>Zeitplan</span>
                </a>
                <?php endif; ?>

                <?php if (isTeacher() && !isAdmin()): ?>
                <hr class="my-4 border-gray-200">
                
                <!-- Teacher Section -->
                <div class="mb-3 px-3">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Lehrer-Bereich</p>
                </div>
                
                <a href="?page=teacher-dashboard" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-100 transition <?php echo $currentPage === 'teacher-dashboard' ? 'bg-blue-50 text-blue-600' : 'text-gray-700'; ?>">
                    <i class="fas fa-chalkboard-teacher w-5"></i>
                    <span>Dashboard</span>
                </a>
                
                <a href="?page=exhibitors" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-100 transition <?php echo $currentPage === 'exhibitors' ? 'bg-blue-50 text-blue-600' : 'text-gray-700'; ?>">
                    <i class="fas fa-building w-5"></i>
                    <span>Aussteller ansehen</span>
                </a>
                <?php endif; ?>

                <?php if (isAdmin()): ?>
                <hr class="my-4 border-gray-200">
                
                <!-- Admin Section -->
                <div class="mb-3 px-3">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Administration</p>
                </div>
                
                <a href="?page=admin-dashboard" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-100 transition <?php echo $currentPage === 'admin-dashboard' ? 'bg-blue-50 text-blue-600' : 'text-gray-700'; ?>">
                    <i class="fas fa-tachometer-alt w-5"></i>
                    <span>Dashboard</span>
                </a>
                
                <a href="?page=admin-exhibitors" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-100 transition <?php echo $currentPage === 'admin-exhibitors' ? 'bg-blue-50 text-blue-600' : 'text-gray-700'; ?>">
                    <i class="fas fa-building w-5"></i>
                    <span>Aussteller verwalten</span>
                </a>
                
                <a href="?page=admin-rooms" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-100 transition <?php echo $currentPage === 'admin-rooms' ? 'bg-blue-50 text-blue-600' : 'text-gray-700'; ?>">
                    <i class="fas fa-map-marker-alt w-5"></i>
                    <span>Raum-Zuteilung</span>
                </a>
                
                <a href="?page=admin-room-capacities" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-100 transition <?php echo $currentPage === 'admin-room-capacities' ? 'bg-blue-50 text-blue-600' : 'text-gray-700'; ?>">
                    <i class="fas fa-table w-5"></i>
                    <span>Slot-Kapazitäten</span>
                </a>
                
                <a href="?page=admin-users" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-100 transition <?php echo $currentPage === 'admin-users' ? 'bg-blue-50 text-blue-600' : 'text-gray-700'; ?>">
                    <i class="fas fa-users w-5"></i>
                    <span>Nutzerverwaltung</span>
                </a>
                
                <a href="?page=admin-settings" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-100 transition <?php echo $currentPage === 'admin-settings' ? 'bg-blue-50 text-blue-600' : 'text-gray-700'; ?>">
                    <i class="fas fa-cog w-5"></i>
                    <span>Einstellungen</span>
                </a>
                <?php endif; ?>
            </nav>

            <!-- Logout -->
            <div class="absolute bottom-6 left-6 right-6">
                <a href="logout.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg bg-red-50 hover:bg-red-100 transition text-red-600">
                    <i class="fas fa-sign-out-alt w-5"></i>
                    <span>Abmelden</span>
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="md:ml-64 min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm sticky top-0 z-30">
            <div class="px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex items-center justify-between">
                    <div class="ml-12 md:ml-0">
                        <h2 class="text-2xl font-bold text-gray-800">
                            <?php 
                            $titles = [
                                'exhibitors' => 'Aussteller',
                                'registration' => 'Einschreibung',
                                'my-registrations' => 'Meine Anmeldungen',
                                'schedule' => 'Zeitplan',
                                'teacher-dashboard' => 'Lehrer Dashboard',
                                'teacher-class-list' => 'Klassenliste',
                                'admin-dashboard' => 'Admin Dashboard',
                                'admin-exhibitors' => 'Aussteller verwalten',
                                'admin-rooms' => 'Raum-Zuteilung',
                                'admin-room-capacities' => 'Slot-Kapazitäten',
                                'admin-users' => 'Nutzerverwaltung',
                                'admin-settings' => 'Einstellungen'
                            ];
                            echo $titles[$currentPage] ?? 'Dashboard';
                            ?>
                        </h2>
                        <p class="text-sm text-gray-600 mt-1">
                            <?php echo formatDate(date('Y-m-d')); ?>
                        </p>
                    </div>
                    
                    <!-- Registration Status Badge -->
                    <div class="hidden sm:block">
                        <?php if ($regStatus === 'open'): ?>
                            <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold bg-green-100 text-green-800">
                                <i class="fas fa-circle text-green-500 mr-2 text-xs animate-pulse"></i>
                                Einschreibung offen
                            </span>
                        <?php elseif ($regStatus === 'upcoming'): ?>
                            <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold bg-yellow-100 text-yellow-800">
                                <i class="fas fa-clock mr-2"></i>
                                Bald verfügbar
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold bg-red-100 text-red-800">
                                <i class="fas fa-lock mr-2"></i>
                                Einschreibung geschlossen
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </header>

        <!-- Content Area -->
        <div class="p-4 sm:p-6 lg:p-8">
            <?php
            // Seiten-Content laden
            switch ($currentPage) {
                case 'exhibitors':
                    include 'pages/exhibitors.php';
                    break;
                case 'registration':
                    include 'pages/registration.php';
                    break;
                case 'my-registrations':
                    include 'pages/my-registrations.php';
                    break;
                case 'schedule':
                    include 'pages/schedule.php';
                    break;
                case 'teacher-dashboard':
                    if (isTeacher() || isAdmin()) include 'pages/teacher-dashboard.php';
                    break;
                case 'teacher-class-list':
                    if (isTeacher() || isAdmin()) include 'pages/teacher-class-list.php';
                    break;
                case 'admin-dashboard':
                    if (isAdmin()) include 'pages/admin-dashboard.php';
                    break;
                case 'admin-exhibitors':
                    if (isAdmin()) include 'pages/admin-exhibitors.php';
                    break;
                case 'admin-rooms':
                    if (isAdmin()) include 'pages/admin-rooms.php';
                    break;
                case 'admin-room-capacities':
                    if (isAdmin()) include 'pages/admin-room-capacities.php';
                    break;
                case 'admin-users':
                    if (isAdmin()) include 'pages/admin-users.php';
                    break;
                case 'admin-print':
                    if (isAdmin()) include 'pages/admin-print.php';
                    break;
                case 'admin-settings':
                    if (isAdmin()) include 'pages/admin-settings.php';
                    break;
                default:
                    if (isTeacher()) {
                        include 'pages/teacher-dashboard.php';
                    } else {
                        include 'pages/exhibitors.php';
                    }
            }
            ?>
        </div>
    </main>

    <script>
        // Mobile Menu Toggle
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const sidebar = document.getElementById('sidebar');
        
        mobileMenuBtn.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            // Toggle button visibility when sidebar opens/closes
            if (sidebar.classList.contains('open')) {
                mobileMenuBtn.style.opacity = '0';
                mobileMenuBtn.style.pointerEvents = 'none';
            } else {
                mobileMenuBtn.style.opacity = '1';
                mobileMenuBtn.style.pointerEvents = 'auto';
            }
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', (e) => {
            if (window.innerWidth < 768) {
                if (!sidebar.contains(e.target) && !mobileMenuBtn.contains(e.target)) {
                    sidebar.classList.remove('open');
                    mobileMenuBtn.style.opacity = '1';
                    mobileMenuBtn.style.pointerEvents = 'auto';
                }
            }
        });
        
        // Reset button when window is resized to desktop
        window.addEventListener('resize', () => {
            if (window.innerWidth >= 768) {
                mobileMenuBtn.style.opacity = '1';
                mobileMenuBtn.style.pointerEvents = 'auto';
            }
        });
    </script>
</body>
</html>
