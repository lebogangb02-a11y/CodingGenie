<?php

/**
 * Configuration file for University Application System
 * EduBridge SA
 * Updated: String-based SMTP_SECURE to avoid PHPMailer class loading in config
 */

// Security: Prevent direct access to this config file
if (basename($_SERVER['PHP_SELF'] ?? '') === 'config.php') {
    header('HTTP/1.0 403 Forbidden');
    exit('Access denied');
}

// Database Configuration - Read from environment where possible
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
// Do NOT keep real credentials in source. Prefer setting DB_USER/DB_PASS via environment.
define('DB_USER', getenv('DB_USER') ?: '');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'u839420047_applications');

// Email Settings (non-SMTP)
define('ADMIN_EMAIL', 'applications@edubridgesa.co.za');
define('FROM_EMAIL', 'applications@edubridgesa.co.za');
define('FROM_NAME', 'EduBridge SA');
define('REPLY_TO_EMAIL', 'applications@edubridgesa.co.za');
define('REPLY_TO_NAME', 'EduBridge SA');
define('EMAIL_SUBJECT', 'New University Application - EduBridge SA');

// Optional: Override FROM for testing (e.g., dev emails)
define('MAIL_FROM_OVERRIDE', null);  // Set to 'test@domain.com' for dev; null uses FROM_EMAIL

// Application Settings
define('UPLOAD_DIR', realpath(__DIR__) . '/uploads/');
// 5MB per file (as per requirements)
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
// Restrict to PDF, JPEG and PNG formats only
define('ALLOWED_FILE_TYPES', ['pdf', 'jpg', 'jpeg', 'png']);

// Security & Auth Settings
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_TIMEOUT', 1800);  // 30 minutes idle timeout (more secure)
// AUTH_SALT should be provided via environment in production
define('AUTH_SALT', getenv('AUTH_SALT') ?: 'edubridge_secure_salt_2024!');  // fallback for local/dev only
define('RATE_LIMIT_WINDOW', 900);  // 15 minutes for login attempts
define('MAX_LOGIN_ATTEMPTS', 5);   // Max failed logins before lockout
define('LOCKOUT_DURATION', 900);   // 15 minutes lockout

// Auto-detect base URL for redirects/emails
// Normalize host to avoid default port suffixes (e.g., :443, :80) in links
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$rawHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
// Strip any trailing :port from HTTP_HOST
$host = preg_replace('/:\\d+$/', '', $rawHost);
// Only append non-default ports explicitly
$port = (int)($_SERVER['SERVER_PORT'] ?? (($protocol === 'https') ? 443 : 80));
if (($protocol === 'http' && $port !== 80) || ($protocol === 'https' && $port !== 443)) {
    $host .= ':' . $port;
}
// Respect pre-defined BASE_URL (e.g., from config_application.php) to avoid conflicts
if (!defined('BASE_URL')) {
    define('BASE_URL', $protocol . '://' . $host);
}

// Error Reporting & Logging
define('DEBUG_MODE', false);  // Set to false for production security
define('LOG_FILE', __DIR__ . '/error.log');  // Custom log file (optional)

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
    // Allow non-fatal DB failure when explicitly requested by callers (e.g., catalog API)
    if (defined('CATALOG_DB_OPTIONAL') && CATALOG_DB_OPTIONAL === true) {
        error_log("Database connection failed (optional): " . $e->getMessage());
        $pdo = null; // Proceed without a database connection
    } else {
        if (DEBUG_MODE) {
            die("Database connection failed: " . $e->getMessage());
        } else {
            die("Database connection failed. Please try again later.");
        }
    }
}

// Note: Session management is handled by session_config.php
// Always include session_config.php before config.php in files needing sessions

// CSRF Token Generation (stable per session)
// Generate once when missing; do not auto-rotate to avoid cross-tab mismatches.
if (session_status() === PHP_SESSION_ACTIVE) {
    if (!isset($_SESSION[CSRF_TOKEN_NAME]) || !is_string($_SESSION[CSRF_TOKEN_NAME]) || $_SESSION[CSRF_TOKEN_NAME] === '') {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
}

// Optional: Load .env via phpdotenv if available (non-fatal)
$composerAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
    if (class_exists('\Dotenv\Dotenv')) {
        try {
            $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
            $dotenv->safeLoad();
        } catch (Exception $e) {
            // Ignore dotenv load failures; environment variables may be provided by hosting
            error_log('Dotenv load warning: ' . $e->getMessage());
        }
    }
}

// Include small security helpers (h(), csrf_input()) when available
if (file_exists(__DIR__ . '/includes/security_helpers.php')) {
    require_once __DIR__ . '/includes/security_helpers.php';
}

// Optional AI configuration (guarded)
// Prefer .htaccess SetEnv or process environment; no hardcoded key in repo
if (!defined('OPENAI_API_KEY')) {
    $openaiKey = $_SERVER['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY') ?? null;
    define('OPENAI_API_KEY', $openaiKey);
}
if (!defined('OPENAI_API_BASE')) {
    define('OPENAI_API_BASE', getenv('OPENAI_API_BASE') ?: 'https://api.openai.com/v1');
}
if (!defined('OPENAI_CHAT_MODEL')) {
    // Default model; helper can override
    define('OPENAI_CHAT_MODEL', getenv('OPENAI_CHAT_MODEL') ?: 'gpt-3.5-turbo');
}
if (!defined('OPENAI_WHISPER_MODEL')) {
    define('OPENAI_WHISPER_MODEL', getenv('OPENAI_WHISPER_MODEL') ?: 'whisper-1');
}
// ... your existing config ...

// Include notification functions
require_once __DIR__ . '/notification_functions.php';

// Debug check (logs only)
if (defined('OPENAI_API_KEY') && OPENAI_API_KEY && strpos(OPENAI_API_KEY, 'sk-proj-') === 0) {
    error_log("✅ OpenAI API key loaded successfully");
} else {
    error_log("❌ OpenAI API key missing - using fallback mode");
}

// Optional: Hugging Face token support (free API)
if (!defined('HUGGINGFACE_API_TOKEN')) {
    $hfToken = $_SERVER['HUGGINGFACE_API_TOKEN'] ?? getenv('HUGGINGFACE_API_TOKEN') ?? null;
    define('HUGGINGFACE_API_TOKEN', $hfToken);
}

// Ensure upload directory exists and is writable
if (!is_dir(UPLOAD_DIR)) {
    @mkdir(UPLOAD_DIR, 0755, true);
}
if (!is_dir(UPLOAD_DIR) || !is_writable(UPLOAD_DIR)) {
    error_log('Upload directory not writable: ' . UPLOAD_DIR);
    // Do not die in production; log the problem so operators can fix permissions
}
