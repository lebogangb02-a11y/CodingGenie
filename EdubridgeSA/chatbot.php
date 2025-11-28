<?php
/**
 * Student Chat Interface - EduBridgeSA
 * FINAL WORKING VERSION with Database
 */

// Use your enhanced session configuration
require_once __DIR__ . '/session_config.php';

// Check if student is logged in
$student_logged_in = isLoggedIn();
$student_id = $_SESSION['student_id'] ?? null;
$student_email = $_SESSION['student_email'] ?? '';
$student_name = $_SESSION['student_name'] ?? 'Student';

if (!$student_logged_in) {
    $_SESSION['return_url'] = 'chatbot.php';
    header('Location: student-login.php');
    exit;
}

// Get or create conversation
$conversation_id = 0;
try {
    require_once __DIR__ . '/config.php';
    
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    
    // Find active conversation
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
        $welcome_message = "Hello $student_name! 👋 I'm here to help you with any questions about your application, documents, or general inquiries. How can I assist you today?";
        $stmt->execute([$conversation_id, $welcome_message]);
        
        error_log("Created new conversation: " . $conversation_id . " for student: " . $student_name);
    } else {
        $conversation_id = $conversation['id'];
        error_log("Found existing conversation: " . $conversation_id . " for student: " . $student_name);
    }
    
} catch (PDOException $e) {
    error_log("Database error in chatbot.php: " . $e->getMessage());
    $conversation_id = 0;
} catch (Exception $e) {
    error_log("Configuration error in chatbot.php: " . $e->getMessage());
    $conversation_id = 0;
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
        
        <!-- Connection Status -->
        <div class="connection-status connection-online" id="connectionOnline">
            <i class="bi bi-check-circle"></i> Connected to EduBridgeSA Support
        </div>
        
        <div class="connection-status connection-error" id="connectionError" style="display: none;">
            <i class="bi bi-exclamation-triangle"></i> <span id="errorMessage">Connection error</span>
        </div>
        
        <!-- Chat Messages -->
        <div class="chat-messages" id="chatMessages">
            <div class="text-center text-muted mt-5" id="loadingIndicator">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading messages...</span>
                </div>
                <p class="mt-2">Loading conversation...</p>
            </div>
        </div>
        
        <!-- Quick Replies -->
        <div class="quick-replies" id="quickReplies">
            <!-- Quick reply buttons will appear here -->
        </div>
        
        <!-- Chat Input -->
        <div class="chat-input">
            <form id="messageForm">
                <input type="hidden" id="conversationId" value="<?php echo $conversation_id; ?>">
                <div class="input-group">
                    <input type="text" class="form-control" id="messageInput" 
                           placeholder="Type your message here..." autocomplete="off"
                           aria-label="Type your message" autofocus>
                    <button type="submit" class="btn btn-primary" id="sendButton">
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

    <script>
        // Configuration
        const CONFIG = {
            conversationId: <?php echo $conversation_id; ?>,
            apiEndpoint: 'chat_api.php'
        };

        // State
        let state = {
            isSending: false
        };

        // DOM Elements
        const elements = {
            chatMessages: document.getElementById('chatMessages'),
            messageInput: document.getElementById('messageInput'),
            sendButton: document.getElementById('sendButton'),
            statusIndicator: document.getElementById('statusIndicator'),
            connectionOnline: document.getElementById('connectionOnline'),
            connectionError: document.getElementById('connectionError'),
            errorMessage: document.getElementById('errorMessage'),
            loadingIndicator: document.getElementById('loadingIndicator'),
            quickReplies: document.getElementById('quickReplies'),
            messageForm: document.getElementById('messageForm')
        };

        // Update connection status
        function updateConnectionStatus(status, message = '') {
            if (status === 'online') {
                elements.connectionOnline.style.display = 'block';
                elements.connectionError.style.display = 'none';
                elements.statusIndicator.className = 'badge status-online';
                elements.statusIndicator.innerHTML = '<i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i> Online';
            } else {
                elements.connectionOnline.style.display = 'none';
                elements.connectionError.style.display = 'block';
                elements.errorMessage.textContent = message;
                elements.statusIndicator.className = 'badge bg-danger';
                elements.statusIndicator.innerHTML = '<i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i> Error';
            }
        }

        // Robust API helpers
        async function apiGet(action, params = {}) {
            const query = new URLSearchParams({ action, ...params });
            const res = await fetch(`${CONFIG.apiEndpoint}?${query.toString()}`);
            if (!res.ok) {
                const body = await res.text().catch(() => '');
                const err = new Error(`HTTP ${res.status} ${res.statusText}`);
                err.status = res.status;
                err.statusText = res.statusText;
                err.body = body;
                throw err;
            }
            return res.json();
        }

        async function apiPost(action, payload = {}) {
            const formData = new FormData();
            formData.append('action', action);
            Object.entries(payload).forEach(([k, v]) => formData.append(k, v));
            const res = await fetch(CONFIG.apiEndpoint, { method: 'POST', body: formData });
            if (!res.ok) {
                const body = await res.text().catch(() => '');
                const err = new Error(`HTTP ${res.status} ${res.statusText}`);
                err.status = res.status;
                err.statusText = res.statusText;
                err.body = body;
                throw err;
            }
            return res.json();
        }

        // Load messages
        async function loadMessages() {
            try {
                const data = await apiGet('get_messages', { conversation_id: CONFIG.conversationId });
                
                if (data.success) {
                    if (data.conversation_id && (!CONFIG.conversationId || CONFIG.conversationId === 0)) {
                        CONFIG.conversationId = data.conversation_id;
                        const hidden = document.getElementById('conversationId');
                        if (hidden) hidden.value = CONFIG.conversationId;
                        console.log('Conversation initialized:', CONFIG.conversationId);
                    }
                    displayMessages(data.messages);
                    displayQuickReplies(data.quick_replies);
                    updateConnectionStatus('online');
                } else {
                    throw new Error(data.message || 'Failed to load messages');
                }
                
            } catch (error) {
                console.error('Failed to load messages:', error);
                const details = error.body ? ` — ${error.body.substring(0, 200)}` : '';
                updateConnectionStatus('error', `Failed to load messages: ${error.message}${details}`);
            } finally {
                elements.loadingIndicator.style.display = 'none';
            }
        }

        // Display messages
        function displayMessages(messages) {
            if (!messages || messages.length === 0) {
                elements.chatMessages.innerHTML = `
                    <div class="text-center text-muted mt-5">
                        <i class="bi bi-chat-square-text display-4"></i>
                        <p class="mt-2">No messages yet. Start a conversation!</p>
                    </div>
                `;
                return;
            }
            
            elements.chatMessages.innerHTML = '';
            
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
                elements.chatMessages.appendChild(messageDiv);
            });
            
            // Scroll to bottom
            elements.chatMessages.scrollTop = elements.chatMessages.scrollHeight;
        }

        // Display quick replies
        function displayQuickReplies(quickReplies) {
            elements.quickReplies.innerHTML = '';
            
            if (quickReplies && quickReplies.length > 0) {
                const title = document.createElement('div');
                title.className = 'w-100 mb-2 small text-muted';
                title.textContent = 'Quick questions:';
                elements.quickReplies.appendChild(title);
                
                quickReplies.forEach(reply => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'btn btn-outline-primary quick-reply-btn';
                    button.textContent = reply;
                    button.onclick = () => sendQuickReply(reply);
                    elements.quickReplies.appendChild(button);
                });
            }
        }

        // Send quick reply
        async function sendQuickReply(replyText) {
            if (state.isSending) return;
            
            state.isSending = true;
            const quickReplyButtons = document.querySelectorAll('.quick-reply-btn');
            
            quickReplyButtons.forEach(btn => {
                btn.disabled = true;
            });
            
            // Add message to UI immediately
            addMessageToUI(replyText, 'student', 'Just now');
            
            try {
                const data = await apiPost('send_message', { conversation_id: CONFIG.conversationId, message: replyText });
                
                if (data.success) {
                    if (data.conversation_id && (!CONFIG.conversationId || CONFIG.conversationId === 0)) {
                        CONFIG.conversationId = data.conversation_id;
                        const hidden = document.getElementById('conversationId');
                        if (hidden) hidden.value = CONFIG.conversationId;
                    }
                    if (data.bot_response) {
                        // Add bot response after a short delay
                        setTimeout(() => {
                            addMessageToUI(data.bot_response.answer, 'bot', 'Just now');
                        }, 1000);
                    }
                    updateConnectionStatus('online');
                } else {
                    throw new Error(data.message || 'Failed to send message');
                }
                
            } catch (error) {
                console.error('Error sending quick reply:', error);
                const details = error.body ? ` — ${error.body.substring(0, 200)}` : '';
                updateConnectionStatus('error', `Failed to send message: ${error.message}${details}`);
            } finally {
                state.isSending = false;
                
                quickReplyButtons.forEach(btn => {
                    btn.disabled = false;
                });
            }
        }

        // Send message
        async function sendMessage(messageText) {
            if (state.isSending || !messageText.trim()) return;
            
            state.isSending = true;
            
            elements.sendButton.disabled = true;
            elements.sendButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';
            
            // Add user message to UI immediately
            addMessageToUI(messageText, 'student', 'Just now');
            
            elements.messageInput.value = '';
            
            try {
                const data = await apiPost('send_message', { conversation_id: CONFIG.conversationId, message: messageText });
                
                if (data.success) {
                    if (data.conversation_id && (!CONFIG.conversationId || CONFIG.conversationId === 0)) {
                        CONFIG.conversationId = data.conversation_id;
                        const hidden = document.getElementById('conversationId');
                        if (hidden) hidden.value = CONFIG.conversationId;
                    }
                    if (data.bot_response) {
                        // Add bot response after a short delay
                        setTimeout(() => {
                            addMessageToUI(data.bot_response.answer, 'bot', 'Just now');
                        }, 1000);
                    }
                    updateConnectionStatus('online');
                } else {
                    throw new Error(data.message || 'Failed to send message');
                }
                
            } catch (error) {
                console.error('Error sending message:', error);
                const details = error.body ? ` — ${error.body.substring(0, 200)}` : '';
                updateConnectionStatus('error', `Failed to send message: ${error.message}${details}`);
            } finally {
                state.isSending = false;
                elements.sendButton.disabled = false;
                elements.sendButton.innerHTML = '<i class="bi bi-send"></i> Send';
            }
        }

        // Helper functions
        function addMessageToUI(text, sender, timeText = 'Just now') {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${sender}`;
            
            const bubbleDiv = document.createElement('div');
            bubbleDiv.className = 'message-bubble';
            
            if (sender !== 'student') {
                const senderName = document.createElement('div');
                senderName.className = 'sender-name';
                senderName.textContent = sender === 'bot' ? 'EduBridge Assistant' : 'Support Agent';
                bubbleDiv.appendChild(senderName);
            }
            
            const messageText = document.createElement('div');
            messageText.textContent = text;
            bubbleDiv.appendChild(messageText);
            
            const messageTime = document.createElement('div');
            messageTime.className = 'message-time';
            messageTime.textContent = timeText;
            bubbleDiv.appendChild(messageTime);
            
            messageDiv.appendChild(bubbleDiv);
            elements.chatMessages.appendChild(messageDiv);
            elements.chatMessages.scrollTop = elements.chatMessages.scrollHeight;
        }

        function formatTime(timestamp) {
            if (!timestamp) return 'Just now';
            
            const date = new Date(timestamp);
            const now = new Date();
            const diffMs = now - date;
            const diffSecs = Math.floor(diffMs / 1000);
            const diffMins = Math.floor(diffMs / 60000);
            
            if (diffSecs < 10) return 'Just now';
            if (diffSecs < 60) return `${diffSecs}s ago`;
            if (diffMins < 60) return `${diffMins}m ago`;
            
            return date.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit',
                hour12: true 
            });
        }

        // Event Listeners
        elements.messageForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const message = elements.messageInput.value.trim();
            if (message) {
                sendMessage(message);
            }
        });

        // Initialize chat
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Chat initializing with conversation ID:', CONFIG.conversationId);
            
            // Always try to load messages; API can create conversation if needed
            loadMessages();
            // Refresh messages every 3 seconds
            setInterval(loadMessages, 3000);
        });
    </script>
</body>
</html>