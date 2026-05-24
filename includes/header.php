<?php
/**
 * Header Include File
 * Included on every authenticated page
 * Contains navbar and common meta tags
 */

// Ensure config and functions are loaded
if (!defined('APP_NAME')) {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/functions.php';
}

// Get current user if logged in
// // 🔧 Avoid PHP built-in name collision (get_current_user()).
$current_user = is_logged_in() ? get_current_user_profile() : null;

// Resolve theme preference (light | dark) for the current user
$_app_theme = 'light';
if (is_logged_in()) {
    try {
        $__t = $pdo->prepare('SELECT theme FROM preferences WHERE user_id = ? LIMIT 1');
        $__t->execute([get_user_id()]);
        $__row = $__t->fetch();
        if ($__row && in_array($__row['theme'], ['light', 'dark'], true)) {
            $_app_theme = $__row['theme'];
        }
    } catch (PDOException $e) {
        // fallback to light
    }
}

// Determine active navigation link
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Map page names to nav links
$nav_map = [
    'index' => 'home',
    'dashboard' => 'dashboard',
    'nutrition' => 'nutrition',
    'workouts' => 'workouts',
    'progress' => 'progress',
    'reports' => 'reports',
    'notification' => 'notifications',
    'achievements' => 'achievements',
    'settings' => 'settings',
];

