<?php
// PAYROLL MANAGEMENT PAGE
// PURPOSE: Manage employee salary processing and payment tracking
// PERMISSIONS: Requires 'view_payroll' permission (admin only)
// FEATURES: Add payroll records, calculate net salary, track payment status, record payments
// WORKFLOW: Calculate salary → Record payroll → Track payment → Mark as paid

// STEP 1: Include auth and require login with permission
require_once __DIR__ . '/../includes/auth.php';
requireLogin();  // Redirect if not authenticated
requirePermission('view_payroll');  // Redirect if lacks payroll permission (admin only)

// STEP 2: Initialize database connection
$db = new Database();
$conn = $db->getConnection();

// STEP 3: Check database connection
if ($conn === null) {
    $error = 'Database connection unavailable. Please import database/shebamiles_db.sql.';
}

$success = '';
$error = '';

// STEP 4: HANDLE FORM SUBMISSIONS (Add/Update Payroll)
// Process POST requests for payroll operations
if ($conn !== null && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // ACTION: Add new payroll record
    if ($_POST['action'] === 'add_payroll') {
        try {
            // STEP 4a: Calculate net salary from components
            // FORMULA: Net Salary = Basic Salary + Bonuses - Deductions
            // All values converted to float for accurate calculation
            $basicSalary = floatval($_POST['basic_salary']);
            $bonuses = floatval($_POST['bonuses'] ?? 0);
            $deductions = floatval($_POST['deductions'] ?? 0);
            $netSalary = $basicSalary + $bonuses - $deductions;
            
            // STEP 4b: Insert payroll record with calculated net salary
            // Stores: employee_id, pay period dates, salary components, payment status
            $stmt = $conn->prepare("INSERT INTO payroll (employee_id, pay_period_start, pay_period_end, basic_salary, bonuses, deductions, net_salary, payment_date, payment_status) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $_POST['employee_id'],
                $_POST['pay_period_start'],
                $_POST['pay_period_end'],
                $basicSalary,
                $bonuses,
                $deductions,
                $netSalary,
                $_POST['payment_date'] ?? null,  // Optional payment date (null if pending)
                $_POST['payment_status']  // Either 'pending' or 'paid'
            ]);
            
            $success = "Payroll record added successfully!";
        } catch(PDOException $e) {
            $error = "Error adding payroll: " . $e->getMessage();
        }
    }
    
    // ACTION: Update payment status
    // Used to mark a pending payment as 'paid' and record payment date
    if ($_POST['action'] === 'update_status') {
        try {
            // STEP 4c: Update payment status and auto-set payment_date
            // If status = 'paid': Sets payment_date to today (NOW())
            // If status = 'pending': Sets payment_date to null
            $stmt = $conn->prepare("UPDATE payroll SET payment_status = ?, payment_date = ? WHERE payroll_id = ?");
            $stmt->execute([
                $_POST['status'],
                $_POST['status'] === 'paid' ? date('Y-m-d') : null,  // Auto-date if marked paid
                $_POST['payroll_id']
            ]);
            
            $success = "Payment status updated successfully!";
        } catch(PDOException $e) {
            $error = "Error updating status: " . $e->getMessage();
        }
    }
}

// STEP 5: HANDLE DELETE REQUEST
// Delete a payroll record from database
if ($conn !== null && isset($_GET['delete'])) {
    try {
        $stmt = $conn->prepare("DELETE FROM payroll WHERE payroll_id = ?");
        $stmt->execute([$_GET['delete']]);
        $success = "Payroll record deleted successfully!";
    } catch(PDOException $e) {
        $error = "Error deleting record: " . $e->getMessage();
    }
}

