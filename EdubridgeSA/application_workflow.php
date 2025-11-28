<?php
/**
 * Application Workflow Test
 * Tests the complete application submission process
 */

require_once 'config.php';
require_once 'session_config.php';
require_once 'security-utils.php';

echo "<h1>Application Workflow Test</h1>";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; }
.success { color: green; font-weight: bold; }
.error { color: red; font-weight: bold; }
.warning { color: orange; font-weight: bold; }
.info { color: blue; font-weight: bold; }
.section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
.section h2 { margin-top: 0; color: #333; }
pre { background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto; }
table { border-collapse: collapse; width: 100%; margin: 10px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
</style>";

// Test 1: Database Connection and Tables
echo "<div class='section'>";
echo "<h2>1. Database Connection Test</h2>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM applications");
    $result = $stmt->fetch();
    echo "<span class='success'>✓ Database connection working</span><br>";
    echo "Current applications in database: " . $result['count'] . "<br>";
    
    // Check table structure
    $stmt = $pdo->query("DESCRIBE applications");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<strong>Applications table structure:</strong><br>";
    echo "<table>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>{$column['Field']}</td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Key']}</td>";
        echo "<td>{$column['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<span class='error'>✗ Database error: " . $e->getMessage() . "</span><br>";
}
echo "</div>";

// Test 2: Session and CSRF Token
echo "<div class='section'>";
echo "<h2>2. Session and CSRF Token Test</h2>";
echo "Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? '<span class="success">ACTIVE</span>' : '<span class="error">INACTIVE</span>') . "<br>";
echo "Session ID: " . session_id() . "<br>";

if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}

echo "CSRF Token: " . (isset($_SESSION[CSRF_TOKEN_NAME]) ? '<span class="success">PRESENT</span>' : '<span class="error">MISSING</span>') . "<br>";
if (isset($_SESSION[CSRF_TOKEN_NAME])) {
    echo "Token Preview: " . substr($_SESSION[CSRF_TOKEN_NAME], 0, 20) . "...<br>";
}
echo "</div>";

// Test 3: SecurityUtils Class
echo "<div class='section'>";
echo "<h2>3. SecurityUtils Class Test</h2>";
if (class_exists('SecurityUtils')) {
    echo "<span class='success'>✓ SecurityUtils class loaded</span><br>";
    
    $methods = ['validateUploadedFile', 'validateEmail', 'sanitizeInput', 'getClientIP', 'generateCSRFToken', 'validateCSRFToken'];
    foreach ($methods as $method) {
        if (method_exists('SecurityUtils', $method)) {
            echo "<span class='success'>✓ Method $method exists</span><br>";
        } else {
            echo "<span class='error'>✗ Method $method missing</span><br>";
        }
    }
    
    // Test getClientIP
    try {
        $ip = SecurityUtils::getClientIP();
        echo "Client IP: <span class='info'>$ip</span><br>";
    } catch (Exception $e) {
        echo "<span class='error'>✗ getClientIP error: " . $e->getMessage() . "</span><br>";
    }
    
} else {
    echo "<span class='error'>✗ SecurityUtils class not found</span><br>";
}
echo "</div>";

// Test 4: File Upload Configuration
echo "<div class='section'>";
echo "<h2>4. File Upload Configuration</h2>";
echo "Upload Max Filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "Post Max Size: " . ini_get('post_max_size') . "<br>";
echo "Max File Uploads: " . ini_get('max_file_uploads') . "<br>";

if (defined('UPLOAD_DIR')) {
    echo "Upload Directory: " . UPLOAD_DIR . "<br>";
    if (is_dir(UPLOAD_DIR)) {
        echo "<span class='success'>✓ Upload directory exists</span><br>";
        if (is_writable(UPLOAD_DIR)) {
            echo "<span class='success'>✓ Upload directory is writable</span><br>";
        } else {
            echo "<span class='error'>✗ Upload directory is not writable</span><br>";
        }
    } else {
        echo "<span class='error'>✗ Upload directory does not exist</span><br>";
    }
} else {
    echo "<span class='warning'>⚠ UPLOAD_DIR not defined</span><br>";
}
echo "</div>";

// Test 5: Email Configuration
echo "<div class='section'>";
echo "<h2>5. Email Configuration Test</h2>";
if (defined('SMTP_HOST')) {
    echo "<span class='success'>✓ SMTP configuration found</span><br>";
    echo "SMTP Host: " . SMTP_HOST . "<br>";
    echo "SMTP Port: " . SMTP_PORT . "<br>";
    echo "From Email: " . FROM_EMAIL . "<br>";
    
    // Check PHPMailer
    if (file_exists('PHPMailer/src/PHPMailer.php')) {
        echo "<span class='success'>✓ PHPMailer library found</span><br>";
        
        // Test PHPMailer loading
        try {
            require_once 'PHPMailer/src/PHPMailer.php';
            require_once 'PHPMailer/src/SMTP.php';
            require_once 'PHPMailer/src/Exception.php';
            echo "<span class='success'>✓ PHPMailer classes loaded successfully</span><br>";
        } catch (Exception $e) {
            echo "<span class='error'>✗ PHPMailer loading error: " . $e->getMessage() . "</span><br>";
        }
    } else {
        echo "<span class='error'>✗ PHPMailer library not found</span><br>";
    }
} else {
    echo "<span class='error'>✗ SMTP configuration missing</span><br>";
}
echo "</div>";

