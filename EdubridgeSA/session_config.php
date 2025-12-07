<?php
/**
 * Enhanced Session Configuration for EduBridge SA
 * Optimized for security, multi-device access, and modern browser compatibility
 * Compatible with PHP 7.4+ and handles both HTTP/HTTPS environments
 */

// Prevent multiple session starts
if (session_status() === PHP_SESSION_NONE) {
    
    // Detect if we're running on HTTPS
    $isHTTPS = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        $_SERVER['SERVER_PORT'] == 443 ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
    );
    
    // Enhanced session configuration for security and multi-device support
    ini_set('session.cookie_lifetime', 0); // Session cookie (expires when browser closes)
    ini_set('session.gc_maxlifetime', 86400 * 7); // Keep session data for 7 days
    ini_set('session.gc_probability', 1);
    ini_set('session.gc_divisor', 100);
    ini_set('session.cookie_secure', $isHTTPS ? 1 : 0); // Secure only on HTTPS
    ini_set('session.cookie_httponly', 1); // Prevent XSS attacks
    ini_set('session.use_strict_mode', 1); // Prevent session fixation
    ini_set('session.use_only_cookies', 1); // Only use cookies for session ID
    ini_set('session.cookie_samesite', 'Lax'); // Enhanced security with cross-page form compatibility
    
    // Set session name for better security
    session_name('EDUBRIDGESA_SESSION');
    
    // Determine a stable cookie domain to prevent session loss across www/non-www
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $cookieDomain = '';
    if ($host) {
        // Normalize to lower-case and strip port if present (e.g., :8080)
        $hostNoPort = strtolower(preg_replace('/:\d+/', '', $host));
        // Use apex domain for EduBridge SA so sessions persist across www and bare domain
        if (preg_match('/(^|\.)edubridgesa\.co\.za$/', $hostNoPort)) {
            $cookieDomain = '.edubridgesa.co.za';
        }
    }

    // Configure session cookie parameters
    session_set_cookie_params([
        'lifetime' => 0, // Session cookie (browser session)
        'path' => '/',
        'domain' => $cookieDomain, // Persist across www/apex in production
        'secure' => $isHTTPS, // Secure only on HTTPS
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    // Start the session
    session_start();
    
    // Session security and regeneration
    if (!isset($_SESSION['initiated'])) {
        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
        $_SESSION['created_at'] = time();
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $_SESSION['ip_address'] = getClientIP();
    }
    
    // Validate session security (soft handling of user-agent changes)
    if (isset($_SESSION['user_agent'])) {
        $currentUA = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if ($currentUA && $_SESSION['user_agent'] !== $currentUA) {
            // Regenerate session ID and update UA to reduce false positives
            session_regenerate_id(true);
            $_SESSION['user_agent'] = $currentUA;
            $_SESSION['ua_changed'] = true; // hint for diagnostics
        }
    }
    
    // Regenerate session ID periodically for active sessions
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 1800) { // 30 minutes
        if (isLoggedIn() && isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) < 3600) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }
}

/**
 * Get client IP address (handles proxies and load balancers)
 */
