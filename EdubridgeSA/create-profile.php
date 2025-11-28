<?php
session_start();
require_once 'config.php';

// Handle profile creation form submission
if ($_POST && isset($_POST['first_name']) && isset($_POST['last_name']) && isset($_POST['email']) && isset($_POST['password'])) {
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $phone = trim($_POST['phone'] ?? '');
    $dateOfBirth = $_POST['date_of_birth'] ?? '';
    $idNumber = trim($_POST['id_number'] ?? '');
    
    // Basic validation
    $errors = [];
    
    if (empty($firstName)) $errors[] = "First name is required.";
    if (empty($lastName)) $errors[] = "Last name is required.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email address is required.";
    if (empty($password) || strlen($password) < 6) $errors[] = "Password must be at least 6 characters long.";
    if ($password !== $confirmPassword) $errors[] = "Passwords do not match.";
    
    if (empty($errors)) {
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
            
            // Check if email already exists, with verified/unverified distinction
            require_once 'user_utils.php';
            $duplicateInfo = null;
            $exist = checkEmailExistence($email);
            if ($exist['exists']) {
                $duplicateInfo = [
                    'email' => $email,
                    'status' => $exist['status'] ?? 'unknown',
                    'email_verified' => (int)($exist['verified'] ? 1 : 0)
                ];
                if ($exist['verified']) {
                    $errors[] = "An account with this email is already verified. Please sign in or reset your password.";
                } else {
                    $errors[] = "Account exists but is not verified yet. You can resend the verification email.";
                }
            } else {
                // Hash the password
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                
                // Generate student ID
                $studentId = 'EBS' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                // Generate email verification token
                $verificationToken = bin2hex(random_bytes(32));
                
                // Insert new user with pending status and verification metadata
                $stmt = $pdo->prepare("
                    INSERT INTO users (
                        email, password_hash, first_name, last_name, phone, date_of_birth, id_number, student_id,
                        status, email_verified, verification_token, verification_sent_at, verification_attempts, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', 0, ?, NOW(), 1, NOW())
                ");
                
                $stmt->execute([
                    $email,
                    $passwordHash,
                    $firstName,
                    $lastName,
                    $phone,
                    $dateOfBirth ?: null,
                    $idNumber,
                    $studentId,
                    $verificationToken
                ]);
                
                $userId = $pdo->lastInsertId();
                
                // Send verification email (centralized function with logging)
                require_once 'email_functions.php';
                $sendResult = sendVerificationEmail($email, trim($firstName . ' ' . $lastName), $verificationToken);
                $emailSent = is_array($sendResult) ? ($sendResult['success'] ?? false) : (bool)$sendResult;
                if ($emailSent) {
                    // Redirect to verification pending page
                    $_SESSION['registration_email'] = $email;
                    header('Location: email-verification-pending.php');
                    exit();
                } else {
                    // Still redirect to pending page where user can resend verification
                    $_SESSION['pending_email'] = $email;
                    $_SESSION['flash_message'] = '<div class="alert alert-warning">Account created, but verification email failed. You can resend from the next page.</div>';
                    header('Location: email-verification-pending.php');
                    exit();
                }
            }
            
        } catch (PDOException $e) {
            $errors[] = "Database error: Unable to create account. Please try again.";
            if (DEBUG_MODE) {
                $errors[] = "Debug: " . $e->getMessage();
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
    <title>Create Profile - EduBridgeSA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1a5fb4;
            --secondary: #2e7d32;
            --light: #f5f7fa;
            --dark: #1a237e;
            --accent: #ff9800;
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

        .profile-container {
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
        }

        .form-row {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            flex: 1;
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

        .errors {
            background: #ffebee;
            color: #c62828;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            border: 1px solid #ffcdd2;
        }

        .errors ul {
            margin: 0;
            padding-left: 1.5rem;
        }

        .links {
            text-align: center;
            margin-top: 1.5rem;
        }

        .links a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
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

        .password-strength {
            margin-top: 0.5rem;
            font-size: 0.9rem;
        }

        .strength-weak { color: #f44336; }
        .strength-medium { color: #ff9800; }
        .strength-strong { color: #4caf50; }

        @media (max-width: 768px) {
            .profile-container {
                padding: 2rem;
                margin: 1rem;
            }

            .form-row {
                flex-direction: column;
                gap: 0;
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
    <a href="application-access.php" class="back-link">
        <i class="fas fa-arrow-left"></i>
        Back
    </a>

    <div class="profile-container">
        <div class="header">
            <div class="logo">
                <i class="fas fa-user-plus"></i>
            </div>
            <h1>Create Your Profile</h1>
            <p>Join EduBridgeSA and start your educational journey</p>
        </div>

        <?php if (isset($errors) && !empty($errors)): ?>
            <div class="errors">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Please fix the following errors:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($duplicateInfo)): ?>
            <div class="errors" style="background:#fff8e1;color:#5d4037;border-color:#ffe082;">
                <strong>Next steps for existing account:</strong>
                <ul>
                    <?php if (!(int)$duplicateInfo['email_verified']): ?>
                        <li>Your email is not verified yet. <a href="resend-verification.php?email=<?php echo urlencode($duplicateInfo['email']); ?>">Resend verification link</a>.</li>
                    <?php else: ?>
                        <li>Your email is verified. Please <a href="student-login.php">sign in</a> or <a href="forgot-password.php">reset your password</a>.</li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="create-profile.php">
            <div class="form-row">
                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" required 
                           value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>"
                           placeholder="Enter your first name">
                </div>

                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" required 
                           value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>"
                           placeholder="Enter your last name">
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required 
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                       placeholder="Enter your email address">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required 
                       placeholder="Create a strong password" minlength="6">
                <div class="password-strength" id="password-strength"></div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required 
                       placeholder="Confirm your password">
            </div>

            <button type="submit" class="btn">
                <i class="fas fa-user-plus"></i>
                Create Profile
            </button>
        </form>

        <div class="links">
            <a href="student-login.php">Already have an account? Sign In</a>
        </div>
    </div>

    <script>
        // Add entrance animation
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.querySelector('.profile-container');
            container.style.opacity = '0';
            container.style.transform = 'translateY(30px)';
            
            setTimeout(() => {
                container.style.transition = 'all 0.6s ease';
                container.style.opacity = '1';
                container.style.transform = 'translateY(0)';
            }, 100);
        });

        // Password strength indicator
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthDiv = document.getElementById('password-strength');
            
            if (password.length === 0) {
                strengthDiv.textContent = '';
                return;
            }
            
            let strength = 0;
            if (password.length >= 6) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            if (strength < 3) {
                strengthDiv.textContent = 'Weak password';
                strengthDiv.className = 'password-strength strength-weak';
            } else if (strength < 4) {
                strengthDiv.textContent = 'Medium strength';
                strengthDiv.className = 'password-strength strength-medium';
            } else {
                strengthDiv.textContent = 'Strong password';
                strengthDiv.className = 'password-strength strength-strong';
            }
        });

        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>