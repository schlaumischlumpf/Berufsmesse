<?php
// Admin Berechtigungsverwaltung (Issue #10)

// Datenbankverbindung holen
$db = getDB();

// Handle Permission Changes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_permissions'])) {
    $userId = intval($_POST['user_id']);
    $permissions = $_POST['permissions'] ?? [];
    
    // Alle aktuellen Berechtigungen löschen
    $db->prepare("DELETE FROM user_permissions WHERE user_id = ?")->execute([$userId]);
    
    // Neue Berechtigungen setzen
    foreach ($permissions as $permission) {
        grantPermission($userId, $permission);
    }
    
    $message = ['type' => 'success', 'text' => 'Berechtigungen erfolgreich aktualisiert'];
    $reopenUserId = $userId; // Flag to reopen modal
}

// Alle Benutzer laden (außer Schüler)
$stmt = $db->query("
    SELECT u.*, COUNT(DISTINCT p.permission) as permission_count
    FROM users u
    LEFT JOIN user_permissions p ON u.id = p.user_id
    WHERE u.role IN ('admin', 'teacher')
    GROUP BY u.id
    ORDER BY u.role ASC, u.lastname ASC, u.firstname ASC
");
$users = $stmt->fetchAll();

// Verfügbare Berechtigungen
$availablePermissions = getAvailablePermissions();

// Statistiken
$stats = [];
$stmt = $db->query("SELECT COUNT(DISTINCT user_id) as count FROM user_permissions");
$stats['users_with_permissions'] = $stmt->fetch()['count'];
$stmt = $db->query("SELECT COUNT(*) as count FROM user_permissions");
$stats['total_permissions'] = $stmt->fetch()['count'];
?>

<div class="space-y-6">
    <?php if (isset($message)): ?>
    <div class="animate-pulse">
        <?php if ($message['type'] === 'success'): ?>
            <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 mr-3"></i>
                    <p class="text-green-700"><?php echo $message['text']; ?></p>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                    <p class="text-red-700"><?php echo $message['text']; ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="bg-white rounded-xl p-6 border-l-4 border-purple-600">
        <div>
            <h2 class="text-2xl font-bold text-gray-800 mb-2">
                <i class="fas fa-shield-alt text-purple-600 mr-3"></i>
                Berechtigungsverwaltung
            </h2>
            <p class="text-gray-600">Vergeben Sie granulare Berechtigungen an Benutzer</p>
        </div>
    </div>

    <!-- Info Box -->
    <div class="bg-blue-50 border-l-4 border-blue-500 p-6 rounded-lg">
        <div class="flex items-start">
            <i class="fas fa-info-circle text-blue-500 text-xl mr-3 mt-1"></i>
            <div>
                <h3 class="font-bold text-blue-900 mb-2">Berechtigungskonzept</h3>
                <ul class="text-sm text-blue-800 space-y-1">
                    <li><i class="fas fa-check mr-2"></i><strong>Administratoren</strong> haben automatisch alle Berechtigungen</li>
                    <li><i class="fas fa-check mr-2"></i><strong>Lehrer</strong> und andere Benutzer benötigen explizite Berechtigungen</li>
                    <li><i class="fas fa-check mr-2"></i>Berechtigungen ermöglichen Zugriff auf spezifische Funktionen</li>
                    <li><i class="fas fa-check mr-2"></i>Mehrere Berechtigungen können kombiniert werden</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-purple-100 text-sm mb-1">Benutzer mit Berechtigungen</p>
                    <p class="text-3xl font-bold"><?php echo $stats['users_with_permissions']; ?></p>
                </div>
                <i class="fas fa-users text-3xl opacity-80"></i>
            </div>
        </div>

        <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-xl p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-indigo-100 text-sm mb-1">Gesamt Berechtigungen</p>
                    <p class="text-3xl font-bold"><?php echo $stats['total_permissions']; ?></p>
                </div>
                <i class="fas fa-key text-3xl opacity-80"></i>
            </div>
        </div>

        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-sm mb-1">Verfügbare Berechtigungen</p>
                    <p class="text-3xl font-bold"><?php echo count($availablePermissions); ?></p>
                </div>
                <i class="fas fa-list text-3xl opacity-80"></i>
            </div>
        </div>
    </div>

    <!-- Users List -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
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
                    ?>
                    <tr class="hover:bg-gray-50 transition">
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
                                <button type="button" 
                                        class="permission-btn px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition text-sm font-semibold shadow-sm hover:shadow-md"
                                        data-user-id="<?php echo htmlspecialchars($user['id']); ?>"
                                        data-user-name="<?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?>"
                                        data-permissions='<?php echo htmlspecialchars(json_encode($userPermissions), ENT_QUOTES, 'UTF-8'); ?>'>
                                    <i class="fas fa-shield-alt mr-2"></i>Berechtigungen verwalten
                                </button>
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

    <!-- Permission Definitions -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-6 py-4">
            <h3 class="text-xl font-bold flex items-center">
                <i class="fas fa-list mr-3"></i>
                Berechtigungsdefinitionen
            </h3>
        </div>

        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($availablePermissions as $key => $description): ?>
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                    <div class="flex items-start">
                        <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3 flex-shrink-0">
                            <i class="fas fa-key text-purple-600"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-800 mb-1"><?php echo $key; ?></h4>
                            <p class="text-sm text-gray-600"><?php echo $description; ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
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
        
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="user_id" id="modal_user_id">
            
            <div class="space-y-3">
                <label class="block text-sm font-semibold text-gray-700 mb-3">
                    Wählen Sie die Berechtigungen aus:
                </label>
                
                <?php foreach ($availablePermissions as $key => $description): ?>
                <label class="flex items-start p-4 bg-gray-50 rounded-lg hover:bg-gray-100 cursor-pointer transition border border-gray-200">
                    <input type="checkbox" 
                           name="permissions[]" 
                           value="<?php echo $key; ?>"
                           class="mt-1 mr-3 rounded text-purple-600 w-5 h-5">
                    <div class="flex-1">
                        <div class="font-semibold text-gray-800"><?php echo $key; ?></div>
                        <div class="text-sm text-gray-600 mt-1"><?php echo $description; ?></div>
                    </div>
                </label>
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
// Event Listener für alle Berechtigungsknöpfe
document.addEventListener('DOMContentLoaded', function() {
    // Alle Berechtigungsknöpfe finden
    const permissionButtons = document.querySelectorAll('.permission-btn');
    
    permissionButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            const userName = this.getAttribute('data-user-name');
            const permissionsJson = this.getAttribute('data-permissions');
            
            let currentPermissions = [];
            try {
                currentPermissions = JSON.parse(permissionsJson);
            } catch(e) {
                console.error('Fehler beim Parsen der Berechtigungen:', e);
                currentPermissions = [];
            }
            
            openPermissionModal(userId, userName, currentPermissions);
        });
    });
});

function openPermissionModal(userId, userName, currentPermissions) {
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
        
        // Alle Checkboxen zurücksetzen und dann die aktuellen Berechtigungen setzen
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
        alert('Fehler beim Öffnen des Berechtigungsfensters. Bitte versuchen Sie es erneut.');
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
