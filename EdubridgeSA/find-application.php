<?php
require_once 'session_config.php';
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: student-login.php');
    exit;
}

$student_email = $_SESSION['student_email'] ?? '';
$message = '';
$messageType = '';
$applications = [];
// Auto-fetch applications for logged-in user's email
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !empty($student_email)) {
    try {
        $stmt = $pdo->prepare("SELECT id, reference_number, full_name, surname, created_at, status FROM applications WHERE email_address = ? ORDER BY created_at DESC");
        $stmt->execute([$student_email]);
        $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($applications)) {
            $message = 'Found ' . count($applications) . ' application(s) for your email.';
            $messageType = 'success';
        }
    } catch (PDOException $e) {
        error_log("Find application auto-lookup error: " . $e->getMessage());
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'config.php';
    
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $message = 'Please enter your email address.';
        $messageType = 'error';
    } else {
        try {
            // Find applications for this email
            $stmt = $pdo->prepare("SELECT id, reference_number, full_name, surname, created_at, status FROM applications WHERE email_address = ? ORDER BY created_at DESC");
            $stmt->execute([$email]);
            $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($applications)) {
                $message = 'No applications found for this email address.';
                $messageType = 'error';
            } else {
                $message = 'Found ' . count($applications) . ' application(s) for your email.';
                $messageType = 'success';
            }
        } catch (PDOException $e) {
            $message = 'Database error occurred. Please try again.';
            $messageType = 'error';
            error_log("Find application error: " . $e->getMessage());
        }
    }
}

// Handle setting application reference in session
if (isset($_GET['set_ref']) && !empty($_GET['set_ref'])) {
    $_SESSION['application_ref'] = $_GET['set_ref'];
    $_SESSION['reference_number'] = $_GET['set_ref'];
    $_SESSION['form_message'] = 'Application reference set successfully! You can now upload documents.';
    $_SESSION['form_message_type'] = 'success';
    header('Location: student-dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find My Application - EduBridge SA</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --royal-blue: #1e3a8a;
            --royal-blue-light: #3b82f6;
            --emerald-green: #059669;
            --emerald-green-light: #10b981;
            --gold: #f59e0b;
            --gold-light: #fbbf24;
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
            background: linear-gradient(135deg, var(--gray-50) 0%, var(--gray-100) 100%);
            min-height: 100vh;
        }

        .nav-container {
            background: var(--white);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        nav {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: var(--royal-blue);
            font-weight: 700;
            font-size: 1.5rem;
        }
        .logo img {
            height: 32px;
            width: auto;
            margin-right: 0.5rem;
        }

        .container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .card {
            background: var(--white);
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .card-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .card-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 0.5rem;
        }

        .card-subtitle {
            color: var(--gray-600);
            font-size: 1rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--gray-700);
        }

        .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--royal-blue);
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
        }

        .btn {
            background: var(--royal-blue);
            color: var(--white);
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.3s ease;
        }

        .btn:hover {
            background: var(--royal-blue-light);
        }

        .btn-secondary {
            background: var(--gray-500);
        }

        .btn-secondary:hover {
            background: var(--gray-600);
        }

        .btn-success {
            background: var(--emerald-green);
        }

        .btn-success:hover {
            background: var(--emerald-green-light);
        }

        .message {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .applications-list {
            margin-top: 2rem;
        }

        .application-item {
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .application-info h4 {
            color: var(--gray-800);
            margin-bottom: 0.5rem;
        }

        .application-details {
            color: var(--gray-600);
            font-size: 0.9rem;
        }

        .application-ref {
            font-weight: 600;
            color: var(--royal-blue);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--royal-blue);
            text-decoration: none;
            margin-bottom: 2rem;
            font-weight: 500;
        }

        .back-link:hover {
            color: var(--royal-blue-light);
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 0.5rem;
            }

            .card {
                padding: 1.5rem;
            }

            .application-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="nav-container">
        <nav>
            <a href="index.php" class="logo">
                <img src="images/logo.png.jpg" alt="EduBridge SA" onerror="this.onerror=null;this.src='images/logo.png';">
                <span>EduBridge SA</span>
            </a>
        </nav>
    </div>

    <div class="container">
        <a href="student-dashboard.php" class="back-link">
            <i class="fas fa-arrow-left"></i>
            Back to Dashboard
        </a>

        <div class="card">
            <div class="card-header">
                <h1 class="card-title">Find My Application</h1>
                <p class="card-subtitle">Enter your email address to find your applications and retrieve your reference number</p>
            </div>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo htmlspecialchars($messageType); ?>">
                    <?php if ($messageType === 'success'): ?>
                        <i class="fas fa-check-circle"></i>
                    <?php else: ?>
                        <i class="fas fa-exclamation-circle"></i>
                    <?php endif; ?>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="find-application.php">
                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" id="email" name="email" class="form-input" 
                           value="<?php echo htmlspecialchars($student_email); ?>" 
                           placeholder="Enter the email used for your application" required>
                </div>

                <button type="submit" class="btn">
                    <i class="fas fa-search"></i>
                    Find Applications
                </button>
            </form>

            <?php if (!empty($applications)): ?>
                <div class="applications-list">
                    <h3 style="margin-bottom: 1rem; color: var(--gray-800);">Your Applications</h3>
                    
                    <?php foreach ($applications as $app): ?>
                        <div class="application-item">
                            <div class="application-info">
                                <h4><?php echo htmlspecialchars($app['full_name'] . ' ' . $app['surname']); ?></h4>
                                <div class="application-details">
                                    <div><strong>Reference:</strong> <span class="application-ref"><?php echo htmlspecialchars($app['reference_number']); ?></span></div>
                                    <div><strong>Submitted:</strong> <?php echo date('F j, Y', strtotime($app['created_at'])); ?></div>
                                    <div><strong>Status:</strong> <?php echo htmlspecialchars($app['status'] ?? 'Pending'); ?></div>
                                </div>
                            </div>
                            <div style="display: flex; gap: 0.5rem; flex-direction: column;">
                                <a href="?set_ref=<?php echo urlencode($app['reference_number']); ?>" class="btn btn-success">
                                    <i class="fas fa-check"></i>
                                    Use This Application
                                </a>
                                <?php $ref = $app['reference_number']; ?>
                                <a href="document_upload.php?ref=<?php echo urlencode($ref); ?>" class="btn">
                                    <i class="fas fa-upload"></i>
                                    Upload Documents
                                </a>
                                <a href="application_status.php?ref=<?php echo urlencode($app['reference_number']); ?>" class="btn btn-info">
                                    <i class="fas fa-chart-line"></i>
                                    View Status
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>