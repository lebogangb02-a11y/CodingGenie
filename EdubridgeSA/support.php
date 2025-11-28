<?php
/**
 * Student Chat Interface - EduBridgeSA
 * Phase 1: Basic Chat System
 */

// Start session and check if student is logged in
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

// Check if student is logged in
$student_logged_in = isset($_SESSION['student_logged_in']) && $_SESSION['student_logged_in'] === true;
$student_id = $_SESSION['student_id'] ?? null;
$student_email = $_SESSION['student_email'] ?? '';
$student_name = $_SESSION['student_name'] ?? 'Student';

if (!$student_logged_in) {
    // Redirect to login (using the correct file name with hyphen)
    $_SESSION['return_url'] = 'chatbot.php';
    header('Location: student-login.php');
    exit;
}

// Get or create conversation
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
    
    // Find active conversation or create new one
    $stmt = $pdo->prepare("
        SELECT id FROM chat_conversations 
        WHERE student_id = ? AND status = 'active' 
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute([$student_id]);
    $conversation = $stmt->fetch();
    
    if (!$conversation) {
        // Create new conversation
        $stmt = $pdo->prepare("
            INSERT INTO chat_conversations (student_id, student_email, student_name) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$student_id, $student_email, $student_name]);
        $conversation_id = $pdo->lastInsertId();
        
        // Add welcome message from bot
        $stmt = $pdo->prepare("
            INSERT INTO chat_messages (conversation_id, sender_type, sender_name, message_text) 
            VALUES (?, 'bot', 'EduBridge Assistant', ?)
        ");
        $welcome_message = "Hello $student_name! I'm here to help you with any questions about your application, documents, or general inquiries. How can I assist you today?";
        $stmt->execute([$conversation_id, $welcome_message]);
    } else {
        $conversation_id = $conversation['id'];
    }
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat with EduBridgeSA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/chatbot-styles.css" rel="stylesheet">
</head>
<body>
    <div class="chat-container">
        <!-- Chat Header -->
        <div class="chat-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div class="header-text">
                    <h5 class="mb-1">
                        <i class="bi bi-chat-dots me-2"></i>
                        EduBridgeSA Support
                    </h5>
                    <small>We're here to help you, <?php echo htmlspecialchars($student_name); ?>!</small>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="badge status-online" id="statusIndicator">
                        <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i>
                        Online
                    </span>
                    <a href="student-dashboard.php" class="btn btn-sm btn-light">
                        <i class="bi bi-arrow-left"></i> <span class="d-none d-sm-inline">Dashboard</span>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Chat Messages -->
        <div class="chat-messages" id="chatMessages">
            <!-- Messages will be loaded here via AJAX -->
            <div class="text-center text-muted mt-5">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">Loading messages...</span>
                </div>
                <p class="mt-2">Loading conversation...</p>
            </div>
        </div>
        
        <!-- Quick Replies -->
        <div class="quick-replies px-3 pt-2" id="quickReplies">
            <!-- Quick reply buttons will appear here -->
        </div>
        
        <!-- Chat Input -->
        <div class="chat-input">
            <form id="messageForm">
                <input type="hidden" id="conversationId" value="<?php echo $conversation_id; ?>">
                <div class="input-group">
                    <input type="text" class="form-control" id="messageInput" 
                           placeholder="Type your message here..." autocomplete="off">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send"></i> <span class="d-none d-sm-inline">Send</span>
                    </button>
                </div>
            </form>
            <div class="mt-2 text-center">
                <small class="text-muted">
                    <i class="bi bi-shield-check"></i>
                    Your conversations are secure and private
                </small>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let conversationId = <?php echo $conversation_id; ?>;
        
        // Load messages function
        function loadMessages() {
            fetch(`chat_api.php?action=get_messages&conversation_id=${conversationId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayMessages(data.messages);
                        displayQuickReplies(data.quick_replies);
                    }
                })
                .catch(error => console.error('Error:', error));
        }
        
        // Display messages in chat
        function displayMessages(messages) {
            const chatMessages = document.getElementById('chatMessages');
            chatMessages.innerHTML = '';
            
            messages.forEach(message => {
                const messageDiv = document.createElement('div');
                messageDiv.className = `message ${message.sender_type}`;
                
                const bubbleDiv = document.createElement('div');
                bubbleDiv.className = 'message-bubble';
                
                if (message.sender_type !== 'student') {
                    const senderName = document.createElement('div');
                    senderName.className = 'sender-name';
                    senderName.textContent = message.sender_name;
                    bubbleDiv.appendChild(senderName);
                }
                
                const messageText = document.createElement('div');
                messageText.textContent = message.message_text;
                bubbleDiv.appendChild(messageText);
                
                const messageTime = document.createElement('div');
                messageTime.className = 'message-time';
                messageTime.textContent = formatTime(message.created_at);
                bubbleDiv.appendChild(messageTime);
                
                messageDiv.appendChild(bubbleDiv);
                chatMessages.appendChild(messageDiv);
            });
            
            // Scroll to bottom
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        // Display quick reply buttons
        function displayQuickReplies(quickReplies) {
            const quickRepliesDiv = document.getElementById('quickReplies');
            quickRepliesDiv.innerHTML = '';
            
            if (quickReplies && quickReplies.length > 0) {
                quickReplies.forEach(reply => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'btn btn-outline-primary quick-reply-btn';
                    button.textContent = reply;
                    button.onclick = () => {
                        document.getElementById('messageInput').value = reply;
                        document.getElementById('messageForm').dispatchEvent(new Event('submit'));
                    };
                    quickRepliesDiv.appendChild(button);
                });
            }
        }
        
        // Format time
        function formatTime(timestamp) {
            const date = new Date(timestamp);
            return date.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit',
                hour12: true 
            });
        }
        
        // Send message
        document.getElementById('messageForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const messageInput = document.getElementById('messageInput');
            const message = messageInput.value.trim();
            
            if (message === '') return;
            
            // Add sending indicator
            const chatMessages = document.getElementById('chatMessages');
            const sendingDiv = document.createElement('div');
            sendingDiv.className = 'message student';
            sendingDiv.innerHTML = `
                <div class="message-bubble">
                    <div>${message}</div>
                    <div class="message-time">Sending...</div>
                </div>
            `;
            chatMessages.appendChild(sendingDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
            
            // Clear input
            messageInput.value = '';
            
            // Send to server
            fetch('chat_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'action': 'send_message',
                    'conversation_id': conversationId,
                    'message': message
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadMessages(); // Reload messages to get bot response
                } else {
                    alert('Error sending message: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error sending message. Please try again.');
            });
        });
        
        // Load messages on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadMessages();
            // Refresh messages every 5 seconds
            setInterval(loadMessages, 5000);
        });
        
        // Auto-focus input
        document.getElementById('messageInput').focus();
    </script>
</body>
</html>