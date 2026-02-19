<?php
// Admin Berechtigungsverwaltung (Issue #10, #26)

// Datenbankverbindung holen
$db = getDB();

// Handle Permission Changes (mit Gruppen-Support)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_permissions'])) {
    if (!isAdmin() && !hasPermission('berechtigungen_vergeben')) die('Keine Berechtigung');
    $userId = intval($_POST['user_id']);
    $permissions = $_POST['permissions'] ?? [];
    $groupIds = $_POST['group_ids'] ?? [];

    if (isAdmin()) {
        // 1. Gruppen-Zuordnungen aktualisieren
        $db->prepare("DELETE FROM user_permission_groups WHERE user_id = ?")->execute([$userId]);
        foreach ($groupIds as $groupId) {
            $stmt = $db->prepare("INSERT INTO user_permission_groups (user_id, group_id) VALUES (?, ?)");
            $stmt->execute([$userId, intval($groupId)]);
        }

        // 2. Individuelle Berechtigungen aktualisieren
        $db->prepare("DELETE FROM user_permissions WHERE user_id = ?")->execute([$userId]);
        foreach ($permissions as $permission) {
            grantPermission($userId, $permission);
        }
    } else {
        // Nicht-Admins können nur eigene Berechtigungen vergeben/entziehen
        $ownPermissions = getUserPermissions($_SESSION['user_id']);
        // Nur eigene Berechtigungen beim Zielbenutzer entfernen
        foreach ($ownPermissions as $perm) {
            $db->prepare("DELETE FROM user_permissions WHERE user_id = ? AND permission = ?")->execute([$userId, $perm]);
        }
        // Nur eigene Berechtigungen neu setzen
        foreach ($permissions as $permission) {
            if (in_array($permission, $ownPermissions)) {
                grantPermission($userId, $permission);
            }
        }
    }

    logAuditAction('Berechtigungen geändert', "Berechtigungen für Benutzer #$userId aktualisiert: " . implode(', ', $permissions) . " | Gruppen: " . implode(', ', $groupIds));

    $message = ['type' => 'success', 'text' => 'Berechtigungen erfolgreich aktualisiert'];
    $reopenUserId = $userId; // Flag to reopen modal
}

// Handle Apply Permission Group
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_group'])) {
    if (!isAdmin() && !hasPermission('berechtigungen_vergeben')) die('Keine Berechtigung');
    $userId = intval($_POST['user_id']);
    $groupId = intval($_POST['group_id']);
    
    // Gruppe anwenden
    applyPermissionGroup($userId, $groupId);
    
    logAuditAction('Berechtigungsgruppe angewendet', "Gruppe #$groupId auf Benutzer #$userId angewendet");
    
    $message = ['type' => 'success', 'text' => 'Berechtigungsgruppe erfolgreich angewendet'];
}

// Handle Create Permission Group
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_group'])) {
    if (!isAdmin() && !hasPermission('berechtigungsgruppen_verwalten')) die('Keine Berechtigung');
    $groupName = trim($_POST['group_name'] ?? '');
    $groupDescription = trim($_POST['group_description'] ?? '');
    $groupPermissions = $_POST['group_permissions'] ?? [];
    
    if (!empty($groupName) && !empty($groupPermissions)) {
        try {
            $stmt = $db->prepare("INSERT INTO permission_groups (name, description, created_by) VALUES (?, ?, ?)");
            $stmt->execute([$groupName, $groupDescription, $_SESSION['user_id']]);
            $newGroupId = $db->lastInsertId();
            
            $insertStmt = $db->prepare("INSERT INTO permission_group_items (group_id, permission) VALUES (?, ?)");
            foreach ($groupPermissions as $perm) {
                $insertStmt->execute([$newGroupId, $perm]);
            }
            
            logAuditAction('Berechtigungsgruppe erstellt', "Gruppe '$groupName' mit " . count($groupPermissions) . " Berechtigungen erstellt");
            $message = ['type' => 'success', 'text' => "Berechtigungsgruppe '$groupName' erfolgreich erstellt"];
        } catch (PDOException $e) {
            $message = ['type' => 'error', 'text' => 'Fehler: Gruppenname existiert bereits oder ungültige Daten'];
        }
    } else {
        $message = ['type' => 'error', 'text' => 'Name und mindestens eine Berechtigung erforderlich'];
    }
}

