<?php
require_once 'session_config.php';
require_once 'config.php';

// Start session and check if user is logged in
// Using centralized session_config for session initialization

// Simple authentication check
if (!isset($_SESSION['student_logged_in']) || $_SESSION['student_logged_in'] !== true) {
    header('Location: student-login.php?redirect=' . urlencode('student-apply.php'));
    exit();
}

// Get user data from session
$username = $_SESSION['student_name'] ?? 'Student';
$email = $_SESSION['student_email'] ?? '';
$reference_number = $_SESSION['reference_number'] ?? 'APP2025000000';
$application_status = $_SESSION['application_status'] ?? 'draft';
$student_id = $_SESSION['student_id'] ?? '';

// Determine which application step to show based on progress
$current_step = $_SESSION['current_application_step'] ?? 1;
$application_progress = $_SESSION['application_progress'] ?? 0;

// Application step URLs based on your workflow (route into the wizard)
$application_steps = [
    1 => 'edubridge_wizard.php?step=1',
    2 => 'edubridge_wizard.php?step=2',
    3 => 'edubridge_wizard.php?step=3',
    4 => 'edubridge_wizard.php?step=4'
];

// Default to the wizard step 1 if anything goes wrong
$continue_url = $application_steps[$current_step] ?? 'edubridge_wizard.php?step=1';
 
 // Generate canonical Upload Documents URL for this session
 $upload_docs_url = 'document_upload.php';
 try {
     $contactEmail = $_SESSION['student_email'] ?? $_SESSION['email'] ?? null;
     $resolvedRef = null;
     // Prefer session reference number if available
     if (!empty($_SESSION['reference_number'])) {
         $resolvedRef = $_SESSION['reference_number'];
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
     // Final fallback: query by the session reference to ensure exists
     if (!$resolvedRef && !empty($_SESSION['reference_number'])) {
         $stmt2 = $pdo->prepare("SELECT reference_number FROM applications WHERE reference_number = ? ORDER BY updated_at DESC, id DESC LIMIT 1");
         $stmt2->execute([$_SESSION['reference_number']]);
         $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
         if ($row2 && !empty($row2['reference_number'])) {
             $resolvedRef = $row2['reference_number'];
         }
     }
     if ($resolvedRef) {
         $upload_docs_url = 'document_upload.php?ref=' . urlencode($resolvedRef);
     }
 } catch (PDOException $e) {
     error_log('dashboard upload link error: ' . $e->getMessage());
 }
 
 // Auto-forward only when explicitly requested
  if (isset($_GET['autofwd']) && $_GET['autofwd'] === '1') {
      header('Location: ' . $continue_url);
      exit();
  }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - EduBridgeSA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Dashboard Styles */
        :root {
            --primary: #1a5fb4;
            --secondary: #2e7d32;
            --light: #f5f7fa;
            --dark: #1a237e;
            --accent: #ff9800;
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

        .status-badge.completed {
            background: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }

        .status-badge.in-review {
            background: #fff3cd;
            color: #856404;
            border-color: #ffeaa7;
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
            width: <?php echo $application_progress; ?>%;
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
            background-image: linear-gradient(
                -45deg,
                rgba(255, 255, 255, 0.2) 25%,
                transparent 25%,
                transparent 50%,
                rgba(255, 255, 255, 0.2) 50%,
                rgba(255, 255, 255, 0.2) 75%,
                transparent 75%,
                transparent
            );
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

        .btn-success {
            background: linear-gradient(135deg, var(--emerald-green), #10b981);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(5, 150, 105, 0.4);
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

        /* Document Status */
        .document-status {
            margin-top: 20px;
        }

        .document-item {
            display: flex;
            justify-content: between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--gray-200);
        }

        .document-item:last-child {
            border-bottom: none;
        }

        .doc-icon {
            width: 40px;
            height: 40px;
            background: var(--gray-100);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: var(--gray-600);
        }

        .doc-info {
            flex: 1;
        }

        .doc-name {
            font-weight: 600;
            color: var(--gray-800);
        }

        .doc-status {
            font-size: 0.85rem;
            color: var(--gray-500);
        }

        .doc-status.uploaded {
            color: var(--emerald-green);
            font-weight: 600;
        }

        .doc-status.pending {
            color: var(--gold);
            font-weight: 600;
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
            
            .action-buttons {
                grid-template-columns: 1fr;
            }
            
            .progress-steps {
                flex-wrap: wrap;
                gap: 20px;
            }
            
            .progress-steps::before {
                display: none;
            }
            
            .step {
                flex: 0 0 calc(50% - 20px);
            }
        }

        @media (max-width: 768px) {
            .dashboard-container {
                padding: 15px;
                gap: 15px;
            }
            
            .card {
                padding: 20px;
            }
            
            .welcome-section h1 {
                font-size: 1.8rem;
            }
            
            .progress-percentage {
                font-size: 2.5rem;
            }
            
            .action-buttons {
                grid-template-columns: 1fr;
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

        /* Notification Badge */
        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #dc2626;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="main-content">
            <!-- Welcome Section -->
            <div class="card welcome-section">
                <h1>Welcome back, <?php echo htmlspecialchars($username); ?>! 👋</h1>
                <p>Your journey to university starts here. Let's make it amazing! ✨</p>
                <div class="user-ref">Application Reference: <?php echo htmlspecialchars($reference_number); ?></div>
            </div>

            <!-- Progress Section -->
            <div class="card progress-section">
                <div class="status-badge <?php echo $application_status === 'completed' ? 'completed' : 'in-review'; ?>">
                    <?php echo strtoupper(htmlspecialchars($application_status)); ?>
                </div>
                <div class="progress-percentage"><?php echo $application_progress; ?>%</div>
                <div class="progress-label">Application Complete</div>
                
                <div class="progress-container">
                    <div class="progress-bar"></div>
                </div>
                
                <p>You're <?php echo $application_progress; ?>% closer to your dream university! 🎓</p>
                <p class="motivator-author">- EduBridge Motivator</p>
            </div>

            <!-- Application Progress Steps -->
            <div class="card">
                <h3 style="color: var(--gray-800); margin-bottom: 20px; font-size: 1.3rem;">Application Progress</h3>
                <div class="progress-steps-container">
                    <div class="progress-steps">
                        <div class="step <?php echo $current_step >= 1 ? 'completed' : ($current_step == 1 ? 'current' : 'locked'); ?>">
                            <div class="step-icon">1</div>
                            <div class="step-label">Personal Info</div>
                        </div>
                        <div class="step <?php echo $current_step >= 2 ? 'completed' : ($current_step == 2 ? 'current' : 'locked'); ?>">
                            <div class="step-icon">2</div>
                            <div class="step-label">Education</div>
                        </div>
                        <div class="step <?php echo $current_step >= 3 ? 'completed' : ($current_step == 3 ? 'current' : 'locked'); ?>">
                            <div class="step-icon">3</div>
                            <div class="step-label">Documents</div>
                        </div>
                        <div class="step <?php echo $current_step >= 4 ? 'completed' : ($current_step == 4 ? 'current' : 'locked'); ?>">
                            <div class="step-icon">4</div>
                            <div class="step-label">Review</div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <a href="<?php echo $continue_url; ?>" class="btn btn-primary" style="padding: 10px 15px; font-size: 0.9rem;">
                        <i class="fas fa-edit"></i>
                        <?php echo $current_step == 1 ? 'Start Application' : 'Continue Application'; ?>
                    </a>
                    <a href="<?php echo htmlspecialchars($upload_docs_url); ?>" class="btn btn-success">
                        <i class="fas fa-upload"></i>
                        Upload Documents
                    </a>
                    <a href="application-preview.php" class="btn btn-secondary">
                        <i class="fas fa-eye"></i>
                        Preview Application
                    </a>
                    <a href="download-application.php" class="btn btn-secondary">
                        <i class="fas fa-download"></i>
                        Download PDF
                    </a>
                </div>
            </div>

            <!-- Document Status -->
            <div class="card">
                <h3 style="color: var(--gray-800); margin-bottom: 20px; font-size: 1.3rem;">Document Status</h3>
                <div class="document-status">
                    <div class="document-item">
                        <div class="doc-icon">
                            <i class="fas fa-id-card"></i>
                        </div>
                        <div class="doc-info">
                            <div class="doc-name">Certified ID Copy</div>
                            <div class="doc-status <?php echo $current_step >= 2 ? 'uploaded' : 'pending'; ?>">
                                <?php echo $current_step >= 2 ? 'Uploaded' : 'Pending'; ?>
                            </div>
                        </div>
                    </div>
                    <div class="document-item">
                        <div class="doc-icon">
                            <i class="fas fa-file-certificate"></i>
                        </div>
                        <div class="doc-info">
                            <div class="doc-name">Academic Transcript</div>
                            <div class="doc-status <?php echo $current_step >= 2 ? 'uploaded' : 'pending'; ?>">
                                <?php echo $current_step >= 2 ? 'Uploaded' : 'Pending'; ?>
                            </div>
                        </div>
                    </div>
                    <div class="document-item">
                        <div class="doc-icon">
                            <i class="fas fa-home"></i>
                        </div>
                        <div class="doc-info">
                            <div class="doc-name">Proof of Residence (optional)</div>
                            <div class="doc-status <?php echo $current_step >= 3 ? 'uploaded' : 'pending'; ?>">
                                <?php echo $current_step >= 3 ? 'Uploaded' : 'Optional'; ?>
                            </div>
                        </div>
                    </div>
                    <div class="document-item">
                        <div class="doc-icon">
                            <i class="fas fa-passport"></i>
                        </div>
                        <div class="doc-info">
                            <div class="doc-name">Passport Photo</div>
                            <div class="doc-status <?php echo $current_step >= 3 ? 'uploaded' : 'pending'; ?>">
                                <?php echo $current_step >= 3 ? 'Uploaded' : 'Pending'; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="sidebar">
            <!-- User Info Card -->
            <div class="card user-info">
                <div class="user-avatar">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="user-name"><?php echo htmlspecialchars($username); ?></div>
                <div class="user-email"><?php echo htmlspecialchars($email); ?></div>
                <div class="user-ref"><?php echo htmlspecialchars($reference_number); ?></div>
            </div>

            <!-- Quick Stats -->
            <div class="card quick-stats" style="position: relative;">
                <div class="stat-number">3</div>
                <div class="stat-label">Notifications</div>
                <div class="stat-desc">New updates available</div>
                <div class="notification-badge">3</div>
            </div>
            
            <div class="card quick-stats">
                <div class="stat-number"><?php echo $current_step >= 2 ? '2/4' : '0/4'; ?></div>
                <div class="stat-label">Documents Uploaded</div>
                <div class="stat-desc"><?php echo $current_step >= 2 ? '2 pending upload' : '4 required'; ?></div>
            </div>

            <div class="card quick-stats">
                <div class="stat-number"><?php echo $application_progress; ?>%</div>
                <div class="stat-label">Application Progress</div>
                <div class="stat-desc">
                    <?php 
                    if ($application_progress >= 70) echo 'Almost there!';
                    elseif ($application_progress >= 40) echo 'Good progress!';
                    else echo 'Getting started';
                    ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <h3 style="color: var(--gray-800); margin-bottom: 15px; font-size: 1.1rem;">Quick Actions</h3>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <a href="<?php echo $continue_url; ?>" class="btn btn-primary" style="padding: 10px 15px; font-size: 0.9rem;">
                        <i class="fas fa-edit"></i>
                        <?php echo $current_step == 1 ? 'Start Application' : 'Edit Application'; ?>
                    </a>
                    <a href="<?php echo htmlspecialchars($upload_docs_url); ?>" class="btn btn-success" style="padding: 10px 15px; font-size: 0.9rem;">
                    <i class="fas fa-upload"></i>
                    Upload Files
                </a>
                    <a href="submit-application.php" class="btn btn-primary" style="padding: 10px 15px; font-size: 0.9rem;">
                        <i class="fas fa-paper-plane"></i>
                        Submit Application
                    </a>
                    <a href="track-progress.php" class="btn btn-secondary" style="padding: 10px 15px; font-size: 0.9rem;">
                        <i class="fas fa-chart-line"></i>
                        Track Progress
                    </a>
                    <a href="support.php" class="btn btn-secondary" style="padding: 10px 15px; font-size: 0.9rem;">
                        <i class="fas fa-headset"></i>
                        Get Help
                    </a>
                </div>
            </div>

            <!-- Logout Button -->
            <a href="auth.php?action=logout" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
    </div>

    <script>
        // Add interactive functionality
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Enhanced Dashboard loaded successfully!');
            
            // Add click handlers for buttons
            document.querySelectorAll('.btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    if (this.classList.contains('btn-secondary') && !this.hasAttribute('href')) {
                        e.preventDefault();
                        const buttonText = this.textContent.trim();
                        alert(`${buttonText} feature coming soon!`);
                    }
                });
            });

            // Animate progress bar on load
            const progressBar = document.querySelector('.progress-bar');
            if (progressBar) {
                setTimeout(() => {
                    progressBar.style.width = '<?php echo $application_progress; ?>%';
                }, 500);
            }

            // Add hover effects to cards
            document.querySelectorAll('.card').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });

            // Notification click handler
            const notificationCard = document.querySelector('.quick-stats');
            if (notificationCard) {
                notificationCard.style.cursor = 'pointer';
                notificationCard.addEventListener('click', function() {
                    alert('You have 3 new notifications:\n- Document approved\n- Application update\n- New message from support');
                });
            }
        });

        // Real-time data polling
        async function fetchApplicationStatus() {
            try {
                const res = await fetch('api/application-status.php', { credentials: 'same-origin', cache: 'default', headers: { 'Accept': 'application/json' } });
                // Respect HTTP caching: skip updates on 304
                if (res.status === 304) {
                    return;
                }
                if (!res.ok) return;
                const data = await res.json();
                renderApplicationStatus(data);
            } catch (e) {
                console.error('Failed to fetch application status', e);
            }
        }

        function renderApplicationStatus(data) {
            // Progress
            if (typeof data.application_progress === 'number') {
                const progressPercentageEl = document.querySelector('.progress-percentage');
                if (progressPercentageEl) progressPercentageEl.textContent = data.application_progress + '%';
                const progressBar = document.querySelector('.progress-bar');
                if (progressBar) progressBar.style.width = data.application_progress + '%';
            }
            // Status badge
            if (data.application_status) {
                const badge = document.querySelector('.status-badge');
                if (badge) {
                    badge.textContent = String(data.application_status).toUpperCase();
                    badge.classList.toggle('completed', data.application_status === 'completed');
                    badge.classList.toggle('in-review', data.application_status !== 'completed');
                }
            }
            // Step markers
            if (typeof data.current_application_step === 'number') {
                const steps = document.querySelectorAll('.progress-steps .step');
                steps.forEach((el, idx) => {
                    const stepNum = idx + 1;
                    el.classList.remove('completed','current','locked');
                    if (data.current_application_step > stepNum) {
                        el.classList.add('completed');
                    } else if (data.current_application_step === stepNum) {
                        el.classList.add('current');
                    } else {
                        el.classList.add('locked');
                    }
                });
            }
            // Document statuses
            if (data.documents) {
                const docMap = {
                    'Certified ID Copy': 'id_copy',
                    'Academic Transcript': 'transcript',
                    'Proof of Residence': 'residence_proof',
                    'Passport Photo': 'passport_photo'
                };
                document.querySelectorAll('.document-status .document-item').forEach(item => {
                    const nameEl = item.querySelector('.doc-name');
                    const statusEl = item.querySelector('.doc-status');
                    if (!nameEl || !statusEl) return;
                    const name = nameEl.textContent.trim();
                    const key = docMap[name];
                    if (!key) return;
                    const st = data.documents[key] || 'pending';
                    statusEl.textContent = st === 'uploaded' ? 'Uploaded' : 'Pending';
                    statusEl.classList.toggle('uploaded', st === 'uploaded');
                    statusEl.classList.toggle('pending', st !== 'uploaded');
                });
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            fetchApplicationStatus();
            setInterval(fetchApplicationStatus, 15000);
        });
    </script>
</body>
</html>