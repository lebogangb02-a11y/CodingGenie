<?php
// Chat helpers for conversation and message DB operations
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/session_config.php';
require_once __DIR__ . '/config.php';

function ensure_chat_tables(PDO $pdo): void
{
    // Create chat tables if they don't exist. Uses a generic schema compatible
    // with most prior implementations of chat_conversations and chat_messages.
    $pdo->exec("CREATE TABLE IF NOT EXISTS chat_conversations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) DEFAULT NULL,
        owner_username VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_owner (owner_username),
        INDEX idx_updated (updated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS chat_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL,
        sender_type ENUM('admin','bot','user') NOT NULL,
        sender_username VARCHAR(100) DEFAULT NULL,
        content TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_conv (conversation_id),
        INDEX idx_created (created_at),
        CONSTRAINT fk_chat_messages_conv FOREIGN KEY (conversation_id)
            REFERENCES chat_conversations(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function get_or_create_conversation(PDO $pdo, string $ownerUsername, ?string $title = null): array
{
    ensure_chat_tables($pdo);
    $stmt = $pdo->prepare('SELECT id, title, owner_username, created_at, updated_at FROM chat_conversations WHERE owner_username = ? ORDER BY updated_at DESC LIMIT 1');
    $stmt->execute([$ownerUsername]);
    $conv = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($conv) return $conv;

    $stmt = $pdo->prepare('INSERT INTO chat_conversations (title, owner_username) VALUES (?, ?)');
    $stmt->execute([$title ?? 'Assistant Chat', $ownerUsername]);
    $id = (int)$pdo->lastInsertId();
    return [
        'id' => $id,
        'title' => $title ?? 'Assistant Chat',
        'owner_username' => $ownerUsername,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ];
}

function add_message(PDO $pdo, int $conversationId, string $senderType, ?string $senderUsername, string $content): int
{
    $stmt = $pdo->prepare('INSERT INTO chat_messages (conversation_id, sender_type, sender_username, content) VALUES (?, ?, ?, ?)');
    $stmt->execute([$conversationId, $senderType, $senderUsername, $content]);
    $pdo->prepare('UPDATE chat_conversations SET updated_at = NOW() WHERE id = ?')->execute([$conversationId]);
    return (int)$pdo->lastInsertId();
}

function list_messages(PDO $pdo, int $conversationId, ?int $afterId = null, int $limit = 100): array
{
    if ($afterId) {
        $stmt = $pdo->prepare('SELECT id, conversation_id, sender_type, sender_username, content, created_at FROM chat_messages WHERE conversation_id = ? AND id > ? ORDER BY id ASC LIMIT ?');
        $stmt->execute([$conversationId, $afterId, $limit]);
    } else {
        $stmt = $pdo->prepare('SELECT id, conversation_id, sender_type, sender_username, content, created_at FROM chat_messages WHERE conversation_id = ? ORDER BY id ASC LIMIT ?');
        $stmt->execute([$conversationId, $limit]);
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function smart_bot_reply(PDO $pdo, string $username, string $userMessage): string
{
    $q = strtolower($userMessage);

    // Quick keyword-based intent detection with graceful DB fallbacks
    if (strpos($q, 'deadline') !== false || strpos($q, 'due date') !== false) {
        try {
            $stmt = $pdo->query("SELECT MIN(application_deadline) AS next_deadline FROM applications WHERE application_deadline IS NOT NULL");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!empty($row['next_deadline'])) {
                return 'The next application deadline is ' . date('M j, Y', strtotime($row['next_deadline'])) . '. Would you like a checklist?';
            }
        } catch (Throwable $e) {
        }
        return 'I couldn’t find a deadline in the system. You can check the Dashboard → Pending Applications for more details.';
    }

    if (strpos($q, 'document') !== false || strpos($q, 'upload') !== false) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) AS docs FROM document_uploads");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (isset($row['docs'])) {
                return 'There are currently ' . (int)$row['docs'] . ' uploaded documents. You can manage them in Manage Students → Documents.';
            }
        } catch (Throwable $e) {
        }
        return 'If you need to upload or verify documents, go to Manage Students and open the student profile, then use Document Upload.';
    }

    if (strpos($q, 'application') !== false || strpos($q, 'apply') !== false) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) AS total, SUM(status='pending') AS pending, SUM(status='approved') AS approved FROM applications");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return 'Applications: total ' . (int)$row['total'] . ', pending ' . (int)$row['pending'] . ', approved ' . (int)$row['approved'] . '. Need a list of pending?';
            }
        } catch (Throwable $e) {
        }
        return 'You can view applications and their statuses from the Admin Dashboard. Ask me “show pending list” for more.';
    }

    if (strpos($q, 'help') !== false || strpos($q, 'quick reply') !== false) {
        return 'Try questions like: “Next deadline?”, “Document requirements?”, “Pending applications count” or “How to upload?”.';
    }

    // Default response
    return 'I’m here to help with applications, documents, and deadlines. Ask me about counts, upcoming dates, or where to find actions.';
}
