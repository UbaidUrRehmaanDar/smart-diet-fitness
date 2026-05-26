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
    $first_name = sanitize_plain_text($_POST['first_name'] ?? '');
    $last_name = sanitize_plain_text($_POST['last_name'] ?? '');
    
    // 🔧 Added physical metrics & goals
    $height_cm = (float) ($_POST['height_cm'] ?? 0);
    $current_weight_kg = (float) ($_POST['current_weight_kg'] ?? 0);
    $target_weight_kg = (float) ($_POST['target_weight_kg'] ?? 0);
    
    $valid_activity = ['sedentary', 'lightly_active', 'moderately_active', 'very_active', 'extremely_active'];
    $activity_level = in_array($_POST['activity_level'] ?? '', $valid_activity) ? $_POST['activity_level'] : 'sedentary';
    
    $valid_goals = ['weight_loss', 'muscle_gain', 'maintenance'];
    $fitness_goal = in_array($_POST['fitness_goal'] ?? '', $valid_goals) ? $_POST['fitness_goal'] : 'maintenance';

    $meal_reminders = isset($_POST['meal_reminders']) ? 1 : 0;
    $hydration_reminders = isset($_POST['hydration_reminders']) ? 1 : 0;
    $theme = in_array($_POST['theme'] ?? '', ['light', 'dark'], true) ? $_POST['theme'] : 'light';

    $existing_pic = null;
    if (db_table_has_column($pdo, 'profiles', 'profile_picture')) {
        $pch = $pdo->prepare('SELECT profile_picture FROM profiles WHERE user_id = ? LIMIT 1');
        $pch->execute([$user_id]);
        $existing_pic = $pch->fetchColumn();
        $existing_pic = $existing_pic !== false ? (string) $existing_pic : null;
        if ($existing_pic === '') {
            $existing_pic = null;
        }
    }

    $avatar_basename = $existing_pic;
    $pending_remove_avatar_basename = null;
    $upload_err_msg = null;
    if (db_table_has_column($pdo, 'profiles', 'profile_picture')) {
        if (!empty($_POST['remove_avatar'])) {
            $avatar_basename = null;
            if ($existing_pic !== null) {
                $pending_remove_avatar_basename = $existing_pic;
            }
        } elseif (!empty($_FILES['profile_picture']['tmp_name'])) {
            $pic_err = null;
            $new_bn = process_profile_avatar_upload($user_id, $existing_pic, $_FILES['profile_picture'], $pic_err);
            if ($pic_err !== null) {
                $upload_err_msg = $pic_err;
            } elseif ($new_bn !== null) {
                $avatar_basename = $new_bn;
            }
        }
    }

    if ($upload_err_msg !== null) {
        $error_msg = $upload_err_msg;
    } else {
        try {
            $pdo->beginTransaction();

            ensure_profile_row_exists($pdo, $user_id);
            ensure_preferences_row($pdo, $user_id);

            // 🔧 Update queries now include onboarding data fields
            if (db_table_has_column($pdo, 'profiles', 'profile_picture')) {
                $stmt = $pdo->prepare(
                    'UPDATE profiles SET first_name = ?, last_name = ?, profile_picture = ?, height_cm = ?, current_weight_kg = ?, target_weight_kg = ?, activity_level = ?, fitness_goal = ? WHERE user_id = ?'
                );
                $stmt->execute([$first_name, $last_name, $avatar_basename, $height_cm, $current_weight_kg, $target_weight_kg, $activity_level, $fitness_goal, $user_id]);
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE profiles SET first_name = ?, last_name = ?, height_cm = ?, current_weight_kg = ?, target_weight_kg = ?, activity_level = ?, fitness_goal = ? WHERE user_id = ?'
                );
                $stmt->execute([$first_name, $last_name, $height_cm, $current_weight_kg, $target_weight_kg, $activity_level, $fitness_goal, $user_id]);
            }

            $pref = $pdo->prepare(
                'UPDATE preferences SET meal_reminders = ?, hydration_reminders = ?, theme = ? WHERE user_id = ?'
            );
            $pref->execute([$meal_reminders, $hydration_reminders, $theme, $user_id]);

            if ($pref->rowCount() === 0) {
                $peek = fetch_preferences($pdo, $user_id);
                if (!$peek) {
                    $insert_pref = $pdo->prepare(
                        'INSERT INTO preferences (user_id, meal_reminders, hydration_reminders, theme) VALUES (?, ?, ?, ?)'
                    );
                    $insert_pref->execute([$user_id, $meal_reminders, $hydration_reminders, $theme]);
                }
            }

            $pdo->commit();
            if ($pending_remove_avatar_basename) {
                $dir = defined('AVATAR_UPLOAD_DIR') ? AVATAR_UPLOAD_DIR : dirname(__DIR__) . '/public/uploads/avatars';
                $old_path = $dir . DIRECTORY_SEPARATOR . basename($pending_remove_avatar_basename);
                if (
                    is_file($old_path)
                    && str_starts_with(basename($old_path), 'u' . $user_id . '_')
                ) {
                    @unlink($old_path);
                }
            }
            $_SESSION['user_name'] = trim($first_name . ' ' . $last_name) !== ''
                ? trim($first_name . ' ' . $last_name)
                : ($_SESSION['user_name'] ?? 'Member');
            $success_msg = 'Settings updated successfully.';
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Settings save: ' . $e->getMessage());
            $error_msg = 'Error updating settings. Please try again.';
        }
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
        // 🔧 Default fallback values for new physical/goal columns
        'height_cm' => '',
        'current_weight_kg' => '',
        'target_weight_kg' => '',
        'activity_level' => 'sedentary',
        'fitness_goal' => 'maintenance'
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

