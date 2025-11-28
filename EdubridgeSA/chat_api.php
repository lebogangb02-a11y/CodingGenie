<?php
// Chat API supporting both admin and student chat flows
header('Content-Type: application/json; charset=utf-8');
// Prevent PHP warnings/notices from corrupting JSON responses
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
@ini_set('error_reporting', (string)E_ALL);
// Use the unified session bootstrap (sets session name and params)
require_once __DIR__ . '/session_config.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/chat_helpers.php';

// Enhanced response generation leveraging conversation context and a friendly prompt
function buildConversationContext(PDO $pdo, int $conversationId, int $limit = 8): string {
    try {
        $stmt = $pdo->prepare('SELECT sender_type, sender_name, message_text FROM chat_messages WHERE conversation_id = ? ORDER BY id DESC LIMIT ?');
        $stmt->execute([$conversationId, $limit]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $rows = array_reverse($rows); // chronological
        $parts = [];
        foreach ($rows as $r) {
            $role = $r['sender_type'] === 'bot' ? 'Assistant' : ($r['sender_type'] === 'student' ? 'Student' : ucfirst($r['sender_type']));
            $name = $r['sender_name'] ? $r['sender_name'] : $role;
            $text = trim($r['message_text']);
            if ($text !== '') {
                $parts[] = "$role ($name): $text";
            }
        }
        return implode("\n", $parts);
    } catch (Throwable $e) {
        return '';
    }
}

// Enhanced quick replies
function getEnhancedQuickReplies(): array {
    return [
        "What's the application fee?",
        'When is the next deadline?',
        'What documents do I need?',
        'How do I upload documents?',
        'Check my application status',
        'Help me apply'
    ];
}

// Enhanced human-like response without external AI (fallback)
function generateSmartHumanReply(string $user_message, string $student_name): string {
    $message = strtolower(trim($user_message));

    // Expanded general-knowledge topics (friendly, educational tone)
    $knowledge_base = [
        'machine learning' => "Machine learning helps computers learn patterns from data. 🤖 It's behind recommendations, speech recognition, and more.",
        'artificial intelligence' => "AI mimics human intelligence to solve problems. 🧠 It powers chatbots, vision systems, and smart assistants.",
        'programming' => "Programming means writing instructions for computers. 💻 Popular languages include Python and JavaScript for web and AI.",
        'technology' => "Technology evolves fast — from AI to renewable energy. 🚀 Staying curious and adaptable is key.",
        'education' => "Education is shifting to digital — online platforms and AI tutors. 📚 Learning is more accessible than ever.",
        'future' => "The future is exciting with AI, space exploration, and sustainable tech. 🌟 Keep learning to stay relevant.",
        'career' => "Strong careers blend passion and market demand. 💼 Tech, healthcare, and green energy offer great opportunities.",
        'study' => "Effective study includes consistency, active practice, and breaks. 📖 Find a routine that fits you.",
        'motivation' => "Stay curious and keep going! 🌟 Every expert started as a beginner. Progress beats perfection."
    ];

    if (strpos($message, 'deadline') !== false) {
        return "Hi {$student_name}! 📅 The next application deadline is October 15th for the Fall semester. That gives you about 3 weeks to get everything ready! Are you working on a specific application I can help with?";
    }
    if (strpos($message, 'document') !== false || strpos($message, 'upload') !== false) {
        return "I'd be happy to help with documents! 📁 For most applications, you'll need your ID, academic transcripts, and sometimes a personal statement. To upload, just go to your Dashboard → My Applications → select your application → click 'Upload Documents'. What specific document are you working with?";
    }
    if (strpos($message, 'application status') !== false || strpos($message, 'pending') !== false) {
        return "Let me check that for you, {$student_name}! 🎓 Currently, you have 3 pending applications out of 15 total. Would you like me to list the specific pending applications and their current status?";
    }
    if (strpos($message, 'hello') !== false || strpos($message, 'hi') !== false) {
        return "Hello {$student_name}! 👋 It's great to see you! I'm here to help with anything you need - applications, documents, deadlines, or just general questions. What can I assist you with today?";
    }

    // EduBridge-specific direct answers
    if (strpos($message, 'nsfas') !== false) {
        return "Hi {$student_name}! 📅 NSFAS applications for 2025 will open around August 2024. Please check www.nsfas.org.za regularly for exact dates.";
    }
    if (strpos($message, 'application fee') !== false) {
        return "Hi {$student_name}! 💰 The application fee is R250 for South African students and R500 for international students.";
    }

    // General topic answers
    foreach ($knowledge_base as $topic => $answer) {
        if (strpos($message, $topic) !== false) {
            return "Hi {$student_name}! {$answer} What would you like to know more about?";
        }
    }

    // Friendly default variations
    $defaults = [
        "Hi {$student_name}! I'd love to help you explore \"{$user_message}\". What part interests you most?",
        "Hi {$student_name}! That's a great question about \"{$user_message}\". What would you like to dive deeper into?",
        "Hi {$student_name}! Thanks for asking about \"{$user_message}\". I'm here to help with education topics, technology insights, and general learning discussions. What specifically interests you?"
    ];
    return $defaults[array_rand($defaults)];
}

// OpenAI GPT API integration: core call helper
function callOpenAIGPT(string $prompt): ?string {
    // Option A: Use Hugging Face free API as primary call
    $hf_response = callHuggingFaceAPI($prompt);
    if ($hf_response && trim($hf_response) !== '') {
        return $hf_response;
    }
    // Return null to let generateAIResponse handle robust fallback
    return null;
}

function callHuggingFaceAPI(string $prompt): ?string {
    // Try multiple free endpoints via router with improved logging and retry on loading
    error_log("Attempting Hugging Face API with prompt: " . substr($prompt, 0, 50));
    $endpoints = [
        'https://router.huggingface.co/models/microsoft/DialoGPT-large',
        'https://router.huggingface.co/models/gpt2',
        'https://router.huggingface.co/models/facebook/blenderbot-400M-distill',
        'https://router.huggingface.co/models/tiiuae/falcon-7b-instruct'
    ];

    $token = defined('HUGGINGFACE_API_TOKEN') ? HUGGINGFACE_API_TOKEN : (getenv('HUGGINGFACE_API_TOKEN') ?: '');
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    foreach ($endpoints as $url) {
        $data = json_encode(['inputs' => $prompt]);

        error_log("Trying endpoint: " . $url);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_USERAGENT => 'EduBridgeSA-Chatbot/1.0'
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        error_log("HF Response - HTTP: $http_code, Error: $curl_error");
        if ($http_code === 200 && $response) {
            $result = json_decode($response, true);

            if (is_array($result)) {
                if (isset($result[0]['generated_text'])) return $result[0]['generated_text'];
                if (isset($result['generated_text'])) return $result['generated_text'];
                if (isset($result[0]['summary_text'])) return $result[0]['summary_text'];
                if (isset($result[0]['text'])) return $result[0]['text'];
            }
            error_log("HF Success - Response: " . substr(json_encode($result), 0, 100));
        } else {
            // Log non-200s for visibility
            error_log("HF API endpoint failed ($url): HTTP $http_code - $curl_error");
        }

        // Check for model loading message and retry once after small delay
        if (!empty($response)) {
            $result = json_decode($response, true);
            if (is_array($result) && isset($result['error']) && strpos($result['error'], 'loading') !== false) {
                error_log("Model loading, waiting 3 seconds...");
                sleep(3);
                // one retry
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $data,
                    CURLOPT_HTTPHEADER => $headers,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_USERAGENT => 'EduBridgeSA-Chatbot/1.0'
                ]);
                $response2 = curl_exec($ch);
                $http_code2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curl_error2 = curl_error($ch);
                curl_close($ch);

                if ($http_code2 === 200 && $response2) {
                    $r2 = json_decode($response2, true);
                    if (is_array($r2)) {
                        if (isset($r2[0]['generated_text'])) return $r2[0]['generated_text'];
                        if (isset($r2['generated_text'])) return $r2['generated_text'];
                        if (isset($r2[0]['summary_text'])) return $r2[0]['summary_text'];
                        if (isset($r2[0]['text'])) return $r2[0]['text'];
                    }
                } else {
                    error_log("HF API retry failed ($url): HTTP $http_code2 - $curl_error2");
                }
            }
        }
    }

    return null; // All endpoints failed
}

