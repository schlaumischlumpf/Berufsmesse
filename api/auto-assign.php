<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

requireAdmin();

set_time_limit(300); // 5 Minuten Timeout

$db = getDB();

// Transaktion starten
$db->beginTransaction();

try {
    // Alle Schüler ohne Anmeldungen finden
    $stmt = $db->query("
        SELECT u.id, u.firstname, u.lastname
        FROM users u
        WHERE u.role = 'student'
        AND u.id NOT IN (SELECT DISTINCT user_id FROM registrations)
    ");
    $studentsWithoutReg = $stmt->fetchAll();
    
    $maxRegistrations = intval(getSetting('max_registrations_per_student', 3));
    $assignedCount = 0;
    $errors = [];
    
    foreach ($studentsWithoutReg as $student) {
        // Für jeden Zeitslot einen Aussteller zuweisen
        $stmt = $db->query("SELECT id FROM timeslots ORDER BY slot_number ASC");
        $timeslots = $stmt->fetchAll();
        
        foreach ($timeslots as $index => $slot) {
            if ($index >= $maxRegistrations) break;
            
            // Aussteller mit den wenigsten Anmeldungen in diesem Slot finden
            $stmt = $db->prepare("
                SELECT e.id, e.total_slots, 
                       COUNT(r.id) as current_registrations
                FROM exhibitors e
                LEFT JOIN registrations r ON e.id = r.exhibitor_id AND r.timeslot_id = ?
                WHERE e.active = 1
                GROUP BY e.id
                HAVING current_registrations < (e.total_slots / 3)
                ORDER BY current_registrations ASC
                LIMIT 1
            ");
            $stmt->execute([$slot['id']]);
            $exhibitor = $stmt->fetch();
            
            if ($exhibitor) {
                // Prüfen ob Student bereits in diesem Slot registriert ist
                $stmt = $db->prepare("
                    SELECT COUNT(*) as count 
                    FROM registrations 
                    WHERE user_id = ? AND timeslot_id = ?
                ");
                $stmt->execute([$student['id'], $slot['id']]);
                $alreadyInSlot = $stmt->fetch()['count'];
                
                if ($alreadyInSlot == 0) {
                    // Automatische Zuteilung durchführen
                    $stmt = $db->prepare("
                        INSERT INTO registrations (user_id, exhibitor_id, timeslot_id, registration_type) 
                        VALUES (?, ?, ?, 'automatic')
                    ");
                    $stmt->execute([$student['id'], $exhibitor['id'], $slot['id']]);
                    $assignedCount++;
                }
            } else {
                $errors[] = "Kein verfügbarer Aussteller für {$student['firstname']} {$student['lastname']} in Slot " . ($index + 1);
            }
        }
    }
    
    // Transaktion bestätigen
    $db->commit();
    
    // Erfolgsmeldung
    $_SESSION['auto_assign_success'] = true;
    $_SESSION['auto_assign_count'] = $assignedCount;
    $_SESSION['auto_assign_students'] = count($studentsWithoutReg);
    $_SESSION['auto_assign_errors'] = $errors;
    
} catch (Exception $e) {
    // Rollback bei Fehler
    $db->rollBack();
    $_SESSION['auto_assign_error'] = $e->getMessage();
}

// Zurück zum Dashboard
header('Location: ../index.php?page=admin-dashboard&auto_assign=done');
exit();
?>
