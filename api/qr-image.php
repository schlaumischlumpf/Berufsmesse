<?php
/**
 * Lokaler QR-Bild-Endpunkt (QR-Check-in 2.0)
 * Liefert einen QR-Code als SVG — ersetzt qrserver.com (kein Token verlässt den Server).
 *
 * Parameter: data (Pflicht), scale (2–20), download (optionaler Dateiname)
 */
require_once '../config.php';
require_once '../functions.php';
require_once '../lib/qr.php';

if (!isLoggedIn()) {
    http_response_code(403);
    exit;
}

$data = (string)($_GET['data'] ?? '');
if ($data === '' || strlen($data) > 1000) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Ungültige Daten.';
    exit;
}
$scale = max(2, min(20, (int)($_GET['scale'] ?? 6)));

header('Content-Type: image/svg+xml; charset=utf-8');
if (!empty($_GET['download'])) {
    $fn = preg_replace('/[^A-Za-z0-9_.-]/', '', (string)$_GET['download']);
    if ($fn === '') $fn = 'qr.svg';
    if (!preg_match('/\.svg$/i', $fn)) $fn .= '.svg';
    header('Content-Disposition: attachment; filename="' . $fn . '"');
}
header('Cache-Control: private, max-age=300');

echo qrSvg($data, $scale, 4);
