<?php
/**
 * API: Log Hydration
 * POST /api/log_hydration.php
 * Body: JSON { amount_ml }  — positive to add, -250 to undo last entry
 * Security: session auth + X-CSRF-Token header
 * Response: JSON
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'Method not allowed'], 405);
}

verify_csrf_ajax();

$input    = read_json_input();
$user_id  = get_user_id();
$today    = date('Y-m-d');
$amount_ml = (int)sanitize_number($input['amount_ml'] ?? 250);

try {
    if ($amount_ml < 0) {
        // Undo: delete the most recent entry for today
        $stmt = $pdo->prepare('
            SELECT id FROM hydration_logs
            WHERE user_id = ? AND logged_date = ?
            ORDER BY logged_at DESC LIMIT 1
        ');
        $stmt->execute([$user_id, $today]);
        $row = $stmt->fetch();
        if ($row) {
            $pdo->prepare('DELETE FROM hydration_logs WHERE id = ?')->execute([$row['id']]);
        }
    } elseif ($amount_ml > 0 && $amount_ml <= 5000) {
        // Add
        $stmt = $pdo->prepare('
            INSERT INTO hydration_logs (user_id, amount_ml, logged_date, logged_at)
            VALUES (?, ?, ?, NOW())
        ');
        $stmt->execute([$user_id, $amount_ml, $today]);
    } else {
        json_response(['success' => false, 'error' => 'Invalid water amount.'], 400);
    }

    // Fetch updated total
    $stmt = $pdo->prepare('
        SELECT COALESCE(SUM(amount_ml), 0) AS total_ml
        FROM hydration_logs
        WHERE user_id = ? AND logged_date = ?
    ');
    $stmt->execute([$user_id, $today]);
    $total_ml = max(0, (int)$stmt->fetchColumn());

    if ($total_ml >= 2000) {
        unlock_achievement($pdo, $user_id, 'Hydration Hero', 'fa-solid fa-droplet', 'Log 2L of water in a day.', 'milestone');
    }

    json_response(['success' => true, 'total_ml' => $total_ml], 200);
} catch (Exception $e) {
    error_log('Hydration logging error: ' . $e->getMessage());
    json_response(['success' => false, 'error' => 'Server error'], 500);
}
