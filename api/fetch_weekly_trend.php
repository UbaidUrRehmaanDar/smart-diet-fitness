<?php
/**
 * 🔧 Changes Made
 * - Added weekly trend endpoint (weight + calories + hydration) for charts.
 *
 * API: Fetch Weekly Trend
 * GET /api/fetch_weekly_trend.php?end_date=YYYY-MM-DD&days=7
 * Response: JSON
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

$user_id = get_user_id();
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$days = isset($_GET['days']) ? (int)$_GET['days'] : 7;
$days = max(7, min(90, $days));

if (!validate_date($end_date)) {
    json_response(['success' => false, 'error' => 'Invalid end_date.'], 400);
}

try {
    // Build date spine (inclusive)
    $labels = [];
    $date_keys = [];
    $dt = new DateTime($end_date);
    $dt->setTime(0, 0, 0);
    for ($i = $days - 1; $i >= 0; $i--) {
        $d = clone $dt;
        $d->modify("-{$i} day");
        $key = $d->format('Y-m-d');
        $labels[] = $d->format('M j');
        $date_keys[] = $key;
    }

    // Diet calories per day
    $stmt = $pdo->prepare('
        SELECT logged_date, COALESCE(SUM(kcal), 0) AS kcal
        FROM diet_logs
        WHERE user_id = ? AND logged_date BETWEEN DATE_SUB(?, INTERVAL ? DAY) AND ?
        GROUP BY logged_date
    ');
    $stmt->execute([$user_id, $end_date, $days - 1, $end_date]);
    $diet_rows = $stmt->fetchAll();
    $diet_map = [];
    foreach ($diet_rows as $r) {
        $diet_map[$r['logged_date']] = (int)round((float)$r['kcal']);
    }

    // Hydration per day
    $stmt = $pdo->prepare('
        SELECT logged_date, COALESCE(SUM(amount_ml), 0) AS ml
        FROM hydration_logs
        WHERE user_id = ? AND logged_date BETWEEN DATE_SUB(?, INTERVAL ? DAY) AND ?
        GROUP BY logged_date
    ');
    $stmt->execute([$user_id, $end_date, $days - 1, $end_date]);
    $hyd_rows = $stmt->fetchAll();
    $hyd_map = [];
    foreach ($hyd_rows as $r) {
        $hyd_map[$r['logged_date']] = (int)round((float)$r['ml']);
    }

    // Weight trend from progress_metrics (if available)
    $stmt = $pdo->prepare('
        SELECT recorded_date, weight_kg
        FROM progress_metrics
        WHERE user_id = ? AND recorded_date BETWEEN DATE_SUB(?, INTERVAL ? DAY) AND ?
        ORDER BY recorded_date ASC
    ');
    $stmt->execute([$user_id, $end_date, $days - 1, $end_date]);
    $w_rows = $stmt->fetchAll();
    $w_map = [];
    foreach ($w_rows as $r) {
        $w_map[$r['recorded_date']] = (float)$r['weight_kg'];
    }

    $kcal_series = [];
    $hydration_series = [];
    $weight_series = [];
    $last_weight = null;
    foreach ($date_keys as $key) {
        $kcal_series[] = $diet_map[$key] ?? 0;
        $hydration_series[] = $hyd_map[$key] ?? 0;
        if (array_key_exists($key, $w_map)) {
            $last_weight = $w_map[$key];
        }
        // // 🔧 Keep chart continuous by carrying forward last known weight.
        $weight_series[] = $last_weight;
    }

    json_response([
        'success' => true,
        'end_date' => $end_date,
        'days' => $days,
        'labels' => $labels,
        'series' => [
            'kcal' => $kcal_series,
            'hydration_ml' => $hydration_series,
            'weight_kg' => $weight_series,
        ]
    ]);
} catch (Exception $e) {
    error_log('Weekly trend error: ' . $e->getMessage());
    json_response(['success' => false, 'error' => 'Server error'], 500);
}

