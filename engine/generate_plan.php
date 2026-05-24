<?php
/**
 * Recommendation Plan Generator
 * Generates: Daily meal plan + workout plan for user
 * Stores in: recommendations table
 * Performance: <5 seconds (optimized with lookup tables)
 * Rule-based: No ML - simple lookup and allocation
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/bmr_tdee.php';

/**
 * Get meal templates based on calorie target and goal
 * Precomputed lookup tables for performance
 * 
 * @param int $kcal_target Daily calorie target
 * @param string $goal 'weight_loss', 'muscle_gain', 'maintenance'
 * @return array Meal plan
 */
function get_meal_templates($kcal_target, $goal) {
    // Simple meal distribution (breakfast:lunch:dinner:snacks = 30:35:25:10)
    $breakfast_kcal = round($kcal_target * 0.30);
    $lunch_kcal = round($kcal_target * 0.35);
    $dinner_kcal = round($kcal_target * 0.25);
    $snack_kcal = round($kcal_target * 0.10);
    
    return [
        [
            'meal_type' => 'breakfast',
            'target_kcal' => $breakfast_kcal,
            'suggested_foods' => 'Oatmeal, eggs, fruit',
        ],
        [
            'meal_type' => 'lunch',
            'target_kcal' => $lunch_kcal,
            'suggested_foods' => 'Lean protein, vegetables, rice',
        ],
        [
            'meal_type' => 'dinner',
            'target_kcal' => $dinner_kcal,
            'suggested_foods' => 'Fish/chicken, greens, sweet potato',
        ],
        [
            'meal_type' => 'snack',
            'target_kcal' => $snack_kcal,
            'suggested_foods' => 'Nuts, yogurt, protein shake',
        ],
    ];
}

/**
 * Get workout recommendations based on goal and activity level
 * Precomputed lookup tables for fast access
 * 
 * @param string $goal 'weight_loss', 'muscle_gain', 'maintenance'
 * @param string $activity_level User activity level
 * @return array Workout plan
 */
function get_workout_templates($goal, $activity_level) {
    // Goal-based workout plans (rule-based, not ML)
    $workouts = [
        'weight_loss' => [
            ['exercise' => 'Running', 'duration' => 30, 'intensity' => 'vigorous', 'frequency' => 'daily'],
            ['exercise' => 'HIIT Training', 'duration' => 20, 'intensity' => 'vigorous', 'frequency' => 'alternate days'],
            ['exercise' => 'Cycling', 'duration' => 45, 'intensity' => 'moderate', 'frequency' => '3x/week'],
            ['exercise' => 'Strength Training', 'duration' => 40, 'intensity' => 'moderate', 'frequency' => '3x/week'],
        ],
        'muscle_gain' => [
            ['exercise' => 'Weight Lifting - Upper Body', 'duration' => 50, 'intensity' => 'vigorous', 'frequency' => '3x/week'],
            ['exercise' => 'Weight Lifting - Lower Body', 'duration' => 50, 'intensity' => 'vigorous', 'frequency' => '3x/week'],
            ['exercise' => 'Compound Lifts', 'duration' => 60, 'intensity' => 'vigorous', 'frequency' => '2x/week'],
            ['exercise' => 'Cardio Recovery', 'duration' => 20, 'intensity' => 'light', 'frequency' => 'daily'],
        ],
        'maintenance' => [
            ['exercise' => 'Mixed Cardio', 'duration' => 30, 'intensity' => 'moderate', 'frequency' => '5x/week'],
            ['exercise' => 'Strength Training', 'duration' => 40, 'intensity' => 'moderate', 'frequency' => '3x/week'],
            ['exercise' => 'Flexibility/Yoga', 'duration' => 30, 'intensity' => 'light', 'frequency' => '2x/week'],
            ['exercise' => 'Sports/Recreation', 'duration' => 45, 'intensity' => 'moderate', 'frequency' => '2x/week'],
        ],
    ];
    
    return $workouts[$goal] ?? $workouts['maintenance'];
}

/**
 * Calculate estimated calories burned for workouts
 * Formula: (MET × weight_kg × duration_hours)
 * Precomputed MET values for common exercises
 * 
 * @param string $exercise Exercise name
 * @param int $duration_mins Duration in minutes
 * @param float $weight_kg User weight
 * @return int Estimated calories burned
 */
function estimate_calories_burned($exercise, $duration_mins, $weight_kg) {
    // MET (Metabolic Equivalent) values for common exercises
    $met_values = [
        'Running' => 9.8,
        'Running (jogging)' => 7.0,
        'Cycling' => 7.5,
        'HIIT Training' => 13.0,
        'Weight Lifting' => 6.0,
        'Weight Lifting - Upper Body' => 6.0,
        'Weight Lifting - Lower Body' => 6.0,
        'Compound Lifts' => 8.0,
        'Walking' => 3.5,
        'Swimming' => 8.0,
        'Cardio Recovery' => 4.0,
        'Mixed Cardio' => 6.0,
        'Flexibility/Yoga' => 3.0,
        'Sports/Recreation' => 6.0,
        'Strength Training' => 6.0,
    ];
    
    $met = $met_values[$exercise] ?? 5.0; // Default MET if not found
    $duration_hours = $duration_mins / 60;
    
    return round($met * $weight_kg * $duration_hours);
}

