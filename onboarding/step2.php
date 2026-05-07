<?php
/**
 * Onboarding Step 2: Activity Level & Preferences
 * Collects: Activity level, diet type, allergies, medical conditions
 * Validates: Against allowed values
 * Session: Stores in $_SESSION['onboard']
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

$user_id = get_user_id();
$errors = [];

// Check if step 1 data exists
if (empty($_SESSION['onboard'])) {
    header("Location: step1.php");
    exit;
}

$step_data = $_SESSION['onboard'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    
    $activity_level = $_POST['activity_level'] ?? '';
    $diet_type = $_POST['diet_type'] ?? '';
    $allergies = sanitize($_POST['allergies'] ?? '');
    $medical_conditions = sanitize($_POST['medical_conditions'] ?? '');
    
    // Validation
    $valid_activities = ['sedentary', 'lightly_active', 'moderately_active', 'very_active', 'extremely_active'];
    if (!in_array($activity_level, $valid_activities)) {
        $errors[] = 'Please select a valid activity level.';
    }
    
    $valid_diets = ['omnivore', 'vegetarian', 'vegan', 'keto', 'paleo'];
    if (!in_array($diet_type, $valid_diets)) {
        $errors[] = 'Please select a valid diet type.';
    }
    
    if (empty($errors)) {
        // Parse allergies and medical conditions into arrays
        $allergies_array = !empty($allergies) 
            ? array_filter(array_map('trim', explode(',', $allergies))) 
            : [];
        
        $conditions_array = !empty($medical_conditions) 
            ? array_filter(array_map('trim', explode(',', $medical_conditions))) 
            : [];
        
        // Add to session data
        $_SESSION['onboard'] = array_merge($step_data, [
            'activity_level' => $activity_level,
            'diet_type' => $diet_type,
            'allergies' => json_encode($allergies_array),
            'medical_conditions' => json_encode($conditions_array),
        ]);

        try {
            persist_onboarding_preferences_step2(
                $pdo,
                $user_id,
                $diet_type,
                $_SESSION['onboard']['allergies'],
                $_SESSION['onboard']['medical_conditions']
            );
            ensure_profile_row_exists($pdo, $user_id);
            $act = $pdo->prepare(
                'UPDATE profiles SET activity_level = ? WHERE user_id = ?'
            );
            $act->execute([$activity_level, $user_id]);
        } catch (PDOException $e) {
            error_log('Onboarding step2 persist: ' . $e->getMessage());
        }

        header("Location: step3.php");
        exit;
    }
}

$page_title = 'Activity & Lifestyle - ' . APP_NAME;
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
            --text-light: #8ca7db;
            --primary-blue: #3d7bf4;
            --primary-blue-hover: #2960cc;
            --input-bg: #f0f5ff;
            --bg-right: #ffffff;
            --border-light: #e5edf9;
            --success-green: #10b981;
            
            --btn-gradient: linear-gradient(135deg, #4d8df5 0%, #3470e8 100%);
            --btn-gradient-hover: linear-gradient(135deg, #3d7bf4 0%, #2056c7 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-dark);
            min-height: 100vh;
        }

        .container {
            max-width: 700px;
            margin: 3rem auto;
            padding: 3rem;
            background: var(--bg-right);
            border-radius: 24px;
            box-shadow: 0 10px 40px rgba(27, 54, 121, 0.08);
        }

        /* Progress Bar - FIXED */
        .progress-bar {
            margin-bottom: 3rem;
        }

        .progress-steps {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            position: relative;
        }

        .progress-steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background-color: var(--border-light);
            z-index: 0;
        }

        .step {
            flex: 1;
            text-align: center;
            position: relative;
            z-index: 1;
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
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .step-circle i {
            position: relative;
            z-index: 3; /* Ensure checkmark is above progress line */
        }

        .step.active .step-circle {
            background-color: var(--primary-blue);
            color: white;
            border-color: var(--primary-blue);
            box-shadow: 0 4px 12px rgba(61, 123, 244, 0.3);
        }

        .step.completed .step-circle {
            background-color: var(--success-green);
            color: white;
            border-color: var(--success-green);
        }

        .step-label {
            font-size: 0.85rem;
            color: var(--text-medium);
            font-weight: 500;
        }

        .step.active .step-label {
            color: var(--primary-blue);
            font-weight: 600;
        }

        .step.completed .step-label {
            color: var(--success-green);
        }

        /* Form Header */
        .form-header {
            margin-bottom: 2rem;
            text-align: center;
        }

        .form-header h1 {
            font-size: 2.2rem;
            margin-bottom: 0.5rem;
            color: var(--text-dark);
        }

        .form-header p {
            color: var(--text-medium);
            font-size: 1rem;
        }

        .step-indicator {
            display: inline-block;
            background-color: var(--input-bg);
            color: var(--primary-blue);
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

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

        .error-message i {
            font-size: 1.2rem;
            margin-top: 0.1rem;
        }

        .error-message ul {
            margin: 0;
            padding-left: 1.5rem;
        }

        .error-message li {
            margin-bottom: 0.3rem;
            font-size: 0.9rem;
        }

        /* Info Box */
        .info-box {
            background-color: var(--input-bg);
            padding: 1rem 1.25rem;
            border-radius: 12px;
            border-left: 4px solid var(--primary-blue);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.9rem;
            color: var(--text-medium);
        }

        .info-box i {
            color: var(--primary-blue);
            font-size: 1.1rem;
        }

        /* Form */
        .form-group {
            margin-bottom: 2rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.75rem;
            color: var(--text-dark);
            font-weight: 600;
            font-size: 0.95rem;
        }

        .form-group label span {
            color: var(--primary-blue);
        }

        /* Radio Group - Activity Levels */
        .radio-group {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .radio-option {
            display: flex;
            align-items: flex-start;
            padding: 1.25rem;
            border: 2px solid var(--border-light);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            background-color: var(--bg-right);
        }

        .radio-option:hover {
            border-color: var(--primary-blue);
            background-color: var(--input-bg);
        }

        .radio-option input[type="radio"] {
            margin: 0.2rem 1rem 0 0;
            cursor: pointer;
            width: 20px;
            height: 20px;
            accent-color: var(--primary-blue);
        }

        .radio-content {
            flex: 1;
        }

        .radio-content label {
            margin: 0;
            cursor: pointer;
            font-weight: 600;
            color: var(--text-dark);
            display: block;
            margin-bottom: 0.25rem;
        }

        .radio-content small {
            display: block;
            color: var(--text-medium);
            font-size: 0.85rem;
            line-height: 1.4;
        }

        /* Select & Textarea */
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.9rem 1.1rem;
            border: 2px solid var(--border-light);
            border-radius: 12px;
            background-color: var(--input-bg);
            color: var(--text-dark);
            font-size: 0.95rem;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
        }

        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-blue);
            background-color: var(--bg-right);
            box-shadow: 0 0 0 4px rgba(61, 123, 244, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-group small {
            display: block;
            color: var(--text-light);
            font-size: 0.85rem;
            margin-top: 0.5rem;
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2.5rem;
        }

        .btn {
            flex: 1;
            padding: 1rem;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.25, 1, 0.5, 1);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--btn-gradient);
            color: white;
            box-shadow: 0 4px 15px rgba(61, 123, 244, 0.3);
        }

        .btn-primary:hover {
            background: var(--btn-gradient-hover);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(61, 123, 244, 0.4);
        }

        .btn-secondary {
            background-color: var(--bg-right);
            color: var(--text-dark);
            border: 2px solid var(--border-light);
        }

        .btn-secondary:hover {
            border-color: var(--primary-blue);
            color: var(--primary-blue);
            background-color: var(--input-bg);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                margin: 1rem;
                padding: 2rem;
            }

            .form-header h1 {
                font-size: 1.8rem;
            }

            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Progress Bar -->
        <div class="progress-bar">
            <div class="progress-steps">
                <div class="step completed">
                    <div class="step-circle">
                        <i class="fa-solid fa-check"></i>
                    </div>
                    <div class="step-label">Profile</div>
                </div>
                <div class="step active">
                    <div class="step-circle">2</div>
                    <div class="step-label">Activity</div>
                </div>
                <div class="step">
                    <div class="step-circle">3</div>
                    <div class="step-label">Goals</div>
                </div>
            </div>
        </div>

        <!-- Form Header -->
        <div class="form-header">
            <span class="step-indicator">Step 2 of 3</span>
            <h1>Activity & Lifestyle</h1>
            <p>Help us understand your daily routine and dietary preferences</p>
        </div>

        <!-- Info Box -->
        <div class="info-box">
            <i class="fa-solid fa-circle-info"></i>
            <span>This information helps us calculate your daily calorie needs and recommend suitable meals.</span>
        </div>

        <!-- Error Messages -->
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
            
            <!-- Activity Level -->
            <div class="form-group">
                <label>Activity Level <span>*</span></label>
                <div class="radio-group">
                    <label class="radio-option">
                        <input type="radio" name="activity_level" value="sedentary" required>
                        <div class="radio-content">
                            <label>Sedentary</label>
                            <small>Little or no exercise, desk job</small>
                        </div>
                    </label>
                    
                    <label class="radio-option">
                        <input type="radio" name="activity_level" value="lightly_active" required>
                        <div class="radio-content">
                            <label>Lightly Active</label>
                            <small>1-3 days/week of light exercise</small>
                        </div>
                    </label>
                    
                    <label class="radio-option">
                        <input type="radio" name="activity_level" value="moderately_active" required>
                        <div class="radio-content">
                            <label>Moderately Active</label>
                            <small>3-5 days/week of moderate exercise</small>
                        </div>
                    </label>
                    
                    <label class="radio-option">
                        <input type="radio" name="activity_level" value="very_active" required>
                        <div class="radio-content">
                            <label>Very Active</label>
                            <small>6-7 days/week of intensive exercise</small>
                        </div>
                    </label>
                    
                    <label class="radio-option">
                        <input type="radio" name="activity_level" value="extremely_active" required>
                        <div class="radio-content">
                            <label>Extremely Active</label>
                            <small>Physical job or intense training twice daily</small>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Diet Type -->
            <div class="form-group">
                <label for="diet_type">Preferred Diet Type <span>*</span></label>
                <select id="diet_type" name="diet_type" required>
                    <option value="">Select Diet Type</option>
                    <option value="omnivore">Omnivore (Everything)</option>
                    <option value="vegetarian">Vegetarian</option>
                    <option value="vegan">Vegan</option>
                    <option value="keto">Keto (Low Carb)</option>
                    <option value="paleo">Paleo</option>
                </select>
            </div>

            <!-- Allergies -->
            <div class="form-group">
                <label for="allergies">Food Allergies or Restrictions</label>
                <textarea
                    id="allergies"
                    name="allergies"
                    placeholder="e.g., peanuts, dairy, shellfish, gluten"
                    rows="2"
                ></textarea>
                <small>Separate multiple items with commas. Leave empty if none.</small>
            </div>

            <!-- Medical Conditions -->
            <div class="form-group">
                <label for="medical_conditions">Medical Conditions (Optional)</label>
                <textarea
                    id="medical_conditions"
                    name="medical_conditions"
                    placeholder="e.g., diabetes, hypertension, thyroid"
                    rows="2"
                ></textarea>
                <small>This helps us recommend appropriate foods. Leave empty if none.</small>
            </div>

            <!-- Actions -->
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="window.location.href='step1.php'">
                    <i class="fa-solid fa-arrow-left"></i> Back
                </button>
                <button type="submit" class="btn btn-primary">
                    Continue to Step 3 <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>
        </form>
    </div>
</body>
</html>