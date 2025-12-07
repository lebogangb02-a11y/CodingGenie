<?php
require_once 'session_config.php';
require_once 'config.php';
require_once 'profile-utils.php';
require_once 'progress_utils.php';

// Start session and check if user is logged in
// Session is initialized via session_config.php; no manual session_start needed.

// Simple authentication check
if (!isset($_SESSION['student_logged_in']) || $_SESSION['student_logged_in'] !== true) {
    header('Location: student-login.php');
    exit();
}

// Get user data from session
$username = $_SESSION['student_name'] ?? 'Student';
$email = $_SESSION['student_email'] ?? '';
$reference_number = $_SESSION['reference_number'] ?? 'APP2025000000';
$application_status = $_SESSION['application_status'] ?? 'draft';
$student_id = $_SESSION['student_id'] ?? '';

// Resolve profile picture path (falls back to default avatar)
$profile_picture = getProfilePicture([]);

// Generate canonical Upload Documents URL for this session
$upload_docs_url = 'document_upload.php';
try {
    $contactEmail = $_SESSION['student_email'] ?? $_SESSION['email'] ?? null;
    $resolvedRef = null;
    // Prefer session reference number if available
    if (!empty($reference_number)) {
        $resolvedRef = $reference_number;
    }
    // Fallback: resolve latest application by email and use its reference number
    if (!$resolvedRef && $contactEmail) {
        $stmt = $pdo->prepare("SELECT reference_number FROM applications WHERE email_address = ? ORDER BY updated_at DESC, id DESC LIMIT 1");
        $stmt->execute([$contactEmail]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['reference_number'])) {
            $resolvedRef = $row['reference_number'];
        }
    }
    // Final fallback: if a session ref exists, use it
    if (!$resolvedRef) {
        $sessionRef = $_SESSION['reference_number'] ?? null;
        if ($sessionRef) {
            $resolvedRef = $sessionRef;
        }
    }
    if ($resolvedRef) {
        $upload_docs_url = 'document_upload.php?ref=' . urlencode($resolvedRef);
    }
} catch (PDOException $e) {
    error_log('student-dashboard upload link error: ' . $e->getMessage());
}

// Check for active chat conversations
$activeChats = 0;
$unreadChatMessages = 0;
try {
    $chatTableExists = $pdo->query("SHOW TABLES LIKE 'chat_conversations'")->rowCount() > 0;
    if ($chatTableExists && $student_id) {
        // Count active conversations
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as active_chats 
            FROM chat_conversations 
            WHERE student_id = ? AND status IN ('active', 'escalated')
        ");
        $stmt->execute([$student_id]);
        $activeChats = $stmt->fetchColumn();

        // Count unread messages
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as unread_messages 
            FROM chat_messages m
            JOIN chat_conversations c ON m.conversation_id = c.id
            WHERE c.student_id = ? AND m.sender_type IN ('admin', 'bot') AND m.is_read = FALSE
        ");
        $stmt->execute([$student_id]);
        $unreadChatMessages = $stmt->fetchColumn();
    }
} catch (Exception $e) {
    // Silently fail for chat stats
    error_log('Chat stats error: ' . $e->getMessage());
}

// Smart progress data (historical-based estimates)
$smartData = [];
try {
    $uid = isset($_SESSION['student_id']) ? (int)$_SESSION['student_id'] : 0;
    if (!$uid) {
        // Fallback to most recent user if session id missing (preview/dev only)
        $stmt = $pdo->query("SELECT id FROM users ORDER BY id DESC LIMIT 1");
        $uid = (int)$stmt->fetchColumn();
    }
    if ($uid) {
        $smartData = computeProgressEstimates($pdo, $uid);
    }
} catch (Throwable $e) {
    $smartData = ['error' => $e->getMessage()];
}

