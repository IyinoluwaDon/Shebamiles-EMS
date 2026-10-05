# Shebamiles EMS - System Architecture

## Project Overview

Shebamiles Employee Management System (EMS) is a comprehensive web-based employee management platform built with PHP, MySQL, HTML, CSS, and JavaScript. It provides organizations with tools to manage employees, track attendance, process payroll, and manage HR operations through a responsive, role-based interface.

## Technology Stack

| Component | Technology |
|-----------|-----------|
| **Backend** | PHP 7.4+ |
| **Database** | MySQL 5.7+ |
| **Frontend** | HTML5, CSS3, JavaScript (ES6+) |
| **Architecture** | MVC (Model-View-Controller) Pattern |
| **Database Access** | PDO (PHP Data Objects) with Prepared Statements |
| **Authentication** | Session-based with Password Hashing |
| **UI Framework** | Custom CSS with Bootstrap-like components |

## Directory Structure

```
shebamiles-ems/
├── css/                          # Stylesheets
│   └── style.css                 # Main stylesheet (2000+ lines)
├── database/                     # Database files
│   ├── shebamiles_db.sql         # Initial database schema
│   └── upgrade_schema.sql        # Schema upgrade migrations
├── includes/                     # Core PHP libraries & utilities
│   ├── auth.php                  # Authentication functions & session management
│   ├── config.php                # Database configuration, PDO connection, BASE_URL
│   ├── helpers.php               # Helper functions (settings, logging, etc.)
│   ├── badge.php                 # Shebamiles branding badge component
│   ├── notification-header.php   # Notification display component
│   └── sidebar.php               # Navigation sidebar component
├── index.php                     # Redirects to login
├── login.php                     # User login page
├── dashboard.php                 # Main dashboard with analytics
├── hr/
│   ├── employees.php             # Employee list & CRUD
│   ├── employee-details.php      # Individual employee profile
│   ├── departments.php           # Department management
│   ├── payroll.php               # Payroll processing & records
│   ├── performance.php           # Performance reviews
│   └── documents.php             # Document management
├── attendance/
│   ├── attendance.php            # Attendance tracking & marking
│   ├── leaves.php                # Leave request management
│   └── holiday-calendar.php      # Holiday management
├── communication/
│   ├── announcements.php         # Company announcements
│   └── notifications.php         # Notification center
├── admin/
│   ├── users.php                 # User account management (Admin)
│   ├── settings.php              # System configuration
│   └── activity-log.php          # System activity logging
├── account/
│   ├── profile.php               # User profile management
│   └── logout.php                # Logout handler
├── README.md                     # Project overview & installation
└── docs/                         # ARCHITECTURE, DATABASE, RBAC_QUICK_REFERENCE
```

## Application Flow

### 1. Request Lifecycle

```
HTTP Request
    ↓
[Router Detection]
    ↓
[Session Check via auth.php]
    ↓
[Permission Verification]
    ↓
[Database Query via PDO]
    ↓
[Response Generation]
    ↓
HTTP Response
```

### 2. Authentication Flow

```
User Login Form
    ↓
[Sanitize Input] → sanitize() in auth.php
    ↓
[Verify Credentials] → login() function
    ├─ Query users table
    ├─ password_verify() against hash
    └─ Create session variables
    ↓
[Update Last Login]
    ↓
Redirect to Dashboard
```

### 3. Data Access Pattern

All data access uses the Database class and PDO:

```
Request
    ↓
$db = new Database()
    ↓
$conn = $db->getConnection()
    ↓
$stmt = $conn->prepare("SELECT...")
    ↓
$stmt->execute([$param1, $param2])
    ↓
$result = $stmt->fetch() / fetchAll()
    ↓
Response
```

## Core Components

### 1. Database Class (config.php)

**Purpose**: Manages database connections using PDO

**Key Features**:
- Singleton pattern (private constructor)
- UTF-8 character set support
- PDO exception handling
- Error suppression in production

**Usage**:
```php
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
```

### 2. Authentication System (auth.php)

**Purpose**: Manages user authentication and authorization

**Key Functions**:
- `isLoggedIn()` - Check if user is authenticated
- `hasRole($role)` - Verify user role (admin, manager, employee)
- `requireLogin()` - Redirect non-authenticated users
- `requireAdmin()` - Restrict to admin users
- `login($username, $password)` - Authenticate user
- `logout()` - End session
- `getCurrentUser()` - Get current session user data
- `sanitize($input)` - Input validation

**Session Variables Set**:
```
$_SESSION['user_id']       - User ID
$_SESSION['username']      - Username
$_SESSION['email']         - Email address
$_SESSION['role']          - User role (admin/manager/employee)
$_SESSION['employee_id']   - Associated employee ID
$_SESSION['full_name']     - Full name
```

