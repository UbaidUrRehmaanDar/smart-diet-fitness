<?php
/**
 * API: Update Theme Preference
 * POST /api/update_theme.php
 * Body: JSON { theme: "light"|"dark" }
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'Method not allowed'], 405);
}

verify_csrf_ajax();

$input = read_json_input();
$theme = $input['theme'] ?? 'light';
if (!in_array($theme, ['light', 'dark'], true)) {
    json_response(['success' => false, 'error' => 'Invalid theme.'], 400);
}

$user_id = get_user_id();

try {
    ensure_preferences_row($pdo, $user_id);
    $stmt = $pdo->prepare('UPDATE preferences SET theme = ? WHERE user_id = ?');
    $stmt->execute([$theme, $user_id]);
    json_response(['success' => true, 'theme' => $theme]);
} catch (Exception $e) {
    error_log('Update theme error: ' . $e->getMessage());
    json_response(['success' => false, 'error' => 'Server error'], 500);
}
