<?php
/**
 * Admin QR-Code Verwaltung (Issue #15)
 * Generiert und zeigt QR-Codes für Anwesenheitsprüfung
 */

// Alle Aussteller mit Räumen laden
$stmt = $db->query("
    SELECT e.*, r.room_number, r.room_name, r.building
    FROM exhibitors e
    LEFT JOIN rooms r ON e.room_id = r.id
    WHERE e.active = 1
    ORDER BY e.name
");
$exhibitors = $stmt->fetchAll();

// Alle Timeslots laden (inkl. Slots 2 und 4 für freie Wahl)
$stmt = $db->query("SELECT * FROM timeslots ORDER BY slot_number ASC");
$timeslots = $stmt->fetchAll();

// Bestehende Tokens laden
$stmt = $db->query("
    SELECT qt.*, e.name as exhibitor_name, t.slot_name, t.slot_number
    FROM qr_tokens qt
    JOIN exhibitors e ON qt.exhibitor_id = e.id
    JOIN timeslots t ON qt.timeslot_id = t.id
    WHERE qt.expires_at > NOW()
    ORDER BY e.name, t.slot_number
");
$existingTokens = $stmt->fetchAll();

// Tokens nach Aussteller+Slot gruppieren
$tokenMap = [];
foreach ($existingTokens as $token) {
    $key = $token['exhibitor_id'] . '_' . $token['timeslot_id'];
    $tokenMap[$key] = $token;
}

// Anwesenheitsstatistik laden
$stmt = $db->query("
    SELECT a.exhibitor_id, a.timeslot_id, COUNT(*) as present_count
    FROM attendance a
    GROUP BY a.exhibitor_id, a.timeslot_id
");
$attendanceStats = [];
foreach ($stmt->fetchAll() as $row) {
    $attendanceStats[$row['exhibitor_id'] . '_' . $row['timeslot_id']] = $row['present_count'];
}

// QR-Code Base URL aus Einstellungen laden
$qrCodeBaseUrl = getSetting('qr_code_url', 'https://localhost' . BASE_URL);

// POST-Handling wurde nach index.php verschoben (vor HTML-Output)
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
            <h2 class="text-xl font-semibold text-gray-800">
                <i class="fas fa-qrcode text-emerald-500 mr-2"></i>
                QR-Code Anwesenheit
            </h2>
            <p class="text-sm text-gray-500 mt-1">QR-Codes für die Anwesenheitsprüfung generieren und verwalten</p>
        </div>
        <form method="POST">
            <button type="submit" name="generate_all" value="1"
                    class="px-5 py-2.5 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition font-medium flex items-center gap-2"
                    onclick="return confirm('Alle QR-Codes neu generieren? Bestehende werden ersetzt.')">
                <i class="fas fa-sync-alt"></i>
                Alle QR-Codes generieren
            </button>
        </form>
    </div>

    <?php if (isset($_GET['generated'])): ?>
    <div class="bg-emerald-50 border border-emerald-200 p-4 rounded-xl">
        <div class="flex items-center">
            <i class="fas fa-check-circle text-emerald-500 mr-3"></i>
            <p class="text-emerald-700 text-sm"><?php echo intval($_GET['generated']); ?> QR-Code(s) erfolgreich generiert.</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Info Box -->
    <div class="bg-blue-50 border border-blue-100 rounded-xl p-4">
        <h4 class="font-semibold text-blue-900 text-sm flex items-center mb-2">
            <i class="fas fa-info-circle mr-2"></i>Ablauf
        </h4>
        <ol class="list-decimal list-inside space-y-1 text-xs text-blue-800">
            <li>QR-Codes für alle Aussteller/Slots generieren</li>
            <li>QR-Codes ausdrucken und den Ausstellern aushändigen</li>
            <li>Schüler scannen den QR-Code am Stand mit ihrem Handy</li>
            <li>Die Webapp gleicht den eingeloggten Benutzer mit der Zuteilung ab</li>
            <li>Anwesenheit wird automatisch erfasst</li>
        </ol>
    </div>

    <!-- QR-Code Übersicht nach Aussteller -->
    <?php foreach ($exhibitors as $exhibitor): ?>
    <div class="bg-white rounded-xl border border-gray-100 p-5">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="font-semibold text-gray-800"><?php echo htmlspecialchars($exhibitor['name']); ?></h3>
                <?php if ($exhibitor['room_number']): ?>
                <p class="text-xs text-gray-500">
                    <i class="fas fa-map-marker-alt mr-1"></i>
                    <?php echo htmlspecialchars($exhibitor['building'] . ' - ' . $exhibitor['room_number']); ?>
                    <?php if ($exhibitor['room_name']): ?>(<?php echo htmlspecialchars($exhibitor['room_name']); ?>)<?php endif; ?>
                </p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <?php foreach ($timeslots as $timeslot): 
                $key = $exhibitor['id'] . '_' . $timeslot['id'];
                $token = $tokenMap[$key] ?? null;
                $presentCount = $attendanceStats[$key] ?? 0;
                
                // Registrierungen für diesen Slot zählen
                $stmt = $db->prepare("SELECT COUNT(*) FROM registrations WHERE exhibitor_id = ? AND timeslot_id = ?");
                $stmt->execute([$exhibitor['id'], $timeslot['id']]);
                $regCount = $stmt->fetchColumn();
            ?>
            <div class="border border-gray-200 rounded-lg p-4 <?php echo $token ? 'bg-gray-50' : 'bg-yellow-50 border-yellow-200'; ?>">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-sm font-medium text-gray-700"><?php echo htmlspecialchars($timeslot['slot_name']); ?></span>
                    <span class="text-xs px-2 py-1 rounded-full <?php echo $presentCount > 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600'; ?>">
                        <?php echo $presentCount; ?>/<?php echo $regCount; ?> anwesend
                    </span>
                </div>
                
                <?php if ($token): ?>
                    <!-- QR-Code anzeigen -->
                    <div class="text-center mb-3">
                        <div id="qr-<?php echo $key; ?>" class="inline-block bg-white p-3 rounded-lg border border-gray-200">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode($qrCodeBaseUrl . '?page=qr-checkin&token=' . $token['token']); ?>" 
                                 alt="QR-Code" class="w-36 h-36" loading="lazy">
                        </div>
                    </div>
                    <div class="text-center">
                        <p class="text-xs text-gray-400 mb-2">
                            Gültig bis: <?php echo date('d.m.Y H:i', strtotime($token['expires_at'])); ?>
                        </p>
                        <div class="flex gap-2 justify-center">
                            <button onclick="window.open('https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=<?php echo urlencode($qrCodeBaseUrl . '?page=qr-checkin&token=' . $token['token']); ?>', '_blank')" 
                                    class="px-3 py-1.5 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition text-xs">
                                <i class="fas fa-print mr-1"></i>Gross
                            </button>
                            <button onclick="showToken('<?php echo htmlspecialchars($token['token'], ENT_QUOTES); ?>')" 
                                    class="px-3 py-1.5 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition text-xs">
                                <i class="fas fa-key mr-1"></i>Token
                            </button>
                            <form method="POST" class="inline">
                                <input type="hidden" name="exhibitor_id" value="<?php echo $exhibitor['id']; ?>">
                                <input type="hidden" name="timeslot_id" value="<?php echo $timeslot['id']; ?>">
                                <button type="submit" name="generate_single" value="1"
                                        class="px-3 py-1.5 bg-amber-100 text-amber-700 rounded-lg hover:bg-amber-200 transition text-xs">
                                    <i class="fas fa-redo mr-1"></i>Neu
                                </button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Kein QR-Code vorhanden -->
                    <div class="text-center py-4">
                        <i class="fas fa-qrcode text-3xl text-gray-300 mb-2"></i>
                        <p class="text-xs text-gray-500 mb-3">Kein QR-Code vorhanden</p>
                        <form method="POST">
                            <input type="hidden" name="exhibitor_id" value="<?php echo $exhibitor['id']; ?>">
                            <input type="hidden" name="timeslot_id" value="<?php echo $timeslot['id']; ?>">
                            <button type="submit" name="generate_single" value="1"
                                    class="px-3 py-1.5 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition text-xs">
                                <i class="fas fa-plus mr-1"></i>Generieren
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($exhibitors)): ?>
    <div class="bg-white rounded-xl border border-gray-100 p-12 text-center">
        <i class="fas fa-inbox text-5xl text-gray-300 mb-4"></i>
        <p class="text-gray-500">Keine aktiven Aussteller vorhanden.</p>
    </div>
    <?php endif; ?>
</div>

<script>
function showToken(token) {
    // Token in Zwischenablage kopieren
    navigator.clipboard.writeText(token).then(function() {
        // Modal oder Alert mit Token anzeigen
        alert('Token:\n\n' + token + '\n\n✓ In Zwischenablage kopiert!');
    }, function(err) {
        // Fallback wenn Zwischenablage nicht funktioniert
        alert('Token:\n\n' + token);
    });
}
</script>
