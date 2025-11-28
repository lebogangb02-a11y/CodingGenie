<?php
session_start();
require_once 'config.php';
require_once 'security-utils.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Get and validate input data
$full_name = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$date_of_birth = trim($_POST['date_of_birth'] ?? '');
$address = trim($_POST['address'] ?? '');
$school_university = trim($_POST['school_university'] ?? '');

// Validation rules
$errors = [];

// Validate full name
if (empty($full_name)) {
    $errors[] = 'Full name is required';
} elseif (strlen($full_name) < 2 || strlen($full_name) > 100) {
    $errors[] = 'Full name must be between 2 and 100 characters';
} elseif (!preg_match('/^[a-zA-Z\s\'-]+$/', $full_name)) {
    $errors[] = 'Full name contains invalid characters';
}

// Validate email
if (empty($email)) {
    $errors[] = 'Email is required';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email format';
} elseif (strlen($email) > 100) {
    $errors[] = 'Email is too long';
}

// Validate phone (optional)
if (!empty($phone)) {
    // Remove spaces and special characters for validation
    $phone_clean = preg_replace('/[^0-9+]/', '', $phone);
    if (strlen($phone_clean) < 10 || strlen($phone_clean) > 15) {
        $errors[] = 'Phone number must be between 10 and 15 digits';
    }
    if (!preg_match('/^[0-9+\s\-()]+$/', $phone)) {
        $errors[] = 'Phone number contains invalid characters';
    }
}

// Validate date of birth (optional)
if (!empty($date_of_birth)) {
    $date = DateTime::createFromFormat('Y-m-d', $date_of_birth);
    if (!$date || $date->format('Y-m-d') !== $date_of_birth) {
        $errors[] = 'Invalid date of birth format';
    } else {
        $today = new DateTime();
        $age = $today->diff($date)->y;
        if ($age < 16 || $age > 100) {
            $errors[] = 'Age must be between 16 and 100 years';
        }
    }
}

// Validate address (optional)
if (!empty($address) && strlen($address) > 500) {
    $errors[] = 'Address is too long (maximum 500 characters)';
}

// Validate school/university (optional)
if (!empty($school_university) && strlen($school_university) > 200) {
    $errors[] = 'School/University name is too long (maximum 200 characters)';
}

// Check if email is already taken by another user
if (empty($errors)) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            $errors[] = 'Email is already registered to another account';
        }
    } catch (Exception $e) {
        error_log("Email check error: " . $e->getMessage());
        $errors[] = 'Database error occurred';
    }
}

// Return validation errors
if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
    exit();
}

try {
    // Start database transaction
    $pdo->beginTransaction();
    
    // Update user profile
    $stmt = $pdo->prepare("
        UPDATE users 
        SET full_name = ?, 
            email = ?, 
            phone = ?, 
            date_of_birth = ?, 
            address = ?, 
            school_university = ?,
            profile_updated_at = NOW()
        WHERE id = ?
    ");
    
    $stmt->execute([
        $full_name,
        $email,
        !empty($phone) ? $phone : null,
        !empty($date_of_birth) ? $date_of_birth : null,
        !empty($address) ? $address : null,
        !empty($school_university) ? $school_university : null,
        $user_id
    ]);
    
    // Update or create student profile record
    $stmt = $pdo->prepare("
        INSERT INTO student_profiles (user_id, updated_at) 
        VALUES (?, NOW()) 
        ON DUPLICATE KEY UPDATE updated_at = NOW()
    ");
    $stmt->execute([$user_id]);
    
    // Commit transaction
    $pdo->commit();
    
    // Update session data
    $_SESSION['full_name'] = $full_name;
    $_SESSION['email'] = $email;
    
    echo json_encode([
        'success' => true, 
        'message' => 'Profile updated successfully',
        'data' => [
            'full_name' => $full_name,
            'email' => $email,
            'phone' => $phone,
            'date_of_birth' => $date_of_birth,
            'address' => $address,
            'school_university' => $school_university
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback transaction
    $pdo->rollBack();
    
    error_log("Profile update error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
}
?>