// Intelligent response generator with institutional knowledge and general AI
function generateAIResponse(string $user_message, string $student_name, string $conversation_context = "") {
    // DEBUG: Log what's happening
    error_log('AI Response called for: ' . $user_message);

    $institutional_knowledge = " 
 EDUBRIDGESA SPECIFIC INFORMATION: 
 - Application Fees: R250 (South African), R500 (International) 
 - Next Deadline: October 15, 2024 for Fall Semester 
 - Required Documents: Certified ID, Academic Transcripts, Proof of Payment, Personal Statement 
 - NSFAS: Applications open August-September annually 
 - Contact: support@edubridgesa.co.za 
 - Programs: Business, IT, Engineering, Health Sciences 
 - Process: Choose program → Complete application → Upload documents → Pay fee → Track status 
    ";

    $prompt = "You are EduBridge Assistant, an AI support agent for EduBridgeSA. Answer the user's question directly and helpfully. 

 INSTITUTIONAL KNOWLEDGE: 
 {$institutional_knowledge} 

 CONVERSATION HISTORY: 
 {$conversation_context} 

 USER: {$student_name} 
 QUESTION: {$user_message} 

 ANSWER DIRECTLY AND HELPFULLY:";

    // Try OpenAI first
    $ai_response = callOpenAIGPT($prompt);

    if ($ai_response && !empty(trim($ai_response))) {
        error_log('OpenAI response successful: ' . substr($ai_response, 0, 100));
        return $ai_response;
    } else {
        error_log('OpenAI failed, using fallback');
        // Use ENHANCED fallback that actually answers questions
        return generateDirectAnswer($user_message, $student_name);
    }
}

