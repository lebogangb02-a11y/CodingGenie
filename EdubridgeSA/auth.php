<?php
/**
 * Enhanced Authentication System for EduBridge SA
 * Comprehensive login/logout functions with security and multi-device support
 * Compatible with PHP 7.4+ and modern browsers
 * Relies on session_config.php for session management
 * Aligned to DB schema: users (login_attempts, locked_until, last_login), login_attempts table
 */

require_once 'session_config.php';
require_once 'config.php';

/**
 * Get PDO connection (lazy-loaded, shared) - SINGLE DEFINITION
 */
function getPDO() {
    global $pdo;
    if (!isset($pdo)) {
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
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                die("Database connection failed. Check credentials.");
            }
            return null;
        }
    }
    return $pdo;
}

/**
 * Enhanced login function with comprehensive security - SINGLE DEFINITION
 */
function authenticateUser($email, $password, $rememberMe = false) {
    $pdo = getPDO();
    if (!$pdo) {
        return [
            'success' => false,
            'message' => 'Database unavailable. Please try again later.',
            'error_code' => 'DB_ERROR'
        ];
    }
    
    try {
        if (empty($email) || empty($password)) {
            return [
                'success' => false,
                'message' => 'Email and password are required.',
                'error_code' => 'MISSING_CREDENTIALS'
            ];
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'message' => 'Please enter a valid email address.',
                'error_code' => 'INVALID_EMAIL'
            ];
        }
        
        $rateLimitResult = checkRateLimit($email);
        if (!$rateLimitResult['allowed']) {
            return [
                'success' => false,
                'message' => $rateLimitResult['message'],
                'error_code' => 'RATE_LIMITED',
                'retry_after' => $rateLimitResult['retry_after']
            ];
        }
        
        $stmt = $pdo->prepare("
            SELECT id, student_id, first_name, last_name, email, password_hash, 
                   status, login_attempts, locked_until, 
                   email_verified, created_at, last_login
            FROM users 
            WHERE email = ? 
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            recordFailedLogin($email, 'USER_NOT_FOUND');
            return [
                'success' => false,
                'message' => 'Invalid email or password.',
                'error_code' => 'INVALID_CREDENTIALS'
            ];
        }
        
        $statusCheck = checkAccountStatus($user);
        if (!$statusCheck['allowed']) {
            recordFailedLogin($email, $statusCheck['error_code']);
            return $statusCheck;
        }
        
        if (!password_verify($password, $user['password_hash'])) {
            recordFailedLogin($email, 'INVALID_PASSWORD');
            incrementFailedAttempts($user['id']);
            return [
                'success' => false,
                'message' => 'Invalid email or password.',
                'error_code' => 'INVALID_CREDENTIALS'
            ];
        }
        
        resetFailedAttempts($user['id']);
        recordSuccessfulLogin($user['id']);
        setLoginSession($user, $rememberMe);
        updateLastLogin($user['id']);
        
        return [
            'success' => true,
            'message' => 'Login successful.',
            'user' => [
                'id' => $user['id'],
                'student_id' => $user['student_id'],
                'name' => trim($user['first_name'] . ' ' . $user['last_name']),
                'email' => $user['email'],
                'status' => $user['status']
            ],
            'redirect_url' => determineRedirectUrl($user)
        ];
        
    } catch (PDOException $e) {
        error_log("Database error in authenticate:User  " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'A system error occurred. Please try again later.',
            'error_code' => 'SYSTEM_ERROR'
        ];
    } catch (Exception $e) {
        error_log("General error in authenticate:User  " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'An unexpected error occurred. Please try again.',
            'error_code' => 'UNEXPECTED_ERROR'
        ];
    }
}

/**
 * Admin authentication with custom hashing
 */
