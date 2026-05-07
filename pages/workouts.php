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
        min-width: 300px;
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

    .main-content {
        padding: 3rem;
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
        padding: 1rem;
        box-shadow: 0 10px 30px rgba(27, 54, 121, 0.04);
        border: 2px solid transparent;
        transition: all 0.3s ease;
        cursor: pointer;
        display: flex;
        flex-direction: column;
    }

    .workout-card:hover {
        border-color: var(--border-light);
        transform: translateY(-5px);
        box-shadow: 0 15px 40px rgba(27, 54, 121, 0.08);
    }

    .workout-img {
        width: 100%;
        height: 160px;
        border-radius: 14px;
        background-position: center;
        background-size: cover;
        margin-bottom: 1.25rem;
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
        background: var(--btn-gradient);
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
        background-color: var(--input-bg);
        border-radius: 20px;
        text-decoration: none;
        color: var(--text-dark);
        transition: all 0.3s ease;
    }

    .community-link:hover {
        background-color: #dbe7ff;
        transform: translateX(5px);
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
        background: var(--btn-gradient);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        box-shadow: 0 10px 25px rgba(61, 123, 244, 0.4);
        cursor: pointer;
        border: none;
        transition: all 0.3s ease;
        z-index: 100;
    }

    .fab:hover { transform: scale(1.1) rotate(90deg); }

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
        background: var(--btn-gradient);
        color: #fff;
        border: none;
        border-radius: 50px;
        padding: 0.8rem 1.8rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-modal-primary:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .btn-modal-secondary {
        background: transparent;
        border: 2px solid var(--border-light);
        color: var(--text-dark);
        border-radius: 50px;
        padding: 0.7rem 1.4rem;
        font-weight: 600;
        cursor: pointer;
    }

    @media (max-width: 1100px) {
        .page-grid { grid-template-columns: 1fr; }
        .sidebar { flex-direction: row; flex-wrap: wrap; }
        .side-card, .achievement-card, .community-link { flex: 1; min-width: 300px; }
    }

    @media (max-width: 768px) {
        .filters-bar { flex-direction: column; align-items: stretch; }
        .dropdowns { flex-wrap: wrap; }
        .custom-select { flex: 1; }
    }
</style>

