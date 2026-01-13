/**
 * Berufsmesse - Guided Tour / Walkthrough
 * Interaktives Tutorial für neue Benutzer
 * Version 1.0
 */

class GuidedTour {
    constructor(options = {}) {
        this.steps = options.steps || [];
        this.currentStep = 0;
        this.overlay = null;
        this.tooltip = null;
        this.spotlight = null;
        this.isActive = false;
        this.onComplete = options.onComplete || (() => {});
        this.onSkip = options.onSkip || (() => {});
        this.storageKey = options.storageKey || 'berufsmesse_tour_completed';
        
        this.init();
    }
    
    init() {
        this.createOverlay();
        this.createTooltip();
        this.createSpotlight();
        this.bindKeyEvents();
    }
    
    createOverlay() {
        this.overlay = document.createElement('div');
        this.overlay.className = 'tour-overlay';
        this.overlay.innerHTML = `
            <div class="tour-overlay-bg"></div>
        `;
        document.body.appendChild(this.overlay);
    }
    
    createSpotlight() {
        this.spotlight = document.createElement('div');
        this.spotlight.className = 'tour-spotlight';
        document.body.appendChild(this.spotlight);
    }
    
    createTooltip() {
        this.tooltip = document.createElement('div');
        this.tooltip.className = 'tour-tooltip';
        this.tooltip.innerHTML = `
            <div class="tour-tooltip-content">
                <div class="tour-tooltip-header">
                    <span class="tour-step-indicator"></span>
                    <button class="tour-close-btn" aria-label="Tour beenden">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="tour-tooltip-body">
                    <h3 class="tour-title"></h3>
                    <p class="tour-description"></p>
                </div>
                <div class="tour-tooltip-footer">
                    <button class="tour-btn tour-btn-skip">Überspringen</button>
                    <div class="tour-nav-buttons">
                        <button class="tour-btn tour-btn-prev">
                            <i class="fas fa-arrow-left"></i> Zurück
                        </button>
                        <button class="tour-btn tour-btn-next">
                            Weiter <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="tour-tooltip-arrow"></div>
        `;
        document.body.appendChild(this.tooltip);
        
        // Event Listeners
        this.tooltip.querySelector('.tour-close-btn').addEventListener('click', () => this.skip());
        this.tooltip.querySelector('.tour-btn-skip').addEventListener('click', () => this.skip());
        this.tooltip.querySelector('.tour-btn-prev').addEventListener('click', () => this.prev());
        this.tooltip.querySelector('.tour-btn-next').addEventListener('click', () => this.next());
    }
    
    bindKeyEvents() {
        document.addEventListener('keydown', (e) => {
            if (!this.isActive) return;
            
            switch(e.key) {
                case 'Escape':
                    this.skip();
                    break;
                case 'ArrowRight':
                case 'Enter':
                    this.next();
                    break;
                case 'ArrowLeft':
                    this.prev();
                    break;
            }
        });
    }
    
    start() {
        if (this.steps.length === 0) return;
        
        // Only start on dashboard page
        const currentPage = new URLSearchParams(window.location.search).get('page') || 'dashboard';
        if (currentPage !== 'dashboard' && currentPage !== '') {
            console.warn('Tour kann nur auf dem Dashboard gestartet werden');
            if (typeof showToast !== 'undefined') {
                showToast('Bitte wechsle zum Dashboard, um die Tour zu starten.', 'warning');
            }
            return;
        }
        
        this.isActive = true;
        this.currentStep = 0;
        this.currentTarget = null;
        this.overlay.classList.add('active');
        this.spotlight.classList.add('active');
        this.tooltip.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        this.showStep(this.currentStep);
    }
    
    showStep(index) {
        const step = this.steps[index];
        if (!step) return;
        
        // Remove highlight from previous target
        if (this.currentTarget) {
            this.currentTarget.classList.remove('tour-highlight');
        }
        
        // Support multiple selectors separated by comma - try each one
        let target = null;
        if (step.target) {
            const selectors = step.target.split(',').map(s => s.trim());
            for (const selector of selectors) {
                target = document.querySelector(selector);
                if (target) break;
            }
        }
        
        this.currentTarget = target;
        
        // Update Step Indicator
        this.tooltip.querySelector('.tour-step-indicator').textContent = 
            `Schritt ${index + 1} von ${this.steps.length}`;
        
        // Update Content
        this.tooltip.querySelector('.tour-title').textContent = step.title;
        this.tooltip.querySelector('.tour-description').innerHTML = step.description;
        
        // Update Navigation Buttons
        const prevBtn = this.tooltip.querySelector('.tour-btn-prev');
        const nextBtn = this.tooltip.querySelector('.tour-btn-next');
        const skipBtn = this.tooltip.querySelector('.tour-btn-skip');
        
        prevBtn.style.display = index === 0 ? 'none' : 'flex';
        
        if (index === this.steps.length - 1) {
            nextBtn.innerHTML = '<i class="fas fa-check"></i> Fertig';
            skipBtn.style.display = 'none';
        } else {
            nextBtn.innerHTML = 'Weiter <i class="fas fa-arrow-right"></i>';
            skipBtn.style.display = 'block';
        }
        
        // Position Elements
        if (target) {
            // Add highlight class to target element
            target.classList.add('tour-highlight');
            this.positionSpotlight(target);
            this.positionTooltip(target, step.position || 'bottom');
            this.scrollToElement(target);
        } else {
            // Center tooltip if no target
            this.centerTooltip();
            this.spotlight.style.opacity = '0';
        }
        
        // Execute step callback if exists
        if (step.onShow) {
            step.onShow();
        }
    }
    
