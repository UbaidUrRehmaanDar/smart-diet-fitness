<?php
/**
 * Notifications Page
 * Displays alerts, reminders, and system notifications for the user
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

$user_id = get_user_id();
$page_title = 'Notifications - ' . APP_NAME;

function table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?');
    $stmt->execute([DB_NAME, $table]);
    return (int)$stmt->fetchColumn() > 0;
}

// Fetch notifications with pagination
$per_page = 20;
$page_num = max(1, (int)($_GET['p'] ?? 1));
$offset   = ($page_num - 1) * $per_page;

$notifications = [];
$total_count   = 0;
if (table_exists($pdo, 'notifications')) {
    try {
        $cnt_stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ?');
        $cnt_stmt->execute([$user_id]);
        $total_count = (int)$cnt_stmt->fetchColumn();

        $stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?');
        $stmt->bindValue(1, $user_id, PDO::PARAM_INT);
        $stmt->bindValue(2, $per_page, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset,   PDO::PARAM_INT);
        $stmt->execute();
        $notifications = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Notifications fetch error: ' . $e->getMessage());
        $notifications = [];
    }
}

$total_pages  = (int)ceil($total_count / $per_page);
$has_prev     = $page_num > 1;
$has_next     = $page_num < $total_pages;

function get_notification_icon($type) {
    switch ($type) {
        case 'hydration_reminder': return '<i class="fa-solid fa-droplet"></i>';
        case 'meal_reminder': return '<i class="fa-solid fa-bowl-food"></i>';
        case 'workout_reminder': return '<i class="fa-solid fa-dumbbell"></i>';
        case 'achievement_unlock': return '<i class="fa-solid fa-trophy"></i>';
        case 'system': default: return '<i class="fa-solid fa-bell"></i>';
    }
}

function get_notification_color($type) {
    switch ($type) {
        case 'hydration_reminder': return 'icon-blue';
        case 'meal_reminder': return 'icon-green';
        case 'workout_reminder': return 'icon-blue';
        case 'achievement_unlock': return 'icon-gold';
        case 'system': default: return 'icon-gray';
    }
}
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<style>
    /* Notifications-specific overrides */
    .delay-1 { transition-delay: 0.1s; }
    .delay-2 { transition-delay: 0.2s; }
    .delay-3 { transition-delay: 0.3s; }
    .delay-4 { transition-delay: 0.4s; }

    .notifications-card {
        background-color: var(--bg-right);
        border-radius: 24px;
        width: 100%;
        max-width: 800px;
        box-shadow: 0 15px 40px rgba(27, 54, 121, 0.05);
        overflow: hidden;
        display: flex;
        flex-direction: column;
        margin: 0 auto;
    }

    .notif-header-area { padding: 2.5rem 2.5rem 0 2.5rem; }
    .notif-title-row { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; }
    .notif-title h1 { font-size: 1.8rem; font-weight: 700; color: var(--text-dark); letter-spacing: -0.5px; margin-bottom: 0.3rem; }
    .notif-title p { font-size: 0.9rem; color: var(--text-medium); }

    .btn-mark-read {
        background-color: var(--input-bg);
        color: var(--primary-blue);
        border: none;
        padding: 0.6rem 1.2rem;
        border-radius: 50px;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    .btn-mark-read:hover { background-color: var(--border-light); color: var(--text-dark); }

    .notif-tabs {
        display: flex;
        gap: 2rem;
        border-bottom: 2px solid var(--border-light);
        padding: 0 2.5rem;
    }
    .tab {
        background: none;
        border: none;
        padding: 1rem 0;
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--text-medium);
        cursor: pointer;
        position: relative;
        transition: color 0.3s;
    }
    .tab:hover { color: var(--text-dark); }
    .tab.active { color: var(--primary-blue); }
    .tab.active::after {
        content: ''; position: absolute; bottom: -2px; left: 0; width: 100%; height: 3px;
        background-color: var(--primary-blue); border-radius: 3px 3px 0 0;
    }

    .notif-list { display: flex; flex-direction: column; padding: 1rem 1.5rem; }
    .notif-item {
        display: flex; align-items: flex-start; gap: 1.25rem; padding: 1.25rem;
        border-radius: 16px; transition: background-color 0.3s ease; position: relative;
    }
    .notif-item:hover { background-color: #f9fbff; }
    .notif-item.unread { background-color: var(--input-bg); }
    .notif-item.unread::before {
        content: ''; position: absolute; left: 0; top: 50%; transform: translateY(-50%);
        height: 60%; width: 4px; background-color: var(--primary-blue); border-radius: 0 4px 4px 0;
    }

    .notif-icon {
        width: 45px; height: 45px; border-radius: 12px; display: flex; align-items: center;
        justify-content: center; font-size: 1.2rem; flex-shrink: 0;
    }
    .icon-blue { background-color: #dbe7ff; color: var(--primary-blue); }
    .icon-gold { background-color: #fef3c7; color: var(--warning-gold); }
    .icon-gray { background-color: var(--border-light); color: var(--text-medium); }

    .notif-content { flex: 1; }
    .notif-content h4 { font-size: 1rem; font-weight: 700; color: var(--text-dark); margin-bottom: 0.3rem; }
    .notif-content p { font-size: 0.9rem; color: var(--text-medium); line-height: 1.4; }
    
    .notif-time {
        font-size: 0.75rem; font-weight: 700; color: var(--text-light);
        text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap;
    }
    .notif-item.unread .notif-time { color: var(--primary-blue); }

    .notif-footer {
        text-align: center; padding: 1.5rem; border-top: 1px solid var(--border-light);
        background-color: #f9fbff;
    }
    .btn-view-all {
        background: none; border: none; color: var(--primary-blue); font-size: 0.85rem;
        font-weight: 700; text-transform: uppercase; letter-spacing: 1px; cursor: pointer; transition: color 0.3s;
    }
    .btn-view-all:hover { color: var(--primary-blue-hover); }

    @media (max-width: 768px) {
        .notif-header-area { padding: 1.5rem 1.5rem 0 1.5rem; }
        .notif-tabs { padding: 0 1.5rem; gap: 1rem; overflow-x: auto; white-space: nowrap; }
        .notif-list { padding: 0.5rem; }
        .notif-item { padding: 1rem; flex-direction: column; position: relative; }
        .notif-time { position: absolute; top: 1.2rem; right: 1rem; }
    }
</style>

<div class="main-content fade-in" style="padding: 3rem; width: 100%; display: flex; justify-content: center;">
    <div class="notifications-card fade-in delay-1">
        
        <!-- Header Area -->
        <div class="notif-header-area">
            <div class="notif-title-row">
                <div class="notif-title">
                    <h1>Notifications</h1>
                    <p>Stay updated with your health and fitness progress.</p>
                </div>
                <button class="btn-mark-read" onclick="markAllRead(this)">Mark all as read</button>
            </div>

            <!-- Tabs (Client-side filtering for simplicity) -->
            <div class="notif-tabs">
                <button class="tab active" onclick="filterNotifs('all', this)">All</button>
                <button class="tab" onclick="filterNotifs('reminder', this)">Reminders</button>
                <button class="tab" onclick="filterNotifs('achievement_unlock', this)">Achievements</button>
                <button class="tab" onclick="filterNotifs('system', this)">System</button>
            </div>
        </div>

        <!-- List -->
        <div class="notif-list" id="notifList">
            <?php if (empty($notifications)): ?>
                <div style="padding: 2rem; text-align: center; color: var(--text-medium);">
                    You have no notifications yet.
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $index => $notif): 
                    $unread_class = !$notif['is_read'] ? 'unread' : '';
                    $delay = min(4, ($index % 4) + 1); // 1 to 4 delay
                ?>
                    <div
                        class="notif-item <?php echo $unread_class; ?> fade-in delay-<?php echo $delay; ?>"
                        data-type="<?php echo htmlspecialchars($notif['notification_type']); ?>"
                        data-id="<?php echo (int)$notif['id']; ?>"
                        data-read="<?php echo $notif['is_read'] ? '1' : '0'; ?>"
                    >
                        <div class="notif-icon <?php echo get_notification_color($notif['notification_type']); ?>">
                            <?php echo get_notification_icon($notif['notification_type']); ?>
                        </div>
                        <div class="notif-content">
                            <h4><?php echo htmlspecialchars($notif['title']); ?></h4>
                            <p><?php echo htmlspecialchars($notif['message']); ?></p>
                        </div>
                        <div class="notif-time"><?php echo time_ago($notif['created_at']); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Footer / Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="notif-footer" style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
            <span style="font-size:0.85rem;color:var(--text-medium);">
                Showing <?php echo number_format($offset + 1); ?>–<?php echo number_format(min($offset + $per_page, $total_count)); ?> of <?php echo number_format($total_count); ?>
            </span>
            <div style="display:flex;gap:0.5rem;">
                <?php if ($has_prev): ?>
                    <a href="?p=<?php echo $page_num - 1; ?>" class="btn-mark-read" style="text-decoration:none;">
                        <i class="fa-solid fa-chevron-left"></i> Prev
                    </a>
                <?php endif; ?>
                <?php for ($p = max(1, $page_num - 2); $p <= min($total_pages, $page_num + 2); $p++): ?>
                    <a href="?p=<?php echo $p; ?>"
                       class="btn-mark-read"
                       style="text-decoration:none;<?php echo $p === $page_num ? 'background:var(--primary-blue);color:#fff;' : ''; ?>">
                        <?php echo $p; ?>
                    </a>
                <?php endfor; ?>
                <?php if ($has_next): ?>
                    <a href="?p=<?php echo $page_num + 1; ?>" class="btn-mark-read" style="text-decoration:none;">
                        Next <i class="fa-solid fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        const elements = document.querySelectorAll('.fade-in');
        setTimeout(() => {
            elements.forEach(el => el.classList.add('visible'));
        }, 100);
    });

    function filterNotifs(type, btn) {
        // Update active tab styles
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        btn.classList.add('active');

        // Filter items
        const items = document.querySelectorAll('.notif-item');
        items.forEach(item => {
            const itemType = item.getAttribute('data-type');
            if (type === 'all') {
                item.style.display = 'flex';
            } else if (type === 'reminder') {
                if (itemType.includes('reminder')) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            } else {
                if (itemType === type) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            }
        });
    }

    function markAllRead(btn) {
        const originalText = btn.innerText;
        btn.innerText = 'Marking...';
        btn.disabled = true;

        fetch('<?php echo APP_URL; ?>/api/notifications.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrf()
            },
            body: JSON.stringify({ action: 'mark_all_read' })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // visually mark all read
                document.querySelectorAll('.notif-item.unread').forEach(el => {
                    el.classList.remove('unread');
                    el.setAttribute('data-read', '1');
                    const timeEl = el.querySelector('.notif-time');
                    if(timeEl) timeEl.style.color = "var(--text-light)"; // reset color
                });
                
                // Update badge if we rely on it globally
                const badge = document.getElementById('notif-count');
                if (badge) badge.style.display = 'none';

                btn.innerText = 'Done';
                setTimeout(() => {
                    btn.innerText = originalText;
                    btn.disabled = false;
                }, 2000);
                showToast('All notifications marked as read.');
            } else {
                showToast('Error marking as read: ' + (data.error || 'Unknown error'), 'error');
                btn.innerText = originalText;
                btn.disabled = false;
            }
        })
        .catch(err => {
            console.error(err);
            btn.innerText = originalText;
            btn.disabled = false;
            showToast('Network error while updating notifications.', 'error');
        });
    }

    // // 🔧 Mark single notification read on click (no UI changes).
    document.addEventListener('click', (event) => {
        const item = event.target.closest('.notif-item');
        if (!item) return;

        const isRead = item.getAttribute('data-read') === '1';
        if (isRead) return;

        const notifId = Number(item.getAttribute('data-id') || 0);
        if (!notifId) return;

        fetch('<?php echo APP_URL; ?>/api/notifications.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrf()
            },
            body: JSON.stringify({ action: 'mark_read', notification_id: notifId })
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                showToast(data.error || 'Unable to update notification.', 'error');
                return;
            }
            item.classList.remove('unread');
            item.setAttribute('data-read', '1');
            const timeEl = item.querySelector('.notif-time');
            if (timeEl) timeEl.style.color = "var(--text-light)";
        })
        .catch(() => showToast('Network error while updating notification.', 'error'));
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>