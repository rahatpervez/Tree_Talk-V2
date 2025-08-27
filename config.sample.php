<?php
/**
 * Rename this file to config.php and adjust DB credentials if needed.
 * Do NOT commit config.php to Git (keep it in .gitignore).
 */

if (session_status() === PHP_SESSION_NONE) session_start();

// ----- Dev mode (show errors locally) -----
define('DEV_MODE', true);
if (DEV_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// ----- Uploads -----
define('UPLOAD_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR);
if (!is_dir(UPLOAD_DIR)) { @mkdir(UPLOAD_DIR, 0755, true); }

// ----- Database -----
$host = "localhost";
$user = "root";
$pass = "";
$db   = "treetalk_db_v2";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// ----- Helpers -----
function base_url(string $path = ''): string {
    $root = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
    $base = ($root === '.' ? '' : $root);
    return $base . '/' . ltrim($path, '/');
}

function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;
}

function redirect(string $to): void {
    header('Location: ' . $to);
    exit;
}
