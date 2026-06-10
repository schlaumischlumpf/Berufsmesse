/**
 * Tour-Engine (QR-Check-in 2.0, Phase 5)
 * -----------------------------------------------------------------------------
 * Interaktive, seitenübergreifende Onboarding-Touren. Vanilla JS, keine Abhängigkeiten.
 *
 * Schritt-Typen:
 *   info     – Spotlight + Text, Weiter sofort möglich
 *   action   – wartet auf Klick/Eingabe am Zielelement (Weiter erst danach aktiv)
 *   navigate – führt auf eine andere Seite; Tour läuft dort weiter
 *
 * Robustheit: fehlt ein Zielelement (UI geändert/Berechtigung), wird der Schritt
 * übersprungen statt die Tour abzubrechen. Fortschritt wird serverseitig gespeichert
 * (api/tour-progress.php) und übersteht Seitenwechsel/Gerätewechsel.
 *
 * Konfiguration über window.TOUR_CONFIG = { endpoint, csrf, role, baseUrl }.
 */
(function () {
    'use strict';

    var tours = {};            // key -> steps[]
    var cfg = window.TOUR_CONFIG || {};
    var state = null;          // { key, steps, index, els:{} }

    // ---- Fortschritt-Persistenz -------------------------------------------------
    function saveProgress(key, step, completed) {
        try {
            fetch(cfg.endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': cfg.csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ tour: key, step: step, completed: !!completed })
            });
        } catch (e) { /* offline → egal */ }
    }
    function loadProgress(key) {
        return fetch(cfg.endpoint + (cfg.endpoint.indexOf('?') >= 0 ? '&' : '?') + 'tour=' + encodeURIComponent(key),
            { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .catch(function () { return { current_step: 0, completed: false }; });
    }

    // ---- DOM-Helfer -------------------------------------------------------------
    function waitForElement(selector, timeout) {
        return new Promise(function (resolve) {
            if (!selector) { resolve(null); return; }
            var existing = document.querySelector(selector);
            if (existing) { resolve(existing); return; }
            var done = false;
            var obs = new MutationObserver(function () {
                var el = document.querySelector(selector);
                if (el) { done = true; obs.disconnect(); resolve(el); }
            });
            obs.observe(document.body, { childList: true, subtree: true });
            setTimeout(function () { if (!done) { obs.disconnect(); resolve(document.querySelector(selector)); } }, timeout || 2500);
        });
    }

    // ---- Overlay / UI -----------------------------------------------------------
    function buildUI() {
        if (document.getElementById('tourOverlay')) return;
        var ov = document.createElement('div');
        ov.id = 'tourOverlay';
        ov.innerHTML =
            '<div id="tourBackdrop"></div>' +
            '<div id="tourSpot"></div>' +
            '<div id="tourBox" role="dialog" aria-live="polite">' +
              '<button id="tourClose" aria-label="Tour beenden">&times;</button>' +
              '<div id="tourStepNo"></div>' +
              '<h3 id="tourTitle"></h3>' +
              '<p id="tourText"></p>' +
              '<div id="tourActionHint"></div>' +
              '<div id="tourNav">' +
                '<button id="tourSkip" class="tour-btn-text">Tour überspringen</button>' +
                '<div class="tour-nav-right">' +
                  '<button id="tourPrev" class="tour-btn-sec">Zurück</button>' +
                  '<button id="tourNext" class="tour-btn-pri">Weiter</button>' +
                '</div>' +
              '</div>' +
            '</div>';
        document.body.appendChild(ov);

        document.getElementById('tourClose').addEventListener('click', end);
        document.getElementById('tourSkip').addEventListener('click', end);
        document.getElementById('tourPrev').addEventListener('click', function () { go(state.index - 1); });
        document.getElementById('tourNext').addEventListener('click', function () { go(state.index + 1); });
        document.addEventListener('keydown', escHandler);
        window.addEventListener('resize', reposition);
        window.addEventListener('scroll', reposition, true);
    }
    function escHandler(e) { if (e.key === 'Escape' && state) end(); }

    function showUI() { var ov = document.getElementById('tourOverlay'); if (ov) ov.classList.add('active'); }
    function hideUI() { var ov = document.getElementById('tourOverlay'); if (ov) ov.classList.remove('active'); }

    var currentTarget = null;
    function reposition() {
        if (!state) return;
        var spot = document.getElementById('tourSpot');
        var box = document.getElementById('tourBox');
        if (!spot || !box) return;
        if (currentTarget && document.body.contains(currentTarget)) {
            var r = currentTarget.getBoundingClientRect();
            var pad = 6;
            spot.style.display = 'block';
            spot.style.top = (r.top - pad) + 'px';
            spot.style.left = (r.left - pad) + 'px';
            spot.style.width = (r.width + pad * 2) + 'px';
            spot.style.height = (r.height + pad * 2) + 'px';
            // Box unter oder über dem Ziel platzieren
            var below = r.bottom + 12;
            if (below + box.offsetHeight > window.innerHeight) {
                box.style.top = Math.max(12, r.top - box.offsetHeight - 12) + 'px';
            } else {
                box.style.top = below + 'px';
            }
            var left = Math.min(Math.max(12, r.left), window.innerWidth - box.offsetWidth - 12);
            box.style.left = left + 'px';
        } else {
            spot.style.display = 'none';
            box.style.top = '50%';
            box.style.left = '50%';
            box.style.transform = 'translate(-50%, -50%)';
        }
    }

    // ---- Ablaufsteuerung --------------------------------------------------------
    function start(key) {
        if (!tours[key]) { return; }
        loadProgress(key).then(function (p) {
            var startIdx = (p && !p.completed && p.current_step) ? p.current_step : 0;
            beginAt(key, startIdx);
        });
    }
    function restart(key) { beginAt(key, 0); }

    function beginAt(key, idx) {
        state = { key: key, steps: tours[key], index: idx };
        buildUI();
        showUI();
        render();
    }

    function go(idx) {
        if (!state) return;
        if (idx < 0) { render(); return; }
        if (idx >= state.steps.length) { complete(); return; }
        state.index = idx;
        saveProgress(state.key, idx, false);
        render();
    }

    function cleanupActionListener() {
        if (state && state._actionCleanup) { state._actionCleanup(); state._actionCleanup = null; }
    }

    function render() {
        if (!state) return;
        cleanupActionListener();
        var step = state.steps[state.index];
        if (!step) { complete(); return; }

        var box = document.getElementById('tourBox');
        box.style.transform = '';

        var sel = typeof step.target === 'function' ? step.target() : step.target;
        waitForElement(sel, step.waitTimeout).then(function (el) {
            // Schritt überspringen, wenn Ziel fehlt (Robustheit)
            if (sel && !el) {
                if (state.index < state.steps.length - 1) { return go(state.index + 1); }
                return complete();
            }
            currentTarget = el;
            if (el && el.scrollIntoView) { try { el.scrollIntoView({ block: 'center', behavior: 'smooth' }); } catch (e) {} }

            document.getElementById('tourStepNo').textContent = 'Schritt ' + (state.index + 1) + ' / ' + state.steps.length;
            document.getElementById('tourTitle').textContent = step.title || '';
            document.getElementById('tourText').innerHTML = step.text || '';
            document.getElementById('tourPrev').style.visibility = state.index === 0 ? 'hidden' : 'visible';

            var nextBtn = document.getElementById('tourNext');
            var hint = document.getElementById('tourActionHint');
            hint.textContent = '';

            if (step.type === 'navigate' && step.url) {
                nextBtn.textContent = step.nextLabel || 'Weiter';
                nextBtn.disabled = false;
                nextBtn.onclick = function () {
                    saveProgress(state.key, state.index + 1, false);
                    try { sessionStorage.setItem('tourContinue', state.key); } catch (e) {}
                    window.location.href = step.url;
                };
            } else if (step.type === 'action' && el) {
                nextBtn.textContent = 'Weiter';
                nextBtn.disabled = true;
                hint.textContent = step.actionHint || 'Führe die Aktion aus, um fortzufahren.';
                var evt = step.event || 'click';
                var handler = function () {
                    nextBtn.disabled = false;
                    if (step.autoAdvance !== false) { setTimeout(function () { go(state.index + 1); }, 350); }
                };
                el.addEventListener(evt, handler, { once: true });
                state._actionCleanup = function () { el.removeEventListener(evt, handler); };
                nextBtn.onclick = function () { go(state.index + 1); };
            } else {
                nextBtn.textContent = (state.index === state.steps.length - 1) ? 'Fertig' : 'Weiter';
                nextBtn.disabled = false;
                nextBtn.onclick = function () { go(state.index + 1); };
            }

            reposition();
        });
    }

    function complete() {
        if (!state) return;
        saveProgress(state.key, state.steps.length, true);
        var k = state.key;
        teardown();
        // kleine Erfolgsmeldung
        toast('Tour abgeschlossen 🎉');
        try { localStorage.setItem('tourDone_' + k, '1'); } catch (e) {}
    }
    function end() {
        if (!state) return;
        saveProgress(state.key, state.index, false);
        teardown();
    }
    function teardown() {
        cleanupActionListener();
        currentTarget = null;
        state = null;
        hideUI();
    }

    function toast(msg) {
        var t = document.createElement('div');
        t.className = 'tour-toast';
        t.textContent = msg;
        document.body.appendChild(t);
        setTimeout(function () { t.classList.add('show'); }, 10);
        setTimeout(function () { t.classList.remove('show'); setTimeout(function () { t.remove(); }, 300); }, 2600);
    }

    // ---- Auto-Resume nach Seitenwechsel ----------------------------------------
    function autoResume() {
        // 1) Nahtlose Fortsetzung nach einem navigate-Schritt (kein Banner)
        var cont = null;
        try { cont = sessionStorage.getItem('tourContinue'); } catch (e) {}
        if (cont && tours[cont]) {
            try { sessionStorage.removeItem('tourContinue'); } catch (e) {}
            loadProgress(cont).then(function (p) {
                if (p && !p.completed && p.current_step < tours[cont].length) { beginAt(cont, p.current_step); }
            });
            return;
        }
        // 2) Sonst: angefangene Tour der Rolle per Banner anbieten
        var key = cfg.role;
        if (!key || !tours[key]) return;
        loadProgress(key).then(function (p) {
            if (p && !p.completed && p.current_step > 0 && p.current_step < tours[key].length) {
                resumeBanner(key, p.current_step);
            }
        });
    }
    function resumeBanner(key, step) {
        if (document.getElementById('tourResumeBanner')) return;
        var b = document.createElement('div');
        b.id = 'tourResumeBanner';
        b.className = 'tour-resume-banner';
        b.innerHTML = '<span>Tour fortsetzen?</span>' +
            '<button id="tourResumeYes" class="tour-btn-pri">Weiter</button>' +
            '<button id="tourResumeNo" class="tour-btn-text">Später</button>';
        document.body.appendChild(b);
        document.getElementById('tourResumeYes').addEventListener('click', function () { b.remove(); beginAt(key, step); });
        document.getElementById('tourResumeNo').addEventListener('click', function () { b.remove(); });
    }

    // ---- Öffentliche API --------------------------------------------------------
    window.TourEngine = {
        register: function (key, steps) { tours[key] = steps; },
        start: start,
        restart: restart,
        autoResume: autoResume,
        has: function (key) { return !!tours[key]; },
        list: function () { return tours; }
    };
})();
