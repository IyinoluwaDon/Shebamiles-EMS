<?php
// SIDEBAR NAVIGATION COMPONENT
// Purpose: Display main navigation menu for all pages
// This component is included in every authenticated page

// GET CURRENT PAGE NAME to highlight active menu item
// basename() extracts filename from full path (e.g., dashboard.php)
$currentPage = basename($_SERVER['PHP_SELF']);

// GET CURRENT USER INFO for display in sidebar footer
// Returns array with user_id, username, email, role, employee_id, full_name
$user = getCurrentUser();
?>
<aside class="sidebar">
    <!-- SIDEBAR HEADER: Logo/branding -->
    <div class="sidebar-header">
        <a href="<?php echo BASE_URL; ?>/dashboard.php" class="sidebar-brand">
            Shebamiles
            <small>EMS</small>
        </a>
    </div>
    
    <!-- MAIN NAVIGATION MENU -->
    <ul class="sidebar-menu">
        <!-- DASHBOARD LINK (All Users) -->
        <!-- Always visible - shows overview and statistics -->
        <li>
            <a href="<?php echo BASE_URL; ?>/dashboard.php" class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <!-- EMPLOYEES LINK (Permission: view_employees) -->
        <!-- Admins see all employees, Employees see directory only -->
        <?php if (hasPermission('view_employees')): ?>
        <li>
            <a href="<?php echo BASE_URL; ?>/hr/employees.php" class="<?php echo $currentPage === 'employees.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>Employees</span>
            </a>
        </li>
        <?php endif; ?>

        <!-- DEPARTMENTS LINK (Permission: view_departments) -->
        <!-- View company departments and structure -->
        <?php if (hasPermission('view_departments')): ?>
        <li>
            <a href="<?php echo BASE_URL; ?>/hr/departments.php" class="<?php echo $currentPage === 'departments.php' ? 'active' : ''; ?>">
                <i class="fas fa-building"></i>
                <span>Departments</span>
            </a>
        </li>
        <?php endif; ?>

        <!-- ATTENDANCE LINK (Permission: view_attendance) -->
        <!-- Mark/view daily attendance records -->
        <?php if (hasPermission('view_attendance')): ?>
        <li>
            <a href="<?php echo BASE_URL; ?>/attendance/attendance.php" class="<?php echo $currentPage === 'attendance.php' ? 'active' : ''; ?>">
                <i class="fas fa-clock"></i>
                <span>Attendance</span>
            </a>
        </li>
        <?php endif; ?>

        <!-- LEAVE REQUESTS LINK (Permission: view_leaves) -->
        <!-- Submit, view, and approve leave requests -->
        <?php if (hasPermission('view_leaves')): ?>
        <li>
            <a href="<?php echo BASE_URL; ?>/attendance/leaves.php" class="<?php echo $currentPage === 'leaves.php' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i>
                <span>Leave Requests</span>
            </a>
        </li>
        <?php endif; ?>

        <!-- DOCUMENTS LINK (Permission: view_documents) -->
        <!-- Upload and manage employee documents -->
        <?php if (hasPermission('view_documents')): ?>
        <li>
            <a href="<?php echo BASE_URL; ?>/hr/documents.php" class="<?php echo $currentPage === 'documents.php' ? 'active' : ''; ?>">
                <i class="fas fa-file"></i>
                <span>Documents</span>
            </a>
        </li>
        <?php endif; ?>

        <!-- ANNOUNCEMENTS LINK (All Users) -->
        <!-- View company-wide announcements and news -->
        <li>
            <a href="<?php echo BASE_URL; ?>/communication/announcements.php" class="<?php echo $currentPage === 'announcements.php' ? 'active' : ''; ?>">
                <i class="fas fa-bullhorn"></i>
                <span>Announcements</span>
            </a>
        </li>

        <!-- HOLIDAY CALENDAR LINK (Permission: view_holidays) -->
        <!-- View company holidays and non-working days -->
        <?php if (hasPermission('view_holidays')): ?>
        <li>
            <a href="<?php echo BASE_URL; ?>/attendance/holiday-calendar.php" class="<?php echo $currentPage === 'holiday-calendar.php' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-check"></i>
                <span>Holiday Calendar</span>
            </a>
        </li>
        <?php endif; ?>

        <!-- ADMIN-ONLY SECTION DIVIDER & HEADER -->
        <?php if (hasRole('admin')): ?>
        <!-- Visual separator between user and admin sections -->
        <li style="border-top: 1px solid rgba(255,255,255,0.1); margin: 10px 0; padding-top: 10px;">
            <span style="font-size: 0.75rem; color: rgba(255,255,255,0.6); text-transform: uppercase; font-weight: 700; padding: 0 1rem; display: block; margin-bottom: 0.5rem;">
                🔐 Admin Panel
            </span>
        </li>

        <!-- PAYROLL LINK (Admin Only - Permission: view_payroll) -->
        <!-- Generate and manage payroll records -->
        <?php if (hasPermission('view_payroll')): ?>
        <li>
            <a href="<?php echo BASE_URL; ?>/hr/payroll.php" class="<?php echo $currentPage === 'payroll.php' ? 'active' : ''; ?>">
                <i class="fas fa-money-bill-wave"></i>
                <span>Payroll</span>
            </a>
        </li>
        <?php endif; ?>

        <!-- PERFORMANCE LINK (Admin Only - Permission: view_performance) -->
        <!-- Create and manage employee performance reviews -->
        <?php if (hasPermission('view_performance')): ?>
        <li>
            <a href="<?php echo BASE_URL; ?>/hr/performance.php" class="<?php echo $currentPage === 'performance.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i>
                <span>Performance</span>
            </a>
        </li>
        <?php endif; ?>

        <!-- USER MANAGEMENT LINK (Admin Only - Permission: view_users) -->
        <!-- Create and manage user accounts -->
        <?php if (hasPermission('view_users')): ?>
        <li>
            <a href="<?php echo BASE_URL; ?>/admin/users.php" class="<?php echo $currentPage === 'users.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-shield"></i>
                <span>User Management</span>
            </a>
        </li>
        <?php endif; ?>

        <!-- SYSTEM SETTINGS LINK (Admin Only - Permission: view_settings) -->
        <!-- Configure company settings and preferences -->
        <?php if (hasPermission('view_settings')): ?>
        <li>
            <a href="<?php echo BASE_URL; ?>/admin/settings.php" class="<?php echo $currentPage === 'settings.php' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
        </li>
        <?php endif; ?>

        <!-- ACTIVITY LOG LINK (Admin Only - Permission: view_activity_log) -->
        <!-- View audit trail of all system actions -->
        <?php if (hasPermission('view_activity_log')): ?>
        <li>
            <a href="<?php echo BASE_URL; ?>/admin/activity-log.php" class="<?php echo $currentPage === 'activity-log.php' ? 'active' : ''; ?>">
                <i class="fas fa-history"></i>
                <span>Activity Log</span>
            </a>
        </li>
        <?php endif; ?>
        <?php endif; ?>
        <!-- END OF ADMIN SECTION -->
    </ul>
    
    <!-- SIDEBAR FOOTER: User profile card -->
    <div class="sidebar-user">
        <!-- USER AVATAR: First letter of user's full name -->
        <div class="sidebar-user-avatar">
            <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
        </div>
        
        <!-- USER INFO SECTION -->
        <div class="sidebar-user-info">
            <!-- User's full name -->
            <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
            <!-- User's role (Admin, Manager, Employee) -->
            <p><?php echo getRoleDisplayName($user['role']); ?></p>
            <!-- Link to user's profile page -->
            <a href="<?php echo BASE_URL; ?>/account/profile.php" style="font-size: 0.75rem; color: rgba(255,255,255,0.8); text-decoration: none; margin-top: 0.25rem; display: inline-block;">
                <i class="fas fa-user-circle"></i> View Profile
            </a>
        </div>
    </div>
</aside>
