<?php
// EMPLOYEE MANAGEMENT PAGE
// PURPOSE: Display list of all employees, handle add/edit/delete operations
// PERMISSIONS: Requires 'view_employees' permission (admin/manager only)
// WORKFLOW: Display table → Search/Filter → Add Modal → Submit → Database → Refresh table

// STEP 1: Include authentication functions and require user to be logged in with permission
require_once __DIR__ . '/../includes/auth.php';
requireLogin();  // Redirect to login if not authenticated
requirePermission('view_employees');  // Redirect if user lacks 'view_employees' permission

// STEP 2: Initialize database connection
$db = new Database();
$conn = $db->getConnection();

// STEP 3: Check if database connection is available
if ($conn === null) {
    $error = 'Database connection unavailable. Please import database/shebamiles_db.sql.';
}

// STEP 4: Initialize success and error message variables
$success = '';
$error = '';

// STEP 5: HANDLE FORM SUBMISSIONS (Add/Edit/Delete operations)
// Check if request is POST with 'action' parameter set
if ($conn !== null && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // ACTION: Add new employee
    // PERMISSION: Requires 'create_employee' permission
    if ($_POST['action'] === 'add_employee' && hasPermission('create_employee')) {
        try {
            // STEP 5a: Create user account in users table (required for login)
            // Sanitize inputs and hash password using bcrypt for security
            $username = sanitize($_POST['username']);
            $email = sanitize($_POST['email']);
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $role = sanitize($_POST['role']);
            
            // Insert user record and get the auto-generated user_id
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$username, $email, $password, $role]);
            $userId = $conn->lastInsertId();
            
            // STEP 5b: Create employee record linked to user account
            // This 2-step process ensures both authentication and employee data are created
            $stmt = $conn->prepare("INSERT INTO employees (user_id, employee_code, first_name, last_name, date_of_birth, gender, phone, address, city, state, country, department_id, position, hire_date, employment_type, salary) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $userId,
                sanitize($_POST['employee_code']),
                sanitize($_POST['first_name']),
                sanitize($_POST['last_name']),
                $_POST['date_of_birth'],
                $_POST['gender'],
                sanitize($_POST['phone']),
                sanitize($_POST['address']),
                sanitize($_POST['city']),
                sanitize($_POST['state']),
                sanitize($_POST['country']),
                $_POST['department_id'] ?: null,  // Department is optional
                sanitize($_POST['position']),
                $_POST['hire_date'],
                $_POST['employment_type'],
                $_POST['salary']
            ]);
            
            $success = "Employee added successfully!";
        } catch(PDOException $e) {
            $error = "Error adding employee: " . $e->getMessage();
        }
    }
}

// STEP 6: HANDLE DELETE REQUEST
// Triggered by delete action from query parameter (e.g., ?delete=5)
// PERMISSION: Requires 'delete_employee' permission
if ($conn !== null && isset($_GET['delete']) && hasPermission('delete_employee')) {
    try {
        $employeeId = $_GET['delete'];
        
        // STEP 6a: Fetch user_id for this employee (needed for cascading delete)
        // Users table has cascade delete rules, so deleted user cascades to employees
        $stmt = $conn->prepare("SELECT user_id FROM employees WHERE employee_id = ?");
        $stmt->execute([$employeeId]);
        $userId = $stmt->fetch()['user_id'];
        
        // STEP 6b: Delete user record (this cascades delete to employee record)
        // Deleting from users table removes the corresponding employee due to foreign key constraints
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        $success = "Employee deleted successfully!";
    } catch(PDOException $e) {
        $error = "Error deleting employee: " . $e->getMessage();
    }
}

