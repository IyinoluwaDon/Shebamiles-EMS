<?php
// SYSTEM SETTINGS PAGE
// PURPOSE: Manage company-wide configuration and system settings
// PERMISSIONS: Admin only (requires 'view_settings' permission)
// SETTINGS: Company info, work hours, leave policies, email configuration
// WORKFLOW: Display settings → Update settings by section → Save to database

// STEP 1: Start session and include config/auth/helpers
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

// STEP 2: Require login and admin permission
requireLogin();  // Redirect if not authenticated
requirePermission('view_settings');  // Redirect if not admin

$success_msg = '';
$error_msg = '';

// STEP 3: Check database connection
if (!(new Database())->getConnection()) {
    header('Location: ../dashboard.php?db=missing');
    exit();
}

// STEP 4: HANDLE SETTINGS UPDATE (POST Request)
// Process form submissions from different settings sections
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = new Database();
        
        // STEP 4a: Route to appropriate settings handler based on setting_action parameter
        // Each case updates multiple related settings in one transaction
        switch ($_POST['setting_action'] ?? '') {
            case 'company_info':
                // Update company identity and contact information
                // These settings appear in emails, invoices, and system headers
                updateSetting('company_name', $_POST['company_name'] ?? '', $_SESSION['user_id']);
                updateSetting('company_email', $_POST['company_email'] ?? '', $_SESSION['user_id']);
                updateSetting('company_phone', $_POST['company_phone'] ?? '', $_SESSION['user_id']);
                updateSetting('company_address', $_POST['company_address'] ?? '', $_SESSION['user_id']);
                updateSetting('company_city', $_POST['company_city'] ?? '', $_SESSION['user_id']);
                updateSetting('company_state', $_POST['company_state'] ?? '', $_SESSION['user_id']);
                updateSetting('company_country', $_POST['company_country'] ?? '', $_SESSION['user_id']);
                $success_msg = 'Company information updated successfully!';
                break;
                
            case 'work_hours':
                // Update working hour settings used for attendance tracking
                // Late threshold determines if employee is marked 'late' vs 'present'
                updateSetting('work_start_time', $_POST['work_start_time'] ?? '', $_SESSION['user_id']);
                updateSetting('work_end_time', $_POST['work_end_time'] ?? '', $_SESSION['user_id']);
                updateSetting('late_arrival_threshold', $_POST['late_threshold'] ?? '', $_SESSION['user_id']);
                $success_msg = 'Work hours updated successfully!';
                break;
                
            case 'leave_policies':
                // Update annual leave allowances for different leave types
                // These values determine how many days employees can take per year
                updateSetting('annual_leave_days', $_POST['annual_leave'] ?? '', $_SESSION['user_id']);
                updateSetting('sick_leave_days', $_POST['sick_leave'] ?? '', $_SESSION['user_id']);
                updateSetting('personal_leave_days', $_POST['personal_leave'] ?? '', $_SESSION['user_id']);
                $success_msg = 'Leave policies updated successfully!';
                break;
                
            case 'email_settings':
                // Update SMTP configuration for automated email notifications
                // Used by system to send leave approvals, alerts, reminders, etc.
                updateSetting('smtp_host', $_POST['smtp_host'] ?? '', $_SESSION['user_id']);
                updateSetting('smtp_port', $_POST['smtp_port'] ?? '', $_SESSION['user_id']);
                updateSetting('smtp_username', $_POST['smtp_username'] ?? '', $_SESSION['user_id']);
                updateSetting('smtp_password', $_POST['smtp_password'] ?? '', $_SESSION['user_id']);
                updateSetting('email_from_address', $_POST['email_from'] ?? '', $_SESSION['user_id']);
                $success_msg = 'Email settings updated successfully!';
                break;
        }
        
        // Log the settings update for audit trail
        logActivity($_SESSION['user_id'], 'UPDATE_SETTING', 'settings', $_POST['setting_action'] ?? 'unknown', 'Updated system settings');
    } catch (Exception $e) {
        $error_msg = 'Error updating settings: ' . $e->getMessage();
    }
}

