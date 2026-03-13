/**
 * QR-Kamera-Scanner für Berufsmesse
 * Nutzt die native Kamera-API und jsQR zur QR-Code-Erkennung.
 * Fallback auf Text-Eingabe wenn keine Kamera verfügbar.
 */
(function() {
    'use strict';

    let videoStream = null;
    let scanInterval = null;
    let lastScannedCode = '';
    let lastScanTime = 0;

    /**
     * Initialisiert den Kamera-Scanner.
     * @param {Object} options
     * @param {HTMLElement} options.container  - Container für Video-Preview
     * @param {Function}    options.onScan     - Callback bei erfolgreichem Scan
     * @param {Function}    [options.onError]  - Callback bei Fehler
     */
    function initScanner(options) {
        const { container, onScan, onError } = options;
        if (!container) return;

        // Prüfe ob Kamera-API verfügbar
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            if (onError) onError('Kamera nicht verfügbar in diesem Browser.');
            return;
        }

        // Video-Element erstellen
        const video = document.createElement('video');
        video.setAttribute('autoplay', '');
        video.setAttribute('playsinline', '');
        video.setAttribute('muted', '');
        video.style.width = '100%';
        video.style.maxWidth = '400px';
        video.style.borderRadius = '0.75rem';
        video.style.objectFit = 'cover';

        // Canvas für Frame-Analyse (unsichtbar)
        const canvas = document.createElement('canvas');
        canvas.style.display = 'none';
        const ctx = canvas.getContext('2d', { willReadFrequently: true });

        // Overlay für Scan-Indikator
        const overlay = document.createElement('div');
        overlay.style.position = 'relative';
        overlay.style.display = 'inline-block';
        overlay.innerHTML = `
            <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);
                        width:200px;height:200px;border:3px solid rgba(168,230,207,0.8);
                        border-radius:1rem;pointer-events:none;
                        box-shadow: 0 0 0 9999px rgba(0,0,0,0.3);">
                <div style="position:absolute;top:-2px;left:-2px;width:30px;height:30px;
                            border-top:4px solid #6bc4a6;border-left:4px solid #6bc4a6;border-radius:4px 0 0 0;"></div>
                <div style="position:absolute;top:-2px;right:-2px;width:30px;height:30px;
                            border-top:4px solid #6bc4a6;border-right:4px solid #6bc4a6;border-radius:0 4px 0 0;"></div>
                <div style="position:absolute;bottom:-2px;left:-2px;width:30px;height:30px;
                            border-bottom:4px solid #6bc4a6;border-left:4px solid #6bc4a6;border-radius:0 0 0 4px;"></div>
                <div style="position:absolute;bottom:-2px;right:-2px;width:30px;height:30px;
                            border-bottom:4px solid #6bc4a6;border-right:4px solid #6bc4a6;border-radius:0 0 4px 0;"></div>
            </div>
        `;

        overlay.prepend(video);
        container.innerHTML = '';
        container.appendChild(overlay);
        container.appendChild(canvas);

        // Kamera starten
        navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: 'environment',  // Rückkamera bevorzugen
                width: { ideal: 1280 },
                height: { ideal: 720 }
            }
        })
        .then(function(stream) {
            videoStream = stream;
            video.srcObject = stream;

            video.addEventListener('loadedmetadata', function() {
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;

                // Frame-Scanning starten (alle 200ms)
                scanInterval = setInterval(function() {
                    if (video.readyState !== video.HAVE_ENOUGH_DATA) return;

                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);

                    // jsQR zur Erkennung (muss extern geladen sein)
                    if (typeof jsQR !== 'undefined') {
                        const code = jsQR(imageData.data, imageData.width, imageData.height, {
                            inversionAttempts: 'dontInvert'
                        });

                        if (code && code.data) {
                            // Duplikat-Schutz (gleicher Code innerhalb 3 Sekunden ignorieren)
                            const now = Date.now();
                            if (code.data === lastScannedCode && now - lastScanTime < 3000) return;

                            lastScannedCode = code.data;
                            lastScanTime = now;

                            // Kurzes visuelles Feedback
                            video.style.borderColor = '#6bc4a6';
                            video.style.border = '3px solid #6bc4a6';
                            setTimeout(function() {
                                video.style.border = 'none';
                            }, 500);

                            if (onScan) onScan(code.data);
                        }
                    }
                }, 200);
            });
        })
        .catch(function(err) {
            console.warn('Kamera-Zugriff fehlgeschlagen:', err);
            if (onError) onError('Kamera-Zugriff verweigert oder nicht verfügbar: ' + err.message);
        });
    }

    /**
     * Stoppt den Scanner und gibt die Kamera frei.
     */
    function stopScanner() {
        if (scanInterval) {
            clearInterval(scanInterval);
            scanInterval = null;
        }
        if (videoStream) {
            videoStream.getTracks().forEach(function(track) { track.stop(); });
            videoStream = null;
        }
        lastScannedCode = '';
    }

    // Global verfügbar machen
    window.QRCameraScanner = {
        init: initScanner,
        stop: stopScanner
    };
})();
