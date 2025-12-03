# 🔥 CRITICAL FIXES - IMPLEMENTATION GUIDE

This guide provides step-by-step implementation for the 7 CRITICAL security issues.

---

## CRITICAL FIX #1: Move Database Credentials to .env

### File: `config.php` - BEFORE (VULNERABLE)
```php
<?php
// EXPOSED CREDENTIALS IN SOURCE CODE!
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'your_password');
define('DB_NAME', 'edubridge_sa');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS
    );
} catch (PDOException $e) {
    die('Database connection failed');
}
?>
```

### File: `.env` - CREATE NEW FILE (SECURE)
```env
DB_HOST=localhost
DB_USER=root
DB_PASS=your_password
DB_NAME=edubridge_sa
APP_ENV=production
APP_DEBUG=false
```

### File: `config.php` - AFTER (SECURE)
```php
<?php
// Load environment variables
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

// Use environment variables
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_USER', $_ENV['DB_USER']);
define('DB_PASS', $_ENV['DB_PASS']);
define('DB_NAME', $_ENV['DB_NAME']);

if (!DB_USER || !DB_PASS) {
    error_log('Database credentials not configured');
    die('Configuration error - contact administrator');
}

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
    error_log('Database connection error: ' . $e->getMessage());
    die('Database connection failed - contact support');
}
?>
```

### Installation:
```bash
cd EdubridgeSA
composer require vlucas/phpdotenv
```

### .gitignore Update:
```
.env
.env.local
.env.*.local
```

---

## CRITICAL FIX #2: Weak Password Hashing → Use password_hash()

### File: `auth.php` - BEFORE (VULNERABLE)
```php
<?php
// VULNERABLE - SHA256 is not for passwords!
$password_input = $_POST['password'];
$stored_password = sha1($password_input); // BAD!

if (sha1($password_input) === $stored_password) {
    $_SESSION['logged_in'] = true;
}
?>
```

### File: `auth.php` - AFTER (SECURE)

**On User Registration/Password Change:**
```php
<?php
session_start();
require_once 'config.php';

$email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
$password = $_POST['password'];

if (!$email) {
    throw new Exception("Invalid email");
}

if (strlen($password) < 12) {
    throw new Exception("Password must be at least 12 characters");
}

// Hash with bcrypt (cost 12 = strong)
$password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

$stmt = $pdo->prepare("INSERT INTO users (email, password_hash) VALUES (?, ?)");
$stmt->execute([$email, $password_hash]);
?>
```

**On User Login:**
```php
<?php
session_start();
require_once 'config.php';

$email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
$password = $_POST['password'];

if (!$email) {
    http_response_code(401);
    die(json_encode(['error' => 'Invalid credentials']));
}

$stmt = $pdo->prepare("SELECT id, email, password_hash FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    // IMPORTANT: Don't reveal if user exists
    http_response_code(401);
    die(json_encode(['error' => 'Invalid credentials']));
}

// SECURE: Use password_verify()
if (password_verify($password, $user['password_hash'])) {
    // Check if password needs rehashing (cost has increased)
    if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT, ['cost' => 12])) {
        $new_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $update_stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $update_stmt->execute([$new_hash, $user['id']]);
    }
    
    // Regenerate session ID to prevent fixation
    session_regenerate_id(true);
    
    $_SESSION['student_logged_in'] = true;
    $_SESSION['student_id'] = $user['id'];
    $_SESSION['student_email'] = $user['email'];
    
    header('Location: student-dashboard.php');
    exit();
} else {
    // IMPORTANT: Generic error message
    http_response_code(401);
    die(json_encode(['error' => 'Invalid credentials']));
}
?>
```

---

## CRITICAL FIX #3: Unsafe File Upload → Add MIME Type Validation

### File: `document_upload.php` - BEFORE (VULNERABLE)
```php
<?php
session_start();
$upload_dir = __DIR__ . '/uploads/';

// VULNERABLE - No validation, keeps original filename
$filename = $_FILES['document']['name'];
$target_file = $upload_dir . $filename;

if (move_uploaded_file($_FILES['document']['tmp_name'], $target_file)) {
    echo "File uploaded successfully";
} else {
    echo "Upload failed";
}
?>
```

