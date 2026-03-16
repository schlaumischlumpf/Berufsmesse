<?php
/**
 * Globaler Super-Admin Bereich
 * Zugriff OHNE Schulkontext — für schulübergreifende Verwaltung
 */
require_once 'config.php';
require_once 'functions.php';

requireLogin();

if (!isAdmin()) {
    header('Location: ' . BASE_URL . 'schools.php');
    exit();
}

$db = getDB();
$currentPage = $_GET['page'] ?? 'overview';

$pages = [
    'overview'           => ['title' => 'Übersicht',           'icon' => 'fa-tachometer-alt', 'file' => null],
    'schools'            => ['title' => 'Schulverwaltung',     'icon' => 'fa-school',         'file' => 'pages/admin-schools.php'],
    'editions'           => ['title' => 'Messe-Editionen',     'icon' => 'fa-calendar-alt',   'file' => 'pages/admin-editions.php'],
    'exhibitor-accounts' => ['title' => 'Aussteller-Accounts', 'icon' => 'fa-user-tie',       'file' => null],
    'global-logs'        => ['title' => 'Globale Logs',        'icon' => 'fa-history',        'file' => null],
];

if (!isset($pages[$currentPage])) {
    $currentPage = 'overview';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Berufsmesse — Globale Verwaltung</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/design-system.css">
    <script src="assets/js/darkmode.js"></script>
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; }

        /* Darkmode Starburst Animation */
        .theme-particle {
            position: fixed;
            pointer-events: none;
            z-index: 9999;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            opacity: 0;
        }

        @keyframes particle-burst {
            0% {
                transform: translate(0, 0) scale(0);
                opacity: 1;
                box-shadow: 0 0 20px currentColor;
            }
            60% {
                opacity: 0.8;
                box-shadow: 0 0 40px currentColor;
            }
            100% {
                transform: translate(var(--tx), var(--ty)) scale(3);
                opacity: 0;
                box-shadow: 0 0 60px currentColor;
            }
        }

        .theme-wave {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 9998;
            opacity: 0;
        }

        @keyframes wave-expand {
            0% {
                clip-path: circle(0% at var(--toggle-x, 50%) var(--toggle-y, 50%));
                opacity: 1;
            }
            100% {
                clip-path: circle(150% at var(--toggle-x, 50%) var(--toggle-y, 50%));
                opacity: 1;
            }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Top Bar -->
    <header class="bg-white border-b border-gray-200 px-6 py-3 flex items-center justify-between sticky top-0 z-30">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg flex items-center justify-center shadow-sm" style="background: linear-gradient(135deg, #a8e6cf 0%, #c3b1e1 100%);">
                <i class="fas fa-shield-alt text-white text-sm"></i>
            </div>
            <div>
                <h1 class="text-lg font-bold text-gray-800 leading-tight">Globale Verwaltung</h1>
                <p class="text-xs text-gray-400">Super-Admin Bereich</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <span class="text-sm text-gray-500">
                <i class="fas fa-user-shield mr-1"></i>
                <?php echo htmlspecialchars($_SESSION['firstname'] . ' ' . $_SESSION['lastname']); ?>
            </span>
            <a href="<?php echo BASE_URL; ?>schools.php" class="px-3 py-1.5 text-xs bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition-colors">
                <i class="fas fa-school mr-1"></i> Zu den Schulen
            </a>
            <a href="<?php echo BASE_URL; ?>login.php?logout=1" class="px-3 py-1.5 text-xs bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition-colors">
                <i class="fas fa-sign-out-alt mr-1"></i> Logout
            </a>
        </div>
    </header>

    <div class="flex">
        <!-- Sidebar -->
        <aside class="w-56 bg-white border-r border-gray-200 min-h-[calc(100vh-57px)] p-4 flex-shrink-0">
            <nav class="space-y-1">
                <?php foreach ($pages as $key => $page): ?>
                <a href="?page=<?php echo $key; ?>"
                   class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm font-medium transition-colors <?php echo $currentPage === $key ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-800'; ?>">
                    <i class="fas <?php echo $page['icon']; ?> text-xs w-4 text-center"></i>
                    <?php echo $page['title']; ?>
                </a>
                <?php endforeach; ?>
            </nav>

            <div class="mt-6 pt-4 border-t border-gray-100">
                <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Schulen</p>
                <?php
                $gaSchools = $db->query("SELECT id, name, slug, is_active FROM schools ORDER BY name")->fetchAll();
                foreach ($gaSchools as $gaS):
                ?>
                <a href="<?php echo BASE_URL . htmlspecialchars($gaS['slug']); ?>/index.php?page=admin-dashboard"
                   class="flex items-center gap-2 px-3 py-1.5 text-xs rounded-lg transition-colors <?php echo $gaS['is_active'] ? 'text-gray-600 hover:bg-gray-50' : 'text-gray-300'; ?>"
                   <?php echo $gaS['is_active'] ? '' : 'title="Inaktiv"'; ?>>
                    <i class="fas fa-school w-3 text-center <?php echo $gaS['is_active'] ? 'text-emerald-400' : 'text-gray-300'; ?>"></i>
                    <span class="truncate"><?php echo htmlspecialchars($gaS['name']); ?></span>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Darkmode Toggle -->
            <div class="mt-6 pt-4 border-t border-gray-100">
                <div class="flex items-center justify-between px-3">
                    <span id="darkmode-label" class="text-sm text-gray-500">Dunkel</span>
                    <button id="darkmode-toggle" onclick="toggleDarkmodeWithAnimation(event)" class="darkmode-switch" aria-label="Dark mode toggle">
                        <div class="toggle-clouds"><span></span><span></span><span></span></div>
                        <div class="toggle-stars"><span></span><span></span><span></span><span></span><span></span></div>
                        <div class="toggle-knob"><div class="sun-ray"></div></div>
                    </button>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-6 max-w-6xl">
            <?php if ($currentPage === 'overview'): ?>
                <!-- Übersicht -->
                <?php
                $schoolCount = $db->query("SELECT COUNT(*) FROM schools WHERE is_active = 1")->fetchColumn();
                $editionCount = $db->query("SELECT COUNT(*) FROM messe_editions WHERE status = 'active'")->fetchColumn();
                $exhibitorCount = $db->query("SELECT COUNT(DISTINCT eu.user_id) FROM exhibitor_users eu WHERE eu.invite_accepted = 1")->fetchColumn();
                $studentCount = $db->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
                ?>
                <h2 class="text-xl font-bold text-gray-800 mb-6">
                    <i class="fas fa-tachometer-alt mr-2 text-blue-500"></i>Globale Übersicht
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                    <div class="bg-white rounded-xl border border-gray-200 p-5">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-blue-50 rounded-lg flex items-center justify-center">
                                <i class="fas fa-school text-blue-500"></i>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-800"><?php echo $schoolCount; ?></p>
                                <p class="text-xs text-gray-500">Aktive Schulen</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 p-5">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-emerald-50 rounded-lg flex items-center justify-center">
                                <i class="fas fa-calendar-check text-emerald-500"></i>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-800"><?php echo $editionCount; ?></p>
                                <p class="text-xs text-gray-500">Aktive Messen</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 p-5">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-purple-50 rounded-lg flex items-center justify-center">
                                <i class="fas fa-building text-purple-500"></i>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-800"><?php echo $exhibitorCount; ?></p>
                                <p class="text-xs text-gray-500">Aktive Aussteller</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 p-5">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-amber-50 rounded-lg flex items-center justify-center">
                                <i class="fas fa-users text-amber-500"></i>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-800"><?php echo $studentCount; ?></p>
                                <p class="text-xs text-gray-500">Schüler gesamt</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pro Schule -->
                <h3 class="text-sm font-semibold text-gray-600 mb-3 uppercase tracking-wider">Pro Schule</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php
                    $schoolStats = $db->query("
                        SELECT s.id, s.name, s.slug, s.is_active,
                               (SELECT COUNT(*) FROM messe_editions me WHERE me.school_id = s.id AND me.status = 'active') as active_editions,
                               (SELECT me2.name FROM messe_editions me2 WHERE me2.school_id = s.id AND me2.status = 'active' LIMIT 1) as edition_name,
                               (SELECT COUNT(*) FROM users u WHERE u.school_id = s.id AND u.role = 'student') as student_count,
                               (SELECT COUNT(*) FROM exhibitors e JOIN messe_editions me3 ON e.edition_id = me3.id WHERE me3.school_id = s.id AND me3.status = 'active' AND e.active = 1) as exhibitor_count
                        FROM schools s ORDER BY s.name
                    ")->fetchAll();
                    foreach ($schoolStats as $ss):
                    ?>
                    <div class="bg-white rounded-xl border border-gray-200 p-5 <?php echo $ss['is_active'] ? '' : 'opacity-50'; ?>">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="font-semibold text-gray-800"><?php echo htmlspecialchars($ss['name']); ?></h4>
                            <span class="text-xs px-2 py-0.5 rounded-full <?php echo $ss['is_active'] ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600'; ?>">
                                <?php echo $ss['is_active'] ? 'Aktiv' : 'Inaktiv'; ?>
                            </span>
                        </div>
                        <?php if ($ss['edition_name']): ?>
                        <p class="text-xs text-gray-500 mb-2">
                            <i class="fas fa-calendar-check mr-1 text-emerald-400"></i>
                            <?php echo htmlspecialchars($ss['edition_name']); ?>
                        </p>
                        <?php endif; ?>
                        <div class="grid grid-cols-2 gap-2 text-xs text-gray-500">
                            <div><i class="fas fa-users mr-1"></i> <?php echo (int)$ss['student_count']; ?> Schüler</div>
                            <div><i class="fas fa-building mr-1"></i> <?php echo (int)$ss['exhibitor_count']; ?> Aussteller</div>
                        </div>
                        <div class="mt-3 flex gap-2">
                            <a href="<?php echo BASE_URL . htmlspecialchars($ss['slug']); ?>/index.php?page=admin-dashboard"
                               class="flex-1 text-center px-3 py-1.5 bg-blue-50 text-blue-600 rounded-lg text-xs font-medium hover:bg-blue-100 transition-colors">
                                <i class="fas fa-external-link-alt mr-1"></i> Öffnen
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

            <?php elseif ($currentPage === 'global-logs'): ?>
                <!-- Globale Audit-Logs -->
                <?php
                $glFilterUser = $_GET['filter_user'] ?? '';
                $glFilterAction = $_GET['filter_action'] ?? '';
                $glFilterDate = $_GET['filter_date'] ?? '';
                $glFilterSeverity = $_GET['filter_severity'] ?? '';
                $glFilterSchool = $_GET['filter_school'] ?? '';
                $glPage = max(1, intval($_GET['log_page'] ?? 1));
                $glPerPage = 50;
                $glOffset = ($glPage - 1) * $glPerPage;

                $glWhere = [];
                $glParams = [];

                if ($glFilterSchool !== '') {
                    if ($glFilterSchool === 'global') {
                        $glWhere[] = "al.school_id IS NULL";
                    } else {
                        $glWhere[] = "al.school_id = ?";
                        $glParams[] = intval($glFilterSchool);
                    }
                }
                if ($glFilterUser) {
                    $glWhere[] = "(al.username LIKE ? OR al.user_id = ?)";
                    $glParams[] = "%$glFilterUser%";
                    $glParams[] = intval($glFilterUser);
                }
                if ($glFilterAction) {
                    $glWhere[] = "al.action LIKE ?";
                    $glParams[] = "%$glFilterAction%";
                }
                if ($glFilterDate) {
                    $glWhere[] = "DATE(al.created_at) = ?";
                    $glParams[] = $glFilterDate;
                }
                if ($glFilterSeverity) {
                    $glWhere[] = "al.severity = ?";
                    $glParams[] = $glFilterSeverity;
                }

                $glWhereStr = !empty($glWhere) ? 'WHERE ' . implode(' AND ', $glWhere) : '';

                $countStmt = $db->prepare("SELECT COUNT(*) FROM audit_logs al $glWhereStr");
                $countStmt->execute($glParams);
                $glTotal = (int)$countStmt->fetchColumn();
                $glTotalPages = max(1, ceil($glTotal / $glPerPage));

                $glParams[] = $glPerPage;
                $glParams[] = $glOffset;
                $logStmt = $db->prepare("
                    SELECT al.*, s.name as school_name
                    FROM audit_logs al
                    LEFT JOIN schools s ON al.school_id = s.id
                    $glWhereStr
                    ORDER BY al.created_at DESC
                    LIMIT ? OFFSET ?
                ");
                $logStmt->execute($glParams);
                $glLogs = $logStmt->fetchAll();

                // Schulen für Filter laden
                $glSchools = $db->query("SELECT id, name FROM schools ORDER BY name")->fetchAll();
                ?>

                <h2 class="text-xl font-bold text-gray-800 mb-6">
                    <i class="fas fa-history mr-2 text-blue-500"></i>Globale Audit-Logs
                </h2>

                <!-- Filter -->
                <form method="GET" class="bg-white rounded-xl border border-gray-200 p-4 mb-6">
                    <input type="hidden" name="page" value="global-logs">
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                        <select name="filter_school" class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
                            <option value="">Alle Schulen</option>
                            <option value="global" <?php echo $glFilterSchool === 'global' ? 'selected' : ''; ?>>Global (schulübergreifend)</option>
                            <?php foreach ($glSchools as $gs): ?>
                                <option value="<?php echo $gs['id']; ?>" <?php echo $glFilterSchool == $gs['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($gs['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="filter_user" placeholder="Benutzer" value="<?php echo htmlspecialchars($glFilterUser); ?>"
                               class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
                        <input type="text" name="filter_action" placeholder="Aktion" value="<?php echo htmlspecialchars($glFilterAction); ?>"
                               class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
                        <input type="date" name="filter_date" value="<?php echo htmlspecialchars($glFilterDate); ?>"
                               class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
                        <select name="filter_severity" class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
                            <option value="">Alle Stufen</option>
                            <option value="info" <?php echo $glFilterSeverity === 'info' ? 'selected' : ''; ?>>Info</option>
                            <option value="warning" <?php echo $glFilterSeverity === 'warning' ? 'selected' : ''; ?>>Warnung</option>
                            <option value="error" <?php echo $glFilterSeverity === 'error' ? 'selected' : ''; ?>>Fehler</option>
                        </select>
                        <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg text-sm hover:bg-blue-600 transition">
                            <i class="fas fa-filter mr-1"></i>Filtern
                        </button>
                    </div>
                </form>

                <p class="text-sm text-gray-500 mb-4"><?php echo $glTotal; ?> Einträge gefunden (Seite <?php echo $glPage; ?>/<?php echo $glTotalPages; ?>)</p>

                <!-- Logs-Tabelle -->
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Zeit</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Schule</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Benutzer</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aktion</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Details</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stufe</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (empty($glLogs)): ?>
                                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Keine Einträge gefunden.</td></tr>
                                <?php else: ?>
                                <?php foreach ($glLogs as $log): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2.5 text-xs text-gray-500 whitespace-nowrap"><?php echo date('d.m.Y H:i', strtotime($log['created_at'])); ?></td>
                                    <td class="px-4 py-2.5 text-xs">
                                        <?php if ($log['school_name']): ?>
                                            <span class="px-1.5 py-0.5 rounded bg-blue-50 text-blue-700"><?php echo htmlspecialchars($log['school_name']); ?></span>
                                        <?php else: ?>
                                            <span class="px-1.5 py-0.5 rounded bg-gray-100 text-gray-500">Global</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-2.5 text-xs text-gray-700"><?php echo htmlspecialchars($log['username'] ?? 'System'); ?></td>
                                    <td class="px-4 py-2.5 text-xs font-medium text-gray-800"><?php echo htmlspecialchars($log['action']); ?></td>
                                    <td class="px-4 py-2.5 text-xs text-gray-500 max-w-xs truncate" title="<?php echo htmlspecialchars($log['details'] ?? ''); ?>"><?php echo htmlspecialchars($log['details'] ?? ''); ?></td>
                                    <td class="px-4 py-2.5 text-xs">
                                        <?php
                                        $sevColors = ['info' => 'bg-blue-50 text-blue-700', 'warning' => 'bg-amber-50 text-amber-700', 'error' => 'bg-red-50 text-red-700'];
                                        $sev = $log['severity'] ?? 'info';
                                        ?>
                                        <span class="px-1.5 py-0.5 rounded <?php echo $sevColors[$sev] ?? $sevColors['info']; ?>"><?php echo ucfirst($sev); ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($glTotalPages > 1): ?>
                <div class="flex justify-center gap-2 mt-4">
                    <?php if ($glPage > 1): ?>
                        <a href="?page=global-logs&log_page=<?php echo $glPage - 1; ?>&filter_school=<?php echo urlencode($glFilterSchool); ?>&filter_user=<?php echo urlencode($glFilterUser); ?>&filter_action=<?php echo urlencode($glFilterAction); ?>&filter_date=<?php echo urlencode($glFilterDate); ?>&filter_severity=<?php echo urlencode($glFilterSeverity); ?>"
                           class="px-3 py-1.5 text-sm bg-gray-100 rounded-lg hover:bg-gray-200 transition">&laquo; Zurück</a>
                    <?php endif; ?>
                    <span class="px-3 py-1.5 text-sm text-gray-500">Seite <?php echo $glPage; ?> von <?php echo $glTotalPages; ?></span>
                    <?php if ($glPage < $glTotalPages): ?>
                        <a href="?page=global-logs&log_page=<?php echo $glPage + 1; ?>&filter_school=<?php echo urlencode($glFilterSchool); ?>&filter_user=<?php echo urlencode($glFilterUser); ?>&filter_action=<?php echo urlencode($glFilterAction); ?>&filter_date=<?php echo urlencode($glFilterDate); ?>&filter_severity=<?php echo urlencode($glFilterSeverity); ?>"
                           class="px-3 py-1.5 text-sm bg-gray-100 rounded-lg hover:bg-gray-200 transition">Weiter &raquo;</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

            <?php elseif ($currentPage === 'exhibitor-accounts'): ?>
                <!-- Globale Aussteller-Account-Verwaltung -->
                <?php
                $exSearch = $_GET['search'] ?? '';
                $exFilter = $_GET['filter'] ?? 'all';

                $exWhere = [];
                $exParams = [];

                if ($exSearch) {
                    $exWhere[] = "(u.username LIKE ? OR u.firstname LIKE ? OR u.lastname LIKE ? OR e.name LIKE ?)";
                    $exParams[] = "%$exSearch%";
                    $exParams[] = "%$exSearch%";
                    $exParams[] = "%$exSearch%";
                    $exParams[] = "%$exSearch%";
                }

                if ($exFilter === 'active') {
                    $exWhere[] = "eu.status = 'active' AND eu.invite_accepted = 1";
                } elseif ($exFilter === 'pending') {
                    $exWhere[] = "eu.invite_accepted = 0";
                } elseif ($exFilter === 'cancelled') {
                    $exWhere[] = "eu.status != 'active'";
                }

                $exWhereStr = !empty($exWhere) ? 'WHERE ' . implode(' AND ', $exWhere) : '';

                try {
                    $exStmt = $db->prepare("
                        SELECT eu.*, u.username, u.firstname, u.lastname, u.email,
                               e.name as exhibitor_name, e.id as exhibitor_id,
                               me.name as edition_name, s.name as school_name
                        FROM exhibitor_users eu
                        JOIN users u ON eu.user_id = u.id
                        JOIN exhibitors e ON eu.exhibitor_id = e.id
                        JOIN messe_editions me ON e.edition_id = me.id
                        JOIN schools s ON me.school_id = s.id
                        $exWhereStr
                        ORDER BY eu.id DESC
                    ");
                    $exStmt->execute($exParams);
                    $exAccounts = $exStmt->fetchAll();
                } catch (PDOException $e) {
                    $exAccounts = [];
                    $exError = "Fehler beim Laden der Accounts: " . $e->getMessage();
                }
                ?>

                <h2 class="text-xl font-bold text-gray-800 mb-6">
                    <i class="fas fa-user-tie mr-2 text-purple-500"></i>Globale Aussteller-Account-Verwaltung
                </h2>

                <?php if (isset($exError)): ?>
                <div class="bg-red-50 border border-red-200 p-4 rounded-lg mb-4 text-red-700 text-sm">
                    <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($exError); ?>
                </div>
                <?php endif; ?>

                <!-- Filter + Suche -->
                <form method="GET" class="bg-white rounded-xl border border-gray-200 p-4 mb-6">
                    <input type="hidden" name="page" value="exhibitor-accounts">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <input type="text" name="search" placeholder="Suche nach Name, Username..." value="<?php echo htmlspecialchars($exSearch); ?>"
                               class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
                        <select name="filter" class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
                            <option value="all" <?php echo $exFilter === 'all' ? 'selected' : ''; ?>>Alle</option>
                            <option value="active" <?php echo $exFilter === 'active' ? 'selected' : ''; ?>>Aktiv</option>
                            <option value="pending" <?php echo $exFilter === 'pending' ? 'selected' : ''; ?>>Einladung ausstehend</option>
                            <option value="cancelled" <?php echo $exFilter === 'cancelled' ? 'selected' : ''; ?>>Abgesagt</option>
                        </select>
                        <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg text-sm hover:bg-blue-600 transition">
                            <i class="fas fa-filter mr-1"></i>Filtern
                        </button>
                    </div>
                </form>

                <p class="text-sm text-gray-500 mb-4"><?php echo count($exAccounts); ?> Accounts gefunden</p>

                <!-- Accounts-Tabelle -->
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Benutzer</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aussteller</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Schule</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Edition</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aktionen</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (empty($exAccounts)): ?>
                                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Keine Accounts gefunden.</td></tr>
                                <?php else: ?>
                                <?php foreach ($exAccounts as $acc): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2.5">
                                        <div class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($acc['username']); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($acc['firstname'] . ' ' . $acc['lastname']); ?></div>
                                        <?php if ($acc['email']): ?>
                                        <div class="text-xs text-gray-400"><?php echo htmlspecialchars($acc['email']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-2.5 text-sm text-gray-700"><?php echo htmlspecialchars($acc['exhibitor_name']); ?></td>
                                    <td class="px-4 py-2.5 text-xs text-gray-500"><?php echo htmlspecialchars($acc['school_name']); ?></td>
                                    <td class="px-4 py-2.5 text-xs text-gray-500"><?php echo htmlspecialchars($acc['edition_name']); ?></td>
                                    <td class="px-4 py-2.5">
                                        <?php
                                        $st = $acc['status'] ?? 'active';
                                        $inv = $acc['invite_accepted'];
                                        if ($st === 'active' && $inv) {
                                            echo '<span class="px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700">Aktiv</span>';
                                        } elseif ($st === 'active' && !$inv) {
                                            echo '<span class="px-2 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-700">Einladung ausstehend</span>';
                                        } else {
                                            echo '<span class="px-2 py-0.5 rounded-full text-xs font-medium bg-red-50 text-red-600">Abgesagt</span>';
                                        }
                                        ?>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <div class="flex gap-1">
                                            <a href="?page=exhibitor-accounts&view=<?php echo $acc['user_id']; ?>"
                                               class="px-2 py-1 text-xs bg-blue-50 text-blue-600 rounded hover:bg-blue-100 transition"
                                               title="Details anzeigen">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($st !== 'active' || !$inv): ?>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                <input type="hidden" name="user_id" value="<?php echo $acc['user_id']; ?>">
                                                <input type="hidden" name="exhibitor_id" value="<?php echo $acc['exhibitor_id']; ?>">
                                                <button type="submit" name="global_delete_account"
                                                        class="px-2 py-1 text-xs bg-red-50 text-red-600 rounded hover:bg-red-100 transition"
                                                        title="Account löschen"
                                                        onclick="return confirm('Account wirklich löschen?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php else: ?>
                <?php
                // Sub-Page laden
                $pageFile = $pages[$currentPage]['file'];
                if ($pageFile && file_exists($pageFile)) {
                    include $pageFile;
                } else {
                    echo '<p class="text-gray-500">Seite nicht gefunden.</p>';
                }
                ?>
            <?php endif; ?>
        </main>
    </div>

    <script>
    function toggleDarkmodeWithAnimation(event) {
        const btn = event.currentTarget;
        const rect = btn.getBoundingClientRect();
        const centerX = rect.left + rect.width / 2;
        const centerY = rect.top + rect.height / 2;
        const x = (centerX / window.innerWidth) * 100;
        const y = (centerY / window.innerHeight) * 100;

        const html = document.documentElement;
        const isDark = html.classList.contains('dark');

        // Farben für die Animation
        const particleColor = isDark ? '#fbbf24' : '#a78bfa'; // Sonne-gelb oder Mond-lila
        const targetBg = isDark ? '#f9fafb' : '#1a1a1a';

        // 1. Partikel-Burst erstellen (25 Partikel)
        const particles = [];
        for (let i = 0; i < 25; i++) {
            const particle = document.createElement('div');
            particle.className = 'theme-particle';
            particle.style.left = centerX + 'px';
            particle.style.top = centerY + 'px';
            particle.style.background = particleColor;

            // Zufällige Richtung
            const angle = (Math.PI * 2 * i) / 25;
            const distance = 300 + Math.random() * 400;
            const tx = Math.cos(angle) * distance;
            const ty = Math.sin(angle) * distance;

            particle.style.setProperty('--tx', tx + 'px');
            particle.style.setProperty('--ty', ty + 'px');
            particle.style.animationDelay = (i * 0.02) + 's';
            particle.style.animation = 'particle-burst 1.2s cubic-bezier(0.4, 0, 0.2, 1) forwards';

            document.body.appendChild(particle);
            particles.push(particle);
        }

        // 2. Welle der neuen Farbe
        const wave = document.createElement('div');
        wave.className = 'theme-wave';
        wave.style.background = targetBg;
        wave.style.setProperty('--toggle-x', x + '%');
        wave.style.setProperty('--toggle-y', y + '%');
        document.body.appendChild(wave);

        // 3. Nach kurzem Delay: Welle ausbreiten und Theme wechseln
        setTimeout(() => {
            wave.style.animation = 'wave-expand 1s cubic-bezier(0.4, 0, 0.2, 1) forwards';
            toggleDarkmode();
        }, 300);

        // 4. Cleanup nach Animation
        setTimeout(() => {
            particles.forEach(p => p.remove());
            wave.remove();
        }, 1800);
    }
    </script>
</body>
</html>
