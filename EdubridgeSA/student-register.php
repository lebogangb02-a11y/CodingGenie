<?php
require_once 'session_config.php';
require_once 'config.php';
require_once 'auth.php';  // For getPDO() and isLoggedIn()

// Check if user is already logged in
if (isLoggedIn()) {
    header('Location: student-dashboard.php');
    exit();
}

// CSRF Token (generate if not set)
if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}

// Initialize variables
$errors = [];
$success = '';
$formData = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => '',
    'school_university' => ''
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    // Server-side CSRF enforcement (best-effort)
    if (function_exists('require_csrf')) { require_csrf(); }

    // CSRF Check
    if (!isset($_POST[CSRF_TOKEN_NAME]) || $_POST[CSRF_TOKEN_NAME] !== $_SESSION[CSRF_TOKEN_NAME]) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        // Get and sanitize form data
        $formData['first_name'] = trim($_POST['first_name'] ?? '');
        $formData['last_name'] = trim($_POST['last_name'] ?? '');
        $formData['email'] = trim($_POST['email'] ?? '');
        $formData['phone'] = trim($_POST['phone'] ?? '');
        $formData['school_university'] = trim($_POST['school_university'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validation
        if (empty($formData['first_name'])) {
            $errors[] = 'First name is required.';
        }
        
        if (empty($formData['last_name'])) {
            $errors[] = 'Last name is required.';
        }
        
        if (empty($formData['email']) || !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email address is required.';
        }
        
        if (empty($formData['phone'])) {
            $errors[] = 'Phone number is required.';
        } elseif (!preg_match('/^[0-9+\-\s()]{10,15}$/', $formData['phone'])) {
            $errors[] = 'Please enter a valid phone number.';
        }
        
        if (empty($formData['school_university'])) {
            $errors[] = 'School/University is required.';
        }
        
        if (empty($password)) {
            $errors[] = 'Password is required.';
        } elseif (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter, one lowercase letter, and one number.';
        }
        
        if ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match.';
        }
        
        // If no validation errors, proceed with registration
        if (empty($errors)) {
            try {
                $pdo = getPDO();  // Use centralized DB connection from auth.php
                
                // Check if email already exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$formData['email']]);
                
                if ($stmt->fetch()) {
                    $errors[] = 'An account with this email already exists. Please use a different email or <a href="student-login.php" style="color: #1a5fb4;">login here</a>.';
                } else {
                    // Hash the password
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Generate unique student ID (retry if duplicate)
                    $studentId = 'EBS' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE student_id = ?");
                    $stmt->execute([$studentId]);
                    if ($stmt->fetch()) {
                        $studentId = 'EBS' . date('Y') . str_pad(rand(10000, 99999), 5, '0', STR_PAD_LEFT);  // Longer if collision
                    }
                    
                    // Generate email verification token
                    $verificationToken = bin2hex(random_bytes(32));
                    
                    // Insert new user with verification metadata
                    $stmt = $pdo->prepare("
                        INSERT INTO users (
                            email, password_hash, first_name, last_name, phone,
                            school_university, student_id, status, email_verified,
                            verification_token, verification_sent_at, verification_attempts, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 0, ?, NOW(), 1, NOW())
                    ");
                    
                    $stmt->execute([
                        $formData['email'],
                        $passwordHash,
                        $formData['first_name'],
                        $formData['last_name'],
                        $formData['phone'],
                        $formData['school_university'],
                        $studentId,
                        $verificationToken
                    ]);
                    
                    // Send verification email (use centralized file)
                    require_once 'email_functions.php';
                    $fullName = trim($formData['first_name'] . ' ' . $formData['last_name']);
                    $emailSent = sendVerificationEmail($formData['email'], $fullName, $verificationToken);
                    
                    if ($emailSent) {
                        error_log("Registration: Verification email sent successfully to " . $formData['email']);
                        $_SESSION['flash_message'] = '<div class="alert alert-success">Registration successful! Check your email for verification.</div>';
                    } else {
                        error_log("Registration: Verification email failed for " . $formData['email'] . " - Falling back to manual resend.");
                        $_SESSION['flash_message'] = '<div class="alert alert-warning">Account created, but verification email failed. You can resend from the next page.</div>';
                    }
                    
                    // Always redirect to pending page for resend/verification status
                    $_SESSION['pending_email'] = $formData['email'];
                    header('Location: email-verification-pending.php');
                    exit();
                }
                
            } catch (Exception $e) {  // Broader catch for PDO/ general errors
                $errors[] = 'Database error: Unable to create account. Please try again.';
                if (defined('DEBUG_MODE') && DEBUG_MODE) {
                    error_log("Registration DB Error: " . $e->getMessage());
                    $errors[] = 'Debug: ' . $e->getMessage();
                }
            }
        }
        
        // Regenerate CSRF token after submission
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - EduBridge SA</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            /* EduBridgeSA Brand Colors */
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
            
            /* Typography */
            --font-family: 'Poppins', sans-serif;
            --font-weight-light: 300;
            --font-weight-normal: 400;
            --font-weight-medium: 500;
            --font-weight-semibold: 600;
            --font-weight-bold: 700;
            
            /* Shadows */
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --shadow-2xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            
            /* Border Radius */
            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            --radius-2xl: 1.5rem;
            
            /* Transitions */
            --transition-fast: 0.15s ease-in-out;
            --transition-normal: 0.3s ease-in-out;
            --transition-slow: 0.5s ease-in-out;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-family);
            font-weight: var(--font-weight-normal);
            background: linear-gradient(135deg, var(--royal-blue) 0%, var(--emerald-green) 50%, var(--royal-blue-light) 100%);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        /* Animated Background */
        .animated-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background: linear-gradient(135deg, var(--royal-blue) 0%, var(--emerald-green) 50%, var(--royal-blue-light) 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Main Container */
        .container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            position: relative;
            z-index: 1;
        }

        .register-card {
            background: var(--white);
            border-radius: var(--radius-2xl);
            box-shadow: var(--shadow-2xl);
            overflow: hidden;
            width: 100%;
            max-width: 500px;
            position: relative;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: slideInUp 0.8s ease-out;
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Header Section */
        .header {
            background: linear-gradient(135deg, var(--royal-blue) 0%, var(--royal-blue-light) 100%);
            color: var(--white);
            padding: 2.5rem 2rem;
            text-align: center;
            position: relative;
        }

        .logo i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--gold);
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .logo h1 {
            font-size: 1.75rem;
            font-weight: var(--font-weight-bold);
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .tagline {
            font-size: 0.95rem;
            font-weight: var(--font-weight-normal);
            opacity: 0.9;
            font-style: italic;
        }

        /* Registration Form */
        .register-form {
            padding: 2rem;
        }

        .form-title {
            text-align: center;
            margin-bottom: 2rem;
        }

        .form-title h2 {
            font-size: 1.5rem;
            font-weight: var(--font-weight-semibold);
            color: var(--gray-800);
            margin-bottom: 0.5rem;
        }

        .form-title p {
            font-size: 0.875rem;
            color: var(--gray-600);
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: var(--font-weight-medium);
            color: var(--gray-700);
            margin-bottom: 0.5rem;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            font-size: 1rem;
            z-index: 1;
        }

        .form-input {
            width: 100%;
            padding: 0.875rem 1rem 0.875rem 3rem;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-lg);
            font-size: 1rem;
            font-family: var(--font-family);
            background: var(--white);
            transition: var(--transition-normal);
            outline: none;
        }

        .form-input:focus {
            border-color: var(--royal-blue);
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
        }

        .input-wrapper i {
            color: var(--royal-blue);
        }

        .form-input::placeholder {
            color: var(--gray-400);
        }

        /* Two column layout for name fields */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        /* Buttons */
        .button-group {
            display: flex