<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

requireLogin();

$db = getDB();

// Handle page redirects BEFORE any HTML output
$currentPage = $_GET['page'] ?? 'dashboard';

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
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Berufsmesse - Dashboard</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/tailwind-config.js"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom Design System -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/design-system.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/guided-tour.css">
    
    <style>
        /* ==========================================================================
           Pastel Color Palette - CSS Variables
           ========================================================================== */
        :root {
            /* Pastellfarben */
            --color-pastel-mint: #a8e6cf;
            --color-pastel-mint-light: #d4f5e4;
            --color-pastel-lavender: #c3b1e1;
            --color-pastel-lavender-light: #e8dff5;
            --color-pastel-peach: #ffb7b2;
            --color-pastel-peach-light: #ffdad8;
            --color-pastel-sky: #b5deff;
            --color-pastel-sky-light: #dceeff;
            --color-pastel-butter: #fff3b0;
            --color-pastel-rose: #ffc8dd;
            
            /* Primary Colors */
            --color-primary: var(--color-pastel-mint);
            --color-primary-dark: #6bc4a6;
            --color-secondary: var(--color-pastel-lavender);
            --color-accent: var(--color-pastel-peach);
            
            /* UI Colors */
            --color-text-main: #1f2937;
            --color-text-muted: #6b7280;
            --color-border: #e5e7eb;
            --color-bg: #f9fafb;
            
            /* Sidebar */
            --sidebar-bg: #ffffff;
            --sidebar-text: #6b7280;
            --sidebar-text-active: #6bc4a6;
            --sidebar-hover: #f3f4f6;
            --sidebar-active-bg: #d4f5e4;
            --sidebar-width: 16rem;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            color: var(--color-text-main);
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            min-height: 100vh;
        }

        /* ==========================================================================
           Sidebar Styles with Animations
           ========================================================================== */
        .sidebar-transition {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .modal {
            transition: opacity 0.3s ease-in-out, transform 0.3s ease-in-out;
        }
        
        .modal-content {
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        /* Custom Scrollbar */
        .sidebar-scroll::-webkit-scrollbar {
            width: 4px;
        }
        .sidebar-scroll::-webkit-scrollbar-track {
            background: transparent;
        }
        .sidebar-scroll::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, var(--color-pastel-mint) 0%, var(--color-pastel-lavender) 100%);
            border-radius: 20px;
        }

        /* Navigation Links with Hover Effects */
        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            border-radius: 0.75rem;
            font-size: 0.875rem;
            color: var(--sidebar-text);
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            margin-bottom: 0.25rem;
            font-weight: 500;
            position: relative;
            overflow: hidden;
        }
        
        .nav-link::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 3px;
            background: linear-gradient(180deg, var(--color-pastel-mint) 0%, var(--color-pastel-lavender) 100%);
            border-radius: 0 4px 4px 0;
            transform: scaleY(0);
            transition: transform 0.25s ease;
        }
        
        .nav-link:hover {
            background: linear-gradient(135deg, var(--color-pastel-mint-light) 0%, #ffffff 100%);
            color: var(--color-text-main);
            transform: translateX(4px);
        }
        
        .nav-link:hover::before {
            transform: scaleY(1);
        }
        
        .nav-link.active {
            background: linear-gradient(135deg, var(--color-pastel-mint-light) 0%, var(--color-pastel-lavender-light) 100%);
            color: var(--color-primary-dark);
            box-shadow: 0 4px 12px rgba(168, 230, 207, 0.3);
        }
        
        .nav-link.active::before {
            transform: scaleY(1);
        }
        
        .nav-link i {
            width: 1.5rem;
            text-align: center;
            margin-right: 0.75rem;
            font-size: 1rem;
            transition: transform 0.25s ease;
        }
        
        .nav-link:hover i {
            transform: scale(1.1);
        }

        /* Nav Group Title */
        .nav-group-title {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #9ca3af;
            margin: 1.5rem 0 0.5rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .nav-group-title::after {
            content: '';
            flex: 1;
            height: 1px;
            background: linear-gradient(90deg, var(--color-border) 0%, transparent 100%);
            margin-right: 1rem;
        }

        /* ==========================================================================
           Card Animations
           ========================================================================== */
        .card {
            background: white;
            border-radius: 1rem;
            border: 1px solid var(--color-border);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px -8px rgba(0, 0, 0, 0.1);
            border-color: var(--color-pastel-mint);
        }

        /* ==========================================================================
           Button Animations
           ========================================================================== */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: 0.75rem;
            border: none;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .btn::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.2) 0%, transparent 100%);
            opacity: 0;
            transition: opacity 0.25s ease;
        }
        
        .btn:hover::after {
            opacity: 1;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--color-pastel-mint) 0%, var(--color-primary-dark) 100%);
            color: var(--color-text-main);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(168, 230, 207, 0.4);
        }
        
        .btn-secondary {
            background: white;
            color: var(--color-text-muted);
            border: 1px solid var(--color-border);
        }
        
        .btn-secondary:hover {
            background: var(--color-bg);
            border-color: var(--color-pastel-mint);
            color: var(--color-text-main);
        }

        /* ==========================================================================
           Badge Styles
           ========================================================================== */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 500;
            border-radius: 9999px;
            transition: all 0.2s ease;
        }
        
        .badge-mint {
            background: var(--color-pastel-mint-light);
            color: var(--color-primary-dark);
        }
        
        .badge-lavender {
            background: var(--color-pastel-lavender-light);
            color: #7c3aed;
        }
        
        .badge-peach {
            background: var(--color-pastel-peach-light);
            color: #dc2626;
        }
        
        .badge-sky {
            background: var(--color-pastel-sky-light);
            color: #2563eb;
        }

        /* ==========================================================================
           Responsive Styles
           ========================================================================== */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.open {
                transform: translateX(0);
            }
        }
        
        /* ==========================================================================
           Page Load Animation
           ========================================================================== */
        @keyframes pageSlideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .page-content {
            animation: pageSlideIn 0.4s ease-out;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Mobile Menu Button -->
    <button id="mobileMenuBtn" class="md:hidden fixed top-4 left-4 z-50 bg-white/90 backdrop-blur-sm text-gray-600 p-3 rounded-xl shadow-lg border border-gray-100 transition-all duration-300 hover:bg-white hover:shadow-xl">
        <i class="fas fa-bars text-lg"></i>
    </button>

    <!-- Sidebar -->
    <aside id="sidebar" class="sidebar sidebar-transition fixed left-0 top-0 h-full bg-white/95 backdrop-blur-lg border-r border-gray-100 w-64 z-40 flex flex-col shadow-xl">
        <div class="p-6 flex items-center justify-between border-b border-gray-100">
            <!-- Logo with Gradient -->
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shadow-lg" style="background: linear-gradient(135deg, var(--color-pastel-mint) 0%, var(--color-pastel-lavender) 100%);">
                    <i class="fas fa-graduation-cap text-white text-lg"></i>
                </div>
                <div>
                    <h1 class="text-lg font-bold text-gray-800 leading-tight">Berufsmesse</h1>
                    <p class="text-xs text-gray-400">2026</p>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <div class="flex-1 overflow-y-auto sidebar-scroll px-4 py-4">
            <nav class="space-y-1">
                <?php if (!isTeacher()): ?>
                <div class="nav-group-title">Übersicht</div>
                
                <!-- Dashboard als Startseite -->
                <a href="?page=dashboard" class="nav-link <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>

                <!-- Unternehmen -->
                <a href="?page=exhibitors" class="nav-link <?php echo $currentPage === 'exhibitors' ? 'active' : ''; ?>">
                    <i class="fas fa-building"></i>
                    <span>Unternehmen</span>
                </a>
                <?php endif; ?>

                <?php if (isTeacher() && !isAdmin()): ?>
                <div class="nav-group-title">Lehrer</div>
                
                <a href="?page=teacher-dashboard" class="nav-link <?php echo $currentPage === 'teacher-dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span>Übersicht</span>
                </a>
                
                <a href="?page=exhibitors" class="nav-link <?php echo $currentPage === 'exhibitors' ? 'active' : ''; ?>">
                    <i class="fas fa-building"></i>
                    <span>Unternehmen</span>
                </a>
                <?php endif; ?>

                <?php if (isAdmin() || hasPermission('manage_exhibitors') || hasPermission('manage_rooms') || hasPermission('manage_settings') || hasPermission('manage_users') || hasPermission('view_reports') || hasPermission('auto_assign')): ?>
                
                <div class="nav-group-title">Verwaltung</div>
                
                <?php if (isAdmin()): ?>
                <a href="?page=admin-dashboard" class="nav-link <?php echo $currentPage === 'admin-dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Admin-Dashboard</span>
                </a>
                <?php endif; ?>
                
                <?php if (isAdmin() || hasPermission('manage_exhibitors')): ?>
                <a href="?page=admin-exhibitors" class="nav-link <?php echo $currentPage === 'admin-exhibitors' ? 'active' : ''; ?>">
                    <i class="fas fa-building"></i>
                    <span>Aussteller</span>
                </a>
                <?php endif; ?>
                
                <?php if (isAdmin() || hasPermission('manage_rooms')): ?>
                <a href="?page=admin-rooms" class="nav-link <?php echo $currentPage === 'admin-rooms' ? 'active' : ''; ?>">
                    <i class="fas fa-map-marker-alt"></i>
                    <span>Räume</span>
                </a>
                
                <a href="?page=admin-room-capacities" class="nav-link <?php echo $currentPage === 'admin-room-capacities' ? 'active' : ''; ?>">
                    <i class="fas fa-table"></i>
                    <span>Kapazitäten</span>
                </a>
                <?php endif; ?>
                
                <?php if (isAdmin() || hasPermission('manage_users')): ?>
                <a href="?page=admin-users" class="nav-link <?php echo $currentPage === 'admin-users' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>Benutzer</span>
                </a>
                <?php endif; ?>
                
                <?php if (isAdmin()): ?>
                <a href="?page=admin-permissions" class="nav-link <?php echo $currentPage === 'admin-permissions' ? 'active' : ''; ?>">
                    <i class="fas fa-shield-alt"></i>
                    <span>Berechtigungen</span>
                </a>
                <?php endif; ?>
                
                <?php if (isAdmin() || hasPermission('manage_settings')): ?>
                <a href="?page=admin-settings" class="nav-link <?php echo $currentPage === 'admin-settings' ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i>
                    <span>Einstellungen</span>
                </a>
                <?php endif; ?>
                
                <?php if (isAdmin() || hasPermission('view_reports')): ?>
                <a href="?page=admin-print" class="nav-link <?php echo $currentPage === 'admin-print' ? 'active' : ''; ?>">
                    <i class="fas fa-print"></i>
                    <span>Druckzentrale</span>
                </a>
                <?php endif; ?>
                <?php endif; ?>
            </nav>
        </div>

        <!-- User Info with Help Button -->
        <div class="p-4 border-t border-gray-100 mt-auto bg-gradient-to-r from-gray-50 to-white">
            <!-- Help/Tour Button -->
            <button onclick="startGuidedTour()" class="w-full mb-3 flex items-center justify-center gap-2 px-3 py-2 bg-gradient-to-r from-amber-50 to-orange-50 text-amber-700 rounded-lg text-sm font-medium hover:from-amber-100 hover:to-orange-100 transition-all duration-300 border border-amber-200">
                <i class="fas fa-question-circle"></i>
                <span>Hilfe & Tour</span>
            </button>
            
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl overflow-hidden shadow-md" style="background: linear-gradient(135deg, var(--color-pastel-mint) 0%, var(--color-pastel-lavender) 100%);">
                    <div class="w-full h-full flex items-center justify-center text-white font-bold text-sm">
                        <?php echo strtoupper(substr($_SESSION['firstname'], 0, 1) . substr($_SESSION['lastname'], 0, 1)); ?>
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-medium text-sm truncate text-gray-800"><?php echo htmlspecialchars($_SESSION['firstname'] . ' ' . $_SESSION['lastname']); ?></p>
                    <p class="text-xs text-gray-500 truncate capitalize">
                        <?php echo htmlspecialchars($_SESSION['role']); ?>
                    </p>
                </div>
                <a href="logout.php" class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-all duration-200" title="Abmelden">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="md:ml-64 min-h-screen">
        <!-- Content Area with Animation -->
        <div class="page-content p-4 sm:p-6 lg:p-8">
            <?php
            // Seiten-Content laden
            $pageLoaded = false;
            
            switch ($currentPage) {
                case 'dashboard':
                    include 'pages/dashboard.php';
                    $pageLoaded = true;
                    break;
                case 'exhibitors':
                    include 'pages/exhibitors.php';
                    $pageLoaded = true;
                    break;
                case 'registration':
                    include 'pages/registration.php';
                    $pageLoaded = true;
                    break;
                case 'my-registrations':
                    include 'pages/my-registrations.php';
                    $pageLoaded = true;
                    break;
                case 'schedule':
                    include 'pages/schedule.php';
                    $pageLoaded = true;
                    break;
                case 'print-view':
                    // Sollte oben bereits abgefangen sein, aber sicherheitshalber
                    include 'pages/print-view.php';
                    $pageLoaded = true;
                    break;
                case 'teacher-dashboard':
                    if (isTeacher() || isAdmin()) {
                        include 'pages/teacher-dashboard.php';
                        $pageLoaded = true;
                    }
                    break;
                case 'teacher-class-list':
                    if (isTeacher() || isAdmin()) {
                        include 'pages/teacher-class-list.php';
                        $pageLoaded = true;
                    }
                    break;
                case 'admin-dashboard':
                    if (isAdmin()) {
                        include 'pages/admin-dashboard.php';
                        $pageLoaded = true;
                    }
                    break;
                case 'admin-exhibitors':
                    if (isAdmin() || hasPermission('manage_exhibitors')) {
                        include 'pages/admin-exhibitors.php';
                        $pageLoaded = true;
                    }
                    break;
                case 'admin-rooms':
                    if (isAdmin() || hasPermission('manage_rooms')) {
                        include 'pages/admin-rooms.php';
                        $pageLoaded = true;
                    }
                    break;
                case 'admin-room-capacities':
                    if (isAdmin() || hasPermission('manage_rooms')) {
                        include 'pages/admin-room-capacities.php';
                        $pageLoaded = true;
                    }
                    break;
                case 'admin-users':
                    if (isAdmin() || hasPermission('manage_users')) {
                        include 'pages/admin-users.php';
                        $pageLoaded = true;
                    }
                    break;
                case 'admin-permissions':
                    if (isAdmin()) {
                        include 'pages/admin-permissions.php';
                        $pageLoaded = true;
                    }
                    break;
                case 'admin-print':
                    if (isAdmin() || hasPermission('view_reports')) {
                        include 'pages/admin-print.php';
                        $pageLoaded = true;
                    }
                    break;
                case 'admin-settings':
                    if (isAdmin() || hasPermission('manage_settings')) {
                        include 'pages/admin-settings.php';
                        $pageLoaded = true;
                    }
                    break;
            }
            
            // Fallback zum Dashboard wenn keine Seite geladen wurde
            if (!$pageLoaded) {
                if (isTeacher()) {
                    include 'pages/teacher-dashboard.php';
                } else {
                    include 'pages/dashboard.php';
                }
            }
            ?>
        </div>
    </main>

    <!-- JavaScript Libraries -->
    <script src="<?php echo BASE_URL; ?>assets/js/animations.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/guided-tour.js"></script>
    
    <script>
        // Mobile Menu Toggle with Animation
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const sidebar = document.getElementById('sidebar');
        
        mobileMenuBtn.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            // Toggle button with rotation animation
            if (sidebar.classList.contains('open')) {
                mobileMenuBtn.style.transform = 'rotate(90deg)';
                mobileMenuBtn.style.opacity = '0.5';
            } else {
                mobileMenuBtn.style.transform = 'rotate(0)';
                mobileMenuBtn.style.opacity = '1';
            }
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', (e) => {
            if (window.innerWidth < 768) {
                if (!sidebar.contains(e.target) && !mobileMenuBtn.contains(e.target)) {
                    sidebar.classList.remove('open');
                    mobileMenuBtn.style.transform = 'rotate(0)';
                    mobileMenuBtn.style.opacity = '1';
                }
            }
        });
        
        // Reset button when window is resized to desktop
        window.addEventListener('resize', () => {
            if (window.innerWidth >= 768) {
                mobileMenuBtn.style.transform = 'rotate(0)';
                mobileMenuBtn.style.opacity = '1';
            }
        });
        
        // Start Guided Tour Function
        function startGuidedTour() {
            // Navigate to dashboard first
            const currentPage = new URLSearchParams(window.location.search).get('page');
            const userRole = '<?php echo $_SESSION["role"] ?? "student"; ?>';
            
            // Determine the correct dashboard page based on actual role
            let dashboardPage = 'dashboard'; // default for students
            if (userRole === 'admin') {
                dashboardPage = 'admin-dashboard';
            } else if (userRole === 'teacher') {
                dashboardPage = 'teacher-dashboard';
            }
            
            // If not on the correct dashboard, navigate there first
            if (currentPage !== dashboardPage) {
                window.location.href = '?page=' + dashboardPage + '&start_tour=1';
                return;
            }
            
            if (typeof GuidedTour !== 'undefined') {
                // Use server-side role for role-specific steps
                const steps = typeof generateTourSteps !== 'undefined' ? generateTourSteps(userRole) : (window.berufsmesseTourSteps || []);

                const tour = new GuidedTour({
                    steps: steps,
                    role: userRole,
                    onComplete: () => {
                        if (typeof showToast !== 'undefined') {
                            showToast('Tour abgeschlossen! 🎉', 'success');
                        }
                    },
                    onSkip: () => {
                        if (typeof showToast !== 'undefined') {
                            showToast('Tour übersprungen', 'info');
                        }
                    }
                });
                tour.reset(); // Allow restarting
                tour.start();
            } else {
                console.warn('GuidedTour nicht geladen');
            }
        }
        
        // Initialize page animations
        document.addEventListener('DOMContentLoaded', () => {
            // Add animation class to cards
            document.querySelectorAll('.card, .quick-action-card').forEach((card, index) => {
                card.style.animationDelay = `${index * 50}ms`;
            });
        });
    </script>
</body>
</html>
