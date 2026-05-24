<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';

if (isset($_SESSION['user_id'])) {
    if (user_needs_onboarding($pdo, (int) $_SESSION['user_id'])) {
        header('Location: ../onboarding/step1.php');
    } else {
        header('Location: ../pages/dashboard.php');
    }
    exit;
}

$error = '';
$info = '';
$reset_link = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = sanitize($_POST['email'] ?? '');

    if (!validate_email($email)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            // Removed 'is_active' from query to match current database schema
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?')->execute([$user['id']]);

                $token = generate_token(32);
                $token_hash = hash('sha256', $token);
                $expires_at = date('Y-m-d H:i:s', time() + 3600);

                $insert = $pdo->prepare('INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)');
                $insert->execute([$user['id'], $token_hash, $expires_at]);

                $reset_link = APP_URL . '/auth/reset_password.php?token=' . $token;
                $sent = send_password_reset_email($email, $reset_link);
                if (!$sent) {
                    error_log('Password reset email failed for user_id=' . $user['id']);
                }
            }

            $info = 'If an account exists for that email, a reset link has been issued.';
        } catch (Exception $e) {
            error_log('Password reset request error: ' . $e->getMessage());
            $error = 'Unable to process your request. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Smart Diet & Fitness</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --bg-left: #d2e4fd;
            --text-dark: #1b3679;
            --text-medium: #4a6aa6;
            --primary-blue: #3d7bf4;
            --primary-blue-hover: #2960cc;
            --input-bg: #f0f5ff;
            --input-placeholder: #8ca7db;
            --bg-right: #ffffff;
            --border-light: #e5edf9;
            --btn-gradient: linear-gradient(135deg, #4d8df5 0%, #3470e8 100%);
            --btn-gradient-hover: linear-gradient(135deg, #3d7bf4 0%, #2056c7 100%);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { min-height: 100vh; display: flex; background-color: var(--bg-right); }

        .panel {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem;
        }

        .card {
            width: 100%;
            max-width: 420px;
            background-color: var(--bg-right);
            border-radius: 24px;
            box-shadow: 0 12px 30px rgba(27, 54, 121, 0.08);
            padding: 2.5rem;
        }

        h1 { font-size: 2rem; font-weight: 700; color: var(--text-dark); margin-bottom: 0.5rem; }
        p { font-size: 0.95rem; color: var(--text-medium); margin-bottom: 2rem; }

        .input-group { margin-bottom: 1.5rem; }
        label { font-size: 0.8rem; font-weight: 700; color: var(--text-medium); text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 0.5rem; }
        input {
            width: 100%;
            border: 2px solid transparent;
            border-radius: 12px;
            background-color: var(--input-bg);
            padding: 0.95rem 1rem;
            font-size: 0.95rem;
            color: var(--text-dark);
            outline: none;
            transition: all 0.3s ease;
        }
        input:focus { border-color: var(--primary-blue); background-color: #fff; box-shadow: 0 4px 12px rgba(61, 123, 244, 0.1); }

        .btn-primary {
            width: 100%;
            padding: 1rem;
            border: none;
            background: var(--btn-gradient);
            color: #fff;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            border-radius: 50px;
            box-shadow: 0 4px 15px rgba(61, 123, 244, 0.3);
            transition: all 0.4s cubic-bezier(0.25, 1, 0.5, 1);
        }
        .btn-primary:hover { background: var(--btn-gradient-hover); border-radius: 12px; }

        .message { padding: 0.9rem 1rem; border-radius: 10px; font-size: 0.85rem; margin-bottom: 1.5rem; }
        .message.error { background-color: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .message.info { background-color: #eff6ff; border: 1px solid #bfdbfe; color: #1d4ed8; }

        .dev-link { margin-top: 1rem; background-color: var(--input-bg); padding: 0.8rem; border-radius: 12px; font-size: 0.8rem; color: var(--text-medium); word-break: break-all; }
        .back-link { display: inline-block; margin-top: 1.5rem; color: var(--primary-blue); text-decoration: none; font-weight: 600; font-size: 0.9rem; }
        .back-link:hover { color: var(--text-dark); }
    </style>
</head>
<body>
    <div class="panel">
        <div class="card">
            <h1>Reset your password</h1>
            <p>Enter your email to receive a reset link.</p>

            <?php if ($error): ?>
                <div class="message error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if ($info): ?>
                <div class="message info"><?php echo htmlspecialchars($info, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <div class="input-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <button type="submit" class="btn-primary">Send Reset Link</button>
            </form>

            <?php if ($reset_link && APP_ENV !== 'production'): ?>
                <div class="dev-link">
                    Reset link (dev): <a href="<?php echo htmlspecialchars($reset_link, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($reset_link, ENT_QUOTES, 'UTF-8'); ?></a>
                </div>
            <?php endif; ?>

            <a href="login.php" class="back-link">Back to Sign In</a>
        </div>
    </div>
</body>
</html>