// Handle Delete Permission Group
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_group'])) {
    if (!isAdmin() && !hasPermission('berechtigungsgruppen_verwalten')) die('Keine Berechtigung');
    $groupId = intval($_POST['group_id']);
    try {
        $stmt = $db->prepare("SELECT name FROM permission_groups WHERE id = ?");
        $stmt->execute([$groupId]);
        $group = $stmt->fetch();
        
        $db->prepare("DELETE FROM permission_groups WHERE id = ?")->execute([$groupId]);
        logAuditAction('Berechtigungsgruppe gelöscht', "Gruppe '" . ($group['name'] ?? $groupId) . "' gelöscht");
        $message = ['type' => 'success', 'text' => 'Berechtigungsgruppe gelöscht'];
    } catch (Exception $e) {
        $message = ['type' => 'error', 'text' => 'Fehler beim Löschen der Gruppe'];
    }
}

// Alle Benutzer laden - DEBUG VERSION
// Erstmal ALLE Benutzer holen, ohne WHERE
$stmt = $db->query("
    SELECT u.*,
           (SELECT COUNT(*) FROM user_permissions WHERE user_id = u.id) as permission_count
    FROM users u
    ORDER BY u.role ASC, u.lastname ASC, u.firstname ASC
");
$allUsersDebug = $stmt->fetchAll();

// Jetzt mit Filter
$stmt = $db->query("
    SELECT u.*,
           (SELECT COUNT(*) FROM user_permissions WHERE user_id = u.id) as permission_count
    FROM users u
    WHERE LOWER(u.role) IN ('admin', 'teacher', 'orga')
    ORDER BY u.role ASC, u.lastname ASC, u.firstname ASC
");
$users = $stmt->fetchAll();

// Debug-Ausgabe (nur für Test)
if (empty($users)) {
    error_log("DEBUG: No users found with role filter. Total users: " . count($allUsersDebug));
    error_log("DEBUG: All roles: " . json_encode(array_column($allUsersDebug, 'role')));
    // Fallback: Zeige alle Benutzer
    $users = $allUsersDebug;
}

// Verfügbare Berechtigungen (gruppiert)
$availablePermissions = getAvailablePermissions();
$allPermissions = getAllPermissionKeys();
$permissionDependencies = getPermissionDependencies();

// Berechtigungen des aktuellen Nutzers (für Einschränkung im Modal)
$currentUserPermissions = isAdmin() ? array_keys($allPermissions) : getUserPermissions($_SESSION['user_id']);

// Berechtigungsgruppen laden
$permissionGroups = getPermissionGroups();

// Statistiken
$stats = [];
$stmt = $db->query("SELECT COUNT(DISTINCT user_id) as count FROM user_permissions");
$stats['users_with_permissions'] = $stmt->fetch()['count'];
$stmt = $db->query("SELECT COUNT(*) as count FROM user_permissions");
$stats['total_permissions'] = $stmt->fetch()['count'];
?>

<div class="space-y-6">
    <?php if (isset($message)): ?>
    <div class="mb-4">
        <?php if ($message['type'] === 'success'): ?>
            <div class="bg-emerald-50 border border-emerald-200 p-4 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-emerald-500 mr-3"></i>
                    <p class="text-emerald-700"><?php echo $message['text']; ?></p>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-red-50 border border-red-200 p-4 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                    <p class="text-red-700"><?php echo $message['text']; ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Header -->
    <div>
        <h2 class="text-xl font-semibold text-gray-800">Berechtigungsverwaltung</h2>
        <p class="text-sm text-gray-500 mt-1">Vergib hier granulare Berechtigungen an Benutzer</p>
    </div>

    <!-- Info Box -->
    <div class="bg-blue-50 border border-blue-100 p-5 rounded-lg">
        <div class="flex items-start">
            <i class="fas fa-info-circle text-blue-500 text-lg mr-3 mt-0.5"></i>
            <div>
                <h3 class="font-semibold text-blue-800 mb-2">Berechtigungskonzept</h3>
                <ul class="text-sm text-blue-700 space-y-1">
                    <li><strong>Administratoren</strong> haben automatisch alle Berechtigungen</li>
                    <li><strong>Orga-Nutzer</strong> haben keine Berechtigungen by default – jede muss explizit vergeben werden</li>
                    <li><strong>Lehrer</strong> und andere Benutzer benötigen explizite Berechtigungen</li>
                    <li>Abhängige Berechtigungen werden beim Vergeben/Entziehen automatisch mit behandelt</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl p-5 border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm mb-1">Benutzer mit Berechtigungen</p>
                    <p class="text-2xl font-semibold text-gray-800"><?php echo $stats['users_with_permissions']; ?></p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-purple-50 flex items-center justify-center">
                    <i class="fas fa-users text-purple-500"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl p-5 border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm mb-1">Gesamt Berechtigungen</p>
                    <p class="text-2xl font-semibold text-gray-800"><?php echo $stats['total_permissions']; ?></p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-indigo-50 flex items-center justify-center">
                    <i class="fas fa-key text-indigo-500"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl p-5 border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm mb-1">Verfügbare Berechtigungen</p>
                    <p class="text-2xl font-semibold text-gray-800"><?php echo count($allPermissions); ?></p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
                    <i class="fas fa-list text-blue-500"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="bg-white rounded-xl border border-gray-100 p-4">
        <div class="relative">
            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
            <input type="text" id="permissionSearchInput" placeholder="Benutzer suchen (Name, Username, Rolle, Berechtigungen)..."
                   class="w-full pl-10 pr-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-400 focus:border-purple-400 transition-all"
                   onkeyup="filterPermissionUsers()">
        </div>
    </div>

    <!-- Users List -->
    <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Benutzer</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Rolle</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Berechtigungen</th>
                        <th class="px-6 py-4 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Aktionen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($users as $user):
                        $userPermissions = getUserPermissions($user['id']);
                        try {
                            $userGroups = getUserPermissionGroups($user['id']);
                        } catch (Exception $e) {
                            $userGroups = [];
                        }
                    ?>
                    <tr class="permission-user-row hover:bg-gray-50 transition"
                        data-name="<?php echo strtolower($user['firstname'] . ' ' . $user['lastname']); ?>"
                        data-username="<?php echo strtolower($user['username']); ?>"
                        data-role="<?php echo strtolower($user['role']); ?>"
                        data-permissions="<?php echo strtolower(implode(' ', $userPermissions)); ?>">
                        <td class="px-6 py-4">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center mr-3">
                                    <span class="font-bold text-sm text-purple-600">
                                        <?php echo strtoupper(substr($user['firstname'], 0, 1) . substr($user['lastname'], 0, 1)); ?>
                                    </span>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></p>
                                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($user['username']); ?></p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <?php if ($user['role'] === 'admin'): ?>
                                <span class="px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-xs font-semibold">
                                    <i class="fas fa-user-shield mr-1"></i>Admin
                                </span>
                            <?php elseif ($user['role'] === 'orga'): ?>
                                <span class="px-3 py-1 bg-orange-100 text-orange-800 rounded-full text-xs font-semibold">
                                    <i class="fas fa-users-cog mr-1"></i>Orga
                                </span>
                            <?php else: ?>
                                <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs font-semibold">
                                    <i class="fas fa-chalkboard-teacher mr-1"></i>Lehrer
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <?php if ($user['role'] === 'admin'): ?>
                                <span class="text-gray-500 italic text-sm">Alle (automatisch)</span>
                            <?php else: ?>
                                <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-semibold">
                                    <?php echo count($userPermissions); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <?php if ($user['role'] !== 'admin'): ?>
                                <div class="flex items-center justify-end gap-2">
                                    <?php if (!empty($permissionGroups)): ?>
                                    <form method="POST" class="inline-flex items-center gap-1">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <select name="group_id" class="text-xs border border-gray-300 rounded-lg px-2 py-1.5 focus:ring-2 focus:ring-indigo-500">
                                            <?php foreach ($permissionGroups as $group): ?>
                                            <option value="<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="apply_group" value="1" 
                                                class="px-2 py-1.5 bg-indigo-100 text-indigo-700 rounded-lg hover:bg-indigo-200 transition text-xs font-semibold"
                                                title="Gruppe anwenden">
                                            <i class="fas fa-layer-group"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <button type="button"
                                            class="permission-btn px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition text-sm font-semibold shadow-sm hover:shadow-md"
                                            data-user-id="<?php echo htmlspecialchars($user['id']); ?>"
                                            data-user-name="<?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?>"
                                            data-permissions='<?php echo htmlspecialchars(json_encode($userPermissions), ENT_QUOTES, 'UTF-8'); ?>'
                                            data-group-ids='<?php echo htmlspecialchars(json_encode($userGroups), ENT_QUOTES, 'UTF-8'); ?>'>
                                        <i class="fas fa-shield-alt mr-2"></i>Berechtigungen
                                    </button>
                                </div>
                                </div>
                            <?php else: ?>
                                <span class="text-gray-400 text-sm italic">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Permission Groups (Issue #26) -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="bg-gradient-to-r from-indigo-600 to-blue-600 text-white px-6 py-4">
            <div class="flex items-center justify-between">
                <h3 class="text-xl font-bold flex items-center">
                    <i class="fas fa-layer-group mr-3"></i>
                    Berechtigungsgruppen
                </h3>
                <button onclick="document.getElementById('createGroupModal').classList.remove('hidden');document.getElementById('createGroupModal').classList.add('flex')" 
                        class="px-4 py-2 bg-white/20 text-white rounded-lg hover:bg-white/30 transition text-sm font-medium">
                    <i class="fas fa-plus mr-1"></i>Neue Gruppe
                </button>
            </div>
        </div>

        <div class="p-6">
            <?php if (empty($permissionGroups)): ?>
            <p class="text-gray-500 text-center py-4">Keine Berechtigungsgruppen vorhanden. Erstelle eine neue Gruppe.</p>
            <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($permissionGroups as $group): 
                    $groupPerms = getPermissionGroupPermissions($group['id']);
                ?>
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <h4 class="font-semibold text-gray-800"><?php echo htmlspecialchars($group['name']); ?></h4>
                            <?php if ($group['description']): ?>
                            <p class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($group['description']); ?></p>
                            <?php endif; ?>
                        </div>
                        <form method="POST" class="inline" onsubmit="return confirm('Gruppe wirklich löschen?')">
                            <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                            <button type="submit" name="delete_group" value="1" class="text-red-400 hover:text-red-600 transition text-sm">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                    <div class="flex flex-wrap gap-1 mb-3">
                        <?php foreach ($groupPerms as $perm): ?>
                        <span class="px-2 py-0.5 bg-indigo-100 text-indigo-700 rounded text-xs font-medium"><?php echo htmlspecialchars($perm); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-xs text-gray-400">
                        <?php echo count($groupPerms); ?> Berechtigungen
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Permission Definitions -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-6 py-4">
            <h3 class="text-xl font-bold flex items-center">
                <i class="fas fa-list mr-3"></i>
                Berechtigungsdefinitionen
            </h3>
        </div>

        <div class="p-6 space-y-4">
            <?php foreach ($availablePermissions as $groupName => $groupPerms): ?>
            <div>
                <h4 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-2"><?php echo htmlspecialchars($groupName); ?></h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <?php foreach ($groupPerms as $key => $description): ?>
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
                        <div class="flex items-start">
                            <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center mr-3 flex-shrink-0">
                                <i class="fas fa-key text-purple-600 text-xs"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-sm text-gray-800 mb-0.5"><?php echo $key; ?></h4>
                                <p class="text-xs text-gray-600"><?php echo $description; ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Permission Modal -->
<div id="permissionModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-6 py-4 rounded-t-xl">
            <h3 class="text-xl font-bold">Berechtigungen bearbeiten</h3>
            <p class="text-sm text-purple-100 mt-1" id="modalUserName"></p>
        </div>
        
        <form method="POST" class="p-6 space-y-6">
            <input type="hidden" name="user_id" id="modal_user_id">

            <!-- Berechtigungsgruppen Sektion -->
            <div class="border-2 border-indigo-200 rounded-lg p-4 bg-indigo-50/50">
                <label class="block text-sm font-bold text-indigo-900 mb-3 flex items-center gap-2">
                    <i class="fas fa-layer-group"></i>
                    Berechtigungsgruppen
                </label>
                <div class="text-xs text-indigo-700 mb-3">
                    Wähle Gruppen aus, um automatisch alle zugehörigen Berechtigungen zu erhalten.
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                    <?php
                    $allPermGroups = getPermissionGroups();
                    foreach ($allPermGroups as $group):
                        $groupPerms = getPermissionGroupPermissions($group['id']);
                    ?>
                    <label class="flex items-start p-3 bg-white rounded-lg border border-indigo-200 hover:bg-indigo-50 cursor-pointer transition group-checkbox-label">
                        <input type="checkbox"
                               name="group_ids[]"
                               value="<?php echo $group['id']; ?>"
                               class="group-checkbox mt-1 mr-3 rounded text-indigo-600 w-4 h-4"
                               data-group-id="<?php echo $group['id']; ?>"
                               data-permissions='<?php echo htmlspecialchars(json_encode($groupPerms), ENT_QUOTES); ?>'>
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-sm text-gray-800"><?php echo htmlspecialchars($group['name']); ?></div>
                            <div class="text-xs text-gray-600 mt-0.5"><?php echo htmlspecialchars($group['description']); ?></div>
                            <div class="text-xs text-indigo-600 mt-1"><?php echo count($groupPerms); ?> Berechtigungen</div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Individuelle Berechtigungen Sektion -->
            <div class="space-y-4">
                <label class="block text-sm font-semibold text-gray-700 mb-3">
                    Individuelle Berechtigungen:
                </label>

                <?php foreach ($availablePermissions as $groupName => $groupPerms): ?>
                <div class="border border-gray-200 rounded-lg overflow-hidden">
                    <div class="bg-gray-50 px-4 py-2 text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        <?php echo htmlspecialchars($groupName); ?>
                    </div>
                    <div class="divide-y divide-gray-100">
                    <?php foreach ($groupPerms as $key => $description): 
                        $canManage = in_array($key, $currentUserPermissions);
                    ?>
                    <label class="flex items-start p-3 <?php echo $canManage ? 'bg-white hover:bg-gray-50 cursor-pointer' : 'bg-gray-50 cursor-not-allowed opacity-50'; ?> transition permission-label" data-key="<?php echo $key; ?>">
                        <input type="checkbox" 
                               name="permissions[]" 
                               value="<?php echo $key; ?>"
                               class="perm-checkbox mt-1 mr-3 rounded text-purple-600 w-4 h-4"
                               data-key="<?php echo $key; ?>"
                               data-deps='<?php echo htmlspecialchars(json_encode(getPermissionDependencies()[$key] ?? []), ENT_QUOTES); ?>'
                               <?php echo $canManage ? '' : 'disabled'; ?>>
                        <div class="flex-1">
                            <div class="font-semibold text-sm text-gray-800"><?php echo $key; ?></div>
                            <div class="text-xs text-gray-600 mt-0.5"><?php echo $description; ?></div>
                            <?php if (!$canManage): ?>
                            <div class="text-xs text-amber-600 mt-0.5"><i class="fas fa-lock mr-1"></i>Keine eigene Berechtigung</div>
                            <?php endif; ?>
                        </div>
                    </label>
                    <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                <button type="button" onclick="closePermissionModal()" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                    Abbrechen
                </button>
                <button type="submit" name="save_permissions" class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
                    <i class="fas fa-save mr-2"></i>Speichern
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Abhängigkeiten (PHP → JS)
const permDeps = <?php echo json_encode($permissionDependencies); ?>;

// Reverse-Map: was hängt von dieser Berechtigung ab?
const permDependents = {};
Object.entries(permDeps).forEach(([perm, deps]) => {
    deps.forEach(dep => {
        if (!permDependents[dep]) permDependents[dep] = [];
        permDependents[dep].push(perm);
    });
});

function getCheckbox(key) {
    return document.querySelector('.perm-checkbox[data-key="' + key + '"]');
}

function checkDependencies(key, checked) {
    if (checked) {
        // Beim Aktivieren: alle transitiven Abhängigkeiten aktivieren (rekursiv)
        const deps = permDeps[key] || [];
        deps.forEach(dep => {
            const cb = getCheckbox(dep);
            if (cb && !cb.checked && !cb.disabled) {
                cb.checked = true;
                // Rekursiv für transitive Abhängigkeiten
                checkDependencies(dep, true);
            }
        });
    } else {
        // Beim Deaktivieren: prüfen, ob andere Berechtigungen davon abhängen
        const dependents = permDependents[key] || [];
        const activeBlockers = dependents.filter(dep => {
            const cb = getCheckbox(dep);
            return cb && cb.checked && !cb.disabled;
        });

        if (activeBlockers.length > 0) {
            // Verhindere das Deaktivieren
            const cb = getCheckbox(key);
            if (cb) {
                cb.checked = true; // Checkbox wieder aktivieren
            }

            // Zeige visuelles Feedback bei den blockierenden Berechtigungen
            activeBlockers.forEach(blocker => {
                const blockerCb = getCheckbox(blocker);
                if (blockerCb) {
                    highlightCheckbox(blockerCb);
                }
            });
        } else {
            // Kein Blocker - normale Deaktivierung
            dependents.forEach(dep => {
                const cb = getCheckbox(dep);
                if (cb && cb.checked && !cb.disabled) {
                    cb.checked = false;
                    checkDependencies(dep, false);
                }
            });
        }
    }
}

// Visuelles Highlight für blockierende Checkboxen
function highlightCheckbox(checkbox) {
    const row = checkbox.closest('.flex.items-start.p-3');
    if (!row) return;

    // Kurzes rotes Flash-Highlight
    row.style.transition = 'background-color 0.3s ease';
    row.style.backgroundColor = '#fee2e2'; // red-100

    // Nach 1 Sekunde zurücksetzen
    setTimeout(() => {
        row.style.backgroundColor = '';
        setTimeout(() => {
            row.style.transition = '';
        }, 300);
    }, 1000);
}

// Event Listener für alle Berechtigungsknöpfe
document.addEventListener('DOMContentLoaded', function() {
    // Alle Berechtigungsknöpfe finden
    const permissionButtons = document.querySelectorAll('.permission-btn');
    
    permissionButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            const userName = this.getAttribute('data-user-name');
            const permissionsJson = this.getAttribute('data-permissions');
            const groupIdsJson = this.getAttribute('data-group-ids');

            let currentPermissions = [];
            let currentGroupIds = [];
            try {
                currentPermissions = JSON.parse(permissionsJson);
                currentGroupIds = JSON.parse(groupIdsJson);
            } catch(e) {
                console.error('Fehler beim Parsen der Berechtigungen:', e);
                currentPermissions = [];
                currentGroupIds = [];
            }

            openPermissionModal(userId, userName, currentPermissions, currentGroupIds);
        });
    });

    // Dependency-Logic für Checkboxen
    document.querySelectorAll('.perm-checkbox').forEach(cb => {
        cb.addEventListener('change', function() {
            checkDependencies(this.getAttribute('data-key'), this.checked);
        });
    });

    // Gruppen-Checkbox-Logik
    document.querySelectorAll('.group-checkbox').forEach(groupCb => {
        groupCb.addEventListener('change', function() {
            const groupId = this.getAttribute('data-group-id');
            const permissionsJson = this.getAttribute('data-permissions');
            const isChecked = this.checked;

            let groupPermissions = [];
            try {
                groupPermissions = JSON.parse(permissionsJson);
            } catch(e) {
                console.error('Fehler beim Parsen der Gruppen-Permissions:', e);
                return;
            }

            // Wenn Gruppe aktiviert wird: alle Berechtigungen aktivieren
            if (isChecked) {
                groupPermissions.forEach(permKey => {
                    const permCb = getCheckbox(permKey);
                    if (permCb && !permCb.disabled) {
                        permCb.checked = true;
                        checkDependencies(permKey, true);
                    }
                });
            } else {
                // Wenn Gruppe deaktiviert wird: nur Permissions entfernen, die nicht von anderen Gruppen kommen
                const allActiveGroupPermissions = new Set();

                // Sammle alle Permissions von ANDEREN aktiven Gruppen
                document.querySelectorAll('.group-checkbox:checked').forEach(otherGroupCb => {
                    if (otherGroupCb !== this) {
                        try {
                            const otherPerms = JSON.parse(otherGroupCb.getAttribute('data-permissions'));
                            otherPerms.forEach(p => allActiveGroupPermissions.add(p));
                        } catch(e) {}
                    }
                });

                // Entferne nur Permissions, die NICHT von anderen Gruppen abgedeckt sind
                groupPermissions.forEach(permKey => {
                    if (!allActiveGroupPermissions.has(permKey)) {
                        const permCb = getCheckbox(permKey);
                        if (permCb && !permCb.disabled) {
                            permCb.checked = false;
                            checkDependencies(permKey, false);
                        }
                    }
                });
            }
        });
    });
});

