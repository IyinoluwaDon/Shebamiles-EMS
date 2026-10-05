<?php
// ATTENDANCE MANAGEMENT PAGE
// PURPOSE: Display and manage daily attendance records
// PERMISSIONS: Requires 'view_attendance' permission
// FEATURES: Mark attendance, view records by date, calculate daily statistics
// WORKFLOW: Select date → View/manage attendance → Calculate stats → Display table

// STEP 1: Include auth and require login with permission
require_once __DIR__ . '/../includes/auth.php';
requireLogin();  // Redirect if not authenticated
requirePermission('view_attendance');  // Redirect if lacks attendance permission

// STEP 2: Initialize database connection
$db = new Database();
$conn = $db->getConnection();

// STEP 3: Check database connection
if ($conn === null) {
    $error = 'Database connection unavailable. Please import database/shebamiles_db.sql.';
}

$success = '';
$error = '';

// STEP 4: HANDLE MARK ATTENDANCE (POST Request)
// Insert new attendance record or update existing record for same date/employee
if ($conn !== null && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'mark_attendance') {
        try {
            // IMPORTANT: This uses INSERT...ON DUPLICATE KEY UPDATE (upsert pattern)
            // PURPOSE: Create new record if doesn't exist, update if already exists for date+employee
            // MySQL will automatically detect unique key conflict and update instead of insert
            // BENEFIT: No need to check if record exists first, single query handles both cases
            
            $stmt = $conn->prepare("INSERT INTO attendance (employee_id, date, clock_in, clock_out, status, notes) 
                                   VALUES (?, ?, ?, ?, ?, ?)
                                   ON DUPLICATE KEY UPDATE 
                                   clock_in = VALUES(clock_in), 
                                   clock_out = VALUES(clock_out), 
                                   status = VALUES(status),
                                   notes = VALUES(notes)");
            
            // Execute with attendance data
            // Some fields like clock_in/clock_out are optional (uses null if empty)
            $stmt->execute([
                $_POST['employee_id'],
                $_POST['date'],
                $_POST['clock_in'] ?: null,  // Null if time not provided
                $_POST['clock_out'] ?: null,  // Null if time not provided
                $_POST['status'],
                sanitize($_POST['notes'])
            ]);
            
            $success = "Attendance marked successfully!";
        } catch(PDOException $e) {
            $error = "Error marking attendance: " . $e->getMessage();
        }
    }
}