function authenticateAdmin($username, $password, $rememberMe = false) {
    $pdo = getPDO();
    if (!$pdo) {
        return [
            'success' => false,
            'message' => 'Database unavailable. Please try again later.',
            'error_code' => 'DB_ERROR'
        ];
    }
    
    try {
        if (empty($username) || empty($password)) {
            return [
                'success' => false,
                'message' => 'Username and password are required.',
                'error_code' => 'MISSING_CREDENTIALS'
            ];
        }
        
        $rateLimitResult = checkRateLimit($username);
        if (!$rateLimitResult['allowed']) {
            return [
                'success' => false,
                'message' => $rateLimitResult['message'],
                'error_code' => 'RATE_LIMITED',
                'retry_after' => $rateLimitResult['retry_after']
            ];
        }
        
        // Look for admin in admins table
        $stmt = $pdo->prepare("
            SELECT id, name, username, email, role, password_hash 
            FROM admins 
            WHERE (username = ? OR email = ?) AND role IN ('super', 'admin')
        ");
        $stmt->execute([$username, $username]);
        $admin = $stmt->fetch();
        
        if (!$admin) {
            recordFailedLogin($username, 'ADMIN_NOT_FOUND');
            return [
                'success' => false,
                'message' => 'Invalid admin credentials.',
                'error_code' => 'INVALID_CREDENTIALS'
            ];
        }
        
        // Use custom hashing for admin (not password_verify)
        $hashed_input = hash('sha256', $password . AUTH_SALT);
        if ($hashed_input === $admin['password_hash']) {
            // Successful admin login
            setAdminLoginSession($admin, $rememberMe);
            updateAdminLastLogin($admin['id']);
            recordSuccessfulLogin($admin['id']);
            
            return [
                'success' => true,
                'message' => 'Admin login successful.',
                'user' => [
                    'id' => $admin['id'],
                    'name' => $admin['name'],
                    'username' => $admin['username'],
                    'email' => $admin['email'],
                    'role' => $admin['role']
                ],
                'redirect_url' => 'admin_dashboard.php'
            ];
        } else {
            recordFailedLogin($username, 'INVALID_ADMIN_PASSWORD');
            return [
                'success' => false,
                'message' => 'Invalid admin credentials.',
                'error_code' => 'INVALID_CREDENTIALS'
            ];
        }
        
    } catch (PDOException $e) {
        error_log("Database error in authenticateAdmin: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'A system error occurred. Please try again later.',
            'error_code' => 'SYSTEM_ERROR'
        ];
    } catch (Exception $e) {
        error_log("General error in authenticateAdmin: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'An unexpected error occurred. Please try again.',
            'error_code' => 'UNEXPECTED_ERROR'
        ];
    }
}

/**
 * Universal authentication function that tries both student and admin
 */
function universalAuthenticate($identifier, $password, $rememberMe = false) {
    // First try admin authentication
    $adminResult = authenticateAdmin($identifier, $password, $rememberMe);
    if ($adminResult['success']) {
        return $adminResult;
    }
    
    // If admin auth fails and identifier looks like an email, try student auth
    if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
        $studentResult = authenticateUser($identifier, $password, $rememberMe);
        if ($studentResult['success']) {
            return $studentResult;
        }
    }
    
    // Both failed, return the most specific error
    return [
        'success' => false,
        'message' => 'Invalid credentials. Please check your username/email and password.',
        'error_code' => 'INVALID_CREDENTIALS'
    ];
}

/**
 * Simple login wrapper (for backward compatibility) - SINGLE DEFINITION
 */
function loginUser($email, $password) {
    $result = authenticateUser($email, $password);
    if ($result['success']) {
        return ['success' => true];
    }
    return $result;
}

/**
 * Check account status and restrictions - SINGLE DEFINITION
 */
