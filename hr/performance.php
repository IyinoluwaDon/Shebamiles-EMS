<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requirePermission('view_performance');

$db = new Database();
$conn = $db->getConnection();

if ($conn === null) {
    $error = 'Database connection unavailable. Please import database/shebamiles_db.sql.';
}

$success = '';
$error = '';

// Handle form submission for adding performance review
if ($conn !== null && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_review') {
        try {
            $stmt = $conn->prepare("INSERT INTO performance_reviews 
                                   (employee_id, reviewer_id, review_date, review_period_start, review_period_end, 
                                    rating, strengths, areas_for_improvement, goals, comments) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $_POST['employee_id'],
                getCurrentUser()['user_id'],
                $_POST['review_date'],
                $_POST['review_period_start'],
                $_POST['review_period_end'],
                $_POST['rating'],
                sanitize($_POST['strengths']),
                sanitize($_POST['areas_for_improvement']),
                sanitize($_POST['goals']),
                sanitize($_POST['comments'])
            ]);
            
            $success = "Performance review added successfully!";
        } catch(PDOException $e) {
            $error = "Error adding review: " . $e->getMessage();
        }
    }
    
    if ($_POST['action'] === 'update_review') {
        try {
            $stmt = $conn->prepare("UPDATE performance_reviews 
                                   SET rating = ?, strengths = ?, areas_for_improvement = ?, goals = ?, comments = ?
                                   WHERE review_id = ?");
            
            $stmt->execute([
                $_POST['rating'],
                sanitize($_POST['strengths']),
                sanitize($_POST['areas_for_improvement']),
                sanitize($_POST['goals']),
                sanitize($_POST['comments']),
                $_POST['review_id']
            ]);
            
            $success = "Performance review updated successfully!";
        } catch(PDOException $e) {
            $error = "Error updating review: " . $e->getMessage();
        }
    }
}

// Handle delete
if ($conn !== null && isset($_GET['delete'])) {
    try {
        $stmt = $conn->prepare("DELETE FROM performance_reviews WHERE review_id = ?");
        $stmt->execute([$_GET['delete']]);
        $success = "Performance review deleted successfully!";
    } catch(PDOException $e) {
        $error = "Error deleting review: " . $e->getMessage();
    }
}

