<?php
/**
 * 🔧 Changes Made
 * - Added daily summary endpoint for dashboard/widgets.
 *
 * API: Fetch Daily Summary
 * GET /api/fetch_daily_summary.php?date=YYYY-MM-DD
 * Response: JSON
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

$user_id = get_user_id();
$date = $_GET['date'] ?? date('Y-m-d');
if (!validate_date($date)) {
    json_response(['success' => false, 'error' => 'Invalid date.'], 400);
}

try {
    // Diet totals
    $stmt = $pdo->prepare('
        SELECT
            COALESCE(SUM(kcal), 0) AS kcal,
            COALESCE(SUM(protein_g), 0) AS protein_g,
            COALESCE(SUM(carbs_g), 0) AS carbs_g,
            COALESCE(SUM(fats_g), 0) AS fats_g
        FROM diet_logs
        WHERE user_id = ? AND logged_date = ?
    ');
    $stmt->execute([$user_id, $date]);
    $diet = $stmt->fetch() ?: [];

    // Workout totals
    $stmt = $pdo->prepare('
        SELECT
            COALESCE(SUM(kcal_burned), 0) AS kcal_burned,
            COALESCE(SUM(duration_mins), 0) AS duration_mins
        FROM workout_logs
        WHERE user_id = ? AND logged_date = ?
    ');
    $stmt->execute([$user_id, $date]);
    $workout = $stmt->fetch() ?: [];

    // Hydration totals
    $stmt = $pdo->prepare('
        SELECT COALESCE(SUM(amount_ml), 0) AS total_ml
        FROM hydration_logs
        WHERE user_id = ? AND logged_date = ?
    ');
    $stmt->execute([$user_id, $date]);
    $hydration_ml = (int)$stmt->fetchColumn();

    // Recommendation targets (if available)
    $stmt = $pdo->prepare('
        SELECT kcal_target, protein_g, carbs_g, fats_g
        FROM recommendations
        WHERE user_id = ? AND recommendation_date = ?
        LIMIT 1
    ');
    $stmt->execute([$user_id, $date]);
    $rec = $stmt->fetch() ?: [];

    // Hydration target fallback (profile-based)
    $stmt = $pdo->prepare('SELECT current_weight_kg FROM profiles WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch() ?: [];
    $weight_kg = (float)($profile['current_weight_kg'] ?? 0);
    $water_target_ml = $weight_kg > 0 ? (int)round($weight_kg * 35) : 2000; // // 🔧 No DB column required.
    $water_target_ml = max(1500, min(4500, $water_target_ml));

    json_response([
        'success' => true,
        'date' => $date,
        'diet' => [
            'kcal' => (int)round((float)($diet['kcal'] ?? 0)),
            'protein_g' => (float)($diet['protein_g'] ?? 0),
            'carbs_g' => (float)($diet['carbs_g'] ?? 0),
            'fats_g' => (float)($diet['fats_g'] ?? 0),
        ],
        'workout' => [
            'kcal_burned' => (int)round((float)($workout['kcal_burned'] ?? 0)),
            'duration_mins' => (int)round((float)($workout['duration_mins'] ?? 0)),
        ],
        'hydration' => [
            'total_ml' => $hydration_ml,
            'target_ml' => $water_target_ml,
        ],
        'targets' => [
            'kcal_target' => (int)($rec['kcal_target'] ?? 0),
            'protein_g' => (int)($rec['protein_g'] ?? 0),
            'carbs_g' => (int)($rec['carbs_g'] ?? 0),
            'fats_g' => (int)($rec['fats_g'] ?? 0),
        ],
    ]);
} catch (Exception $e) {
    error_log('Daily summary error: ' . $e->getMessage());
    json_response(['success' => false, 'error' => 'Server error'], 500);
}

