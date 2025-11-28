<?php
require_once 'config.php';

// Start session and check if user is logged in
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in, if not redirect to login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get user data (you'll need to adjust this based on your database)
$user_id = $_SESSION['user_id'];
// Add code here to fetch user data from database
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - EduBridgeSA</title>
    <link rel="stylesheet" href="./common-styles.css">
    <link rel="stylesheet" href="./dashboard-styles.css">
</head>
<body>
    <!-- Include your navigation -->
    <?php include 'includes/navigation.php'; ?>

    <div class="dashboard-container">
        <div class="main-content">
            <!-- Welcome Section -->
            <div class="card welcome-section">
                <h1>Welcome back, Bongani DIKGANG! 👋</h1>
                <p>Your journey to university starts here. Let's make it amazing! ✨</p>
            </div>

            <!-- Progress Section -->
            <div class="card progress-section">
                <div class="status-badge">DRAFT</div>
                <div class="progress-percentage">20%</div>
                <div class="progress-label">Complete</div>
                
                <div class="progress-container">
                    <div class="progress-bar"></div>
                </div>
                
                <p>You're on fire! Keep chasing those dreams!</p>
                <p class="motivator-author">- EduBridge Motivator</p>
            </div>

            <!-- Progress Steps -->
            <div class="card">
                <div class="progress-steps-container">
                    <div class="progress-steps">
                        <div class="step completed">
                            <div class="step-icon">✓</div>
                            <div class="step-label">First Launch</div>
                        </div>
                        <div class="step locked">
                            <div class="step-icon">🔒</div>
                            <div class="step-label">Paper Master</div>
                        </div>
                        <div class="step locked">
                            <div class="step-icon">🔒</div>
                            <div class="step-label">On My Way</div>
                        </div>
                        <div class="step completed">
                            <div class="step-icon">✓</div>
                            <div class="step-label">All About You</div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <button class="btn btn-primary">Apply</button>
                    <button class="btn btn-secondary">Upload</button>
                    <button class="btn btn-secondary">Submit</button>
                    <button class="btn btn-secondary">Track</button>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="sidebar">
            <div class="card quick-stats">
                <div class="stat-number">0/4</div>
                <div class="stat-label">Notifications</div>
                <div class="stat-desc">Latest updates</div>
            </div>
            
            <div class="card quick-stats">
                <div class="stat-number">4</div>
                <div class="stat-label">Documents Required</div>
                <div class="stat-desc">Pending upload</div>
            </div>
        </div>
    </div>
</body>
</html>