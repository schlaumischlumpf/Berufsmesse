<?php
// Admin Einstellungen

// Handle Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $regStart = $_POST['registration_start'];
    $regEnd = $_POST['registration_end'];
    $eventDate = $_POST['event_date'];
    $maxReg = intval($_POST['max_registrations_per_student']);
    
    updateSetting('registration_start', $regStart);
    updateSetting('registration_end', $regEnd);
    updateSetting('event_date', $eventDate);
    updateSetting('max_registrations_per_student', $maxReg);
    
    $message = ['type' => 'success', 'text' => 'Einstellungen erfolgreich gespeichert'];
}

// Handle QR Code URL Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_qr_url'])) {
    $qrUrl = sanitize($_POST['qr_url']);
    updateSetting('qr_code_url', $qrUrl);
    $message = ['type' => 'success', 'text' => 'QR-Code URL erfolgreich gespeichert'];
}

// Aktuelle Einstellungen laden
$currentSettings = [
    'registration_start' => getSetting('registration_start'),
    'registration_end' => getSetting('registration_end'),
    'event_date' => getSetting('event_date'),
    'max_registrations_per_student' => getSetting('max_registrations_per_student', 3),
    'qr_code_url' => getSetting('qr_code_url', 'http://localhost' . BASE_URL)
];
?>

