<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (isset($_SESSION['user_id'])) {
    if (user_needs_onboarding($pdo, (int) $_SESSION['user_id'])) {
        header('Location: ../onboarding/step1.php');
    } else {
        header('Location: ../pages/dashboard.php');
    }
    exit;
}

$error = '';
$success = '';
$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$token_hash = $token ? hash('sha256', $token) : '';
$user_id = null;

if ($token_hash) {
    $stmt = $pdo->prepare('SELECT user_id, expires_at FROM password_reset_tokens WHERE token = ?');
    $stmt->execute([$token_hash]);
    $record = $stmt->fetch();
    if ($record && strtotime($record['expires_at']) > time()) {
        $user_id = (int)$record['user_id'];
    } else {
        $error = 'This reset link is invalid or has expired.';
    }
} else {
    $error = 'Reset link is missing or invalid.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_id) {
    verify_csrf();
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $validation = validate_password($password);
    if (!$validation['valid']) {
        $error = 'Password must be at least 8 characters, include uppercase, lowercase, number, and special character.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $hash = hash_password($password);
            $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $stmt->execute([$hash, $user_id]);

            $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?')->execute([$user_id]);

            header('Location: login.php?reset=1');
            exit;
        } catch (Exception $e) {
            error_log('Password reset error: ' . $e->getMessage());
            $error = 'Unable to reset password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password - Smart Diet & Fitness</title>

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

        .input-group { margin-bottom: 1.2rem; }
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
        .btn-primary:hover { background: var(--btn-gradient-hover); border-radius: 12px; transform: translateY(-2px); box-shadow: 0 8px 25px rgba(61, 123, 244, 0.4); }
        .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

        .message { padding: 0.9rem 1rem; border-radius: 10px; font-size: 0.85rem; margin-bottom: 1.5rem; }
        .message.error { background-color: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .message.info { background-color: #eff6ff; border: 1px solid #bfdbfe; color: #1d4ed8; }

        .back-link { display: inline-block; margin-top: 1.5rem; color: var(--primary-blue); text-decoration: none; font-weight: 600; font-size: 0.9rem; }
        .back-link:hover { color: var(--text-dark); }
    </style>
</head>
<body>
    <div class="panel">
        <div class="card">
            <h1>Create a new password</h1>
            <p>Enter a new password for your account.</p>

            <?php if ($error): ?>
                <div class="message error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="message info"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <?php if ($user_id): ?>
                <form method="POST" action="">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="input-group">
                        <label for="password">New Password</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <div class="input-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    <button type="submit" class="btn-primary">Update Password</button>
                </form>
            <?php endif; ?>

            <a href="login.php" class="back-link">Back to Sign In</a>
        </div>
    </div>
</body>
</html>
