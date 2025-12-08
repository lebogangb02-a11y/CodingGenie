<?php
require_once 'config.php';
require_once 'session_config.php';

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
$token = $_GET['token'] ?? '';
$valid_token = false;
$user_data = null;

// Validate token
if (!empty($token)) {
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

        // Check if token is valid and not expired
        $stmt = $pdo->prepare("
            SELECT prt.student_id, u.first_name, u.last_name, u.email
            FROM password_reset_tokens prt
            JOIN users u ON prt.student_id = u.student_id
            WHERE prt.token = ? AND prt.expires_at > NOW()
        ");
        $stmt->execute([$token]);
        $user_data = $stmt->fetch();

        if ($user_data) {
            $valid_token = true;
        } else {
            $message = 'Invalid or expired reset token. Please request a new password reset.';
            $message_type = 'error';
        }
    } catch (PDOException $e) {
        $message = 'Database error occurred. Please try again later.';
        $message_type = 'error';
    }
} else {
    $message = 'No reset token provided.';
    $message_type = 'error';
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    // Server-side CSRF enforcement (best-effort)
    if (function_exists('require_csrf')) {
        require_csrf();
    }

    // CSRF protection
    if (!isset($_POST[CSRF_TOKEN_NAME]) || $_POST[CSRF_TOKEN_NAME] !== $_SESSION[CSRF_TOKEN_NAME]) {
        $message = 'Security token mismatch. Please try again.';
        $message_type = 'error';
    } else {
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        $errors = [];

        if (empty($new_password)) {
            $errors[] = 'New password is required.';
        }
        if (strlen($new_password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $new_password)) {
            $errors[] = 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.';
        }
        if ($new_password !== $confirm_password) {
            $errors[] = 'Password and confirmation do not match.';
        }

        if (empty($errors)) {
            try {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET password = ?, updated_at = NOW() 
                    WHERE student_id = ?
                ");
                $stmt->execute([$hashed_password, $user_data['student_id']]);

                // Delete the used reset token
                $stmt = $pdo->prepare("DELETE FROM password_reset_tokens WHERE token = ?");
                $stmt->execute([$token]);

                // Clear all remember tokens for security
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET remember_token = NULL, remember_token_expires = NULL 
                    WHERE student_id = ?
                ");
                $stmt->execute([$user_data['student_id']]);

                // Reset login attempts
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET login_attempts = 0, locked_until = NULL 
                    WHERE student_id = ?
                ");
                $stmt->execute([$user_data['student_id']]);

                $message = 'Your password has been reset successfully. You can now log in with your new password.';
                $message_type = 'success';
                $valid_token = false; // Hide the form

            } catch (PDOException $e) {
                $message = 'Database error occurred. Please try again later.';
                $message_type = 'error';
            }
        } else {
            $message = implode('<br>', $errors);
            $message_type = 'error';
        }

        // Regenerate CSRF token
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - EduBridge SA</title>
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

        .reset-container {
            background: var(--surface-color);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            padding: 2rem;
            width: 100%;
            max-width: 450px;
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

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
            font-weight: 500;
        }

        input[type="password"] {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: var(--transition);
        }

        input[type="password"]:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(26, 95, 180, 0.1);
        }

        .password-strength {
            margin-top: 0.5rem;
            font-size: 0.875rem;
        }

        .strength-bar {
            height: 4px;
            background: var(--border-color);
            border-radius: 2px;
            margin: 0.5rem 0;
            overflow: hidden;
        }

        .strength-fill {
            height: 100%;
            transition: var(--transition);
            width: 0%;
        }

        .strength-weak .strength-fill {
            background: var(--error-color);
            width: 25%;
        }

        .strength-fair .strength-fill {
            background: var(--warning-color);
            width: 50%;
        }

        .strength-good .strength-fill {
            background: var(--secondary-color);
            width: 75%;
        }

        .strength-strong .strength-fill {
            background: var(--success-color);
            width: 100%;
        }

        .password-requirements {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 0.5rem;
        }

        .requirement {
            display: flex;
            align-items: center;
            margin-bottom: 0.25rem;
        }

        .requirement i {
            margin-right: 0.5rem;
            width: 12px;
        }

        .requirement.met {
            color: var(--success-color);
        }

        .requirement.unmet {
            color: var(--error-color);
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
            .reset-container {
                padding: 1.5rem;
            }
        }
    </style>
</head>

<body>
    <div class="reset-container">
        <div class="logo">
            <img src="images/logo.png.jpg" alt="EduBridge SA" onerror="this.onerror=null;this.src='images/logo.png';">
            <h1>EduBridge SA</h1>
        </div>

        <h2 class="form-title">Reset Password</h2>
        <?php if ($valid_token): ?>
            <p class="form-subtitle">Hello <?php echo htmlspecialchars($user_data['first_name']); ?>, enter your new password below.</p>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="alert <?php echo htmlspecialchars($message_type); ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($valid_token): ?>
            <form method="POST" action="reset-password.php" id="resetForm">
                <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo $_SESSION[CSRF_TOKEN_NAME]; ?>">

                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required
                        placeholder="Enter your new password">
                    <div class="password-strength">
                        <div class="strength-bar">
                            <div class="strength-fill"></div>
                        </div>
                        <div class="strength-text">Password strength: <span id="strengthText">Weak</span></div>
                    </div>
                    <div class="password-requirements">
                        <div class="requirement unmet" id="req-length">
                            <i class="fas fa-times"></i>
                            At least 8 characters
                        </div>
                        <div class="requirement unmet" id="req-uppercase">
                            <i class="fas fa-times"></i>
                            One uppercase letter
                        </div>
                        <div class="requirement unmet" id="req-lowercase">
                            <i class="fas fa-times"></i>
                            One lowercase letter
                        </div>
                        <div class="requirement unmet" id="req-number">
                            <i class="fas fa-times"></i>
                            One number
                        </div>
                        <div class="requirement unmet" id="req-special">
                            <i class="fas fa-times"></i>
                            One special character
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required
                        placeholder="Confirm your new password">
                    <div id="password-match" style="font-size: 0.875rem; margin-top: 0.5rem;"></div>
                </div>

                <button type="submit" class="submit-btn" id="submitBtn" disabled>
                    <i class="fas fa-key"></i> Reset Password
                </button>
            </form>
        <?php endif; ?>

        <div class="back-link">
            <a href="student-login.php">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>
        </div>
    </div>

    <script>
        const newPasswordInput = document.getElementById('new_password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const strengthBar = document.querySelector('.strength-bar');
        const strengthText = document.getElementById('strengthText');
        const submitBtn = document.getElementById('submitBtn');
        const passwordMatch = document.getElementById('password-match');

        const requirements = {
            length: document.getElementById('req-length'),
            uppercase: document.getElementById('req-uppercase'),
            lowercase: document.getElementById('req-lowercase'),
            number: document.getElementById('req-number'),
            special: document.getElementById('req-special')
        };

        function checkRequirement(element, condition) {
            if (condition) {
                element.classList.remove('unmet');
                element.classList.add('met');
                element.querySelector('i').className = 'fas fa-check';
            } else {
                element.classList.remove('met');
                element.classList.add('unmet');
                element.querySelector('i').className = 'fas fa-times';
            }
        }

        function checkPasswordStrength() {
            const password = newPasswordInput.value;
            let score = 0;

            // Check requirements
            const hasLength = password.length >= 8;
            const hasUppercase = /[A-Z]/.test(password);
            const hasLowercase = /[a-z]/.test(password);
            const hasNumber = /\d/.test(password);
            const hasSpecial = /[@$!%*?&]/.test(password);

            checkRequirement(requirements.length, hasLength);
            checkRequirement(requirements.uppercase, hasUppercase);
            checkRequirement(requirements.lowercase, hasLowercase);
            checkRequirement(requirements.number, hasNumber);
            checkRequirement(requirements.special, hasSpecial);

            // Calculate score
            if (hasLength) score++;
            if (hasUppercase) score++;
            if (hasLowercase) score++;
            if (hasNumber) score++;
            if (hasSpecial) score++;

            // Update strength display
            strengthBar.className = 'strength-bar';
            if (score === 0) {
                strengthBar.classList.add('strength-weak');
                strengthText.textContent = 'Weak';
            } else if (score <= 2) {
                strengthBar.classList.add('strength-weak');
                strengthText.textContent = 'Weak';
            } else if (score <= 3) {
                strengthBar.classList.add('strength-fair');
                strengthText.textContent = 'Fair';
            } else if (score <= 4) {
                strengthBar.classList.add('strength-good');
                strengthText.textContent = 'Good';
            } else {
                strengthBar.classList.add('strength-strong');
                strengthText.textContent = 'Strong';
            }

            return score === 5;
        }

        function checkPasswordMatch() {
            const password = newPasswordInput.value;
            const confirmPassword = confirmPasswordInput.value;

            if (confirmPassword === '') {
                passwordMatch.textContent = '';
                return false;
            }

            if (password === confirmPassword) {
                passwordMatch.textContent = '✓ Passwords match';
                passwordMatch.style.color = 'var(--success-color)';
                return true;
            } else {
                passwordMatch.textContent = '✗ Passwords do not match';
                passwordMatch.style.color = 'var(--error-color)';
                return false;
            }
        }

        function updateSubmitButton() {
            const isPasswordStrong = checkPasswordStrength();
            const doPasswordsMatch = checkPasswordMatch();

            submitBtn.disabled = !(isPasswordStrong && doPasswordsMatch && newPasswordInput.value.length > 0);
        }

        newPasswordInput.addEventListener('input', updateSubmitButton);
        confirmPasswordInput.addEventListener('input', updateSubmitButton);
    </script>
</body>

</html>