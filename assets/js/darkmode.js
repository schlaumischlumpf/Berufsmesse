/**
 * Darkmode Toggle-Logik
 * Speichert Präferenz in localStorage und respektiert OS-Setting als Default.
 * Sanfter 300ms Übergang beim Umschalten.
 */
(function() {
    'use strict';

    var STORAGE_KEY = 'berufsmesse-theme';

    /**
     * Gibt 'dark' oder 'light' zurück (gespeichert oder OS-Präferenz).
     */
    function getPreferredTheme() {
        var stored = localStorage.getItem(STORAGE_KEY);
        if (stored === 'dark' || stored === 'light') return stored;
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    /**
     * Setzt das Theme auf dem <html>-Element.
     */
    function applyTheme(theme) {
        document.documentElement.classList.toggle('dark', theme === 'dark');
        document.documentElement.setAttribute('data-theme', theme);

        // Label aktualisieren
        var label = document.getElementById('darkmode-label');
        if (label) {
            label.textContent = theme === 'dark' ? 'Hellmodus' : 'Dunkel';
        }
    }

    /**
     * Schaltet zwischen dark und light um (mit Transition).
     */
    function toggleTheme() {
        var html = document.documentElement;
        var current = html.classList.contains('dark') ? 'dark' : 'light';
        var next = current === 'dark' ? 'light' : 'dark';

        // Sanfte Transition aktivieren
        html.style.transition = 'background-color 300ms ease, color 300ms ease';
        document.body.style.transition = 'background-color 300ms ease, color 300ms ease';

        localStorage.setItem(STORAGE_KEY, next);
        applyTheme(next);

        // Transition nach Animation entfernen
        setTimeout(function() {
            html.style.transition = '';
            document.body.style.transition = '';
        }, 350);
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

    // Nach DOM-Ready nochmal anwenden (für das Label)
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            applyTheme(getPreferredTheme());
        });
    }
})();