// STEP 6: FETCH PAYROLL RECORDS WITH SEARCH/FILTER
// Display all payroll records with optional filtering by status and search by employee
try {
    if ($conn === null) {
        throw new Exception('Database connection unavailable. Please import database/shebamiles_db.sql.');
    }
    
    // STEP 6a: Get filter and search parameters
    $filter = isset($_GET['status']) ? $_GET['status'] : '';  // 'pending' or 'paid' or empty
    $search = isset($_GET['search']) ? sanitize($_GET['search']) : '';  // Search by employee name/code
    
    // STEP 6b: Build query to fetch all payroll records with employee and department details
    // JOINs: employees (to get name/code), departments (to get department name)
    $query = "SELECT p.*, e.employee_code, e.first_name, e.last_name, d.department_name
              FROM payroll p
              JOIN employees e ON p.employee_id = e.employee_id
              LEFT JOIN departments d ON e.department_id = d.department_id
              WHERE 1=1";
    
    // STEP 6c: Initialize params array for prepared statement
    $params = [];
    
    // STEP 6d: Add status filter if provided
    // Filters to show only 'pending' or 'paid' payroll records
    if ($filter) {
        $query .= " AND p.payment_status = ?";
        $params[] = $filter;
    }
    
    // STEP 6e: Add search filter if provided
    // Search by first name, last name, or employee code (case-insensitive)
    if ($search) {
        $query .= " AND (e.first_name LIKE ? OR e.last_name LIKE ? OR e.employee_code LIKE ?)";
        $searchParam = "%$search%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
    }
    
    // STEP 6f: Sort results by creation date (newest first)
    $query .= " ORDER BY p.created_at DESC";
    
    // Execute query with prepared statement
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $payrollRecords = $stmt->fetchAll();
    
    // STEP 6g: Fetch all employees for the "Add Payroll" dropdown
    $stmt = $conn->query("SELECT employee_id, employee_code, first_name, last_name, salary FROM employees ORDER BY first_name");
    $employees = $stmt->fetchAll();
    
    // STEP 6h: Calculate payroll statistics using array functions
    // Totals: sum of net_salary for paid and pending records
    // Counts: number of paid vs pending records
    
    // Total paid amount (sum of net_salary where status = 'paid')
    $totalPaid = array_sum(array_map(fn($r) => $r['payment_status'] === 'paid' ? $r['net_salary'] : 0, $payrollRecords));
    
    // Total pending amount (sum of net_salary where status = 'pending')
    $totalPending = array_sum(array_map(fn($r) => $r['payment_status'] === 'pending' ? $r['net_salary'] : 0, $payrollRecords));
    
    // Count of paid records
    $paidCount = count(array_filter($payrollRecords, fn($r) => $r['payment_status'] === 'paid'));
    
    // Count of pending records
    $pendingCount = count(array_filter($payrollRecords, fn($r) => $r['payment_status'] === 'pending'));
    
} catch(PDOException $e) {
    $error = "Error fetching payroll data: " . $e->getMessage();
} catch(Exception $e) {
    $error = $e->getMessage();
}