<div class="main-content">
    <div class="filters-bar fade-in delay-1">
        <div class="search-wrapper">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" placeholder="Search exercises..." id="workoutSearch">
        </div>
        <div class="dropdowns">
            <select class="custom-select" id="catFilter">
                <option value="" disabled selected>Category</option>
                <option>Strength</option>
                <option>Cardio</option>
                <option>Yoga</option>
            </select>
            <select class="custom-select" id="diffFilter">
                <option value="" disabled selected>Difficulty</option>
                <option>Beginner</option>
                <option>Intermediate</option>
                <option>Advanced</option>
            </select>
            <select class="custom-select" id="durFilter">
                <option value="" disabled selected>Duration</option>
                <option>&lt; 30 mins</option>
                <option>30 - 45 mins</option>
                <option>45+ mins</option>
            </select>
        </div>
    </div>

    <div class="page-grid">
    
    <!-- Workouts Grid -->
    <div class="workouts-grid fade-in delay-2">
        
        <?php if (!empty($recommended_workouts)): ?>
            <?php foreach ($recommended_workouts as $workout): ?>
                <?php
                $workout_name = $workout['name'] ?? 'Workout';
                $workout_duration = (int)($workout['duration'] ?? 30);
                $workout_kcal = (int)($workout['kcal'] ?? 150);
                $workout_category = strtolower($workout['category'] ?? 'general');
                ?>
                <div class="workout-card" data-loggable="1" data-name="<?php echo htmlspecialchars($workout_name, ENT_QUOTES, 'UTF-8'); ?>" data-duration="<?php echo $workout_duration; ?>" data-kcal="<?php echo $workout_kcal; ?>" data-category="<?php echo htmlspecialchars($workout_category, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="workout-img" style="background-image: url('https://images.unsplash.com/photo-1517836357463-d25dfeac3438?auto=format&fit=crop&w=600&q=80');"></div>
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
            <!-- Fallback Static Data to Match Design exactly as requested when DB is empty -->
            <div class="workout-card" data-loggable="1" data-name="Full Body HIIT" data-duration="45" data-kcal="350" data-category="strength">
                <div class="workout-img" style="background-image: url('https://images.unsplash.com/photo-1517836357463-d25dfeac3438?auto=format&fit=crop&w=600&q=80');"></div>
                <div class="workout-info">
                    <h3>Full Body HIIT</h3>
                    <div class="workout-stats">
                        <span><i class="fa-regular fa-clock"></i> 45 mins</span>
                        <span><i class="fa-solid fa-bolt"></i> 350 kcal</span>
                    </div>
                    <div class="workout-tags">
                        <span class="tag tag-blue">Strength</span>
                        <span class="tag tag-yellow">Advanced</span>
                    </div>
                </div>
            </div>

            <div class="workout-card" data-loggable="1" data-name="Vinyasa Flow Core" data-duration="30" data-kcal="180" data-category="flexibility">
                <div class="workout-img" style="background-image: url('https://images.unsplash.com/photo-1544367567-0f2fcb009e0b?auto=format&fit=crop&w=600&q=80');"></div>
                <div class="workout-info">
                    <h3>Vinyasa Flow Core</h3>
                    <div class="workout-stats">
                        <span><i class="fa-regular fa-clock"></i> 30 mins</span>
                        <span><i class="fa-solid fa-bolt"></i> 180 kcal</span>
                    </div>
                    <div class="workout-tags">
                        <span class="tag tag-blue">Yoga</span>
                        <span class="tag tag-yellow">Intermediate</span>
                    </div>
                </div>
            </div>

            <div class="workout-card" data-loggable="1" data-name="Lower Body Power" data-duration="50" data-kcal="420" data-category="strength">
                <div class="workout-img" style="background-image: url('https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&w=600&q=80');"></div>
                <div class="workout-info">
                    <h3>Lower Body Power</h3>
                    <div class="workout-stats">
                        <span><i class="fa-regular fa-clock"></i> 50 mins</span>
                        <span><i class="fa-solid fa-bolt"></i> 420 kcal</span>
                    </div>
                    <div class="workout-tags">
                        <span class="tag tag-blue">Strength</span>
                        <span class="tag tag-yellow">Advanced</span>
                    </div>
                </div>
            </div>

            <div class="workout-card" data-loggable="1" data-name="Cardio Endurance" data-duration="25" data-kcal="300" data-category="cardio">
                <div class="workout-img" style="background-image: url('https://images.unsplash.com/photo-1538805060514-97d9cc17730c?auto=format&fit=crop&w=600&q=80');"></div>
                <div class="workout-info">
                    <h3>Cardio Endurance</h3>
                    <div class="workout-stats">
                        <span><i class="fa-regular fa-clock"></i> 25 mins</span>
                        <span><i class="fa-solid fa-bolt"></i> 300 kcal</span>
                    </div>
                    <div class="workout-tags">
                        <span class="tag tag-blue">Cardio</span>
                        <span class="tag tag-yellow">Beginner</span>
                    </div>
                </div>
            </div>

            <div class="workout-card" data-loggable="1" data-name="Pilates Sculpt" data-duration="40" data-kcal="220" data-category="flexibility">
                <div class="workout-img" style="background-image: url('https://images.unsplash.com/photo-1518611012118-696072aa579a?auto=format&fit=crop&w=600&q=80');"></div>
                <div class="workout-info">
                    <h3>Pilates Sculpt</h3>
                    <div class="workout-stats">
                        <span><i class="fa-regular fa-clock"></i> 40 mins</span>
                        <span><i class="fa-solid fa-bolt"></i> 220 kcal</span>
                    </div>
                    <div class="workout-tags">
                        <span class="tag tag-blue">Yoga</span>
                        <span class="tag tag-yellow">Intermediate</span>
                    </div>
                </div>
            </div>

            <div class="workout-card" data-loggable="1" data-name="Deadlift Technique" data-duration="60" data-kcal="450" data-category="strength">
                <div class="workout-img" style="background-image: url('https://images.unsplash.com/photo-1581009146145-b5ef050c2e1e?auto=format&fit=crop&w=600&q=80');"></div>
                <div class="workout-info">
                    <h3>Deadlift Technique</h3>
                    <div class="workout-stats">
                        <span><i class="fa-regular fa-clock"></i> 60 mins</span>
                        <span><i class="fa-solid fa-bolt"></i> 450 kcal</span>
                    </div>
                    <div class="workout-tags">
                        <span class="tag tag-blue">Strength</span>
                        <span class="tag tag-yellow">Advanced</span>
                    </div>
                </div>
            </div>
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

    </div>
