<?php
/**
 * Auto-Zuweisung (Fix Issue #6)
 * 
 * PHASE 1: Schüler mit Anmeldungen (timeslot_id = NULL) 
 *          → Slots bei ihren gewählten Ausstellern zuweisen
 * PHASE 2: Schüler ohne vollständige Slots 
 *          → auf beliebige Aussteller verteilen
 */

session_start();
require_once '../config.php';
require_once '../functions.php';

requireAdmin();

set_time_limit(300); // 5 Minuten Timeout

$db = getDB();

try {
    // Verwaltete Slots (nur 1, 3, 5)
    $managedSlots = [1, 3, 5];
    
    $assignedCount = 0;
    $errors = [];
    
    // ============================================================
    // PHASE 1: Schüler mit Anmeldungen (timeslot_id = NULL) 
    //          → Slots bei ihren gewählten Ausstellern zuweisen
    // ============================================================
    
    $stmt = $db->query("
        SELECT r.id as registration_id, r.user_id, r.exhibitor_id, e.name as exhibitor_name, e.room_id, COALESCE(r.priority, 2) as priority
        FROM registrations r
        JOIN exhibitors e ON r.exhibitor_id = e.id
        WHERE r.timeslot_id IS NULL
        AND e.active = 1
        AND e.room_id IS NOT NULL
        ORDER BY COALESCE(r.priority, 2) ASC, r.registered_at ASC
    ");
    $pendingRegistrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($pendingRegistrations as $reg) {
        $studentId = $reg['user_id'];
        $exhibitorId = $reg['exhibitor_id'];
        $roomId = $reg['room_id'];
        
        // Welche Slots hat der Schüler bereits?
        $stmt = $db->prepare("
            SELECT t.slot_number 
            FROM registrations r
            JOIN timeslots t ON r.timeslot_id = t.id
            WHERE r.user_id = ? AND t.slot_number IN (1, 3, 5)
        ");
        $stmt->execute([$studentId]);
        $usedSlots = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $availableSlots = array_diff($managedSlots, $usedSlots);
        
        if (empty($availableSlots)) {
            $stmt = $db->prepare("DELETE FROM registrations WHERE id = ?");
            $stmt->execute([$reg['registration_id']]);
            $errors[] = "Schüler $studentId hat bereits 3 Slots - überschüssige Anmeldung entfernt";
            continue;
        }
        
        // Besten verfügbaren Slot finden (wenigste Belegung)
        $bestSlot = null;
        $lowestCount = PHP_INT_MAX;
        
        foreach ($availableSlots as $slotNumber) {
            $stmt = $db->prepare("SELECT id FROM timeslots WHERE slot_number = ?");
            $stmt->execute([$slotNumber]);
            $timeslotId = $stmt->fetchColumn();
            
            if (!$timeslotId) continue;
            
            $stmt = $db->prepare("SELECT COUNT(*) FROM registrations WHERE exhibitor_id = ? AND timeslot_id = ?");
            $stmt->execute([$exhibitorId, $timeslotId]);
            $currentCount = $stmt->fetchColumn();
            
            $slotCapacity = getRoomSlotCapacity($roomId, $timeslotId);
            
            if ($slotCapacity > 0 && $currentCount < $slotCapacity && $currentCount < $lowestCount) {
                $bestSlot = ['slot_number' => $slotNumber, 'timeslot_id' => $timeslotId];
                $lowestCount = $currentCount;
            }
        }
        
        if ($bestSlot) {
            $stmt = $db->prepare("UPDATE registrations SET timeslot_id = ?, registration_type = 'automatic' WHERE id = ?");
            if ($stmt->execute([$bestSlot['timeslot_id'], $reg['registration_id']])) {
                $assignedCount++;
            }
        } else {
            $errors[] = "Kein freier Slot für Schüler $studentId bei {$reg['exhibitor_name']}";
        }
    }
    
    // ============================================================
    // PHASE 2: Schüler ohne vollständige Slots verteilen
    // ============================================================
    
    $stmt = $db->query("
        SELECT u.id,
               COALESCE(SUM(CASE WHEN r.timeslot_id IS NOT NULL AND t.slot_number IN (1,3,5) THEN 1 ELSE 0 END), 0) as assigned_count
        FROM users u
        LEFT JOIN registrations r ON u.id = r.user_id
        LEFT JOIN timeslots t ON r.timeslot_id = t.id
        WHERE u.role = 'student'
        GROUP BY u.id
        HAVING assigned_count < " . MANAGED_SLOTS_COUNT . "
        ORDER BY assigned_count DESC
    ");
    $studentsNeedingSlots = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($studentsNeedingSlots as $student) {
        $studentId = $student['id'];
        
        $stmt = $db->prepare("
            SELECT t.slot_number 
            FROM registrations r
            JOIN timeslots t ON r.timeslot_id = t.id
            WHERE r.user_id = ? AND t.slot_number IN (1, 3, 5)
        ");
        $stmt->execute([$studentId]);
        $assignedSlots = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $stmt = $db->prepare("SELECT exhibitor_id FROM registrations WHERE user_id = ?");
        $stmt->execute([$studentId]);
        $existingExhibitors = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $missingSlots = array_diff($managedSlots, $assignedSlots);
        
        foreach ($missingSlots as $slotNumber) {
            $stmt = $db->prepare("SELECT id FROM timeslots WHERE slot_number = ?");
            $stmt->execute([$slotNumber]);
            $timeslotId = $stmt->fetchColumn();
            
            if (!$timeslotId) continue;
            
            $stmt = $db->prepare("
                SELECT e.id, e.name, e.room_id, COUNT(DISTINCT reg.user_id) as current_count
                FROM exhibitors e
                LEFT JOIN registrations reg ON e.id = reg.exhibitor_id AND reg.timeslot_id = ?
                WHERE e.active = 1 AND e.room_id IS NOT NULL
                GROUP BY e.id, e.name, e.room_id
                ORDER BY current_count ASC, RAND()
            ");
            $stmt->execute([$timeslotId]);
            $exhibitors = $stmt->fetchAll();
            
            $selectedExhibitor = null;
            
            foreach ($exhibitors as $ex) {
                if (in_array($ex['id'], $existingExhibitors)) continue;
                
                $slotCapacity = getRoomSlotCapacity($ex['room_id'], $timeslotId);
                if ($slotCapacity > 0 && $ex['current_count'] < $slotCapacity) {
                    $selectedExhibitor = $ex;
                    break;
                }
            }
            
            if ($selectedExhibitor) {
                $stmt = $db->prepare("INSERT INTO registrations (user_id, exhibitor_id, timeslot_id, registration_type) VALUES (?, ?, ?, 'automatic')");
                if ($stmt->execute([$studentId, $selectedExhibitor['id'], $timeslotId])) {
                    $assignedCount++;
                    $existingExhibitors[] = $selectedExhibitor['id'];
                }
            } else {
                $errors[] = "Kein verfügbarer Aussteller für Slot $slotNumber (Schüler $studentId)";
            }
        }
    }
    
    // Erfolgsmeldung
    $_SESSION['auto_assign_success'] = true;
    $_SESSION['auto_assign_count'] = $assignedCount;
    $_SESSION['auto_assign_students'] = count($studentsNeedingSlots) + count($pendingRegistrations);
    $_SESSION['auto_assign_errors'] = $errors;
    
    // Auto-Close: Einschreibung automatisch schliessen nach Zuteilung (Issue #12)
    $autoClose = getSetting('auto_close_registration', '1');
    if ($autoClose === '1') {
        $regStatus = getRegistrationStatus();
        if ($regStatus === 'open') {
            $stmt = $db->prepare("UPDATE settings SET value = ? WHERE `key` = 'registration_end'");
            $stmt->execute([date('Y-m-d H:i:s')]);
            $_SESSION['auto_assign_closed'] = true;
        }
    }
    
} catch (Exception $e) {
    $_SESSION['auto_assign_error'] = $e->getMessage();
}

// Zurück zum Dashboard
header('Location: ../index.php?page=admin-dashboard&auto_assign=done');
exit();
?>
