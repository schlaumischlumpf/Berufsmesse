<?php
// Admin Aussteller Verwaltung

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_exhibitor'])) {
        // Neuen Aussteller hinzufügen
        $name = sanitize($_POST['name']);
        $shortDesc = sanitize($_POST['short_description']);
        $description = sanitize($_POST['description']);
        $category = sanitize($_POST['category'] ?? '');
        $contactPerson = sanitize($_POST['contact_person'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $website = sanitize($_POST['website'] ?? '');
        
        // Sichtbare Felder als JSON speichern
        $visibleFields = isset($_POST['visible_fields']) ? $_POST['visible_fields'] : ['name', 'short_description', 'description', 'category', 'website'];
        $visibleFieldsJson = json_encode($visibleFields);
        
        $stmt = $db->prepare("INSERT INTO exhibitors (name, short_description, description, category, contact_person, email, phone, website, visible_fields) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$name, $shortDesc, $description, $category, $contactPerson, $email, $phone, $website, $visibleFieldsJson])) {
            $message = ['type' => 'success', 'text' => 'Aussteller erfolgreich hinzugefügt'];
        } else {
            $message = ['type' => 'error', 'text' => 'Fehler beim Hinzufügen'];
        }
    } elseif (isset($_POST['edit_exhibitor'])) {
        // Aussteller bearbeiten
        $id = intval($_POST['exhibitor_id']);
        $name = sanitize($_POST['name']);
        $shortDesc = sanitize($_POST['short_description']);
        $description = sanitize($_POST['description']);
        $category = sanitize($_POST['category'] ?? '');
        $contactPerson = sanitize($_POST['contact_person'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $website = sanitize($_POST['website'] ?? '');
        
        // Sichtbare Felder als JSON speichern
        $visibleFields = isset($_POST['visible_fields']) ? $_POST['visible_fields'] : ['name', 'short_description', 'description', 'category', 'website'];
        $visibleFieldsJson = json_encode($visibleFields);
        
        $stmt = $db->prepare("UPDATE exhibitors SET name = ?, short_description = ?, description = ?, category = ?, 
                              contact_person = ?, email = ?, phone = ?, website = ?, visible_fields = ? WHERE id = ?");
        if ($stmt->execute([$name, $shortDesc, $description, $category, $contactPerson, $email, $phone, $website, $visibleFieldsJson, $id])) {
            $message = ['type' => 'success', 'text' => 'Aussteller erfolgreich aktualisiert'];
        } else {
            $message = ['type' => 'error', 'text' => 'Fehler beim Aktualisieren'];
        }
    } elseif (isset($_POST['delete_exhibitor'])) {
        // Aussteller löschen
        $id = intval($_POST['exhibitor_id']);
        $stmt = $db->prepare("DELETE FROM exhibitors WHERE id = ?");
        if ($stmt->execute([$id])) {
            $message = ['type' => 'success', 'text' => 'Aussteller erfolgreich gelöscht'];
        } else {
            $message = ['type' => 'error', 'text' => 'Fehler beim Löschen'];
        }
    } elseif (isset($_POST['upload_document'])) {
        // Dokument hochladen
        $exhibitorId = intval($_POST['exhibitor_id']);
        if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
            $result = uploadFile($_FILES['document'], $exhibitorId);
            $message = $result;
        }
    } elseif (isset($_POST['delete_document'])) {
        // Dokument löschen
        $documentId = intval($_POST['document_id']);
        if (deleteFile($documentId)) {
            $message = ['success' => true, 'message' => 'Dokument erfolgreich gelöscht'];
        }
    }
}

// Alle Aussteller laden mit Raum-Kapazität
$stmt = $db->query("
    SELECT e.*, r.capacity as room_capacity 
    FROM exhibitors e 
    LEFT JOIN rooms r ON e.room_id = r.id 
    ORDER BY e.name ASC
");
$allExhibitors = $stmt->fetchAll();
?>

<div class="space-y-6">
    <?php if (isset($message)): ?>
    <div class="animate-pulse">
        <?php if (($message['type'] ?? $message['success']) === 'success' || ($message['success'] ?? false)): ?>
            <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 mr-3"></i>
                    <p class="text-green-700"><?php echo $message['text'] ?? $message['message']; ?></p>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                    <p class="text-red-700"><?php echo $message['text'] ?? $message['message']; ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Add New Exhibitor Button -->
    <div class="flex justify-end">
        <button onclick="openAddModal()" class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-6 py-3 rounded-lg hover:from-purple-700 hover:to-indigo-700 transition font-semibold shadow-lg">
            <i class="fas fa-plus-circle mr-2"></i>Neuer Aussteller
        </button>
    </div>

    <!-- Exhibitors List -->
    <div class="grid grid-cols-1 gap-6">
        <?php foreach ($allExhibitors as $exhibitor): 
            // Raum-basierte Kapazität berechnen
            $roomCapacity = $exhibitor['room_capacity'] ? intval($exhibitor['room_capacity']) : 0;
            $totalCapacity = $roomCapacity > 0 ? floor($roomCapacity / 3) * 3 : 0;
            
            // Registrierungen zählen
            $stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) as count FROM registrations WHERE exhibitor_id = ?");
            $stmt->execute([$exhibitor['id']]);
            $regCount = $stmt->fetch()['count'];
            
            // Dokumente laden
            $stmt = $db->prepare("SELECT * FROM exhibitor_documents WHERE exhibitor_id = ?");
            $stmt->execute([$exhibitor['id']]);
            $documents = $stmt->fetchAll();
        ?>
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-purple-500 to-indigo-500 text-white px-6 py-4">
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <div class="flex-1">
                        <h3 class="text-xl font-bold mb-1"><?php echo htmlspecialchars($exhibitor['name']); ?></h3>
                        <p class="text-sm text-purple-100">
                            <?php echo $regCount; ?> / <?php echo $totalCapacity; ?> Plätze belegt
                            <?php if ($totalCapacity === 0): ?>
                                <span class="ml-2 text-yellow-300">(Kein Raum zugewiesen)</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($exhibitor)); ?>)" 
                                class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg transition">
                            <i class="fas fa-edit mr-2"></i>Bearbeiten
                        </button>
                        <button onclick="openDocumentModal(<?php echo $exhibitor['id']; ?>, '<?php echo htmlspecialchars($exhibitor['name']); ?>')" 
                                class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg transition">
                            <i class="fas fa-file-upload mr-2"></i>Dokumente
                        </button>
                        <form method="POST" class="inline" onsubmit="return confirm('Wirklich löschen?')">
                            <input type="hidden" name="exhibitor_id" value="<?php echo $exhibitor['id']; ?>">
                            <button type="submit" name="delete_exhibitor" class="bg-red-500/80 hover:bg-red-600 px-4 py-2 rounded-lg transition">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Body -->
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <div>
                        <p class="text-sm text-gray-600 font-semibold mb-1">Kurzbeschreibung</p>
                        <p class="text-gray-800"><?php echo htmlspecialchars($exhibitor['short_description'] ?? '-'); ?></p>
                    </div>
                    <?php if ($exhibitor['category']): ?>
                    <div class="mt-2">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs bg-purple-100 text-purple-800">
                            <i class="fas fa-tag mr-1"></i><?php echo htmlspecialchars($exhibitor['category']); ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
                    <div>
                        <p class="text-sm text-gray-600 font-semibold mb-1">Kontakt</p>
                        <p class="text-gray-800 text-sm">
                            <?php if ($exhibitor['contact_person']): ?>
                                <i class="fas fa-user mr-1"></i><?php echo htmlspecialchars($exhibitor['contact_person']); ?><br>
                            <?php endif; ?>
                            <?php if ($exhibitor['email']): ?>
                                <i class="fas fa-envelope mr-1"></i><?php echo htmlspecialchars($exhibitor['email']); ?><br>
                            <?php endif; ?>
                            <?php if ($exhibitor['website']): ?>
                                <i class="fas fa-globe mr-1"></i><?php echo htmlspecialchars($exhibitor['website']); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                
                <div>
                    <p class="text-sm text-gray-600 font-semibold mb-1">Beschreibung</p>
                    <p class="text-gray-700 text-sm"><?php echo nl2br(htmlspecialchars(substr($exhibitor['description'] ?? '', 0, 200))); ?>...</p>
                </div>

                <?php if (!empty($documents)): ?>
                <div class="mt-4">
                    <p class="text-sm text-gray-600 font-semibold mb-2">Dokumente (<?php echo count($documents); ?>)</p>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($documents as $doc): ?>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs bg-blue-100 text-blue-800">
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
</div>

