/* Schuladmin-Tour (mehrseitig) */
(function () {
    if (!window.TourEngine) return;
    TourEngine.register('school-admin', [
        { title: 'Willkommen im Admin-Bereich 👋', text: 'Diese Tour führt dich durch Aufbau und Tagesbetrieb der Messe.' },
        { target: '[data-page="admin-dashboard"]', title: 'Übersicht', text: 'Kennzahlen und Status deiner Messe.' },
        { target: '[data-page="admin-exhibitors"]', title: 'Aussteller anlegen', text: 'Hier verwaltest du die teilnehmenden Unternehmen.' },
        { target: '[data-page="admin-rooms"]', title: 'Räume & Kapazitäten', text: 'Räume anlegen und Aussteller zuordnen.' },
        { target: '[data-page="admin-supervision"]', title: 'Aufsichtsplan', text: 'Weise Lehrkräfte je Raum und Slot zu — Grundlage für den Lehrer-Check-in.' },
        { target: '[data-page="admin-settings"]', title: 'Check-in-Einstellungen', text: 'Aktiviere Selbst- und/oder Lehrer-Check-in und das Zeitfenster (Tab „Check-in").' },
        { target: '[data-page="admin-qr-codes"]', title: 'QR-Codes drucken', text: 'Raum-QR-Codes und Schüler-QR-Karten erzeugen.' },
        { target: '[data-page="admin-attendance-live"]', title: 'Live-Anwesenheit', text: 'Am Messetag siehst du hier in Echtzeit, wer wo eingecheckt ist.' },
        { target: '[data-page="admin-attendance-report"]', title: 'Auswertung', text: 'Nach der Messe: Quoten, Ranking und No-Show-Liste — exportierbar.' },
        { target: '[data-page="admin-editions"]', title: 'Editionen & Zeiträume', text: 'Einschreibezeiträume und Messe-Editionen verwaltest du hier. Viel Erfolg!' }
    ]);
})();
