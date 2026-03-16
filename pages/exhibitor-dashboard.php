<?php
/**
 * Aussteller-Dashboard: Übersicht über alle zugeordneten Unternehmen
 */
if (!isExhibitor() && !isAdmin()) die('Keine Berechtigung');

$db = getDB();
$userId = $_SESSION['user_id'];

// POST-Handler: Aussteller sagt Teilnahme ab (mit Bestätigungspflicht innerhalb 1 Woche)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_exhibitor_self'])) {
    requireCsrf();
    $cancelExId = intval($_POST['exhibitor_id']);
    $cancelReason = sanitize(trim($_POST['cancel_reason'] ?? ''));

    // Prüfe ob der User diesem Aussteller zugeordnet ist
    $checkStmt = $db->prepare("
        SELECT eu.exhibitor_id, e.edition_id, me.school_id
        FROM exhibitor_users eu
        JOIN exhibitors e ON eu.exhibitor_id = e.id
        JOIN messe_editions me ON e.edition_id = me.id
        WHERE eu.exhibitor_id = ? AND eu.user_id = ? AND eu.status = 'active'
    ");
    $checkStmt->execute([$cancelExId, $userId]);
    $link = $checkStmt->fetch();

    if ($link) {
        $result = createCancellationRequest($cancelExId, $userId, (int)$link['school_id'], 'exhibitor', $cancelReason);
        if ($result['success']) {
            if ($result['requires_confirmation']) {
                $exDashMessage = ['type' => 'info', 'text' => "Ihr Absage-Antrag für '{$result['name']}' wurde an die Schule gesendet und wartet auf Bestätigung."];
            } else {
                $msg = "Ihre Teilnahme für '{$result['name']}' wurde abgesagt.";
                if (($result['redistributed'] ?? 0) > 0) $msg .= " {$result['redistributed']} Schüler wurden umverteilt.";
                $exDashMessage = ['type' => 'success', 'text' => $msg];
            }
        } else {
            $exDashMessage = ['type' => 'error', 'text' => $result['error']];
        }
    } else {
        $exDashMessage = ['type' => 'error', 'text' => 'Keine aktive Zuordnung gefunden.'];
    }
}

// POST-Handler: Absage-Antrag bestätigen/ablehnen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_cancellation'])) {
    requireCsrf();
    $reqId = intval($_POST['request_id']);
    $approve = isset($_POST['approve']);

    $result = confirmCancellationRequest($reqId, $userId, $approve);
    if ($result['success']) {
        if ($approve) {
            $exDashMessage = ['type' => 'success', 'text' => 'Absage wurde bestätigt.'];
        } else {
            $exDashMessage = ['type' => 'info', 'text' => 'Absage wurde abgelehnt.'];
        }
    } else {
        $exDashMessage = ['type' => 'error', 'text' => $result['error']];
    }
}

// POST-Handler: Einladung annehmen (ohne Passwort-Änderung für Re-Invites)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_invite'])) {
    requireCsrf();
    $acceptExId = intval($_POST['exhibitor_id']);

    $result = acceptExhibitorInvitation($userId, $acceptExId);
    if ($result['success']) {
        $exDashMessage = ['type' => 'success', 'text' => "Einladung für '{$result['name']}' wurde angenommen. Sie können nun teilnehmen."];
    } else {
        $exDashMessage = ['type' => 'error', 'text' => $result['error']];
    }
}

