<?php
/**
 * Database Configuration for Student Application System
 * This file contains database connection settings
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'u839420047_Edubridge');
define('DB_PASS', 'BAs1m@n3');
define('DB_NAME', 'u839420047_applications');

// Application Settings
define('APP_NAME', 'Student Application Portal');
define('APP_VERSION', '1.0');
define('BASE_URL', 'https://edubridgesa.co.za');
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB max file size
define('ALLOWED_FILE_TYPES', ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx']);

// Upload directories
define('UPLOAD_BASE_DIR', 'uploads/applications/');
define('UPLOAD_ID_DIR', UPLOAD_BASE_DIR . 'id_documents/');
define('UPLOAD_RESIDENCE_DIR', UPLOAD_BASE_DIR . 'proof_of_residence/');
define('UPLOAD_PARENT_ID_DIR', UPLOAD_BASE_DIR . 'parent_guardian_id/');
define('UPLOAD_ACADEMIC_DIR', UPLOAD_BASE_DIR . 'academic_results/');

// Email Configuration (Hostinger SMTP)
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_PORT', 465);
define('SMTP_SECURE', 'ssl');
define('SMTP_USERNAME', 'applications@edubridgesa.co.za');
define('SMTP_PASSWORD', 'BAs1m@n3');
define('SMTP_TIMEOUT', 30);
define('SMTP_AUTH', true);
define('FROM_EMAIL', 'applications@edubridgesa.co.za');
define('FROM_NAME', 'EduBridge SA');
define('ADMIN_EMAIL', 'applications@edubridgesa.co.za');
define('REPLY_TO_EMAIL', 'applications@edubridgesa.co.za');
define('REPLY_TO_NAME', 'EduBridge SA');

// Optional DKIM configuration (for better authentication & inbox placement)
// Update these paths to your real key locations once generated and stored securely
define('DKIM_DOMAIN', 'edubridgesa.co.za');
define('DKIM_SELECTOR', 'edubridgesa');
define('DKIM_PRIVATE_KEY_PATH', __DIR__ . '/../secure/dkim_private.key');
define('DKIM_PASSPHRASE', '');

// Warm-up enforcement toggle (set true to gradually ramp up sending)
define('ENABLE_WARMUP_LIMIT', true);

// Template preference: use designed verification template (can switch to minimal if needed)
if (!defined('USE_MINIMAL_VERIFICATION_TEMPLATE')) {
    define('USE_MINIMAL_VERIFICATION_TEMPLATE', false);
}

// Security Settings
define('SESSION_TIMEOUT', 3600); // 1 hour
define('CSRF_TOKEN_NAME', 'csrf_token');

/**
 * Get database connection
 */
function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed. Please try again later.");
        }
    }
    
    return $pdo;
}

/**
 * Generate unique reference number
 */
function generateReferenceNumber() {
    $year = date('Y');
    $month = date('m');
    $random = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    return "APP{$year}{$month}{$random}";
}

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email address
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate South African ID number
 */
function isValidSAIDNumber($id) {
    // Basic SA ID validation (13 digits)
    if (!preg_match('/^\d{13}$/', $id)) {
        return false;
    }
    
    // Additional validation can be added here
    return true;
}

/**
 * Validate phone number
 */
function isValidPhoneNumber($phone) {
    // Remove spaces and special characters
    $phone = preg_replace('/[^\d]/', '', $phone);
    
    // Check if it's a valid SA mobile number (10 digits starting with 0)
    return preg_match('/^0[6-8]\d{8}$/', $phone);
}

/**
 * Create upload directories if they don't exist
 */
function createUploadDirectories() {
    $dirs = [
        UPLOAD_BASE_DIR,
        UPLOAD_ID_DIR,
        UPLOAD_RESIDENCE_DIR,
        UPLOAD_PARENT_ID_DIR,
        UPLOAD_ACADEMIC_DIR
    ];
    
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

// Initialize upload directories
createUploadDirectories();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>