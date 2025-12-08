<?php
require_once 'config.php';
require_once 'session_config.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';
require_once 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: student-dashboard.php');
    exit();
}

// Generate CSRF token
if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Server-side CSRF enforcement (best-effort)
    if (function_exists('require_csrf')) { require_csrf(); }

    // CSRF protection
    if (!isset($_POST[CSRF_TOKEN_NAME]) || $_POST[CSRF_TOKEN_NAME] !== $_SESSION[CSRF_TOKEN_NAME]) {
        $message = 'Security token mismatch. Please try again.';
        $message_type = 'error';
    } else {
        $email = trim($_POST['email'] ?? '');
        
        if (empty($email)) {
            $message = 'Please enter your email address.';
            $message_type = 'error';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address.';
            $message_type = 'error';
        } else {
            try {
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

                // Check if user exists
                $stmt = $pdo->prepare("SELECT student_id, first_name, last_name FROM users WHERE email = ? AND email_verified = 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user) {
                    // Generate reset token
                    $reset_token = bin2hex(random_bytes(32));
                    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

                    // Store reset token
                    $stmt = $pdo->prepare("
                        INSERT INTO password_reset_tokens (student_id, token, expires_at, created_at) 
                        VALUES (?, ?, ?, NOW())
                        ON DUPLICATE KEY UPDATE 
                        token = VALUES(token), 
                        expires_at = VALUES(expires_at), 
                        created_at = NOW()
                    ");
                    $stmt->execute([$user['student_id'], $reset_token, $expires_at]);

                    // Send reset email
                    $mail = new PHPMailer(true);
                    
                    try {
                        // Server settings
                        $mail->isSMTP();
                        $mail->Host = SMTP_HOST;
                        $mail->SMTPAuth = true;
                        $mail->Username = SMTP_USERNAME;
                        $mail->Password = SMTP_PASSWORD;
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = SMTP_PORT;

                        // Recipients
                        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                        $mail->addAddress($email, $user['first_name'] . ' ' . $user['last_name']);

                        // Content
                        $mail->isHTML(true);
                        $mail->Subject = 'Password Reset Request - EduBridge SA';
                        
                        $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset-password.php?token=" . $reset_token;
                        
                        $mail->Body = "
                        <html>
                        <head>
                            <style>
                                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                                .header { background: #1a5fb4; color: white; padding: 20px; text-align: center; }
                                .content { padding: 20px; background: #f9f9f9; }
                                .button { display: inline-block; padding: 12px 24px; background: #1a5fb4; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                                .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
                            </style>
                        </head>
                        <body>
                            <div class='container'>
                                <div class='header'>
                                    <h1>Password Reset Request</h1>
                                </div>
                                <div class='content'>
                                    <p>Hello " . htmlspecialchars($user['first_name']) . ",</p>
                                    <p>We received a request to reset your password for your EduBridge SA account.</p>
                                    <p>Click the button below to reset your password:</p>
                                    <p><a href='" . $reset_link . "' class='button'>Reset Password</a></p>
                                    <p>Or copy and paste this link into your browser:</p>
                                    <p>" . $reset_link . "</p>
                                    <p><strong>This link will expire in 1 hour.</strong></p>
                                    <p>If you didn't request this password reset, please ignore this email. Your password will remain unchanged.</p>
                                </div>
                                <div class='footer'>
                                    <p>© 2024 EduBridge SA. All rights reserved.</p>
                                </div>
                            </div>
                        </body>
                        </html>";

                        $mail->send();
                        
                        $message = 'Password reset instructions have been sent to your email address.';
                        $message_type = 'success';
                        
                    } catch (Exception $e) {
                        $message = 'Failed to send reset email. Please try again later.';
                        $message_type = 'error';
                    }
                } else {
                    // Don't reveal if email exists or not for security
                    $message = 'If an account with that email exists, password reset instructions have been sent.';
                    $message_type = 'info';
                }

                // Regenerate CSRF token
                $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));

            } catch (PDOException $e) {
                $message = 'Database error occurred. Please try again later.';
                $message_type = 'error';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - EduBridge SA</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1a5fb4;
            --primary-dark: #155a9e;
            --secondary-color: #26a269;
            --accent-color: #f66151;
            --background-color: #f8f9fa;
            --surface-color: #ffffff;
            --text-primary: #2d3748;
            --text-secondary: #718096;
            --border-color: #e2e8f0;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --error-color: #ef4444;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
            --border-radius: 8px;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .forgot-container {
            background: var(--surface-color);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            padding: 2rem;
            width: 100%;
            max-width: 400px;
        }

        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        .logo img {
            height: 48px;
            width: auto;
            margin-bottom: 0.75rem;
        }

        .logo h1 {
            color: var(--text-primary);
            font-size: 1.5rem;
            font-weight: 600;
        }

        .form-title {
            text-align: center;
            color: var(--text-primary);
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .form-subtitle {
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 2rem;
        }

        .alert {
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
            border: 1px solid;
        }

        .alert.success {
            background: #d1fae5;
            border-color: var(--success-color);
            color: #065f46;
        }

        .alert.error {
            background: #fee2e2;
            border-color: var(--error-color);
            color: #991b1b;
        }

        .alert.info {
            background: #dbeafe;
            border-color: var(--primary-color);
            color: #1e40af;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
            font-weight: 500;
        }

        input[type="email"] {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: var(--transition);
        }

        input[type="email"]:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(26, 95, 180, 0.1);
        }

        .submit-btn {
            width: 100%;
            background: var(--primary-color);
            color: white;
            padding: 0.75rem;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .submit-btn:hover {
            background: var(--primary-dark);
        }

        .submit-btn:disabled {
            background: var(--text-secondary);
            cursor: not-allowed;
        }

        .back-link {
            text-align: center;
            margin-top: 1.5rem;
        }

        .back-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .back-link a:hover {
            color: var(--primary-dark);
        }

        @media (max-width: 480px) {
            .forgot-container {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <div class="logo">
            <img src="images/logo.png.jpg" alt="EduBridge SA" onerror="this.onerror=null;this.src='images/logo.png';">
            <h1>EduBridge SA</h1>
        </div>

        <h2 class="form-title">Forgot Password</h2>
        <p class="form-subtitle">Enter your email address and we'll send you a link to reset your password.</p>

        <?php if ($message): ?>
            <div class="alert <?php echo htmlspecialchars($message_type); ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="forgot-password.php">
            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo $_SESSION[CSRF_TOKEN_NAME]; ?>">
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required 
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                       placeholder="Enter your email address">
            </div>

            <button type="submit" class="submit-btn">
                <i class="fas fa-paper-plane"></i> Send Reset Link
            </button>
        </form>

        <div class="back-link">
            <a href="student-login.php">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>
        </div>
    </div>
</body>
</html>