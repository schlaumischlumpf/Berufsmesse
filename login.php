<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// Wenn bereits eingeloggt, weiterleiten
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . 'index.php');
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
            
            header('Location: ' . BASE_URL . 'index.php');
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
    <title>Login - Berufsmesse 2026</title>
    
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
        
        .gradient-text {
            background: linear-gradient(135deg, #22c55e 0%, #a855f7 50%, #3b82f6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }
        
        .animated-bg {
            background: linear-gradient(-45deg, #22c55e, #a855f7, #3b82f6, #f97316);
            background-size: 400% 400%;
            animation: gradient 15s ease infinite;
        }
        
        @keyframes gradient {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .floating {
            animation: floating 3s ease-in-out infinite;
        }
        
        @keyframes floating {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        
        .floating-delayed {
            animation: floating 3s ease-in-out infinite;
            animation-delay: 1s;
        }
        
        .floating-delayed-2 {
            animation: floating 3s ease-in-out infinite;
            animation-delay: 2s;
        }
        
        .glow-effect {
            box-shadow: 0 0 60px rgba(34, 197, 94, 0.3), 0 0 100px rgba(168, 85, 247, 0.2);
        }
        
        .input-focus-glow:focus {
            box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.15), 0 10px 40px rgba(34, 197, 94, 0.1);
        }
        
        .btn-glow {
            box-shadow: 0 10px 40px rgba(34, 197, 94, 0.4);
        }
        
        .btn-glow:hover {
            box-shadow: 0 15px 50px rgba(34, 197, 94, 0.5);
            transform: translateY(-2px);
        }
        
        .particle {
            position: absolute;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            opacity: 0.5;
            animation: float-particle 8s infinite;
        }
        
        @keyframes float-particle {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-100px) rotate(180deg); }
        }
        
        .shimmer {
            background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.4) 50%, transparent 100%);
            background-size: 200% 100%;
            animation: shimmer 2s infinite;
        }
        
        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }
    </style>