    positionSpotlight(target) {
        const rect = target.getBoundingClientRect();
        const padding = 12;
        
        this.spotlight.style.opacity = '1';
        this.spotlight.style.top = `${rect.top - padding}px`;
        this.spotlight.style.left = `${rect.left - padding}px`;
        this.spotlight.style.width = `${rect.width + padding * 2}px`;
        this.spotlight.style.height = `${rect.height + padding * 2}px`;
    }
    
    positionTooltip(target, position) {
        const targetRect = target.getBoundingClientRect();
        const tooltipRect = this.tooltip.getBoundingClientRect();
        const arrow = this.tooltip.querySelector('.tour-tooltip-arrow');
        const spacing = 16;
        
        let top, left;
        
        // Reset arrow classes
        arrow.className = 'tour-tooltip-arrow';
        
        // Use viewport-relative positioning (fixed) since spotlight is fixed
        switch(position) {
            case 'top':
                top = targetRect.top - tooltipRect.height - spacing;
                left = targetRect.left + (targetRect.width / 2) - (tooltipRect.width / 2);
                arrow.classList.add('arrow-bottom');
                break;
            case 'bottom':
                top = targetRect.bottom + spacing;
                left = targetRect.left + (targetRect.width / 2) - (tooltipRect.width / 2);
                arrow.classList.add('arrow-top');
                break;
            case 'left':
                top = targetRect.top + (targetRect.height / 2) - (tooltipRect.height / 2);
                left = targetRect.left - tooltipRect.width - spacing;
                arrow.classList.add('arrow-right');
                break;
            case 'right':
                top = targetRect.top + (targetRect.height / 2) - (tooltipRect.height / 2);
                left = targetRect.right + spacing;
                arrow.classList.add('arrow-left');
                break;
        }
        
        // Boundary checks
        const windowWidth = window.innerWidth;
        const windowHeight = window.innerHeight;
        
        if (left < 16) left = 16;
        if (left + tooltipRect.width > windowWidth - 16) {
            left = windowWidth - tooltipRect.width - 16;
        }
        if (top < 16) top = 16;
        if (top + tooltipRect.height > windowHeight - 16) {
            top = windowHeight - tooltipRect.height - 16;
        }
        
        this.tooltip.style.top = `${top}px`;
        this.tooltip.style.left = `${left}px`;
        this.tooltip.style.transform = 'none'; // Reset transform for fixed positioning
    }
    
    centerTooltip() {
        const tooltipRect = this.tooltip.getBoundingClientRect();
        this.tooltip.style.top = `${(window.innerHeight - tooltipRect.height) / 2}px`;
        this.tooltip.style.left = `${(window.innerWidth - tooltipRect.width) / 2}px`;
        this.tooltip.style.transform = 'none';
    }
    
    scrollToElement(element) {
        const rect = element.getBoundingClientRect();
        const isInViewport = (
            rect.top >= 0 &&
            rect.left >= 0 &&
            rect.bottom <= window.innerHeight &&
            rect.right <= window.innerWidth
        );
        
        if (!isInViewport) {
            element.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
        }
    }
    
    next() {
        if (this.currentStep < this.steps.length - 1) {
            this.currentStep++;
            this.showStep(this.currentStep);
        } else {
            this.complete();
        }
    }
    
    prev() {
        if (this.currentStep > 0) {
            this.currentStep--;
            this.showStep(this.currentStep);
        }
    }
    
    skip() {
        this.end();
        this.onSkip();
    }
    
    complete() {
        this.end();
        localStorage.setItem(this.storageKey, 'true');
        this.onComplete();
    }
    