function openPermissionModal(userId, userName, currentPermissions, currentGroupIds) {
    try {
        // User ID setzen
        const userIdField = document.getElementById('modal_user_id');
        if (userIdField) {
            userIdField.value = userId;
        }

        // Benutzername setzen
        const userNameField = document.getElementById('modalUserName');
        if (userNameField) {
            userNameField.textContent = userName;
        }

        // Gruppen-Checkboxen zurücksetzen und dann die aktuellen Gruppen setzen
        const groupCheckboxes = document.querySelectorAll('input[name="group_ids[]"]');
        groupCheckboxes.forEach(function(checkbox) {
            checkbox.checked = currentGroupIds.includes(parseInt(checkbox.value));
        });

        // Alle Permission-Checkboxen zurücksetzen und dann die aktuellen Berechtigungen setzen
        const checkboxes = document.querySelectorAll('input[name="permissions[]"]');
        checkboxes.forEach(function(checkbox) {
            checkbox.checked = currentPermissions.includes(checkbox.value);
        });

        // Modal anzeigen
        const modal = document.getElementById('permissionModal');
        if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
    } catch(err) {
        console.error('Fehler beim Öffnen des Modals:', err);
        alert('Fehler beim Öffnen des Berechtigungsfensters. Bitte versuche es erneut.');
    }
}

