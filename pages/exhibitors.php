<!-- Aussteller Übersicht mit Card-Design -->

<!-- Filter & Suche -->
<div class="mb-6 bg-white rounded-xl shadow-md p-6 border border-gray-200">
    <div class="flex flex-col md:flex-row gap-4">
        <div class="flex-1">
            <label class="block text-sm font-semibold text-gray-700 mb-2">
                <i class="fas fa-search mr-2"></i>Suche nach Name
            </label>
            <input type="text" id="searchInput" placeholder="Aussteller suchen..." 
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                   onkeyup="filterExhibitors()">
        </div>
        <div class="flex-1">
            <label class="block text-sm font-semibold text-gray-700 mb-2">
                <i class="fas fa-tag mr-2"></i>Kategorie
            </label>
            <select id="categoryFilter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                    onchange="filterExhibitors()">
                <option value="">Alle Kategorien</option>
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
    </div>
    <div id="filterInfo" class="mt-3 text-sm text-gray-600"></div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="exhibitorGrid">
    <?php foreach ($exhibitors as $exhibitor): 
        // Anzahl registrierter Schüler für diesen Aussteller
        $stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) as count FROM registrations WHERE exhibitor_id = ?");
        $stmt->execute([$exhibitor['id']]);
        $registeredCount = $stmt->fetch()['count'];
        $availableSlots = $exhibitor['total_slots'] - $registeredCount;
    ?>
    <div class="bg-white rounded-xl shadow-md cursor-pointer overflow-hidden border border-gray-200 hover:shadow-lg transition-shadow duration-300 exhibitor-card" 
         data-name="<?php echo strtolower(htmlspecialchars($exhibitor['name'])); ?>"
         data-category="<?php echo htmlspecialchars($exhibitor['category'] ?? ''); ?>"
         onclick="openExhibitorModal(<?php echo $exhibitor['id']; ?>)">
        <!-- Card Header -->
        <div class="h-32 bg-gray-50 relative border-b border-gray-200">
            <div class="absolute inset-0 flex items-center justify-center">
                <?php if ($exhibitor['logo']): ?>
                    <img src="<?php echo BASE_URL . 'uploads/' . $exhibitor['logo']; ?>" 
                         alt="<?php echo htmlspecialchars($exhibitor['name']); ?>" 
                         class="h-20 w-20 object-contain bg-white rounded-lg p-2 shadow-sm">
                <?php else: ?>
                    <div class="h-20 w-20 bg-white rounded-lg flex items-center justify-center shadow-sm border border-gray-200">
                        <i class="fas fa-building text-4xl text-gray-400"></i>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Verfügbarkeits-Badge -->
            <div class="absolute top-3 right-3">
                <?php if ($availableSlots > 5): ?>
                    <span class="bg-white text-green-700 text-xs font-bold px-3 py-1 rounded-full shadow border border-green-200">
                        Verfügbar
                    </span>
                <?php elseif ($availableSlots > 0): ?>
                    <span class="bg-white text-orange-700 text-xs font-bold px-3 py-1 rounded-full shadow border border-orange-200">
                        Wenige Plätze
                    </span>
                <?php else: ?>
                    <span class="bg-white text-red-700 text-xs font-bold px-3 py-1 rounded-full shadow border border-red-200">
                        Ausgebucht
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Card Body -->
        <div class="p-6">
            <?php if ($exhibitor['category']): ?>
            <div class="mb-2">
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-800">
                    <i class="fas fa-tag mr-1"></i><?php echo htmlspecialchars($exhibitor['category']); ?>
                </span>
            </div>
            <?php endif; ?>
            
            <h3 class="text-xl font-bold text-gray-800 mb-2 line-clamp-1">
                <?php echo htmlspecialchars($exhibitor['name']); ?>
            </h3>
            
            <p class="text-gray-600 text-sm mb-4 line-clamp-2">
                <?php echo htmlspecialchars($exhibitor['short_description'] ?? ''); ?>
            </p>

            <!-- Stats -->
            <div class="flex items-center justify-between text-sm">
                <div class="flex items-center text-gray-500">
                    <i class="fas fa-users mr-2"></i>
                    <span><?php echo $registeredCount; ?> / <?php echo $exhibitor['total_slots']; ?> Plätze</span>
                </div>
                
                <button class="text-blue-600 font-semibold hover:text-blue-700 transition">
                    Mehr erfahren <i class="fas fa-arrow-right ml-1"></i>
                </button>
            </div>

            <!-- Progress Bar -->
            <div class="mt-4">
                <div class="w-full bg-gray-100 rounded-full h-2 border border-gray-200">
                    <?php 
                    $percentage = ($exhibitor['total_slots'] > 0) ? ($registeredCount / $exhibitor['total_slots'] * 100) : 0;
                    $colorClass = $percentage >= 90 ? 'bg-gray-800' : ($percentage >= 70 ? 'bg-gray-600' : 'bg-gray-500');
                    ?>
                    <div class="<?php echo $colorClass; ?> h-2 rounded-full transition-all duration-500" 
                         style="width: <?php echo min($percentage, 100); ?>%"></div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Modal für Aussteller-Details -->
