<?php
// Kategorien für Filter ermitteln
$stmt = $db->query("SELECT DISTINCT category FROM exhibitors WHERE active = 1 AND category IS NOT NULL AND category != '' ORDER BY category");
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!-- Aussteller-Übersicht -->
<div class="space-y-6">
    <!-- Filter Tabs -->
    <div class="flex flex-wrap items-center gap-2 mb-6">
        <button onclick="filterCategory('')" class="filter-btn active px-4 py-2 text-sm font-medium rounded-lg transition-all" data-category="">
            Alle Aussteller
        </button>
        <?php foreach ($categories as $cat): ?>
        <button onclick="filterCategory('<?php echo htmlspecialchars($cat); ?>')" class="filter-btn px-4 py-2 text-sm font-medium rounded-lg transition-all" data-category="<?php echo htmlspecialchars($cat); ?>">
            <?php echo htmlspecialchars($cat); ?>
        </button>
        <?php endforeach; ?>
    </div>

    <!-- Suchleiste und Aktionen -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="relative max-w-md w-full">
            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
            <input type="text" id="searchInput" placeholder="Aussteller suchen..." 
                   class="w-full pl-10 pr-4 py-2.5 bg-white border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition"
                   onkeyup="filterExhibitors()">
        </div>
        
        <div class="flex items-center gap-2">
            <button onclick="sortExhibitors('name')" class="flex items-center px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition">
                <i class="fas fa-sort-alpha-down mr-2"></i> A-Z
            </button>
            <?php if (isAdmin()): ?>
            <a href="?page=admin-exhibitors&action=add" class="flex items-center px-4 py-2 bg-emerald-500 text-white rounded-lg text-sm font-medium hover:bg-emerald-600 transition shadow-sm">
                <i class="fas fa-plus mr-2"></i> Hinzufügen
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Karten-Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5" id="exhibitorGrid">
        <?php foreach ($exhibitors as $exhibitor): 
            // Branche
            $branche = $exhibitor['category'] ?? 'Allgemein';
            
            // Angebot (aus short_description oder description parsen)
            $angebot = [];
            $desc = strtolower($exhibitor['short_description'] . ' ' . $exhibitor['description']);
            if (strpos($desc, 'ausbildung') !== false) $angebot[] = 'Ausbildung';
            if (strpos($desc, 'studium') !== false || strpos($desc, 'dual') !== false) $angebot[] = 'Duales Studium';
            if (strpos($desc, 'praktikum') !== false) $angebot[] = 'Praktikum';
            if (empty($angebot)) $angebot[] = 'Ausbildung'; // Default
        ?>
        <div class="exhibitor-card bg-white rounded-xl border border-gray-100 p-5"
             data-name="<?php echo strtolower(htmlspecialchars($exhibitor['name'])); ?>"
             data-category="<?php echo htmlspecialchars($exhibitor['category'] ?? ''); ?>">
            
            <!-- Card Header mit Logo und Name -->
            <div class="flex items-start space-x-4 mb-4">
                <div class="w-14 h-14 rounded-xl bg-gray-50 border border-gray-100 flex items-center justify-center overflow-hidden flex-shrink-0">
                    <?php if ($exhibitor['logo']): ?>
                        <img src="<?php echo BASE_URL . 'uploads/' . $exhibitor['logo']; ?>" 
                             alt="<?php echo htmlspecialchars($exhibitor['name']); ?>" 
                             class="w-12 h-12 object-contain">
                    <?php else: ?>
                        <i class="fas fa-building text-gray-300 text-2xl"></i>
                    <?php endif; ?>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="font-semibold text-gray-900 text-base leading-tight mb-1 truncate">
                        <?php echo htmlspecialchars($exhibitor['name']); ?>
                    </h3>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-50 text-emerald-700">
                        <?php echo htmlspecialchars($branche); ?>
                    </span>
                </div>
            </div>

            <!-- Angebot -->
            <div class="mb-5">
                <p class="text-xs uppercase tracking-wider text-gray-400 font-semibold mb-2">Angebot</p>
                <div class="flex flex-wrap gap-1.5">
                    <?php foreach ($angebot as $a): ?>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-700">
                        <?php echo $a; ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Buttons -->
            <div class="flex items-center gap-2 pt-4 border-t border-gray-100">
                <button onclick="openExhibitorModal(<?php echo $exhibitor['id']; ?>)" 
                        class="flex-1 flex items-center justify-center px-3 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition">
                    <i class="fas fa-info-circle mr-2"></i> Mehr Infos
                </button>
                <?php if (!isTeacher() && !isAdmin()): ?>
                <a href="?page=registration&exhibitor=<?php echo $exhibitor['id']; ?>" 
                   class="flex-1 flex items-center justify-center px-3 py-2 bg-emerald-500 text-white rounded-lg text-sm font-medium hover:bg-emerald-600 transition">
                    <i class="fas fa-user-plus mr-2"></i> Einschreiben
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if (empty($exhibitors)): ?>
    <div class="text-center py-16">
        <i class="fas fa-building text-6xl text-gray-200 mb-4"></i>
        <p class="text-gray-500 text-lg">Keine Aussteller gefunden</p>
    </div>
    <?php endif; ?>