<div class="max-w-4xl mx-auto space-y-6">
    <?php if (isset($message)): ?>
    <div class="animate-pulse">
        <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-green-500 mr-3"></i>
                <p class="text-green-700"><?php echo $message['text']; ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Settings Form -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-6 py-4">
            <h3 class="text-2xl font-bold flex items-center">
                <i class="fas fa-cog mr-3"></i>
                System-Einstellungen
            </h3>
        </div>

        <form method="POST" class="p-6 space-y-6">
            <!-- Registrierungszeitraum -->
            <div class="border-b border-gray-200 pb-6">
                <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-calendar-alt text-purple-600 mr-3"></i>
                    Einschreibezeitraum
                </h4>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Einschreibebeginn *
                        </label>
                        <input type="datetime-local" 
                               name="registration_start" 
                               value="<?php echo date('Y-m-d\TH:i', strtotime($currentSettings['registration_start'])); ?>"
                               required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <p class="text-xs text-gray-500 mt-1">
                            <i class="fas fa-info-circle mr-1"></i>
                            Ab diesem Zeitpunkt können sich Schüler einschreiben
                        </p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Einschreibeschluss *
                        </label>
                        <input type="datetime-local" 
                               name="registration_end" 
                               value="<?php echo date('Y-m-d\TH:i', strtotime($currentSettings['registration_end'])); ?>"
                               required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <p class="text-xs text-gray-500 mt-1">
                            <i class="fas fa-info-circle mr-1"></i>
                            Nach diesem Zeitpunkt erfolgt automatische Zuteilung
                        </p>
                    </div>
                </div>

                <!-- Status Preview -->
                <div class="mt-4 p-4 bg-blue-50 rounded-lg border border-blue-200">
                    <p class="text-sm text-blue-800">
                        <i class="fas fa-info-circle mr-2"></i>
                        <strong>Aktueller Status:</strong>
                        <?php 
                        $status = getRegistrationStatus();
                        if ($status === 'open') {
                            echo '<span class="text-green-600 font-semibold">Einschreibung läuft</span>';
                        } elseif ($status === 'upcoming') {
                            echo '<span class="text-yellow-600 font-semibold">Noch nicht gestartet</span>';
                        } else {
                            echo '<span class="text-red-600 font-semibold">Geschlossen</span>';
                        }
                        ?>
                    </p>
                </div>
            </div>

            <!-- Event Datum -->
            <div class="border-b border-gray-200 pb-6">
                <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-calendar-check text-green-600 mr-3"></i>
                    Veranstaltungsdatum
                </h4>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Datum der Berufsmesse *
                    </label>
                    <input type="date" 
                           name="event_date" 
                           value="<?php echo $currentSettings['event_date']; ?>"
                           required
                           class="w-full md:w-1/2 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <p class="text-xs text-gray-500 mt-1">
                        <i class="fas fa-info-circle mr-1"></i>
                        Das Datum, an dem die Berufsmesse stattfindet
                    </p>
                </div>
            </div>

            <!-- Einschreibungsparameter -->
            <div class="pb-6">
                <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-sliders-h text-blue-600 mr-3"></i>
                    Einschreibungsparameter
                </h4>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Maximale Einschreibungen pro Schüler *
                    </label>
                    <div class="flex items-center space-x-4">
                        <input type="number" 
                               name="max_registrations_per_student" 
                               value="<?php echo $currentSettings['max_registrations_per_student']; ?>"
                               min="1" 
                               max="3"
                               required
                               class="w-32 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-center font-bold text-2xl">
                        <span class="text-gray-600">Aussteller (entspricht den 3 Zeitslots)</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">
                        <i class="fas fa-info-circle mr-1"></i>
                        Standard: 3 (ein Aussteller pro Zeitslot). Empfohlen wird dieser Wert beizubehalten.
                    </p>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="flex justify-end pt-4 border-t border-gray-200">
                <button type="submit" 
                        name="save_settings"
                        class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-8 py-3 rounded-lg hover:from-purple-700 hover:to-indigo-700 transition font-semibold shadow-lg">
                    <i class="fas fa-save mr-2"></i>Einstellungen speichern
                </button>
            </div>
        </form>
    </div>

    <!-- QR Code Generator -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="bg-gradient-to-r from-green-600 to-teal-600 text-white px-6 py-4">
            <h3 class="text-2xl font-bold flex items-center">
                <i class="fas fa-qrcode mr-3"></i>
                QR-Code Generator
            </h3>
        </div>

        <div class="p-6 space-y-6">
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        URL für QR-Code *
                    </label>
                    <input type="url" 
                           name="qr_url" 
                           value="<?php echo htmlspecialchars($currentSettings['qr_code_url']); ?>"
                           required
                           placeholder="https://beispiel.de/berufsmesse"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                    <p class="text-xs text-gray-500 mt-1">
                        <i class="fas fa-info-circle mr-1"></i>
                        Diese URL wird im QR-Code eingebettet (z.B. Anmeldeseite vor Ort)
                    </p>
                </div>
                
                <div class="flex justify-end">
                    <button type="submit" 
                            name="save_qr_url"
                            class="bg-gradient-to-r from-green-600 to-teal-600 text-white px-6 py-2 rounded-lg hover:from-green-700 hover:to-teal-700 transition font-semibold">
                        <i class="fas fa-save mr-2"></i>URL speichern
                    </button>
                </div>
            </form>

            <div class="border-t border-gray-200 pt-6">
                <div class="flex flex-col md:flex-row gap-6 items-center">
                    <div class="flex-1">
                        <h4 class="font-semibold text-gray-800 mb-3">QR-Code Vorschau</h4>
                        <p class="text-sm text-gray-600 mb-4">
                            Scannen Sie diesen QR-Code mit einem Smartphone, um zur konfigurierten URL zu gelangen.
                        </p>
                        <div class="bg-gray-50 p-4 rounded-lg border-2 border-gray-200 inline-block">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?php echo urlencode($currentSettings['qr_code_url']); ?>" 
                                 alt="QR Code" 
                                 class="w-48 h-48"
                                 id="qrCodePreview">
                        </div>
                    </div>
                    
                    <div class="flex-1">
                        <h4 class="font-semibold text-gray-800 mb-3">Aktionen</h4>
                        <div class="space-y-3">
                            <a href="https://api.qrserver.com/v1/create-qr-code/?size=600x600&data=<?php echo urlencode($currentSettings['qr_code_url']); ?>" 
                               download="berufsmesse-qrcode.png"
                               target="_blank"
                               class="block w-full bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition text-center font-semibold">
                                <i class="fas fa-download mr-2"></i>QR-Code herunterladen (600x600)
                            </a>
                            
                            <a href="https://api.qrserver.com/v1/create-qr-code/?size=1200x1200&data=<?php echo urlencode($currentSettings['qr_code_url']); ?>" 
                               download="berufsmesse-qrcode-hd.png"
                               target="_blank"
                               class="block w-full bg-indigo-600 text-white px-6 py-3 rounded-lg hover:bg-indigo-700 transition text-center font-semibold">
                                <i class="fas fa-download mr-2"></i>QR-Code herunterladen (HD 1200x1200)
                            </a>
                            
                            <button onclick="window.print()" 
                                    class="block w-full bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 transition font-semibold">
                                <i class="fas fa-print mr-2"></i>QR-Code drucken
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- System Information -->
    <div class="bg-white rounded-xl shadow-md p-6">
        <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
            <i class="fas fa-info-circle text-blue-600 mr-3"></i>
            System-Information
        </h4>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div class="p-4 bg-gray-50 rounded-lg">
                <p class="text-gray-600 mb-1">PHP Version</p>
                <p class="font-semibold text-gray-800"><?php echo phpversion(); ?></p>
            </div>
            
            <div class="p-4 bg-gray-50 rounded-lg">
                <p class="text-gray-600 mb-1">Datenbank</p>
                <p class="font-semibold text-gray-800"><?php echo DB_NAME; ?></p>
            </div>
            
            <div class="p-4 bg-gray-50 rounded-lg">
                <p class="text-gray-600 mb-1">Max. Upload-Größe</p>
                <p class="font-semibold text-gray-800"><?php echo round(MAX_FILE_SIZE / 1048576, 1); ?> MB</p>
            </div>
            
            <div class="p-4 bg-gray-50 rounded-lg">
                <p class="text-gray-600 mb-1">Upload-Verzeichnis</p>
                <p class="font-semibold text-gray-800 text-xs"><?php echo UPLOAD_DIR; ?></p>
            </div>
        </div>
    </div>

    <!-- Warning Box -->
    <div class="bg-yellow-50 border-l-4 border-yellow-500 p-6 rounded-lg">
        <h4 class="font-semibold text-yellow-900 mb-3 flex items-center">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            Wichtige Hinweise
        </h4>
        <ul class="space-y-2 text-sm text-yellow-800">
            <li class="flex items-start">
                <i class="fas fa-check text-yellow-600 mr-2 mt-1"></i>
                <span>Änderungen am Einschreibezeitraum wirken sich sofort aus.</span>
            </li>
            <li class="flex items-start">
                <i class="fas fa-check text-yellow-600 mr-2 mt-1"></i>
                <span>Nach Ablauf der Einschreibefrist sollten Sie die automatische Zuteilung durchführen.</span>
            </li>
            <li class="flex items-start">
                <i class="fas fa-check text-yellow-600 mr-2 mt-1"></i>
                <span>Stellen Sie sicher, dass genügend Ausstellerplätze für alle Schüler vorhanden sind.</span>
            </li>
            <li class="flex items-start">
                <i class="fas fa-check text-yellow-600 mr-2 mt-1"></i>
                <span>Die maximale Anzahl an Einschreibungen sollte 3 sein (ein Aussteller pro Zeitslot).</span>
            </li>
        </ul>
    </div>
</div>
