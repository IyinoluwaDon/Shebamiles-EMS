<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Database connection check
if (!(new Database())->getConnection()) {
    header('Location: ../dashboard.php?db=missing');
    exit();
}

$user_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// Handle notification actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = new Database();
        
        $action = $_POST['action'] ?? '';
        
        if ($action === 'mark_all_read') {
            // Mark all notifications as read
            $query = "UPDATE notifications SET is_read = 1 WHERE user_id = ?";
            $stmt = $db->conn->prepare($query);
            $stmt->execute([$user_id]);
            $success_msg = 'All notifications marked as read!';
        } elseif ($action === 'mark_read' && isset($_POST['notification_id'])) {
            // Mark single notification as read
            $query = "UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?";
            $stmt = $db->conn->prepare($query);
            $stmt->execute([$_POST['notification_id'], $user_id]);
            $success_msg = 'Notification marked as read!';
        } elseif ($action === 'delete' && isset($_POST['notification_id'])) {
            // Delete notification
            $query = "DELETE FROM notifications WHERE notification_id = ? AND user_id = ?";
            $stmt = $db->conn->prepare($query);
            $stmt->execute([$_POST['notification_id'], $user_id]);
            $success_msg = 'Notification deleted!';
        } elseif ($action === 'clear_all') {
            // Clear all notifications
            $query = "DELETE FROM notifications WHERE user_id = ?";
            $stmt = $db->conn->prepare($query);
            $stmt->execute([$user_id]);
            $success_msg = 'All notifications cleared!';
            logActivity($user_id, 'CLEAR_NOTIFICATIONS', 'notifications', 'all', 'Cleared all notifications');
        }
        
        if (in_array($action, ['mark_all_read', 'delete', 'clear_all'])) {
            logActivity($user_id, 'NOTIFICATION_ACTION', 'notifications', $action, 'Performed notification action: ' . $action);
        }
    } catch (PDOException $e) {
        $error_msg = 'Error processing notification action: ' . $e->getMessage();
    }
}

// Get all notifications for user with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$notifications = [];
$total_notifications = 0;
$unread_count = 0;

try {
    $db = new Database();
    
    // Get total count
    $query = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ?";
    $stmt = $db->conn->prepare($query);
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_notifications = $result['total'] ?? 0;
    
    // Get unread count
    $query = "SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0";
    $stmt = $db->conn->prepare($query);
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $unread_count = $result['unread'] ?? 0;
    
    // Get paginated notifications
    $query = "SELECT *, notification_id AS id FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT " . (int)$per_page . " OFFSET " . (int)$offset;
    $stmt = $db->conn->prepare($query);
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_msg = 'Failed to load notifications: ' . $e->getMessage();
}

