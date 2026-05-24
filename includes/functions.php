<?php

/**
 * Helper Functions for Smart Diet & Fitness Application
 * Provides: CSRF management, input validation, sanitization, common utilities
 */

// =====================================================
// CSRF TOKEN FUNCTIONS
// =====================================================

/**
 * Generate CSRF token HTML input field
 * @return string HTML hidden input with CSRF token
 */
function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verify CSRF token from POST request
 * @throws Exception if token is invalid or missing
 */
function verify_csrf()
{
    if (empty($_SESSION['csrf_token']) || !isset($_POST['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], (string) $_POST['csrf_token'])) {
        http_response_code(403);
        die(json_encode(['error' => 'Invalid request. CSRF token verification failed.']));
    }
}

/**
 * Verify CSRF token from AJAX requests (from X-CSRF-Token header)
 */
function verify_csrf_ajax()
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['csrf_token']) || $token === '' || !hash_equals((string) $_SESSION['csrf_token'], (string) $token)) {
        header('Content-Type: application/json');
        http_response_code(403);
        die(json_encode(['success' => false, 'error' => 'Session expired. Please refresh the page.']));
    }
}

// =====================================================
// INPUT SANITIZATION
// =====================================================

/**
 * Sanitize string input - removes HTML/scripts and trims whitespace
 * @param string $data
 * @return string Sanitized data
 */
