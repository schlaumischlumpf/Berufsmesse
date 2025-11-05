<?php
// Admin Nutzerverwaltung

// Handle User Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_user'])) {
        // Neuen Benutzer erstellen
        $username = sanitize($_POST['username']);
        $email = sanitize($_POST['email']);
        $firstname = sanitize($_POST['firstname']);
        $lastname = sanitize($_POST['lastname']);
        $role = sanitize($_POST['role']);
        $class = sanitize($_POST['class'] ?? '');
        $password = $_POST['password'];
        
        // Prüfen ob Username bereits existiert
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $message = ['type' => 'error', 'text' => 'Benutzername existiert bereits'];
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (username, email, password, firstname, lastname, role, class) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$username, $email, $hashedPassword, $firstname, $lastname, $role, $class])) {
                $message = ['type' => 'success', 'text' => 'Benutzer erfolgreich erstellt'];
            } else {
                $message = ['type' => 'error', 'text' => 'Fehler beim Erstellen des Benutzers'];
            }
        }
    } elseif (isset($_POST['reset_password'])) {
        // Passwort zurücksetzen
        $userId = intval($_POST['user_id']);
        $newPassword = $_POST['new_password'];
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        if ($stmt->execute([$hashedPassword, $userId])) {
            $message = ['type' => 'success', 'text' => 'Passwort erfolgreich zurückgesetzt'];
        } else {
            $message = ['type' => 'error', 'text' => 'Fehler beim Zurücksetzen des Passworts'];
        }
    } elseif (isset($_POST['delete_user'])) {
        // Benutzer löschen
        $userId = intval($_POST['user_id']);
        
        // Zunächst alle Registrierungen löschen
        $stmt = $db->prepare("DELETE FROM registrations WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        // Dann den Benutzer löschen
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt->execute([$userId])) {
            $message = ['type' => 'success', 'text' => 'Benutzer erfolgreich gelöscht'];
        } else {
            $message = ['type' => 'error', 'text' => 'Fehler beim Löschen des Benutzers'];
        }
    }
}

