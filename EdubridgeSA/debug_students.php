<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h3>Debug: Student Management</h3>";

// Check session
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die("Not logged in. Redirect to login.");
}

echo "✓ Session OK<br>";

// Check config
if (!file_exists(__DIR__ . '/config.php')) {
    die("config.php not found");
}
require_once __DIR__ . '/config.php';
echo "✓ Config loaded<br>";

// Check database connection
try {
    $test = $pdo->query("SELECT 1");
    echo "✓ Database connected<br>";
} catch (Exception $e) {
    die("✗ Database error: " . $e->getMessage());
}

// Check if applications table exists
try {
    $tableExists = $pdo->query("SHOW TABLES LIKE 'applications'")->rowCount() > 0;
    echo $tableExists ? "✓ Applications table exists<br>" : "✗ Applications table missing<br>";
} catch (Exception $e) {
    die("✗ Table check failed: " . $e->getMessage());
}

// Test basic query
try {
    $count = $pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn();
    echo "✓ Total applications: " . $count . "<br>";
} catch (Exception $e) {
    die("✗ Query failed: " . $e->getMessage());
}

echo "<hr><h4>All tests passed! The issue is in the main file.</h4>";
?>