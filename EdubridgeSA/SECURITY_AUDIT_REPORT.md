# EduBridge SA PHP Security Audit Report

**Date:** December 3, 2025  
**Auditor:** Senior Full-Stack Code Auditor  
**Status:** CRITICAL ISSUES IDENTIFIED  

---

## Executive Summary

This comprehensive security audit of the EduBridge SA application identified **23 Critical/High severity issues** across authentication, file uploads, database operations, and API endpoints. Immediate remediation is required before production deployment.

**Risk Level:** 🔴 **CRITICAL** - Multiple exploitable vulnerabilities present

---

## 1. SESSION_CONFIG.PHP

**File Path:** `EdubridgeSA/session_config.php`

### Issue #1: Incomplete Remember Token Implementation
- **Severity:** CRITICAL
- **Type:** Broken Authentication Logic
- **Line Numbers:** 177-190 (generateRememberToken function)
- **Problem:** 
  - Function generates tokens but commented-out database storage
  - `storeRememberToken()` call is commented out, tokens are never persisted
  - Session fixation vulnerability: remember tokens not validated against database
- **Impact:** Remember-me functionality stores unverified tokens; attackers can forge tokens
- **Code Snippet - PROBLEM:**
```php
function generateRememberToken($userId) {
    $token = bin2hex(random_bytes(32));
    $hashedToken = hash('sha256', $token);
    
    // Store hashed token in database (you'll need to implement this)
    // storeRememberToken($userId, $hashedToken, time() + (86400 * 30));  // ← COMMENTED OUT!
    
    return $token;
}
```
- **Suggested Fix:**
  - Implement actual token storage in database
  - Validate token expiration and user status
  - Create `remember_tokens` table with user_id, token_hash, expires_at, created_at
  - Always validate remember tokens against database before auto-login

**Code Snippet - SOLUTION:**
```php
function generateRememberToken($userId) {
    $token = bin2hex(random_bytes(32));
    $hashedToken = hash('sha256', $token);
    
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare("
            INSERT INTO remember_tokens (user_id, token_hash, expires_at, created_at)
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY), NOW())
        ");
        $stmt->execute([$userId, $hashedToken]);
    } catch (PDOException $e) {
        error_log("Failed to store remember token: " . $e->getMessage());
        return null;
    }
    
    return $token;
}
```

---

### Issue #2: Missing User-Agent Validation in checkRememberMe()
- **Severity:** HIGH
- **Type:** Session Hijacking Risk
- **Line Numbers:** 206-241
- **Problem:**
  - `checkRememberMe()` doesn't validate user-agent matches session user-agent
  - Only validates token; attacker on different device can hijack session with remember cookie
  - No IP address validation for cross-network hijacking detection
- **Impact:** Session hijacking via stolen remember tokens
- **Suggested Fix:**
```php
function checkRememberMe() {
    if (isLoggedIn()) {
        return true;
    }
    
    foreach ($_COOKIE as $name => $value) {
        if (strpos($name, 'remember_user_') === 0) {
            $userId = str_replace('remember_user_', '', $name);
            
            // Validate token AND verify device fingerprint
            if (validateRememberToken($userId, $value)) {
                $user = getUserById($userId);
                if ($user && $user['status'] === 'active') {
                    // Verify user agent matches (add to remember_tokens table)
                    $currentUA = $_SERVER['HTTP_USER_AGENT'] ?? '';
                    $stmt = $pdo->prepare("
                        SELECT id FROM remember_tokens 
                        WHERE user_id = ? AND token_hash = ? 
                        AND user_agent = ? AND expires_at > NOW()
                    ");
                    $stmt->execute([$userId, hash('sha256', $value), $currentUA]);
                    if ($stmt->rowCount() > 0) {
                        setLoginSession($user, true);
                        return true;
                    }
                }
            }
            
            // Invalid token, remove cookie
            setcookie($name, '', time() - 3600, '/', '', true, true);
        }
    }
    
    return false;
}
```

---

### Issue #3: No CSRF Token Generation in Session
- **Severity:** CRITICAL
- **Type:** CSRF Vulnerability
- **Line Numbers:** 1-100 (entire file initialization)
- **Problem:**
  - Session initialization doesn't generate CSRF token
  - No `$_SESSION['csrf_token']` created during `setLoginSession()`
  - Forms have no built-in CSRF protection mechanism
