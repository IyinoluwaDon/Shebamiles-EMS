<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Database connection check
if (!(new Database())->getConnection()) {
    header('Location: ../dashboard.php?db=missing');
    exit();
}

$user_id = $_SESSION['user_id'];
$is_admin = $_SESSION['role'] === 'admin';
$success_msg = '';
$error_msg = '';

// Handle holiday creation (admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_admin && isset($_POST['action']) && $_POST['action'] === 'add_holiday') {
    try {
        $db = new Database();
        
        $holiday_date = $_POST['holiday_date'] ?? '';
        $holiday_name = $_POST['holiday_name'] ?? '';
        $description = $_POST['description'] ?? '';
        $is_recurring = isset($_POST['is_recurring']) ? 1 : 0;
        
        if (empty($holiday_date) || empty($holiday_name)) {
            throw new Exception('Date and name are required');
        }
        
        $query = "INSERT INTO holidays (holiday_date, holiday_name, description, is_recurring, created_at) 
                  VALUES (?, ?, ?, ?, NOW())";
        $stmt = $db->conn->prepare($query);
        $stmt->execute([$holiday_date, $holiday_name, $description, $is_recurring]);
        
        logActivity($user_id, 'CREATE_HOLIDAY', 'holidays', $db->conn->lastInsertId(), 
                   'Added holiday: ' . $holiday_name);
        
        $success_msg = 'Holiday added successfully!';
    } catch (Exception $e) {
        $error_msg = 'Error adding holiday: ' . $e->getMessage();
    }
}

// Handle holiday deletion (admin only)
if (isset($_GET['delete']) && $is_admin) {
    try {
        $db = new Database();
        $query = "DELETE FROM holidays WHERE holiday_id = ?";
        $stmt = $db->conn->prepare($query);
        $stmt->execute([$_GET['delete']]);
        
        logActivity($user_id, 'DELETE_HOLIDAY', 'holidays', $_GET['delete'], 'Deleted holiday');
        
        $success_msg = 'Holiday deleted successfully!';
    } catch (PDOException $e) {
        $error_msg = 'Error deleting holiday: ' . $e->getMessage();
    }
}

// Get holidays
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$holidays = [];

try {
    $db = new Database();
    
    // Get holidays for the selected year (including recurring holidays)
    $query = "SELECT *, holiday_id AS id FROM holidays 
              WHERE YEAR(holiday_date) = ? OR is_recurring = 1
              ORDER BY MONTH(holiday_date), DAY(holiday_date)";
    $stmt = $db->conn->prepare($query);
    $stmt->execute([$year]);
    $holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_msg = 'Failed to load holidays: ' . $e->getMessage();
}

// Get upcoming holidays (next 7)
$upcoming_holidays = getUpcomingHolidays(7);

// Function to get calendar days
function getCalendarDays($month, $year) {
    $firstDay = mktime(0, 0, 0, $month, 1, $year);
    $lastDay = mktime(23, 59, 59, $month + 1, 0, $year);
    
    $daysInMonth = date('t', $firstDay);
    $startingDayOfWeek = date('w', $firstDay);
    
    return [
        'daysInMonth' => $daysInMonth,
        'startingDayOfWeek' => $startingDayOfWeek
    ];
}

// Build holiday lookup
$holidayLookup = [];
foreach ($holidays as $holiday) {
    $date = date('Y-m-d', strtotime($holiday['holiday_date']));
    $holidayLookup[$date] = $holiday;
}

