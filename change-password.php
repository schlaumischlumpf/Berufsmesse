<?php
/**
 * Berufsmesse - Passwort ändern (Erzwungen beim ersten Login)
 */
session_start();
require_once 'config.php';
require_once 'functions.php';

// Prüfen ob der Benutzer eingeloggt ist und sein Passwort ändern muss
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if (!isset($_SESSION['force_password_change']) || !$_SESSION['force_password_change']) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($newPassword) || empty($confirmPassword)) {
        $error = 'Bitte alle Felder ausfüllen';
    } elseif (strlen($newPassword) < 6) {
        $error = 'Das Passwort muss mindestens 6 Zeichen lang sein';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Die Passwörter stimmen nicht überein';
    } else {
        $db = getDB();
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $stmt = $db->prepare("UPDATE users SET password = ?, force_password_change = 0 WHERE id = ?");
        if ($stmt->execute([$hashedPassword, $_SESSION['user_id']])) {
            unset($_SESSION['force_password_change']);
            header('Location: index.php');
            exit();
        } else {
            $error = 'Fehler beim Ändern des Passworts';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passwort ändern - Berufsmesse 2026</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                        display: ['Plus Jakarta Sans', 'system-ui', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            font-family: 'Inter', system-ui, sans-serif;
        }
        
        .animated-bg {
            background: linear-gradient(-45deg, #f59e0b, #ea580c, #dc2626, #db2777);
            background-size: 400% 400%;
            animation: gradient 15s ease infinite;
        }
        
        @keyframes gradient {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
        }
        
        .input-focus-glow:focus {
            box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.15), 0 10px 40px rgba(245, 158, 11, 0.1);
        }
    </style>
</head>
<body class="min-h-screen animated-bg flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Header -->
        <div class="text-center mb-10">
            <div class="w-24 h-24 bg-white rounded-3xl flex items-center justify-center shadow-2xl mb-6 mx-auto">
                <div class="w-20 h-20 bg-gradient-to-br from-amber-400 to-orange-600 rounded-2xl flex items-center justify-center">
                    <i class="fas fa-key text-white text-4xl"></i>
                </div>
            </div>
            <h1 class="text-3xl font-extrabold font-display text-white mb-2 drop-shadow-lg">
                Passwort ändern
            </h1>
            <p class="text-white/80 text-lg font-medium">
                Bitte wähle ein neues Passwort
            </p>
        </div>

        <!-- Card -->
        <div class="card rounded-3xl shadow-2xl p-8 border border-white/20">
            <div class="bg-gradient-to-r from-amber-50 to-orange-50 border-l-4 border-amber-500 p-4 mb-6 rounded-xl">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center mr-3">
                        <i class="fas fa-info-circle text-amber-500"></i>
                    </div>
                    <div>
                        <p class="text-amber-800 font-semibold">Erstanmeldung</p>
                        <p class="text-amber-700 text-sm">Du musst dein Passwort bei der ersten Anmeldung ändern.</p>
                    </div>
                </div>
            </div>
            
            <?php if ($error): ?>
            <div class="bg-gradient-to-r from-red-50 to-pink-50 border-l-4 border-red-500 p-4 mb-6 rounded-xl">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-red-100 rounded-xl flex items-center justify-center mr-3">
                        <i class="fas fa-exclamation-circle text-red-500"></i>
                    </div>
                    <p class="text-red-700 font-medium"><?php echo $error; ?></p>
                </div>
            </div>
            <?php endif; ?>

            <form method="POST" action="" class="space-y-6">
                <div class="space-y-2">
                    <label for="new_password" class="block text-sm font-semibold text-gray-700">
                        Neues Passwort
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <div class="w-10 h-10 bg-gradient-to-br from-amber-400 to-orange-600 rounded-xl flex items-center justify-center">
                                <i class="fas fa-lock text-white text-sm"></i>
                            </div>
                        </div>
                        <input 
                            type="password" 
                            id="new_password" 
                            name="new_password" 
                            required
                            minlength="6"
                            class="w-full pl-16 pr-4 py-4 bg-gray-50 border-2 border-gray-200 rounded-2xl text-gray-800 placeholder-gray-400 focus:border-amber-500 focus:bg-white focus:outline-none input-focus-glow transition-all duration-300"
                            placeholder="Mindestens 6 Zeichen"
                        >
                    </div>
                </div>

                <div class="space-y-2">
                    <label for="confirm_password" class="block text-sm font-semibold text-gray-700">
                        Passwort bestätigen
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <div class="w-10 h-10 bg-gradient-to-br from-orange-400 to-red-600 rounded-xl flex items-center justify-center">
                                <i class="fas fa-check text-white text-sm"></i>
                            </div>
                        </div>
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            required
                            minlength="6"
                            class="w-full pl-16 pr-4 py-4 bg-gray-50 border-2 border-gray-200 rounded-2xl text-gray-800 placeholder-gray-400 focus:border-orange-500 focus:bg-white focus:outline-none input-focus-glow transition-all duration-300"
                            placeholder="Passwort wiederholen"
                        >
                    </div>
                </div>

                <button 
                    type="submit"
                    class="w-full bg-gradient-to-r from-amber-500 to-orange-600 text-white font-bold py-4 px-6 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 flex items-center justify-center gap-3 group"
                >
                    <span class="text-lg">Passwort speichern</span>
                    <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center group-hover:bg-white/30 transition-colors">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </button>
            </form>
        </div>

        <!-- Footer -->
        <div class="text-center mt-8 text-white/70 text-sm">
            <p class="flex items-center justify-center gap-2">
                <i class="fas fa-shield-alt"></i>
                <span>Angemeldet als <?php echo htmlspecialchars($_SESSION['firstname'] . ' ' . $_SESSION['lastname']); ?></span>
            </p>
        </div>
    </div>
</body>
</html>
