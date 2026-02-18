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
    
    logAuditAction('einstellungen_geaendert', "Einschreibezeitraum: $regStart – $regEnd, Veranstaltung: $eventDate, Max. Registrierungen: $maxReg, Auto-Close: $autoClose");
    $message = ['type' => 'success', 'text' => 'Einstellungen erfolgreich gespeichert'];
}

// Handle QR Code URL Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_qr_url'])) {
    if (!isAdmin() && !hasPermission('einstellungen_bearbeiten')) {
        die('Keine Berechtigung');
    }
    $qrUrl = sanitize($_POST['qr_url']);
    updateSetting('qr_code_url', $qrUrl);
    logAuditAction('qr_url_geaendert', "QR-Code Base-URL auf '$qrUrl' gesetzt");
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

<!-- Einstellungen – Tab-basiertes Mobile-First Layout -->
<div class="max-w-4xl mx-auto space-y-4">
    
    <!-- Header -->
    <div class="flex items-center justify-between">
        <h2 class="text-xl font-bold text-gray-800">
            <i class="fas fa-cog text-emerald-500 mr-2"></i>Einstellungen
        </h2>
    </div>

    <?php if (isset($message)): ?>
    <div class="bg-<?php echo $message['type'] === 'success' ? 'emerald' : 'red'; ?>-50 border border-<?php echo $message['type'] === 'success' ? 'emerald' : 'red'; ?>-200 p-3 rounded-lg flex items-center gap-2 text-sm">
        <i class="fas fa-<?php echo $message['type'] === 'success' ? 'check-circle text-emerald-500' : 'exclamation-circle text-red-500'; ?>"></i>
        <span class="<?php echo $message['type'] === 'success' ? 'text-emerald-700' : 'text-red-700'; ?>"><?php echo $message['text']; ?></span>
    </div>
    <?php endif; ?>

    <!-- Tab Navigation -->
    <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
        <div class="flex border-b border-gray-100 overflow-x-auto" id="settingsTabs" style="-webkit-overflow-scrolling: touch; scrollbar-width: none;">
            <button onclick="switchSettingsTab('allgemein')" data-tab="allgemein"
                    class="settings-tab active flex items-center gap-2 px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 border-emerald-500 text-emerald-600 bg-emerald-50/50 transition-all">
                <i class="fas fa-sliders-h"></i> <span>Allgemein</span>
            </button>
            <button onclick="switchSettingsTab('qrcodes')" data-tab="qrcodes"
                    class="settings-tab flex items-center gap-2 px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50 transition-all">
                <i class="fas fa-qrcode"></i> <span>QR-Codes</span>
            </button>
            <button onclick="switchSettingsTab('branchen')" data-tab="branchen"
                    class="settings-tab flex items-center gap-2 px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50 transition-all">
                <i class="fas fa-industry"></i> <span>Branchen</span>
            </button>
            <button onclick="switchSettingsTab('system')" data-tab="system"
                    class="settings-tab flex items-center gap-2 px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50 transition-all">
                <i class="fas fa-info-circle"></i> <span>System</span>
            </button>
        </div>

        <!-- ============================================================ -->
        <!-- TAB 1: Allgemein -->
        <!-- ============================================================ -->
        <div id="tab-allgemein" class="settings-tab-content p-4 sm:p-6">
            <form method="POST" class="space-y-6">
                
                <!-- Schnellstatus -->
                <div class="p-3 rounded-lg border <?php 
                    $status = getRegistrationStatus();
                    echo $status === 'open' ? 'bg-emerald-50 border-emerald-200' : ($status === 'upcoming' ? 'bg-amber-50 border-amber-200' : 'bg-red-50 border-red-200');
                ?>">
                    <div class="flex items-center gap-2 text-sm font-medium">
                        <span class="w-2.5 h-2.5 rounded-full <?php echo $status === 'open' ? 'bg-emerald-500' : ($status === 'upcoming' ? 'bg-amber-500' : 'bg-red-500'); ?>"></span>
                        <span class="<?php echo $status === 'open' ? 'text-emerald-700' : ($status === 'upcoming' ? 'text-amber-700' : 'text-red-700'); ?>">
                            Einschreibung: <?php echo $status === 'open' ? 'Offen' : ($status === 'upcoming' ? 'Noch nicht gestartet' : 'Geschlossen'); ?>
                        </span>
                    </div>
                </div>

                <!-- Einschreibezeitraum -->
                <div>
                    <h4 class="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-2">
                        <i class="fas fa-calendar-alt text-purple-500"></i> Einschreibezeitraum
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Beginn *</label>
                            <input type="datetime-local" name="registration_start" 
                                   value="<?php echo date('Y-m-d\TH:i', strtotime($currentSettings['registration_start'])); ?>"
                                   required
                                   class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-400 focus:border-emerald-400 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Ende *</label>
                            <input type="datetime-local" name="registration_end" 
                                   value="<?php echo date('Y-m-d\TH:i', strtotime($currentSettings['registration_end'])); ?>"
                                   required
                                   class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-400 focus:border-emerald-400 text-sm">
                        </div>
                    </div>
                </div>

                <!-- Veranstaltungsdatum -->
                <div>
                    <h4 class="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-2">
                        <i class="fas fa-calendar-check text-green-500"></i> Veranstaltung
                    </h4>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Datum der Berufsmesse *</label>
                        <input type="date" name="event_date" 
                               value="<?php echo $currentSettings['event_date']; ?>"
                               required
                               class="w-full sm:w-auto px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-400 focus:border-emerald-400 text-sm">
                    </div>
                </div>

                <!-- Einschreibungsparameter -->
                <div>
                    <h4 class="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-2">
                        <i class="fas fa-sliders-h text-blue-500"></i> Parameter
                    </h4>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Max. Einschreibungen pro Schüler *</label>
                            <div class="flex items-center gap-3">
                                <input type="number" name="max_registrations_per_student" 
                                       value="<?php echo $currentSettings['max_registrations_per_student']; ?>"
                                       min="1" max="3" required
                                       class="w-20 px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-400 focus:border-emerald-400 text-sm text-center font-bold text-lg">
                                <span class="text-xs text-gray-500">Aussteller (= Zeitslots)</span>
                            </div>
                        </div>
                        
                        <label class="flex items-start gap-3 cursor-pointer p-3 rounded-lg border border-gray-100 hover:bg-gray-50 transition">
                            <input type="checkbox" name="auto_close_registration" value="1"
                                   <?php echo $currentSettings['auto_close_registration'] === '1' ? 'checked' : ''; ?>
                                   class="w-5 h-5 text-emerald-500 rounded border-gray-300 focus:ring-emerald-400 mt-0.5 flex-shrink-0">
                            <div>
                                <span class="text-sm font-medium text-gray-700 block">Automatisch schliessen nach Zuteilung</span>
                                <span class="text-xs text-gray-500">Einschreibung wird nach der automatischen Zuteilung geschlossen.</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Speichern -->
                <div class="pt-2">
                    <button type="submit" name="save_settings"
                            class="w-full sm:w-auto px-6 py-2.5 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition font-medium text-sm">
                        <i class="fas fa-save mr-2"></i>Einstellungen speichern
                    </button>
                </div>
            </form>
        </div>

        <!-- ============================================================ -->
        <!-- TAB 2: QR-Codes -->
        <!-- ============================================================ -->
        <div id="tab-qrcodes" class="settings-tab-content hidden p-4 sm:p-6">
            <div class="space-y-6">
                
                <!-- Base URL Einstellung -->
                <form method="POST" class="space-y-4">
                    <h4 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                        <i class="fas fa-link text-emerald-500"></i> Base-URL für QR-Codes
                    </h4>
                    <p class="text-xs text-gray-500">Diese URL wird als Basis für die QR-Code-Check-in-Links verwendet. Die Schüler scannen Codes, die auf diese URL mit Token verweisen.</p>
                    
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">URL *</label>
                        <input type="url" name="qr_url" id="qrUrlInput"
                               value="<?php echo htmlspecialchars($currentSettings['qr_code_url']); ?>"
                               required placeholder="https://beispiel.de/berufsmesse"
                               oninput="updateQrPreview()"
                               class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-400 focus:border-emerald-400 text-sm font-mono">
                    </div>
                    
                    <!-- Live QR-Vorschau -->
                    <div class="flex flex-col sm:flex-row gap-4 items-start">
                        <div class="bg-white p-3 rounded-lg border-2 border-gray-200 inline-block flex-shrink-0">
                            <img id="qrPreviewImg" 
                                 src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=<?php echo urlencode($currentSettings['qr_code_url']); ?>" 
                                 alt="QR-Code Vorschau" class="w-36 h-36 sm:w-44 sm:h-44">
                        </div>
                        <div class="flex-1 space-y-2">
                            <p class="text-xs text-gray-500">
                                <i class="fas fa-info-circle mr-1"></i>
                                Vorschau-URL für Check-in: <br>
                                <code id="qrPreviewUrl" class="text-xs text-emerald-600 break-all"><?php echo htmlspecialchars($currentSettings['qr_code_url']); ?>?page=qr-checkin&amp;token=BEISPIEL</code>
                            </p>
                            <div class="grid grid-cols-1 gap-2">
                                <a href="https://api.qrserver.com/v1/create-qr-code/?size=600x600&data=<?php echo urlencode($currentSettings['qr_code_url']); ?>" 
                                   download="berufsmesse-qrcode.png" target="_blank"
                                   class="flex items-center justify-center gap-2 px-3 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition text-xs font-medium">
                                    <i class="fas fa-download"></i> Download (600px)
                                </a>
                                <a href="https://api.qrserver.com/v1/create-qr-code/?size=1200x1200&data=<?php echo urlencode($currentSettings['qr_code_url']); ?>" 
                                   download="berufsmesse-qrcode-hd.png" target="_blank"
                                   class="flex items-center justify-center gap-2 px-3 py-2 bg-indigo-500 text-white rounded-lg hover:bg-indigo-600 transition text-xs font-medium">
                                    <i class="fas fa-download"></i> Download HD (1200px)
                                </a>
                                <button type="button" onclick="window.print()"
                                        class="flex items-center justify-center gap-2 px-3 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition text-xs font-medium">
                                    <i class="fas fa-print"></i> QR-Code drucken
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" name="save_qr_url"
                            class="w-full sm:w-auto px-6 py-2.5 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition font-medium text-sm">
                        <i class="fas fa-save mr-2"></i>URL speichern
                    </button>
                </form>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- TAB 3: Branchen -->
        <!-- ============================================================ -->
        <div id="tab-branchen" class="settings-tab-content hidden p-4 sm:p-6">
            <div class="space-y-4">
                
                <?php if (isset($industryMessage)): ?>
                <div class="<?php echo $industryMessage['type'] === 'success' ? 'bg-emerald-50 border-emerald-200' : 'bg-red-50 border-red-200'; ?> border p-3 rounded-lg flex items-center gap-2 text-sm">
                    <i class="fas <?php echo $industryMessage['type'] === 'success' ? 'fa-check-circle text-emerald-500' : 'fa-exclamation-circle text-red-500'; ?>"></i>
                    <span class="<?php echo $industryMessage['type'] === 'success' ? 'text-emerald-700' : 'text-red-700'; ?>"><?php echo htmlspecialchars($industryMessage['text']); ?></span>
                </div>
                <?php endif; ?>

                <!-- Neue Branche -->
                <div class="flex items-center justify-between">
                    <h4 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                        <i class="fas fa-industry text-emerald-500"></i> Branchen verwalten
                    </h4>
                    <button onclick="document.getElementById('addIndustryForm').classList.toggle('hidden')"
                            class="px-3 py-1.5 bg-emerald-500 text-white rounded-lg text-xs hover:bg-emerald-600 transition font-medium">
                        <i class="fas fa-plus mr-1"></i>Neue Branche
                    </button>
                </div>

                <!-- Formular: Neue Branche -->
                <div id="addIndustryForm" class="hidden bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <form method="POST" class="space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-medium text-gray-600 mb-1">Name</label>
                                <input type="text" name="industry_name" placeholder="Branchenname" maxlength="100" required
                                       class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-400 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Reihenfolge</label>
                                <input type="number" name="industry_sort_order" placeholder="0" min="0" value="0"
                                       class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-400 text-sm">
                            </div>
                        </div>
                        <button type="submit" name="add_industry"
                                class="w-full sm:w-auto px-4 py-2 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition text-sm font-medium">
                            <i class="fas fa-plus mr-1"></i>Anlegen
                        </button>
                    </form>
                </div>

                <!-- Branchen-Liste -->
                <?php if (empty($allIndustries)): ?>
                <p class="text-center text-gray-400 py-8 text-sm italic">Keine Branchen vorhanden.</p>
                <?php else: ?>
                <div class="space-y-2">
                    <?php foreach ($allIndustries as $ind): ?>
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-100 hover:bg-white transition" id="industry-row-<?php echo $ind['id']; ?>">
                        <!-- Anzeige-Modus -->
                        <div class="flex items-center gap-3 flex-1 min-w-0" id="ind-display-<?php echo $ind['id']; ?>">
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-100 text-emerald-600 text-xs font-bold flex-shrink-0">
                                <?php echo $ind['sort_order']; ?>
                            </span>
                            <span class="text-sm font-medium text-gray-800 truncate"><?php echo htmlspecialchars($ind['name']); ?></span>
                        </div>
                        <div class="flex items-center gap-1 flex-shrink-0" id="ind-actions-<?php echo $ind['id']; ?>">
                            <button onclick="editIndustry(<?php echo $ind['id']; ?>)"
                                    class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition" title="Bearbeiten">
                                <i class="fas fa-edit text-sm"></i>
                            </button>
                            <form method="POST" class="inline" onsubmit="return confirm('Branche wirklich löschen?')">
                                <input type="hidden" name="industry_id" value="<?php echo $ind['id']; ?>">
                                <button type="submit" name="delete_industry"
                                        class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition" title="Löschen">
                                    <i class="fas fa-trash text-sm"></i>
                                </button>
                            </form>
                        </div>
                        
                        <!-- Bearbeiten-Modus (hidden) -->
                        <form id="ind-edit-form-<?php echo $ind['id']; ?>" method="POST" class="hidden w-full">
                            <div class="flex flex-col sm:flex-row gap-2 w-full">
                                <input type="hidden" name="industry_id" value="<?php echo $ind['id']; ?>">
                                <input type="text" name="industry_name" value="<?php echo htmlspecialchars($ind['name']); ?>" maxlength="100" required
                                       class="flex-1 px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-400 text-sm">
                                <input type="number" name="industry_sort_order" value="<?php echo $ind['sort_order']; ?>" min="0"
                                       class="w-20 px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-400 text-sm">
                                <div class="flex gap-1">
                                    <button type="submit" name="edit_industry" class="p-2 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button type="button" onclick="cancelEditIndustry(<?php echo $ind['id']; ?>)" class="p-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- TAB 4: System -->
        <!-- ============================================================ -->
        <div id="tab-system" class="settings-tab-content hidden p-4 sm:p-6">
            <div class="space-y-4">
                <h4 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-server text-blue-500"></i> System-Information
                </h4>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="p-3 bg-gray-50 rounded-lg border border-gray-100">
                        <p class="text-xs text-gray-500 mb-0.5">PHP Version</p>
                        <p class="text-sm font-semibold text-gray-800"><?php echo phpversion(); ?></p>
                    </div>
                    <div class="p-3 bg-gray-50 rounded-lg border border-gray-100">
                        <p class="text-xs text-gray-500 mb-0.5">Datenbank</p>
                        <p class="text-sm font-semibold text-gray-800"><?php echo DB_NAME; ?></p>
                    </div>
                    <div class="p-3 bg-gray-50 rounded-lg border border-gray-100">
                        <p class="text-xs text-gray-500 mb-0.5">Max. Upload-Größe</p>
                        <p class="text-sm font-semibold text-gray-800"><?php echo round(MAX_FILE_SIZE / 1048576, 1); ?> MB</p>
                    </div>
                    <div class="p-3 bg-gray-50 rounded-lg border border-gray-100">
                        <p class="text-xs text-gray-500 mb-0.5">Upload-Verzeichnis</p>
                        <p class="text-sm font-semibold text-gray-800 text-xs break-all"><?php echo UPLOAD_DIR; ?></p>
                    </div>
                </div>

                <!-- Hinweise -->
                <div class="bg-amber-50 border border-amber-200 p-4 rounded-lg mt-4">
                    <h5 class="text-sm font-semibold text-amber-800 flex items-center gap-2 mb-2">
                        <i class="fas fa-exclamation-triangle text-amber-500"></i> Wichtige Hinweise
                    </h5>
                    <ul class="space-y-1.5 text-xs text-amber-700">
                        <li class="flex items-start gap-2">
                            <i class="fas fa-check text-amber-500 mt-0.5 flex-shrink-0"></i>
                            <span>Änderungen am Einschreibezeitraum wirken sich sofort aus.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="fas fa-check text-amber-500 mt-0.5 flex-shrink-0"></i>
                            <span>Nach Ablauf der Frist → automatische Zuteilung durchführen.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="fas fa-check text-amber-500 mt-0.5 flex-shrink-0"></i>
                            <span>Genügend Plätze für alle Schüler sicherstellen.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="fas fa-check text-amber-500 mt-0.5 flex-shrink-0"></i>
                            <span>Max. Einschreibungen = 3 (1 pro Zeitslot) empfohlen.</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Tab-Scrollbar auf Mobile verbergen */
    #settingsTabs::-webkit-scrollbar { display: none; }