</head>
<body class="min-h-screen animated-bg flex items-center justify-center p-4 relative overflow-hidden">
    <!-- Floating Shapes Background -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 left-20 w-72 h-72 bg-white/10 rounded-full blur-3xl floating"></div>
        <div class="absolute top-40 right-20 w-96 h-96 bg-white/10 rounded-full blur-3xl floating-delayed"></div>
        <div class="absolute bottom-20 left-1/3 w-80 h-80 bg-white/10 rounded-full blur-3xl floating-delayed-2"></div>
        
        <!-- Particles -->
        <div class="particle bg-white/30" style="top: 10%; left: 10%; animation-delay: 0s;"></div>
        <div class="particle bg-white/30" style="top: 20%; left: 80%; animation-delay: 1s;"></div>
        <div class="particle bg-white/30" style="top: 60%; left: 20%; animation-delay: 2s;"></div>
        <div class="particle bg-white/30" style="top: 80%; left: 70%; animation-delay: 3s;"></div>
        <div class="particle bg-white/30" style="top: 40%; left: 50%; animation-delay: 4s;"></div>
    </div>

    <div class="w-full max-w-md relative z-10">
        <!-- Logo/Header -->
        <div class="text-center mb-10">
            <div class="relative inline-block">
                <div class="w-24 h-24 bg-white rounded-3xl flex items-center justify-center shadow-2xl glow-effect mb-6 mx-auto transform hover:scale-110 hover:rotate-6 transition-all duration-500">
                    <div class="w-20 h-20 bg-gradient-to-br from-green-400 to-green-600 rounded-2xl flex items-center justify-center">
                        <i class="fas fa-graduation-cap text-white text-4xl"></i>
                    </div>
                </div>
                <div class="absolute -top-2 -right-2 w-8 h-8 bg-gradient-to-br from-purple-500 to-pink-500 rounded-full flex items-center justify-center shadow-lg animate-bounce">
                    <i class="fas fa-star text-white text-xs"></i>
                </div>
            </div>
            <h1 class="text-4xl font-extrabold font-display text-white mb-2 drop-shadow-lg">
                Berufsmesse
            </h1>
            <p class="text-white/80 text-lg font-medium">Karriere Portal 2026</p>
        </div>

        <!-- Login Card -->
        <div class="login-card rounded-3xl shadow-2xl p-8 border border-white/20 glow-effect">
            <div class="text-center mb-8">
                <h2 class="text-2xl font-bold text-gray-800 font-display">Willkommen zurück!</h2>
                <p class="text-gray-500 mt-1">Melden Sie sich an, um fortzufahren</p>
            </div>
            
            <?php if ($error): ?>
            <div class="bg-gradient-to-r from-red-50 to-pink-50 border-l-4 border-red-500 p-4 mb-6 rounded-xl animate-pulse">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-red-100 rounded-xl flex items-center justify-center mr-3">
                        <i class="fas fa-exclamation-circle text-red-500"></i>
                    </div>
                    <p class="text-red-700 font-medium"><?php echo $error; ?></p>
                </div>
            </div>
            <?php endif; ?>

            <form method="POST" action="" class="space-y-6">
                <!-- Username -->
                <div class="space-y-2">
                    <label for="username" class="block text-sm font-semibold text-gray-700">
                        Benutzername
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <div class="w-10 h-10 bg-gradient-to-br from-green-400 to-green-600 rounded-xl flex items-center justify-center">
                                <i class="fas fa-user text-white text-sm"></i>
                            </div>
                        </div>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            required
                            class="w-full pl-16 pr-4 py-4 bg-gray-50 border-2 border-gray-200 rounded-2xl text-gray-800 placeholder-gray-400 focus:border-green-500 focus:bg-white focus:outline-none input-focus-glow transition-all duration-300"
                            placeholder="Benutzername eingeben"
                        >
                    </div>
                </div>

                <!-- Password -->
                <div class="space-y-2">
                    <label for="password" class="block text-sm font-semibold text-gray-700">
                        Passwort
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <div class="w-10 h-10 bg-gradient-to-br from-purple-400 to-purple-600 rounded-xl flex items-center justify-center">
                                <i class="fas fa-lock text-white text-sm"></i>
                            </div>
                        </div>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            required
                            class="w-full pl-16 pr-4 py-4 bg-gray-50 border-2 border-gray-200 rounded-2xl text-gray-800 placeholder-gray-400 focus:border-purple-500 focus:bg-white focus:outline-none input-focus-glow transition-all duration-300"
                            placeholder="••••••••"
                        >
                    </div>
                </div>

                <!-- Submit Button -->
                <button 
                    type="submit"
                    class="w-full bg-gradient-to-r from-green-500 to-green-600 text-white font-bold py-4 px-6 rounded-2xl btn-glow transition-all duration-300 flex items-center justify-center gap-3 group"
                >
                    <span class="text-lg">Anmelden</span>
                    <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center group-hover:bg-white/30 transition-colors">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                </button>
            </form>

            <!-- Demo Credentials -->
            <div class="mt-8 p-5 bg-gradient-to-br from-blue-50 to-indigo-50 rounded-2xl border border-blue-100">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-blue-400 to-blue-600 rounded-xl flex items-center justify-center">
                        <i class="fas fa-info text-white"></i>
                    </div>
                    <span class="font-bold text-blue-800">Demo-Zugangsdaten</span>
                </div>
                <div class="space-y-2 text-sm">
                    <div class="flex items-center justify-between p-2 bg-white/50 rounded-lg">
                        <span class="text-blue-700"><i class="fas fa-user-shield mr-2"></i>Admin:</span>
                        <code class="bg-blue-100 px-2 py-1 rounded text-blue-800 font-mono text-xs">admin / admin123</code>
                    </div>
                    <div class="flex items-center justify-between p-2 bg-white/50 rounded-lg">
                        <span class="text-blue-700"><i class="fas fa-user-graduate mr-2"></i>Schüler:</span>
                        <code class="bg-blue-100 px-2 py-1 rounded text-blue-800 font-mono text-xs">max.mueller / student123</code>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-8 text-white/70 text-sm">
            <p class="flex items-center justify-center gap-2">
                <i class="fas fa-shield-alt"></i>
                <span>&copy; 2026 Berufsmesse. Alle Rechte vorbehalten.</span>
            </p>
        </div>
    </div>

    <script>
        // Add interactive effects
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('scale-[1.02]');
            });
            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('scale-[1.02]');
            });
        });
    </script>
</body>
</html>
