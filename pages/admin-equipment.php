<?php
/**
 * Admin: Ausstattungs-Optionen verwalten (für eine Schule)
 */
if (!isAdmin() && !isSchoolAdmin()) die('Keine Berechtigung');

$db = getDB();
$message = null;

// Schule bestimmen
$school = getCurrentSchool();
$schoolId = $school ? (int)$school['id'] : 1;

// Option erstellen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    requireCsrf();
    $name = sanitize($_POST['name'] ?? '');
    $desc = sanitize($_POST['description'] ?? '');
    $order = (int)($_POST['sort_order'] ?? 0);
    
    if (!empty($name)) {
        $stmt = $db->prepare("INSERT INTO equipment_options (school_id, name, description, sort_order) VALUES (?, ?, ?, ?)");
        $stmt->execute([$schoolId, $name, $desc ?: null, $order]);
        logAuditAction('ausstattung_option_erstellt', "Ausstattungsoption '$name' erstellt");
        $message = ['type' => 'success', 'text' => "Option \"$name\" erstellt."];
    }
}

// Option löschen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    requireCsrf();
    $optId = (int)($_POST['option_id'] ?? 0);
    $stmt = $db->prepare("DELETE FROM equipment_options WHERE id = ? AND school_id = ?");
    $stmt->execute([$optId, $schoolId]);
    $message = ['type' => 'success', 'text' => 'Option gelöscht.'];
}

// Option (de)aktivieren
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    requireCsrf();
    $optId = (int)($_POST['option_id'] ?? 0);
    $stmt = $db->prepare("UPDATE equipment_options SET is_active = NOT is_active WHERE id = ? AND school_id = ?");
    $stmt->execute([$optId, $schoolId]);
    $message = ['type' => 'success', 'text' => 'Status aktualisiert.'];
}

// Optionen laden
$stmt = $db->prepare("SELECT * FROM equipment_options WHERE school_id = ? ORDER BY sort_order, name");
$stmt->execute([$schoolId]);
$options = $stmt->fetchAll();