function getClientIP() {
    $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    foreach ($ipKeys as $key) {
        if (!empty($_SERVER[$key])) {
            $ips = explode(',', $_SERVER[$key]);
            $ip = trim($ips[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/**
 * Enhanced function to check if user is logged in
 */
function isLoggedIn() {
    if (!isset($_SESSION['student_logged_in']) || $_SESSION['student_logged_in'] !== true) {
        return false;
    }
    
    // Soft-check user agent: do not terminate session on change
    if (isset($_SESSION['user_agent'])) {
        $currentUserAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if ($currentUserAgent && $_SESSION['user_agent'] !== $currentUserAgent) {
            $_SESSION['ua_changed'] = true; // allow continued access; optionally log elsewhere
        }
    }
    
    // Check session timeout (24 hours of inactivity)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 86400) {
        clearLoginSession();
        return false;
    }
    
    // Check if session is too old (7 days maximum)
    if (isset($_SESSION['created_at']) && (time() - $_SESSION['created_at']) > (86400 * 7)) {
        clearLoginSession();
        return false;
    }
    
    return true;
}

/**
 * Enhanced function to set login session with device tracking
 */
function setLoginSession($user, $rememberMe = false) {
    // Regenerate session ID for security
    session_regenerate_id(true);
    
    // Core session data
    $_SESSION['student_logged_in'] = true;
    $_SESSION['student_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
    $_SESSION['student_id'] = $user['student_id'];
    $_SESSION['student_email'] = $user['email'];
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_type'] = 'student';
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    $_SESSION['last_regeneration'] = time();
    
    // Device and security tracking
    $_SESSION['login_ip'] = getClientIP();
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $_SESSION['device_id'] = generateDeviceFingerprint();
    // Generate CSRF token for this session if not present
    if (!isset($_SESSION[CSRF_TOKEN_NAME]) || empty($_SESSION[CSRF_TOKEN_NAME])) {
        try {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
            $_SESSION[CSRF_TOKEN_NAME . '_time'] = time();
        } catch (Exception $e) {
            // Fallback: less-strong token (should rarely happen)
            $_SESSION[CSRF_TOKEN_NAME] = substr(bin2hex(openssl_random_pseudo_bytes(32)), 0, 64);
            $_SESSION[CSRF_TOKEN_NAME . '_time'] = time();
        }
    }
    
    // Multi-device support with remember me
    if ($rememberMe) {
        $_SESSION['remember_me'] = true;
        $_SESSION['extended_session'] = true;
        
        // Set a longer-lasting cookie for remember me functionality
        $isHTTPS = (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            $_SERVER['SERVER_PORT'] == 443 ||
            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        );
        
        setcookie(
            'remember_user_' . $user['id'],
            generateRememberToken($user['id']),
            time() + (86400 * 30), // 30 days
            '/',
            '',
            $isHTTPS,
            true
        );
    }
}

/**
 * Generate device fingerprint for multi-device tracking
 */
function generateDeviceFingerprint() {
    // Use a stable fingerprint to avoid spurious changes across mobile networks or proxies
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $uaNorm = strtolower(preg_replace('/\s+/', ' ', trim($ua)));
    return hash('sha256', $uaNorm);
}

/**
 * Generate secure remember token
 */
function generateRememberToken($userId) {
    $token = bin2hex(random_bytes(32));
    $hashedToken = hash('sha256', $token);
    // Attempt to store hashed token in remember_tokens table (if available)
    try {
        // Use existing PDO from config if available
        global $pdo;
        if (!isset($pdo) || !$pdo) {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        }

        $stmt = $pdo->prepare("INSERT INTO remember_tokens (user_id, token_hash, user_agent, ip_address, expires_at, created_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY), NOW())");
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ip = getClientIP();
        $stmt->execute([$userId, $hashedToken, $ua, $ip]);
    } catch (Exception $e) {
        // Log but do not break login flow
        error_log('Failed to store remember token: ' . $e->getMessage());
    }

    return $token;
}

/**
 * Enhanced function to clear login session
 */
function clearLoginSession() {
    $userId = $_SESSION['user_id'] ?? null;
    
    // Clear remember me cookies
    if ($userId) {
        $isHTTPS = (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            $_SERVER['SERVER_PORT'] == 443 ||
            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        );
        
        setcookie(
            'remember_user_' . $userId,
            '',
            time() - 3600,
            '/',
            '',
            $isHTTPS,
            true
        );
    }
    
    // Unset all session variables
    $_SESSION = array();
    
    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
    
    // Start a new clean session
    session_start();
    $_SESSION['logged_out'] = true;
}

/**
 * Function to extend session activity and auto-refresh
 */
function extendSession() {
    if (isLoggedIn()) {
        $_SESSION['last_activity'] = time();
        
        // Extend session cookie if remember me is enabled
        if (isset($_SESSION['remember_me']) && $_SESSION['remember_me']) {
            $params = session_get_cookie_params();
            $isHTTPS = (
                (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                $_SERVER['SERVER_PORT'] == 443 ||
                (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            );
            
            setcookie(
                session_name(),
                session_id(),
                time() + 86400, // Extend for 24 hours
                $params["path"],
                $params["domain"],
                $isHTTPS,
                $params["httponly"]
            );
        }
        
        return true;
    }
    return false;
}

/**
 * Check and handle remember me functionality
 */
function checkRememberMe() {
    if (isLoggedIn()) {
        return true;
    }
    
    // Check for remember me cookies
    foreach ($_COOKIE as $name => $value) {
        if (strpos($name, 'remember_user_') === 0) {
            $userId = str_replace('remember_user_', '', $name);
            
            // Validate remember token (implement database check)
            if (validateRememberToken($userId, $value)) {
                // Auto-login user
                $user = getUserById($userId);
                if ($user && $user['status'] === 'active') {
                    setLoginSession($user, true);
                    return true;
                }
            }
            
            // Invalid token, remove cookie
            $isHTTPS = (
                (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                $_SERVER['SERVER_PORT'] == 443 ||
                (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            );
            
            setcookie($name, '', time() - 3600, '/', '', $isHTTPS, true);
        }
    }
    
    return false;
}

/**
 * Validate remember token (implement with your database)
 */
function validateRememberToken($userId, $token) {
    try {
        require_once 'config.php';
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
            SELECT id FROM users 
            WHERE id = ? AND remember_token = ? AND remember_token_expires > NOW() AND status = 'active'
        ");
        $stmt->execute([$userId, $token]);
        
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Remember token validation failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user by ID (implement with your database)
 */
function getUserById($userId) {
    try {
        require_once 'config.php';
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
            SELECT id, email, first_name, last_name, student_id, status 
            FROM users 
            WHERE id = ? AND status = 'active'
        ");
        $stmt->execute([$userId]);
        
        return $stmt->fetch() ?: false;
    } catch (PDOException $e) {
        error_log("Get user by ID failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Session cleanup and security check
 */
function performSessionMaintenance() {
    // Clean up old sessions (call this periodically)
    if (rand(1, 100) === 1) { // 1% chance
        session_gc();
    }
    
    // Log suspicious activity
    if (isset($_SESSION['security_violation'])) {
        error_log("Session security violation: " . $_SESSION['security_violation'] . " IP: " . getClientIP());
        unset($_SESSION['security_violation']);
    }
}

// Auto-extend session on each page load if user is logged in
if (isLoggedIn()) {
    extendSession();
} else {
    if (!defined('SKIP_REMEMBER_ME') || !SKIP_REMEMBER_ME) {
        checkRememberMe();
    }
}

// Perform maintenance
performSessionMaintenance();

/**
 * ADMIN SESSION FUNCTIONS
 * Added to handle admin-specific sessions
 */

/**
 * Check if user is logged in as admin
 */
function isAdminLoggedIn() {
    // Check for admin session
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        return false;
    }
    
    // Perform the same security checks as isLoggedIn()
    if (isset($_SESSION['user_agent'])) {
        $currentUserAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if ($currentUserAgent && $_SESSION['user_agent'] !== $currentUserAgent) {
            $_SESSION['ua_changed'] = true;
        }
    }
    
    // Check session timeout (24 hours of inactivity)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 86400) {
        clearAdminLoginSession();
        return false;
    }
    
    // Check if session is too old (7 days maximum)
    if (isset($_SESSION['created_at']) && (time() - $_SESSION['created_at']) > (86400 * 7)) {
        clearAdminLoginSession();
        return false;
    }
    
    return true;
}

/**
 * Set admin login session
 */
function setAdminLoginSession($adminUser, $rememberMe = false) {
    // Regenerate session ID for security
    session_regenerate_id(true);
    
    // Clear any existing student session
    unset($_SESSION['student_logged_in']);
    unset($_SESSION['student_name']);
    unset($_SESSION['student_id']);
    unset($_SESSION['student_email']);
    
    // Set admin session data
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_name'] = trim($adminUser['first_name'] . ' ' . $adminUser['last_name']);
    $_SESSION['admin_email'] = $adminUser['email'];
    $_SESSION['user_id'] = $adminUser['id'];
    $_SESSION['user_type'] = 'admin';
    $_SESSION['admin_role'] = $adminUser['role'] ?? 'admin';
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    $_SESSION['last_regeneration'] = time();
    
    // Device and security tracking
    $_SESSION['login_ip'] = getClientIP();
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $_SESSION['device_id'] = generateDeviceFingerprint();
    
    // Multi-device support with remember me
    if ($rememberMe) {
        $_SESSION['remember_me'] = true;
        $_SESSION['extended_session'] = true;
        
        // Set a longer-lasting cookie for remember me functionality
        $isHTTPS = (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            $_SERVER['SERVER_PORT'] == 443 ||
            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        );
        
        setcookie(
            'remember_admin_' . $adminUser['id'],
            generateAdminRememberToken($adminUser['id']),
            time() + (86400 * 30), // 30 days
            '/',
            '',
            $isHTTPS,
            true
        );
    }
    
    // Log admin login activity
    try {
        require_once 'config.php';
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
            INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, user_agent) 
            VALUES (?, 'login', 'Admin logged in successfully', ?, ?)
        ");
        $stmt->execute([
            $adminUser['id'], 
            $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
    } catch (PDOException $e) {
        error_log("Admin activity log error: " . $e->getMessage());
    }
}

/**
 * Generate secure remember token for admin
 */
function generateAdminRememberToken($adminId) {
    $token = bin2hex(random_bytes(32));
    $hashedToken = hash('sha256', $token);
    
    // Store hashed token in database
    try {
        require_once 'config.php';
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
        
        // Try different admin table names
        $tables = ['admin_users', 'admins'];
        foreach ($tables as $table) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE $table 
                    SET remember_token = ?, remember_token_expires = DATE_ADD(NOW(), INTERVAL 30 DAY) 
                    WHERE id = ?
                ");
                $stmt->execute([$hashedToken, $adminId]);
                break;
            } catch (PDOException $e) {
                continue;
            }
        }
    } catch (PDOException $e) {
        error_log("Admin remember token storage error: " . $e->getMessage());
    }
    
    return $token;
}

/**
 * Clear admin login session
 */
function clearAdminLoginSession() {
    $adminId = $_SESSION['user_id'] ?? null;
    
    // Clear remember me cookies
    if ($adminId) {
        $isHTTPS = (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            $_SERVER['SERVER_PORT'] == 443 ||
            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        );
        
        setcookie(
            'remember_admin_' . $adminId,
            '',
            time() - 3600,
            '/',
            '',
            $isHTTPS,
            true
        );
    }
    
    // Unset admin session variables
    unset($_SESSION['admin_logged_in']);
    unset($_SESSION['admin_name']);
    unset($_SESSION['admin_email']);
    unset($_SESSION['admin_role']);
    
    // If no student session exists, clear everything
    if (!isset($_SESSION['student_logged_in'])) {
        $_SESSION = array();
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        
        session_destroy();
        session_start();
        $_SESSION['logged_out'] = true;
    }
}

/**
 * Get admin by ID
 */
function getAdminById($adminId) {
    try {
        require_once 'config.php';
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
        
        // Try different possible admin table names
        $tables = ['admin_users', 'admins'];
        foreach ($tables as $table) {
            try {
                $stmt = $pdo->prepare("
                    SELECT id, email, first_name, last_name, role, status, password_hash 
                    FROM $table 
                    WHERE id = ? AND status = 'active'
                ");
                $stmt->execute([$adminId]);
                $admin = $stmt->fetch();
                if ($admin) {
                    return $admin;
                }
            } catch (PDOException $e) {
                continue;
            }
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Get admin by ID failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Check and handle admin remember me functionality
 */
function checkAdminRememberMe() {
    if (isAdminLoggedIn()) {
        return true;
    }
    
    // Check for admin remember me cookies
    foreach ($_COOKIE as $name => $value) {
        if (strpos($name, 'remember_admin_') === 0) {
            $adminId = str_replace('remember_admin_', '', $name);
            
            // Validate remember token
            if (validateAdminRememberToken($adminId, $value)) {
                // Auto-login admin
                $admin = getAdminById($adminId);
                if ($admin && $admin['status'] === 'active') {
                    setAdminLoginSession($admin, true);
                    return true;
                }
            }
            
            // Invalid token, remove cookie
            removeRememberCookie($name);
        }
    }
    
    return false;
}

/**
 * Validate admin remember token
 */
function validateAdminRememberToken($adminId, $token) {
    try {
        require_once 'config.php';
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
        
        // Try different admin table names
        $tables = ['admin_users', 'admins'];
        foreach ($tables as $table) {
            try {
                $stmt = $pdo->prepare("
                    SELECT id FROM $table 
                    WHERE id = ? AND remember_token = ? AND remember_token_expires > NOW() AND status = 'active'
                ");
                $stmt->execute([$adminId, hash('sha256', $token)]);
                
                if ($stmt->rowCount() > 0) {
                    return true;
                }
            } catch (PDOException $e) {
                continue;
            }
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Admin remember token validation failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Remove remember me cookie
 */
function removeRememberCookie($cookieName) {
    $isHTTPS = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        $_SERVER['SERVER_PORT'] == 443 ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    );
    
    setcookie($cookieName, '', time() - 3600, '/', '', $isHTTPS, true);
}

// Update the auto-extend to handle both student and admin sessions
if (isLoggedIn() || isAdminLoggedIn()) {
    extendSession();
} else {
    if (!defined('SKIP_REMEMBER_ME') || !SKIP_REMEMBER_ME) {
        // Check both student and admin remember me
        if (!checkRememberMe() && !checkAdminRememberMe()) {
            // Neither student nor admin remember me worked
        }
    }
}