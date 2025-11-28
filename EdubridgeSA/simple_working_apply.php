<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['student_logged_in']) || $_SESSION['student_logged_in'] !== true) {
    header('Location: student-login.php');
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Simple Working Apply Form</title>
</head>
<body>
    <h1>✅ SUCCESS! You reached the application form!</h1>
    <p>This proves the navigation works when there are no redirects.</p>
    <p>Student: <?php echo htmlspecialchars($_SESSION['student_name'] ?? 'Unknown'); ?></p>
    <a href="student-dashboard.php">← Back to Dashboard</a>
</body>
</html>