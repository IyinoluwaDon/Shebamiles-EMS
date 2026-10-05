<?php
// USER PROFILE PAGE
// PURPOSE: Display and edit user profile information and personal settings
// PERMISSIONS: For logged-in users only
// FEATURES: View personal details, edit profile, change password, view leave balance
// WORKFLOW: Display profile → Edit personal details → Change password

// STEP 1: Include auth and helpers, require login
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
requireLogin();  // Redirect if not authenticated

// STEP 2: Initialize database connection
$db = new Database();
$conn = $db->getConnection();

// STEP 3: Check database connection
if ($conn === null) {
    header('Location: ../login.php?db=missing');
    exit();
}

$success = '';
$error = '';
$user = getCurrentUser();  // Get current logged-in user info

// STEP 4: FETCH USER PROFILE DATA
// Retrieve employee details and user account information
try {
    // Fetch employee details with department info
    $stmt = $conn->prepare("SELECT e.*, d.department_name 
                           FROM employees e 
                           LEFT JOIN departments d ON e.department_id = d.department_id 
                           WHERE e.user_id = ?");
    $stmt->execute([$user['user_id']]);
    $employee = $stmt->fetch();  // Contains employee personal/work data
    
    // Fetch user account info
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$user['user_id']]);
    $userInfo = $stmt->fetch();  // Contains username, email, role
    
} catch(PDOException $e) {
    $error = "Error fetching profile: " . $e->getMessage();
}