// Get all performance reviews
try {
    if ($conn === null) {
        throw new Exception('Database connection unavailable. Please import database/shebamiles_db.sql.');
    }
    $search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
    $departmentFilter = isset($_GET['department']) ? $_GET['department'] : '';
    
    $query = "SELECT pr.*, 
              e.employee_code, e.first_name, e.last_name, e.position,
              d.department_name,
              u.username as reviewer_name
              FROM performance_reviews pr
              JOIN employees e ON pr.employee_id = e.employee_id
              LEFT JOIN departments d ON e.department_id = d.department_id
              LEFT JOIN users u ON pr.reviewer_id = u.user_id
              WHERE 1=1";
    
    $params = [];
    
    if ($search) {
        $query .= " AND (e.first_name LIKE ? OR e.last_name LIKE ? OR e.employee_code LIKE ?)";
        $searchParam = "%$search%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
    }
    
    if ($departmentFilter) {
        $query .= " AND e.department_id = ?";
        $params[] = $departmentFilter;
    }
    
    $query .= " ORDER BY pr.review_date DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $reviews = $stmt->fetchAll();
    
    // Get employees for dropdown
    $stmt = $conn->query("SELECT e.employee_id, e.employee_code, e.first_name, e.last_name, e.position, d.department_name 
                         FROM employees e 
                         LEFT JOIN departments d ON e.department_id = d.department_id 
                         ORDER BY e.first_name");
    $employees = $stmt->fetchAll();
    
    // Get departments for filter
    $stmt = $conn->query("SELECT * FROM departments ORDER BY department_name");
    $departments = $stmt->fetchAll();
    
    // Calculate statistics
    $avgRating = !empty($reviews) ? array_sum(array_column($reviews, 'rating')) / count($reviews) : 0;
    $totalReviews = count($reviews);
    $thisMonth = count(array_filter($reviews, fn($r) => date('Y-m', strtotime($r['review_date'])) === date('Y-m')));
    $highPerformers = count(array_filter($reviews, fn($r) => $r['rating'] >= 4.0));
    
} catch(PDOException $e) {
    $error = "Error fetching performance data: " . $e->getMessage();
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
    <title>Performance Reviews - Shebamiles EMS</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="topbar">
                <h2>Performance Reviews</h2>
                <div class="topbar-actions">
                    <button onclick="showAddModal()" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus"></i> Add Review
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
                            <i class="fas fa-star"></i>
                        </div>
                        <h3>Average Rating</h3>
                        <div class="stat-card-value"><?php echo number_format($avgRating, 2); ?>/5.0</div>
                        <p class="stat-card-label">Overall performance</p>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-card-icon" style="background: linear-gradient(135deg, #2196F3, #1565C0);">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <h3>Total Reviews</h3>
                        <div class="stat-card-value"><?php echo $totalReviews; ?></div>
                        <p class="stat-card-label">All time</p>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-card-icon" style="background: linear-gradient(135deg, #FFC107, #F57C00);">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h3>This Month</h3>
                        <div class="stat-card-value"><?php echo $thisMonth; ?></div>
                        <p class="stat-card-label">Reviews conducted</p>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-card-icon" style="background: linear-gradient(135deg, #9C27B0, #6A1B9A);">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <h3>High Performers</h3>
                        <div class="stat-card-value"><?php echo $highPerformers; ?></div>
                        <p class="stat-card-label">Rating ≥ 4.0</p>
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
                                    <select name="department" class="form-control">
                                        <option value="">All Departments</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo $dept['department_id']; ?>" <?php echo $departmentFilter == $dept['department_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($dept['department_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                                <a href="performance.php" class="btn btn-outline-orange">
                                    <i class="fas fa-redo"></i> Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Performance Reviews Table -->
                <div class="card">
                    <div class="card-header">
                        <h3>Performance Reviews</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Position</th>
                                        <th>Department</th>
                                        <th>Review Period</th>
                                        <th>Review Date</th>
                                        <th>Rating</th>
                                        <th>Reviewer</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($reviews)): ?>
                                        <tr>
                                            <td colspan="8" style="text-align: center; padding: 2rem;">
                                                <i class="fas fa-chart-line" style="font-size: 3rem; color: var(--medium-gray); margin-bottom: 1rem;"></i>
                                                <p>No performance reviews found</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($reviews as $review): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?></strong><br>
                                                    <small><?php echo htmlspecialchars($review['employee_code']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($review['position'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($review['department_name'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <small>
                                                        <?php echo date('M d, Y', strtotime($review['review_period_start'])); ?><br>
                                                        to <?php echo date('M d, Y', strtotime($review['review_period_end'])); ?>
                                                    </small>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($review['review_date'])); ?></td>
                                                <td>
                                                    <span class="rating-badge rating-<?php echo $review['rating'] >= 4 ? 'excellent' : ($review['rating'] >= 3 ? 'good' : 'poor'); ?>">
                                                        <i class="fas fa-star"></i> <?php echo number_format($review['rating'], 2); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($review['reviewer_name']); ?></td>
                                                <td class="actions">
                                                        <button
                                                            class="btn-icon btn-info"
                                                            title="View Details"
                                                            data-review='<?php echo htmlspecialchars(json_encode($review), ENT_QUOTES, 'UTF-8'); ?>'
                                                            onclick="viewReview(this)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <a href="?delete=<?php echo $review['review_id']; ?>" 
                                                       onclick="return confirm('Are you sure you want to delete this review?')" 
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
    
    <!-- Add Review Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3>Add Performance Review</h3>
                <button class="modal-close" type="button" onclick="closeModal('addModal')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_review">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Employee <span class="required">*</span></label>
                        <select name="employee_id" class="form-control" required>
                            <option value="">Select Employee</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo $emp['employee_id']; ?>">
                                    <?php echo htmlspecialchars($emp['employee_code'] . ' - ' . $emp['first_name'] . ' ' . $emp['last_name'] . ' (' . ($emp['position'] ?? 'N/A') . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Review Date <span class="required">*</span></label>
                            <input type="date" name="review_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Rating (0-5) <span class="required">*</span></label>
                            <input type="number" name="rating" class="form-control" step="0.01" min="0" max="5" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Review Period Start <span class="required">*</span></label>
                            <input type="date" name="review_period_start" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Review Period End <span class="required">*</span></label>
                            <input type="date" name="review_period_end" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Strengths</label>
                        <textarea name="strengths" class="form-control" rows="3" placeholder="List employee's strengths..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Areas for Improvement</label>
                        <textarea name="areas_for_improvement" class="form-control" rows="3" placeholder="List areas that need improvement..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Goals</label>
                        <textarea name="goals" class="form-control" rows="3" placeholder="Set goals for next review period..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Additional Comments</label>
                        <textarea name="comments" class="form-control" rows="3" placeholder="Any additional comments..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-orange" onclick="closeModal('addModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Review</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- View Review Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3>Performance Review Details</h3>
                <button class="modal-close" type="button" onclick="closeModal('viewModal')">&times;</button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <!-- Content will be populated by JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-orange" onclick="closeModal('viewModal')">Close</button>
            </div>
        </div>
    </div>
    
    <style>
        .rating-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .rating-excellent {
            background: #4CAF50;
            color: white;
        }
        .rating-good {
            background: #FFC107;
            color: #333;
        }
        .rating-poor {
            background: #F44336;
            color: white;
        }
        .modal-large {
            max-width: 800px;
        }
        .review-detail-section {
            margin-bottom: 1.5rem;
        }
        .review-detail-section h4 {
            color: var(--primary-orange);
            margin-bottom: 0.5rem;
            font-size: 1rem;
        }
        .review-detail-section p {
            color: var(--dark-gray);
            line-height: 1.6;
        }
    </style>
    
    <script>
        function showAddModal() {
            document.getElementById('addModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function viewReview(button) {
            const review = JSON.parse(button.getAttribute('data-review'));
            const content = `
                <div class="review-detail-section">
                    <h4><i class="fas fa-user"></i> Employee Information</h4>
                    <p><strong>Name:</strong> ${review.first_name} ${review.last_name} (${review.employee_code})</p>
                    <p><strong>Position:</strong> ${review.position || 'N/A'}</p>
                    <p><strong>Department:</strong> ${review.department_name || 'N/A'}</p>
                </div>
                
                <div class="review-detail-section">
                    <h4><i class="fas fa-calendar"></i> Review Information</h4>
                    <p><strong>Review Date:</strong> ${new Date(review.review_date).toLocaleDateString('en-US', {year: 'numeric', month: 'long', day: 'numeric'})}</p>
                    <p><strong>Review Period:</strong> ${new Date(review.review_period_start).toLocaleDateString()} - ${new Date(review.review_period_end).toLocaleDateString()}</p>
                    <p><strong>Reviewer:</strong> ${review.reviewer_name}</p>
                    <p><strong>Rating:</strong> <span class="rating-badge rating-${review.rating >= 4 ? 'excellent' : (review.rating >= 3 ? 'good' : 'poor')}">
                        <i class="fas fa-star"></i> ${parseFloat(review.rating).toFixed(2)}/5.0
                    </span></p>
                </div>
                
                ${review.strengths ? `
                <div class="review-detail-section">
                    <h4><i class="fas fa-thumbs-up"></i> Strengths</h4>
                    <p>${review.strengths}</p>
                </div>` : ''}
                
                ${review.areas_for_improvement ? `
                <div class="review-detail-section">
                    <h4><i class="fas fa-chart-line"></i> Areas for Improvement</h4>
                    <p>${review.areas_for_improvement}</p>
                </div>` : ''}
                
                ${review.goals ? `
                <div class="review-detail-section">
                    <h4><i class="fas fa-bullseye"></i> Goals</h4>
                    <p>${review.goals}</p>
                </div>` : ''}
                
                ${review.comments ? `
                <div class="review-detail-section">
                    <h4><i class="fas fa-comment"></i> Additional Comments</h4>
                    <p>${review.comments}</p>
                </div>` : ''}
            `;
            
            document.getElementById('viewModalBody').innerHTML = content;
            document.getElementById('viewModal').style.display = 'block';
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
