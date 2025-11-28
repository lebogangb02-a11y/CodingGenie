<?php
// enhanced_bot_engine.php
/**
 * Enhanced Bot Engine for EduBridgeSA
 * Phase 2: Advanced Intelligence
 */

class EnhancedBotEngine {
    private $connection;
    private $min_confidence = 0.6;
    
    public function __construct($db_connection) {
        $this->connection = $db_connection;
    }
    
    /**
     * Process student message with advanced matching
     */
    public function processMessage($user_message, $conversation_id, $student_context = []) {
        // Clean and prepare message
        $clean_message = $this->preprocessMessage($user_message);
        
        // Get conversation context
        $context = $this->getConversationContext($conversation_id);
        
        // Multiple matching strategies
        $matches = [
            'exact' => $this->exactMatch($clean_message),
            'keyword' => $this->keywordMatch($clean_message),
            'semantic' => $this->semanticMatch($clean_message, $context),
            'contextual' => $this->contextualMatch($clean_message, $context, $student_context)
        ];
        
        // Find best match
        $best_match = $this->selectBestMatch($matches);
        
        return $best_match;
    }
    
    /**
     * Preprocess message for better matching
     */
    private function preprocessMessage($message) {
        // Convert to lowercase
        $message = strtolower(trim($message));
        
        // Remove punctuation except for question marks
        $message = preg_replace('/[^\w\s?]/', '', $message);
        
        // Expand contractions
        $contractions = [
            "what's" => "what is",
            "i'm" => "i am",
            "don't" => "do not",
            "can't" => "cannot",
            "won't" => "will not",
            "it's" => "it is"
        ];
        
        $message = str_replace(array_keys($contractions), array_values($contractions), $message);
        
        return $message;
    }
    
    /**
     * Exact match with FAQ questions
     */
    private function exactMatch($message) {
        $query = "SELECT *, 
                  (CASE WHEN LOWER(question) = LOWER(:message) THEN 1.0 
                   ELSE 0.0 END) as confidence
                  FROM faq_knowledge_base 
                  WHERE is_active = 1 
                  ORDER BY confidence DESC 
                  LIMIT 1";
        
        $stmt = $this->connection->prepare($query);
        $stmt->bindParam(':message', $message);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['confidence'] > 0) {
            return [
                'answer' => $result['answer'],
                'confidence' => $result['confidence'],
                'type' => 'exact',
                'faq_id' => $result['id']
            ];
        }
        