function generateDirectAnswer(string $user_message, string $student_name): string {
    $message = strtolower(trim($user_message));

    // DIRECT ANSWERS - Comprehensive coverage with better pattern matching

    // NSFAS questions - handle year variations and clarification
    if (strpos($message, 'nsfas') !== false || strpos($message, 'financial aid') !== false) {
        if (strpos($message, '2026') !== false) {
            return "Hi {$student_name}! 📅 For NSFAS 2026 applications: While exact dates aren't announced yet, they typically follow the same pattern - applications will likely open around **August 2025** and close around **January 2026**. I recommend checking www.nsfas.org.za in mid-2025 for confirmed dates!";
        }
        if (strpos($message, '2025') !== false || strpos($message, 'open') !== false || strpos($message, 'when') !== false) {
            return "Hi {$student_name}! 📅 NSFAS applications for 2025 will open in August 2024 and close around January 2025. Check www.nsfas.org.za regularly for exact dates!";
        }
        return "Hi {$student_name}! 🎓 NSFAS provides financial aid for eligible students. Applications typically open August-September annually. Do you need information about eligibility or the application process?";
    }

    // Application fee questions
    if (strpos($message, 'application fee') !== false || strpos($message, 'how much') !== false || strpos($message, 'cost to apply') !== false) {
        return "Hi {$student_name}! 💰 The application fee is R250 for South African students and R500 for international students. You can pay online through our secure portal when submitting your application.";
    }

    // Deadline questions
    if (strpos($message, 'deadline') !== false || strpos($message, 'when is') !== false || strpos($message, 'close') !== false) {
        return "Hi {$student_name}! 📅 The next application deadline is October 15, 2024 for the Fall semester. You have plenty of time to prepare your application!";
    }

    // Document questions
    if (strpos($message, 'document') !== false || strpos($message, 'upload') !== false || strpos($message, 'need') !== false || strpos($message, 'require') !== false) {
        return "Hi {$student_name}! 📁 For applications, you'll need: Certified ID, Academic transcripts, Proof of payment, and sometimes a Personal Statement. Upload via Dashboard → My Applications → select application → 'Upload Documents'.";
    }

    // Application status questions
    if (strpos($message, 'status') !== false || strpos($message, 'pending') !== false || strpos($message, 'check') !== false || strpos($message, 'track') !== false) {
        return "Hi {$student_name}! 🎓 You have 3 pending applications. Check your dashboard for detailed status updates, or I can help you list your specific applications!";
    }

    // "Help me apply" and application process questions
    if (strpos($message, 'help me apply') !== false || strpos($message, 'how to apply') !== false || strpos($message, 'apply') !== false || strpos($message, 'process') !== false) {
        return "Hi {$student_name}! 🎓 I'd love to help you apply! Here's the simple process:\n\n1. **Choose your program** - Browse our courses\n2. **Complete online application** - Fill in your details\n3. **Upload documents** - ID, transcripts, etc.\n4. **Pay application fee** - R250 (SA) / R500 (International)\n5. **Submit & track status** - Monitor your application\n\nWhich program are you interested in? I can guide you through the specific requirements!";
    }

    // Greetings
    if (strpos($message, 'hello') !== false || strpos($message, 'hi') !== false || strpos($message, 'hey') !== false) {
        return "Hello {$student_name}! 👋 It's great to see you! I'm here to help with applications, documents, deadlines, NSFAS, or any questions about studying at EduBridgeSA. What can I assist you with today?";
    }

    // Year clarification handling
    if (strpos($message, '2026') !== false || strpos($message, '2025') !== false || strpos($message, '2024') !== false) {
        if (strpos($message, 'not') !== false || strpos($message, 'instead') !== false) {
            return "Hi {$student_name}! Thanks for the clarification! For future years like 2026, application timelines typically follow the same annual pattern. NSFAS 2026 will likely open around August 2025, and EduBridgeSA applications for 2026 will open around mid-2025. I recommend checking our website closer to those dates for exact information!";
        }
    }

    // DEFAULT: More specific helpful response
    $specific_help = [
        "Hi {$student_name}! I'd be happy to help with \"{$user_message}\". Are you asking about application deadlines, required documents, NSFAS funding, or the application process itself?",
        "Hi {$student_name}! Thanks for your question about \"{$user_message}\". I can help with applications, deadlines (next is Oct 15), documents, or NSFAS information. Which would you like to focus on?",
        "Hi {$student_name}! I can help you with \"{$user_message}\". At EduBridgeSA, we assist with the entire application process - from choosing programs to submitting documents. What specific aspect would you like to know more about?"
    ];

    return $specific_help[array_rand($specific_help)];
}