/**
 * Generate complete daily recommendation plan
 * Stores in database and returns data
 * 
 * @param int $user_id User ID
 * @param array $profile User profile data
 * @param array $bmr_data BMR/TDEE calculation results
 * @return array Generated recommendation data
 */
function generate_daily_plan($user_id, $profile, $bmr_data) {
    global $pdo;
    
    try {
        $kcal_target = $bmr_data['kcal_target'];
        $goal = $profile['fitness_goal'];
        $activity_level = $profile['activity_level'];
        $weight_kg = $profile['current_weight_kg'];
        
        // Get meal and workout templates
        $meal_plan = get_meal_templates($kcal_target, $goal);
        $workout_templates = get_workout_templates($goal, $activity_level);
        
        // Calculate estimated calories for workouts
        $workout_plan = [];
        foreach ($workout_templates as $workout) {
            $kcal_burned = estimate_calories_burned(
                $workout['exercise'],
                $workout['duration'],
                $weight_kg
            );
            $workout['kcal_burned'] = $kcal_burned;
            $workout_plan[] = $workout;
        }
        
        // Create recommendation record in database
        $today = date('Y-m-d');
        
        // Check if recommendation exists for today
        $stmt = $pdo->prepare('
            SELECT id FROM recommendations 
            WHERE user_id = ? AND recommendation_date = ?
        ');
        $stmt->execute([$user_id, $today]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update existing recommendation
            $stmt = $pdo->prepare('
                UPDATE recommendations 
                SET kcal_target = ?, 
                    protein_g = ?, 
                    carbs_g = ?, 
                    fats_g = ?, 
                    meal_plan = ?, 
                    workout_plan = ?, 
                    generated_at = NOW()
                WHERE user_id = ? AND recommendation_date = ?
            ');
            $stmt->execute([
                $kcal_target,
                $bmr_data['protein_g'],
                $bmr_data['carbs_g'],
                $bmr_data['fats_g'],
                json_encode($meal_plan),
                json_encode($workout_plan),
                $user_id,
                $today,
            ]);
        } else {
            // Create new recommendation
            $stmt = $pdo->prepare('
                INSERT INTO recommendations 
                (user_id, recommendation_date, kcal_target, protein_g, carbs_g, fats_g, meal_plan, workout_plan, generated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ');
            $stmt->execute([
                $user_id,
                $today,
                $kcal_target,
                $bmr_data['protein_g'],
                $bmr_data['carbs_g'],
                $bmr_data['fats_g'],
                json_encode($meal_plan),
                json_encode($workout_plan),
            ]);
        }
        
        // // 🔧 Keep reminder notifications aligned with the latest generated plan.
        upsert_daily_reminder_notifications($user_id, $meal_plan, $workout_plan, $profile);

        return [
            'success' => true,
            'kcal_target' => $kcal_target,
            'protein_g' => $bmr_data['protein_g'],
            'carbs_g' => $bmr_data['carbs_g'],
            'fats_g' => $bmr_data['fats_g'],
            'meal_plan' => $meal_plan,
            'workout_plan' => $workout_plan,
            'bmr' => $bmr_data['bmr'],
            'tdee' => $bmr_data['tdee'],
        ];
        
    } catch (PDOException $e) {
        error_log('Plan generation error: ' . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Failed to generate recommendation plan.',
        ];
    }
}

/**
 * Estimate hydration target from profile data.
 * // 🔧 No schema change needed; derived from weight and activity.
 * @param array $profile
 * @return int target in ml
 */
function estimate_hydration_target_ml(array $profile): int
{
    $weight_kg = (float)($profile['current_weight_kg'] ?? 0);
    $activity_level = (string)($profile['activity_level'] ?? 'moderately_active');

    $base_ml = $weight_kg > 0 ? (int)round($weight_kg * 35) : 2000;
    $activity_extra = match ($activity_level) {
        'very_active' => 400,
        'extremely_active' => 600,
        'lightly_active' => 150,
        'moderately_active' => 250,
        default => 0
    };

    return max(1500, min(4500, $base_ml + $activity_extra));
}

/**
 * Create/update today's reminder notifications.
 * // 🔧 Prevent duplicates by replacing today's reminder notifications.
 */
function upsert_daily_reminder_notifications(int $user_id, array $meal_plan, array $workout_plan, array $profile): void
{
    global $pdo;

    try {
        $stmt = $pdo->prepare('
            SELECT meal_reminders, workout_reminders, hydration_reminders
            FROM preferences
            WHERE user_id = ?
            LIMIT 1
        ');
        $stmt->execute([$user_id]);
        $prefs = $stmt->fetch() ?: [];

        $meal_on = (int)($prefs['meal_reminders'] ?? 1) === 1;
        $workout_on = (int)($prefs['workout_reminders'] ?? 1) === 1;
        $hydration_on = (int)($prefs['hydration_reminders'] ?? 1) === 1;

        // Remove today's existing reminder notifications (non-destructive for achievements/system).
        $stmt = $pdo->prepare("
            DELETE FROM notifications
            WHERE user_id = ?
              AND notification_type IN ('meal_reminder', 'workout_reminder', 'hydration_reminder')
              AND DATE(created_at) = CURDATE()
        ");
        $stmt->execute([$user_id]);

        $ins = $pdo->prepare('
            INSERT INTO notifications (user_id, notification_type, title, message, is_read, created_at)
            VALUES (?, ?, ?, ?, 0, NOW())
        ');

        if ($meal_on && !empty($meal_plan)) {
            $first_meal = $meal_plan[0]['meal_type'] ?? 'meal';
            $ins->execute([
                $user_id,
                'meal_reminder',
                'Meal plan ready',
                'Your ' . ucfirst((string)$first_meal) . ' plan is ready for today.'
            ]);
        }

        if ($workout_on && !empty($workout_plan)) {
            $first_workout = $workout_plan[0]['exercise'] ?? 'workout';
            $ins->execute([
                $user_id,
                'workout_reminder',
                'Workout scheduled',
                'Today\'s focus starts with ' . (string)$first_workout . '.'
            ]);
        }

        if ($hydration_on) {
            $target_ml = estimate_hydration_target_ml($profile);
            $ins->execute([
                $user_id,
                'hydration_reminder',
                'Hydration target',
                'Aim for about ' . number_format($target_ml) . ' ml water today.'
            ]);
        }
    } catch (PDOException $e) {
        error_log('Reminder notification upsert error: ' . $e->getMessage());
    }
}

/**
 * Ensure an active recommendation exists for today.
 * // 🔧 Called on first dashboard load and after onboarding.
 * @param int $user_id
 * @return array ['success'=>bool, 'recommendation'=>array|null, 'error'=>string|null]
 */
function ensure_todays_plan(int $user_id): array
{
    global $pdo;

    try {
        $today = date('Y-m-d');
        $stmt = $pdo->prepare('
            SELECT *
            FROM recommendations
            WHERE user_id = ? AND recommendation_date = ? AND is_active = 1
            LIMIT 1
        ');
        $stmt->execute([$user_id, $today]);
        $existing = $stmt->fetch();
        if ($existing) {
            return ['success' => true, 'recommendation' => $existing, 'error' => null];
        }

        $stmt = $pdo->prepare('SELECT * FROM profiles WHERE user_id = ? LIMIT 1');
        $stmt->execute([$user_id]);
        $profile = $stmt->fetch();
        if (!$profile) {
            return ['success' => false, 'recommendation' => null, 'error' => 'Profile missing'];
        }

        $required = ['current_weight_kg', 'height_cm', 'activity_level', 'fitness_goal', 'gender'];
        foreach ($required as $field) {
            if (!isset($profile[$field]) || $profile[$field] === '' || $profile[$field] === null) {
                return ['success' => false, 'recommendation' => null, 'error' => 'Incomplete profile'];
            }
        }

        $age = 25;
        if (!empty($profile['date_of_birth'])) {
            $dob = DateTime::createFromFormat('Y-m-d', (string)$profile['date_of_birth']);
            if ($dob) {
                $age = max(10, min(100, (new DateTime())->diff($dob)->y));
            }
        }

        $bmr_data = calculate_recommendation(
            (float)$profile['current_weight_kg'],
            (float)$profile['height_cm'],
            (int)$age,
            (string)$profile['gender'],
            (string)$profile['activity_level'],
            (string)$profile['fitness_goal']
        );

        $plan = generate_daily_plan($user_id, $profile, $bmr_data);
        if (empty($plan['success'])) {
            return ['success' => false, 'recommendation' => null, 'error' => 'Generation failed'];
        }

        $stmt = $pdo->prepare('
            SELECT *
            FROM recommendations
            WHERE user_id = ? AND recommendation_date = ?
            LIMIT 1
        ');
        $stmt->execute([$user_id, $today]);
        $created = $stmt->fetch() ?: null;

        return ['success' => true, 'recommendation' => $created, 'error' => null];
    } catch (PDOException $e) {
        error_log('Ensure today plan error: ' . $e->getMessage());
        return ['success' => false, 'recommendation' => null, 'error' => 'Server error'];
    }
}

/**
 * Get today's recommendation for a user
 * 
 * @param int $user_id
 * @return array|null Recommendation data or null if not found
 */
function get_todays_recommendation($user_id) {
    global $pdo;
    
    $today = date('Y-m-d');
    
    $stmt = $pdo->prepare('
        SELECT * FROM recommendations 
        WHERE user_id = ? AND recommendation_date = ?
    ');
    $stmt->execute([$user_id, $today]);
    
    return $stmt->fetch();
}

?>
