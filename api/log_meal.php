<?php
/**
 * API: Log Meal
 * POST /api/log_meal.php
 * Logs food/meal intake and returns updated daily totals
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
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Sanitize inputs
    $meal_type = $_POST['meal_type'] ?? ($input['meal_type'] ?? '');
    $food_item = sanitize($_POST['food_item'] ?? ($input['food_item'] ?? ''));
    $kcal = sanitize_number($_POST['kcal'] ?? ($input['kcal'] ?? 0));
    $protein_g = sanitize_number($_POST['protein_g'] ?? ($input['protein_g'] ?? 0));
    $carbs_g = sanitize_number($_POST['carbs_g'] ?? ($input['carbs_g'] ?? 0));
    $fats_g = sanitize_number($_POST['fats_g'] ?? ($input['fats_g'] ?? 0));
    $notes = sanitize($_POST['notes'] ?? ($input['notes'] ?? ''));
    
    // Validation
    if (!in_array($meal_type, ['breakfast', 'lunch', 'dinner', 'snack'])) {
        http_response_code(400);
        $response['error'] = 'Invalid meal type';
        echo json_encode($response);
        exit;
    }
    
    if (empty($food_item)) {
        http_response_code(400);
        $response['error'] = 'Food item is required';
        echo json_encode($response);
        exit;
    }
    
    if ($kcal < 0 || $kcal > 10000) {
        http_response_code(400);
        $response['error'] = 'Invalid calorie value (0-10000)';
        echo json_encode($response);
        exit;
    }
    
    $user_id = get_user_id();
    $today = date('Y-m-d');
    
    // Insert meal log
    $stmt = $pdo->prepare('
        INSERT INTO diet_logs 
        (user_id, meal_type, food_item, kcal, protein_g, carbs_g, fats_g, logged_date, notes, logged_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ');
    $stmt->execute([
        $user_id,
        $meal_type,
        $food_item,
        intval($kcal),
        $protein_g,
        $carbs_g,
        $fats_g,
        $today,
        $notes,
    ]);
    
    // Get daily totals
    $stmt = $pdo->prepare('
        SELECT 
            COALESCE(SUM(kcal), 0) as total_kcal,
            COALESCE(SUM(protein_g), 0) as total_protein,
            COALESCE(SUM(carbs_g), 0) as total_carbs,
            COALESCE(SUM(fats_g), 0) as total_fats,
            COUNT(*) as meal_count
        FROM diet_logs 
        WHERE user_id = ? AND logged_date = ?
    ');
    $stmt->execute([$user_id, $today]);
    $totals = $stmt->fetch();

    // Achievement unlocks based on total meals logged
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM diet_logs WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $meal_count = (int)$stmt->fetchColumn();

    if ($meal_count >= 1) {
        unlock_achievement($pdo, $user_id, 'First Meal Logged', 'fa-solid fa-utensils', 'Log your first meal.', 'milestone');
    }
    if ($meal_count >= 25) {
        unlock_achievement($pdo, $user_id, 'Meal Tracker', 'fa-solid fa-clipboard-check', 'Log 25 meals.', 'consistency');
    }
    if ($meal_count >= 100) {
        unlock_achievement($pdo, $user_id, 'Meal Master', 'fa-solid fa-star', 'Log 100 meals.', 'milestone');
    }
    
    // Get recommendation target
    $stmt = $pdo->prepare('
        SELECT kcal_target, protein_g, carbs_g, fats_g 
        FROM recommendations 
        WHERE user_id = ? AND recommendation_date = ?
    ');
    $stmt->execute([$user_id, $today]);
    $recommendation = $stmt->fetch();
    
    $response['success'] = true;
    $response['logged_meal'] = [
        'food' => $food_item,
        'kcal' => intval($kcal),
    ];
    $response['daily_totals'] = [
        'kcal' => intval($totals['total_kcal']),
        'kcal_target' => intval($recommendation['kcal_target'] ?? 2000),
        'protein' => round($totals['total_protein'], 1),
        'protein_target' => intval($recommendation['protein_g'] ?? 150),
        'carbs' => round($totals['total_carbs'], 1),
        'carbs_target' => intval($recommendation['carbs_g'] ?? 225),
        'fats' => round($totals['total_fats'], 1),
        'fats_target' => intval($recommendation['fats_g'] ?? 67),
        'meal_count' => intval($totals['meal_count']),
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log('Meal logging error: ' . $e->getMessage());
    http_response_code(500);
    $response['error'] = 'Server error';
    echo json_encode($response);
}

?>