$active_nav = $nav_map[$current_page] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Smart Diet & Fitness Recommendation System - Your personal nutrition and fitness coach">
    <meta name="theme-color" content="#3d7bf4">
    <?php if (is_logged_in()): ?>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <title><?php echo htmlspecialchars($page_title ?? APP_NAME); ?></title>
    <!-- Dark mode applied before render to prevent flash -->
    <?php if ($_app_theme === 'dark'): ?>
    <style>:root{color-scheme:dark}</style>
    <?php endif; ?>

    <!-- Inter Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <!-- Inter Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Global Stylesheet -->
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/global.css">

    <!-- Common Styles (navbar, layout — variables defined in global.css) -->
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
            --accent-yellow: #fde047;
            --accent-orange: #f59e0b;
            --accent-red: #ef4444;
            --accent-green: #3b82f6;
            --success-green: #3b82f6;
            --warning-gold: #f59e0b;

            --btn-primary: #3b82f6;
            --btn-primary-hover: #2563eb;
        }

        /* ── Dark Mode ── */
        [data-theme="dark"] {
            --bg-body: #0f1117;
            --text-dark: #e2e8f0;
            --text-medium: #94a3b8;
            --text-light: #64748b;
            --primary-blue: #60a5fa;
            --primary-blue-hover: #93c5fd;
            --input-bg: #1e2535;
            --bg-right: #161b27;
            --border-light: #2d3748;
            --accent-yellow: #fde047;
            --accent-orange: #f59e0b;
            --accent-red: #f87171;
            --accent-green: #60a5fa;
            --success-green: #60a5fa;
            --warning-gold: #fbbf24;

            --btn-primary: #60a5fa;
            --btn-primary-hover: #3b82f6;
        }

        [data-theme="dark"] .navbar,
        [data-theme="dark"] .card,
        [data-theme="dark"] .side-card,
        [data-theme="dark"] .meal-modal,
        [data-theme="dark"] .workout-modal,
        [data-theme="dark"] .progress-modal,
        [data-theme="dark"] .confirm-modal,
        [data-theme="dark"] .notifications-card {
            background-color: var(--bg-right);
            color: var(--text-dark);
        }

        [data-theme="dark"] .navbar {
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
        }

        [data-theme="dark"] input,
        [data-theme="dark"] select,
        [data-theme="dark"] textarea {
            background-color: var(--input-bg) !important;
            color: var(--text-dark) !important;
            border-color: var(--border-light) !important;
        }

        [data-theme="dark"] .user-avatar {
            background-color: #2d3748;
            color: var(--primary-blue);
            border-color: var(--border-light);
        }

        [data-theme="dark"] .dropdown-content {
            background-color: var(--bg-right);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.5);
        }

        [data-theme="dark"] .toast {
            background-color: var(--bg-right);
            border-color: var(--border-light);
            color: var(--text-dark);
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
            overflow-x: hidden;
        }

        /* Animations */
        .fade-in {
            opacity: 0;
            transform: translateY(20px);
            animation: fadeIn 0.8s ease forwards;
        }

        @keyframes fadeIn {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Navbar */
        .navbar {
            background-color: var(--bg-right);
            padding: 1rem 3rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 20px rgba(27, 54, 121, 0.04);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 3rem;
        }

        .logo {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary-blue);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            transition: opacity 0.3s ease;
        }

        .logo:hover {
            opacity: 0.8;
        }

        .logo i {
            font-size: 1.5rem;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--text-medium);
            font-weight: 600;
            font-size: 0.95rem;
            transition: color 0.3s ease;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid transparent;
        }

        .nav-links a:hover,
        .nav-links a.active {
            color: var(--primary-blue);
            border-bottom-color: var(--primary-blue);
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .search-bar {
            position: relative;
            display: none;
        }

        .search-bar i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
        }

        .search-bar input {
            background-color: var(--input-bg);
            border: none;
            padding: 0.7rem 1rem 0.7rem 2.5rem;
            border-radius: 50px;
            font-size: 0.9rem;
            color: var(--text-dark);
            outline: none;
            width: 250px;
            transition: all 0.3s ease;
        }

        .search-bar input:focus {
            background-color: #fff;
            box-shadow: 0 0 0 2px var(--primary-blue);
        }

        /* Navbar icon buttons */
        .icon-btn {
            background: none;
            border: none;
            font-size: 1.2rem;
            color: var(--text-medium);
            cursor: pointer;
            transition: color 0.3s ease;
            position: relative;
        }
        .icon-btn:hover { color: var(--primary-blue); }

        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: var(--accent-red);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 700;
        }

        .user-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background-color: var(--accent-yellow);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #b45309;
            font-weight: 700;
            border: 2px solid var(--border-light);
            cursor: pointer;
            transition: border-color 0.3s ease;
            font-size: 0.9rem;
            text-decoration: none;
            overflow: hidden;
            flex-shrink: 0;
        }

        .user-avatar:hover {
            border-color: var(--primary-blue);
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            display: block;
            image-rendering: -webkit-optimize-contrast;
            image-rendering: crisp-edges;
        }

        /* User Dropdown Menu */
        .user-menu {
            position: relative;
            display: inline-block;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background-color: var(--bg-right);
            min-width: 200px;
            box-shadow: 0 8px 24px rgba(27, 54, 121, 0.12);
            border-radius: 12px;
            z-index: 1000;
            overflow: hidden;
        }

        .dropdown-content a,
        .dropdown-content button {
            color: var(--text-dark);
            padding: 0.75rem 1.5rem;
            text-decoration: none;
            display: block;
            width: 100%;
            text-align: left;
            border: none;
            background: none;
            cursor: pointer;
            transition: background-color 0.3s ease;
            font-size: 0.95rem;
        }

        .dropdown-content a:hover,
        .dropdown-content button:hover {
            background-color: var(--input-bg);
            color: var(--primary-blue);
        }

        .user-menu:hover .dropdown-content {
            display: block;
        }

        .dropdown-divider {
            height: 1px;
            background-color: var(--border-light);
            margin: 0.5rem 0;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .navbar {
                padding: 1rem;
                flex-wrap: wrap;
            }

            .nav-left {
                gap: 1.5rem;
            }

            .nav-links {
                gap: 1rem;
                font-size: 0.85rem;
            }

            .search-bar {
                display: none !important;
            }
        }
    </style>