// Alle Aussteller des eingeloggten Users laden (inkl. ausstehende Einladungen)
$stmt = $db->prepare("
    SELECT e.*, eu.can_edit_profile, eu.can_manage_documents,
           eu.status as eu_status, eu.invite_accepted, eu.cancelled_at, eu.cancel_reason, eu.user_id as eu_user_id,
           me.name as edition_name, me.year as edition_year, me.status as edition_status,
           s.name as school_name, s.slug as school_slug,
           r.room_number, r.room_name,
           (SELECT COUNT(*) FROM registrations reg
                JOIN users ru ON reg.user_id = ru.id
                WHERE reg.exhibitor_id = e.id
                  AND reg.edition_id = e.edition_id
                  AND (me.school_id IS NULL OR ru.school_id = me.school_id)) as registration_count,
           (SELECT COUNT(*) FROM attendance att
                JOIN users au ON att.user_id = au.id
                WHERE att.exhibitor_id = e.id
                  AND att.edition_id = e.edition_id
                  AND (me.school_id IS NULL OR au.school_id = me.school_id)) as attendance_count
    FROM exhibitor_users eu
    JOIN exhibitors e ON eu.exhibitor_id = e.id
    LEFT JOIN messe_editions me ON e.edition_id = me.id
    LEFT JOIN schools s ON me.school_id = s.id
    LEFT JOIN rooms r ON e.room_id = r.id
    WHERE eu.user_id = ?
    ORDER BY eu.status = 'active' DESC, me.year DESC, s.name, e.name
");
$stmt->execute([$userId]);
$exhibitors = $stmt->fetchAll();

// Nach Schulen gruppieren
$bySchool = [];
foreach ($exhibitors as $ex) {
    $schoolName = $ex['school_name'] ?: 'Unbekannte Schule';
    $bySchool[$schoolName][] = $ex;
}
?>

<?php if (isset($exDashMessage)): ?>
<div class="mb-4 p-4 rounded-lg border <?php echo $exDashMessage['type'] === 'success' ? 'bg-emerald-50 border-emerald-200' : 'bg-red-50 border-red-200'; ?>">
    <div class="flex items-center">
        <i class="fas <?php echo $exDashMessage['type'] === 'success' ? 'fa-check-circle text-emerald-500' : 'fa-exclamation-circle text-red-500'; ?> mr-3"></i>
        <p class="<?php echo $exDashMessage['type'] === 'success' ? 'text-emerald-700' : 'text-red-700'; ?> text-sm"><?php echo htmlspecialchars($exDashMessage['text']); ?></p>
    </div>
</div>
<?php endif; ?>

<div class="mb-6">
    <h2 class="text-xl font-bold text-gray-800">
        <i class="fas fa-tachometer-alt mr-2" style="color: var(--color-pastel-mint);"></i>Aussteller-Dashboard
    </h2>
    <p class="text-sm text-gray-500 mt-1">
        Willkommen, <?php echo htmlspecialchars($_SESSION['firstname']); ?>!
        Sie sind <?php echo count(array_filter($exhibitors, fn($e) => $e['eu_status'] === 'active')); ?> Unternehmen aktiv zugeordnet.
    </p>
</div>

<!-- Statistik-Karten -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
                <i class="fas fa-building text-blue-500"></i>
            </div>
            <div>
                <p class="text-2xl font-bold text-gray-800"><?php echo count($exhibitors); ?></p>
                <p class="text-xs text-gray-500">Zugeordnete Unternehmen</p>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center">
                <i class="fas fa-school text-emerald-500"></i>
            </div>
            <div>
                <p class="text-2xl font-bold text-gray-800"><?php echo count($bySchool); ?></p>
                <p class="text-xs text-gray-500">Schulen</p>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-purple-50 flex items-center justify-center">
                <i class="fas fa-users text-purple-500"></i>
            </div>
            <div>
                <p class="text-2xl font-bold text-gray-800"><?php echo array_sum(array_column($exhibitors, 'registration_count')); ?></p>
                <p class="text-xs text-gray-500">Anmeldungen gesamt</p>
            </div>
        </div>
    </div>
</div>

<!-- Unternehmen nach Schule gruppiert -->
<?php
// Aktuelle Schule aus URL ermitteln für Hervorhebung
$currentDashSchool = getCurrentSchool();
$currentDashSlug = $currentDashSchool ? $currentDashSchool['slug'] : null;
?>
<?php foreach ($bySchool as $schoolName => $schoolExhibitors):
    // Slug der ersten Exhibitor-Schule finden für den Link
    $groupSlug = $schoolExhibitors[0]['school_slug'] ?? null;
    $isCurrentSchool = ($groupSlug === $currentDashSlug);
?>
<div class="mb-6">
    <h3 class="text-sm font-semibold text-gray-600 mb-3 flex items-center gap-2">
        <?php if ($groupSlug && !$isCurrentSchool): ?>
            <a href="<?php echo BASE_URL . htmlspecialchars($groupSlug); ?>/index.php?page=exhibitor-dashboard"
               class="flex items-center gap-2 hover:text-blue-600 transition-colors" title="Zu dieser Schule wechseln">
                <i class="fas fa-school text-gray-400"></i>
                <?php echo htmlspecialchars($schoolName); ?>
                <i class="fas fa-external-link-alt text-xs text-blue-400"></i>
            </a>
        <?php else: ?>
            <i class="fas fa-school <?php echo $isCurrentSchool ? 'text-emerald-500' : 'text-gray-400'; ?>"></i>
            <?php echo htmlspecialchars($schoolName); ?>
            <?php if ($isCurrentSchool): ?>
                <span class="text-xs font-normal text-emerald-500">(aktuell)</span>
            <?php endif; ?>
        <?php endif; ?>
        <span class="text-xs font-normal text-gray-400">(<?php echo count($schoolExhibitors); ?> Unternehmen)</span>
    </h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <?php foreach ($schoolExhibitors as $ex): ?>
        <?php
            $isCancelled = ($ex['eu_status'] !== 'active');
            $isPending = (!$isCancelled && $ex['invite_accepted'] == 0);
        ?>
        <div class="bg-white rounded-xl border <?php echo $isCancelled ? 'border-red-200 opacity-70' : ($isPending ? 'border-amber-300' : 'border-gray-200'); ?> p-5 hover:shadow-md transition-shadow">
            <div class="flex items-start justify-between mb-3">
                <div class="flex items-center gap-3">
                    <?php if ($ex['logo']): ?>
                        <img src="uploads/<?php echo htmlspecialchars($ex['logo']); ?>" alt="" class="h-10 w-10 object-contain rounded-lg">
                    <?php else: ?>
                        <div class="h-10 w-10 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-building text-blue-500"></i>
                        </div>
                    <?php endif; ?>
                    <div>
                        <h4 class="font-semibold text-gray-800"><?php echo htmlspecialchars($ex['name']); ?></h4>
                        <p class="text-xs text-gray-500">
                            <?php echo htmlspecialchars($ex['edition_name'] ?? 'N/A'); ?> ·
                            <?php if ($isPending): ?>
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-amber-50 text-amber-700 font-semibold">
                                    <i class="fas fa-envelope mr-1"></i>Einladung ausstehend
                                </span>
                            <?php elseif ($isCancelled): ?>
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-red-50 text-red-600">
                                    <?php
                                    $statusLabels = [
                                        'cancelled_by_exhibitor' => 'Von Ihnen abgesagt',
                                        'cancelled_by_school' => 'Von Schule entfernt',
                                        'removed_by_admin' => 'Vom Admin entfernt',
                                    ];
                                    echo $statusLabels[$ex['eu_status']] ?? 'Abgesagt';
                                    ?>
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs <?php echo $ex['edition_status'] === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500'; ?>">
                                    <?php echo $ex['edition_status'] === 'active' ? 'Aktiv' : 'Inaktiv'; ?>
                                </span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>

            <?php if ($isPending): ?>
            <div class="mb-3 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                <p class="text-sm font-medium text-amber-800 mb-2">
                    <i class="fas fa-envelope mr-2"></i>Sie wurden zu diesem Aussteller eingeladen
                </p>
                <p class="text-xs text-amber-700 mb-3">Bitte bestätigen Sie die Einladung, um teilzunehmen.</p>
                <form method="POST" class="inline">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" name="exhibitor_id" value="<?php echo $ex['id']; ?>">
                    <button type="submit" name="accept_invite" class="w-full py-2 bg-emerald-500 text-white text-sm font-medium rounded-lg hover:bg-emerald-600 transition">
                        <i class="fas fa-check mr-2"></i>Einladung annehmen
                    </button>
                </form>
            </div>
            <?php elseif ($isCancelled && $ex['cancel_reason']): ?>
            <div class="mb-3 p-2 bg-red-50 rounded-lg text-xs text-red-600">
                <i class="fas fa-info-circle mr-1"></i> <?php echo htmlspecialchars($ex['cancel_reason']); ?>
                <?php if ($ex['cancelled_at']): ?>
                    <span class="text-red-400 ml-1">(<?php echo date('d.m.Y', strtotime($ex['cancelled_at'])); ?>)</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!$isCancelled && !$isPending): ?>
            <div class="grid grid-cols-2 gap-2 text-xs text-gray-500 mb-3">
                <div><i class="fas fa-map-marker-alt mr-1"></i> Raum: <?php echo $ex['room_number'] ? htmlspecialchars($ex['room_number'] . ' ' . ($ex['room_name'] ?: '')) : '—'; ?></div>
                <div><i class="fas fa-users mr-1"></i> <?php echo (int)$ex['registration_count']; ?> Anmeldungen</div>
                <div><i class="fas fa-user-check mr-1"></i> <?php echo (int)$ex['attendance_count']; ?> Check-ins</div>
                <div>
                    <i class="fas fa-<?php echo $ex['active'] ? 'check-circle text-emerald-500' : 'times-circle text-red-400'; ?> mr-1"></i>
                    <?php echo $ex['active'] ? 'Aktiv' : 'Inaktiv'; ?>
                </div>
            </div>

            <div class="flex gap-2 mb-3">
                <?php
                // [SCHOOL ISOLATION] Links zur richtigen Schule routen
                $exSchoolBase = $ex['school_slug']
                    ? BASE_URL . htmlspecialchars($ex['school_slug']) . '/index.php'
                    : '?';
                $exLinkPrefix = $ex['school_slug'] ? $exSchoolBase . '?' : '?';
                ?>
                <a href="<?php echo $exLinkPrefix; ?>page=exhibitor-profile&exhibitor_id=<?php echo $ex['id']; ?>" class="flex-1 text-center px-3 py-1.5 bg-blue-50 text-blue-600 rounded-lg text-xs font-medium hover:bg-blue-100 transition-colors">
                    <i class="fas fa-edit mr-1"></i> Profil
                </a>
                <a href="<?php echo $exLinkPrefix; ?>page=exhibitor-slots&exhibitor_id=<?php echo $ex['id']; ?>" class="flex-1 text-center px-3 py-1.5 bg-purple-50 text-purple-600 rounded-lg text-xs font-medium hover:bg-purple-100 transition-colors">
                    <i class="fas fa-calendar mr-1"></i> Slots
                </a>
                <a href="<?php echo $exLinkPrefix; ?>page=exhibitor-documents&exhibitor_id=<?php echo $ex['id']; ?>" class="flex-1 text-center px-3 py-1.5 bg-amber-50 text-amber-600 rounded-lg text-xs font-medium hover:bg-amber-100 transition-colors">
                    <i class="fas fa-file mr-1"></i> Doku
                </a>
            </div>

            <!-- Teilnahme absagen -->
            <details class="border-t border-gray-100 pt-2">
                <summary class="text-xs text-red-500 cursor-pointer hover:text-red-700 transition-colors">
                    <i class="fas fa-times-circle mr-1"></i> Teilnahme absagen
                </summary>
                <form method="POST" class="mt-2 space-y-2" onsubmit="return confirm('Möchten Sie die Teilnahme wirklich absagen? Die Schule wird informiert und Ihre Schüler werden umverteilt.')">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" name="exhibitor_id" value="<?php echo $ex['id']; ?>">
                    <textarea name="cancel_reason" placeholder="Grund der Absage (optional)" rows="2"
                        class="w-full px-3 py-2 text-xs border border-gray-200 rounded-lg focus:ring-2 focus:ring-red-300 focus:outline-none"></textarea>
                    <button type="submit" name="cancel_exhibitor_self" class="w-full py-1.5 bg-red-500 text-white text-xs font-medium rounded-lg hover:bg-red-600 transition">
                        <i class="fas fa-times mr-1"></i> Teilnahme endgültig absagen
                    </button>
                </form>
            </details>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<?php if (empty($exhibitors)): ?>
<div class="text-center py-12 text-gray-500">
    <i class="fas fa-building text-4xl mb-3 text-gray-300"></i>
    <p>Sie sind noch keinem Unternehmen zugeordnet.</p>
    <p class="text-sm mt-1">Bitte kontaktieren Sie den Administrator Ihrer Schule.</p>
</div>
<?php endif; ?>
