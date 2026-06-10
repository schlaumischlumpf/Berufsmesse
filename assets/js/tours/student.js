/* Schüler-Tour */
(function () {
    if (!window.TourEngine) return;
    TourEngine.register('student', [
        { title: 'Willkommen! 👋', text: 'Diese kurze Tour zeigt dir die wichtigsten Funktionen. Du kannst sie jederzeit über das Hilfe-Menü neu starten.' },
        { target: '[data-page="exhibitors"]', title: 'Unternehmen entdecken', text: 'Hier findest du alle Aussteller der Berufsmesse.' },
        { target: '[data-page="exhibitors"]', type: 'navigate', url: '?page=exhibitors', nextLabel: 'Aussteller öffnen', title: 'Zu den Unternehmen', text: 'Wir schauen sie uns gemeinsam an.' },
        { target: '#searchInput', title: 'Filtern & Suchen', text: 'Mit Suche und den Kategorie-Filtern findest du passende Unternehmen schnell.' },
        { target: '.exhibitor-card', title: 'Details ansehen', text: 'Tippe auf ein Unternehmen, um Details und Berufe zu sehen — und dich einzuschreiben (bis zu 3 Wünsche).' },
        { target: '[data-page="schedule"]', title: 'Mein Stundenplan', text: 'Deine Termine am Messetag findest du hier.' },
        { target: '[data-tour="my-qr"]', title: 'Mein QR-Code', text: 'Falls der Lehrer-Check-in aktiv ist, zeigst du diesen Code der Aufsicht zum Einchecken.' },
        { target: '[data-page="qr-checkin"]', title: 'Check-in & Hilfe', text: 'Hier kannst du selbst einchecken. Fragen? Wende dich an deine Lehrkraft. Viel Erfolg!' }
    ]);
})();