### File: `document_upload.php` - AFTER (SECURE)
```php
<?php
session_start();
require_once 'config.php';

// Session check
if (!isset($_SESSION['student_logged_in']) || $_SESSION['student_logged_in'] !== true) {
    http_response_code(403);
    die(json_encode(['error' => 'Unauthorized']));
}

// CSRF check
if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    die(json_encode(['error' => 'CSRF token validation failed']));
}

try {
    // File validation
    if (!isset($_FILES['document'])) {
        throw new Exception("No file provided");
    }
    
    $file = $_FILES['document'];
    
    // Size limit (5MB)
    $max_size = 5 * 1024 * 1024;
    if ($file['size'] > $max_size) {
        throw new Exception("File too large. Maximum 5MB allowed.");
    }
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Upload error: " . $file['error']);
    }
    
    // MIME type validation - use fileinfo, NOT client-provided MIME
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    // Whitelist allowed MIME types
    $allowed_mimes = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];
    
    if (!in_array($mime_type, $allowed_mimes)) {
        throw new Exception("File type not allowed: " . $mime_type);
    }
    
    // Extension whitelist as additional check
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_ext = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
    
    if (!in_array($file_ext, $allowed_ext)) {
        throw new Exception("File extension not allowed");
    }
    
    // Create secure filename (random hash + extension)
    $new_filename = bin2hex(random_bytes(16)) . '.' . $file_ext;
    
    // Store in directory outside web root if possible
    $upload_dir = __DIR__ . '/../uploads_secure/';
    
    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Disable execute permissions on uploads
    $target_file = $upload_dir . $new_filename;
    
    if (!move_uploaded_file($file['tmp_name'], $target_file)) {
        throw new Exception("Failed to move uploaded file");
    }
    
    // Set file permissions (read-only for web server)
    chmod($target_file, 0644);
    
    // Store reference in database
    $stmt = $pdo->prepare("
        INSERT INTO application_documents 
        (application_id, document_type, file_path, original_filename, file_size, uploaded_at, student_id) 
        VALUES (?, ?, ?, ?, ?, NOW(), ?)
    ");
    
    $stmt->execute([
        $_POST['application_id'],
        $_POST['document_type'],
        $new_filename,
        $file['name'],  // Store original for reference
        $file['size'],
        $_SESSION['student_id']
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'File uploaded successfully',
        'document_id' => $pdo->lastInsertId()
    ]);
    
} catch (Exception $e) {
    error_log('File upload error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
```

**Apache .htaccess - Prevent Execution in Upload Dir:**
```apache
<Files *.php>
    Deny from all
</Files>

<FilesMatch "\.(?:php|phtml|php3|php4|php5|phar|exe)$">
    Deny from all
</FilesMatch>

# Disable script execution
php_flag engine off
```

---

## CRITICAL FIX #4: Add CSRF Token Protection

### All Forms - BEFORE (VULNERABLE)
```html
<form method="POST" action="handleApplicationSubmit.php">
    <!-- NO CSRF PROTECTION -->
    <input type="text" name="university" />
    <button type="submit">Submit</button>
</form>
```

### file: `student-dashboard.php` - AFTER (SECURE)
```php
<?php
session_start();
require_once 'config.php';

// Authentication check
if (!isset($_SESSION['student_logged_in']) || $_SESSION['student_logged_in'] !== true) {
    header('Location: student-login.php');
    exit();
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<form method="POST" action="handleApplicationSubmit.php">
    <!-- SECURE: CSRF Token -->
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
    
    <input type="text" name="university" />
    <button type="submit">Submit</button>
</form>
```

### File: `handleApplicationSubmit.php` - CSRF Validation
```php
<?php
session_start();
require_once 'config.php';

// STEP 1: Verify CSRF token
if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    die(json_encode(['error' => 'CSRF token validation failed']));
}

// STEP 2: Regenerate token after validation
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// STEP 3: Continue with form processing
// ... rest of application logic
?>
```

---

## CRITICAL FIX #5: XSS Protection - htmlspecialchars() Everywhere

### All Output - BEFORE (VULNERABLE)
```php
<?php
echo "Welcome " . $_SESSION['student_name'];
echo "<p>" . $_POST['comment'] . "</p>";
echo '<input value="' . $user['bio'] . '" />';
?>
```

### All Output - AFTER (SECURE)
```php
<?php
// EVERY output must be escaped with htmlspecialchars()

// Escape for HTML context
echo "Welcome " . htmlspecialchars($_SESSION['student_name'], ENT_QUOTES, 'UTF-8');

// Escape user input
echo "<p>" . htmlspecialchars($_POST['comment'], ENT_QUOTES, 'UTF-8') . "</p>";

// Escape for attribute context
echo '<input value="' . htmlspecialchars($user['bio'], ENT_QUOTES, 'UTF-8') . '" />';

// Escape for JSON
echo '<script>var data = ' . json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APO | JSON_HEX_QUOT) . ';</script>';

// For URL context
echo '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">Link</a>';
?>
```

