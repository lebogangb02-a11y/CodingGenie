<?php
/**
 * Database Structure Check
 * Investigates table structure and email fields
 */

require_once 'config.php';

echo "<h1>Database Structure Check</h1>";

try {
    // Use the PDO connection from config.php
    echo "✓ Connected to database successfully<br><br>";
    
    // Get all tables
    echo "<h2>Available Tables:</h2>";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        echo "- $table<br>";
    }
    
    // Check specific tables for email fields
    $email_tables = ['students', 'applications', 'admin_users'];
    
    echo "<h2>Email Fields Check:</h2>";
    foreach ($email_tables as $table) {
        echo "<h3>Table: $table</h3>";
        
        try {
            $stmt = $pdo->query("DESCRIBE `$table`");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $email_fields = [];
            foreach ($columns as $column) {
                if (strpos(strtolower($column['Field']), 'email') !== false) {
                    $email_fields[] = $column['Field'] . ' (' . $column['Type'] . ')';
                }
            }
            
            if ($email_fields) {
                echo "✓ Email fields found:<br>";
                foreach ($email_fields as $field) {
                    echo "  - $field<br>";
                }
            } else {
                echo "⚠ No email fields found<br>";
            }
            
            // Show all fields for reference
            echo "All fields:<br>";
            foreach ($columns as $column) {
                echo "  - " . $column['Field'] . " (" . $column['Type'] . ")<br>";
            }
            
        } catch (Exception $e) {
            echo "✗ Error accessing table '$table': " . $e->getMessage() . "<br>";
        }
        
        echo "<br>";
    }
    
} catch (Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "<br>";
}
?>