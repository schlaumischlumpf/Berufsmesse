<?php
/**
 * QR-Code Check-In Seite (Issue #15)
 * Schüler scannen QR-Code und werden hier eingecheckt
 */

$token = $_GET['token'] ?? '';
$checkinResult = null;

if (!empty($token)) {
    // Token validieren
    $stmt = $db->prepare("
        SELECT qt.*, e.name as exhibitor_name, e.short_description, t.slot_name, t.slot_number,
               r.room_number
        FROM qr_tokens qt
        JOIN exhibitors e ON qt.exhibitor_id = e.id
        JOIN timeslots t ON qt.timeslot_id = t.id
        LEFT JOIN rooms r ON e.room_id = r.id
        WHERE qt.token = ? AND (qt.expires_at IS NULL OR qt.expires_at > NOW())
    ");
    $stmt->execute([$token]);
    $qrToken = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$qrToken) {
        $checkinResult = ['type' => 'error', 'message' => 'Ungültiger oder abgelaufener QR-Code.'];
    } else {
        $userId = $_SESSION['user_id'];
        $exhibitorId = $qrToken['exhibitor_id'];
        $timeslotId = $qrToken['timeslot_id'];
        
        // Prüfen ob der Schüler für diesen Aussteller/Slot registriert ist
        $stmt = $db->prepare("
            SELECT id FROM registrations 
            WHERE user_id = ? AND exhibitor_id = ? AND timeslot_id = ?
        ");
        $stmt->execute([$userId, $exhibitorId, $timeslotId]);
        $registration = $stmt->fetch();
        
        if (!$registration) {
            $checkinResult = [
                'type' => 'warning', 
                'message' => 'Du bist nicht für diesen Aussteller in diesem Zeitslot zugeteilt.',
                'exhibitor' => $qrToken
            ];
        } else {
            // Prüfen ob bereits eingecheckt
            $stmt = $db->prepare("
                SELECT id FROM attendance 
                WHERE user_id = ? AND exhibitor_id = ? AND timeslot_id = ?
            ");
            $stmt->execute([$userId, $exhibitorId, $timeslotId]);
            
            if ($stmt->fetch()) {
                $checkinResult = [
                    'type' => 'info', 
                    'message' => 'Du bist bereits als anwesend markiert.',
                    'exhibitor' => $qrToken
                ];
            } else {
                // Anwesenheit eintragen
                try {
                    $stmt = $db->prepare("
                        INSERT INTO attendance (user_id, exhibitor_id, timeslot_id, qr_token) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$userId, $exhibitorId, $timeslotId, $token]);
                    
                    $checkinResult = [
                        'type' => 'success', 
                        'message' => 'Anwesenheit erfolgreich bestätigt!',
                        'exhibitor' => $qrToken
                    ];
                } catch (Exception $e) {
                    $checkinResult = ['type' => 'error', 'message' => 'Fehler beim Check-in: ' . $e->getMessage()];
                }
            }
        }
    }
}
?>

<div class="max-w-lg mx-auto space-y-6">
    <!-- Header -->
    <div class="text-center">
        <div class="w-16 h-16 mx-auto rounded-2xl flex items-center justify-center shadow-lg mb-4" 
             style="background: linear-gradient(135deg, var(--color-pastel-mint) 0%, var(--color-pastel-lavender) 100%);">
            <i class="fas fa-qrcode text-white text-2xl"></i>
        </div>
        <h2 class="text-xl font-semibold text-gray-800">QR-Code Check-In</h2>
        <p class="text-sm text-gray-500 mt-1">Scanne den QR-Code am Ausstellerstand</p>
    </div>

    <?php if ($checkinResult): ?>
        <?php
        $colors = [
            'success' => ['bg' => 'emerald', 'icon' => 'check-circle'],
            'error'   => ['bg' => 'red',     'icon' => 'times-circle'],
            'warning' => ['bg' => 'amber',   'icon' => 'exclamation-triangle'],
            'info'    => ['bg' => 'blue',     'icon' => 'info-circle'],
        ];
        $c = $colors[$checkinResult['type']] ?? $colors['info'];
        ?>
        
        <div class="bg-<?php echo $c['bg']; ?>-50 border border-<?php echo $c['bg']; ?>-200 p-6 rounded-xl text-center">
            <div class="w-16 h-16 mx-auto bg-<?php echo $c['bg']; ?>-100 rounded-full flex items-center justify-center mb-4">
                <i class="fas fa-<?php echo $c['icon']; ?> text-<?php echo $c['bg']; ?>-500 text-3xl"></i>
            </div>
            <h3 class="font-semibold text-<?php echo $c['bg']; ?>-800 text-lg mb-2"><?php echo $checkinResult['message']; ?></h3>
            
            <?php if (isset($checkinResult['exhibitor'])): ?>
            <div class="mt-4 bg-white rounded-lg p-4 border border-<?php echo $c['bg']; ?>-100">
                <p class="font-semibold text-gray-800"><?php echo htmlspecialchars(html_entity_decode($checkinResult['exhibitor']['exhibitor_name'], ENT_QUOTES | ENT_HTML5, 'UTF-8')); ?></p>
                <p class="text-sm text-gray-500 mt-1">
                    <i class="fas fa-clock mr-1"></i><?php echo htmlspecialchars($checkinResult['exhibitor']['slot_name']); ?>
                </p>
                <?php if ($checkinResult['exhibitor']['room_number']): ?>
                <p class="text-sm text-gray-500">
                    <i class="fas fa-map-marker-alt mr-1"></i>
                    <?php echo htmlspecialchars($checkinResult['exhibitor']['building'] . ' - ' . $checkinResult['exhibitor']['room_number']); ?>
                </p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    <?php elseif (empty($token)): ?>
        <!-- Kein Token - manuelle Eingabe -->
        <div class="bg-white rounded-xl border border-gray-100 p-6">
            <h3 class="font-semibold text-gray-800 text-sm mb-4">
                <i class="fas fa-keyboard text-emerald-500 mr-2"></i>
                Code manuell eingeben
            </h3>
            <form method="GET" class="space-y-4">
                <input type="hidden" name="page" value="qr-checkin">
                <div>
                    <input type="text" name="token" placeholder="QR-Code Token eingeben..." 
                           class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-300 focus:border-emerald-400 outline-none text-center text-lg tracking-wider"
                           required>
                </div>
                <button type="submit" class="w-full py-3 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition font-medium">
                    <i class="fas fa-check mr-2"></i>Check-in
                </button>
            </form>
        </div>

        <div class="bg-blue-50 border border-blue-100 rounded-xl p-4">
            <h4 class="font-semibold text-blue-900 text-sm flex items-center mb-2">
                <i class="fas fa-info-circle mr-2"></i>Wie funktioniert's?
            </h4>
            <ul class="space-y-1 text-xs text-blue-800">
                <li><i class="fas fa-check text-blue-500 mr-1"></i> Scanne den QR-Code am Ausstellerstand</li>
                <li><i class="fas fa-check text-blue-500 mr-1"></i> Dein Browser öffnet automatisch die Check-in Seite</li>
                <li><i class="fas fa-check text-blue-500 mr-1"></i> Deine Anwesenheit wird automatisch erfasst</li>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Meine Anwesenheit -->
    <?php
    $stmt = $db->prepare("
        SELECT a.*, e.name as exhibitor_name, t.slot_name, t.slot_number
        FROM attendance a
        JOIN exhibitors e ON a.exhibitor_id = e.id
        JOIN timeslots t ON a.timeslot_id = t.id
        WHERE a.user_id = ?
        ORDER BY t.slot_number ASC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $myAttendance = $stmt->fetchAll();
    ?>
    
    <?php if (!empty($myAttendance)): ?>
    <div class="bg-white rounded-xl border border-gray-100 p-5">
        <h3 class="text-sm font-semibold text-gray-800 mb-3">
            <i class="fas fa-clipboard-check text-emerald-500 mr-2"></i>
            Meine Anwesenheit
        </h3>
        <div class="space-y-2">
            <?php foreach ($myAttendance as $att): ?>
            <div class="flex items-center justify-between p-3 bg-emerald-50 rounded-lg border border-emerald-100">
                <div>
                    <p class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars(html_entity_decode($att['exhibitor_name'], ENT_QUOTES | ENT_HTML5, 'UTF-8')); ?></p>
                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($att['slot_name']); ?></p>
                </div>
                <div class="flex items-center text-emerald-600 text-xs font-medium">
                    <i class="fas fa-check-circle mr-1"></i>
                    <?php echo date('H:i', strtotime($att['checked_in_at'])); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