// Offene Anfragen laden
$stmt = $db->prepare("
    SELECT eer.*, eo.name as option_name, e.name as exhibitor_name
    FROM exhibitor_equipment_requests eer
    JOIN exhibitors e ON eer.exhibitor_id = e.id
    JOIN messe_editions me ON eer.edition_id = me.id
    LEFT JOIN equipment_options eo ON eer.equipment_option_id = eo.id
    WHERE me.school_id = ?
    ORDER BY eer.status ASC, eer.created_at DESC
");
$stmt->execute([$schoolId]);
$requests = $stmt->fetchAll();

// Anfrage-Status ändern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    requireCsrf();
    $reqId  = (int)($_POST['request_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $notes  = sanitize($_POST['admin_notes'] ?? '');
    
    if (in_array($status, ['approved', 'denied', 'pending'])) {
        $stmt = $db->prepare("UPDATE exhibitor_equipment_requests SET status = ?, admin_notes = ? WHERE id = ?");
        $stmt->execute([$status, $notes ?: null, $reqId]);
        logAuditAction('ausstattung_status', "Anfrage #{$reqId} Status: $status");
        $message = ['type' => 'success', 'text' => 'Status aktualisiert.'];
        
        // Anfragen neu laden
        $stmt = $db->prepare("
            SELECT eer.*, eo.name as option_name, e.name as exhibitor_name
            FROM exhibitor_equipment_requests eer
            JOIN exhibitors e ON eer.exhibitor_id = e.id
            JOIN messe_editions me ON eer.edition_id = me.id
            LEFT JOIN equipment_options eo ON eer.equipment_option_id = eo.id
            WHERE me.school_id = ?
            ORDER BY eer.status ASC, eer.created_at DESC
        ");
        $stmt->execute([$schoolId]);
        $requests = $stmt->fetchAll();
    }
}
?>

<?php if ($message): ?>
<div class="mb-4 p-4 rounded-xl border <?php echo $message['type'] === 'error' ? 'bg-red-50 border-red-200 text-red-700' : 'bg-emerald-50 border-emerald-200 text-emerald-700'; ?>">
    <div class="flex items-center gap-2">
        <i class="fas <?php echo $message['type'] === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'; ?>"></i>
        <?php echo htmlspecialchars($message['text']); ?>
    </div>
</div>
<?php endif; ?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-xl font-bold text-gray-800">
            <i class="fas fa-tools mr-2 text-amber-500"></i>Ausstattungsverwaltung
        </h2>
        <p class="text-sm text-gray-500 mt-1"><?php echo count($options); ?> Optionen · <?php echo count($requests); ?> Anfragen</p>
    </div>
</div>

<!-- Neue Option -->
<div class="bg-white rounded-xl border border-gray-200 p-5 mb-6">
    <h3 class="text-sm font-semibold text-gray-700 mb-3">
        <i class="fas fa-plus-circle mr-1 text-emerald-500"></i> Neue Ausstattungsoption
    </h3>
    <form method="POST" class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
        <input type="hidden" name="action" value="create">
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Name *</label>
            <input type="text" name="name" required class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent" placeholder="z.B. Beamer">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Beschreibung</label>
            <input type="text" name="description" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent" placeholder="Optional">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Reihenfolge</label>
            <input type="number" name="sort_order" value="0" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
        </div>
        <div>
            <button type="submit" class="w-full px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-medium rounded-lg transition-colors">
                <i class="fas fa-plus mr-1"></i> Erstellen
            </button>
        </div>
    </form>
</div>

<!-- Optionen-Liste -->
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-6">
    <div class="px-5 py-3 border-b bg-gray-50">
        <h3 class="text-sm font-semibold text-gray-700">Ausstattungsoptionen</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="text-left px-4 py-2 font-medium text-gray-600">Name</th>
                    <th class="text-left px-4 py-2 font-medium text-gray-600">Beschreibung</th>
                    <th class="text-center px-4 py-2 font-medium text-gray-600">Reihenfolge</th>
                    <th class="text-center px-4 py-2 font-medium text-gray-600">Status</th>
                    <th class="text-right px-4 py-2 font-medium text-gray-600">Aktion</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php foreach ($options as $opt): ?>
                <tr class="hover:bg-gray-50 <?php echo $opt['is_active'] ? '' : 'opacity-50'; ?>">
                    <td class="px-4 py-2 font-medium"><?php echo htmlspecialchars($opt['name']); ?></td>
                    <td class="px-4 py-2 text-gray-500"><?php echo htmlspecialchars($opt['description'] ?? '—'); ?></td>
                    <td class="px-4 py-2 text-center"><?php echo $opt['sort_order']; ?></td>
                    <td class="px-4 py-2 text-center">
                        <span class="px-2 py-0.5 rounded text-xs font-medium <?php echo $opt['is_active'] ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'; ?>">
                            <?php echo $opt['is_active'] ? 'Aktiv' : 'Inaktiv'; ?>
                        </span>
                    </td>
                    <td class="px-4 py-2 text-right">
                        <div class="flex items-center justify-end gap-1">
                            <form method="POST" class="inline">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="option_id" value="<?php echo $opt['id']; ?>">
                                <button type="submit" class="px-2 py-1 text-xs rounded <?php echo $opt['is_active'] ? 'text-amber-600 hover:bg-amber-50' : 'text-emerald-600 hover:bg-emerald-50'; ?>" title="<?php echo $opt['is_active'] ? 'Deaktivieren' : 'Aktivieren'; ?>">
                                    <i class="fas <?php echo $opt['is_active'] ? 'fa-toggle-on' : 'fa-toggle-off'; ?>"></i>
                                </button>
                            </form>
                            <form method="POST" class="inline" onsubmit="return confirm('Option wirklich löschen?')">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="option_id" value="<?php echo $opt['id']; ?>">
                                <button type="submit" class="px-2 py-1 text-xs text-red-400 hover:text-red-600 rounded hover:bg-red-50">
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
</div>

<!-- Anfragen-Liste -->
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <div class="px-5 py-3 border-b bg-gray-50">
        <h3 class="text-sm font-semibold text-gray-700">Ausstattungsanfragen</h3>
    </div>
    <?php if (empty($requests)): ?>
        <div class="p-8 text-center text-gray-400">
            <p class="text-sm">Keine Anfragen vorhanden.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="text-left px-4 py-2 font-medium text-gray-600">Aussteller</th>
                        <th class="text-left px-4 py-2 font-medium text-gray-600">Option</th>
                        <th class="text-left px-4 py-2 font-medium text-gray-600">Details</th>
                        <th class="text-center px-4 py-2 font-medium text-gray-600">Anz.</th>
                        <th class="text-center px-4 py-2 font-medium text-gray-600">Status</th>
                        <th class="text-right px-4 py-2 font-medium text-gray-600">Aktion</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($requests as $req): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 font-medium"><?php echo htmlspecialchars($req['exhibitor_name']); ?></td>
                        <td class="px-4 py-2"><?php echo htmlspecialchars($req['option_name'] ?? '—'); ?></td>
                        <td class="px-4 py-2 text-gray-500"><?php echo htmlspecialchars($req['custom_text'] ?? '—'); ?></td>
                        <td class="px-4 py-2 text-center"><?php echo (int)$req['quantity']; ?></td>
                        <td class="px-4 py-2 text-center">
                            <?php
                            $sc = ['pending' => 'bg-amber-50 text-amber-700', 'approved' => 'bg-emerald-50 text-emerald-700', 'denied' => 'bg-red-50 text-red-700'];
                            $sl = ['pending' => 'Offen', 'approved' => 'Genehmigt', 'denied' => 'Abgelehnt'];
                            ?>
                            <span class="px-2 py-0.5 rounded text-xs font-medium <?php echo $sc[$req['status']]; ?>">
                                <?php echo $sl[$req['status']]; ?>
                            </span>
                        </td>
                        <td class="px-4 py-2 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <form method="POST" class="inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                    <input type="hidden" name="status" value="approved">
                                    <button type="submit" class="px-2 py-1 text-xs text-emerald-600 hover:bg-emerald-50 rounded" title="Genehmigen">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </form>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                    <input type="hidden" name="status" value="denied">
                                    <button type="submit" class="px-2 py-1 text-xs text-red-400 hover:bg-red-50 rounded" title="Ablehnen">
                                        <i class="fas fa-times"></i>
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
