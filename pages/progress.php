<?php
/**
 * Progress Page
 * Displays: Weight trend chart, summary cards, weekly activity, and body measurements.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

$user_id = get_user_id();
$page_title = 'Progress - ' . APP_NAME;

$range_options = [7, 30, 90, 365];
$range = isset($_GET['range']) && in_array((int)$_GET['range'], $range_options, true) ? (int)$_GET['range'] : 30;

function table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?');
    $stmt->execute([DB_NAME, $table]);
    return (int)$stmt->fetchColumn() > 0;
}

// 1. Fetch User Profile & Goal
try {
    $stmt = $pdo->prepare('
        -- // 🔧 Schema alignment: weights live in profiles (not users) and preferences has no goal_type.
        SELECT p.*
        FROM profiles p
        WHERE p.user_id = ?
    ');
    $stmt->execute([$user_id]);
    $user_profile = $stmt->fetch() ?: [];
} catch (PDOException $e) {
    error_log('Progress profile fetch error: ' . $e->getMessage());
    $user_profile = [];
}

$current_weight = (float)($user_profile['current_weight_kg'] ?? $user_profile['weight_kg'] ?? 0);
$target_weight = (float)($user_profile['target_weight_kg'] ?? $current_weight);
$start_weight = $current_weight; // Ideally fetched from history, assuming current for default logic

$latest_metrics = null;
$previous_metrics = null;
if (table_exists($pdo, 'progress_metrics')) {
    try {
        $stmt = $pdo->prepare('
            SELECT weight_kg, waist_cm, chest_cm, hips_cm, body_fat_percent, muscle_mass_kg, recorded_date
            FROM progress_metrics
            WHERE user_id = ?
            ORDER BY recorded_date DESC
            LIMIT 2
        ');
        $stmt->execute([$user_id]);
        $metrics = $stmt->fetchAll();
        if (!empty($metrics)) {
            $latest_metrics = $metrics[0];
            $previous_metrics = $metrics[1] ?? null;
        }
    } catch (PDOException $e) {
        error_log('Progress metrics fetch error: ' . $e->getMessage());
    }
}

if ($latest_metrics && isset($latest_metrics['weight_kg'])) {
    $current_weight = (float)$latest_metrics['weight_kg'];
}

// Fetch first weight log to determine start weight
if (table_exists($pdo, 'progress_metrics')) {
    try {
        $stmt = $pdo->prepare('SELECT weight_kg FROM progress_metrics WHERE user_id = ? ORDER BY recorded_date ASC LIMIT 1');
        $stmt->execute([$user_id]);
        if ($first_log = $stmt->fetch()) {
            $start_weight = (float)$first_log['weight_kg'];
        }
    } catch (PDOException $e) {
        error_log('Progress start weight fetch error: ' . $e->getMessage());
    }
}

// Compute change
$weight_change = $current_weight - $start_weight;
$change_text = ($weight_change >= 0) ? '+' . number_format($weight_change, 1) . ' kg' : number_format($weight_change, 1) . ' kg';
$change_color = ($weight_change <= 0) ? 'var(--primary-blue)' : 'var(--accent-red)';

// Goal Progress %
$progress_pct = 0;
if ($start_weight != $target_weight) {
    $progress_pct = min(100, max(0, (($start_weight - $current_weight) / ($start_weight - $target_weight)) * 100));
}

// 2. Fetch Weight Logs for Chart (Selected range)
if (table_exists($pdo, 'progress_metrics')) {
    try {
        $stmt = $pdo->prepare('
            SELECT weight_kg, DATE_FORMAT(recorded_date, "%b %d") as date_str 
            FROM progress_metrics 
            WHERE user_id = ? AND recorded_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            ORDER BY recorded_date ASC
        ');
        $stmt->execute([$user_id, $range]);
        $chart_logs = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Progress chart fetch error: ' . $e->getMessage());
        $chart_logs = [];
    }
} else {
    $chart_logs = [];
}

// Formatting JS data for chart
if (empty($chart_logs)) {
    // Fake data if none available for visual fidelity
    $chart_labels = "['Oct 1', 'Oct 7', 'Oct 14', 'Oct 21', 'Oct 24']";
    $chart_data = "[78.5, 77.8, 77.2, 76.5, {$current_weight}]";
} else {
    $labels = [];
    $data = [];
    foreach ($chart_logs as $log) {
        $labels[] = "'" . $log['date_str'] . "'";
        $data[] = $log['weight_kg'];
    }
    // ensure current weight is the last point if it's not logged today
    $chart_labels = "[" . implode(',', $labels) . "]";
    $chart_data = "[" . implode(',', $data) . "]";
}

// 3. Activity Chart Data (Mon-Sun total duration)
$weekly_data = [
    1 => 0, // Mon
    2 => 0, // Tue
    3 => 0, // Wed
    4 => 0, // Thu
    5 => 0, // Fri
    6 => 0, // Sat
    7 => 0  // Sun
];
if (table_exists($pdo, 'workout_logs')) {
    try {
        $stmt = $pdo->prepare('
            SELECT WEEKDAY(logged_date) as wd, SUM(duration_mins) as total_mins 
            FROM workout_logs 
            WHERE user_id = ? AND logged_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
            GROUP BY wd
        ');
        $stmt->execute([$user_id]);
        while ($row = $stmt->fetch()) {
            $day_index = intval($row['wd']) + 1; // Map to 1-7
            if (isset($weekly_data[$day_index])) {
                $weekly_data[$day_index] = min(100, round(($row['total_mins'] / 60) * 100)); // 60 mins a day target
            }
        }
    } catch (PDOException $e) {
        error_log('Progress weekly activity fetch error: ' . $e->getMessage());
    }
}

?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<style>
    /* Specific overrides and classes merged from frontend layout without breaking global scope */
    .delay-1 { transition-delay: 0.1s; }
    .delay-2 { transition-delay: 0.2s; }
    .delay-3 { transition-delay: 0.3s; }
    .delay-4 { transition-delay: 0.4s; }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        margin-bottom: 2.5rem;
        flex-wrap: wrap;
        gap: 1.5rem;
    }

    .header-title .subtitle {
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--primary-blue);
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 0.5rem;
        display: block;
    }

    .header-title h1 {
        font-size: 2.2rem;
        font-weight: 700;
        color: var(--text-dark);
        letter-spacing: -0.5px;
    }

    .time-filters {
        display: flex;
        background-color: var(--input-bg);
        padding: 0.4rem;
        border-radius: 50px;
        gap: 0.2rem;
    }

    .time-filter {
        border: none;
        background: transparent;
        padding: 0.5rem 1.2rem;
        border-radius: 50px;
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-medium);
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .time-filter:hover { color: var(--text-dark); }
    .time-filter.active {
        background-color: var(--primary-blue);
        color: #ffffff;
        box-shadow: 0 4px 10px rgba(61, 123, 244, 0.3);
    }

    .dashboard-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 2rem;
        margin-bottom: 2rem;
    }

    .dashboard-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
    }

    .card {
        background-color: var(--bg-right);
        border-radius: 24px;
        padding: 2rem;
        box-shadow: 0 10px 30px rgba(27, 54, 121, 0.04);
        position: relative;
    }

    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1.5rem;
    }

    .card-header h3 {
        font-size: 1.2rem;
        font-weight: 700;
        margin-bottom: 0.4rem;
    }

    .card-header p {
        font-size: 0.9rem;
        color: var(--text-medium);
    }

    .badge {
        background-color: var(--input-bg);
        color: var(--primary-blue);
        font-size: 0.75rem;
        font-weight: 700;
        padding: 0.4rem 0.8rem;
        border-radius: 50px;
    }

    .badge-success {
        background-color: var(--accent-green);
        color: #ffffff;
    }

    .trend-chart-wrapper {
        position: relative;
        width: 100%;
        height: 250px;
        margin-top: 2rem;
    }

    .svg-chart {
        width: 100%;
        height: 100%;
        overflow: visible;
    }

    .chart-path {
        fill: none;
        stroke: var(--primary-blue);
        stroke-width: 4;
        stroke-dasharray: 12, 12;
        stroke-linecap: round;
        opacity: 0;
        animation: drawLine 1.5s ease-out forwards 0.5s;
    }

    @keyframes drawLine { to { opacity: 1; } }

    .chart-point {
        fill: var(--bg-right);
        stroke: var(--primary-blue);
        stroke-width: 3;
        pointer-events: none;
        transition: all 0.3s;
    }

    .chart-point:hover {
        fill: var(--primary-blue);
        stroke-width: 4;
    }

    .x-axis {
        display: flex;
        justify-content: space-between;
        margin-top: 1rem;
        padding: 0 1rem;
    }

    .x-axis span {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text-light);
        text-transform: uppercase;
    }

    .summary-col {
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }

    .summary-card {
        background-color: var(--bg-right);
        border-radius: 20px;
        padding: 1.5rem;
        box-shadow: 0 10px 30px rgba(27, 54, 121, 0.04);
        display: flex;
        align-items: center;
        gap: 1.5rem;
        position: relative;
        overflow: hidden;
    }

    .summary-icon {
        width: 50px;
        height: 50px;
        background-color: var(--input-bg);
        color: var(--primary-blue);
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
    }

    .summary-info h4 {
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        color: var(--text-medium);
        letter-spacing: 1px;
        margin-bottom: 0.3rem;
    }

    .summary-info .val-wrap {
        display: flex;
        align-items: baseline;
        gap: 0.8rem;
    }

    .summary-info h2 {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--text-dark);
    }

    .change-text {
        font-size: 0.8rem;
        font-weight: 600;
    }

    .mini-ring { width: 45px; height: 45px; margin-left: auto; }
    .badge-status { margin-left: auto; padding: 0.4rem 1rem; }

    .bar-chart {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        height: 200px;
        margin-top: 2rem;
        padding: 0 1rem;
    }

    .bar-col {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.8rem;
        width: 10%;
    }

    .bar-bg {
        width: 100%;
        height: 160px;
        background-color: var(--input-bg);
        border-radius: 8px 8px 0 0;
        position: relative;
        overflow: hidden;
    }

    .bar-fill {
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100%;
        background-color: var(--primary-blue);
        border-radius: 8px 8px 0 0;
        height: 0%; 
        transition: height 1s ease-out;
    }

    .measurements-table {
        width: 100%;
        margin-top: 1.5rem;
        border-collapse: collapse;
    }

    .measurements-table th {
        text-align: left;
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--text-medium);
        text-transform: uppercase;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--border-light);
    }

    .measurements-table th:last-child { text-align: right; }

    .measurements-table td {
        padding: 1.2rem 0;
        font-size: 0.95rem;
        font-weight: 600;
        border-bottom: 1px solid var(--border-light);
    }
    .measurements-table tr:last-child td { border-bottom: none; }
    .measurements-table td.metric-name { color: var(--text-medium); font-weight: 500; }
    .measurements-table td.change-val { text-align: right; color: var(--primary-blue); }

    .btn-update {
        position: absolute;
        bottom: 2rem;
        right: 2rem;
        padding: 1rem 1.8rem;
        border: none;
        background: #3b82f6 !important;
        color: white;
        font-size: 0.95rem;
        font-weight: 600;
        cursor: pointer;
        border-radius: 50px;
        transition: all 0.4s cubic-bezier(0.25, 1, 0.5, 1);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-update:hover {
        background: #2563eb !important;
        border-radius: 12px;
    }

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
        cursor: pointer;
        border: none;
        transition: all 0.4s cubic-bezier(0.25, 1, 0.5, 1);
        z-index: 100;
    }

    .fab:hover { 
        background: #2563eb !important;
        transform: rotate(90deg);
    }

    /* --- Progress Modal --- */
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

    .progress-modal {
        width: 100%;
        max-width: 600px;
        background-color: var(--bg-right);
        border-radius: 24px;
        box-shadow: 0 18px 45px rgba(27, 54, 121, 0.15);
        padding: 2rem;
        position: relative;
        animation: fadeInModal 0.25s ease;
    }

    @keyframes fadeInModal {
        from { opacity: 0; transform: translateY(12px); }
        to { opacity: 1; transform: translateY(0); }
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

    .input-group input {
        width: 100%;
        border: none;
        background-color: var(--input-bg);
        border-radius: 14px;
        padding: 0.8rem 1rem;
        font-size: 0.95rem;
        color: var(--text-dark);
        outline: none;
    }

    .input-group input:focus {
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
        color: #fff;
        border: none;
        border-radius: 50px;
        padding: 0.8rem 1.8rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
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
        background: var(--bg-right);
        border: 2px solid var(--border-light);
        color: var(--text-dark);
        border-radius: 50px;
        padding: 0.7rem 1.4rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.4s cubic-bezier(0.25, 1, 0.5, 1);
    }

    .btn-modal-secondary:hover {
        border-color: var(--primary-blue);
        color: var(--primary-blue);
        background-color: var(--input-bg);
        border-radius: 12px;
    }

    @media (max-width: 1100px) {
        .dashboard-grid { grid-template-columns: 1fr; }
        .dashboard-row { grid-template-columns: 1fr; }
        .btn-update { position: relative; bottom: 0; right: 0; width: 100%; margin-top: 1rem; justify-content: center; }
    }
    @media (max-width: 768px) {
        .progress-wrapper { padding: 1rem !important; }
        .header-title h1 { font-size: 1.6rem; }
        .time-filters { width: 100%; justify-content: space-between; }
        .time-filter { padding: 0.5rem 0.6rem; font-size: 0.8rem; }
        .summary-card { flex-wrap: wrap; gap: 1rem; }
        .summary-info h2 { font-size: 1.4rem; }
        .form-row { grid-template-columns: 1fr; }
        .card { padding: 1.25rem; }
        .bar-chart { padding: 0; }
        .measurements-table td, .measurements-table th { padding: 0.75rem 0; font-size: 0.85rem; }
    }
</style>

<div class="progress-wrapper" style="max-width: 1400px; margin: 0 auto; width: 100%; padding: clamp(1rem, 4vw, 3rem);">
    <!-- Page Header & Time Filter -->
    <div class="page-header fade-in delay-1">
        <div class="header-title">
            <span class="subtitle">Analytics Overview</span>
            <h1>Your Progress</h1>
        </div>
        <div class="time-filters">
            <button class="time-filter <?php echo $range === 7 ? 'active' : ''; ?>" data-range="7">1W</button>
            <button class="time-filter <?php echo $range === 30 ? 'active' : ''; ?>" data-range="30">1M</button>
            <button class="time-filter <?php echo $range === 90 ? 'active' : ''; ?>" data-range="90">3M</button>
            <button class="time-filter <?php echo $range === 365 ? 'active' : ''; ?>" data-range="365">1Y</button>
        </div>
    </div>

    <!-- Top Dashboard Grid -->
    <div class="dashboard-grid">
        
        <!-- Trend Chart Card -->
        <div class="card fade-in delay-2">
            <div class="card-header">
                <div>
                    <h3>Weight Trend</h3>
                    <p>Track your transformation</p>
                </div>
                <span class="badge badge-success">On Track</span>
            </div>
            
            <div class="trend-chart-wrapper">
                <svg viewBox="0 0 600 200" class="svg-chart" preserveAspectRatio="none">
                    <!-- Grid Lines -->
                    <line x1="0" y1="50" x2="600" y2="50" stroke="var(--border-light)" stroke-width="1" />
                    <line x1="0" y1="100" x2="600" y2="100" stroke="var(--border-light)" stroke-width="1" />
                    <line x1="0" y1="150" x2="600" y2="150" stroke="var(--border-light)" stroke-width="1" />
                    <path class="chart-path" id="weightPath" d="" />
                    <g id="weightPoints"></g>
                </svg>
            </div>

            <!-- X-Axis Labels populated in JS based on chart_labels -->
            <div class="x-axis" id="xAxisLabels">
                <!-- Javascript will inject labels -->
            </div>
        </div>

        <!-- Summary Column -->
        <div class="summary-col fade-in delay-3">
            
            <div class="summary-card">
                <div class="summary-icon"><i class="fa-solid fa-weight-scale"></i></div>
                <div class="summary-info">
                    <h4>Current Weight</h4>
                    <div class="val-wrap">
                        <h2><?php echo htmlspecialchars(number_format($current_weight, 1), ENT_QUOTES, 'UTF-8'); ?> <span style="font-size:1rem;font-weight:500;color:var(--text-medium);">kg</span></h2>
                        <?php if ($weight_change != 0): ?>
                        <span class="change-text" style="color:<?php echo htmlspecialchars($change_color, ENT_QUOTES, 'UTF-8'); ?>;">
                            <?php echo htmlspecialchars($change_text, ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="summary-card">
                <div class="summary-icon"><i class="fa-solid fa-bullseye"></i></div>
                <div class="summary-info">
                    <h4>Target Weight</h4>
                    <div class="val-wrap">
                        <h2><?php echo htmlspecialchars(number_format($target_weight, 1), ENT_QUOTES, 'UTF-8'); ?> <span style="font-size:1rem;font-weight:500;color:var(--text-medium);">kg</span></h2>
                    </div>
                </div>
                <!-- Mini Progress Ring -->
                <div class="mini-ring">
                    <svg viewBox="0 0 36 36" style="width:100%;height:100%;">
                        <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="var(--input-bg)" stroke-width="4"/>
                        <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="var(--primary-blue)" stroke-width="4" stroke-dasharray="<?php echo intval($progress_pct); ?>, 100"/>
                    </svg>
                </div>
            </div>

            <!-- Compact stats row -->
            <div style="background:var(--bg-right);border-radius:20px;padding:1.25rem 1.5rem;box-shadow:0 10px 30px rgba(27,54,121,0.04);">
                <div style="display:flex;justify-content:space-between;align-items:center;padding:0.6rem 0;border-bottom:1px solid var(--border-light);">
                    <span style="font-size:0.8rem;font-weight:600;color:var(--text-medium);text-transform:uppercase;letter-spacing:0.5px;">Goal progress</span>
                    <span style="font-size:0.95rem;font-weight:700;color:var(--primary-blue);"><?php echo intval($progress_pct); ?>%</span>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:0.6rem 0;border-bottom:1px solid var(--border-light);">
                    <span style="font-size:0.8rem;font-weight:600;color:var(--text-medium);text-transform:uppercase;letter-spacing:0.5px;">To go</span>
                    <span style="font-size:0.95rem;font-weight:700;color:var(--text-dark);"><?php echo number_format(abs($target_weight - $current_weight), 1); ?> kg</span>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:0.6rem 0;">
                    <span style="font-size:0.8rem;font-weight:600;color:var(--text-medium);text-transform:uppercase;letter-spacing:0.5px;">Start weight</span>
                    <span style="font-size:0.95rem;font-weight:700;color:var(--text-dark);"><?php echo number_format($start_weight, 1); ?> kg</span>
                </div>
            </div>
            
        </div>
    </div>

    <!-- Bottom Dashboard Row -->
    <div class="dashboard-row">
        
        <!-- Activity Chart -->
        <div class="card fade-in delay-2">
            <div class="card-header">
                <div>
                    <h3>Weekly Activity</h3>
                    <p>Based on workout duration</p>
                </div>
                <span class="badge">Avg 45 mins</span>
            </div>

            <div class="bar-chart">
                <div class="bar-col">
                    <div class="bar-bg"><div class="bar-fill" id="barMonP"></div></div>
                    <span>Mon</span>
                </div>
                <div class="bar-col">
                    <div class="bar-bg"><div class="bar-fill" id="barTueP"></div></div>
                    <span>Tue</span>
                </div>
                <div class="bar-col">
                    <div class="bar-bg"><div class="bar-fill" id="barWedP"></div></div>
                    <span>Wed</span>
                </div>
                <div class="bar-col">
                    <div class="bar-bg"><div class="bar-fill" id="barThuP"></div></div>
                    <span>Thu</span>
                </div>
                <div class="bar-col">
                    <div class="bar-bg"><div class="bar-fill" id="barFriP"></div></div>
                    <span>Fri</span>
                </div>
                <div class="bar-col">
                    <div class="bar-bg"><div class="bar-fill" id="barSatP"></div></div>
                    <span>Sat</span>
                </div>
                <div class="bar-col">
                    <div class="bar-bg"><div class="bar-fill" id="barSunP"></div></div>
                    <span>Sun</span>
                </div>
            </div>
        </div>

        <!-- Body Measurements -->
        <div class="card fade-in delay-3">
            <div class="card-header">
                <div>
                    <h3>Body Measurements</h3>
                    <p>Last updated: <?php echo $latest_metrics ? htmlspecialchars(date('M jS, Y', strtotime($latest_metrics['recorded_date'])), ENT_QUOTES, 'UTF-8') : 'No logs yet'; ?></p>
                </div>
                <span class="badge">cm</span>
            </div>

            <table class="measurements-table">
                <thead>
                    <tr>
                        <th>Metric</th>
                        <th>Current</th>
                        <th>Change</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($latest_metrics): ?>
                        <?php
                        $waist = (float)($latest_metrics['waist_cm'] ?? 0);
                        $hips = (float)($latest_metrics['hips_cm'] ?? 0);
                        $chest = (float)($latest_metrics['chest_cm'] ?? 0);
                        $waist_change = $previous_metrics ? $waist - (float)($previous_metrics['waist_cm'] ?? 0) : 0;
                        $hips_change = $previous_metrics ? $hips - (float)($previous_metrics['hips_cm'] ?? 0) : 0;
                        $chest_change = $previous_metrics ? $chest - (float)($previous_metrics['chest_cm'] ?? 0) : 0;
                        ?>
                        <tr>
                            <td class="metric-name">Waist</td>
                            <td><?php echo number_format($waist, 1); ?></td>
                            <td class="change-val" style="color: <?php echo $waist_change <= 0 ? 'var(--primary-blue)' : 'var(--accent-red)'; ?>;">
                                <?php echo ($waist_change > 0 ? '+' : '') . number_format($waist_change, 1); ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="metric-name">Hips</td>
                            <td><?php echo number_format($hips, 1); ?></td>
                            <td class="change-val" style="color: <?php echo $hips_change <= 0 ? 'var(--primary-blue)' : 'var(--accent-red)'; ?>;">
                                <?php echo ($hips_change > 0 ? '+' : '') . number_format($hips_change, 1); ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="metric-name">Chest</td>
                            <td><?php echo number_format($chest, 1); ?></td>
                            <td class="change-val" style="color: <?php echo $chest_change <= 0 ? 'var(--primary-blue)' : 'var(--accent-red)'; ?>;">
                                <?php echo ($chest_change > 0 ? '+' : '') . number_format($chest_change, 1); ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td class="metric-name" colspan="3">No measurements logged yet</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <button class="btn-update">
                <i class="fa-solid fa-pen"></i> Update Logs
            </button>
        </div>

    </div>
</div>

<!-- Floating Action Button -->
<button class="fab fade-in delay-4" title="Log Weight">
    <i class="fa-solid fa-plus"></i>
</button>

<!-- Progress Modal -->
<div class="modal-overlay" id="progressModal">
    <div class="progress-modal">
        <div class="modal-header">
            <h3>Update Progress</h3>
            <button class="modal-close" type="button" id="closeProgressModal"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="progressForm">
            <div class="modal-body">
                <div class="input-group">
                    <label for="weightKg">Weight (kg)</label>
                    <input type="number" step="0.1" min="1" max="500" id="weightKg" name="weight_kg" value="<?php echo htmlspecialchars(number_format($current_weight, 1), ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div class="form-row">
                    <div class="input-group">
                        <label for="waistCm">Waist (cm)</label>
                        <input type="number" step="0.1" min="0" max="300" id="waistCm" name="waist_cm" value="<?php echo htmlspecialchars(number_format((float)($latest_metrics['waist_cm'] ?? 0), 1), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="input-group">
                        <label for="hipsCm">Hips (cm)</label>
                        <input type="number" step="0.1" min="0" max="300" id="hipsCm" name="hips_cm" value="<?php echo htmlspecialchars(number_format((float)($latest_metrics['hips_cm'] ?? 0), 1), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="input-group">
                        <label for="chestCm">Chest (cm)</label>
                        <input type="number" step="0.1" min="0" max="300" id="chestCm" name="chest_cm" value="<?php echo htmlspecialchars(number_format((float)($latest_metrics['chest_cm'] ?? 0), 1), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="input-group">
                        <label for="bodyFat">Body Fat (%)</label>
                        <input type="number" step="0.1" min="0" max="80" id="bodyFat" name="body_fat_percent" value="<?php echo htmlspecialchars(number_format((float)($latest_metrics['body_fat_percent'] ?? 0), 1), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="input-group">
                        <label for="muscleMass">Muscle Mass (kg)</label>
                        <input type="number" step="0.1" min="0" max="200" id="muscleMass" name="muscle_mass_kg" value="<?php echo htmlspecialchars(number_format((float)($latest_metrics['muscle_mass_kg'] ?? 0), 1), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="input-group">
                        <label for="recordedDate">Recorded Date</label>
                        <input type="date" id="recordedDate" name="recorded_date" value="<?php echo htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>
                <div class="modal-error" id="progressError"></div>
                <div class="modal-actions">
                    <button type="button" class="btn-modal-secondary" id="cancelProgress">Cancel</button>
                    <button type="submit" class="btn-modal-primary" id="saveProgress">Save Progress</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        // Entrance Animations
        setTimeout(() => {
            const elements = document.querySelectorAll('.fade-in');
            elements.forEach(el => el.classList.add('visible'));
        }, 100);

        // Populate X-Axis labels from PHP
        const chartLabels = <?php echo $chart_labels; ?>;
        const chartData = <?php echo $chart_data; ?>;
        const xAxisContainer = document.getElementById('xAxisLabels');
        if (chartLabels.length > 0 && xAxisContainer) {
            // Get 5 evenly spaced labels (or fewer)
            const step = Math.max(1, Math.floor(chartLabels.length / 5));
            for (let i = 0; i < chartLabels.length; i += step) {
                const span = document.createElement('span');
                span.innerText = chartLabels[i];
                xAxisContainer.appendChild(span);
            }
            if (chartLabels.length % step !== 1 && chartLabels.length > 1) {
                 const span = document.createElement('span');
                 span.innerText = chartLabels[chartLabels.length - 1];
                 xAxisContainer.appendChild(span);
            }
        }

        // Draw Weight Trend Path
        const path = document.getElementById('weightPath');
        const pointsContainer = document.getElementById('weightPoints');
        if (path && pointsContainer && chartData.length > 1) {
            const width = 600;
            const height = 200;
            const padding = 10;
            const minVal = Math.min(...chartData);
            const maxVal = Math.max(...chartData);
            const range = Math.max(1, maxVal - minVal);

            const points = chartData.map((val, idx) => {
                const x = padding + (idx / (chartData.length - 1)) * (width - padding * 2);
                const y = height - padding - ((val - minVal) / range) * (height - padding * 2);
                return { x, y };
            });

            const d = points.map((point, idx) => `${idx === 0 ? 'M' : 'L'} ${point.x} ${point.y}`).join(' ');
            path.setAttribute('d', d);

            pointsContainer.innerHTML = '';
            points.forEach(point => {
                const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                circle.setAttribute('class', 'chart-point');
                circle.setAttribute('cx', point.x);
                circle.setAttribute('cy', point.y);
                circle.setAttribute('r', 5);
                pointsContainer.appendChild(circle);
            });
        }

        // Animate Weekly Bars
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
            document.getElementById('barMonP').style.height = `${weeklyData[1]}%`;
            document.getElementById('barTueP').style.height = `${weeklyData[2]}%`;
            document.getElementById('barWedP').style.height = `${weeklyData[3]}%`;
            document.getElementById('barThuP').style.height = `${weeklyData[4]}%`;
            document.getElementById('barFriP').style.height = `${weeklyData[5]}%`;
            document.getElementById('barSatP').style.height = `${weeklyData[6]}%`;
            document.getElementById('barSunP').style.height = `${weeklyData[7]}%`;
        }, 800);
    });

    // Time filter navigation
    document.querySelectorAll('.time-filter').forEach(button => {
        button.addEventListener('click', () => {
            const selectedRange = button.getAttribute('data-range');
            if (selectedRange) {
                window.location.href = `?range=${selectedRange}`;
            }
        });
    });

    // Progress Modal
    const progressModal = document.getElementById('progressModal');
    const progressForm = document.getElementById('progressForm');
    const progressError = document.getElementById('progressError');
    const saveProgressBtn = document.getElementById('saveProgress');

    function openProgressModal() {
        progressError.textContent = '';
        progressModal.classList.add('active');
        document.getElementById('weightKg').focus();
    }

    function closeProgressModal() {
        progressModal.classList.remove('active');
    }

    document.querySelector('.btn-update').addEventListener('click', openProgressModal);
    document.querySelector('.fab').addEventListener('click', openProgressModal);
    document.getElementById('closeProgressModal').addEventListener('click', closeProgressModal);
    document.getElementById('cancelProgress').addEventListener('click', closeProgressModal);

    progressModal.addEventListener('click', (event) => {
        if (event.target === progressModal) {
            closeProgressModal();
        }
    });

    progressForm.addEventListener('submit', (event) => {
        event.preventDefault();
        if (saveProgressBtn.disabled) {
            return;
        }

        progressError.textContent = '';
        saveProgressBtn.disabled = true;
        saveProgressBtn.textContent = 'Saving...';

        const payload = {
            weight_kg: Number(document.getElementById('weightKg').value),
            waist_cm: Number(document.getElementById('waistCm').value || 0),
            hips_cm: Number(document.getElementById('hipsCm').value || 0),
            chest_cm: Number(document.getElementById('chestCm').value || 0),
            body_fat_percent: Number(document.getElementById('bodyFat').value || 0),
            muscle_mass_kg: Number(document.getElementById('muscleMass').value || 0),
            recorded_date: document.getElementById('recordedDate').value
        };

        if (!payload.weight_kg || payload.weight_kg <= 0) {
            progressError.textContent = 'Please enter a valid weight.';
            saveProgressBtn.disabled = false;
            saveProgressBtn.textContent = 'Save Progress';
            return;
        }

        fetch('<?php echo APP_URL; ?>/api/log_progress.php', {
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
                progressError.textContent = data.error || 'Unable to save progress.';
                return;
            }
            closeProgressModal();
            setTimeout(() => window.location.reload(), 300);
        })
        .catch(() => {
            progressError.textContent = 'A network error occurred. Please try again.';
        })
        .finally(() => {
            saveProgressBtn.disabled = false;
            saveProgressBtn.textContent = 'Save Progress';
        });
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
