<?php
/**
 * Simulated Reminder Script
 * ─────────────────────────
 * Purpose : Insert today's meal / workout / hydration reminder notifications
 *           for every active user who has that reminder type enabled and has
 *           NOT already received one today.
 *
 * Usage (FYP demo):
 *   • CLI  : php cron/send_reminders.php
 *   • Web  : http://localhost/SHFS/cron/send_reminders.php
 *             (protected by a simple secret token in production)
 *
 * In a real deployment this would be called by a cron job:
 *   0 7 * * * php /path/to/SHFS/cron/send_reminders.php >> /path/to/logs/reminders.log 2>&1
 */

// ── Bootstrap ──────────────────────────────────────────────────────────────
define('CRON_RUN', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Simple token guard when accessed via browser (skip in CLI)
if (PHP_SAPI !== 'cli') {
    $expected_token = getenv('CRON_SECRET') ?: 'shfs_cron_2026';
    $provided_token = $_GET['token'] ?? '';
    if (!hash_equals($expected_token, $provided_token)) {
        http_response_code(403);
        die(json_encode(['error' => 'Forbidden. Provide ?token=<CRON_SECRET>']));
    }
    header('Content-Type: application/json');
}

$today = date('Y-m-d');
$now   = date('Y-m-d H:i:s');
$stats = ['processed' => 0, 'meal' => 0, 'workout' => 0, 'hydration' => 0, 'skipped' => 0, 'errors' => 0];

// ── Fetch all users with their preferences ─────────────────────────────────
try {
    $stmt = $pdo->query('
        SELECT
            u.id          AS user_id,
            pr.meal_reminders,
            pr.workout_reminders,
            pr.hydration_reminders,
            pr.notifications_enabled
        FROM users u
        LEFT JOIN preferences pr ON pr.user_id = u.id
        WHERE u.is_active = 1
    ');
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Reminders: user fetch error: ' . $e->getMessage());
    $msg = ['success' => false, 'error' => 'DB error fetching users'];
    echo PHP_SAPI === 'cli' ? json_encode($msg) . PHP_EOL : json_encode($msg);
    exit(1);
}

// ── Pre-build a set of (user_id, type) pairs that already have a reminder today ──
try {
    $existing_stmt = $pdo->prepare("
        SELECT user_id, notification_type
        FROM notifications
        WHERE notification_type IN ('meal_reminder','workout_reminder','hydration_reminder')
          AND DATE(created_at) = ?
    ");
    $existing_stmt->execute([$today]);
    $already_sent = [];
    foreach ($existing_stmt->fetchAll() as $row) {
        $already_sent[$row['user_id'] . ':' . $row['notification_type']] = true;
    }
} catch (PDOException $e) {
    error_log('Reminders: existing check error: ' . $e->getMessage());
    $already_sent = [];
}

// ── Insert statement (reused for all notifications) ────────────────────────
$ins = $pdo->prepare('
    INSERT INTO notifications (user_id, notification_type, title, message, is_read, created_at)
    VALUES (?, ?, ?, ?, 0, NOW())
');

// ── Reminder message templates ─────────────────────────────────────────────
$meal_messages = [
    'Time to fuel up! Log your breakfast to stay on track.',
    'Lunch time! Don\'t forget to log your meal.',
    'Dinner reminder — log what you eat to hit your daily targets.',
    'Snack time? Log it and keep your macros in check.',
];
$workout_messages = [
    'Ready to move? Check your workout plan for today.',
    'Your workout is waiting — even 20 minutes makes a difference.',
    'Stay consistent! Log a workout to keep your streak alive.',
];
$hydration_messages = [
    'Drink up! Aim for at least 2 litres of water today.',
    'Hydration check — have you had enough water today?',
    'Stay hydrated for better energy and recovery.',
];

// ── Process each user ──────────────────────────────────────────────────────
foreach ($users as $user) {
    $uid = (int) $user['user_id'];
    $notifs_on = (int) ($user['notifications_enabled'] ?? 1) === 1;

    if (!$notifs_on) {
        $stats['skipped']++;
        continue;
    }

    $stats['processed']++;

    try {
        // Meal reminder
        if ((int) ($user['meal_reminders'] ?? 1) === 1) {
            $key = $uid . ':meal_reminder';
            if (!isset($already_sent[$key])) {
                $msg = $meal_messages[array_rand($meal_messages)];
                $ins->execute([$uid, 'meal_reminder', 'Meal reminder', $msg]);
                $already_sent[$key] = true;
                $stats['meal']++;
            }
        }

        // Workout reminder
        if ((int) ($user['workout_reminders'] ?? 1) === 1) {
            $key = $uid . ':workout_reminder';
            if (!isset($already_sent[$key])) {
                $msg = $workout_messages[array_rand($workout_messages)];
                $ins->execute([$uid, 'workout_reminder', 'Workout reminder', $msg]);
                $already_sent[$key] = true;
                $stats['workout']++;
            }
        }

        // Hydration reminder
        if ((int) ($user['hydration_reminders'] ?? 1) === 1) {
            $key = $uid . ':hydration_reminder';
            if (!isset($already_sent[$key])) {
                $msg = $hydration_messages[array_rand($hydration_messages)];
                $ins->execute([$uid, 'hydration_reminder', 'Hydration reminder', $msg]);
                $already_sent[$key] = true;
                $stats['hydration']++;
            }
        }
    } catch (PDOException $e) {
        error_log('Reminders: insert error for user ' . $uid . ': ' . $e->getMessage());
        $stats['errors']++;
    }
}

// ── Output result ──────────────────────────────────────────────────────────
$result = [
    'success'   => true,
    'date'      => $today,
    'stats'     => $stats,
    'message'   => "Reminders processed for {$today}. "
                 . "Meal: {$stats['meal']}, Workout: {$stats['workout']}, "
                 . "Hydration: {$stats['hydration']}, Skipped: {$stats['skipped']}, "
                 . "Errors: {$stats['errors']}.",
];

error_log('Reminders run: ' . json_encode($result));

if (PHP_SAPI === 'cli') {
    echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
} else {
    echo json_encode($result);
}
