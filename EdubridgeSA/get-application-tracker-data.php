<?php
// Use centralized session configuration for consistent cookie/name settings
require_once 'session_config.php';
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Get all applications for the user with university information
    $stmt = $pdo->prepare("
        SELECT 
            a.id,
            a.reference_number,
            a.status,
            a.created_at,
            a.updated_at,
            a.progress_percentage,
            a.current_step,
            a.is_active,
            a.priority_order,
            u.name as university_name,
            u.logo_url,
            u.location
        FROM applications a
        LEFT JOIN universities u ON a.university_id = u.id
        WHERE a.user_id = ?
        ORDER BY a.priority_order ASC, a.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get steps for each application
    foreach ($applications as &$application) {
        // Get application steps
        $stmt = $pdo->prepare("
            SELECT 
                step_name,
                step_description,
                is_completed,
                step_order,
                completion_date
            FROM application_steps
            WHERE application_id = ?
            ORDER BY step_order ASC
        ");
        $stmt->execute([$application['id']]);
        $application['steps'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get next steps
        $stmt = $pdo->prepare("
            SELECT 
                step_name,
                step_description,
                action_url,
                priority
            FROM application_next_steps
            WHERE application_id = ?
            ORDER BY priority ASC
            LIMIT 1
        ");
        $stmt->execute([$application['id']]);
        $nextStep = $stmt->fetch(PDO::FETCH_ASSOC);
        $application['next_step'] = $nextStep ?: null;

        // Get timeline events
        $stmt = $pdo->prepare("
            SELECT 
                event_type,
                event_description,
                event_date,
                created_by
            FROM application_timeline
            WHERE application_id = ?
            ORDER BY event_date DESC
            LIMIT 10
        ");
        $stmt->execute([$application['id']]);
        $application['timeline'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate progress if not set
        if ($application['progress_percentage'] === null) {
            $totalSteps = count($application['steps']);
            $completedSteps = count(array_filter($application['steps'], function($step) {
                return $step['is_completed'];
            }));
            $application['progress_percentage'] = $totalSteps > 0 ? round(($completedSteps / $totalSteps) * 100) : 0;
        }

        // Determine progress color
        $progress = $application['progress_percentage'];
        if ($progress <= 30) {
            $application['progress_color'] = 'red';
        } elseif ($progress <= 70) {
            $application['progress_color'] = 'yellow';
        } else {
            $application['progress_color'] = 'green';
        }

        // Format dates
        $application['created_at_formatted'] = date('M j, Y', strtotime($application['created_at']));
        $application['updated_at_formatted'] = date('M j, Y g:i A', strtotime($application['updated_at']));

        // Add university logo fallback
        if (empty($application['logo_url'])) {
            $application['logo_fallback'] = strtoupper(substr($application['university_name'] ?: 'UN', 0, 2));
        }
    }

    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'applications' => $applications,
        'total_applications' => count($applications),
        'active_applications' => count(array_filter($applications, function($app) {
            return $app['is_active'];
        })),
        'completed_applications' => count(array_filter($applications, function($app) {
            return $app['progress_percentage'] >= 100;
        }))
    ]);

} catch (PDOException $e) {
    error_log("Database error in get-application-tracker-data.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("General error in get-application-tracker-data.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred']);
}
?>