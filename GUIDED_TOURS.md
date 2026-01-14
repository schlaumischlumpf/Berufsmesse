# Geführte Touren - Dokumentation

Die Berufsmesse-Plattform verfügt über drei spezialisierte geführte Touren, die automatisch an die Benutzerrolle angepasst werden.

## 📋 Übersicht

### 1. **Admin-Tour** 👑
**Zielgruppe:** Administratoren
**Umfang:** 8 Schritte
**Dauer:** ~8-10 Minuten

#### Inhalte:
- ✅ Willkommen & Überblick
- 📊 Admin-Dashboard Funktionen
- 👥 Benutzerverwaltung (Import, Rollen, Passwörter)
- 🏢 Ausstellerverwaltung (Anlegen, Räume, Dokumente)
- 🚪 Raumverwaltung & Kapazitäten
- 🖨️ Druck & Export (Reports, Listen, PDF)
- ⚙️ System-Einstellungen
- 🔐 Berechtigungen & Rollen

#### Features:
- Detaillierte Erklärungen mit Emojis und Farben
- Interaktive Highlights der UI-Elemente
- Tipps und Best Practices für Admin-Aufgaben
- Schrittweise Navigation durch Admin-Panel

---

### 2. **Lehrer-Tour** 👨‍🏫
**Zielgruppe:** Lehrkräfte
**Umfang:** 7 Schritte
**Dauer:** ~5-7 Minuten

#### Inhalte:
- ✅ Willkommen & Überblick
- 📊 Lehrer-Dashboard Statistiken
- 📋 Klassenlisten (nach Klasse filtern)
- 📈 Statistik-Karten (Anmeldestatus)
- 🖨️ Listen drucken & exportieren
- 📅 Zeitpläne ansehen

#### Features:
- Fokus auf Schüler-Überwachung
- Praktische Tipps für Klassenverwaltung
- Export- und Druck-Funktionen
- Anmeldestatus-Überwachung

---

### 3. **Schüler-Tour** 🎓
**Zielgruppe:** Schüler
**Umfang:** 5 Schritte
**Dauer:** ~3-5 Minuten

#### Inhalte:
- ✅ Willkommen & Überblick
- 📋 Navigation & Seitenleiste
- ⚡ Schnellzugriff-Karten
- 📆 Persönlicher Zeitplan
- ✏️ Anmeldungsprozess

#### Features:
- Einfache, verständliche Erklärungen
- Fokus auf die wichtigsten Funktionen
- Schritt-für-Schritt Anleitung zur Anmeldung
- Erklärung der Farbcodes

---

## 🚀 Touren starten

### Manuell starten
Jeder Benutzer kann die Tour jederzeit starten über:
- **Admin:** Button "Tour starten" im Admin-Dashboard Header
- **Lehrer:** Button "Tour starten" in der Tipps-Box am Ende der Seite
- **Schüler:** Button "Tour starten" im Dashboard oder auf der Startseite

### Automatisch beim Login (Optional)
Die Tour startet automatisch, wenn folgende URL aufgerufen wird:
```
?page=admin-dashboard&start_tour=1
?page=teacher-dashboard&start_tour=1
?page=dashboard&start_tour=1
```

---

## 💻 Technische Details

### Dateien
- **JavaScript:** `assets/js/guided-tour.js`
- **CSS:** `assets/css/guided-tour.css`
- **Integration:** `pages/admin-dashboard.php`, `pages/teacher-dashboard.php`, `pages/dashboard.php`, `index.php`

### Funktion: `generateTourSteps(userRole)`
```javascript
// Wird mit der Rolle aufgerufen: 'admin', 'teacher', oder 'student'
const steps = generateTourSteps('admin');
```

### Tour-Klasse
```javascript
const tour = new GuidedTour({
    steps: steps,
    role: userRole,
    onComplete: () => { /* Aktion nach Tour */ },
    onSkip: () => { /* Aktion beim Abbruch */ }
});

// Tour starten
tour.start();
```

