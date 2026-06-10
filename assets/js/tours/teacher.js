/* Lehrer-Tour */
(function () {
    if (!window.TourEngine) return;
    TourEngine.register('teacher', [
        { title: 'Willkommen, Lehrkraft! 👋', text: 'Diese Tour zeigt dir Klassenübersicht und den Check-in-Scanner.' },
        { target: '[data-page="teacher-dashboard"]', title: 'Deine Übersicht', text: 'Statistiken und Schnellzugriffe findest du hier.' },
        { target: '[data-page="exhibitors"]', title: 'Unternehmen', text: 'Auch du kannst alle Aussteller einsehen.' },
        { target: '[data-page="teacher-scan"]', title: 'Check-in-Scanner', text: 'Falls der Lehrer-Check-in aktiv ist: hier scannst du die QR-Codes deiner Schüler.' },
        { target: '[data-page="teacher-scan"]', type: 'navigate', url: '?page=teacher-scan', nextLabel: 'Scanner öffnen', title: 'Scanner ansehen', text: 'Wir öffnen den Scanner.' },
        { target: '#roomSelect', title: 'Raum & Slot', text: 'Slot und dein zugewiesener Raum sind vorausgewählt. Du kannst den Raum für diese Session überschreiben.' },
        { target: '#toggleCameraBtn', title: 'Scannen', text: 'Kamera starten und den persönlichen QR-Code des Schülers in den Rahmen halten.' },
        { target: '#manualSearch', title: 'Manueller Check-in', text: 'Ohne Code? Suche den Schüler per Name/Klasse und checke ihn manuell ein.' }
    ]);
})();
