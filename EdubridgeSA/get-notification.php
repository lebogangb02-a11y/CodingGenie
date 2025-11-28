<?php
require_once 'session_config.php';
require_once 'config.php';
require_once 'security-utils.php';

// Check if user is logged in
if (!isset($_SESSION['student_logged_in']) || $_SESSION['student_logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    // Get student data
    $student_id = $_SESSION['student_id'];
    
    // Get unread notifications count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as unread 
        FROM email_notifications 
        WHERE application_id = ? 
        AND status = 'unread'
    ");
    $stmt->execute([$student_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode([
        'unread' => (int)$result['unread'],
        'success' => true
    ]);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Database error occurred',
        'success' => false
    ]);
    error_log("Notifications error: " . $e->getMessage());
}