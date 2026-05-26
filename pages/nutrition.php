<?php
/**
 * Nutrition Page
 * Displays: Daily meals logged, macro progress, calories remaining, hydration tracking
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

$user_id = get_user_id();
$page_title = 'Nutrition - ' . APP_NAME;

// Allow date navigation via ?date= param (max 90 days back, no future)
$today = date('Y-m-d');
$view_date = $today;
if (!empty($_GET['date'])) {
    $candidate = $_GET['date'];
    if (validate_date($candidate) && $candidate <= $today && $candidate >= date('Y-m-d', strtotime('-90 days'))) {
        $view_date = $candidate;
    }
}
$is_today = ($view_date === $today);
$prev_date = date('Y-m-d', strtotime($view_date . ' -1 day'));
$next_date = date('Y-m-d', strtotime($view_date . ' +1 day'));
$can_go_next = ($view_date < $today);
$display_date = ($is_today ? 'Today, ' : '') . date('F jS', strtotime($view_date));

// 0. Fetch user profile (for hydration target defaults)
try {
    $stmt = $pdo->prepare('SELECT current_weight_kg FROM profiles WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch() ?: [];
} catch (PDOException $e) {
    $profile = [];
}

// 1. Fetch Recommendation for viewed date
try {
    $stmt = $pdo->prepare('SELECT * FROM recommendations WHERE user_id = ? AND recommendation_date = ?');
    $stmt->execute([$user_id, $view_date]);
    $recommendation = $stmt->fetch() ?: [];
    // Fallback: use most recent recommendation if none for this date
    if (empty($recommendation)) {
        $stmt = $pdo->prepare('SELECT * FROM recommendations WHERE user_id = ? ORDER BY recommendation_date DESC LIMIT 1');
        $stmt->execute([$user_id]);
        $recommendation = $stmt->fetch() ?: [];
    }
} catch (PDOException $e) {
    error_log('Nutrition recommendation fetch error: ' . $e->getMessage());
    $recommendation = [];
}

$rec_kcal = (int)($recommendation['kcal_target'] ?? 2200);
$rec_protein = (int)($recommendation['protein_g'] ?? 180);
$rec_carbs = (int)($recommendation['carbs_g'] ?? 250);
$rec_fats = (int)($recommendation['fats_g'] ?? 70);
// // 🔧 Schema alignment: recommendations table has no water_ml column. Use profile-based fallback.
$weight_kg = (float)($profile['current_weight_kg'] ?? 0);
$rec_water = $weight_kg > 0 ? (int)round($weight_kg * 35) : 2000; // ~35ml/kg/day
$rec_water = max(1500, min(4500, $rec_water));

// 2. Fetch Diet Logs for viewed date
try {
    $stmt = $pdo->prepare('SELECT * FROM diet_logs WHERE user_id = ? AND logged_date = ? ORDER BY logged_at ASC');
    $stmt->execute([$user_id, $view_date]);
    $diet_logs = $stmt->fetchAll();
} catch (PDOException $e) {
    $diet_logs = [];
}

// Group logs by meal type
$meals = [
    'breakfast' => [],
    'lunch'     => [],
    'dinner'    => [],
    'snack'     => []
];

$total_kcal = 0;
$total_protein = 0;
$total_carbs = 0;
$total_fats = 0;

foreach ($diet_logs as $log) {
    $m_type = strtolower($log['meal_type']);
    if (isset($meals[$m_type])) {
        $meals[$m_type][] = $log;
    } else {
        $meals['snack'][] = $log;
    }
    $total_kcal += (float)$log['kcal'];
    $total_protein += (float)$log['protein_g'];
    $total_carbs += (float)$log['carbs_g'];
    $total_fats += (float)$log['fats_g'];
}

$calc_meal_total = function($meal_logs) {
    return array_sum(array_column($meal_logs, 'kcal'));
};
$breakfast_total = $calc_meal_total($meals['breakfast']);
$lunch_total = $calc_meal_total($meals['lunch']);
$dinner_total = $calc_meal_total($meals['dinner']);

// 3. Fetch Active Burn for viewed date
try {
    $stmt = $pdo->prepare('SELECT SUM(kcal_burned) as total_burned FROM workout_logs WHERE user_id = ? AND logged_date = ?');
    $stmt->execute([$user_id, $view_date]);
    $burned_result = $stmt->fetch();
    $total_burned = (float)($burned_result['total_burned'] ?? 0);
} catch (PDOException $e) {
    $total_burned = 0;
}

// 4. Fetch Hydration for viewed date
try {
    $stmt = $pdo->prepare('SELECT SUM(amount_ml) as total_water FROM hydration_logs WHERE user_id = ? AND logged_date = ?');
    $stmt->execute([$user_id, $view_date]);
    $water_result = $stmt->fetch();
    $water_logged = (float)($water_result['total_water'] ?? 0);
} catch (PDOException $e) {
    $water_logged = 0;
}

// Derived Values
$remaining_kcal = max(0, $rec_kcal - $total_kcal + $total_burned);
$progress_circle_pct = min(100, round(($total_kcal / max(1, $rec_kcal)) * 100)); 
$protein_pct = min(100, round(($total_protein / max(1, $rec_protein)) * 100));
$carbs_pct = min(100, round(($total_carbs / max(1, $rec_carbs)) * 100));
$fats_pct = min(100, round(($total_fats / max(1, $rec_fats)) * 100));

$water_liters = number_format($water_logged / 1000, 1);
$rec_water_liters = number_format($rec_water / 1000, 1);
// Ensure we don't exceed 8 drops visually on a fresh load (250ml per drop)
$water_drops_filled = min(8, floor($water_logged / 250));

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

    /* --- Main Layout Grid --- */
    .nutrition-container {
        padding: clamp(1rem, 4vw, 3rem);
        max-width: 1400px;
        margin: 0 auto;
        display: grid;
        grid-template-columns: 1.8fr 1fr;
        gap: 2.5rem;
        align-items: start;
    }

    .card {
        background-color: var(--bg-right);
        border-radius: 24px;
        padding: 2rem;
        box-shadow: 0 10px 30px rgba(27, 54, 121, 0.04);
        margin-bottom: 1.5rem;
    }

    .card-title {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--text-dark);
        margin-bottom: 1.5rem;
    }

    /* --- Left Column: Date & Meals --- */
    .date-selector {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background-color: var(--input-bg);
        padding: 1.5rem 2rem;
        border-radius: 20px;
        margin-bottom: 2rem;
    }

    .date-selector button {
        background: none;
        border: none;
        color: var(--text-dark);
        font-size: 1.2rem;
        cursor: pointer;
        transition: color 0.3s;
    }

    .date-selector button:hover { color: var(--primary-blue); }

    .date-info { text-align: center; }
    .date-info h2 {
        font-size: 1.3rem;
        font-weight: 700;
        margin-bottom: 0.2rem;
    }
    .date-info span {
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--primary-blue);
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    /* Meal Cards */
    .meal-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1.5rem;
    }

    .meal-header h3 {
        font-size: 1.4rem;
        color: var(--text-dark);
        margin-bottom: 0.3rem;
    }

    .meal-header p {
        font-size: 0.85rem;
        color: var(--text-medium);
        font-weight: 500;
    }

    .meal-total {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--text-dark);
    }

    .meal-total span {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text-medium);
    }

    .food-list {
        list-style: none;
        margin-bottom: 1.5rem;
    }

    .food-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.8rem 0;
        border-bottom: 1px dashed var(--border-light);
    }

    .food-item:last-child { border-bottom: none; }

    .food-name {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        font-size: 0.95rem;
        font-weight: 500;
        color: var(--text-medium);
    }

    .food-name::before {
        content: '';
        display: block;
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background-color: var(--text-light); /* fallback input-placeholder */
    }

    .food-kcal {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--text-dark);
    }

    .btn-add-food {
        background: none;
        border: none;
        color: var(--primary-blue);
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.4rem;
        transition: color 0.3s ease;
    }

    .btn-add-food:hover { color: var(--text-dark); }

    /* --- Right Column: Stats & Progress --- */
    .progress-circle-container {
        position: relative;
        width: 200px;
        height: 200px;
        margin: 0 auto 2rem auto;
    }

    .circular-chart {
        display: block;
        max-width: 100%;
        max-height: 100%;
    }

    .circle-bg {
        fill: none;
        stroke: var(--input-bg);
        stroke-width: 3.5;
    }

    .circle {
        fill: none;
        stroke-width: 3.5;
        stroke-linecap: round;
        stroke: var(--primary-blue);
        stroke-dasharray: 0, 100;
        transition: stroke-dasharray 1.5s cubic-bezier(0.25, 1, 0.5, 1);
    }

    .chart-text {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        text-align: center;
    }

    .chart-text .val {
        font-size: 2.2rem;
        font-weight: 700;
        color: var(--text-dark);
        display: block;
        line-height: 1;
        margin-bottom: 0.2rem;
    }

    .chart-text .lbl {
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        color: var(--text-medium);
        letter-spacing: 0.5px;
    }

    .stat-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem 0;
        border-bottom: 1px solid var(--border-light);
        font-size: 0.95rem;
        font-weight: 500;
    }

    .stat-row:last-child { border-bottom: none; padding-bottom: 0; }
    
    .stat-label { color: var(--text-medium); }
    .stat-val { font-weight: 700; color: var(--text-dark); }
    .stat-val.blue { color: var(--primary-blue); }

    /* Macros */
    .macro-item { margin-bottom: 1.5rem; }
    .macro-item:last-child { margin-bottom: 0; }

    .macro-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.6rem;
        font-size: 0.9rem;
        font-weight: 600;
    }

    .macro-header span:first-child { color: var(--text-dark); }
    .macro-header span:last-child { color: var(--text-medium); }

    .macro-bar-bg {
        width: 100%;
        height: 8px;
        background-color: var(--input-bg);
        border-radius: 10px;
        overflow: hidden;
    }

    .macro-bar-fill {
        height: 100%;
        background-color: var(--text-dark);
        border-radius: 10px;
        transition: width 1s ease-out;
    }

    /* Hydration */
    .hydration-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }

    .hydration-header h3 {
        font-size: 1.2rem;
        color: var(--text-dark);
    }

    .hydration-header span {
        font-size: 0.9rem;
        font-weight: 700;
        color: var(--primary-blue);
    }

    .hydration-tracker {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .drops-container {
        display: flex;
        gap: 0.5rem;
    }

    .drop-icon {
        font-size: 1.5rem;
        color: var(--input-bg);
        transition: color 0.3s ease;
    }

    .drop-icon.filled { color: var(--primary-blue); }

    .btn-quick-add {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        background-color: var(--primary-blue);
        color: white;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(61, 123, 244, 0.3);
        transition: all 0.3s ease;
    }

    .btn-quick-add:hover {
        background-color: var(--primary-blue-hover);
        transform: scale(1.05);
    }

    .btn-quick-add:active { transform: scale(0.95); }

    /* --- Meal Modal --- */
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

    .meal-modal {
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
    .input-group textarea:focus {
        box-shadow: 0 0 0 2px var(--primary-blue);
        background-color: #fff;
    }

    .form-row {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
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

    /* --- FAB --- */
    .fab {
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: #3b82f6 !important;
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

    /* --- Responsive Design --- */
    @media (max-width: 1024px) {
        .nutrition-container { grid-template-columns: 1fr; padding: 1.5rem; }
    }
    @media (max-width: 768px) {
        .nutrition-container { padding: 1rem; gap: 1.5rem; }
        .date-selector { padding: 1rem 1.25rem; }
        .date-info h2 { font-size: 1rem; }
        .meal-header { flex-direction: column; gap: 0.5rem; }
        .meal-total { font-size: 1.3rem; }
        .form-row { grid-template-columns: 1fr; }
        .card { padding: 1.25rem; }
        .drops-container { gap: 0.3rem; }
        .drop-icon { font-size: 1.2rem; }
    }
</style>

<div class="nutrition-container">

    <!-- Left Column: Meals -->
    <div class="meals-column">
        
        <!-- Date Selector -->
        <div class="date-selector fade-in delay-1">
            <a href="?date=<?php echo htmlspecialchars($prev_date, ENT_QUOTES, 'UTF-8'); ?>" style="text-decoration:none;">
                <button type="button"><i class="fa-solid fa-chevron-left"></i></button>
            </a>
            <div class="date-info">
                <h2><?php echo htmlspecialchars($display_date, ENT_QUOTES, 'UTF-8'); ?></h2>
                <span>Diet Plan View</span>
            </div>
            <?php if ($can_go_next): ?>
                <a href="?date=<?php echo htmlspecialchars($next_date, ENT_QUOTES, 'UTF-8'); ?>" style="text-decoration:none;">
                    <button type="button"><i class="fa-solid fa-chevron-right"></i></button>
                </a>
            <?php else: ?>
                <button type="button" disabled style="opacity:0.3;cursor:default;"><i class="fa-solid fa-chevron-right"></i></button>
            <?php endif; ?>
        </div>

        <!-- Breakfast Card -->
        <div class="card meal-card fade-in delay-2">
            <div class="meal-header">
                <div>
                    <h3>Breakfast</h3>
                    <p>Recommended: <?php echo intval(round($rec_kcal * 0.25)); ?>-<?php echo intval(round($rec_kcal * 0.3)); ?> kcal</p>
                </div>
                <div class="meal-total" id="breakfastTotal"><?php echo intval($breakfast_total); ?> <span>kcal</span></div>
            </div>
            <ul class="food-list" id="breakfastList">
                <?php if (empty($meals['breakfast'])): ?>
                    <li class="food-item">
                        <span class="food-name">No foods logged</span>
                    </li>
                <?php else: ?>
                    <?php foreach ($meals['breakfast'] as $item): ?>
                        <li class="food-item">
                            <span class="food-name"><?php echo htmlspecialchars($item['food_item'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="food-kcal"><?php echo intval($item['kcal']); ?> kcal</span>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
            <button class="btn-add-food" data-meal="breakfast" <?php echo !$is_today ? 'disabled style="opacity:0.4;cursor:not-allowed;"' : ''; ?>><i class="fa-solid fa-plus"></i> Add Food</button>
        </div>

        <!-- Lunch Card -->
        <div class="card meal-card fade-in delay-3">
            <div class="meal-header">
                <div>
                    <h3>Lunch</h3>
                    <p>Recommended: <?php echo intval(round($rec_kcal * 0.35)); ?>-<?php echo intval(round($rec_kcal * 0.4)); ?> kcal</p>
                </div>
                <div class="meal-total" id="lunchTotal"><?php echo intval($lunch_total); ?> <span>kcal</span></div>
            </div>
            <ul class="food-list" id="lunchList">
                <?php if (empty($meals['lunch'])): ?>
                    <li class="food-item">
                        <span class="food-name">No foods logged</span>
                    </li>
                <?php else: ?>
                    <?php foreach ($meals['lunch'] as $item): ?>
                        <li class="food-item">
                            <span class="food-name"><?php echo htmlspecialchars($item['food_item'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="food-kcal"><?php echo intval($item['kcal']); ?> kcal</span>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
            <button class="btn-add-food" data-meal="lunch" <?php echo !$is_today ? 'disabled style="opacity:0.4;cursor:not-allowed;"' : ''; ?>><i class="fa-solid fa-plus"></i> Add Food</button>
        </div>

        <!-- Dinner Card -->
        <div class="card meal-card fade-in delay-4">
            <div class="meal-header">
                <div>
                    <h3>Dinner</h3>
                    <p>Recommended: <?php echo intval(round($rec_kcal * 0.25)); ?>-<?php echo intval(round($rec_kcal * 0.35)); ?> kcal</p>
                </div>
                <div class="meal-total" id="dinnerTotal"><?php echo intval($dinner_total); ?> <span>kcal</span></div>
            </div>
            <ul class="food-list" id="dinnerList">
                <?php if (empty($meals['dinner'])): ?>
                    <li class="food-item">
                        <span class="food-name">No foods logged</span>
                    </li>
                <?php else: ?>
                    <?php foreach ($meals['dinner'] as $item): ?>
                        <li class="food-item">
                            <span class="food-name"><?php echo htmlspecialchars($item['food_item'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="food-kcal"><?php echo intval($item['kcal']); ?> kcal</span>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
            <button class="btn-add-food" data-meal="dinner" <?php echo !$is_today ? 'disabled style="opacity:0.4;cursor:not-allowed;"' : ''; ?>><i class="fa-solid fa-plus"></i> Add Food</button>
        </div>

        <!-- Snack Card -->
        <div class="card meal-card fade-in delay-4">
            <div class="meal-header">
                <div>
                    <h3>Snacks</h3>
                    <p>Recommended: <?php echo intval(round($rec_kcal * 0.10)); ?>-<?php echo intval(round($rec_kcal * 0.15)); ?> kcal</p>
                </div>
                <div class="meal-total" id="snackTotal"><?php echo intval($calc_meal_total($meals['snack'])); ?> <span>kcal</span></div>
            </div>
            <ul class="food-list" id="snackList">
                <?php if (empty($meals['snack'])): ?>
                    <li class="food-item">
                        <span class="food-name">No snacks logged</span>
                    </li>
                <?php else: ?>
                    <?php foreach ($meals['snack'] as $item): ?>
                        <li class="food-item">
                            <span class="food-name"><?php echo htmlspecialchars($item['food_item'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="food-kcal"><?php echo intval($item['kcal']); ?> kcal</span>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
            <button class="btn-add-food" data-meal="snack" <?php echo !$is_today ? 'disabled style="opacity:0.4;cursor:not-allowed;"' : ''; ?>><i class="fa-solid fa-plus"></i> Add Snack</button>
        </div>

    </div>

    <!-- Right Column: Stats -->
    <div class="stats-column">
        
        <!-- Daily Progress Card -->
        <div class="card fade-in delay-1">
            <h3 class="card-title">Daily Progress</h3>
            
            <div class="progress-circle-container">
                <svg viewBox="0 0 36 36" class="circular-chart">
                    <path class="circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                    <!-- stroke-dasharray dynamically updated via JS below -->
                    <path class="circle" id="nutritionCircle" stroke-dasharray="0, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                </svg>
                <div class="chart-text">
                    <span class="val" id="dailyKcal"><?php echo number_format($total_kcal); ?></span>
                    <span class="lbl">of <?php echo number_format($rec_kcal); ?> kcal</span>
                </div>
            </div>

            <div class="stat-row">
                <span class="stat-label">Remaining</span>
                <span class="stat-val blue" id="remainingKcal"><?php echo number_format($remaining_kcal); ?> kcal</span>
            </div>
            <div class="stat-row">
                <span class="stat-label">Burned (Active)</span>
                <span class="stat-val" id="burnedKcal"><?php echo number_format($total_burned); ?> kcal</span>
            </div>
        </div>

        <!-- Macronutrients Card -->
        <div class="card fade-in delay-2">
            <h3 class="card-title">Macronutrients</h3>
            
            <div class="macro-item">
                <div class="macro-header">
                    <span>Protein</span>
                    <span><span id="proteinVal"><?php echo intval($total_protein); ?></span>g / <?php echo intval($rec_protein); ?>g</span>
                </div>
                <div class="macro-bar-bg">
                    <div class="macro-bar-fill" id="proteinFill" style="width: 0%;"></div>
                </div>
            </div>

            <div class="macro-item">
                <div class="macro-header">
                    <span>Carbohydrates</span>
                    <span><span id="carbsVal"><?php echo intval($total_carbs); ?></span>g / <?php echo intval($rec_carbs); ?>g</span>
                </div>
                <div class="macro-bar-bg">
                    <div class="macro-bar-fill" id="carbsFill" style="width: 0%; background-color: var(--primary-blue);"></div>
                </div>
            </div>

            <div class="macro-item">
                <div class="macro-header">
                    <span>Fats</span>
                    <span><span id="fatsVal"><?php echo intval($total_fats); ?></span>g / <?php echo intval($rec_fats); ?>g</span>
                </div>
                <div class="macro-bar-bg">
                    <div class="macro-bar-fill" id="fatsFill" style="width: 0%;"></div>
                </div>
            </div>
        </div>

        <!-- Hydration Status Card -->
        <div class="card fade-in delay-3">
            <div class="hydration-header">
                <h3>Hydration Status</h3>
                <span id="waterText"><?php echo htmlspecialchars($water_liters, ENT_QUOTES, 'UTF-8'); ?>L / <?php echo htmlspecialchars($rec_water_liters, ENT_QUOTES, 'UTF-8'); ?>L</span>
            </div>
            
            <div class="hydration-tracker">
                <div class="drops-container" id="dropContainer">
                    <?php for ($i = 1; $i <= 8; $i++): ?>
                        <i class="fa-solid fa-droplet drop-icon <?php echo ($i <= $water_drops_filled) ? 'filled' : ''; ?>"></i>
                    <?php endfor; ?>
                </div>
                <div style="display:flex;gap:0.5rem;align-items:center;">
                    <?php if ($is_today && $water_drops_filled > 0): ?>
                    <button class="btn-quick-add" onclick="removeWaterDrop()" title="Undo last 250ml"
                        style="background:var(--input-bg);color:var(--text-medium);box-shadow:none;font-size:1rem;">
                        <i class="fa-solid fa-minus"></i>
                    </button>
                    <?php endif; ?>
                    <?php if ($is_today): ?>
                    <button class="btn-quick-add" onclick="addWaterDrop()">
                        <i class="fa-solid fa-plus"></i>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Floating Action Button -->
<button class="fab fade-in delay-4" title="Log Meal" data-meal="breakfast" <?php echo !$is_today ? 'disabled style="opacity:0.4;cursor:not-allowed;"' : ''; ?>>
    <i class="fa-solid fa-plus"></i>
</button>

<!-- Meal Modal -->
<div class="modal-overlay" id="mealModal">
    <div class="meal-modal">
        <div class="modal-header">
            <h3 id="modalTitle">Log Meal</h3>
            <button class="modal-close" type="button" id="closeMealModal"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="mealForm">
            <input type="hidden" id="mealType" name="meal_type" value="breakfast">
            <div class="modal-body">
                <div class="input-group">
                    <label for="foodItem">Food Item</label>
                    <input type="text" id="foodItem" name="food_item" maxlength="120" placeholder="e.g. Oatmeal with banana" required>
                </div>
                <div class="input-group">
                    <label for="mealKcal">Calories (kcal)</label>
                    <input type="number" id="mealKcal" name="kcal" min="0" max="10000" placeholder="e.g. 320" required>
                </div>
                <div class="form-row">
                    <div class="input-group">
                        <label for="mealProtein">Protein (g)</label>
                        <input type="number" id="mealProtein" name="protein_g" min="0" max="500" placeholder="0">
                    </div>
                    <div class="input-group">
                        <label for="mealCarbs">Carbs (g)</label>
                        <input type="number" id="mealCarbs" name="carbs_g" min="0" max="500" placeholder="0">
                    </div>
                    <div class="input-group">
                        <label for="mealFats">Fats (g)</label>
                        <input type="number" id="mealFats" name="fats_g" min="0" max="500" placeholder="0">
                    </div>
                </div>
                <div class="input-group">
                    <label for="mealNotes">Notes (optional)</label>
                    <textarea id="mealNotes" name="notes" rows="3" maxlength="255" placeholder="Add any notes"></textarea>
                </div>
                <div class="modal-error" id="mealError"></div>
                <div class="modal-actions">
                    <button type="button" class="btn-modal-secondary" id="cancelMeal">Cancel</button>
                    <button type="submit" class="btn-modal-primary" id="saveMeal">Save Meal</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Scripts -->
<script>
    document.addEventListener("DOMContentLoaded", () => {
        // 1. Entrance Animations
        setTimeout(() => {
            const elements = document.querySelectorAll('.fade-in');
            elements.forEach(el => el.classList.add('visible'));
        }, 100);

        // 2. Animate Circular Chart 
        setTimeout(() => {
            const circle = document.getElementById('nutritionCircle');
            if (circle) {
                circle.style.strokeDasharray = "<?php echo intval($progress_circle_pct); ?>, 100";
            }
        }, 500);

        // 3. Animate Macro Bars
        setTimeout(() => {
            const pFill = document.getElementById('proteinFill');
            const cFill = document.getElementById('carbsFill');
            const fFill = document.getElementById('fatsFill');

            if (pFill) pFill.style.width = "<?php echo intval($protein_pct); ?>%";
            if (cFill) cFill.style.width = "<?php echo intval($carbs_pct); ?>%";
            if (fFill) fFill.style.width = "<?php echo intval($fats_pct); ?>%";
        }, 600);
    });

    // 4. Hydration Quick Add Logic with AJAX
    let currentDrops = <?php echo intval($water_drops_filled); ?>;
    const maxDrops = 8;
    const recommendedLiters = '<?php echo htmlspecialchars($rec_water_liters, ENT_QUOTES, "UTF-8"); ?>';
    let isSubmitting = false;

    function addWaterDrop() {
        if (currentDrops < maxDrops && !isSubmitting) {
            isSubmitting = true;
            
            // Post 250ml to backend API
            fetch('<?php echo APP_URL; ?>/api/log_hydration.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': getCsrf()
                },
                body: JSON.stringify({ amount_ml: 250 })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const drops = document.querySelectorAll('#dropContainer .drop-icon');
                    if (drops[currentDrops]) drops[currentDrops].classList.add('filled');
                    currentDrops++;
                    
                    // Update Text seamlessly
                    const newLiters = (data.total_ml / 1000).toFixed(1);
                    document.getElementById('waterText').innerText = `${newLiters}L / ${recommendedLiters}L`;
                } else {
                    showToast(data.error || 'Failed to update hydration.', 'error');
                }
            })
            .catch(error => {
                console.error('Hydration Error:', error);
                showToast('A network error occurred.', 'error');
            })
            .finally(() => {
                isSubmitting = false;
            });
        } else if (currentDrops >= maxDrops) {
            showToast('Great job! You reached your visual daily hydration goal.');
        }
    }

    function removeWaterDrop() {
        if (currentDrops <= 0 || isSubmitting) return;
        isSubmitting = true;
        fetch('<?php echo APP_URL; ?>/api/log_hydration.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrf()
            },
            body: JSON.stringify({ amount_ml: -250 })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                currentDrops = Math.max(0, currentDrops - 1);
                const drops = document.querySelectorAll('#dropContainer .drop-icon');
                drops.forEach((d, i) => {
                    i < currentDrops ? d.classList.add('filled') : d.classList.remove('filled');
                });
                const newLiters = (data.total_ml / 1000).toFixed(1);
                document.getElementById('waterText').innerText = `${newLiters}L / ${recommendedLiters}L`;
                showToast('Removed 250ml.');
            } else {
                showToast(data.error || 'Could not remove.', 'error');
            }
        })
        .catch(() => showToast('Network error.', 'error'))
        .finally(() => { isSubmitting = false; });
    }

    // 5. Meal Logging Modal + AJAX
    const mealModal = document.getElementById('mealModal');
    const mealForm = document.getElementById('mealForm');
    const mealTypeInput = document.getElementById('mealType');
    const modalTitle = document.getElementById('modalTitle');
    const mealError = document.getElementById('mealError');
    const saveMealBtn = document.getElementById('saveMeal');
    const addMealButtons = document.querySelectorAll('.btn-add-food');
    const fabButton = document.querySelector('.fab');

    const dailyKcalEl = document.getElementById('dailyKcal');
    const remainingKcalEl = document.getElementById('remainingKcal');
    const proteinValEl = document.getElementById('proteinVal');
    const carbsValEl = document.getElementById('carbsVal');
    const fatsValEl = document.getElementById('fatsVal');

    const totalBurned = <?php echo json_encode((float)$total_burned); ?>;
    const recKcal = <?php echo json_encode((int)$rec_kcal); ?>;
    const recProtein = <?php echo json_encode((int)$rec_protein); ?>;
    const recCarbs = <?php echo json_encode((int)$rec_carbs); ?>;
    const recFats = <?php echo json_encode((int)$rec_fats); ?>;

    let totalKcal = <?php echo json_encode((float)$total_kcal); ?>;
    let totalProtein = <?php echo json_encode((float)$total_protein); ?>;
    let totalCarbs = <?php echo json_encode((float)$total_carbs); ?>;
    let totalFats = <?php echo json_encode((float)$total_fats); ?>;

    const mealTotals = {
        breakfast: <?php echo json_encode((float)$breakfast_total); ?>,
        lunch: <?php echo json_encode((float)$lunch_total); ?>,
        dinner: <?php echo json_encode((float)$dinner_total); ?>,
        snack: <?php echo json_encode((float)$calc_meal_total($meals['snack'])); ?>,
    };

    function openMealModal(mealType) {
        mealForm.reset();
        mealTypeInput.value = mealType;
        modalTitle.textContent = `Log ${mealType.charAt(0).toUpperCase() + mealType.slice(1)}`;
        mealError.textContent = '';
        mealModal.classList.add('active');
        document.getElementById('foodItem').focus();
    }

    function closeMealModal() {
        mealModal.classList.remove('active');
    }

    addMealButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            openMealModal(btn.dataset.meal || 'breakfast');
        });
    });

    if (fabButton) {
        fabButton.addEventListener('click', () => {
            openMealModal(fabButton.dataset.meal || 'breakfast');
        });
    }

    document.getElementById('closeMealModal').addEventListener('click', closeMealModal);
    document.getElementById('cancelMeal').addEventListener('click', closeMealModal);

    mealModal.addEventListener('click', (event) => {
        if (event.target === mealModal) {
            closeMealModal();
        }
    });

    function updateMacroBars() {
        const proteinPct = Math.min(100, Math.round((totalProtein / Math.max(1, recProtein)) * 100));
        const carbsPct = Math.min(100, Math.round((totalCarbs / Math.max(1, recCarbs)) * 100));
        const fatsPct = Math.min(100, Math.round((totalFats / Math.max(1, recFats)) * 100));

        document.getElementById('proteinFill').style.width = `${proteinPct}%`;
        document.getElementById('carbsFill').style.width = `${carbsPct}%`;
        document.getElementById('fatsFill').style.width = `${fatsPct}%`;
    }

    function updateProgressCircle() {
        const circle = document.getElementById('nutritionCircle');
        const progressPct = Math.min(100, Math.round((totalKcal / Math.max(1, recKcal)) * 100));
        if (circle) {
            circle.style.strokeDasharray = `${progressPct}, 100`;
        }
    }

    function updateTotalsUI() {
        dailyKcalEl.textContent = Math.round(totalKcal).toLocaleString();
        const remaining = Math.max(0, Math.round(recKcal - totalKcal + totalBurned));
        remainingKcalEl.textContent = `${remaining.toLocaleString()} kcal`;
        proteinValEl.textContent = Math.round(totalProtein).toLocaleString();
        carbsValEl.textContent = Math.round(totalCarbs).toLocaleString();
        fatsValEl.textContent = Math.round(totalFats).toLocaleString();
        updateMacroBars();
        updateProgressCircle();
    }

    mealForm.addEventListener('submit', (event) => {
        event.preventDefault();
        if (saveMealBtn.disabled) {
            return;
        }

        mealError.textContent = '';
        saveMealBtn.disabled = true;
        saveMealBtn.textContent = 'Saving...';

        const payload = {
            meal_type: mealTypeInput.value,
            food_item: document.getElementById('foodItem').value.trim(),
            kcal: Number(document.getElementById('mealKcal').value),
            protein_g: Number(document.getElementById('mealProtein').value || 0),
            carbs_g: Number(document.getElementById('mealCarbs').value || 0),
            fats_g: Number(document.getElementById('mealFats').value || 0),
            notes: document.getElementById('mealNotes').value.trim()
        };

        if (!payload.food_item || payload.kcal <= 0) {
            mealError.textContent = 'Please enter a food item and calories.';
            saveMealBtn.disabled = false;
            saveMealBtn.textContent = 'Save Meal';
            return;
        }

        fetch('<?php echo APP_URL; ?>/api/log_meal.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrf()
            },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                mealError.textContent = data.error || 'Unable to save meal.';
                return;
            }

            totalKcal = data.daily_totals.kcal;
            totalProtein = data.daily_totals.protein;
            totalCarbs = data.daily_totals.carbs;
            totalFats = data.daily_totals.fats;

            const listMap = {
                breakfast: document.getElementById('breakfastList'),
                lunch: document.getElementById('lunchList'),
                dinner: document.getElementById('dinnerList'),
                snack: document.getElementById('snackList'),
            };

            const targetList = listMap[payload.meal_type];
            if (targetList) {
                const placeholder = targetList.querySelector('.food-name');
                if (placeholder && (placeholder.textContent === 'No foods logged' || placeholder.textContent === 'No snacks logged')) {
                    targetList.innerHTML = '';
                }

                const item = document.createElement('li');
                item.className = 'food-item';
                item.innerHTML = `
                    <span class="food-name">${payload.food_item}</span>
                    <span class="food-kcal">${Math.round(payload.kcal)} kcal</span>
                `;
                targetList.appendChild(item);
            }

            if (mealTotals[payload.meal_type] !== undefined) {
                mealTotals[payload.meal_type] += payload.kcal;
                const totalMap = {
                    breakfast: document.getElementById('breakfastTotal'),
                    lunch: document.getElementById('lunchTotal'),
                    dinner: document.getElementById('dinnerTotal'),
                    snack: document.getElementById('snackTotal'),
                };
                const totalEl = totalMap[payload.meal_type];
                if (totalEl) {
                    totalEl.innerHTML = `${Math.round(mealTotals[payload.meal_type])} <span>kcal</span>`;
                }
            }

            updateTotalsUI();
            closeMealModal();
        })
        .catch(() => {
            mealError.textContent = 'A network error occurred. Please try again.';
        })
        .finally(() => {
            saveMealBtn.disabled = false;
            saveMealBtn.textContent = 'Save Meal';
        });
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
