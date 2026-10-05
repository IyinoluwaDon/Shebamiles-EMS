# Shebamiles EMS - Database Schema Documentation

## Database Overview

The Shebamiles EMS uses a relational database design with MySQL 5.7+. The schema is organized around core HR entities: employees, users, departments, and various HR operations like attendance, payroll, and performance.

## Core Entity-Relationship Diagram

```
┌─────────────────┐         ┌──────────────────┐
│    users        │◄────────│   employees      │
│  (accounts)     │         │  (staff info)    │
└─────────────────┘         └──────────────────┤
                                     │
                    ┌────────────────┼────────────────┐
                    │                │                │
              ┌─────▼────────┐ ┌──────▼────┐ ┌──────▼──────┐
              │ attendance   │ │payroll    │ │performance │
              │ (daily logs) │ │(payments) │ │(reviews)   │
              └──────────────┘ └───────────┘ └────────────┘
                    │
          ┌─────────┴─────────┐
          │                   │
    ┌─────▼──────┐     ┌──────▼──────┐
    │leave_req   │     │holidays     │
    │(time off)  │     │(company)    │
    └────────────┘     └─────────────┘

Additional Tables:
├── departments      (organizational structure)
├── notifications    (alerts & messages)
├── announcements    (company updates)
├── documents        (file storage metadata)
├── activity_log     (audit trail)
└── company_settings (configuration)
```

## Table Definitions

### 1. users

**Purpose**: User accounts and authentication

```sql
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,              -- bcrypt hash
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM('admin', 'manager', 'employee'),
    status ENUM('active', 'inactive'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id)
);
```

**Key Constraints**:
- `username`: Must be unique (prevents duplicate logins)
- `email`: Must be unique (for password resets)
- `role`: Controls access permissions
- `password`: Stored as bcrypt hash ($2y$10$...)

**Indexes**:
- PRIMARY KEY: user_id (fast lookups)
- UNIQUE: username, email (authentication)

### 2. employees

**Purpose**: Employee information and personal details

```sql
CREATE TABLE employees (
    employee_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,                                  -- Links to user account
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE,
    phone VARCHAR(20),
    department_id INT,                            -- Links to department
    position VARCHAR(100),
    hire_date DATE,
    salary DECIMAL(10, 2),
    status ENUM('active', 'inactive'),
    date_of_birth DATE,
    gender ENUM('Male', 'Female', 'Other'),
    address VARCHAR(255),
    city VARCHAR(100),
    state VARCHAR(100),
    postal_code VARCHAR(20),
    country VARCHAR(100),
    emergency_contact VARCHAR(100),
    emergency_phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (department_id) REFERENCES departments(department_id)
);
```

**Key Fields**:
- `user_id`: One-to-one relationship with users table
- `department_id`: Many-to-one relationship with departments
- `email` & `phone`: Can be same or different from user email
- `salary`: Basis for payroll calculations

**Indexes**:
- PRIMARY KEY: employee_id
- FOREIGN KEY: user_id, department_id
- UNIQUE: email (for employee lookup)

### 3. departments

**Purpose**: Organizational structure

```sql
CREATE TABLE departments (
    department_id INT PRIMARY KEY AUTO_INCREMENT,
    department_name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    manager_id INT,                               -- Manager employee ID
    budget DECIMAL(12, 2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (manager_id) REFERENCES employees(employee_id)
);
```

**Structure**:
- Each employee belongs to one department
- Each department has one manager (optional)
- Supports hierarchical organization

**Relationships**:
```
1 Department : Many Employees (1:N)
1 Employee : 1 Department
```

### 4. attendance

**Purpose**: Daily attendance tracking and status records

```sql
CREATE TABLE attendance (
    attendance_id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    date DATE NOT NULL,
    status ENUM('present', 'absent', 'leave', 'half-day') DEFAULT 'absent',
    check_in TIME,
    check_out TIME,
    notes TEXT,
    marked_by INT,                                -- Admin/Manager who marked
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id),
    FOREIGN KEY (marked_by) REFERENCES users(user_id),
    UNIQUE KEY unique_attendance (employee_id, date)  -- One record per employee per day
);
```

