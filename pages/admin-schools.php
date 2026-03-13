<?php
if (!isAdmin()) die('Keine Berechtigung');

$db = getDB();
$message = null;

// Schule erstellen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    requireCsrf();
    $name    = sanitize($_POST['name'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $email   = sanitize($_POST['contact_email'] ?? '');
    $phone   = sanitize($_POST['contact_phone'] ?? '');

    if (empty($name)) {
        $message = ['type' => 'error', 'text' => 'Schulname ist Pflichtfeld.'];
    } else {
        $slug = generateSchoolSlug($name);
        $stmt = $db->prepare("INSERT INTO schools (name, slug, address, contact_email, contact_phone, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $slug, $address ?: null, $email ?: null, $phone ?: null, $_SESSION['user_id']]);
        logAuditAction('schule_erstellt', "Schule '$name' (Slug: $slug) erstellt", 'info');
        $message = ['type' => 'success', 'text' => "Schule \"$name\" erstellt (Slug: $slug)."];
    }
}

// Schule bearbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    requireCsrf();
    $id      = (int)($_POST['school_id'] ?? 0);
    $name    = sanitize($_POST['name'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $email   = sanitize($_POST['contact_email'] ?? '');
    $phone   = sanitize($_POST['contact_phone'] ?? '');

    if ($id > 0 && !empty($name)) {
        $stmt = $db->prepare("UPDATE schools SET name = ?, address = ?, contact_email = ?, contact_phone = ? WHERE id = ?");
        $stmt->execute([$name, $address ?: null, $email ?: null, $phone ?: null, $id]);
        logAuditAction('schule_bearbeitet', "Schule #$id bearbeitet", 'info');
        $message = ['type' => 'success', 'text' => 'Schule aktualisiert.'];
    }
}

// Schule (de)aktivieren
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_active') {
    requireCsrf();
    $id = (int)($_POST['school_id'] ?? 0);
    if ($id > 0) {
        $stmt = $db->prepare("UPDATE schools SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$id]);
        logAuditAction('schule_status', "Schule #$id Status geändert", 'warning');
        $message = ['type' => 'success', 'text' => 'Status aktualisiert.'];
    }
}

// Schulen laden
$schools = $db->query("SELECT s.*, (SELECT COUNT(*) FROM messe_editions me WHERE me.school_id = s.id) as edition_count FROM schools s ORDER BY s.name")->fetchAll();
?>

<?php if ($message): ?>
<div class="mb-4 p-4 rounded-xl border <?php echo $message['type'] === 'error' ? 'bg-red-50 border-red-200 text-red-700' : 'bg-emerald-50 border-emerald-200 text-emerald-700'; ?>">
    <div class="flex items-center gap-2">
        <i class="fas <?php echo $message['type'] === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'; ?>"></i>
        <?php echo htmlspecialchars($message['text']); ?>
    </div>
</div>
<?php endif; ?>

<!-- Header -->
<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-xl font-bold text-gray-800">
            <i class="fas fa-school mr-2 text-blue-500"></i>Schulverwaltung
        </h2>
        <p class="text-sm text-gray-500 mt-1"><?php echo count($schools); ?> Schulen</p>
    </div>
</div>

<!-- Neue Schule erstellen -->
<div class="bg-white rounded-xl border border-gray-200 p-5 mb-6">
    <h3 class="text-sm font-semibold text-gray-700 mb-3">
        <i class="fas fa-plus-circle mr-1 text-emerald-500"></i> Neue Schule erstellen
    </h3>
    <form method="POST" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
        <input type="hidden" name="action" value="create">
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Name *</label>
            <input type="text" name="name" required class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent" placeholder="z.B. Gymnasium Muster">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Adresse</label>
            <input type="text" name="address" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent" placeholder="Musterstrasse 1, 8000 Zürich">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">E-Mail</label>
            <input type="email" name="contact_email" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent" placeholder="info@schule.ch">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Telefon</label>
            <input type="text" name="contact_phone" class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent" placeholder="+41 44 123 45 67">
        </div>
        <div>
            <button type="submit" class="w-full px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-medium rounded-lg transition-colors">
                <i class="fas fa-plus mr-1"></i> Erstellen
            </button>
        </div>
    </form>
</div>

<!-- Schulen-Tabelle -->
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600">Schule</th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600">Slug</th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600">Kontakt</th>
                    <th class="text-center px-4 py-3 font-semibold text-gray-600">Editionen</th>
                    <th class="text-center px-4 py-3 font-semibold text-gray-600">Status</th>
                    <th class="text-right px-4 py-3 font-semibold text-gray-600">Aktionen</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($schools as $s): ?>
                <tr class="hover:bg-gray-50 <?php echo $s['is_active'] ? '' : 'opacity-50'; ?>">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <?php if ($s['logo']): ?>
                                <img src="uploads/<?php echo htmlspecialchars($s['logo']); ?>" class="h-8 w-8 object-contain rounded">
                            <?php else: ?>
                                <div class="h-8 w-8 bg-blue-100 rounded flex items-center justify-center">
                                    <i class="fas fa-school text-blue-500 text-xs"></i>
                                </div>
                            <?php endif; ?>
                            <div>
                                <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($s['name']); ?></p>
                                <?php if ($s['address']): ?>
                                    <p class="text-xs text-gray-400"><?php echo htmlspecialchars($s['address']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <code class="text-xs bg-gray-100 px-2 py-1 rounded">/<?php echo htmlspecialchars($s['slug']); ?>/</code>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-500">
                        <?php if ($s['contact_email']): ?>
                            <div><i class="fas fa-envelope mr-1"></i><?php echo htmlspecialchars($s['contact_email']); ?></div>
                        <?php endif; ?>
                        <?php if ($s['contact_phone']): ?>
                            <div><i class="fas fa-phone mr-1"></i><?php echo htmlspecialchars($s['contact_phone']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700">
                            <?php echo (int)$s['edition_count']; ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php echo $s['is_active'] ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'; ?>">
                            <?php echo $s['is_active'] ? 'Aktiv' : 'Inaktiv'; ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <a href="<?php echo BASE_URL . htmlspecialchars($s['slug']); ?>/index.php?page=admin-dashboard" 
                               class="px-2 py-1 text-xs bg-blue-50 text-blue-600 rounded hover:bg-blue-100 transition-colors"
                               title="Zur Schule" target="_blank">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                            <form method="POST" class="inline">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                <input type="hidden" name="action" value="toggle_active">
                                <input type="hidden" name="school_id" value="<?php echo $s['id']; ?>">
                                <button type="submit" class="px-2 py-1 text-xs <?php echo $s['is_active'] ? 'bg-red-50 text-red-600 hover:bg-red-100' : 'bg-emerald-50 text-emerald-600 hover:bg-emerald-100'; ?> rounded transition-colors" title="<?php echo $s['is_active'] ? 'Deaktivieren' : 'Aktivieren'; ?>">
                                    <i class="fas <?php echo $s['is_active'] ? 'fa-toggle-on' : 'fa-toggle-off'; ?>"></i>
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
