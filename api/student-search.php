<?php
/**
 * Schüler-Suche für den Lehrer-Check-in (QR-Check-in 2.0)
 * Liefert Schüler der aktuellen Schule/Edition zur manuellen Anwesenheitserfassung.
 */
require_once '../config.php';
require_once '../functions.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !(isTeacher() || isAdminOrSchoolAdmin())) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Keine Berechtigung']);
    exit;
}

try {
    $db = getDB();
    $activeEditionId = getActiveEditionId();
    $schoolId = getCurrentSchoolId();
    $q = trim($_GET['q'] ?? '');

    if (mb_strlen($q) < 2) {
        echo json_encode(['success' => true, 'users' => []]);
        exit;
    }

    $sql = "SELECT id, firstname, lastname, class FROM users
            WHERE role = 'student' AND edition_id = ?";
    $params = [$activeEditionId];
    if ($schoolId !== null) {
        $sql .= " AND school_id = ?";
        $params[] = $schoolId;
    }
    $sql .= " AND (firstname LIKE ? OR lastname LIKE ? OR CONCAT(firstname,' ',lastname) LIKE ? OR class LIKE ?)
              ORDER BY lastname, firstname LIMIT 25";
    $term = '%' . $q . '%';
    array_push($params, $term, $term, $term, $term);

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $users = array_map(function ($u) {
        return [
            'id' => (int)$u['id'],
            'name' => trim($u['firstname'] . ' ' . $u['lastname']),
            'class' => $u['class'],
        ];
    }, $stmt->fetchAll());

    echo json_encode(['success' => true, 'users' => $users]);

} catch (Exception $e) {
    logErrorToAudit($e, 'API-StudentSearch');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ein interner Fehler ist aufgetreten.']);
}