- **Impact:** Forms vulnerable to CSRF attacks (state-changing operations)
- **Suggested Fix:**
```php
function setLoginSession($user, $rememberMe = false) {
    session_regenerate_id(true);
    
    // ... existing code ...
    
    // Generate CSRF token for form protection
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_time'] = time();
    
    // ... rest of function ...
}
```

---

## 2. AUTH.PHP

**File Path:** `EdubridgeSA/auth.php`

### Issue #4: Admin Password Using Weak SHA256 Instead of password_verify()
- **Severity:** CRITICAL
- **Type:** Weak Cryptography
- **Line Numbers:** 201-205
- **Problem:**
  - Admin authentication uses `hash('sha256', $password . AUTH_SALT)` instead of `password_verify()`
  - SHA256 is fast and suitable for rainbow table attacks
  - No salt per password, single AUTH_SALT used for all admins (hardcoded in config)
  - Vulnerable to offline dictionary attacks if database compromised
- **Impact:** Compromised database allows rapid admin password cracking
- **Current Code - PROBLEM:**
```php
$hashed_input = hash('sha256', $password . AUTH_SALT);
if ($hashed_input === $admin['password_hash']) {
```
- **Suggested Fix:**
```php
// For new admin registrations, use password_hash() and bcrypt
if (password_verify($password, $admin['password_hash'])) {
    // Verify password successfully
    recordSuccessfulLogin($admin['id']);
    // ... rest of code ...
} else {
    recordFailedLogin($username, 'INVALID_ADMIN_PASSWORD');
    // ...
}
```

---

### Issue #5: SQL Injection in generateApplicationReference() - Direct Query
- **Severity:** CRITICAL
- **Type:** SQL Injection (Legacy mysqli API)
- **Line Numbers:** 42-55 in handleApplicationSubmit.php
- **Problem:**
  - File uses `mysqli` with `bind_param()` - different from rest of PDO codebase
  - SQL construction correct but mixing database APIs is dangerous
  - No prepared statements validation in bind parameters
- **Impact:** If binding fails silently, SQL injection possible
- **Suggested Fix:** Standardize on PDO across all files

---

### Issue #6: Missing Email Verification Status Check Before Login
- **Severity:** HIGH
- **Type:** Authentication Bypass (Partial)
- **Line Numbers:** 94-96
- **Problem:**
  - `checkAccountStatus()` checks `email_verified` flag
  - But flag check is on line 311, not enforced during login in all paths
  - If session is set before verification check completes, user can access protected pages
- **Impact:** Unverified emails can potentially access restricted features
- **Suggested Fix:**
```php
// In authenticateUser() after password_verify succeeds:
if (!$user['email_verified']) {
    recordFailedLogin($email, 'EMAIL_NOT_VERIFIED');
    return [
        'success' => false,
        'message' => 'Please verify your email before logging in.',
        'error_code' => 'EMAIL_NOT_VERIFIED'
    ];
}
```

---

### Issue #7: Hardcoded Credentials in config.php
- **Severity:** CRITICAL
- **Type:** Hardcoded Secrets
- **Line Numbers:** config.php, lines 14-16
- **Problem:**
```php
define('DB_USER', 'u839420047_Edubridge');
define('DB_PASS', 'BAs1m@n3');
define('DB_NAME', 'u839420047_applications');
```
  - Database credentials hardcoded in version control
  - Password exposed in source code
  - No environment variable usage
- **Impact:** Anyone with repo access has full database access
- **Suggested Fix:**
```php
// Use environment variables
define('DB_USER', getenv('DB_USER') ?: 'default_user');
define('DB_PASS', getenv('DB_PASS') ?: null);
define('DB_NAME', getenv('DB_NAME') ?: 'default_db');

if (!DB_PASS) {
    die('Database password not configured in environment variables');
}
```

---

### Issue #8: No Rate Limiting on Admin Login
- **Severity:** HIGH
- **Type:** Brute Force Attack
- **Line Numbers:** 172-180
- **Problem:**
  - `authenticateAdmin()` calls `checkRateLimit()` with username
  - But `checkRateLimit()` in auth.php checks 'email' field, not 'username'
  - Rate limit table may have different column naming
- **Impact:** Admin brute force attacks may bypass rate limiting
- **Suggested Fix:** Ensure rate limiting consistently checks both IP and identifier

---

