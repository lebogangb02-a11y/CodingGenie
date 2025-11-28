<?php
// check_existing_chat.php
require_once 'config.php';

echo "<pre>";
echo "=== Checking Existing Chat System ===\n\n";

// Check existing chat files
$chat_files = [
    'chatbot.php',
    'chat_api.php', 
    'admin_chat.php',
    'support.php',
    'support-tickets.php'
];

foreach ($chat_files as $file) {
    if (file_exists($file)) {
        echo "📄 $file - EXISTS\n";
        
        // Check file size and basic info
        $size = filesize($file);
        $lines = count(file($file));
        echo "   Size: " . round($size/1024, 2) . " KB, Lines: $lines\n";
        
        // Read first few lines to understand structure
        $content = file_get_contents($file);
        if (strlen($content) > 500) {
            $preview = substr($content, 0, 500);
            echo "   Preview: " . htmlspecialchars($preview) . "...\n";
        }
        echo "\n";
    } else {
        echo "❌ $file - NOT FOUND\n\n";
    }
}

// Check database for existing chat tables
try {
    $stmt = $pdo->query("SHOW TABLES LIKE '%chat%'");
    $chat_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "🗄️ Existing Chat Tables:\n";
    if (empty($chat_tables)) {
        echo "   No chat tables found\n";
    } else {
        foreach ($chat_tables as $table) {
            echo "   - $table\n";
            
            // Show table structure
            $stmt2 = $pdo->query("DESCRIBE $table");
            $columns = $stmt2->fetchAll();
            foreach ($columns as $column) {
                echo "     * {$column['Field']} ({$column['Type']})\n";
            }
            echo "\n";
        }
    }
    
    // Check for support/ticket tables
    $stmt = $pdo->query("SHOW TABLES LIKE '%support%' OR TABLES LIKE '%ticket%'");
    $support_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($support_tables)) {
        echo "🗄️ Existing Support Tables:\n";
        foreach ($support_tables as $table) {
            echo "   - $table\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>