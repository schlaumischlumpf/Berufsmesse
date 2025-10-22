<?php
// Meine Anmeldungen Seite

// Registrierungen des Benutzers mit Details laden
$stmt = $db->prepare("
    SELECT r.*, e.name as exhibitor_name, e.short_description, t.slot_name, t.slot_number, t.start_time, t.end_time,
           r.registration_type
    FROM registrations r 
    JOIN exhibitors e ON r.exhibitor_id = e.id 
    JOIN timeslots t ON r.timeslot_id = t.id 
    WHERE r.user_id = ?
    ORDER BY t.slot_number ASC
");
$stmt->execute([$_SESSION['user_id']]);
$myRegistrations = $stmt->fetchAll();

// Handle Abmeldung
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unregister'])) {
    $registrationId = intval($_POST['registration_id']);
    
    // Prüfen ob die Registrierung dem User gehört
    $stmt = $db->prepare("SELECT * FROM registrations WHERE id = ? AND user_id = ?");
    $stmt->execute([$registrationId, $_SESSION['user_id']]);
    $registration = $stmt->fetch();
    
    if ($registration) {
        // Nur manuelle Registrierungen können abgemeldet werden
        if ($registration['registration_type'] === 'manual' && getRegistrationStatus() === 'open') {
            $stmt = $db->prepare("DELETE FROM registrations WHERE id = ?");
            if ($stmt->execute([$registrationId])) {
                $message = ['type' => 'success', 'text' => 'Abmeldung erfolgreich'];
                
                // Registrierungen neu laden
                $stmt = $db->prepare("
                    SELECT r.*, e.name as exhibitor_name, e.short_description, t.slot_name, t.slot_number, t.start_time, t.end_time,
                           r.registration_type
                    FROM registrations r 
                    JOIN exhibitors e ON r.exhibitor_id = e.id 
                    JOIN timeslots t ON r.timeslot_id = t.id 
                    WHERE r.user_id = ?
                    ORDER BY t.slot_number ASC
                ");
                $stmt->execute([$_SESSION['user_id']]);
                $myRegistrations = $stmt->fetchAll();
            } else {
                $message = ['type' => 'error', 'text' => 'Fehler beim Abmelden'];
            }
        } else {
            $message = ['type' => 'error', 'text' => 'Diese Anmeldung kann nicht abgemeldet werden'];
        }
    }
}
?>

<div class="max-w-5xl mx-auto">
    <?php if (isset($message)): ?>
    <div class="mb-6">
        <?php if ($message['type'] === 'success'): ?>
            <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 mr-3"></i>
                    <p class="text-green-700"><?php echo $message['text']; ?></p>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                    <p class="text-red-700"><?php echo $message['text']; ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Übersicht Card -->
    <div class="bg-gradient-to-r from-purple-600 to-indigo-600 rounded-xl shadow-lg p-6 mb-6 text-white">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h2 class="text-2xl font-bold mb-2">Ihre Anmeldungen</h2>
                <p class="text-purple-100">Übersicht aller gebuchten Messetermine</p>
            </div>
            <div class="bg-white/20 backdrop-blur-sm rounded-lg px-6 py-4">
                <div class="text-3xl font-bold"><?php echo count($myRegistrations); ?></div>
                <div class="text-sm text-purple-100">Termine</div>
            </div>
        </div>
    </div>

    <?php if (empty($myRegistrations)): ?>
    <!-- Keine Anmeldungen -->
    <div class="bg-white rounded-xl shadow-md p-12 text-center">
        <i class="fas fa-calendar-times text-6xl text-gray-300 mb-4"></i>
        <h3 class="text-xl font-semibold text-gray-700 mb-2">Noch keine Anmeldungen</h3>
        <p class="text-gray-500 mb-6">Sie haben sich noch für keinen Aussteller eingeschrieben.</p>
        <a href="?page=registration" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-lg hover:from-purple-700 hover:to-indigo-700 transition font-semibold">
            <i class="fas fa-plus-circle mr-2"></i>
            Jetzt einschreiben
        </a>
    </div>
    <?php else: ?>
    <!-- Registrierungen nach Zeitslot -->
    <div class="space-y-6">
        <?php 
        // Nach Slot gruppieren
        $slotGroups = [];
        foreach ($myRegistrations as $reg) {
            $slotGroups[$reg['slot_number']][] = $reg;
        }
        
        foreach ($slotGroups as $slotNumber => $registrations):
            $slot = $registrations[0];
        ?>
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <!-- Slot Header -->
            <div class="bg-gradient-to-r from-purple-500 to-indigo-500 text-white px-6 py-4">
                <div class="flex items-center justify-between flex-wrap gap-2">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-clock text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold"><?php echo htmlspecialchars($slot['slot_name']); ?></h3>
                            <p class="text-sm text-purple-100">
                                <?php echo date('H:i', strtotime($slot['start_time'])); ?> - 
                                <?php echo date('H:i', strtotime($slot['end_time'])); ?> Uhr
                            </p>
                        </div>
                    </div>
                    <div class="bg-white/20 backdrop-blur-sm rounded-lg px-4 py-2">
                        <span class="text-sm font-semibold"><?php echo count($registrations); ?> Anmeldung(en)</span>
                    </div>
                </div>
            </div>

            <!-- Slot Content -->
            <div class="p-6 space-y-4">
                <?php foreach ($registrations as $reg): ?>
                <div class="border border-gray-200 rounded-lg p-4 hover:border-purple-300 hover:shadow-md transition">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-2">
                                <h4 class="text-lg font-semibold text-gray-800">
                                    <?php echo htmlspecialchars($reg['exhibitor_name']); ?>
                                </h4>
                                <?php if ($reg['registration_type'] === 'automatic'): ?>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
                                        <i class="fas fa-robot mr-1"></i>Automatisch zugeteilt
                                    </span>
                                <?php endif; ?>
                            </div>
                            <p class="text-sm text-gray-600 mb-3">
                                <?php echo htmlspecialchars($reg['short_description'] ?? ''); ?>
                            </p>
                            <div class="flex flex-wrap gap-2 text-xs text-gray-500">
                                <span class="flex items-center">
                                    <i class="fas fa-calendar-alt mr-1"></i>
                                    Angemeldet am <?php echo formatDateTime($reg['registered_at']); ?>
                                </span>
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-2">
                            <button onclick="openExhibitorModal(<?php echo $reg['exhibitor_id']; ?>)" 
                                    class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition whitespace-nowrap">
                                <i class="fas fa-info-circle mr-2"></i>Details
                            </button>
                            
                            <?php if ($reg['registration_type'] === 'manual' && getRegistrationStatus() === 'open'): ?>
                            <form method="POST" class="inline" onsubmit="return confirm('Möchten Sie sich wirklich abmelden?')">
                                <input type="hidden" name="registration_id" value="<?php echo $reg['id']; ?>">
                                <button type="submit" 
                                        name="unregister"
                                        class="px-4 py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition whitespace-nowrap">
                                    <i class="fas fa-times-circle mr-2"></i>Abmelden
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Info Box -->
    <div class="mt-6 bg-blue-50 border border-blue-200 rounded-xl p-6">
        <h4 class="font-semibold text-blue-900 mb-3 flex items-center">
            <i class="fas fa-info-circle mr-2"></i>
            Wichtige Hinweise
        </h4>
        <ul class="space-y-2 text-sm text-blue-800">
            <li class="flex items-start">
                <i class="fas fa-check text-blue-600 mr-2 mt-1"></i>
                <span>Automatisch zugeteilte Anmeldungen können nicht abgemeldet werden.</span>
            </li>
            <li class="flex items-start">
                <i class="fas fa-check text-blue-600 mr-2 mt-1"></i>
                <span>Abmeldungen sind nur während des Einschreibungszeitraums möglich.</span>
            </li>
            <li class="flex items-start">
                <i class="fas fa-check text-blue-600 mr-2 mt-1"></i>
                <span>Bitte erscheinen Sie pünktlich zu Ihren gebuchten Terminen.</span>
            </li>
            <li class="flex items-start">
                <i class="fas fa-check text-blue-600 mr-2 mt-1"></i>
                <span>Notieren Sie sich die Uhrzeiten der einzelnen Zeitslots.</span>
            </li>
        </ul>
    </div>
    <?php endif; ?>
</div>
