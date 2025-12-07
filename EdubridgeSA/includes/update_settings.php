<?php
// update_settings.php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    // Enforce CSRF check server-side
    if (function_exists('require_csrf')) { require_csrf(); }
    $dark_mode = isset($_POST['dark_mode']) ? 1 : 0;
    $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
    $login_alerts = isset($_POST['login_alerts']) ? 1 : 0;
    $language = $_POST['language'] ?? 'en';
    
    try {
        $stmt = $pdo->prepare('UPDATE admins SET dark_mode = ?, email_notifications = ?, login_alerts = ?, language = ? WHERE id = ?');
        $stmt->execute([$dark_mode, $email_notifications, $login_alerts, $language, $admin_id]);
        
        // Update session for immediate effect
        $_SESSION['admin_dark_mode'] = $dark_mode;
        
        // Log activity
        logAdminActivity($pdo, $admin_id, 'Update Settings', 'Updated account preferences', $_SERVER['REMOTE_ADDR']);
        
        $msg = 'Settings updated successfully.';
        $msgType = 'success';
        
        // Refresh page to apply dark mode
        echo '<script>setTimeout(() => window.location.reload(), 1000);</script>';
        
    } catch (Exception $e) {
        $msg = 'Error updating settings: ' . $e->getMessage();
        $msgType = 'danger';
    }
}
?>