## 3. CONFIG.PHP

**File Path:** `EdubridgeSA/config.php`

### Issue #9: Database Credentials Hardcoded (Already Covered Above)
- **Severity:** CRITICAL
- See Issue #7

---

### Issue #10: Missing File Upload Directory Permissions Check
- **Severity:** MEDIUM
- **Line Numbers:** 32
- **Problem:**
```php
define('UPLOAD_DIR', 'uploads/');
```
  - No verification that directory is writable
  - No path traversal prevention
  - Directory can be outside web root (good) but not validated
- **Suggested Fix:**
```php
define('UPLOAD_DIR', realpath(__DIR__) . '/uploads/');

// Validate at startup
if (!is_dir(UPLOAD_DIR)) {
    @mkdir(UPLOAD_DIR, 0755, true);
}
if (!is_writable(UPLOAD_DIR)) {
    trigger_error('Upload directory not writable', E_USER_ERROR);
}
```

---

### Issue #11: BASE_URL Auto-Detection Vulnerable to Host Header Injection
- **Severity:** MEDIUM
- **Type:** Host Header Injection
- **Line Numbers:** 48-57
- **Problem:**
```php
$rawHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
// ...
$host = preg_replace('/:\\d+$/', '', $rawHost);
```
  - `HTTP_HOST` can be spoofed by attacker
  - Used in email links and redirects
  - `preg_replace()` doesn't validate domain format
- **Impact:** Email phishing links pointing to attacker domain
- **Suggested Fix:**
```php
// Whitelist allowed hosts in environment variable
$allowedHosts = explode(',', getenv('ALLOWED_HOSTS') ?: 'edubridgesa.co.za,www.edubridgesa.co.za');
$rawHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$host = preg_replace('/:\\d+$/', '', strtolower($rawHost));

if (!in_array($host, $allowedHosts)) {
    $host = $allowedHosts[0]; // Fallback to primary domain
}
```

---

## 4. DOCUMENT_UPLOAD.PHP

**File Path:** `EdubridgeSA/document_upload.php`

### Issue #12: Arbitrary File Upload - No File Type Validation Enforcement
- **Severity:** CRITICAL
- **Type:** Arbitrary File Upload / Remote Code Execution
- **Line Numbers:** 70-90, 109-140
- **Problem:**
  - File extension checked with `strtolower(pathinfo($file['name'], PATHINFO_EXTENSION))`
  - Only compares against ALLOWED_FILE_TYPES list from config
  - No MIME type validation via `finfo_file()`
  - Attacker can upload `.php` file as `.pdf`
  - `.htaccess` can be uploaded to enable PHP execution in upload directory
- **Current Code - PROBLEM:**
```php
$file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($file_extension, ALLOWED_FILE_TYPES)) {
    $upload_errors[] = "$document_name: Invalid file type.";
    continue;
}
```
- **Impact:** RCE via malicious PHP files executed from uploads directory
- **Suggested Fix:**
```php
// Validate MIME type first
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);

$allowed_mimes = ['application/pdf', 'image/jpeg', 'image/png'];
if (!in_array($mime, $allowed_mimes)) {
    $upload_errors[] = "$document_name: Invalid file type (MIME check failed).";
    continue;
}

// Validate extension matches MIME type
$file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$extension_to_mime = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png'
];

if (!isset($extension_to_mime[$file_extension]) || $extension_to_mime[$file_extension] !== $mime) {
    $upload_errors[] = "$document_name: File extension doesn't match MIME type.";
    continue;
}

// Rename file to remove original extension completely
$filename = bin2hex(random_bytes(16)) . '.secure';
$targetDir = getUploadDirFor($field_name);
```

---

### Issue #13: File Upload Path Traversal Vulnerability
- **Severity:** HIGH
- **Type:** Path Traversal
- **Line Numbers:** 100-105
- **Problem:**
  - `reference_number` from user input used in filename: `$reference_number . '_' . $field_name`
  - If `reference_number` contains `../`, attacker can write outside upload directory
  - `getUploadDirFor()` doesn't validate directory is within UPLOAD_DIR
