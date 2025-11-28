<?php
require_once 'config.php';
require_once 'session_config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: student-login.php');
    exit();
}

// Generate CSRF token
if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}

$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!isset($_POST[CSRF_TOKEN_NAME]) || $_POST[CSRF_TOKEN_NAME] !== $_SESSION[CSRF_TOKEN_NAME]) {
        $message = 'Security token mismatch. Please try again.';
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

            $action = $_POST['action'] ?? '';

            if ($action === 'change_password') {
                // Handle password change
                $current_password = $_POST['current_password'] ?? '';
                $new_password = $_POST['new_password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';

                $errors = [];

                if (empty($current_password)) {
                    $errors[] = 'Current password is required.';
                }
                if (empty($new_password)) {
                    $errors[] = 'New password is required.';
                }
                if (strlen($new_password) < 8) {
                    $errors[] = 'New password must be at least 8 characters long.';
                }
                if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $new_password)) {
                    $errors[] = 'New password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.';
                }
                if ($new_password !== $confirm_password) {
                    $errors[] = 'New password and confirmation do not match.';
                }

                if (empty($errors)) {
                    // Verify current password
                    $stmt = $pdo->prepare("SELECT password FROM users WHERE student_id = ?");
                    $stmt->execute([$_SESSION['student_id']]);
                    $user = $stmt->fetch();

                    if ($user && password_verify($current_password, $user['password'])) {
                        // Update password
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("
                            UPDATE users 
                            SET password = ?, updated_at = NOW() 
                            WHERE student_id = ?
                        ");
                        $stmt->execute([$hashed_password, $_SESSION['student_id']]);

                        // Clear remember tokens for security
                        $stmt = $pdo->prepare("
                            UPDATE users 
                            SET remember_token = NULL, remember_token_expires = NULL 
                            WHERE student_id = ?
                        ");
                        $stmt->execute([$_SESSION['student_id']]);

                        $message = 'Your password has been changed successfully. All remember me sessions have been cleared.';
                        $message_type = 'success';
                    } else {
                        $message = 'Current password is incorrect.';
                        $message_type = 'error';
                    }
                } else {
                    $message = implode('<br>', $errors);
                    $message_type = 'error';
                }

            } elseif ($action === 'clear_sessions') {
                // Clear all remember me sessions
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET remember_token = NULL, remember_token_expires = NULL 
                    WHERE student_id = ?
                ");
                $stmt->execute([$_SESSION['student_id']]);

                $message = 'All remember me sessions have been cleared successfully.';
                $message_type = 'success';

            } elseif ($action === 'reset_login_attempts') {
                // Reset login attempts (admin feature for self-service)
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET login_attempts = 0, locked_until = NULL 
                    WHERE student_id = ?
                ");
                $stmt->execute([$_SESSION['student_id']]);

                $message = 'Login attempts have been reset successfully.';
                $message_type = 'success';
            }

        } catch (PDOException $e) {
            $message = 'Database error occurred. Please try again.';
            $message_type = 'error';
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                $message .= '<br>Debug: ' . $e->getMessage();
            }
        }
    }
}

