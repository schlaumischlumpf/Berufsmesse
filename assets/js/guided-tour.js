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
        this.role = options.role || null; // role for role-based steps
        this.stateKey = 'berufsmesse_tour_state';
        
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
    
    start(startIndex = 0) {
        if (this.steps.length === 0) return;
        
        // Verhindere, dass mehrere Touren gleichzeitig gestartet werden
        if (this.isActive) {
            console.warn('Eine Tour ist bereits aktiv. Bitte beende diese zuerst.');
            return;
        }
        
        this.isActive = true;
        this.currentStep = startIndex || 0;
        
        // Stelle sicher, dass das Overlay sichtbar ist
        this.overlay.style.display = '';
        this.overlay.classList.add('active');
        this.spotlight.classList.add('active');
        this.tooltip.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // save initial state
        this.saveState();
        this.showStep(this.currentStep);
    }
    
    showStep(index) {
        const step = this.steps[index];
        if (!step) return;
        
        // Save state before trying actions
        this.currentStep = index;
        this.saveState();
        
        // Navigate to page if needed
        if (step.page && window.location.search.indexOf('page=' + step.page) === -1) {
            // Ensure state saved so tour can resume after navigation
            this.saveState();
            window.location.href = '?page=' + step.page;
            return;
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
        
        // Remove previous highlight (if any) before updating content
        this.clearHighlight();
        
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
            // Prepare visual highlight for the target (elevate it above overlay)
            // Pass the step object to support noBlur and highlightAll options
            this.prepareHighlight(target, step);

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
        const padding = 8;
        
        // Prüfe ob das Element fixed positioniert ist
        const computedStyle = window.getComputedStyle(target);
        const isFixed = computedStyle.position === 'fixed';
        
        this.spotlight.style.opacity = '1';
        this.spotlight.style.position = isFixed ? 'fixed' : 'absolute';
        this.spotlight.style.top = `${rect.top + (isFixed ? 0 : window.scrollY) - padding}px`;
        this.spotlight.style.left = `${rect.left - padding}px`;
        this.spotlight.style.width = `${rect.width + padding * 2}px`;
        this.spotlight.style.height = `${rect.height + padding * 2}px`;
    }
    
    positionTooltip(target, position) {
        const targetRect = target.getBoundingClientRect();
        const tooltipRect = this.tooltip.getBoundingClientRect();
        const arrow = this.tooltip.querySelector('.tour-tooltip-arrow');
        const spacing = 16;
        
        // Prüfe ob das Element fixed positioniert ist
        const computedStyle = window.getComputedStyle(target);
        const isFixed = computedStyle.position === 'fixed';
        const scrollY = isFixed ? 0 : window.scrollY;
        
        let top, left;
        
        // Reset arrow classes and tooltip position
        arrow.className = 'tour-tooltip-arrow';
        this.tooltip.style.position = isFixed ? 'fixed' : 'absolute';
        this.tooltip.style.transform = ''; // Reset transform
        
        switch(position) {
            case 'top':
                top = targetRect.top + scrollY - tooltipRect.height - spacing;
                left = targetRect.left + (targetRect.width / 2) - (tooltipRect.width / 2);
                arrow.classList.add('arrow-bottom');
                break;
            case 'top-right':
                top = targetRect.top + scrollY - tooltipRect.height - spacing;
                left = targetRect.right - tooltipRect.width;
                arrow.classList.add('arrow-bottom');
                break;
            case 'bottom':
                top = targetRect.bottom + scrollY + spacing;
                left = targetRect.left + (targetRect.width / 2) - (tooltipRect.width / 2);
                arrow.classList.add('arrow-top');
                break;
            case 'left':
                top = targetRect.top + scrollY + (targetRect.height / 2) - (tooltipRect.height / 2);
                left = targetRect.left - tooltipRect.width - spacing;
                arrow.classList.add('arrow-right');
                break;
            case 'right':
                top = targetRect.top + scrollY + (targetRect.height / 2) - (tooltipRect.height / 2);
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
        
        this.tooltip.style.top = `${top}px`;
        this.tooltip.style.left = `${left}px`;
    }
    
    centerTooltip() {
        this.tooltip.style.position = 'fixed';
        this.tooltip.style.top = '50%';
        this.tooltip.style.left = '50%';
        this.tooltip.style.transform = 'translate(-50%, -50%)';
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
            this.saveState();
            this.showStep(this.currentStep);
        } else {
            this.complete();
        }
    }
    
    prev() {
        if (this.currentStep > 0) {
            this.currentStep--;
            this.saveState();
            this.showStep(this.currentStep);
        }
    }
    
    skip() {
        this.end();
        localStorage.setItem(this.storageKey, 'true');
        this.clearState();
        // Delay callback until overlay is fully hidden
        setTimeout(() => {
            this.onSkip();
        }, 350);
    }
    
    complete() {
        this.end();
        localStorage.setItem(this.storageKey, 'true');
        this.clearState();
        // Delay callback until overlay is fully hidden
        setTimeout(() => {
            this.onComplete();
        }, 350);
    }
    
    end() {
        this.isActive = false;
        this.overlay.classList.remove('active');
        this.spotlight.classList.remove('active');
        this.tooltip.classList.remove('active');
        document.body.style.overflow = '';
        this.clearHighlight();
        
        // Stelle sicher, dass alle Styling entfernt wird
        setTimeout(() => {
            this.overlay.style.display = 'none';
        }, 300);
    }
    
    // Check if tour has been completed
    hasCompleted() {
        return localStorage.getItem(this.storageKey) === 'true';
    }
    
    // Reset tour completion status
    reset() {
        localStorage.removeItem(this.storageKey);
        this.clearState();
    }

    // Save/Load tour runtime state (for resuming across page navigation)
    saveState() {
        const state = {
            active: this.isActive,
            role: this.role,
            step: this.currentStep
        };
        localStorage.setItem(this.stateKey, JSON.stringify(state));
    }

    loadState() {
        try {
            const raw = localStorage.getItem(this.stateKey);
            if (!raw) return null;
            return JSON.parse(raw);
        } catch (e) {
            return null;
        }
    }

    clearState() {
        localStorage.removeItem(this.stateKey);
    }

    // Highlight helper: elevate element(s) above overlay and mark them visually
    prepareHighlight(element, step = {}) {
        if (!element) return;

        // Clear any previous highlight
        this.clearHighlight();
        
        // Array to hold all highlighted elements
        this._currentHighlighted = [];

        // If noBlur is set, also highlight the sidebar to prevent it from being blurred
        if (step.noBlur) {
            const sidebar = document.getElementById('sidebar');
            if (sidebar) {
                this.highlightSingleElement(sidebar);
            }
        }

        // If highlightAll is set and the selector matches multiple elements, highlight all
        if (step.highlightAll && step.target) {
            const allElements = document.querySelectorAll(step.target);
            allElements.forEach(el => this.highlightSingleElement(el));
        } else {
            // Just highlight the primary element
            this.highlightSingleElement(element);
        }

        // Ensure tooltip sits above the highlighted elements
        if (this.tooltip) this.tooltip.style.zIndex = '100002';
    }

    // Helper to highlight a single element
    highlightSingleElement(element) {
        if (!element) return;

        // Store previous inline styles to restore later
        element.dataset.tourPrevPosition = element.style.position || '';
        element.dataset.tourPrevZ = element.style.zIndex || '';
        element.dataset.tourPrevTransition = element.style.transition || '';

        // If element is statically positioned, make it relative so z-index applies
        const computed = window.getComputedStyle(element);
        if (computed.position === 'static') {
            element.style.position = 'relative';
        }

        // Elevate and add visual class
        element.style.zIndex = '100001';
        element.classList.add('tour-highlight');

        // Add to tracked array
        this._currentHighlighted.push(element);
    }

    clearHighlight() {
        try {
            // Get all currently highlighted elements
            const elements = this._currentHighlighted && this._currentHighlighted.length > 0 
                ? this._currentHighlighted 
                : Array.from(document.querySelectorAll('.tour-highlight'));
            
            elements.forEach(el => {
                if (!el) return;

                // restore inline styles
                el.style.position = el.dataset.tourPrevPosition || '';
                el.style.zIndex = el.dataset.tourPrevZ || '';
                el.style.transition = el.dataset.tourPrevTransition || '';

                // remove datasets
                delete el.dataset.tourPrevPosition;
                delete el.dataset.tourPrevZ;
                delete el.dataset.tourPrevTransition;

                el.classList.remove('tour-highlight');
            });
            
            this._currentHighlighted = [];
        } catch (e) {
            // ignore
        }
    }
}

