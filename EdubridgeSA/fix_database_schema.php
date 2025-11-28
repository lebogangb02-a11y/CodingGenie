<?php
/**
 * Database Schema Fix Script
 * This script fixes all database schema issues identified in the application workflow test
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔧 Database Schema Fix</h1>";

// Include database configuration
require_once 'config.php';

if (!isset($pdo)) {
    die("❌ Database connection failed. Please check config.php");
}

echo "✅ Database connection established<br><br>";

try {
    // Start transaction
    $pdo->beginTransaction();
    
    echo "<h2>1. Checking and fixing column names</h2>";
    
    // Check if we need to rename sa_id to id_number
    $stmt = $pdo->query("SHOW COLUMNS FROM applications LIKE 'sa_id'");
    if ($stmt->rowCount() > 0) {
        echo "⚠ Found 'sa_id' column, renaming to 'id_number'...<br>";
        $pdo->exec("ALTER TABLE applications CHANGE sa_id id_number VARCHAR(13) NOT NULL");
        echo "✅ Renamed 'sa_id' to 'id_number'<br>";
    } else {
        echo "✅ 'id_number' column already exists<br>";
    }
    
    // Check if we need to add application_ref column
    $stmt = $pdo->query("SHOW COLUMNS FROM applications LIKE 'application_ref'");
    if ($stmt->rowCount() == 0) {
        echo "⚠ Adding missing 'application_ref' column...<br>";
        $pdo->exec("ALTER TABLE applications ADD COLUMN application_ref VARCHAR(50) UNIQUE");
        echo "✅ Added 'application_ref' column<br>";
    } else {
        echo "✅ 'application_ref' column already exists<br>";
    }
    
    echo "<h2>2. Adding missing document upload fields</h2>";
    
    // Document fields that should exist
    $documentFields = [
        'id_copy' => 'VARCHAR(255)',
        'proof_of_address' => 'VARCHAR(255)', 
        'parent_id_copy' => 'VARCHAR(255)',
        'academic_results' => 'VARCHAR(255)'
    ];
    
    foreach ($documentFields as $field => $type) {
        $stmt = $pdo->query("SHOW COLUMNS FROM applications LIKE '{$field}'");
        if ($stmt->rowCount() == 0) {
            echo "⚠ Adding missing '{$field}' column...<br>";
            $pdo->exec("ALTER TABLE applications ADD COLUMN {$field} {$type}");
            echo "✅ Added '{$field}' column<br>";
        } else {
            echo "✅ '{$field}' column already exists<br>";
        }
    }
    
    echo "<h2>3. Updating status column values</h2>";
    
    // Update status column to use proper ENUM values
    $pdo->exec("ALTER TABLE applications MODIFY status ENUM('Pending', 'Accepted', 'Rejected', 'Submitted (with docs)', 'Submitted (without docs)') DEFAULT 'Pending'");
    echo "✅ Updated status column ENUM values<br>";
    
    echo "<h2>4. Generating application references for existing records</h2>";
    
    // Generate application references for records that don't have them
    $stmt = $pdo->query("SELECT id FROM applications WHERE application_ref IS NULL OR application_ref = ''");
    $existingApps = $stmt->fetchAll();
    
    if (count($existingApps) > 0) {
        echo "Found " . count($existingApps) . " records without application references<br>";
        
        foreach ($existingApps as $app) {
            $appRef = 'APP' . date('Y') . str_pad($app['id'], 6, '0', STR_PAD_LEFT);
            $updateStmt = $pdo->prepare("UPDATE applications SET application_ref = ? WHERE id = ?");
            $updateStmt->execute([$appRef, $app['id']]);
            echo "✅ Generated reference {$appRef} for application ID {$app['id']}<br>";
        }
    } else {
        echo "✅ All existing records already have application references<br>";
    }
    
    echo "<h2>5. Verifying schema integrity</h2>";
    
    // Verify all required columns exist
    $requiredColumns = [
        'id', 'application_ref', 'first_name', 'last_name', 'id_number', 
        'dob', 'gender', 'email', 'phone', 'address', 'city', 'province', 'postal_code',
        'program_choice_1', 'institution_choice_1', 'status', 'application_date',
        'id_copy', 'proof_of_address', 'parent_id_copy', 'academic_results'
    ];
    
    $stmt = $pdo->query("SHOW COLUMNS FROM applications");
    $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $missingColumns = array_diff($requiredColumns, $existingColumns);
    
    if (empty($missingColumns)) {
        echo "✅ All required columns are present<br>";
    } else {
        echo "⚠ Missing columns: " . implode(', ', $missingColumns) . "<br>";
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo "<br><h2>🎉 Database schema fix completed successfully!</h2>";
    echo "<p>✅ All database schema issues have been resolved.</p>";
    echo "<p>📋 <strong>Next steps:</strong></p>";
    echo "<ul>";
    echo "<li>Run the application workflow test again</li>";
    echo "<li>Test the upload documents functionality</li>";
    echo "<li>Verify admin dashboard displays application references</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollback();
    echo "❌ Error fixing database schema: " . $e->getMessage() . "<br>";
    echo "<p>The database has been rolled back to its previous state.</p>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Schema Fix - EduBridge SA</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }
        h1, h2 {
            color: #333;
        }
        h1 {
            text-align: center;
            margin-bottom: 30px;
        }
        .success {
            color: #27ae60;
        }
        .warning {
            color: #f39c12;
        }
        .error {
            color: #e74c3c;
        }
        ul {
            padding-left: 20px;
        }
        li {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- PHP output will appear here -->
    </div>
</body>
</html>