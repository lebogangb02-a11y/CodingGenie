<?php
/**
 * Admin Chat Dashboard - EduBridgeSA
 * FIXED VERSION: Works with current chat_api.php
 */

// Universal authentication check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

// Check if admin is logged in
$admin_logged_in = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
if (!$admin_logged_in) {
    header('Location: admin_login.php');
    exit;
}

$admin_username = $_SESSION['admin_username'] ?? 'Admin';

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
    
    // Get active conversations
    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.student_name,
            c.student_email,
            c.status,
            c.created_at,
            c.updated_at,
            (SELECT COUNT(*) FROM chat_messages m WHERE m.conversation_id = c.id AND m.sender_type = 'student' AND m.is_read = FALSE) as unread_count
        FROM chat_conversations c
        WHERE c.status IN ('active', 'escalated')
        ORDER BY c.updated_at DESC
    ");
    $stmt->execute();
    $conversations = $stmt->fetchAll();
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Chat Dashboard - EduBridgeSA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .conversation-list {
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .conversation-item {
            border-left: 4px solid transparent;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .conversation-item:hover {
            background-color: #f8f9fa;
        }
        
        .conversation-item.active {
            border-left-color: #007bff;
            background-color: #e3f2fd;
        }
        
        .conversation-item.escalated {
            border-left-color: #dc3545;
        }
        
        .unread-badge {
            font-size: 0.7rem;
        }
        
        .chat-container {
            height: 70vh;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
        }
        
        .chat-messages {
            height: calc(100% - 120px);
            overflow-y: auto;
            padding: 1rem;
        }
        
        .message-bubble {
            max-width: 70%;
            padding: 0.75rem 1rem;
            border-radius: 1rem;
            margin-bottom: 0.5rem;
        }
        
        .student-message {
            background: #007bff;
            color: white;
            margin-left: auto;
            border-bottom-right-radius: 0.25rem;
        }
        
        .admin-message {
            background: #28a745;
            color: white;
            border-bottom-left-radius: 0.25rem;
        }
        
        .bot-message {
            background: #6c757d;
            color: white;
            border-bottom-left-radius: 0.25rem;
        }
        
        .sender-name {
            font-size: 0.75rem;
            font-weight: bold;
            margin-bottom: 0.25rem;
        }
        
        .message-time {
            font-size: 0.7rem;
            opacity: 0.8;
            margin-top: 0.25rem;
        }
        
        .typing-indicator {
            display: none;
            padding: 0.5rem 1rem;
            font-style: italic;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>
                        <i class="bi bi-chat-dots text-primary"></i>
                        Chat Support Dashboard
                    </h1>
                    <div>
                        <span class="badge bg-primary">Logged in as: <?php echo htmlspecialchars($admin_username); ?></span>
                        <a href="admin_dashboard.php" class="btn btn-outline-secondary ms-2">
                            <i class="bi bi-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Conversations List -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-list-ul"></i>
                            Active Conversations
                            <span class="badge bg-light text-dark ms-2"><?php echo count($conversations); ?></span>
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="conversation-list">
                            <?php if (empty($conversations)): ?>
                                <div class="text-center text-muted py-4">
                                    <i class="bi bi-chat-square fs-1"></i>
                                    <p class="mt-2">No active conversations</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($conversations as $conv): ?>
                                    <div class="conversation-item p-3 border-bottom <?php echo $conv['status'] === 'escalated' ? 'escalated' : ''; ?>" 
                                         data-conversation-id="<?php echo $conv['id']; ?>"
                                         data-student-name="<?php echo htmlspecialchars($conv['student_name']); ?>">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($conv['student_name']); ?></h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($conv['student_email']); ?></small>
                                            </div>
                                            <?php if ($conv['unread_count'] > 0): ?>
                                                <span class="badge bg-danger unread-badge"><?php echo $conv['unread_count']; ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                Last activity: <?php echo date('M j, g:i A', strtotime($conv['updated_at'])); ?>
                                            </small>
                                            <?php if ($conv['status'] === 'escalated'): ?>
                                                <span class="badge bg-warning float-end">Needs Help</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Chat Interface -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0" id="chatHeader">
                            <i class="bi bi-chat-left-text"></i>
                            Select a conversation to start chatting
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="chat-container">
                            <div class="chat-messages" id="adminChatMessages">
                                <div class="text-center text-muted mt-5">
                                    <i class="bi bi-chat-square-quote fs-1"></i>
                                    <p class="mt-2">Select a conversation from the list to view and respond to messages</p>
                                </div>
                            </div>
                            
                            <!-- Typing Indicator -->
                            <div class="typing-indicator" id="adminTypingIndicator">
                                <i class="bi bi-three-dots"></i> <?php echo htmlspecialchars($admin_username); ?> is typing...
                            </div>
                            
                            <div class="chat-input p-3 border-top" id="adminChatInput" style="display: none;">
                                <form id="adminMessageForm">
                                    <input type="hidden" id="adminConversationId">
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="adminMessageInput" 
                                               placeholder="Type your response..." autocomplete="off"
                                               aria-label="Type your message">
                                        <button type="submit" class="btn btn-success" id="adminSendButton">
                                            <i class="bi bi-send"></i> Send
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentConversationId = null;
        let currentStudentName = null;
        let isLoading = false;
        
        // Add click event listeners to conversation items
        document.addEventListener('DOMContentLoaded', function() {
            const conversationItems = document.querySelectorAll('.conversation-item');
            conversationItems.forEach(item => {
                item.addEventListener('click', function() {
                    const conversationId = this.getAttribute('data-conversation-id');
                    const studentName = this.getAttribute('data-student-name');
                    loadConversation(conversationId, studentName, this);
                });
            });
        });
        
        function loadConversation(conversationId, studentName, element) {
            if (isLoading) return;
            
            currentConversationId = conversationId;
            currentStudentName = studentName;
            
            // Update UI
            document.querySelectorAll('.conversation-item').forEach(item => {
                item.classList.remove('active');
            });
            element.classList.add('active');
            
            // Show loading
            const chatMessages = document.getElementById('adminChatMessages');
            chatMessages.innerHTML = `
                <div class="text-center text-muted mt-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading messages...</span>
                    </div>
                    <p class="mt-2">Loading conversation with ${studentName}...</p>
                </div>
            `;
            
            isLoading = true;
            
            // Load messages
            fetch(`chat_api.php?action=get_messages&conversation_id=${conversationId}&t=${Date.now()}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        displayAdminMessages(data.messages);
                        document.getElementById('adminChatInput').style.display = 'block';
                        document.getElementById('adminConversationId').value = conversationId;
                        
                        // Update header
                        document.getElementById('chatHeader').innerHTML = 
                            `<i class="bi bi-chat-left-text"></i> Chat with ${studentName}`;
                            
                        // Clear unread badge for this conversation
                        const unreadBadge = element.querySelector('.unread-badge');
                        if (unreadBadge) {
                            unreadBadge.remove();
                        }
                    } else {
                        showAdminError('Failed to load messages: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAdminError('Failed to load messages. Please check your connection.');
                })
                .finally(() => {
                    isLoading = false;
                });
        }
        
        function displayAdminMessages(messages) {
            const chatMessages = document.getElementById('adminChatMessages');
            chatMessages.innerHTML = '';
            
            if (!messages || messages.length === 0) {
                chatMessages.innerHTML = `
                    <div class="text-center text-muted mt-5">
                        <i class="bi bi-chat-square fs-1"></i>
                        <p class="mt-2">No messages in this conversation yet</p>
                    </div>
                `;
                return;
            }
            
            messages.forEach(message => {
                const messageDiv = document.createElement('div');
                messageDiv.className = 'd-flex mb-3';
                
                if (message.sender_type === 'student') {
                    messageDiv.classList.add('justify-content-end');
                }
                
                const bubbleDiv = document.createElement('div');
                bubbleDiv.className = `message-bubble ${
                    message.sender_type === 'student' ? 'student-message' : 
                    message.sender_type === 'admin' ? 'admin-message' : 'bot-message'
                }`;
                
                // Add sender name for non-student messages
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
        
        function showAdminError(message) {
            const chatMessages = document.getElementById('adminChatMessages');
            const errorDiv = document.createElement('div');
            errorDiv.className = 'alert alert-danger alert-dismissible fade show m-3';
            errorDiv.innerHTML = `
                <i class="bi bi-exclamation-triangle"></i> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            chatMessages.appendChild(errorDiv);
        }
        
        function formatTime(timestamp) {
            if (!timestamp) return 'Just now';
            
            const date = new Date(timestamp);
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);
            const diffHours = Math.floor(diffMs / 3600000);
            
            if (diffMins < 1) {
                return 'Just now';
            } else if (diffMins < 60) {
                return `${diffMins}m ago`;
            } else if (diffHours < 24) {
                return `${diffHours}h ago`;
            } else {
                return date.toLocaleDateString() + ' ' + date.toLocaleTimeString('en-US', { 
                    hour: '2-digit', 
                    minute: '2-digit',
                    hour12: true 
                });
            }
        }
        
        function showAdminTypingIndicator() {
            const indicator = document.getElementById('adminTypingIndicator');
            indicator.style.display = 'block';
            const chatMessages = document.getElementById('adminChatMessages');
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        function hideAdminTypingIndicator() {
            const indicator = document.getElementById('adminTypingIndicator');
            indicator.style.display = 'none';
        }
        
        // Send admin message - FIXED: Uses regular send_message action
        document.getElementById('adminMessageForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!currentConversationId) return;
            
            const messageInput = document.getElementById('adminMessageInput');
            const sendButton = document.getElementById('adminSendButton');
            const message = messageInput.value.trim();
            
            if (message === '') return;
            
            // Disable send button and show loading state
            sendButton.disabled = true;
            sendButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Sending...';
            
            // Show typing indicator
            showAdminTypingIndicator();
            
            // Send to server using the regular send_message action (admin status is handled in session)
            fetch('chat_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'action': 'send_message',
                    'conversation_id': currentConversationId,
                    'message': message
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                // Re-enable send button
                sendButton.disabled = false;
                sendButton.innerHTML = '<i class="bi bi-send"></i> Send';
                
                // Hide typing indicator
                hideAdminTypingIndicator();
                
                if (data.success) {
                    messageInput.value = '';
                    loadConversation(currentConversationId, currentStudentName, 
                        document.querySelector('.conversation-item.active'));
                } else {
                    showAdminError('Error sending message: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAdminError('Error sending message. Please check your connection and try again.');
                
                // Re-enable send button on error
                sendButton.disabled = false;
                sendButton.innerHTML = '<i class="bi bi-send"></i> Send';
                hideAdminTypingIndicator();
            });
        });
        
        // Handle Enter key for sending
        document.getElementById('adminMessageInput').addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                document.getElementById('adminMessageForm').dispatchEvent(new Event('submit'));
            }
        });
        
        // Auto-refresh current conversation every 5 seconds
        setInterval(() => {
            if (currentConversationId) {
                const activeItem = document.querySelector('.conversation-item.active');
                if (activeItem) {
                    loadConversation(currentConversationId, currentStudentName, activeItem);
                }
            }
        }, 5000);
    </script>
</body>
</html>