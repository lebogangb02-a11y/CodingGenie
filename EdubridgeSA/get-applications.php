<?php
require_once 'session_config.php';
require_once 'config.php';
require_once 'security-utils.php';

// Set JSON header
header('Content-Type: application/json');

// Check if student is logged in
if (!isset($_SESSION['student_logged_in']) || $_SESSION['student_logged_in'] !== true || !isset($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['student_id'];

try {
    // Get the current student's application
    $stmt = $pdo->prepare("
        SELECT 
            a.id,
            a.reference_number,
            a.application_status as status,
            a.created_at,
            a.updated_at,
            uc.university_name,
            uc.course_name,
            uc.faculty
        FROM applications a
        LEFT JOIN university_choices uc ON a.id = uc.application_id
        WHERE a.id = ?
        ORDER BY a.created_at DESC
    ");
    
    $stmt->execute([$user_id]);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the applications data
    $formatted_applications = [];
    foreach ($applications as $app) {
        $formatted_applications[] = [
            'id' => $app['id'],
            'reference_number' => $app['reference_number'],
            'status' => $app['status'],
            'university_name' => $app['university_name'] ?? 'Not specified',
            'course_name' => $app['course_name'] ?? 'Not specified',
            'faculty' => $app['faculty'] ?? '',
            'created_at' => $app['created_at'],
            'updated_at' => $app['updated_at']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'applications' => $formatted_applications
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in get-applications.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch applications'
    ]);
}
?>