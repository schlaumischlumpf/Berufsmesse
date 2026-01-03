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
            
            // Wenn Schüler bereits MANAGED_SLOTS_COUNT oder mehr Slots hat, überspringen
            if ($currentRegCount >= MANAGED_SLOTS_COUNT) {
                continue;
            }
            
            // Fehlende Slots ermitteln (nur so viele wie noch fehlen bis MANAGED_SLOTS_COUNT)
            $missingSlots = array_diff($managedSlots, $registeredSlots);
            $slotsToAssign = array_slice(array_values($missingSlots), 0, MANAGED_SLOTS_COUNT - $currentRegCount);
            
            if (empty($slotsToAssign)) {
                continue; // Schüler hat alle MANAGED_SLOTS_COUNT Slots
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
                
                // Alle Aussteller mit Raum abrufen
                $stmt = $db->prepare("
                    SELECT e.id, e.name, e.room_id,
                           COUNT(DISTINCT reg.user_id) as current_count
                    FROM exhibitors e
                    LEFT JOIN rooms r ON e.room_id = r.id
                    LEFT JOIN registrations reg ON e.id = reg.exhibitor_id AND reg.timeslot_id = ?
                    WHERE e.active = 1 AND e.room_id IS NOT NULL
                    GROUP BY e.id
                    ORDER BY current_count ASC, RAND()
                ");
                $stmt->execute([$timeslotId]);
                $exhibitors = $stmt->fetchAll();
                
                $exhibitor = null;
                
                // Aussteller finden, der noch Kapazität hat und Schüler noch nicht hat
                foreach ($exhibitors as $ex) {
                    // Prüfen ob Schüler bereits bei diesem Aussteller ist
                    $stmt = $db->prepare("SELECT COUNT(*) FROM registrations WHERE user_id = ? AND exhibitor_id = ?");
                    $stmt->execute([$studentId, $ex['id']]);
                    if ($stmt->fetchColumn() > 0) {
                        continue; // Schüler bereits bei diesem Aussteller
                    }
                    
                    // Kapazität für diesen Slot prüfen (Issue #4)
                    $slotCapacity = getRoomSlotCapacity($ex['room_id'], $timeslotId);
                    if ($slotCapacity > 0 && $ex['current_count'] < $slotCapacity) {
                        $exhibitor = $ex;
                        break;
                    }
                }
                
                if (!$exhibitor) {
                    $errors[] = "Kein verfügbarer Aussteller für Slot $slotNumber (Schüler ID: $studentId)";
                    continue;
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
        
        // Statistik erstellen - Anzahl Schüler mit unvollständigen Anmeldungen
        $stmt = $db->query("
            SELECT COUNT(*) as incomplete
            FROM users u
            WHERE u.role = 'student'
            AND (
                SELECT COUNT(DISTINCT t.slot_number)
                FROM registrations r
                JOIN timeslots t ON r.timeslot_id = t.id
                WHERE r.user_id = u.id AND t.slot_number IN (1, 3, 5)
            ) < " . MANAGED_SLOTS_COUNT . "
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
        :root {
            --color-primary: #10b981;
            --color-primary-hover: #059669;
            --color-text-main: #1f2937;
            --color-text-muted: #6b7280;
            --color-border: #e5e7eb;
            --color-bg: #f9fafb;
            --sidebar-bg: #ffffff;
            --sidebar-text: #6b7280;
            --sidebar-text-active: #10b981;
            --sidebar-hover: #f3f4f6;
            --sidebar-active-bg: #ecfdf5;
            --sidebar-width: 16rem;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            color: var(--color-text-main);
        }

        .sidebar-transition {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .modal {
            transition: opacity 0.3s ease-in-out, transform 0.3s ease-in-out;
        }
        
        .modal-content {
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        /* Custom Scrollbar for Sidebar */
        .sidebar-scroll::-webkit-scrollbar {
            width: 4px;
        }
        .sidebar-scroll::-webkit-scrollbar-track {
            background: transparent;
        }
        .sidebar-scroll::-webkit-scrollbar-thumb {
            background-color: #d1d5db;
            border-radius: 20px;
        }

        /* Compact Sidebar Links */
        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.65rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            color: var(--sidebar-text);
            transition: all 0.2s;
            margin-bottom: 0.25rem;
            font-weight: 500;
        }
        .nav-link:hover {
            background-color: var(--sidebar-hover);
            color: var(--color-text-main);
        }
        .nav-link.active {
            background-color: var(--sidebar-active-bg);
            color: var(--sidebar-text-active);
        }
        .nav-link i {
            width: 1.5rem;
            text-align: center;
            margin-right: 0.75rem;
            font-size: 1rem;
        }

        /* Admin Group */
        .nav-group-title {
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #9ca3af;
            margin: 1.5rem 0 0.5rem 1rem;
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
    <button id="mobileMenuBtn" class="md:hidden fixed top-4 left-4 z-50 bg-white text-gray-600 p-2 rounded-lg shadow-md border border-gray-200 transition-opacity duration-300">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <aside id="sidebar" class="sidebar sidebar-transition fixed left-0 top-0 h-full bg-white border-r border-gray-200 w-64 z-40 flex flex-col">
        <div class="p-6 flex items-center justify-between border-b border-gray-100">
            <!-- Logo -->
            <div class="flex items-center space-x-3">
                <div class="w-8 h-8 bg-emerald-500 rounded-lg flex items-center justify-center shadow-sm">
                    <i class="fas fa-graduation-cap text-white text-sm"></i>
                </div>
                <div>
                    <h1 class="text-lg font-bold text-gray-800 leading-tight">Berufsmesse</h1>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <div class="flex-1 overflow-y-auto sidebar-scroll px-4 py-2">
            <nav class="space-y-1">
                <?php if (!isTeacher()): ?>
                <div class="nav-group-title">Menu</div>
                
                <a href="?page=schedule" class="nav-link <?php echo $currentPage === 'schedule' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Calendar</span>
                </a>

                <a href="?page=exhibitors" class="nav-link <?php echo $currentPage === 'exhibitors' ? 'active' : ''; ?>">
                    <i class="fas fa-briefcase"></i>
                    <span>Companies</span>
                </a>
                
                <a href="?page=registration" class="nav-link <?php echo $currentPage === 'registration' ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Registration</span>
                </a>
                
                <a href="?page=my-registrations" class="nav-link <?php echo $currentPage === 'my-registrations' ? 'active' : ''; ?>">
                    <i class="fas fa-check-circle"></i>
                    <span>My Slots</span>
                </a>
                <?php endif; ?>

                <?php if (isTeacher() && !isAdmin()): ?>
                <div class="nav-group-title">Teacher</div>
                
                <a href="?page=teacher-dashboard" class="nav-link <?php echo $currentPage === 'teacher-dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span>Dashboard</span>
                </a>
                
                <a href="?page=exhibitors" class="nav-link <?php echo $currentPage === 'exhibitors' ? 'active' : ''; ?>">
                    <i class="fas fa-building"></i>
                    <span>Companies</span>
                </a>
                <?php endif; ?>

                <?php if (isAdmin() || hasPermission('manage_exhibitors') || hasPermission('manage_rooms') || hasPermission('manage_settings') || hasPermission('manage_users') || hasPermission('view_reports') || hasPermission('auto_assign')): ?>
                
                <div class="nav-group-title">Administration</div>
                
                <?php if (isAdmin()): ?>
                <a href="?page=admin-dashboard" class="nav-link <?php echo $currentPage === 'admin-dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <?php endif; ?>
                
                <?php if (isAdmin() || hasPermission('manage_exhibitors')): ?>
                <a href="?page=admin-exhibitors" class="nav-link <?php echo $currentPage === 'admin-exhibitors' ? 'active' : ''; ?>">
                    <i class="fas fa-building"></i>
                    <span>Companies</span>
                </a>
                <?php endif; ?>
                
                <?php if (isAdmin() || hasPermission('manage_rooms')): ?>
                <a href="?page=admin-rooms" class="nav-link <?php echo $currentPage === 'admin-rooms' ? 'active' : ''; ?>">
                    <i class="fas fa-map-marker-alt"></i>
                    <span>Rooms</span>
                </a>
                
                <a href="?page=admin-room-capacities" class="nav-link <?php echo $currentPage === 'admin-room-capacities' ? 'active' : ''; ?>">
                    <i class="fas fa-table"></i>
                    <span>Capacities</span>
                </a>
                <?php endif; ?>
                
                <?php if (isAdmin() || hasPermission('manage_users')): ?>
                <a href="?page=admin-users" class="nav-link <?php echo $currentPage === 'admin-users' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                </a>
                <?php endif; ?>
                
                <?php if (isAdmin()): ?>
                <a href="?page=admin-permissions" class="nav-link <?php echo $currentPage === 'admin-permissions' ? 'active' : ''; ?>">
                    <i class="fas fa-shield-alt"></i>
                    <span>Permissions</span>
                </a>
                <?php endif; ?>
                
                <?php if (isAdmin() || hasPermission('manage_settings')): ?>
                <a href="?page=admin-settings" class="nav-link <?php echo $currentPage === 'admin-settings' ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
                <?php endif; ?>
                
                <?php if (isAdmin() || hasPermission('view_reports')): ?>
                <a href="?page=admin-print" class="nav-link <?php echo $currentPage === 'admin-print' ? 'active' : ''; ?>">
                    <i class="fas fa-print"></i>
                    <span>Reports</span>
                </a>
                <?php endif; ?>
                <?php endif; ?>
            </nav>
        </div>

        <!-- User Info Compact -->
        <div class="p-4 border-t border-gray-100 mt-auto">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-full overflow-hidden border-2 border-gray-200">
                    <div class="w-full h-full bg-emerald-500 flex items-center justify-center text-white font-bold text-sm">
                        <?php echo strtoupper(substr($_SESSION['firstname'], 0, 1) . substr($_SESSION['lastname'], 0, 1)); ?>
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-medium text-sm truncate text-gray-800"><?php echo htmlspecialchars($_SESSION['firstname'] . ' ' . $_SESSION['lastname']); ?></p>
                    <p class="text-xs text-gray-500 truncate">
                        <?php echo htmlspecialchars($_SESSION['email'] ?? 'user@example.com'); ?>
                    </p>
                </div>
                <a href="logout.php" class="text-gray-400 hover:text-red-500 transition">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="md:ml-64 min-h-screen">
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
                    if (isAdmin() || hasPermission('manage_exhibitors')) include 'pages/admin-exhibitors.php';
                    break;
                case 'admin-rooms':
                    if (isAdmin() || hasPermission('manage_rooms')) include 'pages/admin-rooms.php';
                    break;
                case 'admin-room-capacities':
                    if (isAdmin() || hasPermission('manage_rooms')) include 'pages/admin-room-capacities.php';
                    break;
                case 'admin-users':
                    if (isAdmin() || hasPermission('manage_users')) include 'pages/admin-users.php';
                    break;
                case 'admin-permissions':
                    if (isAdmin()) include 'pages/admin-permissions.php';
                    break;
                case 'admin-print':
                    if (isAdmin() || hasPermission('view_reports')) include 'pages/admin-print.php';
                    break;
                case 'admin-settings':
                    if (isAdmin() || hasPermission('manage_settings')) include 'pages/admin-settings.php';
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
