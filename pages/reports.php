<?php
/**
 * Reports Page
 * Displays: Adherence score, Calorie balance, Top exercise, and Nutrient distribution
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

$user_id = get_user_id();
$page_title = 'Reports - ' . APP_NAME;

function table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?');
    $stmt->execute([DB_NAME, $table]);
    return (int)$stmt->fetchColumn() > 0;
}

// Helper: Calculate sums over a date range
function fetch_sums(PDO $pdo, int $user_id, int $days = 30): array
{
    $diet = ['tk' => 0, 'tp' => 0, 'tc' => 0, 'tf' => 0];
    $workout = ['tkb' => 0, 'tdm' => 0];
    $top_ex = ['exercise_name' => 'General Activity', 'm' => 0];

    if (table_exists($pdo, 'diet_logs')) {
        try {
            $diet_stmt = $pdo->prepare('SELECT SUM(kcal) as tk, SUM(protein_g) as tp, SUM(carbs_g) as tc, SUM(fats_g) as tf FROM diet_logs WHERE user_id = ? AND logged_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)');
            $diet_stmt->execute([$user_id, $days]);
            $diet = $diet_stmt->fetch() ?: $diet;
        } catch (PDOException $e) {
            error_log('Reports diet logs fetch error: ' . $e->getMessage());
        }
    }

    if (table_exists($pdo, 'workout_logs')) {
        try {
            $workout_stmt = $pdo->prepare('SELECT SUM(kcal_burned) as tkb, SUM(duration_mins) as tdm FROM workout_logs WHERE user_id = ? AND logged_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)');
            $workout_stmt->execute([$user_id, $days]);
            $workout = $workout_stmt->fetch() ?: $workout;

            // Fetch Top Exercise
            $top_ex_stmt = $pdo->prepare('SELECT exercise_name, SUM(duration_mins) as m FROM workout_logs WHERE user_id = ? AND logged_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY) GROUP BY exercise_name ORDER BY m DESC LIMIT 1');
            $top_ex_stmt->execute([$user_id, $days]);
            $top_ex = $top_ex_stmt->fetch() ?: $top_ex;
        } catch (PDOException $e) {
            error_log('Reports workout logs fetch error: ' . $e->getMessage());
        }
    }

    return [
        'consumed' => (float)($diet['tk'] ?? 0),
        'protein'  => (float)($diet['tp'] ?? 0),
        'carbs'    => (float)($diet['tc'] ?? 0),
        'fats'     => (float)($diet['tf'] ?? 0),
        'burned'   => (float)($workout['tkb'] ?? 0),
        'activity' => (float)($workout['tdm'] ?? 0),
        'top_ex'   => $top_ex['exercise_name'] ?? 'General Activity',
        'top_ex_m' => (float)($top_ex['m'] ?? 0)
    ];
}

// Ensure default range parameter
$range = isset($_GET['range']) && in_array($_GET['range'], ['7', '30', '90']) ? (int)$_GET['range'] : 30;
$stats = fetch_sums($pdo, $user_id, $range);

// Calculate Adherence (mock logic based on average daily target ~2200 kcal)
$avg_target = 2200 * $range; 
$adherence = 0;
if ($avg_target > 0) {
    // 100% if exactly hit, penalty for over/under. Simplified:
    $ratio = ($stats['consumed'] / max(1, $avg_target));
    $adherence = min(100, max(0, 100 - abs(1 - $ratio) * 100 + ($stats['burned']/max(1, $avg_target)*20)));
    $adherence = round($adherence);
}
if ($stats['consumed'] == 0 && $stats['burned'] == 0) $adherence = 0; // nothing logged

// Calorie Balance logic
$target_budget = 2200 * $range;
$pct_consumed = min(100, round(($stats['consumed'] / max(1, $target_budget)) * 100));
$pct_burned = min(100, round(($stats['burned'] / max(1, $target_budget)) * 100));
$deficit = $stats['consumed'] - $stats['burned'];

// Nutrients Distribution
$total_macros = $stats['protein'] + $stats['carbs'] + $stats['fats'];
$p_pct = 0; $c_pct = 0; $f_pct = 0;
if ($total_macros > 0) {
    $p_pct = round(($stats['protein'] / $total_macros) * 100);
    $c_pct = round(($stats['carbs'] / $total_macros) * 100);
    $f_pct = round(($stats['fats'] / $total_macros) * 100);
}
// SVG stroke-dasharray (calculate arc lengths out of 100)
$p_arc = $p_pct;
$c_arc = $c_pct;
$f_arc = $f_pct;
// Create offsets
$p_off = 0;
$c_off = -($p_arc);
$f_off = -($p_arc + $c_arc);

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = 'report-' . $range . '-days.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Metric', 'Value']);
    fputcsv($output, ['Range (days)', $range]);
    fputcsv($output, ['Calories Consumed', number_format($stats['consumed'], 0, '.', '')]);
    fputcsv($output, ['Calories Burned', number_format($stats['burned'], 0, '.', '')]);
    fputcsv($output, ['Net Balance', number_format($deficit, 0, '.', '')]);
    fputcsv($output, ['Adherence Score (%)', $adherence]);
    fputcsv($output, ['Protein (g)', number_format($stats['protein'], 1, '.', '')]);
    fputcsv($output, ['Carbs (g)', number_format($stats['carbs'], 1, '.', '')]);
    fputcsv($output, ['Fats (g)', number_format($stats['fats'], 1, '.', '')]);
    fputcsv($output, ['Top Activity', $stats['top_ex']]);
    fputcsv($output, ['Top Activity Minutes', number_format($stats['top_ex_m'], 0, '.', '')]);
    fclose($output);
    exit;
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

    .header-actions {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .custom-select {
        appearance: none;
        background-color: var(--bg-right);
        border: 2px solid var(--border-light);
        padding: 0.8rem 2.5rem 0.8rem 1.2rem;
        border-radius: 50px;
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--text-dark);
        cursor: pointer;
        outline: none;
        background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%234a6aa6%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E");
        background-repeat: no-repeat;
        background-position: right 1rem top 50%;
        background-size: 0.65rem auto;
        transition: all 0.3s ease;
    }

    .custom-select:hover {
        border-color: var(--primary-blue);
    }

    .btn-download {
        background-color: var(--bg-right);
        color: var(--text-dark);
        border: 2px solid var(--border-light);
        padding: 0.8rem 1.5rem;
        border-radius: 50px;
        font-size: 0.95rem;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
    }

    .btn-download:hover {
        border-color: var(--primary-blue);
        color: var(--primary-blue);
        background-color: var(--input-bg);
        border-radius: 12px;
    }

    .grid-top {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 2rem;
        margin-bottom: 2rem;
    }

    .grid-bottom {
        display: grid;
        grid-template-columns: 1fr 1.5fr;
        gap: 2rem;
        margin-bottom: 3rem;
    }

    .card {
        background-color: var(--bg-right);
        border-radius: 24px;
        padding: 2rem;
        box-shadow: 0 10px 30px rgba(27, 54, 121, 0.04);
        position: relative;
        display: flex;
        flex-direction: column;
    }

    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1.5rem;
        width: 100%;
    }

    .card-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text-dark);
    }

    /* Adherence Card */
    .adherence-card { text-align: center; align-items: center; }
    .shield-icon { position: absolute; top: 2rem; right: 2rem; font-size: 1.5rem; color: var(--input-bg); }
    .circular-chart-container { position: relative; width: 160px; height: 160px; margin: 1rem auto 2rem auto; }
    .circular-chart { display: block; width: 100%; height: 100%; }
    .circle-bg { fill: none; stroke: var(--input-bg); stroke-width: 4; }
    .circle-fill { fill: none; stroke: var(--primary-blue); stroke-width: 4; stroke-linecap: round; stroke-dasharray: 0, 100; transition: stroke-dasharray 1.5s cubic-bezier(0.25, 1, 0.5, 1); }
    .chart-text { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; }
    .chart-text .val { font-size: 2.5rem; font-weight: 700; color: var(--text-dark); line-height: 1; margin-bottom: 0.2rem; }
    .chart-text .lbl { font-size: 0.75rem; color: var(--text-medium); font-weight: 500; }
    .adherence-card p { font-size: 0.9rem; color: var(--text-medium); line-height: 1.5; }

    /* Calorie Balance Card */
    .cal-row { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 0.5rem; }
    .cal-label { font-size: 0.9rem; font-weight: 600; color: var(--text-dark); }
    .cal-val { font-size: 0.9rem; font-weight: 700; color: var(--text-dark); }
    .bar-bg { width: 100%; height: 12px; background-color: var(--input-bg); border-radius: 10px; margin-bottom: 2rem; overflow: hidden; }
    .bar-fill-light { height: 100%; background-color: #93c5fd; border-radius: 10px; width: 0%; transition: width 1.5s ease-out; }
    .bar-fill-dark { height: 100%; background-color: var(--primary-blue); border-radius: 10px; width: 0%; transition: width 1.5s ease-out; }
    .deficit-box { margin-top: auto; display: flex; justify-content: space-between; align-items: center; }
    .deficit-info span { display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-medium); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.2rem; }
    .deficit-info strong { font-size: 1.1rem; color: var(--primary-blue); }
    .trend-icon { width: 35px; height: 35px; background-color: var(--input-bg); color: var(--primary-blue); display: flex; align-items: center; justify-content: center; border-radius: 8px; font-size: 1rem; }

    /* Top Exercise Card */
    .exercise-header { display: flex; gap: 1rem; align-items: center; margin-bottom: 2rem; }
    .ex-icon { width: 50px; height: 50px; background-color: var(--primary-blue); color: white; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
    .ex-text span { display: block; font-size: 0.8rem; color: var(--text-medium); margin-bottom: 0.2rem; }
    .ex-text strong { font-size: 1.1rem; color: var(--text-dark); }
    .gain-box { background-color: var(--input-bg); border-radius: 16px; padding: 1.5rem; position: relative; overflow: hidden; margin-top: auto; }
    .gain-box h2 { font-size: 2.2rem; font-weight: 700; color: var(--primary-blue); margin-bottom: 0.5rem; display: flex; align-items: baseline; gap: 0.5rem; }
    .gain-box h2 span { font-size: 0.9rem; color: var(--text-dark); font-weight: 600; }
    .gain-box p { font-size: 0.85rem; color: var(--text-medium); max-width: 80%; line-height: 1.4; }
    .gain-arrows { position: absolute; right: 1rem; bottom: 1rem; color: rgba(61, 123, 244, 0.1); font-size: 4rem; line-height: 0.6; display: flex; flex-direction: column; }

    /* Nutrient Distribution Card */
    .nutrients-content { display: flex; align-items: center; gap: 2.5rem; height: 100%; }
    .donut-container { width: 180px; height: 180px; position: relative; flex-shrink: 0; }
    .donut-chart { width: 100%; height: 100%; transform: rotate(-90deg); }
    .donut-segment { fill: none; stroke-width: 4; stroke-linecap: round; }
    .donut-protein { stroke: #1e40af; stroke-dasharray: 0 100; transition: stroke-dasharray 1.5s ease; }
    .donut-carbs { stroke: var(--primary-blue); stroke-dasharray: 0 100; transition: stroke-dasharray 1.5s ease 0.2s; }
    .donut-fats { stroke: #93c5fd; stroke-dasharray: 0 100; transition: stroke-dasharray 1.5s ease 0.4s; }
    .donut-text { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; }
    .donut-text strong { display: block; font-size: 1.1rem; color: var(--text-dark); }
    .donut-text span { font-size: 0.8rem; color: var(--text-medium); }
    .nutrients-info { flex: 1; display: flex; flex-direction: column; gap: 1rem; }
    .legend { display: flex; gap: 1rem; margin-bottom: 0.5rem; }
    .legend-item { display: flex; align-items: center; gap: 0.4rem; font-size: 0.85rem; font-weight: 600; color: var(--text-dark); }
    .dot { width: 10px; height: 10px; border-radius: 50%; }
    .info-box { background-color: var(--input-bg); padding: 1.2rem; border-radius: 16px; }
    .info-box h4 { font-size: 0.85rem; color: var(--text-medium); margin-bottom: 0.4rem; font-weight: 600; }
    .info-box p { font-size: 0.9rem; color: var(--text-dark); font-weight: 500; line-height: 1.4; }

    @media (max-width: 1024px) {
        .grid-top { grid-template-columns: 1fr 1fr; }
        .grid-bottom { grid-template-columns: 1fr; }
    }
    @media (max-width: 768px) {
        .grid-top { grid-template-columns: 1fr; }
        .nutrients-content { flex-direction: column; text-align: center; }
        .legend { justify-content: center; }
    }

    /* ── Print / PDF ── */
    @media print {
        body { background: #fff !important; }
        .navbar, .btn-download, .custom-select, .header-actions { display: none !important; }
        .reports-wrapper { padding: 1rem !important; }
        .card { box-shadow: none !important; border: 1px solid #e5edf9; break-inside: avoid; }
        .grid-top { grid-template-columns: repeat(3, 1fr) !important; }
        .grid-bottom { grid-template-columns: 1fr !important; }
        .page-header { margin-bottom: 1rem; }
        a[href]:after { content: none !important; }
    }
</style>

<div class="reports-wrapper" style="padding: 3rem; max-width: 1400px; margin: 0 auto; width: 100%;">
    <!-- Page Header -->
    <div class="page-header fade-in delay-1">
        <div class="header-title">
            <span class="subtitle">Insights & Analytics</span>
            <h1>Performance Report</h1>
        </div>
        <div class="header-actions">
            <select class="custom-select" id="timeRangeFilter" onchange="window.location.href='?range='+this.value">
                <option value="7" <?php echo $range==7 ? 'selected' : ''; ?>>Last 7 Days</option>
                <option value="30" <?php echo $range==30 ? 'selected' : ''; ?>>Last 30 Days</option>
                <option value="90" <?php echo $range==90 ? 'selected' : ''; ?>>Last 3 Months</option>
            </select>
            <a class="btn-download" href="?range=<?php echo $range; ?>&export=csv">
                <i class="fa-solid fa-cloud-arrow-down"></i> Export CSV
            </a>
            <button class="btn-download" onclick="window.print()" style="border:none;cursor:pointer;">
                <i class="fa-solid fa-print"></i> Print / PDF
            </button>
        </div>
    </div>

    <!-- Top Grid -->
    <div class="grid-top">
        
        <!-- Adherence Score -->
        <div class="card adherence-card fade-in delay-2">
            <i class="fa-solid fa-shield-halved shield-icon"></i>
            <h3 class="card-title">Adherence Score</h3>
            
            <div class="circular-chart-container">
                <svg viewBox="0 0 36 36" class="circular-chart">
                    <path class="circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                    <!-- JS triggers the stroke-dasharray animation -->
                    <path class="circle-fill" id="adherenceCircle" stroke-dasharray="0, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                </svg>
                <div class="chart-text">
                    <span class="val"><?php echo intval($adherence); ?><span style="font-size: 1.5rem">%</span></span>
                    <span class="lbl">Score</span>
                </div>
            </div>
            
            <p>Your consistency in hitting daily nutrition and fitness targets over the selected period.</p>
        </div>

        <!-- Calorie Balance -->
        <div class="card fade-in delay-3">
            <div class="card-header">
                <h3 class="card-title">Calorie Balance</h3>
            </div>
            
            <div class="cal-row">
                <span class="cal-label">Consumed</span>
                <span class="cal-val"><?php echo number_format($stats['consumed']); ?> kcal</span>
            </div>
            <div class="bar-bg" title="Target: <?php echo number_format($target_budget); ?> kcal">
                <div class="bar-fill-light" id="barConsumed" style="width: 0%;"></div>
            </div>

            <div class="cal-row">
                <span class="cal-label">Burned Activity</span>
                <span class="cal-val"><?php echo number_format($stats['burned']); ?> kcal</span>
            </div>
            <div class="bar-bg">
                <div class="bar-fill-dark" id="barBurned" style="width: 0%;"></div>
            </div>

            <div class="deficit-box">
                <div class="deficit-info">
                    <span>Net Balance</span>
                    <strong><?php echo $deficit > 0 ? '+' : ''; ?><?php echo number_format($deficit); ?> kcal</strong>
                </div>
                <div class="trend-icon">
                    <?php if ($deficit < 0): ?>
                        <i class="fa-solid fa-arrow-trend-down"></i>
                    <?php else: ?>
                        <i class="fa-solid fa-arrow-trend-up"></i>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Top Exercise -->
        <div class="card fade-in delay-4">
            <div class="card-header">
                <h3 class="card-title">Top Activity</h3>
            </div>
            
            <div class="exercise-header">
                <div class="ex-icon"><i class="fa-solid fa-dumbbell"></i></div>
                <div class="ex-text">
                    <span>Most Frequent</span>
                    <strong><?php echo htmlspecialchars($stats['top_ex'], ENT_QUOTES, 'UTF-8'); ?></strong>
                </div>
            </div>

            <div class="gain-box">
                <h2><?php echo intval($stats['top_ex_m'] / 60); ?> <span>hrs</span> <?php echo intval($stats['top_ex_m'] % 60); ?> <span>min</span></h2>
                <p>Total time spent on this activity during this period.</p>
                <div class="gain-arrows">
                    <i class="fa-solid fa-angle-up" style="transform: translateY(10px);"></i>
                    <i class="fa-solid fa-angle-up"></i>
                </div>
            </div>
        </div>

    </div>

    <!-- Bottom Grid -->
    <div class="grid-bottom">
        
        <!-- Nutrient Distribution -->
        <div class="card fade-in delay-2" style="grid-column: 1 / -1;">
            <div class="card-header">
                <h3 class="card-title">Macronutrient Distribution</h3>
            </div>
            
            <div class="nutrients-content">
                <div class="donut-container">
                    <svg viewBox="0 0 36 36" class="donut-chart">
                        <!-- Background -->
                        <path class="donut-segment" stroke="var(--input-bg)" stroke-dasharray="100 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                        <!-- Multi-segments built dynamically via JS with correct offsets -->
                        <path class="donut-segment donut-protein" id="donutP" stroke-dasharray="0 100" stroke-dashoffset="0" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                        <path class="donut-segment donut-carbs" id="donutC" stroke-dasharray="0 100" stroke-dashoffset="0" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                        <path class="donut-segment donut-fats" id="donutF" stroke-dasharray="0 100" stroke-dashoffset="0" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                    </svg>
                    <div class="donut-text">
                        <strong><?php echo number_format($total_macros); ?>g</strong>
                        <span>Total</span>
                    </div>
                </div>

                <div class="nutrients-info">
                    <div class="legend">
                        <div class="legend-item">
                            <div class="dot" style="background-color: #1e40af;"></div>
                            Protein (<?php echo $p_pct; ?>%)
                        </div>
                        <div class="legend-item">
                            <div class="dot" style="background-color: var(--primary-blue);"></div>
                            Carbs (<?php echo $c_pct; ?>%)
                        </div>
                        <div class="legend-item">
                            <div class="dot" style="background-color: #93c5fd;"></div>
                            Fats (<?php echo $f_pct; ?>%)
                        </div>
                    </div>
                    
                    <div class="info-box">
                        <h4>Nutritional Insight</h4>
                        <p>
                            <?php if ($p_pct >= 30): ?>
                                Outstanding protein intake! Your muscle recovery should be optimal.
                            <?php elseif ($c_pct > 50): ?>
                                High carbohydrate proportion. Ensure these are mostly complex carbs for sustained energy.
                            <?php else: ?>
                                Your macro distribution looks fairly balanced. Keep up the consistent tracking!
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        // Entrance Animations
        setTimeout(() => {
            const elements = document.querySelectorAll('.fade-in');
            elements.forEach(el => el.classList.add('visible'));
        }, 100);

        // Circular Adherence Chart
        setTimeout(() => {
            document.getElementById('adherenceCircle').style.strokeDasharray = "<?php echo intval($adherence); ?>, 100";
        }, 500);

        // Calorie Balance Bars
        setTimeout(() => {
            document.getElementById('barConsumed').style.width = "<?php echo intval($pct_consumed); ?>%";
            document.getElementById('barBurned').style.width = "<?php echo intval($pct_burned); ?>%";
        }, 600);

        // Donut Chart Segments
        // Protein starts at 0 offset
        // Carbs offset by Protein length (negative for SVG dashoffset)
        // Fats offset by Protein + Carbs
        setTimeout(() => {
            const dp = document.getElementById('donutP');
            const dc = document.getElementById('donutC');
            const df = document.getElementById('donutF');

            dp.style.strokeDasharray = "<?php echo intval($p_arc); ?> 100";
            
            dc.style.strokeDashoffset = "<?php echo intval($c_off); ?>";
            dc.style.strokeDasharray = "<?php echo intval($c_arc); ?> 100";
            
            df.style.strokeDashoffset = "<?php echo intval($f_off); ?>";
            df.style.strokeDasharray = "<?php echo intval($f_arc); ?> 100";
        }, 800);
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>