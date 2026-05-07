<?php
/**
 * Onboarding Step 1: Profile Information
 * Collects: Name, DOB, Gender, Height, Weight, Target Weight
 * Pre-fills: Name from signup session
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

$user_id = get_user_id();
$errors = [];
$step_data = $_SESSION['onboard'] ?? [];

// Pre-fill name from signup if not already set
if (isset($_SESSION['user_name']) && empty($step_data['first_name']) && empty($step_data['last_name'])) {
    $full_name = $_SESSION['user_name'];
    $name_parts = explode(' ', trim($full_name), 2);
    $step_data['first_name'] = $step_data['first_name'] ?? ($name_parts[0] ?? '');
    $step_data['last_name'] = $step_data['last_name'] ?? ($name_parts[1] ?? '');
}

// Fetch current profile data
try {
    $stmt = $pdo->prepare('SELECT * FROM profiles WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Profile fetch error: ' . $e->getMessage());
    $profile = null;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    
    $first_name = sanitize_plain_text($_POST['first_name'] ?? '');
    $last_name = sanitize_plain_text($_POST['last_name'] ?? '');
    $date_of_birth = $_POST['date_of_birth'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $height_cm = sanitize_number($_POST['height_cm'] ?? 0);
    $current_weight = sanitize_number($_POST['current_weight_kg'] ?? 0);
    $target_weight = sanitize_number($_POST['target_weight_kg'] ?? 0);
    
    // Validation
    if (empty($first_name)) $errors[] = 'First name is required.';
    if (empty($last_name)) $errors[] = 'Last name is required.';
    if (empty($date_of_birth) || !validate_date($date_of_birth)) {
        $errors[] = 'Valid date of birth is required.';
    }
    if (!in_array($gender, ['male', 'female', 'other'])) {
        $errors[] = 'Please select a valid gender.';
    }
    if (!validate_range($height_cm, 100, 250)) {
        $errors[] = 'Height must be between 100-250 cm.';
    }
    if (!validate_range($current_weight, 20, 300)) {
        $errors[] = 'Current weight must be between 20-300 kg.';
    }
    if (!validate_range($target_weight, 20, 300)) {
        $errors[] = 'Target weight must be between 20-300 kg.';
    }
    
    // Store in session for next step + persist profile immediately (fixes empty Settings if onboarding stops early)
    if (empty($errors)) {
        $_SESSION['onboard'] = array_merge($step_data, [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'date_of_birth' => $date_of_birth,
            'gender' => $gender,
            'height_cm' => $height_cm,
            'current_weight_kg' => $current_weight,
            'target_weight_kg' => $target_weight,
        ]);

        try {
            persist_onboarding_profile_step1($pdo, $user_id, $_SESSION['onboard']);
            $_SESSION['user_name'] = trim($first_name . ' ' . $last_name);
        } catch (PDOException $e) {
            error_log('Onboarding step1 persist: ' . $e->getMessage());
        }

        header("Location: step2.php");
        exit;
    }
}

$page_title = 'Profile Setup - ' . APP_NAME;
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

        /* Progress Bar - FIXED TICKS & Z-INDEX */
        .progress-bar {
            margin-bottom: 3rem;
            position: relative;
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
            z-index: 0; /* Line behind circles */
        }

        .step {
            flex: 1;
            text-align: center;
            position: relative;
            z-index: 2; /* Circles above line */
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
            position: relative;
            z-index: 2; /* Circle above line */
        }

        .step-circle i {
            position: relative;
            z-index: 3; /* Tick icon above circle */
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

        /* Info Box - FontAwesome Icon (not emoji) */
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
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-dark);
            font-weight: 600;
            font-size: 0.95rem;
        }

        .form-group label span {
            color: var(--primary-blue);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.9rem 1.1rem;
            border: 2px solid var(--border-light);
            border-radius: 12px;
            background-color: var(--input-bg);
            color: var(--text-dark);
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-blue);
            background-color: var(--bg-right);
            box-shadow: 0 0 0 4px rgba(61, 123, 244, 0.1);
        }

        .form-group input::placeholder {
            color: var(--text-light);
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

            .form-row {
                grid-template-columns: 1fr;
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
                <div class="step active">
                    <div class="step-circle">1</div>
                    <div class="step-label">Profile</div>
                </div>
                <div class="step">
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
            <span class="step-indicator">Step 1 of 3</span>
            <h1>Let's Get Started!</h1>
            <p>Tell us about yourself so we can create your personalized plan</p>
        </div>

        <!-- Info Box - FontAwesome Icon (not emoji) -->
        <div class="info-box">
            <i class="fa-solid fa-circle-info"></i>
            <span>This information helps us calculate your daily calorie and macro targets.</span>
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
            
            <!-- Name (Pre-filled from signup) -->
            <div class="form-row">
                <div class="form-group">
                    <label for="first_name">First Name <span>*</span></label>
                    <input
                        type="text"
                        id="first_name"
                        name="first_name"
                        placeholder="John"
                        value="<?php echo htmlspecialchars($step_data['first_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                        required
                    >
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name <span>*</span></label>
                    <input
                        type="text"
                        id="last_name"
                        name="last_name"
                        placeholder="Doe"
                        value="<?php echo htmlspecialchars($step_data['last_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                        required
                    >
                </div>
            </div>

            <!-- Date of Birth -->
            <div class="form-group">
                <label for="date_of_birth">Date of Birth <span>*</span></label>
                <input
                    type="date"
                    id="date_of_birth"
                    name="date_of_birth"
                    value="<?php echo htmlspecialchars($step_data['date_of_birth'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    required
                >
            </div>

            <!-- Gender -->
            <div class="form-group">
                <label for="gender">Gender <span>*</span></label>
                <select id="gender" name="gender" required>
                    <option value="">Select Gender</option>
                    <option value="male" <?php echo ($step_data['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                    <option value="female" <?php echo ($step_data['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                    <option value="other" <?php echo ($step_data['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>

            <!-- Height & Weight -->
            <div class="form-row">
                <div class="form-group">
                    <label for="height_cm">Height (cm) <span>*</span></label>
                    <input
                        type="number"
                        id="height_cm"
                        name="height_cm"
                        placeholder="180"
                        value="<?php echo htmlspecialchars($step_data['height_cm'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                        min="100"
                        max="250"
                        step="0.1"
                        required
                    >
                </div>
                <div class="form-group">
                    <label for="current_weight_kg">Current Weight (kg) <span>*</span></label>
                    <input
                        type="number"
                        id="current_weight_kg"
                        name="current_weight_kg"
                        placeholder="80"
                        value="<?php echo htmlspecialchars($step_data['current_weight_kg'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                        min="20"
                        max="300"
                        step="0.1"
                        required
                    >
                </div>
            </div>

            <!-- Target Weight -->
            <div class="form-group">
                <label for="target_weight_kg">Target Weight (kg) <span>*</span></label>
                <input
                    type="number"
                    id="target_weight_kg"
                    name="target_weight_kg"
                    placeholder="75"
                    value="<?php echo htmlspecialchars($step_data['target_weight_kg'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    min="20"
                    max="300"
                    step="0.1"
                    required
                >
            </div>

            <!-- Actions -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    Continue to Step 2 <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>
        </form>
    </div>
</body>
</html>