<div id="exhibitorModal" class="modal fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4" onclick="closeModalOnBackdrop(event)">
    <div class="modal-content bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden" onclick="event.stopPropagation()">
        <!-- Modal Header -->
        <div class="sticky top-0 bg-blue-600 text-white px-6 py-4 flex items-center justify-between z-10">
            <h2 id="modalTitle" class="text-2xl font-bold">Aussteller Details</h2>
            <button onclick="closeExhibitorModal()" class="text-white hover:bg-white/20 rounded-lg p-2 transition">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <!-- Tabs -->
        <div class="border-b border-gray-200 bg-white sticky top-[72px] z-10">
            <nav class="flex overflow-x-auto">
                <button onclick="switchTab('info')" id="tab-info" class="tab-button px-6 py-4 font-semibold text-blue-600 border-b-2 border-blue-600 whitespace-nowrap">
                    <i class="fas fa-info-circle mr-2"></i>Informationen
                </button>
                <button onclick="switchTab('documents')" id="tab-documents" class="tab-button px-6 py-4 font-semibold text-gray-500 hover:text-gray-700 border-b-2 border-transparent whitespace-nowrap">
                    <i class="fas fa-file-download mr-2"></i>Dokumente
                </button>
                <button onclick="switchTab('contact')" id="tab-contact" class="tab-button px-6 py-4 font-semibold text-gray-500 hover:text-gray-700 border-b-2 border-transparent whitespace-nowrap">
                    <i class="fas fa-address-card mr-2"></i>Kontakt
                </button>
            </nav>
        </div>

        <!-- Modal Body -->
        <div id="modalBody" class="p-6 overflow-y-auto" style="max-height: calc(90vh - 200px);">
            <!-- Content wird per JavaScript geladen -->
        </div>
    </div>
</div>

<script>
let currentExhibitorId = null;

function openExhibitorModal(exhibitorId) {
    currentExhibitorId = exhibitorId;
    const modal = document.getElementById('exhibitorModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    
    // Animation
    setTimeout(() => {
        modal.style.opacity = '1';
        modal.querySelector('.modal-content').style.transform = 'scale(1)';
    }, 10);
    
    // Daten laden
    loadExhibitorData(exhibitorId, 'info');
    document.body.style.overflow = 'hidden';
}

function closeExhibitorModal() {
    const modal = document.getElementById('exhibitorModal');
    modal.style.opacity = '0';
    modal.querySelector('.modal-content').style.transform = 'scale(0.95)';
    
    setTimeout(() => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = 'auto';
    }, 300);
}

function closeModalOnBackdrop(event) {
    if (event.target.id === 'exhibitorModal') {
        closeExhibitorModal();
    }
}

function switchTab(tabName) {
    // Tab-Buttons aktualisieren
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('text-blue-600', 'border-blue-600');
        btn.classList.add('text-gray-500', 'border-transparent');
    });
    
    const activeTab = document.getElementById(`tab-${tabName}`);
    activeTab.classList.remove('text-gray-500', 'border-transparent');
    activeTab.classList.add('text-blue-600', 'border-blue-600');
    
    // Content laden
    loadExhibitorData(currentExhibitorId, tabName);
}

function loadExhibitorData(exhibitorId, tab) {
    const modalBody = document.getElementById('modalBody');
    modalBody.innerHTML = '<div class="flex items-center justify-center py-12"><i class="fas fa-spinner fa-spin text-4xl text-blue-600"></i></div>';
    
    fetch(`api/get-exhibitor.php?id=${exhibitorId}&tab=${tab}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('modalTitle').textContent = data.exhibitor.name;
                modalBody.innerHTML = data.content;
            } else {
                modalBody.innerHTML = '<div class="text-center py-12 text-red-600">Fehler beim Laden der Daten</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            modalBody.innerHTML = '<div class="text-center py-12 text-red-600">Fehler beim Laden der Daten</div>';
        });
}

// ESC-Taste zum Schließen
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeExhibitorModal();
    }
});

// Initial Modal Style
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('exhibitorModal');
    modal.style.opacity = '0';
    modal.querySelector('.modal-content').style.transform = 'scale(0.95)';
});

// Filter-Funktion
function filterExhibitors() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const category = document.getElementById('categoryFilter').value;
    const cards = document.querySelectorAll('.exhibitor-card');
    let visibleCount = 0;
    
    cards.forEach(card => {
        const name = card.getAttribute('data-name');
        const cardCategory = card.getAttribute('data-category');
        
        const matchesSearch = name.includes(searchTerm);
        const matchesCategory = !category || cardCategory === category;
        
        if (matchesSearch && matchesCategory) {
            card.style.display = 'block';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });
    
    // Info anzeigen
    const filterInfo = document.getElementById('filterInfo');
    if (visibleCount === 0) {
        filterInfo.innerHTML = '<i class="fas fa-info-circle mr-2"></i>Keine Aussteller gefunden';
        filterInfo.classList.add('text-orange-600');
    } else {
        filterInfo.innerHTML = `<i class="fas fa-check-circle mr-2"></i>${visibleCount} Aussteller gefunden`;
        filterInfo.classList.remove('text-orange-600');
    }
}
</script>
