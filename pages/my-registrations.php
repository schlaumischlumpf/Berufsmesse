<?php
// Meine Anmeldungen - Premium Dashboard Design

// Registrierungen des Benutzers mit Details laden
$stmt = $db->prepare("
    SELECT r.*, e.name as exhibitor_name, e.short_description, t.slot_name, t.slot_number, t.start_time, t.end_time,
           r.registration_type, rm.room_number, rm.room_name, rm.building, rm.floor
    FROM registrations r 
    JOIN exhibitors e ON r.exhibitor_id = e.id 
    JOIN timeslots t ON r.timeslot_id = t.id 
    LEFT JOIN rooms rm ON e.room_id = rm.id
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
                           r.registration_type, rm.room_number, rm.room_name, rm.building, rm.floor
                    FROM registrations r 
                    JOIN exhibitors e ON r.exhibitor_id = e.id 
                    JOIN timeslots t ON r.timeslot_id = t.id 
                    LEFT JOIN rooms rm ON e.room_id = rm.id
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

$maxRegistrations = intval(getSetting('max_registrations_per_student', 3));
$progress = ($maxRegistrations > 0) ? (count($myRegistrations) / $maxRegistrations * 100) : 0;
?>

<div class="max-w-5xl mx-auto space-y-8">
    <!-- Hero Header -->
    <div class="relative overflow-hidden bg-gradient-to-br from-purple-600 via-indigo-600 to-blue-600 rounded-3xl p-8 text-white shadow-2xl">
        <div class="absolute inset-0 bg-black/5"></div>
        <div class="absolute top-0 right-0 w-96 h-96 bg-white/10 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2"></div>
        <div class="absolute bottom-0 left-0 w-72 h-72 bg-white/10 rounded-full blur-3xl translate-y-1/2 -translate-x-1/2"></div>
        
        <div class="relative">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                <div>
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-16 h-16 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center shadow-lg">
                            <i class="fas fa-bookmark text-3xl"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl font-extrabold font-display">Meine Anmeldungen</h1>
                            <p class="text-white/80">Übersicht aller gebuchten Messetermine</p>
                        </div>
                    </div>
                </div>
                
                <div class="flex items-center gap-4">
                    <!-- Stats Cards -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-4 text-center min-w-[100px]">
                            <div class="text-3xl font-bold"><?php echo count($myRegistrations); ?></div>
                            <div class="text-sm text-white/70">Termine</div>
                        </div>
                        <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-4 text-center min-w-[100px]">
                            <div class="text-3xl font-bold"><?php echo $maxRegistrations - count($myRegistrations); ?></div>
                            <div class="text-sm text-white/70">Verfügbar</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Progress Bar -->
            <div class="mt-6 p-4 bg-white/10 backdrop-blur-sm rounded-2xl">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-sm font-medium text-white/90">Buchungsfortschritt</span>
                    <span class="px-3 py-1 bg-white/20 rounded-full text-sm font-bold">
                        <?php echo count($myRegistrations); ?> / <?php echo $maxRegistrations; ?>
                    </span>
                </div>
                <div class="h-3 bg-white/20 rounded-full overflow-hidden">
                    <div class="h-full bg-white rounded-full transition-all duration-700 ease-out" style="width: <?php echo min($progress, 100); ?>%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Message Alert -->
    <?php if (isset($message)): ?>
    <div class="transform transition-all duration-500 animate-slideUp">
        <?php if ($message['type'] === 'success'): ?>
            <div class="bg-gradient-to-r from-primary-50 to-emerald-50 border border-primary-200 p-5 rounded-2xl shadow-lg">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-primary-100 rounded-xl flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-check-circle text-primary-500 text-xl"></i>
                    </div>
                    <div>
                        <h4 class="font-bold text-primary-800 mb-1">Erfolg!</h4>
                        <p class="text-primary-700 text-sm"><?php echo $message['text']; ?></p>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-gradient-to-r from-red-50 to-rose-50 border border-red-200 p-5 rounded-2xl shadow-lg">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-exclamation-circle text-red-500 text-xl"></i>
                    </div>
                    <div>
                        <h4 class="font-bold text-red-800 mb-1">Fehler</h4>
                        <p class="text-red-700 text-sm"><?php echo $message['text']; ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($myRegistrations)): ?>
    <!-- Empty State -->
    <div class="bg-white rounded-3xl shadow-xl border border-gray-100 p-12 text-center">
        <div class="w-24 h-24 bg-gradient-to-br from-gray-100 to-gray-200 rounded-full flex items-center justify-center mx-auto mb-6">
            <i class="fas fa-calendar-times text-5xl text-gray-300"></i>
        </div>
        <h3 class="text-2xl font-bold text-gray-800 mb-3">Noch keine Anmeldungen</h3>
        <p class="text-gray-500 mb-8 max-w-md mx-auto">Sie haben sich noch für keinen Aussteller eingeschrieben. Starten Sie jetzt und entdecken Sie spannende Unternehmen!</p>
        <a href="?page=registration" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-primary-500 to-emerald-500 text-white rounded-2xl hover:shadow-lg hover:scale-105 transition-all duration-300 font-semibold">
            <i class="fas fa-plus-circle mr-2"></i>
            Jetzt einschreiben
        </a>
    </div>
    <?php else: ?>
    <!-- Registrations List -->
    <div class="space-y-6">
        <?php 
        // Nach Slot gruppieren
        $slotGroups = [];
        foreach ($myRegistrations as $reg) {
            $slotGroups[$reg['slot_number']][] = $reg;
        }
        
        $slotGradients = [
            1 => 'from-blue-500 to-cyan-500',
            2 => 'from-purple-500 to-pink-500',
            3 => 'from-primary-500 to-emerald-500',
            4 => 'from-orange-500 to-amber-500',
            5 => 'from-rose-500 to-red-500'
        ];
        
        foreach ($slotGroups as $slotNumber => $registrations):
            $slot = $registrations[0];
            $gradient = $slotGradients[$slotNumber] ?? 'from-gray-500 to-gray-600';
        ?>
        <div class="bg-white rounded-3xl shadow-xl border border-gray-100 overflow-hidden group hover:shadow-2xl transition-all duration-300">
            <!-- Slot Header -->
            <div class="relative bg-gradient-to-r <?php echo $gradient; ?> p-5">
                <div class="absolute inset-0 bg-black/5"></div>
                <div class="relative flex items-center justify-between flex-wrap gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center shadow-lg">
                            <i class="fas fa-clock text-white text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-white text-lg"><?php echo htmlspecialchars($slot['slot_name']); ?></h3>
                            <p class="text-white/80 text-sm flex items-center gap-2">
                                <i class="fas fa-hourglass-half"></i>
                                <?php echo date('H:i', strtotime($slot['start_time'])); ?> - 
                                <?php echo date('H:i', strtotime($slot['end_time'])); ?> Uhr
                            </p>
                        </div>
                    </div>
                    <span class="px-4 py-2 bg-white/20 backdrop-blur-sm text-white text-sm font-semibold rounded-xl">
                        <?php echo count($registrations); ?> Termin(e)
                    </span>
                </div>
            </div>

            <!-- Slot Content -->
            <div class="p-6 space-y-4">
                <?php foreach ($registrations as $index => $reg): ?>
                <div class="relative bg-gradient-to-br from-white to-gray-50 rounded-2xl border border-gray-100 overflow-hidden hover:border-primary-200 hover:shadow-lg transition-all duration-300" style="animation-delay: <?php echo $index * 100; ?>ms;">
                    <!-- Gradient Accent -->
                    <div class="absolute left-0 top-0 bottom-0 w-1.5 bg-gradient-to-b <?php echo $gradient; ?> rounded-l-2xl"></div>
                    
                    <div class="p-5 pl-6">
                        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                            <div class="flex items-start gap-4 flex-1">
                                <!-- Company Icon -->
                                <div class="w-14 h-14 bg-gradient-to-br <?php echo $gradient; ?> rounded-2xl flex items-center justify-center shadow-lg flex-shrink-0">
                                    <i class="fas fa-building text-white text-xl"></i>
                                </div>
                                
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1 flex-wrap">
                                        <h4 class="font-bold text-gray-800 text-lg">
                                            <?php echo htmlspecialchars($reg['exhibitor_name']); ?>
                                        </h4>
                                        <?php if ($reg['registration_type'] === 'automatic'): ?>
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-100">
                                                <i class="fas fa-robot mr-1"></i>Auto-Zuweisung
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-primary-50 text-primary-700 border border-primary-100">
                                                <i class="fas fa-hand-pointer mr-1"></i>Manuelle Wahl
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <p class="text-sm text-gray-500 mb-3">
                                        <?php echo htmlspecialchars($reg['short_description'] ?? 'Keine Beschreibung verfügbar'); ?>
                                    </p>
                                    
                                    <!-- Location Info -->
                                    <div class="flex flex-wrap items-center gap-2">
                                        <?php if ($reg['room_name'] || $reg['room_number']): ?>
                                        <span class="inline-flex items-center px-3 py-1.5 rounded-xl bg-gray-100 text-gray-700 text-xs font-medium">
                                            <i class="fas fa-map-marker-alt text-red-400 mr-1.5"></i>
                                            <?php echo htmlspecialchars($reg['room_name'] ?? $reg['room_number']); ?>
                                        </span>
                                        <?php endif; ?>
                                        <?php if ($reg['building']): ?>
                                        <span class="inline-flex items-center px-3 py-1.5 rounded-xl bg-gray-100 text-gray-700 text-xs font-medium">
                                            <i class="fas fa-building text-blue-400 mr-1.5"></i>
                                            <?php echo htmlspecialchars($reg['building']); ?>
                                        </span>
                                        <?php endif; ?>
                                        <?php if ($reg['floor']): ?>
                                        <span class="inline-flex items-center px-3 py-1.5 rounded-xl bg-gray-100 text-gray-700 text-xs font-medium">
                                            <i class="fas fa-layer-group text-purple-400 mr-1.5"></i>
                                            <?php echo htmlspecialchars($reg['floor']); ?>. Etage
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex items-center gap-3 lg:flex-shrink-0">
                                <button onclick="openExhibitorModal(<?php echo $reg['exhibitor_id']; ?>)" 
                                        class="px-4 py-2.5 bg-gray-100 text-gray-600 rounded-xl hover:bg-gray-200 transition-all duration-200 text-sm font-medium flex items-center gap-2">
                                    <i class="fas fa-info-circle"></i>
                                    <span>Details</span>
                                </button>
                                
                                <?php if ($reg['registration_type'] === 'manual' && getRegistrationStatus() === 'open'): ?>
                                <form method="POST" class="inline" onsubmit="return confirm('Möchten Sie sich wirklich abmelden?')">
                                    <input type="hidden" name="registration_id" value="<?php echo $reg['id']; ?>">
                                    <button type="submit" 
                                            name="unregister"
                                            class="px-4 py-2.5 bg-red-50 text-red-600 rounded-xl hover:bg-red-100 hover:shadow-md transition-all duration-200 text-sm font-medium flex items-center gap-2">
                                        <i class="fas fa-times-circle"></i>
                                        <span>Abmelden</span>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Quick Actions -->
    <div class="flex flex-wrap gap-4">
        <a href="?page=schedule" class="flex-1 min-w-[200px] bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-100 rounded-2xl p-5 hover:shadow-lg transition-all duration-300 group">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                    <i class="fas fa-calendar-alt text-blue-500 text-xl"></i>
                </div>
                <div>
                    <h4 class="font-bold text-blue-900">Zeitplan anzeigen</h4>
                    <p class="text-sm text-blue-600">Vollständiger Tagesablauf</p>
                </div>
            </div>
        </a>
        
        <?php if (count($myRegistrations) < $maxRegistrations): ?>
        <a href="?page=registration" class="flex-1 min-w-[200px] bg-gradient-to-br from-primary-50 to-emerald-50 border border-primary-100 rounded-2xl p-5 hover:shadow-lg transition-all duration-300 group">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-primary-100 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                    <i class="fas fa-plus-circle text-primary-500 text-xl"></i>
                </div>
                <div>
                    <h4 class="font-bold text-primary-900">Weitere Anmeldung</h4>
                    <p class="text-sm text-primary-600">Noch <?php echo $maxRegistrations - count($myRegistrations); ?> Plätze frei</p>
                </div>
            </div>
        </a>
        <?php endif; ?>
    </div>

    <!-- Info Card -->
    <div class="bg-gradient-to-br from-amber-50 to-orange-50 border border-amber-100 rounded-3xl p-6 shadow-lg">
        <h4 class="font-bold text-amber-900 mb-4 flex items-center gap-3 text-lg">
            <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-lightbulb text-amber-500"></i>
            </div>
            Wichtige Hinweise
        </h4>
        <div class="grid md:grid-cols-2 gap-4">
            <div class="flex items-start gap-3 bg-white/60 backdrop-blur-sm rounded-2xl p-4 border border-amber-100">
                <div class="w-8 h-8 bg-amber-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-robot text-amber-600 text-sm"></i>
                </div>
                <p class="text-sm text-amber-800">Automatisch zugeteilte Anmeldungen können nicht abgemeldet werden.</p>
            </div>
            <div class="flex items-start gap-3 bg-white/60 backdrop-blur-sm rounded-2xl p-4 border border-amber-100">
                <div class="w-8 h-8 bg-amber-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-clock text-amber-600 text-sm"></i>
                </div>
                <p class="text-sm text-amber-800">Abmeldungen sind nur während des Einschreibungszeitraums möglich.</p>
            </div>
            <div class="flex items-start gap-3 bg-white/60 backdrop-blur-sm rounded-2xl p-4 border border-amber-100">
                <div class="w-8 h-8 bg-amber-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-user-check text-amber-600 text-sm"></i>
                </div>
                <p class="text-sm text-amber-800">Bitte erscheinen Sie pünktlich zu Ihren gebuchten Terminen.</p>
            </div>
            <div class="flex items-start gap-3 bg-white/60 backdrop-blur-sm rounded-2xl p-4 border border-amber-100">
                <div class="w-8 h-8 bg-amber-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-map text-amber-600 text-sm"></i>
                </div>
                <p class="text-sm text-amber-800">Notieren Sie sich die Räume und Uhrzeiten der Zeitslots.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-slideUp {
    animation: slideUp 0.5s ease-out;
}
</style>