**Key Features**:
- Tracks presence/absence for each employee daily
- Check-in/check-out times optional
- Multiple status types for different scenarios
- Prevents duplicate entries (UNIQUE constraint)

**Common Queries**:
```sql
-- Attendance for date range
SELECT * FROM attendance WHERE employee_id = ? AND date BETWEEN ? AND ?;

-- Present count by date
SELECT COUNT(*) as present FROM attendance WHERE date = CURDATE() AND status = 'present';

-- Monthly attendance report
SELECT employee_id, COUNT(*) as present_days FROM attendance 
WHERE date BETWEEN ? AND ? AND status = 'present' GROUP BY employee_id;
```

### 5. leave_requests

**Purpose**: Employee time-off requests and approvals

```sql
CREATE TABLE leave_requests (
    leave_id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    leave_type ENUM('vacation', 'sick', 'personal', 'unpaid'),
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    days_requested INT,
    reason TEXT,
    status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    approved_by INT,                              -- Manager/Admin who approved
    approval_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id),
    FOREIGN KEY (approved_by) REFERENCES users(user_id),
    INDEX (employee_id, status),                  -- Fast status lookups
    INDEX (start_date, end_date)                  -- Date range queries
);
```

**Workflow**:
```
Employee submits request
        ↓
status = 'pending'
        ↓
Manager reviews
        ↓
Approve: status = 'approved', approved_by = manager_id
    OR
Reject: status = 'rejected', approval_notes = reason
        ↓
Auto-mark attendance as 'leave' if approved
```

**Validation Rules**:
- `end_date >= start_date`
- `days_requested = (end_date - start_date) + 1`
- Check leave balance before approval
- Prevent overlapping requests

### 6. payroll

**Purpose**: Salary payments and payroll records

```sql
CREATE TABLE payroll (
    payroll_id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    pay_period_start DATE NOT NULL,
    pay_period_end DATE NOT NULL,
    base_salary DECIMAL(10, 2),
    bonuses DECIMAL(10, 2) DEFAULT 0,
    deductions DECIMAL(10, 2) DEFAULT 0,
    taxes DECIMAL(10, 2) DEFAULT 0,
    gross_pay DECIMAL(10, 2),                     -- base + bonuses
    net_pay DECIMAL(10, 2),                       -- gross - deductions - taxes
    payment_date DATE,
    payment_method ENUM('bank_transfer', 'check', 'cash'),
    status ENUM('pending', 'processed', 'paid'),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id),
    INDEX (employee_id, pay_period_start)         -- Monthly payroll queries
);
```

**Calculation Logic**:
```
gross_pay = base_salary + bonuses
net_pay = gross_pay - deductions - taxes
```

**Common Queries**:
```sql
-- Monthly payroll for processing
SELECT * FROM payroll WHERE pay_period_start = ? AND status = 'pending';

-- Employee salary history
SELECT * FROM payroll WHERE employee_id = ? ORDER BY payment_date DESC;

-- Total payroll cost
SELECT SUM(net_pay) as total_payroll FROM payroll WHERE pay_period_start = ?;
```

### 7. performance_reviews

**Purpose**: Employee performance evaluations

```sql
CREATE TABLE performance_reviews (
    review_id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    reviewer_id INT,                              -- Manager/Admin conducting review
    review_date DATE,
    rating DECIMAL(3, 2),                         -- 1.0 to 5.0
    feedback TEXT,
    goals TEXT,                                   -- Performance goals for next period
    strengths TEXT,
    areas_for_improvement TEXT,
    status ENUM('draft', 'submitted', 'acknowledged') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id),
    FOREIGN KEY (reviewer_id) REFERENCES users(user_id),
    INDEX (employee_id, review_date DESC)         -- Employee review history
);
```

**Rating Scale**:
- 1.0-1.9: Unsatisfactory
- 2.0-2.9: Needs Improvement
- 3.0-3.9: Meets Expectations
- 4.0-4.9: Exceeds Expectations
- 5.0: Outstanding

### 8. holidays

**Purpose**: Company-wide holidays and non-working days

```sql
CREATE TABLE holidays (
    holiday_id INT PRIMARY KEY AUTO_INCREMENT,
    holiday_name VARCHAR(100) NOT NULL,
    holiday_date DATE UNIQUE NOT NULL,
    description TEXT,
    is_paid BOOLEAN DEFAULT TRUE,                 -- Whether employees are paid
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (holiday_date)                          -- Fast holiday lookups
);
```

