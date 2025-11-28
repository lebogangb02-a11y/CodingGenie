<?php
/**
 * Student Login Page for EduBridge SA - Simplified & Fixed
 * Handles authentication with email + reference number
 * Redirects to new student dashboard on success
 */

require_once 'session_config.php';
require_once 'config.php';
require_once 'security-utils.php';

// SIMPLIFIED SESSION CHECK - Only redirect if truly logged in
if (isset($_SESSION['student_logged_in']) && $_SESSION['student_logged_in'] === true) {
    // Check if we have basic session data
    if (!empty($_SESSION['student_email'])) {
        $current_page = basename($_SERVER['PHP_SELF']);
        if ($current_page === 'student-login.php') {
            // Safe redirect
            $redirect = $_GET['redirect'] ?? 'student-dashboard.php';
            if ($redirect === 'student-login.php') {
                $redirect = 'student-dashboard.php';
            }
            header('Location: ' . $redirect);
            exit();
        }
    } else {
        // Clear invalid session
        unset($_SESSION['student_logged_in']);
    }
}

// Initialize variables
$error = '';
$email = '';
$reference_number = '';
$success = '';

// Handle POST login attempt
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $email = $email ? strtolower($email) : '';
    $raw_login_method = $_POST['login_method'] ?? '';
    $reference_number = trim($_POST['reference_number'] ?? '');
    $reference_number = $reference_number ? strtoupper(preg_replace('/[\s\-_]+/', '', $reference_number)) : '';
    $password = $_POST['password'] ?? '';

    // DEBUG: Log the received data
    error_log("Login attempt - Email: $email, Method: $raw_login_method, Reference: $reference_number");

    // Determine login method
    if ($raw_login_method === 'reference' || $raw_login_method === 'password') {
        $login_method = $raw_login_method;
    } elseif (!empty($reference_number) && empty($password)) {
        $login_method = 'reference';
    } elseif (!empty($password) && empty($reference_number)) {
        $login_method = 'password';
    } else {
        $login_method = 'reference'; // Default
    }

    // Basic validation
    if (empty($email)) {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($login_method === 'reference' && empty($reference_number)) {
        $error = 'Please enter your application reference number.';
    } elseif ($login_method === 'password' && empty($password)) {
        $error = 'Please enter your password.';
    } else {
        try {
            // Check rate limiting
            $client_ip = SecurityUtils::getClientIP();
            SecurityUtils::checkRateLimit($pdo, $client_ip, 'login_attempt', 5, 900);
            
            // Validate email
            $validated_email = SecurityUtils::validateEmail($email);
            if (!$validated_email) {
                throw new Exception('Please enter a valid email address.');
            }
            $email = strtolower($validated_email);
            
            $authenticated = false;
            $user_data = null;
            
            if ($login_method === 'reference') {
                // FIXED: Use consistent email comparison
                $stmt = $pdo->prepare("
                    SELECT *, LOWER(email_address) as normalized_email 
                    FROM applications 
                    WHERE LOWER(email_address) = LOWER(?) AND reference_number = ?
                ");
                $stmt->execute([$email, $reference_number]);
                $application = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($application) {
                    error_log("Reference login SUCCESS - App ID: " . $application['id']);
                    $authenticated = true;
                    $user_data = $application;
                    $user_data['auth_method'] = 'reference';
                    
                    // Ensure user_id is set for reference logins
                    if (empty($user_data['user_id']) && !empty($user_data['id'])) {
                        $user_data['user_id'] = $user_data['id'];
                    }
                } else {
                    error_log("Reference login FAILED - Email: $email, Ref: $reference_number");
                    // Check if email exists but reference is wrong
                    $stmt = $pdo->prepare("SELECT reference_number FROM applications WHERE LOWER(email_address) = LOWER(?) LIMIT 1");
                    $stmt->execute([$email]);
                    $existing_app = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($existing_app) {
                        $error = 'Invalid reference number. Your reference should be: ' . htmlspecialchars($existing_app['reference_number']);
                    } else {
                        $error = 'No application found with this email address.';
                    }
                }
            } else {
                // Password-based login
                $stmt = $pdo->prepare("
                    SELECT u.id, u.student_id, u.first_name, u.last_name, u.email, u.password_hash, 
                           u.status as user_status, u.email_verified,
                           a.id as application_id, a.reference_number, a.status as application_status
                    FROM users u 
                    LEFT JOIN applications a ON u.id = a.user_id
                    WHERE LOWER(u.email) = LOWER(?)
                    LIMIT 1
                ");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user) {
                    $error = 'No password-based account found. Please use Application Reference login.';
                } elseif ($user['user_status'] !== 'active') {
                    $error = 'Your account is not active. Please contact support.';
                } elseif (!password_verify($password, $user['password_hash'])) {
                    $error = 'Invalid password. Please try again.';
                } else {
                    error_log("Password login SUCCESS - User ID: " . $user['id']);
                    $authenticated = true;
                    $user_data = $user;
                    $user_data['auth_method'] = 'password';

                    // Update last login
                    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $stmt->execute([$user['id']]);
                }
            }
            
            if ($authenticated && $user_data) {
                // SIMPLIFIED SESSION SETUP - Consistent for both methods
                $_SESSION['student_logged_in'] = true;
                $_SESSION['student_email'] = $user_data['email'] ?? $user_data['email_address'];
                $_SESSION['user_type'] = 'student';
                $_SESSION['last_activity'] = time();
                $_SESSION['auth_method'] = $user_data['auth_method'];
                
                // Set consistent identifiers
                if ($user_data['auth_method'] === 'reference') {
                    $_SESSION['student_id'] = $user_data['id']; // application id
                    $_SESSION['reference_number'] = $user_data['reference_number'];
                    $_SESSION['student_name'] = $user_data['full_name'] ?? 'Student';
                    if (!empty($user_data['user_id'])) {
                        $_SESSION['user_id'] = $user_data['user_id'];
                    }
                } else {
                    $_SESSION['user_id'] = $user_data['id']; // user id
                    $_SESSION['student_id'] = $user_data['student_id'] ?? $user_data['id'];
                    $_SESSION['student_name'] = $user_data['first_name'] . ' ' . $user_data['last_name'];
                    if (!empty($user_data['reference_number'])) {
                        $_SESSION['reference_number'] = $user_data['reference_number'];
                    }
                }
                
                // Log successful login
                SecurityUtils::logSecurityEvent(
                    $pdo,
                    'login_success',
                    'Student logged in via ' . $user_data['auth_method'],
                    $_SESSION['user_id'] ?? $_SESSION['student_id'],
                    $client_ip
                );

                error_log("Session created - Student: " . $_SESSION['student_email'] . ", ID: " . $_SESSION['student_id']);

                // Ensure session is saved
                session_write_close();
                
                // Redirect
                $redirect = $_POST['redirect'] ?? ($_GET['redirect'] ?? 'student-dashboard.php');
                if ($redirect === 'student-login.php') {
                    $redirect = 'student-dashboard.php';
                }
                
                header('Location: ' . $redirect);
                exit();
            } else {
                SecurityUtils::logSecurityEvent(
                    $pdo,
                    'login_failed',
                    'Failed login for: ' . $email . ' via ' . $login_method,
                    null,
                    $client_ip
                );
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
            error_log("Login error: " . $e->getMessage());
        }
    }
}

// Display flash messages
if (isset($_SESSION['flash_message'])) {
    $success = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Login - EduBridge SA</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Your existing CSS remains the same */
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
            background: linear-gradient(135deg, var(--royal-blue) 0%, var(--royal-blue-light) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .login-container {
            background: var(--white);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 450px;
        }

        .login-header {
            background: linear-gradient(135deg, var(--royal-blue) 0%, var(--royal-blue-light) 100%);
            color: var(--white);
            padding: 2rem;
            text-align: center;
        }

        .login-header h1 {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .login-header .logo-img {
            height: 36px;
            width: auto;
            vertical-align: middle;
            margin-right: 10px;
            border-radius: 4px;
        }

        .login-header p {
            opacity: 0.9;
            font-size: 0.95rem;
        }

        .login-form {
            padding: 2rem;
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
            border: 2px solid var(--gray-200);
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--royal-blue);
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
        }

        .btn {
            width: 100%;
            background: linear-gradient(135deg, var(--royal-blue) 0%, var(--royal-blue-light) 100%);
            color: var(--white);
            padding: 0.875rem;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 1rem;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(30, 58, 138, 0.2);
        }

        .message {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
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

        .login-links {
            text-align: center;
            margin-top: 1.5rem;
        }

        .login-links a {
            color: var(--royal-blue);
            text-decoration: none;
            font-weight: 500;
            margin: 0 0.5rem;
        }

        .login-links a:hover {
            color: var(--royal-blue-light);
        }

        .login-method-toggle {
            display: flex;
            background: var(--gray-100);
            border-radius: 10px;
            padding: 0.25rem;
            margin-top: 0.5rem;
        }

        .login-method-toggle input[type="radio"] {
            display: none;
        }

        .toggle-option {
            flex: 1;
            text-align: center;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            color: var(--gray-600);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .toggle-option:hover {
            color: var(--royal-blue);
        }

        .login-method-toggle input[type="radio"]:checked + .toggle-option {
            background: var(--royal-blue);
            color: white;
            box-shadow: 0 2px 4px rgba(30, 58, 138, 0.2);
        }

        .password-input-container {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray-400);
            cursor: pointer;
            padding: 0.25rem;
            font-size: 1rem;
            transition: color 0.3s ease;
        }

        .password-toggle:hover {
            color: var(--royal-blue);
        }

        .divider {
            text-align: center;
            margin: 1.5rem 0;
            position: relative;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: var(--gray-200);
        }

        .divider span {
            background: var(--white);
            padding: 0 1rem;
            color: var(--gray-500);
            font-size: 0.9rem;
        }

        @media (max-width: 480px) {
            .login-container {
                margin: 0.5rem;
            }
            
            .login-header, .login-form {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1><img src="images/logo.png.jpg" alt="EduBridge SA" class="logo-img" onerror="this.style.display='none'"> EduBridge SA</h1>
            <p>Student Portal Login</p>
        </div>
        
        <div class="login-form">
            <?php if (!empty($success)): ?>
                <div class="message success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="message error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="student-login.php">
                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($_GET['redirect'] ?? ''); ?>">
                
                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" id="email" name="email" class="form-input" 
                           value="<?php echo htmlspecialchars($email); ?>" 
                           placeholder="Enter your email address" 
                           autocomplete="email"
                           required>
                </div>

                <div class="form-group">
                    <label class="form-label">Login Method</label>
                    <div class="login-method-toggle">
                        <input type="radio" id="method_reference" name="login_method" value="reference" checked>
                        <label for="method_reference" class="toggle-option">
                            <i class="fas fa-file-alt"></i>
                            Application Reference
                        </label>
                        
                        <input type="radio" id="method_password" name="login_method" value="password">
                        <label for="method_password" class="toggle-option">
                            <i class="fas fa-key"></i>
                            Password
                        </label>
                    </div>
                </div>

                <div class="form-group" id="reference_field">
                    <label for="reference_number" class="form-label">Application Reference Number</label>
                    <input type="text" id="reference_number" name="reference_number" class="form-input" 
                           value="<?php echo htmlspecialchars($reference_number); ?>" 
                           placeholder="Enter your application reference number (e.g., APP2025097459)"
                           autocomplete="off"
                           pattern="APP[0-9]{10}"
                           title="Reference format: APP##########">
                </div>

                <div class="form-group" id="password_field" style="display: none;">
                    <label for="password" class="form-label">Password</label>
                    <div class="password-input-container">
                        <input type="password" id="password" name="password" class="form-input" 
                               placeholder="Enter your password"
                               autocomplete="current-password">
                        <button type="button" class="password-toggle" onclick="togglePassword()">
                            <i class="fas fa-eye" id="password-toggle-icon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn">
                    <i class="fas fa-sign-in-alt"></i>
                    Login to Dashboard
                </button>
            </form>

            <div class="divider">
                <span>Need Help?</span>
            </div>

            <div class="login-links">
                <a href="find-application.php">
                    <i class="fas fa-search"></i>
                    Find My Application
                </a>
                <br><br>
                <a href="student-register.php">
                    <i class="fas fa-user-plus"></i>
                    Create New Application
                </a>
                <br><br>
                <a href="index.php">
                    <i class="fas fa-home"></i>
                    Back to Home
                </a>
            </div>
        </div>
    </div>

    <script>
        function toggleLoginMethod() {
            const referenceMethod = document.getElementById('method_reference');
            const referenceField = document.getElementById('reference_field');
            const passwordField = document.getElementById('password_field');
            const referenceInput = document.getElementById('reference_number');
            const passwordInput = document.getElementById('password');

            if (referenceMethod.checked) {
                referenceField.style.display = 'block';
                passwordField.style.display = 'none';
                referenceInput.required = true;
                passwordInput.required = false;
                passwordInput.value = '';
            } else {
                referenceField.style.display = 'none';
                passwordField.style.display = 'block';
                referenceInput.required = false;
                passwordInput.required = true;
                referenceInput.value = '';
            }
        }

        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('password-toggle-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const emailField = document.getElementById('email');
            const referenceMethod = document.getElementById('method_reference');
            const passwordMethod = document.getElementById('method_password');
            
            if (emailField.value === '1' || emailField.value === '0') {
                emailField.value = '';
            }
            
            if (!emailField.value) {
                emailField.focus();
            }
            
            referenceMethod.addEventListener('change', toggleLoginMethod);
            passwordMethod.addEventListener('change', toggleLoginMethod);
            
            toggleLoginMethod();
        });
    </script>
</body>
</html>