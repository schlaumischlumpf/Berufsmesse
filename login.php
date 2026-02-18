<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// Wenn bereits eingeloggt, weiterleiten
if (isLoggedIn()) {
    $redirect = $_GET['redirect'] ?? '';
    if (!empty($redirect) && strpos($redirect, '/') === 0) {
        header('Location: ' . $redirect);
    } else {
        header('Location: ' . BASE_URL . 'index.php');
    }
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Bitte alle Felder ausfüllen';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, username, password, firstname, lastname, role FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['firstname'] = $user['firstname'];
            $_SESSION['lastname'] = $user['lastname'];
            $_SESSION['role'] = $user['role'];
            
            logAuditAction('Login', 'Benutzer hat sich angemeldet');
            
            // Prüfe ob Passwort erzwungen werden muss (beim ersten Login oder nach Admin-Reset)
            $db = getDB();
            $stmt = $db->prepare("SELECT must_change_password FROM users WHERE id = ?");
            $stmt->execute([$user['id']]);
            $userData = $stmt->fetch();
            
            if ($userData && $userData['must_change_password']) {
                $_SESSION['force_password_change'] = true;
                header('Location: ' . BASE_URL . 'change-password.php');
                exit();
            }
            
            // Nach Login zur ursprünglichen Seite zurückkehren (z.B. QR-Checkin)
            $redirect = $_GET['redirect'] ?? ($_POST['redirect'] ?? '');
            if (!empty($redirect) && strpos($redirect, '/') === 0) {
                header('Location: ' . $redirect);
            } else {
                header('Location: ' . BASE_URL . 'index.php');
            }
            exit();
        } else {
            $error = 'Ungültiger Benutzername oder Passwort';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Berufsmesse</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --color-pastel-mint: #a8e6cf;
            --color-pastel-mint-light: #d4f5e4;
            --color-pastel-lavender: #c3b1e1;
            --color-pastel-lavender-light: #e8dff5;
            --color-pastel-peach: #ffb7b2;
            --color-pastel-sky: #b5deff;
        }
        
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, var(--color-pastel-mint-light) 50%, var(--color-pastel-lavender-light) 100%);
            min-height: 100vh;
        }
        
        /* Card Animation */
        .login-card {
            animation: slideUp 0.6s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Input Focus Effects */
        .input-field {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .input-field:focus {
            border-color: var(--color-pastel-mint);
            box-shadow: 0 0 0 4px rgba(168, 230, 207, 0.3);
        }
        
        /* Button Hover */
        .btn-login {
            background: linear-gradient(135deg, var(--color-pastel-mint) 0%, #6bc4a6 100%);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(168, 230, 207, 0.4);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md relative z-10">
        <!-- Logo/Header -->
        <div class="text-center mb-8 animate-fade-in">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-2xl shadow-xl mb-4" style="background: linear-gradient(135deg, var(--color-pastel-mint) 0%, var(--color-pastel-lavender) 100%);">
                <i class="fas fa-graduation-cap text-white text-3xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Berufsmesse</h1>
            <p class="text-gray-500">Willkommen zurück! Bitte melde dich an.</p>
        </div>

        <!-- Login Card -->
        <div class="login-card bg-white/90 backdrop-blur-lg rounded-3xl shadow-2xl p-8 border border-white/50">
            <h2 class="text-xl font-bold text-gray-800 mb-6 text-center">Anmelden</h2>
            
            <?php if ($error): ?>
            <div class="mb-6 p-4 rounded-xl border" style="background: var(--color-pastel-peach); border-color: rgba(255, 183, 178, 0.5);">
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-lg bg-white/50 flex items-center justify-center mr-3">
                        <i class="fas fa-exclamation-circle text-red-500"></i>
                    </div>
                    <p class="text-gray-800 text-sm font-medium"><?php echo $error; ?></p>
                </div>
            </div>
            <?php endif; ?>

            <form method="POST" action="" class="space-y-5">
                <?php if (!empty($_GET['redirect'])): ?>
                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($_GET['redirect']); ?>">
                <?php endif; ?>
                <!-- Username -->
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                        Benutzername
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fas fa-user text-gray-400"></i>
                        </div>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            required
                            class="input-field w-full pl-11 pr-4 py-3.5 border-2 border-gray-200 rounded-xl focus:outline-none text-gray-800"
                            placeholder="Benutzername eingeben"
                        >
                    </div>
                </div>

                <!-- Password -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                        Passwort
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fas fa-lock text-gray-400"></i>
                        </div>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            required
                            class="input-field w-full pl-11 pr-4 py-3.5 border-2 border-gray-200 rounded-xl focus:outline-none text-gray-800"
                            placeholder="Passwort eingeben"
                        >
                    </div>
                </div>

                <!-- Submit Button -->
                <button 
                    type="submit"
                    class="btn-login w-full text-gray-800 font-semibold py-3.5 px-6 rounded-xl shadow-lg flex items-center justify-center gap-2"
                >
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Anmelden</span>
                </button>
            </form>
        </div>

        <!-- Footer -->
        <div class="text-center mt-8 text-gray-500 text-sm">
            <p>&copy; 2026 Berufsmesse. Alle Rechte vorbehalten.</p>
        </div>
    </div>
    
    <script>
        // Add animation class on load
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelector('.login-card').classList.add('animate');
        });
    </script>
</body>
</html>
