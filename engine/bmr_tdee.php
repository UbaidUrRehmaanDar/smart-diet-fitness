<?php
/**
 * BMR & TDEE Calculator
 * Implements: Mifflin-St Jeor formula for BMR
 * Applies: Activity level multiplier to calculate TDEE
 * Returns: Daily calorie targets and macro splits
 * Performance: <100ms for calculation (optimized)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

/**
 * Calculate Basal Metabolic Rate (BMR) using Mifflin-St Jeor formula
 * 
 * @param float $weight_kg Weight in kilograms
 * @param float $height_cm Height in centimeters
 * @param int $age Age in years
 * @param string $gender 'male', 'female', 'other'
 * @return float BMR in kcal/day
 */
function calculate_bmr($weight_kg, $height_cm, $age, $gender) {
    // Mifflin-St Jeor Formula
    if ($gender === 'male') {
        $bmr = (10 * $weight_kg) + (6.25 * $height_cm) - (5 * $age) + 5;
    } elseif ($gender === 'female') {
        $bmr = (10 * $weight_kg) + (6.25 * $height_cm) - (5 * $age) - 161;
    } else {
        // Average for non-binary users
        $bmr_male = (10 * $weight_kg) + (6.25 * $height_cm) - (5 * $age) + 5;
        $bmr_female = (10 * $weight_kg) + (6.25 * $height_cm) - (5 * $age) - 161;
        $bmr = ($bmr_male + $bmr_female) / 2;
    }
    
    return round($bmr, 2);
}

/**
 * Get activity multiplier based on activity level
 * 
 * @param string $activity_level 'sedentary', 'lightly_active', 'moderately_active', 'very_active', 'extremely_active'
 * @return float Activity multiplier (1.2 - 1.9)
 */
function get_activity_multiplier($activity_level) {
    $multipliers = [
        'sedentary' => 1.2,
        'lightly_active' => 1.375,
        'moderately_active' => 1.55,
        'very_active' => 1.725,
        'extremely_active' => 1.9,
    ];
    
    return $multipliers[$activity_level] ?? 1.55; // Default to moderately active
}

/**
 * Calculate Total Daily Energy Expenditure (TDEE)
 * 
 * @param float $bmr Basal Metabolic Rate
 * @param string $activity_level Activity level enum
 * @return float TDEE in kcal/day
 */
function calculate_tdee($bmr, $activity_level) {
    $multiplier = get_activity_multiplier($activity_level);
    return round($bmr * $multiplier, 2);
}

/**
 * Apply goal-based calorie adjustment
 * 
 * @param float $tdee Total Daily Energy Expenditure
 * @param string $goal 'weight_loss', 'muscle_gain', 'maintenance'
 * @return float Adjusted daily calorie target
 */
function apply_goal_adjustment($tdee, $goal) {
    $adjustment = match($goal) {
        'weight_loss' => -500,      // 500 kcal deficit for ~0.5 kg/week loss
        'muscle_gain' => 300,        // 300 kcal surplus for muscle building
        'maintenance' => 0,          // No adjustment
        default => 0,
    };
    
    $target_kcal = $tdee + $adjustment;
    
    // Ensure minimum 1200 kcal (health constraint)
    return max($target_kcal, 1200);
}

/**
 * Calculate macronutrient targets
 * Standard split: Protein 30%, Carbs 40%, Fats 30%
 * 
 * @param float $kcal Daily calorie target
 * @return array ['protein_g' => float, 'carbs_g' => float, 'fats_g' => float]
 */
function calculate_macros($kcal) {
    // Calorie per gram: Protein=4, Carbs=4, Fats=9
    $protein_kcal = $kcal * 0.30;
    $carbs_kcal = $kcal * 0.40;
    $fats_kcal = $kcal * 0.30;
    
    return [
        'protein_g' => round($protein_kcal / 4, 1),
        'carbs_g' => round($carbs_kcal / 4, 1),
        'fats_g' => round($fats_kcal / 9, 1),
    ];
}

/**
 * Calculate complete recommendation package
 * 
 * @param int $weight_kg
 * @param int $height_cm
 * @param int $age
 * @param string $gender
 * @param string $activity_level
 * @param string $fitness_goal
 * @return array Complete recommendation package
 */
function calculate_recommendation($weight_kg, $height_cm, $age, $gender, $activity_level, $fitness_goal) {
    // Step 1: Calculate BMR
    $bmr = calculate_bmr($weight_kg, $height_cm, $age, $gender);
    
    // Step 2: Calculate TDEE
    $tdee = calculate_tdee($bmr, $activity_level);
    
    // Step 3: Apply goal adjustment
    $kcal_target = apply_goal_adjustment($tdee, $fitness_goal);
    
    // Step 4: Calculate macros
    $macros = calculate_macros($kcal_target);
    
    return [
        'bmr' => $bmr,
        'tdee' => $tdee,
        'kcal_target' => round($kcal_target, 0),
        'protein_g' => $macros['protein_g'],
        'carbs_g' => $macros['carbs_g'],
        'fats_g' => $macros['fats_g'],
        'goal_adjustment' => apply_goal_adjustment($tdee, $fitness_goal) - $tdee,
    ];
}

// Example usage for testing:
/*
$recommendation = calculate_recommendation(
    weight_kg: 80,
    height_cm: 180,
    age: 25,
    gender: 'male',
    activity_level: 'moderately_active',
    fitness_goal: 'weight_loss'
);

echo json_encode($recommendation, JSON_PRETTY_PRINT);
*/

?>