// Default Tour Steps for Berufsmesse - Dynamic Generation
/**
 * Generiert rollenspezifische Tour-Schritte
 * @param {string} userRole - Die Rolle des Benutzers (student, teacher, admin)
 * @returns {Array} Array mit Tour-Schritten
 */
function generateTourSteps(userRole) {
    // Basis-Schritte für alle Rollen
    const baseSteps = [
        {
            target: null,
            title: 'Willkommen zur Berufsmesse! 🎓',
            description: `
                <p>Schön, dass du da bist! Diese Tour zeigt dir alle wichtigen Funktionen 
                der Plattform passend zu deiner Rolle.</p>
                <p class="mt-2 text-sm text-gray-500">
                    💡 Tipp: Du kannst die Tour jederzeit mit <kbd>Esc</kbd> beenden.
                </p>
            `,
            position: 'center'
        }
    ];
    
    // ADMIN-Tour
    if (userRole === 'admin') {
        return [
            {
                target: null,
                title: '👑 Willkommen, Administrator!',
                description: `
                    <p>Diese umfassende Tour zeigt dir alle Admin-Funktionen der Berufsmesse.</p>
                    <p class="mt-2 text-sm text-gray-500">
                        Du hast Zugriff auf alle System-Verwaltungsfunktionen. Lass uns beginnen!
                    </p>
                `,
                position: 'center'
            },
            {
                target: '.bg-gradient-to-r.from-emerald-500',
                title: '📊 Admin-Dashboard Übersicht',
                description: `
                    <p><strong>Deine Kommandozentrale:</strong></p>
                    <ul class="mt-2 space-y-1 text-sm">
                        <li>📈 <strong>Live-Statistiken</strong> - Alle wichtigen KPIs auf einen Blick</li>
                        <li>👥 <strong>Benutzer-Übersicht</strong> - Gesamt, Lehrer, Schüler</li>
                        <li>🏢 <strong>Aussteller-Status</strong> - Wie viele sind angemeldet?</li>
                        <li>📊 <strong>Registrierungs-Statistik</strong> - Teilnahmequote und Trends</li>
                    </ul>
                `,
                position: 'bottom'
            },
            {
                target: '[onclick*="startGuidedTour"]',
                title: '🎬 Tour jederzeit starten',
                description: `
                    <p>Mit diesem Button kannst du jederzeit eine neue Tour starten oder 
                    die aktuelle Tour neu beginnen.</p>
                    <p class="mt-2 text-sm text-gray-500">
                        Praktisch für das Onboarding neuer Admin-Kollegen!
                    </p>
                `,
                position: 'top-right',
                noBlur: true
            },
            {
                target: 'a[href*="admin-users"]',
                title: '👥 Benutzerverwaltung - Das Herzstück',
                description: `
                    <p><strong>Zentrale Verwaltung aller Benutzer:</strong></p>
                    <ul class="mt-2 space-y-1 text-sm">
                        <li>👤 <strong>Einzelne Benutzer</strong> - Anlegen, bearbeiten, löschen</li>
                        <li>📥 <strong>CSV-Import</strong> - Hunderte Schüler auf einmal anlegen</li>
                        <li>🔑 <strong>Passwort-Reset</strong> - Benutzer können Zugangsdaten zurücksetzen</li>
                        <li>🎭 <strong>Rollen zuweisen</strong> - Student, Lehrer, Admin Rollen</li>
                        <li>📧 <strong>E-Mail ändern</strong> - Kontaktdaten aktualisieren</li>
                        <li>🔒 <strong>Sperren/Entsperren</strong> - Konto-Zugriff kontrollieren</li>
                    </ul>
                    <p class="mt-2 text-sm text-amber-600">
                        <strong>CSV-Format:</strong> firstname;lastname;email;class;role
                    </p>
                `,
                position: 'right',
                noBlur: true
            },
            {
                target: 'a[href*="admin-exhibitors"]',
                title: '🏢 Ausstellerverwaltung',
                description: `
                    <p><strong>Alle teilnehmenden Unternehmen verwalten:</strong></p>
                    <ul class="mt-2 space-y-1 text-sm">
                        <li>➕ <strong>Aussteller hinzufügen</strong> - Name, Beschreibung, Logo</li>
                        <li>🏷️ <strong>Kategorien</strong> - Industrien und Fachrichtungen</li>
                        <li>🚪 <strong>Räume zuweisen</strong> - In welchem Raum findet die Präsentation statt?</li>
                        <li>📄 <strong>Dokumente/Flyer</strong> - Unternehmensinformationen hochladen</li>
                        <li>🔗 <strong>Website & Links</strong> - Externe Karriereseiten verlinken</li>
                        <li>✅/❌ <strong>Aktivieren/Deaktivieren</strong> - Aussteller sichtbar machen</li>
                        <li>📊 <strong>Anmeldungen anzeigen</strong> - Wie viele Schüler interessieren sich?</li>
                    </ul>
                `,
                position: 'right',
                noBlur: true
            },
            {
                target: 'a[href*="admin-rooms"]',
                title: '🚪 Raumverwaltung & Kapazitäten',
                description: `
                    <p><strong>Räume konfigurieren und Aussteller zuordnen:</strong></p>
                    <ul class="mt-2 space-y-1 text-sm">
                        <li>🏗️ <strong>Räume anlegen</strong> - Raumnummer, Name, Gebäude, Etage</li>
                        <li>👥 <strong>Kapazität festlegen</strong> - Max. Schüler pro Zeitslot</li>
                        <li>🏢 <strong>Aussteller zuordnen</strong> - Welcher Aussteller in welchem Raum</li>
                        <li>📊 <strong>Auslastung anzeigen</strong> - Wie voll sind die Slots?</li>
                        <li>⚠️ <strong>Warnungen</strong> - Über- oder unterbelegte Räume</li>
                    </ul>
                    <p class="mt-2 text-sm text-blue-600">
                        <strong>💡 Automatische Berechnung:</strong> 
                        Kapazität pro Slot = Gesamtkapazität ÷ 3 Slots
                    </p>
                `,
                position: 'right',
                noBlur: true
            },
            {
                target: 'a[href*="admin-print"]',
                title: '🖨️ Druck & Export - Professionelle Reports',
                description: `
                    <p><strong>Verschiedene Ausgabeformate für unterschiedliche Zwecke:</strong></p>
                    <ul class="mt-2 space-y-1 text-sm">
                        <li>📋 <strong>Gesamtübersicht</strong> - Alle Anmeldungen nach Raum sortiert</li>
                        <li>🎓 <strong>Klassenlisten</strong> - Pro Klasse, ideal für Lehrer und Schüler</li>
                        <li>🚪 <strong>Raumpläne</strong> - Welche Schüler kommen in welchen Raum?</li>
                        <li>📅 <strong>Zeitplan-Übersicht</strong> - Nach Zeitslots organisiert</li>
                        <li>📊 <strong>Statistik-Report</strong> - Umfangreiche Analyse und Kennzahlen</li>
                        <li>🔍 <strong>Suchfilter</strong> - Nach Klasse, Raum, Aussteller filtern</li>
                        <li>💾 <strong>PDF/Excel Export</strong> - Zum Ausdrucken oder in Excel</li>
                    </ul>
                `,
                position: 'right',
                noBlur: true
            },
            {
                target: 'a[href*="admin-settings"]',
                title: '⚙️ System-Einstellungen',
                description: `
                    <p><strong>Globale Konfiguration der gesamten Berufsmesse:</strong></p>
                    <ul class="mt-2 space-y-1 text-sm">
                        <li>📅 <strong>Messedatum</strong> - Wann findet die Messe statt?</li>
                        <li>⏰ <strong>Öffnungszeiten</strong> - Von wann bis wann?</li>
                        <li>🔓 <strong>Anmeldezeitraum</strong> - Start- und Enddatum für Registrierungen</li>
                        <li>🔢 <strong>Max. Anmeldungen pro Schüler</strong> - Wie viele verwaltete Slots?</li>
                        <li>🎨 <strong>Farben & Design</strong> - Anpassung der Oberfläche</li>
                        <li>📧 <strong>Benachrichtigungen</strong> - E-Mail und Systembenachrichtigungen</li>
                        <li>⚠️ <strong>WICHTIG:</strong> Änderungen hier betreffen das gesamte System!</li>
                    </ul>
                `,
                position: 'right',
                noBlur: true
            },
            {
                target: 'a[href*="admin-permissions"]',
                title: '🔐 Berechtigungen & Rollen',
                description: `
                    <p><strong>Feinkörnige Kontrolle über Benutzerrechte:</strong></p>
                    <ul class="mt-2 space-y-1 text-sm">
                        <li>👑 <strong>Admin</strong> - Vollzugriff auf alle Funktionen</li>
                        <li>👨‍🏫 <strong>Lehrer</strong> - Sehen ihre Klassenlisten und können Schüler-Status überprüfen</li>
                        <li>🎓 <strong>Student</strong> - Können sich für Aussteller anmelden</li>
                        <li>🔒 <strong>Gast</strong> - Nur Lesezugriff auf bestimmte Inhalte</li>
                    </ul>
                    <p class="mt-2 text-sm text-gray-500">
                        Custom-Rollen können hinzugefügt werden, falls nötig.
                    </p>
                `,
                position: 'right',
                noBlur: true
            },
            {
                target: null,
                title: '✅ Admin-Tour abgeschlossen!',
                description: `
                    <p class="font-semibold mb-2">Du hast jetzt einen Überblick über alle Admin-Funktionen!</p>
                    <p class="text-sm mb-3">Verwende deine Superkräfte verantwortungsvoll:</p>
                    <ul class="mt-2 space-y-1 text-sm">
                        <li>✅ Schüler und Lehrer importieren</li>
                        <li>✅ Aussteller und Räume konfigurieren</li>
                        <li>✅ Automatische Zuteilung durchführen</li>
                        <li>✅ Reports und Listen drucken</li>
                        <li>✅ System-Einstellungen verwalten</li>
                    </ul>
                    <p class="mt-3 text-sm text-gray-500">
                        💡 Diese Tour ist jederzeit über den "Tour starten"-Button erreichbar.
                    </p>
                `,
                position: 'center'
            }
        ];
    }
    
    // LEHRER-Tour
    else if (userRole === 'teacher') {
        return [
            {
                target: null,
                title: '👨‍🏫 Willkommen, Lehrkraft!',
                description: `
                    <p>Diese Tour zeigt dir alle Funktionen für Lehrkräfte zur Verwaltung 
                    deiner Schüleranmeldungen.</p>
                    <p class="mt-2 text-sm text-gray-500">
                        Du kannst Schüler überwachen und Listen exportieren.
                    </p>
                `,
                position: 'center'
            },
            {
                target: '.bg-white.rounded-xl.p-6.border-l-4',
                title: '📊 Dein Lehrer-Dashboard',
                description: `
                    <p><strong>Überblick über deine Schüler:</strong></p>
                    <ul class="mt-2 space-y-1 text-sm">
                        <li>👥 <strong>Gesamt-Schüler</strong> - Alle Schüler in deinen Klassen</li>
                        <li>✅ <strong>Vollständig angemeldet</strong> - Schüler mit allen 3 Slots</li>
                        <li>⚠️ <strong>Unvollständig</strong> - Fehlen noch Slots?</li>
                        <li>❌ <strong>Ohne Anmeldung</strong> - Wer hat sich noch nicht angemeldet?</li>
                    </ul>
                `,
                position: 'bottom'
            },
            {
                target: '[onclick*="startGuidedTour"]',
                title: '🎬 Tour jederzeit wiederholen',
                description: `
                    <p>Du kannst diese Tour jederzeit neu starten, wenn du etwas vergessen hast.</p>
                `,
                position: 'left'
            },
            {
                target: '.grid.grid-cols-1.md\\:grid-cols-4, [class*="grid-cols"]',
                title: '📈 Statistik-Karten',
                description: `
                    <p><strong>Auf einen Blick alles wichtige:</strong></p>
                    <ul class="mt-2 space-y-1 text-sm">
                        <li>🔵 <strong>Gesamtzahl Schüler</strong> - Wie viele Schüler in deinen Klassen?</li>
                        <li>🟢 <strong>Vollständig</strong> - Mit allen erforderlichen Anmeldungen</li>
                        <li>🟠 <strong>Unvollständig</strong> - Mit nur teilweisen Anmeldungen</li>
                        <li>🔴 <strong>Ohne Anmeldung</strong> - Benötigen deine Unterstützung</li>
                    </ul>
                    <p class="mt-2 text-sm text-gray-500">Diese Zahlen aktualisieren sich in Echtzeit!</p>
                `,
                position: 'bottom'
            },
            {
                target: 'a[href*="teacher-class"], .tabs, [role="tab"]',
                title: '📋 Klassenlisten',
                description: `
                    <p><strong>Detaillierte Übersicht deiner Klassen:</strong></p>
                    <ul class="mt-2 space-y-1 text-sm">
                        <li>📚 <strong>Nach Klasse filtern</strong> - Schüler pro Klasse sehen</li>
                        <li>✅ <strong>Anmeldestatus prüfen</strong> - Wer hat sich für welche Aussteller angemeldet?</li>
                        <li>🔢 <strong>Slot-Information</strong> - Slot 1, 3 und 5 Management</li>
                        <li>👥 <strong>Schülernamen</strong> - Vollständige Klassenliste mit allen Details</li>
                    </ul>
                `,
                position: 'bottom'
            },
            {
                target: 'a[href*="print"], button[class*="print"], [class*="export"]',
                title: '🖨️ Listen drucken & exportieren',
                description: `
                    <p><strong>Professionelle Dokumente für deine Klassen:</strong></p>
                    <ul class="mt-2 space-y-1 text-sm">
                        <li>📄 <strong>Klassenliste</strong> - Alle Schüler mit Anmeldestatus</li>
                        <li>📊 <strong>Zeitplan-Übersicht</strong> - Wo ist welcher Schüler wann?</li>
                        <li>📋 <strong>Raumpläne</strong> - In welche Räume gehen deine Schüler?</li>
                        <li>💾 <strong>PDF & Excel</strong> - Download für deine Unterlagen</li>
                        <li>🎯 <strong>Filter</strong> - Nur bestimmte Klassen oder Schüler exportieren</li>
                    </ul>
                    <p class="mt-2 text-sm text-gray-500">Perfekt für die Vorbereitung und Durchführung der Messe!</p>
                `,
                position: 'right'
            },
            {
                target: 'a[href*="schedule"], a[href*="zeitplan"]',
                title: '📅 Zeitpläne ansehen',
                description: `
                    <p><strong>Gesamtübersicht aller Zeitslots und Aussteller:</strong></p>
                    <ul class="mt-2 space-y-1 text-sm">
                        <li>🕐 <strong>Zeitslot-Struktur</strong> - Slot 1, 3 und 5 mit Zeiten</li>
                        <li>🏢 <strong>Aussteller pro Slot</strong> - Welche Unternehmen in welcher Zeit?</li>
                        <li>👥 <strong>Anmeldungen pro Aussteller</strong> - Wie viele Schüler angemeldet?</li>
                        <li>🚪 <strong>Raum-Information</strong> - Welcher Aussteller in welchem Raum?</li>
                    </ul>
                `,
                position: 'right'
            },
            {
                target: null,
                title: '✅ Lehrer-Tour abgeschlossen!',
                description: `
                    <p class="font-semibold mb-2">Du kennst jetzt alle deine Lehrkraft-Funktionen!</p>
                    <p class="text-sm mb-3">Mit diesen Tools kannst du:</p>
                    <ul class="mt-2 space-y-1 text-sm">
                        <li>✅ Schüler-Anmeldungen überwachen</li>
                        <li>✅ Klassenlisten prüfen</li>
                        <li>✅ Zeitpläne anzeigen und drucken</li>
                        <li>✅ Deine Schüler auf die Messe vorbereiten</li>
                    </ul>
                    <p class="mt-3 text-sm text-gray-500">
                        💡 Tipp: Die Tour findest du jederzeit über den "Tour starten"-Button.
                    </p>
                `,
                position: 'center'
            }
        ];
    }
    
    // STUDENT-Tour (Standard)
    else {
        return [
            baseSteps[0],
            {
                target: '#sidebar',
                title: '📋 Navigation',
                description: `
                    <p>Über die Seitenleiste erreichst du alle Bereiche:</p>
                    <ul class="mt-2 space-y-1 text-sm">
                        <li>🏠 <strong>Dashboard</strong> - Deine persönliche Übersicht</li>
                        <li>🏢 <strong>Unternehmen</strong> - Alle Aussteller entdecken</li>
                        <li>📅 <strong>Zeitplan</strong> - Dein Tagesablauf</li>
                    </ul>
                    <p class="mt-2 text-sm text-gray-500">Auf Mobilgeräten erreichst du die Navigation über das Menü-Symbol.</p>
                `,
                position: 'right',
                noBlur: true
            },
            {
                target: '.quick-actions-grid',
                title: '⚡ Schnellzugriff',
                description: `
                    <p>Diese Karten bieten dir schnellen Zugriff auf die wichtigsten Funktionen:</p>
                    <ul class="mt-2 space-y-1 text-sm">
                        <li>📅 <strong>Mein Zeitplan</strong> - Alle deine Termine auf einen Blick anzeigen</li>
                        <li>✏️ <strong>Einschreibung</strong> - Für Aussteller-Präsentationen anmelden</li>
                        <li>🏢 <strong>Unternehmen</strong> - Alle Aussteller durchsuchen und kennenlernen</li>
                        <li>✅ <strong>Meine Slots</strong> - Deine Anmeldungen verwalten und bearbeiten</li>
                    </ul>
                    <p class="mt-2 text-sm text-gray-500">Klicke auf eine Karte, um zur jeweiligen Funktion zu gelangen.</p>
                `,
                position: 'bottom',
                highlightAll: true
            },
            {
                target: '.schedule-card, .upcoming-schedule',
                title: '📆 Dein Tagesplan',
                description: `
                    <p>Hier siehst du deinen persönlichen Zeitplan für die Berufsmesse:</p>
                    <ul class="mt-2 space-y-1 text-sm">
                        <li>🟢 <strong>Grün</strong> = Du bist erfolgreich angemeldet</li>
                        <li>🟣 <strong>Lila</strong> = Freie Wahl vor Ort (kein Slot nötig)</li>
                        <li>⚪ <strong>Grau</strong> = Noch keine Zuteilung</li>
                    </ul>
                    <p class="mt-2 text-sm text-gray-500">Klicke auf "Drucken", um deinen Plan auszudrucken.</p>
                `,
                position: 'left'
            },
            {
                target: 'a[href*="registration"], a[href*="exhibitors"]',
                title: '✏️ Einschreibung - So funktioniert\'s',
                description: `
                    <p><strong>Schritt-für-Schritt zur Anmeldung:</strong></p>
                    <ol class="mt-2 space-y-1 text-sm list-decimal list-inside">
                        <li><strong>Unternehmen auswählen</strong> - Stöbere durch die Aussteller</li>
                        <li><strong>Zeitslot wählen</strong> - Wähle einen freien Slot (1, 3 oder 5)</li>
                        <li><strong>Anmelden</strong> - Klicke auf "Anmelden" beim gewünschten Aussteller</li>
                        <li><strong>Bestätigung</strong> - Du erhältst eine Bestätigung deiner Anmeldung</li>
                    </ol>
                    <p class="mt-2 text-sm text-amber-600">
                        ⚠️ Du kannst dich für max. 3 verwaltete Slots anmelden. Die Slots 2 und 4 sind freie Wahl.
                    </p>
                `,
                position: 'right'
            },
            {
                target: null,
                title: 'Bereit? Los geht\'s! 🚀',
                description: `
                    <p>Super! Du kennst jetzt die wichtigsten Funktionen.</p>
                    <p class="mt-2"><strong>Nächste Schritte:</strong></p>
                    <ul class="mt-1 space-y-1 text-sm">
                        <li>→ Entdecke die Unternehmen</li>
                        <li>→ Melde dich für Zeitslots an</li>
                        <li>→ Schau dir deinen Zeitplan an</li>
                    </ul>
                    <p class="mt-3 text-sm text-gray-500">
                        💡 Tipp: Diese Tour findest du jederzeit im Dashboard.
                    </p>
                `,
                position: 'center'
            }
        ];
    }
}

