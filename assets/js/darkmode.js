/**
 * Darkmode Toggle-Logik
 * Speichert Präferenz in localStorage und respektiert OS-Setting als Default.
 */
(function() {
    'use strict';

    const STORAGE_KEY = 'berufsmesse-theme';

    /**
     * Gibt 'dark' oder 'light' zurück (gespeichert oder OS-Präferenz).
     */
    function getPreferredTheme() {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored === 'dark' || stored === 'light') return stored;
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    /**
     * Setzt das Theme auf dem <html>-Element.
     */
    function applyTheme(theme) {
        document.documentElement.classList.toggle('dark', theme === 'dark');
        document.documentElement.setAttribute('data-theme', theme);

        // Icon und Text des Toggle-Buttons aktualisieren
        const btn = document.getElementById('darkmode-toggle');
        if (btn) {
            const icon = btn.querySelector('i');
            const label = btn.querySelector('span');
            if (icon) {
                icon.className = theme === 'dark'
                    ? 'fas fa-sun'
                    : 'fas fa-moon';
            }
            if (label) {
                label.textContent = theme === 'dark' ? 'Hellmodus' : 'Dunkel';
            }
        }
    }

    /**
     * Schaltet zwischen dark und light um.
     */
    function toggleTheme() {
        const current = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
        const next = current === 'dark' ? 'light' : 'dark';
        localStorage.setItem(STORAGE_KEY, next);
        applyTheme(next);
    }

    // Sofort anwenden (vermeidet Flash of Unstyled Content)
    applyTheme(getPreferredTheme());

    // OS-Wechsel beobachten (falls kein expliziter Wert gespeichert)
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
        if (!localStorage.getItem(STORAGE_KEY)) {
            applyTheme(e.matches ? 'dark' : 'light');
        }
    });

    // Global verfügbar machen
    window.toggleDarkmode = toggleTheme;

    // Nach DOM-Ready nochmal anwenden (für den Button)
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            applyTheme(getPreferredTheme());
        });
    }
})();
