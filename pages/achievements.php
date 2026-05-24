<?php
/**
 * Achievements / Profile Page
 * Displays user profile, stats, dynamic milestones, and recent activity
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

$user_id = get_user_id();
$page_title = 'Achievements - ' . APP_NAME;

function table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?');
    $stmt->execute([DB_NAME, $table]);
    return (int)$stmt->fetchColumn() > 0;
}

// Fetch user profile data
try {
    $stmt = $pdo->prepare('SELECT p.*, u.email FROM profiles p JOIN users u ON p.user_id = u.id WHERE p.user_id = ?');
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch() ?: [];
} catch (PDOException $e) {
    error_log('Achievements profile fetch error: ' . $e->getMessage());
    $profile = [];
}

// Format Member Since
$member_since = date('F Y', strtotime($profile['created_at'] ?? 'now'));
$first_name = $profile['first_name'] ?? '';
$last_name = $profile['last_name'] ?? '';
$full_name = trim($first_name . ' ' . $last_name) ?: 'Member';

// Calculate Age
$age = 'N/A';
if (!empty($profile['date_of_birth'])) {
    $dob = new DateTime($profile['date_of_birth']);
    $now = new DateTime();
    $age = $now->diff($dob)->y;
}

$height_cm = isset($profile['height_cm']) ? floatval($profile['height_cm']) : 0;
$current_weight = isset($profile['current_weight_kg']) ? floatval($profile['current_weight_kg']) : 0;
$fitness_goal = $profile['fitness_goal'] ?? '';
$fitness_goal_label = $fitness_goal !== '' ? str_replace('_', ' ', $fitness_goal) : 'Not set';

// Calculate streaks or basic milestones (mocked based on diet/workout history)
// E.g., Total workouts
$w_count = 0;
if (table_exists($pdo, 'workout_logs')) {
    try {
        $w_stmt = $pdo->prepare('SELECT COUNT(*) as c FROM workout_logs WHERE user_id = ?');
        $w_stmt->execute([$user_id]);
        $w_count = (int)($w_stmt->fetch()['c'] ?? 0);
    } catch (PDOException $e) {
        error_log('Achievements workout count error: ' . $e->getMessage());
    }
}

// Tier logic
$tier = 'Bronze Tier';
if ($w_count > 10) $tier = 'Silver Tier';
if ($w_count > 50) $tier = 'Gold Tier';

// Fetch unlocked achievements
$unlocked_badges = [];
try {
    $stmt = $pdo->prepare('SELECT badge_name FROM achievements WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $rows = $stmt->fetchAll();
    $names = array_map(function ($row) {
        return $row['badge_name'];
    }, $rows);
    $unlocked_badges = array_fill_keys($names, true);
} catch (PDOException $e) {
    error_log('Achievements badge fetch error: ' . $e->getMessage());
}

$milestones = [
    [
        'badge' => 'First Workout',
        'name' => 'First Workout',
        'icon' => 'fa-shoe-prints',
    ],
    [
        'badge' => 'Workout Warrior',
        'name' => '10 Workouts',
        'icon' => 'fa-dumbbell',
    ],
    [
        'badge' => 'Workout Pro',
        'name' => '50 Workouts',
        'icon' => 'fa-medal',
    ],
    [
        'badge' => 'Iron Will',
        'name' => '100 Workouts',
        'icon' => 'fa-trophy',
    ],
    [
        'badge' => 'First Meal Logged',
        'name' => 'First Meal',
        'icon' => 'fa-utensils',
    ],
    [
        'badge' => 'Meal Tracker',
        'name' => '25 Meals',
        'icon' => 'fa-clipboard-check',
    ],
    [
        'badge' => 'Meal Master',
        'name' => '100 Meals',
        'icon' => 'fa-star',
    ],
    [
        'badge' => 'Hydration Hero',
        'name' => '2L Hydration',
        'icon' => 'fa-droplet',
    ],
    [
        'badge' => 'First Check-in',
        'name' => 'First Check-in',
        'icon' => 'fa-chart-line',
    ],
    [
        'badge' => 'Progress Tracker',
        'name' => '10 Check-ins',
        'icon' => 'fa-check-double',
    ],
];

// Build a Recent Activity feed (last 10 items combining workouts and diet logs)
$activities = [];

if (table_exists($pdo, 'workout_logs')) {
    try {
        $w_list = $pdo->prepare('SELECT exercise_name as title, duration_mins, kcal_burned, logged_at FROM workout_logs WHERE user_id = ? ORDER BY logged_at DESC LIMIT 5');
        $w_list->execute([$user_id]);
        foreach ($w_list->fetchAll() as $w) {
            $activities[] = [
                'type' => 'workout',
                'title' => 'Completed ' . $w['title'],
                'desc' => 'Duration: ' . $w['duration_mins'] . ' mins • Calories burned: ' . $w['kcal_burned'],
                'time' => $w['logged_at'],
                'icon' => '<i class="fa-solid fa-dumbbell"></i>'
            ];
        }
    } catch (PDOException $e) {
        error_log('Achievements workouts fetch error: ' . $e->getMessage());
    }
}

if (table_exists($pdo, 'diet_logs')) {
    try {
        $d_list = $pdo->prepare('SELECT meal_type, kcal, protein_g, logged_at FROM diet_logs WHERE user_id = ? ORDER BY logged_at DESC LIMIT 5');
        $d_list->execute([$user_id]);
        foreach ($d_list->fetchAll() as $d) {
            $activities[] = [
                'type' => 'diet',
                'title' => 'Logged ' . ucfirst($d['meal_type']),
                'desc' => 'Consumed ' . $d['kcal'] . ' kcal • Protein: ' . rtrim(rtrim(number_format($d['protein_g'], 1), '0'), '.') . 'g',
                'time' => $d['logged_at'],
                'icon' => '<i class="fa-solid fa-utensils"></i>'
            ];
        }
    } catch (PDOException $e) {
        error_log('Achievements diet fetch error: ' . $e->getMessage());
    }
}

// Sort activities descending
usort($activities, function($a, $b) {
    return strtotime($b['time']) - strtotime($a['time']);
});

?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<style>
    .delay-1 { transition-delay: 0.1s; }
    .delay-2 { transition-delay: 0.2s; }
    .delay-3 { transition-delay: 0.3s; }
    .delay-4 { transition-delay: 0.4s; }

    main.main-content {
        padding: 3rem;
        box-sizing: border-box;
    }

    .achievements-content {
        max-width: 1400px;
        margin: 0 auto;
        width: 100%;
        display: grid;
        grid-template-columns: minmax(0, 320px) minmax(0, 1fr);
        gap: 2.5rem;
        align-items: start;
        flex: 1;
    }

    .right-column,
    .profile-card {
        min-width: 0;
    }

    .card {
        background-color: var(--bg-right);
        border-radius: 24px;
        padding: 2.5rem;
        box-shadow: 0 10px 30px rgba(27, 54, 121, 0.04);
        display: flex;
        flex-direction: column;
    }

    <!-- Profile Column -->
    .profile-card { text-align: center; }
    .profile-avatar-wrapper {
        position: relative; width: 140px; height: 140px; margin: 0 auto 1.5rem auto;
        border-radius: 50%; padding: 6px; background: linear-gradient(135deg, var(--border-light), var(--input-bg));
    }
    .profile-avatar {
        width: 100%; height: 100%; border-radius: 50%;
        background-size: cover; background-position: center; border: 4px solid var(--bg-right);
        background-color: var(--accent-yellow);
        display: flex; align-items: center; justify-content: center;
        font-size: 2.5rem; font-weight: 700; color: #92400e;
        overflow: hidden;
    }
    .profile-avatar img {
        width: 100%; height: 100%; object-fit: cover; border-radius: 50%; display: block;
        image-rendering: -webkit-optimize-contrast;
        image-rendering: crisp-edges;
    }
    .profile-name { font-size: 1.6rem; font-weight: 700; color: var(--text-dark); margin-bottom: 0.4rem; }
    .profile-tier { font-size: 0.9rem; color: var(--text-medium); margin-bottom: 2rem; font-weight: 500; }
    .profile-tier strong { color: var(--primary-blue); font-weight: 600; }

    .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 2rem; }
    .stat-box { background-color: var(--input-bg); padding: 1.2rem 1rem; border-radius: 16px; text-align: center; }
    .stat-box span { display: block; font-size: 0.7rem; font-weight: 700; color: var(--text-medium); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.4rem; }
    .stat-box strong { display: block; font-size: 1.2rem; font-weight: 700; color: var(--text-dark); }

    .btn-edit-profile {
        width: 100%; padding: 1rem; background: var(--btn-primary); color: #ffffff;
        border: none; border-radius: 50px; font-size: 0.95rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease;
    }
    .btn-edit-profile:hover {
        background: var(--btn-primary-hover);
        border-radius: 12px;
    }

    /* Right Column */
    .right-column { display: flex; flex-direction: column; gap: 2rem; }
    .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
    .section-title { font-size: 1.25rem; font-weight: 700; color: var(--text-dark); }

    /* Milestones */
    .milestones-container {
        display: flex; gap: 2.5rem; overflow-x: auto; padding-bottom: 1rem; -ms-overflow-style: none; scrollbar-width: none;
    }
    .milestones-container::-webkit-scrollbar { display: none; }
    .milestone-item { display: flex; flex-direction: column; align-items: center; gap: 0.8rem; min-width: 80px; }
    .milestone-icon {
        width: 70px; height: 70px; border-radius: 50%; display: flex; align-items: center;
        justify-content: center; font-size: 1.8rem; transition: transform 0.3s ease; box-shadow: 0 8px 20px rgba(61, 123, 244, 0.2);
    }
    .milestone-item:hover .milestone-icon { transform: translateY(-5px); }
    .milestone-icon.achieved { background: var(--btn-primary); color: white; }
    .milestone-icon.locked { background: var(--input-bg); color: var(--text-light); box-shadow: none; }
    .milestone-name { font-size: 0.8rem; font-weight: 600; color: var(--text-dark); text-align: center; white-space: nowrap; }
    .milestone-item.locked .milestone-name { color: var(--text-light); }

    /* Recent Activity */
    .activity-list { display: flex; flex-direction: column; gap: 1.5rem; }
    .activity-item { display: flex; align-items: flex-start; gap: 1.25rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-light); }
    .activity-item:last-child { border-bottom: none; padding-bottom: 0; }
    .act-icon {
        width: 45px; height: 45px; border-radius: 50%; background-color: var(--input-bg);
        color: var(--primary-blue); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0;
    }
    .act-content { flex: 1; display: flex; flex-direction: column; justify-content: center; }
    .act-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem; }
    .act-title { font-size: 1.05rem; font-weight: 600; color: var(--text-dark); }
    .act-time { font-size: 0.8rem; color: var(--text-medium); }
    .act-desc { font-size: 0.9rem; color: var(--text-medium); display: flex; justify-content: space-between; align-items: center; }

    /* Extended FAB */
    .fab-extended {
        position: fixed; bottom: 2rem; right: 2rem; height: 60px; padding: 0 2rem; border-radius: 50px;
        background: linear-gradient(135deg, #4d8df5 0%, #3470e8 100%); color: white; display: flex;
        align-items: center; gap: 0.8rem; font-size: 1.05rem; font-weight: 600; box-shadow: 0 10px 25px rgba(61, 123, 244, 0.4);
        cursor: pointer; border: none; transition: all 0.3s ease; z-index: 100;
    }
    .fab-extended:hover { border-radius: 12px; background: var(--btn-gradient-hover); }

    @media (max-width: 1024px) { .achievements-content { grid-template-columns: 1fr; } .profile-card { max-width: 600px; margin: 0 auto; width: 100%; } }
    @media (max-width: 768px) { main.main-content { padding: 1.5rem; } }
    @media (max-width: 768px) { .act-row, .act-desc { flex-direction: column; align-items: flex-start; gap: 0.25rem; } .act-time { order: -1; } .fab-extended { bottom: 1.5rem; right: 1.5rem; padding: 0 1.5rem; font-size: 0.95rem; } }
</style>

<div class="achievements-content">
    <!-- Left Profile Column -->
    <div class="card profile-card fade-in delay-1">
        <div class="profile-avatar-wrapper">
            <?php
            $ach_avatar_url = null;
            if (!empty($profile['profile_picture'])) {
                $ach_avatar_url = avatar_public_url($profile['profile_picture']);
            }
            if ($ach_avatar_url): ?>
                <div class="profile-avatar" style="font-size:0;">
                    <img src="<?php echo htmlspecialchars($ach_avatar_url, ENT_QUOTES, 'UTF-8'); ?>" alt="Profile photo">
                </div>
            <?php else:
                $initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));
                if ($initials === '') $initials = strtoupper(substr($profile['email'] ?? 'U', 0, 1));
            ?>
                <div class="profile-avatar"><?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
        </div>
        
        <h1 class="profile-name"><?php echo htmlspecialchars($full_name); ?></h1>
        
        <p class="profile-tier">Member since <?php echo $member_since; ?> | <strong><?php echo $tier; ?></strong></p>

        <div class="stats-grid">
            <div class="stat-box">
                <span>Height</span>
                <strong><?php echo number_format($height_cm, 0); ?> cm</strong>
            </div>
            <div class="stat-box">
                <span>Weight</span>
                <strong><?php echo number_format($current_weight, 0); ?> kg</strong>
            </div>
            <div class="stat-box">
                <span>Age</span>
                <strong><?php echo $age; ?></strong>
            </div>
            <div class="stat-box">
                <span>Goal</span>
                <strong style="text-transform: capitalize;"><?php echo htmlspecialchars($fitness_goal_label, ENT_QUOTES, 'UTF-8'); ?></strong>
            </div>
        </div>

        <button class="btn-edit-profile" onclick="window.location.href='settings.php'">Edit Profile</button>
    </div>

    <!-- Right Content Column -->
    <div class="right-column">
        
        <!-- Milestones Card -->
        <div class="card fade-in delay-2">
            <div class="section-header">
                <h2 class="section-title">Milestones</h2>
            </div>
            
            <div class="milestones-container">
                <?php foreach ($milestones as $milestone):
                    $is_unlocked = isset($unlocked_badges[$milestone['badge']]);
                    $state = $is_unlocked ? 'achieved' : 'locked';
                ?>
                    <div class="milestone-item <?php echo $state; ?>">
                        <div class="milestone-icon <?php echo $state; ?>">
                            <i class="fa-solid <?php echo htmlspecialchars($milestone['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                        </div>
                        <span class="milestone-name"><?php echo htmlspecialchars($milestone['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Recent Activity Card -->
        <div class="card fade-in delay-3">
            <div class="section-header" style="margin-bottom: 2rem;">
                <h2 class="section-title">Recent Activity</h2>
            </div>

            <div class="activity-list">
                <?php if (empty($activities)): ?>
                    <p style="color: var(--text-medium); font-size: 0.9rem;">No recent activities to show. Start logging to build your streak!</p>
                <?php else: ?>
                    <?php foreach ($activities as $act): ?>
                        <div class="activity-item">
                            <div class="act-icon">
                                <?php echo $act['icon']; ?>
                            </div>
                            <div class="act-content">
                                <div class="act-row">
                                    <h3 class="act-title"><?php echo htmlspecialchars($act['title']); ?></h3>
                                    <span class="act-time"><?php echo time_ago($act['time']); ?></span>
                                </div>
                                <div class="act-desc">
                                    <span><?php echo htmlspecialchars($act['desc']); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- Share Progress FAB -->
<button class="fab-extended fade-in delay-4" onclick="showToast('Share feature coming soon!')">
    <i class="fa-solid fa-share-nodes"></i>
    Share Progress
</button>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        setTimeout(() => {
            const elements = document.querySelectorAll('.fade-in');
            elements.forEach(el => el.classList.add('visible'));
        }, 100);
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>