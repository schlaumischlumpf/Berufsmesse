<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

header('Content-Type: application/json');

requireAdmin();

$exhibitorId = intval($_GET['exhibitor_id'] ?? 0);

if (!$exhibitorId) {
    echo json_encode(['documents' => []]);
    exit();
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM exhibitor_documents WHERE exhibitor_id = ? ORDER BY uploaded_at DESC");
$stmt->execute([$exhibitorId]);
$documents = $stmt->fetchAll();

echo json_encode(['documents' => $documents]);
?>
