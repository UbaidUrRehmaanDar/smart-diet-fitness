<?php
/**
 * 🔧 Changes Made
 * - Added secure password change page (current password verification + strong password policy).
 *
 * Route: /auth/change_password.php
 * Security: session auth + CSRF + password_hash verification
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = 'Change Password - ' . APP_NAME;
$user_id = get_user_id();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($current_password === '' || $new_password === '' || $confirm_password === '') {
        $error = 'All fields are required.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match.';
    } elseif ($current_password === $new_password) {
        $error = 'New password must be different from your current password.';
    } else {
        $validation = validate_password($new_password);
        if (!$validation['valid']) {
            $error = 'Password must be at least 8 characters and include uppercase, lowercase, number, and special character.';
        } else {
            try {
                $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();

                if (!$user || empty($user['password_hash']) || !verify_password($current_password, $user['password_hash'])) {
                    $error = 'Current password is incorrect.';
                } else {
                    $new_hash = hash_password($new_password);
                    // Use schema-safe update (updated_at may not exist in all deployments)
                    try {
                        $stmt = $pdo->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?');
                        $stmt->execute([$new_hash, $user_id]);
                    } catch (PDOException $e) {
                        $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                        $stmt->execute([$new_hash, $user_id]);
                    }

                    // // 🔧 Rotate session after sensitive change.
                    session_regenerate_id(true);
                    $_SESSION['session_regenerated'] = time();

                    $success = 'Password updated successfully.';
                }
            } catch (Exception $e) {
                error_log('Change password error: ' . $e->getMessage());
                $error = 'Unable to update password. Please try again.';
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
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>

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
            --btn-gradient: linear-gradient(135deg, #4d8df5 0%, #3470e8 100%);
            --btn-gradient-hover: linear-gradient(135deg, #3d7bf4 0%, #2056c7 100%);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: var(--bg-body); color: var(--text-dark); min-height: 100vh; }
        .wrap { max-width: 520px; margin: 3rem auto; padding: 2.5rem; background: var(--bg-right); border-radius: 24px; box-shadow: 0 10px 40px rgba(27, 54, 121, 0.08); }
        h1 { font-size: 1.8rem; margin-bottom: 0.5rem; }
        p.subtitle { color: var(--text-medium); margin-bottom: 2rem; }
        .alert { padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; font-weight: 500; }
        .alert-success { background-color: #dbeafe; color: #1e40af; border: 1px solid #3b82f6; }
        .alert-danger { background-color: #fee2e2; color: #991b1b; border: 1px solid #ef4444; }
        .input-group { margin-bottom: 1.25rem; }
        label { display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-medium); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.5rem; }
        .input-wrap { position: relative; }
        .input-wrap input {
            width: 100%;
            border: 2px solid transparent;
            border-radius: 12px;
            background-color: var(--input-bg);
            padding: 1rem 3rem 1rem 1rem;
            font-size: 0.95rem;
            color: var(--text-dark);
            outline: none;
            transition: all 0.3s ease;
        }
        .input-wrap input:focus { border-color: var(--primary-blue); background-color: #fff; box-shadow: 0 4px 12px rgba(61, 123, 244, 0.1); }
        .input-wrap input.error { border-color: #ef4444; }
        .eye-toggle {
            position: absolute; right: 1rem; top: 50%; transform: translateY(-50%);
            color: var(--text-light); cursor: pointer; font-size: 1rem; transition: color 0.3s;
        }
        .eye-toggle:hover { color: var(--primary-blue); }
        .field-error { font-size: 0.78rem; color: #ef4444; font-weight: 600; margin-top: 0.3rem; display: none; }
        .field-error.show { display: block; }
        .strength-wrap { margin-top: 0.5rem; display: none; }
        .strength-bars { display: flex; gap: 4px; margin-bottom: 0.3rem; }
        .strength-bar { flex: 1; height: 4px; border-radius: 2px; background: var(--border-light); transition: background 0.3s; }
        .strength-label { font-size: 0.78rem; font-weight: 600; }
        .actions { display: flex; gap: 0.75rem; margin-top: 1.5rem; }
        .btn {
            flex: 1; padding: 0.85rem 1.4rem; border: none; border-radius: 50px;
            font-weight: 600; cursor: pointer;
            transition: background 0.3s ease, border-radius 0.3s ease, border-color 0.3s ease, color 0.3s ease;
        }
        .btn-primary { background: var(--btn-gradient); color: #fff; }
        .btn-primary:hover { background: var(--btn-gradient-hover); border-radius: 12px; }
        .btn-secondary { background: transparent; border: 2px solid var(--border-light); color: var(--text-dark); }
        .btn-secondary:hover { border-color: var(--primary-blue); color: var(--primary-blue); border-radius: 12px; background: var(--input-bg); }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>Change Password</h1>
        <p class="subtitle">Update your account password securely.</p>

        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fa-solid fa-check"></i> <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="POST" action="" id="changePwForm" novalidate>
            <?php echo csrf_field(); ?>

            <div class="input-group">
                <label for="current_password">Current Password</label>
                <div class="input-wrap">
                    <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
                    <i class="fa-regular fa-eye eye-toggle" onclick="togglePw('current_password', this)"></i>
                </div>
                <p class="field-error" id="err_current">Current password is required.</p>
            </div>

            <div class="input-group">
                <label for="new_password">New Password</label>
                <div class="input-wrap">
                    <input type="password" id="new_password" name="new_password" required autocomplete="new-password" oninput="checkStrength(this.value)">
                    <i class="fa-regular fa-eye eye-toggle" onclick="togglePw('new_password', this)"></i>
                </div>
                <div class="strength-wrap" id="strengthWrap">
                    <div class="strength-bars">
                        <div class="strength-bar" id="pw_sb1"></div>
                        <div class="strength-bar" id="pw_sb2"></div>
                        <div class="strength-bar" id="pw_sb3"></div>
                        <div class="strength-bar" id="pw_sb4"></div>
                    </div>
                    <span class="strength-label" id="pw_label"></span>
                </div>
                <p class="field-error" id="err_new">Min 8 chars, uppercase, lowercase, number, and special character.</p>
            </div>

            <div class="input-group">
                <label for="confirm_password">Confirm New Password</label>
                <div class="input-wrap">
                    <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password" oninput="checkMatch()">
                    <i class="fa-regular fa-eye eye-toggle" onclick="togglePw('confirm_password', this)"></i>
                </div>
                <p class="field-error" id="err_confirm">Passwords do not match.</p>
            </div>

            <div class="actions">
                <button type="button" class="btn btn-secondary" onclick="window.location.href='<?php echo htmlspecialchars(APP_URL . '/pages/settings.php', ENT_QUOTES, 'UTF-8'); ?>'">Back</button>
                <button type="submit" class="btn btn-primary" id="submitBtn">Update Password</button>
            </div>
        </form>
    </div>

    <script>
        function togglePw(id, icon) {
            const input = document.getElementById(id);
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        function checkStrength(val) {
            const wrap = document.getElementById('strengthWrap');
            const label = document.getElementById('pw_label');
            const bars = ['pw_sb1','pw_sb2','pw_sb3','pw_sb4'].map(id => document.getElementById(id));
            if (!val) { wrap.style.display = 'none'; return; }
            wrap.style.display = 'block';
            let score = 0;
            if (val.length >= 8) score++;
            if (/[A-Z]/.test(val)) score++;
            if (/[0-9]/.test(val)) score++;
            if (/[^A-Za-z0-9]/.test(val)) score++;
            const colors = ['#ef4444','#f59e0b','#3b82f6','#3b82f6'];
            const labels = ['Weak','Fair','Good','Strong'];
            bars.forEach((b, i) => b.style.background = i < score ? colors[score-1] : 'var(--border-light)');
            label.textContent = labels[score-1] || '';
            label.style.color = colors[score-1] || 'var(--text-medium)';
            // Hide error if valid
            const errEl = document.getElementById('err_new');
            if (score >= 4) { errEl.classList.remove('show'); document.getElementById('new_password').classList.remove('error'); }
        }

        function checkMatch() {
            const np = document.getElementById('new_password').value;
            const cp = document.getElementById('confirm_password').value;
            const errEl = document.getElementById('err_confirm');
            const input = document.getElementById('confirm_password');
            if (cp && np !== cp) {
                errEl.classList.add('show'); input.classList.add('error');
            } else {
                errEl.classList.remove('show'); input.classList.remove('error');
            }
        }

        document.getElementById('changePwForm').addEventListener('submit', function(e) {
            let valid = true;

            const cur = document.getElementById('current_password');
            const errCur = document.getElementById('err_current');
            if (!cur.value.trim()) {
                errCur.classList.add('show'); cur.classList.add('error'); valid = false;
            } else { errCur.classList.remove('show'); cur.classList.remove('error'); }

            const np = document.getElementById('new_password');
            const errNew = document.getElementById('err_new');
            const val = np.value;
            const strong = val.length >= 8 && /[A-Z]/.test(val) && /[0-9]/.test(val) && /[^A-Za-z0-9]/.test(val);
            if (!strong) {
                errNew.classList.add('show'); np.classList.add('error'); valid = false;
            } else if (val === cur.value) {
                errNew.textContent = 'New password must be different from your current password.';
                errNew.classList.add('show'); np.classList.add('error'); valid = false;
            } else { errNew.classList.remove('show'); np.classList.remove('error'); }

            const cp = document.getElementById('confirm_password');
            const errCp = document.getElementById('err_confirm');
            if (cp.value !== np.value) {
                errCp.classList.add('show'); cp.classList.add('error'); valid = false;
            } else { errCp.classList.remove('show'); cp.classList.remove('error'); }

            if (!valid) e.preventDefault();
        });
    </script>


</body>
</html>