// Days of week
$daysOfWeek = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Holiday Calendar - Shebamiles EMS</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .calendar-controls {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .year-selector {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
        }

        .nav-button {
            background-color: #FF6B35;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .nav-button:hover {
            background-color: #e55a25;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .calendar {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .calendar-title {
            background: linear-gradient(135deg, #FF6B35 0%, #e55a25 100%);
            color: white;
            padding: 15px;
            text-align: center;
            font-weight: 700;
            font-size: 16px;
        }

        .calendar-weekdays {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            background: #f8f8f8;
            border-bottom: 2px solid #eee;
        }

        .weekday {
            padding: 10px;
            text-align: center;
            font-weight: 700;
            color: #666;
            font-size: 12px;
        }

        .calendar-days {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background: #eee;
            padding: 1px;
        }

        .day {
            aspect-ratio: 1;
            background: white;
            padding: 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            font-size: 12px;
            min-height: 80px;
            position: relative;
            overflow: hidden;
        }

        .day.other-month {
            background: #f8f8f8;
            color: #ccc;
        }

        .day.holiday {
            background: linear-gradient(135deg, rgba(255, 107, 53, 0.1) 0%, rgba(255, 107, 53, 0.05) 100%);
            border: 2px solid #FF6B35;
            font-weight: 600;
        }

        .day.today {
            border: 2px solid #4CAF50;
        }

        .day-number {
            font-weight: 700;
            color: #333;
        }

        .day.other-month .day-number {
            color: #ccc;
        }

        .holiday-name {
            font-size: 10px;
            color: #FF6B35;
            margin-top: 3px;
            text-align: center;
            word-break: break-word;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
        }

        .holidays-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }

        .holiday-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #FF6B35;
        }

        .holiday-card h3 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 16px;
        }

        .holiday-meta {
            font-size: 12px;
            color: #999;
            margin-bottom: 10px;
        }

        .holiday-action {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .holiday-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .holiday-btn-delete {
            background-color: #f0f0f0;
            color: #dc3545;
        }

        .holiday-btn-delete:hover {
            background-color: #e0e0e0;
        }

        .add-holiday-btn {
            background-color: #FF6B35;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 700;
            cursor: pointer;
            margin-bottom: 20px;
        }

        .add-holiday-btn:hover {
            background-color: #e55a25;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-overlay {
            background: white;
            border-radius: 8px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
            float: right;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #FF6B35;
            box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.1);
        }

        .form-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-primary {
            background-color: #FF6B35;
            color: white;
        }

        .btn-secondary {
            background-color: #f0f0f0;
            color: #333;
        }

        .upcoming-holidays {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin: 30px 0;
        }

        .upcoming-holidays h3 {
            color: #FF6B35;
            margin-top: 0;
        }

        .upcoming-item {
            padding: 12px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .upcoming-item:last-child {
            border-bottom: none;
        }

        .upcoming-date {
            font-weight: 700;
            color: #FF6B35;
        }

        @media (max-width: 768px) {
            .calendar-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .calendar-grid {
                grid-template-columns: 1fr;
            }

            .day {
                min-height: 60px;
                font-size: 11px;
            }

            .holiday-name {
                font-size: 9px;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <?php include __DIR__ . '/../includes/badge.php'; ?>

    <div class="main-content">
        <div class="header">
            <h1>📅 Holiday Calendar</h1>
            <p>View and manage company holidays and special days</p>
        </div>

        <?php if ($success_msg): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_msg); ?></div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <!-- Year Selector & Controls -->
        <div class="calendar-header">
            <h2><?php echo $year; ?> Holiday Calendar</h2>
            <div class="calendar-controls">
                <a href="?year=<?php echo $year - 1; ?>" class="nav-button">← Previous Year</a>
                <select class="year-selector" onchange="window.location='?year=' + this.value">
                    <?php for ($y = date('Y') - 2; $y <= date('Y') + 5; $y++): ?>
                        <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
                <a href="?year=<?php echo $year + 1; ?>" class="nav-button">Next Year →</a>
                <?php if ($is_admin): ?>
                    <button class="add-holiday-btn" onclick="openAddModal()">➕ Add Holiday</button>
                <?php endif; ?>
            </div>
        </div>

        <div class="calendar-grid">
            <?php for ($month = 1; $month <= 12; $month++): 
                $cal = getCalendarDays($month, $year);
                $daysInMonth = $cal['daysInMonth'];
                $startingDay = $cal['startingDayOfWeek'];
                $monthName = date('F', mktime(0, 0, 0, $month, 1));
            ?>
                <div class="calendar">
                    <div class="calendar-title"><?php echo $monthName; ?> <?php echo $year; ?></div>
                    <div class="calendar-weekdays">
                        <?php foreach ($daysOfWeek as $day): ?>
                            <div class="weekday"><?php echo $day; ?></div>
                        <?php endforeach; ?>
                    </div>
                    <div class="calendar-days">
                        <?php
                        // Print empty cells for days before the first day of month
                        for ($i = 0; $i < $startingDay; $i++) {
                            echo '<div class="day other-month"></div>';
                        }
                        
                        // Print days of the month
                        for ($day = 1; $day <= $daysInMonth; $day++) {
                            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
                            $isHoliday = isset($holidayLookup[$dateStr]);
                            $isToday = $dateStr === date('Y-m-d');
                            
                            $classes = 'day';
                            if ($isHoliday) $classes .= ' holiday';
                            if ($isToday) $classes .= ' today';
                            
                            echo '<div class="' . $classes . '">';
                            echo '<div class="day-number">' . $day . '</div>';
                            if ($isHoliday) {
                                echo '<div class="holiday-name">' . substr(htmlspecialchars($holidayLookup[$dateStr]['holiday_name']), 0, 15) . '</div>';
                            }
                            echo '</div>';
                        }
                        
                        // Print empty cells for days after the last day of month
                        $totalCells = $startingDay + $daysInMonth;
                        $remainingCells = 42 - $totalCells; // 6 rows * 7 days
                        for ($i = 0; $i < $remainingCells; $i++) {
                            echo '<div class="day other-month"></div>';
                        }
                        ?>
                    </div>
                </div>
            <?php endfor; ?>
        </div>

        <!-- Upcoming Holidays -->
        <?php if (!empty($upcoming_holidays)): ?>
            <div class="upcoming-holidays">
                <h3>🔔 Upcoming Holidays (Next 7 Days)</h3>
                <?php foreach ($upcoming_holidays as $holiday): ?>
                    <div class="upcoming-item">
                        <div>
                            <strong><?php echo htmlspecialchars($holiday['holiday_name']); ?></strong>
                            <div style="font-size: 12px; color: #999;">
                                <?php echo htmlspecialchars($holiday['description'] ?? 'Company holiday'); ?>
                            </div>
                        </div>
                        <span class="upcoming-date">
                            <?php echo date('M d, Y', strtotime($holiday['holiday_date'])); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- All Holidays List (for viewing/editing) -->
        <div class="upcoming-holidays">
            <h3>📋 All Holidays for <?php echo $year; ?></h3>
            <?php if (empty($holidays)): ?>
                <p>No holidays scheduled for this year.</p>
            <?php else: ?>
                <div class="holidays-list">
                    <?php foreach ($holidays as $holiday): 
                        if (date('Y', strtotime($holiday['holiday_date'])) == $year || $holiday['is_recurring']):
                    ?>
                        <div class="holiday-card">
                            <h3><?php echo htmlspecialchars($holiday['holiday_name']); ?></h3>
                            <div class="holiday-meta">
                                📅 <?php echo date('F d, Y', strtotime($holiday['holiday_date'])); ?>
                                <?php if ($holiday['is_recurring']): ?>
                                    <br>🔄 Recurring Holiday
                                <?php endif; ?>
                            </div>
                            <?php if ($holiday['description']): ?>
                                <p style="margin: 10px 0; font-size: 13px; color: #666;">
                                    <?php echo htmlspecialchars($holiday['description']); ?>
                                </p>
                            <?php endif; ?>
                            <?php if ($is_admin): ?>
                                <div class="holiday-action">
                                    <a href="?delete=<?php echo $holiday['id']; ?>" 
                                       onclick="return confirm('Delete this holiday?');"
                                       class="holiday-btn holiday-btn-delete">🗑️ Delete</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Holiday Modal (Admin Only) -->
    <?php if ($is_admin): ?>
        <div class="modal" id="addModal">
            <div class="modal-overlay">
                <button class="modal-close" onclick="closeAddModal()">✕</button>
                <h2 style="color: #FF6B35; margin-top: 0;">Add Holiday</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="add_holiday">
                    
                    <div class="form-group">
                        <label for="holiday_date">Date *</label>
                        <input type="date" id="holiday_date" name="holiday_date" required>
                    </div>

                    <div class="form-group">
                        <label for="holiday_name">Holiday Name *</label>
                        <input type="text" id="holiday_name" name="holiday_name" 
                               placeholder="e.g., New Year's Day" required>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" 
                                  placeholder="Optional description"></textarea>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_recurring" value="1">
                            This is a recurring holiday (same date every year)
                        </label>
                    </div>

                    <div class="form-buttons">
                        <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">✅ Add Holiday</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            function openAddModal() {
                document.getElementById('addModal').classList.add('show');
            }

            function closeAddModal() {
                document.getElementById('addModal').classList.remove('show');
            }

            document.getElementById('addModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeAddModal();
                }
            });
        </script>
    <?php endif; ?>
</body>
</html>
