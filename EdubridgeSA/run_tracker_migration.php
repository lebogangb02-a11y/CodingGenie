<?php
/**
 * Multi-Application Tracker Migration Runner
 * This script runs the migration to add progress tracking capabilities
 */

require_once 'config.php';

echo "<h2>Multi-Application Tracker Migration</h2>\n";
echo "<p>Running migration to add progress tracking capabilities...</p>\n";

try {
    // Read the migration file
    $migrationFile = 'multi_application_tracker_migration.sql';
    
    if (!file_exists($migrationFile)) {
        throw new Exception("Migration file not found: $migrationFile");
    }
    
    $sql = file_get_contents($migrationFile);
    
    if ($sql === false) {
        throw new Exception("Failed to read migration file");
    }
    
    echo "<h3>Executing Migration...</h3>\n";
    
    // Split SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue; // Skip empty statements and comments
        }
        
        try {
            $pdo->exec($statement);
            $successCount++;
            echo "<p style='color: green;'>✓ Executed statement successfully</p>\n";
        } catch (PDOException $e) {
            $errorCount++;
            // Check if it's a "table already exists" or "column already exists" error
            if (strpos($e->getMessage(), 'already exists') !== false || 
                strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "<p style='color: orange;'>⚠ Skipped (already exists): " . substr($statement, 0, 50) . "...</p>\n";
            } else {
                echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>\n";
                echo "<p style='color: red;'>Statement: " . substr($statement, 0, 100) . "...</p>\n";
            }
        }
    }
    
    echo "<h3>Migration Summary</h3>\n";
    echo "<p><strong>Successful statements:</strong> $successCount</p>\n";
    echo "<p><strong>Errors/Skipped:</strong> $errorCount</p>\n";
    
    if ($errorCount === 0) {
        echo "<p style='color: green; font-weight: bold;'>✓ Migration completed successfully!</p>\n";
    } else {
        echo "<p style='color: orange; font-weight: bold;'>⚠ Migration completed with some warnings/errors</p>\n";
    }
    
    // Test the new tables
    echo "<h3>Testing New Tables...</h3>\n";
    
    $testTables = [
        'application_steps' => 'Application steps tracking',
        'application_progress_history' => 'Progress history logging',
        'application_next_steps' => 'Next steps recommendations'
    ];
    
    foreach ($testTables as $table => $description) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
            $count = $stmt->fetchColumn();
            echo "<p style='color: green;'>✓ $description ($table): $count records</p>\n";
        } catch (PDOException $e) {
            echo "<p style='color: red;'>✗ $description ($table): " . $e->getMessage() . "</p>\n";
        }
    }
    
    // Check if applications table has new columns
    echo "<h3>Checking Applications Table Updates...</h3>\n";
    
    $newColumns = ['progress_percentage', 'current_step', 'is_active', 'priority_order'];
    
    foreach ($newColumns as $column) {
        try {
            $stmt = $pdo->query("SELECT $column FROM applications LIMIT 1");
            echo "<p style='color: green;'>✓ Column '$column' exists in applications table</p>\n";
        } catch (PDOException $e) {
            echo "<p style='color: red;'>✗ Column '$column' missing from applications table</p>\n";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red; font-weight: bold;'>Migration failed: " . $e->getMessage() . "</p>\n";
}

echo "<hr>\n";
echo "<p><a href='student-dashboard.php'>← Back to Dashboard</a></p>\n";
?>