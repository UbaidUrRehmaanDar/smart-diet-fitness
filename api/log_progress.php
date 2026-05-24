<?php
/**
 * API: Log Progress Metrics
 * POST /api/log_progress.php
 * Logs body measurements and weight updates
 * Security: CSRF token validation, input sanitization
 * Response: JSON
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $response['error'] = 'Method not allowed';
    echo json_encode($response);
    exit;
}

verify_csrf_ajax();

$input = json_decode(file_get_contents('php://input'), true) ?: [];

$user_id = get_user_id();
$weight_kg = sanitize_number($input['weight_kg'] ?? 0);
$waist_cm = sanitize_number($input['waist_cm'] ?? 0);
$hips_cm = sanitize_number($input['hips_cm'] ?? 0);
$chest_cm = sanitize_number($input['chest_cm'] ?? 0);
$body_fat = sanitize_number($input['body_fat_percent'] ?? 0);
$muscle_mass = sanitize_number($input['muscle_mass_kg'] ?? 0);
$recorded_date = $input['recorded_date'] ?? date('Y-m-d');

if ($weight_kg <= 0 || $weight_kg > 500) {
    http_response_code(400);
    $response['error'] = 'Please enter a valid weight.';
    echo json_encode($response);
    exit;
}

if (!validate_date($recorded_date)) {
    http_response_code(400);
    $response['error'] = 'Invalid date format.';
    echo json_encode($response);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?');
    $stmt->execute([DB_NAME, 'progress_metrics']);
    if ((int)$stmt->fetchColumn() === 0) {
        http_response_code(500);
        $response['error'] = 'Progress metrics table not found.';
        echo json_encode($response);
        exit;
    }

    $stmt = $pdo->prepare('
        INSERT INTO progress_metrics
            (user_id, weight_kg, waist_cm, chest_cm, hips_cm, body_fat_percent, muscle_mass_kg, recorded_date, recorded_at)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            weight_kg = VALUES(weight_kg),
            waist_cm = VALUES(waist_cm),
            chest_cm = VALUES(chest_cm),
            hips_cm = VALUES(hips_cm),
            body_fat_percent = VALUES(body_fat_percent),
            muscle_mass_kg = VALUES(muscle_mass_kg),
            recorded_at = NOW()
    ');

    $stmt->execute([
        $user_id,
        $weight_kg,
        $waist_cm,
        $chest_cm,
        $hips_cm,
        $body_fat,
        $muscle_mass,
        $recorded_date
    ]);

    $stmt = $pdo->prepare('SELECT height_cm FROM profiles WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch() ?: [];
    $height_cm = (float)($profile['height_cm'] ?? 0);
    $height_m = $height_cm > 0 ? $height_cm / 100 : 0;
    $bmi = ($height_m > 0) ? round($weight_kg / ($height_m ** 2), 2) : null;

    $stmt = $pdo->prepare('UPDATE profiles SET current_weight_kg = ?, bmi = ? WHERE user_id = ?');
    $stmt->execute([$weight_kg, $bmi, $user_id]);

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM progress_metrics WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $progress_count = (int)$stmt->fetchColumn();

    if ($progress_count >= 1) {
        unlock_achievement($pdo, $user_id, 'First Check-in', 'fa-solid fa-chart-line', 'Log your first progress check-in.', 'milestone');
    }
    if ($progress_count >= 10) {
        unlock_achievement($pdo, $user_id, 'Progress Tracker', 'fa-solid fa-check-double', 'Log 10 progress check-ins.', 'consistency');
    }

    $response['success'] = true;
    $response['recorded_date'] = $recorded_date;
    $response['weight_kg'] = $weight_kg;
    echo json_encode($response);
} catch (Exception $e) {
    error_log('Progress logging error: ' . $e->getMessage());
    http_response_code(500);
    $response['error'] = 'Server error';
    echo json_encode($response);
}

?>