// STEP 5: FETCH ATTENDANCE RECORDS FOR SELECTED DATE
// Role-based visibility: Admins see all employees, regular employees see only themselves
try {
    if ($conn === null) {
        throw new Exception('Database connection unavailable. Please import database/shebamiles_db.sql.');
    }
    
    // STEP 5a: Get date filter from query parameter (defaults to today)
    $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
    
    // STEP 5b: Build base query to fetch attendance records for selected date
    // JOINs ensure we get employee details (code, name) and department info
    // LEFT JOINs keep records even if employee lacks department assignment
    $attendanceQuery = "SELECT a.*, e.employee_code, e.first_name, e.last_name, d.department_name
                           FROM attendance a
                           JOIN employees e ON a.employee_id = e.employee_id
                           LEFT JOIN departments d ON e.department_id = d.department_id
                           WHERE a.date = ?";
    
    $attendanceParams = [$date];
    
    // STEP 5c: Apply role-based visibility filter
    // Regular employees (without 'view_all_attendance') see only their own records
    // Admins see all employee records for the date
    if (!hasPermission('view_all_attendance')) {
        // User is not admin: filter to only their attendance
        $attendanceQuery .= " AND e.user_id = ?";
        $attendanceParams[] = $_SESSION['user_id'];
    }
    
    // STEP 5d: Sort results by employee name
    $attendanceQuery .= " ORDER BY e.first_name, e.last_name";
    
    // Execute query with prepared statement (prevents SQL injection)
    $stmt = $conn->prepare($attendanceQuery);
    $stmt->execute($attendanceParams);
    $attendanceRecords = $stmt->fetchAll();
    
    // STEP 5e: Fetch employee list for "Mark Attendance" dropdown
    // Different list based on permission: admins see all, employees see only self
    $employeeQuery = "SELECT e.*, d.department_name 
                         FROM employees e 
                         LEFT JOIN departments d ON e.department_id = d.department_id";
    
    if (!hasPermission('view_all_attendance')) {
        // Regular employee: only show themselves in dropdown
        $employeeQuery .= " WHERE e.user_id = ?";
        $stmt = $conn->prepare($employeeQuery);
        $stmt->execute([$_SESSION['user_id']]);
    } else {
        // Admin: show all employees, sorted by name
        $stmt = $conn->prepare($employeeQuery . " ORDER BY e.first_name, e.last_name");
        $stmt->execute();
    }
    $allEmployees = $stmt->fetchAll();
    
    // STEP 5f: Calculate daily statistics from attendance records
    // Uses array_filter() with arrow functions to count records by status
    // Each filter counts records where status matches the condition
    $present = count(array_filter($attendanceRecords, fn($r) => $r['status'] === 'present'));
    $absent = count(array_filter($attendanceRecords, fn($r) => $r['status'] === 'absent'));
    $late = count(array_filter($attendanceRecords, fn($r) => $r['status'] === 'late'));
    $halfDay = count(array_filter($attendanceRecords, fn($r) => $r['status'] === 'half-day'));
    
} catch(PDOException $e) {
    $error = "Error fetching attendance: " . $e->getMessage();
} catch(Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - Shebamiles EMS</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="topbar">
                <h2>Attendance Management</h2>
                <div class="topbar-actions">
                    <?php if (hasPermission('manage_attendance')): ?>
                    <button onclick="showMarkModal()" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus"></i> Mark Attendance
                    </button>
                    <?php endif; ?>
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
                
                <!-- Date Filter -->
                <div class="table-container" style="margin-bottom: 1rem;">
                    <div style="padding: 1.5rem;">
                        <form method="GET" action="" class="form-row">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label>Select Date</label>
                                <input 
                                    type="date" 
                                    name="date" 
                                    class="form-control" 
                                    value="<?php echo htmlspecialchars($date); ?>"
                                    onchange="this.form.submit()"
                                >
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-card-icon" style="background: linear-gradient(135deg, #4CAF50, #388E3C);">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <h3>Present</h3>
                        <div class="stat-card-value"><?php echo $present; ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-card-icon" style="background: linear-gradient(135deg, #F44336, #C62828);">
                            <i class="fas fa-user-times"></i>
                        </div>
                        <h3>Absent</h3>
                        <div class="stat-card-value"><?php echo $absent; ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-card-icon" style="background: linear-gradient(135deg, #FFC107, #F57C00);">
                            <i class="fas fa-user-clock"></i>
                        </div>
                        <h3>Late</h3>
                        <div class="stat-card-value"><?php echo $late; ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-card-icon" style="background: linear-gradient(135deg, #2196F3, #1565C0);">
                            <i class="fas fa-user-minus"></i>
                        </div>
                        <h3>Half Day</h3>
                        <div class="stat-card-value"><?php echo $halfDay; ?></div>
                    </div>
                </div>
                
                <!-- Attendance Table -->
                <div class="table-container">
                    <div class="table-header">
                        <h3>Attendance for <?php echo date('F d, Y', strtotime($date)); ?></h3>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Employee Code</th>
                                <th>Name</th>
                                <th>Department</th>
                                <th>Clock In</th>
                                <th>Clock Out</th>
                                <th>Status</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($attendanceRecords)): ?>
                                <tr>
                                    <td colspan="7">
                                        <div class="empty-state">
                                            <i class="fas fa-clock"></i>
                                            <h3>No Attendance Records</h3>
                                            <p>Mark attendance for this date</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($attendanceRecords as $record): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($record['employee_code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($record['department_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo $record['clock_in'] ? date('h:i A', strtotime($record['clock_in'])) : 'N/A'; ?></td>
                                    <td><?php echo $record['clock_out'] ? date('h:i A', strtotime($record['clock_out'])) : 'N/A'; ?></td>
                                    <td>
                                        <?php
                                        $statusClass = 'badge-primary';
                                        if ($record['status'] === 'present') $statusClass = 'badge-success';
                                        if ($record['status'] === 'absent') $statusClass = 'badge-danger';
                                        if ($record['status'] === 'late') $statusClass = 'badge-warning';
                                        if ($record['status'] === 'half-day') $statusClass = 'badge-info';
                                        ?>
                                        <span class="badge <?php echo $statusClass; ?>">
                                            <?php echo ucfirst(str_replace('-', ' ', $record['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($record['notes'] ?? '-'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Mark Attendance Modal -->
    <div id="markModal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Mark Attendance</h3>
                <button class="modal-close" onclick="closeModal('markModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="mark_attendance">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Employee *</label>
                        <select name="employee_id" class="form-control" required>
                            <option value="">Select Employee</option>
                            <?php foreach ($allEmployees as $emp): ?>
                                <option value="<?php echo $emp['employee_id']; ?>">
                                    <?php echo htmlspecialchars($emp['employee_code'] . ' - ' . $emp['first_name'] . ' ' . $emp['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Date *</label>
                        <input type="date" name="date" class="form-control" value="<?php echo $date; ?>" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Clock In</label>
                            <input type="time" name="clock_in" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Clock Out</label>
                            <input type="time" name="clock_out" class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Status *</label>
                        <select name="status" class="form-control" required>
                            <option value="present">Present</option>
                            <option value="absent">Absent</option>
                            <option value="late">Late</option>
                            <option value="half-day">Half Day</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="3" 
                                  placeholder="Any additional notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-orange" onclick="closeModal('markModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Mark Attendance</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function showMarkModal() {
            document.getElementById('markModal').style.display = 'flex';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.style.display = 'none';
            }
        }
    </script>
    
    <?php include __DIR__ . '/../includes/badge.php'; ?>
</body>
</html>
