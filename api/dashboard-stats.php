<?php
require_once '../config.php';
require_once '../functions.php';

header('Content-Type: application/json');

requireLogin();
if (!isAdmin() && !hasPermission('berichte_sehen')) {
    http_response_code(403);
    echo json_encode(['error' => 'Keine Berechtigung']); exit;
}

try {
    $db = getDB();

    // [SCHOOL ISOLATION] use edition + school passed from the dashboard page
    $activeEditionId = intval($_GET['edition_id'] ?? 0);
    $statsSchoolId   = intval($_GET['school_id']  ?? 0) ?: null; // 0 → null

    // Validate: ensure this edition actually belongs to the declared school
    if ($activeEditionId > 0) {
        $stmtChk = $db->prepare(
            "SELECT 1 FROM messe_editions WHERE id = ? AND (? IS NULL OR school_id = ?) LIMIT 1"
        );
        $stmtChk->execute([$activeEditionId, $statsSchoolId, $statsSchoolId]);
        if (!$stmtChk->fetchColumn()) {
            echo json_encode(['timeline' => [], 'classes' => []]);
            exit;
        }
    } else {
        // No edition_id — fallback to session-scoped edition (safe for non-admin users)
        $activeEditionId = getActiveEditionId();
        $statsSchoolId   = isset($_SESSION['school_id']) ? (int)$_SESSION['school_id'] : null;
    }

    // Timeline: Einschreibungen pro Tag
    $stmt = $db->prepare("
        SELECT DATE(r.registered_at) AS day,
               SUM(r.registration_type = 'manual')    AS manual,
               SUM(r.registration_type = 'automatic') AS auto
        FROM registrations r
        JOIN users u ON r.user_id = u.id
        WHERE r.edition_id = ?
        AND (? IS NULL OR u.school_id = ?)   -- [SCHOOL ISOLATION]
        GROUP BY DATE(r.registered_at)
        ORDER BY day ASC
    ");
    $stmt->execute([$activeEditionId, $statsSchoolId, $statsSchoolId]);
    $timeline = $stmt->fetchAll();

    // Klassenbeteiligung
    $stmt = $db->prepare("
        SELECT u.class,
               COUNT(DISTINCT u.id)                                     AS total,
               COUNT(DISTINCT CASE WHEN r.id IS NOT NULL THEN u.id END) AS registered
        FROM users u
        LEFT JOIN registrations r ON r.user_id = u.id AND r.edition_id = ?
        WHERE u.role = 'student' AND u.class IS NOT NULL AND u.class != ''
        AND (? IS NULL OR u.school_id = ?)   -- [SCHOOL ISOLATION]
        GROUP BY u.class
        ORDER BY u.class ASC
    ");
    $stmt->execute([$activeEditionId, $statsSchoolId, $statsSchoolId]);
    $classRows = $stmt->fetchAll();
    $classes = array_map(function($row) {
        $row['rate'] = $row['total'] > 0 ? round($row['registered'] / $row['total'] * 100) : 0;
        return $row;
    }, $classRows);

    echo json_encode(['timeline' => $timeline, 'classes' => $classes]);

} catch (Exception $e) {
    logErrorToAudit($e, 'API-DashboardStats');
    http_response_code(500);
    echo json_encode(['timeline' => [], 'classes' => []]);
}
