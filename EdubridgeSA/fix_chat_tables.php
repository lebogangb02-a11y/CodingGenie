<?php
/**
 * Fix Chat Database Tables
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

    // Drop tables if they exist
    $pdo->exec("DROP TABLE IF EXISTS chat_messages");
    $pdo->exec("DROP TABLE IF EXISTS chat_conversations");

    // Create conversations table
    $pdo->exec("
        CREATE TABLE chat_conversations (
            id INT PRIMARY KEY AUTO_INCREMENT,
            student_id INT NOT NULL,
            student_email VARCHAR(255) NOT NULL,
            student_name VARCHAR(255) NOT NULL,
            status ENUM('active', 'escalated', 'resolved') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_student_id (student_id),
            INDEX idx_status (status)
        )
    ");

    // Create messages table
    $pdo->exec("
        CREATE TABLE chat_messages (
            id INT PRIMARY KEY AUTO_INCREMENT,
            conversation_id INT NOT NULL,
            sender_type ENUM('student', 'bot', 'admin') NOT NULL,
            sender_name VARCHAR(255),
            message_text TEXT NOT NULL,
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE,
            INDEX idx_conversation_id (conversation_id),
            INDEX idx_created (created_at)
        )
    ");

    echo "✅ Chat database tables created successfully!\n";

} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}
?>