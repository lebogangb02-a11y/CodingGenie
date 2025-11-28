<?php
session_start();
require_once 'config.php';

$message = '';
$messageType = '';
$email = $_GET['email'] ?? $_SESSION['registration_email'] ?? '';

// Handle form submission
if ($_POST && isset($_POST['email'])) {
    $email = trim($_POST['email']);
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $messageType = 'error';
    } else {
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
            
            // Find user with pending status and track attempts
            // Helper: check if a column exists in users table (schema compatibility)
            $columnExists = function(PDO $pdo, string $column): bool {
                try {
                    $sql = "SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'users' AND COLUMN_NAME = ?";
                    $st = $pdo->prepare($sql);
                    $st->execute([DB_NAME, $column]);
                    $row = $st->fetch();
                    return (int)($row['cnt'] ?? 0) > 0;
                } catch (Throwable $e) {
                    return false; // fail safe
                }
            };

            $hasSentAt = $columnExists($pdo, 'verification_sent_at');
            $hasAttempts = $columnExists($pdo, 'verification_attempts');

            // Build SELECT dynamically based on available columns
            $selectCols = ['id', 'first_name', 'last_name'];
            if ($hasAttempts) { $selectCols[] = 'verification_attempts'; }
            if ($hasSentAt)  { $selectCols[] = 'verification_sent_at'; }
            $selectSql = "SELECT " . implode(', ', $selectCols) . " FROM users WHERE email = ? AND status = 'pending'";

            $stmt = $pdo->prepare($selectSql);
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $message = 'No pending account found with this email address. The account may already be verified or does not exist.';
                $messageType = 'error';
            } else {
                // Simple rate limiting (if metadata available): max 5 attempts within 1 hour window
                $attempts = $hasAttempts ? (int)($user['verification_attempts'] ?? 0) : 0;
                $lastSent = ($hasSentAt && !empty($user['verification_sent_at'])) ? new DateTime($user['verification_sent_at']) : null;
                $now = new DateTime();
                $withinHour = $lastSent ? (($now->getTimestamp() - $lastSent->getTimestamp()) < 3600) : false;

                if ($hasAttempts && $hasSentAt && $attempts >= 5 && $withinHour) {
                    $message = 'You have requested too many verification emails. Please wait an hour before trying again.';
                    $messageType = 'error';
                } else {
                    // Generate new verification token
                    $verificationToken = bin2hex(random_bytes(32));
                    
                    // Update user with new token and attempt metadata (only if columns exist)
                    if ($hasAttempts && $hasSentAt) {
                        $stmt = $pdo->prepare("UPDATE users SET verification_token = ?, verification_sent_at = NOW(), verification_attempts = verification_attempts + 1 WHERE id = ?");
                        $stmt->execute([$verificationToken, $user['id']]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET verification_token = ? WHERE id = ?");
                        $stmt->execute([$verificationToken, $user['id']]);
                    }
                    
                    // Send new verification email via unified email functions (with logging)
                    require_once 'email_functions.php';
                    $result = sendVerificationEmail($email, trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')), $verificationToken);
                    $emailSent = is_array($result) ? ($result['success'] ?? false) : (bool)$result;
                    if ($emailSent) {
                        $message = 'A new verification email has been sent to your email address. Please check your inbox.';
                        $messageType = 'success';
                        $_SESSION['registration_email'] = $email;
                    } else {
                        $message = 'Failed to send verification email. Please try again or contact support.';
                        $messageType = 'error';
                    }
                }
            }
            
        } catch (PDOException $e) {
            $message = 'Database error occurred. Please try again later.';
            $messageType = 'error';
            if (DEBUG_MODE) {
                error_log("Resend verification error: " . $e->getMessage());
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
    <title>Resend Verification Email - EduBridge SA</title>
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

        .resend-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 3rem;
            box-shadow: var(--shadow-heavy);
            max-width: 500px;
            width: 90%;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo {
            width: 60px;
            height: 60px;
            background: var(--gradient-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            box-shadow: var(--shadow-medium);
        }

        .logo i {
            font-size: 1.5rem;
            color: white;
        }

        .header h1 {
            color: var(--dark);
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .header p {
            color: #666;
            font-size: 1rem;
            line-height: 1.6;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--dark);
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 95, 180, 0.1);
        }

        .btn {
            width: 100%;
            padding: 1rem;
            background: var(--gradient-primary);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 1rem;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .message {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            border: 1px solid;
        }

        .message.success {
            background: #e8f5e8;
            color: #2e7d32;
            border-color: #c8e6c9;
        }

        .message.error {
            background: #ffebee;
            color: #c62828;
            border-color: #ffcdd2;
        }

        .message i {
            margin-right: 0.5rem;
        }

        .links {
            text-align: center;
            margin-top: 1.5rem;
        }

        .links a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            margin: 0 1rem;
        }

        .links a:hover {
            text-decoration: underline;
        }

        .back-link {
            position: absolute;
            top: 2rem;
            left: 2rem;
            background: rgba(255, 255, 255, 0.9);
            color: var(--primary);
            padding: 0.8rem 1.2rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-light);
        }

        .back-link:hover {
            background: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .back-link i {
            margin-right: 0.5rem;
        }

        @media (max-width: 768px) {
            .resend-container {
                padding: 2rem;
                margin: 1rem;
            }

            .back-link {
                top: 1rem;
                left: 1rem;
                padding: 0.6rem 1rem;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <!-- Back Link -->
    <a href="student-login.php" class="back-link">
        <i class="fas fa-arrow-left"></i>
        Back to Login
    </a>

    <div class="resend-container">
        <div class="header">
            <div class="logo">
                <i class="fas fa-paper-plane"></i>
            </div>
            <h1>Resend Verification Email</h1>
            <p>Enter your email address to receive a new verification link</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $messageType; ?>">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($messageType !== 'success'): ?>
            <form method="POST" action="resend-verification.php">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo htmlspecialchars($email); ?>"
                           placeholder="Enter your email address">
                </div>

                <button type="submit" class="btn">
                    <i class="fas fa-paper-plane"></i>
                    Send Verification Email
                </button>
            </form>
        <?php else: ?>
            <div style="text-align: center; margin: 2rem 0;">
                <a href="email-verification-pending.php" class="btn" style="display: inline-block; text-decoration: none;">
                    <i class="fas fa-envelope"></i>
                    Check Email Status
                </a>
            </div>
        <?php endif; ?>

        <div class="links">
            <a href="student-login.php">Back to Login</a>
            <a href="create-profile.php">Create New Account</a>
        </div>
    </div>

    <script>
        // Add entrance animation
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.querySelector('.resend-container');
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