// STEP 5: HANDLE FORM SUBMISSIONS (Update Profile / Change Password)
// Process POST requests for profile and password updates
if ($conn !== null && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // ACTION: Update personal profile information
    // User can update phone, address, city, state, country
    if ($_POST['action'] === 'update_profile') {
        try {
            // Update employee contact and location details
            $stmt = $conn->prepare("UPDATE employees SET 
                                   phone = ?, address = ?, city = ?, state = ?, country = ?
                                   WHERE user_id = ?");
            $stmt->execute([
                sanitize($_POST['phone']),
                sanitize($_POST['address']),
                sanitize($_POST['city']),
                sanitize($_POST['state']),
                sanitize($_POST['country']),
                $user['user_id']
            ]);
            
            // Log this activity for audit trail
            logActivity($user['user_id'], 'update_profile', 'employees', $employee['employee_id'], 'Updated personal information');
            $success = "Profile updated successfully!";
            
            // Refresh employee data from database
            $stmt = $conn->prepare("SELECT e.*, d.department_name 
                                   FROM employees e 
                                   LEFT JOIN departments d ON e.department_id = d.department_id 
                                   WHERE e.user_id = ?");
            $stmt->execute([$user['user_id']]);
            $employee = $stmt->fetch();
        } catch(PDOException $e) {
            $error = "Error updating profile: " . $e->getMessage();
        }
    }
    
    // ACTION: Change user password
    // Validates current password and requires new password confirmation
    if ($_POST['action'] === 'change_password') {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        // STEP 5a: Validate new passwords match
        if ($newPassword !== $confirmPassword) {
            $error = "New passwords do not match!";
        }
        // STEP 5b: Validate minimum password length
        elseif (strlen($newPassword) < 6) {
            $error = "Password must be at least 6 characters!";
        } else {
            try {
                // STEP 5c: Fetch current password hash from database
                $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
                $stmt->execute([$user['user_id']]);
                $currentHash = $stmt->fetchColumn();
                
                // STEP 5d: Verify current password matches hash using bcrypt
                if (password_verify($currentPassword, $currentHash)) {
                    // STEP 5e: Hash new password with bcrypt and update database
                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                    $stmt->execute([$newHash, $user['user_id']]);
                    
                    // Log password change for security audit
                    logActivity($user['user_id'], 'change_password', 'users', $user['user_id'], 'Changed password');
                    $success = "Password changed successfully!";
                } else {
                    $error = "Current password is incorrect!";
                }
            } catch(PDOException $e) {
                $error = "Error changing password: " . $e->getMessage();
            }
        }
    }
}

// Get leave balance
$leaveBalance = [];
if ($employee) {
    $leaveBalance = getLeaveBalance($employee['employee_id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Shebamiles EMS</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="topbar">
                <h2>My Profile</h2>
                <div class="topbar-actions">
                    <a href="../dashboard.php" class="btn btn-sm btn-outline-orange">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <a href="logout.php" class="btn btn-sm btn-outline-orange">
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
                
                <!-- Profile Header -->
                <div class="card" style="margin-bottom: 2rem;">
                    <div class="card-body">
                        <div style="display: flex; gap: 2rem; align-items: center;">
                            <div style="width: 120px; height: 120px; border-radius: 50%; background: linear-gradient(135deg, var(--primary-orange), var(--primary-orange-dark)); display: flex; align-items: center; justify-content: center; color: white; font-size: 3rem; font-weight: 700;">
                                <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                            </div>
                            <div style="flex: 1;">
                                <h2 style="margin-bottom: 0.5rem;"><?php echo htmlspecialchars($user['full_name']); ?></h2>
                                <p style="color: var(--medium-gray); margin-bottom: 0.5rem;">
                                    <?php echo htmlspecialchars($employee['position'] ?? 'N/A'); ?> • 
                                    <?php echo htmlspecialchars($employee['department_name'] ?? 'No Department'); ?>
                                </p>
                                <p style="color: var(--medium-gray); margin-bottom: 1rem;">
                                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($userInfo['email']); ?>
                                </p>
                                <div style="display: flex; gap: 1rem;">
                                    <span class="badge badge-primary"><?php echo ucfirst($employee['employment_type']); ?></span>
                                    <span class="badge badge-info"><?php echo ucfirst($user['role']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 2rem;">
                    <!-- Personal Information -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-user"></i> Personal Information</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_profile">
                                
                                <div class="form-group">
                                    <label>Employee Code</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($employee['employee_code']); ?>" readonly>
                                </div>
                                
                                <div class="form-group">
                                    <label>Phone</label>
                                    <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($employee['phone'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label>Address</label>
                                    <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($employee['address'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label>City</label>
                                    <input type="text" name="city" class="form-control" value="<?php echo htmlspecialchars($employee['city'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label>State</label>
                                    <input type="text" name="state" class="form-control" value="<?php echo htmlspecialchars($employee['state'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label>Country</label>
                                    <input type="text" name="country" class="form-control" value="<?php echo htmlspecialchars($employee['country'] ?? ''); ?>">
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-save"></i> Update Profile
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Change Password -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-lock"></i> Change Password</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="change_password">
                                
                                <div class="form-group">
                                    <label>Current Password</label>
                                    <input type="password" name="current_password" class="form-control" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>New Password</label>
                                    <input type="password" name="new_password" class="form-control" required minlength="6">
                                    <small>Minimum 6 characters</small>
                                </div>
                                
                                <div class="form-group">
                                    <label>Confirm New Password</label>
                                    <input type="password" name="confirm_password" class="form-control" required minlength="6">
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-key"></i> Change Password
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Leave Balance -->
                <?php if (!empty($leaveBalance)): ?>
                <div class="card" style="margin-top: 2rem;">
                    <div class="card-header">
                        <h3><i class="fas fa-calendar-check"></i> Leave Balance (<?php echo date('Y'); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
                            <?php foreach ($leaveBalance as $balance): ?>
                                <div style="background: var(--light-gray); padding: 1.5rem; border-radius: 12px; text-align: center;">
                                    <div style="font-size: 0.9rem; color: var(--medium-gray); text-transform: uppercase; margin-bottom: 0.5rem;">
                                        <?php echo ucfirst($balance['leave_type']); ?> Leave
                                    </div>
                                    <div style="font-size: 2rem; font-weight: 700; color: var(--primary-orange); margin-bottom: 0.5rem;">
                                        <?php echo $balance['remaining_days']; ?>
                                    </div>
                                    <div style="font-size: 0.85rem; color: var(--medium-gray);">
                                        <?php echo $balance['used_days']; ?> used / <?php echo $balance['total_days']; ?> total
                                    </div>
                                    <div style="margin-top: 1rem; background: white; height: 8px; border-radius: 4px; overflow: hidden;">
                                        <div style="height: 100%; background: var(--primary-orange); width: <?php echo ($balance['used_days'] / $balance['total_days']) * 100; ?>%;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Employment Details -->
                <div class="card" style="margin-top: 2rem;">
                    <div class="card-header">
                        <h3><i class="fas fa-briefcase"></i> Employment Details</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                            <div>
                                <strong style="display: block; color: var(--dark-gray); margin-bottom: 0.5rem;">Department</strong>
                                <span><?php echo htmlspecialchars($employee['department_name'] ?? 'N/A'); ?></span>
                            </div>
                            <div>
                                <strong style="display: block; color: var(--dark-gray); margin-bottom: 0.5rem;">Position</strong>
                                <span><?php echo htmlspecialchars($employee['position'] ?? 'N/A'); ?></span>
                            </div>
                            <div>
                                <strong style="display: block; color: var(--dark-gray); margin-bottom: 0.5rem;">Hire Date</strong>
                                <span><?php echo date('M d, Y', strtotime($employee['hire_date'])); ?></span>
                            </div>
                            <div>
                                <strong style="display: block; color: var(--dark-gray); margin-bottom: 0.5rem;">Employment Type</strong>
                                <span><?php echo ucfirst(str_replace('-', ' ', $employee['employment_type'])); ?></span>
                            </div>
                            <div>
                                <strong style="display: block; color: var(--dark-gray); margin-bottom: 0.5rem;">Gender</strong>
                                <span><?php echo ucfirst($employee['gender'] ?? 'N/A'); ?></span>
                            </div>
                            <div>
                                <strong style="display: block; color: var(--dark-gray); margin-bottom: 0.5rem;">Date of Birth</strong>
                                <span><?php echo $employee['date_of_birth'] ? date('M d, Y', strtotime($employee['date_of_birth'])) : 'N/A'; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <?php include __DIR__ . '/../includes/badge.php'; ?>
</body>
</html>
