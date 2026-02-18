<?php
// Admin Einstellungen

// Handle Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    if (!isAdmin() && !hasPermission('einstellungen_bearbeiten')) {
        die('Keine Berechtigung');
    }
    $regStart = $_POST['registration_start'];
    $regEnd = $_POST['registration_end'];
    $eventDate = $_POST['event_date'];
    $maxReg = intval($_POST['max_registrations_per_student']);
    
    updateSetting('registration_start', $regStart);
    updateSetting('registration_end', $regEnd);
    updateSetting('event_date', $eventDate);
    updateSetting('max_registrations_per_student', $maxReg);
    
    // Auto-Close Einstellung (Issue #12)
    $autoClose = isset($_POST['auto_close_registration']) ? '1' : '0';
    updateSetting('auto_close_registration', $autoClose);
    
    $message = ['type' => 'success', 'text' => 'Einstellungen erfolgreich gespeichert'];
}

// Handle QR Code URL Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_qr_url'])) {
    if (!isAdmin() && !hasPermission('einstellungen_bearbeiten')) {
        die('Keine Berechtigung');
    }
    $qrUrl = sanitize($_POST['qr_url']);
    updateSetting('qr_code_url', $qrUrl);
    $message = ['type' => 'success', 'text' => 'QR-Code URL erfolgreich gespeichert'];
}

// Handle Industry Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isAdmin() || hasPermission('einstellungen_bearbeiten'))) {
    if (isset($_POST['add_industry'])) {
        $indName = trim($_POST['industry_name'] ?? '');
        $indOrder = intval($_POST['industry_sort_order'] ?? 0);
        if ($indName === '') {
            $industryMessage = ['type' => 'error', 'text' => 'Branchenname darf nicht leer sein'];
        } elseif (mb_strlen($indName) > 100) {
            $industryMessage = ['type' => 'error', 'text' => 'Branchenname darf maximal 100 Zeichen lang sein'];
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO industries (name, sort_order) VALUES (?, ?)");
                $stmt->execute([$indName, $indOrder]);
                logAuditAction('branche_erstellt', "Branche '$indName' erstellt");
                $industryMessage = ['type' => 'success', 'text' => "Branche '$indName' erfolgreich angelegt"];
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $industryMessage = ['type' => 'error', 'text' => "Branche '$indName' existiert bereits"];
                } else {
                    $industryMessage = ['type' => 'error', 'text' => 'Fehler beim Anlegen der Branche'];
                }
            }
        }
    } elseif (isset($_POST['edit_industry'])) {
        $indId = intval($_POST['industry_id']);
        $indName = trim($_POST['industry_name'] ?? '');
        $indOrder = intval($_POST['industry_sort_order'] ?? 0);
        if ($indName === '') {
            $industryMessage = ['type' => 'error', 'text' => 'Branchenname darf nicht leer sein'];
        } elseif (mb_strlen($indName) > 100) {
            $industryMessage = ['type' => 'error', 'text' => 'Branchenname darf maximal 100 Zeichen lang sein'];
        } else {
            try {
                $stmt = $db->prepare("UPDATE industries SET name = ?, sort_order = ? WHERE id = ?");
                $stmt->execute([$indName, $indOrder, $indId]);
                logAuditAction('branche_bearbeitet', "Branche ID $indId zu '$indName' umbenannt");
                $industryMessage = ['type' => 'success', 'text' => "Branche erfolgreich aktualisiert"];
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $industryMessage = ['type' => 'error', 'text' => "Eine Branche mit diesem Namen existiert bereits"];
                } else {
                    $industryMessage = ['type' => 'error', 'text' => 'Fehler beim Aktualisieren der Branche'];
                }
            }
        }
    } elseif (isset($_POST['delete_industry'])) {
        $indId = intval($_POST['industry_id']);
        // Check if any exhibitor uses this industry
        $stmt = $db->prepare("SELECT COUNT(*) FROM exhibitors WHERE category = (SELECT name FROM industries WHERE id = ?)");
        $stmt->execute([$indId]);
        $usageCount = $stmt->fetchColumn();
        if ($usageCount > 0) {
            $industryMessage = ['type' => 'error', 'text' => "Diese Branche kann nicht gelöscht werden, da noch $usageCount Aussteller dieser Branche zugeordnet sind"];
        } else {
            $stmt = $db->prepare("SELECT name FROM industries WHERE id = ?");
            $stmt->execute([$indId]);
            $indRow = $stmt->fetch();
            $stmt = $db->prepare("DELETE FROM industries WHERE id = ?");
            $stmt->execute([$indId]);
            logAuditAction('branche_geloescht', "Branche '{$indRow['name']}' (ID: $indId) gelöscht");
            $industryMessage = ['type' => 'success', 'text' => "Branche erfolgreich gelöscht"];
        }
    }
}

