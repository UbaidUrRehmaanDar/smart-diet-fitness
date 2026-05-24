<?php
/**
 * Core Configuration File
 * Handles: Database connection (PDO), session setup, timezone, error handling
 * Security: Prepared statements, session regeneration, CSRF token initialization
 */

// =====================================================
// ERROR HANDLING & REPORTING
// =====================================================
define('APP_ENV', getenv('APP_ENV') ?: 'development');
define('APP_DEBUG', APP_ENV !== 'production');

error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? 1 : 0); // Don't expose errors to users in production
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Create logs directory if it doesn't exist
if (!is_dir(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0755, true);
}

// =====================================================
// TIMEZONE SETUP
// =====================================================
date_default_timezone_set('UTC');

// =====================================================
// DATABASE CONFIGURATION
// =====================================================
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_NAME', 'smart_diet_fyp');
define('DB_USER', 'root');
define('DB_PASS', '1122'); // Laragon default is empty

// =====================================================
// DATABASE CONNECTION (PDO)
// =====================================================
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ]
    );
} catch (PDOException $e) {
    error_log('Database Connection Error: ' . $e->getMessage());
    http_response_code(500);
    die('Database connection failed. Please try again later.');
}

require_once __DIR__ . '/schema_patches.php';

// =====================================================
// SESSION CONFIGURATION
// =====================================================
// Only start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_httponly', 1);
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', 1);
    }
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', 3600);
    ini_set('session.cookie_lifetime', 0);
    
    session_start();
}

// =====================================================
// CSRF TOKEN INITIALIZATION
// =====================================================
// Generate CSRF token if it doesn't exist
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// =====================================================
// SECURITY HEADERS
// =====================================================
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; font-src https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: https://images.unsplash.com https://fonts.gstatic.com https://cdnjs.cloudflare.com;");

// =====================================================
// CONSTANTS
// =====================================================
define('APP_NAME', 'Smart Diet & Fitness');
define('APP_VERSION', '1.0.0');
define('APP_URL', getenv('APP_URL') ?: 'http://localhost/SHFS');
/** Absolute filesystem path for avatar uploads (JPEG/PNG/WebP). */
define('AVATAR_UPLOAD_DIR', dirname(__DIR__) . '/public/uploads/avatars');
/** Web path segment after APP_URL for uploaded avatars. */
define('AVATAR_WEB_PATH', '/public/uploads/avatars');
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds
define('PASSWORD_HASH_ALGO', PASSWORD_BCRYPT);
define('PASSWORD_HASH_COST', 10);

// =====================================================
// SMTP CONFIGURATION
// =====================================================
define('SMTP_HOST', getenv('SMTP_HOST') ?: '');
define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: 587));
define('SMTP_USER', getenv('SMTP_USER') ?: '');
define('SMTP_PASS', getenv('SMTP_PASS') ?: '');
define('SMTP_SECURE', getenv('SMTP_SECURE') ?: 'tls'); // tls|ssl|none
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: 'no-reply@localhost');
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: APP_NAME);
define('SMTP_TIMEOUT', (int)(getenv('SMTP_TIMEOUT') ?: 10));

// =====================================================
// ACTIVITY TIMEOUT CHECK
// =====================================================
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['last_activity'])) {
        $inactive = time() - $_SESSION['last_activity'];
        if ($inactive > SESSION_TIMEOUT) {
            session_unset();
            session_destroy();
            session_start();
            $_SESSION['session_expired'] = true;
            header('Location: ' . APP_URL . '/auth/login.php?expired=1');
            exit;
        }
    }
    $_SESSION['last_activity'] = time();
}

?>