function sanitize($data)
{
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Plain text for database storage (names, notes) — no HTML entities.
 */
function sanitize_plain_text($data, int $maxLength = 190): string
{
    if ($data === null || !is_string($data)) {
        return '';
    }
    $t = trim(strip_tags($data));
    $t = preg_replace('/\x00/', '', $t);
    if (function_exists('mb_substr')) {
        return mb_substr($t, 0, $maxLength, 'UTF-8');
    }
    return substr($t, 0, $maxLength);
}

/**
 * Sanitize numeric input
 * @param mixed $data
 * @return int|float Cleaned number
 */
function sanitize_number($data)
{
    return is_numeric($data) ? (float)$data : 0;
}

/**
 * Sanitize email address
 * @param string $email
 * @return string Sanitized email
 */
function sanitize_email($email)
{
    return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
}

// =====================================================
// VALIDATION FUNCTIONS
// =====================================================

/**
 * Validate email format
 * @param string $email
 * @return bool
 */
function validate_email($email)
{
    return filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate password strength
 * Minimum: 8 chars, 1 uppercase, 1 lowercase, 1 number, 1 special char
 * @param string $password
 * @return array ['valid' => bool, 'errors' => array]
 */
function validate_password($password)
{
    $errors = [];

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number.';
    }
    if (!preg_match('/[!@#$%^&*()_+=\-\[\]{};\':"\\|,.<>\/?]/', $password)) {
        $errors[] = 'Password must contain at least one special character.';
    }

    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * Validate numeric range
 * @param mixed $value
 * @param int $min
 * @param int $max
 * @return bool
 */
function validate_range($value, $min, $max)
{
    $num = sanitize_number($value);
    return $num >= $min && $num <= $max;
}

/**
 * Validate date format (YYYY-MM-DD)
 * @param string $date
 * @return bool
 */
function validate_date($date)
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

// =====================================================
// AUTHENTICATION HELPERS
// =====================================================

/**
 * Hash password using bcrypt
 * @param string $password
 * @return string Hashed password
 */
function hash_password($password)
{
    return password_hash($password, PASSWORD_HASH_ALGO, ['cost' => PASSWORD_HASH_COST]);
}

/**
 * Verify password against hash
 * @param string $password
 * @param string $hash
 * @return bool
 */
function verify_password($password, $hash)
{
    return password_verify($password, $hash);
}

/**
 * Check if user is logged in
 * @return bool
 */
function is_logged_in()
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user ID
 * @return int|null
 */
function get_user_id()
{
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user data from database
 * @return array|null User data or null if not logged in
 */
function fetch_current_user_data()
{
    if (!is_logged_in()) {
        return null;
    }

    try {
        $pdo = get_pdo();
        $stmt = $pdo->prepare('
            SELECT
                u.id AS user_id,
                u.email,
                u.role,
                u.created_at AS user_created_at,
                u.updated_at AS user_updated_at,
                p.id AS profile_row_id,
                p.first_name,
                p.last_name,
                p.date_of_birth,
                p.gender,
                p.height_cm,
                p.current_weight_kg,
                p.target_weight_kg,
                p.bmi,
                p.activity_level,
                p.fitness_goal,
                p.onboarding_completed,
                p.created_at AS profile_created_at,
                p.updated_at AS profile_updated_at,
                pr.id AS preferences_row_id,
                pr.diet_type,
                pr.allergies,
                pr.medical_conditions,
                pr.theme,
                pr.notifications_enabled,
                pr.meal_reminders,
                pr.workout_reminders,
                pr.hydration_reminders,
                pr.reminder_frequency,
                pr.created_at AS preferences_created_at,
                pr.updated_at AS preferences_updated_at
            FROM users u
            LEFT JOIN profiles p ON u.id = p.user_id
            LEFT JOIN preferences pr ON u.id = pr.user_id
            WHERE u.id = ?
        ');
        $stmt->execute([get_user_id()]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log('Error fetching user data: ' . $e->getMessage());
        return null;
    }
}

/**
 * Whether the user must finish onboarding before accessing the app.
 * Uses profiles.onboarding_completed when present; otherwise infers from required profile fields.
 */
function user_needs_onboarding(?PDO $pdo = null, ?int $user_id = null): bool
{
    $pdo = $pdo ?: get_pdo();
    $uid = $user_id ?? (int) get_user_id();
    if ($uid <= 0) {
        return false;
    }

    static $has_onboarding_col = null;

    try {
        if ($has_onboarding_col === null) {
            $col_stmt = $pdo->prepare('
                SELECT COUNT(*)
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
            ');
            $col_stmt->execute([DB_NAME, 'profiles', 'onboarding_completed']);
            $has_onboarding_col = (int) $col_stmt->fetchColumn() > 0;
        }

        if (!$has_onboarding_col) {
            $stmt = $pdo->prepare('
                SELECT fitness_goal, date_of_birth, height_cm, gender
                FROM profiles
                WHERE user_id = ?
                LIMIT 1
            ');
            $stmt->execute([$uid]);
            $p = $stmt->fetch();
            if (!$p) {
                return true;
            }
            foreach (['fitness_goal', 'date_of_birth', 'height_cm', 'gender'] as $field) {
                if (!isset($p[$field]) || $p[$field] === '' || $p[$field] === null) {
                    return true;
                }
            }

            return false;
        }

        $stmt = $pdo->prepare('SELECT onboarding_completed FROM profiles WHERE user_id = ? LIMIT 1');
        $stmt->execute([$uid]);
        $row = $stmt->fetch();
        if (!$row) {
            return true;
        }

        return (int) ($row['onboarding_completed'] ?? 0) !== 1;
    } catch (PDOException $e) {
        error_log('user_needs_onboarding error: ' . $e->getMessage());

        return false;
    }
}

// =====================================================
// REDIRECT FUNCTIONS
// =====================================================

/**
 * Redirect to URL
 * @param string $url
 */
function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

/**
 * Redirect with message (stored in session)
 * @param string $url
 * @param string $message
 * @param string $type 'success', 'error', 'warning', 'info'
 */
function redirect_with_message($url, $message, $type = 'info')
{
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
    redirect($url);
}

/**
 * Get and clear flash message
 * @return array ['message' => string, 'type' => string] or null
 */
function get_flash_message()
{
    if (isset($_SESSION['message']) && isset($_SESSION['message_type'])) {
        $message = $_SESSION['message'];
        $type = $_SESSION['message_type'];
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
        return ['message' => $message, 'type' => $type];
    }
    return null;
}

// =====================================================
// DATABASE HELPERS
// =====================================================

/**
 * Get PDO connection
 * @return PDO
 */
function get_pdo()
{
    global $pdo;
    return $pdo;
}

/**
 * Execute a prepared statement and return result
 * @param string $query SQL query with ? placeholders
 * @param array $params Parameters for prepared statement
 * @return PDOStatement
 */
function execute_query($query, $params = [])
{
    try {
        $pdo = get_pdo();
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log('Database Error: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Fetch single row
 * @param string $query
 * @param array $params
 * @return array|null
 */
function fetch_one($query, $params = [])
{
    $stmt = execute_query($query, $params);
    return $stmt->fetch();
}

/**
 * Fetch all rows
 * @param string $query
 * @param array $params
 * @return array
 */
function fetch_all($query, $params = [])
{
    $stmt = execute_query($query, $params);
    return $stmt->fetchAll();
}

/**
 * Insert record and return last insert ID
 * @param string $table Table name
 * @param array $data Associative array of column => value
 * @return int Last insert ID
 */
function insert_record($table, $data)
{
    $columns = implode(', ', array_keys($data));
    $placeholders = implode(', ', array_fill(0, count($data), '?'));
    $query = "INSERT INTO $table ($columns) VALUES ($placeholders)";

    execute_query($query, array_values($data));
    return get_pdo()->lastInsertId();
}

/**
 * Update record
 * @param string $table Table name
 * @param array $data Associative array of column => value
 * @param string $where WHERE clause (e.g., "id = ?")
 * @param array $where_params Parameters for WHERE clause
 * @return int Number of affected rows
 */
function update_record($table, $data, $where, $where_params = [])
{
    $set = implode(', ', array_map(function ($col) {
        return "$col = ?";
    }, array_keys($data)));
    $query = "UPDATE $table SET $set WHERE $where";

    $stmt = execute_query($query, array_merge(array_values($data), $where_params));
    return $stmt->rowCount();
}

/**
 * Delete record
 * @param string $table Table name
 * @param string $where WHERE clause
 * @param array $params Parameters for WHERE clause
 * @return int Number of affected rows
 */
function delete_record($table, $where, $params = [])
{
    $query = "DELETE FROM $table WHERE $where";
    $stmt = execute_query($query, $params);
    return $stmt->rowCount();
}

// =====================================================
// UTILITY FUNCTIONS
// =====================================================

/**
 * Calculate BMI
 * @param float $weight_kg
 * @param float $height_cm
 * @return float BMI value
 */
function calculate_bmi($weight_kg, $height_cm)
{
    $height_m = $height_cm / 100;
    return round($weight_kg / ($height_m * $height_m), 2);
}

/**
 * Get BMI category
 * @param float $bmi
 * @return string Category name
 */
function get_bmi_category($bmi)
{
    if ($bmi < 18.5) return 'Underweight';
    if ($bmi < 25) return 'Normal weight';
    if ($bmi < 30) return 'Overweight';
    return 'Obese';
}

/**
 * Format number with 2 decimal places
 * @param float $num
 * @return string Formatted number
 */
function format_number($num)
{
    return number_format($num, 2, '.', '');
}

/**
 * Format date for display
 * @param string $date YYYY-MM-DD format
 * @return string Formatted date
 */
function format_date($date)
{
    return date('M d, Y', strtotime($date));
}

/**
 * Generate random token
 * @param int $length
 * @return string
 */
function generate_token($length = 32)
{
    return bin2hex(random_bytes($length / 2));
}

/**
 * Get time ago string (e.g., "2 hours ago")
 * @param string $timestamp
 * @return string
 */
function time_ago($timestamp)
{
    $time = strtotime($timestamp);
    $diff = time() - $time;

    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('M d, Y', $time);
}

/**
 * Check if array is associative
 * @param array $arr
 * @return bool
 */
function is_assoc($arr)
{
    if (!is_array($arr)) return false;
    return array_keys($arr) !== range(0, count($arr) - 1);
}

// =====================================================
// JSON RESPONSE HELPER
// =====================================================

/**
 * Send JSON response
 * @param array $data
 * @param int $status_code
 * @param string $content_type
 */
function json_response($data, $status_code = 200, $content_type = 'application/json')
{
    header('Content-Type: ' . $content_type);
    http_response_code($status_code);
    echo json_encode($data);
    exit;
}

// =====================================================
// REQUEST HELPERS
// =====================================================

/**
 * Read JSON body safely (returns [] on invalid/empty JSON).
 * // 🔧 Centralizes JSON parsing for APIs.
 * @return array
 */
function read_json_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

// =====================================================
// USER HELPERS (for header/navbar)
// =====================================================

function db_table_has_column(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];

    $key = $table . '.' . $column;
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([DB_NAME, $table, $column]);
        $cache[$key] = (int) $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log('db_table_has_column: ' . $e->getMessage());
        $cache[$key] = false;
    }

    return $cache[$key];
}

/**
 * Ensure profiles row exists (fixes orphan users where signup insert failed silently).
 */
function ensure_profile_row_exists(PDO $pdo, int $user_id): void
{
    try {
        $chk = $pdo->prepare('SELECT id FROM profiles WHERE user_id = ? LIMIT 1');
        $chk->execute([$user_id]);
        if ($chk->fetch()) {
            return;
        }

        $ins = $pdo->prepare(
            'INSERT INTO profiles (
                user_id, height_cm, current_weight_kg, target_weight_kg,
                activity_level, fitness_goal, onboarding_completed
            ) VALUES (?, 170.00, 70.00, 70.00, ?, ?, 0)'
        );
        $ins->execute([$user_id, 'lightly_active', 'maintenance']);
    } catch (PDOException $e) {
        error_log('ensure_profile_row_exists: ' . $e->getMessage());
    }
}

/**
 * Ensure preferences row exists for user.
 */
function ensure_preferences_row(PDO $pdo, int $user_id): void
{
    try {
        $chk = $pdo->prepare('SELECT id FROM preferences WHERE user_id = ? LIMIT 1');
        $chk->execute([$user_id]);
        if ($chk->fetch()) {
            return;
        }
        $ins = $pdo->prepare('INSERT INTO preferences (user_id) VALUES (?)');
        $ins->execute([$user_id]);
    } catch (PDOException $e) {
        error_log('ensure_preferences_row: ' . $e->getMessage());
    }
}

/**
 * Save onboarding profile fields after step 1 (not only after step 3).
 */
function persist_onboarding_profile_step1(PDO $pdo, int $user_id, array $d): void
{
    ensure_profile_row_exists($pdo, $user_id);
    $stmt = $pdo->prepare(
        'UPDATE profiles SET
            first_name = ?, last_name = ?, date_of_birth = ?, gender = ?,
            height_cm = ?, current_weight_kg = ?, target_weight_kg = ?
         WHERE user_id = ?'
    );
    $stmt->execute([
        $d['first_name'],
        $d['last_name'],
        $d['date_of_birth'],
        $d['gender'],
        $d['height_cm'],
        $d['current_weight_kg'],
        $d['target_weight_kg'],
        $user_id,
    ]);
}

/**
 * Save preferences after onboarding step 2.
 */
function persist_onboarding_preferences_step2(PDO $pdo, int $user_id, string $diet_type, string $allergies_json, string $medical_json): void
{
    ensure_preferences_row($pdo, $user_id);
    $stmt = $pdo->prepare(
        'UPDATE preferences SET diet_type = ?, allergies = ?, medical_conditions = ? WHERE user_id = ?'
    );
    $stmt->execute([$diet_type, $allergies_json, $medical_json, $user_id]);
}

/**
 * Process avatar upload; returns new basename or null on skip. Sets $error out param on failure.
 *
 * @param-out string|null $error
 */
function process_profile_avatar_upload(int $user_id, ?string $previous_basename, array $file, ?string &$error = null): ?string
{
    $error = null;
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $error = 'Upload failed. Please try a smaller image.';

        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']) ?: '';
    $map = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($map[$mime])) {
        $error = 'Please upload a JPEG, PNG, or WebP image.';

        return null;
    }
    $ext = $map[$mime];
    if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
        $error = 'Image must be 2 MB or smaller.';

        return null;
    }

    $dir = defined('AVATAR_UPLOAD_DIR') ? AVATAR_UPLOAD_DIR : dirname(__DIR__) . '/public/uploads/avatars';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        $error = 'Upload directory is not writable.';

        return null;
    }

    $safe = 'u' . $user_id . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = $dir . DIRECTORY_SEPARATOR . $safe;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        $error = 'Could not save the image.';

        return null;
    }

    if ($previous_basename !== null && $previous_basename !== '' && $previous_basename !== $safe) {
        $prev = $dir . DIRECTORY_SEPARATOR . basename($previous_basename);
        if (is_file($prev) && str_starts_with(basename($prev), 'u' . $user_id . '_')) {
            @unlink($prev);
        }
    }

    return $safe;
}

/**
 * Public URL for a stored avatar basename.
 */
function avatar_public_url(?string $basename): ?string
{
    if ($basename === null || $basename === '') {
        return null;
    }
    $seg = defined('AVATAR_WEB_PATH') ? AVATAR_WEB_PATH : '/public/uploads/avatars';

    return APP_URL . $seg . '/' . rawurlencode(basename($basename));
}

/**
 * Get current user profile (for navbar/avatar).
 * // 🔧 Fixes missing function used by includes/header.php.
 * @return array|null
 */
function get_current_user_profile(): ?array
{
    if (!is_logged_in()) {
        return null;
    }

    try {
        $pdo = get_pdo();
        $pic_sel = '';
        if (db_table_has_column($pdo, 'profiles', 'profile_picture')) {
            $pic_sel = ', p.profile_picture';
        }

        $stmt = $pdo->prepare('
            SELECT 
                u.id,
                u.email,
                p.first_name,
                p.last_name' . $pic_sel . '
            FROM users u
            LEFT JOIN profiles p ON p.user_id = u.id
            WHERE u.id = ?
            LIMIT 1
        ');
        $stmt->execute([get_user_id()]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (PDOException $e) {
        error_log('Error fetching current user: ' . $e->getMessage());
        return null;
    }
}

// =====================================================
// ACHIEVEMENTS & NOTIFICATIONS
// =====================================================

/**
 * Create a notification for a user.
 */
function create_notification(PDO $pdo, int $user_id, string $type, string $title, string $message): void
{
    try {
        $stmt = $pdo->prepare('
            INSERT INTO notifications (user_id, notification_type, title, message)
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute([$user_id, $type, $title, $message]);
    } catch (PDOException $e) {
        error_log('Notification insert error: ' . $e->getMessage());
    }
}

/**
 * Check if an achievement already exists.
 */
function achievement_exists(PDO $pdo, int $user_id, string $badge_name): bool
{
    $stmt = $pdo->prepare('SELECT id FROM achievements WHERE user_id = ? AND badge_name = ? LIMIT 1');
    $stmt->execute([$user_id, $badge_name]);
    return (bool)$stmt->fetch();
}

/**
 * Unlock an achievement and create a notification (idempotent).
 */
function unlock_achievement(PDO $pdo, int $user_id, string $badge_name, string $badge_icon, string $description, string $type = 'milestone'): bool
{
    if (achievement_exists($pdo, $user_id, $badge_name)) {
        return false;
    }

    try {
        $stmt = $pdo->prepare('
            INSERT INTO achievements (user_id, badge_name, badge_icon, description, achievement_type)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([$user_id, $badge_name, $badge_icon, $description, $type]);

        create_notification(
            $pdo,
            $user_id,
            'achievement_unlock',
            'Achievement unlocked',
            $badge_name . ' - ' . $description
        );

        return true;
    } catch (PDOException $e) {
        error_log('Achievement unlock error: ' . $e->getMessage());
        return false;
    }
}