    end() {
        this.isActive = false;
        
        // Remove highlight from current target
        if (this.currentTarget) {
            this.currentTarget.classList.remove('tour-highlight');
            this.currentTarget = null;
        }
        
        this.overlay.classList.remove('active');
        this.spotlight.classList.remove('active');
        this.tooltip.classList.remove('active');
        document.body.style.overflow = '';
    }
    
    // Check if tour has been completed
    hasCompleted() {
        return localStorage.getItem(this.storageKey) === 'true';
    }
    
    // Reset tour completion status
    reset() {
        localStorage.removeItem(this.storageKey);
    }
}

// Default Tour Steps for Berufsmesse
const berufsmesseTourSteps = [
    {
        target: null,
        title: 'Willkommen zur Berufsmesse!',
        description: `
            <p>Schön, dass du da bist! Diese kurze Tour zeigt dir, 
            wie du die Plattform optimal nutzen kannst.</p>
            <p class="mt-2 text-sm text-gray-500">
                Du kannst die Tour jederzeit mit <kbd>Esc</kbd> beenden oder mit den Pfeiltasten navigieren.
            </p>
        `,
        position: 'center'
    },
    {
        target: '#sidebar',
        title: 'Navigation',
        description: `
            <p>Über die Seitenleiste erreichst du alle Bereiche:</p>
            <ul class="mt-2 space-y-1 text-sm">
                <li><i class="fas fa-home text-emerald-500 mr-1"></i> <strong>Dashboard</strong> - Deine persönliche Übersicht</li>
                <li><i class="fas fa-building text-purple-500 mr-1"></i> <strong>Unternehmen</strong> - Alle Aussteller entdecken</li>
            </ul>
            <p class="mt-2 text-sm text-gray-500">Auf Mobilgeräten erreichst du die Navigation über das Menü-Symbol.</p>
        `,
        position: 'right'
    },
    {
        target: '.quick-action-card, .quick-actions-grid',
        title: 'Schnellzugriff',
        description: `
            <p>Diese Karten bieten dir schnellen Zugriff auf wichtige Funktionen:</p>
            <ul class="mt-2 space-y-1 text-sm">
                <li><i class="fas fa-calendar-alt text-blue-500 mr-1"></i> <strong>Zeitplan</strong> - Alle Termine auf einen Blick</li>
                <li><i class="fas fa-edit text-emerald-500 mr-1"></i> <strong>Einschreibung</strong> - Für Aussteller anmelden</li>
                <li><i class="fas fa-building text-purple-500 mr-1"></i> <strong>Unternehmen</strong> - Aussteller durchsuchen</li>
                <li><i class="fas fa-check-circle text-orange-500 mr-1"></i> <strong>Meine Slots</strong> - Anmeldungen verwalten</li>
            </ul>
        `,
        position: 'bottom'
    },
    {
        target: '.upcoming-schedule, .timeline-item',
        title: 'Dein Tagesplan',
        description: `
            <p>Hier siehst du deinen persönlichen Zeitplan für die Berufsmesse:</p>
            <ul class="mt-2 space-y-1 text-sm">
                <li><i class="fas fa-circle text-emerald-500 mr-1" style="font-size:8px"></i> <strong>Grün</strong> = Du bist angemeldet</li>
                <li><i class="fas fa-circle text-purple-500 mr-1" style="font-size:8px"></i> <strong>Lila</strong> = Freie Wahl vor Ort</li>
                <li><i class="fas fa-circle text-gray-400 mr-1" style="font-size:8px"></i> <strong>Grau</strong> = Noch keine Zuteilung</li>
            </ul>
        `,
        position: 'top'
    },
    {
        target: null,
        title: 'Bereit? Los geht\'s!',
        description: `
            <p>Super! Du kennst jetzt die wichtigsten Funktionen.</p>
            <p class="mt-2"><strong>Nächste Schritte:</strong></p>
            <ul class="mt-1 space-y-1 text-sm">
                <li><i class="fas fa-arrow-right text-emerald-500 mr-1"></i> Entdecke die Unternehmen</li>
                <li><i class="fas fa-arrow-right text-emerald-500 mr-1"></i> Melde dich für Zeitslots an</li>
                <li><i class="fas fa-arrow-right text-emerald-500 mr-1"></i> Schau dir deinen Zeitplan an</li>
            </ul>
            <p class="mt-3 text-sm text-gray-500">
                <i class="fas fa-lightbulb text-amber-500 mr-1"></i> Tipp: Diese Tour findest du im "Hilfe & Tour"-Button in der Seitenleiste.
            </p>
        `,
        position: 'center'
    }
];

// Export for use
window.GuidedTour = GuidedTour;
window.berufsmesseTourSteps = berufsmesseTourSteps;
