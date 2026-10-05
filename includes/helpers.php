<?php
/**
 * System Settings Helper Functions
 * Manages company settings and configuration
 */

/**
 * GET SETTING WITH CACHING
 * 
 * OPTIMIZATION PATTERN:
 * This function uses static variable caching to avoid repeated database queries.
 * 
 * FIRST CALL:
 *   - $settings is null
 *   - Query database once: SELECT ALL settings
 *   - Store in static $settings variable
 *   - Return requested setting
 * 
 * SUBSEQUENT CALLS:
 *   - $settings already loaded in memory
 *   - No database query needed
 *   - Return from cache array
 * 
 * ADVANTAGE:
 *   If a page uses getSetting() 10 times, it only queries database once
 *   instead of 10 times, significantly improving performance for pages
 *   like dashboard.php that call this many times.
 * 
 * TRADEOFF:
 *   If settings are changed mid-request (rare), the changes won't be visible
 *   until next page load. This is acceptable because:
 *   - Settings rarely change during request execution
 *   - Single-request performance is more important
 *   - Each new page load clears the static cache
 */

// Get a specific setting value
function getSetting($key, $default = null) {
    // OPTIMIZATION: Static variable persists for duration of page load
    // 'static' means $settings is initialized once per PHP execution
    // and retains value between function calls
    static $settings = null;
    
    // FIRST-TIME INITIALIZATION
    if ($settings === null) {
        $db = new Database();
        $conn = $db->getConnection();
        
        // Gracefully handle database unavailability
        if ($conn === null) {
            return $default;  // Return default value if DB is down
        }
        
        try {
            // Load all settings at once using associative array fetch
            // PDO::FETCH_KEY_PAIR converts result into key=>value array
            // Example result:
            // [
            //   'company_name' => 'Shebamiles',
            //   'timezone' => 'UTC',
            //   'working_days' => '5'
            // ]
            $stmt = $conn->query("SELECT setting_key, setting_value FROM company_settings");
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch(PDOException $e) {
            // Query failed, return default
            return $default;
        }
    }
    
    // Return specific setting or default if not found
    // ?? (null coalescing) operator: use second value if first is not set
    return isset($settings[$key]) ? $settings[$key] : $default;
}

// Update a setting value
function updateSetting($key, $value, $userId = null) {
    $db = new Database();
    $conn = $db->getConnection();
    
    if ($conn === null) {
        return false;
    }
    
    try {
        $stmt = $conn->prepare("INSERT INTO company_settings (setting_key, setting_value, updated_by) 
                               VALUES (?, ?, ?)
                               ON DUPLICATE KEY UPDATE setting_value = ?, updated_by = ?");
        $stmt->execute([$key, $value, $userId, $value, $userId]);
        return true;
    } catch(PDOException $e) {
        return false;
    }
}

// Get all settings by group
function getSettingsByGroup($group = 'general') {
    $db = new Database();
    $conn = $db->getConnection();
    
    if ($conn === null) {
        return [];
    }
    
    try {
        $stmt = $conn->prepare("SELECT setting_key, setting_value 
                               FROM company_settings 
                               WHERE setting_group = ?");
        $stmt->execute([$group]);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch(PDOException $e) {
        return [];
    }
}

/**
 * ACTIVITY LOGGING FUNCTION
 * 
 * Records user actions to audit trail for:
 * - Compliance and regulations
 * - Security investigation
 * - Change tracking
 * - User accountability
 * 
 * CAPTURED INFORMATION:
 * - WHO: user_id (who performed action)
 * - WHAT: action (create/update/delete/view), entity_type (employee/payroll/etc)
 * - WHICH: entity_id (ID of affected record)
 * - WHY: description (reason/details)
 * - WHEN: created_at (timestamp, automatic)
 * - WHERE FROM: ip_address (user's IP for security)
 * - HOW: user_agent (browser/device details)
 * 
 * USAGE EXAMPLES:
 * logActivity($userId, 'create', 'employee', 42, 'Created new employee record')
 * logActivity($userId, 'update', 'attendance', 100, 'Marked present for 2026-02-08')
 * logActivity($userId, 'delete', 'leave_request', 5, 'Cancelled leave request')
 * 
 * SECURITY NOTE:
 * - IP address stored for geographic security checks
 * - User agent reveals technology (important for breach investigation)
 * - Enables detection of suspicious patterns (e.g., sudden bulk deletes)
 */

// Log activity
function logActivity($userId, $action, $entityType = null, $entityId = null, $description = null) {
    $db = new Database();
    $conn = $db->getConnection();
    
    if ($conn === null) {
        return false;  // DB down, silently fail (don't prevent user action)
    }
    
    try {
        // CAPTURE REQUEST CONTEXT
        // $_SERVER['REMOTE_ADDR']: Client IP address
        // Useful for detecting location-based attacks
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        
        // $_SERVER['HTTP_USER_AGENT']: Browser/device details
        // Example: "Mozilla/5.0 (Windows NT 10.0; Win64; x64)..."
        // Useful for device-based authentication and anomaly detection
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        // INSERT new log record with all contextual information
        $stmt = $conn->prepare("INSERT INTO activity_log 
                               (user_id, action, entity_type, entity_id, description, ip_address, user_agent) 
                               VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([$userId, $action, $entityType, $entityId, $description, $ipAddress, $userAgent]);
        return true;
    } catch(PDOException $e) {
        // Silently fail - logging shouldn't block user actions
        return false;
    }
}

/**
 * GET RECENT ACTIVITY LOGS WITH FILTER
 * 
 * PATTERN: Dynamic SQL Query Building
 * This demonstrates conditional SQL construction for flexible queries:
 * - Base query: all activity with user info (JOIN)
 * - Optional userId filter: WHERE al.user_id = ?
 * - Limit clause: LIMIT 50
 * 
 * Two different execute() calls:
 * 1. WITH userId: execute([$userId, $limit])
 * 2. WITHOUT userId: execute([$limit])
 * 
 * This pattern allows same function to handle both:
 * - System admin view: all activity from all users
 * - User activity view: just one user's actions
 * 
 * RETURNED DATA:
 * Array with columns:
 * - log_id: Log record identifier
 * - user_id: Who performed action
 * - username: User's login name
 * - role: User's access level
 * - action: create/update/delete/view
 * - entity_type: employee/payroll/attendance/etc
 * - entity_id: Which record was affected
 * - description: Change details
 * - ip_address: Where request came from
 * - user_agent: What browser/device
 * - created_at: When action occurred
 */

// Get recent activity logs
function getRecentActivity($limit = 50, $userId = null) {
    $db = new Database();
    $conn = $db->getConnection();
    
    if ($conn === null) {
        return [];  // Return empty array if DB unavailable
    }
    
    try {
        // STEP 1: Build base query with inner JOIN to users table
        // This fetches:
        // - All activity_log columns (log_id, action, entity_type, etc)
        // - Plus username and role from users table
        // - Useful for displaying "Admin John updated Employee record"
        $query = "SELECT al.*, u.username, u.role 
                  FROM activity_log al
                  JOIN users u ON al.user_id = u.user_id";
        
        // STEP 2: Conditionally add WHERE clause if userId specified
        // This allows filtering to single user's activity
        if ($userId) {
            $query .= " WHERE al.user_id = ?";  // Filter by user
        }
        
        // STEP 3: Add LIMIT and ORDER BY
        // ORDER BY created_at DESC: Most recent first
        // LIMIT: Prevent returning millions of rows (e.g., limit to 50)
        $query .= " ORDER BY al.created_at DESC LIMIT " . (int)$limit;
        
        // STEP 4: Prepare statement once (same for both cases)
        $stmt = $conn->prepare($query);
        
        // STEP 5: Execute with different parameters based on whether userId provided
        if ($userId) {
            // Case 1: Filtering by user - pass userId and limit
            // [$userId, $limit] -> replaces both ? in query
            $stmt->execute([$userId]);
        } else {
            // Case 2: No filter - pass only limit
            $stmt->execute();
        }
        
        // STEP 6: Return all matching rows
        return $stmt->fetchAll();  // Returns array of all rows
        
    } catch(PDOException $e) {
        return [];  // Return empty on error
    }
}

// Check if today is a holiday
function isHoliday($date = null) {
    $date = $date ?? date('Y-m-d');
    
    $db = new Database();
    $conn = $db->getConnection();
    
    if ($conn === null) {
        return false;
    }
    
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM holidays WHERE holiday_date = ?");
        $stmt->execute([$date]);
        return $stmt->fetchColumn() > 0;
    } catch(PDOException $e) {
        return false;
    }
}

// Get upcoming holidays
function getUpcomingHolidays($limit = 5) {
    $db = new Database();
    $conn = $db->getConnection();
    
    if ($conn === null) {
        return [];
    }
    
    try {
        $stmt = $conn->prepare("SELECT * FROM holidays 
                               WHERE holiday_date >= CURDATE() 
                               ORDER BY holiday_date ASC 
                               LIMIT " . (int)$limit);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        return [];
    }
}

// Get employee leave balance
function getLeaveBalance($employeeId, $leaveType = null, $year = null) {
    $year = $year ?? date('Y');
    
    $db = new Database();
    $conn = $db->getConnection();
    
    if ($conn === null) {
        return [];
    }
    
    try {
        if ($leaveType) {
            $stmt = $conn->prepare("SELECT * FROM leave_balance 
                                   WHERE employee_id = ? AND leave_type = ? AND year = ?");
            $stmt->execute([$employeeId, $leaveType, $year]);
            return $stmt->fetch();
        } else {
            $stmt = $conn->prepare("SELECT * FROM leave_balance 
                                   WHERE employee_id = ? AND year = ?");
            $stmt->execute([$employeeId, $year]);
            return $stmt->fetchAll();
        }
    } catch(PDOException $e) {
        return $leaveType ? null : [];
    }
}

// Update leave balance
function updateLeaveBalance($employeeId, $leaveType, $daysUsed, $year = null) {
    $year = $year ?? date('Y');
    
    $db = new Database();
    $conn = $db->getConnection();
    
    if ($conn === null) {
        return false;
    }
    
    try {
        $stmt = $conn->prepare("UPDATE leave_balance 
                               SET used_days = used_days + ? 
                               WHERE employee_id = ? AND leave_type = ? AND year = ?");
        $stmt->execute([$daysUsed, $employeeId, $leaveType, $year]);
        return true;
    } catch(PDOException $e) {
        return false;
    }
}

// Create notification
function createNotification($userId, $title, $message, $type = 'info', $link = null) {
    $db = new Database();
    $conn = $db->getConnection();
    
    if ($conn === null) {
        return false;
    }
    
    try {
        $stmt = $conn->prepare("INSERT INTO notifications 
                               (user_id, title, message, type, link) 
                               VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $title, $message, $type, $link]);
        return true;
    } catch(PDOException $e) {
        return false;
    }
}

// Get unread notification count
function getUnreadNotificationCount($userId) {
    $db = new Database();
    $conn = $db->getConnection();
    
    if ($conn === null) {
        return 0;
    }
    
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications 
                               WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch(PDOException $e) {
        return 0;
    }
}

// Get user notifications
function getUserNotifications($userId, $limit = 10, $unreadOnly = false) {
    $db = new Database();
    $conn = $db->getConnection();
    
    if ($conn === null) {
        return [];
    }
    
    try {
        $query = "SELECT * FROM notifications WHERE user_id = ?";
        if ($unreadOnly) {
            $query .= " AND is_read = 0";
        }
        $query .= " ORDER BY created_at DESC LIMIT " . (int)$limit;
        
        $stmt = $conn->prepare($query);
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        return [];
    }
}

// Mark notification as read
function markNotificationRead($notificationId) {
    $db = new Database();
    $conn = $db->getConnection();
    
    if ($conn === null) {
        return false;
    }
    
    try {
        $stmt = $conn->prepare("UPDATE notifications 
                               SET is_read = 1, read_at = NOW() 
                               WHERE notification_id = ?");
        $stmt->execute([$notificationId]);
        return true;
    } catch(PDOException $e) {
        return false;
    }
}

// Format currency
function formatCurrency($amount) {
    $symbol = getSetting('currency_symbol', '₦');
    return $symbol . number_format($amount, 2);
}

// Get company info
function getCompanyInfo() {
    return [
        'name' => getSetting('company_name', 'Shebamiles'),
        'email' => getSetting('company_email', 'info@shebamiles.com'),
        'phone' => getSetting('company_phone', '+234-000-0000'),
        'address' => getSetting('company_address', 'Lagos, Nigeria')
    ];
}
?>