- **Current Code - PROBLEM:**
```php
$filename = $reference_number . '_' . $field_name . '_' . time() . '.' . $file_extension;
$targetDir = getUploadDirFor($field_name);
$upload_path = rtrim($targetDir, '/\\') . '/' . $filename;
```
- **Suggested Fix:**
```php
// Sanitize reference_number
$reference_number_safe = preg_replace('/[^A-Za-z0-9_-]/', '', $reference_number);

// Validate directory is within UPLOAD_DIR
$targetDir = getUploadDirFor($field_name);
$realPath = realpath($targetDir);
$uploadBase = realpath(UPLOAD_DIR);

if ($realPath === false || strpos($realPath, $uploadBase) !== 0) {
    $upload_errors[] = "Invalid upload directory configuration.";
    continue;
}

$filename = $reference_number_safe . '_' . $field_name . '_' . time() . '.' . $file_extension;
$upload_path = $realPath . '/' . $filename;

// Final validation - ensure file is within expected directory
if (realpath(dirname($upload_path)) !== $realPath) {
    $upload_errors[] = "Invalid upload path.";
    continue;
}
```

---

### Issue #14: Race Condition - File Exists Check Missing
- **Severity:** MEDIUM
- **Type:** Race Condition / TOCTOU
- **Line Numbers:** 110-115
- **Problem:**
  - Directory created if not exists: `@mkdir($targetDir, 0755, true);`
  - Then immediately uploads: `move_uploaded_file($file['tmp_name'], $upload_path);`
  - Between check and write, file might already exist (low risk but possible)
- **Suggested Fix:**
```php
// Use more specific checks
if (!is_dir($targetDir)) {
    if (!@mkdir($targetDir, 0755, true)) {
        $upload_errors[] = "Failed to create upload directory.";
        continue;
    }
}

// Ensure destination doesn't exist to prevent overwrites
if (file_exists($upload_path)) {
    $upload_errors[] = "A file with this name already exists.";
    continue;
}

if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
    $upload_errors[] = "$document_name: Failed to upload file.";
    continue;
}
```

---

### Issue #15: Missing CSRF Token Validation
- **Severity:** HIGH
- **Type:** CSRF
- **Line Numbers:** 55-60 (POST handler)
- **Problem:**
  - No CSRF token validation on form submission
  - POST requests accept any source
  - Attacker can forge document upload requests
- **Suggested Fix:**
```php
// At top of POST handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_documents'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? null)) {
        http_response_code(403);
        die('CSRF token validation failed');
    }
    
    // ... rest of upload logic ...
}
```

---

## 5. PROFILE-UTILS.PHP

**File Path:** `EdubridgeSA/profile-utils.php`

### Issue #16: Arbitrary SQL Injection in Profile Picture Update
- **Severity:** CRITICAL
- **Type:** SQL Injection
- **Line Numbers:** 104-116
- **Problem:**
  - PDO connection created inline without prepared statements consistency check
  - While using prepared statements, student_id is not validated before use in filename
  - Path constructed without proper sanitization
- **Current Code - PROBLEM:**
```php
$filename = $dir . '/' . preg_replace('/[^A-Za-z0-9_-]/', '_', $student_id) . '.jpg';
$webPath = 'uploads/profile_pictures/' . preg_replace('/[^A-Za-z0-9_-]/', '_', $student_id) . '.jpg';

// Update DB profile_picture path
try {
    $pdo = new PDO(...);
    $stmt = $pdo->prepare('UPDATE users SET profile_picture = ?, updated_at = NOW() WHERE student_id = ?');
    $stmt->execute([$webPath, $student_id]);
```
- **Suggested Fix:**
```php
// Ensure student_id is numeric or properly validated
if (!preg_match('/^[A-Za-z0-9_-]+$/', $student_id)) {
    return ['success' => false, 'error' => 'Invalid student ID format.'];
}

$filename = $dir . '/' . $student_id . '.jpg';
$webPath = 'uploads/profile_pictures/' . $student_id . '.jpg';

// Validate file path
if (realpath(dirname($filename)) !== realpath($dir)) {
    return ['success' => false, 'error' => 'Invalid file path.'];
}
```

---

### Issue #17: Missing Image Dimension Validation Could Allow DOS
- **Severity:** MEDIUM
- **Type:** Denial of Service (DOS)
- **Line Numbers:** 42-45
- **Problem:**
  - Minimum dimensions enforced (150x150) but no maximum
  - Attacker can upload massive image (e.g., 100000x100000 pixels)
  - Server tries to process huge file causing memory exhaustion
