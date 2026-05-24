<?php
/**
 * 🔧 Changes Made
 * - Added secure endpoint to update current weight (and BMI) + optional progress_metrics entry.
 *
 * API: Update Weight
 * POST /api/update_weight.php
 * Body: JSON { weight_kg, recorded_date? }
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
$input = read_json_input();

$user_id = get_user_id();
$weight_kg = sanitize_number($input['weight_kg'] ?? 0);
$recorded_date = $input['recorded_date'] ?? date('Y-m-d');

if ($weight_kg <= 0 || $weight_kg > 500) {
    json_response(['success' => false, 'error' => 'Please enter a valid weight.'], 400);
}
if (!validate_date($recorded_date)) {
    json_response(['success' => false, 'error' => 'Invalid date.'], 400);
}

try {
    $pdo->beginTransaction();

    // Get height for BMI calc
    $stmt = $pdo->prepare('SELECT height_cm FROM profiles WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch() ?: [];
    $height_cm = (float)($profile['height_cm'] ?? 0);
    $height_m = $height_cm > 0 ? $height_cm / 100 : 0;
    $bmi = ($height_m > 0) ? round($weight_kg / ($height_m ** 2), 2) : null;

    // Update profile
    $stmt = $pdo->prepare('UPDATE profiles SET current_weight_kg = ?, bmi = ? WHERE user_id = ?');
    $stmt->execute([$weight_kg, $bmi, $user_id]);

    // Also keep progress_metrics in sync (upsert for the date)
    $stmt = $pdo->prepare('
        INSERT INTO progress_metrics (user_id, weight_kg, recorded_date, recorded_at)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE weight_kg = VALUES(weight_kg), recorded_at = NOW()
    ');
    $stmt->execute([$user_id, $weight_kg, $recorded_date]);

    $pdo->commit();
    json_response([
        'success' => true,
        'recorded_date' => $recorded_date,
        'weight_kg' => (float)$weight_kg,
        'bmi' => $bmi,
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Update weight error: ' . $e->getMessage());
    json_response(['success' => false, 'error' => 'Server error'], 500);
}