### 3. Helper Functions (helpers.php)

**Purpose**: Business logic and utility functions

**Key Categories**:

#### Settings Management
- `getSetting($key, $default)` - Retrieve company settings with caching
- `updateSetting($key, $value, $userId)` - Update company settings
- `getSettingsByGroup($group)` - Get grouped settings

#### Activity Logging
- `logActivity($userId, $action, $entityType, $entityId, $description)` - Log user actions
- `getRecentActivity($limit, $userId)` - Retrieve activity logs

#### Holiday Management
- `isHoliday($date)` - Check if date is a holiday
- `getUpcomingHolidays($limit)` - Get upcoming holidays

#### Permissions
- `hasPermission($permission)` - Check user permission
- `getAllPermissions()` - Get all system permissions
- `getPermissionsByRole($role)` - Get role-specific permissions

### 4. Badge Component (badge.php)

**Purpose**: Shebamiles branding badge displayed on every page

**Features**:
- Fixed position bottom-right corner
- Orange/white color scheme
- Smooth animations
- CSS-based styling
- Accessibility compliant

## Design Patterns Used

### 1. MVC Pattern
- **Model**: Database classes and helper functions
- **View**: PHP pages with HTML/CSS/JS
- **Controller**: Page logic and request handlers

### 2. Singleton Pattern
- Database class maintains single connection instance

### 3. Factory Pattern
- Config creates database connections on demand

### 4. Middleware Pattern
- Auth functions (requireLogin, requireAdmin) act as middleware
- Permission checks on protected pages

## File Naming Conventions

| Pattern | Purpose | Examples |
|---------|---------|----------|
| `[entity].php` | Main CRUD pages | employees.php, departments.php |
| `[entity]-details.php` | Individual record view | employee-details.php |
| `[noun-verb].php` | Specific actions | activity-log.php |
| `includes/[name].php` | Library files | auth.php, config.php |
| `php/[action].php` | Action handlers | logout.php |
| `[NAME].md` | Documentation | README.md, DATABASE.md |

## Security Implementations

### 1. SQL Injection Prevention
- All queries use prepared statements with parameter binding
- PDO automatic escaping

### 2. Password Security
- `password_hash()` for storage
- `password_verify()` for authentication
- No plaintext passwords

### 3. Input Validation
- `sanitize()` function removes dangerous characters
- `htmlspecialchars()` for output encoding
- Type casting where appropriate

### 4. Session Management
- Session-based authentication
- Session timeout handling
- Secure session cookies

### 5. Role-Based Access Control (RBAC)
- Multiple roles: admin, manager, employee
- Permission-based access control
- Configurable permissions per role

## Performance Optimizations

### 1. Database
- Indexed key columns
- Query optimization in complex pages
- Pagination for large result sets

### 2. Settings Caching
- `getSetting()` uses static variable caching
- Prevents repeated database queries

### 3. Frontend
- CSS and JS bundled efficiently
- Minimal external dependencies
- Local resource loading

## Error Handling

### 1. Database Errors
- Try-catch blocks around all database operations
- PDOException handling
- User-friendly error messages

### 2. Authentication Errors
- Invalid login feedback
- Database connection failures
- Missing required fields

### 3. Authorization Errors
- Role-based redirect logic
- Permission violation handling

## Integration Points

### Browser APIs Used
- LocalStorage (if any)
- Fetch API (if any)
- DOM manipulation APIs

### Third-Party Libraries
- Font Awesome icons (CDN)
- Chart.js (dashboard charts)
- Bootstrap styling (custom implementation)

## Deployment Considerations

### Required Environment
- PHP 7.4+ with PDO extension
- MySQL 5.7+ database
- Web server (Apache/Nginx)
- Write permissions for session files

### Configuration
- Database credentials in `includes/config.php`
- Server name and paths may need adjustment
- Session settings in `php.ini`

### Database Setup
1. Import `database/shebamiles_db.sql`
2. Create necessary indexes
3. Set up automatic backups

### Performance at Scale
- Consider query optimization for >10,000 employees
- Implement pagination throughout
- Archive old activity logs
- Monitor database performance

## Future Architecture Improvements

1. **API Layer**: RESTful API for mobile apps
2. **Caching**: Redis for performance
3. **Queue System**: Background jobs for batch operations
4. **Microservices**: Separate concerns into services
5. **GraphQL**: Alternative to REST API
6. **Testing Framework**: PHPUnit for unit tests
7. **CI/CD Pipeline**: Automated deployment

---

**Last Updated**: February 2026  
**Version**: 1.0  
**Maintainer**: Shebamiles Development Team
