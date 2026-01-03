<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// Diese Seite sollte nur temporär für Tests verwendet werden
// Für Produktion: Diese Seite löschen oder mit Passwort schützen

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $firstname = sanitize($_POST['firstname'] ?? '');
    $lastname = sanitize($_POST['lastname'] ?? '');
    $class = sanitize($_POST['class'] ?? '');
    $role = $_POST['role'] ?? 'student';
    
    // Validierung
    if (empty($username) || empty($password) || empty($firstname) || empty($lastname)) {
        $message = 'Bitte alle Felder ausfüllen';
        $messageType = 'error';
    } elseif (strlen($password) < 6) {
        $message = 'Passwort muss mindestens 6 Zeichen lang sein';
        $messageType = 'error';
    } else {
        // Prüfen ob Benutzername bereits existiert
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        
        if ($stmt->fetch()) {
            $message = 'Benutzername bereits vergeben';
            $messageType = 'error';
        } else {
            // Benutzer anlegen
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (username, password, firstname, lastname, class, role) VALUES (?, ?, ?, ?, ?, ?)");
            
            if ($stmt->execute([$username, $hashedPassword, $firstname, $lastname, $class, $role])) {
                $message = "Benutzer erfolgreich angelegt! Sie können sich jetzt mit '$username' anmelden.";
                $messageType = 'success';
                
                // Formular zurücksetzen
                $_POST = [];
            } else {
                $message = 'Fehler beim Anlegen des Benutzers';
                $messageType = 'error';
            }
        }
    }
}

