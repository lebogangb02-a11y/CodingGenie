<?php

/**
 * Knowledge Base Manager - EduBridgeSA
 * Complete FAQ management with learning capabilities
 */

class KnowledgeBaseManager
{
    private $connection;

    public function __construct($db_connection)
    {
        $this->connection = $db_connection;
    }

    /**
     * Add new FAQ to knowledge base
     */
    public function addFAQ($question, $answer, $category = 'general', $keywords = [], $quick_replies = [], $escalation_required = false)
    {
        $query = "INSERT INTO faq_knowledge_base 
                  (question, answer, category, keywords, quick_replies, escalation_required, confidence_threshold) 
                  VALUES (:question, :answer, :category, :keywords, :quick_replies, :escalation_required, 0.7)";

        $stmt = $this->connection->prepare($query);
        $stmt->bindParam(':question', $question);
        $stmt->bindParam(':answer', $answer);
        $stmt->bindParam(':category', $category);
        $stmt->bindParam(':keywords', json_encode($keywords));
        $stmt->bindParam(':quick_replies', json_encode($quick_replies));
        $stmt->bindParam(':escalation_required', $escalation_required, PDO::PARAM_BOOL);

        return $stmt->execute();
    }

    /**
     * Train bot from admin responses
     */
    public function learnFromAdminResponse($conversation_id, $admin_message)
    {
        try {
            // Get the student's question that prompted this admin response
            $query = "SELECT message_text FROM chat_messages 
                      WHERE conversation_id = :conversation_id 
                      AND sender_type = 'student' 
                      ORDER BY created_at DESC 
                      LIMIT 1";

            $stmt = $this->connection->prepare($query);
            $stmt->bindParam(':conversation_id', $conversation_id);
            $stmt->execute();

            $student_question = $stmt->fetch(PDO::FETCH_COLUMN);

            if ($student_question && strlen(trim($student_question)) > 10) {
                // Extract keywords from student question
                $keywords = $this->extractKeywords($student_question);
                $category = $this->categorizeMessage($student_question);

                // Generate quick replies based on context
                $quick_replies = $this->generateQuickReplies($student_question);

                // Add to knowledge base
                $success = $this->addFAQ(
                    $student_question,
                    $admin_message,
                    $category,
                    $keywords,
                    $quick_replies,
                    false
                );

                if ($success) {
                    error_log("Bot learned new response: " . substr($student_question, 0, 50));
                }

                return $success;
            }

            return false;
        } catch (PDOException $e) {
            error_log("Learning from admin response failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Extract keywords from text
     */
    private function extractKeywords($text)
    {
        $stopwords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'been', 'being'];
        $words = str_word_count(strtolower($text), 1);
        $keywords = array_diff($words, $stopwords);

        // Keep only meaningful words (length > 2)
        $keywords = array_filter($keywords, function ($word) {
            return strlen($word) > 2;
        });

        return array_slice(array_unique($keywords), 0, 8);
    }

    /**
     * Categorize message automatically
     */
    private function categorizeMessage($text)
    {
        $text = strtolower($text);
        $categories = [
            'applications' => ['apply', 'application', 'submit', 'form', 'register', 'enroll'],
            'documents' => ['document', 'upload', 'file', 'pdf', 'id', 'certificate', 'transcript'],
            'status' => ['status', 'progress', 'check', 'where', 'track', 'update'],
            'deadlines' => ['deadline', 'when', 'date', 'close', 'due', 'last date'],
            'payments' => ['payment', 'pay', 'fee', 'cost', 'price', 'tuition', 'financial'],
            'technical' => ['password', 'login', 'account', 'technical', 'error', 'problem', 'issue'],
            'requirements' => ['requirement', 'need', 'required', 'eligibility', 'criteria', 'qualification']
        ];

        $best_category = 'general';
        $highest_score = 0;

        foreach ($categories as $category => $keywords) {
            $score = 0;
            foreach ($keywords as $keyword) {
                if (strpos($text, $keyword) !== false) {
                    $score++;
                }
            }

            if ($score > $highest_score) {
                $highest_score = $score;
                $best_category = $category;
            }
        }

        return $highest_score > 0 ? $best_category : 'general';
    }

    /**
     * Generate quick replies based on question context
     */
    private function generateQuickReplies($question)
    {
        $question_lower = strtolower($question);
        $quick_replies = [];

        if (strpos($question_lower, 'application') !== false) {
            $quick_replies = ["Application status", "Required documents", "Application deadline", "Check eligibility"];
        } elseif (strpos($question_lower, 'document') !== false) {
            $quick_replies = ["How to upload documents?", "Document requirements", "Missing documents", "Document deadline"];
        } elseif (strpos($question_lower, 'status') !== false) {
            $quick_replies = ["Application progress", "When will I know?", "Check status online", "Contact admissions"];
        } elseif (strpos($question_lower, 'payment') !== false) {
            $quick_replies = ["Payment methods", "Fee structure", "Payment deadline", "Financial aid"];
        } else {
            $quick_replies = ["Application help", "Document requirements", "Status check", "Contact support"];
        }

        return array_slice($quick_replies, 0, 4);
    }

    /**
     * Get FAQ statistics
     */
    public function getStats()
    {
        $query = "SELECT 
                  COUNT(*) as total_faqs,
                  COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_faqs,
                  COUNT(CASE WHEN escalation_required = 1 THEN 1 END) as escalation_faqs,
                  GROUP_CONCAT(DISTINCT category) as categories
                  FROM faq_knowledge_base";

        $stmt = $this->connection->query($query);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Search FAQs by keyword
     */
    public function searchFAQs($search_term, $limit = 10)
    {
        $query = "SELECT id, question, answer, keywords, confidence_score, created_at FROM faq_knowledge_base 
                        WHERE question LIKE :search 
                            OR answer LIKE :search 
                            OR JSON_CONTAINS(keywords, :search_json)
                  ORDER BY 
                    (CASE WHEN question LIKE :search_exact THEN 1 ELSE 0 END) DESC,
                    confidence_score DESC
                  LIMIT :limit";

        $search_param = "%$search_term%";
        $search_json = json_encode($search_term);
        $search_exact = "$search_term%";

        $stmt = $this->connection->prepare($query);
        $stmt->bindParam(':search', $search_param);
        $stmt->bindParam(':search_json', $search_json);
        $stmt->bindParam(':search_exact', $search_exact);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
