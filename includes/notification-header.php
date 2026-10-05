<?php
// NOTIFICATION HEADER COMPONENT
// Purpose: Display bell icon with unread notification count in page header
// This component is included in main pages to show real-time notification status

// Include helper functions for notification operations
require_once __DIR__ . '/helpers.php';

// GET UNREAD NOTIFICATION COUNT for current user
// Only attempt if user is logged in (session exists)
if (isset($_SESSION['user_id'])) {
    try {
        // Call helper function to count unread notifications
        // Returns integer count (0 if no unread notifications)
        $unread_count = getUnreadNotificationCount($_SESSION['user_id']);
    } catch (Exception $e) {
        // Gracefully handle errors - show 0 if function fails
        $unread_count = 0;
    }
}
?>

<!-- NOTIFICATION BELL HEADER ELEMENT -->
<div class="notification-header">
    <!-- Link to notifications page -->
    <a href="<?php echo BASE_URL; ?>/communication/notifications.php" class="notification-bell">
        <!-- Bell icon from Font Awesome -->
        <i class="fas fa-bell"></i>
        
        <!-- NOTIFICATION BADGE: Shows count if there are unread notifications -->
        <?php if (isset($unread_count) && $unread_count > 0): ?>
            <!-- Display count with 99+ cap to prevent layout issues -->
            <span class="notification-badge"><?php echo $unread_count > 99 ? '99+' : $unread_count; ?></span>
        <?php endif; ?>
    </a>
</div>