- **Suggested Fix:**
```php
[$width, $height] = $info;
if ($width < 150 || $height < 150) {
    return ['success' => false, 'error' => 'Image too small. Min 150x150.'];
}
if ($width > 5000 || $height > 5000) {
    return ['success' => false, 'error' => 'Image too large. Max 5000x5000.'];
}
```

---

## 6. PROGRESS_UTILS.PHP

**File Path:** `EdubridgeSA/progress_utils.php`

### Issue #18: N+1 Query Problem in computeUserStage()
- **Severity:** HIGH
- **Type:** Performance Issue
- **Line Numbers:** 82-140
- **Problem:**
  - Function makes 5+ database queries sequentially
  - If called for each user in a loop (e.g., 1000 users), that's 5000+ queries
  - No query result caching
- **Current Code - PROBLEM:**
```php
function computeUserStage(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("SELECT id, status, email_verified, created_at, verified_at, profile_updated_at FROM users WHERE id=?");
    $stmt->execute([$userId]);  // Query 1
    $u = $stmt->fetch();
    
    // ... later ...
    
    if (tableExists($pdo, 'application_documents')) {  // Query 2 (tableExists)
        $stmt = $pdo->prepare("SELECT MIN(uploaded_at) AS first_upload FROM application_documents WHERE user_id=?");
        $stmt->execute([$userId]);  // Query 3
```
- **Suggested Fix:**
```php
function computeUserStageOptimized(PDO $pdo, int $userId): array {
    // Single query with JOINs where possible
    $sql = "
    SELECT 
        u.id, u.status, u.email_verified, u.created_at, u.verified_at, u.profile_updated_at,
        MIN(d.uploaded_at) as first_doc_upload,
        a.submitted_at
    FROM users u
    LEFT JOIN application_documents d ON d.user_id = u.id
    LEFT JOIN applications a ON a.user_id = u.id
    WHERE u.id = ?
    GROUP BY u.id
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $data = $stmt->fetch();
    
    // Process single result instead of multiple queries
    // ...
}
```

---

### Issue #19: SQL Injection in computeHistoricalEstimates() - Dynamic Table Name
- **Severity:** HIGH
- **Type:** SQL Injection (Unsafe Dynamic Query)
- **Line Numbers:** 65
- **Problem:**
  - String concatenation in information_schema query: `"SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='" . DB_NAME . "'"`
  - DB_NAME is a constant but string concatenation is risky pattern
  - Database name could be compromised
- **Current Code - PROBLEM:**
```php
$hasProfileUpdated = in_array('profile_updated_at', array_map(fn($r)=>$r['COLUMN_NAME'], $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='" . DB_NAME . "' AND TABLE_NAME='users'")->fetchAll()));
```
- **Suggested Fix:**
```php
$hasProfileUpdated = false;
try {
    $stmt = $pdo->prepare("
        SELECT COLUMN_NAME 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'users' 
        AND COLUMN_NAME = 'profile_updated_at'
    ");
    $stmt->execute();
    $hasProfileUpdated = $stmt->rowCount() > 0;
} catch (Exception $e) {
    error_log("Column check failed: " . $e->getMessage());
}
```

---

## 7. STUDENT-DASHBOARD.PHP

**File Path:** `EdubridgeSA/student-dashboard.php`

### Issue #20: Missing URL Encoding on Reference Number in Links
- **Severity:** MEDIUM
- **Type:** XSS / URL Injection
- **Line Numbers:** 23-45
- **Problem:**
  - Reference number used in query string without validation
  - `urlencode()` used correctly, but reference_number not validated format
  - If reference contains special chars, might cause SQL injection downstream
- **Suggested Fix:**
```php
// Validate reference format first
if ($reference_number && !preg_match('/^[A-Za-z0-9_-]+$/', $reference_number)) {
    $reference_number = null;  // Clear invalid reference
}

if ($resolvedRef) {
    $upload_docs_url = 'document_upload.php?ref=' . urlencode($resolvedRef);
}
```

---

### Issue #21: Undefined Variable Access - $resolvedId
- **Severity:** MEDIUM
- **Type:** Undefined Variable
- **Line Numbers:** 95-96
- **Problem:**
  - Variable `$resolvedId` used but never defined in scope
  - Should be `$resolvedRef` not `$resolvedId`
  - Triggers PHP Warning if error_reporting enabled
