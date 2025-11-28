<?php
session_start();
require_once 'config.php';

$verificationStatus = '';
$userEmail = '';
$userName = '';

// Check if token is provided
if (!isset($_GET['token']) || empty($_GET['token'])) {
    $verificationStatus = 'invalid_token';
} else {
    $token = $_GET['token'];
    
    try {
        // Create database connection
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        
        // Find user with this verification token (fallback if verification_sent_at column is missing)
        try {
            $stmt = $pdo->prepare("SELECT id, email, first_name, last_name, student_id, created_at, verification_sent_at FROM users WHERE verification_token = ? AND status = 'pending'");
            $stmt->execute([$token]);
        } catch (PDOException $selEx) {
            $msg = $selEx->getMessage();
            if (strpos($msg, 'Unknown column') !== false && strpos($msg, 'verification_sent_at') !== false) {
                if (defined('DEBUG_MODE') && DEBUG_MODE) {
                    error_log("verify-email: 'verification_sent_at' column missing on users; selecting without it.");
                }
                $stmt = $pdo->prepare("SELECT id, email, first_name, last_name, student_id, created_at FROM users WHERE verification_token = ? AND status = 'pending'");
                $stmt->execute([$token]);
            } else {
                throw $selEx;
            }
        }
        $user = $stmt->fetch();
        
        if (!$user) {
            $verificationStatus = 'invalid_token';
        } else {
            // Check if token is expired (24 hours) using verification_sent_at when available
            $createdAt = !empty($user['verification_sent_at']) ? new DateTime($user['verification_sent_at']) : new DateTime($user['created_at']);
            $now = new DateTime();
            $hoursDiff = $now->diff($createdAt)->h + ($now->diff($createdAt)->days * 24);
            
            if ($hoursDiff > 24) {
                $verificationStatus = 'expired_token';
                $userEmail = $user['email'];
            } else {
                // Activate the user account and mark email_verified
                try {
                    $stmt = $pdo->prepare("UPDATE users SET status = 'active', email_verified = 1, verification_token = NULL, verified_at = NOW() WHERE id = ?");
                    $stmt->execute([$user['id']]);
                } catch (PDOException $updateEx) {
                    // Fallback if 'verified_at' column is missing in some environments
                    $msg = $updateEx->getMessage();
                    if (strpos($msg, 'Unknown column') !== false && strpos($msg, 'verified_at') !== false) {
                        if (defined('DEBUG_MODE') && DEBUG_MODE) {
                            error_log("verify-email: 'verified_at' column missing on users; applying fallback update without it.");
                        }
                        $stmt = $pdo->prepare("UPDATE users SET status = 'active', email_verified = 1, verification_token = NULL WHERE id = ?");
                        $stmt->execute([$user['id']]);
                    } else {
                        throw $updateEx; // Re-throw unexpected errors
                    }
                }
                
                $verificationStatus = 'success';
                $userEmail = $user['email'];
                $userName = $user['first_name'] . ' ' . $user['last_name'];
                
                // Create session for the verified user
                $_SESSION['student_logged_in'] = true;
                $_SESSION['student_name'] = $userName;
                $_SESSION['student_email'] = $user['email'];
                $_SESSION['student_id'] = $user['student_id'];
                $_SESSION['user_id'] = $user['id'];
                
                // Clean up registration session
                unset($_SESSION['registration_email']);
            }
        }
        
    } catch (PDOException $e) {
        $verificationStatus = 'database_error';
        if (DEBUG_MODE) {
            error_log("Email verification error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - EduBridge SA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1a5fb4;
            --secondary: #2e7d32;
            --light: #f5f7fa;
            --dark: #1a237e;
            --accent: #ff9800;
            --success: #4caf50;
            --error: #f44336;
            --warning: #ff9800;
            --gradient-primary: linear-gradient(135deg, #1a5fb4 0%, #2e7d32 100%);
            --shadow-light: 0 4px 20px rgba(26, 95, 180, 0.1);
            --shadow-medium: 0 8px 30px rgba(26, 95, 180, 0.15);
            --shadow-heavy: 0 15px 50px rgba(26, 95, 180, 0.2);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--gradient-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            padding: 2rem 0;
        }

        .verification-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 3rem;
            box-shadow: var(--shadow-heavy);
            max-width: 500px;
            width: 90%;
            border: 1px solid rgba(255, 255, 255, 0.2);
            text-align: center;
        }

        .icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            box-shadow: var(--shadow-medium);
        }

        .icon i {
            font-size: 2rem;
            color: white;
        }

        .icon.success {
            background: var(--success);
        }

        .icon.error {
            background: var(--error);
        }

        .icon.warning {
            background: var(--warning);
        }

        .header h1 {
            color: var(--dark);
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .header p {
            color: #666;
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .user-info {
            background: #e8f5e8;
            border: 1px solid #c8e6c9;
            border-radius: 10px;
            padding: 1.5rem;
            margin: 2rem 0;
        }

        .user-info strong {
            color: var(--success);
            font-weight: 600;
        }

        .btn {
            display: inline-block;
            padding: 1rem 2rem;
            background: var(--gradient-primary);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 0.5rem;
            text-decoration: none;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .btn-secondary {
            background: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
        }

        .btn-secondary:hover {
            background: var(--primary);
            color: white;
        }

        .error-info {
            background: #ffebee;
            border: 1px solid #ffcdd2;
            border-radius: 10px;
            padding: 1.5rem;
            margin: 2rem 0;
            color: #c62828;
        }

        .warning-info {
            background: #fff3e0;
            border: 1px solid #ffcc02;
            border-radius: 10px;
            padding: 1.5rem;
            margin: 2rem 0;
            color: #e65100;
        }

        .links {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e0e0e0;
        }

        .links a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .links a:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .verification-container {
                padding: 2rem;
                margin: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="verification-container">
        <?php if ($verificationStatus === 'success'): ?>
            <div class="icon success">
                <i class="fas fa-check-circle"></i>
            </div>
            
            <div class="header">
                <h1>Email Verified Successfully!</h1>
                <p>Congratulations! Your email address has been verified and your account is now active.</p>
            </div>

            <div class="user-info">
                <strong>Welcome, <?php echo htmlspecialchars($userName); ?>!</strong><br>
                Your account (<?php echo htmlspecialchars($userEmail); ?>) is now ready to use.
            </div>

            <div style="margin: 2rem 0;">
                <a href="student-dashboard.php" class="btn">
                    <i class="fas fa-tachometer-alt"></i>
                    Go to Dashboard
                </a>
            </div>

            <script>
                // Auto-redirect to dashboard after 5 seconds
                setTimeout(function() {
                    window.location.href = 'student-dashboard.php';
                }, 5000);
            </script>

        <?php elseif ($verificationStatus === 'expired_token'): ?>
            <div class="icon warning">
                <i class="fas fa-clock"></i>
            </div>
            
            <div class="header">
                <h1>Verification Link Expired</h1>
                <p>This verification link has expired. Verification links are valid for 24 hours for security reasons.</p>
            </div>

            <div class="warning-info">
                <strong>Don't worry!</strong> You can request a new verification email to complete your registration.
            </div>

            <div style="margin: 2rem 0;">
                <a href="resend-verification.php?email=<?php echo urlencode($userEmail); ?>" class="btn">
                    <i class="fas fa-paper-plane"></i>
                    Send New Verification Email
                </a>
            </div>

        <?php elseif ($verificationStatus === 'invalid_token'): ?>
            <div class="icon error">
                <i class="fas fa-times-circle"></i>
            </div>
            
            <div class="header">
                <h1>Invalid Verification Link</h1>
                <p>This verification link is invalid or has already been used.</p>
            </div>

            <div class="error-info">
                <strong>Possible reasons:</strong><br>
                • The link has already been used<br>
                • The link is malformed or incomplete<br>
                • The account has already been verified
            </div>

            <div style="margin: 2rem 0;">
                <a href="student-login.php" class="btn">
                    <i class="fas fa-sign-in-alt"></i>
                    Try to Login
                </a>
                <a href="create-profile.php" class="btn btn-secondary">
                    <i class="fas fa-user-plus"></i>
                    Create New Account
                </a>
            </div>

        <?php else: ?>
            <div class="icon error">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            
            <div class="header">
                <h1>Verification Error</h1>
                <p>An error occurred while verifying your email address. Please try again or contact support.</p>
            </div>

            <div class="error-info">
                <strong>What you can do:</strong><br>
                • Try clicking the verification link again<br>
                • Request a new verification email<br>
                • Contact our support team for assistance
            </div>

            <div style="margin: 2rem 0;">
                <a href="resend-verification.php" class="btn">
                    <i class="fas fa-paper-plane"></i>
                    Request New Verification
                </a>
            </div>
        <?php endif; ?>

        <div class="links">
            <p>Need help? <a href="mailto:applications@edubridgesa.co.za">Contact Support</a></p>
            <p><a href="student-login.php">Back to Login</a></p>
        </div>
    </div>

    <script>
        // Add entrance animation
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.querySelector('.verification-container');
            container.style.opacity = '0';
            container.style.transform = 'translateY(30px)';
            
            setTimeout(() => {
                container.style.transition = 'all 0.6s ease';
                container.style.opacity = '1';
                container.style.transform = 'translateY(0)';
            }, 100);
        });
    </script>
</body>
</html>