</style>

<script>
function switchSettingsTab(tabName) {
    // Alle Tabs deaktivieren
    document.querySelectorAll('.settings-tab').forEach(btn => {
        btn.classList.remove('active', 'border-emerald-500', 'text-emerald-600', 'bg-emerald-50/50');
        btn.classList.add('border-transparent', 'text-gray-500');
    });
    document.querySelectorAll('.settings-tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    // Aktiven Tab aktivieren
    const activeBtn = document.querySelector(`.settings-tab[data-tab="${tabName}"]`);
    if (activeBtn) {
        activeBtn.classList.add('active', 'border-emerald-500', 'text-emerald-600', 'bg-emerald-50/50');
        activeBtn.classList.remove('border-transparent', 'text-gray-500');
    }
    const activeContent = document.getElementById('tab-' + tabName);
    if (activeContent) {
        activeContent.classList.remove('hidden');
    }
    
    // URL Hash setzen für Deep-Linking
    history.replaceState(null, '', location.pathname + location.search + '#' + tabName);
}

// Tab aus URL Hash laden
document.addEventListener('DOMContentLoaded', function() {
    const hash = location.hash.replace('#', '');
    if (hash && document.getElementById('tab-' + hash)) {
        switchSettingsTab(hash);
    }
    <?php if (isset($industryMessage)): ?>
    // Bei Branchen-Aktionen automatisch zum Branchen-Tab
    switchSettingsTab('branchen');
    <?php endif; ?>
});

// Live QR-Vorschau
let qrUpdateTimer;
function updateQrPreview() {
    clearTimeout(qrUpdateTimer);
    qrUpdateTimer = setTimeout(function() {
        const url = document.getElementById('qrUrlInput').value;
        if (url) {
            document.getElementById('qrPreviewImg').src = 
                'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' + encodeURIComponent(url);
            document.getElementById('qrPreviewUrl').textContent = url + '?page=qr-checkin&token=BEISPIEL';
        }
    }, 500);
}

function editIndustry(id) {
    document.getElementById('ind-display-' + id).classList.add('hidden');
    document.getElementById('ind-actions-' + id).classList.add('hidden');
    document.getElementById('ind-edit-form-' + id).classList.remove('hidden');
}
function cancelEditIndustry(id) {
    document.getElementById('ind-display-' + id).classList.remove('hidden');
    document.getElementById('ind-actions-' + id).classList.remove('hidden');
    document.getElementById('ind-edit-form-' + id).classList.add('hidden');
}
</script>
