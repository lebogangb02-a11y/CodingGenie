<?php
/**
 * Command Line Migration Script
 * Run this script to create missing database tables
 */

require_once 'config.php';

echo "=== Database Migration - Creating Missing Tables ===\n";

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Database connection successful!\n";
    echo "Database: " . DB_NAME . "\n";
    echo "Host: " . DB_HOST . "\n\n";
    
    // Embedded SQL statements for missing tables
    $sqlStatements = [
        // Create application_documents table
        "CREATE TABLE IF NOT EXISTS `application_documents` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `application_id` int(11) NOT NULL,
            `document_type` varchar(100) NOT NULL,
            `file_name` varchar(255) NOT NULL,
            `file_path` varchar(500) NOT NULL,
            `file_size` int(11) DEFAULT NULL,
            `mime_type` varchar(100) DEFAULT NULL,
            `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `status` enum('pending','approved','rejected') DEFAULT 'pending',
            PRIMARY KEY (`id`),
            KEY `idx_application_id` (`application_id`),
            KEY `idx_document_type` (`document_type`),
            FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Create user_sessions table
        "CREATE TABLE IF NOT EXISTS `user_sessions` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `session_id` varchar(128) NOT NULL,
            `ip_address` varchar(45) DEFAULT NULL,
            `user_agent` text,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `last_activity` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `expires_at` timestamp NOT NULL,
            `is_active` tinyint(1) DEFAULT 1,
            PRIMARY KEY (`id`),
            UNIQUE KEY `session_id` (`session_id`),
            KEY `idx_user_id` (`user_id`),
            KEY `idx_expires_at` (`expires_at`),
            FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Create password_reset_tokens table
        "CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `token` varchar(255) NOT NULL,
            `expires_at` timestamp NOT NULL,
            `used` tinyint(1) DEFAULT 0,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `used_at` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `token` (`token`),
            KEY `idx_user_id` (`user_id`),
            KEY `idx_expires_at` (`expires_at`),
            FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Insert migration record
        "INSERT IGNORE INTO `migrations` (`migration_name`, `executed_at`) VALUES ('create_missing_tables_v1.1', NOW())"
    ];
    
    $successCount = 0;
    $errorCount = 0;
    
    echo "Executing migration statements...\n";
    echo "=====================================\n";
    
    foreach ($sqlStatements as $index => $statement) {
        try {
            $pdo->exec($statement);
            $successCount++;
            
            // Extract table name for better feedback
            if (preg_match('/CREATE TABLE.*?`([^`]+)`/i', $statement, $matches)) {
                echo "✅ Created table: {$matches[1]}\n";
            } elseif (preg_match('/INSERT.*?migrations/i', $statement)) {
                echo "✅ Migration record inserted\n";
            }
            
        } catch (PDOException $e) {
            $errorCount++;
            echo "❌ Error executing statement " . ($index + 1) . ": " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n=====================================\n";
    echo "Migration Summary:\n";
    echo "✅ Successful statements: $successCount\n";
    echo "❌ Failed statements: $errorCount\n\n";
    
    // Verify tables were created
    echo "Verification - Checking Tables:\n";
    echo "===============================\n";
    $tables = ['application_documents', 'user_sessions', 'password_reset_tokens'];
    
    foreach ($tables as $table) {
        $result = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($result->rowCount() > 0) {
            echo "✅ Table '$table' exists\n";
            
            // Show column count
            $columns = $pdo->query("DESCRIBE `$table`");
            $columnCount = $columns->rowCount();
            echo "   Columns: $columnCount\n";
        } else {
            echo "❌ Table '$table' does NOT exist\n";
        }
    }
    
    // Check migration status
    echo "\nMigration History:\n";
    echo "==================\n";
    $migrations = $pdo->query("SELECT migration_name, executed_at FROM migrations ORDER BY executed_at DESC");
    while ($row = $migrations->fetch(PDO::FETCH_ASSOC)) {
        echo "- {$row['migration_name']} (executed: {$row['executed_at']})\n";
    }
    
    echo "\n=== Migration Complete ===\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>