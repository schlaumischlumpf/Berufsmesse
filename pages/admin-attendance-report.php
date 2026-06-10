<?php
// Auswertung nach der Messe (QR-Check-in 2.0)
if (!isAdminOrSchoolAdmin() && !hasPermission('berichte_sehen') && !hasPermission('qr_codes_sehen')) {
    die('Keine Berechtigung zum Anzeigen dieser Seite');
}

$schoolId = getCurrentSchoolId();
$schoolFilter = $schoolId !== null ? " AND u.school_id = " . (int)$schoolId : "";

// Gesamtzahlen
$totalAtt = (int)$db->query("SELECT COUNT(*) FROM attendance WHERE edition_id = " . (int)$activeEditionId)->fetchColumn();
$totalWrong = (int)$db->query("SELECT COUNT(*) FROM attendance WHERE edition_id = " . (int)$activeEditionId . " AND wrong_room = 1")->fetchColumn();
$totalReg = (int)$db->query("SELECT COUNT(*) FROM registrations WHERE edition_id = " . (int)$activeEditionId)->fetchColumn();
$overallPct = $totalReg > 0 ? round($totalAtt / $totalReg * 100) : 0;
$wrongPct = $totalAtt > 0 ? round($totalWrong / $totalAtt * 100, 1) : 0;

// Methoden-Verteilung
$methodDist = [];
$stmt = $db->query("SELECT checkin_method, COUNT(*) c FROM attendance WHERE edition_id = " . (int)$activeEditionId . " GROUP BY checkin_method");
foreach ($stmt->fetchAll() as $r) $methodDist[$r['checkin_method']] = (int)$r['c'];
$methodLabels = ['self_scan' => 'Selbst-Scan', 'teacher_scan' => 'Lehrer-Scan', 'manual' => 'Manuell'];

