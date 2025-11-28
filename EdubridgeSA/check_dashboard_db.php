<?php
require_once 'config.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    echo "<h2>Dashboard Database Structure Check</h2>\n";
    
    // Check users table structure
    echo "<h3>Users Table Structure:</h3>\n";
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll();
    
    $required_columns = ['profile_picture', 'date_of_birth', 'address', 'school_university', 'profile_updated_at'];
    $existing_columns = array_column($columns, 'Field');
    
    echo "<table border='1'>\n";
    echo "<tr><th>Column</th><th>Type</th><th>Status</th></tr>\n";
    
    foreach ($columns as $column) {
        echo "<tr><td>{$column['Field']}</td><td>{$column['Type']}</td><td>✅ Exists</td></tr>\n";
    }
    
    foreach ($required_columns as $req_col) {
        if (!in_array($req_col, $existing_columns)) {
            echo "<tr><td>{$req_col}</td><td>Missing</td><td>❌ Missing</td></tr>\n";
        }
    }
    echo "</table>\n";
    
    // Check applications table status column
    echo "<h3>Applications Table Status Column:</h3>\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM applications LIKE 'status'");
    $status_column = $stmt->fetch();
    
    if ($status_column) {
        echo "<p>Status column type: {$status_column['Type']}</p>\n";
        if (strpos($status_column['Type'], 'documents_pending') !== false) {
            echo "<p>✅ Status column includes new status options</p>\n";
        } else {
            echo "<p>❌ Status column needs updating</p>\n";
        }
    }
    
    // Check if student_profiles table exists
    echo "<h3>Student Profiles Table:</h3>\n";
    try {
        $stmt = $pdo->query("DESCRIBE student_profiles");
        echo "<p>✅ student_profiles table exists</p>\n";
    } catch (Exception $e) {
        echo "<p>❌ student_profiles table does not exist</p>\n";
    }
    
    // Check if application_courses table exists
    echo "<h3>Application Courses Table:</h3>\n";
    try {
        $stmt = $pdo->query("DESCRIBE application_courses");
        echo "<p>✅ application_courses table exists</p>\n";
    } catch (Exception $e) {
        echo "<p>❌ application_courses table does not exist</p>\n";
    }
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>\n";
}
?>