// Test 6: Application Form Files
echo "<div class='section'>";
echo "<h2>6. Application Form Files Test</h2>";
$form_files = [
    'apply_test.php' => 'Main application form',
    'handleApplicationSubmit.php' => 'Form submission handler',
    'student-login.php' => 'Student login page',
    'student-dashboard.php' => 'Student dashboard',
    'find-application.php' => 'Application finder'
];

foreach ($form_files as $file => $description) {
    if (file_exists($file)) {
        echo "<span class='success'>✓ $file</span> - $description<br>";
        
        // Check for CSRF token in form files
        if (in_array($file, ['apply_test.php', 'handleApplicationSubmit.php'])) {
            $content = file_get_contents($file);
            if (strpos($content, 'CSRF_TOKEN_NAME') !== false) {
                echo "  <span class='success'>✓ CSRF token implementation found</span><br>";
            } else {
                echo "  <span class='warning'>⚠ CSRF token implementation not found</span><br>";
            }
        }
    } else {
        echo "<span class='error'>✗ $file</span> - $description (MISSING)<br>";
    }
}
echo "</div>";

// Test 7: Simulate Application Submission
echo "<div class='section'>";
echo "<h2>7. Application Submission Simulation</h2>";

// Create test data
$test_data = [
    'full_name' => 'Test Student',
    'surname' => 'Workflow',
    'email_address' => 'test.workflow@example.com',
    'id_number' => '9901010001088',
    'cellphone_number' => '0123456789',
    'date_of_birth' => '1999-01-01',
    'gender' => 'Male',
    'physical_address' => '123 Test Street, Test City',
    'postal_code' => '1234',
    'country_of_residence' => 'South Africa',
    CSRF_TOKEN_NAME => $_SESSION[CSRF_TOKEN_NAME]
];

echo "<strong>Test Data:</strong><br>";
echo "<pre>";
foreach ($test_data as $key => $value) {
    if ($key === CSRF_TOKEN_NAME) {
        echo "$key: " . substr($value, 0, 20) . "...\n";
    } else {
        echo "$key: $value\n";
    }
}
echo "</pre>";

// Validate required fields
$required_fields = ['full_name', 'surname', 'email_address', 'id_number'];
$validation_passed = true;

foreach ($required_fields as $field) {
    if (empty($test_data[$field])) {
        echo "<span class='error'>✗ Required field '$field' is empty</span><br>";
        $validation_passed = false;
    } else {
        echo "<span class='success'>✓ Required field '$field' has value</span><br>";
    }
}

// Test email validation
if (isset($test_data['email_address']) && class_exists('SecurityUtils') && method_exists('SecurityUtils', 'validateEmail')) {
    if (SecurityUtils::validateEmail($test_data['email_address'])) {
        echo "<span class='success'>✓ Email validation passed</span><br>";
    } else {
        echo "<span class='error'>✗ Email validation failed</span><br>";
    }
}

// Test CSRF validation
if (isset($test_data[CSRF_TOKEN_NAME]) && isset($_SESSION[CSRF_TOKEN_NAME])) {
    if ($test_data[CSRF_TOKEN_NAME] === $_SESSION[CSRF_TOKEN_NAME]) {
        echo "<span class='success'>✓ CSRF token validation would pass</span><br>";
    } else {
        echo "<span class='error'>✗ CSRF token validation would fail</span><br>";
    }
} else {
    echo "<span class='error'>✗ CSRF token missing for validation</span><br>";
}

echo "</div>";

// Test 8: Error Log Analysis
echo "<div class='section'>";
echo "<h2>8. Recent Error Analysis</h2>";
if (file_exists('error.log')) {
    $lines = file('error.log');
    $recent_lines = array_slice($lines, -5);
    
    echo "<strong>Last 5 error log entries:</strong><br>";
    echo "<pre>";
    foreach ($recent_lines as $line) {
        echo htmlspecialchars($line);
    }
    echo "</pre>";
} else {
    echo "<span class='info'>No error.log file found</span><br>";
}
echo "</div>";

// Summary
echo "<div class='section'>";
echo "<h2>Workflow Test Summary</h2>";
echo "<p>This test verifies all components needed for the application submission workflow.</p>";
echo "<p><strong>Key Points:</strong></p>";
echo "<ul>";
echo "<li>Database connection and table structure</li>";
echo "<li>Session management and CSRF protection</li>";
echo "<li>Security utilities and validation</li>";
echo "<li>File upload configuration</li>";
echo "<li>Email system setup</li>";
echo "<li>Form files and handlers</li>";
echo "</ul>";
echo "<p>Review any red (✗) items above for issues that need attention.</p>";
echo "</div>";
?>