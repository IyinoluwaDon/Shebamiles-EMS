<?php
// USER MANAGEMENT PAGE
// PURPOSE: Manage system user accounts and access control
// PERMISSIONS: Admin only (requires 'view_users' permission)
// FEATURES: Create/edit/delete users, reset passwords, manage user roles/status
// WORKFLOW: List users → Filter/search → Add/Edit/Delete → Manage permissions

// STEP 1: Include auth and require login with admin permission
require_once __DIR__ . '/../includes/auth.php';
requireLogin();  // Redirect if not authenticated
requirePermission('view_users');  // Redirect if not admin

// STEP 2: Initialize database connection
$db = new Database();
$conn = $db->getConnection();

// STEP 3: Check database connection
if ($conn === null) {
    $error = 'Database connection unavailable. Please import database/shebamiles_db.sql.';
}

$success = '';
$error = '';
// STEP 4: HANDLE FORM SUBMISSIONS (Add/Edit/Reset/Delete)
// Process POST requests for user account management
if ($conn !== null && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // ACTION: Create new user account
    if ($_POST['action'] === 'add_user') {
        try {
            // Sanitize inputs to prevent XSS
            $username = sanitize($_POST['username']);
            $email = sanitize($_POST['email']);
            // Hash password with bcrypt for security
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $role = sanitize($_POST['role']);  // admin, manager, or employee
            $status = $_POST['status'];  // active or inactive
            
            // Insert new user account
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$username, $email, $password, $role, $status]);
            
            $success = "User added successfully!";
        } catch(PDOException $e) {
            $error = "Error adding user: " . $e->getMessage();
        }
    }
    
    // ACTION: Update existing user account
    if ($_POST['action'] === 'update_user') {
        try {
            // Update user: email, role, and status (not password)
            $stmt = $conn->prepare("UPDATE users SET email = ?, role = ?, status = ? WHERE user_id = ?");
            $stmt->execute([
                sanitize($_POST['email']),
                $_POST['role'],
                $_POST['status'],
                $_POST['user_id']
            ]);
            
            $success = "User updated successfully!";
        } catch(PDOException $e) {
            $error = "Error updating user: " . $e->getMessage();
        }
    }
    
    // ACTION: Reset user password (admin-initiated)
    if ($_POST['action'] === 'reset_password') {
        try {
            // Hash the new password with bcrypt
            $newPassword = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->execute([$newPassword, $_POST['user_id']]);
            
            $success = "Password reset successfully!";
        } catch(PDOException $e) {
            $error = "Error resetting password: " . $e->getMessage();
        }
    }
}

// STEP 5: HANDLE DELETE REQUEST
// Delete user account from system
if ($conn !== null && isset($_GET['delete'])) {
    try {
        $userId = $_GET['delete'];
        
        // SECURITY: Prevent admin from deleting their own account
        // This keeps at least one admin in the system
        if ($userId == getCurrentUser()['user_id']) {
            $error = "You cannot delete your own account!";
        } else {
            // Delete user from users table (cascades to employees if foreign key set)
            $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $success = "User deleted successfully!";
        }
    } catch(PDOException $e) {
        $error = "Error deleting user: " . $e->getMessage();
    }
}

