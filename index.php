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
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0fdf4', 100: '#dcfce7', 200: '#bbf7d0', 300: '#86efac',
                            400: '#4ade80', 500: '#22c55e', 600: '#16a34a', 700: '#15803d',
                            800: '#166534', 900: '#14532d'
                        },
                        accent: {
                            50: '#faf5ff', 100: '#f3e8ff', 200: '#e9d5ff', 300: '#d8b4fe',
                            400: '#c084fc', 500: '#a855f7', 600: '#9333ea', 700: '#7e22ce',
                            800: '#6b21a8', 900: '#581c87'
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                        display: ['Plus Jakarta Sans', 'system-ui', 'sans-serif']
                    },
                    boxShadow: {
                        'glow-primary': '0 0 40px rgba(34, 197, 94, 0.3)',
                        'glow-accent': '0 0 40px rgba(168, 85, 247, 0.3)',
                        'glow-blue': '0 0 40px rgba(59, 130, 246, 0.3)'
                    }
                }
            }
        }
    </script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom Styles -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    
    <style>
        /* Additional inline styles for unique effects */
        .gradient-text {
            background: linear-gradient(135deg, #22c55e 0%, #a855f7 50%, #3b82f6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .hero-glow {
            position: relative;
        }
        
        .hero-glow::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(34, 197, 94, 0.1) 0%, transparent 70%);
            animation: pulse-glow 4s ease-in-out infinite;
        }
        
        @keyframes pulse-glow {
            0%, 100% { opacity: 0.5; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.1); }
        }
        
        .card-shine {
            position: relative;
            overflow: hidden;
        }
        
        .card-shine::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                45deg,
                transparent 40%,
                rgba(255,255,255,0.1) 50%,
                transparent 60%
            );
            transform: translateX(-100%);
            transition: transform 0.6s;
        }
        
        .card-shine:hover::after {
            transform: translateX(100%);
        }
        
        /* Floating animation for icons */
        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-10px) rotate(5deg); }
        }
        
        .float-animation {
            animation: float 3s ease-in-out infinite;
        }
        
        /* Gradient border effect */
        .gradient-border {
            position: relative;
            background: white;
            border-radius: 1rem;
        }
        
        .gradient-border::before {
            content: '';
            position: absolute;
            inset: -2px;
            background: linear-gradient(135deg, #22c55e, #a855f7, #3b82f6);
            border-radius: inherit;
            z-index: -1;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .gradient-border:hover::before {
            opacity: 1;
        }
        
        /* Modern scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, #22c55e, #16a34a);
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(180deg, #16a34a, #15803d);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Animated Background Mesh -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden z-0">
        <div class="absolute top-0 right-0 w-96 h-96 bg-primary-400/10 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-0 left-0 w-96 h-96 bg-accent-400/10 rounded-full blur-3xl animate-pulse" style="animation-delay: 1s;"></div>
        <div class="absolute top-1/2 left-1/2 w-96 h-96 bg-blue-400/10 rounded-full blur-3xl animate-pulse" style="animation-delay: 2s;"></div>
    </div>

    <!-- Mobile Menu Button -->
    <button id="mobileMenuBtn" class="md:hidden fixed top-4 left-4 z-50 bg-white/90 backdrop-blur-xl text-gray-600 p-3 rounded-2xl shadow-lg border border-gray-100 hover:shadow-xl hover:scale-105 transition-all duration-300">
        <i class="fas fa-bars text-lg"></i>
    </button>

    <!-- Mobile Menu Overlay -->
    <div id="mobileOverlay" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-30 opacity-0 invisible transition-all duration-300 md:hidden"></div>

    <!-- Sidebar -->
    <aside id="sidebar" class="fixed left-0 top-0 h-full w-72 bg-white/95 backdrop-blur-xl border-r border-gray-100 z-40 flex flex-col shadow-2xl transition-transform duration-300 md:translate-x-0 -translate-x-full">
        <!-- Logo Section -->
        <div class="p-6 border-b border-gray-100">
            <div class="flex items-center gap-3">
                <div class="relative">
                    <div class="w-12 h-12 bg-gradient-to-br from-primary-500 to-primary-600 rounded-2xl flex items-center justify-center shadow-lg shadow-primary-500/30 transform hover:scale-110 transition-transform duration-300">
                        <i class="fas fa-graduation-cap text-white text-xl"></i>
                    </div>
                    <div class="absolute -top-1 -right-1 w-4 h-4 bg-accent-500 rounded-full border-2 border-white animate-pulse"></div>
                </div>
                <div>
                    <h1 class="text-xl font-extrabold font-display">
                        <span class="gradient-text">Berufsmesse</span>
                    </h1>
                    <span class="text-xs text-gray-400 font-medium">Karriere Portal 2026</span>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 overflow-y-auto p-4 space-y-1">
            <?php if (!isTeacher()): ?>
            <div class="mb-4">
                <span class="px-3 text-xs font-bold text-gray-400 uppercase tracking-wider">Navigation</span>
            </div>
            
            <a href="?page=schedule" class="group flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all duration-200 <?php echo $currentPage === 'schedule' ? 'bg-gradient-to-r from-primary-500 to-primary-600 text-white shadow-lg shadow-primary-500/30' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'; ?>">
                <div class="w-9 h-9 rounded-lg <?php echo $currentPage === 'schedule' ? 'bg-white/20' : 'bg-primary-100 group-hover:bg-primary-200'; ?> flex items-center justify-center transition-colors">
                    <i class="fas fa-calendar-alt <?php echo $currentPage === 'schedule' ? 'text-white' : 'text-primary-600'; ?>"></i>
                </div>
                <span>Kalender</span>
                <?php if ($currentPage === 'schedule'): ?>
                <i class="fas fa-chevron-right ml-auto text-white/70 text-sm"></i>
                <?php endif; ?>
            </a>

            <a href="?page=exhibitors" class="group flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all duration-200 <?php echo $currentPage === 'exhibitors' ? 'bg-gradient-to-r from-primary-500 to-primary-600 text-white shadow-lg shadow-primary-500/30' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'; ?>">
                <div class="w-9 h-9 rounded-lg <?php echo $currentPage === 'exhibitors' ? 'bg-white/20' : 'bg-accent-100 group-hover:bg-accent-200'; ?> flex items-center justify-center transition-colors">
                    <i class="fas fa-building <?php echo $currentPage === 'exhibitors' ? 'text-white' : 'text-accent-600'; ?>"></i>
                </div>
                <span>Unternehmen</span>
            </a>
            
            <a href="?page=registration" class="group flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all duration-200 <?php echo $currentPage === 'registration' ? 'bg-gradient-to-r from-primary-500 to-primary-600 text-white shadow-lg shadow-primary-500/30' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'; ?>">
                <div class="w-9 h-9 rounded-lg <?php echo $currentPage === 'registration' ? 'bg-white/20' : 'bg-blue-100 group-hover:bg-blue-200'; ?> flex items-center justify-center transition-colors">
                    <i class="fas fa-clipboard-list <?php echo $currentPage === 'registration' ? 'text-white' : 'text-blue-600'; ?>"></i>
                </div>
                <span>Einschreibung</span>
            </a>
            
            <a href="?page=my-registrations" class="group flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all duration-200 <?php echo $currentPage === 'my-registrations' ? 'bg-gradient-to-r from-primary-500 to-primary-600 text-white shadow-lg shadow-primary-500/30' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'; ?>">
                <div class="w-9 h-9 rounded-lg <?php echo $currentPage === 'my-registrations' ? 'bg-white/20' : 'bg-orange-100 group-hover:bg-orange-200'; ?> flex items-center justify-center transition-colors">
                    <i class="fas fa-check-circle <?php echo $currentPage === 'my-registrations' ? 'text-white' : 'text-orange-600'; ?>"></i>
                </div>
                <span>Meine Termine</span>
            </a>
            <?php endif; ?>

            <?php if (isTeacher() && !isAdmin()): ?>
            <div class="mb-4">
                <span class="px-3 text-xs font-bold text-gray-400 uppercase tracking-wider">Lehrer</span>
            </div>
            
            <a href="?page=teacher-dashboard" class="group flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all duration-200 <?php echo $currentPage === 'teacher-dashboard' ? 'bg-gradient-to-r from-primary-500 to-primary-600 text-white shadow-lg shadow-primary-500/30' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'; ?>">
                <div class="w-9 h-9 rounded-lg <?php echo $currentPage === 'teacher-dashboard' ? 'bg-white/20' : 'bg-primary-100'; ?> flex items-center justify-center">
                    <i class="fas fa-chalkboard-teacher <?php echo $currentPage === 'teacher-dashboard' ? 'text-white' : 'text-primary-600'; ?>"></i>
                </div>
                <span>Dashboard</span>
            </a>
            
            <a href="?page=exhibitors" class="group flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all duration-200 <?php echo $currentPage === 'exhibitors' ? 'bg-gradient-to-r from-primary-500 to-primary-600 text-white shadow-lg shadow-primary-500/30' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'; ?>">
                <div class="w-9 h-9 rounded-lg <?php echo $currentPage === 'exhibitors' ? 'bg-white/20' : 'bg-accent-100'; ?> flex items-center justify-center">
                    <i class="fas fa-building <?php echo $currentPage === 'exhibitors' ? 'text-white' : 'text-accent-600'; ?>"></i>
                </div>
                <span>Unternehmen</span>
            </a>
            <?php endif; ?>

            <?php if (isAdmin() || hasPermission('manage_exhibitors') || hasPermission('manage_rooms') || hasPermission('manage_settings') || hasPermission('manage_users') || hasPermission('view_reports') || hasPermission('auto_assign')): ?>
            
            <div class="mt-6 mb-4">
                <span class="px-3 text-xs font-bold text-accent-500 uppercase tracking-wider flex items-center gap-2">
                    <i class="fas fa-shield-alt text-xs"></i>
                    Administration
                </span>
            </div>
            
            <?php if (isAdmin()): ?>
            <a href="?page=admin-dashboard" class="group flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all duration-200 <?php echo $currentPage === 'admin-dashboard' ? 'bg-gradient-to-r from-accent-500 to-accent-600 text-white shadow-lg shadow-accent-500/30' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'; ?>">
                <div class="w-9 h-9 rounded-lg <?php echo $currentPage === 'admin-dashboard' ? 'bg-white/20' : 'bg-accent-100'; ?> flex items-center justify-center">
                    <i class="fas fa-tachometer-alt <?php echo $currentPage === 'admin-dashboard' ? 'text-white' : 'text-accent-600'; ?>"></i>
                </div>
                <span>Dashboard</span>
            </a>
            <?php endif; ?>
            
            <?php if (isAdmin() || hasPermission('manage_exhibitors')): ?>
            <a href="?page=admin-exhibitors" class="group flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all duration-200 <?php echo $currentPage === 'admin-exhibitors' ? 'bg-gradient-to-r from-accent-500 to-accent-600 text-white shadow-lg shadow-accent-500/30' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'; ?>">
                <div class="w-9 h-9 rounded-lg <?php echo $currentPage === 'admin-exhibitors' ? 'bg-white/20' : 'bg-purple-100'; ?> flex items-center justify-center">
                    <i class="fas fa-building <?php echo $currentPage === 'admin-exhibitors' ? 'text-white' : 'text-purple-600'; ?>"></i>
                </div>
                <span>Aussteller</span>
            </a>
            <?php endif; ?>
            
            <?php if (isAdmin() || hasPermission('manage_rooms')): ?>
            <a href="?page=admin-rooms" class="group flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all duration-200 <?php echo $currentPage === 'admin-rooms' ? 'bg-gradient-to-r from-accent-500 to-accent-600 text-white shadow-lg shadow-accent-500/30' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'; ?>">
                <div class="w-9 h-9 rounded-lg <?php echo $currentPage === 'admin-rooms' ? 'bg-white/20' : 'bg-pink-100'; ?> flex items-center justify-center">
                    <i class="fas fa-map-marker-alt <?php echo $currentPage === 'admin-rooms' ? 'text-white' : 'text-pink-600'; ?>"></i>
                </div>
                <span>Räume</span>
            </a>
            
            <a href="?page=admin-room-capacities" class="group flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all duration-200 <?php echo $currentPage === 'admin-room-capacities' ? 'bg-gradient-to-r from-accent-500 to-accent-600 text-white shadow-lg shadow-accent-500/30' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'; ?>">
                <div class="w-9 h-9 rounded-lg <?php echo $currentPage === 'admin-room-capacities' ? 'bg-white/20' : 'bg-cyan-100'; ?> flex items-center justify-center">
                    <i class="fas fa-table <?php echo $currentPage === 'admin-room-capacities' ? 'text-white' : 'text-cyan-600'; ?>"></i>
                </div>
                <span>Kapazitäten</span>
            </a>
            <?php endif; ?>
            
            <?php if (isAdmin() || hasPermission('manage_users')): ?>
            <a href="?page=admin-users" class="group flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all duration-200 <?php echo $currentPage === 'admin-users' ? 'bg-gradient-to-r from-accent-500 to-accent-600 text-white shadow-lg shadow-accent-500/30' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'; ?>">
                <div class="w-9 h-9 rounded-lg <?php echo $currentPage === 'admin-users' ? 'bg-white/20' : 'bg-indigo-100'; ?> flex items-center justify-center">
                    <i class="fas fa-users <?php echo $currentPage === 'admin-users' ? 'text-white' : 'text-indigo-600'; ?>"></i>
                </div>
                <span>Benutzer</span>
            </a>
            <?php endif; ?>
            
            <?php if (isAdmin()): ?>
            <a href="?page=admin-permissions" class="group flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all duration-200 <?php echo $currentPage === 'admin-permissions' ? 'bg-gradient-to-r from-accent-500 to-accent-600 text-white shadow-lg shadow-accent-500/30' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'; ?>">
                <div class="w-9 h-9 rounded-lg <?php echo $currentPage === 'admin-permissions' ? 'bg-white/20' : 'bg-yellow-100'; ?> flex items-center justify-center">
                    <i class="fas fa-shield-alt <?php echo $currentPage === 'admin-permissions' ? 'text-white' : 'text-yellow-600'; ?>"></i>
                </div>
                <span>Berechtigungen</span>
            </a>
            <?php endif; ?>
            
            <?php if (isAdmin() || hasPermission('manage_settings')): ?>
            <a href="?page=admin-settings" class="group flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all duration-200 <?php echo $currentPage === 'admin-settings' ? 'bg-gradient-to-r from-accent-500 to-accent-600 text-white shadow-lg shadow-accent-500/30' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'; ?>">
                <div class="w-9 h-9 rounded-lg <?php echo $currentPage === 'admin-settings' ? 'bg-white/20' : 'bg-gray-100'; ?> flex items-center justify-center">
                    <i class="fas fa-cog <?php echo $currentPage === 'admin-settings' ? 'text-white' : 'text-gray-600'; ?>"></i>
                </div>
                <span>Einstellungen</span>
            </a>
            <?php endif; ?>
            
            <?php if (isAdmin() || hasPermission('view_reports')): ?>
            <a href="?page=admin-print" class="group flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all duration-200 <?php echo $currentPage === 'admin-print' ? 'bg-gradient-to-r from-accent-500 to-accent-600 text-white shadow-lg shadow-accent-500/30' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'; ?>">
                <div class="w-9 h-9 rounded-lg <?php echo $currentPage === 'admin-print' ? 'bg-white/20' : 'bg-emerald-100'; ?> flex items-center justify-center">
                    <i class="fas fa-print <?php echo $currentPage === 'admin-print' ? 'text-white' : 'text-emerald-600'; ?>"></i>
                </div>
                <span>Berichte</span>
            </a>
            <?php endif; ?>
            <?php endif; ?>
        </nav>

        <!-- User Profile Section -->
        <div class="p-4 border-t border-gray-100 bg-gradient-to-t from-gray-50/80">
            <div class="flex items-center gap-3 p-3 rounded-2xl bg-white border border-gray-100 shadow-sm hover:shadow-md hover:border-primary-200 transition-all duration-300">
                <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-accent-500 to-accent-600 flex items-center justify-center text-white font-bold shadow-lg shadow-accent-500/20">
                    <?php echo strtoupper(substr($_SESSION['firstname'], 0, 1) . substr($_SESSION['lastname'], 0, 1)); ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-gray-800 text-sm truncate"><?php echo htmlspecialchars($_SESSION['firstname'] . ' ' . $_SESSION['lastname']); ?></p>
                    <p class="text-xs text-gray-400 truncate flex items-center gap-1">
                        <span class="w-2 h-2 rounded-full bg-primary-500 animate-pulse"></span>
                        <?php echo ucfirst($_SESSION['role']); ?>
                    </p>
                </div>
                <a href="logout.php" class="w-10 h-10 rounded-xl bg-gray-100 text-gray-400 flex items-center justify-center hover:bg-red-50 hover:text-red-500 transition-all duration-300 hover:rotate-12" title="Abmelden">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="md:ml-72 min-h-screen relative z-10">
        <!-- Content Area -->
        <div class="p-6 lg:p-8">
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
        const mobileOverlay = document.getElementById('mobileOverlay');
        
        function toggleMobileMenu() {
            sidebar.classList.toggle('-translate-x-full');
            sidebar.classList.toggle('translate-x-0');
            mobileOverlay.classList.toggle('opacity-0');
            mobileOverlay.classList.toggle('invisible');
            mobileOverlay.classList.toggle('opacity-100');
            mobileOverlay.classList.toggle('visible');
        }
        
        mobileMenuBtn.addEventListener('click', toggleMobileMenu);
        mobileOverlay.addEventListener('click', toggleMobileMenu);

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', (e) => {
            if (window.innerWidth < 768) {
                if (!sidebar.contains(e.target) && !mobileMenuBtn.contains(e.target)) {
                    sidebar.classList.add('-translate-x-full');
                    sidebar.classList.remove('translate-x-0');
                    mobileOverlay.classList.add('opacity-0', 'invisible');
                    mobileOverlay.classList.remove('opacity-100', 'visible');
                }
            }
        });

        // Add entrance animations to cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.card, .stat-card, .exhibitor-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1)';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 50);
            });
        });
    </script>
</body>
</html>