### Create Helper Function:
```php
<?php
function safe_html($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function safe_attr($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function safe_json($data) {
    return json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APO | JSON_HEX_QUOT);
}

function safe_url($url) {
    return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
}

// Usage:
echo "Welcome " . safe_html($_SESSION['student_name']);
echo '<input value="' . safe_attr($user['bio']) . '" />';
?>
```

---

## CRITICAL FIX #6: All SQL Queries → Prepared Statements

### Pattern - BEFORE (VULNERABLE)
```php
<?php
// VULNERABLE - SQL Injection!
$user_id = $_GET['id'];
$query = "SELECT * FROM users WHERE id = " . $user_id;
$result = $pdo->query($query);

// VULNERABLE - String concatenation
$query = "SELECT * FROM users WHERE name = '" . $_POST['name'] . "'";
$result = $pdo->query($query);
?>
```

### Pattern - AFTER (SECURE)
```php
<?php
// SECURE - Prepared statement with placeholders
$user_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$result = $stmt->fetchAll();

// SECURE - Named placeholders (more readable)
$stmt = $pdo->prepare("SELECT * FROM users WHERE name = :name LIMIT 1");
$stmt->execute([':name' => $_POST['name']]);
$result = $stmt->fetch();

// SECURE - Multiple parameters
$stmt = $pdo->prepare("
    SELECT * FROM applications 
    WHERE student_id = ? 
    AND status = ? 
    AND created_at >= ?
");
$stmt->execute([$student_id, $status, $date]);
$results = $stmt->fetchAll();
?>
```

---

## CRITICAL FIX #7: Missing Session Authentication Check

### Add This to EVERY Protected PHP File

```php
<?php
/**
 * AUTHENTICATION GUARD
 * Add this to the top of every page that requires login
 */

session_start();

// Check if session is still valid
if (empty($_SESSION['student_logged_in']) || $_SESSION['student_logged_in'] !== true) {
    // Redirect to login
    header('Location: student-login.php');
    exit();
}

// Optional: Check session timeout
$inactive_time = 3600; // 1 hour
if (isset($_SESSION['last_activity'])) {
    if ((time() - $_SESSION['last_activity']) > $inactive_time) {
        // Session expired
        session_destroy();
        header('Location: student-login.php?expired=1');
        exit();
    }
}
$_SESSION['last_activity'] = time();

// Optional: Verify expected session variables exist
$required_session_vars = ['student_id', 'student_email', 'student_name'];
foreach ($required_session_vars as $var) {
    if (!isset($_SESSION[$var])) {
        session_destroy();
        header('Location: student-login.php');
        exit();
    }
}

// If we reach here, user is authenticated
?>
```

### Create a Helper File: `auth-required.php`
```php
<?php
/**
 * Include this file in every protected page
 * Usage: require_once 'auth-required.php';
 */

session_start();

if (empty($_SESSION['student_logged_in']) || $_SESSION['student_logged_in'] !== true) {
    header('Location: student-login.php');
    exit();
}

if (isset($_SESSION['last_activity'])) {
    if ((time() - $_SESSION['last_activity']) > 3600) {
        session_destroy();
        header('Location: student-login.php?expired=1');
        exit();
    }
}
$_SESSION['last_activity'] = time();
?>
```

Then use in every protected file:
```php
<?php
require_once 'auth-required.php';
require_once 'config.php';
// ... rest of file
?>
```

---

## 📋 Implementation Checklist

- [ ] Create `.env` file with database credentials
- [ ] Update `config.php` to read from `.env`
- [ ] Install vlucas/phpdotenv: `composer require vlucas/phpdotenv`
- [ ] Update `auth.php` with password_hash/password_verify
- [ ] Migrate existing users to new password hash (migration script)
- [ ] Update `document_upload.php` with MIME validation
- [ ] Add CSRF tokens to all forms
- [ ] Add htmlspecialchars() to all output
- [ ] Convert all SQL queries to prepared statements
- [ ] Add auth check to all protected pages
- [ ] Create `.htaccess` in uploads directory
- [ ] Test all forms and file uploads
- [ ] Run security audit again

---

## 🧪 Testing Commands

```bash
# Test password hashing
php -r "echo password_hash('test123', PASSWORD_BCRYPT, ['cost' => 12]);"

# Test htmlspecialchars
php -r "echo htmlspecialchars('<script>alert(1)</script>', ENT_QUOTES, 'UTF-8');"

# Test file MIME detection
php -r "echo finfo_file(finfo_open(FILEINFO_MIME_TYPE), 'file.pdf');"
```

---

**Implementation Priority:** Fix all 7 CRITICAL issues before ANY deployment