function checkAccountStatus($user) {
    $pdo = getPDO();
    if (!$pdo) {
        return ['allowed' => false, 'success' => false, 'message' => 'System unavailable.', 'error_code' => 'DB_ERROR'];
    }
    
    if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
        $lockTime = strtotime($user['locked_until']);
        $remainingTime = $lockTime - time();
        $minutes = ceil($remainingTime / 60);
        return [
            'allowed' => false,
            'success' => false,
            'message' => "Account is temporarily locked. Please try again in {$minutes} minutes.",
            'error_code' => 'ACCOUNT_LOCKED',
            'retry_after' => $remainingTime
        ];
    }
    
    if (!$user['email_verified']) {
        return [
            'allowed' => false,
            'success' => false,
            'message' => 'Please verify your email address before logging in.',
            'error_code' => 'EMAIL_NOT_VERIFIED',
            'redirect_url' => 'email-verification-pending.php'
        ];
    }
    
    switch ($user['status']) {
        case 'pending':
            return [
                'allowed' => false,
                'success' => false,
                'message' => 'Your account is pending approval. Please contact support.',
                'error_code' => 'ACCOUNT_PENDING'
            ];
        case 'inactive':
            return [
                'allowed' => false,
                'success' => false,
                'message' => 'Your account is inactive. Please contact support to reactivate.',
                'error_code' => 'ACCOUNT_INACTIVE'
            ];
        case 'active':
            return ['allowed' => true];
        default:
            return [
                'allowed' => false,
                'success' => false,
                'message' => 'Account status is invalid. Please contact support.',
                'error_code' => 'INVALID_STATUS'
            ];
    }
}

/**
 * Rate limiting to prevent brute force attacks - SINGLE DEFINITION
 */
function checkRateLimit($email) {
    $pdo = getPDO();
    if (!$pdo) {
        return ['allowed' => true];
    }
    
    try {
        $ip = getClientIP();
        $timeWindow = 900; // 15 minutes
        $maxAttempts = 5;
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as attempts 
            FROM login_attempts 
            WHERE ip_address = ? 
            AND attempt_time > DATE_SUB(NOW(), INTERVAL ? SECOND)
            AND success = 0
        ");
        $stmt->execute([$ip, $timeWindow]);
        $ipAttempts = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as attempts 
            FROM login_attempts 
            WHERE email = ? 
            AND attempt_time > DATE_SUB(NOW(), INTERVAL ? SECOND)
            AND success = 0
        ");
        $stmt->execute([$email, $timeWindow]);
        $emailAttempts = $stmt->fetchColumn();
        
        if ($ipAttempts >= $maxAttempts || $emailAttempts >= $maxAttempts) {
            return [
                'allowed' => false,
                'message' => 'Too many failed login attempts. Please try again in 15 minutes.',
                'retry_after' => $timeWindow
            ];
        }
        
        return ['allowed' => true];
        
    } catch (PDOException $e) {
        error_log("Database error in checkRateLimit: " . $e->getMessage());
        return ['allowed' => true];
    }
}

/**
 * Record failed login attempt - SINGLE DEFINITION
 */
function recordFailedLogin($email, $reason) {
    $pdo = getPDO();
    if (!$pdo) return;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO login_attempts (email, ip_address, user_agent, success, failure_reason, attempt_time)
            VALUES (?, ?, ?, 0, ?, NOW())
        ");
        $stmt->execute([
            $email,
            getClientIP(),
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $reason
        ]);
    } catch (PDOException $e) {
        error_log("Error recording failed login: " . $e->getMessage());
    }
}

/**
 * Record successful login - SINGLE DEFINITION
 */
function recordSuccessfulLogin($userId) {
    $pdo = getPDO();
    if (!$pdo) return;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO login_attempts (user_id, email, ip_address, user_agent, success, attempt_time)
            SELECT ?, email, ?, ?, 1, NOW()
            FROM users WHERE id = ?
        ");
        $stmt->execute([
            $userId,
            getClientIP(),
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $userId
        ]);
    } catch (PDOException $e) {
        error_log("Error recording successful login: " . $e->getMessage());
    }
}

/**
 * Increment failed login attempts for user - SINGLE DEFINITION
 */
function incrementFailedAttempts($userId) {
    $pdo = getPDO();
    if (!$pdo) return;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET login_attempts = login_attempts + 1,
                locked_until = CASE 
                    WHEN login_attempts + 1 >= 5 THEN DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                    ELSE locked_until
                END
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
    } catch (PDOException $e) {
        error_log("Error incrementing failed attempts: " . $e->getMessage());
    }
}

/**
 * Reset failed login attempts - SINGLE DEFINITION
 */
function resetFailedAttempts($userId) {
    $pdo = getPDO();
    if (!$pdo) return;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET login_attempts = 0, locked_until = NULL 
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
    } catch (PDOException $e) {
        error_log("Error resetting failed attempts: " . $e->getMessage());
    }
}

