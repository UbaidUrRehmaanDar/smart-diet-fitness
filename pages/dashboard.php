<?php
// 🔧 Changes Made:
// 🔧 Use shared header/navbar for consistent logo markup.
/**
 * Main Dashboard Page
 * Recreated to match original dashboard.html design
 * Fully functional with backend data integration
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../engine/generate_plan.php';

$user_id = get_user_id();
$page_title = 'Dashboard - ' . APP_NAME;

// Fetch user profile data safely
try {
    $stmt = $pdo->prepare('SELECT * FROM profiles WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch() ?: [];
} catch (PDOException $e) {
    error_log('Profile fetch error: ' . $e->getMessage());
    $profile = [];
}

// // 🔧 Ensure first-time users have today's recommendation before rendering dashboard.
$ensure_result = ensure_todays_plan((int)$user_id);
if (!$ensure_result['success'] && !empty($ensure_result['error']) && $ensure_result['error'] !== 'Incomplete profile') {
    error_log('Dashboard ensure plan warning: ' . $ensure_result['error']);
}

// Fetch today's recommendation
try {
    $today = date('Y-m-d');
    $stmt = $pdo->prepare('SELECT * FROM recommendations WHERE user_id = ? AND recommendation_date = ?');
    $stmt->execute([$user_id, $today]);
    $recommendation = $stmt->fetch() ?: [];
} catch (PDOException $e) {
    $recommendation = [];
}

// 0. Derived targets (hydration + weight change)
// // 🔧 Hydration target fallback (no DB column required).
$weight_kg = (float)($profile['current_weight_kg'] ?? 0);
$hydration_target_ml = $weight_kg > 0 ? (int)round($weight_kg * 35) : 2000;
$hydration_target_ml = max(1500, min(4500, $hydration_target_ml));

// // 🔧 Compute 7-day weight change from progress_metrics when available.
$weekly_weight_change = null;
try {
    $stmt = $pdo->prepare('
        SELECT recorded_date, weight_kg
        FROM progress_metrics
        WHERE user_id = ? AND recorded_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ORDER BY recorded_date ASC
        LIMIT 1
    ');
    $stmt->execute([$user_id]);
    $start_row = $stmt->fetch();

    $stmt = $pdo->prepare('
        SELECT recorded_date, weight_kg
        FROM progress_metrics
        WHERE user_id = ? AND recorded_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ORDER BY recorded_date DESC
        LIMIT 1
    ');
    $stmt->execute([$user_id]);
    $end_row = $stmt->fetch();

    if ($start_row && $end_row && $start_row['weight_kg'] !== null && $end_row['weight_kg'] !== null) {
        $weekly_weight_change = (float)$end_row['weight_kg'] - (float)$start_row['weight_kg'];
    }
} catch (PDOException $e) {
    // keep null (UI will show fallback)
}

// Fetch today's diet logs
try {
    $stmt = $pdo->prepare('SELECT * FROM diet_logs WHERE user_id = ? AND logged_date = ? ORDER BY logged_at DESC');
    $stmt->execute([$user_id, $today]);
    $diet_logs = $stmt->fetchAll();
} catch (PDOException $e) {
    $diet_logs = [];
}

// Fetch today's workout logs
try {
    $stmt = $pdo->prepare('SELECT * FROM workout_logs WHERE user_id = ? AND logged_date = ? ORDER BY logged_at DESC');
    $stmt->execute([$user_id, $today]);
    $workout_logs = $stmt->fetchAll();
} catch (PDOException $e) {
    $workout_logs = [];
}

// Fetch hydration for today
try {
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount_ml), 0) as total FROM hydration_logs WHERE user_id = ? AND logged_date = ?');
    $stmt->execute([$user_id, $today]);
    $hydration_result = $stmt->fetch();
    $total_hydration = (int)($hydration_result['total'] ?? 0);
} catch (PDOException $e) {
    $total_hydration = 0;
}

// Calculate daily totals
$total_kcal    = array_sum(array_column($diet_logs, 'kcal'));
$total_protein = array_sum(array_column($diet_logs, 'protein_g'));
$total_carbs   = array_sum(array_column($diet_logs, 'carbs_g'));
$total_fats    = array_sum(array_column($diet_logs, 'fats_g'));

// Target values from recommendation
$kcal_target   = (int)($recommendation['kcal_target'] ?? 2200);
$protein_target = (int)($recommendation['protein_g'] ?? 180);
$carbs_target   = (int)($recommendation['carbs_g'] ?? 250);
$fats_target    = (int)($recommendation['fats_g'] ?? 70);

// Calculate percentages
$kcal_percentage = $kcal_target > 0 ? min(100, round(($total_kcal / $kcal_target) * 100)) : 0;
$protein_percentage = $protein_target > 0 ? min(100, round(($total_protein / $protein_target) * 100)) : 0;
$carbs_percentage = $carbs_target > 0 ? min(100, round(($total_carbs / $carbs_target) * 100)) : 0;
$fats_percentage = $fats_target > 0 ? min(100, round(($total_fats / $fats_target) * 100)) : 0;

// User display name
// // 🔧 Schema alignment: profiles uses first_name/last_name (no full_name).
$display_name = (isset($profile['first_name'], $profile['last_name']) && trim($profile['first_name'] . $profile['last_name']) !== '')
    ? trim($profile['first_name'] . ' ' . $profile['last_name'])
    : 'User';

// Hydration glasses
$glasses_consumed = floor($total_hydration / 250);

// Get the first upcoming meal from diet logs
$upcoming_meal = !empty($diet_logs) ? $diet_logs[0] : null;

// Current month day for weight trend
$day_name = date('l');
$month_day = date('F jS');

// Daily Adherence — dynamic checks
$meals_logged_today   = count($diet_logs);
$workouts_logged_today = count($workout_logs);
$hydration_goal_met   = $total_hydration >= $hydration_target_ml;
$protein_remaining    = max(0, $protein_target - $total_protein);
$kcal_remaining       = max(0, $kcal_target - $total_kcal);
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<style>
        /* Dashboard-specific layout overrides — variables come from global.css */

        /* Main Container */
        .dashboard-container {
            padding: 3rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Header Section */
        .header-section {
            margin-bottom: 3rem;
        }
        .header-section h1 {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--text-dark);
        }
        .header-section p {
            color: var(--text-medium);
            font-size: 1rem;
        }

        /* Dashboard Grid Layout */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        /* Cards */
        .card {
            background-color: var(--bg-right);
            border-radius: 24px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(27, 54, 121, 0.04);
            margin-bottom: 2rem;
        }
        .card h3 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--text-dark);
        }
        .card h4 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-dark);
        }

        /* Nutrition Intake Section */
        .nutrition-intake {
            margin-bottom: 2rem;
        }
        .calorie-display {
            text-align: center;
            margin-bottom: 2rem;
        }
        .calorie-display .val {
            font-size: 3.5rem;
            font-weight: 700;
            color: var(--primary-blue);
            display: block;
        }
        .calorie-display .lbl {
            font-size: 1.2rem;
            color: var(--text-medium);
        }
        .progress-description {
            text-align: center;
            color: var(--text-medium);
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        .progress-description strong {
            color: var(--text-dark);
        }

        /* Macro Display */
        .macro-display {
            display: flex;
            justify-content: space-around;
            margin-bottom: 2rem;
            gap: 1rem;
        }
        .macro-item {
            text-align: center;
            flex: 1;
        }
        .macro-item .value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-dark);
            display: block;
        }
        .macro-item .label {
            font-size: 0.9rem;
            color: var(--text-medium);
            font-weight: 500;
        }

        /* Progress Bar */
        .progress-container {
            margin: 1.5rem 0;
        }
        .progress-bar-bg {
            background-color: var(--input-bg);
            height: 12px;
            border-radius: 10px;
            overflow: hidden;
        }
        .progress-bar-fill {
            background: #3b82f6 !important;
            height: 100%;
            transition: width 0.5s ease;
            border-radius: 10px;
        }

        /* Weight Trend Section */
        .weight-trend {
            margin-bottom: 2rem;
        }
        .weight-analysis {
            text-align: center;
            margin-bottom: 2rem;
        }
        .weight-change {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-blue);
            display: block;
        }
        .weight-label {
            color: var(--text-medium);
            font-size: 0.9rem;
        }
        .days-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
        }
        .day-item {
            text-align: center;
            flex: 1;
            padding: 0.5rem;
            color: var(--text-light);
            font-weight: 500;
            font-size: 0.85rem;
        }
        .day-item.active {
            color: var(--primary-blue);
            font-weight: 600;
        }
        .day-item .dot {
            width: 8px;
            height: 8px;
            background-color: var(--border-light);
            border-radius: 50%;
            margin: 0.5rem auto 0;
        }
        .day-item.active .dot {
            background-color: var(--primary-blue);
            width: 10px;
            height: 10px;
        }

        /* Upcoming Meal Section */
        .upcoming-meal {
            margin-bottom: 2rem;
        }
        .meal-card {
            background-color: var(--input-bg);
            padding: 1.5rem;
            border-radius: 16px;
            margin-bottom: 1rem;
        }
        .meal-card h4 {
            margin-bottom: 0.5rem;
        }
        .meal-card p {
            color: var(--text-medium);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            line-height: 1.5;
        }
        .meal-meta {
            display: flex;
            gap: 1.5rem;
            font-size: 0.85rem;
            color: var(--text-light);
        }
        .meal-meta span {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        /* Daily Adherence Section */
        .daily-adherence {
            margin-bottom: 2rem;
        }
        .adherence-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-light);
        }
        .adherence-item:last-child {
            border-bottom: none;
        }
        .adherence-item .task {
            font-weight: 500;
            color: var(--text-dark);
        }
        .adherence-item .status {
            font-size: 0.85rem;
            font-weight: 600;
            padding: 0.3rem 0.8rem;
            border-radius: 50px;
        }
        .adherence-item .status.completed {
            background-color: #dbeafe;
            color: #1e40af;
        }
        .adherence-item .status.pending {
            background-color: #fef3c7;
            color: #92400e;
        }
        .adherence-item .status.left {
            background-color: var(--input-bg);
            color: var(--text-medium);
        }

        /* Hydration Section */
        .hydration-status {
            text-align: center;
        }
        .hydration-glasses {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-blue);
            margin-bottom: 0.5rem;
        }
        .hydration-label {
            color: var(--text-medium);
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
        }
        .btn-add-water {
            background: #3b82f6 !important;
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.25, 1, 0.5, 1);
        }
        .btn-add-water:hover {
            background: #2563eb !important;
            border-radius: 12px;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 1.5rem;
            }
            .macro-display {
                flex-direction: column;
                gap: 1rem;
            }
        }
    </style>

    <div class="dashboard-container">
        <!-- Header Section -->
        <div class="header-section">
            <h1>Your personalized diet and fitness overview for today, <?php echo $month_day; ?>.</h1>
        </div>

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Left Column -->
            <div class="left-column">
                <!-- Nutrition Intake -->
                <div class="card nutrition-intake">
                    <h3>Nutrition Intake</h3>
                    <h4>Daily Calorie Progress</h4>
                    <div class="calorie-display">
                        <span class="val"><?php echo number_format($total_kcal); ?> <small style="font-size: 1.5rem;">of <?php echo number_format($kcal_target); ?> kcal</small></span>
                    </div>
                    <p class="progress-description">
                        You have consumed <strong><?php echo $kcal_percentage; ?>%</strong> of your daily target. 
                        Your metabolic rate is optimal for today's scheduled activity.
                    </p>
                    
                    <!-- Macro Display -->
                    <div class="macro-display">
                        <div class="macro-item">
                            <span class="value"><?php echo number_format($total_protein); ?>g</span>
                            <span class="label">Protein</span>
                        </div>
                        <div class="macro-item">
                            <span class="value"><?php echo number_format($total_carbs); ?>g</span>
                            <span class="label">Carbohydrates</span>
                        </div>
                        <div class="macro-item">
                            <span class="value"><?php echo number_format($total_fats); ?>g</span>
                            <span class="label">Fats</span>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div class="progress-container">
                        <div class="progress-bar-bg">
                            <div class="progress-bar-fill" style="width: <?php echo $kcal_percentage; ?>%;"></div>
                        </div>
                    </div>
                </div>

                <!-- Weight Trend -->
                <div class="card weight-trend">
                    <h3>Weight Trend</h3>
                    <h4>Past 7 days analysis</h4>
                    <div class="weight-analysis">
                        <span class="weight-change" style="color:<?php
                            if ($weekly_weight_change === null) echo 'var(--text-medium)';
                            elseif ($weekly_weight_change <= 0) echo 'var(--primary-blue)';
                            else echo 'var(--accent-red)';
                        ?>;">
                            <?php
                            if ($weekly_weight_change === null) {
                                echo 'No data yet';
                            } else {
                                $sign = $weekly_weight_change > 0 ? '+' : '';
                                echo $sign . number_format($weekly_weight_change, 1) . ' kg';
                            }
                            ?>
                        </span>
                        <span class="weight-label">This week</span>
                    </div>
                    <div class="days-row">
                        <div class="day-item <?php echo $day_name === 'Monday' ? 'active' : ''; ?>">MON<div class="dot"></div></div>
                        <div class="day-item <?php echo $day_name === 'Tuesday' ? 'active' : ''; ?>">TUE<div class="dot"></div></div>
                        <div class="day-item <?php echo $day_name === 'Wednesday' ? 'active' : ''; ?>">WED<div class="dot"></div></div>
                        <div class="day-item <?php echo $day_name === 'Thursday' ? 'active' : ''; ?>">THU<div class="dot"></div></div>
                        <div class="day-item <?php echo $day_name === 'Friday' ? 'active' : ''; ?>">FRI<div class="dot"></div></div>
                        <div class="day-item <?php echo $day_name === 'Saturday' ? 'active' : ''; ?>">SAT<div class="dot"></div></div>
                        <div class="day-item <?php echo $day_name === 'Sunday' ? 'active' : ''; ?>">SUN<div class="dot"></div></div>
                    </div>
                </div>

                <!-- Upcoming Meal -->
                <div class="card upcoming-meal">
                    <h3>Upcoming: <?php echo $upcoming_meal ? ucfirst($upcoming_meal['meal_type']) : 'Lunch'; ?></h3>
                    <?php if ($upcoming_meal): ?>
                        <div class="meal-card">
                            <h4><?php echo htmlspecialchars($upcoming_meal['food_item']); ?></h4>
                            <p>Prepared for metabolic boost and sustained energy levels through the afternoon.</p>
                            <div class="meal-meta">
                                <span><i class="fa-regular fa-clock"></i> 15 min</span>
                                <span><i class="fa-solid fa-fire"></i> <?php echo intval($upcoming_meal['kcal']); ?> kcal</span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="meal-card">
                            <h4>Harvest Quinoa Power Bowl</h4>
                            <p>Prepared for metabolic boost and sustained energy levels through the afternoon.</p>
                            <div class="meal-meta">
                                <span><i class="fa-regular fa-clock"></i> 15 min</span>
                                <span><i class="fa-solid fa-fire"></i> 420 kcal</span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Daily Adherence -->
                <div class="card daily-adherence">
                    <h3>Daily Adherence</h3>
                    <div class="adherence-item">
                        <span class="task"><i class="fa-solid fa-dumbbell" style="margin-right:0.5rem;color:var(--primary-blue);"></i>Workouts Logged</span>
                        <?php if ($workouts_logged_today > 0): ?>
                            <span class="status completed"><i class="fa-solid fa-check"></i> <?php echo $workouts_logged_today; ?> done</span>
                        <?php else: ?>
                            <span class="status pending">Not yet</span>
                        <?php endif; ?>
                    </div>
                    <div class="adherence-item">
                        <span class="task"><i class="fa-solid fa-utensils" style="margin-right:0.5rem;color:var(--primary-blue);"></i>Meals Logged</span>
                        <?php if ($meals_logged_today > 0): ?>
                            <span class="status completed"><i class="fa-solid fa-check"></i> <?php echo $meals_logged_today; ?> logged</span>
                        <?php else: ?>
                            <span class="status pending">Not yet</span>
                        <?php endif; ?>
                    </div>
                    <div class="adherence-item">
                        <span class="task"><i class="fa-solid fa-egg" style="margin-right:0.5rem;color:var(--primary-blue);"></i>Protein Goal (<?php echo $protein_target; ?>g)</span>
                        <?php if ($protein_remaining <= 0): ?>
                            <span class="status completed"><i class="fa-solid fa-check"></i> Reached</span>
                        <?php else: ?>
                            <span class="status left"><?php echo intval($protein_remaining); ?>g left</span>
                        <?php endif; ?>
                    </div>
                    <div class="adherence-item">
                        <span class="task"><i class="fa-solid fa-droplet" style="margin-right:0.5rem;color:var(--primary-blue);"></i>Hydration Goal</span>
                        <?php if ($hydration_goal_met): ?>
                            <span class="status completed"><i class="fa-solid fa-check"></i> Goal met</span>
                        <?php else: ?>
                            <span class="status left"><?php echo number_format($hydration_target_ml - $total_hydration); ?>ml left</span>
                        <?php endif; ?>
                    </div>
                    <div class="adherence-item">
                        <span class="task"><i class="fa-solid fa-fire" style="margin-right:0.5rem;color:var(--primary-blue);"></i>Calorie Target (<?php echo number_format($kcal_target); ?> kcal)</span>
                        <?php if ($kcal_remaining <= 0): ?>
                            <span class="status completed"><i class="fa-solid fa-check"></i> Reached</span>
                        <?php else: ?>
                            <span class="status left"><?php echo number_format($kcal_remaining); ?> kcal left</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="right-column">
                <!-- Hydration Status -->
                <div class="card hydration-status">
                    <h3>Hydration Status</h3>
                    <div class="hydration-glasses">
                        <?php echo $glasses_consumed; ?> of 8 glasses consumed
                    </div>
                    <div class="hydration-label">
                        <?php echo number_format($total_hydration); ?> / <?php echo number_format($hydration_target_ml); ?> ml
                    </div>
                    <button class="btn-add-water" onclick="logHydration(250)">
                        <i class="fa-solid fa-plus"></i> Add +250ml
                    </button>
                </div>

                <!-- Quick Actions -->
                <div class="card" style="margin-top:0;">
                    <h3 style="font-size:1.1rem;margin-bottom:1.2rem;">Quick Actions</h3>
                    <div style="display:flex;flex-direction:column;gap:0.75rem;">
                        <button class="btn-add-water" data-nav="<?php echo APP_URL; ?>/pages/nutrition.php">
                            <i class="fa-solid fa-utensils"></i> Log a Meal
                        </button>
                        <button class="btn-add-water" data-nav="<?php echo APP_URL; ?>/pages/workouts.php">
                            <i class="fa-solid fa-dumbbell"></i> Log Workout
                        </button>
                        <button class="btn-add-water" data-nav="<?php echo APP_URL; ?>/pages/progress.php">
                            <i class="fa-solid fa-weight-scale"></i> Update Weight
                        </button>
                    </div>
                </div>

                <!-- Today's Summary -->
                <div class="card" style="margin-top:0;">
                    <h3 style="font-size:1.1rem;margin-bottom:1.2rem;">Today's Summary</h3>
                    <div style="display:flex;flex-direction:column;gap:0.6rem;font-size:0.9rem;">
                        <div style="display:flex;justify-content:space-between;padding:0.5rem 0;border-bottom:1px solid var(--border-light);">
                            <span style="color:var(--text-medium);">Calories consumed</span>
                            <strong><?php echo number_format($total_kcal); ?> kcal</strong>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:0.5rem 0;border-bottom:1px solid var(--border-light);">
                            <span style="color:var(--text-medium);">Calories remaining</span>
                            <strong style="color:var(--primary-blue);"><?php echo number_format(max(0, $kcal_target - $total_kcal)); ?> kcal</strong>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:0.5rem 0;border-bottom:1px solid var(--border-light);">
                            <span style="color:var(--text-medium);">Workouts today</span>
                            <strong><?php echo $workouts_logged_today; ?></strong>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:0.5rem 0;">
                            <span style="color:var(--text-medium);">Current weight</span>
                            <strong><?php echo $weight_kg > 0 ? number_format($weight_kg, 1) . ' kg' : '—'; ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function logHydration(amount) {
            const btn = document.querySelector('.btn-add-water');
            if (btn) { btn.disabled = true; btn.textContent = 'Logging...'; }

            fetch('../api/log_hydration.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': getCsrf()
                },
                body: JSON.stringify({ amount_ml: amount })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const glasses = Math.floor(data.total_ml / 250);
                    const glassEl = document.querySelector('.hydration-glasses');
                    if (glassEl) glassEl.textContent = `${glasses} of 8 glasses consumed`;
                    const labelEl = document.querySelector('.hydration-label');
                    if (labelEl) labelEl.textContent = `${data.total_ml.toLocaleString()} / <?php echo number_format($hydration_target_ml); ?> ml`;
                    showToast('+250ml logged!');
                } else {
                    showToast(data.error || 'Error logging hydration', 'error');
                }
            })
            .catch(() => showToast('Network error. Please try again.', 'error'))
            .finally(() => {
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-plus"></i> Add +250ml'; }
            });
        }

        // Quick-navigate buttons
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-nav]').forEach(btn => {
                btn.addEventListener('click', () => {
                    window.location.href = btn.dataset.nav;
                });
            });
        });
    </script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
