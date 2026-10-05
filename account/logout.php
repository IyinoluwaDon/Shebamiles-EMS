<?php
// LOGOUT HANDLER PAGE
// Purpose: Safely end user session and redirect to login page
// This page is called when user clicks "Logout" button

// STEP 1: Include authentication functions/helpers
require_once __DIR__ . '/../includes/auth.php';

// STEP 2: Call logout() function which:
//   - Unsets all session variables ($_SESSION)
//   - Destroys session file on server
//   - Redirects to login.php
//   - Exits script execution
logout();
?>
