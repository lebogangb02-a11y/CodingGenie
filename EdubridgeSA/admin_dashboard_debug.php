<?php
// Simple dashboard for testing
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login_debug.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard Debug</title>
    <style>
        body { font-family: Arial; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { background: #d4edda; padding: 20px; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="success">
        <h1>✅ Admin Dashboard - Debug</h1>
        <p><strong>Username:</strong> <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Unknown'); ?></p>
        <p><strong>Login Time:</strong> <?php echo date('Y-m-d H:i:s', $_SESSION['login_time'] ?? time()); ?></p>
        <p><strong>Session ID:</strong> <?php echo session_id(); ?></p>
        
        <h3>Available Pages:</h3>
        <ul>
            <li><a href="admin_messages.php">Messages</a></li>
            <li><a href="manage_students.php">Manage Students</a></li>
            <li><a href="admin_login_debug.php?action=logout">Logout</a></li>
        </ul>
    </div>
</body>
</html>