// Calculate real-time completion percentage for the current user
$completion_percentage = 0;
$application_id = null;
$has_all_required_docs = false;
try {
    // Reuse resolved application id if available
    if (isset($resolvedId) && $resolvedId) {
        $application_id = (int)$resolvedId;
    } else {
        // Fallback: try to locate latest application via email or reference number
        $contactEmail = $_SESSION['student_email'] ?? $_SESSION['email'] ?? null;
        if ($contactEmail) {
            $stmt = $pdo->prepare("SELECT id FROM applications WHERE email_address = ? ORDER BY updated_at DESC, id DESC LIMIT 1");
            $stmt->execute([$contactEmail]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['id'])) {
                $application_id = (int)$row['id'];
            }
        }
        if (!$application_id) {
            $sessionRef = $_SESSION['reference_number'] ?? null;
            if ($sessionRef) {
                $stmt2 = $pdo->prepare("SELECT id FROM applications WHERE reference_number = ? ORDER BY updated_at DESC, id DESC LIMIT 1");
                $stmt2->execute([$sessionRef]);
                $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
                if ($row2 && !empty($row2['id'])) {
                    $application_id = (int)$row2['id'];
                }
            }
        }
    }

    // If we found an application, compute progress using core milestones
    if ($application_id) {
        $total_steps = 4;
        $completed_steps = 0;

        // OPTIMIZED: Fetch all progress data in one query using JOINs + GROUP_CONCAT for documents
        $stmt = $pdo->prepare("
            SELECT 
                a.id as app_id,
                a.application_status,
                COALESCE(pg.full_name, '') as pg_full_name,
                COALESCE(uc.university_1, '') as university_1,
                GROUP_CONCAT(DISTINCT ad.document_type) as document_types
            FROM applications a
            LEFT JOIN parent_guardian_details pg ON pg.application_id = a.id
            LEFT JOIN university_choices uc ON uc.application_id = a.id
            LEFT JOIN application_documents ad ON ad.application_id = a.id
            WHERE a.id = ?
            GROUP BY a.id
            LIMIT 1
        ");
        $stmt->execute([$application_id]);
        $progress_row = $stmt->fetch(PDO::FETCH_ASSOC);

        // Initialize step counter
        $completed_steps = 1; // Application record exists (we have the data)

        // Parse the aggregated data
        if ($progress_row) {
            $application = [
                'id' => $progress_row['app_id'],
                'application_status' => $progress_row['application_status']
            ];

            // Step 2: Check parent/guardian
            if (!empty($progress_row['pg_full_name'])) {
                $completed_steps++;
            }

            // Step 3: Check university choices
            if (!empty($progress_row['university_1'])) {
                $completed_steps++;
            }

            // Step 4: Check required documents
            $document_types = !empty($progress_row['document_types']) ?
                array_filter(array_map('trim', explode(',', $progress_row['document_types']))) : [];
            $required_document_types = ['certified_id', 'academic_results'];
            $required_uploaded = array_intersect($required_document_types, $document_types);
            $has_all_required_docs = count($required_uploaded) === count($required_document_types);
            if ($has_all_required_docs) {
                $completed_steps++;
            }
        }

        $completion_percentage = ($total_steps > 0) ? round(($completed_steps / $total_steps) * 100) : 0;
        // If compulsory docs are done, reflect full readiness
        if ($has_all_required_docs) {
            $completion_percentage = 100;
        }
    } else {
        // Graceful fallback using session application_status
        $status = strtolower($application_status);
        $map = [
            'draft' => 20,
            'in_review' => 60,
            'submitted' => 80,
            'completed' => 100
        ];
        $completion_percentage = $map[$status] ?? 20;
    }
} catch (PDOException $e) {
    error_log('student-dashboard progress calc error: ' . $e->getMessage());
    // Keep a sensible fallback rather than breaking the page
    if ($completion_percentage <= 0) {
        $completion_percentage = 20;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="description" content="EduBridgeSA Student Dashboard - Track your university application progress">
    <meta name="theme-color" content="#004AAD">
    <title>Student Dashboard - EduBridgeSA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- AOS animations CSS -->
    <link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Dashboard Styles */
        :root {
            /* EduBridge SA theme */
            --primary: #004AAD;
            /* EduBridge Blue */
            --secondary: #4CAF50;
            /* EduBridge Green */
            --light: #f5f7fa;
            --dark: #1a237e;
            --accent: #F9FAFB;
            /* Soft white surface */
            --royal-blue: #1e3a8a;
            --royal-blue-light: #3b82f6;
            --emerald-green: #059669;
            --gold: #f59e0b;
            --white: #ffffff;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--royal-blue) 0%, var(--royal-blue-light) 100%);
            min-height: 100vh;
            color: #333;
            line-height: 1.6;
        }

        /* Dashboard Container */
        .dashboard-container {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 25px;
            padding: 30px;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Main Content Area */
        .main-content {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        /* Card Components */
        .card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            margin-bottom: 0;
            border: 1px solid var(--gray-200);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.15);
        }

        /* Welcome Section */
        .welcome-section h1 {
            font-size: 2.2rem;
            color: var(--gray-800);
            margin-bottom: 10px;
            font-weight: 700;
        }

        .welcome-section p {
            font-size: 1.1rem;
            color: var(--gray-600);
            margin-bottom: 20px;
        }

        /* Progress Section */
        .progress-section {
            text-align: center;
        }

        /* Circular progress ring */
        .progress-visual {
            justify-content: center;
        }

        .progress-ring {
            width: 160px;
            height: 160px;
            position: relative;
            margin: 10px auto;
        }

        .progress-ring svg {
            width: 160px;
            height: 160px;
        }

        .progress-ring circle.bg {
            stroke: var(--gray-200);
            stroke-width: 12;
            fill: none;
        }

        .progress-ring circle.fg {
            stroke: url(#ringGradient);
            stroke-width: 12;
            fill: none;
            stroke-linecap: round;
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
            transition: stroke-dashoffset .8s ease;
        }

        .progress-ring__text {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.8rem;
            color: var(--gray-800);
        }

        .progress-ring.glow {
            filter: drop-shadow(0 4px 12px rgba(76, 175, 80, .2));
        }

        .status-badge {
            display: inline-block;
            background: #e3f2fd;
            color: #1976d2;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 15px;
            border: 1px solid #bbdefb;
            text-transform: uppercase;
        }

        .progress-percentage {
            font-size: 3rem;
            font-weight: 800;
            color: var(--gray-800);
            margin: 10px 0;
        }

        .progress-label {
            font-size: 1.1rem;
            color: var(--gray-600);
            margin-bottom: 20px;
        }

        /* Progress Bar Design */
        .progress-container {
            background: var(--gray-200);
            border-radius: 25px;
            height: 20px;
            margin: 20px 0;
            overflow: hidden;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .progress-bar {
            background: linear-gradient(90deg, var(--emerald-green), #10b981);
            height: 100%;
            border-radius: 25px;
            width: 20%;
            transition: width 0.5s ease;
            position: relative;
            overflow: hidden;
        }

        .progress-bar::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            bottom: 0;
            right: 0;
            background-image: linear-gradient(-45deg,
                    rgba(255, 255, 255, 0.2) 25%,
                    transparent 25%,
                    transparent 50%,
                    rgba(255, 255, 255, 0.2) 50%,
                    rgba(255, 255, 255, 0.2) 75%,
                    transparent 75%,
                    transparent);
            background-size: 50px 50px;
            animation: move 2s linear infinite;
        }

        @keyframes move {
            0% {
                background-position: 0 0;
            }

            100% {
                background-position: 50px 50px;
            }
        }

        /* Motivator Section */
        .motivator-section {
            background: linear-gradient(135deg, var(--royal-blue) 0%, var(--royal-blue-light) 100%);
            color: white;
            text-align: center;
        }

        .motivator-section p {
            font-size: 1.2rem;
            margin-bottom: 10px;
            font-weight: 500;
        }

        .motivator-author {
            font-style: italic;
            opacity: 0.9;
            font-size: 1rem;
        }

        /* Hero Banner (visual replacement for progress bar) */
        .hero-banner {
            position: relative;
            overflow: hidden;
            border-radius: 16px;
            padding: 0;
            min-height: 220px;
            background: linear-gradient(180deg, #F9FAFB 0%, #E8F0FA 100%);
        }

        .hero-img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            filter: saturate(105%) contrast(95%);
        }

        .hero-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(0, 74, 173, .75) 0%, rgba(76, 175, 80, .45) 100%);
        }

        .hero-content {
            position: relative;
            z-index: 2;
            padding: 28px 28px 32px;
            color: #fff;
        }

        .hero-title {
            font-size: 1.6rem;
            font-weight: 700;
            margin: 0 0 8px;
        }

        .hero-sub {
            font-size: 1rem;
            opacity: .95;
        }

        /* Student Journey Gallery */
        .journey-gallery {
            display: grid;
            grid-auto-flow: column;
            grid-auto-columns: 220px;
            gap: 14px;
            overflow-x: auto;
            padding: 8px 2px 10px;
            scroll-snap-type: x mandatory;
        }

        .journey-item {
            position: relative;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .12);
            transform: translateZ(0);
            transition: transform .25s ease, box-shadow .25s ease;
            scroll-snap-align: start;
        }

        .journey-item:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 28px rgba(0, 0, 0, .18);
        }

        .journey-item img {
            width: 100%;
            height: 140px;
            object-fit: cover;
            display: block;
        }

        .journey-caption {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            padding: 10px 12px;
            color: #fff;
            font-weight: 600;
            font-size: .9rem;
            background: linear-gradient(180deg, rgba(0, 0, 0, 0) 0%, rgba(0, 0, 0, .55) 100%);
        }

        /* Smart Progress Widget */
        .smart-progress-card .smart-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 8px;
        }

        .smart-progress-card .smart-title {
            margin: 0;
            font-size: 1.25rem;
            color: var(--gray-800);
        }

        .smart-badge {
            padding: 6px 12px;
            border-radius: 999px;
            color: #fff;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .smart-badge.track {
            background: var(--primary);
        }

        .smart-badge.ahead {
            background: var(--secondary);
        }

        .smart-badge.slight {
            background: var(--accent);
        }

        .smart-badge.delay {
            background: #c62828;
        }

        .smart-bar {
            height: 10px;
            background: var(--gray-200);
            border-radius: 6px;
            overflow: hidden;
            margin: 10px 0 12px;
        }

        .smart-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            width: 0;
            transition: width .4s ease;
        }

        .smart-meta {
            color: var(--gray-600);
            font-size: 0.9rem;
        }

        .smart-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-top: 12px;
        }

        .smart-stage {
            border: 1px solid var(--gray-200);
            border-radius: 10px;
            padding: 12px;
        }

        .smart-stage .label {
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 4px;
        }

        .smart-stage .muted {
            color: var(--gray-600);
            font-size: 0.85rem;
        }

        /* Dark mode toggle styles */
        body.dark {
            --gray-50: #0f172a;
            --gray-100: #0b1220;
            --gray-200: #14233a;
            --gray-300: #1e2e4a;
            --gray-600: #cbd5e1;
            --gray-800: #e2e8f0;
            background: linear-gradient(135deg, #0b1740 0%, #142c5b 100%);
            color: var(--gray-800);
        }

        body.dark .card {
            background: #0f1f3b;
            border-color: #1f3a66;
            box-shadow: 0 8px 25px rgba(0, 0, 0, .35);
        }

        body.dark .user-info {
            background: linear-gradient(135deg, #0c1a36 0%, #12305f 100%);
        }

        /* Progress Steps Design */
        .progress-steps-container {
            position: relative;
            margin: 30px 0;
        }

        .progress-steps {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin: 40px 0;
            /* Ensure this block never blocks clicks below */
            pointer-events: none;
        }

        .progress-steps::before {
            content: '';
            position: absolute;
            top: 25px;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gray-300);
            z-index: 1;
            border-radius: 2px;
            /* Make the decorative line ignore pointer events */
            pointer-events: none;
        }

        .step {
            text-align: center;
            position: relative;
            z-index: 2;
            flex: 1;
        }

        .step-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--gray-300);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-weight: bold;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            border: 3px solid transparent;
        }

        .step.completed .step-icon {
            background: var(--emerald-green);
            color: white;
            box-shadow: 0 4px 15px rgba(5, 150, 105, 0.3);
        }

        .step.current .step-icon {
            background: var(--royal-blue-light);
            color: white;
            border: 3px solid #93c5fd;
            box-shadow: 0 4px 20px rgba(59, 130, 246, 0.4);
            transform: scale(1.1);
        }

        .step.locked .step-icon {
            background: var(--gray-400);
            color: white;
            opacity: 0.6;
        }

        .step-label {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--gray-600);
            margin-top: 8px;
        }

        .step.completed .step-label {
            color: var(--emerald-green);
        }

        .step.current .step-label {
            color: var(--royal-blue-light);
            font-weight: 700;
        }

        /* Action Buttons */
        .action-buttons {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 20px;
            /* Lift above any decorative overlays */
            position: relative;
            z-index: 100;
            pointer-events: auto;
        }

        .btn {
            padding: 15px 20px;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            /* Ensure buttons can always be clicked */
            pointer-events: auto;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--royal-blue) 0%, var(--royal-blue-light) 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: var(--gray-100);
            color: var(--gray-700);
            border: 2px solid var(--gray-300);
        }

        .btn-secondary:hover {
            background: var(--gray-200);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        /* Chat Support Button Styles */
        .btn-chat {
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);
            color: white;
        }

        .btn-chat:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(5, 150, 105, 0.4);
        }

        .btn-chat .badge {
            background: #dc2626;
            color: white;
            border-radius: 50%;
            padding: 4px 8px;
            font-size: 0.7rem;
            margin-left: 8px;
        }

        /* Sidebar */
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .quick-stats {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--royal-blue);
            margin: 10px 0;
        }

        .stat-label {
            color: var(--gray-600);
            font-size: 1rem;
            font-weight: 600;
        }

        .stat-desc {
            color: var(--gray-500);
            font-size: 0.9rem;
            margin-top: 5px;
        }

        /* User Info */
        .user-info {
            background: linear-gradient(135deg, var(--royal-blue) 0%, var(--royal-blue-light) 100%);
            color: white;
            padding: 20px;
            border-radius: 16px;
            text-align: center;
        }

        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 2rem;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }

        .user-name {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .user-email {
            opacity: 0.9;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }

        .user-ref {
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        /* Logout Button */
        .logout-btn {
            background: #dc2626;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            width: 100%;
        }

        .logout-btn:hover {
            background: #b91c1c;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3);
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .dashboard-container {
                grid-template-columns: 1fr;
                padding: 20px;
                gap: 20px;
            }

            .sidebar {
                order: -1;
            }

            .action-buttons {
                grid-template-columns: repeat(2, 1fr);
            }

            .progress-steps {
                flex-wrap: wrap;
                gap: 20px;
            }

            .progress-steps::before {
                display: none;
            }

            .step {
                flex: 0 0 calc(50% - 10px);
            }

            .smart-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            :root {
                font-size: 14px;
            }

            .dashboard-container {
                padding: 12px;
                gap: 15px;
            }

            .card {
                padding: 18px;
                border-radius: 12px;
            }

            .welcome-section h1 {
                font-size: 1.6rem;
                margin-bottom: 8px;
            }

            .welcome-section p {
                font-size: 1rem;
            }

            .progress-percentage {
                font-size: 2.2rem;
            }

            .progress-ring {
                width: 120px;
                height: 120px;
            }

            .progress-ring svg {
                width: 120px;
                height: 120px;
            }

            .action-buttons {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .btn {
                padding: 12px 16px;
                font-size: 0.95rem;
            }

            .journey-gallery {
                grid-auto-columns: 180px;
                gap: 10px;
            }

            .journey-item img {
                height: 120px;
            }

            .hero-title {
                font-size: 1.3rem;
            }

            .hero-sub {
                font-size: 0.9rem;
            }

            .progress-steps {
                margin: 30px 0;
            }

            .step {
                flex: 0 0 calc(50% - 10px);
            }

            .step-icon {
                width: 40px;
                height: 40px;
                font-size: 0.9rem;
                border-width: 2px;
            }

            .step-label {
                font-size: 0.8rem;
            }

            .smart-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .smart-stage {
                padding: 10px;
            }

            .user-avatar {
                width: 70px;
                height: 70px;
                font-size: 1.8rem;
            }

            .user-name {
                font-size: 1.1rem;
            }

            .stat-number {
                font-size: 2rem;
            }

            .nav-container nav {
                padding: 8px 12px;
            }

            .logo img {
                height: 28px;
            }
        }

        @media (max-width: 480px) {
            :root {
                font-size: 13px;
            }

            .dashboard-container {
                padding: 8px;
                gap: 12px;
            }

            .card {
                padding: 14px;
                border-radius: 10px;
            }

            .welcome-section h1 {
                font-size: 1.4rem;
            }

            .progress-percentage {
                font-size: 1.8rem;
            }

            .progress-ring {
                width: 100px;
                height: 100px;
            }

            .progress-ring svg {
                width: 100px;
                height: 100px;
            }

            .progress-ring__text {
                font-size: 1.4rem;
            }

            .action-buttons {
                grid-template-columns: 1fr;
            }

            .btn {
                padding: 10px 14px;
                font-size: 0.9rem;
                gap: 6px;
            }

            .btn i {
                font-size: 0.9rem;
            }

            .journey-gallery {
                grid-auto-columns: 150px;
                gap: 8px;
            }

            .journey-item img {
                height: 100px;
            }

            .journey-caption {
                font-size: 0.75rem;
                padding: 8px 10px;
            }

            .step {
                flex: 0 0 calc(100% / 2 - 5px);
            }

            .step-icon {
                width: 35px;
                height: 35px;
                font-size: 0.75rem;
                border-width: 2px;
            }

            .step-label {
                font-size: 0.7rem;
            }

            .stat-number {
                font-size: 1.6rem;
            }

            .user-avatar {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }

            .user-name {
                font-size: 1rem;
            }

            .user-email {
                font-size: 0.85rem;
            }

            .hero-title {
                font-size: 1.1rem;
            }

            .hero-sub {
                font-size: 0.85rem;
            }

            #themeToggle {
                padding: 6px 10px;
                font-size: 0.8rem;
            }

            .nav-container nav {
                flex-direction: column;
                gap: 8px;
                align-items: flex-start;
            }
        }

        /* Animation for progress */
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(5, 150, 105, 0.4);
            }

            70% {
                box-shadow: 0 0 0 10px rgba(5, 150, 105, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(5, 150, 105, 0);
            }
        }

        .progress-section {
            animation: fadeInUp 0.8s ease;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* NUCLEAR CSS FIX - Add this to your dashboard styles */
        a[href="edubridge_wizard.php"] {
            pointer-events: auto !important;
            cursor: pointer !important;
            position: relative !important;
            z-index: 10000 !important;
        }

        /* Remove any overlays blocking clicks */
        .action-buttons {
            pointer-events: auto !important;
            position: relative !important;
            z-index: 1000 !important;
        }

        .card {
            pointer-events: auto !important;
        }

        /* Top Navigation */
        .nav-container {
            background: #ffffff;
            border-bottom: 1px solid var(--gray-200);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .nav-container nav {
            max-width: 1400px;
            margin: 0 auto;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo {
            display: inline-flex;
            align-items: center;
            color: var(--gray-800);
            font-weight: 600;
            text-decoration: none;
        }

        .logo img {
            height: 32px;
            width: auto;
            margin-right: 8px;
        }
    </style>
</head>

<body>
    <!-- Skip to main content link for accessibility -->
    <a href="#main-content" class="skip-link" style="position:absolute;top:-40px;left:-40px;z-index:9999;padding:8px 12px;background:#004AAD;color:white;text-decoration:none;border-radius:0 0 4px 0;">Skip to main content</a>

    <div class="nav-container" role="navigation" aria-label="Primary navigation">
        <nav>
            <a href="index.php" class="logo">
                <img src="images/logo.png.jpg" alt="EduBridge SA" onerror="this.onerror=null;this.src='images/logo.png';">
                <span>EduBridge SA</span>
            </a>
            <div class="d-flex align-items-center gap-2">
                <button id="themeToggle" class="btn btn-sm btn-outline-secondary" type="button" title="Toggle dark mode">Dark Mode</button>
            </div>
        </nav>
    </div>
    <main id="main-content" role="main">
        <div class="dashboard-container">
            <div class="main-content">
                <!-- Welcome Section -->
                <div class="card welcome-section" data-aos="fade-up">
                    <h1>Welcome back, <?php echo htmlspecialchars($username); ?>! 👋</h1>
                    <p id="welcome-subtext">Your journey to university starts here. Let's make it amazing! ✨</p>
                    <span class="badge text-bg-primary-subtle">— EduBridge Motivator</span>
                </div>

                <!-- Hero Banner -->
                <div class="card hero-banner" data-aos="fade-up" data-aos-delay="80">
                    <img class="hero-img" src="https://images.unsplash.com/photo-1523580494863-6f3031224c94?q=80&w=1600&auto=format&fit=crop" alt="Students on campus">
                    <div class="hero-overlay"></div>
                    <div class="hero-content">
                        <div class="hero-title">Every application brings you closer to your goal.</div>
                        <div class="hero-sub">EduBridge SA believes in you — keep going.</div>
                    </div>
                </div>

                <!-- Motivator Quote Box -->
                <div class="card" data-aos="fade-up" data-aos-delay="100">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <i class="fas fa-quote-left text-primary"></i>
                        <div id="quoteTyped" style="font-weight:600; color: var(--gray-700);">Stay consistent — your dream university awaits.</div>
                    </div>
                </div>

                <!-- Student Journey Gallery -->
                <div class="card" data-aos="fade-up" data-aos-delay="120">
                    <div class="journey-gallery" aria-label="Student journey gallery">
                        <div class="journey-item">
                            <img src="https://images.pexels.com/photos/1164572/pexels-photo-1164572.jpeg?auto=compress&cs=tinysrgb&w=1200&h=800&dpr=1" alt="Starting your journey" referrerpolicy="no-referrer" crossorigin="anonymous" onerror="this.onerror=null;this.src='https://picsum.photos/id/1005/1200/800';">
                            <div class="journey-caption">Starting Your Journey</div>
                        </div>
                        <div class="journey-item">
                            <img src="https://images.unsplash.com/photo-1553877522-43269d4ea984?q=80&w=1200&auto=format&fit=crop" alt="Submitting applications">
                            <div class="journey-caption">Submitting Applications</div>
                        </div>
                        <div class="journey-item">
                            <img src="https://images.unsplash.com/photo-1461896836934-ffe607ba8211?q=80&w=1200&auto=format&fit=crop" alt="Celebrating acceptance">
                            <div class="journey-caption">Celebrating Acceptance</div>
                        </div>
                    </div>
                </div>

                <!-- Progress Section visual banner -->
                <!-- Removed empty progress banner -->

                <!-- Progress Steps -->
                <?php
                // Helper to format duration like "2d 5h"
                function fmtSmartDuration($sec)
                {
                    $sec = (int)$sec;
                    if ($sec <= 0) return '—';
                    $days = intdiv($sec, 86400);
                    $hours = intdiv($sec % 86400, 3600);
                    if ($days > 0) return $days . 'd ' . $hours . 'h';
                    $mins = intdiv($sec % 3600, 60);
                    return $hours . 'h ' . $mins . 'm';
                }
                $smartStatus = $smartData['status'] ?? 'Unknown';
                $smartCls = 'track';
                if ($smartStatus === 'Ahead of Schedule') $smartCls = 'ahead';
                elseif ($smartStatus === 'Slightly Delayed') $smartCls = 'slight';
                elseif ($smartStatus === 'Delayed') $smartCls = 'delay';
                $smartPct = isset($smartData['progress_pct']) ? (int)$smartData['progress_pct'] : 0;
                ?>
                <!-- Smart Progress Indicator removed per design update -->

                <div class="card" data-aos="fade-up" data-aos-delay="300">
                    <div class="progress-steps-container">
                        <div class="progress-steps">
                            <div class="step completed" title="Congrats! First Launch completed">
                                <div class="step-icon">✓</div>
                                <div class="step-label">First Launch</div>
                            </div>
                            <div class="step locked" data-bs-toggle="tooltip" title="Complete your next step to unlock!">
                                <div class="step-icon">🔒</div>
                                <div class="step-label">Paper Master</div>
                            </div>
                            <div class="step locked" data-bs-toggle="tooltip" title="Complete your next step to unlock!">
                                <div class="step-icon">🔒</div>
                                <div class="step-label">On My Way</div>
                            </div>
                            <div class="step completed" title="Profile completed">
                                <div class="step-icon">✓</div>
                                <div class="step-label">All About You</div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="action-buttons" data-aos="fade-up" data-aos-delay="450">
                        <a href="edubridge_wizard.php" class="btn btn-primary">
                            <i class="fas fa-rocket"></i>
                            Apply Now
                        </a>
                        <a href="<?php echo htmlspecialchars($upload_docs_url); ?>" class="btn btn-secondary">
                            <i class="fas fa-upload"></i>
                            Upload Documents
                        </a>
                        <a href="chatbot.php" class="btn btn-chat">
                            <i class="fas fa-comments"></i>
                            Get Help
                            <?php if ($unreadChatMessages > 0): ?>
                                <span class="badge"><?php echo $unreadChatMessages; ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="track-progress.php" class="btn btn-secondary">
                            <i class="fas fa-chart-line"></i>
                            Track Progress
                        </a>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="sidebar" data-aos="fade-left" data-aos-delay="600">
                <!-- User Info Card -->
                <div class="card user-info">
                    <div class="user-avatar">
                        <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile" onerror="this.onerror=null;this.src='images/default-avatar.png';">
                    </div>
                    <div class="user-name"><?php echo htmlspecialchars($username); ?></div>
                    <div class="user-email"><?php echo htmlspecialchars($email); ?></div>
                    <div class="user-ref">Ref: <?php echo htmlspecialchars($reference_number); ?></div>
                </div>

                <!-- Quick Stats -->
                <div class="card quick-stats" id="notifications-card">
                    <div class="d-flex align-items-center justify-content-center gap-3">
                        <i class="fas fa-bell text-warning"></i>
                        <div class="stat-number"><span id="notif-unread"><?php echo $unreadChatMessages; ?></span></div>
                    </div>
                    <div class="stat-label">Notifications</div>
                    <div class="stat-desc">Unread messages and deadlines</div>
                </div>

                <div class="card quick-stats">
                    <div class="d-flex align-items-center justify-content-center gap-3">
                        <i class="fas fa-file-upload text-primary"></i>
                        <div class="stat-number"><span id="docs-required-count">0</span></div>
                    </div>
                    <div class="stat-label">Documents Required</div>
                    <div class="stat-desc">Pending upload</div>
                </div>

                <div class="card quick-stats">
                    <div class="d-flex align-items-center justify-content-center gap-3">
                        <i class="fas fa-folder-open text-success"></i>
                        <div class="stat-number"><span id="applications-count">0</span></div>
                    </div>
                    <div class="stat-label">Applications</div>
                    <div class="stat-desc">In progress</div>
                </div>

                <!-- Chat Support Quick Stats -->
                <div class="card quick-stats">
                    <div class="d-flex align-items-center justify-content-center gap-3">
                        <i class="fas fa-comments text-info"></i>
                        <div class="stat-number"><span id="chat-active"><?php echo $activeChats; ?></span></div>
                    </div>
                    <div class="stat-label">Active Chats</div>
                    <div class="stat-desc">With support team</div>
                </div>

                <div class="card" id="quick-links" data-aos="fade-left" data-aos-delay="750">
                    <h3 style="margin-bottom:12px;color:var(--gray-800)">Quick Links</h3>
                    <div class="action-buttons" style="grid-template-columns:1fr; gap:10px;">
                        <a href="student-account.php" class="btn btn-secondary"><i class="fas fa-user-cog"></i> Edit Profile</a>
                        <a href="edubridge_wizard.php" class="btn btn-primary"><i class="fas fa-rocket"></i> Apply Now</a>
                        <a href="chatbot.php" class="btn btn-chat">
                            <i class="fas fa-comments"></i> Get Help
                            <?php if ($unreadChatMessages > 0): ?>
                                <span class="badge"><?php echo $unreadChatMessages; ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="application_status.php" class="btn btn-secondary"><i class="fas fa-clipboard-list"></i> Check Application Status</a>
                    </div>
                </div>

                <!-- Logout Button -->
                <a href="auth.php?action=logout" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>
    </main>

    <!-- External JS Libraries (loaded first) -->
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script defer src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/typed.js@2.0.12"></script>
    <script defer src="https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.10.2/lottie.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>

    <!-- Dashboard Initialization & Logic -->
    <script defer>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 Dashboard initialized');

            // ====== PROGRESS RING INITIALIZATION ======
            var pct = <?php echo (int)$completion_percentage; ?>;
            var radius = 70;
            var circumference = 2 * Math.PI * radius;
            var ring = document.getElementById('ringFg');
            if (ring) {
                ring.style.strokeDasharray = circumference;
                var offset = circumference * (1 - Math.min(100, pct) / 100);
                ring.style.strokeDashoffset = offset;
            }

            // ====== DARK MODE TOGGLE ======
            var toggle = document.getElementById('themeToggle');
            if (toggle) {
                // Restore theme from localStorage
                if (localStorage.getItem('dashboardTheme') === 'dark') {
                    document.body.classList.add('dark');
                    toggle.textContent = 'Light Mode';
                }
                toggle.addEventListener('click', function() {
                    document.body.classList.toggle('dark');
                    var isDark = document.body.classList.contains('dark');
                    toggle.textContent = isDark ? 'Light Mode' : 'Dark Mode';
                    localStorage.setItem('dashboardTheme', isDark ? 'dark' : 'light');
                });
            }

            // ====== ANIMATIONS & EFFECTS ======
            // Initialize AOS
            if (window.AOS) {
                AOS.init({
                    once: true,
                    duration: 600,
                    easing: 'ease-out'
                });
            }

            // Initialize Bootstrap tooltips
            try {
                var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tooltipTriggerList.forEach(function(tooltipTriggerEl) {
                    new bootstrap.Tooltip(tooltipTriggerEl);
                });
            } catch (e) {
                console.warn('Tooltip init failed:', e);
            }

            // ====== MOTIVATIONAL QUOTE ROTATION ======
            var quotes = [
                "Believe in the journey. Every step counts.",
                "Small progress daily leads to big results.",
                "Your future self is cheering you on.",
                "Dream it. Plan it. Achieve it.",
                "You're closer than you think—keep going!"
            ];
            var subtextEl = document.getElementById('welcome-subtext');

            function setRandomQuote() {
                try {
                    if (subtextEl) {
                        var q = quotes[Math.floor(Math.random() * quotes.length)];
                        subtextEl.textContent = q + " ✨";
                    }
                } catch (e) {
                    console.warn('Quote rotation error:', e);
                }
            }
            setRandomQuote();
            setInterval(setRandomQuote, 30000);

            // ====== TYPED.JS ANIMATIONS ======
            var msgs;
            if (pct < 25) {
                msgs = ['Starting strong!', 'Step by step, you\'ve got this!', 'Your future is calling — answer boldly.'];
            } else if (pct < 75) {
                msgs = ['You\'re halfway there!', 'Momentum is your friend — keep going!', 'Every upload is a step closer.'];
            } else if (pct < 100) {
                msgs = ['Almost there — stay focused!', 'Final stretch — you\'re shining!', 'Your dedication shows.'];
            } else {
                msgs = ['You made it! 🎉', 'Congratulations — cap and gown ready!', 'Dream unlocked — onward!'];
            }

            var motivatorEl = document.getElementById('motivatorText');
            if (window.Typed) {
                try {
                    if (motivatorEl) {
                        new Typed('#motivatorText', {
                            strings: msgs,
                            typeSpeed: 40,
                            backSpeed: 25,
                            backDelay: 1800,
                            loop: true
                        });
                    }
                    var quoteMsgs = [
                        'Stay consistent — your dream university awaits.',
                        'EduBridgeSA believes in you.',
                        'Little progress is still progress.',
                        'Every application brings you closer to your goal.'
                    ];
                    if (document.getElementById('quoteTyped')) {
                        new Typed('#quoteTyped', {
                            strings: quoteMsgs,
                            typeSpeed: 35,
                            backSpeed: 20,
                            backDelay: 2200,
                            loop: true
                        });
                    }
                } catch (e) {
                    console.error('Typed.js init failed:', e);
                    if (motivatorEl) motivatorEl.textContent = msgs[0];
                    var qt = document.getElementById('quoteTyped');
                    if (qt) qt.textContent = 'Stay consistent — your dream university awaits.';
                }
            }

            // ====== LOTTIE ANIMATIONS ======
            var lottieUrl = (pct < 25) ?
                'https://assets1.lottiefiles.com/packages/lf20_1pxqjw0q.json' :
                (pct < 75) ?
                'https://assets2.lottiefiles.com/packages/lf20_touohxv0.json' :
                (pct < 100) ?
                'https://assets9.lottiefiles.com/packages/lf20_rzjohb.json' :
                'https://assets9.lottiefiles.com/packages/lf20_2lbm6w.json';
            if (window.lottie && document.getElementById('lottieProgress')) {
                try {
                    lottie.loadAnimation({
                        container: document.getElementById('lottieProgress'),
                        renderer: 'svg',
                        loop: true,
                        autoplay: true,
                        path: lottieUrl
                    });
                } catch (e) {
                    console.warn('Lottie init failed:', e);
                }
            }

            // ====== CONFETTI CELEBRATION ======
            if (pct >= 100 && window.confetti) {
                try {
                    confetti({
                        particleCount: 120,
                        spread: 100,
                        origin: {
                            y: 0.6
                        }
                    });
                } catch (e) {
                    console.warn('Confetti failed:', e);
                }
            }

            // ====== METRICS POLLING ======
            function applyMetrics(data) {
                try {
                    if (data.progress && typeof data.progress === 'number') {
                        var progressPercent = Math.max(0, Math.min(100, data.progress));
                        var progressPercentageEl = document.getElementById('progress-percentage');
                        var progressBar = document.getElementById('progress-bar');
                        var progressNote = document.getElementById('progress-note');

                        if (progressPercentageEl) progressPercentageEl.textContent = progressPercent + '%';
                        if (progressBar) progressBar.style.width = progressPercent + '%';
                        if (progressNote) progressNote.textContent = "You're " + progressPercent + "% closer to your dream university!";
                    }
                    if (data.counts) {
                        var notifUnread = document.getElementById('notif-unread');
                        var docsRequired = document.getElementById('docs-required-count');
                        var appsCount = document.getElementById('applications-count');
                        var chatActive = document.getElementById('chat-active');

                        if (notifUnread) notifUnread.textContent = (data.counts.notifications || 0);
                        if (docsRequired) docsRequired.textContent = (data.counts.documents_required || 0);
                        if (appsCount) appsCount.textContent = (data.counts.applications || 0);
                        if (chatActive) chatActive.textContent = (data.counts.active_chats || 0);
                    }
                } catch (e) {
                    console.error('Apply metrics error:', e);
                }
            }

            function fetchMetrics() {
                // Skip if offline
                if (!navigator.onLine) {
                    console.warn('Offline - skipping metrics fetch');
                    return;
                }

                try {
                    fetch('get-dashboard-metrics.php', {
                            credentials: 'same-origin'
                        })
                        .then(function(r) {
                            if (!r.ok) throw new Error('HTTP ' + r.status);
                            return r.json();
                        })
                        .then(function(data) {
                            if (data && data.success) {
                                applyMetrics(data);
                            }
                        })
                        .catch(function(err) {
                            console.error('Metrics fetch failed:', err);
                        });
                } catch (e) {
                    console.error('Fetch metrics error:', e);
                }
            }

            // Initial fetch and 30s interval
            fetchMetrics();
            setInterval(fetchMetrics, 30000);

            // Fetch notifications on page load
            try {
                fetch('get-notifications.php')
                    .then(function(r) {
                        if (!r.ok) throw new Error('HTTP ' + r.status);
                        return r.json();
                    })
                    .then(function(data) {
                        if (data && data.success) {
                            var el = document.getElementById('notif-unread');
                            if (el) el.textContent = data.unread || 0;
                        }
                    })
                    .catch(function(err) {
                        console.error('Notifications fetch failed:', err);
                    });
            } catch (e) {
                console.error('Notification fetch error:', e);
            }

            console.log('✅ Dashboard fully initialized');
        });
    </script>
</body>

</html>