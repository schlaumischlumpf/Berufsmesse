<?php
/**
 * API: Schul-CRUD (nur für globale Admins)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Keine Berechtigung']);
    exit;
}

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// GET: Schulen auflisten
if ($method === 'GET') {
    try {
        $stmt = $db->query("SELECT id, name, slug, logo, address, contact_email, contact_phone, is_active, created_at FROM schools ORDER BY name");
        echo json_encode(['success' => true, 'schools' => $stmt->fetchAll()]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// POST: Schule erstellen
if ($method === 'POST') {
    requireCsrf();
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $name = sanitize($input['name'] ?? '');
    
    if (empty($name)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Name ist Pflichtfeld']);
        exit;
    }
    
    try {
        $slug = generateSchoolSlug($name);
        $stmt = $db->prepare("INSERT INTO schools (name, slug, address, contact_email, contact_phone, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $name,
            $slug,
            sanitize($input['address'] ?? '') ?: null,
            sanitize($input['contact_email'] ?? '') ?: null,
            sanitize($input['contact_phone'] ?? '') ?: null,
            $_SESSION['user_id']
        ]);
        $schoolId = $db->lastInsertId();
        logAuditAction('api_schule_erstellt', "Schule '$name' (ID: $schoolId) erstellt");
        echo json_encode(['success' => true, 'school_id' => (int)$schoolId, 'slug' => $slug]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