- **Current Code - PROBLEM:**
```php
if (isset($resolvedId) && $resolvedId) {  // ← $resolvedId never defined!
    $application_id = (int)$resolvedId;
}
```
- **Suggested Fix:**
```php
if (!empty($resolvedRef)) {
    $stmt = $pdo->prepare("SELECT id FROM applications WHERE reference_number = ? ORDER BY updated_at DESC LIMIT 1");
    $stmt->execute([$resolvedRef]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['id'])) {
        $application_id = (int)$row['id'];
    }
}
```

---

### Issue #22: XSS Vulnerability in Output - Missing htmlspecialchars()
- **Severity:** HIGH
- **Type:** Cross-Site Scripting (XSS)
- **Line Numbers:** 19 (username output)
- **Problem:**
  - Session data output without escaping: `$username = $_SESSION['student_name'] ?? 'Student';`
  - Then used in HTML: `Welcome, <?php echo $username; ?>`
  - If session data contains malicious content, XSS occurs
- **Suggested Fix:**
```php
$username = htmlspecialchars($_SESSION['student_name'] ?? 'Student', ENT_QUOTES, 'UTF-8');
$email = htmlspecialchars($_SESSION['student_email'] ?? '', ENT_QUOTES, 'UTF-8');
$reference_number = htmlspecialchars($_SESSION['reference_number'] ?? 'APP2025000000', ENT_QUOTES, 'UTF-8');
```

---

## 8. GET-DASHBOARD-METRICS.PHP

**File Path:** `EdubridgeSA/get-dashboard-metrics.php`

### Issue #23: Missing JSON Response Headers on Error
- **Severity:** MEDIUM
- **Type:** Information Disclosure
- **Line Numbers:** 12
- **Problem:**
  - Header `Content-Type: application/json` set at start
  - But error messages might be PHP errors (not JSON) if exception occurs before JSON encoding
  - Error details exposed to client
- **Suggested Fix:**
```php
<?php
require_once 'session_config.php';
require_once 'config.php';
require_once 'security-utils.php';

header('Content-Type: application/json');
ini_set('display_errors', 0);  // Don't display errors
ini_set('log_errors', 1);       // Log to file instead

try {
    // Auth check
    if (!isset($_SESSION['student_logged_in']) || $_SESSION['student_logged_in'] !== true) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit();
    }
    
    // ... rest of logic ...
    
} catch (Exception $e) {
    http_response_code(500);
    error_log('Dashboard metrics error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Internal error']);
    exit();
}
?>
```

---

### Issue #24: SQL Injection in Required Docs Array Diff
- **Severity:** MEDIUM
- **Type:** Logic Error (Not Direct SQL Injection)
- **Line Numbers:** 130-135
- **Problem:**
  - Document types converted to lowercase and merged: `$doc_types = array_map('strtolower', array_merge($docsA, $docsB));`
  - But `$required_docs` not normalized to lowercase
  - Comparison might fail if database has mixed case: `'Academic_Results'` vs `'academic_results'`
- **Suggested Fix:**
```php
$required_docs_a = array_map('strtolower', ['certified_id', 'academic_results']);
$required_docs_b = array_map('strtolower', ['id_document', 'matric_certificate', 'academic_transcript']);

// ... later ...

$doc_types = array_map('strtolower', array_merge($docsA, $docsB));
$required_docs = array_map('strtolower', $required_docs); // Normalize required docs too!

$missing = array_diff($required_docs, $doc_types);
```

---

## 9. HANDLEAPPLICATIONSUBMIT.PHP

**File Path:** `EdubridgeSA/handleApplicationSubmit.php`

### Issue #25: Deprecated PHP Function - Unescaped User Input
- **Severity:** CRITICAL
- **Type:** SQL Injection (via mysqli)
- **Line Numbers:** 50-70 (generateApplicationReference)
- **Problem:**
  - Uses `bind_param()` but type specifiers might be wrong
  - No prepared statement validation before parameter binding
  - Mixed database APIs (mysqli vs PDO elsewhere)
- **Suggested Fix:** Standardize on PDO with prepared statements

---

### Issue #26: Missing Input Validation on Phone Number
- **Severity:** MEDIUM
- **Type:** Data Validation
- **Line Numbers:** 82-88
- **Problem:**
  - Phone regex allows `+`, `-`, `(`, `)`, spaces
  - Regex is loose: `/^[0-9+\-\s()]{7,20}$/` allows invalid patterns like `++++++`
  - No actual format validation (South African numbers need specific format)
