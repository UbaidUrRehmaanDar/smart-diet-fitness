<?php
/**
 * Workouts Page
 * Displays: Recommended workouts, weekly active minutes, and recent achievements.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

$user_id = get_user_id();
$page_title = 'Workouts - ' . APP_NAME;
$today = date('Y-m-d');

// 1. Fetch Today's Recommendation (Workouts)
try {
    $stmt = $pdo->prepare('SELECT workout_plan FROM recommendations WHERE user_id = ? AND recommendation_date = ?');
    $stmt->execute([$user_id, $today]);
    $recommendation = $stmt->fetch() ?: [];
    
    if (!empty($recommendation['workout_plan'])) {
        $decoded = json_decode($recommendation['workout_plan'], true);
        $recommended_workouts = is_array($decoded) ? $decoded : [];
    } else {
        $recommended_workouts = [];
    }
} catch (PDOException $e) {
    error_log('Workouts recommendation fetch error: ' . $e->getMessage());
    $recommended_workouts = [];
}

// 1b. Fetch Today's Logged Workouts (what the user actually saved)
try {
    $stmt = $pdo->prepare('
        SELECT id, exercise_name, exercise_type, duration_mins, intensity, kcal_burned, notes, logged_at
        FROM workout_logs
        WHERE user_id = ? AND logged_date = ?
        ORDER BY logged_at DESC
    ');
    $stmt->execute([$user_id, $today]);
    $todays_logged_workouts = $stmt->fetchAll();
} catch (PDOException $e) {
    $todays_logged_workouts = [];
}

// 2. Fetch Weekly Active Minutes for Chart
// Approximating Monday-Sunday for the chart
$weekly_data = [
    1 => 0, // Mon
    2 => 0, // Tue
    3 => 0, // Wed
    4 => 0, // Thu
    5 => 0, // Fri
    6 => 0, // Sat
    7 => 0  // Sun
];
try {
    $stmt = $pdo->prepare('
        SELECT WEEKDAY(logged_date) as wd, SUM(duration_mins) as total_mins 
        FROM workout_logs 
        WHERE user_id = ? AND logged_date >= DATE_SUB(?, INTERVAL 7 DAY) 
        GROUP BY wd
    ');
    // WEEKDAY() returns 0 for Monday, 6 for Sunday
    $stmt->execute([$user_id, $today]);
    while ($row = $stmt->fetch()) {
        $day_index = intval($row['wd']) + 1; // Map to 1-7
        if (isset($weekly_data[$day_index])) {
            $weekly_data[$day_index] = min(100, round(($row['total_mins'] / 60) * 100)); // Cap percentage at 100 based on 60 min goal
        }
    }
} catch (PDOException $e) {
    // defaults already set
}

// 3. Fetch latest achievement for the banner
try {
    // // 🔧 Schema alignment: achievements table uses badge_name (not title).
    $stmt = $pdo->prepare('SELECT badge_name, description, unlocked_at FROM achievements WHERE user_id = ? ORDER BY unlocked_at DESC LIMIT 1');
    $stmt->execute([$user_id]);
    $latest_achievement = $stmt->fetch();
} catch (PDOException $e) {
    $latest_achievement = null;
}

?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<style>
    /* Specific overrides and classes merged from frontend layout without breaking global scope */
    .delay-1 { transition-delay: 0.1s; }
    .delay-2 { transition-delay: 0.2s; }
    .delay-3 { transition-delay: 0.3s; }
    .delay-4 { transition-delay: 0.4s; }

    @keyframes slideIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Filters Area */
    .filters-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        gap: 1.5rem;
        flex-wrap: wrap;
    }

    .search-wrapper {
        flex: 1;
        position: relative;
        min-width: 0;
    }

    .search-wrapper i {
        position: absolute;
        left: 1.5rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-light);
        font-size: 1.1rem;
    }

    .search-wrapper input {
        width: 100%;
        background-color: var(--bg-right);
        border: 2px solid transparent;
        padding: 1.1rem 1.5rem 1.1rem 3.5rem;
        border-radius: 50px;
        font-size: 1rem;
        color: var(--text-dark);
        outline: none;
        box-shadow: 0 10px 30px rgba(27, 54, 121, 0.04);
        transition: all 0.3s ease;
    }

    .search-wrapper input:focus {
        border-color: var(--primary-blue);
    }

    .dropdowns {
        display: flex;
        gap: 1rem;
    }

    .custom-select {
        appearance: none;
        background-color: var(--bg-right);
        border: none;
        padding: 1.1rem 3rem 1.1rem 1.5rem;
        border-radius: 50px;
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--text-dark);
        cursor: pointer;
        box-shadow: 0 10px 30px rgba(27, 54, 121, 0.04);
        outline: none;
        background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%234a6aa6%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E");
        background-repeat: no-repeat;
        background-position: right 1.2rem top 50%;
        background-size: 0.65rem auto;
        transition: all 0.3s ease;
    }

    .custom-select:hover {
        box-shadow: 0 12px 35px rgba(27, 54, 121, 0.08);
    }

    .workouts-page-content {
        padding: clamp(1rem, 4vw, 3rem);
        max-width: 1400px;
        margin: 0 auto;
        width: 100%;
    }

    .page-grid {
        display: grid;
        grid-template-columns: 2.2fr 1fr;
        gap: 2.5rem;
        align-items: start;
    }

    .workouts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1.5rem;
    }

    .workout-card {
        background-color: var(--bg-right);
        border-radius: 20px;
        padding: 1.25rem;
        box-shadow: 0 10px 30px rgba(27, 54, 121, 0.04);
        border: 2px solid transparent;
        transition: all 0.3s ease;
        cursor: pointer;
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .workout-card:hover {
        border-color: var(--border-light);
        transform: translateY(-4px);
    }

    /* Card header — coloured icon strip, no image */
    .workout-card-header {
        width: 100%;
        height: 90px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.2rem;
        color: rgba(255,255,255,0.9);
        flex-shrink: 0;
    }

    .workout-info {
        padding: 0 0.5rem 0.5rem 0.5rem;
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .workout-info h3 {
        font-size: 1.15rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }

    .workout-stats {
        display: flex;
        align-items: center;
        gap: 1rem;
        font-size: 0.85rem;
        color: var(--text-medium);
        font-weight: 500;
        margin-bottom: 1.5rem;
    }

    .workout-stats i {
        color: var(--text-light);
    }

    .workout-tags {
        display: flex;
        gap: 0.8rem;
        margin-top: auto;
    }

    .tag {
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        padding: 0.4rem 0.8rem;
        border-radius: 50px;
        letter-spacing: 0.5px;
    }

    .tag-blue {
        background-color: var(--input-bg);
        color: var(--primary-blue);
    }

    .tag-yellow {
        background-color: var(--accent-yellow); /* simplified for PHP template */
        color: #b45309;
    }

    .sidebar {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .side-card {
        background-color: var(--bg-right);
        border-radius: 24px;
        padding: 2rem;
        box-shadow: 0 10px 30px rgba(27, 54, 121, 0.04);
    }

    .side-card h3 {
        font-size: 1.15rem;
        font-weight: 700;
        margin-bottom: 1.5rem;
    }

    .chart-wrapper {
        width: 100%;
        height: 140px;
        display: flex;
        align-items: flex-end;
        gap: 8%;
        padding-top: 1rem;
        border-bottom: 2px solid var(--border-light);
    }

    .bar-col {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: flex-end;
        height: 100%;
        gap: 0.5rem;
    }

    .bar {
        width: 100%;
        background-color: var(--input-bg);
        border-radius: 6px 6px 0 0;
        transition: height 1s ease-out, background-color 0.3s;
    }

    .bar.active {
        background-color: var(--primary-blue);
    }

    .bar-col span {
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--text-medium);
    }

    .achievement-card {
        background: var(--btn-primary);
        color: white;
        border-radius: 24px;
        padding: 2rem;
        box-shadow: 0 15px 30px rgba(61, 123, 244, 0.2);
        position: relative;
        overflow: hidden;
    }

    .achievement-icon {
        width: 40px;
        height: 40px;
        background-color: rgba(255, 255, 255, 0.2);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        margin-bottom: 1.5rem;
    }

    .achievement-card h4 {
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        opacity: 0.9;
        margin-bottom: 0.5rem;
    }

    .achievement-card h2 {
        font-size: 1.6rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }

    .achievement-card p {
        font-size: 0.85rem;
        opacity: 0.8;
    }

    .community-link {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.5rem 2rem;
        background-color: var(--bg-right);
        border: 2px solid var(--border-light);
        border-radius: 50px;
        text-decoration: none;
        color: var(--text-dark);
        transition: all 0.4s cubic-bezier(0.25, 1, 0.5, 1);
    }

    .community-link:hover {
        border-color: var(--primary-blue);
        color: var(--primary-blue);
        background-color: var(--input-bg);
        border-radius: 12px;
    }

    .comm-info span {
        display: block;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text-medium);
        margin-bottom: 0.25rem;
    }

    .comm-info h4 {
        font-size: 1.05rem;
        font-weight: 700;
        color: var(--text-dark);
    }

    .community-link:hover .comm-info h4,
    .community-link:hover .comm-info span {
        color: var(--primary-blue);
    }

    .community-link i {
        color: var(--primary-blue);
        font-size: 1.2rem;
    }

    .fab {
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: var(--btn-primary);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        cursor: pointer;
        border: none;
        transition: all 0.3s ease;
        z-index: 100;
    }

    .fab:hover { 
        background: var(--btn-primary-hover);
        border-radius: 12px;
    }

    /* --- Workout Modal --- */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(27, 54, 121, 0.25);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 200;
        padding: 1.5rem;
    }

    .modal-overlay.active { display: flex; }

    .workout-modal {
        width: 100%;
        max-width: 520px;
        background-color: var(--bg-right);
        border-radius: 24px;
        box-shadow: 0 18px 45px rgba(27, 54, 121, 0.15);
        padding: 2rem;
        position: relative;
        animation: slideIn 0.25s ease;
    }

    .modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1.5rem;
    }

    .modal-header h3 {
        font-size: 1.3rem;
        color: var(--text-dark);
    }

    .modal-close {
        background: none;
        border: none;
        color: var(--text-medium);
        font-size: 1.2rem;
        cursor: pointer;
    }

    .modal-body { display: grid; gap: 1rem; }

    .input-group label {
        display: block;
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-medium);
        margin-bottom: 0.4rem;
    }

    .input-group input,
    .input-group select,
    .input-group textarea {
        width: 100%;
        border: none;
        background-color: var(--input-bg);
        border-radius: 14px;
        padding: 0.8rem 1rem;
        font-size: 0.95rem;
        color: var(--text-dark);
        outline: none;
    }

    .input-group input:focus,
    .input-group select:focus,
    .input-group textarea:focus {
        box-shadow: 0 0 0 2px var(--primary-blue);
        background-color: #fff;
    }

    .form-row {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 0.8rem;
    }

    .modal-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: 0.5rem;
    }

    .modal-error {
        font-size: 0.85rem;
        color: #b91c1c;
        min-height: 1rem;
    }

    .btn-modal-primary {
        background: #3b82f6 !important;
        color: #fff !important;
        border: none;
        border-radius: 50px;
        padding: 0.8rem 1.8rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    .btn-modal-primary:hover {
        background: #2563eb !important;
        border-radius: 12px;
    }

    .btn-modal-primary:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .btn-modal-secondary {
        background: var(--bg-right) !important;
        border: 2px solid var(--border-light);
        color: var(--text-dark) !important;
        border-radius: 50px;
        padding: 0.7rem 1.4rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    .btn-modal-secondary:hover {
        border-color: var(--primary-blue);
        color: var(--primary-blue) !important;
        background-color: var(--input-bg) !important;
        border-radius: 12px;
    }

    @media (max-width: 1100px) {
        .page-grid { grid-template-columns: 1fr; }
        .sidebar { flex-direction: row; flex-wrap: wrap; }
        .side-card, .achievement-card, .community-link { flex: 1; min-width: 0; }
    }

    @media (max-width: 768px) {
        .workouts-page-content { padding: 1rem; }
        .filters-bar { flex-direction: column; align-items: stretch; }
        .dropdowns { flex-wrap: wrap; }
        .custom-select { flex: 1; min-width: 0; }
        .search-wrapper { min-width: 0; }
        .workouts-grid { grid-template-columns: 1fr; }
        .sidebar { flex-direction: column; }
        .side-card, .achievement-card, .community-link { min-width: 0; }
        .workout-modal { padding: 1.25rem; }
        .form-row { grid-template-columns: 1fr; }
    }
</style>

<div class="workouts-page-content">
    <div class="filters-bar fade-in delay-1">
        <div class="search-wrapper">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" placeholder="Search exercises..." id="workoutSearch">
        </div>
        <div class="dropdowns">
            <select class="custom-select" id="catFilter">
                <option value="">All Categories</option>
                <option value="strength">Strength</option>
                <option value="cardio">Cardio</option>
                <option value="yoga">Yoga</option>
            </select>
            <select class="custom-select" id="diffFilter">
                <option value="">All Levels</option>
                <option value="beginner">Beginner</option>
                <option value="intermediate">Intermediate</option>
                <option value="advanced">Advanced</option>
            </select>
            <select class="custom-select" id="durFilter">
                <option value="">Any Duration</option>
                <option value="< 30 mins">&lt; 30 mins</option>
                <option value="30 - 45 mins">30 - 45 mins</option>
                <option value="45+ mins">45+ mins</option>
            </select>
        </div>
    </div>

    <div class="page-grid">
    
    <!-- Workouts Grid -->
    <div class="workouts-grid fade-in delay-2">

        <?php if (!empty($todays_logged_workouts)): ?>
        <!-- ── Today's Logged Workouts (from DB) ── -->
        <div style="grid-column:1/-1;margin-bottom:0.5rem;">
            <h3 style="font-size:1rem;font-weight:700;color:var(--text-medium);text-transform:uppercase;letter-spacing:1px;margin-bottom:1rem;">
                <i class="fa-solid fa-circle-check" style="color:#22c55e;margin-right:0.4rem;"></i>
                Today's Logged Workouts (<?= count($todays_logged_workouts) ?>)
            </h3>
        </div>
        <?php
        $log_colors = ['strength'=>'#3b82f6','cardio'=>'#60a5fa','flexibility'=>'#2563eb','sports'=>'#3b82f6'];
        $log_icons  = ['strength'=>'fa-dumbbell','cardio'=>'fa-person-running','flexibility'=>'fa-spa','sports'=>'fa-futbol'];
        foreach ($todays_logged_workouts as $wl):
            $wl_cat  = strtolower($wl['exercise_type'] ?? 'cardio');
            $wl_grad = $log_colors[$wl_cat] ?? '#3b82f6';
            $wl_icon = $log_icons[$wl_cat]  ?? 'fa-dumbbell';
        ?>
        <div class="workout-card" data-loggable="1"
             data-name="<?= htmlspecialchars($wl['exercise_name'], ENT_QUOTES, 'UTF-8') ?>"
             data-duration="<?= (int)$wl['duration_mins'] ?>"
             data-kcal="<?= (int)$wl['kcal_burned'] ?>"
             data-category="<?= htmlspecialchars($wl_cat, ENT_QUOTES, 'UTF-8') ?>"
             data-difficulty="<?= htmlspecialchars($wl['intensity'], ENT_QUOTES, 'UTF-8') ?>"
             style="border:2px solid #22c55e22;">
            <div class="workout-card-header" style="background:<?= $wl_grad ?>;">
                <i class="fa-solid <?= $wl_icon ?>"></i>
            </div>
            <div class="workout-info">
                <h3><?= htmlspecialchars($wl['exercise_name'], ENT_QUOTES, 'UTF-8') ?></h3>
                <div class="workout-stats">
                    <span><i class="fa-regular fa-clock"></i> <?= (int)$wl['duration_mins'] ?> mins</span>
                    <span><i class="fa-solid fa-bolt"></i> <?= (int)$wl['kcal_burned'] ?> kcal</span>
                </div>
                <div class="workout-tags">
                    <span class="tag tag-blue"><?= htmlspecialchars(ucfirst($wl_cat), ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="tag" style="background:#dcfce7;color:#166534;"><?= htmlspecialchars(ucfirst($wl['intensity']), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Divider before suggestions -->
        <div style="grid-column:1/-1;border-top:2px solid var(--border-light);padding-top:1.5rem;margin-top:0.5rem;">
            <h3 style="font-size:1rem;font-weight:700;color:var(--text-medium);text-transform:uppercase;letter-spacing:1px;margin-bottom:1rem;">
                <i class="fa-solid fa-lightbulb" style="color:var(--primary-blue);margin-right:0.4rem;"></i>
                Suggested Workouts
            </h3>
        </div>
        <?php endif; ?>

        <?php
        // CSS gradient backgrounds per category — no external images needed
        $card_gradients = [
            'strength'    => '#3b82f6',
            'cardio'      => '#60a5fa',
            'flexibility' => '#2563eb',
            'sports'      => '#3b82f6',
            'general'     => '#60a5fa',
        ];
        $card_icons = [
            'strength'    => 'fa-dumbbell',
            'cardio'      => 'fa-person-running',
            'flexibility' => 'fa-spa',
            'sports'      => 'fa-futbol',
            'general'     => 'fa-bolt',
        ];
        ?>
        <?php if (!empty($recommended_workouts)): ?>
            <?php foreach ($recommended_workouts as $workout): ?>
                <?php
                $workout_name     = $workout['name'] ?? 'Workout';
                $workout_duration = (int)($workout['duration'] ?? 30);
                $workout_kcal     = (int)($workout['kcal'] ?? 150);
                $workout_category = strtolower($workout['category'] ?? 'general');
                $grad  = $card_gradients[$workout_category] ?? $card_gradients['general'];
                $icon  = $card_icons[$workout_category]    ?? $card_icons['general'];
                ?>
                <div class="workout-card" data-loggable="1"
                     data-name="<?php echo htmlspecialchars($workout_name, ENT_QUOTES, 'UTF-8'); ?>"
                     data-duration="<?php echo $workout_duration; ?>"
                     data-kcal="<?php echo $workout_kcal; ?>"
                     data-category="<?php echo htmlspecialchars($workout_category, ENT_QUOTES, 'UTF-8'); ?>"
                     data-difficulty="">
                    <div class="workout-card-header" style="background:<?php echo $grad; ?>;">
                        <i class="fa-solid <?php echo $icon; ?>"></i>
                    </div>
                    <div class="workout-info">
                        <h3><?php echo htmlspecialchars($workout_name, ENT_QUOTES, 'UTF-8'); ?></h3>
                        <div class="workout-stats">
                            <span><i class="fa-regular fa-clock"></i> <?php echo $workout_duration; ?> mins</span>
                            <span><i class="fa-solid fa-bolt"></i> <?php echo $workout_kcal; ?> kcal</span>
                        </div>
                        <div class="workout-tags">
                            <span class="tag tag-blue"><?php echo htmlspecialchars($workout['category'] ?? 'General', ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <!-- Fallback cards — CSS gradients, no external images -->
            <?php
            $fallback_workouts = [
                ['name'=>'Full Body HIIT',     'duration'=>45, 'kcal'=>350, 'category'=>'strength',    'tag1'=>'Strength',    'tag2'=>'Advanced'],
                ['name'=>'Vinyasa Flow Core',  'duration'=>30, 'kcal'=>180, 'category'=>'flexibility', 'tag1'=>'Yoga',        'tag2'=>'Intermediate'],
                ['name'=>'Lower Body Power',   'duration'=>50, 'kcal'=>420, 'category'=>'strength',    'tag1'=>'Strength',    'tag2'=>'Advanced'],
                ['name'=>'Cardio Endurance',   'duration'=>25, 'kcal'=>300, 'category'=>'cardio',      'tag1'=>'Cardio',      'tag2'=>'Beginner'],
                ['name'=>'Pilates Sculpt',     'duration'=>40, 'kcal'=>220, 'category'=>'flexibility', 'tag1'=>'Yoga',        'tag2'=>'Intermediate'],
                ['name'=>'Deadlift Technique', 'duration'=>60, 'kcal'=>450, 'category'=>'strength',    'tag1'=>'Strength',    'tag2'=>'Advanced'],
            ];
            foreach ($fallback_workouts as $fw):
                $grad = $card_gradients[$fw['category']] ?? $card_gradients['general'];
                $icon = $card_icons[$fw['category']]    ?? $card_icons['general'];
            ?>
            <div class="workout-card" data-loggable="1"
                 data-name="<?php echo htmlspecialchars($fw['name'], ENT_QUOTES, 'UTF-8'); ?>"
                 data-duration="<?php echo $fw['duration']; ?>"
                 data-kcal="<?php echo $fw['kcal']; ?>"
                 data-category="<?php echo htmlspecialchars($fw['category'], ENT_QUOTES, 'UTF-8'); ?>"
                 data-difficulty="<?php echo htmlspecialchars(strtolower($fw['tag2']), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="workout-card-header" style="background:<?php echo $grad; ?>;">
                    <i class="fa-solid <?php echo $icon; ?>"></i>
                </div>
                <div class="workout-info">
                    <h3><?php echo htmlspecialchars($fw['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                    <div class="workout-stats">
                        <span><i class="fa-regular fa-clock"></i> <?php echo $fw['duration']; ?> mins</span>
                        <span><i class="fa-solid fa-bolt"></i> <?php echo $fw['kcal']; ?> kcal</span>
                    </div>
                    <div class="workout-tags">
                        <span class="tag tag-blue"><?php echo htmlspecialchars($fw['tag1'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="tag tag-yellow"><?php echo htmlspecialchars($fw['tag2'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>

    <!-- Sidebar -->
    <aside class="sidebar fade-in delay-3">
        
        <!-- Minutes Active Chart -->
        <div class="side-card">
            <h3>Minutes Active</h3>
            <div class="chart-wrapper">
                <div class="bar-col">
                    <div class="bar <?php echo date('N') == 1 ? 'active' : ''; ?>" id="barMon" style="height: 0%;"></div>
                    <span>Mon</span>
                </div>
                <div class="bar-col">
                    <div class="bar <?php echo date('N') == 2 ? 'active' : ''; ?>" id="barTue" style="height: 0%;"></div>
                    <span>Tue</span>
                </div>
                <div class="bar-col">
                    <div class="bar <?php echo date('N') == 3 ? 'active' : ''; ?>" id="barWed" style="height: 0%;"></div>
                    <span>Wed</span>
                </div>
                <div class="bar-col">
                    <div class="bar <?php echo date('N') == 4 ? 'active' : ''; ?>" id="barThu" style="height: 0%;"></div>
                    <span>Thu</span>
                </div>
                <div class="bar-col">
                    <div class="bar <?php echo date('N') == 5 ? 'active' : ''; ?>" id="barFri" style="height: 0%;"></div>
                    <span>Fri</span>
                </div>
                <div class="bar-col">
                    <div class="bar <?php echo date('N') == 6 ? 'active' : ''; ?>" id="barSat" style="height: 0%;"></div>
                    <span>Sat</span>
                </div>
                <div class="bar-col">
                    <div class="bar <?php echo date('N') == 7 ? 'active' : ''; ?>" id="barSun" style="height: 0%;"></div>
                    <span>Sun</span>
                </div>
            </div>
        </div>

        <!-- Achievement Card -->
        <div class="achievement-card">
            <div class="achievement-icon">
                <i class="fa-solid fa-award"></i>
            </div>
            
            <?php if ($latest_achievement): ?>
                <h4><?php echo htmlspecialchars($latest_achievement['badge_name'] ?? 'New Badge', ENT_QUOTES, 'UTF-8'); ?></h4>
                <h2><?php echo htmlspecialchars($latest_achievement['description'] ?? 'Keep it up!', ENT_QUOTES, 'UTF-8'); ?></h2>
                <p>Achieved on <?php echo htmlspecialchars(date('M jS', strtotime($latest_achievement['unlocked_at'])), ENT_QUOTES, 'UTF-8'); ?></p>
            <?php else: ?>
                <h4>New Personal Best</h4>
                <h2>Keep up the good work!</h2>
                <p>Log a workout to earn an achievement.</p>
            <?php endif; ?>
        </div>

        <!-- Community Link -->
        <a href="<?php echo htmlspecialchars(APP_URL . '/pages/community.php', ENT_QUOTES, 'UTF-8'); ?>" class="community-link">
            <div class="comm-info">
                <span>Join the community</span>
                <h4>New Challenges</h4>
            </div>
            <i class="fa-solid fa-arrow-right"></i>
        </a>

    </aside>

    </div><!-- end .workouts-page-content -->

<button class="fab fade-in delay-4" title="Log Custom Workout" id="openWorkoutModal">
    <i class="fa-solid fa-plus"></i>
</button>

<!-- Workout Modal -->
<div class="modal-overlay" id="workoutModal">
    <div class="workout-modal">
        <div class="modal-header">
            <h3 id="modalTitle">Log Workout</h3>
            <button class="modal-close" type="button" id="closeWorkoutModal"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="workoutForm">
            <div class="modal-body">
                <div class="input-group">
                    <label for="workoutName">Workout Name</label>
                    <input type="text" id="workoutName" name="exercise_name" maxlength="120" placeholder="e.g. Full Body HIIT" required>
                </div>
                <div class="form-row">
                    <div class="input-group">
                        <label for="workoutType">Category</label>
                        <select id="workoutType" name="exercise_type">
                            <option value="cardio">Cardio</option>
                            <option value="strength">Strength</option>
                            <option value="flexibility">Flexibility</option>
                            <option value="sports">Sports</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label for="workoutIntensity">Difficulty Level</label>
                        <select id="workoutIntensity" name="intensity">
                            <option value="beginner">Beginner</option>
                            <option value="intermediate" selected>Intermediate</option>
                            <option value="advanced">Advanced</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="input-group">
                        <label for="workoutDuration">Duration (mins)</label>
                        <input type="number" id="workoutDuration" name="duration_mins" min="1" max="480" placeholder="30" required>
                    </div>
                    <div class="input-group">
                        <label for="workoutKcal">Calories Burned (kcal)</label>
                        <input type="number" id="workoutKcal" name="kcal_burned" min="0" max="5000" placeholder="0">
                    </div>
                </div>
                <div class="input-group">
                    <label for="workoutNotes">Notes (optional)</label>
                    <textarea id="workoutNotes" name="notes" rows="3" maxlength="255" placeholder="Add any notes"></textarea>
                </div>
                <div class="modal-error" id="workoutError"></div>
                <div class="modal-success" id="workoutSuccess" style="display:none;font-size:0.9rem;color:#16a34a;font-weight:600;padding:0.5rem 0;text-align:center;"></div>
                <div class="modal-actions">
                    <button type="button" class="btn-modal-secondary" id="cancelWorkout">Cancel</button>
                    <button type="submit" class="btn-modal-primary" id="saveWorkout">Save Workout</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {

    // ── Fade-in animations ──
    setTimeout(function() {
        document.querySelectorAll('.fade-in').forEach(function(el) { el.classList.add('visible'); });
    }, 100);

    // ── Weekly bar chart ──
    var weeklyData = {
        1: <?php echo intval($weekly_data[1] ?? 0); ?>,
        2: <?php echo intval($weekly_data[2] ?? 0); ?>,
        3: <?php echo intval($weekly_data[3] ?? 0); ?>,
        4: <?php echo intval($weekly_data[4] ?? 0); ?>,
        5: <?php echo intval($weekly_data[5] ?? 0); ?>,
        6: <?php echo intval($weekly_data[6] ?? 0); ?>,
        7: <?php echo intval($weekly_data[7] ?? 0); ?>
    };
    setTimeout(function() {
        document.getElementById('barMon').style.height = weeklyData[1] + '%';
        document.getElementById('barTue').style.height = weeklyData[2] + '%';
        document.getElementById('barWed').style.height = weeklyData[3] + '%';
        document.getElementById('barThu').style.height = weeklyData[4] + '%';
        document.getElementById('barFri').style.height = weeklyData[5] + '%';
        document.getElementById('barSat').style.height = weeklyData[6] + '%';
        document.getElementById('barSun').style.height = weeklyData[7] + '%';
    }, 500);

    // ── Search & Filter ──
    var searchInput = document.getElementById('workoutSearch');
    var catFilter   = document.getElementById('catFilter');
    var diffFilter  = document.getElementById('diffFilter');
    var durFilter   = document.getElementById('durFilter');

    function applyFilters() {
        var query = searchInput ? searchInput.value.toLowerCase().trim() : '';
        var cat   = (catFilter  && catFilter.selectedIndex  > 0) ? catFilter.value.toLowerCase()  : '';
        var diff  = (diffFilter && diffFilter.selectedIndex > 0) ? diffFilter.value.toLowerCase() : '';
        var dur   = (durFilter  && durFilter.selectedIndex  > 0) ? durFilter.value : '';
        var anyVisible = false;

        document.querySelectorAll('.workout-card').forEach(function(card) {
            var name     = (card.querySelector('h3') ? card.querySelector('h3').textContent : '').toLowerCase();
            var cardCat  = (card.dataset.category   || '').toLowerCase();
            var cardDiff = (card.dataset.difficulty || '').toLowerCase();
            var mins     = Number(card.dataset.duration || 0);
            var show = true;

            if (query && name.indexOf(query) === -1) show = false;
            if (cat) {
                var catMap = { strength:'strength', cardio:'cardio', yoga:'flexibility' };
                if (cardCat !== (catMap[cat] || cat)) show = false;
            }
            if (diff && cardDiff && cardDiff.indexOf(diff) === -1) show = false;
            if (dur === '< 30 mins'    && mins >= 30) show = false;
            if (dur === '30 - 45 mins' && (mins < 30 || mins > 45)) show = false;
            if (dur === '45+ mins'     && mins <= 45) show = false;

            card.style.display = show ? '' : 'none';
            if (show) anyVisible = true;
        });

        var noRes = document.getElementById('noWorkoutsMsg');
        if (!anyVisible) {
            if (!noRes) {
                noRes = document.createElement('p');
                noRes.id = 'noWorkoutsMsg';
                noRes.style.cssText = 'color:var(--text-medium);padding:2rem;text-align:center;grid-column:1/-1;';
                noRes.textContent = 'No workouts match your filters.';
                document.querySelector('.workouts-grid').appendChild(noRes);
            }
        } else if (noRes) { noRes.remove(); }
    }

    if (searchInput) searchInput.addEventListener('input', applyFilters);
    if (catFilter)   catFilter.addEventListener('change', applyFilters);
    if (diffFilter)  diffFilter.addEventListener('change', applyFilters);
    if (durFilter)   durFilter.addEventListener('change', applyFilters);

    // ── Modal ──
    var workoutModal   = document.getElementById('workoutModal');
    var workoutForm    = document.getElementById('workoutForm');
    var workoutError   = document.getElementById('workoutError');
    var saveWorkoutBtn = document.getElementById('saveWorkout');
    var openWorkoutBtn = document.getElementById('openWorkoutModal');

    function openModal(prefill) {
        prefill = prefill || {};
        workoutForm.reset();
        document.getElementById('workoutName').value     = prefill.name     || '';
        document.getElementById('workoutDuration').value = prefill.duration || '';
        document.getElementById('workoutKcal').value     = prefill.kcal     || '';
        document.getElementById('workoutType').value     = prefill.category || 'cardio';
        workoutError.textContent = '';
        var successEl = document.getElementById('workoutSuccess');
        if (successEl) { successEl.style.display = 'none'; successEl.textContent = ''; }
        workoutModal.classList.add('active');
        document.getElementById('workoutName').focus();
    }

    function closeModal() { workoutModal.classList.remove('active'); }

    // Wire existing cards
    document.querySelectorAll('.workout-card[data-loggable="1"]').forEach(function(card) {
        card.addEventListener('click', function() {
            openModal({ name: card.dataset.name, duration: card.dataset.duration, kcal: card.dataset.kcal, category: card.dataset.category });
        });
    });

    if (openWorkoutBtn) openWorkoutBtn.addEventListener('click', function() { openModal(); });
    document.getElementById('closeWorkoutModal').addEventListener('click', closeModal);
    document.getElementById('cancelWorkout').addEventListener('click', closeModal);
    workoutModal.addEventListener('click', function(e) { if (e.target === workoutModal) closeModal(); });

    // ── Submit — matches exact pattern from nutrition.php ──
    workoutForm.addEventListener('submit', function(e) {
        e.preventDefault();
        if (saveWorkoutBtn.disabled) return;

        workoutError.textContent = '';
        var successEl = document.getElementById('workoutSuccess');
        if (successEl) successEl.style.display = 'none';
        saveWorkoutBtn.disabled    = true;
        saveWorkoutBtn.textContent = 'Saving...';

        var payload = {
            exercise_name: document.getElementById('workoutName').value.trim(),
            exercise_type: document.getElementById('workoutType').value,
            duration_mins: Number(document.getElementById('workoutDuration').value),
            intensity:     document.getElementById('workoutIntensity').value,
            kcal_burned:   Number(document.getElementById('workoutKcal').value || 0),
            notes:         document.getElementById('workoutNotes').value.trim()
        };

        if (!payload.exercise_name || payload.duration_mins <= 0) {
            workoutError.textContent   = 'Please enter a workout name and duration.';
            saveWorkoutBtn.disabled    = false;
            saveWorkoutBtn.textContent = 'Save Workout';
            return;
        }

        fetch('<?php echo APP_URL; ?>/api/log_workout.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrf()
            },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(function(data) {
            if (!data.success) {
                workoutError.textContent = data.error || 'Unable to save workout.';
                return;
            }

            // Update CSRF token in meta tag
            if (data.csrf_token) {
                var meta = document.querySelector('meta[name="csrf-token"]');
                if (meta) meta.setAttribute('content', data.csrf_token);
            }

            // Update bar chart for today
            var days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
            var bar  = document.getElementById('bar' + days[new Date().getDay()]);
            if (bar) bar.style.height = Math.min(100, parseFloat(bar.style.height || '0') + Math.round((payload.duration_mins / 60) * 100)) + '%';

            // Add new card to grid
            var colors = { cardio:'#60a5fa', strength:'#3b82f6', flexibility:'#2563eb', sports:'#3b82f6' };
            var icons  = { cardio:'fa-person-running', strength:'fa-dumbbell', flexibility:'fa-spa', sports:'fa-futbol' };
            var grid   = document.querySelector('.workouts-grid');
            if (grid) {
                var kcalLogged = (data.logged_workout && data.logged_workout.kcal_burned) || 0;
                var c = document.createElement('div');
                c.className = 'workout-card';
                c.dataset.loggable  = '1';
                c.dataset.name      = payload.exercise_name;
                c.dataset.duration  = payload.duration_mins;
                c.dataset.category  = payload.exercise_type;
                c.dataset.kcal      = kcalLogged;
                c.dataset.difficulty = payload.intensity;
                c.innerHTML =
                    '<div class="workout-card-header" style="background:' + (colors[payload.exercise_type] || '#3b82f6') + '">' +
                        '<i class="fa-solid ' + (icons[payload.exercise_type] || 'fa-dumbbell') + '"></i>' +
                    '</div>' +
                    '<div class="workout-info">' +
                        '<h3>' + payload.exercise_name.replace(/</g, '&lt;') + '</h3>' +
                        '<div class="workout-stats">' +
                            '<span><i class="fa-regular fa-clock"></i> ' + payload.duration_mins + ' mins</span>' +
                            '<span><i class="fa-solid fa-bolt"></i> ' + kcalLogged + ' kcal</span>' +
                        '</div>' +
                        '<div class="workout-tags"><span class="tag tag-blue">' + payload.exercise_type + '</span></div>' +
                    '</div>';
                c.addEventListener('click', function() {
                    openModal({ name: payload.exercise_name, duration: payload.duration_mins, kcal: kcalLogged, category: payload.exercise_type });
                });
                grid.prepend(c);
            }

            // Show success feedback
            if (successEl) {
                successEl.textContent = '✅ ' + payload.exercise_name + ' logged successfully!';
                successEl.style.display = 'block';
            }
            showToast(payload.exercise_name + ' logged successfully!');
            setTimeout(closeModal, 1200);
        })
        .catch(function() {
            workoutError.textContent = 'A network error occurred. Please try again.';
        })
        .finally(function() {
            saveWorkoutBtn.disabled    = false;
            saveWorkoutBtn.textContent = 'Save Workout';
        });
    });

});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
