<?php
/**
 * Database Check Script
 * Verify database structure and check for student data
 */

require_once 'config.php';

echo "<h1>🔍 Database Structure Check</h1>";
echo "<style>
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .info { color: blue; }
    .warning { color: orange; font-weight: bold; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
</style>";

try {
    // Check if applications table exists
    echo "<h2>📋 Checking Applications Table</h2>";
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'applications'");
    $table_exists = $stmt->fetch();
    
    if ($table_exists) {
        echo "<span class='success'>✓ Applications table exists</span><br>";
        
        // Get table structure
        echo "<h3>Table Structure:</h3>";
        $stmt = $pdo->query("DESCRIBE applications");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table>";
        echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>{$column['Field']}</td>";
            echo "<td>{$column['Type']}</td>";
            echo "<td>{$column['Null']}</td>";
            echo "<td>{$column['Key']}</td>";
            echo "<td>{$column['Default']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Check total records
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM applications");
        $total = $stmt->fetch()['total'];
        echo "<span class='info'>📊 Total applications in database: $total</span><br>";
        
        // Check for the specific email from the login form
        $test_email = 'lebogangb02@gmail.com';
        $test_ref = 'APP2025093298';
        
        echo "<h3>🔍 Checking for specific credentials:</h3>";
        echo "<span class='info'>Email: $test_email</span><br>";
        echo "<span class='info'>Reference: $test_ref</span><br><br>";
        
        // Check if email exists
        $stmt = $pdo->prepare("SELECT * FROM applications WHERE email_address = ?");
        $stmt->execute([$test_email]);
        $email_match = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($email_match) {
            echo "<span class='success'>✓ Email found in database</span><br>";
            echo "<span class='info'>Reference number in DB: {$email_match['reference_number']}</span><br>";
            echo "<span class='info'>Status: {$email_match['status']}</span><br>";
            
            if ($email_match['reference_number'] === $test_ref) {
                echo "<span class='success'>✓ Reference number matches!</span><br>";
            } else {
                echo "<span class='warning'>⚠ Reference number mismatch!</span><br>";
                echo "<span class='info'>Expected: $test_ref</span><br>";
                echo "<span class='info'>Found: {$email_match['reference_number']}</span><br>";
            }
        } else {
            echo "<span class='error'>✗ Email not found in database</span><br>";
        }
        
        // Check if reference number exists (maybe with different email)
        $stmt = $pdo->prepare("SELECT * FROM applications WHERE reference_number = ?");
        $stmt->execute([$test_ref]);
        $ref_match = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($ref_match) {
            echo "<span class='success'>✓ Reference number found in database</span><br>";
            echo "<span class='info'>Email in DB: {$ref_match['email_address']}</span><br>";
            
            if ($ref_match['email_address'] === $test_email) {
                echo "<span class='success'>✓ Email matches!</span><br>";
            } else {
                echo "<span class='warning'>⚠ Email mismatch!</span><br>";
                echo "<span class='info'>Expected: $test_email</span><br>";
                echo "<span class='info'>Found: {$ref_match['email_address']}</span><br>";
            }
        } else {
            echo "<span class='error'>✗ Reference number not found in database</span><br>";
        }
        
        // Show some sample data (first 5 records)
        echo "<h3>📋 Sample Applications (first 5 records):</h3>";
        $stmt = $pdo->query("SELECT id, email_address, reference_number, full_name, status, created_at FROM applications LIMIT 5");
        $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($samples) {
            echo "<table>";
            echo "<tr><th>ID</th><th>Email</th><th>Reference</th><th>Name</th><th>Status</th><th>Created</th></tr>";
            foreach ($samples as $sample) {
                echo "<tr>";
                echo "<td>{$sample['id']}</td>";
                echo "<td>{$sample['email_address']}</td>";
                echo "<td>{$sample['reference_number']}</td>";
                echo "<td>{$sample['full_name']}</td>";
                echo "<td>{$sample['status']}</td>";
                echo "<td>{$sample['created_at']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<span class='warning'>⚠ No sample data found</span><br>";
        }
        
    } else {
        echo "<span class='error'>✗ Applications table does not exist!</span><br>";
        echo "<span class='info'>This could be why login is failing.</span><br>";
        
        // Show all tables
        echo "<h3>Available tables:</h3>";
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if ($tables) {
            foreach ($tables as $table) {
                echo "<span class='info'>- $table</span><br>";
            }
        } else {
            echo "<span class='warning'>No tables found in database</span><br>";
        }
    }
    
} catch (Exception $e) {
    echo "<span class='error'>✗ Database error: " . $e->getMessage() . "</span><br>";
}
?>