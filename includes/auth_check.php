<?php
/**
 * Authentication Guard
 * Redirects unauthenticated users to login page
 * Include at the top of protected pages
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Check if user is logged in
if (!is_logged_in()) {
    $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'];
    // Use root-relative path to avoid depth issues
    header('Location: /SHFS/auth/login.php');
    exit;
}

// Onboarding guard: block app pages and APIs until setup is complete (skip onboarding wizard itself).
$script_name = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$in_onboarding = strpos($script_name, '/onboarding/') !== false;
$in_api = strpos($script_name, '/api/') !== false;

if (!$in_onboarding && user_needs_onboarding()) {
    if ($in_api) {
        json_response([
            'success' => false,
            'error' => 'Complete onboarding first.',
            'redirect' => APP_URL . '/onboarding/step1.php',
        ], 403);
    }
    header('Location: ' . APP_URL . '/onboarding/step1.php');
    exit;
}

// Regenerate session ID periodically for security
if (!isset($_SESSION['session_regenerated']) || time() - $_SESSION['session_regenerated'] > 300) {
    session_regenerate_id(true);
    $_SESSION['session_regenerated'] = time();
}
?>