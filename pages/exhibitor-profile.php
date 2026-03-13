<?php
/**
 * Aussteller-Profil: Unternehmensprofil bearbeiten
 */
if (!isExhibitor() && !isAdmin()) die('Keine Berechtigung');

$db = getDB();
$userId = $_SESSION['user_id'];
$exhibitorId = (int)($_GET['exhibitor_id'] ?? 0);
$message = null;

// Prüfen ob User Zugriff auf diesen Aussteller hat
if (!isAdmin()) {
    $ids = getExhibitorIdsForUser($userId);
    if (!in_array($exhibitorId, $ids)) {
        echo '<div class="p-4 bg-red-50 text-red-700 rounded-xl">Kein Zugriff auf diesen Aussteller.</div>';
        return;
    }
}

// Aussteller laden
$stmt = $db->prepare("SELECT e.*, r.room_number, r.room_name FROM exhibitors e LEFT JOIN rooms r ON e.room_id = r.id WHERE e.id = ?");
$stmt->execute([$exhibitorId]);
$exhibitor = $stmt->fetch();

if (!$exhibitor) {
    echo '<div class="p-4 bg-red-50 text-red-700 rounded-xl">Aussteller nicht gefunden.</div>';
    return;
}

// Profil speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_profile') {
    requireCsrf();
    $description = sanitize($_POST['description'] ?? '');
    $website = sanitize($_POST['website'] ?? '');

    $stmt = $db->prepare("UPDATE exhibitors SET description = ?, website = ? WHERE id = ?");
    $stmt->execute([$description, $website ?: null, $exhibitorId]);
    logAuditAction('aussteller_profil_bearbeitet', "Aussteller #{$exhibitorId} Profil aktualisiert");
    $message = ['type' => 'success', 'text' => 'Profil aktualisiert.'];
    
    // Daten neu laden
    $stmt = $db->prepare("SELECT e.*, r.room_number, r.room_name FROM exhibitors e LEFT JOIN rooms r ON e.room_id = r.id WHERE e.id = ?");
    $stmt->execute([$exhibitorId]);
    $exhibitor = $stmt->fetch();
}

// Branchen laden
$industries = $db->query("SELECT * FROM industries ORDER BY sort_order, name")->fetchAll();
?>

<div class="mb-6">
    <div class="flex items-center gap-2 mb-1">
        <a href="?page=exhibitor-dashboard" class="text-gray-400 hover:text-gray-600 transition-colors">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h2 class="text-xl font-bold text-gray-800">
            <i class="fas fa-building mr-2" style="color: var(--color-pastel-lavender);"></i>
            <?php echo htmlspecialchars($exhibitor['name']); ?>
        </h2>
    </div>
    <p class="text-sm text-gray-500">Unternehmensprofil bearbeiten</p>
</div>

<?php if ($message): ?>
<div class="mb-4 p-4 rounded-xl border <?php echo $message['type'] === 'error' ? 'bg-red-50 border-red-200 text-red-700' : 'bg-emerald-50 border-emerald-200 text-emerald-700'; ?>">
    <div class="flex items-center gap-2">
        <i class="fas <?php echo $message['type'] === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'; ?>"></i>
        <?php echo htmlspecialchars($message['text']); ?>
    </div>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl border border-gray-200 p-6">
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
        <input type="hidden" name="action" value="save_profile">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Name (nur lesbar) -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Unternehmensname</label>
                <input type="text" value="<?php echo htmlspecialchars($exhibitor['name']); ?>" disabled
                       class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 text-gray-500">
                <p class="text-xs text-gray-400 mt-1">Änderungen bitte an den Administrator.</p>
            </div>
            
            <!-- Raum (nur lesbar) -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Zugewiesener Raum</label>
                <input type="text" value="<?php echo $exhibitor['room_number'] ? htmlspecialchars($exhibitor['room_number'] . ' ' . ($exhibitor['room_name'] ?: '')) : 'Noch nicht zugewiesen'; ?>" disabled
                       class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 text-gray-500">
            </div>
            
            <!-- Beschreibung -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Beschreibung</label>
                <textarea name="description" rows="4"
                          class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                          placeholder="Stellen Sie Ihr Unternehmen vor..."><?php echo htmlspecialchars($exhibitor['description'] ?? ''); ?></textarea>
            </div>
            
            <!-- Website -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Website</label>
                <input type="url" name="website" value="<?php echo htmlspecialchars($exhibitor['website'] ?? ''); ?>"
                       class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                       placeholder="https://www.example.com">
            </div>
        </div>
        
        <div class="mt-6 flex justify-end">
            <button type="submit" class="px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-medium rounded-lg transition-colors">
                <i class="fas fa-save mr-1"></i> Speichern
            </button>
        </div>
    </form>
</div>