function closePermissionModal() {
    const modal = document.getElementById('permissionModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

// ESC-Taste zum Schließen
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closePermissionModal();
    }
});

// Klick außerhalb des Modals zum Schließen
document.getElementById('permissionModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closePermissionModal();
    }
});
<?php if (isset($reopenUserId) && $reopenUserId): ?>
<?php
try {
    $stmt = $db->prepare("SELECT firstname, lastname FROM users WHERE id = ?");
    $stmt->execute([$reopenUserId]);
    $reopenUser = $stmt->fetch();
    
    if ($reopenUser && isset($reopenUser['firstname']) && isset($reopenUser['lastname'])) {
        $reopenPermissions = getUserPermissions($reopenUserId);
        $reopenUserName = $reopenUser['firstname'] . ' ' . $reopenUser['lastname'];
        ?>

// Auto-reopen modal after save
setTimeout(function() {
    openPermissionModal(<?php echo json_encode(intval($reopenUserId)); ?>, <?php echo json_encode($reopenUserName); ?>, <?php echo json_encode($reopenPermissions); ?>);
}, 100);
<?php 
    }
} catch (Exception $e) {
    // Silent error - don't break the page
}
?>
<?php endif; ?>
</script>

<!-- Create Group Modal (Issue #26) -->
<div id="createGroupModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="bg-gradient-to-r from-indigo-600 to-blue-600 text-white px-6 py-4 rounded-t-xl">
            <h3 class="text-xl font-bold">Neue Berechtigungsgruppe</h3>
            <p class="text-sm text-indigo-100 mt-1">Erstelle eine wiederverwendbare Berechtigungsgruppe</p>
        </div>
        
        <form method="POST" class="p-6 space-y-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Gruppenname</label>
                <input type="text" name="group_name" required
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                       placeholder="z.B. Orga-Team">
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Beschreibung</label>
                <input type="text" name="group_description"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                       placeholder="Kurze Beschreibung der Gruppe">
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Berechtigungen</label>
                <div class="space-y-3">
                    <?php foreach ($availablePermissions as $groupName => $groupPerms): ?>
                    <div class="border border-gray-200 rounded-lg overflow-hidden">
                        <div class="bg-gray-50 px-3 py-1.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            <?php echo htmlspecialchars($groupName); ?>
                        </div>
                        <div class="divide-y divide-gray-100">
                        <?php foreach ($groupPerms as $key => $description): ?>
                        <label class="flex items-start p-2.5 bg-white hover:bg-gray-50 cursor-pointer border-0">
                            <input type="checkbox" name="group_permissions[]" value="<?php echo $key; ?>"
                                   class="mt-0.5 mr-3 rounded text-indigo-600 w-4 h-4">
                            <div>
                                <div class="font-medium text-sm text-gray-800"><?php echo $key; ?></div>
                                <div class="text-xs text-gray-500"><?php echo $description; ?></div>
                            </div>
                        </label>
                        <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                <button type="button" onclick="document.getElementById('createGroupModal').classList.add('hidden');document.getElementById('createGroupModal').classList.remove('flex')" 
                        class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                    Abbrechen
                </button>
                <button type="submit" name="create_group" value="1" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                    <i class="fas fa-save mr-2"></i>Erstellen
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Close create group modal on ESC or outside click
document.getElementById('createGroupModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        this.classList.add('hidden');
        this.classList.remove('flex');
    }
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const cgModal = document.getElementById('createGroupModal');
        if (cgModal && !cgModal.classList.contains('hidden')) {
            cgModal.classList.add('hidden');
            cgModal.classList.remove('flex');
        }
    }
});

// Live Search für Berechtigungen
function filterPermissionUsers() {
    const searchTerm = document.getElementById('permissionSearchInput').value.toLowerCase();
    const rows = document.querySelectorAll('.permission-user-row');

    rows.forEach(row => {
        const name = row.getAttribute('data-name');
        const username = row.getAttribute('data-username');
        const role = row.getAttribute('data-role');
        const permissions = row.getAttribute('data-permissions');

        const matchesSearch = name.includes(searchTerm) ||
                             username.includes(searchTerm) ||
                             role.includes(searchTerm) ||
                             permissions.includes(searchTerm);

        if (matchesSearch) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}
</script>
