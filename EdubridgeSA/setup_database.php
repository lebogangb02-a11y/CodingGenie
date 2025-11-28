<?php
/**
 * Database Setup Script for Student Application System
 * EduBridge SA
 * 
 * This script creates all necessary database tables using the schema file
 */

require_once 'config.php';

// Function to execute SQL statements from schema file
function executeSQLFile($pdo, $filename) {
    if (!file_exists($filename)) {
        throw new Exception("Schema file not found: $filename");
    }
    
    $sql = file_get_contents($filename);
    if ($sql === false) {
        throw new Exception("Could not read schema file: $filename");
    }
    
    // Split SQL into individual statements
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^\s*--/', $stmt);
        }
    );
    
    $executed = 0;
    $errors = [];
    
    foreach ($statements as $statement) {
        try {
            $pdo->exec($statement);
            $executed++;
            echo "✓ Executed statement " . ($executed) . "<br>\n";
        } catch (PDOException $e) {
            $errors[] = "Error executing statement: " . $e->getMessage();
            echo "✗ Error in statement " . ($executed + 1) . ": " . $e->getMessage() . "<br>\n";
        }
    }
    
    return ['executed' => $executed, 'errors' => $errors];
}

// Function to check if tables exist
function checkTablesExist($pdo) {
    $tables = ['applications', 'parent_guardian_details', 'university_choices', 
               'application_documents', 'application_status_history', 
               'email_notifications', 'universities'];
    
    $existing = [];
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                $existing[] = $table;
            }
        } catch (PDOException $e) {
            // Table doesn't exist or other error
        }
    }
    
    return $existing;
}

// Function to get table row counts
function getTableCounts($pdo, $tables) {
    $counts = [];
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM `$table`");
            $result = $stmt->fetch();
            $counts[$table] = $result['count'];
        } catch (PDOException $e) {
            $counts[$table] = 'Error: ' . $e->getMessage();
        }
    }
    return $counts;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - EduBridge SA</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        .setup-content {
            font-size: 16px;
            line-height: 1.6;
        }
        a {
            color: #667eea;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        .success {
            color: #27ae60;
        }
        .error {
            color: #e74c3c;
        }
        .info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            border-left: 4px solid #667eea;
        }
        .table-status {
            background: #f1f3f4;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎓 EduBridge SA - Database Setup</h1>
        <div class="setup-content">
            
<?php
echo "<h2>=== Database Setup Started ===</h2>\n";

try {
    // Check database connection
    echo "<h3>1. Testing database connection...</h3>\n";
    $stmt = $pdo->query("SELECT VERSION() as version");
    $version = $stmt->fetch();
    echo "✅ Connected to MySQL version: " . $version['version'] . "<br>\n";
    echo "✅ Database: " . DB_NAME . "<br><br>\n";
    
    // Check existing tables
    echo "<h3>2. Checking existing tables...</h3>\n";
    $existingTables = checkTablesExist($pdo);
    if (!empty($existingTables)) {
        echo "⚠️ Found existing tables: " . implode(', ', $existingTables) . "<br>\n";
        echo "⚠️ This script will attempt to create missing tables<br><br>\n";
    } else {
        echo "✅ No existing tables found - clean setup<br><br>\n";
    }
    
    // Execute schema file
    echo "<h3>3. Executing database schema...</h3>\n";
    $result = executeSQLFile($pdo, 'database_schema.sql');
    
    echo "<h3>=== Execution Summary ===</h3>\n";
    echo "Statements executed: " . $result['executed'] . "<br>\n";
    
    if (!empty($result['errors'])) {
        echo "Errors encountered: " . count($result['errors']) . "<br>\n";
        foreach ($result['errors'] as $error) {
            echo "<span class='error'>  - $error</span><br>\n";
        }
    } else {
        echo "✅ No errors encountered<br>\n";
    }
    
    // Verify tables were created
    echo "<h3>4. Verifying table creation...</h3>\n";
    $finalTables = checkTablesExist($pdo);
    $expectedTables = ['applications', 'parent_guardian_details', 'university_choices', 
                      'application_documents', 'application_status_history', 
                      'email_notifications', 'universities'];
    
    $missing = array_diff($expectedTables, $finalTables);
    
    if (empty($missing)) {
        echo "✅ All required tables created successfully<br>\n";
        
        // Show table counts
        echo "<h3>5. Table status:</h3>\n";
        $counts = getTableCounts($pdo, $finalTables);
        echo "<div class='table-status'>\n";
        foreach ($counts as $table => $count) {
            echo "  - $table: $count records<br>\n";
        }
        echo "</div>\n";
        
        // Create upload directories
        echo "<h3>6. Creating upload directories...</h3>\n";
        $uploadDirs = [
            'uploads/',
            'uploads/certified_id/',
            'uploads/proof_of_residence/',
            'uploads/parent_guardian_id/',
            'uploads/academic_results/'
        ];
        
        foreach ($uploadDirs as $dir) {
            if (!is_dir($dir)) {
                if (mkdir($dir, 0755, true)) {
                    echo "✅ Created directory: $dir<br>\n";
                } else {
                    echo "❌ Failed to create directory: $dir<br>\n";
                }
            } else {
                echo "✅ Directory exists: $dir<br>\n";
            }
        }
        
        // Create .htaccess for upload protection
        $htaccessContent = "# Deny direct access to uploaded files\nOrder Deny,Allow\nDeny from all\n";
        if (!file_exists('uploads/.htaccess')) {
            file_put_contents('uploads/.htaccess', $htaccessContent);
            echo "✅ Upload directory protection configured<br>\n";
        }
        
        echo "<div class='info'>\n";
        echo "<h3>🎉 Database Setup Complete!</h3>\n";
        echo "✅ Your student application system database is ready!<br>\n";
        echo "✅ You can now use the application form at: <a href='student_application.php'>student_application.php</a><br>\n";
        echo "✅ Check application status at: <a href='application_status.php'>application_status.php</a><br>\n";
        echo "✅ Upload documents at: <a href='document_upload.php'>document_upload.php</a><br>\n";
        echo "</div>\n";
        
    } else {
        echo "❌ Missing tables: " . implode(', ', $missing) . "<br>\n";
        echo "⚠️ Database setup incomplete - please check errors above<br>\n";
    }
    
} catch (Exception $e) {
    echo "<span class='error'>❌ Setup failed: " . $e->getMessage() . "</span><br>\n";
    echo "Please check your database configuration and try again.<br>\n";
}
?>

        </div>
    </div>
</body>
</html>