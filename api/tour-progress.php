<?php
/**
 * Tour-Fortschritt API (Phase 5)
 * GET  ?tour=<key>            → aktuellen Fortschritt laden
 * POST {tour, step, completed} → Fortschritt speichern (CSRF-geschützt)
 */
require_once '../config.php';
require_once '../functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Nicht eingeloggt']);
    exit;
}

$db = getDB();
$userId = (int)$_SESSION['user_id'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $tour = trim($_GET['tour'] ?? '');
        if ($tour === '') {
            // Alle Touren des Users
            $stmt = $db->prepare("SELECT tour_key, current_step, completed_at FROM user_tour_progress WHERE user_id = ?");
            $stmt->execute([$userId]);
            $all = [];
            foreach ($stmt->fetchAll() as $row) {
                $all[$row['tour_key']] = [
                    'current_step' => (int)$row['current_step'],
                    'completed' => $row['completed_at'] !== null,
                ];
            }
            echo json_encode(['success' => true, 'tours' => $all]);
            exit;
        }
        $stmt = $db->prepare("SELECT current_step, completed_at FROM user_tour_progress WHERE user_id = ? AND tour_key = ?");
        $stmt->execute([$userId, $tour]);
        $row = $stmt->fetch();
        echo json_encode([
            'success' => true,
            'current_step' => $row ? (int)$row['current_step'] : 0,
            'completed' => $row ? ($row['completed_at'] !== null) : false,
        ]);
        exit;
    }

    // POST
    requireCsrf();
    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $tour = trim($data['tour'] ?? '');
    $step = (int)($data['step'] ?? 0);
    $completed = !empty($data['completed']);
    if ($tour === '') {
        echo json_encode(['success' => false, 'message' => 'Kein Tour-Schlüssel']);
        exit;
    }
    $stmt = $db->prepare("INSERT INTO user_tour_progress (user_id, tour_key, current_step, completed_at)
        VALUES (?, ?, ?, " . ($completed ? "NOW()" : "NULL") . ")
        ON DUPLICATE KEY UPDATE current_step = VALUES(current_step),
            completed_at = " . ($completed ? "NOW()" : "completed_at"));
    $stmt->execute([$userId, $tour, $step]);
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    logErrorToAudit($e, 'API-TourProgress');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ein interner Fehler ist aufgetreten.']);
}
