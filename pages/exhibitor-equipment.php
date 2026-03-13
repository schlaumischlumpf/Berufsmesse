<?php
/**
 * Aussteller-Ausstattung: Ausstattungsanfragen stellen
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

// Aussteller + Edition laden
$stmt = $db->prepare("SELECT e.*, me.name as edition_name, me.school_id FROM exhibitors e LEFT JOIN messe_editions me ON e.edition_id = me.id WHERE e.id = ?");
$stmt->execute([$exhibitorId]);
$exhibitor = $stmt->fetch();

if (!$exhibitor) {
    echo '<div class="p-4 bg-red-50 text-red-700 rounded-xl">Aussteller nicht gefunden.</div>';
    return;
}

$schoolId = (int)($exhibitor['school_id'] ?? 1);

// Anfrage speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_request') {
    requireCsrf();
    $equipmentId = (int)($_POST['equipment_option_id'] ?? 0);
    $customText  = sanitize($_POST['custom_text'] ?? '');
    $quantity    = max(1, (int)($_POST['quantity'] ?? 1));

    if ($equipmentId > 0 || !empty($customText)) {
        $stmt = $db->prepare("INSERT INTO exhibitor_equipment_requests (exhibitor_id, edition_id, equipment_option_id, custom_text, quantity, requested_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$exhibitorId, $exhibitor['edition_id'], $equipmentId ?: null, $customText ?: null, $quantity, $userId]);
        logAuditAction('ausstattung_angefragt', "Aussteller #{$exhibitorId}: Ausstattung angefragt");
        $message = ['type' => 'success', 'text' => 'Anfrage gespeichert.'];
    }
}

// Anfrage löschen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_request') {
    requireCsrf();
    $reqId = (int)($_POST['request_id'] ?? 0);
    $stmt = $db->prepare("DELETE FROM exhibitor_equipment_requests WHERE id = ? AND exhibitor_id = ? AND status = 'pending'");
    $stmt->execute([$reqId, $exhibitorId]);
    $message = ['type' => 'success', 'text' => 'Anfrage gelöscht.'];
}

// Verfügbare Optionen laden
try {
    $stmt = $db->prepare("SELECT * FROM equipment_options WHERE school_id = ? AND is_active = 1 ORDER BY sort_order, name");
    $stmt->execute([$schoolId]);
    $options = $stmt->fetchAll();
} catch (Exception $e) { $options = []; }

// Bestehende Anfragen laden
$stmt = $db->prepare("
    SELECT eer.*, eo.name as option_name
    FROM exhibitor_equipment_requests eer
    LEFT JOIN equipment_options eo ON eer.equipment_option_id = eo.id
    WHERE eer.exhibitor_id = ? AND eer.edition_id = ?
    ORDER BY eer.created_at DESC
");
$stmt->execute([$exhibitorId, $exhibitor['edition_id']]);
$requests = $stmt->fetchAll();
?>

<div class="mb-6">
    <div class="flex items-center gap-2 mb-1">
        <a href="?page=exhibitor-dashboard" class="text-gray-400 hover:text-gray-600 transition-colors">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h2 class="text-xl font-bold text-gray-800">
            <i class="fas fa-tools mr-2" style="color: var(--color-pastel-peach);"></i>
            Ausstattung — <?php echo htmlspecialchars($exhibitor['name']); ?>
        </h2>
    </div>
    <p class="text-sm text-gray-500">Ausstattung anfragen (Beamer, Strom, Tische, etc.)</p>
</div>

<?php if ($message): ?>
<div class="mb-4 p-4 rounded-xl border <?php echo $message['type'] === 'error' ? 'bg-red-50 border-red-200 text-red-700' : 'bg-emerald-50 border-emerald-200 text-emerald-700'; ?>">
    <div class="flex items-center gap-2">
        <i class="fas <?php echo $message['type'] === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'; ?>"></i>
        <?php echo htmlspecialchars($message['text']); ?>
    </div>
</div>
<?php endif; ?>

<!-- Neue Anfrage -->
<div class="bg-white rounded-xl border border-gray-200 p-5 mb-6">
    <h3 class="text-sm font-semibold text-gray-700 mb-3">
        <i class="fas fa-plus-circle mr-1 text-emerald-500"></i> Neue Anfrage
    </h3>
    <form method="POST" class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
        <input type="hidden" name="action" value="save_request">
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Ausstattung</label>
            <select name="equipment_option_id" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                <option value="0">— Freitext —</option>
                <?php foreach ($options as $opt): ?>
                    <option value="<?php echo $opt['id']; ?>"><?php echo htmlspecialchars($opt['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Freitext / Details</label>
            <input type="text" name="custom_text" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent" placeholder="z.B. 2 Verlängerungskabel">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Anzahl</label>
            <input type="number" name="quantity" value="1" min="1" max="99" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
        </div>
        <div>
            <button type="submit" class="w-full px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-medium rounded-lg transition-colors">
                <i class="fas fa-paper-plane mr-1"></i> Anfragen
            </button>
        </div>
    </form>
</div>

<!-- Bestehende Anfragen -->
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <div class="px-5 py-3 border-b border-gray-200 bg-gray-50">
        <h3 class="text-sm font-semibold text-gray-700">Meine Anfragen</h3>
    </div>
    <?php if (empty($requests)): ?>
        <div class="p-8 text-center text-gray-400">
            <i class="fas fa-inbox text-3xl mb-2"></i>
            <p class="text-sm">Noch keine Anfragen gestellt.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="text-left px-4 py-2 font-medium text-gray-600">Ausstattung</th>
                        <th class="text-left px-4 py-2 font-medium text-gray-600">Details</th>
                        <th class="text-center px-4 py-2 font-medium text-gray-600">Anzahl</th>
                        <th class="text-center px-4 py-2 font-medium text-gray-600">Status</th>
                        <th class="text-right px-4 py-2 font-medium text-gray-600">Aktion</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($requests as $req): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2"><?php echo htmlspecialchars($req['option_name'] ?? '—'); ?></td>
                        <td class="px-4 py-2 text-gray-500"><?php echo htmlspecialchars($req['custom_text'] ?? '—'); ?></td>
                        <td class="px-4 py-2 text-center"><?php echo (int)$req['quantity']; ?></td>
                        <td class="px-4 py-2 text-center">
                            <?php
                            $statusClasses = [
                                'pending'  => 'bg-amber-50 text-amber-700',
                                'approved' => 'bg-emerald-50 text-emerald-700',
                                'denied'   => 'bg-red-50 text-red-700',
                            ];
                            $statusLabels = ['pending' => 'Offen', 'approved' => 'Genehmigt', 'denied' => 'Abgelehnt'];
                            ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?php echo $statusClasses[$req['status']]; ?>">
                                <?php echo $statusLabels[$req['status']]; ?>
                            </span>
                        </td>
                        <td class="px-4 py-2 text-right">
                            <?php if ($req['status'] === 'pending'): ?>
                            <form method="POST" class="inline">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                <input type="hidden" name="action" value="delete_request">
                                <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                <button type="submit" class="text-red-400 hover:text-red-600 text-xs" onclick="return confirm('Anfrage löschen?')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