// STEP 6: FETCH ALL USERS WITH SEARCH/FILTER
// Display all users with optional filtering by role, status, and search
try {
    if ($conn === null) {
        throw new Exception('Database connection unavailable. Please import database/shebamiles_db.sql.');
    }
    
    // Get search, role filter, and status filter parameters
    $search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
    $roleFilter = isset($_GET['role']) ? $_GET['role'] : '';
    $statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
    
    // Build query to fetch users with linked employee data
    // JOINs get employee details (code, name) if user has employee record
    $query = "SELECT u.*, e.employee_code, e.first_name, e.last_name 
              FROM users u 
              LEFT JOIN employees e ON u.user_id = e.user_id 
              WHERE 1=1";
    
    $params = [];
    
    // Add search filter (search username, email, first name, last name)
    if ($search) {
        $query .= " AND (u.username LIKE ? OR u.email LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ?)";
        $searchParam = "%$search%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
    }
    
    // Add role filter (admin, manager, employee)
    if ($roleFilter) {
        $query .= " AND u.role = ?";
        $params[] = $roleFilter;
    }
    
    // Add status filter (active, inactive)
    if ($statusFilter) {
        $query .= " AND u.status = ?";
        $params[] = $statusFilter;
    }
    
    // Sort by creation date (newest first)
    $query .= " ORDER BY u.created_at DESC";
    
    // Execute query with prepared statement
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
    // Calculate user statistics
    $totalUsers = count($users);
    $activeUsers = count(array_filter($users, fn($u) => $u['status'] === 'active'));
    $admins = count(array_filter($users, fn($u) => $u['role'] === 'admin'));
    $managers = count(array_filter($users, fn($u) => $u['role'] === 'manager'));
    
} catch(PDOException $e) {
    $error = "Error fetching users: " . $e->getMessage();
} catch(Exception $e) {
    $error = $e->getMessage();
}

