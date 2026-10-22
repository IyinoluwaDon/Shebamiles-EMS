<?php
// Offline contract checks: no database connection or login attempt.
$_SERVER['REQUEST_METHOD'] = 'GET';
require_once __DIR__ . '/../includes/auth.php';
function check($condition, $message) {
    if (!$condition) { fwrite(STDERR, $message . PHP_EOL); exit(1); }
}
check(!verifyCSRFToken(null), 'Missing token accepted');
check(!verifyCSRFToken(['bad']), 'Array token accepted');
$token = generateCSRFToken();
check(strlen($token) === 64, 'Wrong token length');
check(verifyCSRFToken($token), 'Valid token rejected');
check(!verifyCSRFToken(str_repeat('x', 64)), 'Invalid token accepted');
check(!hasPermission('view_users'), 'Anonymous permission granted');
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'fixture';
$_SESSION['role'] = 'employee';
check(!hasPermission('view_payroll'), 'Employee payroll access granted');
check(!hasPermission('manage_roles'), 'Employee role-management access granted');
$_SESSION['role'] = 'admin';
check(hasPermission('manage_roles'), 'Admin role-management permission missing');
echo 'CSRF and permission checks passed' . PHP_EOL;
session_destroy();