$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Management - Shebamiles EMS</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="topbar">
                <h2>Payroll Management</h2>
                <div class="topbar-actions">
                    <button onclick="showAddModal()" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus"></i> Add Payroll
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
                        <div class="stat-card-icon" style="background: linear-gradient(135deg, #4CAF50, #388E3C);">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3>Total Paid</h3>
                        <div class="stat-card-value">₦<?php echo number_format($totalPaid, 2); ?></div>
                        <p class="stat-card-label"><?php echo $paidCount; ?> payments</p>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-card-icon" style="background: linear-gradient(135deg, #FFC107, #F57C00);">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3>Total Pending</h3>
                        <div class="stat-card-value">₦<?php echo number_format($totalPending, 2); ?></div>
                        <p class="stat-card-label"><?php echo $pendingCount; ?> pending</p>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-card-icon" style="background: linear-gradient(135deg, #2196F3, #1565C0);">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <h3>Total Records</h3>
                        <div class="stat-card-value"><?php echo count($payrollRecords); ?></div>
                        <p class="stat-card-label">All time</p>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-card-icon" style="background: linear-gradient(135deg, #FF5722, #D84315);">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3>Employees</h3>
                        <div class="stat-card-value"><?php echo count($employees); ?></div>
                        <p class="stat-card-label">Active employees</p>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="card">
                    <div class="card-body">
                        <form method="GET" class="filter-form">
                            <div class="form-row">
                                <div class="form-group">
                                    <input type="text" name="search" class="form-control" placeholder="Search by employee..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="form-group">
                                    <select name="status" class="form-control">
                                        <option value="">All Status</option>
                                        <option value="pending" <?php echo $filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="paid" <?php echo $filter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                                <a href="payroll.php" class="btn btn-outline-orange">
                                    <i class="fas fa-redo"></i> Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Payroll Table -->
                <div class="card">
                    <div class="card-header">
                        <h3>Payroll Records</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Department</th>
                                        <th>Period</th>
                                        <th>Basic Salary</th>
                                        <th>Bonuses</th>
                                        <th>Deductions</th>
                                        <th>Net Salary</th>
                                        <th>Payment Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($payrollRecords)): ?>
                                        <tr>
                                            <td colspan="10" style="text-align: center; padding: 2rem;">
                                                <i class="fas fa-money-bill-wave" style="font-size: 3rem; color: var(--medium-gray); margin-bottom: 1rem;"></i>
                                                <p>No payroll records found</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($payrollRecords as $record): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></strong><br>
                                                    <small><?php echo htmlspecialchars($record['employee_code']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($record['department_name'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <?php echo date('M d, Y', strtotime($record['pay_period_start'])); ?><br>
                                                    <small>to <?php echo date('M d, Y', strtotime($record['pay_period_end'])); ?></small>
                                                </td>
                                                <td>₦<?php echo number_format($record['basic_salary'], 2); ?></td>
                                                <td>₦<?php echo number_format($record['bonuses'], 2); ?></td>
                                                <td>₦<?php echo number_format($record['deductions'], 2); ?></td>
                                                <td><strong>₦<?php echo number_format($record['net_salary'], 2); ?></strong></td>
                                                <td><?php echo $record['payment_date'] ? date('M d, Y', strtotime($record['payment_date'])) : '-'; ?></td>
                                                <td>
                                                    <?php if ($record['payment_status'] === 'paid'): ?>
                                                        <span class="badge badge-success">Paid</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-warning">Pending</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="actions">
                                                    <?php if ($record['payment_status'] === 'pending'): ?>
                                                        <button onclick="markAsPaid(<?php echo $record['payroll_id']; ?>)" 
                                                                class="btn-icon btn-success" 
                                                                title="Mark as Paid">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <a href="?delete=<?php echo $record['payroll_id']; ?>" 
                                                       onclick="return confirm('Are you sure you want to delete this payroll record?')" 
                                                       class="btn-icon btn-danger" 
                                                       title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
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
    
    <!-- Add Payroll Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Payroll Record</h3>
                <button class="modal-close" type="button" onclick="closeModal('addModal')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_payroll">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Employee <span class="required">*</span></label>
                        <select name="employee_id" class="form-control" required onchange="updateSalary(this)">
                            <option value="">Select Employee</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo $emp['employee_id']; ?>" data-salary="<?php echo $emp['salary']; ?>">
                                    <?php echo htmlspecialchars($emp['employee_code'] . ' - ' . $emp['first_name'] . ' ' . $emp['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Pay Period Start <span class="required">*</span></label>
                            <input type="date" name="pay_period_start" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Pay Period End <span class="required">*</span></label>
                            <input type="date" name="pay_period_end" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Basic Salary <span class="required">*</span></label>
                        <input type="number" id="basic_salary" name="basic_salary" class="form-control" step="0.01" required onchange="calculateNet()">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Bonuses</label>
                            <input type="number" id="bonuses" name="bonuses" class="form-control" step="0.01" value="0" onchange="calculateNet()">
                        </div>
                        <div class="form-group">
                            <label>Deductions</label>
                            <input type="number" id="deductions" name="deductions" class="form-control" step="0.01" value="0" onchange="calculateNet()">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Net Salary</label>
                        <input type="text" id="net_salary_display" class="form-control" readonly style="font-weight: bold; font-size: 1.2rem;">
                    </div>
                    
                    <div class="form-group">
                        <label>Payment Status <span class="required">*</span></label>
                        <select name="payment_status" class="form-control" required>
                            <option value="pending">Pending</option>
                            <option value="paid">Paid</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-orange" onclick="closeModal('addModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Payroll</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Mark as Paid Form -->
    <form id="markPaidForm" method="POST" style="display:none;">
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="status" value="paid">
        <input type="hidden" name="payroll_id" id="payroll_id_input">
    </form>
    
    <script>
        function showAddModal() {
            document.getElementById('addModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function updateSalary(select) {
            const selectedOption = select.options[select.selectedIndex];
            const salary = selectedOption.getAttribute('data-salary');
            if (salary) {
                document.getElementById('basic_salary').value = salary;
                calculateNet();
            }
        }
        
        function calculateNet() {
            const basic = parseFloat(document.getElementById('basic_salary').value) || 0;
            const bonuses = parseFloat(document.getElementById('bonuses').value) || 0;
            const deductions = parseFloat(document.getElementById('deductions').value) || 0;
            const net = basic + bonuses - deductions;
            document.getElementById('net_salary_display').value = '₦' + net.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        }
        
        function markAsPaid(payrollId) {
            if (confirm('Mark this payroll as paid?')) {
                document.getElementById('payroll_id_input').value = payrollId;
                document.getElementById('markPaidForm').submit();
            }
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
