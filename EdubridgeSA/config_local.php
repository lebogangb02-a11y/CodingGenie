<?php
/**
 * Local Development Configuration
 * Use this for testing reset scripts locally
 */

// Local Database Configuration (for XAMPP/WAMP)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');  // Usually empty for XAMPP
define('DB_NAME', 'edubridgesa_local');  // Local database name

// Email Configuration (same as production)
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_PORT', 465);
define('SMTP_SECURE', 'ssl');
define('SMTP_USERNAME', 'applications@edubridgesa.co.za');
define('SMTP_PASSWORD', 'BAs1m@n3');
define('SMTP_TIMEOUT', 30);
define('SMTP_AUTH', true);

// Email Settings
define('ADMIN_EMAIL', 'applications@edubridgesa.co.za');
define('FROM_EMAIL', 'applications@edubridgesa.co.za');
define('FROM_NAME', 'EduBridge SA');
define('REPLY_TO_EMAIL', 'applications@edubridgesa.co.za');
define('REPLY_TO_NAME', 'EduBridge SA');
define('EMAIL_SUBJECT', 'New University Application - EduBridge SA');

// Application Settings
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2MB
define('ALLOWED_FILE_TYPES', ['pdf', 'jpg', 'jpeg']);

// Security & Auth Settings
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_TIMEOUT', 1800);
define('AUTH_SALT', 'edubridge_secure_salt_2024!');
define('RATE_LIMIT_WINDOW', 900);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 900);

// Local development settings
define('DEBUG_MODE', true);  // Enable debug for local testing
define('LOG_FILE', __DIR__ . '/error.log');

// Auto-detect base URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$port = $_SERVER['SERVER_PORT'] ?? 80;
if (($protocol === 'http' && $port !== 80) || ($protocol === 'https' && $port !== 443)) {
    $host .= ':' . $port;
}
define('BASE_URL', $protocol . '://' . $host);

// Error Reporting
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', LOG_FILE);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', LOG_FILE);
}

// Database Connection
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    if (DEBUG_MODE) {
        die("Database connection failed: " . $e->getMessage());
    } else {
        die("Database connection failed. Please try again later.");
    }
}

// CSRF Token Generation
if (session_status() === PHP_SESSION_ACTIVE) {
    if (!isset($_SESSION[CSRF_TOKEN_NAME]) || (isset($_SESSION['csrf_generated_at']) && (time() - $_SESSION['csrf_generated_at'] > SESSION_TIMEOUT))) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        $_SESSION['csrf_generated_at'] = time();
    }
}
?>