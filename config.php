<?php
// Datenbankkonfiguration
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'berufsmesse');

// Zeitzone
date_default_timezone_set('Europe/Berlin');

// Session Konfiguration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Auf 1 setzen bei HTTPS

// Upload-Verzeichnis
define('UPLOAD_DIR', __DIR__ . '/uploads/');
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0775, true);
}

// Maximale Upload-Größe (in Bytes)
define('MAX_FILE_SIZE', 10485760); // 10 MB

// Erlaubte Dateitypen
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif']);

// Base URL – kann per Umgebungsvariable überschrieben werden
define('BASE_URL', getenv('BASE_URL') ?: '/');

// Error Reporting – in Produktion deaktiviert (PHP-ini übernimmt das im Docker-Container)
$appEnv = getenv('APP_ENV') ?: 'production';
if ($appEnv === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
?>
