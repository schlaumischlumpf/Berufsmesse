<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Keine Berechtigung']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (empty($data['room_number'])) {
    echo json_encode(['success' => false, 'message' => 'Raumnummer ist erforderlich']);
    exit;
}

if (empty($data['capacity']) || $data['capacity'] < 1) {
    echo json_encode(['success' => false, 'message' => 'Kapazität muss mindestens 1 sein']);
    exit;
}

try {
    // Check if room number already exists
    $stmt = $db->prepare("SELECT id FROM rooms WHERE room_number = ?");
    $stmt->execute([$data['room_number']]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Ein Raum mit dieser Nummer existiert bereits']);
        exit;
    }
    
    // Insert new room
    $stmt = $db->prepare("
        INSERT INTO rooms (room_number, room_name, building, floor, capacity) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $data['room_number'],
        $data['room_name'] ?: null,
        $data['building'] ?: null,
        $data['floor'] ?: null,
        $data['capacity']
    ]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Raum wurde erfolgreich hinzugefügt',
        'room_id' => $db->lastInsertId()
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Datenbankfehler: ' . $e->getMessage()]);
}
