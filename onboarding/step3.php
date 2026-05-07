<?php
/**
 * Onboarding Step 3: Fitness Goals & Plan Generation
 * Collects: Primary fitness goal
 * Generates: Complete recommendation plan
 * Stores: Profile data + plan in database
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../engine/bmr_tdee.php';
require_once __DIR__ . '/../engine/generate_plan.php';

$user_id = get_user_id();
$errors = [];
$step_data = $_SESSION['onboard'] ?? [];

// Check if previous steps data exists
if (empty($step_data)) {
    header("Location: step1.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    
    $fitness_goal = $_POST['fitness_goal'] ?? '';
    
    // Validation
    if (!in_array($fitness_goal, ['weight_loss', 'muscle_gain', 'maintenance'])) {
        $errors[] = 'Please select a valid fitness goal.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Add goal to session data
            $step_data['fitness_goal'] = $fitness_goal;

            // Calculate age from DOB
            $dob = new DateTime($step_data['date_of_birth']);
            $today = new DateTime();
            $age = $today->diff($dob)->y;

            // Calculate BMI
            $height_m = $step_data['height_cm'] / 100;
            $bmi = $step_data['current_weight_kg'] / ($height_m * $height_m);

            // Update profile in database (handle schemas with full_name vs first_name/last_name)
            $columns = $pdo->query('SHOW COLUMNS FROM profiles')->fetchAll();
            $column_names = array_map(function($col) { return $col['Field']; }, $columns);
            $column_types = [];
            foreach ($columns as $col) {
                $column_types[$col['Field']] = $col['Type'];
            }
            
            $set_parts = [];
            $params = [];

            $has_first = in_array('first_name', $column_names, true);
            $has_last = in_array('last_name', $column_names, true);
            $has_full = in_array('full_name', $column_names, true);

            if ($has_first) {
                $set_parts[] = 'first_name = ?';
                $params[] = $step_data['first_name'];
            }

            if ($has_last) {
                $set_parts[] = 'last_name = ?';
                $params[] = $step_data['last_name'];
            }

            if (!$has_first && !$has_last && $has_full) {
                $set_parts[] = 'full_name = ?';
                $params[] = trim($step_data['first_name'] . ' ' . $step_data['last_name']);
            }

            if (in_array('date_of_birth', $column_names, true)) {
                $set_parts[] = 'date_of_birth = ?';
                $params[] = $step_data['date_of_birth'];
            }

            if (in_array('gender', $column_names, true)) {
                $set_parts[] = 'gender = ?';
                $params[] = $step_data['gender'];
            }

            if (in_array('height_cm', $column_names, true)) {
                $set_parts[] = 'height_cm = ?';
                $params[] = $step_data['height_cm'];
            }

            if (in_array('current_weight_kg', $column_names, true)) {
                $set_parts[] = 'current_weight_kg = ?';
                $params[] = $step_data['current_weight_kg'];
            }

            if (in_array('target_weight_kg', $column_names, true)) {
                $set_parts[] = 'target_weight_kg = ?';
                $params[] = $step_data['target_weight_kg'];
            }

            if (in_array('activity_level', $column_names, true)) {
                $activity_value = $step_data['activity_level'];
                $col_type = $column_types['activity_level'] ?? '';
                if (stripos($col_type, 'enum(') === 0) {
                    $enum_raw = preg_replace('/^enum\((.*)\)$/i', '$1', $col_type);
                    $enum_vals = array_map(function ($val) {
                        return trim($val, "'\"");
                    }, explode(',', $enum_raw));
                    if (!in_array($activity_value, $enum_vals, true)) {
                        $activity_value = $enum_vals[0] ?? $activity_value;
                    }
                }
                $set_parts[] = 'activity_level = ?';
                $params[] = $activity_value;
            }

            if (in_array('fitness_goal', $column_names, true)) {
                $set_parts[] = 'fitness_goal = ?';
                $params[] = $fitness_goal;
            }

            if (in_array('bmi', $column_names, true)) {
                $set_parts[] = 'bmi = ?';
                $params[] = round($bmi, 2);
            }

            if (in_array('onboarding_completed', $column_names, true)) {
                $set_parts[] = 'onboarding_completed = 1';
            }

            $params[] = $user_id;

            if (!empty($set_parts)) {
                $stmt = $pdo->prepare('
                    UPDATE profiles 
                    SET ' . implode(', ', $set_parts) . ' 
                    WHERE user_id = ?
                ');

                $stmt->execute($params);
            }

            // Update preferences (schema-aware)
            $pref_table_stmt = $pdo->prepare('
                SELECT COUNT(*)
                FROM information_schema.tables
                WHERE table_schema = ? AND table_name = ?
            ');
            $pref_table_stmt->execute([DB_NAME, 'preferences']);
            $pref_exists = (int)$pref_table_stmt->fetchColumn() > 0;

            if ($pref_exists) {
                $pref_cols = $pdo->query('SHOW COLUMNS FROM preferences')->fetchAll();
                $pref_names = array_map(function($col) { return $col['Field']; }, $pref_cols);
                $pref_types = [];
                foreach ($pref_cols as $col) {
                    $pref_types[$col['Field']] = $col['Type'];
                }

                $pref_set = [];
                $pref_params = [];

                if (in_array('diet_type', $pref_names, true)) {
                    $diet_value = $step_data['diet_type'];
                    $pref_type = $pref_types['diet_type'] ?? '';
                    if (stripos($pref_type, 'enum(') === 0) {
                        $enum_raw = preg_replace('/^enum\((.*)\)$/i', '$1', $pref_type);
                        $enum_vals = array_map(function ($val) {
                            return trim($val, "'\"");
                        }, explode(',', $enum_raw));
                        if (!in_array($diet_value, $enum_vals, true)) {
                            $diet_value = $enum_vals[0] ?? $diet_value;
                        }
                    }
                    $pref_set[] = 'diet_type = ?';
                    $pref_params[] = $diet_value;
                }
                if (in_array('allergies', $pref_names, true)) {
                    $pref_set[] = 'allergies = ?';
                    $pref_params[] = $step_data['allergies'];
                }
                if (in_array('medical_conditions', $pref_names, true)) {
                    $pref_set[] = 'medical_conditions = ?';
                    $pref_params[] = $step_data['medical_conditions'];
                }

                if (!empty($pref_set)) {
                    $pref_params[] = $user_id;
                    $stmt = $pdo->prepare('
                        UPDATE preferences 
                        SET ' . implode(', ', $pref_set) . ' 
                        WHERE user_id = ?
                    ');
                    $stmt->execute($pref_params);
                }
            }

            // Fetch updated profile for plan generation
            $stmt = $pdo->prepare('SELECT * FROM profiles WHERE user_id = ?');
            $stmt->execute([$user_id]);
            $profile = $stmt->fetch() ?: [];

            $profile_values = array_merge($step_data, $profile);

            // Generate recommendation plan
            $bmr_data = calculate_recommendation(
                $profile_values['current_weight_kg'] ?? $step_data['current_weight_kg'],
                $profile_values['height_cm'] ?? $step_data['height_cm'],
                $age,
                $profile_values['gender'] ?? $step_data['gender'],
                $profile_values['activity_level'] ?? $step_data['activity_level'],
                $profile_values['fitness_goal'] ?? $fitness_goal
            );
            
            generate_daily_plan($user_id, $profile_values, $bmr_data);

            $pdo->commit();

            // Clear onboarding session
            unset($_SESSION['onboard']);
            $_SESSION['onboarding_completed'] = 1;

            // Redirect to dashboard with flash message
            // // 🔧 Use existing flash helper keys used by header/footer.
            redirect_with_message('../pages/dashboard.php', 'Profile setup complete! Your personalized plan is ready.', 'success');

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Onboarding Step 3 Error: ' . $e->getMessage());
            if (defined('APP_DEBUG') && APP_DEBUG) {
                $errors[] = 'Debug: ' . $e->getMessage();
            }
            $errors[] = 'An error occurred while setting up your profile. Please try again.';
        }
    }
}

$page_title = 'Fitness Goals - ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    
    <!-- Inter Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-body: #f4f7fb;
            --text-dark: #1b3679;
            --text-medium: #4a6aa6;
            --primary-blue: #3d7bf4;
            --input-bg: #f0f5ff;
            --border-light: #e5edf9;
            --success-green: #10b981;
            --btn-gradient: linear-gradient(135deg, #4d8df5 0%, #3470e8 100%);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: var(--bg-body); color: var(--text-dark); min-height: 100vh; }

        .container {
            max-width: 700px;
            margin: 3rem auto;
            padding: 3rem;
            background: var(--bg-right);
            border-radius: 24px;
            box-shadow: 0 10px 40px rgba(27, 54, 121, 0.08);
        }

        /* FIXED PROGRESS BAR CSS */
        .progress-bar { margin-bottom: 3rem; position: relative; }
        
        .progress-steps {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            position: relative;
        }

        /* The Line */
        .progress-steps::before {
            content: '';
            position: absolute;
            top: 20px; /* Half of circle height */
            left: 0;
            right: 0;
            height: 2px;
            background-color: var(--border-light);
            z-index: 0; /* BEHIND circles */
        }

        .step {
            flex: 1;
            text-align: center;
            position: relative;
            z-index: 1; /* ABOVE line */
        }

        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--bg-right);
            border: 2px solid var(--border-light);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            font-weight: 700;
            color: var(--text-medium);
            position: relative;
            z-index: 2; /* ABOVE everything */
        }

        .step-circle i {
            position: relative;
            z-index: 3; /* Ensure checkmark is above progress line */
        }

        .step.completed .step-circle {
            background-color: var(--success-green);
            border-color: var(--success-green);
            color: white;
        }

        .step.active .step-circle {
            background-color: var(--primary-blue);
            border-color: var(--primary-blue);
            color: white;
        }

        .step-label { font-size: 0.85rem; color: var(--text-medium); font-weight: 500; }
        .step.completed .step-label { color: var(--success-green); font-weight: 600; }
        .step.active .step-label { color: var(--primary-blue); font-weight: 600; }

        /* Form Header */
        .form-header { text-align: center; margin-bottom: 2rem; }
        .form-header h1 { font-size: 2.2rem; margin-bottom: 0.5rem; }
        .form-header p { color: var(--text-medium); }

        /* Error Message */
        .error-message {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }

        /* Goal Cards */
        .card-grid { display: grid; grid-template-columns: 1fr; gap: 1rem; margin-bottom: 2rem; }
        
        .goal-card {
            padding: 1.5rem;
            border: 2px solid var(--border-light);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            background: var(--bg-right);
        }

        .goal-card:hover { border-color: var(--primary-blue); background-color: rgba(61, 123, 244, 0.05); }
        .goal-card.selected { border-color: var(--primary-blue); background-color: rgba(61, 123, 244, 0.1); }
        
        .goal-card input[type="radio"] { display: none; }
        
        .goal-icon {
            font-size: 2.5rem;
            color: var(--text-medium);
            margin-bottom: 1rem;
            transition: color 0.3s ease;
        }

        .goal-card.selected .goal-icon,
        .goal-card:hover .goal-icon {
            color: var(--primary-blue);
        }

        .goal-content h3 { font-size: 1.1rem; margin-bottom: 0.3rem; }
        .goal-content p { font-size: 0.85rem; color: var(--text-medium); }

        /* Summary Box */
        .summary-box {
            background-color: var(--input-bg);
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            border-left: 4px solid var(--primary-blue);
        }
        .summary-item { display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--border-light); }
        .summary-item:last-child { border-bottom: none; }
        .summary-item-label { color: var(--text-medium); font-size: 0.9rem; }
        .summary-item-value { font-weight: 600; color: var(--text-dark); }

        /* Buttons */
        .form-actions { display: flex; gap: 1rem; margin-top: 2rem; }
        .btn {
            flex: 1; padding: 1rem; border: none; border-radius: 50px;
            font-weight: 600; font-size: 0.95rem; cursor: pointer;
            transition: all 0.3s ease; text-decoration: none; display: inline-flex;
            align-items: center; justify-content: center; gap: 0.5rem;
        }
        .btn-primary {
            background: var(--btn-gradient); color: white;
            box-shadow: 0 4px 15px rgba(61, 123, 244, 0.3);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(61, 123, 244, 0.4); }
        .btn-secondary { background-color: var(--bg-right); color: var(--text-dark); border: 2px solid var(--border-light); }
        .btn-secondary:hover { border-color: var(--primary-blue); color: var(--primary-blue); }

        @media (max-width: 768px) {
            .container { margin: 1rem; padding: 2rem; }
            .form-actions { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Progress Bar -->
        <div class="progress-bar">
            <div class="progress-steps">
                <div class="step completed">
                    <div class="step-circle"><i class="fas fa-check"></i></div>
                    <div class="step-label">Profile</div>
                </div>
                <div class="step completed">
                    <div class="step-circle"><i class="fas fa-check"></i></div>
                    <div class="step-label">Activity</div>
                </div>
                <div class="step active">
                    <div class="step-circle">3</div>
                    <div class="step-label">Goals</div>
                </div>
            </div>
        </div>

        <!-- Form Header -->
        <div class="form-header">
            <h1>Your Fitness Goal</h1>
            <p>Select your primary objective to get personalized recommendations</p>
        </div>

        <!-- Summary -->
        <div class="summary-box">
            <div class="summary-item">
                <span class="summary-item-label">Name:</span>
                <span class="summary-item-value"><?php echo htmlspecialchars($step_data['first_name'] . ' ' . $step_data['last_name']); ?></span>
            </div>
            <div class="summary-item">
                <span class="summary-item-label">Height:</span>
                <span class="summary-item-value"><?php echo intval($step_data['height_cm']); ?> cm</span>
            </div>
            <div class="summary-item">
                <span class="summary-item-label">Weight:</span>
                <span class="summary-item-value"><?php echo number_format($step_data['current_weight_kg'], 1); ?> kg</span>
            </div>
            <div class="summary-item">
                <span class="summary-item-label">Activity Level:</span>
                <span class="summary-item-value"><?php echo ucwords(str_replace('_', ' ', $step_data['activity_level'])); ?></span>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="error-message">
                <i class="fa-solid fa-circle-exclamation"></i>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <form method="POST" action="" novalidate>
            <?php echo csrf_field(); ?>
            
            <div class="card-grid">
                <label class="goal-card">
                    <input type="radio" name="fitness_goal" value="weight_loss" required onchange="this.parentElement.classList.add('selected')">
                    <div class="goal-icon"><i class="fa-solid fa-scale-balanced"></i></div>
                    <div class="goal-content">
                        <h3>Weight Loss</h3>
                        <p>Lose 0.5 kg/week with 500 kcal deficit</p>
                    </div>
                </label>
                
                <label class="goal-card">
                    <input type="radio" name="fitness_goal" value="muscle_gain" required onchange="this.parentElement.classList.add('selected')">
                    <div class="goal-icon"><i class="fa-solid fa-dumbbell"></i></div>
                    <div class="goal-content">
                        <h3>Muscle Gain</h3>
                        <p>Build muscle with 300 kcal surplus</p>
                    </div>
                </label>
                
                <label class="goal-card">
                    <input type="radio" name="fitness_goal" value="maintenance" required onchange="this.parentElement.classList.add('selected')">
                    <div class="goal-icon"><i class="fa-solid fa-bolt"></i></div>
                    <div class="goal-content">
                        <h3>Maintenance</h3>
                        <p>Maintain current weight and fitness</p>
                    </div>
                </label>
            </div>

            <!-- Actions -->
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="window.history.back()">
                    <i class="fa-solid fa-arrow-left"></i> Back
                </button>
                <button type="submit" class="btn btn-primary">
                    Complete Setup <i class="fa-solid fa-check"></i>
                </button>
            </div>
        </form>
    </div>

    <script>
        // Auto-select goal card when radio is clicked
        document.querySelectorAll('input[name="fitness_goal"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.querySelectorAll('.goal-card').forEach(card => card.classList.remove('selected'));
                this.parentElement.classList.add('selected');
            });
        });
    </script>
</body>
</html>