**Usage**:
- Check if attendance date is a holiday
- Calculate working days (exclude weekends + holidays)
- Prevent leave requests on holidays

### 9. notifications

**Purpose**: User notifications and alerts

```sql
CREATE TABLE notifications (
    notification_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT,
    type ENUM('info', 'warning', 'success', 'error'),
    is_read BOOLEAN DEFAULT FALSE,
    action_url VARCHAR(255),                      -- Link to related page
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    INDEX (user_id, is_read)                      -- Unread notifications
);
```

**Generation Events**:
- Leave request submitted
- Leave request approved/rejected
- Performance review due
- Payroll processed
- Attendance marked
- Documents uploaded

### 10. announcements

**Purpose**: Company-wide announcements and news

```sql
CREATE TABLE announcements (
    announcement_id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    author_id INT,                                -- User who posted
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expiry_date DATE,                             -- When to stop showing
    is_active BOOLEAN DEFAULT TRUE,
    views INT DEFAULT 0,                          -- Tracking reads
    FOREIGN KEY (author_id) REFERENCES users(user_id)
);
```

### 11. documents

**Purpose**: Document and file storage metadata

```sql
CREATE TABLE documents (
    document_id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT,                              -- If document belongs to employee
    title VARCHAR(255) NOT NULL,
    file_path VARCHAR(500),                       -- Server file location
    file_type VARCHAR(50),                        -- pdf, doc, xlsx, etc.
    file_size INT,                                -- Size in bytes
    uploaded_by INT,                              -- User who uploaded
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id),
    FOREIGN KEY (uploaded_by) REFERENCES users(user_id)
);
```

### 12. activity_log

**Purpose**: Audit trail of system actions

```sql
CREATE TABLE activity_log (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100),                          -- create, update, delete, view
    entity_type VARCHAR(50),                      -- employee, attendance, payroll, etc.
    entity_id INT,                                -- ID of affected entity
    description TEXT,
    ip_address VARCHAR(45),                       -- IPv4 or IPv6
    user_agent TEXT,                              -- Browser/client info
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    INDEX (user_id, created_at DESC),             -- User activity
    INDEX (action, entity_type),                  -- Action tracking
    INDEX (created_at DESC)                       -- Timeline queries
);
```

**Common Filters**:
```sql
-- All actions by user
SELECT * FROM activity_log WHERE user_id = ? ORDER BY created_at DESC;

-- All changes to specific employee
SELECT * FROM activity_log WHERE entity_type = 'employee' AND entity_id = ?;

-- Admin actions only
SELECT al.* FROM activity_log al
JOIN users u ON al.user_id = u.user_id
WHERE u.role = 'admin' ORDER BY al.created_at DESC;
```

### 13. company_settings

**Purpose**: System configuration and preferences

```sql
CREATE TABLE company_settings (
    setting_id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_group VARCHAR(50),                    -- general, email, notification, etc.
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(user_id)
);
```

**Common Settings**:
```
company_name           → Organization name
company_logo           → Logo file path
financial_year_start   → FY start date
working_days           → Days per week (e.g., 5)
leave_year_start       → Reset date for annual leave
notification_enabled   → Global notifications on/off
timezone               → Server timezone
```

**Access Pattern**:
```php
// Get with caching
$companyName = getSetting('company_name', 'Shebamiles');

// Update setting
updateSetting('company_name', 'New Company Name', $adminId);
```

## Relationships Summary

| From | To | Relationship | Type |
|------|-----|-------------|------|
| users | employees | one user → one employee | 1:1 |
| employees | departments | many employees → one department | N:1 |
| employees | attendance | one employee → many attendance records | 1:N |
| employees | leave_requests | one employee → many requests | 1:N |
| employees | payroll | one employee → many payroll records | 1:N |
| employees | performance_reviews | one employee → many reviews | 1:N |
| users | activity_log | one user → many activity records | 1:N |
| users | notifications | one user → many notifications | 1:N |

## Indexing Strategy