// Backwards compatibility
const berufsmesseTourSteps = generateTourSteps('student');

// Export for use
window.GuidedTour = GuidedTour;
window.generateTourSteps = generateTourSteps;
window.berufsmesseTourSteps = berufsmesseTourSteps;

// Auto-resume tour on page load if state exists
document.addEventListener('DOMContentLoaded', () => {
    try {
        const raw = localStorage.getItem('berufsmesse_tour_state');
        if (!raw) return;
        const state = JSON.parse(raw);
        if (!state || !state.active) return;

        // Generate steps for role if possible
        const role = state.role || 'student';
        const steps = typeof generateTourSteps !== 'undefined' ? generateTourSteps(role) : (window.berufsmesseTourSteps || []);

        // Create tour instance and start at saved step
        window.currentGuidedTour = new GuidedTour({
            steps: steps,
            role: role,
            onComplete: () => {
                if (typeof showToast !== 'undefined') showToast('Tour abgeschlossen! 🎉', 'success');
                window.currentGuidedTour = null;
            },
            onSkip: () => {
                if (typeof showToast !== 'undefined') showToast('Tour übersprungen', 'info');
                window.currentGuidedTour = null;
            }
        });

        // Start from saved step
        const startStep = state.step || 0;
        window.currentGuidedTour.start(startStep);
        window.currentGuidedTour.showStep(startStep);
    } catch (e) {
        console.warn('Fehler beim Wiederherstellen der Tour:', e);
    }
});
