<?php
/**
 * User Logout Handler
 * Destroys session and redirects to login page
 */
require_once __DIR__ . '/../includes/config.php';

// 1. Clear all session data
$_SESSION = array();

// 2. Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// 3. Destroy the session
session_destroy();

// 4. Redirect to login page in the SAME folder (auth/)
header('Location: login.php?logged_out=1');
exit;
?>