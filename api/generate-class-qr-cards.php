<?php
/**
 * QR-Karten-Klassensatz (QR-Check-in 2.0)
 * Erzeugt ein PDF mit Scheckkarten (Name, Klasse, persönlicher QR-Code) zum
 * Ausschneiden/Laminieren — pro Klasse. Lehrkräfte teilen sie morgens aus.
 */
require_once '../config.php';
require_once '../functions.php';
require_once '../fpdf/fpdf.php';
require_once '../lib/qr.php';

if (!isLoggedIn() || (!isAdmin() && !isSchoolAdmin() && !isTeacher() && !hasPermission('berichte_drucken'))) {
    die('Keine Berechtigung');
}

try {
    $db = getDB();
    $activeEditionId = getActiveEditionId();
    $schoolId = getCurrentSchoolId();
    $filterClass = $_GET['class'] ?? '';

    if (!function_exists('conv')) {
        function conv($str) {
            return iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', (string)$str);
        }
    }

    $sql = "SELECT id, firstname, lastname, class FROM users
            WHERE role = 'student' AND edition_id = ?";
    $params = [$activeEditionId];
    if ($schoolId !== null) { $sql .= " AND school_id = ?"; $params[] = $schoolId; }
    if ($filterClass !== '') { $sql .= " AND class = ?"; $params[] = $filterClass; }
    $sql .= " ORDER BY class, lastname, firstname";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll();

    if (empty($students)) {
        die('Keine Schüler gefunden.');
    }

    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->SetTitle(conv('QR-Karten' . ($filterClass ? ' Klasse ' . $filterClass : '')));
    $pdf->SetAutoPageBreak(false);

    // Kartenraster: 2 Spalten x 5 Reihen = 10 Karten/Seite
    $cardW = 95; $cardH = 52; $marginX = 10; $marginY = 12; $gapX = 0; $gapY = 1;
    $cols = 2; $rows = 5; $perPage = $cols * $rows;

    foreach ($students as $i => $s) {
        $posOnPage = $i % $perPage;
        if ($posOnPage === 0) {
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetTextColor(150, 150, 150);
            $pdf->SetXY(10, 5);
            $pdf->Cell(190, 5, conv('Berufsmesse ' . date('Y') . ' · Check-in-Karten'
                . ($filterClass ? ' · Klasse ' . $filterClass : '')), 0, 0, 'L');
            $pdf->SetTextColor(0, 0, 0);
        }
        $col = $posOnPage % $cols;
        $row = intval($posOnPage / $cols);
        $x = $marginX + $col * ($cardW + $gapX);
        $y = $marginY + $row * ($cardH + $gapY);

        // Rahmen (gestrichelt-Optik durch dünne graue Linie)
        $pdf->SetDrawColor(180, 180, 180);
        $pdf->Rect($x, $y, $cardW, $cardH);

        // QR links
        $token = getOrCreateStudentToken((int)$s['id'], $activeEditionId);
        qrDrawFpdf($pdf, $token, $x + 3, $y + 4, 44);

        // Text rechts
        $tx = $x + 50;
        $pdf->SetXY($tx, $y + 8);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->MultiCell($cardW - 52, 6, conv($s['firstname'] . ' ' . $s['lastname']), 0, 'L');
        $pdf->SetXY($tx, $y + 24);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell($cardW - 52, 6, conv('Klasse: ' . ($s['class'] ?: '—')), 0, 2, 'L');
        $pdf->SetFont('Arial', '', 7);
        $pdf->SetTextColor(120, 120, 120);
        $pdf->SetXY($tx, $y + 38);
        $pdf->MultiCell($cardW - 52, 4, conv('Persönlicher Check-in-Code · der Aufsicht zeigen'), 0, 'L');
        $pdf->SetTextColor(0, 0, 0);
    }

    $fname = 'QR-Karten' . ($filterClass ? '_' . preg_replace('/[^A-Za-z0-9_-]/', '', $filterClass) : '') . '_' . date('Y-m-d') . '.pdf';
    $pdf->Output('D', $fname);

} catch (Exception $e) {
    logErrorToAudit($e, 'PDF-QRKarten');
    if (!headers_sent()) { http_response_code(500); header('Content-Type: text/plain; charset=utf-8'); }
    die('Fehler beim Erstellen des PDFs.');
}