// Alle Benutzer anzeigen (für Übersicht)
$db = getDB();
$stmt = $db->query("SELECT id, username, firstname, lastname, class, role, created_at FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Benutzer Registrierung - Berufsmesse</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="min-h-screen py-8 px-4">
    <div class="max-w-6xl mx-auto">
        <!-- Warning Banner -->
        <div class="bg-yellow-500 text-white px-6 py-4 rounded-lg shadow-lg mb-6">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle text-2xl mr-4"></i>
                <div>
                    <h2 class="font-bold text-lg">⚠️ Temporäre Test-Seite</h2>
                    <p class="text-sm">Diese Seite ist nur für Tests gedacht. Löschen Sie diese Datei (register.php) vor dem Produktivbetrieb!</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Registration Form -->
            <div class="bg-white rounded-2xl shadow-2xl p-8">
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-purple-600 rounded-full shadow-lg mb-4">
                        <i class="fas fa-user-plus text-white text-2xl"></i>
                    </div>
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">Neuen Benutzer anlegen</h1>
                    <p class="text-gray-600">Erstellen Sie Test-Accounts für Schüler oder Admins</p>
                </div>

                <?php if ($message): ?>
                <div class="mb-6 animate-pulse">
                    <?php if ($messageType === 'success'): ?>
                    <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-lg">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 mr-3"></i>
                            <p class="text-green-700"><?php echo $message; ?></p>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-lg">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                            <p class="text-red-700"><?php echo $message; ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="" class="space-y-4">
                    <!-- Role Selection -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-user-tag text-purple-600 mr-2"></i>Rolle *
                        </label>
                        <select name="role" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            <option value="student" <?php echo ($_POST['role'] ?? '') === 'student' ? 'selected' : ''; ?>>Schüler</option>
                            <option value="admin" <?php echo ($_POST['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                        </select>
                    </div>

                    <!-- Username -->
                    <div>
                        <label for="username" class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-user text-purple-600 mr-2"></i>Benutzername *
                        </label>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            required
                            value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                            placeholder="z.B. max.mueller"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                        >
                        <p class="text-xs text-gray-500 mt-1">Nur Kleinbuchstaben, Zahlen, Punkt und Unterstrich</p>
                    </div>

                    <!-- Password -->
                    <div>
                        <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-lock text-purple-600 mr-2"></i>Passwort *
                        </label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            required
                            minlength="6"
                            placeholder="Mindestens 6 Zeichen"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                        >
                    </div>

                    <!-- First Name -->
                    <div>
                        <label for="firstname" class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-id-card text-purple-600 mr-2"></i>Vorname *
                        </label>
                        <input 
                            type="text" 
                            id="firstname" 
                            name="firstname" 
                            required
                            value="<?php echo htmlspecialchars($_POST['firstname'] ?? ''); ?>"
                            placeholder="Vorname"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                        >
                    </div>

                    <!-- Last Name -->
                    <div>
                        <label for="lastname" class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-id-card text-purple-600 mr-2"></i>Nachname *
                        </label>
                        <input 
                            type="text" 
                            id="lastname" 
                            name="lastname" 
                            required
                            value="<?php echo htmlspecialchars($_POST['lastname'] ?? ''); ?>"
                            placeholder="Nachname"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                        >
                    </div>

                    <!-- Class (Klasse) - only for students -->
                    <div id="classField">
                        <label for="class" class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-school text-purple-600 mr-2"></i>Klasse
                        </label>
                        <input 
                            type="text" 
                            id="class" 
                            name="class" 
                            value="<?php echo htmlspecialchars($_POST['class'] ?? ''); ?>"
                            placeholder="z.B. 10A, 11B"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                        >
                        <p class="text-xs text-gray-500 mt-1">Optional - nur für Schüler relevant</p>
                    </div>

                    <!-- Submit Button -->
                    <button 
                        type="submit"
                        class="w-full bg-gradient-to-r from-purple-600 to-indigo-600 text-white font-semibold py-3 px-6 rounded-lg hover:from-purple-700 hover:to-indigo-700 transform hover:scale-[1.02] transition duration-200 shadow-lg"
                    >
                        <i class="fas fa-user-plus mr-2"></i>Benutzer anlegen
                    </button>
                </form>

                <!-- Quick Actions -->
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <p class="text-sm text-gray-600 mb-3 font-semibold">Schnellzugriff:</p>
                    <div class="flex gap-2">
                        <a href="login.php" class="flex-1 text-center bg-blue-100 text-blue-700 px-4 py-2 rounded-lg hover:bg-blue-200 transition text-sm font-semibold">
                            <i class="fas fa-sign-in-alt mr-1"></i>Zum Login
                        </a>
                        <a href="index.php" class="flex-1 text-center bg-green-100 text-green-700 px-4 py-2 rounded-lg hover:bg-green-200 transition text-sm font-semibold">
                            <i class="fas fa-home mr-1"></i>Zur App
                        </a>
                    </div>
                </div>
            </div>

            <!-- Users List -->
            <div class="bg-white rounded-2xl shadow-2xl p-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
                    <i class="fas fa-users text-purple-600 mr-3"></i>
                    Alle Benutzer (<?php echo count($users); ?>)
                </h2>

                <div class="space-y-3 max-h-[600px] overflow-y-auto">
                    <?php foreach ($users as $user): ?>
                    <div class="border border-gray-200 rounded-lg p-4 hover:border-purple-300 hover:shadow-md transition">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3 flex-1 min-w-0">
                                <div class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center <?php echo $user['role'] === 'admin' ? 'bg-red-100' : 'bg-blue-100'; ?>">
                                    <span class="font-bold text-sm <?php echo $user['role'] === 'admin' ? 'text-red-600' : 'text-blue-600'; ?>">
                                        <?php echo strtoupper(substr($user['firstname'], 0, 1) . substr($user['lastname'], 0, 1)); ?>
                                    </span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-gray-800 truncate">
                                        <?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?>
                                    </p>
                                    <p class="text-sm text-gray-600 truncate">
                                        <i class="fas fa-user mr-1"></i><?php echo htmlspecialchars($user['username']); ?>
                                    </p>
                                    <?php if (!empty($user['class'])): ?>
                                    <p class="text-xs text-gray-500">
                                        <i class="fas fa-school mr-1"></i>Klasse: <?php echo htmlspecialchars($user['class']); ?>
                                    </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="flex-shrink-0 ml-3">
                                <?php if ($user['role'] === 'admin'): ?>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800">
                                        <i class="fas fa-crown mr-1"></i>Admin
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
                                        <i class="fas fa-user-graduate mr-1"></i>Schüler
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="mt-2 text-xs text-gray-500">
                            <i class="fas fa-clock mr-1"></i>Erstellt: <?php echo formatDateTime($user['created_at']); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Statistics -->
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-blue-50 rounded-lg p-4 text-center">
                            <div class="text-2xl font-bold text-blue-600">
                                <?php echo count(array_filter($users, fn($u) => $u['role'] === 'student')); ?>
                            </div>
                            <div class="text-sm text-blue-800 mt-1">Schüler</div>
                        </div>
                        <div class="bg-red-50 rounded-lg p-4 text-center">
                            <div class="text-2xl font-bold text-red-600">
                                <?php echo count(array_filter($users, fn($u) => $u['role'] === 'admin')); ?>
                            </div>
                            <div class="text-sm text-red-800 mt-1">Admins</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Info Box -->
        <div class="mt-6 bg-white rounded-2xl shadow-2xl p-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-info-circle text-blue-600 mr-3"></i>
                Hinweise zur Nutzung
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div class="bg-blue-50 rounded-lg p-4">
                    <h4 class="font-semibold text-blue-900 mb-2">
                        <i class="fas fa-user-graduate mr-2"></i>Schüler-Accounts
                    </h4>
                    <ul class="space-y-1 text-blue-800">
                        <li>• Können Aussteller durchsuchen</li>
                        <li>• Können sich für Aussteller einschreiben</li>
                        <li>• Sehen nur Schüler-Bereiche</li>
                        <li>• Bekommen automatische Zuteilung</li>
                    </ul>
                </div>
                <div class="bg-red-50 rounded-lg p-4">
                    <h4 class="font-semibold text-red-900 mb-2">
                        <i class="fas fa-crown mr-2"></i>Admin-Accounts
                    </h4>
                    <ul class="space-y-1 text-red-800">
                        <li>• Voller Zugriff auf alle Funktionen</li>
                        <li>• Können Aussteller verwalten</li>
                        <li>• Sehen Dashboard mit Statistiken</li>
                        <li>• Können Einstellungen ändern</li>
                    </ul>
                </div>
            </div>
            
            <div class="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                <p class="text-sm text-yellow-800">
                    <i class="fas fa-lightbulb mr-2"></i>
                    <strong>Tipp:</strong> Verwenden Sie sinnvolle Benutzernamen wie "vorname.nachname" für bessere Übersichtlichkeit.
                </p>
            </div>
        </div>

        <!-- Security Warning -->
        <div class="mt-6 bg-red-600 text-white rounded-2xl shadow-2xl p-6">
            <div class="flex items-start">
                <i class="fas fa-shield-alt text-3xl mr-4 flex-shrink-0"></i>
                <div>
                    <h3 class="text-xl font-bold mb-2">🔒 Sicherheitshinweis</h3>
                    <p class="mb-3">Diese Registrierungsseite ist <strong>nicht für den Produktivbetrieb</strong> gedacht!</p>
                    <ul class="space-y-1 text-sm">
                        <li>• Löschen Sie die Datei <code class="bg-red-700 px-2 py-1 rounded">register.php</code> vor dem Live-Gang</li>
                        <li>• Oder schützen Sie sie mit einem starken Passwort</li>
                        <li>• In Produktion sollten Accounts nur vom Admin angelegt werden</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