<!-- Add/Edit Modal -->
<div id="exhibitorModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-3xl w-full max-h-[90vh] overflow-y-auto">
        <style>
        /* Custom Select Styling for Category Dropdown */
        #category {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%236B7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
            background-position: right 0.75rem center;
            background-repeat: no-repeat;
            background-size: 1.25em 1.25em;
            padding-right: 2.5rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        #category:hover {
            border-color: #A855F7;
            box-shadow: 0 0 0 3px rgba(168, 85, 247, 0.1);
        }

        #category:focus {
            outline: none;
            border-color: #A855F7;
            box-shadow: 0 0 0 3px rgba(168, 85, 247, 0.2);
        }

        #category option {
            padding: 12px 16px;
            font-size: 14px;
            color: #1F2937;
            background-color: white;
        }

        #category option:checked {
            background-color: #F3E8FF;
            color: #7C3AED;
            font-weight: 500;
        }

        #category option[value=""] {
            color: #9CA3AF;
        }
        </style>
        
        <div class="sticky top-0 bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-6 py-4 flex items-center justify-between">
            <h2 id="modalTitle" class="text-2xl font-bold">Aussteller hinzufügen</h2>
            <button onclick="closeModal()" class="text-white hover:bg-white/20 rounded-lg p-2">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <form method="POST" class="p-6 space-y-4" id="exhibitorForm">
            <input type="hidden" name="exhibitor_id" id="exhibitor_id">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Name *</label>
                    <input type="text" name="name" id="name" required 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                </div>
                
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Kurzbeschreibung *</label>
                    <input type="text" name="short_description" id="short_description" required maxlength="500"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                </div>
                
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-tag mr-1"></i>Kategorie *
                    </label>
                    <select name="category" id="category" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                        <option value="">-- Bitte wählen --</option>
                        <option value="Automobilindustrie">Automobilindustrie</option>
                        <option value="Handwerk">Handwerk</option>
                        <option value="Gesundheitswesen">Gesundheitswesen</option>
                        <option value="IT & Software">IT & Software</option>
                        <option value="Dienstleistung">Dienstleistung</option>
                        <option value="Öffentlicher Dienst">Öffentlicher Dienst</option>
                        <option value="Bildung">Bildung</option>
                        <option value="Gastronomie & Hotellerie">Gastronomie & Hotellerie</option>
                        <option value="Handel & Verkauf">Handel & Verkauf</option>
                        <option value="Sonstiges">Sonstiges</option>
                    </select>
                </div>
                
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Beschreibung *</label>
                    <textarea name="description" id="description" required rows="4"
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500"></textarea>
                </div>
                
                <div class="md:col-span-2 bg-blue-50 border-l-4 border-blue-500 p-4 rounded">
                    <p class="text-sm text-blue-800">
                        <i class="fas fa-info-circle mr-2"></i>
                        <strong>Hinweis:</strong> Die Kapazität wird automatisch basierend auf dem zugewiesenen Raum berechnet. 
                        Bitte weisen Sie den Aussteller in der Raumverwaltung einem Raum zu.
                    </p>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Ansprechpartner</label>
                    <input type="text" name="contact_person" id="contact_person"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">E-Mail</label>
                    <input type="email" name="email" id="email"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Telefon</label>
                    <input type="tel" name="phone" id="phone"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                </div>
                
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Webseite</label>
                    <input type="text" name="website" id="website" placeholder="www.beispiel.de"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                </div>
                
                <!-- Sichtbarkeitseinstellungen (Issue #9) -->
                <div class="md:col-span-2 border-t border-gray-200 pt-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-3">
                        <i class="fas fa-eye mr-2"></i>Für Schüler sichtbare Felder
                    </label>
                    <p class="text-xs text-gray-600 mb-3">Wählen Sie, welche Informationen für Schüler angezeigt werden sollen:</p>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 cursor-pointer transition">
                            <input type="checkbox" name="visible_fields[]" value="name" checked disabled class="mr-2 rounded text-purple-600">
                            <span class="text-sm">Name (immer sichtbar)</span>
                        </label>
                        <label class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 cursor-pointer transition">
                            <input type="checkbox" name="visible_fields[]" value="short_description" checked class="mr-2 rounded text-purple-600">
                            <span class="text-sm">Kurzbeschreibung</span>
                        </label>
                        <label class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 cursor-pointer transition">
                            <input type="checkbox" name="visible_fields[]" value="description" checked class="mr-2 rounded text-purple-600">
                            <span class="text-sm">Beschreibung</span>
                        </label>
                        <label class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 cursor-pointer transition">
                            <input type="checkbox" name="visible_fields[]" value="category" checked class="mr-2 rounded text-purple-600">
                            <span class="text-sm">Kategorie</span>
                        </label>
                        <label class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 cursor-pointer transition">
                            <input type="checkbox" name="visible_fields[]" value="contact_person" class="mr-2 rounded text-purple-600">
                            <span class="text-sm">Ansprechpartner</span>
                        </label>
                        <label class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 cursor-pointer transition">
                            <input type="checkbox" name="visible_fields[]" value="email" class="mr-2 rounded text-purple-600">
                            <span class="text-sm">E-Mail</span>
                        </label>
                        <label class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 cursor-pointer transition">
                            <input type="checkbox" name="visible_fields[]" value="phone" class="mr-2 rounded text-purple-600">
                            <span class="text-sm">Telefon</span>
                        </label>
                        <label class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 cursor-pointer transition">
                            <input type="checkbox" name="visible_fields[]" value="website" checked class="mr-2 rounded text-purple-600">
                            <span class="text-sm">Webseite</span>
                        </label>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">
                        <i class="fas fa-info-circle mr-1"></i>
                        Kontaktinformationen werden standardmäßig ausgeblendet und können bei Bedarf aktiviert werden.
                    </p>
                </div>
            </div>
            
            <div class="flex gap-3 pt-4">
                <button type="submit" name="add_exhibitor" id="submitBtn"
                        class="flex-1 bg-gradient-to-r from-purple-600 to-indigo-600 text-white py-3 rounded-lg hover:from-purple-700 hover:to-indigo-700 transition font-semibold">
                    <i class="fas fa-save mr-2"></i>Speichern
                </button>
                <button type="button" onclick="closeModal()"
                        class="px-6 bg-gray-200 text-gray-700 py-3 rounded-lg hover:bg-gray-300 transition">
                    Abbrechen
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Document Upload Modal -->
<div id="documentModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-6 py-4 flex items-center justify-between">
            <h2 id="docModalTitle" class="text-2xl font-bold">Dokumente verwalten</h2>
            <button onclick="closeDocumentModal()" class="text-white hover:bg-white/20 rounded-lg p-2">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <div class="p-6">
            <!-- Upload Form -->
            <form method="POST" enctype="multipart/form-data" class="mb-6">
                <input type="hidden" name="exhibitor_id" id="doc_exhibitor_id">
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
                    <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-3"></i>
                    <label class="block mb-2">
                        <span class="text-sm text-gray-600">Datei auswählen (max. 10 MB)</span>
                        <input type="file" name="document" required accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png,.gif"
                               class="block w-full text-sm text-gray-500 mt-2 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100">
                    </label>
                    <button type="submit" name="upload_document" 
                            class="mt-3 bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition">
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
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Aussteller hinzufügen';
    document.getElementById('exhibitorForm').reset();
    document.getElementById('exhibitor_id').value = '';
    document.getElementById('submitBtn').name = 'add_exhibitor';
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
    
    // Sichtbare Felder setzen
    const visibleFields = exhibitor.visible_fields ? JSON.parse(exhibitor.visible_fields) : ['name', 'short_description', 'description', 'category', 'website'];
    document.querySelectorAll('input[name="visible_fields[]"]').forEach(checkbox => {
        if (checkbox.value !== 'name') { // name ist immer aktiviert
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
    fetch(`api/get-documents.php?exhibitor_id=${exhibitorId}`)
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('documentsList');
            if (data.documents.length === 0) {
                container.innerHTML = '<p class="text-center text-gray-500 py-8">Keine Dokumente vorhanden</p>';
            } else {
                container.innerHTML = data.documents.map(doc => `
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg mb-2">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-file text-blue-500"></i>
                            <span class="text-sm font-medium">${doc.original_name}</span>
                        </div>
                        <form method="POST" class="inline" onsubmit="return confirm('Wirklich löschen?')">
                            <input type="hidden" name="document_id" value="${doc.id}">
                            <button type="submit" name="delete_document" class="text-red-600 hover:text-red-700">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                `).join('');
            }
        });
}
</script>
