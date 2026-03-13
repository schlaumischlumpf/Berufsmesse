<?php
/**
 * Aussteller-Dokumente: Eigene Dokumente verwalten
 */
if (!isExhibitor() && !isAdmin()) die('Keine Berechtigung');

$db = getDB();
$userId = $_SESSION['user_id'];
$exhibitorId = (int)($_GET['exhibitor_id'] ?? 0);
$message = null;

// Prüfen ob User Zugriff hat
if (!isAdmin()) {
    $ids = getExhibitorIdsForUser($userId);
    if (!in_array($exhibitorId, $ids)) {
        echo '<div class="p-4 bg-red-50 text-red-700 rounded-xl">Kein Zugriff auf diesen Aussteller.</div>';
        return;
    }
}

// Aussteller laden
$stmt = $db->prepare("SELECT * FROM exhibitors WHERE id = ?");
$stmt->execute([$exhibitorId]);
$exhibitor = $stmt->fetch();

if (!$exhibitor) {
    echo '<div class="p-4 bg-red-50 text-red-700 rounded-xl">Aussteller nicht gefunden.</div>';
    return;
}

// Datei hochladen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload') {
    requireCsrf();
    if (isset($_FILES['document'])) {
        $result = uploadFile($_FILES['document'], $exhibitorId);
        if ($result['success']) {
            logAuditAction('dokument_hochgeladen', "Dokument für Aussteller #{$exhibitorId} hochgeladen");
            $message = ['type' => 'success', 'text' => 'Dokument hochgeladen.'];
        } else {
            $message = ['type' => 'error', 'text' => $result['message']];
        }
    }
}

// Datei löschen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    requireCsrf();
    $docId = (int)($_POST['document_id'] ?? 0);
    // Prüfen ob Dokument zum Aussteller gehört
    $stmt = $db->prepare("SELECT id FROM exhibitor_documents WHERE id = ? AND exhibitor_id = ?");
    $stmt->execute([$docId, $exhibitorId]);
    if ($stmt->fetch()) {
        deleteFile($docId);
        logAuditAction('dokument_geloescht', "Dokument #{$docId} von Aussteller #{$exhibitorId} gelöscht");
        $message = ['type' => 'success', 'text' => 'Dokument gelöscht.'];
    }
}

// Sichtbarkeit ändern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_visibility') {
    requireCsrf();
    $docId = (int)($_POST['document_id'] ?? 0);
    $stmt = $db->prepare("UPDATE exhibitor_documents SET visible_for_students = NOT visible_for_students WHERE id = ? AND exhibitor_id = ?");
    $stmt->execute([$docId, $exhibitorId]);
    $message = ['type' => 'success', 'text' => 'Sichtbarkeit aktualisiert.'];
}

// Dokumente laden
$stmt = $db->prepare("SELECT * FROM exhibitor_documents WHERE exhibitor_id = ? ORDER BY uploaded_at DESC");
$stmt->execute([$exhibitorId]);
$documents = $stmt->fetchAll();
?>

<div class="mb-6">
    <div class="flex items-center gap-2 mb-1">
        <a href="?page=exhibitor-dashboard" class="text-gray-400 hover:text-gray-600 transition-colors">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h2 class="text-xl font-bold text-gray-800">
            <i class="fas fa-file-alt mr-2" style="color: var(--color-pastel-sky);"></i>
            Dokumente — <?php echo htmlspecialchars($exhibitor['name']); ?>
        </h2>
    </div>
    <p class="text-sm text-gray-500"><?php echo count($documents); ?> Dokumente</p>
</div>

<?php if ($message): ?>
<div class="mb-4 p-4 rounded-xl border <?php echo $message['type'] === 'error' ? 'bg-red-50 border-red-200 text-red-700' : 'bg-emerald-50 border-emerald-200 text-emerald-700'; ?>">
    <div class="flex items-center gap-2">
        <i class="fas <?php echo $message['type'] === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'; ?>"></i>
        <?php echo htmlspecialchars($message['text']); ?>
    </div>
</div>
<?php endif; ?>

<!-- Upload -->
<div class="bg-white rounded-xl border border-gray-200 p-5 mb-6">
    <h3 class="text-sm font-semibold text-gray-700 mb-3">
        <i class="fas fa-upload mr-1 text-emerald-500"></i> Neues Dokument hochladen
    </h3>
    <form method="POST" enctype="multipart/form-data" class="flex items-end gap-4">
        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
        <input type="hidden" name="action" value="upload">
        <div class="flex-1">
            <label class="block text-xs font-medium text-gray-600 mb-1">Datei (max. 10 MB: PDF, DOC, PPT, Bilder)</label>
            <input type="file" name="document" required accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png,.gif"
                   class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
        </div>
        <button type="submit" class="px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-medium rounded-lg transition-colors">
            <i class="fas fa-upload mr-1"></i> Hochladen
        </button>
    </form>
</div>

<!-- Dokumente-Liste -->
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <?php if (empty($documents)): ?>
        <div class="p-8 text-center text-gray-400">
            <i class="fas fa-folder-open text-3xl mb-2"></i>
            <p class="text-sm">Noch keine Dokumente hochgeladen.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="text-left px-4 py-2 font-medium text-gray-600">Dateiname</th>
                        <th class="text-left px-4 py-2 font-medium text-gray-600">Typ</th>
                        <th class="text-center px-4 py-2 font-medium text-gray-600">Für Schüler sichtbar</th>
                        <th class="text-left px-4 py-2 font-medium text-gray-600">Hochgeladen</th>
                        <th class="text-right px-4 py-2 font-medium text-gray-600">Aktionen</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($documents as $doc): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-file text-gray-400"></i>
                                <?php echo htmlspecialchars($doc['original_filename'] ?? $doc['filename']); ?>
                            </div>
                        </td>
                        <td class="px-4 py-2 text-gray-500 uppercase text-xs"><?php echo htmlspecialchars($doc['file_type'] ?? '—'); ?></td>
                        <td class="px-4 py-2 text-center">
                            <form method="POST" class="inline">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                <input type="hidden" name="action" value="toggle_visibility">
                                <input type="hidden" name="document_id" value="<?php echo $doc['id']; ?>">
                                <button type="submit" class="<?php echo ($doc['visible_for_students'] ?? 0) ? 'text-emerald-500' : 'text-gray-300'; ?> hover:opacity-70">
                                    <i class="fas fa-<?php echo ($doc['visible_for_students'] ?? 0) ? 'eye' : 'eye-slash'; ?>"></i>
                                </button>
                            </form>
                        </td>
                        <td class="px-4 py-2 text-gray-500 text-xs"><?php echo date('d.m.Y H:i', strtotime($doc['uploaded_at'] ?? $doc['created_at'] ?? 'now')); ?></td>
                        <td class="px-4 py-2 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="api/download-document.php?id=<?php echo $doc['id']; ?>" class="text-blue-500 hover:text-blue-700 text-xs">
                                    <i class="fas fa-download"></i>
                                </a>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="document_id" value="<?php echo $doc['id']; ?>">
                                    <button type="submit" class="text-red-400 hover:text-red-600 text-xs" onclick="return confirm('Dokument löschen?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