// Aktuelle Einstellungen laden
$currentSettings = [
    'registration_start' => getSetting('registration_start'),
    'registration_end' => getSetting('registration_end'),
    'event_date' => getSetting('event_date'),
    'max_registrations_per_student' => getSetting('max_registrations_per_student', 3),
    'auto_close_registration' => getSetting('auto_close_registration', '1'),
    'qr_code_url' => getSetting('qr_code_url', 'https://localhost' . BASE_URL)
];

// Branchen laden
$allIndustries = getIndustries();
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
    <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                <i class="fas fa-cog text-emerald-500 mr-2"></i>
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
                
                <!-- Auto-Close Toggle (Issue #12) -->
                <div class="mt-6">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" 
                               name="auto_close_registration" 
                               value="1"
                               <?php echo $currentSettings['auto_close_registration'] === '1' ? 'checked' : ''; ?>
                               class="w-5 h-5 text-emerald-500 rounded border-gray-300 focus:ring-emerald-400">
                        <div>
                            <span class="text-sm font-semibold text-gray-700">Einschreibung nach Zuteilung automatisch schliessen</span>
                            <p class="text-xs text-gray-500 mt-0.5">
                                <i class="fas fa-info-circle mr-1"></i>
                                Nach der automatischen Zuteilung wird die Einschreibung automatisch geschlossen. Nur Admins können danach noch Änderungen vornehmen.
                            </p>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="flex justify-end pt-4 border-t border-gray-100">
                <button type="submit" 
                        name="save_settings"
                        class="bg-emerald-500 text-white px-6 py-2.5 rounded-lg hover:bg-emerald-600 transition font-medium">
                    <i class="fas fa-save mr-2"></i>Speichern
                </button>
            </div>
        </form>
    </div>

    <!-- QR Code Generator -->
    <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                <i class="fas fa-qrcode text-emerald-500 mr-2"></i>
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
                            Scanne diesen QR-Code mit einem Smartphone, um zur konfigurierten URL zu gelangen.
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
                <span>Nach Ablauf der Einschreibefrist solltest Du die automatische Zuteilung durchführen.</span>
            </li>
            <li class="flex items-start">
                <i class="fas fa-check text-yellow-600 mr-2 mt-1"></i>
                <span>Stelle sicher, dass genügend Ausstellerplätze für alle Schüler vorhanden sind.</span>
            </li>
            <li class="flex items-start">
                <i class="fas fa-check text-yellow-600 mr-2 mt-1"></i>
                <span>Die maximale Anzahl an Einschreibungen sollte 3 sein (ein Aussteller pro Zeitslot).</span>
            </li>
        </ul>
    </div>

    <!-- Branchen-Verwaltung -->
    <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                <i class="fas fa-industry text-emerald-500 mr-2"></i>
                Branchen-Verwaltung
            </h3>
            <button onclick="document.getElementById('addIndustryForm').classList.toggle('hidden')"
                    class="bg-emerald-500 text-white px-4 py-2 rounded-lg text-sm hover:bg-emerald-600 transition">
                <i class="fas fa-plus mr-1"></i>Neue Branche
            </button>
        </div>

        <div class="p-6 space-y-4">
            <?php if (isset($industryMessage)): ?>
            <div class="<?php echo $industryMessage['type'] === 'success' ? 'bg-emerald-50 border border-emerald-200' : 'bg-red-50 border border-red-200'; ?> p-4 rounded-lg">
                <div class="flex items-center">
                    <i class="fas <?php echo $industryMessage['type'] === 'success' ? 'fa-check-circle text-emerald-500' : 'fa-exclamation-circle text-red-500'; ?> mr-3"></i>
                    <p class="<?php echo $industryMessage['type'] === 'success' ? 'text-emerald-700' : 'text-red-700'; ?>"><?php echo htmlspecialchars($industryMessage['text']); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Neue Branche hinzufügen -->
            <div id="addIndustryForm" class="hidden bg-gray-50 rounded-lg p-4 border border-gray-200">
                <h4 class="text-sm font-semibold text-gray-700 mb-3">Neue Branche anlegen</h4>
                <form method="POST" class="flex flex-col sm:flex-row gap-3">
                    <input type="text" name="industry_name" placeholder="Branchenname" maxlength="100" required
                           aria-label="Branchenname"
                           class="flex-1 px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm">
                    <input type="number" name="industry_sort_order" placeholder="Reihenfolge" min="0" value="0"
                           aria-label="Sortierreihenfolge"
                           class="w-32 px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm">
                    <button type="submit" name="add_industry"
                            class="bg-emerald-500 text-white px-5 py-2.5 rounded-lg hover:bg-emerald-600 transition text-sm font-medium whitespace-nowrap">
                        <i class="fas fa-plus mr-1"></i>Anlegen
                    </button>
                </form>
            </div>

            <!-- Branchen-Liste -->
            <?php if (empty($allIndustries)): ?>
            <p class="text-center text-gray-400 py-6 italic">Keine Branchen vorhanden. Führe zuerst die migrations.sql aus.</p>
            <?php else: ?>
            <div class="overflow-hidden rounded-lg border border-gray-200">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-600">Name</th>
                            <th class="px-4 py-3 text-center font-medium text-gray-600 w-28">Reihenfolge</th>
                            <th class="px-4 py-3 text-center font-medium text-gray-600 w-36">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($allIndustries as $ind): ?>
                        <tr class="hover:bg-gray-50" id="industry-row-<?php echo $ind['id']; ?>">
                            <td class="px-4 py-3 font-medium text-gray-800">
                                <span id="ind-name-display-<?php echo $ind['id']; ?>"><?php echo htmlspecialchars($ind['name']); ?></span>
                                <form id="ind-edit-form-<?php echo $ind['id']; ?>" method="POST" class="hidden flex gap-2 mt-1">
                                    <input type="hidden" name="industry_id" value="<?php echo $ind['id']; ?>">
                                    <input type="text" name="industry_name" value="<?php echo htmlspecialchars($ind['name']); ?>" maxlength="100" required
                                           aria-label="Branchenname bearbeiten"
                                           class="flex-1 px-3 py-1.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 text-sm">
                                    <input type="number" name="industry_sort_order" value="<?php echo $ind['sort_order']; ?>" min="0"
                                           aria-label="Sortierreihenfolge"
                                           class="w-20 px-3 py-1.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 text-sm">
                                    <button type="submit" name="edit_industry" class="bg-emerald-500 text-white px-3 py-1.5 rounded-lg text-xs hover:bg-emerald-600 transition">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button type="button" onclick="cancelEditIndustry(<?php echo $ind['id']; ?>)" class="bg-gray-200 text-gray-700 px-3 py-1.5 rounded-lg text-xs hover:bg-gray-300 transition">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </form>
                            </td>
                            <td class="px-4 py-3 text-center text-gray-500"><?php echo $ind['sort_order']; ?></td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-2" id="ind-actions-<?php echo $ind['id']; ?>">
                                    <button onclick="editIndustry(<?php echo $ind['id']; ?>)"
                                            class="px-3 py-1.5 text-xs bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                                        <i class="fas fa-edit mr-1"></i>Bearbeiten
                                    </button>
                                    <form method="POST" class="inline" onsubmit="return confirm('Branche wirklich löschen?')">
                                        <input type="hidden" name="industry_id" value="<?php echo $ind['id']; ?>">
                                        <button type="submit" name="delete_industry"
                                                class="px-3 py-1.5 text-xs bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function editIndustry(id) {
    document.getElementById('ind-name-display-' + id).classList.add('hidden');
    document.getElementById('ind-actions-' + id).classList.add('hidden');
    document.getElementById('ind-edit-form-' + id).classList.remove('hidden');
    document.getElementById('ind-edit-form-' + id).classList.add('flex');
}
function cancelEditIndustry(id) {
    document.getElementById('ind-name-display-' + id).classList.remove('hidden');
    document.getElementById('ind-actions-' + id).classList.remove('hidden');
    document.getElementById('ind-edit-form-' + id).classList.add('hidden');
    document.getElementById('ind-edit-form-' + id).classList.remove('flex');
}
</script>