// Pro Slot
$stmt = $db->prepare("SELECT t.id, t.slot_name,
    (SELECT COUNT(*) FROM registrations rg WHERE rg.timeslot_id = t.id AND rg.edition_id = ?) reg,
    (SELECT COUNT(*) FROM attendance a WHERE a.timeslot_id = t.id AND a.edition_id = ?) pres
    FROM timeslots t WHERE t.edition_id = ? AND t.is_break = 0 ORDER BY t.slot_number");
$stmt->execute([$activeEditionId, $activeEditionId, $activeEditionId]);
$perSlot = $stmt->fetchAll();

// Pro Klasse (registriert vs. anwesend)
$regByClass = [];
$stmt = $db->prepare("SELECT u.class, COUNT(*) c FROM registrations rg JOIN users u ON rg.user_id = u.id
    WHERE rg.edition_id = ? $schoolFilter GROUP BY u.class");
$stmt->execute([$activeEditionId]);
foreach ($stmt->fetchAll() as $r) $regByClass[$r['class'] ?: 'Ohne Klasse'] = ['reg' => (int)$r['c'], 'pres' => 0];
$stmt = $db->prepare("SELECT u.class, COUNT(*) c FROM attendance a JOIN users u ON a.user_id = u.id
    WHERE a.edition_id = ? $schoolFilter GROUP BY u.class");
$stmt->execute([$activeEditionId]);
foreach ($stmt->fetchAll() as $r) {
    $k = $r['class'] ?: 'Ohne Klasse';
    if (!isset($regByClass[$k])) $regByClass[$k] = ['reg' => 0, 'pres' => 0];
    $regByClass[$k]['pres'] = (int)$r['c'];
}
ksort($regByClass);

// Aussteller-Ranking
$stmt = $db->prepare("SELECT e.name, COUNT(a.id) cnt FROM attendance a JOIN exhibitors e ON a.exhibitor_id = e.id
    WHERE a.edition_id = ? GROUP BY e.id ORDER BY cnt DESC LIMIT 15");
$stmt->execute([$activeEditionId]);
$ranking = $stmt->fetchAll();

// No-Show-Liste
$stmt = $db->prepare("SELECT u.class, u.firstname, u.lastname FROM users u
    WHERE u.role = 'student' $schoolFilter
      AND EXISTS (SELECT 1 FROM registrations rg WHERE rg.user_id = u.id AND rg.edition_id = ?)
      AND NOT EXISTS (SELECT 1 FROM attendance a WHERE a.user_id = u.id AND a.edition_id = ?)
    ORDER BY u.class, u.lastname, u.firstname");
$stmt->execute([$activeEditionId, $activeEditionId]);
$noShows = $stmt->fetchAll();
?>
<div class="space-y-6">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h2 class="text-xl font-semibold text-gray-800"><i class="fas fa-chart-pie text-emerald-500 mr-2"></i>Auswertung</h2>
            <p class="text-sm text-gray-500 mt-1">Bericht für die Schulleitung — Anwesenheit der aktiven Edition.</p>
        </div>
        <a href="<?php echo schoolUrl('api/export-attendance.php'); ?>" target="_blank" class="btn btn-secondary">
            <i class="fas fa-file-csv"></i><span>Komplett-Export (CSV)</span>
        </a>
    </div>

    <!-- KPIs -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="card p-4 text-center"><div class="text-2xl font-bold text-emerald-600"><?php echo $overallPct; ?>%</div><div class="text-xs text-gray-500">Anwesenheitsquote</div></div>
        <div class="card p-4 text-center"><div class="text-2xl font-bold text-gray-700"><?php echo $totalAtt; ?></div><div class="text-xs text-gray-500">Check-ins gesamt</div></div>
        <div class="card p-4 text-center"><div class="text-2xl font-bold text-amber-500"><?php echo $wrongPct; ?>%</div><div class="text-xs text-gray-500">Falsch-Raum-Quote</div></div>
        <div class="card p-4 text-center"><div class="text-2xl font-bold text-red-500"><?php echo count($noShows); ?></div><div class="text-xs text-gray-500">No-Shows</div></div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Pro Slot -->
        <div class="card p-5">
            <h3 class="text-lg font-semibold text-gray-800 mb-3">Anwesenheit pro Slot</h3>
            <table class="w-full text-sm">
                <thead><tr class="text-left text-gray-500 border-b border-gray-100"><th class="py-2">Slot</th><th>Anwesend</th><th>Registriert</th><th>Quote</th></tr></thead>
                <tbody>
                <?php foreach ($perSlot as $s): $q = $s['reg'] > 0 ? round($s['pres'] / $s['reg'] * 100) : 0; ?>
                    <tr class="border-b border-gray-50"><td class="py-2"><?php echo htmlspecialchars($s['slot_name']); ?></td>
                        <td><?php echo (int)$s['pres']; ?></td><td><?php echo (int)$s['reg']; ?></td>
                        <td><span class="badge <?php echo $q >= 90 ? 'badge-mint' : ($q >= 60 ? 'badge-sky' : 'badge-peach'); ?>"><?php echo $q; ?>%</span></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Methoden-Verteilung -->
        <div class="card p-5">
            <h3 class="text-lg font-semibold text-gray-800 mb-3">Check-in-Methoden</h3>
            <?php $sumM = array_sum($methodDist) ?: 1; foreach ($methodLabels as $k => $lbl): $c = $methodDist[$k] ?? 0; $p = round($c / $sumM * 100); ?>
                <div class="mb-2">
                    <div class="flex justify-between text-sm"><span><?php echo $lbl; ?></span><span class="text-gray-500"><?php echo $c; ?> · <?php echo $p; ?>%</span></div>
                    <div class="h-2 bg-gray-100 rounded-full overflow-hidden"><div class="h-full bg-emerald-400" style="width:<?php echo $p; ?>%"></div></div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Aussteller-Ranking -->
        <div class="card p-5">
            <h3 class="text-lg font-semibold text-gray-800 mb-3">Beliebteste Aussteller</h3>
            <ol class="space-y-1 text-sm">
                <?php foreach ($ranking as $i => $r): ?>
                    <li class="flex justify-between"><span><?php echo ($i + 1) . '. ' . htmlspecialchars($r['name']); ?></span><span class="text-gray-500 font-medium"><?php echo (int)$r['cnt']; ?></span></li>
                <?php endforeach; ?>
                <?php if (empty($ranking)): ?><li class="text-gray-400">Noch keine Check-ins.</li><?php endif; ?>
            </ol>
        </div>

        <!-- Pro Klasse -->
        <div class="card p-5">
            <h3 class="text-lg font-semibold text-gray-800 mb-3">Anwesenheit pro Klasse</h3>
            <div class="max-h-72 overflow-y-auto">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-gray-500 border-b border-gray-100"><th class="py-2">Klasse</th><th>Anwesend</th><th>Registriert</th></tr></thead>
                <tbody>
                <?php foreach ($regByClass as $cls => $v): ?>
                    <tr class="border-b border-gray-50"><td class="py-1"><?php echo htmlspecialchars($cls); ?></td><td><?php echo $v['pres']; ?></td><td><?php echo $v['reg']; ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <!-- No-Show-Liste -->
    <div class="card p-5">
        <h3 class="text-lg font-semibold text-gray-800 mb-3">No-Show-Liste (registriert, nie erschienen)</h3>
        <?php if (empty($noShows)): ?>
            <p class="text-sm text-emerald-600">Keine No-Shows — alle registrierten Schüler waren anwesend.</p>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-1 text-sm max-h-72 overflow-y-auto">
                <?php foreach ($noShows as $n): ?>
                    <div><span class="text-gray-400"><?php echo htmlspecialchars($n['class'] ?: '—'); ?></span> <?php echo htmlspecialchars($n['firstname'] . ' ' . $n['lastname']); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
