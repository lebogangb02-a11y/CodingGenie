<?php
/**
 * Check Students Table Structure
 */

require_once 'config.php';

try {
    $pdo = new PDO($dsn, $username, $password, $options);
    echo "Connected to database successfully\n";
    
    // Check if students table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'students'");
    $table_exists = $stmt->fetch();
    
    if ($table_exists) {
        echo "Students table exists\n";
        $stmt = $pdo->query("DESCRIBE students");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Students table structure:\n";
        foreach ($columns as $column) {
            echo "  - " . $column['Field'] . " (" . $column['Type'] . ")\n";
        }
        
        // Check for email fields specifically
        $email_columns = array_filter($columns, function($col) {
            return strpos(strtolower($col['Field']), 'email') !== false;
        });
        
        if ($email_columns) {
            echo "\nEmail fields found:\n";
            foreach ($email_columns as $col) {
                echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
            }
        } else {
            echo "\nNo email fields found in students table\n";
        }
        
    } else {
        echo "Students table does not exist\n";
        
        // Show all tables
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "Available tables:\n";
        foreach ($tables as $table) {
            echo "  - $table\n";
        }
    }
    
} catch (Exception $e) {
    echo 'Database error: ' . $e->getMessage() . "\n";
}
?>