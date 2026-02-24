<?php
require_once '../config.php';
require_once '../functions.php';

requireLogin();

if (!isAdmin() && !hasPermission('aussteller_dokumente_verwalten')) {
    http_response_code(403);
    die('Keine Berechtigung');
}

$documentId = intval($_GET['id'] ?? 0);

if (!$documentId) {
    http_response_code(400);
    die('Ungültige Dokument-ID');
}

$db = getDB();
$stmt = $db->prepare("SELECT filename, original_name, file_type FROM exhibitor_documents WHERE id = ?");
$stmt->execute([$documentId]);
$doc = $stmt->fetch();

if (!$doc) {
    http_response_code(404);
    die('Dokument nicht gefunden');
}

$filepath = UPLOAD_DIR . $doc['filename'];

if (!file_exists($filepath)) {
    http_response_code(404);
    die('Datei nicht gefunden');
}

$mimeTypes = [
    'pdf'  => 'application/pdf',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'ppt'  => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
];

$extension = strtolower($doc['file_type']);
$mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';

$safeFilename = str_replace(['"', "\r", "\n", "\0"], '', $doc['original_name']);
header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: private, no-cache');

readfile($filepath);
exit();
?>
