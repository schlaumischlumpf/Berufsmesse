<?php
// Kategorien fuer Filter aus DB laden (Fallback: DISTINCT aus exhibitors)
$industryList = getIndustries();
$categories = !empty($industryList) 
    ? array_column($industryList, 'name')
    : $db->query("SELECT DISTINCT category FROM exhibitors WHERE active = 1 AND category IS NOT NULL AND category != '' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
?>

<!-- Aussteller-Übersicht -->
<div class="space-y-6">
    <!-- Filter Tabs -->
    <div class="flex flex-wrap items-center gap-2 mb-6">
        <button onclick="filterCategory('')" class="filter-btn active px-4 py-2 text-sm font-medium rounded-lg transition-all" data-category="">
            Alle Aussteller
        </button>
        <?php foreach ($categories as $cat): ?>
        <button onclick="filterCategory('<?php echo htmlspecialchars(html_entity_decode($cat, ENT_QUOTES | ENT_HTML5, 'UTF-8')); ?>')" class="filter-btn px-4 py-2 text-sm font-medium rounded-lg transition-all" data-category="<?php echo htmlspecialchars(html_entity_decode($cat, ENT_QUOTES | ENT_HTML5, 'UTF-8')); ?>">
            <?php echo htmlspecialchars(html_entity_decode($cat, ENT_QUOTES | ENT_HTML5, 'UTF-8')); ?>
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
            <button id="sortButton" onclick="sortExhibitors('name')" class="flex items-center px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition">
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
        <?php foreach ($exhibitors as $index => $exhibitor):
            // Branche - Unterstützt jetzt mehrere Kategorien
            $categoriesArray = [];
            if (!empty($exhibitor['category'])) {
                try {
                    $categoriesArray = json_decode($exhibitor['category'], true) ?? [];
                } catch (Exception $e) {
                    // Fallback für alte String-Werte
                    $categoriesArray = [$exhibitor['category']];
                }
            }
            if (!is_array($categoriesArray)) $categoriesArray = [$categoriesArray];
            $branche = !empty($categoriesArray) ? $categoriesArray[0] : 'Allgemein';
            $categoriesJson = json_encode($categoriesArray);
            
            // Angebot aus offer_types JSON laden (Fallback: aus Beschreibung parsen)
            $angebot = [];
            if (!empty($exhibitor['offer_types'])) {
                $offerData = json_decode($exhibitor['offer_types'], true);
                if ($offerData && !empty($offerData['selected'])) {
                    $angebot = $offerData['selected'];
                }
                if ($offerData && !empty($offerData['custom'])) {
                    $angebot[] = $offerData['custom'];
                }
            }
            if (empty($angebot)) {
                $desc = strtolower($exhibitor['short_description'] . ' ' . $exhibitor['description']);
                if (strpos($desc, 'ausbildung') !== false) $angebot[] = 'Ausbildung';
                if (strpos($desc, 'studium') !== false || strpos($desc, 'dual') !== false) $angebot[] = 'Duales Studium';
                if (strpos($desc, 'praktikum') !== false) $angebot[] = 'Praktikum';
            }
            
            // Pastel Color variants based on index
            $colors = ['mint', 'lavender', 'peach', 'sky'];
            $colorIndex = $index % count($colors);
            $colorClass = $colors[$colorIndex];
        ?>
        <div class="exhibitor-card bg-white rounded-xl border border-gray-100 p-5 hover:border-gray-200"
             data-name="<?php echo strtolower(htmlspecialchars($exhibitor['name'])); ?>"
             data-categories="<?php echo htmlspecialchars($categoriesJson); ?>">
            
            <!-- Card Header mit Logo und Name -->
            <div class="flex items-start space-x-4 mb-4">
                <div class="w-14 h-14 rounded-xl flex items-center justify-center overflow-hidden flex-shrink-0 shadow-sm" style="background: linear-gradient(135deg, var(--color-pastel-<?php echo $colorClass; ?>-light, #f3f4f6) 0%, white 100%); border: 1px solid rgba(0,0,0,0.05);">
                    <?php if ($exhibitor['logo']): ?>
                        <img src="<?php echo BASE_URL . 'uploads/' . $exhibitor['logo']; ?>" 
                             alt="<?php echo htmlspecialchars($exhibitor['name']); ?>" 
                             class="w-full h-full object-contain p-1"
                             style="max-width: 100%; max-height: 100%; display: block;"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <span style="display:none; width:100%; height:100%; align-items:center; justify-content:center;">
                            <i class="fas fa-building text-gray-300 text-2xl"></i>
                        </span>
                    <?php else: ?>
                        <i class="fas fa-building text-gray-300 text-2xl"></i>
                    <?php endif; ?>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="font-semibold text-gray-900 text-base leading-tight mb-1 truncate">
                        <?php echo htmlspecialchars(html_entity_decode($exhibitor['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8')); ?>
                    </h3>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" style="background: var(--color-pastel-<?php echo $colorClass; ?>-light, #d4f5e4); color: var(--color-pastel-<?php echo $colorClass; ?>-dark, #6bc4a6);">
                        <?php echo htmlspecialchars(html_entity_decode($branche, ENT_QUOTES | ENT_HTML5, 'UTF-8')); ?>
                    </span>
                </div>
            </div>

            <!-- Angebot -->
            <div class="mb-5">
                <p class="text-xs uppercase tracking-wider text-gray-400 font-semibold mb-2">Angebot</p>
                <div class="flex flex-wrap gap-1.5">
                    <?php foreach ($angebot as $a): ?>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium bg-gray-100 text-gray-700 transition-colors hover:bg-gray-200">
                        <?php echo htmlspecialchars(html_entity_decode($a, ENT_QUOTES | ENT_HTML5, 'UTF-8')); ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Buttons -->
            <div class="flex items-center gap-2 pt-4 border-t border-gray-100">
                <button onclick="openExhibitorModal(<?php echo $exhibitor['id']; ?>)" 
                        class="flex-1 flex items-center justify-center px-3 py-2.5 bg-gray-100 text-gray-700 rounded-xl text-sm font-medium hover:bg-gray-200 transition-all duration-200 hover:shadow-sm">
                    <i class="fas fa-info-circle mr-2"></i> Mehr Infos
                </button>
                <?php if (!isTeacher() && !isAdmin()): ?>
                <a href="?page=registration&exhibitor=<?php echo $exhibitor['id']; ?>" 
                   class="flex-1 flex items-center justify-center px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 hover:shadow-md" style="background: linear-gradient(135deg, var(--color-pastel-mint) 0%, #6bc4a6 100%); color: #1f2937;">
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

<style>
    /* Filter Buttons with Pastel Style */
    .filter-btn {
        background: white;
        color: #6b7280;
        border: 1px solid #e5e7eb;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .filter-btn:hover {
        background: linear-gradient(135deg, #d4f5e4 0%, #e8dff5 100%);
        color: #374151;
        border-color: #a8e6cf;
        transform: translateY(-1px);
    }
    .filter-btn.active {
        background: linear-gradient(135deg, #a8e6cf 0%, #c3b1e1 100%);
        color: #1f2937;
        border-color: transparent;
        box-shadow: 0 4px 12px rgba(168, 230, 207, 0.4);
    }
    
    /* Exhibitor Card Hover Animation */
    .exhibitor-card {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .exhibitor-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 24px -8px rgba(0, 0, 0, 0.12);
        border-color: #a8e6cf;
    }
    
    /* Card Animation Stagger */
    .exhibitor-card {
        animation: fadeInUp 0.4s ease-out forwards;
        opacity: 0;
    }
    
    .exhibitor-card:nth-child(1) { animation-delay: 0.05s; }
    .exhibitor-card:nth-child(2) { animation-delay: 0.1s; }
    .exhibitor-card:nth-child(3) { animation-delay: 0.15s; }
    .exhibitor-card:nth-child(4) { animation-delay: 0.2s; }
    .exhibitor-card:nth-child(5) { animation-delay: 0.25s; }
    .exhibitor-card:nth-child(6) { animation-delay: 0.3s; }
    
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>

<script>
let currentFilter = '';
let sortDirection = 'asc'; // Track current sort direction

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

        // Parse categories from JSON
        const categoriesAttr = card.getAttribute('data-categories') || '[]';
        const cats = (() => {
            try {
                return JSON.parse(categoriesAttr);
            } catch(e) {
                return [];
            }
        })();

        const matchesSearch = name.includes(searchTerm);
        const matchesCategory = !currentFilter || cats.includes(currentFilter);

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

    // Toggle sort direction
    sortDirection = (sortDirection === 'asc') ? 'desc' : 'asc';

    // Sort with current direction
    cards.sort((a, b) => {
        const nameA = a.getAttribute('data-name');
        const nameB = b.getAttribute('data-name');

        if (sortDirection === 'asc') {
            return nameA.localeCompare(nameB);
        } else {
            return nameB.localeCompare(nameA);
        }
    });

    // Update button text and icon
    const sortBtn = document.getElementById('sortButton');
    if (sortDirection === 'asc') {
        sortBtn.innerHTML = '<i class="fas fa-sort-alpha-down mr-2"></i> A-Z';
    } else {
        sortBtn.innerHTML = '<i class="fas fa-sort-alpha-up mr-2"></i> Z-A';
    }

    // Re-append cards in new order
    cards.forEach(card => grid.appendChild(card));
}

// Initialize search input event listener when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        // Use input event for real-time filtering (fires on every change)
        searchInput.addEventListener('input', filterExhibitors);
        // Also keep keyup for compatibility
        searchInput.addEventListener('keyup', filterExhibitors);
    }
});
</script>
