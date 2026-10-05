<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireLogin();
requirePermission('view_activity_log');

// Database connection check
if (!(new Database())->getConnection()) {
    header('Location: ../dashboard.php?db=missing');
    exit();
}

$filter_user = isset($_GET['user']) ? intval($_GET['user']) : null;
$filter_action = isset($_GET['action']) ? $_GET['action'] : null;
$filter_entity = isset($_GET['entity']) ? $_GET['entity'] : null;
$date_from = isset($_GET['from']) ? $_GET['from'] : null;
$date_to = isset($_GET['to']) ? $_GET['to'] : null;

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

$activity_logs = [];
$total_logs = 0;
$users_list = [];
$actions_list = [];
$entities_list = [];

try {
    $db = new Database();
    
    // Get list of users for filter dropdown
    $query = "SELECT DISTINCT user_id FROM activity_log ORDER BY user_id";
    $stmt = $db->conn->prepare($query);
    $stmt->execute();
    $user_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($user_ids)) {
        $placeholders = str_repeat('?,', count($user_ids) - 1) . '?';
        $query = "SELECT u.user_id, COALESCE(NULLIF(TRIM(CONCAT(e.first_name, ' ', e.last_name)), ''), u.username) AS full_name FROM users u LEFT JOIN employees e ON e.user_id = u.user_id WHERE u.user_id IN ($placeholders)";
        $stmt = $db->conn->prepare($query);
        $stmt->execute($user_ids);
        $users_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get list of actions for filter dropdown
    $query = "SELECT DISTINCT action FROM activity_log ORDER BY action";
    $stmt = $db->conn->prepare($query);
    $stmt->execute();
    $actions_list = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get list of entities for filter dropdown
    $query = "SELECT DISTINCT entity_type FROM activity_log ORDER BY entity_type";
    $stmt = $db->conn->prepare($query);
    $stmt->execute();
    $entities_list = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Build main query with filters
    $query = "SELECT COUNT(*) as total FROM activity_log WHERE 1=1";
    $params = [];
    
    if ($filter_user) {
        $query .= " AND user_id = ?";
        $params[] = $filter_user;
    }
    if ($filter_action) {
        $query .= " AND action = ?";
        $params[] = $filter_action;
    }
    if ($filter_entity) {
        $query .= " AND entity_type = ?";
        $params[] = $filter_entity;
    }
    if ($date_from) {
        $query .= " AND DATE(created_at) >= ?";
        $params[] = $date_from;
    }
    if ($date_to) {
        $query .= " AND DATE(created_at) <= ?";
        $params[] = $date_to;
    }
    
    $stmt = $db->conn->prepare($query);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_logs = $result['total'] ?? 0;
    
    // Get paginated activity logs
    $query = "SELECT a.*, COALESCE(NULLIF(TRIM(CONCAT(e.first_name, ' ', e.last_name)), ''), u.username) AS full_name FROM activity_log a 
              LEFT JOIN users u ON a.user_id = u.user_id 
              LEFT JOIN employees e ON e.user_id = u.user_id
              WHERE 1=1";
    
    if ($filter_user) {
        $query .= " AND a.user_id = ?";
    }
    if ($filter_action) {
        $query .= " AND a.action = ?";
    }
    if ($filter_entity) {
        $query .= " AND a.entity_type = ?";
    }
    if ($date_from) {
        $query .= " AND DATE(a.created_at) >= ?";
    }
    if ($date_to) {
        $query .= " AND DATE(a.created_at) <= ?";
    }
    
    $query .= " ORDER BY a.created_at DESC LIMIT " . (int)$per_page . " OFFSET " . (int)$offset;
    
    $stmt = $db->conn->prepare($query);
    $stmt->execute($params);
    $activity_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_msg = 'Failed to load activity logs: ' . $e->getMessage();
}

$total_pages = ceil($total_logs / $per_page);

// Get action icon
function getActionIcon($action) {
    return match(strtolower($action)) {
        'create' => '➕',
        'read' => '👁️',
        'update', 'edit' => '✏️',
        'delete' => '🗑️',
        'login' => '🔓',
        'logout' => '🔐',
        'upload_document' => '📤',
        'download_document' => '📥',
        'approve_leave' => '✅',
        'reject_leave' => '❌',
        default => '📌'
    };
}

