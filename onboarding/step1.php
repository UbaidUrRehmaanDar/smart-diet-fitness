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
            
            // 🔧 Handle optional profile picture upload
            if (!empty($_FILES['profile_picture']['tmp_name'])) {
                $err = null;
                $uploaded = process_profile_avatar_upload($user_id, $profile['profile_picture'] ?? null, $_FILES['profile_picture'], $err);
                if ($err) {
                    $errors[] = $err;
                } elseif ($uploaded) {
                    $stmt = $pdo->prepare('UPDATE profiles SET profile_picture = ? WHERE user_id = ?');
                    $stmt->execute([$uploaded, $user_id]);
                }
            }
        } catch (PDOException $e) {
            error_log('Onboarding step1 persist: ' . $e->getMessage());
        }

        if (empty($errors)) {
            header("Location: step2.php");
            exit;
        }
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
            --success-green: #3b82f6;
            
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
            padding: 0.85rem 1.4rem;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: background 0.3s ease, border-radius 0.3s ease, border-color 0.3s ease, color 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--btn-gradient);
            color: white;
        }

        .btn-primary:hover {
            background: var(--btn-gradient-hover);
            border-radius: 12px;
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
            border-radius: 12px;
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
        <form method="POST" action="" enctype="multipart/form-data" novalidate>
            <?php echo csrf_field(); ?>
            
            <!-- Profile Picture with live preview -->
            <div class="form-group" style="text-align: center; margin-bottom: 2rem;">
                <label style="margin-bottom: 1rem;">Profile Photo <span style="color: var(--text-medium); font-weight: normal;">(Optional)</span></label>
                <div style="display: flex; flex-direction: column; align-items: center; gap: 0.75rem;">
                    <!-- Live preview circle -->
                    <div id="avatarPreviewCircle" style="width:90px;height:90px;border-radius:50%;overflow:hidden;background:var(--input-bg);border:3px solid var(--border-light);display:flex;align-items:center;justify-content:center;font-size:2rem;color:var(--text-medium);">
                        <?php
                        $existing_pic_step1 = $profile['profile_picture'] ?? null;
                        if ($existing_pic_step1):
                            $existing_url = avatar_public_url($existing_pic_step1);
                        ?>
                            <img src="<?php echo htmlspecialchars($existing_url, ENT_QUOTES, 'UTF-8'); ?>" alt="" style="width:100%;height:100%;object-fit:cover;display:block;">
                        <?php else: ?>
                            <i class="fa-solid fa-user"></i>
                        <?php endif; ?>
                    </div>
                    <label style="display:inline-flex;align-items:center;gap:0.5rem;padding:0.6rem 1.2rem;background:var(--input-bg);color:var(--primary-blue);border:2px dashed var(--primary-blue);border-radius:8px;cursor:pointer;font-size:0.85rem;font-weight:600;">
                        <i class="fa-solid fa-camera"></i> Choose Photo
                        <input type="file" name="profile_picture" id="step1PicInput" accept="image/jpeg,image/png,image/webp" style="display:none;">
                    </label>
                    <span id="step1-file-info" style="font-size:0.75rem;color:var(--text-medium);">JPEG, PNG or WebP · max 2 MB</span>
                </div>
            </div>
            
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

            <!-- Date of Birth — 3 separate fields -->
            <div class="form-group">
                <label>Date of Birth <span>*</span></label>
                <?php
                $dob_val = $step_data['date_of_birth'] ?? '';
                $dob_parts = $dob_val ? explode('-', $dob_val) : ['', '', ''];
                $dob_year  = $dob_parts[0] ?? '';
                $dob_month = isset($dob_parts[1]) ? ltrim($dob_parts[1], '0') : '';
                $dob_day   = isset($dob_parts[2]) ? ltrim($dob_parts[2], '0') : '';
                ?>
                <!-- Hidden field that gets assembled by JS -->
                <input type="hidden" id="date_of_birth" name="date_of_birth" value="<?php echo htmlspecialchars($dob_val, ENT_QUOTES, 'UTF-8'); ?>">
                <div style="display:grid;grid-template-columns:1fr 1fr 1.4fr;gap:0.75rem;">
                    <div>
                        <input type="number" id="dob_day" placeholder="Day" min="1" max="31"
                               value="<?php echo htmlspecialchars($dob_day, ENT_QUOTES, 'UTF-8'); ?>"
                               style="text-align:center;" oninput="assembleDOB()">
                        <small style="display:block;text-align:center;color:var(--text-light);font-size:0.72rem;margin-top:0.25rem;">Day</small>
                    </div>
                    <div>
                        <input type="number" id="dob_month" placeholder="Month" min="1" max="12"
                               value="<?php echo htmlspecialchars($dob_month, ENT_QUOTES, 'UTF-8'); ?>"
                               style="text-align:center;" oninput="assembleDOB()">
                        <small style="display:block;text-align:center;color:var(--text-light);font-size:0.72rem;margin-top:0.25rem;">Month</small>
                    </div>
                    <div>
                        <input type="number" id="dob_year" placeholder="Year" min="1900" max="<?php echo date('Y') - 5; ?>"
                               value="<?php echo htmlspecialchars($dob_year, ENT_QUOTES, 'UTF-8'); ?>"
                               style="text-align:center;" oninput="assembleDOB()">
                        <small style="display:block;text-align:center;color:var(--text-light);font-size:0.72rem;margin-top:0.25rem;">Year</small>
                    </div>
                </div>
                <p class="field-hint" id="dob_error" style="font-size:0.78rem;color:#ef4444;margin-top:0.3rem;font-weight:600;display:none;"></p>
            </div>

            <!-- Gender — styled card buttons -->
            <div class="form-group">
                <label>Gender <span>*</span></label>
                <?php $sel_gender = $step_data['gender'] ?? ''; ?>
                <input type="hidden" id="gender" name="gender" value="<?php echo htmlspecialchars($sel_gender, ENT_QUOTES, 'UTF-8'); ?>">
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0.75rem;">
                    <?php foreach ([['male','Male','fa-mars'],['female','Female','fa-venus'],['other','Other','fa-genderless']] as [$val,$lbl,$icon]): ?>
                    <label class="gender-card <?php echo $sel_gender === $val ? 'selected' : ''; ?>"
                           style="display:flex;flex-direction:column;align-items:center;gap:0.5rem;padding:1rem 0.5rem;border:2px solid var(--border-light);border-radius:14px;cursor:pointer;transition:all 0.25s;background:var(--bg-right);text-align:center;">
                        <input type="radio" name="_gender_radio" value="<?php echo $val; ?>"
                               <?php echo $sel_gender === $val ? 'checked' : ''; ?>
                               style="display:none;" onchange="selectGender('<?php echo $val; ?>')">
                        <i class="fa-solid <?php echo $icon; ?>" style="font-size:1.5rem;color:var(--text-light);transition:color 0.25s;"></i>
                        <span style="font-size:0.85rem;font-weight:700;color:var(--text-dark);"><?php echo $lbl; ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <p class="field-hint" id="gender_error" style="font-size:0.78rem;color:#ef4444;margin-top:0.3rem;font-weight:600;display:none;">Please select a gender.</p>
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

    <script>
        // ── Gender card selection ──────────────────────────────────────────
        function selectGender(val) {
            document.getElementById('gender').value = val;
            document.querySelectorAll('.gender-card').forEach(card => {
                const radio = card.querySelector('input[type="radio"]');
                const icon  = card.querySelector('i');
                if (radio && radio.value === val) {
                    card.style.borderColor = 'var(--primary-blue)';
                    card.style.background  = 'var(--input-bg)';
                    if (icon) icon.style.color = 'var(--primary-blue)';
                } else {
                    card.style.borderColor = 'var(--border-light)';
                    card.style.background  = 'var(--bg-right)';
                    if (icon) icon.style.color = 'var(--text-light)';
                }
            });
            const errEl = document.getElementById('gender_error');
            if (errEl) errEl.style.display = 'none';
        }

        // Apply selected state on page load (for back-navigation)
        document.addEventListener('DOMContentLoaded', () => {
            const saved = document.getElementById('gender').value;
            if (saved) selectGender(saved);
        });

        // ── Assemble DOB from 3 fields ─────────────────────────────────────
        function assembleDOB() {
            const d = document.getElementById('dob_day').value.trim();
            const m = document.getElementById('dob_month').value.trim();
            const y = document.getElementById('dob_year').value.trim();
            const hidden = document.getElementById('date_of_birth');
            if (d && m && y && y.length === 4) {
                const dd = d.padStart(2, '0');
                const mm = m.padStart(2, '0');
                hidden.value = `${y}-${mm}-${dd}`;
            } else {
                hidden.value = '';
            }
        }

        // ── Avatar live preview ────────────────────────────────────────────
        const step1PicInput = document.getElementById('step1PicInput');
        if (step1PicInput) {
            step1PicInput.addEventListener('change', function() {
                const file = this.files[0];
                if (!file) return;
                document.getElementById('step1-file-info').textContent = file.name + ' selected.';
                const reader = new FileReader();
                reader.onload = function(e) {
                    const circle = document.getElementById('avatarPreviewCircle');
                    circle.innerHTML = `<img src="${e.target.result}" alt="" style="width:100%;height:100%;object-fit:cover;display:block;border-radius:50%;">`;
                };
                reader.readAsDataURL(file);
            });
        }

        // ── Inline validation ──────────────────────────────────────────────
        function showFieldError(inputEl, msg) {
            if (!inputEl) return;
            inputEl.style.borderColor = '#ef4444';
            let hint = inputEl.parentElement.querySelector('.field-hint');
            if (!hint) {
                hint = document.createElement('p');
                hint.className = 'field-hint';
                hint.style.cssText = 'font-size:0.78rem;color:#ef4444;margin-top:0.3rem;font-weight:600;';
                inputEl.parentElement.appendChild(hint);
            }
            hint.textContent = msg;
        }
        function clearFieldError(inputEl) {
            if (!inputEl) return;
            inputEl.style.borderColor = '';
            const hint = inputEl.parentElement.querySelector('.field-hint');
            if (hint) hint.remove();
        }

        document.getElementById('first_name').addEventListener('blur', function() {
            this.value.trim() ? clearFieldError(this) : showFieldError(this, 'First name is required.');
        });
        document.getElementById('last_name').addEventListener('blur', function() {
            this.value.trim() ? clearFieldError(this) : showFieldError(this, 'Last name is required.');
        });
        document.getElementById('height_cm').addEventListener('blur', function() {
            const v = parseFloat(this.value);
            (v >= 100 && v <= 250) ? clearFieldError(this) : showFieldError(this, 'Height must be 100–250 cm.');
        });
        document.getElementById('current_weight_kg').addEventListener('blur', function() {
            const v = parseFloat(this.value);
            (v >= 20 && v <= 300) ? clearFieldError(this) : showFieldError(this, 'Weight must be 20–300 kg.');
        });
        document.getElementById('target_weight_kg').addEventListener('blur', function() {
            const v = parseFloat(this.value);
            (v >= 20 && v <= 300) ? clearFieldError(this) : showFieldError(this, 'Target weight must be 20–300 kg.');
        });

        ['first_name','last_name','height_cm','current_weight_kg','target_weight_kg'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.addEventListener('input', () => clearFieldError(el));
        });

        // Validate DOB on submit
        document.querySelector('form').addEventListener('submit', function(e) {
            assembleDOB();
            let valid = true;

            // Gender check
            const genderVal = document.getElementById('gender').value;
            const genderErr = document.getElementById('gender_error');
            if (!genderVal) {
                genderErr.style.display = 'block';
                valid = false;
            } else {
                genderErr.style.display = 'none';
            }

            // DOB check
            const dob = document.getElementById('date_of_birth').value;
            const errEl = document.getElementById('dob_error');
            if (!dob) {
                errEl.textContent = 'Please enter a valid date of birth.';
                errEl.style.display = 'block';
                valid = false;
            } else {
                const age = (new Date() - new Date(dob)) / (365.25 * 24 * 3600 * 1000);
                if (age < 5 || age > 120) {
                    errEl.textContent = 'Please enter a valid date of birth.';
                    errEl.style.display = 'block';
                    valid = false;
                } else {
                    errEl.style.display = 'none';
                }
            }

            if (!valid) e.preventDefault();
        });
    </script>
</body>
</html>