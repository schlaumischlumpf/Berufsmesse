<?php
require_once 'config.php';
require_once 'functions.php';

// Seitenpasswort prüfen
checkSitePassword();

$token   = trim($_GET['token'] ?? '');
$message = null;
$valid   = false;
$invite  = null;

if ($token) {
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT eu.*, e.name as exhibitor_name, u.username, u.firstname, u.lastname
        FROM exhibitor_users eu
        JOIN exhibitors e ON eu.exhibitor_id = e.id
        JOIN users u      ON eu.user_id      = u.id
        WHERE eu.invite_token = ?
          AND eu.invite_accepted = 0
          AND (eu.invite_expires IS NULL OR eu.invite_expires > NOW())
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $invite = $stmt->fetch();

    if (!$invite) {
        $message = ['type' => 'error',
            'text' => 'Dieser Einladungslink ist ungültig oder abgelaufen. Bitte wende dich an den Administrator.'];
    } else {
        $valid = true;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_password'])) {
            requireCsrf();
            $pw1 = $_POST['password']  ?? '';
            $pw2 = $_POST['password2'] ?? '';
            if (strlen($pw1) < 8) {
                $message = ['type' => 'error', 'text' => 'Das Passwort muss mindestens 8 Zeichen haben.'];
            } elseif ($pw1 !== $pw2) {
                $message = ['type' => 'error', 'text' => 'Die Passwörter stimmen nicht überein.'];
            } else {
                $hash = password_hash($pw1, PASSWORD_DEFAULT);
                $db->prepare("UPDATE users SET password = ? WHERE id = ?")
                   ->execute([$hash, $invite['user_id']]);
                $db->prepare("
                    UPDATE exhibitor_users
                    SET invite_accepted = 1, invite_token = NULL, invite_expires = NULL
                    WHERE id = ?
                ")->execute([$invite['id']]);
                logAuditAction(
                    'aussteller_einladung_angenommen',
                    "Aussteller '{$invite['username']}' Einladung für '{$invite['exhibitor_name']}' angenommen",
                    'info',
                    null  // system-level event — no school URL context
                );
                $message = ['type' => 'success',
                    'text' => 'Passwort gesetzt! Sie können sich jetzt einloggen. Sie werden in 3 Sekunden weitergeleitet…'];
                $valid = false;
                header("Refresh: 3; url=" . BASE_URL . "login.php");
            }
        }
    }
} else {
    $message = ['type' => 'error', 'text' => 'Kein Einladungstoken angegeben.'];
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Einladung annehmen – Berufsmesse</title>

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
        }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, var(--color-pastel-mint-light) 50%, var(--color-pastel-lavender-light) 100%);
            min-height: 100vh;
        }
        .accept-card { animation: slideUp 0.6s ease-out; }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .input-field { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .input-field:focus {
            border-color: var(--color-pastel-mint);
            box-shadow: 0 0 0 4px rgba(168, 230, 207, 0.3);
        }
        .btn-accept {
            background: linear-gradient(135deg, var(--color-pastel-mint) 0%, #6bc4a6 100%);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .btn-accept:hover  { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(168,230,207,0.4); }
        .btn-accept:active { transform: translateY(0); }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md relative z-10">
        <!-- Logo/Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-2xl shadow-xl mb-4"
                 style="background: linear-gradient(135deg, var(--color-pastel-mint) 0%, var(--color-pastel-lavender) 100%);">
                <i class="fas fa-handshake text-white text-3xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Berufsmesse</h1>
            <p class="text-gray-500">Aussteller-Einladung</p>
        </div>

        <!-- Card -->
        <div class="accept-card bg-white/90 backdrop-blur-lg rounded-3xl shadow-2xl p-8 border border-white/50">

            <?php if ($invite && $valid): ?>
            <!-- Exhibitor context info -->
            <div class="mb-6 p-4 rounded-xl bg-indigo-50 border border-indigo-100">
                <p class="text-sm text-indigo-700">
                    <i class="fas fa-building mr-2"></i>
                    <strong><?= htmlspecialchars($invite['exhibitor_name']) ?></strong>
                </p>
                <p class="text-sm text-indigo-600 mt-1">
                    <i class="fas fa-user mr-2"></i>
                    Benutzername: <strong><?= htmlspecialchars($invite['username']) ?></strong>
                </p>
            </div>

            <h2 class="text-xl font-bold text-gray-800 mb-2 text-center">Passwort festlegen</h2>
            <p class="text-sm text-gray-500 text-center mb-6">Setzen Sie ein Passwort, um sich einloggen zu können.</p>
            <?php endif; ?>

            <?php if ($message): ?>
            <div class="mb-6 p-4 rounded-xl border
                <?= $message['type'] === 'success'
                    ? 'bg-emerald-50 border-emerald-200 text-emerald-800'
                    : 'bg-red-50 border-red-200 text-red-800' ?>">
                <div class="flex items-center gap-3">
                    <i class="fas <?= $message['type'] === 'success' ? 'fa-check-circle text-emerald-500' : 'fa-exclamation-circle text-red-500' ?>"></i>
                    <p class="text-sm font-medium"><?= htmlspecialchars($message['text']) ?></p>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($valid): ?>
            <form method="POST" action="" class="space-y-5">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Passwort</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fas fa-lock text-gray-400"></i>
                        </div>
                        <input type="password" id="password" name="password" required minlength="8"
                               class="input-field w-full pl-11 pr-4 py-3.5 border-2 border-gray-200 rounded-xl focus:outline-none text-gray-800"
                               placeholder="Mindestens 8 Zeichen">
                    </div>
                </div>

                <div>
                    <label for="password2" class="block text-sm font-medium text-gray-700 mb-2">Passwort bestätigen</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fas fa-lock text-gray-400"></i>
                        </div>
                        <input type="password" id="password2" name="password2" required minlength="8"
                               class="input-field w-full pl-11 pr-4 py-3.5 border-2 border-gray-200 rounded-xl focus:outline-none text-gray-800"
                               placeholder="Passwort wiederholen">
                    </div>
                </div>

                <button type="submit" name="set_password" value="1"
                        class="btn-accept w-full text-gray-800 font-semibold py-3.5 px-6 rounded-xl shadow-lg flex items-center justify-center gap-2">
                    <i class="fas fa-key"></i>
                    <span>Passwort festlegen &amp; Account aktivieren</span>
                </button>
            </form>
            <?php elseif (!$message): ?>
            <p class="text-center text-gray-500 text-sm">Kein gültiger Token angegeben.</p>
            <?php endif; ?>

            <?php if (!$valid && $message && $message['type'] !== 'error'): ?>
            <div class="mt-4 text-center">
                <a href="<?= BASE_URL ?>login.php" class="text-sm text-indigo-600 hover:underline">
                    <i class="fas fa-sign-in-alt mr-1"></i>Zum Login
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="text-center mt-8 text-gray-500 text-sm">
            <p>&copy; 2026 Berufsmesse. Alle Rechte vorbehalten.</p>
        </div>
    </div>
</body>
</html>
