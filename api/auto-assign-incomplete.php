<?php
/**
 * Auto-Zuweisung für unvollständige Registrierungen
 * Verteilt Schüler, die sich nicht für alle 3 Slots registriert haben,
 * automatisch auf die Aussteller mit den wenigsten Teilnehmern
 */

session_start();
require_once '../config.php';
require_once '../functions.php';

header('Content-Type: application/json');

if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Keine Berechtigung']);
    exit;
}

$db = getDB();

try {
    // Verwaltete Slots (nur 1, 3, 5)
    $managedSlots = [1, 3, 5];
    
    $assignedCount = 0;
    $errors = [];
    
    // Alle aktiven Schüler laden
    $stmt = $db->query("SELECT id FROM users WHERE role = 'student'");
    $students = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($students as $studentId) {
        // Prüfen, für welche verwalteten Slots der Schüler bereits registriert ist
        $stmt = $db->prepare("
            SELECT t.slot_number 
            FROM registrations r
            JOIN timeslots t ON r.timeslot_id = t.id
            WHERE r.user_id = ? AND t.slot_number IN (1, 3, 5)
        ");
        $stmt->execute([$studentId]);
        $registeredSlots = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Fehlende Slots ermitteln
        $missingSlots = array_diff($managedSlots, $registeredSlots);
        
        if (empty($missingSlots)) {
            continue; // Schüler hat alle 3 Slots
        }
        
        // Für jeden fehlenden Slot den Aussteller mit den wenigsten Teilnehmern finden
        foreach ($missingSlots as $slotNumber) {
            // Timeslot ID ermitteln
            $stmt = $db->prepare("SELECT id FROM timeslots WHERE slot_number = ?");
            $stmt->execute([$slotNumber]);
            $timeslotId = $stmt->fetchColumn();
            
            if (!$timeslotId) {
                $errors[] = "Slot $slotNumber nicht gefunden";
                continue;
            }
            
            // Aussteller mit wenigsten Teilnehmern in diesem Slot finden
            // die noch nicht ihre Kapazität erreicht haben
            $stmt = $db->prepare("
                SELECT e.id, e.name, r.capacity, FLOOR(r.capacity / 3) as slots_per_timeslot,
                       COUNT(DISTINCT reg.user_id) as current_count
                FROM exhibitors e
                LEFT JOIN rooms r ON e.room_id = r.id
                LEFT JOIN registrations reg ON e.id = reg.exhibitor_id AND reg.timeslot_id = ?
                WHERE e.active = 1 AND e.room_id IS NOT NULL AND r.capacity IS NOT NULL
                GROUP BY e.id
                HAVING current_count < FLOOR(r.capacity / 3)
                ORDER BY current_count ASC, RAND()
                LIMIT 1
            ");
            $stmt->execute([$timeslotId]);
            $exhibitor = $stmt->fetch();
            
            if (!$exhibitor) {
                $errors[] = "Kein verfügbarer Aussteller für Slot $slotNumber (Schüler ID: $studentId)";
                continue;
            }
            
            // Prüfen, ob Schüler bereits bei diesem Aussteller in einem anderen Slot ist
            $stmt = $db->prepare("
                SELECT COUNT(*) 
                FROM registrations 
                WHERE user_id = ? AND exhibitor_id = ?
            ");
            $stmt->execute([$studentId, $exhibitor['id']]);
            $alreadyRegistered = $stmt->fetchColumn();
            
            if ($alreadyRegistered > 0) {
                // Schüler ist bereits bei diesem Aussteller - nächsten suchen
                $stmt = $db->prepare("
                    SELECT e.id, e.name, r.capacity, FLOOR(r.capacity / 3) as slots_per_timeslot,
                           COUNT(DISTINCT reg.user_id) as current_count
                    FROM exhibitors e
                    LEFT JOIN rooms r ON e.room_id = r.id
                    LEFT JOIN registrations reg ON e.id = reg.exhibitor_id AND reg.timeslot_id = ?
                    WHERE e.active = 1 
                      AND e.room_id IS NOT NULL 
                      AND r.capacity IS NOT NULL
                      AND e.id NOT IN (
                          SELECT exhibitor_id FROM registrations WHERE user_id = ?
                      )
                    GROUP BY e.id
                    HAVING current_count < FLOOR(r.capacity / 3)
                    ORDER BY current_count ASC, RAND()
                    LIMIT 1
                ");
                $stmt->execute([$timeslotId, $studentId]);
                $exhibitor = $stmt->fetch();
                
                if (!$exhibitor) {
                    $errors[] = "Kein alternativer Aussteller für Slot $slotNumber (Schüler ID: $studentId)";
                    continue;
                }
            }
            
            // Registrierung erstellen
            $stmt = $db->prepare("
                INSERT INTO registrations (user_id, exhibitor_id, timeslot_id, registration_type)
                VALUES (?, ?, ?, 'automatic')
            ");
            
            if ($stmt->execute([$studentId, $exhibitor['id'], $timeslotId])) {
                $assignedCount++;
            } else {
                $errors[] = "Fehler bei Zuweisung: Schüler $studentId zu " . $exhibitor['name'] . " (Slot $slotNumber)";
            }
        }
    }
    
    // Statistik erstellen
    $stmt = $db->query("
        SELECT COUNT(*) as total
        FROM users 
        WHERE role = 'student'
    ");
    $totalStudents = $stmt->fetchColumn();
    
    $stmt = $db->query("
        SELECT COUNT(DISTINCT user_id) as complete
        FROM (
            SELECT r.user_id, COUNT(DISTINCT t.slot_number) as slot_count
            FROM registrations r
            JOIN timeslots t ON r.timeslot_id = t.id
            WHERE t.slot_number IN (1, 3, 5)
            GROUP BY r.user_id
            HAVING slot_count = 3
        ) as complete_registrations
    ");
    $completeStudents = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'message' => "$assignedCount Zuweisungen erfolgreich durchgeführt",
        'assigned' => $assignedCount,
        'errors' => $errors,
        'statistics' => [
            'total_students' => $totalStudents,
            'complete_registrations' => $completeStudents,
            'incomplete_registrations' => $totalStudents - $completeStudents
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Fehler bei der automatischen Zuweisung: ' . $e->getMessage()
    ]);
}