</head>
<body data-theme="<?php echo htmlspecialchars($_app_theme, ENT_QUOTES, 'UTF-8'); ?>">
    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="nav-left">
            <a href="<?php echo APP_URL; ?>/pages/dashboard.php" class="logo">
                <i class="fas fa-leaf"></i>
                <span><?php echo htmlspecialchars(APP_NAME); ?></span>
            </a>
            
            <?php if (is_logged_in()): ?>
            <div class="nav-links">
                <a href="<?php echo APP_URL; ?>/pages/dashboard.php" class="<?php echo $active_nav === 'dashboard' ? 'active' : ''; ?>">Dashboard</a>
                <a href="<?php echo APP_URL; ?>/pages/nutrition.php" class="<?php echo $active_nav === 'nutrition' ? 'active' : ''; ?>">Nutrition</a>
                <a href="<?php echo APP_URL; ?>/pages/workouts.php" class="<?php echo $active_nav === 'workouts' ? 'active' : ''; ?>">Workouts</a>
                <a href="<?php echo APP_URL; ?>/pages/progress.php" class="<?php echo $active_nav === 'progress' ? 'active' : ''; ?>">Progress</a>
                <a href="<?php echo APP_URL; ?>/pages/reports.php" class="<?php echo $active_nav === 'reports' ? 'active' : ''; ?>">Reports</a>
            </div>
            <?php endif; ?>
        </div>

        <div class="nav-right">
            <?php if (is_logged_in()): ?>
                <!-- Theme Toggle -->
                <button class="icon-btn" id="themeToggleBtn" title="Toggle theme" aria-label="Toggle dark/light mode">
                    <i class="fas <?php echo $_app_theme === 'dark' ? 'fa-sun' : 'fa-moon'; ?>" id="themeToggleIcon"></i>
                </button>

                <!-- Notification Bell -->
                <button class="icon-btn" id="notification-btn" title="Notifications">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge" id="notif-count" style="display:none;">0</span>
                </button>

                <!-- User Avatar & Menu -->
                <div class="user-menu">
                    <div class="user-avatar" title="<?php echo htmlspecialchars(trim(($current_user['first_name'] ?? '') . ' ' . ($current_user['last_name'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php
                        $avatar_src = avatar_public_url($current_user['profile_picture'] ?? null);
                        if ($avatar_src !== null):
                            ?>
                            <img src="<?php echo htmlspecialchars($avatar_src, ENT_QUOTES, 'UTF-8'); ?>" alt="">
                            <?php
                        else:
                            $fn = (string)($current_user['first_name'] ?? '');
                            $ln = (string)($current_user['last_name'] ?? '');
                            $i1 = strtoupper($fn !== '' ? substr($fn, 0, 1) : 'U');
                            $i2 = strtoupper($ln !== '' ? substr($ln, 0, 1) : '');
                            echo htmlspecialchars($i1 . $i2, ENT_QUOTES, 'UTF-8');
                        endif;
                        ?>
                    </div>
                    <div class="dropdown-content">
                        <a href="<?php echo APP_URL; ?>/pages/settings.php">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                        <a href="<?php echo APP_URL; ?>/pages/achievements.php">
                            <i class="fas fa-trophy"></i> Achievements
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="<?php echo APP_URL; ?>/auth/logout.php" data-logout="true">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <a href="<?php echo APP_URL; ?>/auth/login.php" style="text-decoration:none; color:var(--text-medium); font-weight:600;">Login</a>
                <a href="<?php echo APP_URL; ?>/auth/signup.php" style="background: var(--btn-gradient); color:white; padding:0.7rem 1.5rem; border-radius:50px; text-decoration:none; font-weight:600;">Sign Up</a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Flash Message Display -->
    <?php 
    $flash = get_flash_message();
    if ($flash): 
        $bg_color = match($flash['type']) {
            'success' => '#d1fae5',
            'error' => '#fee2e2',
            'warning' => '#fef3c7',
            'info' => '#dbeafe',
            default => '#dbeafe'
        };
        $text_color = match($flash['type']) {
            'success' => '#1e40af',
            'error' => '#7f1d1d',
            'warning' => '#92400e',
            'info' => '#1e40af',
            default => '#1e40af'
        };
    ?>
    <div style="background-color:<?php echo $bg_color; ?>; color:<?php echo $text_color; ?>; padding:1rem 3rem; text-align:center; font-weight:500; animation: slideDown 0.3s ease;">
        <?php echo htmlspecialchars($flash['message']); ?>
    </div>
    <style>
        @keyframes slideDown {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
    </style>
    <?php endif; ?>

    <!-- Main Content Wrapper -->
    <main class="main-content">