// Fetch current user security data
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

    $stmt = $pdo->prepare("
        SELECT first_name, last_name, email, last_login, login_attempts, 
               locked_until, remember_token, created_at
        FROM users 
        WHERE student_id = ?
    ");
    $stmt->execute([$_SESSION['student_id']]);
    $user_data = $stmt->fetch();

    if (!$user_data) {
        header('Location: student-login.php');
        exit();
    }

} catch (PDOException $e) {
    $message = 'Error loading security information.';
    $message_type = 'error';
    $user_data = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Settings - EduBridge SA</title>
    <style>
        :root {
            --primary-color: #1a5fb4;
            --primary-dark: #155a9e;
            --secondary-color: #26a269;
            --accent-color: #f66151;
            --warning-color: #f57c00;
            --danger-color: #d32f2f;
            --background-color: #f8f9fa;
            --surface-color: #ffffff;
            --text-primary: #2d3748;
            --text-secondary: #718096;
            --border-color: #e2e8f0;
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--background-color);
            color: var(--text-primary);
            line-height: 1.6;
        }

        /* Navigation */
        .navbar {
            background: var(--surface-color);
            padding: 1rem 0;
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        .logo img {
            height: 28px;
            width: auto;
            margin-right: 0.5rem;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--text-primary);
            font-weight: 500;
            transition: var(--transition);
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
        }

        .nav-links a:hover,
        .nav-links a.active {
            background: var(--primary-color);
            color: white;
        }

        /* Main Content */
        .main-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .page-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }

        /* Security Cards */
        .security-card {
            background: var(--surface-color);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .card-description {
            opacity: 0.9;
            font-size: 0.95rem;
        }

        .card-body {
            padding: 2rem;
        }

        /* Message */
        .message {
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            border: 1px solid;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }

        /* Form */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: var(--transition);
            background: var(--surface-color);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(26, 95, 180, 0.1);
        }

        .password-requirements {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-top: 0.5rem;
        }

        .password-requirements ul {
            margin: 0.5rem 0 0 1rem;
        }

        /* Buttons */
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-warning {
            background: var(--warning-color);
            color: white;
        }

        .btn-warning:hover {
            background: #e65100;
        }

        .btn-danger {
            background: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background: #b71c1c;
        }

        .btn-secondary {
            background: var(--text-secondary);
            color: white;
        }

        .btn-secondary:hover {
            background: #4a5568;
        }

        /* Security Status */
        .security-status {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .status-item {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: var(--border-radius);
            text-align: center;
        }

        .status-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .status-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .status-good {
            border-left: 4px solid var(--secondary-color);
        }

        .status-warning {
            border-left: 4px solid var(--warning-color);
        }

        .status-danger {
            border-left: 4px solid var(--danger-color);
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 1.5rem;
        }

        .action-button {
            flex: 1;
            min-width: 200px;
        }

        /* Password Strength Indicator */
        .password-strength {
            margin-top: 0.5rem;
            height: 4px;
            background: #e2e8f0;
            border-radius: 2px;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            transition: var(--transition);
            width: 0%;
        }

        .strength-weak { background: var(--danger-color); }
        .strength-fair { background: var(--warning-color); }
        .strength-good { background: var(--secondary-color); }
        .strength-strong { background: var(--primary-color); }

        /* Responsive */
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }

            .main-container {
                margin: 1rem auto;
                padding: 0 0.5rem;
            }

            .page-title {
                font-size: 2rem;
            }

            .action-buttons {
                flex-direction: column;
            }

            .action-button {
                min-width: auto;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="student-dashboard.php" class="logo">
                <img src="images/logo.png.jpg" alt="EduBridge SA" onerror="this.onerror=null;this.src='images/logo.png';">
                <span>EduBridge SA</span>
            </a>
            <ul class="nav-links">
                <li><a href="student-dashboard.php">Dashboard</a></li>
                <li><a href="student-apply.php">Apply</a></li>
                <li><a href="student-account.php">Account</a></li>
                <li><a href="student-security.php" class="active">Security</a></li>
                <li><a href="student-settings.php">Settings</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-container">
        <div class="page-header">
            <h1 class="page-title">Security Settings</h1>
            <p class="page-subtitle">Manage your account security and password settings</p>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Security Status Overview -->
        <div class="security-card">
            <div class="card-header">
                <h2 class="card-title">Security Overview</h2>
                <p class="card-description">Current status of your account security</p>
            </div>
            <div class="card-body">
                <div class="security-status">
                    <div class="status-item <?php echo ($user_data['login_attempts'] ?? 0) == 0 ? 'status-good' : 'status-warning'; ?>">
                        <div class="status-value"><?php echo $user_data['login_attempts'] ?? 0; ?></div>
                        <div class="status-label">Failed Login Attempts</div>
                    </div>
                    <div class="status-item <?php echo !empty($user_data['remember_token']) ? 'status-warning' : 'status-good'; ?>">
                        <div class="status-value"><?php echo !empty($user_data['remember_token']) ? 'Active' : 'None'; ?></div>
                        <div class="status-label">Remember Me Sessions</div>
                    </div>
                    <div class="status-item status-good">
                        <div class="status-value"><?php echo $user_data['last_login'] ? date('M j', strtotime($user_data['last_login'])) : 'Never'; ?></div>
                        <div class="status-label">Last Login</div>
                    </div>
                    <div class="status-item status-good">
                        <div class="status-value"><?php echo date('M j, Y', strtotime($user_data['created_at'])); ?></div>
                        <div class="status-label">Account Created</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Change Password -->
        <div class="security-card">
            <div class="card-header">
                <h2 class="card-title">Change Password</h2>
                <p class="card-description">Update your account password for better security</p>
            </div>
            <div class="card-body">
                <form method="POST" action="student-security.php" id="passwordForm">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo $_SESSION[CSRF_TOKEN_NAME]; ?>">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-group">
                        <label for="current_password" class="form-label">Current Password *</label>
                        <input type="password" id="current_password" name="current_password" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label for="new_password" class="form-label">New Password *</label>
                        <input type="password" id="new_password" name="new_password" class="form-input" required>
                        <div class="password-strength">
                            <div class="password-strength-bar" id="strengthBar"></div>
                        </div>
                        <div class="password-requirements">
                            Password must contain:
                            <ul>
                                <li>At least 8 characters</li>
                                <li>One uppercase letter</li>
                                <li>One lowercase letter</li>
                                <li>One number</li>
                                <li>One special character (@$!%*?&)</li>
                            </ul>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirm New Password *</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-input" required>
                    </div>

                    <button type="submit" class="btn btn-primary">Change Password</button>
                </form>
            </div>
        </div>

        <!-- Security Actions -->
        <div class="security-card">
            <div class="card-header">
                <h2 class="card-title">Security Actions</h2>
                <p class="card-description">Additional security management options</p>
            </div>
            <div class="card-body">
                <div class="action-buttons">
                    <form method="POST" action="student-security.php" style="flex: 1;">
                        <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo $_SESSION[CSRF_TOKEN_NAME]; ?>">
                        <input type="hidden" name="action" value="clear_sessions">
                        <button type="submit" class="btn btn-warning action-button" 
                                onclick="return confirm('This will log you out of all devices where you selected Remember Me. Continue?')">
                            Clear All Sessions
                        </button>
                    </form>

                    <?php if (($user_data['login_attempts'] ?? 0) > 0): ?>
                    <form method="POST" action="student-security.php" style="flex: 1;">
                        <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo $_SESSION[CSRF_TOKEN_NAME]; ?>">
                        <input type="hidden" name="action" value="reset_login_attempts">
                        <button type="submit" class="btn btn-secondary action-button">
                            Reset Login Attempts
                        </button>
                    </form>
                    <?php endif; ?>
                </div>

                <div style="margin-top: 2rem; padding: 1rem; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: var(--border-radius);">
                    <h4 style="color: #856404; margin-bottom: 0.5rem;">Security Tips</h4>
                    <ul style="color: #856404; margin-left: 1rem;">
                        <li>Use a unique password that you don't use elsewhere</li>
                        <li>Enable "Remember Me" only on trusted devices</li>
                        <li>Log out when using shared computers</li>
                        <li>Change your password regularly</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Password strength checker
        document.getElementById('new_password').addEventListener('input', function(e) {
            const password = e.target.value;
            const strengthBar = document.getElementById('strengthBar');
            
            let strength = 0;
            let width = 0;
            let className = '';
            
            // Check length
            if (password.length >= 8) strength++;
            
            // Check for lowercase
            if (/[a-z]/.test(password)) strength++;
            
            // Check for uppercase
            if (/[A-Z]/.test(password)) strength++;
            
            // Check for numbers
            if (/\d/.test(password)) strength++;
            
            // Check for special characters
            if (/[@$!%*?&]/.test(password)) strength++;
            
            switch(strength) {
                case 0:
                case 1:
                    width = 20;
                    className = 'strength-weak';
                    break;
                case 2:
                    width = 40;
                    className = 'strength-weak';
                    break;
                case 3:
                    width = 60;
                    className = 'strength-fair';
                    break;
                case 4:
                    width = 80;
                    className = 'strength-good';
                    break;
                case 5:
                    width = 100;
                    className = 'strength-strong';
                    break;
            }
            
            strengthBar.style.width = width + '%';
            strengthBar.className = 'password-strength-bar ' + className;
        });

        // Form validation
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New password and confirmation do not match.');
                return false;
            }
            
            if (newPassword.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long.');
                return false;
            }
            
            if (!/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/.test(newPassword)) {
                e.preventDefault();
                alert('Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.');
                return false;
            }
            
            // Show loading state
            const submitBtn = e.target.querySelector('button[type="submit"]');
            submitBtn.textContent = 'Changing Password...';
            submitBtn.disabled = true;
        });

        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = e.target.value;
            
            if (confirmPassword && newPassword !== confirmPassword) {
                e.target.style.borderColor = 'var(--danger-color)';
            } else {
                e.target.style.borderColor = 'var(--border-color)';
            }
        });
    </script>
</body>
</html>