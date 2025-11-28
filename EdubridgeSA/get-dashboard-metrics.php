<?php
// Real-time Dashboard Metrics API
// Returns progress, status, and counters for the logged-in student

require_once 'session_config.php';
require_once 'config.php';
require_once 'security-utils.php';

header('Content-Type: application/json');

// Auth check
if (!isset($_SESSION['student_logged_in']) || $_SESSION['student_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Session data
$student_id = $_SESSION['student_id'] ?? null;
$student_email = $_SESSION['student_email'] ?? ($_SESSION['email'] ?? null);

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Resolve active application for this student (prefer student_id, fallback to email, then reference)
    $application = null;
    $application_id = null;

    if (!empty($student_id)) {
        $stmt = $pdo->prepare("SELECT * FROM applications WHERE student_id = ? ORDER BY updated_at DESC, id DESC LIMIT 1");
        $stmt->execute([$student_id]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$application && !empty($student_email)) {
        $stmt = $pdo->prepare("SELECT * FROM applications WHERE email_address = ? ORDER BY updated_at DESC, id DESC LIMIT 1");
        $stmt->execute([$student_email]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$application && isset($_SESSION['reference_number'])) {
        $stmt = $pdo->prepare("SELECT * FROM applications WHERE reference_number = ? ORDER BY updated_at DESC, id DESC LIMIT 1");
        $stmt->execute([$_SESSION['reference_number']]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    $application_id = $application['id'] ?? null;

    // Helper: determine if personal info is present
    $has_personal_info = false;
    if ($application) {
        $fields = ['full_name','surname','first_name','last_name','id_number','date_of_birth','phone_number'];
        foreach ($fields as $f) {
            if (!empty($application[$f])) { $has_personal_info = true; break; }
        }
    }

    // Helper: academic history
    $has_academic_history = false;
    // Check documents table schema variant
    if ($application_id) {
        // Variant A: application_documents with document_type
        $docsA = [];
        try {
            $stmtA = $pdo->prepare("SELECT document_type FROM application_documents WHERE application_id = ?");
            $stmtA->execute([$application_id]);
            $docsA = $stmtA->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) { /* table may not exist */ }

        // Variant B: documents table with doc_type
        $docsB = [];
        try {
            $stmtB = $pdo->prepare("SELECT doc_type FROM documents WHERE application_id = ?");
            $stmtB->execute([$application_id]);
            $docsB = $stmtB->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) { /* table may not exist */ }

        $doc_types = array_map('strtolower', array_merge($docsA, $docsB));
        $has_academic_history = in_array('academic_results', $doc_types) || in_array('academic_transcript', $doc_types) || in_array('matric_certificate', $doc_types);
    }

    // Determine required docs based on available schema
    $required_docs = [];
    if ($application_id) {
        // If using documents table
        $required_docs_b = ['id_document', 'matric_certificate', 'academic_transcript'];
        // If using application_documents table
        $required_docs_a = ['certified_id', 'academic_results'];

        // Detect which schema has data; prefer the populated one
        $hasA = false; $hasB = false;
        try {
            $countA = $pdo->prepare("SELECT COUNT(*) FROM application_documents WHERE application_id = ?");
            $countA->execute([$application_id]);
            $hasA = (int)$countA->fetchColumn() > 0;
        } catch (Exception $e) { /* ignore */ }
        try {
            $countB = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE application_id = ?");
            $countB->execute([$application_id]);
            $hasB = (int)$countB->fetchColumn() > 0;
        } catch (Exception $e) { /* ignore */ }

        $required_docs = $hasB ? $required_docs_b : $required_docs_a;
    }

    // Gather uploaded docs
    $uploaded_doc_types = [];
    if ($application_id && !empty($required_docs)) {
        // Try both tables
        try {
            $stmtA = $pdo->prepare("SELECT document_type FROM application_documents WHERE application_id = ?");
            $stmtA->execute([$application_id]);
            foreach ($stmtA->fetchAll(PDO::FETCH_COLUMN) as $dt) { $uploaded_doc_types[] = strtolower($dt); }
        } catch (Exception $e) { /* ignore */ }
        try {
            $stmtB = $pdo->prepare("SELECT doc_type FROM documents WHERE application_id = ?");
            $stmtB->execute([$application_id]);
            foreach ($stmtB->fetchAll(PDO::FETCH_COLUMN) as $dt) { $uploaded_doc_types[] = strtolower($dt); }
        } catch (Exception $e) { /* ignore */ }
    }

    // Step completion based on requirements in the spec
    // Steps and weights:
    // First Launch: 15%, Personal Info: 25%, Academic History: 20%, Document Upload: 25%, Application Review: 15%
    $progress = 0;

    // First Launch (application exists)
    $first_launch_done = !empty($application_id);
    if ($first_launch_done) $progress += 15;

    // Personal Info
    $personal_info_done = $has_personal_info;
    if ($personal_info_done) $progress += 25;

    // Academic History
    $academic_history_done = $has_academic_history;
    if ($academic_history_done) $progress += 20;

    // Document Upload (all required docs uploaded)
    $document_upload_done = false;
    if (!empty($required_docs)) {
        $missing = array_diff($required_docs, $uploaded_doc_types);
        $document_upload_done = count($missing) === 0;
        if ($document_upload_done) $progress += 25;
    }

    // Application Review (submitted or under review)
    $review_done = false;
    $status_raw = strtolower($application['status'] ?? ($application['application_status'] ?? ''));
    if ($status_raw) {
        $review_done = in_array($status_raw, ['submitted','under_review','in_review','accepted','complete','completed','submitted (with docs)']);
    }
    if ($review_done) $progress += 15;

    // Clamp 0-100
    if ($progress < 0) $progress = 0; if ($progress > 100) $progress = 100;

    // Status mapping
    $status_label = 'Draft';
    if ($progress >= 100) {
        $status_label = 'Complete';
    } elseif ($progress >= 75) {
        $status_label = 'Submitted';
    } elseif ($progress >= 25) {
        $status_label = 'In Progress';
    } else {
        $status_label = 'Draft';
    }

    // Notifications count: unread per all student applications
    $notifications_unread = 0;
    try {
        // Collect all application ids for this student
        $appIds = [];
        if (!empty($student_id)) {
            $stmt = $pdo->prepare("SELECT id FROM applications WHERE student_id = ?");
            $stmt->execute([$student_id]);
            $appIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        if (empty($appIds) && !empty($student_email)) {
            $stmt = $pdo->prepare("SELECT id FROM applications WHERE email_address = ?");
            $stmt->execute([$student_email]);
            $appIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        if (!empty($appIds)) {
            // Use IN query safely
            $placeholders = implode(',', array_fill(0, count($appIds), '?'));
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM email_notifications WHERE application_id IN ($placeholders) AND status = 'unread'");
            $stmt->execute($appIds);
            $notifications_unread = (int)$stmt->fetchColumn();
        }
    } catch (Exception $e) { /* fallback silently */ }

    // Applications count
    $applications_count = 0;
    try {
        if (!empty($student_id)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE student_id = ?");
            $stmt->execute([$student_id]);
            $applications_count = (int)$stmt->fetchColumn();
        } else if (!empty($student_email)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE email_address = ?");
            $stmt->execute([$student_email]);
            $applications_count = (int)$stmt->fetchColumn();
        }
    } catch (Exception $e) { /* ignore */ }

    // Documents required count (remaining required docs for active application)
    $documents_required_remaining = 0;
    if (!empty($required_docs)) {
        $documents_required_remaining = count(array_diff($required_docs, $uploaded_doc_types));
    }

    echo json_encode([
        'success' => true,
        'progress' => (int)$progress,
        'status' => $status_label,
        'applicationId' => $application_id,
        'steps' => [
            'first_launch' => $first_launch_done,
            'personal_info' => $personal_info_done,
            'academic_history' => $academic_history_done,
            'document_upload' => $document_upload_done,
            'application_review' => $review_done
        ],
        'counts' => [
            'notifications' => $notifications_unread,
            'applications' => $applications_count,
            'documents_required' => $documents_required_remaining
        ],
        'timestamp' => time()
    ]);
} catch (PDOException $e) {
    error_log('get-dashboard-metrics error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
}
?>