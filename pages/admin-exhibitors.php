<?php
// Admin Aussteller Verwaltung

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_exhibitor'])) {
        if (!isAdmin() && !hasPermission('aussteller_erstellen')) die('Keine Berechtigung');
        // Neuen Aussteller hinzufuegen
        $name = strip_tags(trim($_POST['name']));
        $shortDesc = sanitize($_POST['short_description']);
        $description = sanitize($_POST['description']);
        $category = html_entity_decode(strip_tags(trim($_POST['category'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $contactPerson = sanitize($_POST['contact_person'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $website = sanitize($_POST['website'] ?? '');
        $jobs = sanitize($_POST['jobs'] ?? '');
        $features = sanitize($_POST['features'] ?? '');
        
        // Equipment als kommaseparierter String speichern
        $equipment = isset($_POST['equipment']) ? implode(',', $_POST['equipment']) : '';
        
        // Angebotstypen als JSON speichern
        $offerSelected = isset($_POST['offer_types_selected']) ? (array)$_POST['offer_types_selected'] : [];
        $offerCustom = trim($_POST['offer_types_custom'] ?? '');
        $offerTypesJson = (!empty($offerSelected) || $offerCustom !== '') 
            ? json_encode(['selected' => $offerSelected, 'custom' => $offerCustom]) 
            : null;
        
        // Sichtbare Felder als JSON speichern
        $visibleFields = isset($_POST['visible_fields']) ? $_POST['visible_fields'] : ['name', 'short_description', 'description', 'category', 'website'];
        $visibleFieldsJson = json_encode($visibleFields);
        
        // Logo-Upload verarbeiten
        $logoPath = null;
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $logoPath = handleLogoUpload($_FILES['logo']);
        }
        
        $stmt = $db->prepare("INSERT INTO exhibitors (name, short_description, description, category, contact_person, email, phone, website, visible_fields, logo, offer_types, jobs, features, equipment) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$name, $shortDesc, $description, $category, $contactPerson, $email, $phone, $website, $visibleFieldsJson, $logoPath, $offerTypesJson, $jobs, $features, $equipment])) {
            logAuditAction('aussteller_erstellt', "Aussteller '$name' erstellt");
            $message = ['type' => 'success', 'text' => 'Aussteller erfolgreich hinzugefuegt'];
        } else {
            $message = ['type' => 'error', 'text' => 'Fehler beim Hinzufuegen'];
        }
    } elseif (isset($_POST['edit_exhibitor'])) {
        if (!isAdmin() && !hasPermission('aussteller_bearbeiten')) die('Keine Berechtigung');
        // Aussteller bearbeiten
        $id = intval($_POST['exhibitor_id']);
        $name = strip_tags(trim($_POST['name']));
        $shortDesc = sanitize($_POST['short_description']);
        $description = sanitize($_POST['description']);
        $category = html_entity_decode(strip_tags(trim($_POST['category'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $contactPerson = sanitize($_POST['contact_person'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $website = sanitize($_POST['website'] ?? '');
        $jobs = sanitize($_POST['jobs'] ?? '');
        $features = sanitize($_POST['features'] ?? '');
        
        // Equipment als kommaseparierter String speichern
        $equipment = isset($_POST['equipment']) ? implode(',', $_POST['equipment']) : '';
        
        // Angebotstypen als JSON speichern
        $offerSelected = isset($_POST['offer_types_selected']) ? (array)$_POST['offer_types_selected'] : [];
        $offerCustom = trim($_POST['offer_types_custom'] ?? '');
        $offerTypesJson = (!empty($offerSelected) || $offerCustom !== '') 
            ? json_encode(['selected' => $offerSelected, 'custom' => $offerCustom]) 
            : null;
        
        // Sichtbare Felder als JSON speichern
        $visibleFields = isset($_POST['visible_fields']) ? $_POST['visible_fields'] : ['name', 'short_description', 'description', 'category', 'website'];
        $visibleFieldsJson = json_encode($visibleFields);
        
        // Logo-Upload verarbeiten
        $logoPath = null;
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $logoPath = handleLogoUpload($_FILES['logo']);
            
            // Altes Logo loeschen
            $stmt = $db->prepare("SELECT logo FROM exhibitors WHERE id = ?");
            $stmt->execute([$id]);
            $oldLogo = $stmt->fetch()['logo'];
            if ($oldLogo && file_exists('uploads/' . $oldLogo)) {
                unlink('uploads/' . $oldLogo);
            }
            
            $stmt = $db->prepare("UPDATE exhibitors SET name = ?, short_description = ?, description = ?, category = ?, 
                                  contact_person = ?, email = ?, phone = ?, website = ?, visible_fields = ?, logo = ?, offer_types = ?, jobs = ?, features = ?, equipment = ? WHERE id = ?");
            $result = $stmt->execute([$name, $shortDesc, $description, $category, $contactPerson, $email, $phone, $website, $visibleFieldsJson, $logoPath, $offerTypesJson, $jobs, $features, $equipment, $id]);
        } else {
            $stmt = $db->prepare("UPDATE exhibitors SET name = ?, short_description = ?, description = ?, category = ?, 
                                  contact_person = ?, email = ?, phone = ?, website = ?, visible_fields = ?, offer_types = ?, jobs = ?, features = ?, equipment = ? WHERE id = ?");
            $result = $stmt->execute([$name, $shortDesc, $description, $category, $contactPerson, $email, $phone, $website, $visibleFieldsJson, $offerTypesJson, $jobs, $features, $equipment, $id]);
        }
        
        if ($result) {
            logAuditAction('aussteller_bearbeitet', "Aussteller '$name' (ID: $id) bearbeitet");
            $message = ['type' => 'success', 'text' => 'Aussteller erfolgreich aktualisiert'];
        } else {
            $message = ['type' => 'error', 'text' => 'Fehler beim Aktualisieren'];
        }
    } elseif (isset($_POST['delete_exhibitor'])) {
        if (!isAdmin() && !hasPermission('aussteller_loeschen')) die('Keine Berechtigung');
        // Aussteller loeschen
        $id = intval($_POST['exhibitor_id']);

        // Name für Audit-Log vorab laden
        $stmt = $db->prepare("SELECT name, logo FROM exhibitors WHERE id = ?");
        $stmt->execute([$id]);
        $exRow = $stmt->fetch();
        $deletedName = $exRow['name'] ?? "ID $id";
        $logo = $exRow['logo'] ?? null;
        if ($logo && file_exists('uploads/' . $logo)) {
            unlink('uploads/' . $logo);
        }

        // Umverteilung: Studenten mit zugewiesenen Slots umverteilen
        $stmt = $db->prepare("
            SELECT r.id, r.user_id, r.timeslot_id, t.slot_number
            FROM registrations r
            JOIN timeslots t ON r.timeslot_id = t.id
            WHERE r.exhibitor_id = ? AND r.timeslot_id IS NOT NULL
        ");
        $stmt->execute([$id]);
        $affectedRegistrations = $stmt->fetchAll();

        $redistributedCount = 0;
        foreach ($affectedRegistrations as $reg) {
            $studentId = $reg['user_id'];
            $timeslotId = $reg['timeslot_id'];
            $slotNumber = $reg['slot_number'];

            // Finde einen anderen Aussteller mit Kapazität im gleichen Slot
            $stmt = $db->prepare("
                SELECT e.id, e.name, e.room_id,
                       COUNT(DISTINCT r2.user_id) as current_count
                FROM exhibitors e
                LEFT JOIN registrations r2 ON e.id = r2.exhibitor_id AND r2.timeslot_id = ?
                WHERE e.active = 1 AND e.id != ? AND e.room_id IS NOT NULL
                GROUP BY e.id, e.name, e.room_id
                ORDER BY current_count ASC, RAND()
                LIMIT 1
            ");
            $stmt->execute([$timeslotId, $id]);
            $newExhibitor = $stmt->fetch();

            if ($newExhibitor) {
                // Prüfe Kapazität
                $slotCapacity = getRoomSlotCapacity($newExhibitor['room_id'], $timeslotId);
                if ($slotCapacity > 0 && $newExhibitor['current_count'] < $slotCapacity) {
                    // Umverteilung durchführen
                    $stmt = $db->prepare("UPDATE registrations SET exhibitor_id = ? WHERE id = ?");
                    if ($stmt->execute([$newExhibitor['id'], $reg['id']])) {
                        $redistributedCount++;
                    }
                } else {
                    // Keine Kapazität - Registrierung löschen (wird durch CASCADE gelöscht)
                }
            }
        }

        $stmt = $db->prepare("DELETE FROM exhibitors WHERE id = ?");
        if ($stmt->execute([$id])) {
            $logMsg = "Aussteller '$deletedName' (ID: $id) gelöscht";
            if ($redistributedCount > 0) {
                $logMsg .= " - $redistributedCount Schüler umverteilt";
            }
            logAuditAction('aussteller_geloescht', $logMsg);
            $successMsg = 'Aussteller erfolgreich gelöscht';
            if ($redistributedCount > 0) {
                $successMsg .= " ($redistributedCount Schüler wurden umverteilt)";
            }
            $message = ['type' => 'success', 'text' => $successMsg];
        } else {
            $message = ['type' => 'error', 'text' => 'Fehler beim Loeschen'];
        }
    } elseif (isset($_POST['upload_document'])) {
        if (!isAdmin() && !hasPermission('aussteller_dokumente_verwalten')) die('Keine Berechtigung');
        // Dokument hochladen
        $exhibitorId = intval($_POST['exhibitor_id']);
        if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
            $result = uploadFile($_FILES['document'], $exhibitorId);
            $message = $result;
        }
    } elseif (isset($_POST['delete_document'])) {
        if (!isAdmin() && !hasPermission('aussteller_dokumente_verwalten')) die('Keine Berechtigung');
        // Dokument loeschen
        $documentId = intval($_POST['document_id']);
        if (deleteFile($documentId)) {
            $message = ['success' => true, 'message' => 'Dokument erfolgreich geloescht'];
        }
    } elseif (isset($_POST['delete_logo'])) {
        // Logo loeschen
        $id = intval($_POST['exhibitor_id']);
        $stmt = $db->prepare("SELECT logo FROM exhibitors WHERE id = ?");
        $stmt->execute([$id]);
        $logo = $stmt->fetch()['logo'];
        if ($logo && file_exists('uploads/' . $logo)) {
            unlink('uploads/' . $logo);
        }
        $stmt = $db->prepare("UPDATE exhibitors SET logo = NULL WHERE id = ?");
        if ($stmt->execute([$id])) {
            $message = ['type' => 'success', 'text' => 'Logo erfolgreich entfernt'];
        }
    }

    // ============================================================
    // Branchen Management (verschoben von admin-settings.php)
    // ============================================================
    if (isset($_POST['add_industry'])) {
        if (!isAdmin() && !hasPermission('branchen_verwalten')) die('Keine Berechtigung');
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
        if (!isAdmin() && !hasPermission('branchen_verwalten')) die('Keine Berechtigung');
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
        if (!isAdmin() && !hasPermission('branchen_verwalten')) die('Keine Berechtigung');
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
    } elseif (isset($_POST['add_orga_member'])) {
        if (!isAdmin() && !hasPermission('aussteller_bearbeiten')) die('Keine Berechtigung');
        $exhibitorId = intval($_POST['exhibitor_id']);
        $userId = intval($_POST['user_id']);

        if (assignExhibitorOrgaMember($userId, $exhibitorId)) {
            $stmt = $db->prepare("SELECT name FROM exhibitors WHERE id = ?");
            $stmt->execute([$exhibitorId]);
            $exhibitorName = $stmt->fetch()['name'];
            $stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $username = $stmt->fetch()['username'];
            logAuditAction('orga_member_zugewiesen', "Benutzer '$username' als Orga-Mitglied für Aussteller '$exhibitorName' zugewiesen");
            $orgaMessage = ['type' => 'success', 'text' => 'Orga-Mitglied erfolgreich hinzugefügt'];
            $activeTab = 'orga-team'; // Keep orga-team tab active
        } else {
            $orgaMessage = ['type' => 'error', 'text' => 'Fehler beim Hinzufügen oder Mitglied bereits zugewiesen'];
            $activeTab = 'orga-team'; // Keep orga-team tab active
        }
    } elseif (isset($_POST['remove_orga_member'])) {
        if (!isAdmin() && !hasPermission('aussteller_bearbeiten')) die('Keine Berechtigung');
        $exhibitorId = intval($_POST['exhibitor_id']);
        $userId = intval($_POST['user_id']);

        if (removeExhibitorOrgaMember($userId, $exhibitorId)) {
            $stmt = $db->prepare("SELECT name FROM exhibitors WHERE id = ?");
            $stmt->execute([$exhibitorId]);
            $exhibitorName = $stmt->fetch()['name'];
            $stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $username = $stmt->fetch()['username'];
            logAuditAction('orga_member_entfernt', "Benutzer '$username' als Orga-Mitglied für Aussteller '$exhibitorName' entfernt");
            $orgaMessage = ['type' => 'success', 'text' => 'Orga-Mitglied erfolgreich entfernt'];
            $activeTab = 'orga-team'; // Keep orga-team tab active
        } else {
            $orgaMessage = ['type' => 'error', 'text' => 'Fehler beim Entfernen'];
            $activeTab = 'orga-team'; // Keep orga-team tab active
        }
    }
}

// Logo-Upload Funktion
function handleLogoUpload($file) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5 MB
    
    if (!in_array($file['type'], $allowedTypes)) {
        return null;
    }
    
    if ($file['size'] > $maxSize) {
        return null;
    }
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'logo_' . uniqid() . '.' . $ext;
    $uploadPath = 'uploads/' . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        return $filename;
    }
    
    return null;
}

// Branchen aus DB laden
$industries = getIndustries();
$industryNames = array_column($industries, 'name');
$allIndustries = $industries; // Für Branchen-Tab

// Alle Aussteller laden mit Raum-Kapazitaet
$stmt = $db->query("
    SELECT e.*, r.capacity as room_capacity
    FROM exhibitors e
    LEFT JOIN rooms r ON e.room_id = r.id
    ORDER BY e.name ASC
");
$allExhibitors = $stmt->fetchAll();

// Orga-Benutzer laden (Rolle 'orga' oder mit qr_codes_verwalten Berechtigung)
$stmt = $db->query("
    SELECT DISTINCT u.id, u.username, u.firstname, u.lastname, u.email, u.role
    FROM users u
    LEFT JOIN user_permissions up ON u.id = up.user_id
    WHERE u.role = 'orga' OR up.permission = 'qr_codes_verwalten'
    ORDER BY u.lastname, u.firstname
");
$orgaUsers = $stmt->fetchAll();
?>

<div class="space-y-6">
    <?php if (isset($message)): ?>
    <div class="mb-4">
        <?php if (($message['type'] ?? $message['success']) === 'success' || ($message['success'] ?? false)): ?>
            <div class="bg-emerald-50 border border-emerald-200 p-4 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-emerald-500 mr-3"></i>
                    <p class="text-emerald-700"><?php echo $message['text'] ?? ($message['message'] ?? ''); ?></p>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-red-50 border border-red-200 p-4 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                    <p class="text-red-700"><?php echo $message['text'] ?? ($message['message'] ?? 'Ein Fehler ist aufgetreten'); ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-xl font-semibold text-gray-800">Aussteller & Branchen</h2>
            <p class="text-sm text-gray-500 mt-1">Verwalte Aussteller und Branchen</p>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="bg-white rounded-xl border border-gray-100 overflow-hidden mb-6">
        <div class="flex border-b border-gray-100">
            <button onclick="switchExhibitorsTab('aussteller')" data-tab="aussteller"
                    class="exhibitors-tab-button flex-1 px-6 py-4 text-sm font-medium transition border-b-2 border-emerald-500 text-emerald-600">
                <i class="fas fa-building mr-2"></i>Aussteller
            </button>
            <button onclick="switchExhibitorsTab('branchen')" data-tab="branchen"
                    class="exhibitors-tab-button flex-1 px-6 py-4 text-sm font-medium transition border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                <i class="fas fa-industry mr-2"></i>Branchen
            </button>
            <button onclick="switchExhibitorsTab('orga-team')" data-tab="orga-team"
                    class="exhibitors-tab-button flex-1 px-6 py-4 text-sm font-medium transition border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                <i class="fas fa-users-cog mr-2"></i>Orga-Team
            </button>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- TAB 1: Aussteller -->
    <!-- ============================================================ -->
    <div id="tab-aussteller" class="exhibitors-tab-content">
        <!-- Add Button -->
        <div class="flex justify-end mb-4">
            <button onclick="openAddModal()" class="bg-emerald-500 text-white px-5 py-2.5 rounded-lg hover:bg-emerald-600 transition font-medium">
                <i class="fas fa-plus mr-2"></i>Neuer Aussteller
            </button>
        </div>

        <!-- Exhibitors List -->
    <div class="grid grid-cols-1 gap-4">
        <?php foreach ($allExhibitors as $exhibitor): 
            // Raum-basierte Kapazitaet berechnen
            $roomCapacity = $exhibitor['room_capacity'] ? intval($exhibitor['room_capacity']) : 0;
            $totalCapacity = $roomCapacity > 0 ? floor($roomCapacity / 3) * 3 : 0;
            
            // Registrierungen zaehlen
            $stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) as count FROM registrations WHERE exhibitor_id = ?");
            $stmt->execute([$exhibitor['id']]);
            $regCount = $stmt->fetch()['count'];
            
            // Dokumente laden
            $stmt = $db->prepare("SELECT * FROM exhibitor_documents WHERE exhibitor_id = ?");
            $stmt->execute([$exhibitor['id']]);
            $documents = $stmt->fetchAll();
        ?>
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
            <!-- Header -->
            <div class="px-5 py-4 border-b border-gray-100">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <!-- Logo -->
                        <div class="w-12 h-12 rounded-lg bg-gray-50 border border-gray-100 flex items-center justify-center overflow-hidden flex-shrink-0">
                            <?php if ($exhibitor['logo']): ?>
                                <img src="<?php echo BASE_URL . 'uploads/' . $exhibitor['logo']; ?>" 
                                     alt="<?php echo htmlspecialchars($exhibitor['name']); ?>" 
                                     class="w-10 h-10 object-contain">
                            <?php else: ?>
                                <i class="fas fa-building text-gray-300 text-lg"></i>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h3 class="text-base font-semibold text-gray-800"><?php echo htmlspecialchars($exhibitor['name']); ?></h3>
                            <p class="text-sm text-gray-500">
                                <?php echo $regCount; ?> / <?php echo $totalCapacity; ?> Plaetze belegt
                                <?php if ($totalCapacity === 0): ?>
                                    <span class="text-amber-500 ml-1">(Kein Raum)</span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($exhibitor)); ?>)" 
                                class="px-3 py-1.5 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                            <i class="fas fa-edit mr-1"></i>Bearbeiten
                        </button>
                        <button onclick="openDocumentModal(<?php echo $exhibitor['id']; ?>, '<?php echo htmlspecialchars($exhibitor['name']); ?>')" 
                                class="px-3 py-1.5 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                            <i class="fas fa-file-upload mr-1"></i>Dokumente
                        </button>
                        <form method="POST" class="inline" onsubmit="return confirm('Wirklich loeschen?')">
                            <input type="hidden" name="exhibitor_id" value="<?php echo $exhibitor['id']; ?>">
                            <button type="submit" name="delete_exhibitor" class="px-3 py-1.5 text-sm bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Body -->
            <div class="px-5 py-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1">Kurzbeschreibung</p>
                        <p class="text-sm text-gray-700"><?php echo htmlspecialchars($exhibitor['short_description'] ?? '-'); ?></p>
                        <?php if ($exhibitor['category']): ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 mt-2 rounded text-xs font-medium bg-emerald-50 text-emerald-700">
                            <i class="fas fa-tag mr-1"></i><?php echo htmlspecialchars($exhibitor['category']); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1">Kontakt</p>
                        <p class="text-sm text-gray-700">
                            <?php if ($exhibitor['contact_person']): ?>
                                <i class="fas fa-user mr-1 text-gray-400"></i><?php echo htmlspecialchars($exhibitor['contact_person']); ?><br>
                            <?php endif; ?>
                            <?php if ($exhibitor['email']): ?>
                                <i class="fas fa-envelope mr-1 text-gray-400"></i><?php echo htmlspecialchars($exhibitor['email']); ?><br>
                            <?php endif; ?>
                            <?php if ($exhibitor['website']): ?>
                                <i class="fas fa-globe mr-1 text-gray-400"></i><?php echo htmlspecialchars($exhibitor['website']); ?>
                            <?php endif; ?>
                            <?php if (!$exhibitor['contact_person'] && !$exhibitor['email'] && !$exhibitor['website']): ?>
                                <span class="text-gray-400">-</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <?php if (!empty($documents)): ?>
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">Dokumente (<?php echo count($documents); ?>)</p>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($documents as $doc): ?>
                            <span class="inline-flex items-center px-2.5 py-1 rounded text-xs font-medium bg-blue-50 text-blue-700">
                                <i class="fas fa-file mr-1"></i><?php echo htmlspecialchars($doc['original_name']); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    </div><!-- Ende Tab Aussteller -->

    <!-- ============================================================ -->
    <!-- TAB 2: Branchen -->
    <!-- ============================================================ -->
    <div id="tab-branchen" class="exhibitors-tab-content hidden">
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
    </div><!-- Ende Tab Branchen -->

    <!-- ============================================================ -->
    <!-- TAB 3: Orga-Team -->
    <!-- ============================================================ -->
    <div id="tab-orga-team" class="exhibitors-tab-content hidden">
        <?php if (isset($orgaMessage)): ?>
        <div class="mb-4 animate-pulse">
            <?php if ($orgaMessage['type'] === 'success'): ?>
                <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-500 mr-3"></i>
                        <p class="text-green-700"><?php echo $orgaMessage['text']; ?></p>
                    </div>
                </div>
            <?php else: ?>
                <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                        <p class="text-red-700"><?php echo $orgaMessage['text']; ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="bg-blue-50 border border-blue-100 p-5 rounded-lg mb-6">
            <div class="flex items-start">
                <i class="fas fa-info-circle text-blue-500 text-xl mr-3 mt-1"></i>
                <div>
                    <h3 class="font-bold text-blue-900 mb-2">Orga-Team für Aussteller</h3>
                    <p class="text-sm text-blue-800">
                        Hier können Sie einzelnen Orga-Mitgliedern Zugang zu bestimmten Ausstellern geben.
                        Diese Mitglieder können dann nur für ihre zugewiesenen Aussteller QR-Codes generieren und verwalten.
                    </p>
                </div>
            </div>
        </div>

        <!-- Exhibitors with Orga Team Management -->
        <div class="space-y-4">
            <?php foreach ($allExhibitors as $exhibitor):
                $orgaMembers = getExhibitorOrgaMembers($exhibitor['id']);
            ?>
            <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-building text-emerald-600"></i>
                            <h3 class="font-semibold text-gray-800"><?php echo htmlspecialchars($exhibitor['name']); ?></h3>
                        </div>
                        <span class="px-3 py-1 bg-emerald-100 text-emerald-700 rounded-full text-sm font-medium">
                            <?php echo count($orgaMembers); ?> Mitglied<?php echo count($orgaMembers) != 1 ? 'er' : ''; ?>
                        </span>
                    </div>
                </div>

                <div class="p-6">
                    <!-- Add Orga Member Form -->
                    <form method="POST" class="mb-4">
                        <input type="hidden" name="exhibitor_id" value="<?php echo $exhibitor['id']; ?>">
                        <div class="flex gap-3">
                            <select name="user_id" required class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                                <option value="">-- Orga-Mitglied auswählen --</option>
                                <?php foreach ($orgaUsers as $orgaUser):
                                    // Skip if already assigned
                                    $alreadyAssigned = false;
                                    foreach ($orgaMembers as $member) {
                                        if ($member['id'] == $orgaUser['id']) {
                                            $alreadyAssigned = true;
                                            break;
                                        }
                                    }
                                    if ($alreadyAssigned) continue;
                                ?>
                                    <option value="<?php echo $orgaUser['id']; ?>">
                                        <?php echo htmlspecialchars($orgaUser['firstname'] . ' ' . $orgaUser['lastname']); ?>
                                        (<?php echo htmlspecialchars($orgaUser['username']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" name="add_orga_member"
                                    class="px-6 py-2 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition font-medium">
                                <i class="fas fa-plus mr-2"></i>Hinzufügen
                            </button>
                        </div>
                    </form>

                    <!-- Current Orga Members -->
                    <?php if (count($orgaMembers) > 0): ?>
                    <div class="space-y-2">
                        <h4 class="text-sm font-semibold text-gray-600 mb-3">Zugewiesene Orga-Mitglieder:</h4>
                        <?php foreach ($orgaMembers as $member): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-200">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center">
                                    <i class="fas fa-user text-emerald-600"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-800">
                                        <?php echo htmlspecialchars($member['firstname'] . ' ' . $member['lastname']); ?>
                                    </p>
                                    <p class="text-sm text-gray-500">
                                        <?php echo htmlspecialchars($member['username']); ?>
                                        <?php if ($member['email']): ?>
                                        · <?php echo htmlspecialchars($member['email']); ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                            <form method="POST" onsubmit="return confirm('Möchten Sie dieses Orga-Mitglied wirklich entfernen?');">
                                <input type="hidden" name="exhibitor_id" value="<?php echo $exhibitor['id']; ?>">
                                <input type="hidden" name="user_id" value="<?php echo $member['id']; ?>">
                                <button type="submit" name="remove_orga_member"
                                        class="px-4 py-2 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 transition text-sm font-medium">
                                    <i class="fas fa-trash mr-2"></i>Entfernen
                                </button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-users text-4xl mb-3 opacity-50"></i>
                        <p>Noch keine Orga-Mitglieder zugewiesen</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div><!-- Ende Tab Orga-Team -->

</div>

<!-- Add/Edit Modal -->
<div id="exhibitorModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl max-w-3xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-gray-100 px-6 py-4 flex items-center justify-between">
            <h2 id="modalTitle" class="text-lg font-semibold text-gray-800">Aussteller hinzufuegen</h2>
            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 rounded-lg p-2">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        
        <form method="POST" class="p-6 space-y-4" id="exhibitorForm" enctype="multipart/form-data">
            <input type="hidden" name="exhibitor_id" id="exhibitor_id">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Logo Upload -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Logo</label>
                    <div class="flex items-center space-x-4">
                        <div id="logoPreview" class="w-20 h-20 rounded-lg bg-gray-50 border border-gray-200 flex items-center justify-center overflow-hidden">
                            <i class="fas fa-building text-gray-300 text-2xl" id="logoPlaceholder"></i>
                            <img id="logoImage" src="" alt="" class="w-full h-full object-contain hidden">
                        </div>
                        <div class="flex-1">
                            <input type="file" name="logo" id="logoInput" accept="image/jpeg,image/png,image/gif,image/webp"
                                   class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
                            <p class="text-xs text-gray-500 mt-1">JPG, PNG, GIF oder WebP. Max. 5 MB</p>
                        </div>
                    </div>
                </div>
                
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Name *</label>
                    <input type="text" name="name" id="name" required 
                           class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Kurzbeschreibung *</label>
                    <input type="text" name="short_description" id="short_description" required maxlength="500"
                           class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-tag mr-1 text-gray-400"></i>Kategorie *
                    </label>
                    <select name="category" id="category" required
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        <option value="">-- Bitte waehlen --</option>
                        <?php foreach ($industryNames as $ind): 
                            $cleanInd = html_entity_decode($ind, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        ?>
                        <option value="<?php echo htmlspecialchars($cleanInd, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($cleanInd); ?></option>
                        <?php endforeach; ?>
                        <?php if (empty($industryNames)): ?>
                        <option disabled>-- Keine Branchen vorhanden (migrations.sql ausführen) --</option>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Beschreibung *</label>
                    <textarea name="description" id="description" required rows="4"
                              class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"></textarea>
                </div>
                
                <div class="md:col-span-2 bg-blue-50 border border-blue-100 p-4 rounded-lg">
                    <p class="text-sm text-blue-700">
                        <i class="fas fa-info-circle mr-2"></i>
                        Die Kapazitaet wird automatisch basierend auf dem zugewiesenen Raum berechnet.
                    </p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Ansprechpartner</label>
                    <input type="text" name="contact_person" id="contact_person"
                           class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">E-Mail</label>
                    <input type="email" name="email" id="email"
                           class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Telefon</label>
                    <input type="tel" name="phone" id="phone"
                           class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Webseite</label>
                    <input type="text" name="website" id="website" placeholder="www.beispiel.de"
                           class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                </div>

                <!-- Typische Berufe / Taetigkeiten -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Typische Berufe / Taetigkeiten</label>
                    <textarea name="jobs" id="jobs" rows="2"
                              class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                              placeholder="z.B. Mechatroniker, Informatiker, Kaufmann/frau..."></textarea>
                </div>

                <!-- Besonderheiten -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Besonderheiten</label>
                    <textarea name="features" id="features" rows="2"
                              class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                              placeholder="z.B. familienfreundliches Unternehmen, internationale Standorte..."></textarea>
                </div>

                <!-- Technisches Equipment -->
                <div class="md:col-span-2 border-t border-gray-100 pt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-3">
                        <i class="fas fa-tools mr-2 text-blue-500"></i>Benötigtes technisches Equipment
                    </label>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                        <label class="flex items-center p-3 bg-blue-50 rounded-lg cursor-pointer hover:bg-blue-100 transition">
                            <input type="checkbox" name="equipment[]" value="Beamer" class="mr-2 rounded text-blue-500 equipment-checkbox">
                            <span class="text-sm text-gray-700">Beamer</span>
                        </label>
                        <label class="flex items-center p-3 bg-blue-50 rounded-lg cursor-pointer hover:bg-blue-100 transition">
                            <input type="checkbox" name="equipment[]" value="Smartboard" class="mr-2 rounded text-blue-500 equipment-checkbox">
                            <span class="text-sm text-gray-700">Smartboard</span>
                        </label>
                        <label class="flex items-center p-3 bg-blue-50 rounded-lg cursor-pointer hover:bg-blue-100 transition">
                            <input type="checkbox" name="equipment[]" value="Whiteboard" class="mr-2 rounded text-blue-500 equipment-checkbox">
                            <span class="text-sm text-gray-700">Whiteboard</span>
                        </label>
                        <label class="flex items-center p-3 bg-blue-50 rounded-lg cursor-pointer hover:bg-blue-100 transition">
                            <input type="checkbox" name="equipment[]" value="Lautsprecher" class="mr-2 rounded text-blue-500 equipment-checkbox">
                            <span class="text-sm text-gray-700">Lautsprecher</span>
                        </label>
                        <label class="flex items-center p-3 bg-blue-50 rounded-lg cursor-pointer hover:bg-blue-100 transition">
                            <input type="checkbox" name="equipment[]" value="WLAN" class="mr-2 rounded text-blue-500 equipment-checkbox">
                            <span class="text-sm text-gray-700">WLAN</span>
                        </label>
                        <label class="flex items-center p-3 bg-blue-50 rounded-lg cursor-pointer hover:bg-blue-100 transition">
                            <input type="checkbox" name="equipment[]" value="Steckdosen" class="mr-2 rounded text-blue-500 equipment-checkbox">
                            <span class="text-sm text-gray-700">Steckdosen</span>
                        </label>
                    </div>
                </div>

                <!-- Angebote fuer Schueler -->
                <div class="md:col-span-2 border-t border-gray-100 pt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-3">
                        <i class="fas fa-graduation-cap mr-2 text-emerald-500"></i>Angebote fuer Schueler
                    </label>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 mb-3">
                        <?php 
                        $offerTypeOptions = ['Ausbildung', 'Duales Studium', 'Studium', 'Praktikum', 'Werkstudent', 'Hospitation', 'Sonstiges'];
                        foreach ($offerTypeOptions as $opt): ?>
                        <label class="flex items-center p-3 bg-emerald-50 rounded-lg cursor-pointer hover:bg-emerald-100 transition">
                            <input type="checkbox" name="offer_types_selected[]" value="<?php echo htmlspecialchars($opt); ?>" class="mr-2 rounded text-emerald-500 offer-type-checkbox">
                            <span class="text-sm text-gray-700"><?php echo htmlspecialchars($opt); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Weiteres Angebot (optional)</label>
                        <input type="text" name="offer_types_custom" id="offer_types_custom"
                               placeholder="z.B. Trainee-Programm, Gap-Year-Stelle..."
                               class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                </div>
                
                <!-- Sichtbarkeitseinstellungen -->
                <div class="md:col-span-2 border-t border-gray-100 pt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-3">
                        <i class="fas fa-eye mr-2 text-gray-400"></i>Fuer Schueler sichtbare Felder
                    </label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex items-center p-3 bg-gray-50 rounded-lg cursor-pointer">
                            <input type="checkbox" name="visible_fields[]" value="name" checked disabled class="mr-2 rounded text-emerald-500">
                            <span class="text-sm text-gray-600">Name (immer sichtbar)</span>
                        </label>
                        <label class="flex items-center p-3 bg-gray-50 rounded-lg cursor-pointer">
                            <input type="checkbox" name="visible_fields[]" value="short_description" checked class="mr-2 rounded text-emerald-500">
                            <span class="text-sm text-gray-600">Kurzbeschreibung</span>
                        </label>
                        <label class="flex items-center p-3 bg-gray-50 rounded-lg cursor-pointer">
                            <input type="checkbox" name="visible_fields[]" value="description" checked class="mr-2 rounded text-emerald-500">
                            <span class="text-sm text-gray-600">Beschreibung</span>
                        </label>
                        <label class="flex items-center p-3 bg-gray-50 rounded-lg cursor-pointer">
                            <input type="checkbox" name="visible_fields[]" value="category" checked class="mr-2 rounded text-emerald-500">
                            <span class="text-sm text-gray-600">Kategorie</span>
                        </label>
                        <label class="flex items-center p-3 bg-gray-50 rounded-lg cursor-pointer">
                            <input type="checkbox" name="visible_fields[]" value="contact_person" class="mr-2 rounded text-emerald-500">
                            <span class="text-sm text-gray-600">Ansprechpartner</span>
                        </label>
                        <label class="flex items-center p-3 bg-gray-50 rounded-lg cursor-pointer">
                            <input type="checkbox" name="visible_fields[]" value="email" class="mr-2 rounded text-emerald-500">
                            <span class="text-sm text-gray-600">E-Mail</span>
                        </label>
                        <label class="flex items-center p-3 bg-gray-50 rounded-lg cursor-pointer">
                            <input type="checkbox" name="visible_fields[]" value="phone" class="mr-2 rounded text-emerald-500">
                            <span class="text-sm text-gray-600">Telefon</span>
                        </label>
                        <label class="flex items-center p-3 bg-gray-50 rounded-lg cursor-pointer">
                            <input type="checkbox" name="visible_fields[]" value="website" checked class="mr-2 rounded text-emerald-500">
                            <span class="text-sm text-gray-600">Webseite</span>
                        </label>
                        <label class="flex items-center p-3 bg-gray-50 rounded-lg cursor-pointer">
                            <input type="checkbox" name="visible_fields[]" value="offer_types" checked class="mr-2 rounded text-emerald-500">
                            <span class="text-sm text-gray-600">Angebote</span>
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="flex gap-3 pt-4 border-t border-gray-100">
                <button type="submit" name="add_exhibitor" id="submitBtn"
                        class="flex-1 bg-emerald-500 text-white py-2.5 rounded-lg hover:bg-emerald-600 transition font-medium">
                    <i class="fas fa-save mr-2"></i>Speichern
                </button>
                <button type="button" onclick="closeModal()"
                        class="px-6 bg-gray-100 text-gray-700 py-2.5 rounded-lg hover:bg-gray-200 transition">
                    Abbrechen
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Document Upload Modal -->
<div id="documentModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-gray-100 px-6 py-4 flex items-center justify-between">
            <h2 id="docModalTitle" class="text-lg font-semibold text-gray-800">Dokumente verwalten</h2>
            <button onclick="closeDocumentModal()" class="text-gray-400 hover:text-gray-600 rounded-lg p-2">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        
        <div class="p-6">
            <!-- Upload Form -->
            <form method="POST" enctype="multipart/form-data" class="mb-6">
                <input type="hidden" name="exhibitor_id" id="doc_exhibitor_id">
                <div class="border-2 border-dashed border-gray-200 rounded-lg p-6 text-center">
                    <i class="fas fa-cloud-upload-alt text-3xl text-gray-300 mb-3"></i>
                    <label class="block mb-2">
                        <span class="text-sm text-gray-500">Datei auswaehlen (max. 10 MB)</span>
                        <input type="file" name="document" required accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png,.gif"
                               class="block w-full text-sm text-gray-500 mt-2 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
                    </label>
                    <button type="submit" name="upload_document" 
                            class="mt-3 bg-emerald-500 text-white px-5 py-2 rounded-lg hover:bg-emerald-600 transition">
                        <i class="fas fa-upload mr-2"></i>Hochladen
                    </button>
                </div>
            </form>
            
            <!-- Documents List -->
            <div id="documentsList">
                <!-- Wird per JavaScript geladen -->
            </div>
        </div>
    </div>
</div>

<script>
// Logo Preview
document.getElementById('logoInput').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('logoImage').src = e.target.result;
            document.getElementById('logoImage').classList.remove('hidden');
            document.getElementById('logoPlaceholder').classList.add('hidden');
        };
        reader.readAsDataURL(file);
    }
});

function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Aussteller hinzufuegen';
    document.getElementById('exhibitorForm').reset();
    document.getElementById('exhibitor_id').value = '';
    document.getElementById('submitBtn').name = 'add_exhibitor';
    document.getElementById('logoImage').classList.add('hidden');
    document.getElementById('logoPlaceholder').classList.remove('hidden');
    document.getElementById('exhibitorModal').classList.remove('hidden');
    document.getElementById('exhibitorModal').classList.add('flex');
}

function openEditModal(exhibitor) {
    document.getElementById('modalTitle').textContent = 'Aussteller bearbeiten';
    document.getElementById('exhibitor_id').value = exhibitor.id;
    document.getElementById('name').value = exhibitor.name;
    document.getElementById('short_description').value = exhibitor.short_description || '';
    document.getElementById('description').value = exhibitor.description || '';
    document.getElementById('category').value = exhibitor.category || '';
    document.getElementById('contact_person').value = exhibitor.contact_person || '';
    document.getElementById('email').value = exhibitor.email || '';
    document.getElementById('phone').value = exhibitor.phone || '';
    document.getElementById('website').value = exhibitor.website || '';
    document.getElementById('jobs').value = exhibitor.jobs || '';
    document.getElementById('features').value = exhibitor.features || '';
    
    // Equipment setzen
    document.querySelectorAll('.equipment-checkbox').forEach(cb => { cb.checked = false; });
    if (exhibitor.equipment) {
        const equipmentArray = exhibitor.equipment.split(',').map(e => e.trim()).filter(e => e);
        document.querySelectorAll('.equipment-checkbox').forEach(cb => {
            cb.checked = equipmentArray.includes(cb.value);
        });
    }
    
    // Angebotstypen setzen
    document.querySelectorAll('.offer-type-checkbox').forEach(cb => { cb.checked = false; });
    document.getElementById('offer_types_custom').value = '';
    if (exhibitor.offer_types) {
        try {
            const offerData = typeof exhibitor.offer_types === 'string' 
                ? JSON.parse(exhibitor.offer_types) 
                : exhibitor.offer_types;
            if (offerData && offerData.selected) {
                document.querySelectorAll('.offer-type-checkbox').forEach(cb => {
                    cb.checked = offerData.selected.includes(cb.value);
                });
            }
            if (offerData && offerData.custom) {
                document.getElementById('offer_types_custom').value = offerData.custom;
            }
        } catch(e) {}
    }
    
    // Logo anzeigen
    if (exhibitor.logo) {
        document.getElementById('logoImage').src = '<?php echo BASE_URL; ?>uploads/' + exhibitor.logo;
        document.getElementById('logoImage').classList.remove('hidden');
        document.getElementById('logoPlaceholder').classList.add('hidden');
    } else {
        document.getElementById('logoImage').classList.add('hidden');
        document.getElementById('logoPlaceholder').classList.remove('hidden');
    }
    
    // Sichtbare Felder setzen
    const visibleFields = exhibitor.visible_fields ? JSON.parse(exhibitor.visible_fields) : ['name', 'short_description', 'description', 'category', 'website'];
    document.querySelectorAll('input[name="visible_fields[]"]').forEach(checkbox => {
        if (checkbox.value !== 'name') {
            checkbox.checked = visibleFields.includes(checkbox.value);
        }
    });
    
    document.getElementById('submitBtn').name = 'edit_exhibitor';
    document.getElementById('exhibitorModal').classList.remove('hidden');
    document.getElementById('exhibitorModal').classList.add('flex');
}

function closeModal() {
    document.getElementById('exhibitorModal').classList.add('hidden');
    document.getElementById('exhibitorModal').classList.remove('flex');
}

function openDocumentModal(exhibitorId, exhibitorName) {
    document.getElementById('docModalTitle').textContent = 'Dokumente - ' + exhibitorName;
    document.getElementById('doc_exhibitor_id').value = exhibitorId;
    document.getElementById('documentModal').classList.remove('hidden');
    document.getElementById('documentModal').classList.add('flex');
    loadDocuments(exhibitorId);
}

function closeDocumentModal() {
    document.getElementById('documentModal').classList.add('hidden');
    document.getElementById('documentModal').classList.remove('flex');
}

function loadDocuments(exhibitorId) {
    fetch(`api/get-documents.php?exhibitor_id=` + exhibitorId)
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('documentsList');
            if (data.documents.length === 0) {
                container.innerHTML = '<p class="text-center text-gray-400 py-6">Keine Dokumente vorhanden</p>';
            } else {
                container.innerHTML = data.documents.map(doc => `
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg mb-2">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-file text-emerald-500"></i>
                            <span class="text-sm text-gray-700">${doc.original_name}</span>
                        </div>
                        <form method="POST" class="inline" onsubmit="return confirm('Wirklich loeschen?')">
                            <input type="hidden" name="document_id" value="${doc.id}">
                            <button type="submit" name="delete_document" class="text-red-500 hover:text-red-600">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                `).join('');
            }
        });
}

// ============================================================
// Tab-System für Aussteller/Branchen
// ============================================================
function switchExhibitorsTab(tabName) {
    // Alle Tabs ausblenden
    document.querySelectorAll('.exhibitors-tab-content').forEach(tab => {
        tab.classList.add('hidden');
    });

    // Alle Tab-Buttons inaktiv setzen
    document.querySelectorAll('.exhibitors-tab-button').forEach(btn => {
        btn.classList.remove('border-emerald-500', 'text-emerald-600');
        btn.classList.add('border-transparent', 'text-gray-500');
    });

    // Aktiven Tab anzeigen
    const activeTab = document.getElementById('tab-' + tabName);
    if (activeTab) {
        activeTab.classList.remove('hidden');
    }

    // Aktiven Button markieren
    const activeBtn = document.querySelector('.exhibitors-tab-button[data-tab="' + tabName + '"]');
    if (activeBtn) {
        activeBtn.classList.add('border-emerald-500', 'text-emerald-600');
        activeBtn.classList.remove('border-transparent', 'text-gray-500');
    }
}

// Beim Laden der Seite den richtigen Tab aktivieren
<?php if (isset($activeTab)): ?>
document.addEventListener('DOMContentLoaded', function() {
    switchExhibitorsTab('<?php echo $activeTab; ?>');
});
<?php endif; ?>

// ============================================================
// Branchen-Verwaltung (von admin-settings.php übernommen)
// ============================================================
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