// STEP 5: FETCH ALL SETTINGS FROM DATABASE
// Load current settings values into array for display in forms
$settings = [];
try {
    $db = new Database();
    // Query company_settings table to get all key-value pairs
    $query = "SELECT setting_key, setting_value FROM company_settings";
    $stmt = $db->conn->prepare($query);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert to associative array for easy access in form: $settings['key'] = 'value'
    foreach ($result as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    $error_msg = 'Failed to load settings';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Shebamiles EMS</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .settings-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 30px;
            margin-top: 30px;
        }

        .settings-menu {
            background: white;
            border-radius: 8px;
            padding: 20px;
            height: fit-content;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 20px;
        }

        .settings-menu-item {
            padding: 12px 16px;
            margin-bottom: 8px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            font-size: 14px;
            font-weight: 500;
            color: #666;
        }

        .settings-menu-item:hover {
            background-color: #f5f5f5;
        }

        .settings-menu-item.active {
            background-color: #FF6B35;
            color: white;
            border-color: #FF6B35;
        }

        .settings-content {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .settings-section {
            display: none;
        }

        .settings-section.active {
            display: block;
        }

        .settings-section h2 {
            color: #FF6B35;
            margin-bottom: 20px;
            font-size: 22px;
        }

        .settings-form {
            max-width: 600px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
            font-size: 14px;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-family: inherit;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #FF6B35;
            box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-group-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .form-group-row .form-group {
            margin-bottom: 0;
        }

        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }

        .btn {
            padding: 10px 24px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .btn-primary {
            background-color: #FF6B35;
            color: white;
        }

        .btn-primary:hover {
            background-color: #e55a25;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 107, 53, 0.3);
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

        .info-box {
            background-color: #f0f8ff;
            border-left: 4px solid #FF6B35;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-size: 13px;
            color: #333;
        }

        @media (max-width: 992px) {
            .settings-container {
                grid-template-columns: 1fr;
            }

            .settings-menu {
                position: static;
                display: flex;
                gap: 10px;
                overflow-x: auto;
                margin-bottom: 20px;
            }

            .settings-menu-item {
                white-space: nowrap;
                flex-shrink: 0;
            }
        }

        @media (max-width: 768px) {
            .form-group-row {
                grid-template-columns: 1fr;
            }

            .settings-content {
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
            <h1>⚙️ Settings & Configuration</h1>
            <p>Manage system settings and company information</p>
        </div>

        <?php if ($success_msg): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_msg); ?></div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <div class="settings-container">
            <!-- Settings Menu -->
            <div class="settings-menu">
                <div class="settings-menu-item active" onclick="switchTab('company')">
                    🏢 Company Info
                </div>
                <div class="settings-menu-item" onclick="switchTab('hours')">
                    ⏰ Work Hours
                </div>
                <div class="settings-menu-item" onclick="switchTab('leave')">
                    📅 Leave Policies
                </div>
                <div class="settings-menu-item" onclick="switchTab('email')">
                    📧 Email Settings
                </div>
            </div>

            <!-- Settings Content -->
            <div class="settings-content">
                <!-- Company Information Section -->
                <div class="settings-section active" id="company">
                    <h2>Company Information</h2>
                    <div class="info-box">
                        Update your company's basic information that appears across the system.
                    </div>
                    <form method="POST" class="settings-form">
                        <input type="hidden" name="setting_action" value="company_info">
                        
                        <div class="form-group">
                            <label for="company_name">Company Name</label>
                            <input type="text" id="company_name" name="company_name" 
                                   value="<?php echo htmlspecialchars($settings['company_name'] ?? 'Shebamiles'); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="company_email">Company Email</label>
                            <input type="email" id="company_email" name="company_email" 
                                   value="<?php echo htmlspecialchars($settings['company_email'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="company_phone">Company Phone</label>
                            <input type="tel" id="company_phone" name="company_phone" 
                                   value="<?php echo htmlspecialchars($settings['company_phone'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="company_address">Address</label>
                            <input type="text" id="company_address" name="company_address" 
                                   value="<?php echo htmlspecialchars($settings['company_address'] ?? ''); ?>">
                        </div>

                        <div class="form-group-row">
                            <div class="form-group">
                                <label for="company_city">City</label>
                                <input type="text" id="company_city" name="company_city" 
                                       value="<?php echo htmlspecialchars($settings['company_city'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="company_state">State/Province</label>
                                <input type="text" id="company_state" name="company_state" 
                                       value="<?php echo htmlspecialchars($settings['company_state'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="company_country">Country</label>
                            <input type="text" id="company_country" name="company_country" 
                                   value="<?php echo htmlspecialchars($settings['company_country'] ?? ''); ?>">
                        </div>

                        <div class="button-group">
                            <button type="submit" class="btn btn-primary">💾 Save Changes</button>
                        </div>
                    </form>
                </div>

                <!-- Work Hours Section -->
                <div class="settings-section" id="hours">
                    <h2>Work Hours Configuration</h2>
                    <div class="info-box">
                        Set standard work hours and late arrival thresholds for your organization.
                    </div>
                    <form method="POST" class="settings-form">
                        <input type="hidden" name="setting_action" value="work_hours">
                        
                        <div class="form-group-row">
                            <div class="form-group">
                                <label for="work_start_time">Work Start Time</label>
                                <input type="time" id="work_start_time" name="work_start_time" 
                                       value="<?php echo htmlspecialchars($settings['work_start_time'] ?? '09:00'); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="work_end_time">Work End Time</label>
                                <input type="time" id="work_end_time" name="work_end_time" 
                                       value="<?php echo htmlspecialchars($settings['work_end_time'] ?? '17:00'); ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="late_threshold">Late Arrival Threshold (minutes)</label>
                            <input type="number" id="late_threshold" name="late_threshold" min="1" max="120"
                                   value="<?php echo htmlspecialchars($settings['late_arrival_threshold'] ?? '15'); ?>" required>
                        </div>

                        <div class="button-group">
                            <button type="submit" class="btn btn-primary">💾 Save Changes</button>
                        </div>
                    </form>
                </div>

                <!-- Leave Policies Section -->
                <div class="settings-section" id="leave">
                    <h2>Leave Policies</h2>
                    <div class="info-box">
                        Define the number of leave days available for each leave type per year.
                    </div>
                    <form method="POST" class="settings-form">
                        <input type="hidden" name="setting_action" value="leave_policies">
                        
                        <div class="form-group">
                            <label for="annual_leave">Annual/Vacation Leave Days</label>
                            <input type="number" id="annual_leave" name="annual_leave" min="1" max="365"
                                   value="<?php echo htmlspecialchars($settings['annual_leave_days'] ?? '20'); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="sick_leave">Sick Leave Days</label>
                            <input type="number" id="sick_leave" name="sick_leave" min="1" max="365"
                                   value="<?php echo htmlspecialchars($settings['sick_leave_days'] ?? '10'); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="personal_leave">Personal Leave Days</label>
                            <input type="number" id="personal_leave" name="personal_leave" min="1" max="365"
                                   value="<?php echo htmlspecialchars($settings['personal_leave_days'] ?? '5'); ?>" required>
                        </div>

                        <div class="button-group">
                            <button type="submit" class="btn btn-primary">💾 Save Changes</button>
                        </div>
                    </form>
                </div>

                <!-- Email Settings Section -->
                <div class="settings-section" id="email">
                    <h2>Email Configuration</h2>
                    <div class="info-box">
                        Configure SMTP settings for sending automated emails. Leave empty to disable email notifications.
                    </div>
                    <form method="POST" class="settings-form">
                        <input type="hidden" name="setting_action" value="email_settings">
                        
                        <div class="form-group">
                            <label for="smtp_host">SMTP Host</label>
                            <input type="text" id="smtp_host" name="smtp_host" 
                                   placeholder="e.g., smtp.gmail.com"
                                   value="<?php echo htmlspecialchars($settings['smtp_host'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="smtp_port">SMTP Port</label>
                            <input type="number" id="smtp_port" name="smtp_port" min="1" max="65535"
                                   placeholder="e.g., 587"
                                   value="<?php echo htmlspecialchars($settings['smtp_port'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="smtp_username">SMTP Username</label>
                            <input type="text" id="smtp_username" name="smtp_username" 
                                   placeholder="e.g., your-email@gmail.com"
                                   value="<?php echo htmlspecialchars($settings['smtp_username'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="smtp_password">SMTP Password</label>
                            <input type="password" id="smtp_password" name="smtp_password" 
                                   placeholder="Leave empty to keep current password"
                                   value="">
                        </div>

                        <div class="form-group">
                            <label for="email_from">From Email Address</label>
                            <input type="email" id="email_from" name="email_from" 
                                   placeholder="e.g., noreply@company.com"
                                   value="<?php echo htmlspecialchars($settings['email_from_address'] ?? ''); ?>">
                        </div>

                        <div class="button-group">
                            <button type="submit" class="btn btn-primary">💾 Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            // Hide all sections
            document.querySelectorAll('.settings-section').forEach(section => {
                section.classList.remove('active');
            });

            // Remove active class from all menu items
            document.querySelectorAll('.settings-menu-item').forEach(item => {
                item.classList.remove('active');
            });

            // Show selected section
            document.getElementById(tabName).classList.add('active');

            // Mark menu item as active
            event.target.classList.add('active');
        }
    </script>
</body>
</html>
