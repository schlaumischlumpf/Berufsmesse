<?php
/**
 * Aussteller-Profil: Unternehmensprofil bearbeiten
 */
if (!isExhibitor() && !isAdmin()) die('Keine Berechtigung');

$db = getDB();
$userId = $_SESSION['user_id'];
$ids = isAdmin() ? [] : getExhibitorIdsForUser($userId);
$exhibitorId = (int)($_GET['exhibitor_id'] ?? 0);
$message = null;

// Auto-default: if no exhibitor_id given, use the first one linked to this user
if (!isAdmin() && !in_array($exhibitorId, $ids)) {
    if (!empty($ids)) {
        $defaultId = $ids[0];
        $page = $_GET['page'] ?? 'exhibitor-profile';
        header("Location: ?page={$page}&exhibitor_id={$defaultId}");
        exit;
    } else {
        echo '<div class="p-4 bg-red-50 text-red-700 rounded-xl">Kein Aussteller-Konto verknüpft. Bitte wende dich an den Administrator.</div>';
        return;
    }
}

// Aussteller laden
$stmt = $db->prepare("
    SELECT e.*,
           r.room_number, r.room_name,
           me.name as edition_name, me.year as edition_year,
           s.name as school_name
    FROM exhibitors e
    LEFT JOIN rooms r ON e.room_id = r.id
    LEFT JOIN messe_editions me ON e.edition_id = me.id
    LEFT JOIN schools s ON me.school_id = s.id
    WHERE e.id = ?
");
$stmt->execute([$exhibitorId]);
$exhibitor = $stmt->fetch();

if (!$exhibitor) {
    echo '<div class="p-4 bg-red-50 text-red-700 rounded-xl">Aussteller nicht gefunden.</div>';
    return;
}

// Profil speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_profile') {
    requireCsrf();

    $shortDesc     = sanitize($_POST['short_description'] ?? '');
    $description   = sanitize($_POST['description']       ?? '');
    $contactPerson = sanitize($_POST['contact_person']    ?? '');
    $email         = sanitize($_POST['email']             ?? '');
    $phone         = sanitize($_POST['phone']             ?? '');
    $website       = sanitize($_POST['website']           ?? '');
    $jobs          = sanitize($_POST['jobs']              ?? '');
    $features      = sanitize($_POST['features']          ?? '');
    $offerSelected = isset($_POST['offer_types_selected']) ? (array)$_POST['offer_types_selected'] : [];
    $offerCustom   = trim($_POST['offer_types_custom'] ?? '');
    $offerTypesJson = (!empty($offerSelected) || $offerCustom !== '')
        ? json_encode(['selected' => $offerSelected, 'custom' => $offerCustom])
        : null;

    // Logo-Upload verarbeiten
    $logoPath = null;
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 5 * 1024 * 1024;
        if (in_array($_FILES['logo']['type'], $allowedTypes) && $_FILES['logo']['size'] <= $maxSize) {
            $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $filename = 'logo_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['logo']['tmp_name'], 'uploads/' . $filename)) {
                // Altes Logo löschen
                if ($exhibitor['logo'] && file_exists('uploads/' . $exhibitor['logo'])) {
                    unlink('uploads/' . $exhibitor['logo']);
                }
                $logoPath = $filename;
            }
        } else {
            $message = ['type' => 'error', 'text' => 'Logo-Datei ungültig (max. 5 MB, JPG/PNG/GIF/WebP).'];
        }
    }

    if (!$message) {
        if ($logoPath) {
            $stmt = $db->prepare("UPDATE exhibitors SET
                short_description = ?, description = ?, contact_person = ?, email = ?, phone = ?,
                website = ?, jobs = ?, features = ?, offer_types = ?, logo = ?
                WHERE id = ?");
            $stmt->execute([$shortDesc, $description, $contactPerson, $email ?: null,
                $phone ?: null, $website ?: null, $jobs ?: null, $features ?: null,
                $offerTypesJson, $logoPath, $exhibitorId]);
        } else {
            $stmt = $db->prepare("UPDATE exhibitors SET
                short_description = ?, description = ?, contact_person = ?, email = ?, phone = ?,
                website = ?, jobs = ?, features = ?, offer_types = ?
                WHERE id = ?");
            $stmt->execute([$shortDesc, $description, $contactPerson, $email ?: null,
                $phone ?: null, $website ?: null, $jobs ?: null, $features ?: null,
                $offerTypesJson, $exhibitorId]);
        }
        logAuditAction('aussteller_profil_bearbeitet', "Aussteller #{$exhibitorId} Profil aktualisiert");
        $message = ['type' => 'success', 'text' => 'Profil aktualisiert.'];
    }

    // Daten neu laden
    $stmt = $db->prepare("SELECT e.*, r.room_number, r.room_name FROM exhibitors e LEFT JOIN rooms r ON e.room_id = r.id WHERE e.id = ?");
    $stmt->execute([$exhibitorId]);
    $exhibitor = $stmt->fetch();
}

// Angebotstypen dekodieren
$offerDecoded = [];
if (!empty($exhibitor['offer_types'])) {
    $decoded = json_decode($exhibitor['offer_types'], true);
    if (is_array($decoded)) $offerDecoded = $decoded;
}
$offerSelected = $offerDecoded['selected'] ?? [];
$offerCustom   = $offerDecoded['custom']   ?? '';
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
    <p class="text-sm text-gray-500">
        <?php echo htmlspecialchars($exhibitor['school_name'] ?? 'Unbekannte Schule'); ?>
        · <?php echo htmlspecialchars($exhibitor['edition_name'] ?? ''); ?>
        · Unternehmensprofil bearbeiten
    </p>
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
    <form method="POST" enctype="multipart/form-data">
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

            <!-- Logo -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Logo</label>
                <div class="flex items-center gap-4">
                    <?php if ($exhibitor['logo']): ?>
                    <img src="<?php echo BASE_URL . 'uploads/' . $exhibitor['logo']; ?>"
                         alt="Logo" class="w-16 h-16 object-contain rounded-lg border border-gray-200 bg-gray-50">
                    <?php else: ?>
                    <div class="w-16 h-16 rounded-lg border border-gray-200 bg-gray-50 flex items-center justify-center">
                        <i class="fas fa-image text-gray-300 text-xl"></i>
                    </div>
                    <?php endif; ?>
                    <div class="flex-1">
                        <input type="file" name="logo" accept="image/*"
                               class="w-full text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
                        <p class="text-xs text-gray-400 mt-1">JPG, PNG, GIF oder WebP, max. 5 MB.</p>
                    </div>
                </div>
            </div>

            <!-- Kurzbeschreibung -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Kurzbeschreibung <span class="text-gray-400 font-normal">(max. 500 Zeichen)</span></label>
                <textarea name="short_description" rows="2" maxlength="500"
                          class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                          placeholder="Ein Satz über Ihr Unternehmen…"><?php echo htmlspecialchars($exhibitor['short_description'] ?? ''); ?></textarea>
            </div>

            <!-- Beschreibung -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Ausführliche Beschreibung</label>
                <textarea name="description" rows="4"
                          class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                          placeholder="Stellen Sie Ihr Unternehmen ausführlich vor…"><?php echo htmlspecialchars($exhibitor['description'] ?? ''); ?></textarea>
            </div>

            <!-- Ansprechpartner -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Ansprechpartner</label>
                <input type="text" name="contact_person" value="<?php echo htmlspecialchars($exhibitor['contact_person'] ?? ''); ?>"
                       class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                       placeholder="Name der Kontaktperson">
            </div>

            <!-- E-Mail -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">E-Mail</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($exhibitor['email'] ?? ''); ?>"
                       class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                       placeholder="kontakt@firma.de">
            </div>

            <!-- Telefon -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Telefon</label>
                <input type="text" name="phone" value="<?php echo htmlspecialchars($exhibitor['phone'] ?? ''); ?>"
                       class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                       placeholder="+49 123 456789">
            </div>

            <!-- Website -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Website</label>
                <input type="url" name="website" value="<?php echo htmlspecialchars($exhibitor['website'] ?? ''); ?>"
                       class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                       placeholder="https://www.example.com">
            </div>

            <!-- Typische Berufe / Tätigkeiten -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Typische Berufe / Tätigkeiten</label>
                <textarea name="jobs" rows="3"
                          class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                          placeholder="Welche Berufsbilder bieten Sie an?"><?php echo htmlspecialchars($exhibitor['jobs'] ?? ''); ?></textarea>
            </div>

            <!-- Besonderheiten -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Besonderheiten des Unternehmens</label>
                <textarea name="features" rows="3"
                          class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                          placeholder="Was zeichnet Ihr Unternehmen besonders aus?"><?php echo htmlspecialchars($exhibitor['features'] ?? ''); ?></textarea>
            </div>

            <!-- Angebotstypen -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">Angebotstypen</label>
                <div class="flex flex-wrap gap-4 mb-3">
                    <?php
                    $offerOptions = ['Ausbildung', 'Duales Studium', 'Praktikum'];
                    foreach ($offerOptions as $opt):
                    ?>
                    <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                        <input type="checkbox" name="offer_types_selected[]" value="<?php echo htmlspecialchars($opt); ?>"
                               <?php echo in_array($opt, $offerSelected) ? 'checked' : ''; ?>
                               class="w-4 h-4 text-emerald-500 rounded border-gray-300 focus:ring-emerald-400">
                        <?php echo htmlspecialchars($opt); ?>
                    </label>
                    <?php endforeach; ?>
                </div>
                <input type="text" name="offer_types_custom"
                       value="<?php echo htmlspecialchars($offerCustom); ?>"
                       class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                       placeholder="Sonstiges (freitext)">
            </div>

        </div>

        <div class="mt-6 flex justify-end">
            <button type="submit" class="px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-medium rounded-lg transition-colors">
                <i class="fas fa-save mr-1"></i> Speichern
            </button>
        </div>
    </form>
</div>
