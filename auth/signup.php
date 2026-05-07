<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// If already logged in, redirect to onboarding or dashboard
if (isset($_SESSION['user_id'])) {
    if (user_needs_onboarding($pdo, (int) $_SESSION['user_id'])) {
        header('Location: ../onboarding/step1.php');
    } else {
        header('Location: ../pages/dashboard.php');
    }
    exit;
}

$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    
    $name = sanitize_plain_text($_POST['name'] ?? '', 200);
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($name) || empty($email) || empty($password)) {
        $error = "All fields are required.";
    } elseif (!validate_email($email)) {
        $error = "Please enter a valid email address.";
    } elseif (!validate_password($password)['valid']) {
        $error = "Password must be at least 8 characters, include uppercase, lowercase, number, and special character.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        try {
            // Check if email exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = "Email already registered. Please sign in.";
            }

            if (!$error) {
                // Start transaction
                $pdo->beginTransaction();

                // Create user in users table
                $hash = hash_password($password);
                $user_col_stmt = $pdo->prepare('
                    SELECT COLUMN_NAME
                    FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
                ');
                $user_col_stmt->execute([DB_NAME, 'users']);
                $user_columns = array_map('strtolower', array_column($user_col_stmt->fetchAll(), 'COLUMN_NAME'));
                $user_columns = array_fill_keys($user_columns, true);

                $user_insert_cols = ['email', 'password_hash'];
                $user_values = [$email, $hash];

                if (isset($user_columns['role'])) {
                    $user_insert_cols[] = 'role';
                    $user_values[] = 'user';
                }
                if (isset($user_columns['is_active'])) {
                    $user_insert_cols[] = 'is_active';
                    $user_values[] = 1;
                }

                $user_placeholders = implode(', ', array_fill(0, count($user_insert_cols), '?'));
                $user_sql = 'INSERT INTO users (' . implode(', ', $user_insert_cols) . ') VALUES (' . $user_placeholders . ')';
                $stmt = $pdo->prepare($user_sql);
                $stmt->execute($user_values);
                $user_id = $pdo->lastInsertId();

            $name_parts = preg_split('/\s+/', trim($name));
            $first_name = $name_parts[0] ?? '';
            $last_name = count($name_parts) > 1 ? implode(' ', array_slice($name_parts, 1)) : '';

            // Insert into profiles with required defaults
            $col_stmt = $pdo->prepare('
                SELECT COLUMN_NAME, COLUMN_TYPE
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
            ');
            $col_stmt->execute([DB_NAME, 'profiles']);
            $profile_rows = $col_stmt->fetchAll();
            $profile_columns = [];
            $profile_enums = [];
            foreach ($profile_rows as $row) {
                $col_name = strtolower($row['COLUMN_NAME']);
                $profile_columns[$col_name] = true;
                if (isset($row['COLUMN_TYPE']) && stripos($row['COLUMN_TYPE'], 'enum(') === 0) {
                    $enum_raw = trim($row['COLUMN_TYPE']);
                    $enum_raw = preg_replace('/^enum\((.*)\)$/i', '$1', $enum_raw);
                    $values = array_map(function ($val) {
                        return trim($val, "'\"");
                    }, explode(',', $enum_raw));
                    $profile_enums[$col_name] = $values;
                }
            }

            $columns = ['user_id'];
            $values = [$user_id];

            if (isset($profile_columns['first_name'])) {
                $columns[] = 'first_name';
                $values[] = $first_name;
            }
            if (isset($profile_columns['last_name'])) {
                $columns[] = 'last_name';
                $values[] = $last_name;
            }
            if (isset($profile_columns['height_cm'])) {
                $columns[] = 'height_cm';
                $values[] = 170;
            }
            if (isset($profile_columns['current_weight_kg'])) {
                $columns[] = 'current_weight_kg';
                $values[] = 70;
            }
            if (isset($profile_columns['target_weight_kg'])) {
                $columns[] = 'target_weight_kg';
                $values[] = 70;
            }
            if (isset($profile_columns['activity_level'])) {
                $columns[] = 'activity_level';
                $activity_value = 'lightly_active';
                if (isset($profile_enums['activity_level']) && !in_array($activity_value, $profile_enums['activity_level'], true)) {
                    $activity_value = $profile_enums['activity_level'][0] ?? $activity_value;
                }
                $values[] = $activity_value;
            }
            if (isset($profile_columns['fitness_goal'])) {
                $columns[] = 'fitness_goal';
                $goal_value = 'maintenance';
                if (isset($profile_enums['fitness_goal']) && !in_array($goal_value, $profile_enums['fitness_goal'], true)) {
                    $goal_value = $profile_enums['fitness_goal'][0] ?? $goal_value;
                }
                $values[] = $goal_value;
            }
            if (isset($profile_columns['onboarding_completed'])) {
                $columns[] = 'onboarding_completed';
                $values[] = 0;
            }

            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            $sql = 'INSERT INTO profiles (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);

                $pref_table_stmt = $pdo->prepare('
                    SELECT COUNT(*)
                    FROM information_schema.tables
                    WHERE table_schema = ? AND table_name = ?
                ');
                $pref_table_stmt->execute([DB_NAME, 'preferences']);
                $pref_exists = (int)$pref_table_stmt->fetchColumn() > 0;
                if ($pref_exists) {
                    $pref_stmt = $pdo->prepare('INSERT INTO preferences (user_id) VALUES (?)');
                    $pref_stmt->execute([$user_id]);
                }

                // Commit transaction
                $pdo->commit();

                // Auto-login
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user_id;
                $_SESSION['role'] = 'user';
                $_SESSION['user_name'] = trim((string)$first_name . ' ' . (string)$last_name) !== ''
                    ? trim((string)$first_name . ' ' . (string)$last_name)
                    : (strstr($email, '@', true) ?: 'Member');
                $_SESSION['onboarding_completed'] = 0;

                // Redirect to onboarding step 1
                header("Location: ../onboarding/step1.php");
                exit;
            }
            
        } catch (Exception $e) {
            // Rollback on error to prevent orphaned users
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            
            // Log the actual error for debugging (Check C:\laragon\www\SHFS\logs\error.log)
            error_log("Signup Error: " . $e->getMessage());
            
            // Show user-friendly message
            if (APP_DEBUG) {
                $error = "Database Error: " . $e->getMessage();
            } else {
                $error = "An error occurred while creating your account. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - Smart Diet & Fitness</title>
    
    <!-- Inter Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            /* Color Palette */
            --bg-left: #d2e4fd;
            --text-dark: #1b3679;
            --text-medium: #4a6aa6;
            --primary-blue: #3d7bf4;
            --primary-blue-hover: #2960cc;
            --input-bg: #f0f5ff;
            --input-placeholder: #8ca7db;
            --bg-right: #ffffff;
            --border-light: #e5edf9;
            
            /* Gradients */
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
            display: flex;
            min-height: 100vh;
            background-color: var(--bg-right);
            overflow-x: hidden;
        }

        /* --- Animations --- */
        .fade-in {
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.8s ease, transform 0.8s ease;
        }

        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* Delay classes for staggered animation */
        .delay-1 { transition-delay: 0.1s; }
        .delay-2 { transition-delay: 0.2s; }
        .delay-3 { transition-delay: 0.3s; }
        .delay-4 { transition-delay: 0.4s; }

        /* --- Layout --- */
        .split-layout {
            display: flex;
            width: 100%;
        }

        /* --- Left Panel --- */
        .left-panel {
            flex: 1;
            background-color: var(--bg-left);
            padding: 4rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background-image: linear-gradient(rgba(210, 228, 253, 0.4), rgba(210, 228, 253, 0.9)), url('https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?auto=format&fit=crop&w=1000&q=80');
            background-size: cover;
            background-position: center;
        }

        /* LOGO CSS */
        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-dark);
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .logo-icon {
            width: 42px;
            height: 42px;
            background: var(--btn-gradient);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            box-shadow: 0 4px 15px rgba(61, 123, 244, 0.3);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            background-color: #ffffff;
            color: var(--text-dark);
            padding: 0.6rem 1.2rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 2rem;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            width: fit-content;
        }

        .badge i {
            color: var(--primary-blue);
        }

        .hero-content {
            max-width: 500px;
        }

        .hero-content h1 {
            font-size: 3.5rem;
            font-weight: 700;
            color: var(--text-dark);
            line-height: 1.1;
            margin-bottom: 1.5rem;
            letter-spacing: -1px;
        }

        .hero-content p {
            font-size: 1.125rem;
            color: var(--text-dark);
            font-weight: 500;
            line-height: 1.6;
            max-width: 420px;
        }

        /* --- Right Panel --- */
        .right-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 3rem 4rem;
            background-color: var(--bg-right);
        }

        .form-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 1;
        }

        .form-container {
            width: 100%;
            max-width: 420px;
        }

        .form-header {
            margin-bottom: 2.5rem;
        }

        .form-header h2 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            letter-spacing: -0.5px;
        }

        .form-header p {
            color: var(--primary-blue);
            font-size: 0.95rem;
            font-weight: 500;
            line-height: 1.5;
        }

        /* --- Inputs --- */
        .input-group {
            position: relative;
            margin-bottom: 1.25rem;
        }

        .input-icon {
            position: absolute;
            left: 1.2rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-blue);
            font-size: 1.1rem;
        }

        /* PASSWORD TOGGLE ICON */
        .password-toggle {
            position: absolute;
            right: 1.2rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--input-placeholder);
            font-size: 1.1rem;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .password-toggle:hover {
            color: var(--primary-blue);
        }

        .input-group input {
            width: 100%;
            padding: 1.1rem 3.5rem 1.1rem 3.5rem; 
            border: 2px solid transparent;
            border-radius: 12px;
            background-color: var(--input-bg);
            font-size: 1rem;
            color: var(--text-dark);
            outline: none;
            transition: all 0.3s ease;
        }

        .input-group input::placeholder {
            color: var(--input-placeholder);
        }

        .input-group input:focus {
            border-color: var(--primary-blue);
            background-color: #fff;
            box-shadow: 0 4px 12px rgba(61, 123, 244, 0.1);
        }

        .btn-submit {
            width: 100%;
            padding: 1.1rem;
            border: none;
            background: var(--btn-gradient);
            color: white;
            font-size: 1.05rem;
            font-weight: 600;
            cursor: pointer;
            border-radius: 50px; 
            box-shadow: 0 4px 15px rgba(61, 123, 244, 0.3);
            transition: all 0.4s cubic-bezier(0.25, 1, 0.5, 1);
            margin-bottom: 2rem;
            margin-top: 1rem;
        }

        .btn-submit:hover {
            background: var(--btn-gradient-hover);
            border-radius: 12px; 
            box-shadow: 0 8px 25px rgba(61, 123, 244, 0.4);
            transform: translateY(-2px);
        }

        .btn-submit:active {
            transform: translateY(0);
            box-shadow: 0 2px 10px rgba(61, 123, 244, 0.3);
        }

        .login-link {
            text-align: center;
            color: var(--text-medium);
            font-size: 0.95rem;
        }

        .login-link a {
            color: var(--text-dark);
            font-weight: 700;
            text-decoration: none;
            margin-left: 0.25rem;
            transition: color 0.3s ease;
        }

        .login-link a:hover {
            color: var(--primary-blue);
        }
        
        /* Error message styling */
        .error-message {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.25rem;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* --- Responsive Design --- */
        @media (max-width: 900px) {
            .split-layout {
                flex-direction: column;
            }
            .left-panel {
                padding: 3rem 2rem;
                min-height: 35vh;
            }
            .right-panel {
                padding: 3rem 2rem;
            }
        }
    </style>
</head>
<body>

    <div class="split-layout">
        
        <!-- Left Panel -->
        <div class="left-panel">
            
            <!-- LOGO MOVED HERE -->
            <div class="logo fade-in">
                <div class="logo-icon"><i class="fa-solid fa-leaf"></i></div>
                Smart Diet & Fitness
            </div>

            <!-- Grouped Bottom Content -->
            <div>
                <div class="badge fade-in">
                    <i class="fa-solid fa-medal"></i> Your Health, Simplified.
                </div>
                <div class="hero-content">
                    <h1 class="fade-in delay-1">Build better habits today.</h1>
                    <p class="fade-in delay-2">Join thousands of others in taking control of your fitness journey.</p>
                </div>
            </div>

        </div>

        <!-- Right Panel (Form) -->
        <div class="right-panel">
            <div class="form-wrapper">
                <div class="form-container">
                    <div class="form-header fade-in delay-1">
                        <h2>Create an Account</h2>
                        <p>Enter your details below to get started.</p>
                    </div>

                    <!-- Display error if exists -->
                    <?php if (!empty($error)): ?>
                        <div class="error-message fade-in">
                            <i class="fa-solid fa-circle-exclamation"></i>
                            <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="signup.php">
                        <?php echo csrf_field(); ?>
                        
                        <div class="input-group fade-in delay-2">
                            <i class="fa-regular fa-user input-icon"></i>
                            <input type="text" name="name" placeholder="Full Name" value="<?php echo htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>

                        <div class="input-group fade-in delay-2">
                            <i class="fa-regular fa-envelope input-icon"></i>
                            <input type="email" name="email" placeholder="Email Address" value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>

                        <div class="input-group fade-in delay-3">
                            <i class="fa-solid fa-lock input-icon"></i>
                            <input type="password" id="signupPassword" name="password" placeholder="Create Password" required>
                            <!-- Toggle Password Icon -->
                            <i class="fa-regular fa-eye password-toggle" onclick="togglePassword('signupPassword', this)"></i>
                        </div>

                        <!-- Hidden confirm field for JS validation -->
                        <input type="hidden" name="confirm_password" id="confirmPassword">

                        <button type="submit" class="btn-submit fade-in delay-4">Sign Up</button>
                    </form>

                    <p class="login-link fade-in delay-4">
                        Already have an account? <a href="login.php">Sign In</a>
                    </p>
                </div>
            </div>
        </div>

    </div>

    <!-- Scripts -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            setTimeout(() => {
                const elements = document.querySelectorAll('.fade-in');
                elements.forEach(el => el.classList.add('visible'));
            }, 100);
        });

        // Function to toggle password visibility
        function togglePassword(inputId, icon) {
            const input = document.getElementById(inputId);
            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            } else {
                input.type = "password";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            }
        }
        
        // Copy password to confirm field on submit (for backend validation)
        document.querySelector('form').addEventListener('submit', function() {
            document.getElementById('confirmPassword').value = document.getElementById('signupPassword').value;
        });
    </script>

</body>
</html>