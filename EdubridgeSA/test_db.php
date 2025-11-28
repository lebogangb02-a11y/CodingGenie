<?php
require_once 'config.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    echo "✅ Database connected successfully!\n";
    
    // Check if tables exist
    $tables = $pdo->query("SHOW TABLES LIKE 'chat_%'")->fetchAll();
    if (count($tables) > 0) {
        echo "✅ Chat tables exist!\n";
        foreach ($tables as $table) {
            echo " - " . current($table) . "\n";
        }
    } else {
        echo "❌ No chat tables found!\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}
?>