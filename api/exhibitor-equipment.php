<?php
/**
 * API: Ausstattungsanfragen (für Aussteller)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Nicht eingeloggt']);
    exit;
}

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$userId = $_SESSION['user_id'];

// POST: Anfrage erstellen
if ($method === 'POST') {
    requireCsrf();
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $exhibitorId     = (int)($input['exhibitor_id'] ?? 0);
    $equipmentOptId  = (int)($input['equipment_option_id'] ?? 0);
    $customText      = sanitize($input['custom_text'] ?? '');
    $quantity        = max(1, (int)($input['quantity'] ?? 1));

    // Zugriffsprüfung
    if (!isAdmin()) {
        $ids = getExhibitorIdsForUser($userId);
        if (!in_array($exhibitorId, $ids)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Kein Zugriff']);
            exit;
        }
    }

    if ($exhibitorId <= 0 || ($equipmentOptId <= 0 && empty($customText))) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Fehlende Parameter']);
        exit;
    }

    try {
        // Edition des Ausstellers ermitteln
        $stmt = $db->prepare("SELECT edition_id FROM exhibitors WHERE id = ?");
        $stmt->execute([$exhibitorId]);
        $ex = $stmt->fetch();
        if (!$ex) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Aussteller nicht gefunden']);
            exit;
        }

        $stmt = $db->prepare("INSERT INTO exhibitor_equipment_requests (exhibitor_id, edition_id, equipment_option_id, custom_text, quantity, requested_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$exhibitorId, $ex['edition_id'], $equipmentOptId ?: null, $customText ?: null, $quantity, $userId]);
        
        echo json_encode(['success' => true, 'request_id' => (int)$db->lastInsertId()]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// GET: Anfragen für einen Aussteller laden
if ($method === 'GET') {
    $exhibitorId = (int)($_GET['exhibitor_id'] ?? 0);
    
    if (!isAdmin()) {
        $ids = getExhibitorIdsForUser($userId);
        if (!in_array($exhibitorId, $ids)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Kein Zugriff']);
            exit;
        }
    }

    try {
        $stmt = $db->prepare("
            SELECT eer.*, eo.name as option_name
            FROM exhibitor_equipment_requests eer
            LEFT JOIN equipment_options eo ON eer.equipment_option_id = eo.id
            WHERE eer.exhibitor_id = ?
            ORDER BY eer.created_at DESC
        ");
        $stmt->execute([$exhibitorId]);
        echo json_encode(['success' => true, 'requests' => $stmt->fetchAll()]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
