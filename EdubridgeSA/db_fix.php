<?php
/**
 * Database Fix Script for EduBridgeSA Chat System
 * Run this once to fix any database issues
 */

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

    echo "🔧 Fixing database tables...\n";

    // Fix chat_messages table if needed
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chat_messages (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            conversation_id INT(11) NOT NULL,
            sender_type ENUM('student','admin','bot') NOT NULL,
            sender_name VARCHAR(255) NOT NULL,
            message_text TEXT NOT NULL,
            message_type ENUM('text','file','quick_reply') DEFAULT 'text',
            file_path VARCHAR(500) DEFAULT NULL,
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_conversation (conversation_id),
            INDEX idx_created (created_at)
        )
    ");

    // Fix chat_conversations table if needed
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chat_conversations (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            student_id INT(11) NOT NULL,
            student_email VARCHAR(255) NOT NULL,
            student_name VARCHAR(255) NOT NULL,
            status ENUM('active','resolved','escalated') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_student (student_id),
            INDEX idx_status (status)
        )
    ");

    echo "✅ Database tables fixed successfully!\n";

} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}
?>