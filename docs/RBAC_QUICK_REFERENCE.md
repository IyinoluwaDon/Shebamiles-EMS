# RBAC Quick Reference Guide

## For Developers - How to Use the Permission System

### 1. Check Permission Before Page Access
```php
<?php
require_once 'includes/auth.php';
requireLogin();                              // Ensure user is logged in
requirePermission('view_xxx');              // Ensure user has specific permission
```

**Result:** If user lacks permission, automatically redirects to 403 Access Denied page.

---

### 2. Conditionally Show UI Elements
```php
<?php if (hasPermission('create_employee')): ?>
    <button onclick="openAddForm()">Add Employee</button>
<?php endif; ?>
```

**Result:** Button only appears if user has `create_employee` permission.

---

### 3. Filter Data by User Role
```php
$query = "SELECT * FROM employees WHERE 1=1";

// Non-admins see only their own records
if (!hasPermission('view_all_employees')) {
    $query .= " AND employee_id = ?";
    $params[] = $_SESSION['user_id'];
}

$stmt = $conn->prepare($query);
$stmt->execute($params);
```

**Result:** Employees see only their data, admins see all.

---

### 4. Check Multiple Conditions
```php
if (hasPermission('approve_leave') && hasPermission('reject_leave')) {
    // User can both approve and reject leaves
}

if (hasPermission('view_payroll') || hasPermission('view_performance')) {
    // User has access to at least one admin feature
}
```

---

## Available Permissions Reference

### Dashboard & Analytics
- `view_dashboard` - Access to dashboard page
- `view_analytics` - See analytics charts and statistics (admin only)

### Employee Management
- `view_employees` - See employee list
- `create_employee` - Add new employees
- `edit_employee` - Modify employee data
- `delete_employee` - Remove employees
- `view_employee_details` - View detailed employee profiles (admin only)

### Departments
- `view_departments` - See all departments
- `create_department` - Add new departments (admin only)
- `edit_department` - Modify departments (admin only)
- `delete_department` - Remove departments (admin only)

### Attendance
- `view_attendance` - Access attendance page
- `manage_attendance` - Mark attendance for employees (admin only)
- `view_all_attendance` - See all employees' attendance (admin only)

### Leave Management
- `view_leaves` - Access leave requests page
- `approve_leave` - Approve leave requests (admin only)
- `reject_leave` - Reject leave requests (admin only)
- `view_all_leaves` - See all employees' leaves (admin only)

### Payroll (Admin Only)
- `view_payroll` - Access payroll page
- `create_payroll` - Create salary records
- `edit_payroll` - Modify salary records
- `delete_payroll` - Remove salary records

### Performance (Admin Only)
- `view_performance` - Access performance reviews
- `create_performance` - Create performance records
- `edit_performance` - Modify performance records

### User Management (Admin Only)
- `view_users` - Manage system users
- `create_user` - Add new users
- `edit_user` - Modify user accounts
- `delete_user` - Remove users
- `manage_roles` - Change user roles

### System Administration
- `view_settings` - Access system settings (admin only)
- `edit_settings` - Modify system configuration (admin only)
- `view_activity_log` - Review activity logs (admin only)
- `view_audit_trail` - Access audit information (admin only)

### Documents
- `view_documents` - Access documents page
- `upload_document` - Upload files
- `delete_document` - Delete own documents (admin can delete any)
- `view_all_documents` - See all documents (admin only)
- `delete_others_documents` - Delete others' documents (admin only)

### Announcements
- `view_announcements` - See announcements
- `create_announcement` - Post announcements (admin only)
- `edit_announcement` - Modify announcements (admin only)
- `delete_announcement` - Remove announcements (admin only)

### Holiday Calendar
- `view_holidays` - View holiday calendar
- `manage_holidays` - Add/remove holidays (admin only)

### Notifications
- `view_notifications` - Access notifications
- `manage_notifications` - Manage notification settings (admin only)

---

## Role Mapping

### Admin Role
Permissions: 42 permissions - **FULL ACCESS**
- Access all pages
- See all company data
- Perform all actions
- Manage system configuration

