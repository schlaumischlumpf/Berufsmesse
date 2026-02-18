<?php
/**
 * PDF-Generator für Klassenübersichten
 * Verwendet FPDF zur Erstellung professioneller PDFs
 */
session_start();
require_once '../config.php';
require_once '../functions.php';
require_once '../fpdf/fpdf.php';

// Berechtigungsprüfung
if (!isLoggedIn() || (!isAdmin() && !isTeacher() && !hasPermission('berichte_drucken'))) {
    die('Keine Berechtigung');
}

$db = getDB();

// Filter
$filterClass = $_GET['class'] ?? '';

// Daten laden
$query = "
    SELECT
        u.firstname, u.lastname, u.class,
        e.name as exhibitor_name,
        t.slot_name, t.slot_number, t.start_time, t.end_time,
        r.room_number, r.room_name, r.building
    FROM registrations reg
    JOIN users u ON reg.user_id = u.id
    JOIN exhibitors e ON reg.exhibitor_id = e.id
    JOIN timeslots t ON reg.timeslot_id = t.id
    LEFT JOIN rooms r ON e.room_id = r.id
    WHERE u.role = 'student'
";

$params = [];
if ($filterClass) {
    $query .= " AND u.class = ?";
    $params[] = $filterClass;
}

$query .= " ORDER BY u.class, u.lastname, u.firstname, t.slot_number";

$stmt = $db->prepare($query);
$stmt->execute($params);
$registrations = $stmt->fetchAll();

// Nach Klasse gruppieren
$groupedByClass = [];
foreach ($registrations as $reg) {
    $class = $reg['class'] ?: 'Keine Klasse';
    $studentKey = $reg['lastname'] . ', ' . $reg['firstname'];
    $groupedByClass[$class][$studentKey][] = $reg;
}
ksort($groupedByClass);

// Helper: UTF-8 zu Latin-1 konvertieren
function conv($str) {
    return iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $str);
}

// Titel bestimmen
if ($filterClass) {
    $docTitle = "Klasse $filterClass";
} else {
    $docTitle = 'Alle Klassen';
}

$eventDate = getSetting('event_date') ?? date('Y-m-d');

// PDF Klasse
class ClassPDF extends FPDF {
    public $eventDate;
    public $docTitle;

    function Header() {
        // Mint-farbener Header-Balken
        $this->SetFillColor(168, 230, 207);
        $this->Rect(10, 10, 190, 22, 'F');

        // Titel
        $this->SetFont('Arial', 'B', 16);
        $this->SetXY(15, 13);
        $this->Cell(100, 8, 'Berufsmesse ' . date('Y'), 0, 1);

        $this->SetFont('Arial', '', 10);
        $this->SetXY(15, 21);
        $this->Cell(100, 5, conv($this->docTitle), 0, 1);

        // Datum rechts
        $this->SetFont('Arial', 'B', 11);
        $this->SetXY(140, 13);
        $this->Cell(55, 6, date('d.m.Y', strtotime($this->eventDate)), 0, 1, 'R');

        $this->SetFont('Arial', '', 8);
        $this->SetXY(140, 19);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(55, 4, 'Erstellt am ' . date('d.m.Y, H:i') . ' Uhr', 0, 1, 'R');
        $this->SetTextColor(0, 0, 0);

        $this->Ln(15);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(150, 150, 150);
        $this->Cell(0, 10, 'Berufsmesse ' . date('Y') . ' - ' . conv($this->docTitle) . ' - Seite ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        $this->SetTextColor(0, 0, 0);
    }

    function ClassHeader($className, $studentCount) {
        $this->SetFillColor(212, 245, 228);
        $this->SetDrawColor(168, 230, 207);
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, conv($className) . ' (' . $studentCount . ' ' . conv('Schüler') . ')', 1, 1, 'L', true);
        $this->Ln(3);
    }

    function StudentCard($studentName, $registrations) {
        // Linke Bordüre
        $y = $this->GetY();
        $this->SetFillColor(168, 230, 207);
        $this->Rect(10, $y, 2, 8, 'F');

        // Student Name
        $this->SetFont('Arial', 'B', 10);
        $this->SetX(14);
        $this->Cell(0, 8, conv($studentName), 0, 1);

        // Tabelle Header
        $this->SetFillColor(243, 244, 246);
        $this->SetDrawColor(200, 200, 200);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(30, 6, 'Slot', 1, 0, 'L', true);
        $this->Cell(30, 6, 'Zeit', 1, 0, 'L', true);
        $this->Cell(85, 6, 'Aussteller', 1, 0, 'L', true);
        $this->Cell(30, 6, 'Raum', 1, 1, 'L', true);

        // Registrierungen
        $this->SetFont('Arial', '', 8);
        usort($registrations, fn($a, $b) => $a['slot_number'] <=> $b['slot_number']);

        $alt = false;
        foreach ($registrations as $reg) {
            if ($alt) {
                $this->SetFillColor(249, 250, 251);
            } else {
                $this->SetFillColor(255, 255, 255);
            }
            $this->Cell(30, 6, conv($reg['slot_name']), 1, 0, 'L', true);
            $this->Cell(30, 6, date('H:i', strtotime($reg['start_time'])) . '-' . date('H:i', strtotime($reg['end_time'])), 1, 0, 'L', true);
            $this->Cell(85, 6, conv($reg['exhibitor_name']), 1, 0, 'L', true);
            $this->Cell(30, 6, conv($reg['room_number'] ?? '-'), 1, 1, 'L', true);
            $alt = !$alt;
        }

        $this->Ln(5);
    }
}

// PDF erstellen
$pdf = new ClassPDF();
$pdf->eventDate = $eventDate;
$pdf->docTitle = $docTitle;
$pdf->SetTitle('Berufsmesse - ' . $docTitle);
$pdf->SetAuthor($_SESSION['firstname'] . ' ' . $_SESSION['lastname']);
$pdf->SetCreator('Berufsmesse System');
$pdf->AliasNbPages();

if (empty($groupedByClass)) {
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 20, conv('Keine Registrierungen gefunden.'), 0, 1, 'C');
} else {
    foreach ($groupedByClass as $class => $students) {
        $pdf->AddPage();
        $pdf->ClassHeader($class, count($students));

        ksort($students);
        foreach ($students as $studentName => $regs) {
            // Seitenumbruch prüfen (genug Platz für Name + Header + mind. 1 Zeile)
            $neededHeight = 8 + 6 + (count($regs) * 6) + 5;
            if ($pdf->GetY() + $neededHeight > 270) {
                $pdf->AddPage();
            }
            $pdf->StudentCard($studentName, $regs);
        }
    }
}

// PDF ausgeben
$filename = 'Berufsmesse_' . ($filterClass ? str_replace(' ', '_', $filterClass) : 'Alle_Klassen') . '_' . date('Y-m-d') . '.pdf';
$pdf->Output('D', $filename);