/**
 * Update last login timestamp for users - SINGLE DEFINITION
 */
function updateLastLogin($userId) {
    $pdo = getPDO();
    if (!$pdo) return;
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$userId]);
    } catch (PDOException $e) {
        error_log("Error updating last login: " . $e->getMessage());
    }
}

/**
 * Update last login timestamp for admins
 */
function updateAdminLastLogin($adminId) {
    $pdo = getPDO();
    if (!$pdo) return;
    
    try {
        $stmt = $pdo->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$adminId]);
    } catch (PDOException $e) {
        error_log("Error updating admin last login: " . $e->getMessage());
    }
}

/**
 * Determine redirect URL based on user status - SINGLE DEFINITION
 */
function determineRedirectUrl($user) {
    if (isset($_SESSION['intended_url'])) {
        $intendedUrl = $_SESSION['intended_url'];
        unset($_SESSION['intended_url']);
        return $intendedUrl;
    }
    
    switch ($user['status']) {
        case 'admin':
            return 'admin_dashboard.php';
        case 'active':
        case 'verified':
            return 'student-dashboard.php';
        default:
            return 'student-dashboard.php';
    }
}

/**
 * Validate and sanitize user input - SINGLE DEFINITION
 */
function sanitizeInput($input, $type = 'string') {
    $input = trim($input);
    $input = stripslashes($input);
    
    switch ($type) {
        case 'email':
            return filter_var($input, FILTER_SANITIZE_EMAIL);
        case 'int':
            return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
        case 'string':
        default:
            return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Get current logged-in user from session - SINGLE DEFINITION
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'student_id' => $_SESSION['student_id'] ?? null,
        'name' => $_SESSION['user_name'] ?? '',
        'email' => $_SESSION['email'] ?? null,
        'status' => $_SESSION['user_status'] ?? null
    ];
}

/**
 * Get current logged-in admin from session
 */
function getCurrentAdmin() {
    if (!isAdminLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'name' => $_SESSION['admin_name'] ?? '',
        'username' => $_SESSION['admin_username'] ?? null,
        'email' => $_SESSION['admin_email'] ?? null,
        'role' => $_SESSION['admin_role'] ?? null
    ];
}

/**
 * Logout function - SINGLE DEFINITION
 */
function logout() {
    $pdo = getPDO();
    if ($pdo && isset($_SESSION['user_id'])) {
        try {
            // Record logout event
            $stmt = $pdo->prepare("
                INSERT INTO user_sessions (user_id, action, ip_address, user_agent, created_at)
                VALUES (?, 'logout', ?, ?, NOW())
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                getClientIP(),
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (PDOException $e) {
            error_log("Error recording logout: " . $e->getMessage());
        }
    }
    
    // Clear login session
    clearLoginSession();
    
    // Redirect to website homepage
    header('Location: https://edubridgesa.co.za/');
    exit();
}

/**
 * Generate CSRF token - SINGLE DEFINITION
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token - SINGLE DEFINITION
 */
function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Check if user is logged in as admin
 */
function isAdminLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Set admin login session
 */
function setAdminLoginSession($admin, $rememberMe = false) {
    // Regenerate session ID for security
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    
    // Clear any existing student session
    unset($_SESSION['student_logged_in']);
    unset($_SESSION['student_name']);
    unset($_SESSION['student_id']);
    unset($_SESSION['student_email']);
    
    // Set admin session data
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_name'] = $admin['name'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_email'] = $admin['email'];
    $_SESSION['user_id'] = $admin['id'];
    $_SESSION['user_type'] = 'admin';
    $_SESSION['admin_role'] = $admin['role'];
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    
    // Device and security tracking
    $_SESSION['login_ip'] = getClientIP();
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Log admin login activity
    try {
        $pdo = getPDO();
        if ($pdo) {
            $stmt = $pdo->prepare("
                INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, user_agent) 
                VALUES (?, 'login', 'Admin logged in successfully', ?, ?)
            ");
            $stmt->execute([
                $admin['id'], 
                $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);
        }
    } catch (PDOException $e) {
        error_log("Admin activity log error: " . $e->getMessage());
    }
}
?>