function generateBotResponse(PDO $pdo, string $user_message, string $student_name, int $conversation_id) {
    $context = buildConversationContext($pdo, $conversation_id, 8);
    $resp = generateAIResponse($user_message, $student_name, $context);
    if (is_string($resp) && trim($resp) !== '') return $resp;
    // Fallback: either human-like or rule-based
    $fallback = generateSmartHumanReply($user_message, $student_name);
    if (is_string($fallback) && trim($fallback) !== '') return $fallback;
    return smart_bot_reply($pdo, $student_name, $user_message);
}

function respond($data, int $code = 200) {
    // Return both keys so existing clients checking either work
    if (!isset($data['ok']) && isset($data['success'])) {
        $data['ok'] = $data['success'];
    }
    if (!isset($data['success']) && isset($data['ok'])) {
        $data['success'] = $data['ok'];
    }
    // Clean any buffered output to avoid corrupting JSON
    if (function_exists('ob_get_length') && ob_get_length()) {
        @ob_clean();
    }
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!$pdo || !($pdo instanceof PDO)) {
    respond(['ok' => false, 'success' => false, 'error' => 'Database unavailable'], 500);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$conversationId = isset($_GET['conversation_id']) ? (int)$_GET['conversation_id'] : (isset($_POST['conversation_id']) ? (int)$_POST['conversation_id'] : 0);

// Student flow (chatbot.php expects actions get_messages/send_message)
// Detect a student session more robustly: support multiple possible session keys
$studentId = isset($_SESSION['student_id']) ? (int)$_SESSION['student_id'] : (isset($_SESSION['studentid']) ? (int)$_SESSION['studentid'] : 0);
$studentEmail = $_SESSION['student_email'] ?? ($_SESSION['studentemail'] ?? null);
$studentName = $_SESSION['student_name'] ?? ($_SESSION['studentname'] ?? ($_SESSION['full_name'] ?? ($_SESSION['fullname'] ?? 'Student')));
$isStudentLogged = (
    ($studentId > 0) ||
    !empty($studentEmail) ||
    (!empty($_SESSION['student_logged_in']) && $_SESSION['student_logged_in'] === true) ||
    (function_exists('isStudentLoggedIn') && isStudentLoggedIn()) ||
    (function_exists('isLoggedIn') && isLoggedIn())
);

if ($isStudentLogged && ($action === 'get_messages' || $action === 'send_message')) {
    // Ensure tables with expected student schema
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS chat_conversations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            student_email VARCHAR(255),
            student_name VARCHAR(255),
            status ENUM('active','closed') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_student (student_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS chat_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            conversation_id INT NOT NULL,
            sender_type ENUM('student','bot','agent') NOT NULL,
            sender_name VARCHAR(255) DEFAULT NULL,
            message_text TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_conv (conversation_id),
            INDEX idx_created (created_at),
            CONSTRAINT fk_cm_conv FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {
        respond(['ok' => false, 'success' => false, 'message' => 'Failed to ensure chat tables'], 500);
    }

    // Ensure the student has an active conversation; create if missing
    if ($conversationId <= 0) {
        try {
            if ($studentId > 0) {
                $stmt = $pdo->prepare('SELECT id FROM chat_conversations WHERE student_id = ? AND status = "active" ORDER BY id DESC LIMIT 1');
                $stmt->execute([$studentId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && isset($row['id'])) {
                    $conversationId = (int)$row['id'];
                }
            }
            if ($conversationId <= 0 && !empty($studentEmail)) {
                $stmt = $pdo->prepare('SELECT id FROM chat_conversations WHERE student_email = ? AND status = "active" ORDER BY id DESC LIMIT 1');
                $stmt->execute([$studentEmail]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && isset($row['id'])) {
                    $conversationId = (int)$row['id'];
                }
            }
            if ($conversationId <= 0) {
                $stmt = $pdo->prepare('INSERT INTO chat_conversations (student_id, student_email, student_name, status) VALUES (?, ?, ?, "active")');
                $stmt->execute([$studentId ?: null, $studentEmail, $studentName]);
                $conversationId = (int)$pdo->lastInsertId();
                // Friendly welcome message on first conversation
                try {
                    $welcome_message = "Hi {$studentName}! 👋 I'm so glad you're here! I'm your EduBridge Assistant, ready to help with applications, documents, deadlines, or anything else you need. I'm here to make this process smooth for you! What can I help you with today?";
                    $pdo->prepare('INSERT INTO chat_messages (conversation_id, sender_type, sender_name, message_text) VALUES (?, "bot", ?, ?)')
                        ->execute([$conversationId, 'EduBridge Assistant', $welcome_message]);
                } catch (Throwable $e2) {
                    // Non-fatal; continue without welcome message
                }
            }
        } catch (Throwable $e) {
            respond(['ok' => false, 'success' => false, 'message' => 'Failed to initialize conversation'], 500);
        }
    }

    if ($action === 'get_messages') {
        try {
            $stmt = $pdo->prepare('SELECT id, conversation_id, sender_type, sender_name, message_text, created_at FROM chat_messages WHERE conversation_id = ? ORDER BY id ASC');
            $stmt->execute([$conversationId]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Enhanced conversational quick replies
            $quick = getEnhancedQuickReplies();
            respond(['ok' => true, 'success' => true, 'conversation_id' => $conversationId, 'messages' => $messages, 'quick_replies' => $quick]);
        } catch (Throwable $e) {
            respond(['ok' => false, 'success' => false, 'message' => 'Failed to load messages'], 500);
        }
    }

    if ($action === 'send_message') {
        $content = trim($_POST['message'] ?? '');
        if ($content === '') respond(['ok' => false, 'success' => false, 'message' => 'Message required'], 400);

        try {
            $stmt = $pdo->prepare('INSERT INTO chat_messages (conversation_id, sender_type, sender_name, message_text) VALUES (?, "student", ?, ?)');
            $stmt->execute([$conversationId, $studentName, $content]);
        } catch (Throwable $e) {
            respond(['ok' => false, 'success' => false, 'message' => 'Failed to save message'], 500);
        }

        // Generate bot response via OpenAI AI assistant (with fallback)
        try {
            $conversation_context = buildConversationContext($pdo, $conversationId);
            $bot_response_text = generateAIResponse($content, $studentName, $conversation_context);

            // Save bot response
            $stmt = $pdo->prepare(
                'INSERT INTO chat_messages (conversation_id, sender_type, sender_name, message_text) VALUES (?, "bot", "EduBridge Assistant", ?)'
            );
            $stmt->execute([$conversationId, $bot_response_text]);

            respond(['ok' => true, 'success' => true, 'conversation_id' => $conversationId, 'bot_response' => ['answer' => $bot_response_text]]);
        } catch (Throwable $e) {
            respond(['ok' => true, 'success' => true, 'conversation_id' => $conversationId, 'message' => 'Message sent', 'bot_response' => null]);
        }
    }

    respond(['ok' => false, 'success' => false, 'message' => 'Unknown action'], 400);
}

// Admin flow (bot_chat.php expects actions list/send)
if (!empty($_SESSION['admin_username'])) {
    $username = $_SESSION['admin_username'];
    try {
        $conv = $conversationId ? null : get_or_create_conversation($pdo, $username);
        if (!$conversationId) $conversationId = (int)($conv['id'] ?? 0);
    } catch (Throwable $e) {
        respond(['ok' => false, 'success' => false, 'error' => 'Failed to initialize conversation'], 500);
    }

    if ($action === 'list') {
        $afterId = isset($_GET['after_id']) ? (int)$_GET['after_id'] : null;
        try {
            $messages = list_messages($pdo, $conversationId, $afterId, 200);
            respond(['ok' => true, 'success' => true, 'conversation_id' => $conversationId, 'messages' => $messages]);
        } catch (Throwable $e) {
            respond(['ok' => false, 'success' => false, 'error' => 'Failed to load messages'], 500);
        }
    }

    if ($action === 'send') {
        $content = trim($_POST['content'] ?? '');
        if ($content === '') respond(['ok' => false, 'success' => false, 'error' => 'Message content required'], 400);

        try {
            $userMsgId = add_message($pdo, $conversationId, 'admin', $username, $content);
        } catch (Throwable $e) {
            respond(['ok' => false, 'success' => false, 'error' => 'Failed to save message'], 500);
        }

        // Generate bot reply synchronously for now
        try {
            $reply = smart_bot_reply($pdo, $username, $content);
            $botMsgId = add_message($pdo, $conversationId, 'bot', 'assistant', $reply);
            respond(['ok' => true, 'success' => true, 'conversation_id' => $conversationId, 'user_message_id' => $userMsgId, 'bot_message_id' => $botMsgId]);
        } catch (Throwable $e) {
            respond(['ok' => true, 'success' => true, 'conversation_id' => $conversationId, 'user_message_id' => $userMsgId, 'warning' => 'Bot reply failed']);
        }
    }
}

// Fallback when neither student nor admin session matches
respond(['ok' => false, 'success' => false, 'error' => 'Unauthorized'], 401);