</div>

<!-- Modal für Aussteller-Details -->
<div id="exhibitorModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50 p-4" onclick="closeModalOnBackdrop(event)">
    <div class="modal-content bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden flex flex-col transform scale-95 opacity-0 transition-all duration-300" onclick="event.stopPropagation()">
        <!-- Modal Header -->
        <div class="bg-white border-b border-gray-100 px-6 py-4 flex items-center justify-between">
            <h2 id="modalTitle" class="text-xl font-bold text-gray-800">Unternehmensdetails</h2>
            <button onclick="closeExhibitorModal()" class="text-gray-400 hover:text-gray-600 rounded-lg p-2 hover:bg-gray-100 transition">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <div id="modalBody" class="p-6 overflow-y-auto flex-1">
            <div class="flex items-center justify-center py-12">
                <i class="fas fa-spinner fa-spin text-3xl text-emerald-500"></i>
            </div>
        </div>

        <!-- Modal Footer -->
        <div id="modalFooter" class="bg-gray-50 border-t border-gray-100 px-6 py-4 flex justify-end gap-3">
            <button onclick="closeExhibitorModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-300 transition">
                Schließen
            </button>
            <a id="modalRegisterBtn" href="#" class="px-4 py-2 bg-emerald-500 text-white rounded-lg text-sm font-medium hover:bg-emerald-600 transition">
                <i class="fas fa-user-plus mr-2"></i> Einschreiben
            </a>
        </div>
    </div>
</div>

<style>
    .filter-btn {
        background-color: white;
        color: #6b7280;
        border: 1px solid #e5e7eb;
    }
    .filter-btn:hover {
        background-color: #f3f4f6;
        color: #374151;
    }
    .filter-btn.active {
        background-color: #10b981;
        color: white;
        border-color: #10b981;
    }
</style>

<script>
let currentExhibitorId = null;
let currentFilter = '';

function openExhibitorModal(exhibitorId) {
    currentExhibitorId = exhibitorId;
    const modal = document.getElementById('exhibitorModal');
    const content = modal.querySelector('.modal-content');
    
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    
    // Animation
    setTimeout(() => {
        content.classList.remove('scale-95', 'opacity-0');
        content.classList.add('scale-100', 'opacity-100');
    }, 10);
    
    // Update register button link
    document.getElementById('modalRegisterBtn').href = '?page=registration&exhibitor=' + exhibitorId;
    
    // Hide register button for teachers/admins
    <?php if (isTeacher() || isAdmin()): ?>
    document.getElementById('modalRegisterBtn').style.display = 'none';
    <?php endif; ?>
    
    // Load data
    loadExhibitorDetails(exhibitorId);
    document.body.style.overflow = 'hidden';
}

function closeExhibitorModal() {
    const modal = document.getElementById('exhibitorModal');
    const content = modal.querySelector('.modal-content');
    
    content.classList.add('scale-95', 'opacity-0');
    content.classList.remove('scale-100', 'opacity-100');
    
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

function loadExhibitorDetails(exhibitorId) {
    const modalBody = document.getElementById('modalBody');
    modalBody.innerHTML = '<div class="flex items-center justify-center py-12"><i class="fas fa-spinner fa-spin text-3xl text-emerald-500"></i></div>';
    
    fetch('api/get-exhibitor.php?id=' + exhibitorId + '&tab=details')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('modalTitle').textContent = data.exhibitor.name;
                modalBody.innerHTML = data.content;
            } else {
                modalBody.innerHTML = '<div class="text-center py-12 text-red-500">Fehler beim Laden</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            modalBody.innerHTML = '<div class="text-center py-12 text-red-500">Fehler beim Laden</div>';
        });
}

// ESC to close
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeExhibitorModal();
});

// Filter functions
function filterCategory(category) {
    currentFilter = category;
    
    // Update active button
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.getAttribute('data-category') === category) {
            btn.classList.add('active');
        }
    });
    
    filterExhibitors();
}

function filterExhibitors() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const cards = document.querySelectorAll('.exhibitor-card');
    
    cards.forEach(card => {
        const name = card.getAttribute('data-name');
        const cardCategory = card.getAttribute('data-category');
        
        const matchesSearch = name.includes(searchTerm);
        const matchesCategory = !currentFilter || cardCategory === currentFilter;
        
        if (matchesSearch && matchesCategory) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

function sortExhibitors(by) {
    const grid = document.getElementById('exhibitorGrid');
    const cards = Array.from(grid.querySelectorAll('.exhibitor-card'));
    
    cards.sort((a, b) => {
        const nameA = a.getAttribute('data-name');
        const nameB = b.getAttribute('data-name');
        return nameA.localeCompare(nameB);
    });
    
    cards.forEach(card => grid.appendChild(card));
}
</script>
