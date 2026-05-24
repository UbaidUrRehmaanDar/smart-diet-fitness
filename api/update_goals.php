<?php
/**
 * 🔧 Changes Made
 * - Added secure endpoint to update profile goals and regenerate today's plan.
 *
 * API: Update Goals
 * POST /api/update_goals.php
 * Body: JSON { target_weight_kg, activity_level, fitness_goal }
 * Security: session auth + X-CSRF-Token header
 * Response: JSON
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../engine/bmr_tdee.php';
require_once __DIR__ . '/../engine/generate_plan.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'Method not allowed'], 405);
}

verify_csrf_ajax();
$input = read_json_input();

$user_id = get_user_id();

$target_weight_kg = sanitize_number($input['target_weight_kg'] ?? 0);
$activity_level = $input['activity_level'] ?? '';
$fitness_goal = $input['fitness_goal'] ?? '';

$valid_activities = ['sedentary', 'lightly_active', 'moderately_active', 'very_active', 'extremely_active'];
$valid_goals = ['weight_loss', 'muscle_gain', 'maintenance'];

if ($target_weight_kg <= 0 || $target_weight_kg > 500) {
    json_response(['success' => false, 'error' => 'Invalid target weight.'], 400);
}
if (!in_array($activity_level, $valid_activities, true)) {
    json_response(['success' => false, 'error' => 'Invalid activity level.'], 400);
}
if (!in_array($fitness_goal, $valid_goals, true)) {
    json_response(['success' => false, 'error' => 'Invalid fitness goal.'], 400);
}

try {
    $pdo->beginTransaction();

    // Update profile goals
    $stmt = $pdo->prepare('
        UPDATE profiles
        SET target_weight_kg = ?, activity_level = ?, fitness_goal = ?
        WHERE user_id = ?
    ');
    $stmt->execute([$target_weight_kg, $activity_level, $fitness_goal, $user_id]);

    // Fetch profile for plan generation
    $stmt = $pdo->prepare('SELECT * FROM profiles WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch();
    if (!$profile) {
        throw new Exception('Profile not found.');
    }

    // Derive age from DOB (fallback to 25 if missing)
    $age = 25;
    if (!empty($profile['date_of_birth'])) {
        $dob = DateTime::createFromFormat('Y-m-d', $profile['date_of_birth']);
        if ($dob) {
            $age = (new DateTime())->diff($dob)->y;
            $age = max(10, min(100, $age));
        }
    }

    // Generate new daily recommendation
    $bmr_data = calculate_recommendation(
        (float)$profile['current_weight_kg'],
        (float)$profile['height_cm'],
        (int)$age,
        (string)$profile['gender'],
        (string)$profile['activity_level'],
        (string)$profile['fitness_goal']
    );
    $plan = generate_daily_plan($user_id, $profile, $bmr_data);

    create_notification(
        $pdo,
        $user_id,
        'system',
        'Goals updated',
        'Your goals were updated and your plan was refreshed.'
    );

    $pdo->commit();

    json_response([
        'success' => true,
        'profile' => [
            'target_weight_kg' => (float)$target_weight_kg,
            'activity_level' => $activity_level,
            'fitness_goal' => $fitness_goal,
        ],
        'recommendation' => [
            'kcal_target' => (int)($plan['kcal_target'] ?? $bmr_data['kcal_target']),
            'protein_g' => (float)($plan['protein_g'] ?? $bmr_data['protein_g']),
            'carbs_g' => (float)($plan['carbs_g'] ?? $bmr_data['carbs_g']),
            'fats_g' => (float)($plan['fats_g'] ?? $bmr_data['fats_g']),
        ],
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Update goals error: ' . $e->getMessage());
    json_response(['success' => false, 'error' => 'Server error'], 500);
}