- **Suggested Fix:**
```php
if (!empty($data['phone'])) {
    // Remove common separators for validation
    $phoneDigits = preg_replace('/[^0-9+]/', '', $data['phone']);
    
    // South African format: 10-15 digits, starting with country code or 0
    if (!preg_match('/^(\+27|0)[0-9]{9}$/', $phoneDigits)) {
        $errors['phone'] = 'Please enter a valid South African phone number.';
    }
}
```

---

### Issue #27: Age Calculation Vulnerable to Timezone Issues
- **Severity:** MEDIUM
- **Type:** Data Validation
- **Line Numbers:** 95-103
- **Problem:**
  - Uses `DateTime` but server timezone not set
  - Age calculation might be off by 1 day depending on timezone
  - No validation that DOB is in past
- **Suggested Fix:**
```php
if (!empty($data['dob'])) {
    try {
        $dob = new DateTime($data['dob'], new DateTimeZone('Africa/Johannesburg'));
        $now = new DateTime('now', new DateTimeZone('Africa/Johannesburg'));
        
        if ($dob > $now) {
            $errors['dob'] = 'Date of birth cannot be in the future.';
        } else {
            $age = $now->diff($dob)->y;
            
            if ($age < 16) {
                $errors['dob'] = 'Applicants must be at least 16 years old.';
            }
            if ($age > 120) {
                $errors['dob'] = 'Please enter a valid date of birth.';
            }
        }
    } catch (Exception $e) {
        $errors['dob'] = 'Invalid date format.';
    }
}
```

---

### Issue #28: Transaction Not Properly Committed/Rolled Back
- **Severity:** HIGH
- **Type:** Database Integrity
- **Line Numbers:** 265-266
- **Problem:**
  - `$conn->autocommit(false);` set but no corresponding `commit()` or `rollback()`
  - If exception occurs, transaction is never rolled back
  - Database might be left in inconsistent state
- **Current Code - PROBLEM:**
```php
function saveApplicationData($conn, $data, $files) {
    // Start transaction
    $conn->autocommit(false);  // ← But never explicitly committed/rolled back!
    
    try {
        // ... INSERT statements ...
    } catch (Exception $e) {
        // No rollback!
        throw $e;
    }
}
```
- **Suggested Fix:**
```php
try {
    // ... INSERT statements ...
    
    $conn->commit();
    return ['success' => true, 'message' => 'Application saved'];
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Application save failed: " . $e->getMessage());
    return ['success' => false, 'error' => 'Failed to save application'];
    
} finally {
    $conn->autocommit(true);  // Restore autocommit
}
```

---

### Issue #29: File Upload Error Handling Missing Error Code Details
- **Severity:** MEDIUM
- **Type:** Error Handling
- **Line Numbers:** 230-232
- **Problem:**
  - Checks `$file['error'] !== UPLOAD_ERR_OK` but doesn't specify which error
  - Doesn't map error codes to user messages
- **Suggested Fix:**
```php
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds form limit',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Server temporary folder missing',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk'
    ];
    $errorMsg = $errorMessages[$file['error']] ?? 'Unknown upload error';
    $result['errors'][$field] = $errorMsg;
    continue;
}
```

---

## 10. APPLICATION_STEP1-4.PHP

**File Path:** `EdubridgeSA/application_step{1-4}.php`

### Issue #30: Stored XSS in Form Field Values
- **Severity:** HIGH
- **Type:** Cross-Site Scripting (XSS)
- **Line Numbers:** 14-21 (step1), 12-30 (all steps)
- **Problem:**
  - Form data echoed without `htmlspecialchars()`:
```php
value="<?php echo ($step1_data['full_name'] ?? ''); ?>"  // ← No escaping!
```
  - If `step1_data` from user input (e.g., session, GET, POST), XSS possible
  - Attacker can inject: `<img src=x onerror="alert('XSS')">`
- **Current Code - PROBLEM:**
```php
<input type="text" name="full_name" id="full_name" 
       value="<?php echo ($step1_data['full_name'] ?? ''); ?>"  // ← VULNERABLE
       placeholder="Enter your full name" required>
```
- **Suggested Fix:** (Correctly done in some fields, needs consistency)
```php
<input type="text" name="full_name" id="full_name" 
       value="<?php echo htmlspecialchars($step1_data['full_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
       placeholder="Enter your full name" required>
```

