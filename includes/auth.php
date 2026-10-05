<?php
/**
 * Authentication Functions for Shebamiles EMS
 */

session_start();
require_once __DIR__ . '/config.php';

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

// Check user role
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit();
    }
}

// Redirect if not admin
function requireAdmin() {
    requireLogin();
    if (!hasRole('admin')) {
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit();
    }
}

/**
 * AUTHENTICATION FUNCTION: login()
 * Authenticates user based on username/password credentials
 * 
 * SECURITY CONSIDERATIONS:
 * - Uses password_verify() for constant-time comparison (prevents timing attacks)
 * - Only allows active users (status = 'active')
 * - Prepared statement prevents SQL injection
 * - Database errors don't expose system details (caught exceptions)
 * - LEFT JOIN allows authentication even if no employee record exists
 * 
 * @param string $username - Username to authenticate
 * @param string $password - Plain-text password (never stored plain)
 * @return boolean - true on success, false on failure/error
 */
function login($username, $password) {
    // STEP 1: Initialize database connection
    $db = new Database();
    $conn = $db->getConnection();
    
    // Check if database connection is available (gracefully handle DB down)
    if ($conn === null) {
        return false;  // Database unavailable, deny login
    }
    
    try {
        // STEP 2: Query user with employee join
        // LEFT JOIN: Find user's employee record if exists (not required)
        // WHERE status = 'active': Only allow non-suspended users
        $stmt = $conn->prepare("SELECT u.*, e.employee_id, e.first_name, e.last_name 
                                FROM users u 
                                LEFT JOIN employees e ON u.user_id = e.user_id 
                                WHERE u.username = ? AND u.status = 'active'");
        $stmt->execute([$username]);  // ? replaced with $username
        $user = $stmt->fetch();        // Get one row as associative array
        
        // STEP 3: Verify password using bcrypt comparison
        // password_verify() is constant-time: takes same time regardless of correct/wrong
        // This prevents attackers from guessing password based on response time
        // Return: false if user not found OR password doesn't match
        if ($user && password_verify($password, $user['password'])) {
            // PASSWORD CORRECT - Set session variables for authenticated user
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];  // admin, manager, or employee
            $_SESSION['employee_id'] = $user['employee_id'];  // May be null
            $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
            
            // STEP 4: Update last_login timestamp for audit trail
            // Shows when user was last authenticated (used for security analysis)
            $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
            $updateStmt->execute([$user['user_id']]);
            
            return true;  // Authentication successful
        }
        
        return false;  // Credentials don't match
    } catch(PDOException $e) {
        // SECURITY: Don't expose database errors to user
        // Instead of showing error, just deny authentication
        return false;
    }
}

// Logout function
function logout() {
    session_unset();
    session_destroy();
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}

// Get current user info
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'user_id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'email' => $_SESSION['email'],
        'role' => $_SESSION['role'],
        'employee_id' => $_SESSION['employee_id'] ?? null,
        'full_name' => $_SESSION['full_name'] ?? 'User'
    ];
}

// Sanitize input
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Generate CSRF token
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * ROLE-BASED ACCESS CONTROL (RBAC) SYSTEM
 * 
 * ARCHITECTURE:
 * This system uses a role-based permission matrix:
 * - Admin: Full system access (all operations)
 * - Employee: Restricted access (own records only)
 * 
 * PERMISSION CHECKING FLOW:
 * 1. Call hasPermission('permission_name')
 * 2. Function checks if user is logged in
 * 3. Gets user's role from session
 * 4. Looks up permission in role's permission array
 * 5. Returns true/false
 * 
 * ADVANTAGES:
 * - Centralized permission management
 * - Easy to audit access rights
 * - Simple to add new permissions
 * - Consistent across application
 */

