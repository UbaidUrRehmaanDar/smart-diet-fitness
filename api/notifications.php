<?php
/**
 * API: Notifications
 * GET /api/notifications.php?action=list|count
 * POST /api/notifications.php?action=mark_read|mark_all_read
 * Response: JSON
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

$response = ['success' => false];
$user_id = get_user_id();
// Support action from URL param, POST field, OR JSON body
$_json_body = read_json_input();
$action = $_GET['action'] ?? $_POST['action'] ?? ($_json_body['action'] ?? '');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // GET actions
        if ($action === 'count') {
            // Get unread count
            $stmt = $pdo->prepare('
                SELECT COUNT(*) as count FROM notifications 
                WHERE user_id = ? AND is_read = 0
            ');
            $stmt->execute([$user_id]);
            $result = $stmt->fetch();
            
            $response['success'] = true;
            $response['count'] = intval($result['count']);
            
        } elseif ($action === 'list') {
            // Get list of notifications
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
            $limit = max(1, min(50, $limit)); // // 🔧 Hard cap to prevent abuse.
            
            $stmt = $pdo->prepare('
                SELECT * FROM notifications 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?
            ');
            // // 🔧 Bind limit as integer to satisfy MySQL/PDO.
            $stmt->bindValue(1, $user_id, PDO::PARAM_INT);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->execute();
            $notifications = $stmt->fetchAll();
            
            $response['success'] = true;
            $response['notifications'] = array_map(function($notif) {
                return [
                    'id' => intval($notif['id']),
                    'type' => $notif['notification_type'],
                    'title' => $notif['title'],
                    'message' => $notif['message'],
                    'is_read' => boolval($notif['is_read']),
                    'created_at' => $notif['created_at'],
                    'time_ago' => time_ago($notif['created_at']),
                ];
            }, $notifications);
            $response['count'] = count($notifications);
            
        } else {
            http_response_code(400);
            $response['error'] = 'Invalid action';
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // POST actions - require CSRF
        verify_csrf_ajax();
        $input = $_json_body; // already parsed above
        
        if ($action === 'mark_read') {
            // Mark single notification as read
            $notif_id = intval($_POST['notification_id'] ?? ($input['notification_id'] ?? 0));
            
            if ($notif_id <= 0) {
                http_response_code(400);
                $response['error'] = 'Invalid notification ID';
                echo json_encode($response);
                exit;
            }
            
            // Verify ownership
            $stmt = $pdo->prepare('SELECT user_id FROM notifications WHERE id = ?');
            $stmt->execute([$notif_id]);
            $notif = $stmt->fetch();
            
            if (!$notif || $notif['user_id'] != $user_id) {
                http_response_code(403);
                $response['error'] = 'Unauthorized';
                echo json_encode($response);
                exit;
            }
            
            // Mark as read
            $stmt = $pdo->prepare('
                UPDATE notifications 
                SET is_read = 1, read_at = NOW() 
                WHERE id = ?
            ');
            $stmt->execute([$notif_id]);
            
            $response['success'] = true;
            $response['marked_id'] = $notif_id;
            
        } elseif ($action === 'mark_all_read') {
            // Mark all unread as read
            $stmt = $pdo->prepare('
                UPDATE notifications 
                SET is_read = 1, read_at = NOW() 
                WHERE user_id = ? AND is_read = 0
            ');
            $stmt->execute([$user_id]);
            
            $response['success'] = true;
            $response['marked_count'] = (int)$stmt->rowCount(); // // 🔧 Correct affected rows.
            
        } else {
            http_response_code(400);
            $response['error'] = 'Invalid action';
        }
        
    } else {
        http_response_code(405);
        $response['error'] = 'Method not allowed';
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log('Notification API error: ' . $e->getMessage());
    http_response_code(500);
    $response['error'] = 'Server error';
    echo json_encode($response);
}

?>
