<?php
// DEPARTMENTS MANAGEMENT PAGE
// PURPOSE: Manage organizational departments
// PERMISSIONS: Admin only (checked via hasRole('admin'))
// FEATURES: Create/delete departments, view employee distribution, assign department heads
// WORKFLOW: List departments → View employee count → Add/Delete → Manage staffing

// STEP 1: Include auth and require login
require_once __DIR__ . '/../includes/auth.php';
requireLogin();  // Redirect if not authenticated

// STEP 2: Initialize database connection
$db = new Database();
$conn = $db->getConnection();

// STEP 3: Check database connection
if ($conn === null) {
    $error = 'Database connection unavailable. Please import database/shebamiles_db.sql.';
}

$success = '';
$error = '';

// STEP 4: HANDLE FORM SUBMISSIONS (Add/Delete)
// Process POST requests for department management (admin only)
if ($conn !== null && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // ACTION: Create new department
    // Only admin users can add departments
    if ($_POST['action'] === 'add_department' && hasRole('admin')) {
        try {
            // Insert new department with name and description
            $stmt = $conn->prepare("INSERT INTO departments (department_name, description) VALUES (?, ?)");
            $stmt->execute([
                sanitize($_POST['department_name']),
                sanitize($_POST['description'])
            ]);
            $success = "Department added successfully!";
        } catch(PDOException $e) {
            $error = "Error adding department: " . $e->getMessage();
        }
    }
}

// STEP 5: HANDLE DELETE REQUEST
// Delete a department from system (admin only)
if ($conn !== null && isset($_GET['delete']) && hasRole('admin')) {
    try {
        // Delete department by ID
        // NOTE: May fail if foreign key constraints prevent orphaning employees
        // Users should reassign employees before deleting department
        $stmt = $conn->prepare("DELETE FROM departments WHERE department_id = ?");
        $stmt->execute([$_GET['delete']]);
        $success = "Department deleted successfully!";
    } catch(PDOException $e) {
        $error = "Error deleting department: " . $e->getMessage();
    }
}

// STEP 6: FETCH ALL DEPARTMENTS WITH STATISTICS
// Display all departments with employee counts and department head info
try {
    if ($conn === null) {
        throw new Exception('Database connection unavailable. Please import database/shebamiles_db.sql.');
    }
    
    // Query to get:
    // - Department info
    // - COUNT of employees in each department  
    // - Department head name (from head_id)
    // Uses LEFT JOINs to include departments with no employees or no head
    $stmt = $conn->query("SELECT d.*, COUNT(e.employee_id) as employee_count,
                          CONCAT(emp.first_name, ' ', emp.last_name) as head_name
                          FROM departments d 
                          LEFT JOIN employees e ON d.department_id = e.department_id 
                          LEFT JOIN employees emp ON d.head_id = emp.employee_id
                          GROUP BY d.department_id 
                          ORDER BY d.department_name");
    $departments = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Error fetching departments: " . $e->getMessage();
} catch(Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Departments - Shebamiles EMS</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="topbar">
                <h2>Departments</h2>
                <div class="topbar-actions">
                    <?php if (hasRole('admin')): ?>
                    <button onclick="showAddModal()" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus"></i> Add Department
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
                
                <!-- Departments Grid -->
                <div class="stats-grid">
                    <?php if (empty($departments)): ?>
                        <div class="empty-state" style="grid-column: 1 / -1;">
                            <i class="fas fa-building"></i>
                            <h3>No Departments Found</h3>
                            <p>Add your first department to get started</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($departments as $dept): ?>
                        <div class="stat-card">
                            <div class="stat-card-icon">
                                <i class="fas fa-building"></i>
                            </div>
                            <h3><?php echo htmlspecialchars($dept['department_name']); ?></h3>
                            <div class="stat-card-value"><?php echo $dept['employee_count']; ?></div>
                            <p style="color: var(--medium-gray); margin-top: 0.5rem; font-size: 0.9rem;">
                                <?php echo $dept['employee_count'] == 1 ? 'Employee' : 'Employees'; ?>
                            </p>
                            <?php if (hasRole('admin')): ?>
                            <div style="margin-top: 1rem; display: flex; gap: 0.5rem;">
                                <a href="?delete=<?php echo $dept['department_id']; ?>" 
                                   class="btn-icon" 
                                   onclick="return confirm('Are you sure? This will unassign all employees from this department.')"
                                   title="Delete">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Detailed Table -->
                <div class="table-container" style="margin-top: 2rem;">
                    <div class="table-header">
                        <h3>Department Details</h3>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Department Name</th>
                                <th>Description</th>
                                <th>Department Head</th>
                                <th>Employee Count</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($departments)): ?>
                                <tr>
                                    <td colspan="5">
                                        <div class="empty-state">
                                            <i class="fas fa-building"></i>
                                            <h3>No Departments Found</h3>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($departments as $dept): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($dept['department_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($dept['description'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($dept['head_name'] ?? 'Not assigned'); ?></td>
                                    <td>
                                        <span class="badge badge-primary">
                                            <?php echo $dept['employee_count']; ?> 
                                            <?php echo $dept['employee_count'] == 1 ? 'Employee' : 'Employees'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (hasRole('admin')): ?>
                                        <a href="?delete=<?php echo $dept['department_id']; ?>" 
                                           class="btn-icon" 
                                           title="Delete"
                                           onclick="return confirm('Are you sure you want to delete this department?')">
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
    
    <!-- Add Department Modal -->
    <div id="addModal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Department</h3>
                <button class="modal-close" onclick="closeModal('addModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_department">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Department Name *</label>
                        <input type="text" name="department_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="4" 
                                  placeholder="Brief description of the department..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-orange" onclick="closeModal('addModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Department</button>
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
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.style.display = 'none';
            }
        }
    </script>
    
    <?php include __DIR__ . '/../includes/badge.php'; ?>
</body>
</html>
