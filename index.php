<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

requireLogin();

$db = getDB();

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
                        <p class="text-xs text-gray-600 truncate"><?php echo isAdmin() ? 'Administrator' : 'Schüler'; ?></p>
                    </div>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="space-y-2">
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
                                'admin-dashboard' => 'Admin Dashboard',
                                'admin-exhibitors' => 'Aussteller verwalten',
                                'admin-rooms' => 'Raum-Zuteilung',
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
                case 'admin-dashboard':
                    if (isAdmin()) include 'pages/admin-dashboard.php';
                    break;
                case 'admin-exhibitors':
                    if (isAdmin()) include 'pages/admin-exhibitors.php';
                    break;
                case 'admin-rooms':
                    if (isAdmin()) include 'pages/admin-rooms.php';
                    break;
                case 'admin-settings':
                    if (isAdmin()) include 'pages/admin-settings.php';
                    break;
                default:
                    include 'pages/exhibitors.php';
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
