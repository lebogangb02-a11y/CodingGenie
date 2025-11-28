<?php
// Simple migration to ensure users.verified_at column exists
require_once __DIR__ . '/../config.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    // Check if column exists
    $checkSql = "SELECT COUNT(*) as cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'users' AND COLUMN_NAME = 'verified_at'";
    $stmt = $pdo->prepare($checkSql);
    $stmt->execute([DB_NAME]);
    $exists = (int)$stmt->fetchColumn() > 0;

    if ($exists) {
        echo "Column users.verified_at already exists.\n";
    } else {
        // Add the column
        $pdo->exec("ALTER TABLE users ADD COLUMN verified_at TIMESTAMP NULL DEFAULT NULL");
        echo "Added column users.verified_at (TIMESTAMP NULL).\n";
    }
    echo "Migration completed successfully.\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        error_log("Migration error (add_users_verified_at): " . $e->getMessage());
    }
    http_response_code(500);
}
?>