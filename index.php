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
        
        // ============================================================
        // PHASE 1: Schüler mit Anmeldungen (timeslot_id = NULL) 
        //          → Slots bei ihren gewählten Ausstellern zuweisen
        // ============================================================
        
        // Alle Anmeldungen ohne Slot-Zuteilung laden (Priorität berücksichtigen - Issue #16)
        $stmt = $db->query("
            SELECT r.id as registration_id, r.user_id, r.exhibitor_id, e.name as exhibitor_name, e.room_id, COALESCE(r.priority, 2) as priority
            FROM registrations r
            JOIN exhibitors e ON r.exhibitor_id = e.id
            WHERE r.timeslot_id IS NULL
            AND e.active = 1
            AND e.room_id IS NOT NULL
            ORDER BY COALESCE(r.priority, 2) ASC, r.registered_at ASC
        ");
        $pendingRegistrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($pendingRegistrations as $reg) {
            $studentId = $reg['user_id'];
            $exhibitorId = $reg['exhibitor_id'];
            $roomId = $reg['room_id'];
            
            // Welche Slots hat der Schüler bereits?
            $stmt = $db->prepare("
                SELECT t.slot_number 
                FROM registrations r
                JOIN timeslots t ON r.timeslot_id = t.id
                WHERE r.user_id = ? AND t.slot_number IN (1, 3, 5)
            ");
            $stmt->execute([$studentId]);
            $usedSlots = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Verfügbare Slots für diesen Schüler
            $availableSlots = array_diff($managedSlots, $usedSlots);
            
            if (empty($availableSlots)) {
                // Schüler hat bereits alle 3 Slots - Anmeldung ohne Slot löschen
                $stmt = $db->prepare("DELETE FROM registrations WHERE id = ?");
                $stmt->execute([$reg['registration_id']]);
                $errors[] = "Schüler $studentId hat bereits 3 Slots - überschüssige Anmeldung für {$reg['exhibitor_name']} entfernt";
                continue;
            }
            
            // Besten verfügbaren Slot für diesen Aussteller finden (wenigste Belegung)
            $bestSlot = null;
            $lowestCount = PHP_INT_MAX;
            
            foreach ($availableSlots as $slotNumber) {
                $stmt = $db->prepare("SELECT id FROM timeslots WHERE slot_number = ?");
                $stmt->execute([$slotNumber]);
                $timeslotId = $stmt->fetchColumn();
                
                if (!$timeslotId) continue;
                
                // Aktuelle Belegung bei diesem Aussteller in diesem Slot
                $stmt = $db->prepare("
                    SELECT COUNT(*) as cnt FROM registrations 
                    WHERE exhibitor_id = ? AND timeslot_id = ?
                ");
                $stmt->execute([$exhibitorId, $timeslotId]);
                $currentCount = $stmt->fetchColumn();
                
                // Kapazität prüfen
                $slotCapacity = getRoomSlotCapacity($roomId, $timeslotId);
                
                if ($slotCapacity > 0 && $currentCount < $slotCapacity && $currentCount < $lowestCount) {
                    $bestSlot = ['slot_number' => $slotNumber, 'timeslot_id' => $timeslotId, 'count' => $currentCount];
                    $lowestCount = $currentCount;
                }
            }
            
            if ($bestSlot) {
                // Slot zuweisen durch UPDATE der bestehenden Registrierung
                $stmt = $db->prepare("UPDATE registrations SET timeslot_id = ?, registration_type = 'automatic' WHERE id = ?");
                if ($stmt->execute([$bestSlot['timeslot_id'], $reg['registration_id']])) {
                    $assignedCount++;
                } else {
                    $errors[] = "Fehler beim Zuweisen von Slot {$bestSlot['slot_number']} für Schüler $studentId bei {$reg['exhibitor_name']}";
                }
            } else {
                $errors[] = "Kein freier Slot für Schüler $studentId bei {$reg['exhibitor_name']} (alle Slots voll oder bereits belegt)";
            }
        }
        
        // ============================================================
        // PHASE 2: Schüler ohne vollständige Slots 
        //          → auf beliebige Aussteller verteilen
        // ============================================================
        
        // Alle Schüler mit weniger als 3 zugewiesenen Slots (timeslot_id NOT NULL)
        $stmt = $db->query("
            SELECT u.id,
                   COALESCE(SUM(CASE WHEN r.timeslot_id IS NOT NULL AND t.slot_number IN (1,3,5) THEN 1 ELSE 0 END), 0) as assigned_count
            FROM users u
            LEFT JOIN registrations r ON u.id = r.user_id
            LEFT JOIN timeslots t ON r.timeslot_id = t.id
            WHERE u.role = 'student'
            GROUP BY u.id
            HAVING assigned_count < " . MANAGED_SLOTS_COUNT . "
            ORDER BY assigned_count DESC
        ");
        $studentsNeedingSlots = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($studentsNeedingSlots as $student) {
            $studentId = $student['id'];
            
            // Welche Slots hat der Schüler bereits zugewiesen?
            $stmt = $db->prepare("
                SELECT t.slot_number 
                FROM registrations r
                JOIN timeslots t ON r.timeslot_id = t.id
                WHERE r.user_id = ? AND t.slot_number IN (1, 3, 5)
            ");
            $stmt->execute([$studentId]);
            $assignedSlots = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Bei welchen Ausstellern ist der Schüler bereits (mit oder ohne Slot)?
            $stmt = $db->prepare("SELECT exhibitor_id FROM registrations WHERE user_id = ?");
            $stmt->execute([$studentId]);
            $existingExhibitors = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Fehlende Slots ermitteln
            $missingSlots = array_diff($managedSlots, $assignedSlots);
            
            foreach ($missingSlots as $slotNumber) {
                // Timeslot ID ermitteln
                $stmt = $db->prepare("SELECT id FROM timeslots WHERE slot_number = ?");
                $stmt->execute([$slotNumber]);
                $timeslotId = $stmt->fetchColumn();
                
                if (!$timeslotId) {
                    $errors[] = "Slot $slotNumber nicht gefunden";
                    continue;
                }
                
                // Aussteller mit wenigsten Teilnehmern in diesem Slot finden
                $stmt = $db->prepare("
                    SELECT e.id, e.name, e.room_id,
                           COUNT(DISTINCT reg.user_id) as current_count
                    FROM exhibitors e
                    LEFT JOIN rooms r ON e.room_id = r.id
                    LEFT JOIN registrations reg ON e.id = reg.exhibitor_id AND reg.timeslot_id = ?
                    WHERE e.active = 1 AND e.room_id IS NOT NULL
                    GROUP BY e.id, e.name, e.room_id
                    ORDER BY current_count ASC, RAND()
                ");
                $stmt->execute([$timeslotId]);
                $exhibitors = $stmt->fetchAll();
                
                $selectedExhibitor = null;
                
                foreach ($exhibitors as $ex) {
                    // Schüler darf nicht bereits bei diesem Aussteller sein
                    if (in_array($ex['id'], $existingExhibitors)) {
                        continue;
                    }
                    
                    // Kapazität prüfen
                    $slotCapacity = getRoomSlotCapacity($ex['room_id'], $timeslotId);
                    if ($slotCapacity > 0 && $ex['current_count'] < $slotCapacity) {
                        $selectedExhibitor = $ex;
                        break;
                    }
                }
                
                if ($selectedExhibitor) {
                    // Neue Registrierung erstellen
                    $stmt = $db->prepare("
                        INSERT INTO registrations (user_id, exhibitor_id, timeslot_id, registration_type)
                        VALUES (?, ?, ?, 'automatic')
                    ");
                    
                    if ($stmt->execute([$studentId, $selectedExhibitor['id'], $timeslotId])) {
                        $assignedCount++;
                        $existingExhibitors[] = $selectedExhibitor['id']; // Merken für nächste Iteration
                    } else {
                        $errors[] = "Fehler bei Zuweisung: Schüler $studentId zu {$selectedExhibitor['name']} (Slot $slotNumber)";
                    }
                } else {
                    $errors[] = "Kein verfügbarer Aussteller für Slot $slotNumber (Schüler ID: $studentId)";
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
        
        // Auto-Close: Einschreibung automatisch schliessen nach Zuteilung (Issue #12)
        $autoClose = getSetting('auto_close_registration', '1');
        if ($autoClose === '1') {
            $regStatus = getRegistrationStatus();
            if ($regStatus === 'open') {
                // Einschreibungsende auf jetzt setzen
                $stmt = $db->prepare("UPDATE settings SET value = ? WHERE `key` = 'registration_end'");
                $stmt->execute([date('Y-m-d H:i:s')]);
                $_SESSION['auto_assign_closed'] = true;
            }
        }
        
    } catch (Exception $e) {
        $_SESSION['auto_assign_error'] = 'Fehler bei der automatischen Zuteilung: ' . $e->getMessage();
    }
    
    header('Location: ?page=admin-dashboard&auto_assign=done');
    exit;
}

// QR-Code Generierung (Bulk) - BEFORE HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_all']) && isAdmin()) {
    $generated = 0;
    try {
        $stmt = $db->query("SELECT id FROM exhibitors WHERE active = 1");
        $exhibitors = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $stmt = $db->query("SELECT id FROM timeslots ORDER BY slot_number ASC");
        $timeslots = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($exhibitors as $exId) {
            foreach ($timeslots as $tsId) {
                $token = bin2hex(random_bytes(3)); // 6 Zeichen
                $stmt = $db->prepare("
                    INSERT INTO qr_tokens (exhibitor_id, timeslot_id, token, expires_at)
                    VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))
                    ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at)
                ");
                $stmt->execute([$exId, $tsId, $token]);
                $generated++;
            }
        }
    } catch (Exception $e) {
        error_log('QR generation error: ' . $e->getMessage());
    }
    header('Location: ?page=admin-qr-codes&generated=' . $generated);
    exit;
}

// QR-Code Generierung (Einzeln) - BEFORE HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_single']) && isAdmin()) {
    try {
        $exhibitorId = intval($_POST['exhibitor_id']);
        $timeslotId = intval($_POST['timeslot_id']);
        $token = bin2hex(random_bytes(3)); // 6 Zeichen
        $stmt = $db->prepare("
            INSERT INTO qr_tokens (exhibitor_id, timeslot_id, token, expires_at)
            VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))
            ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at)
        ");
        $stmt->execute([$exhibitorId, $timeslotId, $token]);
    } catch (Exception $e) {
        error_log('QR generation error: ' . $e->getMessage());
    }
    header('Location: ?page=admin-qr-codes&generated=1');
    exit;
}

// Aussteller laden
$stmt = $db->query("SELECT * FROM exhibitors WHERE active = 1 ORDER BY name ASC");
$exhibitors = $stmt->fetchAll();

// Einschreibungen des Benutzers laden (LEFT JOIN für NULL timeslot_id - Issue #6)
$stmt = $db->prepare("SELECT r.*, e.name as exhibitor_name, t.slot_name 
                      FROM registrations r 
                      JOIN exhibitors e ON r.exhibitor_id = e.id 
                      LEFT JOIN timeslots t ON r.timeslot_id = t.id 
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
    
    <!-- Material Icons (genutzt in der Guided Tour) -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    
    <!-- Custom Design System -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/design-system.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/guided-tour.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/easter-eggs.css">
    
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
        
        /* ==========================================================================
           Exhibitor Modal - Komplett neu geschrieben
           ========================================================================== */
        .exhibitor-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        
        .exhibitor-modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        .exhibitor-modal-box {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            width: 100%;
            max-width: 42rem;
            max-height: 90vh;
            margin: 1rem;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transform: scale(0.95) translateY(10px);
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        
        .exhibitor-modal-overlay.active .exhibitor-modal-box {
            transform: scale(1) translateY(0);
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
                <a href="<?php echo $currentPage === 'dashboard' ? 'javascript:void(0)' : '?page=dashboard'; ?>" data-page="dashboard" class="nav-link <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>

                <!-- Unternehmen -->
                <a href="<?php echo $currentPage === 'exhibitors' ? 'javascript:void(0)' : '?page=exhibitors'; ?>" data-page="exhibitors" class="nav-link <?php echo $currentPage === 'exhibitors' ? 'active' : ''; ?>">
                    <i class="fas fa-building"></i>
                    <span>Unternehmen</span>
                </a>

                <!-- QR Check-In -->
                <a href="<?php echo $currentPage === 'qr-checkin' ? 'javascript:void(0)' : '?page=qr-checkin'; ?>" data-page="qr-checkin" class="nav-link <?php echo $currentPage === 'qr-checkin' ? 'active' : ''; ?>">
                    <i class="fas fa-qrcode"></i>
                    <span>QR Check-In</span>
                </a>
                <?php endif; ?>

                <?php if (isTeacher() && !isAdmin()): ?>
                <div class="nav-group-title">Lehrer</div>
                
                <a href="<?php echo $currentPage === 'teacher-dashboard' ? 'javascript:void(0)' : '?page=teacher-dashboard'; ?>" data-page="teacher-dashboard" class="nav-link <?php echo $currentPage === 'teacher-dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span>Übersicht</span>
                </a>
                
                <a href="<?php echo $currentPage === 'exhibitors' ? 'javascript:void(0)' : '?page=exhibitors'; ?>" data-page="exhibitors" class="nav-link <?php echo $currentPage === 'exhibitors' ? 'active' : ''; ?>">
                    <i class="fas fa-building"></i>
                    <span>Unternehmen</span>
                </a>
                <?php endif; ?>

                <?php if (isAdmin() || hasPermission('manage_exhibitors') || hasPermission('manage_rooms') || hasPermission('manage_settings') || hasPermission('manage_users') || hasPermission('view_reports') || hasPermission('auto_assign')): ?>
                
                <div class="nav-group-title">Verwaltung</div>
                
                <?php if (isAdmin()): ?>
                <a href="<?php echo $currentPage === 'admin-dashboard' ? 'javascript:void(0)' : '?page=admin-dashboard'; ?>" data-page="admin-dashboard" class="nav-link <?php echo $currentPage === 'admin-dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Admin-Dashboard</span>
                </a>
                <?php endif; ?>
                
                <?php if (isAdmin() || hasPermission('manage_exhibitors')): ?>
                <a href="<?php echo $currentPage === 'admin-exhibitors' ? 'javascript:void(0)' : '?page=admin-exhibitors'; ?>" data-page="admin-exhibitors" class="nav-link <?php echo $currentPage === 'admin-exhibitors' ? 'active' : ''; ?>">
                    <i class="fas fa-building"></i>
                    <span>Aussteller</span>
                </a>
                <?php endif; ?>
                
                <?php if (isAdmin() || hasPermission('manage_rooms')): ?>
                <a href="<?php echo $currentPage === 'admin-rooms' ? 'javascript:void(0)' : '?page=admin-rooms'; ?>" data-page="admin-rooms" class="nav-link <?php echo $currentPage === 'admin-rooms' ? 'active' : ''; ?>">
                    <i class="fas fa-map-marker-alt"></i>
                    <span>Räume</span>
                </a>
                
                <a href="<?php echo $currentPage === 'admin-room-capacities' ? 'javascript:void(0)' : '?page=admin-room-capacities'; ?>" data-page="admin-room-capacities" class="nav-link <?php echo $currentPage === 'admin-room-capacities' ? 'active' : ''; ?>">
                    <i class="fas fa-table"></i>
                    <span>Kapazitäten</span>
                </a>
                <?php endif; ?>
                
                <?php if (isAdmin() || hasPermission('manage_users')): ?>
                <a href="<?php echo $currentPage === 'admin-users' ? 'javascript:void(0)' : '?page=admin-users'; ?>" data-page="admin-users" class="nav-link <?php echo $currentPage === 'admin-users' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>Benutzer</span>
                </a>
                <?php endif; ?>
                
                <?php if (isAdmin()): ?>
                <a href="<?php echo $currentPage === 'admin-permissions' ? 'javascript:void(0)' : '?page=admin-permissions'; ?>" data-page="admin-permissions" class="nav-link <?php echo $currentPage === 'admin-permissions' ? 'active' : ''; ?>">
                    <i class="fas fa-shield-alt"></i>
                    <span>Berechtigungen</span>
                </a>
                <?php endif; ?>
                
                <?php if (isAdmin() || hasPermission('manage_settings')): ?>
                <a href="<?php echo $currentPage === 'admin-settings' ? 'javascript:void(0)' : '?page=admin-settings'; ?>" data-page="admin-settings" class="nav-link <?php echo $currentPage === 'admin-settings' ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i>
                    <span>Einstellungen</span>
                </a>
                <?php endif; ?>
                
                <?php if (isAdmin() || hasPermission('view_reports')): ?>
                <a href="<?php echo $currentPage === 'admin-print' ? 'javascript:void(0)' : '?page=admin-print'; ?>" data-page="admin-print" class="nav-link <?php echo $currentPage === 'admin-print' ? 'active' : ''; ?>">
                    <i class="fas fa-print"></i>
                    <span>Druckzentrale</span>
                </a>
                <?php endif; ?>
                
                <?php if (isAdmin()): ?>
                <a href="<?php echo $currentPage === 'admin-qr-codes' ? 'javascript:void(0)' : '?page=admin-qr-codes'; ?>" data-page="admin-qr-codes" class="nav-link <?php echo $currentPage === 'admin-qr-codes' ? 'active' : ''; ?>">
                    <i class="fas fa-qrcode"></i>
                    <span>QR-Anwesenheit</span>
                </a>
                
                <a href="<?php echo $currentPage === 'admin-registrations' ? 'javascript:void(0)' : '?page=admin-registrations'; ?>" data-page="admin-registrations" class="nav-link <?php echo $currentPage === 'admin-registrations' ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Einschreibungen</span>
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
                case 'admin-qr-codes':
                    if (isAdmin()) {
                        include 'pages/admin-qr-codes.php';
                        $pageLoaded = true;
                    }
                    break;
                case 'admin-registrations':
                    if (isAdmin()) {
                        include 'pages/admin-registrations.php';
                        $pageLoaded = true;
                    }
                    break;
                case 'qr-checkin':
                    include 'pages/qr-checkin.php';
                    $pageLoaded = true;
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
    <script src="<?php echo BASE_URL; ?>assets/js/easter-eggs.js"></script>
    
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
            // Verhindere mehrfaches Starten
            if (window.currentGuidedTour && window.currentGuidedTour.isActive) {
                console.warn('Eine Tour ist bereits aktiv');
                return;
            }
            
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

                window.currentGuidedTour = new GuidedTour({
                    steps: steps,
                    role: userRole,
                    onComplete: () => {
                        if (typeof showToast !== 'undefined') {
                            showToast('Tour abgeschlossen! 🎉', 'success');
                        }
                        window.currentGuidedTour = null;
                    },
                    onSkip: () => {
                        if (typeof showToast !== 'undefined') {
                            showToast('Tour übersprungen', 'info');
                        }
                        window.currentGuidedTour = null;
                    }
                });
                window.currentGuidedTour.reset(); // Allow restarting
                window.currentGuidedTour.start();
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

        // ==========================================================================
        // EXHIBITOR MODAL SYSTEM (GLOBAL) - Komplett neu geschrieben
        // ==========================================================================
        const ExhibitorModal = {
            overlay: null,
            
            init() {
                this.overlay = document.getElementById('exhibitorModalOverlay');
                if (!this.overlay) return;
                
                // Click auf Overlay schließt Modal
                this.overlay.addEventListener('click', (e) => {
                    if (e.target === this.overlay) {
                        this.close();
                    }
                });
                
                // ESC schließt Modal
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape' && this.overlay.classList.contains('active')) {
                        this.close();
                    }
                });
            },
            
            open(exhibitorId) {
                if (!this.overlay) this.init();
                
                // Modal anzeigen
                this.overlay.classList.add('active');
                document.body.style.overflow = 'hidden';
                
                // Register Button aktualisieren
                const regBtn = document.getElementById('modalRegisterBtn');
                if (regBtn) {
                    regBtn.href = '?page=registration&exhibitor=' + exhibitorId;
                    const isManagement = <?php echo (isTeacher() || isAdmin()) ? 'true' : 'false'; ?>;
                    const isRegistrationPage = '<?php echo $currentPage; ?>' === 'registration';
                    regBtn.style.display = (isManagement || isRegistrationPage) ? 'none' : 'inline-flex';
                }
                
                // Daten laden
                this.loadDetails(exhibitorId);
            },
            
            close() {
                if (!this.overlay) return;
                this.overlay.classList.remove('active');
                document.body.style.overflow = '';
            },
            
            loadDetails(exhibitorId) {
                const modalBody = document.getElementById('modalBody');
                modalBody.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;padding:3rem;"><i class="fas fa-spinner fa-spin" style="font-size:2rem;color:#10b981;"></i></div>';
                
                fetch('api/get-exhibitor.php?id=' + exhibitorId + '&tab=details')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('modalTitle').textContent = data.exhibitor.name;
                            modalBody.innerHTML = data.content;
                        } else {
                            modalBody.innerHTML = '<div style="text-align:center;padding:3rem;color:#ef4444;">Fehler beim Laden</div>';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        modalBody.innerHTML = '<div style="text-align:center;padding:3rem;color:#ef4444;">Fehler beim Laden</div>';
                    });
            }
        };
        
        // Globale Funktionen für onclick Handler
        function openExhibitorModal(exhibitorId) {
            ExhibitorModal.open(exhibitorId);
        }
        
        function closeExhibitorModal() {
            ExhibitorModal.close();
        }
        
        // Initialisierung
        document.addEventListener('DOMContentLoaded', () => {
            ExhibitorModal.init();
        });
    </script>

    <!-- Global Exhibitor Modal - Komplett neu geschrieben -->
    <div id="exhibitorModalOverlay" class="exhibitor-modal-overlay">
        <div class="exhibitor-modal-box">
            <!-- Modal Header -->
            <div style="padding: 1rem 1.5rem; border-bottom: 1px solid #f3f4f6; display: flex; align-items: center; justify-content: space-between;">
                <h2 id="modalTitle" style="font-size: 1.25rem; font-weight: 700; color: #1f2937; margin: 0;">Unternehmensdetails</h2>
                <button onclick="closeExhibitorModal()" style="background: none; border: none; padding: 0.5rem; cursor: pointer; color: #9ca3af; border-radius: 0.5rem; transition: all 0.2s;" onmouseover="this.style.background='#f3f4f6';this.style.color='#4b5563'" onmouseout="this.style.background='none';this.style.color='#9ca3af'">
                    <i class="fas fa-times" style="font-size: 1.125rem;"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div id="modalBody" style="padding: 1.5rem; overflow-y: auto; flex: 1;">
                <div style="display: flex; align-items: center; justify-content: center; padding: 3rem;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #10b981;"></i>
                </div>
            </div>

            <!-- Modal Footer -->
            <div style="padding: 1rem 1.5rem; border-top: 1px solid #f3f4f6; background: #f9fafb; display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button onclick="closeExhibitorModal()" style="padding: 0.5rem 1rem; background: #e5e7eb; color: #374151; border: none; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 500; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='#d1d5db'" onmouseout="this.style.background='#e5e7eb'">
                    Schließen
                </button>
                <a id="modalRegisterBtn" href="#" style="padding: 0.5rem 1rem; background: #10b981; color: white; border: none; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 500; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; transition: background 0.2s;" onmouseover="this.style.background='#059669'" onmouseout="this.style.background='#10b981'">
                    <i class="fas fa-user-plus" style="margin-right: 0.5rem;"></i> Einschreiben
                </a>
            </div>
        </div>
    </div>
</body>
</html>
