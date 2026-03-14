<?php
/**
 * Aussteller-Dashboard: Übersicht über alle zugeordneten Unternehmen
 */
if (!isExhibitor() && !isAdmin()) die('Keine Berechtigung');

$db = getDB();
$userId = $_SESSION['user_id'];

// Alle Aussteller des eingeloggten Users laden
$stmt = $db->prepare("
    SELECT e.*, eu.can_edit_profile, eu.can_manage_documents,
           me.name as edition_name, me.year as edition_year, me.status as edition_status,
           s.name as school_name, s.slug as school_slug,
           r.room_number, r.room_name,
           (SELECT COUNT(*) FROM registrations reg
                WHERE reg.exhibitor_id = e.id
                  AND reg.edition_id = e.edition_id) as registration_count,
           (SELECT COUNT(*) FROM attendance att
                WHERE att.exhibitor_id = e.id
                  AND att.edition_id = e.edition_id) as attendance_count
    FROM exhibitor_users eu
    JOIN exhibitors e ON eu.exhibitor_id = e.id
    LEFT JOIN messe_editions me ON e.edition_id = me.id
    LEFT JOIN schools s ON me.school_id = s.id
    LEFT JOIN rooms r ON e.room_id = r.id
    WHERE eu.user_id = ?
    ORDER BY me.year DESC, s.name, e.name
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

<div class="mb-6">
    <h2 class="text-xl font-bold text-gray-800">
        <i class="fas fa-tachometer-alt mr-2" style="color: var(--color-pastel-mint);"></i>Aussteller-Dashboard
    </h2>
    <p class="text-sm text-gray-500 mt-1">
        Willkommen, <?php echo htmlspecialchars($_SESSION['firstname']); ?>! 
        Sie sind <?php echo count($exhibitors); ?> Unternehmen zugeordnet.
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
<?php foreach ($bySchool as $schoolName => $schoolExhibitors): ?>
<div class="mb-6">
    <h3 class="text-sm font-semibold text-gray-600 mb-3 flex items-center gap-2">
        <i class="fas fa-school text-gray-400"></i>
        <?php echo htmlspecialchars($schoolName); ?>
        <span class="text-xs font-normal text-gray-400">(<?php echo count($schoolExhibitors); ?> Unternehmen)</span>
    </h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <?php foreach ($schoolExhibitors as $ex): ?>
        <div class="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-md transition-shadow">
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
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs <?php echo $ex['edition_status'] === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500'; ?>">
                                <?php echo $ex['edition_status'] === 'active' ? 'Aktiv' : 'Inaktiv'; ?>
                            </span>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-2 text-xs text-gray-500 mb-3">
                <div><i class="fas fa-map-marker-alt mr-1"></i> Raum: <?php echo $ex['room_number'] ? htmlspecialchars($ex['room_number'] . ' ' . ($ex['room_name'] ?: '')) : '—'; ?></div>
                <div><i class="fas fa-users mr-1"></i> <?php echo (int)$ex['registration_count']; ?> Anmeldungen</div>
                <div><i class="fas fa-user-check mr-1"></i> <?php echo (int)$ex['attendance_count']; ?> Check-ins</div>
                <div>
                    <i class="fas fa-<?php echo $ex['active'] ? 'check-circle text-emerald-500' : 'times-circle text-red-400'; ?> mr-1"></i>
                    <?php echo $ex['active'] ? 'Aktiv' : 'Inaktiv'; ?>
                </div>
            </div>
            
            <div class="flex gap-2">
                <a href="?page=exhibitor-profile&exhibitor_id=<?php echo $ex['id']; ?>" class="flex-1 text-center px-3 py-1.5 bg-blue-50 text-blue-600 rounded-lg text-xs font-medium hover:bg-blue-100 transition-colors">
                    <i class="fas fa-edit mr-1"></i> Profil
                </a>
                <a href="?page=exhibitor-slots&exhibitor_id=<?php echo $ex['id']; ?>" class="flex-1 text-center px-3 py-1.5 bg-purple-50 text-purple-600 rounded-lg text-xs font-medium hover:bg-purple-100 transition-colors">
                    <i class="fas fa-calendar mr-1"></i> Slots
                </a>
                <a href="?page=exhibitor-documents&exhibitor_id=<?php echo $ex['id']; ?>" class="flex-1 text-center px-3 py-1.5 bg-amber-50 text-amber-600 rounded-lg text-xs font-medium hover:bg-amber-100 transition-colors">
                    <i class="fas fa-file mr-1"></i> Doku
                </a>
            </div>
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