$avatar_preview_url = null;
if (db_table_has_column($pdo, 'profiles', 'profile_picture')
    && !empty($profile['profile_picture'])) {
    $avatar_preview_url = avatar_public_url($profile['profile_picture']);
}

?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<style>
    .delay-1 { transition-delay: 0.1s; }
    .delay-2 { transition-delay: 0.2s; }
    .delay-3 { transition-delay: 0.3s; }
    .delay-4 { transition-delay: 0.4s; }

    .settings-container {
        padding: clamp(1rem, 4vw, 3rem); max-width: 1200px; margin: 0 auto; width: 100%; flex: 1; display: flex; gap: 3rem; align-items: flex-start;
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
        display: flex; align-items: center; gap: 1rem; padding: 0.85rem 1.2rem; border-radius: 50px;
        border: 2px solid var(--border-light); background-color: transparent; color: #ef4444; font-weight: 600; font-size: 0.95rem; cursor: pointer;
        transition: background 0.3s ease, border-radius 0.3s ease, border-color 0.3s ease; width: 100%;
    }
    .btn-logout:hover { background-color: #fee2e2; border-color: #ef4444; border-radius: 12px; }
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
        padding: 0.85rem 1.8rem;
        border: none;
        background: var(--btn-primary);
        color: white;
        font-size: 0.95rem;
        font-weight: 600;
        cursor: pointer;
        border-radius: 50px;
        transition: background 0.3s ease, border-radius 0.3s ease;
    }
    .btn-save:hover {
        background: var(--btn-primary-hover);
        border-radius: 12px;
    }

    .alert { padding: 1rem; border-radius: 8px; margin-bottom: 2rem; font-weight: 500; }
    .alert-success { background-color: #dbeafe; color: #1e40af; border: 1px solid #3b82f6; }
    .alert-danger { background-color: #fee2e2; color: #991b1b; border: 1px solid #ef4444; }

    @media (max-width: 900px) {
        .settings-container { flex-direction: column; padding: 1rem; }
        .settings-sidebar { width: 100%; }
        .sidebar-menu { flex-direction: row; flex-wrap: wrap; margin-bottom: 1.5rem; }
        .sidebar-menu li { flex: 1; min-width: 120px; }
        .sidebar-header { text-align: center; }
        .input-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 600px) {
        .settings-content { padding: 1.25rem; }
        .sidebar-menu li a { padding: 0.7rem 0.8rem; font-size: 0.85rem; }
        .preference-row { flex-wrap: wrap; gap: 0.75rem; }
        .theme-selector { width: 100%; }
        .theme-btn { flex: 1; justify-content: center; }
    }

    .avatar-setting-row {
        display: flex; align-items: center; gap: 1.25rem; flex-wrap: wrap;
        padding: 1rem; background-color: var(--input-bg); border-radius: 12px;
    }
    .avatar-preview-wrap {
        width: 88px; height: 88px; border-radius: 50%; overflow: hidden;
        flex-shrink: 0; background: var(--border-light); border: 3px solid var(--border-light);
    }
    .avatar-preview-wrap img {
        width: 100%; height: 100%; object-fit: cover; display: block;
        image-rendering: -webkit-optimize-contrast;
        image-rendering: crisp-edges;
    }
    .avatar-preview-placeholder {
        width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;
        color: var(--text-medium); font-weight: 700; font-size: 1.5rem;
    }
    .avatar-controls { display: flex; flex-direction: column; gap: 0.5rem; align-items: flex-start; font-size: 0.875rem; color: var(--text-medium); }
    .avatar-remove-row { display: flex; align-items: center; gap: 0.5rem; }

    #removeAvatarBtn:hover {
        background: #dc2626 !important;
        border-radius: 12px !important;
    }
    #removeAvatarBtn:active {
        background: #b91c1c !important;
        transform: scale(0.97);
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
        </ul>
    </aside>

    <section class="settings-content fade-in delay-2">
        <?php if ($success_msg): ?>
            <div class="alert alert-success"><?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
            <div class="alert alert-danger"><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="update_settings" value="1">

            <!-- Save Changes at the top -->
            <div style="display:flex;justify-content:flex-end;margin-bottom:2rem;">
                <button type="submit" class="btn-save">Save Changes</button>
            </div>
            
            <h3 class="section-title">Profile Settings</h3>
            <?php if (db_table_has_column($pdo, 'profiles', 'profile_picture')): ?>
                <div class="input-grid" style="margin-bottom: 1.25rem;">
                    <div class="input-group" style="grid-column: 1 / -1;">
                        <label>Profile photo</label>
                        <div class="avatar-setting-row">
                            <div class="avatar-preview-wrap">
                                <?php if ($avatar_preview_url): ?>
                                    <img src="<?php echo htmlspecialchars($avatar_preview_url, ENT_QUOTES, 'UTF-8'); ?>" alt="">
                                <?php else: ?>
                                    <div class="avatar-preview-placeholder">
                                        <?php
                                        $fn0 = (string)($profile['first_name'] ?? '');
                                        echo htmlspecialchars(strtoupper($fn0 !== '' ? substr($fn0, 0, 1) : 'U'), ENT_QUOTES, 'UTF-8');
                                        ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="avatar-controls">
                                <label class="btn-choose-file">
                                    <i class="fa-solid fa-camera"></i> Choose Photo
                                    <input type="file" name="profile_picture" id="profilePicInput" accept="image/jpeg,image/png,image/webp" style="display: none;">
                                </label>
                                <span id="file-info-text" style="display: block; font-size: 0.75rem; color: var(--text-medium); margin-top: 0.25rem;">JPEG or PNG or WebP, max 2 MB.</span>
                                <?php if ($avatar_preview_url): ?>
                                    <button type="button" id="removeAvatarBtn"
                                        id="removeAvatarBtn"
                                        style="margin-top:0.25rem;background:#ef4444;border:none;color:#fff;border-radius:50px;padding:0.45rem 1.1rem;font-size:0.82rem;font-weight:600;cursor:pointer;transition:all 0.25s ease;display:inline-flex;align-items:center;gap:0.4rem;">
                                        <i class="fa-solid fa-trash-can"></i> Remove photo
                                    </button>
                                    <input type="hidden" name="remove_avatar" id="removeAvatarInput" value="">
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
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

            <!-- 🔧 Added Physical Metrics fields -->
            <h3 class="section-title" style="margin-top: 1rem;">Physical Metrics</h3>
            <div class="input-grid">
                <div class="input-group">
                    <label>Height (cm)</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-ruler-vertical"></i>
                        <input type="number" step="0.01" name="height_cm" value="<?php echo htmlspecialchars($profile['height_cm'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="input-group">
                    <label>Current Weight (kg)</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-weight-scale"></i>
                        <input type="number" step="0.01" name="current_weight_kg" value="<?php echo htmlspecialchars($profile['current_weight_kg'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="input-group">
                    <label>Target Weight (kg)</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-bullseye"></i>
                        <input type="number" step="0.01" name="target_weight_kg" value="<?php echo htmlspecialchars($profile['target_weight_kg'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- 🔧 Added Fitness Goals fields -->
            <h3 class="section-title" style="margin-top: 1rem;">Fitness Goals</h3>
            <div class="input-grid">
                <div class="input-group">
                    <label>Activity Level</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-person-running"></i>
                        <select name="activity_level" style="width: 100%; padding: 1rem 1rem 1rem 3.2rem; border: 2px solid transparent; border-radius: 12px; background-color: var(--input-bg); font-size: 0.95rem; font-weight: 500; color: var(--text-dark); outline: none; appearance: none; cursor: pointer;">
                            <option value="sedentary" <?php echo ($profile['activity_level'] ?? '') === 'sedentary' ? 'selected' : ''; ?>>Sedentary (Little to no exercise)</option>
                            <option value="lightly_active" <?php echo ($profile['activity_level'] ?? '') === 'lightly_active' ? 'selected' : ''; ?>>Lightly Active (1-3 days/week)</option>
                            <option value="moderately_active" <?php echo ($profile['activity_level'] ?? '') === 'moderately_active' ? 'selected' : ''; ?>>Moderately Active (3-5 days/week)</option>
                            <option value="very_active" <?php echo ($profile['activity_level'] ?? '') === 'very_active' ? 'selected' : ''; ?>>Very Active (6-7 days/week)</option>
                            <option value="extremely_active" <?php echo ($profile['activity_level'] ?? '') === 'extremely_active' ? 'selected' : ''; ?>>Extremely Active (Twice daily)</option>
                        </select>
                    </div>
                </div>
                <div class="input-group">
                    <label>Primary Goal</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-flag-checkered"></i>
                        <select name="fitness_goal" style="width: 100%; padding: 1rem 1rem 1rem 3.2rem; border: 2px solid transparent; border-radius: 12px; background-color: var(--input-bg); font-size: 0.95rem; font-weight: 500; color: var(--text-dark); outline: none; appearance: none; cursor: pointer;">
                            <option value="weight_loss" <?php echo ($profile['fitness_goal'] ?? '') === 'weight_loss' ? 'selected' : ''; ?>>Weight Loss</option>
                            <option value="maintenance" <?php echo ($profile['fitness_goal'] ?? '') === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                            <option value="muscle_gain" <?php echo ($profile['fitness_goal'] ?? '') === 'muscle_gain' ? 'selected' : ''; ?>>Muscle Gain</option>
                        </select>
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

        // ── Live avatar preview before save ───────────────────────────────
        const picInput = document.getElementById('profilePicInput');
        if (picInput) {
            picInput.addEventListener('change', function() {
                const file = this.files[0];
                if (!file) return;
                const infoText = document.getElementById('file-info-text');
                if (infoText) { infoText.textContent = file.name + ' selected.'; infoText.style.color = 'var(--primary-blue)'; }
                const reader = new FileReader();
                reader.onload = function(e) {
                    const wrap = document.querySelector('.avatar-preview-wrap');
                    if (!wrap) return;
                    wrap.innerHTML = `<img src="${e.target.result}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;">`;
                };
                reader.readAsDataURL(file);
            });
        }

        // ── Remove photo button — wired via addEventListener, NOT onclick ──
        const removeBtn = document.getElementById('removeAvatarBtn');
        const removeInput = document.getElementById('removeAvatarInput');
        if (removeBtn && removeInput) {
            removeBtn.addEventListener('click', function() {
                const wrap = document.querySelector('.avatar-preview-wrap');
                if (removeInput.value === '1') {
                    // Undo
                    removeInput.value = '';
                    removeBtn.innerHTML = '<i class="fa-solid fa-trash-can"></i> Remove photo';
                    removeBtn.style.background = '#ef4444';
                    removeBtn.style.borderRadius = '50px';
                    if (wrap) wrap.style.opacity = '1';
                } else {
                    // Confirm removal — tick icon + darker red
                    removeInput.value = '1';
                    removeBtn.innerHTML = '<i class="fa-solid fa-check"></i> Marked for removal';
                    removeBtn.style.background = '#b91c1c';
                    removeBtn.style.borderRadius = '50px';
                    if (wrap) wrap.style.opacity = '0.25';
                }
            });
        }

        // ── Live dark mode preview ─────────────────────────────────────────
        const themeBtns = document.querySelectorAll('.theme-btn');
        const themeInput = document.getElementById('themeInput');

        themeBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                themeBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                const chosen = btn.getAttribute('data-theme');
                themeInput.value = chosen;
                // Apply preview immediately — reverts if user doesn't save
                document.body.setAttribute('data-theme', chosen);
            });
        });

        // ── Logout handler (also handled by footer, but settings has its own button) ──
        document.querySelectorAll('[data-logout="true"]').forEach(el => {
            el.addEventListener('click', e => {
                e.preventDefault();
                const modal = document.getElementById('logoutModal');
                if (modal) modal.classList.add('active');
            });
        });
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>