### Primary Indexes (Default with PRIMARY KEY)
- users.user_id
- employees.employee_id
- departments.department_id
- All tables (for fast direct lookup)

### Foreign Key Indexes (Auto-created)
- performance_reviews.employee_id
- performance_reviews.reviewer_id
- payroll.employee_id
- leave_requests.employee_id

### Query Optimization Indexes
```sql
-- Attendance lookups
INDEX idx_attendance_date (date)
INDEX idx_attendance_employee_date (employee_id, date)

-- Leave requests by status
INDEX idx_leave_status (status)
INDEX idx_leave_emp_status (employee_id, status)

-- Activity log searches
INDEX idx_activity_user_date (user_id, created_at DESC)
INDEX idx_activity_action (action, entity_type)

-- Holiday checks
INDEX idx_holiday_date (holiday_date)

-- Notifications
INDEX idx_notif_user_read (user_id, is_read)
```

## Data Integrity Constraints

### Unique Constraints
- `users.username` - Prevent duplicate logins
- `users.email` - Prevent duplicate emails
- `employees.email` - Employee email uniqueness
- `departments.department_name` - Department name uniqueness
- `holidays.holiday_date` - One holiday per date
- `attendance.employee_id + date` - One record per employee per day

### Foreign Key Constraints
- All employee references enforce referential integrity
- Cascading deletes where appropriate (e.g., delete employee → delete attendance)
- ON DELETE CASCADE/RESTRICT per relationship

### Check Constraints
- salary >= 0
- rating BETWEEN 1.0 AND 5.0 (where applicable)
- end_date >= start_date (for date ranges)

## Common Query Patterns

### 1. User Authentication
```sql
SELECT u.*, e.employee_id, e.first_name, e.last_name 
FROM users u 
LEFT JOIN employees e ON u.user_id = e.user_id 
WHERE u.username = ? AND u.status = 'active';
```

### 2. Employee Directory
```sql
SELECT e.*, d.department_name, u.role
FROM employees e
LEFT JOIN departments d ON e.department_id = d.department_id
LEFT JOIN users u ON e.user_id = u.user_id
WHERE e.status = 'active'
ORDER BY e.first_name, e.last_name;
```

### 3. Department Statistics
```sql
SELECT 
    d.department_id,
    d.department_name,
    COUNT(e.employee_id) as employee_count,
    AVG(e.salary) as avg_salary
FROM departments d
LEFT JOIN employees e ON d.department_id = e.department_id
GROUP BY d.department_id;
```

### 4. Attendance Summary
```sql
SELECT 
    DATE(date) as attendance_date,
    status,
    COUNT(*) as count
FROM attendance
WHERE date BETWEEN ? AND ?
GROUP BY DATE(date), status;
```

### 5. Leave Balance (Manual Calculation)
```sql
SELECT 
    lr.employee_id,
    COUNT(CASE WHEN lr.status = 'approved' THEN 1 END) as approved_days,
    COUNT(CASE WHEN lr.status = 'pending' THEN 1 END) as pending_days
FROM leave_requests lr
WHERE YEAR(lr.start_date) = YEAR(CURDATE())
GROUP BY lr.employee_id;
```

## Database Backup Strategy

### Recommended Approach
1. **Daily backups** of database (automated)
2. **Weekly full exports** (schema + data)
3. **Monthly archives** to external storage
4. **Transaction logs** for point-in-time recovery

### Backup Query
```sql
-- Export for backup
mysqldump -u root -p shebamiles_ems_new > backup_YYYY-MM-DD.sql

-- Restore from backup
mysql -u root -p shebamiles_ems_new < backup_YYYY-MM-DD.sql
```

## Database Maintenance

### Index Optimization
```sql
-- Analyze table statistics
ANALYZE TABLE users;
ANALYZE TABLE employees;
ANALYZE TABLE attendance;

-- Rebuild fragmented indexes (MySQL 5 compatible)
REBUILD INDEX...  -- or
OPTIMIZE TABLE attendance;
```

### Data Cleanup
```sql
-- Archive old activity logs (older than 1 year)
DELETE FROM activity_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR);

-- Remove old notifications
DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 3 MONTH);
```

---

**Last Updated**: February 2026  
**Database Version**: 1.0  
**MySQL Compatibility**: 5.7+