$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Shebamiles EMS</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="topbar">
                <h2>User Management</h2>
                <div class="topbar-actions">
                    <button onclick="showAddModal()" class="btn btn-sm btn-primary">
                        <i class="fas fa-user-plus"></i> Add User
                    </button>
                    <a href="../account/logout.php" class="btn btn-sm btn-outline-orange">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
            
            <div class="content">
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-card-icon" style="background: linear-gradient(135deg, #2196F3, #1565C0);">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3>Total Users</h3>
                        <div class="stat-card-value"><?php echo $totalUsers; ?></div>
                        <p class="stat-card-label">All accounts</p>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-card-icon" style="background: linear-gradient(135deg, #4CAF50, #388E3C);">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3>Active Users</h3>
                        <div class="stat-card-value"><?php echo $activeUsers; ?></div>
                        <p class="stat-card-label">Currently active</p>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-card-icon" style="background: linear-gradient(135deg, #9C27B0, #6A1B9A);">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <h3>Administrators</h3>
                        <div class="stat-card-value"><?php echo $admins; ?></div>
                        <p class="stat-card-label">Admin accounts</p>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-card-icon" style="background: linear-gradient(135deg, #FFC107, #F57C00);">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <h3>Managers</h3>
                        <div class="stat-card-value"><?php echo $managers; ?></div>
                        <p class="stat-card-label">Manager accounts</p>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="card">
                    <div class="card-body">
                        <form method="GET" class="filter-form">
                            <div class="form-row">
                                <div class="form-group">
                                    <input type="text" name="search" class="form-control" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="form-group">
                                    <select name="role" class="form-control">
                                        <option value="">All Roles</option>
                                        <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                        <option value="manager" <?php echo $roleFilter === 'manager' ? 'selected' : ''; ?>>Manager</option>
                                        <option value="employee" <?php echo $roleFilter === 'employee' ? 'selected' : ''; ?>>Employee</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <select name="status" class="form-control">
                                        <option value="">All Status</option>
                                        <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                                <a href="users.php" class="btn btn-outline-orange">
                                    <i class="fas fa-redo"></i> Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Users Table -->
                <div class="card">
                    <div class="card-header">
                        <h3>User Accounts</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Full Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Last Login</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($users)): ?>
                                        <tr>
                                            <td colspan="8" style="text-align: center; padding: 2rem;">
                                                <i class="fas fa-users" style="font-size: 3rem; color: var(--medium-gray); margin-bottom: 1rem;"></i>
                                                <p>No users found</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                                    <?php if ($user['user_id'] == $currentUser['user_id']): ?>
                                                        <span class="badge badge-info" style="margin-left: 0.5rem;">You</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    if ($user['first_name'] && $user['last_name']) {
                                                        echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
                                                        echo '<br><small>' . htmlspecialchars($user['employee_code'] ?? '') . '</small>';
                                                    } else {
                                                        echo '<span style="color: var(--medium-gray);">No employee profile</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td>
                                                    <?php 
                                                    $roleColors = [
                                                        'admin' => 'badge-danger',
                                                        'manager' => 'badge-warning',
                                                        'employee' => 'badge-info'
                                                    ];
                                                    $roleClass = $roleColors[$user['role']] ?? 'badge-secondary';
                                                    ?>
                                                    <span class="badge <?php echo $roleClass; ?>">
                                                        <?php echo ucfirst($user['role']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($user['status'] === 'active'): ?>
                                                        <span class="badge badge-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-secondary">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    if ($user['last_login']) {
                                                        echo date('M d, Y', strtotime($user['last_login']));
                                                        echo '<br><small>' . date('h:i A', strtotime($user['last_login'])) . '</small>';
                                                    } else {
                                                        echo '<span style="color: var(--medium-gray);">Never</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                                <td class="actions">
                                                    <button onclick="showEditModal(<?php echo htmlspecialchars(json_encode($user)); ?>)" 
                                                            class="btn-icon btn-warning" 
                                                            title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button onclick="showResetPasswordModal(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" 
                                                            class="btn-icon btn-info" 
                                                            title="Reset Password">
                                                        <i class="fas fa-key"></i>
                                                    </button>
                                                    <?php if ($user['user_id'] != $currentUser['user_id']): ?>
                                                        <a href="?delete=<?php echo $user['user_id']; ?>" 
                                                           onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')" 
                                                           class="btn-icon btn-danger" 
                                                           title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Add User Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New User</h3>
                <button class="modal-close" type="button" onclick="closeModal('addModal')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_user">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Username <span class="required">*</span></label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Email <span class="required">*</span></label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Password <span class="required">*</span></label>
                        <input type="password" name="password" class="form-control" required minlength="6">
                        <small>Minimum 6 characters</small>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Role <span class="required">*</span></label>
                            <select name="role" class="form-control" required>
                                <option value="employee">Employee</option>
                                <option value="manager">Manager</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Status <span class="required">*</span></label>
                            <select name="status" class="form-control" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> After creating this user account, you can link it to an employee profile in the Employees section.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-orange" onclick="closeModal('addModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit User</h3>
                <button class="modal-close" type="button" onclick="closeModal('editModal')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_user">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" id="edit_username" class="form-control" readonly style="background: #f5f5f5;">
                        <small>Username cannot be changed</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Email <span class="required">*</span></label>
                        <input type="email" name="email" id="edit_email" class="form-control" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Role <span class="required">*</span></label>
                            <select name="role" id="edit_role" class="form-control" required>
                                <option value="employee">Employee</option>
                                <option value="manager">Manager</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Status <span class="required">*</span></label>
                            <select name="status" id="edit_status" class="form-control" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-orange" onclick="closeModal('editModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Reset Password Modal -->
    <div id="resetPasswordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Reset Password</h3>
                <button class="modal-close" type="button" onclick="closeModal('resetPasswordModal')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="reset_user_id">
                <div class="modal-body">
                    <p>Reset password for: <strong id="reset_username_display"></strong></p>
                    
                    <div class="form-group">
                        <label>New Password <span class="required">*</span></label>
                        <input type="password" name="new_password" class="form-control" required minlength="6">
                        <small>Minimum 6 characters</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Confirm Password <span class="required">*</span></label>
                        <input type="password" id="confirm_password" class="form-control" required minlength="6">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-orange" onclick="closeModal('resetPasswordModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" onclick="return validatePassword()">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function showAddModal() {
            document.getElementById('addModal').style.display = 'block';
        }
        
        function showEditModal(user) {
            document.getElementById('edit_user_id').value = user.user_id;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_role').value = user.role;
            document.getElementById('edit_status').value = user.status;
            document.getElementById('editModal').style.display = 'block';
        }
        
        function showResetPasswordModal(userId, username) {
            document.getElementById('reset_user_id').value = userId;
            document.getElementById('reset_username_display').textContent = username;
            document.getElementById('resetPasswordModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function validatePassword() {
            const newPassword = document.querySelector('[name="new_password"]').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                alert('Passwords do not match!');
                return false;
            }
            return true;
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }

        window.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.style.display = 'none';
            }
        });
    </script>
    
    <?php include __DIR__ . '/../includes/badge.php'; ?>
</body>
</html>
