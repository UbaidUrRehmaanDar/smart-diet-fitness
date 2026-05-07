<?php
// 🔧 Changes Made:
// 🔧 Added onboarding completion guard after login.
// 🔧 Added schema-aware check for onboarding_completed column.
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// If already logged in, send to onboarding or dashboard
if (isset($_SESSION['user_id'])) {
    if (user_needs_onboarding($pdo, (int) $_SESSION['user_id'])) {
        header('Location: ../onboarding/step1.php');
    } else {
        header('Location: ../pages/dashboard.php');
    }
    exit;
}

$error = '';
$logout_success = '';
$success_msg = '';

// Check for logout success message via URL param
if (isset($_GET['logged_out']) && $_GET['logged_out'] === '1') {
    $logout_success = 'You have been successfully logged out.';
}

if (isset($_GET['expired']) && $_GET['expired'] === '1') {
    $error = 'Your session expired. Please sign in again.';
}

if (isset($_GET['reset']) && $_GET['reset'] === '1') {
    $success_msg = 'Your password was updated. Please sign in.';
}

$max_attempts = 5;
$lockout_seconds = 300;
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (!empty($_SESSION['login_lock_until']) && time() < $_SESSION['login_lock_until']) {
        $remaining = $_SESSION['login_lock_until'] - time();
        $error = 'Too many attempts. Try again in ' . ceil($remaining / 60) . ' minutes.';
    }

    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($error) && !validate_email($email)) {
        $error = "Please enter a valid email address.";
    } elseif (empty($error) && empty($password)) {
        $error = "Password is required.";
    } elseif (empty($error)) {
        // Check credentials (is_active when column exists)
        try {
            $stmt = $pdo->prepare('SELECT id, email, password_hash, role, is_active FROM users WHERE email = ?');
            $stmt->execute([$email]);
        } catch (PDOException $e) {
            $stmt = $pdo->prepare('SELECT id, email, password_hash, role FROM users WHERE email = ?');
            $stmt->execute([$email]);
        }
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            if (array_key_exists('is_active', $user) && (int) $user['is_active'] !== 1) {
                $_SESSION['login_attempts']++;
                if ($_SESSION['login_attempts'] >= $max_attempts) {
                    $_SESSION['login_lock_until'] = time() + $lockout_seconds;
                    $error = 'Too many attempts. Try again in 5 minutes.';
                } else {
                    $error = 'This account is inactive. Please contact support.';
                }
            } else {
            // Regenerate session ID to prevent fixation
            session_regenerate_id(true);
            $_SESSION['session_regenerated'] = time();
            $_SESSION['login_attempts'] = 0;
            unset($_SESSION['login_lock_until']);

            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['user_name'] = 'Member';
            try {
                $pn = $pdo->prepare(
                    'SELECT first_name, last_name FROM profiles WHERE user_id = ? LIMIT 1'
                );
                $pn->execute([$user['id']]);
                $prow = $pn->fetch();
                if ($prow) {
                    $disp = trim((string)($prow['first_name'] ?? '') . ' ' . (string)($prow['last_name'] ?? ''));
                    if ($disp !== '') {
                        $_SESSION['user_name'] = $disp;
                    } elseif (!empty($user['email'])) {
                        $at = strstr($user['email'], '@', true);
                        $_SESSION['user_name'] = $at !== false ? $at : 'Member';
                    }
                }
            } catch (PDOException $e) {
                error_log('Login profile name fetch: ' . $e->getMessage());
            }

            $needs_onboarding = user_needs_onboarding($pdo, (int) $user['id']);
            $_SESSION['onboarding_completed'] = $needs_onboarding ? 0 : 1;

            if ($needs_onboarding) {
                header('Location: ../onboarding/step1.php');
                exit;
            }

            // Redirect to dashboard
            $redirect = $_SESSION['login_redirect'] ?? '../pages/dashboard.php';
            unset($_SESSION['login_redirect']);
            header('Location: ' . $redirect);
            exit;
            }
        } else {
            $_SESSION['login_attempts']++;
            if ($_SESSION['login_attempts'] >= $max_attempts) {
                $_SESSION['login_lock_until'] = time() + $lockout_seconds;
                $error = "Too many attempts. Try again in 5 minutes.";
            } else {
                $error = "Invalid email or password. Please try again.";
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
    <title>Login - Smart Diet & Fitness</title>

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

        .delay-1 {
            transition-delay: 0.1s;
        }

        .delay-2 {
            transition-delay: 0.2s;
        }

        .delay-3 {
            transition-delay: 0.3s;
        }

        .delay-4 {
            transition-delay: 0.4s;
        }

        /* --- Layout --- */
        .split-layout {
            display: flex;
            width: 100%;
        }

        /* --- Left Panel (Hero) --- */
        .left-panel {
            flex: 1;
            background-color: var(--bg-left);
            padding: 4rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
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

        .hero-content {
            max-width: 480px;
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
            color: var(--text-medium);
            line-height: 1.6;
            max-width: 400px;
        }

        .social-proof {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .avatars {
            display: flex;
        }

        .avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 2px solid var(--bg-left);
            background-color: var(--primary-blue);
            margin-left: -12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.8rem;
        }

        .avatar:first-child {
            margin-left: 0;
            background-color: #fca5a5;
        }

        .avatar:nth-child(2) {
            background-color: #fde047;
            color: #b45309;
        }

        .social-proof span {
            color: var(--text-dark);
            font-size: 0.95rem;
            font-weight: 500;
        }

        /* --- Right Panel (Form) --- */
        .right-panel {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background-color: var(--bg-right);
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
        }

        .form-header p {
            color: var(--primary-blue);
            font-size: 1rem;
            font-weight: 500;
        }

        .input-group {
            position: relative;
            margin-bottom: 1.25rem;
        }

        .input-group i:not(.password-toggle) {
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
            padding: 1rem 3rem 1rem 3rem;
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

        .forgot-password {
            display: block;
            text-align: right;
            color: var(--primary-blue);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 600;
            margin-top: -0.5rem;
            margin-bottom: 1.5rem;
            transition: color 0.3s ease;
        }

        .forgot-password:hover {
            color: var(--text-dark);
        }

        /* Super Smooth Button styles */
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
            margin-top: 0.5rem;
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

        .signup-link {
            text-align: center;
            color: var(--primary-blue);
            font-size: 0.95rem;
            margin-bottom: 3rem;
        }

        .signup-link a {
            color: var(--text-dark);
            font-weight: 700;
            text-decoration: none;
            margin-left: 0.25rem;
            transition: color 0.3s ease;
        }

        .signup-link a:hover {
            color: var(--primary-blue);
        }

        .footer-links {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
        }

        .footer-links .links {
            display: flex;
            gap: 1.5rem;
        }

        .footer-links a {
            color: var(--input-placeholder);
            text-decoration: none;
            font-size: 0.8rem;
            transition: color 0.3s ease;
        }

        .footer-links a:hover {
            color: var(--primary-blue);
        }

        .footer-links .copyright {
            color: var(--input-placeholder);
            font-size: 0.75rem;
        }

        /* --- Responsive Design --- */
        @media (max-width: 900px) {
            .split-layout {
                flex-direction: column;
            }

            .left-panel {
                padding: 3rem 2rem;
                min-height: 40vh;
                justify-content: center;
                gap: 2rem;
            }

            .hero-content h1 {
                font-size: 2.5rem;
            }

            .right-panel {
                padding: 3rem 2rem;
            }
        }

        /* Error/Success message styling */
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

        .success-message {
            background-color: #d1fae5;
            border: 1px solid #10b981;
            color: #065f46;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.25rem;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
    </style>
</head>

<body>

    <div class="split-layout">

        <!-- Left Hero Section -->
        <div class="left-panel">
            <div class="logo fade-in">
                <div class="logo-icon"><i class="fa-solid fa-leaf"></i></div>
                Smart Diet & Fitness
            </div>
            <div class="hero-content">
                <h1 class="fade-in delay-1">Your Fitness Journey Starts Here.</h1>
                <p class="fade-in delay-2">Personalized nutrition and workout plans for a healthier, stronger you.</p>
            </div>
            <div class="social-proof fade-in delay-3">
                <div class="avatars">
                    <div class="avatar"><i class="fa-solid fa-user"></i></div>
                    <div class="avatar"><i class="fa-solid fa-user-astronaut"></i></div>
                    <div class="avatar"><i class="fa-solid fa-dumbbell"></i></div>
                </div>
                <span>Join 2,000+ fitness enthusiasts</span>
            </div>
        </div>

        <!-- Right Form Section -->
        <div class="right-panel">
            <div class="form-container">
                <div class="form-header fade-in">
                    <h2>Welcome Back</h2>
                    <p>Sign in to continue your fitness journey.</p>
                </div>

                    <!-- Display logout success message -->
                    <?php if (!empty($logout_success)): ?>
                        <div class="success-message fade-in">
                            <i class="fa-solid fa-check-circle"></i>
                            <?php echo htmlspecialchars($logout_success, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($success_msg)): ?>
                        <div class="success-message fade-in">
                            <i class="fa-solid fa-check-circle"></i>
                            <?php echo htmlspecialchars($success_msg, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>

                <!-- Display error if exists -->
                <?php if (!empty($error)): ?>
                    <div class="error-message fade-in">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login.php" id="loginForm">
                    <?php echo csrf_field(); ?>

                    <div class="input-group fade-in delay-1">
                        <i class="fa-regular fa-envelope"></i>
                        <input type="email" name="email" placeholder="Email" value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>

                    <a href="forgot_password.php" class="forgot-password fade-in delay-2">Forgot Password?</a>

                    <div class="input-group fade-in delay-2">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" name="password" id="loginPassword" placeholder="Password" required>
                        <!-- Toggle Password Icon -->
                        <i class="fa-regular fa-eye password-toggle" onclick="togglePassword('loginPassword', this)"></i>
                    </div>

                    <button type="submit" class="btn-submit fade-in delay-3">Sign In</button>
                </form>

                <p class="signup-link fade-in delay-4">
                    New here? <a href="signup.php">Create an Account</a>
                </p>

                <div class="footer-links fade-in delay-4">
                    <div class="links">
                        <a href="<?php echo htmlspecialchars(APP_URL . '/pages/privacy.php', ENT_QUOTES, 'UTF-8'); ?>">Privacy Policy</a>
                        <a href="<?php echo htmlspecialchars(APP_URL . '/pages/terms.php', ENT_QUOTES, 'UTF-8'); ?>">Terms of Service</a>
                        <a href="<?php echo htmlspecialchars(APP_URL . '/pages/support.php', ENT_QUOTES, 'UTF-8'); ?>">Support</a>
                    </div>
                    <span class="copyright">© 2026 Smart Diet & Fitness.</span>
                </div>
            </div>
        </div>

    </div>

    <!-- JS for Animation and Password Toggle -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            setTimeout(() => {
                const elements = document.querySelectorAll('.fade-in');
                elements.forEach(el => {
                    el.classList.add('visible');
                });
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
    </script>

</body>

</html>