// Get entity icon
function getEntityIcon($entity) {
    return match(strtolower($entity)) {
        'employees' => '👤',
        'departments' => '🏢',
        'attendance' => '📍',
        'leave_requests' => '📅',
        'users' => '👥',
        'payroll' => '💰',
        'performance_reviews' => '⭐',
        'documents' => '📂',
        'settings' => '⚙️',
        'notifications' => '🔔',
        default => '📄'
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log - Shebamiles EMS</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
            font-size: 13px;
        }

        .filter-group select,
        .filter-group input {
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            font-family: inherit;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #FF6B35;
            box-shadow: 0 0 0 2px rgba(255, 107, 53, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn-filter {
            padding: 8px 16px;
            background-color: #FF6B35;
            color: white;
            border: none;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.3s ease;
        }

        .btn-filter:hover {
            background-color: #e55a25;
        }

        .btn-reset {
            padding: 8px 16px;
            background-color: #f0f0f0;
            color: #333;
            border: none;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.3s ease;
        }

        .btn-reset:hover {
            background-color: #e0e0e0;
        }

        .activity-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .activity-table thead {
            background-color: #f8f8f8;
            border-bottom: 2px solid #eee;
        }

        .activity-table th {
            padding: 15px;
            text-align: left;
            font-weight: 700;
            color: #333;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .activity-table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
            font-size: 13px;
            color: #666;
        }

        .activity-table tbody tr:hover {
            background-color: #f9f9f9;
        }

        .activity-table tbody tr:last-child td {
            border-bottom: none;
        }

        .activity-user {
            font-weight: 600;
            color: #333;
        }

        .activity-action {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background-color: #f0f0f0;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            color: #666;
        }

        .activity-entity {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background-color: rgba(255, 107, 53, 0.1);
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            color: #FF6B35;
        }

        .activity-description {
            max-width: 300px;
            word-break: break-word;
            color: #666;
        }

        .activity-time {
            color: #999;
            font-size: 12px;
            white-space: nowrap;
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

        .empty-state-title {
            font-size: 20px;
            font-weight: 700;
            color: #666;
            margin-bottom: 10px;
        }

        @media (max-width: 768px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }

            .filter-actions {
                flex-direction: column;
            }

            .activity-table {
                font-size: 11px;
            }

            .activity-table th,
            .activity-table td {
                padding: 10px;
            }

            .activity-description {
                max-width: 150px;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <?php include __DIR__ . '/../includes/badge.php'; ?>

    <div class="main-content">
        <div class="header">
            <h1>📊 Activity Log Viewer</h1>
            <p>Monitor all user activities and system events</p>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" class="filter-form">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label for="filter_user">User</label>
                        <select id="filter_user" name="user">
                            <option value="">All Users</option>
                            <?php foreach ($users_list as $user): ?>
                                <option value="<?php echo $user['user_id']; ?>" 
                                    <?php echo $filter_user == $user['user_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="filter_action">Action</label>
                        <select id="filter_action" name="action">
                            <option value="">All Actions</option>
                            <?php foreach ($actions_list as $action): ?>
                                <option value="<?php echo htmlspecialchars($action); ?>" 
                                    <?php echo $filter_action == $action ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($action))); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="filter_entity">Entity Type</label>
                        <select id="filter_entity" name="entity">
                            <option value="">All Entities</option>
                            <?php foreach ($entities_list as $entity): ?>
                                <option value="<?php echo htmlspecialchars($entity); ?>" 
                                    <?php echo $filter_entity == $entity ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($entity))); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="date_from">From Date</label>
                        <input type="date" id="date_from" name="from" 
                               value="<?php echo htmlspecialchars($date_from ?? ''); ?>">
                    </div>

                    <div class="filter-group">
                        <label for="date_to">To Date</label>
                        <input type="date" id="date_to" name="to" 
                               value="<?php echo htmlspecialchars($date_to ?? ''); ?>">
                    </div>
                </div>

                <div class="filter-actions">
                    <button type="reset" class="btn-reset" onclick="window.location='activity-log.php'">Clear Filters</button>
                    <button type="submit" class="btn-filter">🔍 Filter Logs</button>
                </div>
            </form>
        </div>

        <!-- Activity Log Table -->
        <?php if (empty($activity_logs)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <div class="empty-state-title">No Activity Logs Found</div>
                <p>No activities match your filter criteria.</p>
            </div>
        <?php else: ?>
            <table class="activity-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Action</th>
                        <th>Entity</th>
                        <th>Description</th>
                        <th>IP Address</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activity_logs as $log): ?>
                        <tr>
                            <td>
                                <span class="activity-user">
                                    <?php echo htmlspecialchars($log['full_name'] ?? 'Unknown'); ?>
                                </span>
                            </td>
                            <td>
                                <span class="activity-action">
                                    <?php echo getActionIcon($log['action']); ?>
                                    <?php echo str_replace('_', ' ', htmlspecialchars($log['action'])); ?>
                                </span>
                            </td>
                            <td>
                                <span class="activity-entity">
                                    <?php echo getEntityIcon($log['entity_type']); ?>
                                    <?php echo str_replace('_', ' ', htmlspecialchars($log['entity_type'])); ?>
                                </span>
                            </td>
                            <td class="activity-description">
                                <?php echo htmlspecialchars($log['description']); ?>
                            </td>
                            <td>
                                <code style="background: #f0f0f0; padding: 2px 6px; border-radius: 3px; font-size: 11px;">
                                    <?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?>
                                </code>
                            </td>
                            <td class="activity-time">
                                <?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">« First</a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">‹ Previous</a>
                    <?php endif; ?>

                    <?php
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    
                    if ($start > 1) echo '<span>...</span>';
                    
                    for ($i = $start; $i <= $end; $i++) {
                        if ($i === $page) {
                            echo '<span class="current">' . $i . '</span>';
                        } else {
                            echo '<a href="?'. http_build_query(array_merge($_GET, ['page' => $i])) . '">' . $i . '</a>';
                        }
                    }
                    
                    if ($end < $total_pages) echo '<span>...</span>';
                    ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next ›</a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>">Last »</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
