<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireLogin();
requirePermission('view_announcements');

$user_id = $_SESSION['user_id'];
$is_admin = hasPermission('create_announcement');
$success_msg = '';
$error_msg = '';

// Handle announcement creation (admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hasPermission('create_announcement')) {
    try {
        $db = new Database();
        
        $title = $_POST['announcement_title'] ?? '';
        $content = $_POST['announcement_content'] ?? '';
        $priority = $_POST['priority'] ?? 'normal';
        $target_audience = $_POST['target_audience'] ?? 'all';
        $expire_date = !empty($_POST['expire_date']) ? $_POST['expire_date'] : null;
        
        if (empty($title) || empty($content)) {
            throw new Exception('Title and content are required');
        }
        
        $query = "INSERT INTO announcements (title, content, priority, target_audience, expire_date, created_by, created_at) 
                  VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $db->conn->prepare($query);
        $stmt->execute([$title, $content, $priority, $target_audience, $expire_date, $user_id]);
        
        logActivity($user_id, 'CREATE_ANNOUNCEMENT', 'announcements', $db->conn->lastInsertId(), 
                   'Created announcement: ' . $title);
        
        $success_msg = 'Announcement published successfully!';
    } catch (Exception $e) {
        $error_msg = 'Error creating announcement: ' . $e->getMessage();
    }
}

// Handle announcement deletion (admin only)
if (isset($_GET['delete']) && hasPermission('delete_announcement')) {
    try {
        $db = new Database();
        $query = "DELETE FROM announcements WHERE announcement_id = ?";
        $stmt = $db->conn->prepare($query);
        $stmt->execute([$_GET['delete']]);
        
        logActivity($user_id, 'DELETE_ANNOUNCEMENT', 'announcements', $_GET['delete'], 'Deleted announcement');
        
        $success_msg = 'Announcement deleted successfully!';
    } catch (PDOException $e) {
        $error_msg = 'Error deleting announcement: ' . $e->getMessage();
    }
}

// Get announcements
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$announcements = [];
$total_announcements = 0;

try {
    $db = new Database();
    
    // For non-admins, only show non-expired announcements
    // Roles are singular (manager) but the audience values are plural (managers)
    $audience = ['admin' => 'admins', 'manager' => 'managers', 'employee' => 'employees'][$_SESSION['role']] ?? 'all';
    $where = "WHERE is_published = 1 AND (expire_date IS NULL OR expire_date >= CURDATE())";
    if (!$is_admin) {
        $where .= " AND (target_audience = 'all' OR target_audience = ?)";
    }
    
    $query = "SELECT COUNT(*) as total FROM announcements $where";
    $stmt = $db->conn->prepare($query);
    
    if (!$is_admin) {
        $stmt->execute([$audience]);
    } else {
        $stmt->execute();
    }
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_announcements = $result['total'] ?? 0;
    
    // Get announcements with user info
    $query = "SELECT a.*, a.announcement_id AS id,
                     COALESCE(NULLIF(TRIM(CONCAT(e.first_name, ' ', e.last_name)), ''), u.username) AS full_name
              FROM announcements a
              LEFT JOIN users u ON a.created_by = u.user_id
              LEFT JOIN employees e ON e.user_id = u.user_id
              $where
              ORDER BY a.created_at DESC LIMIT " . (int)$per_page . " OFFSET " . (int)$offset;
    
    $stmt = $db->conn->prepare($query);
    
    if (!$is_admin) {
        $stmt->execute([$audience]);
    } else {
        $stmt->execute();
    }
    
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_msg = 'Failed to load announcements: ' . $e->getMessage();
}

$total_pages = ceil($total_announcements / $per_page);

