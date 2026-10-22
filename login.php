<?php
// LOGIN PAGE - Shebamiles EMS
// Purpose: Authenticate users via username/password form
// Redirect to dashboard if already logged in

// INCLUDE AUTHENTICATION FUNCTIONS
require_once 'includes/auth.php';

// CHECK IF USER ALREADY LOGGED IN
// If yes, redirect to dashboard (prevent re-login)
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

// INITIALIZE ERROR MESSAGE
$error = '';

// CHECK DATABASE CONNECTION
// If database not set up, show error message to user
$db = new Database();
if ($db->getConnection() === null) {
    $error = 'Database not set up. Please import database/shebamiles_db.sql first.';
}

// HANDLE LOGIN FORM SUBMISSION
// Process POST request when user clicks "Sign In" button
if (!isset($_POST['delete']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // EXTRACT AND SANITIZE INPUT
    // Get username and password from form
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // VALIDATE INPUT
    // Check that both fields are filled (not empty)
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        // ATTEMPT AUTHENTICATION
        // Call login() function with username and password
        if (login($username, $password)) {
            // LOGIN SUCCESSFUL
            // Redirect to dashboard
            header('Location: dashboard.php');
            exit();
        } else {
            // LOGIN FAILED
            // Show generic error message (don't reveal which field is wrong for security)
            $error = 'Invalid username or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Shebamiles EMS</title>
    <!-- Link to main stylesheet -->
    <link rel="stylesheet" href="css/style.css">
    <!-- Link to Font Awesome icon library -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- MAIN LOGIN CONTAINER -->
    <div class="login-container">
        <!-- LOGIN BOX: Center card with login form -->
        <div class="login-box">
            <!-- LOGO SECTION -->
            <div class="login-logo">
                <h1>Shebamiles</h1>
                <p>Employee Management System</p>
            </div>
            
            <!-- ERROR MESSAGE DISPLAY (if any) -->
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <!-- LOGIN FORM -->
            <form method="POST" action="">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
                <!-- USERNAME INPUT -->
                <div class="form-group">
                    <label for="username">Username</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        class="form-control" 
                        placeholder="Enter your username"
                        value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                        required
                        autofocus
                    >
                </div>
                
                <!-- PASSWORD INPUT -->
                <div class="form-group">
                    <label for="password">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-control" 
                        placeholder="Enter your password"
                        required
                    >
                </div>
                
                <!-- SUBMIT BUTTON -->
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
            </form>
            
            <!-- DEFAULT CREDENTIALS INFO -->
            <div style="margin-top: 1.5rem; text-align: center; color: var(--medium-gray); font-size: 0.85rem;">
                <p><strong>Default Login:</strong></p>
                <p>Username: <strong>admin</strong> | Password: <strong>admin123</strong></p>
            </div>
        </div>
    </div>
    
    <!-- INCLUDE SHEBAMILES BRANDING BADGE -->
    <?php include 'includes/badge.php'; ?>
</body>
</html>