### LocalStorage
Die Tour speichert ihren Zustand in LocalStorage:
- **Schlüssel:** `berufsmesse_tour_state`
- **Daten:** Aktuelle Schritt-Nummer, Rolle, Aktivitätsstatus

---

## 🎨 Styling

### CSS-Klassen
- `.tour-overlay` - Dunkler Hintergrund
- `.tour-spotlight` - Hervorgehobenes Element
- `.tour-tooltip` - Tooltip mit Erklärungen
- `.tour-highlight` - Visueller Fokus auf Element

### Farben & Icons
- ✅ = Erfolgreich / Erledigt
- ⚠️ = Warnung / Wichtig
- 💡 = Tipp / Hilfreiche Information
- 🎯 = Funktionalität / Ziel

---

## 📝 Touren bearbeiten

### Neuen Schritt hinzufügen
```javascript
// In generateTourSteps() Funktion
{
    target: '.selector-der-elemente',        // CSS Selector oder null für zentriert
    title: '🔥 Titel des Schritts',
    description: `
        <p>Erklärung mit HTML</p>
        <ul class="mt-2 space-y-1 text-sm">
            <li>Punkt 1</li>
            <li>Punkt 2</li>
        </ul>
    `,
    position: 'bottom',                       // top, bottom, left, right
    noBlur: false,                            // true = Element nicht blurred
    highlightAll: false                       // true = mehrere Elemente
}
```

### Position-Optionen
- `'top'` - Tooltip über dem Element
- `'bottom'` - Tooltip unter dem Element
- `'left'` - Tooltip links vom Element
- `'right'` - Tooltip rechts vom Element
- `'center'` - Zentriert im Viewport

---

## 🎯 Best Practices

### Für Admins
1. Neue Admins sollten die Tour beim ersten Login durchlaufen
2. Die Tour zeigt alle kritischen Funktionen
3. Tipps zu Best Practices sind eingebunden

### Für Lehrer
1. Fokus auf praktische Überwachungsaufgaben
2. Export-Funktionen werden deutlich erklärt
3. Schülerverwaltung steht im Mittelpunkt

### Für Schüler
1. Einfache, kurze Erklärungen
2. Fokus auf Anmeldungsprozess
3. Farbcodes und Status werden erklärt

---

## 🔧 Troubleshooting

### Tour startet nicht
- Prüfen Sie, ob `assets/js/guided-tour.js` geladen ist
- Konsole prüfen auf JavaScript-Fehler
- LocalStorage-Daten löschen und neu laden

### Elemente nicht hervorgehoben
- Überprüfen Sie den CSS-Selector
- Element muss im DOM vorhanden sein
- Probieren Sie mehrere Selektoren (mit Komma trennen)

### Tour wird unterbrochen
- Überprüfen Sie die LocalStorage-Größe
- Prüfen Sie auf Navigation während der Tour
- Browser-Entwicklertools zur Fehlersuche nutzen

---

## 📊 Statistiken

Die Touren tracken folgende Metriken:
- Begonnene Touren
- Abgeschlossene Touren
- Übersprungene Touren
- Abgebrochene Touren (Mid-Tour)

Hinweis: Aktuell keine Implementierung von Analytics. Kann später hinzugefügt werden.

---

## 🔄 Zukünftige Verbesserungen

- [ ] Analytics & Tracking
- [ ] Mehrsprachige Touren
- [ ] Mobile-spezifische Tour-Anpassungen
- [ ] Interaktive Übungen in Touren
- [ ] Video-Tutorials integrieren
- [ ] Tour-Vorlage für schnelle Erstellung
- [ ] Custom-Rollen-Touren

---

## 📞 Support

Bei Fragen oder Bugs:
1. Konsole (F12) auf Fehler prüfen
2. LocalStorage leeren und Browser neuladen
3. Auf Admin-Dashboard navigieren und Tour erneut starten

**Letzte Aktualisierung:** Januar 2026
