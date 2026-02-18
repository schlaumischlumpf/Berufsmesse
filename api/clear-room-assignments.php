<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

header('Content-Type: application/json');

// Nur für Admins oder Benutzer mit Raum-Bearbeitung
if (!isAdmin() && !hasPermission('raeume_bearbeiten')) {
    echo json_encode(['success' => false, 'message' => 'Keine Berechtigung']);
    exit;
}

try {
    $db = getDB();
    
    // Alle Raum-Zuordnungen entfernen
    $stmt = $db->prepare("UPDATE exhibitors SET room_id = NULL");
    $stmt->execute();
    
    $affectedRows = $stmt->rowCount();
    logAuditAction('raumzuordnungen_geleert', "Alle $affectedRows Raum-Zuordnungen der Aussteller entfernt");
    
    echo json_encode([
        'success' => true,
        'message' => 'Alle Raum-Zuordnungen wurden gelöscht',
        'affected_rows' => $affectedRows
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Fehler beim Löschen: ' . $e->getMessage()
    ]);
}