// Get all permissions for a role
function getPermissions($role = null) {
    if ($role === null && isLoggedIn()) {
        $role = $_SESSION['role'] ?? 'employee';
    }
    
    // PERMISSION MATRIX: Define what each role can do
    // Format: 'action' => true/false
    // This is loaded once and compared against throughout the application
    $permissions = [
        'admin' => [
            // Dashboard & Core
            'view_dashboard' => true,
            'view_analytics' => true,  // Can see system-wide statistics
            
            // Employee Management
            'view_employees' => true,
            'create_employee' => true,
            'edit_employee' => true,
            'delete_employee' => true,
            'view_employee_details' => true,
            
            // Departments
            'view_departments' => true,
            'create_department' => true,
            'edit_department' => true,
            'delete_department' => true,
            
            // Attendance
            'view_attendance' => true,
            'manage_attendance' => true,        // Can mark for any employee
            'view_all_attendance' => true,      // Can see any employee's records
            
            // Leave Management
            'view_leaves' => true,
            'approve_leave' => true,            // Can approve/reject requests
            'reject_leave' => true,
            'view_all_leaves' => true,
            
            // Payroll
            'view_payroll' => true,
            'create_payroll' => true,           // Can generate payroll
            'edit_payroll' => true,
            'delete_payroll' => true,
            
            // Performance
            'view_performance' => true,
            'create_performance' => true,       // Can create reviews
            'edit_performance' => true,
            
            // User Management
            'view_users' => true,
            'create_user' => true,              // Can create accounts
            'edit_user' => true,
            'delete_user' => true,
            'manage_roles' => true,             // Can change user roles
            
            // System Administration
            'view_settings' => true,
            'edit_settings' => true,            // Can change company config
            'view_activity_log' => true,        // Complete audit trail access
            'view_audit_trail' => true,
            
            // Documents
            'view_documents' => true,
            'upload_document' => true,
            'delete_document' => true,          // Can delete any document
            'view_all_documents' => true,
            'delete_others_documents' => true,
            
            // Announcements
            'view_announcements' => true,
            'create_announcement' => true,      // Can post company-wide messages
            'edit_announcement' => true,
            'delete_announcement' => true,
            
            // Holiday Calendar
            'view_holidays' => true,
            'manage_holidays' => true,          // Can set company holidays
            
            // Notifications
            'view_notifications' => true,
            'manage_notifications' => true,
        ],
        
        'employee' => [
            // Dashboard & Core - Employees see limited dashboard
            'view_dashboard' => true,
            'view_analytics' => false,          // Can't see company-wide stats
            
            // Employee Management - Can't view other employees' details
            'view_employees' => true,           // Limited: directory only
            'create_employee' => false,
            'edit_employee' => false,
            'delete_employee' => false,
            'view_employee_details' => false,   // Can't view detailed profiles
            
            // Departments - Read-only access
            'view_departments' => true,
            'create_department' => false,
            'edit_department' => false,
            'delete_department' => false,
            
            // Attendance - Can only view own
            'view_attendance' => true,          // Own records only
            'manage_attendance' => false,       // Can't mark attendance
            'view_all_attendance' => false,
            
            // Leave Management - Submit but not approve
            'view_leaves' => true,              // Own requests only
            'approve_leave' => false,           // Can't approve for others
            'reject_leave' => false,
            'view_all_leaves' => false,
            
            // Payroll - View only, can't process
            'view_payroll' => false,            // Can't see salary details
            'create_payroll' => false,
            'edit_payroll' => false,
            'delete_payroll' => false,
            
            // Performance - View only
            'view_performance' => false,        // Can't see reviews
            'create_performance' => false,
            'edit_performance' => false,
            
            // User Management - No access
            'view_users' => false,
            'create_user' => false,
            'edit_user' => false,               // Can't create users
            'delete_user' => false,
            'manage_roles' => false,
            
            // System Administration - No access
            'view_settings' => false,
            'edit_settings' => false,           // Can't change config
            'view_activity_log' => false,       // Can't see audit logs
            'view_audit_trail' => false,
            
            // Documents - Personal only
            'view_documents' => true,
            'upload_document' => true,          // Can upload own documents
            'delete_document' => false,         // Can't delete
            'view_all_documents' => false,      // Own documents only
            'delete_others_documents' => false,
            
            // Announcements - View only
            'view_announcements' => true,
            'create_announcement' => false,     // Can't post messages
            'edit_announcement' => false,
            'delete_announcement' => false,
            
            // Holiday Calendar - Read-only
            'view_holidays' => true,            // View company holidays
            'manage_holidays' => false,         // Can't add holidays
            
            // Notifications - View only
            'view_notifications' => true,
            'manage_notifications' => false,
        ]
    ];
    
    return $permissions[$role] ?? $permissions['employee'];  // Default to most restrictive
}

// Check if user has a specific permission
function hasPermission($permission) {
    // STEP 1: Check if user is authenticated
    if (!isLoggedIn()) {
        return false;  // Non-authenticated users have no permissions
    }
    
    // STEP 2: Get user's role from session
    $role = $_SESSION['role'] ?? 'employee';  // Default to employee if not set
    
    // STEP 3: Load permission matrix for this role
    $permissions = getPermissions($role);
    
    // STEP 4: Check permission and return result
    // Using null coalescing: if permission not in array, return false (deny by default)
    return $permissions[$permission] ?? false;  // Default to DENY for security
}

// Require specific permission
function requirePermission($permission) {
    if (!hasPermission($permission)) {
        header('HTTP/1.0 403 Forbidden');
        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>Access Denied</title>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
                .deny-box { background: #f8d7da; color: #721c24; padding: 20px; border-radius: 8px; max-width: 500px; margin: 0 auto; }
                h1 { color: #721c24; }
                a { color: #FF6B35; text-decoration: none; margin-top: 20px; display: inline-block; }
            </style>
        </head>
        <body>
            <div class="deny-box">
                <h1>🚫 Access Denied</h1>
                <p>You do not have permission to access this resource.</p>
                <a href="' . BASE_URL . '/dashboard.php">→ Back to Dashboard</a>
            </div>
        </body>
        </html>';
        exit();
    }
}

// Get role display name
function getRoleDisplayName($role = null) {
    if ($role === null && isLoggedIn()) {
        $role = $_SESSION['role'] ?? 'employee';
    }
    
    return match(strtolower($role)) {
        'admin' => 'Administrator',
        'employee' => 'Employee',
        default => 'User'
    };
}

// List all available roles
function getAvailableRoles() {
    return [
        'admin' => 'Administrator (Full Access)',
        'employee' => 'Employee (Limited Access)'
    ];
}

?>

