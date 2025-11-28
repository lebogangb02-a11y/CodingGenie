<?php
/**
 * Comprehensive Email System Verification
 * Final test to verify all components are working correctly
 */

// Set execution time limit
set_time_limit(60);
ini_set('max_execution_time', 60);

// Load configuration and dependencies
require_once 'config.php';
require_once 'email_functions.php';
require_once 'security-utils.php';

echo "<h1>🔍 Comprehensive Email System Verification</h1>";
echo "<style>
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    .info { color: blue; }
    .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
    h2 { color: #333; border-bottom: 2px solid #007cba; padding-bottom: 5px; }
    h3 { color: #555; }
</style>";

$all_tests_passed = true;

// Test 1: Database Connection
echo "<div class='section'>";
echo "<h2>1. 📊 Database Connection Test</h2>";
try {
    // Test database connection
    $stmt = $pdo->query("SELECT 1");
    echo "<span class='success'>✓ Database connection successful</span><br>";
    
    // Check tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<span class='success'>✓ Found " . count($tables) . " tables</span><br>";
    
    // Check specific tables for email fields
    $email_tables = ['students', 'applications', 'admin_users'];
    foreach ($email_tables as $table) {
        try {
            $stmt = $pdo->query("DESCRIBE `$table`");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $email_fields = array_filter($columns, function($col) {
                return strpos(strtolower($col['Field']), 'email') !== false;
            });
            
            if ($email_fields) {
                echo "<span class='success'>✓ Table '$table' has email fields</span><br>";
            } else {
                echo "<span class='warning'>⚠ Table '$table' has no email fields</span><br>";
            }
        } catch (Exception $e) {
            echo "<span class='error'>✗ Cannot access table '$table': " . $e->getMessage() . "</span><br>";
            if ($table === 'students') {
                echo "<span class='info'>ℹ This may be normal if students table doesn't exist yet</span><br>";
            }
        }
    }
} catch (Exception $e) {
    echo "<span class='error'>✗ Database connection failed: " . $e->getMessage() . "</span><br>";
    $all_tests_passed = false;
}
echo "</div>";

// Test 2: Email Validation Fix
echo "<div class='section'>";
echo "<h2>2. ✉️ Email Validation Test (Fixed)</h2>";
if (class_exists('SecurityUtils') && method_exists('SecurityUtils', 'validateEmail')) {
    echo "<span class='success'>✓ SecurityUtils email validation available</span><br>";
    
    $test_emails = [
        'valid@example.com' => true,
        'test@edubridgesa.co.za' => true,
        'user.name+tag@domain.co.za' => true,
        'valid.email@subdomain.example.org' => true,
        'invalid.email' => false,
        '@invalid.com' => false,
        'test@' => false,
        'no-at-sign' => false
    ];
    
    $validation_passed = true;
    foreach ($test_emails as $email => $expected) {
        $result = SecurityUtils::validateEmail($email);
        $status = ($result === $expected) ? 'success' : 'error';
        $symbol = ($result === $expected) ? '✓' : '✗';
        
        if ($result !== $expected) {
            $validation_passed = false;
            $all_tests_passed = false;
        }
        
        echo "<span class='$status'>$symbol $email: " . ($result ? 'VALID' : 'INVALID') . " (Expected: " . ($expected ? 'VALID' : 'INVALID') . ")</span><br>";
    }
    
    if ($validation_passed) {
        echo "<span class='success'>✓ All email validation tests passed!</span><br>";
    }
} else {
    echo "<span class='error'>✗ Email validation function not available</span><br>";
    $all_tests_passed = false;
}
echo "</div>";

// Test 3: SMTP Connection
echo "<div class='section'>";
echo "<h2>3. 📧 SMTP Connection Test</h2>";
try {
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    // Server settings
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USERNAME;
    $mail->Password = SMTP_PASSWORD;
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = SMTP_PORT;
    $mail->Timeout = 15;
    $mail->SMTPDebug = 0;
    
    echo "<span class='info'>Testing SMTP connection to " . SMTP_HOST . ":" . SMTP_PORT . "...</span><br>";
    
    if ($mail->smtpConnect()) {
        echo "<span class='success'>✓ SMTP connection successful!</span><br>";
        $mail->smtpClose();
    } else {
        echo "<span class='error'>✗ SMTP connection failed</span><br>";
        $all_tests_passed = false;
    }
    
} catch (Exception $e) {
    echo "<span class='error'>✗ SMTP error: " . $e->getMessage() . "</span><br>";
    $all_tests_passed = false;
}
echo "</div>";

// Test 4: Email Functions
echo "<div class='section'>";
echo "<h2>4. 🔧 Email Functions Test</h2>";
$required_functions = [
    'sendVerificationEmail',
    'sendPasswordResetEmail', 
    'sendWelcomeEmail',
    'sendApplicationConfirmationEmail',
    'sendDocumentReminderEmail'
];

$functions_passed = true;
foreach ($required_functions as $func) {
    if (function_exists($func)) {
        echo "<span class='success'>✓ $func exists</span><br>";
    } else {
        echo "<span class='error'>✗ $func missing</span><br>";
        $functions_passed = false;
        $all_tests_passed = false;
    }
}

if ($functions_passed) {
    echo "<span class='success'>✓ All required email functions are available!</span><br>";
}
echo "</div>";

// Test 5: Configuration Check
echo "<div class='section'>";
echo "<h2>5. ⚙️ Configuration Check</h2>";
$config_items = [
    'SMTP_HOST' => SMTP_HOST,
    'SMTP_PORT' => SMTP_PORT,
    'SMTP_USERNAME' => SMTP_USERNAME,
    'FROM_EMAIL' => FROM_EMAIL,
    'FROM_NAME' => FROM_NAME
];

foreach ($config_items as $key => $value) {
    if (!empty($value)) {
        echo "<span class='success'>✓ $key: " . ($key === 'SMTP_PASSWORD' ? '********' : $value) . "</span><br>";
    } else {
        echo "<span class='error'>✗ $key is not configured</span><br>";
        $all_tests_passed = false;
    }
}
echo "</div>";

// Final Summary
echo "<div class='section'>";
echo "<h2>📋 Final System Status</h2>";
if ($all_tests_passed) {
    echo "<h3 style='color: green; font-size: 24px;'>🎉 EMAIL SYSTEM FULLY OPERATIONAL!</h3>";
    echo "<p class='success'>All tests passed successfully. The email system is ready for production use.</p>";
    echo "<ul>";
    echo "<li class='success'>✓ Database connectivity verified</li>";
    echo "<li class='success'>✓ Email validation fixed and working correctly</li>";
    echo "<li class='success'>✓ SMTP connection established successfully</li>";
    echo "<li class='success'>✓ All email functions are available</li>";
    echo "<li class='success'>✓ Configuration is complete and valid</li>";
    echo "</ul>";
} else {
    echo "<h3 style='color: orange; font-size: 24px;'>⚠️ SYSTEM NEEDS ATTENTION</h3>";
    echo "<p class='warning'>Some components need to be addressed before full production deployment.</p>";
    echo "<p class='info'>Please review the test results above and fix any issues marked with ✗</p>";
}

echo "<hr>";
echo "<p><strong>Test completed at:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<p><strong>Server:</strong> " . $_SERVER['HTTP_HOST'] . "</p>";
echo "</div>";
?>