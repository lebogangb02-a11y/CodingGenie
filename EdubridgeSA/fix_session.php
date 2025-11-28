<?php
require_once 'session_config.php';

echo "<h1>Session Repair</h1>";

// Fix the session issues
if (isset($_SESSION['student_logged_in']) && $_SESSION['student_logged_in'] === true) {
    // Remove the conflicting logged_out flag
    unset($_SESSION['logged_out']);
    
    // Ensure user_id is set correctly
    if (!isset($_SESSION['user_id']) && isset($_SESSION['student_id'])) {
        // Try to get the actual user ID from database
        try {
            require_once 'config.php';
            $pdo = Database::getPDO();
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$_SESSION['student_email']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                echo "<p>✅ Fixed user_id: " . $user['id'] . "</p>";
            }
        } catch (Exception $e) {
            echo "<p>❌ Could not get user_id: " . $e->getMessage() . "</p>";
        }
    }
    
    // Standardize variable names
    if (isset($_SESSION['student_number']) && !isset($_SESSION['reference_number'])) {
        $_SESSION['reference_number'] = $_SESSION['student_number'];
    }
    
    echo "<p>✅ Session repaired successfully!</p>";
    echo "<pre>Fixed Session: ";
    print_r($_SESSION);
    echo "</pre>";
    
    echo "<p><a href='student-dashboard.php'>Go to Dashboard</a></p>";
} else {
    echo "<p>❌ Not logged in</p>";
}
?>