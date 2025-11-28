<?php
/**
 * Database Migration Runner
 * This script executes the database migration to fix missing tables and columns
 */

require_once 'config.php';

echo "<h2>Database Migration Runner</h2>\n";
echo "<p>This will update your database structure to fix login issues.</p>\n";

try {
    // Create database connection
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    
    echo "✅ Database connection successful!<br><br>\n";
    
    // Read and execute the migration script
    $migrationSQL = file_get_contents('dashboard_upgrade_migration.sql');
    
    if ($migrationSQL === false) {
        throw new Exception("Could not read dashboard_upgrade_migration.sql file");
    }
    
    echo "<h3>Executing Migration Script...</h3>\n";
    
    // Split the SQL into individual statements
    $statements = explode(';', $migrationSQL);
    
    $executedCount = 0;
    $errorCount = 0;
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        
        // Skip empty statements and comments
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }
        
        try {
            $pdo->exec($statement);
            $executedCount++;
            echo "✅ Executed statement successfully<br>\n";
        } catch (PDOException $e) {
            // Some statements might fail if they already exist, which is okay
            if (strpos($e->getMessage(), 'already exists') !== false || 
                strpos($e->getMessage(), 'Duplicate') !== false) {
                echo "ℹ️ Skipped (already exists): " . substr($statement, 0, 50) . "...<br>\n";
            } else {
                echo "❌ Error: " . $e->getMessage() . "<br>\n";
                echo "Statement: " . substr($statement, 0, 100) . "...<br>\n";
                $errorCount++;
            }
        }
    }
    
    echo "<br><h3>Migration Summary:</h3>\n";
    echo "Statements executed: $executedCount<br>\n";
    echo "Errors encountered: $errorCount<br><br>\n";
    
    // Now create missing tables that aren't in the migration script
    echo "<h3>Creating Missing Tables...</h3>\n";
    
    // Create application_documents table
    $createAppDocs = "
    CREATE TABLE IF NOT EXISTS application_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        application_id INT NOT NULL,
        document_type VARCHAR(50) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        original_filename VARCHAR(255) NOT NULL,
        file_size INT NOT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_application_id (application_id)
    )";
    
    try {
        $pdo->exec($createAppDocs);
        echo "✅ Created application_documents table<br>\n";
    } catch (PDOException $e) {
        echo "ℹ️ application_documents table already exists<br>\n";
    }
    
    // Create user_sessions table
    $createUserSessions = "
    CREATE TABLE IF NOT EXISTS user_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        session_token VARCHAR(128) NOT NULL UNIQUE,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        INDEX idx_user_id (user_id),
        INDEX idx_session_token (session_token),
        INDEX idx_expires_at (expires_at)
    )";
    
    try {
        $pdo->exec($createUserSessions);
        echo "✅ Created user_sessions table<br>\n";
    } catch (PDOException $e) {
        echo "ℹ️ user_sessions table already exists<br>\n";
    }
    
    // Create password_reset_tokens table
    $createPasswordReset = "
    CREATE TABLE IF NOT EXISTS password_reset_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(64) NOT NULL UNIQUE,
        expires_at TIMESTAMP NOT NULL,
        used BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_token (token),
        INDEX idx_expires_at (expires_at)
    )";
    
    try {
        $pdo->exec($createPasswordReset);
        echo "✅ Created password_reset_tokens table<br>\n";
    } catch (PDOException $e) {
        echo "ℹ️ password_reset_tokens table already exists<br>\n";
    }
    
    // Verify the test user exists and has the right status
    echo "<h3>Checking Test User...</h3>\n";
    
    $stmt = $pdo->prepare("SELECT id, email, status FROM users WHERE email = ?");
    $stmt->execute(['lebogang002@gmail.com']);
    $testUser = $stmt->fetch();
    
    if (!$testUser) {
        // Check if lebogangb02@gmail.com exists (from the database results)
        $stmt = $pdo->prepare("SELECT id, email, status FROM users WHERE email = ?");
        $stmt->execute(['lebogangb02@gmail.com']);
        $existingUser = $stmt->fetch();
        
        if ($existingUser) {
            echo "✅ Found existing user: " . $existingUser['email'] . " (status: " . $existingUser['status'] . ")<br>\n";
            echo "You can use this email to test login.<br>\n";
        } else {
            echo "❌ No test users found. You may need to register a new account.<br>\n";
        }
    } else {
        echo "✅ Test user found: " . $testUser['email'] . " (status: " . $testUser['status'] . ")<br>\n";
    }
    
    echo "<br><h3>✅ Migration Completed Successfully!</h3>\n";
    echo "<p>Your database structure has been updated. You can now try logging in again.</p>\n";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "<br>\n";
}

echo "<br><a href='test_db_connection.php'>🔍 Test Database Again</a> | ";
echo "<a href='student-login.php'>🔑 Try Login</a>\n";
?>