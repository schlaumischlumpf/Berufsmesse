/**
 * Tour-Selftest (Phase 5)
 * Prüft, ob die in den Tour-Definitionen referenzierten Selektoren auf der
 * aktuellen Seite existieren. Reine Entwickler-Hilfe — nur aktiv mit ?tourtest=1.
 * Da Touren seitenübergreifend sind, ist „fehlt hier" oft normal; der Report
 * hilft, gebrochene Anker (z. B. nach UI-Änderungen) zu finden.
 */
(function () {
    'use strict';
    try {
        if (new URLSearchParams(location.search).get('tourtest') !== '1') return;
    } catch (e) { return; }
    if (!window.TourEngine || !TourEngine.list) {
        console.warn('[tour-selftest] TourEngine nicht verfügbar.');
        return;
    }
    var tours = TourEngine.list();
    var report = {};
    Object.keys(tours).forEach(function (key) {
        var present = [], missing = [];
        (tours[key] || []).forEach(function (step, i) {
            var sel = typeof step.target === 'function' ? null : step.target;
            if (!sel) return; // zentrierte Schritte ohne Ziel
            (document.querySelector(sel) ? present : missing).push('#' + (i + 1) + ' ' + sel);
        });
        report[key] = { present: present, missing: missing };
    });
    console.groupCollapsed('%c[tour-selftest] Anker-Report (aktuelle Seite)', 'color:#6bc4a6;font-weight:bold');
    Object.keys(report).forEach(function (key) {
        console.log('Tour "' + key + '": ' + report[key].present.length + ' vorhanden, '
            + report[key].missing.length + ' hier nicht gefunden');
        if (report[key].missing.length) console.log('   nicht gefunden:', report[key].missing);
    });
    console.groupEnd();
})();
