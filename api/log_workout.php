<?php
/**
 * API: Log Workout
 * POST /api/log_workout.php
 * Logs exercise/workout and returns updated daily totals
 * Security: CSRF token validation, input sanitization
 * Response: JSON
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

$response = ['success' => false];

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $response['error'] = 'Method not allowed';
    echo json_encode($response);
    exit;
}

// Verify CSRF
verify_csrf_ajax();

try {
    // Get JSON input (same pattern as log_meal.php)
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        $response['error'] = 'Invalid JSON input';
        echo json_encode($response);
        exit;
    }

    // Read inputs — same pattern as log_meal.php
    $exercise_name = sanitize_plain_text($input['exercise_name'] ?? '', 120);
    $exercise_type = $input['exercise_type'] ?? 'cardio';
    $duration_mins = (int) sanitize_number($input['duration_mins'] ?? 0);
    $intensity     = $input['intensity'] ?? 'moderate';
    $kcal_burned   = (int) sanitize_number($input['kcal_burned'] ?? 0);
    $notes         = sanitize_plain_text($input['notes'] ?? '', 255);

    // Validation
    if (empty($exercise_name)) {
        http_response_code(400);
        $response['error'] = 'Exercise name is required';
        echo json_encode($response);
        exit;
    }

    if (!in_array($exercise_type, ['cardio', 'strength', 'flexibility', 'sports'])) {
        $exercise_type = 'cardio'; // safe fallback
    }

    if ($duration_mins <= 0 || $duration_mins > 480) {
        http_response_code(400);
        $response['error'] = 'Duration must be between 1 and 480 minutes';
        echo json_encode($response);
        exit;
    }

    $valid_intensities = ['light', 'moderate', 'vigorous', 'beginner', 'intermediate', 'advanced'];
    if (!in_array($intensity, $valid_intensities)) {
        $intensity = 'moderate'; // safe fallback instead of rejecting
    }

    $user_id = get_user_id();
    $today   = date('Y-m-d');

    // Auto-calculate kcal if not provided
    if ($kcal_burned <= 0) {
        $multiplier = match($intensity) {
            'light'        => 4,
            'beginner'     => 5,
            'moderate'     => 8,
            'intermediate' => 10,
            'vigorous'     => 12,
            'advanced'     => 15,
            default        => 8,
        };
        $kcal_burned = $duration_mins * $multiplier;
    }

    // Insert workout log
    $stmt = $pdo->prepare('
        INSERT INTO workout_logs
            (user_id, exercise_name, exercise_type, duration_mins, intensity, kcal_burned, logged_date, notes, logged_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ');
    $stmt->execute([
        $user_id,
        $exercise_name,
        $exercise_type,
        $duration_mins,
        $intensity,
        $kcal_burned,
        $today,
        $notes,
    ]);

    // Get daily totals
    $stmt = $pdo->prepare('
        SELECT
            COUNT(*)                        AS workout_count,
            COALESCE(SUM(duration_mins), 0) AS total_duration,
            COALESCE(SUM(kcal_burned),   0) AS total_kcal_burned
        FROM workout_logs
        WHERE user_id = ? AND logged_date = ?
    ');
    $stmt->execute([$user_id, $today]);
    $totals = $stmt->fetch();

    // Achievements
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM workout_logs WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $total_count = (int) $stmt->fetchColumn();

    if ($total_count >= 1)   unlock_achievement($pdo, $user_id, 'First Workout',   'fa-solid fa-shoe-prints', 'Log your first workout.',   'milestone');
    if ($total_count >= 10)  unlock_achievement($pdo, $user_id, 'Workout Warrior',  'fa-solid fa-dumbbell',    'Complete 10 workouts.',     'consistency');
    if ($total_count >= 50)  unlock_achievement($pdo, $user_id, 'Workout Pro',      'fa-solid fa-medal',       'Complete 50 workouts.',     'milestone');
    if ($total_count >= 100) unlock_achievement($pdo, $user_id, 'Iron Will',        'fa-solid fa-trophy',      'Complete 100 workouts.',    'milestone');

    $response['success']       = true;
    $response['csrf_token']    = $_SESSION['csrf_token'] ?? '';
    $response['logged_workout'] = [
        'exercise'    => $exercise_name,
        'duration'    => $duration_mins,
        'kcal_burned' => $kcal_burned,
        'intensity'   => $intensity,
        'type'        => $exercise_type,
    ];
    $response['daily_totals'] = [
        'workout_count'      => (int) $totals['workout_count'],
        'total_duration_mins'=> (int) $totals['total_duration'],
        'total_kcal_burned'  => (int) $totals['total_kcal_burned'],
    ];

    echo json_encode($response);

} catch (Exception $e) {
    error_log('Workout logging error: ' . $e->getMessage());
    http_response_code(500);
    $response['error'] = 'Server error: ' . $e->getMessage();
    echo json_encode($response);
}