// Alle Benutzer laden
$stmt = $db->query("
    SELECT u.*, COUNT(DISTINCT r.id) as registration_count
    FROM users u
    LEFT JOIN registrations r ON u.id = r.user_id
    GROUP BY u.id
    ORDER BY u.role ASC, u.lastname ASC, u.firstname ASC
");
$users = $stmt->fetchAll();

// Statistiken
$stats = [];
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
$stats['admins'] = $stmt->fetch()['count'];
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'");
$stats['students'] = $stmt->fetch()['count'];
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'teacher'");
$stats['teachers'] = $stmt->fetch()['count'];
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
    <div class="bg-white rounded-xl p-6 border-l-4 border-blue-600">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">
                    <i class="fas fa-users text-blue-600 mr-3"></i>
                    Nutzerverwaltung
                </h2>
                <p class="text-gray-600">Benutzer erstellen, bearbeiten und löschen</p>
            </div>
            <button onclick="openCreateUserModal()" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold flex items-center gap-2">
                <i class="fas fa-user-plus"></i>
                Neuer Benutzer
            </button>
        </div>
    </div>

    <!-- Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-purple-100 text-sm mb-1">Administratoren</p>
                    <p class="text-3xl font-bold"><?php echo $stats['admins']; ?></p>
                </div>
                <i class="fas fa-user-shield text-3xl opacity-80"></i>
            </div>
        </div>

        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-sm mb-1">Schüler</p>
                    <p class="text-3xl font-bold"><?php echo $stats['students']; ?></p>
                </div>
                <i class="fas fa-user-graduate text-3xl opacity-80"></i>
            </div>
        </div>

        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-100 text-sm mb-1">Lehrer</p>
                    <p class="text-3xl font-bold"><?php echo $stats['teachers']; ?></p>
                </div>
                <i class="fas fa-chalkboard-teacher text-3xl opacity-80"></i>
            </div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Benutzer</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Rolle</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Klasse</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Anmeldungen</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Erstellt</th>
                        <th class="px-6 py-4 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Aktionen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($users as $user): ?>
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-6 py-4">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                    <span class="font-bold text-sm text-blue-600">
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
                            <?php elseif ($user['role'] === 'teacher'): ?>
                                <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs font-semibold">
                                    <i class="fas fa-chalkboard-teacher mr-1"></i>Lehrer
                                </span>
                            <?php else: ?>
                                <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-semibold">
                                    <i class="fas fa-user-graduate mr-1"></i>Schüler
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-gray-700">
                            <?php echo htmlspecialchars($user['class'] ?: '-'); ?>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-gray-700 font-semibold"><?php echo $user['registration_count']; ?></span>
                        </td>
                        <td class="px-6 py-4 text-gray-600 text-sm">
                            <?php echo formatDate($user['created_at']); ?>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex justify-end gap-2">
                                <button onclick="openResetPasswordModal(<?php echo intval($user['id']); ?>, '<?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname'], ENT_QUOTES); ?>')"
                                        class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded hover:bg-yellow-200 transition text-sm font-medium">
                                    <i class="fas fa-key mr-1"></i>Passwort
                                </button>
                                <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                <button onclick="confirmDeleteUser(<?php echo intval($user['id']); ?>, '<?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname'], ENT_QUOTES); ?>')"
                                        class="px-3 py-1 bg-red-100 text-red-700 rounded hover:bg-red-200 transition text-sm font-medium">
                                    <i class="fas fa-trash mr-1"></i>Löschen
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create User Modal -->
<div id="createUserModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-6 py-4 rounded-t-xl">
            <h3 class="text-xl font-bold">Neuen Benutzer erstellen</h3>
        </div>
        
        <form method="POST" class="p-6 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Vorname *</label>
                    <input type="text" name="firstname" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Nachname *</label>
                    <input type="text" name="lastname" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Benutzername *</label>
                <input type="text" name="username" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">E-Mail</label>
                <input type="email" name="email" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Passwort *</label>
                <input type="password" name="password" required minlength="6" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                <p class="text-xs text-gray-500 mt-1">Mindestens 6 Zeichen</p>
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Rolle *</label>
                <select name="role" id="roleSelect" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" onchange="toggleClassField()">
                    <option value="student">Schüler</option>
                    <option value="teacher">Lehrer</option>
                    <option value="admin">Administrator</option>
                </select>
            </div>
            
            <div id="classField">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Klasse</label>
                <input type="text" name="class" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                <button type="button" onclick="closeCreateUserModal()" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                    Abbrechen
                </button>
                <button type="submit" name="create_user" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    <i class="fas fa-user-plus mr-2"></i>Erstellen
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="resetPasswordModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4">
        <div class="bg-gradient-to-r from-yellow-600 to-orange-600 text-white px-6 py-4 rounded-t-xl">
            <h3 class="text-xl font-bold">Passwort zurücksetzen</h3>
        </div>
        
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="user_id" id="resetUserId">
            
            <p class="text-gray-700">
                Passwort zurücksetzen für: <strong id="resetUserName"></strong>
            </p>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Neues Passwort *</label>
                <input type="password" name="new_password" required minlength="6" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500">
                <p class="text-xs text-gray-500 mt-1">Mindestens 6 Zeichen</p>
            </div>
            
            <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                <button type="button" onclick="closeResetPasswordModal()" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                    Abbrechen
                </button>
                <button type="submit" name="reset_password" class="px-6 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition">
                    <i class="fas fa-key mr-2"></i>Passwort zurücksetzen
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteUserModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4">
        <div class="bg-gradient-to-r from-red-600 to-pink-600 text-white px-6 py-4 rounded-t-xl">
            <h3 class="text-xl font-bold">Benutzer löschen</h3>
        </div>
        
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="user_id" id="deleteUserId">
            
            <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded">
                <p class="text-red-800">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    Möchten Sie den Benutzer <strong id="deleteUserName"></strong> wirklich löschen?
                </p>
                <p class="text-red-700 text-sm mt-2">
                    Diese Aktion kann nicht rückgängig gemacht werden. Alle Anmeldungen des Benutzers werden ebenfalls gelöscht.
                </p>
            </div>
            
            <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                <button type="button" onclick="closeDeleteUserModal()" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                    Abbrechen
                </button>
                <button type="submit" name="delete_user" class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                    <i class="fas fa-trash mr-2"></i>Löschen
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateUserModal() {
    document.getElementById('createUserModal').classList.remove('hidden');
    document.getElementById('createUserModal').classList.add('flex');
}

function closeCreateUserModal() {
    document.getElementById('createUserModal').classList.add('hidden');
    document.getElementById('createUserModal').classList.remove('flex');
}

function openResetPasswordModal(userId, userName) {
    document.getElementById('resetUserId').value = userId;
    document.getElementById('resetUserName').textContent = userName;
    document.getElementById('resetPasswordModal').classList.remove('hidden');
    document.getElementById('resetPasswordModal').classList.add('flex');
}

function closeResetPasswordModal() {
    document.getElementById('resetPasswordModal').classList.add('hidden');
    document.getElementById('resetPasswordModal').classList.remove('flex');
}

function confirmDeleteUser(userId, userName) {
    document.getElementById('deleteUserId').value = userId;
    document.getElementById('deleteUserName').textContent = userName;
    document.getElementById('deleteUserModal').classList.remove('hidden');
    document.getElementById('deleteUserModal').classList.add('flex');
}

function closeDeleteUserModal() {
    document.getElementById('deleteUserModal').classList.add('hidden');
    document.getElementById('deleteUserModal').classList.remove('flex');
}

function toggleClassField() {
    const roleSelect = document.getElementById('roleSelect');
    const classField = document.getElementById('classField');
    
    if (roleSelect.value === 'student') {
        classField.style.display = 'block';
    } else {
        classField.style.display = 'none';
    }
}

// Close modals on ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeCreateUserModal();
        closeResetPasswordModal();
        closeDeleteUserModal();
    }
});
</script>
