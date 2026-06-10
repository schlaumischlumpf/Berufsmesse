/* Orga-Tour */
(function () {
    if (!window.TourEngine) return;
    TourEngine.register('orga', [
        { title: 'Willkommen im Orga-Team 👋', text: 'Kurzer Überblick über deine Aufgaben und Grenzen.' },
        { target: '[data-page="admin-exhibitors"]', title: 'Deine Aussteller', text: 'Du betreust die dir zugewiesenen Unternehmen.' },
        { target: '[data-page="admin-qr-codes"]', title: 'QR-Code-Verwaltung', text: 'Für „deine" Aussteller kannst du QR-Codes verwalten.' },
        { target: '[data-page="admin-attendance"]', title: 'Anwesenheit', text: 'Den Anwesenheitsstand kannst du im Blick behalten.' },
        { title: 'Grenzen deiner Rechte', text: 'Funktionen außerhalb deiner Zuständigkeit sind ausgeblendet. Bei Bedarf wende dich an die Schulleitung.' }
    ]);
})();
