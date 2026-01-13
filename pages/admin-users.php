<?php
// Admin Nutzerverwaltung

// Datenbankverbindung holen
$db = getDB();

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
        
        // SICHERHEIT: Nur echte Admins können Admin-Accounts erstellen
        if ($role === 'admin' && !isAdmin()) {
            $message = ['type' => 'error', 'text' => 'Nur Administratoren können Admin-Accounts erstellen'];
        } else {
            // Prüfen ob Username bereits existiert
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $message = ['type' => 'error', 'text' => 'Benutzername existiert bereits'];
            } else {
                // Validate role to avoid DB truncation/enum issues
                $allowedRoles = ['student', 'teacher', 'admin']; // must match app expectations
                if (!in_array($role, $allowedRoles, true)) {
                    // fallback to a safe default
                    $role = 'student';
                }

                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (username, email, password, firstname, lastname, role, class) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?)");
                try {
                    if ($stmt->execute([$username, $email, $hashedPassword, $firstname, $lastname, $role, $class])) {
                        $message = ['type' => 'success', 'text' => 'Benutzer erfolgreich erstellt'];
                    } else {
                        // fetch error info for logging
                        $err = $stmt->errorInfo();
                        error_log('DB insert user failed: ' . json_encode($err));
                        $message = ['type' => 'error', 'text' => 'Fehler beim Erstellen des Benutzers'];
                    }
                } catch (PDOException $e) {
                    // Log and show a friendly error instead of letting the script crash
                    error_log('PDOException when inserting user: ' . $e->getMessage());
                    $message = ['type' => 'error', 'text' => 'Datenbankfehler beim Erstellen des Benutzers. Bitte überprüfe Eingaben und Schema.'];
                }
            }
        }
    } elseif (isset($_POST['reset_password'])) {
        // Passwort zurücksetzen
        $userId = intval($_POST['user_id']);
        $newPassword = $_POST['new_password'];
        
        // SICHERHEIT: Prüfen ob der Zielbenutzer ein Admin ist
        $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $targetUser = $stmt->fetch();
        
        if ($targetUser && $targetUser['role'] === 'admin' && !isAdmin()) {
            $message = ['type' => 'error', 'text' => 'Nur Administratoren können Admin-Passwörter ändern'];
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($stmt->execute([$hashedPassword, $userId])) {
                $message = ['type' => 'success', 'text' => 'Passwort erfolgreich zurückgesetzt'];
            } else {
                $message = ['type' => 'error', 'text' => 'Fehler beim Zurücksetzen des Passworts'];
            }
        }
    } elseif (isset($_POST['delete_user'])) {
        // Benutzer löschen
        $userId = intval($_POST['user_id']);
        
        // SICHERHEIT: Prüfen ob der Zielbenutzer ein Admin ist
        $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $targetUser = $stmt->fetch();
        
        if ($targetUser && $targetUser['role'] === 'admin' && !isAdmin()) {
            $message = ['type' => 'error', 'text' => 'Nur Administratoren können Admin-Accounts löschen'];
        } else {
            // Zunächst alle Registrierungen löschen
            $stmt = $db->prepare("DELETE FROM registrations WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            // Berechtigungen löschen
            $stmt = $db->prepare("DELETE FROM user_permissions WHERE user_id = ?");
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
$stats['total'] = $stats['admins'] + $stats['students'] + $stats['teachers'];
?>

<style>
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.animate-fadeInUp {
    animation: fadeInUp 0.5s ease-out forwards;
}

.stat-card {
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--gradient);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.stat-card:hover::before {
    opacity: 1;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 40px -12px rgba(0,0,0,0.1);
}

.user-row {
    transition: all 0.3s ease;
}

.user-row:hover {
    background: linear-gradient(90deg, rgba(139, 92, 246, 0.05) 0%, rgba(59, 130, 246, 0.05) 100%);
    transform: translateX(4px);
}

.action-btn {
    transition: all 0.2s ease;
}

.action-btn:hover {
    transform: scale(1.05);
}

.modal-content {
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from { opacity: 0; transform: scale(0.9) translateY(-20px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

.input-modern {
    transition: all 0.2s ease;
}

.input-modern:focus {
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.2);
    border-color: #8b5cf6;
}
</style>

<div class="space-y-8">
    <?php if (isset($message)): ?>
    <div class="animate-fadeInUp">
        <?php if ($message['type'] === 'success'): ?>
            <div class="bg-gradient-to-r from-primary-50 to-emerald-50 border border-primary-200 p-5 rounded-2xl shadow-sm">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-primary-100 rounded-xl flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-check-circle text-primary-500 text-xl"></i>
                    </div>
                    <p class="text-primary-700 font-semibold"><?php echo $message['text']; ?></p>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-gradient-to-r from-red-50 to-rose-50 border border-red-200 p-5 rounded-2xl shadow-sm">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-exclamation-circle text-red-500 text-xl"></i>
                    </div>
                    <p class="text-red-700 font-semibold"><?php echo $message['text']; ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Hero Header -->
    <div class="bg-gradient-to-br from-slate-800 via-slate-900 to-purple-900 rounded-3xl p-8 shadow-2xl relative overflow-hidden animate-fadeInUp">
        <!-- Background Pattern -->
        <div class="absolute inset-0 opacity-10">
            <div class="absolute top-0 left-0 w-full h-full" style="background-image: url('data:image/svg+xml,%3Csvg width=\"60\" height=\"60\" viewBox=\"0 0 60 60\" xmlns=\"http://www.w3.org/2000/svg\"%3E%3Cg fill=\"none\" fill-rule=\"evenodd\"%3E%3Cg fill=\"%23ffffff\" fill-opacity=\"0.4\"%3E%3Cpath d=\"M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\"/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"></div>
        </div>
        
        <div class="relative flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
            <div class="flex items-center gap-5">
                <div class="w-16 h-16 bg-gradient-to-br from-accent-500 to-purple-600 rounded-2xl flex items-center justify-center shadow-xl shadow-accent-500/30">
                    <i class="fas fa-users-cog text-white text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-3xl font-extrabold text-white font-display">Nutzerverwaltung</h2>
                    <p class="text-slate-300 mt-1">Benutzer erstellen, bearbeiten und verwalten</p>
                </div>
            </div>
            <button onclick="openCreateUserModal()" class="group bg-gradient-to-r from-accent-500 to-purple-600 text-white px-6 py-3.5 rounded-xl font-bold hover:shadow-xl hover:shadow-accent-500/30 transition-all duration-300 flex items-center gap-3 transform hover:scale-105">
                <i class="fas fa-user-plus text-lg group-hover:rotate-12 transition-transform"></i>
                <span>Neuer Benutzer</span>
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 animate-fadeInUp" style="animation-delay: 100ms;">
        <div class="stat-card bg-white rounded-2xl p-5 border border-gray-100 shadow-sm transition-all duration-300" style="--gradient: linear-gradient(90deg, #8b5cf6, #a855f7);">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium mb-1">Gesamt</p>
                    <p class="text-3xl font-extrabold text-gray-800"><?php echo $stats['total']; ?></p>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-slate-100 to-gray-200 flex items-center justify-center">
                    <i class="fas fa-users text-xl text-gray-600"></i>
                </div>
            </div>
        </div>

        <div class="stat-card bg-white rounded-2xl p-5 border border-gray-100 shadow-sm transition-all duration-300" style="--gradient: linear-gradient(90deg, #ef4444, #f97316);">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium mb-1">Administratoren</p>
                    <p class="text-3xl font-extrabold text-gray-800"><?php echo $stats['admins']; ?></p>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-red-100 to-rose-200 flex items-center justify-center">
                    <i class="fas fa-user-shield text-xl text-red-600"></i>
                </div>
            </div>
        </div>

        <div class="stat-card bg-white rounded-2xl p-5 border border-gray-100 shadow-sm transition-all duration-300" style="--gradient: linear-gradient(90deg, #3b82f6, #06b6d4);">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium mb-1">Schüler</p>
                    <p class="text-3xl font-extrabold text-gray-800"><?php echo $stats['students']; ?></p>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-100 to-cyan-200 flex items-center justify-center">
                    <i class="fas fa-user-graduate text-xl text-blue-600"></i>
                </div>
            </div>
        </div>

        <div class="stat-card bg-white rounded-2xl p-5 border border-gray-100 shadow-sm transition-all duration-300" style="--gradient: linear-gradient(90deg, #f59e0b, #f97316);">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium mb-1">Lehrer</p>
                    <p class="text-3xl font-extrabold text-gray-800"><?php echo $stats['teachers']; ?></p>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-amber-100 to-orange-200 flex items-center justify-center">
                    <i class="fas fa-chalkboard-teacher text-xl text-amber-600"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="bg-white rounded-3xl border border-gray-100 shadow-lg overflow-hidden animate-fadeInUp" style="animation-delay: 200ms;">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gradient-to-r from-gray-50 to-slate-100 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-5 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Benutzer</th>
                        <th class="px-6 py-5 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Rolle</th>
                        <th class="px-6 py-5 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Klasse</th>
                        <th class="px-6 py-5 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Anmeldungen</th>
                        <th class="px-6 py-5 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Erstellt</th>
                        <th class="px-6 py-5 text-right text-xs font-bold text-gray-600 uppercase tracking-wider">Aktionen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($users as $index => $user): 
                        $roleColors = [
                            'admin' => ['from-red-500 to-rose-600', 'bg-red-50 text-red-700 border-red-200'],
                            'teacher' => ['from-amber-500 to-orange-600', 'bg-amber-50 text-amber-700 border-amber-200'],
                            'student' => ['from-blue-500 to-cyan-600', 'bg-blue-50 text-blue-700 border-blue-200']
                        ];
                        $roleColor = $roleColors[$user['role']] ?? ['from-gray-500 to-gray-600', 'bg-gray-50 text-gray-700 border-gray-200'];
                    ?>
                    <tr class="user-row">
                        <td class="px-6 py-5">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-gradient-to-br <?php echo $roleColor[0]; ?> rounded-xl flex items-center justify-center shadow-md">
                                    <span class="font-bold text-sm text-white">
                                        <?php echo strtoupper(substr($user['firstname'], 0, 1) . substr($user['lastname'], 0, 1)); ?>
                                    </span>
                                </div>
                                <div>
                                    <p class="font-bold text-gray-800"><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></p>
                                    <p class="text-sm text-gray-500"><i class="fas fa-at mr-1 text-gray-400"></i><?php echo htmlspecialchars($user['username']); ?></p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-5">
                            <?php if ($user['role'] === 'admin'): ?>
                                <span class="inline-flex items-center px-3 py-1.5 rounded-xl text-xs font-bold bg-red-50 text-red-700 border border-red-200">
                                    <i class="fas fa-crown mr-1.5"></i>Admin
                                </span>
                            <?php elseif ($user['role'] === 'teacher'): ?>
                                <span class="inline-flex items-center px-3 py-1.5 rounded-xl text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                    <i class="fas fa-chalkboard-teacher mr-1.5"></i>Lehrer
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-3 py-1.5 rounded-xl text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                    <i class="fas fa-user-graduate mr-1.5"></i>Schüler
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-5">
                            <?php if (!empty($user['class'])): ?>
                                <span class="inline-flex items-center px-3 py-1.5 rounded-xl text-xs font-bold bg-gray-100 text-gray-700">
                                    <i class="fas fa-school mr-1.5 text-gray-500"></i><?php echo htmlspecialchars($user['class']); ?>
                                </span>
                            <?php else: ?>
                                <span class="text-gray-400">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-5">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-accent-50 text-accent-700 font-bold text-sm">
                                    <?php echo $user['registration_count']; ?>
                                </span>
                            </div>
                        </td>
                        <td class="px-6 py-5 text-gray-500 text-sm">
                            <i class="far fa-calendar-alt mr-1 text-gray-400"></i>
                            <?php echo formatDate($user['created_at']); ?>
                        </td>
                        <td class="px-6 py-5 text-right">
                            <div class="flex justify-end gap-2">
                                <?php 
                                // Nur Admins können Admin-Accounts bearbeiten
                                $canEdit = ($user['role'] !== 'admin') || isAdmin();
                                ?>
                                <?php if ($canEdit): ?>
                                    <button onclick="openResetPasswordModal(<?php echo intval($user['id']); ?>, '<?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname'], ENT_QUOTES); ?>', '<?php echo $user['role']; ?>')"
                                            class="action-btn px-4 py-2 bg-gradient-to-r from-amber-50 to-orange-50 text-amber-700 border border-amber-200 rounded-xl hover:shadow-md text-sm font-bold">
                                        <i class="fas fa-key mr-1.5"></i>Passwort
                                    </button>
                                    <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                    <button onclick="confirmDeleteUser(<?php echo intval($user['id']); ?>, '<?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname'], ENT_QUOTES); ?>', '<?php echo $user['role']; ?>')"
                                            class="action-btn px-4 py-2 bg-gradient-to-r from-red-50 to-rose-50 text-red-700 border border-red-200 rounded-xl hover:shadow-md text-sm font-bold">
                                        <i class="fas fa-trash mr-1.5"></i>Löschen
                                    </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-gray-400 text-sm italic flex items-center gap-2">
                                        <i class="fas fa-lock"></i>Nur für Admins
                                    </span>
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
<div id="createUserModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
    <div class="modal-content bg-white rounded-3xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="bg-gradient-to-r from-accent-500 via-purple-600 to-indigo-600 text-white px-8 py-6 rounded-t-3xl relative overflow-hidden">
            <div class="absolute inset-0 bg-white/10 opacity-30" style="background-image: url('data:image/svg+xml,%3Csvg width=\"40\" height=\"40\" viewBox=\"0 0 40 40\" xmlns=\"http://www.w3.org/2000/svg\"%3E%3Cg fill=\"%23ffffff\" fill-opacity=\"0.3\" fill-rule=\"evenodd\"%3E%3Cpath d=\"M0 40L40 0H20L0 20M40 40V20L20 40\"/%3E%3C/g%3E%3C/svg%3E');"></div>
            <div class="relative flex items-center gap-4">
                <div class="w-14 h-14 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center">
                    <i class="fas fa-user-plus text-2xl"></i>
                </div>
                <div>
                    <h3 class="text-2xl font-extrabold font-display">Neuen Benutzer erstellen</h3>
                    <p class="text-white/80 text-sm">Füllen Sie alle erforderlichen Felder aus</p>
                </div>
            </div>
        </div>
        
        <form method="POST" class="p-8 space-y-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-user text-accent-500 mr-2"></i>Vorname *
                    </label>
                    <input type="text" name="firstname" required placeholder="Max" class="input-modern w-full px-5 py-3.5 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white transition-all">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Nachname *</label>
                    <input type="text" name="lastname" required placeholder="Mustermann" class="input-modern w-full px-5 py-3.5 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white transition-all">
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">
                    <i class="fas fa-at text-accent-500 mr-2"></i>Benutzername *
                </label>
                <input type="text" name="username" required placeholder="max.mustermann" class="input-modern w-full px-5 py-3.5 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white transition-all">
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">
                    <i class="fas fa-envelope text-accent-500 mr-2"></i>E-Mail
                </label>
                <input type="email" name="email" placeholder="max@beispiel.de" class="input-modern w-full px-5 py-3.5 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white transition-all">
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">
                    <i class="fas fa-lock text-accent-500 mr-2"></i>Passwort *
                </label>
                <input type="password" name="password" required minlength="6" placeholder="Mindestens 6 Zeichen" class="input-modern w-full px-5 py-3.5 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white transition-all">
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">
                    <i class="fas fa-user-tag text-accent-500 mr-2"></i>Rolle *
                </label>
                <select name="role" id="roleSelect" required class="input-modern w-full px-5 py-3.5 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white transition-all font-medium" onchange="toggleClassField()">
                    <option value="student">👨‍🎓 Schüler</option>
                    <option value="teacher">👨‍🏫 Lehrer</option>
                    <?php if (isAdmin()): ?>
                    <option value="admin">👑 Administrator</option>
                    <?php endif; ?>
                </select>
                <?php if (!isAdmin()): ?>
                <p class="text-xs text-gray-500 mt-2 flex items-center gap-1">
                    <i class="fas fa-info-circle"></i>Nur Administratoren können Admin-Accounts erstellen
                </p>
                <?php endif; ?>
            </div>
            
            <div id="classField">
                <label class="block text-sm font-bold text-gray-700 mb-2">
                    <i class="fas fa-school text-accent-500 mr-2"></i>Klasse
                </label>
                <input type="text" name="class" placeholder="z.B. 10A, 11B" class="input-modern w-full px-5 py-3.5 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white transition-all">
            </div>
            
            <div class="flex justify-end gap-3 pt-6 border-t border-gray-100">
                <button type="button" onclick="closeCreateUserModal()" class="px-6 py-3 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition font-bold">
                    Abbrechen
                </button>
                <button type="submit" name="create_user" class="px-6 py-3 bg-gradient-to-r from-accent-500 to-purple-600 text-white rounded-xl hover:shadow-lg hover:shadow-accent-500/30 transition font-bold">
                    <i class="fas fa-user-plus mr-2"></i>Erstellen
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="resetPasswordModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
    <div class="modal-content bg-white rounded-3xl shadow-2xl max-w-md w-full">
        <div class="bg-gradient-to-r from-amber-500 via-orange-500 to-red-500 text-white px-8 py-6 rounded-t-3xl relative overflow-hidden">
            <div class="absolute inset-0 bg-white/10 opacity-30" style="background-image: url('data:image/svg+xml,%3Csvg width=\"40\" height=\"40\" viewBox=\"0 0 40 40\" xmlns=\"http://www.w3.org/2000/svg\"%3E%3Cg fill=\"%23ffffff\" fill-opacity=\"0.3\" fill-rule=\"evenodd\"%3E%3Cpath d=\"M0 40L40 0H20L0 20M40 40V20L20 40\"/%3E%3C/g%3E%3C/svg%3E');"></div>
            <div class="relative flex items-center gap-4">
                <div class="w-14 h-14 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center">
                    <i class="fas fa-key text-2xl"></i>
                </div>
                <h3 class="text-2xl font-extrabold font-display">Passwort zurücksetzen</h3>
            </div>
        </div>
        
        <form method="POST" class="p-8 space-y-5">
            <input type="hidden" name="user_id" id="resetUserId">
            
            <div class="bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-200 rounded-2xl p-5">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-amber-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-user text-amber-600 text-lg"></i>
                    </div>
                    <div>
                        <p class="text-sm text-amber-600 font-medium">Passwort zurücksetzen für:</p>
                        <p class="font-bold text-amber-800" id="resetUserName"></p>
                    </div>
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">
                    <i class="fas fa-lock text-amber-500 mr-2"></i>Neues Passwort *
                </label>
                <input type="password" name="new_password" required minlength="6" placeholder="Mindestens 6 Zeichen" class="input-modern w-full px-5 py-3.5 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white transition-all">
            </div>
            
            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                <button type="button" onclick="closeResetPasswordModal()" class="px-6 py-3 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition font-bold">
                    Abbrechen
                </button>
                <button type="submit" name="reset_password" class="px-6 py-3 bg-gradient-to-r from-amber-500 to-orange-500 text-white rounded-xl hover:shadow-lg hover:shadow-amber-500/30 transition font-bold">
                    <i class="fas fa-key mr-2"></i>Zurücksetzen
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteUserModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
    <div class="modal-content bg-white rounded-3xl shadow-2xl max-w-md w-full">
        <div class="bg-gradient-to-r from-red-500 via-rose-500 to-pink-600 text-white px-8 py-6 rounded-t-3xl relative overflow-hidden">
            <div class="absolute inset-0 bg-white/10 opacity-30" style="background-image: url('data:image/svg+xml,%3Csvg width=\"40\" height=\"40\" viewBox=\"0 0 40 40\" xmlns=\"http://www.w3.org/2000/svg\"%3E%3Cg fill=\"%23ffffff\" fill-opacity=\"0.3\" fill-rule=\"evenodd\"%3E%3Cpath d=\"M0 40L40 0H20L0 20M40 40V20L20 40\"/%3E%3C/g%3E%3C/svg%3E');"></div>
            <div class="relative flex items-center gap-4">
                <div class="w-14 h-14 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-2xl"></i>
                </div>
                <h3 class="text-2xl font-extrabold font-display">Benutzer löschen</h3>
            </div>
        </div>
        
        <form method="POST" class="p-8 space-y-5">
            <input type="hidden" name="user_id" id="deleteUserId">
            
            <div class="bg-gradient-to-r from-red-50 to-rose-50 border border-red-200 rounded-2xl p-5">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-trash text-red-600 text-lg"></i>
                    </div>
                    <div>
                        <p class="text-red-800 font-semibold">
                            Möchten Sie <strong id="deleteUserName"></strong> wirklich löschen?
                        </p>
                        <p class="text-red-600 text-sm mt-2">
                            <i class="fas fa-exclamation-circle mr-1"></i>
                            Diese Aktion kann nicht rückgängig gemacht werden. Alle Anmeldungen werden ebenfalls gelöscht.
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                <button type="button" onclick="closeDeleteUserModal()" class="px-6 py-3 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition font-bold">
                    Abbrechen
                </button>
                <button type="submit" name="delete_user" class="px-6 py-3 bg-gradient-to-r from-red-500 to-rose-600 text-white rounded-xl hover:shadow-lg hover:shadow-red-500/30 transition font-bold">
                    <i class="fas fa-trash mr-2"></i>Endgültig löschen
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

function openResetPasswordModal(userId, userName, userRole) {
    // Warnung für Admin-Accounts anzeigen
    if (userRole === 'admin') {
        if (!confirm('WARNUNG: Sie ändern das Passwort eines Administrator-Accounts. Möchten Sie fortfahren?')) {
            return;
        }
    }
    
    document.getElementById('resetUserId').value = userId;
    document.getElementById('resetUserName').textContent = userName;
    document.getElementById('resetPasswordModal').classList.remove('hidden');
    document.getElementById('resetPasswordModal').classList.add('flex');
}

function closeResetPasswordModal() {
    document.getElementById('resetPasswordModal').classList.add('hidden');
    document.getElementById('resetPasswordModal').classList.remove('flex');
}

function confirmDeleteUser(userId, userName, userRole) {
    // Extra Warnung für Admin-Accounts
    if (userRole === 'admin') {
        if (!confirm('WARNUNG: Sie löschen einen Administrator-Account! Dies ist eine sensible Aktion. Sind Sie absolut sicher?')) {
            return;
        }
    }
    
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
