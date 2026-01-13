<?php
// Kategorien für Filter ermitteln
$stmt = $db->query("SELECT DISTINCT category FROM exhibitors WHERE active = 1 AND category IS NOT NULL AND category != '' ORDER BY category");
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!-- Aussteller-Übersicht - Stunning Design -->
<div class="space-y-8">
    <!-- Page Header with Animation -->
    <div class="relative overflow-hidden bg-gradient-to-br from-white via-green-50/30 to-purple-50/30 rounded-3xl p-8 border border-gray-100 shadow-lg">
        <div class="absolute top-0 right-0 w-64 h-64 bg-gradient-to-br from-primary-400/10 to-accent-400/10 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2"></div>
        <div class="relative">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                <div>
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-12 h-12 bg-gradient-to-br from-primary-500 to-primary-600 rounded-2xl flex items-center justify-center shadow-lg shadow-primary-500/30">
                            <i class="fas fa-building text-white text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl font-extrabold text-gray-900 font-display">Unternehmen</h1>
                            <p class="text-gray-500">Entdecke <?php echo count($exhibitors); ?> spannende Arbeitgeber</p>
                        </div>
                    </div>
                </div>
                
                <!-- Search and Actions -->
                <div class="flex flex-col sm:flex-row gap-3">
                    <div class="relative">
                        <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input type="text" id="searchInput" placeholder="Unternehmen suchen..." 
                               class="w-full sm:w-80 pl-12 pr-4 py-3 bg-white border-2 border-gray-200 rounded-2xl text-sm focus:outline-none focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 transition-all duration-300"
                               onkeyup="filterExhibitors()">
                    </div>
                    <?php if (isAdmin()): ?>
                    <a href="?page=admin-exhibitors&action=add" class="flex items-center justify-center gap-2 px-6 py-3 bg-gradient-to-r from-primary-500 to-primary-600 text-white rounded-2xl font-semibold shadow-lg shadow-primary-500/30 hover:shadow-xl hover:shadow-primary-500/40 hover:-translate-y-0.5 transition-all duration-300">
                        <i class="fas fa-plus"></i>
                        <span>Hinzufügen</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Pills with Glowing Effect -->
    <div class="flex flex-wrap items-center gap-2">
        <button onclick="filterCategory('')" class="filter-btn active group relative px-5 py-2.5 rounded-full font-medium text-sm transition-all duration-300 overflow-hidden" data-category="">
            <span class="relative z-10 flex items-center gap-2">
                <i class="fas fa-layer-group"></i>
                Alle Aussteller
            </span>
        </button>
        <?php foreach ($categories as $cat): ?>
        <button onclick="filterCategory('<?php echo htmlspecialchars($cat); ?>')" class="filter-btn group relative px-5 py-2.5 rounded-full font-medium text-sm transition-all duration-300 overflow-hidden" data-category="<?php echo htmlspecialchars($cat); ?>">
            <span class="relative z-10"><?php echo htmlspecialchars($cat); ?></span>
        </button>
        <?php endforeach; ?>
    </div>

    <!-- Karten-Grid with Staggered Animation -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6" id="exhibitorGrid">
        <?php foreach ($exhibitors as $index => $exhibitor): 
            // Branche
            $branche = $exhibitor['category'] ?? 'Allgemein';
            
            // Angebot (aus short_description oder description parsen)
            $angebot = [];
            $desc = strtolower($exhibitor['short_description'] . ' ' . $exhibitor['description']);
            if (strpos($desc, 'ausbildung') !== false) $angebot[] = 'Ausbildung';
            if (strpos($desc, 'studium') !== false || strpos($desc, 'dual') !== false) $angebot[] = 'Duales Studium';
            if (strpos($desc, 'praktikum') !== false) $angebot[] = 'Praktikum';
            if (empty($angebot)) $angebot[] = 'Ausbildung'; // Default
            
            // Random gradient for variety
            $gradients = [
                'from-primary-500 to-emerald-500',
                'from-accent-500 to-pink-500',
                'from-blue-500 to-cyan-500',
                'from-orange-500 to-amber-500',
                'from-indigo-500 to-purple-500',
                'from-rose-500 to-pink-500'
            ];
            $gradient = $gradients[$index % count($gradients)];
        ?>
        <div class="exhibitor-card group bg-white rounded-3xl border border-gray-100 overflow-hidden shadow-sm hover:shadow-2xl hover:shadow-primary-500/10 hover:-translate-y-2 transition-all duration-500"
             data-name="<?php echo strtolower(htmlspecialchars($exhibitor['name'])); ?>"
             data-category="<?php echo htmlspecialchars($exhibitor['category'] ?? ''); ?>"
             style="animation-delay: <?php echo $index * 50; ?>ms;">
            
            <!-- Card Top Gradient Bar -->
            <div class="h-2 bg-gradient-to-r <?php echo $gradient; ?>"></div>
            
            <div class="p-6">
                <!-- Card Header mit Logo und Name -->
                <div class="flex items-start gap-4 mb-5">
                    <div class="relative">
                        <div class="w-20 h-20 rounded-2xl bg-gradient-to-br from-gray-50 to-gray-100 border-2 border-gray-100 flex items-center justify-center overflow-hidden group-hover:border-primary-200 group-hover:shadow-lg transition-all duration-300">
                            <?php if ($exhibitor['logo']): ?>
                                <img src="<?php echo BASE_URL . 'uploads/' . $exhibitor['logo']; ?>" 
                                     alt="<?php echo htmlspecialchars($exhibitor['name']); ?>" 
                                     class="w-16 h-16 object-contain group-hover:scale-110 transition-transform duration-300">
                            <?php else: ?>
                                <i class="fas fa-building text-3xl text-gray-300 group-hover:text-primary-400 transition-colors duration-300"></i>
                            <?php endif; ?>
                        </div>
                        <div class="absolute -bottom-1 -right-1 w-6 h-6 bg-gradient-to-br <?php echo $gradient; ?> rounded-lg flex items-center justify-center shadow-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                            <i class="fas fa-check text-white text-xs"></i>
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="font-bold text-gray-900 text-lg leading-tight mb-2 group-hover:text-primary-600 transition-colors duration-300">
                            <?php echo htmlspecialchars($exhibitor['name']); ?>
                        </h3>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-gradient-to-r <?php echo $gradient; ?> text-white shadow-sm">
                            <i class="fas fa-tag"></i>
                            <?php echo htmlspecialchars($branche); ?>
                        </span>
                    </div>
                </div>

                <!-- Kurzbeschreibung -->
                <?php if ($exhibitor['short_description']): ?>
                <p class="text-gray-500 text-sm mb-5 line-clamp-2">
                    <?php echo htmlspecialchars(substr($exhibitor['short_description'], 0, 100)); ?>...
                </p>
                <?php endif; ?>

                <!-- Angebot Tags -->
                <div class="mb-6">
                    <p class="text-xs uppercase tracking-wider text-gray-400 font-bold mb-2 flex items-center gap-1">
                        <i class="fas fa-briefcase"></i>
                        Angebote
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($angebot as $a): ?>
                        <span class="inline-flex items-center px-3 py-1.5 rounded-xl text-xs font-medium bg-gray-100 text-gray-700 hover:bg-primary-100 hover:text-primary-700 transition-colors duration-200">
                            <?php if ($a === 'Ausbildung'): ?>
                                <i class="fas fa-graduation-cap mr-1.5 text-primary-500"></i>
                            <?php elseif ($a === 'Duales Studium'): ?>
                                <i class="fas fa-book mr-1.5 text-accent-500"></i>
                            <?php else: ?>
                                <i class="fas fa-user-tie mr-1.5 text-blue-500"></i>
                            <?php endif; ?>
                            <?php echo $a; ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Buttons -->
                <div class="flex items-center gap-3 pt-5 border-t border-gray-100">
                    <button onclick="openExhibitorModal(<?php echo $exhibitor['id']; ?>)" 
                            class="flex-1 flex items-center justify-center gap-2 px-4 py-3 bg-gray-100 text-gray-700 rounded-xl font-medium hover:bg-gray-200 hover:text-gray-900 transition-all duration-200">
                        <i class="fas fa-info-circle"></i>
                        <span>Details</span>
                    </button>
                    <?php if (!isTeacher() && !isAdmin()): ?>
                    <a href="?page=registration&exhibitor=<?php echo $exhibitor['id']; ?>" 
                       class="flex-1 flex items-center justify-center gap-2 px-4 py-3 bg-gradient-to-r from-primary-500 to-primary-600 text-white rounded-xl font-medium shadow-md shadow-primary-500/30 hover:shadow-lg hover:shadow-primary-500/40 hover:-translate-y-0.5 transition-all duration-200">
                        <i class="fas fa-user-plus"></i>
                        <span>Einschreiben</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if (empty($exhibitors)): ?>
    <!-- Empty State with Animation -->
    <div class="text-center py-20">
        <div class="w-32 h-32 mx-auto mb-6 bg-gradient-to-br from-gray-100 to-gray-200 rounded-full flex items-center justify-center">
            <i class="fas fa-building text-5xl text-gray-300"></i>
        </div>
        <h3 class="text-xl font-bold text-gray-800 mb-2">Keine Aussteller gefunden</h3>
        <p class="text-gray-500 mb-6">Es sind noch keine Unternehmen registriert.</p>
        <?php if (isAdmin()): ?>
        <a href="?page=admin-exhibitors&action=add" class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-primary-500 to-primary-600 text-white rounded-2xl font-semibold shadow-lg">
            <i class="fas fa-plus"></i>
            Ersten Aussteller hinzufügen
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal für Aussteller-Details - Redesigned -->
<div id="exhibitorModal" class="fixed inset-0 bg-black/60 backdrop-blur-md hidden items-center justify-center z-50 p-4" onclick="closeModalOnBackdrop(event)">
    <div class="modal-content bg-white rounded-3xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden flex flex-col transform scale-95 opacity-0 transition-all duration-500" onclick="event.stopPropagation()">
        <!-- Modal Header -->
        <div class="relative bg-gradient-to-r from-primary-500 via-accent-500 to-blue-500 px-6 py-8 text-white">
            <div class="absolute inset-0 bg-black/10"></div>
            <div class="relative flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center">
                        <i class="fas fa-building text-2xl"></i>
                    </div>
                    <div>
                        <h2 id="modalTitle" class="text-2xl font-bold">Unternehmensdetails</h2>
                        <p class="text-white/80 text-sm">Informationen über das Unternehmen</p>
                    </div>
                </div>
                <button onclick="closeExhibitorModal()" class="w-10 h-10 bg-white/20 hover:bg-white/30 rounded-xl flex items-center justify-center transition-colors duration-200">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
        </div>

        <!-- Modal Body -->
        <div id="modalBody" class="p-6 overflow-y-auto flex-1">
            <div class="flex items-center justify-center py-12">
                <div class="w-16 h-16 border-4 border-primary-200 border-t-primary-500 rounded-full animate-spin"></div>
            </div>
        </div>

        <!-- Modal Footer -->
        <div id="modalFooter" class="bg-gray-50 border-t border-gray-100 px-6 py-4 flex justify-end gap-3">
            <button onclick="closeExhibitorModal()" class="px-5 py-2.5 bg-gray-200 text-gray-700 rounded-xl font-medium hover:bg-gray-300 transition-colors duration-200">
                Schließen
            </button>
            <a id="modalRegisterBtn" href="#" class="px-5 py-2.5 bg-gradient-to-r from-primary-500 to-primary-600 text-white rounded-xl font-medium shadow-md shadow-primary-500/30 hover:shadow-lg transition-all duration-200 flex items-center gap-2">
                <i class="fas fa-user-plus"></i>
                Einschreiben
            </a>
        </div>
    </div>
</div>

<style>
    .filter-btn {
        background-color: white;
        color: #6b7280;
        border: 2px solid #e5e7eb;
    }
    .filter-btn:hover {
        border-color: #22c55e;
        color: #16a34a;
        background-color: #f0fdf4;
    }
    .filter-btn.active {
        background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
        color: white;
        border-color: transparent;
        box-shadow: 0 4px 15px rgba(34, 197, 94, 0.3);
    }
    
    .line-clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    @keyframes cardFadeIn {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .exhibitor-card {
        animation: cardFadeIn 0.6s ease-out forwards;
        opacity: 0;
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
