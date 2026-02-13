# Berufsmesse Frontend Redesign

## 🎨 Design System Overview

Dieses Redesign implementiert ein modernes, benutzerfreundliches Interface mit:
- **Pastellfarben** für eine beruhigende, einladende Atmosphäre
- **Subtile Animationen** für verbesserte User Experience
- **Responsive Design** für alle Gerätegrößen
- **Guided Tour** für neue Benutzer

---

## 🌈 Farbpalette

### Primäre Pastellfarben

| Farbe | Hex Code | CSS Variable | Verwendung |
|-------|----------|--------------|------------|
| Mint | `#a8e6cf` | `--color-pastel-mint` | Primäre Aktionen, Erfolg |
| Lavender | `#c3b1e1` | `--color-pastel-lavender` | Sekundäre Akzente |
| Peach | `#ffb7b2` | `--color-pastel-peach` | Warnungen, Wichtiges |
| Sky | `#b5deff` | `--color-pastel-sky` | Informationen |
| Butter | `#fff3b0` | `--color-pastel-butter` | Hinweise |
| Rose | `#ffc8dd` | `--color-pastel-rose` | Besondere Highlights |

### Varianten
Jede Farbe hat eine `light` und `dark` Variante:
- `-light`: Für Hintergründe und subtile Akzente
- `-dark`: Für Text und Hover-States

---

## ✨ Animationen

### Verfügbare Animationen

```css
/* Fade Animationen */
.animate-fade-in       /* Einfaches Einblenden */
.animate-fade-in-up    /* Einblenden von unten */
.animate-fade-in-down  /* Einblenden von oben */

/* Slide Animationen */
.animate-slide-in-left  /* Von links einschieben */
.animate-slide-in-right /* Von rechts einschieben */

/* Sonstige */
.animate-scale-in      /* Skalieren beim Erscheinen */
.animate-bounce        /* Sanftes Hüpfen */
.animate-float         /* Schwebendes Element */
```

### Stagger-Delays
Für verzögerte Animationen in Listen:
```html
<div class="animate-fade-in-up stagger-1">Erstes Element</div>
<div class="animate-fade-in-up stagger-2">Zweites Element</div>
<div class="animate-fade-in-up stagger-3">Drittes Element</div>
```

---

## 🧩 Komponenten

### Buttons

```html
<!-- Primär (Mint-Gradient) -->
<button class="btn btn-primary">Aktion</button>

<!-- Sekundär (Weiß mit Border) -->
<button class="btn btn-secondary">Sekundär</button>

<!-- Lavender Variante -->
<button class="btn btn-lavender">Lavender</button>

<!-- Peach Variante -->
<button class="btn btn-peach">Peach</button>

<!-- Ghost (Transparent) -->
<button class="btn btn-ghost">Ghost</button>
```

### Cards

```html
<!-- Standard Card -->
<div class="card p-6">
    Inhalt
</div>

<!-- Pastel Cards -->
<div class="card card-pastel-mint p-6">Mint Card</div>
<div class="card card-pastel-lavender p-6">Lavender Card</div>
<div class="card card-pastel-peach p-6">Peach Card</div>
<div class="card card-pastel-sky p-6">Sky Card</div>
```

### Badges

```html
<span class="badge badge-mint">Mint</span>
<span class="badge badge-lavender">Lavender</span>
<span class="badge badge-peach">Peach</span>
<span class="badge badge-sky">Sky</span>
<span class="badge badge-butter">Butter</span>
```

---

## 🗺️ Navigation

### Überarbeitete Sidebar-Struktur

**Für Schüler:**
- Dashboard (Startseite mit integriertem Kalender & Einschreibungen)
- Unternehmen (Aussteller-Übersicht)

**Entfernt aus Sidebar:**
- ~~Kalender~~ → Jetzt auf Dashboard integriert
- ~~Einschreibungen~~ → Jetzt auf Dashboard integriert
- ~~Meine Slots~~ → Erreichbar über Dashboard Quick-Actions

---

## 🎓 Guided Tour

### Verwendung

```javascript
// Tour starten
function startGuidedTour() {
    const tour = new GuidedTour({
        steps: berufsmesseTourSteps,
        onComplete: () => {
            showToast('Tour abgeschlossen!', 'success');
        }
    });
    tour.start();
}

// Tour zurücksetzen
tour.reset();
```

### Eigene Steps definieren

```javascript
const customSteps = [
    {
        target: '.element-selector',
        title: 'Schritt Titel',
        description: 'Beschreibung des Schritts',
        position: 'bottom' // top, bottom, left, right
    }
];
```

---

## 📱 Responsive Design

### Breakpoints

| Breakpoint | Width | Verwendung |
|------------|-------|------------|
| sm | 640px | Kleine Geräte |
| md | 768px | Tablets |
| lg | 1024px | Desktop |
| xl | 1280px | Große Bildschirme |

### Mobile Sidebar
Die Sidebar wird auf mobilen Geräten (< 768px) ausgeblendet und über einen Hamburger-Button zugänglich gemacht.

---

## 🔧 JavaScript Utilities

### Toast Notifications

```javascript
// Erfolg
showToast('Aktion erfolgreich!', 'success');

// Fehler
showToast('Ein Fehler ist aufgetreten', 'error');

// Warnung
showToast('Bitte beachten Sie...', 'warning');

// Info
showToast('Wussten Sie schon?', 'info');
```

### Skeleton Loading

```javascript
// Container mit Skeleton-Elementen füllen
showSkeleton(document.getElementById('container'), 5);
```

---

## 📁 Dateistruktur

```
assets/
├── css/
│   ├── design-system.css    # Hauptstyles & CSS Variables
│   └── guided-tour.css      # Tour-spezifische Styles
├── js/
│   ├── animations.js        # Micro-Animations & Interactions
│   ├── guided-tour.js       # Tour-Logik
│   └── tailwind-config.js   # Tailwind Extensions
└── images/                   # Bildressourcen

pages/
└── dashboard.php            # Neue Homepage mit integriertem Kalender
```

---

## 🚀 Quick Start

1. Stelle sicher, dass alle Asset-Dateien geladen werden
2. Die neue Startseite ist automatisch das Dashboard
3. Der Guided Tour startet automatisch beim ersten Besuch
4. Alle Animationen sind auf 300-500ms begrenzt für optimale UX

---

## 📝 Changelog

### Version 2.0 (Januar 2026)
- ✅ Pastellfarben-Palette implementiert
- ✅ Subtile Animationen hinzugefügt
- ✅ Sidebar umstrukturiert
- ✅ Dashboard als neue Homepage
- ✅ Guided Tour Feature
- ✅ Responsive Verbesserungen
- ✅ Login-Seite redesigned