function getPriorityBadge($priority) {
    return match(strtolower($priority)) {
        'high' => '<span style="display: inline-block; background-color: #F44336; color: white; padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 600;">🔴 High</span>',
        'medium' => '<span style="display: inline-block; background-color: #FFC107; color: #333; padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 600;">🟡 Medium</span>',
        default => '<span style="display: inline-block; background-color: #4CAF50; color: white; padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 600;">🟢 Normal</span>'
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - Shebamiles EMS</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .announcements-container {
            padding: 20px 0;
        }

        .create-announcement-btn {
            background-color: #FF6B35;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 30px;
        }

        .create-announcement-btn:hover {
            background-color: #e55a25;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-overlay {
            background: white;
            border-radius: 8px;
            padding: 30px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
            float: right;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .modal-close:hover {
            background-color: #f0f0f0;
        }

        .modal h2 {
            color: #FF6B35;
            margin-top: 0;
            margin-bottom: 20px;
            clear: both;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-family: inherit;
            font-size: 14px;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 150px;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #FF6B35;
            box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.1);
        }

        .form-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background-color: #FF6B35;
            color: white;
        }

        .btn-primary:hover {
            background-color: #e55a25;
        }

        .btn-secondary {
            background-color: #f0f0f0;
            color: #333;
        }

        .btn-secondary:hover {
            background-color: #e0e0e0;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
        }

        .announcement-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .announcement-card {
            background: white;
            border-left: 4px solid #FF6B35;
            padding: 20px;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .announcement-card:hover {
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
        }

        .announcement-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .announcement-title {
            font-size: 18px;
            font-weight: 700;
            color: #333;
            margin: 0;
        }

        .announcement-meta {
            font-size: 12px;
            color: #999;
            margin-top: 8px;
        }

        .announcement-content {
            margin: 15px 0;
            color: #666;
            line-height: 1.6;
        }

        .announcement-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .announcement-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .announcement-btn-delete {
            background-color: #f0f0f0;
            color: #dc3545;
        }

        .announcement-btn-delete:hover {
            background-color: #e0e0e0;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 30px;
        }

        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            font-size: 13px;
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
        }

        @media (max-width: 768px) {
            .announcement-header {
                flex-direction: column;
            }

            .modal-overlay {
                width: 95%;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <?php include __DIR__ . '/../includes/badge.php'; ?>

    <div class="main-content">
        <div class="header">
            <h1>📢 Announcements</h1>
            <p>Stay updated with company news and updates</p>
        </div>

        <?php if ($success_msg): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_msg); ?></div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <div class="announcements-container">
            <?php if ($is_admin): ?>
                <button class="create-announcement-btn" onclick="openCreateModal()">
                    ➕ Create Announcement
                </button>
            <?php endif; ?>

            <?php if (empty($announcements)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <h3>No Announcements</h3>
                    <p>Check back later for company announcements.</p>
                </div>
            <?php else: ?>
                <div class="announcement-list">
                    <?php foreach ($announcements as $announcement): 
                        $is_expired = $announcement['expire_date'] && strtotime($announcement['expire_date']) < time();
                    ?>
                        <div class="announcement-card" style="<?php echo $is_expired ? 'opacity: 0.7;' : ''; ?>">
                            <div class="announcement-header">
                                <div>
                                    <h3 class="announcement-title">
                                        <?php echo htmlspecialchars($announcement['title']); ?>
                                    </h3>
                                    <div class="announcement-meta">
                                        <?php echo getPriorityBadge($announcement['priority']); ?>
                                        <span style="margin-left: 15px;">
                                            👤 <?php echo htmlspecialchars($announcement['full_name'] ?? 'Admin'); ?>
                                        </span>
                                        <span style="margin-left: 15px;">
                                            📅 <?php echo date('M d, Y H:i', strtotime($announcement['created_at'])); ?>
                                        </span>
                                        <?php if ($announcement['expire_date']): ?>
                                            <span style="margin-left: 15px;">
                                                ⏰ Expires: <?php echo date('M d, Y', strtotime($announcement['expire_date'])); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($is_admin && $announcement['created_by'] == $user_id): ?>
                                    <form method="GET" style="margin: 0; display: inline;" onsubmit="return confirm('Delete this announcement?');">
                                        <input type="hidden" name="delete" value="<?php echo $announcement['id']; ?>">
                                        <button type="submit" class="announcement-btn announcement-btn-delete">🗑️ Delete</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                            <div class="announcement-content">
                                <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
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
    </div>

    <!-- Create Announcement Modal -->
    <div class="modal" id="createModal">
        <div class="modal-overlay">
            <button class="modal-close" onclick="closeCreateModal()">✕</button>
            <h2>Create Announcement</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="announcement_title">Title *</label>
                    <input type="text" id="announcement_title" name="announcement_title" required>
                </div>

                <div class="form-group">
                    <label for="announcement_content">Content *</label>
                    <textarea id="announcement_content" name="announcement_content" required></textarea>
                </div>

                <div class="form-group">
                    <label for="priority">Priority</label>
                    <select id="priority" name="priority">
                        <option value="normal">🟢 Normal (Low)</option>
                        <option value="medium">🟡 Medium</option>
                        <option value="high">🔴 High (Urgent)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="target_audience">Target Audience</label>
                    <select id="target_audience" name="target_audience">
                        <option value="all">All Employees</option>
                        <option value="admin">Admin & Managers</option>
                        <option value="employee">Employees Only</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="expire_date">Expiration Date (Optional)</label>
                    <input type="date" id="expire_date" name="expire_date">
                </div>

                <div class="form-buttons">
                    <button type="button" class="btn btn-secondary" onclick="closeCreateModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">📢 Publish</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openCreateModal() {
            document.getElementById('createModal').classList.add('show');
        }

        function closeCreateModal() {
            document.getElementById('createModal').classList.remove('show');
        }

        // Close modal when clicking outside
        document.getElementById('createModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeCreateModal();
            }
        });
    </script>
</body>
</html>