$total_pages = ceil($total_notifications / $per_page);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Shebamiles EMS</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .notifications-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .notifications-header h1 {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #333;
        }

        .unread-badge {
            background-color: #FF6B35;
            color: white;
            border-radius: 20px;
            padding: 4px 12px;
            font-size: 12px;
            font-weight: 700;
        }

        .notification-actions {
            display: flex;
            gap: 10px;
        }

        .btn-small {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 13px;
        }

        .btn-primary-small {
            background-color: #FF6B35;
            color: white;
        }

        .btn-primary-small:hover {
            background-color: #e55a25;
        }

        .btn-secondary-small {
            background-color: #f0f0f0;
            color: #333;
        }

        .btn-secondary-small:hover {
            background-color: #e0e0e0;
        }

        .btn-danger-small {
            background-color: #dc3545;
            color: white;
        }

        .btn-danger-small:hover {
            background-color: #c82333;
        }

        .notification-list {
            display: flex;
            flex-direction: column;
            gap: 0;
        }

        .notification-item {
            border: 1px solid #eee;
            padding: 20px;
            margin-bottom: -1px;
            background: white;
            transition: all 0.3s ease;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .notification-item:hover {
            background-color: #f9f9f9;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .notification-item.unread {
            background-color: #fffbf0;
            border-left: 4px solid #FF6B35;
        }

        .notification-item.unread .notification-content strong {
            color: #FF6B35;
        }

        .notification-content {
            flex: 1;
            min-width: 0;
        }

        .notification-icon {
            font-size: 24px;
            margin-right: 10px;
        }

        .notification-title {
            font-weight: 700;
            color: #333;
            margin-bottom: 4px;
        }

        .notification-message {
            color: #666;
            font-size: 14px;
            margin-bottom: 8px;
            word-break: break-word;
        }

        .notification-meta {
            font-size: 12px;
            color: #999;
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .notification-time {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .notification-type {
            display: inline-block;
            background-color: #f0f0f0;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            color: #666;
        }

        .notification-actions-item {
            display: flex;
            gap: 8px;
            flex-shrink: 0;
        }

        .notification-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 6px 12px;
            border-radius: 4px;
            transition: all 0.3s ease;
            font-size: 12px;
            font-weight: 600;
        }

        .notification-btn-read {
            color: #FF6B35;
            background-color: rgba(255, 107, 53, 0.1);
        }

        .notification-btn-read:hover {
            background-color: rgba(255, 107, 53, 0.2);
        }

        .notification-btn-delete {
            color: #dc3545;
            background-color: rgba(220, 53, 69, 0.1);
        }

        .notification-btn-delete:hover {
            background-color: rgba(220, 53, 69, 0.2);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 10px;
            color: #666;
        }

        .empty-state-text {
            font-size: 14px;
            margin-bottom: 20px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            font-size: 13px;
            transition: all 0.3s ease;
        }

        .pagination a:hover {
            background-color: #FF6B35;
            color: white;
            border-color: #FF6B35;
        }

        .pagination .current {
            background-color: #FF6B35;
            color: white;
            border-color: #FF6B35;
            font-weight: 700;
        }

        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        @media (max-width: 768px) {
            .notification-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .notification-actions-item {
                width: 100%;
                justify-content: flex-start;
            }

            .notifications-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .filter-section {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <?php include __DIR__ . '/../includes/badge.php'; ?>

    <div class="main-content">
        <div class="notifications-header">
            <div>
                <h1>
                    🔔 Notifications
                    <?php if ($unread_count > 0): ?>
                        <span class="unread-badge"><?php echo $unread_count; ?> Unread</span>
                    <?php endif; ?>
                </h1>
            </div>
            <div class="notification-actions">
                <?php if ($unread_count > 0): ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="mark_all_read">
                        <button type="submit" class="btn-small btn-primary-small">✓ Mark All as Read</button>
                    </form>
                <?php endif; ?>
                <?php if ($total_notifications > 0): ?>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to clear all notifications?');">
                        <input type="hidden" name="action" value="clear_all">
                        <button type="submit" class="btn-small btn-danger-small">🗑️ Clear All</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($success_msg): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_msg); ?></div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <?php if (empty($notifications)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">🎉</div>
                <div class="empty-state-title">No Notifications</div>
                <div class="empty-state-text">You're all caught up! No notifications at this time.</div>
            </div>
        <?php else: ?>
            <div class="notification-list">
                <?php foreach ($notifications as $notification): 
                    $is_unread = $notification['is_read'] == 0;
                    $time_ago = time_ago($notification['created_at']);
                    $icon = match($notification['type']) {
                        'info' => 'ℹ️',
                        'success' => '✓',
                        'warning' => '⚠️',
                        'error' => '❌',
                        'leave' => '📅',
                        'attendance' => '📍',
                        'payroll' => '💰',
                        'announcement' => '📢',
                        default => '🔔'
                    };
                ?>
                    <div class="notification-item <?php echo $is_unread ? 'unread' : ''; ?>">
                        <div class="notification-content">
                            <div class="notification-title">
                                <span class="notification-icon"><?php echo $icon; ?></span>
                                <?php echo htmlspecialchars($notification['title']); ?>
                            </div>
                            <div class="notification-message">
                                <?php echo htmlspecialchars($notification['message']); ?>
                            </div>
                            <div class="notification-meta">
                                <span class="notification-time">
                                    🕒 <?php echo $time_ago; ?>
                                </span>
                                <span class="notification-type"><?php echo ucfirst($notification['type']); ?></span>
                                <?php if ($notification['link']): ?>
                                    <a href="<?php echo htmlspecialchars(preg_match('#^(https?:)?//|^/#', $notification['link']) ? $notification['link'] : appUrl($notification['link'])); ?>" style="color: #FF6B35; text-decoration: none; font-weight: 600;">
                                        View Details →
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="notification-actions-item">
                            <?php if ($is_unread): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="mark_read">
                                    <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                    <button type="submit" class="notification-btn notification-btn-read" title="Mark as read">✓ Read</button>
                                </form>
                            <?php endif; ?>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this notification?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                <button type="submit" class="notification-btn notification-btn-delete" title="Delete notification">🗑️ Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=1">« First</a>
                        <a href="?page=<?php echo $page - 1; ?>">‹ Previous</a>
                    <?php endif; ?>

                    <?php
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    
                    if ($start > 1) echo '<span>...</span>';
                    
                    for ($i = $start; $i <= $end; $i++) {
                        if ($i === $page) {
                            echo '<span class="current">' . $i . '</span>';
                        } else {
                            echo '<a href="?page=' . $i . '">' . $i . '</a>';
                        }
                    }
                    
                    if ($end < $total_pages) echo '<span>...</span>';
                    ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>">Next ›</a>
                        <a href="?page=<?php echo $total_pages; ?>">Last »</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
        function time_ago(time_str) {
            const time = new Date(time_str);
            const now = new Date();
            const diff = Math.floor((now - time) / 1000);

            if (diff < 60) return diff + ' seconds ago';
            if (diff < 3600) return Math.floor(diff / 60) + ' minutes ago';
            if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
            if (diff < 604800) return Math.floor(diff / 86400) + ' days ago';
            
            return time.toLocaleDateString();
        }
    </script>
</body>
</html>

<?php
function time_ago($time_str) {
    $time = new DateTime($time_str);
    $now = new DateTime();
    $diff = $now->getTimestamp() - $time->getTimestamp();

    if ($diff < 60) return $diff . ' second' . ($diff !== 1 ? 's' : '') . ' ago';
    if ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins !== 1 ? 's' : '') . ' ago';
    }
    if ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours !== 1 ? 's' : '') . ' ago';
    }
    if ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days !== 1 ? 's' : '') . ' ago';
    }
    return $time->format('M d, Y');
}
?>
