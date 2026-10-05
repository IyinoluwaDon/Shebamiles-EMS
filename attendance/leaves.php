<?php
// LEAVE MANAGEMENT PAGE
// PURPOSE: Manage leave requests workflow (submit, approve, reject, view)
// PERMISSIONS: Requires 'view_leaves' permission
// ROLES: Employees can request leaves, Admins can approve/reject leaves
// WORKFLOW: Submit request → Admin review → Approve/Reject → Track status

// STEP 1: Include auth and require login with permission
require_once __DIR__ . '/../includes/auth.php';
requireLogin();  // Redirect if not authenticated
requirePermission('view_leaves');  // Redirect if lacks leave permission

// STEP 2: Initialize database connection
$db = new Database();
$conn = $db->getConnection();

// STEP 3: Check database connection
if ($conn === null) {
    $error = 'Database connection unavailable. Please import database/shebamiles_db.sql.';
}

$success = '';
$error = '';

// STEP 4: HANDLE FORM SUBMISSIONS (Request/Approve/Reject)
// Process POST requests for leave actions
if ($conn !== null && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // ACTION: Submit new leave request
    if ($_POST['action'] === 'request_leave') {
        try {
            // Get current employee's ID from user session
            // This ensures employees can only submit their own leave requests
            $employeeId = getCurrentUser()['employee_id'];
            
            // Insert new leave request with pending status
            // Status starts as 'pending' and awaits manager/admin approval
            $stmt = $conn->prepare("INSERT INTO leave_requests (employee_id, leave_type, start_date, end_date, reason) 
                                   VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $employeeId,
                $_POST['leave_type'],
                $_POST['start_date'],
                $_POST['end_date'],
                sanitize($_POST['reason'])
            ]);
            
            $success = "Leave request submitted successfully!";
        } catch(PDOException $e) {
            $error = "Error submitting request: " . $e->getMessage();
        }
    }
    
    // ACTION: Approve or Reject leave request
    // Only admins with appropriate permissions can perform this action
    if (($_POST['action'] === 'approve' || $_POST['action'] === 'reject') && 
        (($_POST['action'] === 'approve' && hasPermission('approve_leave')) || 
         ($_POST['action'] === 'reject' && hasPermission('reject_leave')))) {
        try {
            // Determine status based on action (approve → 'approved', reject → 'rejected')
            $status = $_POST['action'] === 'approve' ? 'approved' : 'rejected';
            
            // Update leave request with approval decision and metadata
            // Sets: status, who approved (approved_by), when approved (approval_date), optional comments
            $stmt = $conn->prepare("UPDATE leave_requests 
                                   SET status = ?, approved_by = ?, approval_date = NOW(), comments = ?
                                   WHERE leave_id = ?");
            $stmt->execute([
                $status,
                getCurrentUser()['user_id'],  // Record which admin/manager made decision
                sanitize($_POST['comments'] ?? ''),  // Optional manager comments
                $_POST['leave_id']
            ]);
            
            $success = "Leave request " . $status . " successfully!";
        } catch(PDOException $e) {
            $error = "Error processing request: " . $e->getMessage();
        }
    }
}

