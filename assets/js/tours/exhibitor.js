/* Aussteller-Tour */
(function () {
    if (!window.TourEngine) return;
    TourEngine.register('exhibitor', [
        { title: 'Willkommen, Aussteller! 👋', text: 'Diese Tour zeigt dir, wie du dein Unternehmensprofil pflegst und dich auf die Messe vorbereitest.' },
        { target: '[data-page="exhibitor-dashboard"]', title: 'Dein Dashboard', text: 'Status, Aufgaben und Schnellzugriffe rund um deinen Auftritt.' },
        { target: '[data-page="exhibitor-profile"]', title: 'Unternehmensprofil', text: 'Beschreibung, Berufe und sichtbare Felder pflegst du hier — das sehen die Schüler.' },
        { target: '[data-page="exhibitor-slots"]', title: 'Slot-Anmeldungen', text: 'Hier siehst du, welche Schüler sich für deine Zeitfenster angemeldet haben.' },
        { target: '[data-page="exhibitor-equipment"]', title: 'Ausstattung', text: 'Beamer, Strom, Tische & Co. kannst du hier bei der Schule anfragen.' },
        { target: '[data-page="exhibitor-documents"]', title: 'Dokumente', text: 'Lade Materialien hoch und gib frei, was Schüler sehen dürfen. Viel Erfolg auf der Messe!' }
    ]);
})();
