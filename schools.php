<?php
require_once 'config.php';
require_once 'functions.php';

$db = getDB();
try {
    $stmt = $db->query("SELECT id, name, slug, logo, address FROM schools WHERE is_active = 1 ORDER BY name");
    $schools = $stmt->fetchAll();
} catch (Exception $e) {
    $schools = [];
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Berufsmesse — Schule wählen</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/design-system.css">
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 to-blue-50 min-h-screen flex items-center justify-center">
    <div class="max-w-2xl w-full mx-auto p-8">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-2xl shadow-xl mb-4" style="background: linear-gradient(135deg, #a8e6cf 0%, #c3b1e1 100%);">
                <i class="fas fa-graduation-cap text-white text-3xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Berufsmesse</h1>
            <p class="text-gray-600">Wähle deine Schule, um fortzufahren:</p>
        </div>
        
        <div class="grid gap-4">
            <?php if (empty($schools)): ?>
                <div class="text-center text-gray-500 py-8">
                    <i class="fas fa-school text-4xl mb-3 text-gray-300"></i>
                    <p>Noch keine Schulen eingerichtet.</p>
                </div>
            <?php else: ?>
                <?php foreach ($schools as $school): ?>
                    <a href="<?= BASE_URL . htmlspecialchars($school['slug']) ?>/"
                       class="bg-white rounded-xl border border-gray-200 p-6 hover:shadow-lg transition-all duration-300 flex items-center gap-4">
                        <?php if ($school['logo']): ?>
                            <img src="uploads/<?= htmlspecialchars($school['logo']) ?>" alt="" class="h-12 w-12 object-contain rounded-lg">
                        <?php else: ?>
                            <div class="h-12 w-12 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-school text-blue-600"></i>
                            </div>
                        <?php endif; ?>
                        <div class="flex-1 min-w-0">
                            <h2 class="font-semibold text-lg text-gray-800"><?= htmlspecialchars($school['name']) ?></h2>
                            <?php if ($school['address']): ?>
                                <p class="text-sm text-gray-500"><?= htmlspecialchars($school['address']) ?></p>
                            <?php endif; ?>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400 flex-shrink-0"></i>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Link für Admins/Aussteller -->
        <div class="text-center mt-8 space-y-2">
            <?php if (isLoggedIn() && isAdmin()): ?>
            <div>
                <a href="<?= BASE_URL ?>global-admin.php" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-50 text-blue-700 rounded-lg text-sm font-medium hover:bg-blue-100 transition-colors">
                    <i class="fas fa-shield-alt"></i> Globale Verwaltung
                </a>
            </div>
            <?php endif; ?>
            <div>
                <a href="<?= BASE_URL ?>login.php" class="text-sm text-gray-500 hover:text-gray-700 transition-colors">
                    Admin / Aussteller Login →
                </a>
            </div>
        </div>
    </div>
</body>
</html>