// STEP 7: FETCH EMPLOYEES WITH SEARCH AND FILTERING
// Display all employees in table, with optional search and department filtering
try {
    if ($conn === null) {
        throw new Exception('Database connection unavailable. Please import database/shebamiles_db.sql.');
    }
    
    // STEP 7a: Get search and filter parameters from query string
    // These come from the search form submission
    $search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
    $departmentFilter = isset($_GET['department']) ? $_GET['department'] : '';
    
    // STEP 7b: Build base query with JOINs to get complete employee information
    // LEFT JOINs ensure employees without dept/user records still display
    // Selects: employee details + department name + user email/role
    $query = "SELECT e.*, d.department_name, u.email, u.role 
              FROM employees e 
              LEFT JOIN departments d ON e.department_id = d.department_id 
              LEFT JOIN users u ON e.user_id = u.user_id 
              WHERE 1=1";
    
    // STEP 7c: Initialize parameters array for prepared statement
    $params = [];
    
    // STEP 7d: Add search filter if provided
    // Search by first_name, last_name, or employee_code (case-insensitive LIKE)
    // Uses %search% pattern for substring matching
    if ($search) {
        $query .= " AND (e.first_name LIKE ? OR e.last_name LIKE ? OR e.employee_code LIKE ?)";
        $searchParam = "%$search%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
    }
    
    // STEP 7e: Add department filter if provided
    // Filters to show only employees in selected department
    if ($departmentFilter) {
        $query .= " AND e.department_id = ?";
        $params[] = $departmentFilter;
    }
    
    // STEP 7f: Sort results by creation date (newest first)
    $query .= " ORDER BY e.created_at DESC";
    
    // STEP 7g: Execute query with prepared statement (prevents SQL injection)
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $employees = $stmt->fetchAll();
    
    // STEP 7h: Fetch all departments for the filter dropdown
    $stmt = $conn->query("SELECT * FROM departments ORDER BY department_name");
    $departments = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $error = "Error fetching employees: " . $e->getMessage();
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
    <title>Employees - Shebamiles EMS</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="topbar">
                <h2>Employees</h2>
                <div class="topbar-actions">
                    <?php if (hasPermission('create_employee')): ?>
                    <button onclick="showAddModal()" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus"></i> Add Employee
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
                
                <!-- Search and Filter -->
                <div class="table-container" style="margin-bottom: 1rem;">
                    <div style="padding: 1.5rem;">
                        <form method="GET" action="" class="form-row">
                            <div class="form-group" style="margin-bottom: 0;">
                                <input 
                                    type="text" 
                                    name="search" 
                                    class="form-control" 
                                    placeholder="Search by name or code..."
                                    value="<?php echo htmlspecialchars($search); ?>"
                                >
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <select name="department" class="form-control">
                                    <option value="">All Departments</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['department_id']; ?>" 
                                                <?php echo $departmentFilter == $dept['department_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($dept['department_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Search
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Employees Table -->
                <div class="table-container">
                    <div class="table-header">
                        <h3>All Employees (<?php echo count($employees); ?>)</h3>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Department</th>
                                <th>Position</th>
                                <th>Phone</th>
                                <th>Type</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($employees)): ?>
                                <tr>
                                    <td colspan="8">
                                        <div class="empty-state">
                                            <i class="fas fa-users"></i>
                                            <h3>No Employees Found</h3>
                                            <p>Add your first employee to get started</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($employees as $emp): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($emp['employee_code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($emp['email']); ?></td>
                                    <td><?php echo htmlspecialchars($emp['department_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($emp['position'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($emp['phone'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge badge-primary">
                                            <?php echo ucfirst(str_replace('-', ' ', $emp['employment_type'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (hasPermission('view_employee_details')): ?>
                                        <a href="employee-details.php?id=<?php echo $emp['employee_id']; ?>" class="btn-icon" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php endif; ?>
                                        <?php if (hasPermission('delete_employee')): ?>
                                        <a href="?delete=<?php echo $emp['employee_id']; ?>" 
                                           class="btn-icon" 
                                           title="Delete"
                                           onclick="return confirm('Are you sure you want to delete this employee?')">
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
        </main>
    </div>
    
    <!-- Add Employee Modal -->
    <div id="addModal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Employee</h3>
                <button class="modal-close" onclick="closeModal('addModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_employee">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Employee Code *</label>
                            <input type="text" name="employee_code" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Username *</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name *</label>
                            <input type="text" name="first_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Last Name *</label>
                            <input type="text" name="last_name" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Password *</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Date of Birth</label>
                            <input type="date" name="date_of_birth" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Gender</label>
                            <select name="gender" class="form-control">
                                <option value="">Select Gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="tel" name="phone" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Role *</label>
                            <select name="role" class="form-control" required>
                                <option value="employee">Employee</option>
                                <option value="manager">Manager</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Address</label>
                        <input type="text" name="address" class="form-control">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>City</label>
                            <input type="text" name="city" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>State</label>
                            <input type="text" name="state" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Country</label>
                            <input type="text" name="country" class="form-control" value="Nigeria">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Department</label>
                            <select name="department_id" class="form-control">
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['department_id']; ?>">
                                        <?php echo htmlspecialchars($dept['department_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Position</label>
                            <input type="text" name="position" class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Hire Date *</label>
                            <input type="date" name="hire_date" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Employment Type *</label>
                            <select name="employment_type" class="form-control" required>
                                <option value="full-time">Full-time</option>
                                <option value="part-time">Part-time</option>
                                <option value="contract">Contract</option>
                                <option value="intern">Intern</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Salary</label>
                            <input type="number" name="salary" class="form-control" step="0.01">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-orange" onclick="closeModal('addModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Employee</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function showAddModal() {
            document.getElementById('addModal').style.display = 'flex';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Close modal on outside click
        window.onclick = function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.style.display = 'none';
            }
        }
    </script>
    
    <?php include __DIR__ . '/../includes/badge.php'; ?>
</body>
</html>
