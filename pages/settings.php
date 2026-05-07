<?php
/**
 * Settings Page
 * User profile, preferences and toggles
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

$user_id = get_user_id();
$page_title = 'Settings - ' . APP_NAME;
$success_msg = '';
$error_msg = '';

// Handle Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    verify_csrf();
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $meal_reminders = isset($_POST['meal_reminders']) ? 1 : 0;
    $hydration_reminders = isset($_POST['hydration_reminders']) ? 1 : 0;
    $theme = in_array($_POST['theme'] ?? '', ['light', 'dark'], true) ? $_POST['theme'] : 'light';

    try {
        $pdo->beginTransaction();
        
        // Update profile
        $stmt = $pdo->prepare('UPDATE profiles SET first_name = ?, last_name = ? WHERE user_id = ?');
        $stmt->execute([$first_name, $last_name, $user_id]);

        // Update preferences
        $pref = $pdo->prepare('UPDATE preferences SET meal_reminders = ?, hydration_reminders = ?, theme = ? WHERE user_id = ?');
        $pref->execute([$meal_reminders, $hydration_reminders, $theme, $user_id]);

        // If no preference record matched (maybe missing), insert it
        if ($pref->rowCount() === 0 && !fetch_preferences($pdo, $user_id)) {
            $insert_pref = $pdo->prepare('INSERT INTO preferences (user_id, meal_reminders, hydration_reminders, theme) VALUES (?, ?, ?, ?)');
            $insert_pref->execute([$user_id, $meal_reminders, $hydration_reminders, $theme]);
        }

        $pdo->commit();
        $success_msg = 'Settings updated successfully.';
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_msg = 'Error updating settings. Please try again.';
    }
}

// Fetch user data
$stmt = $pdo->prepare('SELECT p.*, u.email FROM profiles p JOIN users u ON p.user_id = u.id WHERE p.user_id = ?');
$stmt->execute([$user_id]);
$profile = $stmt->fetch();
if ($profile === false) {
    $stmt = $pdo->prepare('SELECT u.email FROM users u WHERE u.id = ?');
    $stmt->execute([$user_id]);
    $user_row = $stmt->fetch();
    $profile = [
        'first_name' => '',
        'last_name' => '',
        'email' => $user_row['email'] ?? '',
    ];
}

// Fetch Preferences
function fetch_preferences($pdo, $user_id) {
    $stmt = $pdo->prepare('SELECT * FROM preferences WHERE user_id = ?');
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}
$preferences = fetch_preferences($pdo, $user_id);

$meal_reminders = $preferences['meal_reminders'] ?? 1;
$hydration_reminders = $preferences['hydration_reminders'] ?? 1;
$theme_pref = $preferences['theme'] ?? 'light';

?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<style>
    .delay-1 { transition-delay: 0.1s; }
    .delay-2 { transition-delay: 0.2s; }
    .delay-3 { transition-delay: 0.3s; }
    .delay-4 { transition-delay: 0.4s; }

    .settings-container {
        padding: 3rem; max-width: 1200px; margin: 0 auto; width: 100%; flex: 1; display: flex; gap: 3rem; align-items: flex-start;
    }

    .settings-sidebar { width: 250px; flex-shrink: 0; }
    .sidebar-header { margin-bottom: 2rem; }
    .sidebar-header h2 { font-size: 1.8rem; font-weight: 700; margin-bottom: 0.2rem; letter-spacing: -0.5px; }
    .sidebar-header p { font-size: 0.85rem; color: var(--text-medium); }

    .sidebar-menu { list-style: none; display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 3rem; }
    .sidebar-menu li a {
        display: flex; align-items: center; gap: 1rem; padding: 0.9rem 1.2rem; border-radius: 12px;
        text-decoration: none; color: var(--text-medium); font-weight: 600; font-size: 0.95rem; transition: all 0.3s ease;
    }
    .sidebar-menu li a i { font-size: 1.1rem; width: 20px; text-align: center; }
    .sidebar-menu li a:hover { color: var(--primary-blue); background-color: var(--input-bg); }
    .sidebar-menu li a.active { background-color: var(--bg-right); color: var(--primary-blue); box-shadow: 0 4px 15px rgba(27, 54, 121, 0.05); }

    .sidebar-footer-menu {
        list-style: none; display: flex; flex-direction: column; gap: 0.5rem; border-top: 1px dashed var(--border-light); padding-top: 1.5rem;
    }
    .btn-logout {
        display: flex; align-items: center; gap: 1rem; padding: 0.9rem 1.2rem; border-radius: 12px;
        border: 1px solid var(--border-light); background-color: transparent; color: #ef4444; font-weight: 600; font-size: 0.95rem; cursor: pointer; transition: all 0.3s ease; width: 100%;
    }
    .btn-logout:hover { background-color: #fee2e2; border-color: #ef4444; }
    .btn-delete {
        display: flex; align-items: center; gap: 1rem; padding: 0.9rem 1.2rem; border-radius: 12px; border: none; background-color: transparent; color: var(--text-light); font-weight: 600; font-size: 0.95rem; cursor: pointer; transition: all 0.3s ease; width: 100%;
    }
    .btn-delete:hover { color: #ef4444; }

    /* Main Content Area */
    .settings-content {
        flex: 1; background-color: var(--bg-right); border-radius: 24px; padding: 3rem; box-shadow: 0 10px 30px rgba(27, 54, 121, 0.04);
    }
    .section-title { font-size: 1.25rem; font-weight: 700; color: var(--text-dark); margin-bottom: 1.5rem; }

    .input-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2.5rem; }
    .input-group { display: flex; flex-direction: column; gap: 0.5rem; }
    .input-group label { font-size: 0.75rem; font-weight: 700; color: var(--text-medium); text-transform: uppercase; letter-spacing: 0.5px; }
    .input-wrapper { position: relative; }
    .input-wrapper i { position: absolute; left: 1.2rem; top: 50%; transform: translateY(-50%); color: var(--primary-blue); font-size: 1.1rem; }
    .input-wrapper input {
        width: 100%; padding: 1rem 1rem 1rem 3.2rem; border: 2px solid transparent; border-radius: 12px; background-color: var(--input-bg);
        font-size: 0.95rem; font-weight: 500; color: var(--text-dark); outline: none; transition: all 0.3s ease;
    }
    .input-wrapper input:focus { border-color: var(--primary-blue); background-color: #fff; box-shadow: 0 4px 12px rgba(61, 123, 244, 0.1); }

    .preference-row {
        display: flex; align-items: center; justify-content: space-between; padding: 1.2rem 1.5rem; background-color: var(--input-bg); border-radius: 16px; margin-bottom: 1rem;
    }
    .pref-info { display: flex; align-items: center; gap: 1rem; }
    .pref-icon {
        width: 40px; height: 40px; border-radius: 10px; background-color: var(--bg-right); color: var(--primary-blue); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; box-shadow: 0 4px 10px rgba(27, 54, 121, 0.05);
    }
    .pref-text h4 { font-size: 0.95rem; font-weight: 600; color: var(--text-dark); margin-bottom: 0.1rem; }
    .pref-text p { font-size: 0.8rem; color: var(--text-medium); }

    /* Custom Toggle */
    .toggle-switch { position: relative; display: inline-block; width: 46px; height: 26px; }
    .toggle-switch input { opacity: 0; width: 0; height: 0; }
    .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--text-light); transition: .4s; border-radius: 34px; }
    .slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
    input:checked + .slider { background-color: var(--primary-blue); }
    input:checked + .slider:before { transform: translateX(20px); }

    .theme-selector {
        display: flex; background-color: var(--input-bg); padding: 0.5rem; border-radius: 12px; gap: 0.5rem; width: fit-content; margin-bottom: 3rem;
    }
    .theme-btn {
        display: flex; align-items: center; gap: 0.5rem; padding: 0.8rem 1.5rem; border: none; background: transparent; color: var(--text-medium); font-size: 0.9rem; font-weight: 600; border-radius: 8px; cursor: pointer; transition: all 0.3s ease;
    }
    .theme-btn:hover { color: var(--text-dark); }
    .theme-btn.active { background-color: var(--bg-right); color: var(--primary-blue); box-shadow: 0 4px 10px rgba(27, 54, 121, 0.05); }

    .settings-footer { display: flex; justify-content: flex-end; padding-top: 2rem; border-top: 1px solid var(--border-light); }
    .btn-save {
        padding: 1rem 2rem; border: none; background: var(--btn-gradient); color: white; font-size: 0.95rem; font-weight: 600;
        cursor: pointer; border-radius: 50px; box-shadow: 0 8px 20px rgba(61, 123, 244, 0.3); transition: all 0.4s cubic-bezier(0.25, 1, 0.5, 1);
    }
    .btn-save:hover {
        transform: translateY(-2px);
        border-radius: 12px;
        background: var(--btn-gradient-hover);
        box-shadow: 0 12px 25px rgba(61, 123, 244, 0.4);
    }

    .alert { padding: 1rem; border-radius: 8px; margin-bottom: 2rem; font-weight: 500; }
    .alert-success { background-color: #d1fae5; color: #065f46; border: 1px solid #10b981; }
    .alert-danger { background-color: #fee2e2; color: #991b1b; border: 1px solid #ef4444; }

    @media (max-width: 900px) {
        .settings-container { flex-direction: column; } .settings-sidebar { width: 100%; } .sidebar-menu { flex-direction: row; flex-wrap: wrap; margin-bottom: 1.5rem; } .sidebar-menu li { flex: 1; min-width: 140px; } .sidebar-header { text-align: center; } .input-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="settings-container">
    
    <aside class="settings-sidebar fade-in delay-1">
        <div class="sidebar-header">
            <h2>Settings</h2>
            <p>Manage your account</p>
        </div>

        <ul class="sidebar-menu">
            <li><a href="settings.php" class="active"><i class="fa-regular fa-user"></i> Profile</a></li>
            <li><a href="<?php echo htmlspecialchars(APP_URL . '/pages/security.php', ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-shield-halved"></i> Privacy & Security</a></li>
            <li><a href="notification.php"><i class="fa-regular fa-bell"></i> Notifications</a></li>
        </ul>

        <ul class="sidebar-footer-menu">
            <li>
                <button class="btn-logout" type="button" data-logout="true">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i> Log Out
                </button>
            </li>
            <li>
                <button class="btn-delete" onclick="showToast('Account deletion requires admin contact.', 'error')">
                    <i class="fa-regular fa-trash-can"></i> Delete Account
                </button>
            </li>
        </ul>
    </aside>

    <section class="settings-content fade-in delay-2">
        <?php if ($success_msg): ?>
            <div class="alert alert-success"><?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
            <div class="alert alert-danger"><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="update_settings" value="1">
            
            <h3 class="section-title">Profile Settings</h3>
            <div class="input-grid">
                <div class="input-group">
                    <label>First Name</label>
                    <div class="input-wrapper">
                        <i class="fa-regular fa-user"></i>
                        <input type="text" name="first_name" value="<?php echo htmlspecialchars($profile['first_name'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="input-group">
                    <label>Last Name</label>
                    <div class="input-wrapper">
                        <i class="fa-regular fa-user"></i>
                        <input type="text" name="last_name" value="<?php echo htmlspecialchars($profile['last_name'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="input-group" style="grid-column: 1 / -1;">
                    <label>Email Address</label>
                    <div class="input-wrapper">
                        <i class="fa-regular fa-envelope"></i>
                        <input type="email" value="<?php echo htmlspecialchars($profile['email'] ?? ''); ?>" readonly style="color: var(--text-medium); background-color: var(--border-light); cursor: not-allowed;">
                    </div>
                </div>
            </div>

            <h3 class="section-title" style="margin-top: 1rem;">Preferences & Reminders</h3>
            
            <div class="preference-row">
                <div class="pref-info">
                    <div class="pref-icon"><i class="fa-solid fa-utensils"></i></div>
                    <div class="pref-text">
                        <h4>Meal Reminders</h4>
                        <p>Receive push notifications for meals.</p>
                    </div>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="meal_reminders" <?php echo $meal_reminders ? 'checked' : ''; ?>>
                    <span class="slider"></span>
                </label>
            </div>

            <div class="preference-row">
                <div class="pref-info">
                    <div class="pref-icon"><i class="fa-solid fa-droplet"></i></div>
                    <div class="pref-text">
                        <h4>Hydration Alerts</h4>
                        <p>Get reminded every 2 hours to drink water.</p>
                    </div>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="hydration_reminders" <?php echo $hydration_reminders ? 'checked' : ''; ?>>
                    <span class="slider"></span>
                </label>
            </div>

            <h3 class="section-title" style="margin-top: 2.5rem;">Theme Selection</h3>
            <input type="hidden" name="theme" id="themeInput" value="<?php echo htmlspecialchars($theme_pref); ?>">
            <div class="theme-selector">
                <button type="button" class="theme-btn <?php echo $theme_pref === 'light' ? 'active' : ''; ?>" data-theme="light">
                    <i class="fa-regular fa-sun"></i> Light
                </button>
                <button type="button" class="theme-btn <?php echo $theme_pref === 'dark' ? 'active' : ''; ?>" data-theme="dark">
                    <i class="fa-regular fa-moon"></i> Dark
                </button>
            </div>

            <div class="settings-footer">
                <button type="submit" class="btn-save">Save Changes</button>
            </div>
        </form>
    </section>

</div>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        setTimeout(() => {
            const elements = document.querySelectorAll('.fade-in');
            elements.forEach(el => el.classList.add('visible'));
        }, 100);

        const themeBtns = document.querySelectorAll('.theme-btn');
        const themeInput = document.getElementById('themeInput');
        
        themeBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                themeBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                themeInput.value = btn.getAttribute('data-theme');
            });
        });
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>