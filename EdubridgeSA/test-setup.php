<?php
session_start();
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_username'] = 'test_admin';

// Test database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=u839420047_applications;charset=utf8mb4", 
                   "u839420047_applications_user", "Eduweb@2024");
    echo "✅ Database connected successfully<br>";
    
    // Test if applications table exists
    $tables = $pdo->query("SHOW TABLES LIKE 'applications'")->rowCount();
    echo $tables > 0 ? "✅ Applications table exists<br>" : "❌ Applications table missing<br>";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

echo "✅ PHP is working correctly";
?>