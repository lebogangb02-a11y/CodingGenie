<?php
require_once 'config.php';

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    echo "Connected to database successfully\n";

    // First, check the structure of the applications table
    echo "Checking applications table structure...\n";
    $stmt = $pdo->query("DESCRIBE applications");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Applications table columns:\n";
    foreach ($columns as $column) {
        echo "  - " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }

    // Get all data from applications table to check for Chinese characters
    echo "\nChecking applications table for Chinese characters...\n";
    $stmt = $pdo->query("SELECT id, reference_number, email_address, first_name, last_name, created_at FROM applications LIMIT 10");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $found = false;

    foreach ($results as $row) {
        foreach ($row as $key => $value) {
            if (is_string($value) && preg_match('/[\x{4e00}-\x{9fff}]/u', $value)) {
                echo "Found Chinese characters in $key: $value\n";
                $found = true;
            }
        }
    }

    if (!$found) {
        echo "No Chinese characters found in applications table\n";
    }

    // Check if there are any email templates stored in database
    $tables = ['applications', 'students', 'admin_users'];

    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                echo "Table $table exists\n";

                // Get column names
                $stmt = $pdo->query("DESCRIBE $table");
                $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

                // Check for any text fields that might contain Chinese characters
                foreach ($columns as $column) {
                    $stmt = $pdo->query("SELECT $column FROM $table WHERE $column REGEXP '[一-龯]' LIMIT 5");
                    $chineseResults = $stmt->fetchAll(PDO::FETCH_COLUMN);

                    if (!empty($chineseResults)) {
                        echo "Found Chinese characters in $table.$column:\n";
                        foreach ($chineseResults as $result) {
                            echo "  - $result\n";
                        }
                    }
                }
            }
        } catch (Exception $e) {
            echo "Error checking table $table: " . $e->getMessage() . "\n";
        }
    }

    echo "Database check completed\n";
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
