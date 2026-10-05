<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$db = new Database();
$conn = $db->getConnection();

if ($conn === null) {
    header('Location: employees.php?db=missing');
    exit();
}

$employeeId = $_GET['id'] ?? null;

if (!$employeeId) {
    header('Location: employees.php');
    exit();
}

// Get employee details
try {
    $stmt = $conn->prepare("SELECT e.*, d.department_name, u.email, u.username, u.role
                           FROM employees e
                           LEFT JOIN departments d ON e.department_id = d.department_id
                           LEFT JOIN users u ON e.user_id = u.user_id
                           WHERE e.employee_id = ?");
    $stmt->execute([$employeeId]);
    $employee = $stmt->fetch();
    
    if (!$employee) {
        header('Location: employees.php');
        exit();
    }
    
    // Get attendance summary
    $stmt = $conn->prepare("SELECT status, COUNT(*) as count 
                           FROM attendance 
                           WHERE employee_id = ? 
                           GROUP BY status");
    $stmt->execute([$employeeId]);
    $attendanceStats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Get recent attendance
    $stmt = $conn->prepare("SELECT * FROM attendance 
                           WHERE employee_id = ? 
                           ORDER BY date DESC 
                           LIMIT 10");
    $stmt->execute([$employeeId]);
    $recentAttendance = $stmt->fetchAll();
    
    // Get leave requests
    $stmt = $conn->prepare("SELECT * FROM leave_requests 
                           WHERE employee_id = ? 
                           ORDER BY created_at DESC 
                           LIMIT 5");
    $stmt->execute([$employeeId]);
    $leaveRequests = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $error = "Error fetching employee details: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Details - Shebamiles EMS</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="topbar">
                <h2>Employee Details</h2>
                <div class="topbar-actions">
                    <a href="employees.php" class="btn btn-sm btn-outline-orange">
                        <i class="fas fa-arrow-left"></i> Back to Employees
                    </a>
                    <a href="../account/logout.php" class="btn btn-sm btn-outline-orange">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
            
            <div class="content">
                <!-- Employee Info Card -->
                <div style="background: white; border-radius: 16px; padding: 2rem; margin-bottom: 2rem; box-shadow: var(--shadow-sm);">
                    <div style="display: flex; gap: 2rem; align-items: start;">
                        <div style="width: 120px; height: 120px; border-radius: 50%; background: linear-gradient(135deg, var(--primary-orange), var(--primary-orange-dark)); display: flex; align-items: center; justify-content: center; color: white; font-size: 3rem; font-weight: 700;">
                            <?php echo strtoupper(substr($employee['first_name'], 0, 1) . substr($employee['last_name'], 0, 1)); ?>
                        </div>
                        <div style="flex: 1;">
                            <h2 style="margin-bottom: 0.5rem; color: var(--almost-black);">
                                <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                            </h2>
                            <p style="color: var(--medium-gray); margin-bottom: 1rem; font-size: 1.1rem;">
                                <?php echo htmlspecialchars($employee['position'] ?? 'N/A'); ?> • 
                                <?php echo htmlspecialchars($employee['department_name'] ?? 'No Department'); ?>
                            </p>
                            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                                <span class="badge badge-primary">
                                    <?php echo ucfirst(str_replace('-', ' ', $employee['employment_type'])); ?>
                                </span>
                                <span class="badge badge-info">
                                    <?php echo ucfirst($employee['role']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Details Grid -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; margin-bottom: 2rem;">
                    <!-- Personal Information -->
                    <div style="background: white; border-radius: 16px; padding: 2rem; box-shadow: var(--shadow-sm);">
                        <h3 style="margin-bottom: 1.5rem; color: var(--primary-orange);">
                            <i class="fas fa-user"></i> Personal Information
                        </h3>
                        <div style="display: grid; gap: 1rem;">
                            <div>
                                <strong style="color: var(--dark-gray); display: block; margin-bottom: 0.25rem;">Employee Code</strong>
                                <span><?php echo htmlspecialchars($employee['employee_code']); ?></span>
                            </div>
                            <div>
                                <strong style="color: var(--dark-gray); display: block; margin-bottom: 0.25rem;">Email</strong>
                                <span><?php echo htmlspecialchars($employee['email']); ?></span>
                            </div>
                            <div>
                                <strong style="color: var(--dark-gray); display: block; margin-bottom: 0.25rem;">Phone</strong>
                                <span><?php echo htmlspecialchars($employee['phone'] ?? 'N/A'); ?></span>
                            </div>
                            <div>
                                <strong style="color: var(--dark-gray); display: block; margin-bottom: 0.25rem;">Date of Birth</strong>
                                <span><?php echo $employee['date_of_birth'] ? date('M d, Y', strtotime($employee['date_of_birth'])) : 'N/A'; ?></span>
                            </div>
                            <div>
                                <strong style="color: var(--dark-gray); display: block; margin-bottom: 0.25rem;">Gender</strong>
                                <span><?php echo ucfirst($employee['gender'] ?? 'N/A'); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Employment Information -->
                    <div style="background: white; border-radius: 16px; padding: 2rem; box-shadow: var(--shadow-sm);">
                        <h3 style="margin-bottom: 1.5rem; color: var(--primary-orange);">
                            <i class="fas fa-briefcase"></i> Employment Information
                        </h3>
                        <div style="display: grid; gap: 1rem;">
                            <div>
                                <strong style="color: var(--dark-gray); display: block; margin-bottom: 0.25rem;">Department</strong>
                                <span><?php echo htmlspecialchars($employee['department_name'] ?? 'N/A'); ?></span>
                            </div>
                            <div>
                                <strong style="color: var(--dark-gray); display: block; margin-bottom: 0.25rem;">Position</strong>
                                <span><?php echo htmlspecialchars($employee['position'] ?? 'N/A'); ?></span>
                            </div>
                            <div>
                                <strong style="color: var(--dark-gray); display: block; margin-bottom: 0.25rem;">Hire Date</strong>
                                <span><?php echo date('M d, Y', strtotime($employee['hire_date'])); ?></span>
                            </div>
                            <div>
                                <strong style="color: var(--dark-gray); display: block; margin-bottom: 0.25rem;">Employment Type</strong>
                                <span><?php echo ucfirst(str_replace('-', ' ', $employee['employment_type'])); ?></span>
                            </div>
                            <?php if (hasRole('admin')): ?>
                            <div>
                                <strong style="color: var(--dark-gray); display: block; margin-bottom: 0.25rem;">Salary</strong>
                                <span>₦<?php echo number_format($employee['salary'], 2); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Address Information -->
                    <div style="background: white; border-radius: 16px; padding: 2rem; box-shadow: var(--shadow-sm);">
                        <h3 style="margin-bottom: 1.5rem; color: var(--primary-orange);">
                            <i class="fas fa-map-marker-alt"></i> Address
                        </h3>
                        <div style="display: grid; gap: 1rem;">
                            <div>
                                <strong style="color: var(--dark-gray); display: block; margin-bottom: 0.25rem;">Address</strong>
                                <span><?php echo htmlspecialchars($employee['address'] ?? 'N/A'); ?></span>
                            </div>
                            <div>
                                <strong style="color: var(--dark-gray); display: block; margin-bottom: 0.25rem;">City</strong>
                                <span><?php echo htmlspecialchars($employee['city'] ?? 'N/A'); ?></span>
                            </div>
                            <div>
                                <strong style="color: var(--dark-gray); display: block; margin-bottom: 0.25rem;">State</strong>
                                <span><?php echo htmlspecialchars($employee['state'] ?? 'N/A'); ?></span>
                            </div>
                            <div>
                                <strong style="color: var(--dark-gray); display: block; margin-bottom: 0.25rem;">Country</strong>
                                <span><?php echo htmlspecialchars($employee['country'] ?? 'N/A'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Attendance Summary -->
                <div class="table-container" style="margin-bottom: 2rem;">
                    <div class="table-header">
                        <h3>Attendance Summary</h3>
                    </div>
                    <div style="padding: 2rem;">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1.5rem;">
                            <div style="text-align: center;">
                                <div style="font-size: 2rem; font-weight: 700; color: #4CAF50;">
                                    <?php echo $attendanceStats['present'] ?? 0; ?>
                                </div>
                                <div style="color: var(--medium-gray); margin-top: 0.5rem;">Present</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 2rem; font-weight: 700; color: #F44336;">
                                    <?php echo $attendanceStats['absent'] ?? 0; ?>
                                </div>
                                <div style="color: var(--medium-gray); margin-top: 0.5rem;">Absent</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 2rem; font-weight: 700; color: #FFC107;">
                                    <?php echo $attendanceStats['late'] ?? 0; ?>
                                </div>
                                <div style="color: var(--medium-gray); margin-top: 0.5rem;">Late</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 2rem; font-weight: 700; color: #2196F3;">
                                    <?php echo $attendanceStats['half-day'] ?? 0; ?>
                                </div>
                                <div style="color: var(--medium-gray); margin-top: 0.5rem;">Half Day</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Leave Requests -->
                <div class="table-container">
                    <div class="table-header">
                        <h3>Recent Leave Requests</h3>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Leave Type</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Status</th>
                                <th>Requested On</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($leaveRequests)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 2rem; color: var(--medium-gray);">
                                        No leave requests found
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($leaveRequests as $leave): ?>
                                <tr>
                                    <td><span class="badge badge-info"><?php echo ucfirst($leave['leave_type']); ?></span></td>
                                    <td><?php echo date('M d, Y', strtotime($leave['start_date'])); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($leave['end_date'])); ?></td>
                                    <td>
                                        <?php
                                        $statusClass = 'badge-warning';
                                        if ($leave['status'] === 'approved') $statusClass = 'badge-success';
                                        if ($leave['status'] === 'rejected') $statusClass = 'badge-danger';
                                        ?>
                                        <span class="badge <?php echo $statusClass; ?>">
                                            <?php echo ucfirst($leave['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($leave['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <?php include __DIR__ . '/../includes/badge.php'; ?>
</body>
</html>