</div>

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
                        <label for="workoutIntensity">Intensity</label>
                        <select id="workoutIntensity" name="intensity">
                            <option value="light">Light</option>
                            <option value="moderate" selected>Moderate</option>
                            <option value="vigorous">Vigorous</option>
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
                <div class="modal-actions">
                    <button type="button" class="btn-modal-secondary" id="cancelWorkout">Cancel</button>
                    <button type="submit" class="btn-modal-primary" id="saveWorkout">Save Workout</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        setTimeout(() => {
            const elements = document.querySelectorAll('.fade-in');
            elements.forEach(el => el.classList.add('visible'));
        }, 100);

        // Inject calculated PHP weekly active minutes data into JS
        const weeklyData = {
            1: <?php echo intval($weekly_data[1] ?? 0); ?>,
            2: <?php echo intval($weekly_data[2] ?? 0); ?>,
            3: <?php echo intval($weekly_data[3] ?? 0); ?>,
            4: <?php echo intval($weekly_data[4] ?? 0); ?>,
            5: <?php echo intval($weekly_data[5] ?? 0); ?>,
            6: <?php echo intval($weekly_data[6] ?? 0); ?>,
            7: <?php echo intval($weekly_data[7] ?? 0); ?>
        };

        setTimeout(() => {
            document.getElementById('barMon').style.height = `${weeklyData[1]}%`;
            document.getElementById('barTue').style.height = `${weeklyData[2]}%`;
            document.getElementById('barWed').style.height = `${weeklyData[3]}%`; 
            document.getElementById('barThu').style.height = `${weeklyData[4]}%`;
            document.getElementById('barFri').style.height = `${weeklyData[5]}%`;
            document.getElementById('barSat').style.height = `${weeklyData[6]}%`;
            document.getElementById('barSun').style.height = `${weeklyData[7]}%`;
        }, 500);

        const loggableCards = document.querySelectorAll('.workout-card[data-loggable="1"]');
        loggableCards.forEach(card => {
            card.addEventListener('click', () => {
                const name = card.dataset.name || 'Workout';
                const duration = Number(card.dataset.duration || 30);
                const kcal = Number(card.dataset.kcal || 0);
                const category = card.dataset.category || 'cardio';
                openWorkoutModal({ name, duration, kcal, category });
            });
        });
    });

    // Workout Modal + AJAX logging
    const workoutModal = document.getElementById('workoutModal');
    const workoutForm = document.getElementById('workoutForm');
    const workoutError = document.getElementById('workoutError');
    const saveWorkoutBtn = document.getElementById('saveWorkout');
    const openWorkoutBtn = document.getElementById('openWorkoutModal');

    function openWorkoutModal(prefill = {}) {
        workoutForm.reset();
        document.getElementById('workoutName').value = prefill.name || '';
        document.getElementById('workoutDuration').value = prefill.duration || '';
        document.getElementById('workoutKcal').value = prefill.kcal || '';
        document.getElementById('workoutType').value = prefill.category || 'cardio';
        workoutError.textContent = '';
        workoutModal.classList.add('active');
        document.getElementById('workoutName').focus();
    }

    function closeWorkoutModal() {
        workoutModal.classList.remove('active');
    }

    openWorkoutBtn.addEventListener('click', () => openWorkoutModal());
    document.getElementById('closeWorkoutModal').addEventListener('click', closeWorkoutModal);
    document.getElementById('cancelWorkout').addEventListener('click', closeWorkoutModal);

    workoutModal.addEventListener('click', (event) => {
        if (event.target === workoutModal) {
            closeWorkoutModal();
        }
    });

    workoutForm.addEventListener('submit', (event) => {
        event.preventDefault();
        if (saveWorkoutBtn.disabled) {
            return;
        }

        workoutError.textContent = '';
        saveWorkoutBtn.disabled = true;
        saveWorkoutBtn.textContent = 'Saving...';

        const payload = {
            exercise_name: document.getElementById('workoutName').value.trim(),
            exercise_type: document.getElementById('workoutType').value,
            duration_mins: Number(document.getElementById('workoutDuration').value),
            intensity: document.getElementById('workoutIntensity').value,
            kcal_burned: Number(document.getElementById('workoutKcal').value || 0),
            notes: document.getElementById('workoutNotes').value.trim()
        };

        if (!payload.exercise_name || payload.duration_mins <= 0) {
            workoutError.textContent = 'Please enter a workout name and duration.';
            saveWorkoutBtn.disabled = false;
            saveWorkoutBtn.textContent = 'Save Workout';
            return;
        }

        fetch('<?php echo APP_URL; ?>/api/log_workout.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': '<?php echo htmlspecialchars($_SESSION["csrf_token"] ?? "", ENT_QUOTES, "UTF-8"); ?>'
            },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                workoutError.textContent = data.error || 'Unable to save workout.';
                return;
            }

            const todayIndex = new Date().getDay();
            const mappedIndex = todayIndex === 0 ? 7 : todayIndex;
            const currentBar = document.getElementById(`bar${['Mon','Tue','Wed','Thu','Fri','Sat','Sun'][mappedIndex - 1]}`);
            if (currentBar) {
                const currentHeight = parseFloat(currentBar.style.height || '0');
                const addedHeight = Math.min(100, Math.round((payload.duration_mins / 60) * 100));
                currentBar.style.height = `${Math.min(100, currentHeight + addedHeight)}%`;
            }

            closeWorkoutModal();
        })
        .catch(() => {
            workoutError.textContent = 'A network error occurred. Please try again.';
        })
        .finally(() => {
            saveWorkoutBtn.disabled = false;
            saveWorkoutBtn.textContent = 'Save Workout';
        });
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>