        return null;
    }
    
    /**
     * Advanced keyword matching with confidence scoring
     */
    private function keywordMatch($message) {
        // Get all active FAQs
        $query = "SELECT * FROM faq_knowledge_base WHERE is_active = 1";
        $stmt = $this->connection->query($query);
        $faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $best_match = null;
        $highest_confidence = 0;
        
        foreach ($faqs as $faq) {
            $keywords = json_decode($faq['keywords'], true) ?? [];
            $confidence = $this->calculateKeywordConfidence($message, $keywords);
            
            // Boost confidence for question patterns
            if ($this->isQuestion($message)) {
                $confidence *= 1.2;
            }
            
            if ($confidence > $highest_confidence && $confidence > 0.3) {
                $highest_confidence = $confidence;
                $best_match = [
                    'answer' => $faq['answer'],
                    'confidence' => min($confidence, 1.0),
                    'type' => 'keyword',
                    'faq_id' => $faq['id'],
                    'matched_keywords' => array_slice($keywords, 0, 3)
                ];
            }
        }
        
        return $best_match;
    }
    
    /**
     * Calculate confidence based on keyword matches
     */
    private function calculateKeywordConfidence($message, $keywords) {
        $matches = 0;
        $total_keywords = count($keywords);
        
        if ($total_keywords === 0) return 0;
        
        foreach ($keywords as $keyword) {
            if (stripos($message, $keyword) !== false) {
                $matches++;
            }
        }
        
        // Calculate base confidence
        $base_confidence = $matches / $total_keywords;
        
        // Boost for exact matches
        if ($matches > 0) {
            $base_confidence = min($base_confidence * (1 + ($matches * 0.1)), 1.0);
        }
        
        return $base_confidence;
    }
    
    /**
     * Semantic matching for similar meaning
     */
    private function semanticMatch($message, $context) {
        // Simple semantic matching using word relationships
        $word_groups = [
            'application' => ['apply', 'submission', 'submit', 'application', 'apply now'],
            'status' => ['status', 'progress', 'where is', 'check status', 'track'],
            'document' => ['document', 'upload', 'file', 'pdf', 'submit document'],
            'deadline' => ['deadline', 'closing date', 'last date', 'when', 'due date'],
            'payment' => ['payment', 'pay', 'fee', 'cost', 'price']
        ];
        
        $best_confidence = 0;
        $best_category = null;
        
        foreach ($word_groups as $category => $words) {
            $matches = 0;
            foreach ($words as $word) {
                if (stripos($message, $word) !== false) {
                    $matches++;
                }
            }
            
            $confidence = $matches / count($words);
            if ($confidence > $best_confidence) {
                $best_confidence = $confidence;
                $best_category = $category;
            }
        }
        
        if ($best_confidence > 0.5) {
            // Get FAQ from this category
            $query = "SELECT * FROM faq_knowledge_base 
                      WHERE category = :category AND is_active = 1 
                      ORDER BY confidence_score DESC 
                      LIMIT 1";
            $stmt = $this->connection->prepare($query);
            $stmt->bindParam(':category', $best_category);
            $stmt->execute();
            
            $faq = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($faq) {
                return [
                    'answer' => $faq['answer'],
                    'confidence' => $best_confidence,
                    'type' => 'semantic',
                    'faq_id' => $faq['id'],
                    'category' => $best_category
                ];
            }
        }
        
        return null;
    }
    
    /**
     * Contextual matching based on conversation history
     */
    private function contextualMatch($message, $context, $student_context) {
        // If we have recent context, use it to improve matching
        if (!empty($context['last_topics'])) {
            $recent_topics = $context['last_topics'];
            
            // Boost FAQs related to recent topics
            $query = "SELECT * FROM faq_knowledge_base 
                      WHERE category IN ('" . implode("','", $recent_topics) . "') 
                      AND is_active = 1 
                      ORDER BY confidence_score DESC";
            $stmt = $this->connection->query($query);
            $contextual_faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($contextual_faqs as $faq) {
                $keywords = json_decode($faq['keywords'], true) ?? [];
                $confidence = $this->calculateKeywordConfidence($message, $keywords);
                
                // Boost confidence for contextual relevance
                $confidence *= 1.3;
                
                if ($confidence > 0.4) {
                    return [
                        'answer' => $faq['answer'],
                        'confidence' => min($confidence, 1.0),
                        'type' => 'contextual',
                        'faq_id' => $faq['id']
                    ];
                }
            }
        }
        
        return null;
    }
    
    /**
     * Select the best match from all strategies
     */
    private function selectBestMatch($matches) {
        $best_confidence = 0;
        $best_match = null;
        
        foreach ($matches as $type => $match) {
            if ($match && $match['confidence'] > $best_confidence) {
                $best_confidence = $match['confidence'];
                $best_match = $match;
            }
        }
        
        return $best_match;
    }
    
    /**
     * Get conversation context
     */
    private function getConversationContext($conversation_id) {
        $query = "SELECT cm.message_text, cm.sender_type, cm.created_at 
                  FROM chat_messages cm 
                  WHERE cm.conversation_id = :conversation_id 
                  ORDER BY cm.created_at DESC 
                  LIMIT 10";
        
        $stmt = $this->connection->prepare($query);
        $stmt->bindParam(':conversation_id', $conversation_id);
        $stmt->execute();
        
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $topics = [];
        
        // Simple topic extraction from recent messages
        foreach ($messages as $message) {
            $text = strtolower($message['message_text']);
            if (strpos($text, 'application') !== false) $topics[] = 'applications';
            if (strpos($text, 'document') !== false) $topics[] = 'documents';
            if (strpos($text, 'status') !== false) $topics[] = 'status';
            if (strpos($text, 'deadline') !== false) $topics[] = 'deadlines';
            if (strpos($text, 'payment') !== false) $topics[] = 'payments';
        }
        
        return [
            'last_topics' => array_slice(array_unique($topics), 0, 3),
            'message_count' => count($messages)
        ];
    }
    
    /**
     * Check if message is a question
     */
    private function isQuestion($message) {
        $question_words = ['what', 'when', 'where', 'why', 'how', 'who', 'which', 'can', 'could', 'will', 'would'];
        $first_word = strtolower(explode(' ', $message)[0]);
        
        return in_array($first_word, $question_words) || strpos($message, '?') !== false;
    }
    
    /**
     * Generate quick reply suggestions
     */
    public function getQuickReplies($conversation_id, $current_topic = null) {
        $query = "SELECT question, category 
                  FROM faq_knowledge_base 
                  WHERE is_active = 1 
                  AND (:topic IS NULL OR category = :topic)
                  ORDER BY confidence_score DESC 
                  LIMIT 4";
        
        $stmt = $this->connection->prepare($query);
        $stmt->bindParam(':topic', $current_topic);
        $stmt->execute();
        
        $suggestions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $quick_replies = [];
        foreach ($suggestions as $suggestion) {
            $quick_replies[] = [
                'text' => $suggestion['question'],
                'category' => $suggestion['category']
            ];
        }
        
        return $quick_replies;
    }
}
?>