// STEP 5: FETCH LEAVE REQUESTS
// Display all or filtered leave requests based on user role and permissions
try {
    if ($conn === null) {
        throw new Exception('Database connection unavailable. Please import database/shebamiles_db.sql.');
    }
    $user = getCurrentUser();
    
    // STEP 5a: Check if user has permission to view all leave requests
    // Admins see all requests | Regular employees see only their own
    if (hasPermission('view_all_leaves')) {
        // ADMIN VIEW: Fetch all leave requests with complete employee and manager info
        // JOINs get: employee details (code, name), department, and approver name
        $stmt = $conn->query("SELECT l.*, e.employee_code, e.first_name, e.last_name, 
                             d.department_name, u.username as approved_by_name
                             FROM leave_requests l
                             JOIN employees e ON l.employee_id = e.employee_id
                             LEFT JOIN departments d ON e.department_id = d.department_id
                             LEFT JOIN users u ON l.approved_by = u.user_id
                             ORDER BY l.created_at DESC");
    } else {
        // EMPLOYEE VIEW: Fetch only this employee's leave requests
        // Filtered by employee_id to show only the user's leaves
        $stmt = $conn->prepare("SELECT l.*, e.employee_code, e.first_name, e.last_name, 
                               d.department_name, u.username as approved_by_name
                               FROM leave_requests l
                               JOIN employees e ON l.employee_id = e.employee_id
                               LEFT JOIN departments d ON e.department_id = d.department_id
                               LEFT JOIN users u ON l.approved_by = u.user_id
                               WHERE l.employee_id = ?
                               ORDER BY l.created_at DESC");
        $stmt->execute([$user['employee_id']]);
    }
    
    $leaveRequests = $stmt->fetchAll();
    
    // STEP 5b: Calculate leave request statistics
    // Uses array_filter() to count requests by status
    // Pending: awaiting approval | Approved: accepted | Rejected: denied
    $pending = count(array_filter($leaveRequests, fn($r) => $r['status'] === 'pending'));
    $approved = count(array_filter($leaveRequests, fn($r) => $r['status'] === 'approved'));
    $rejected = count(array_filter($leaveRequests, fn($r) => $r['status'] === 'rejected'));
    
} catch(PDOException $e) {
    $error = "Error fetching leave requests: " . $e->getMessage();
} catch(Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Requests - Shebamiles EMS</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="topbar">
                <h2>Leave Requests</h2>
                <div class="topbar-actions">
                    <button onclick="showRequestModal()" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus"></i> Request Leave
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
                
                <!-- Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-card-icon" style="background: linear-gradient(135deg, #FFC107, #F57C00);">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3>Pending</h3>
                        <div class="stat-card-value"><?php echo $pending; ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-card-icon" style="background: linear-gradient(135deg, #4CAF50, #388E3C);">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3>Approved</h3>
                        <div class="stat-card-value"><?php echo $approved; ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-card-icon" style="background: linear-gradient(135deg, #F44336, #C62828);">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <h3>Rejected</h3>
                        <div class="stat-card-value"><?php echo $rejected; ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-card-icon">
                            <i class="fas fa-list"></i>
                        </div>
                        <h3>Total Requests</h3>
                        <div class="stat-card-value"><?php echo count($leaveRequests); ?></div>
                    </div>
                </div>
                
                <!-- Leave Requests Table -->
                <div class="table-container">
                    <div class="table-header">
                        <h3>All Leave Requests</h3>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <?php if (hasRole('admin')): ?>
                                <th>Employee</th>
                                <th>Department</th>
                                <?php endif; ?>
                                <th>Leave Type</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Days</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($leaveRequests)): ?>
                                <tr>
                                    <td colspan="<?php echo hasRole('admin') ? '9' : '7'; ?>">
                                        <div class="empty-state">
                                            <i class="fas fa-calendar-alt"></i>
                                            <h3>No Leave Requests</h3>
                                            <p>Submit your first leave request</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($leaveRequests as $leave): ?>
                                <?php
                                $start = new DateTime($leave['start_date']);
                                $end = new DateTime($leave['end_date']);
                                $days = $start->diff($end)->days + 1;
                                ?>
                                <tr>
                                    <?php if (hasRole('admin')): ?>
                                    <td><strong><?php echo htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($leave['department_name'] ?? 'N/A'); ?></td>
                                    <?php endif; ?>
                                    <td>
                                        <span class="badge badge-info">
                                            <?php echo ucfirst($leave['leave_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($leave['start_date'])); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($leave['end_date'])); ?></td>
                                    <td><strong><?php echo $days; ?> day<?php echo $days > 1 ? 's' : ''; ?></strong></td>
                                    <td><?php echo htmlspecialchars(substr($leave['reason'], 0, 50)) . (strlen($leave['reason']) > 50 ? '...' : ''); ?></td>
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
                                    <td>
                                        <?php if (hasRole('admin') && $leave['status'] === 'pending'): ?>
                                        <button onclick="showApprovalModal(<?php echo $leave['leave_id']; ?>, '<?php echo htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']); ?>')" 
                                                class="btn-icon" title="Review">
                                            <i class="fas fa-tasks"></i>
                                        </button>
                                        <?php else: ?>
                                        <span class="btn-icon" title="No details view available" style="cursor: not-allowed; opacity: 0.5;">
                                            <i class="fas fa-eye"></i>
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Request Leave Modal -->
    <div id="requestModal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Request Leave</h3>
                <button class="modal-close" onclick="closeModal('requestModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="request_leave">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Leave Type *</label>
                        <select name="leave_type" class="form-control" required>
                            <option value="">Select Type</option>
                            <option value="sick">Sick Leave</option>
                            <option value="vacation">Vacation</option>
                            <option value="personal">Personal Leave</option>
                            <option value="emergency">Emergency Leave</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Start Date *</label>
                            <input type="date" name="start_date" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>End Date *</label>
                            <input type="date" name="end_date" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Reason *</label>
                        <textarea name="reason" class="form-control" rows="4" 
                                  placeholder="Please provide a reason for your leave request..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-orange" onclick="closeModal('requestModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Approval Modal -->
    <div id="approvalModal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Review Leave Request</h3>
                <button class="modal-close" onclick="closeModal('approvalModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="leave_id" id="approval_leave_id">
                <div class="modal-body">
                    <p id="approval_employee_name" style="margin-bottom: 1rem; font-weight: 600;"></p>
                    
                    <div class="form-group">
                        <label>Comments</label>
                        <textarea name="comments" class="form-control" rows="3" 
                                  placeholder="Optional comments..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-orange" onclick="closeModal('approvalModal')">Cancel</button>
                    <button type="submit" name="action" value="reject" class="btn" style="background: #F44336; color: white;">
                        <i class="fas fa-times"></i> Reject
                    </button>
                    <button type="submit" name="action" value="approve" class="btn" style="background: #4CAF50; color: white;">
                        <i class="fas fa-check"></i> Approve
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function showRequestModal() {
            document.getElementById('requestModal').style.display = 'flex';
        }
        
        function showApprovalModal(leaveId, employeeName) {
            document.getElementById('approval_leave_id').value = leaveId;
            document.getElementById('approval_employee_name').textContent = 'Leave request from: ' + employeeName;
            document.getElementById('approvalModal').style.display = 'flex';
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