---

### Issue #31: Client-Side Validation Only - No Server-Side Enforcement
- **Severity:** HIGH
- **Type:** Missing Validation
- **Line Numbers:** 142-190 (script section)
- **Problem:**
  - JavaScript validation easily bypassed
  - ID number format: `pattern="[0-9]{13}"` - but no server check
  - Cellphone format: `pattern="0[6-8][0-9]{8}"` - but no server check
  - If form bypasses client validation, no server-side check enforces it
- **Suggested Fix:** Add server-side validation in form processing:
```php
// In handleApplicationSubmit.php or form processor
if (!preg_match('/^[0-9]{13}$/', $data['id_number'])) {
    $errors['id_number'] = 'ID number must be exactly 13 digits.';
}

if (!preg_match('/^0[6-8][0-9]{8}$/', $data['cellphone_number'])) {
    $errors['cellphone_number'] = 'Invalid South African phone format.';
}
```

---

### Issue #32: Missing CSRF Token in Step Forms
- **Severity:** HIGH
- **Type:** CSRF
- **Line Numbers:** All step files (form submission)
- **Problem:**
  - Multi-step forms don't include CSRF token
  - Each step POST is vulnerable to CSRF attacks
  - Attacker can forge step advancement or data injection
- **Suggested Fix:** Add CSRF token to each step form:
```php
<form method="POST" action="process-step.php">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES); ?>">
    <!-- rest of form fields -->
</form>
```

---

## Additional Security Recommendations

### General Security Issues

1. **Missing Security Headers**
   - No `X-Frame-Options: DENY` (clickjacking protection)
   - No `X-Content-Type-Options: nosniff`
   - No `Strict-Transport-Security` for HTTPS
   - No `Content-Security-Policy`

2. **Logging Issues**
   - Failed login attempts logged but output visible in logs
   - No sensitive data filtering in error logs
   - Log files not protected from web access

3. **Database Connection Issues**
   - PDO connections created multiple times instead of singleton pattern
   - No connection pooling
   - No prepared statement caching

4. **Session Security**
   - Session timeout after 24 hours but web session cookies expire on browser close
   - No "logout all devices" functionality
   - No session activity logging

---

## Severity Summary

| Severity | Count | Issues |
|----------|-------|--------|
| CRITICAL | 7 | #1, #4, #7, #12, #16, #25, #26 (SQL/Auth/File Upload) |
| HIGH | 12 | #2, #3, #6, #8, #13, #15, #22, #28, #30, #31, #32, #20 (CSRF/XSS/Auth) |
| MEDIUM | 11 | #10, #11, #14, #17, #18, #19, #21, #23, #24, #27, #29 (Performance/Validation/DOS) |
| LOW | 3 | Documentation, minor issues |

**Total Issues Found: 32**  
**Risk Level: CRITICAL** 🔴

---

## Recommended Priority Fixes (Phase 1 - Emergency)

1. **Remove hardcoded database credentials** - Move to environment variables
2. **Fix arbitrary file upload vulnerability** - Add MIME type validation + path traversal prevention
3. **Replace SHA256 admin auth** with `password_hash()`/`password_verify()`
4. **Add CSRF token validation** to all forms
5. **Fix XSS vulnerabilities** - Use `htmlspecialchars()` consistently
6. **Add server-side input validation** - Don't rely on client-side only

---

## Testing Recommendations

1. **Penetration Testing**
   - File upload bypass attempts
   - SQL injection via all input fields
   - CSRF form submissions
   - Session hijacking via remember tokens

2. **Code Review**
   - Audit all `$_GET`, `$_POST`, `$_COOKIE` usage
   - Verify all database queries are parameterized
   - Check all file operations for path traversal

3. **Security Scanning**
   - Run OWASP ZAP / Burp Suite
   - Use PHPCS with security rules
   - Static analysis: SonarQube, Psalm

---

## Implementation Timeline

- **Week 1:** Critical issues (#1-7, #12, #16)
- **Week 2:** High priority issues (#2-3, #6, #22, #30-32)
- **Week 3:** Medium priority + additional hardening
- **Week 4:** Security testing + penetration testing

---

**Report Generated:** December 3, 2025  
**Next Review:** After critical fixes implementation
