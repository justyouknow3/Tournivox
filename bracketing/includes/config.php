<?php
/**
 * TOURNIVOX Bracketing Manager - Configuration
 */

define('APP_NAME', 'TOURNIVOX Bracketing Manager');
define('APP_VERSION', '2.0.0');

// Detect /Tournivox/bracketing regardless of the XAMPP htdocs folder name.
$script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/Tournivox/bracketing/index.php');
$marker = '/bracketing/';
$pos = strpos($script, $marker);
$base = $pos !== false ? substr($script, 0, $pos + strlen('/bracketing')) : rtrim(dirname($script), '/');
define('APP_URL', rtrim($base, '/'));
define('SITE_URL', rtrim(dirname(APP_URL), '/'));
define('APP_ROOT', dirname(__DIR__));
define('SITE_ROOT', dirname(APP_ROOT));

// Database - same database used by the rest of TOURNIVOX.
define('DB_HOST', 'localhost');
define('DB_NAME', 'tournivox');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Security.
define('CSRF_TOKEN_NAME', 'tournivox_bracketing_csrf_token');
define('SESSION_LIFETIME', 86400 * 7);
define('REMEMBER_LIFETIME', 86400 * 30);

// Uploads.
define('UPLOAD_PATH', APP_ROOT . '/uploads');
define('UPLOAD_URL', APP_URL . '/uploads');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024);
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

// Pagination.
define('ITEMS_PER_PAGE', 12);

date_default_timezone_set('Asia/Manila');

// Development settings. Disable display_errors in production.
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Use the same PHP session as TOURNIVOX /auth/login.php so role redirects work.
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']),
        'path' => '/',
    ]);
    session_start();
}