### Employee Role  
Permissions: 42 permissions - **LIMITED/OWN DATA ONLY**
- Access: Dashboard, Employees (list), Attendance (own), Leaves (own), Documents (own), Announcements, Holidays
- Cannot access: Payroll, Performance, Users, Settings, Activity Log
- See only own records
- Cannot approve/reject requests
- Cannot delete data

---

## Common Permission Checks

### "Is this an admin user?"
```php
if (hasPermission('view_activity_log')) {
    // This is an admin (activity log is admin-only)
}
```

### "Can user manage this record?"
```php
if (hasPermission('edit_employee') && hasPermission('delete_employee')) {
    // User can both edit and delete employees
}
```

### "Should we filter data to current user?"
```php
$get_all = hasPermission('view_all_attendance');
if (!$get_all) {
    // Filter to current user's records
}
```

---

## For Administrators - User Management

### How to Create Admin User
1. Go to Users → User Management (or /users.php)
2. Click "Add User"
3. Fill in user details
4. Set Role: **Administrator (Full Access)**
5. Set password
6. Click Save

**Result:** User has access to all pages and data.

### How to Create Employee User
1. Go to Users → User Management 
2. Click "Add User"
3. Fill in user details
4. Set Role: **Employee (Limited Access)**
5. Set password
6. Click Save

**Result:** User has limited access:
- Can view own attendance, leaves, documents
- Cannot access admin pages
- Dashboard shows only personal statistics

### How to Change User Role
1. Go to Users → User Management
2. Find user in table
3. Click edit (pencil icon)
4. Change Role dropdown
5. Click Save

**Result:** User permissions change immediately for next action.

---

## Debugging Permission Issues

### User says "Access Denied"
1. Check user role: Users → User Management → find user
2. Verify permission is assigned:
   - `includes/auth.php` → check `getPermissions()` function
   - Verify role has permission set to `true`
3. Clear browser cache and refresh
4. Re-login if cached permissions might be stale

### Button not showing for user
1. Verify permission gate: `<?php if (hasPermission('xxx')): ?>`
2. Check permission name matches `getPermissions()` array
3. Verify user role and permissions
4. Check browser console for JavaScript errors

### Data showing when it shouldn't
1. Check query WHERE clause filters by permission
2. Verify `hasPermission()` is used in query condition
3. Check user role and assigned permissions
4. Verify no hardcoded role checks (`$_SESSION['role'] === 'admin'`) - should use `hasPermission()`

---

## Security Best Practices

✅ **DO**
- Use `requirePermission()` at page entry point
- Use `hasPermission()` for conditional UI rendering  
- Filter queries based on permissions
- Update `getPermissions()` for new features
- Log access denied events (via activity_log)

❌ **DON'T**
- Hard-code role checks: `if ($_SESSION['role'] === 'admin')`
- Show buttons that do nothing when clicked
- Forget to filter admin-only data
- Mix permission naming conventions
- Trust client-side validation alone

---

## Adding a New Feature with Permissions

### Step 1: Define Permission in auth.php
```php
function getPermissions($role = null) {
    $permissions = [
        'admin' => [
            // ... existing permissions
            'view_my_new_feature' => true,
            'create_new_item' => true,
            'delete_new_item' => true,
        ],
        'employee' => [
            // ... existing permissions
            'view_my_new_feature' => true,  // Employees can see
            'create_new_item' => false,     // But cannot create
            'delete_new_item' => false,     // And cannot delete
        ]
    ];
}
```

### Step 2: Gate Page Access
```php
<?php
require_once 'includes/auth.php';
requireLogin();
requirePermission('view_my_new_feature');  // Add this line
```

### Step 3: Gate UI Elements
```php
<?php if (hasPermission('create_new_item')): ?>
    <button>Add Item</button>
<?php endif; ?>
```

### Step 4: Filter Data if Needed
```php
if (!hasPermission('view_all_items')) {
    $query .= " WHERE owner_id = ?";
    $params[] = $_SESSION['user_id'];
}
```

---

## Support & Questions

For RBAC issues:
1. Check this quick reference
2. Review the permission matrix above and `includes/auth.php`
3. Check includes/auth.php for permission definitions
4. Verify permission names in error messages

---

Last Updated: 2025
RBAC System Version: 1.0
