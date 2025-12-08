<?php
require_once 'session_config.php';
require_once 'config.php';

echo "<h1>Dashboard Debug</h1>";

// Check session first
echo "<h2>Session Status:</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Check if logged in
if (!isLoggedIn()) {
    echo "<h3 style='color: red'>❌ NOT LOGGED IN</h3>";
    exit;
} else {
    echo "<h3 style='color: green'>✅ LOGGED IN</h3>";
}

// Test database connection and data
try {
    $pdo = Database::getPDO();
    echo "<h3>✅ Database Connected</h3>";

    // Get user applications
    $stmt = $pdo->prepare("
        SELECT a.*, u.first_name, u.last_name 
        FROM applications a 
        LEFT JOIN users u ON a.email_address = u.email 
        WHERE a.email_address = ? OR a.student_id = ?
        ORDER BY a.created_at DESC
    ");
    $stmt->execute([$_SESSION['student_email'], $_SESSION['student_id']]);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h3>Applications Found: " . count($applications) . "</h3>";
    if ($applications) {
        echo "<pre>";
        print_r($applications);
        echo "</pre>";
    } else {
        echo "<p>No applications found for this user.</p>";
    }

    // Test notifications
    $stmt = $pdo->prepare("SELECT id, student_id, notification_type, message, created_at, read_at FROM notifications WHERE student_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$_SESSION['user_id']]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h3>Notifications Found: " . count($notifications) . "</h3>";
} catch (Exception $e) {
    echo "<h3 style='color: red'>❌ Database Error: " . $e->getMessage() . "</h3>";
}
