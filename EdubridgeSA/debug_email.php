<?php
/**
 * Simple Email Debug Script
 * Step-by-step debugging to identify the issue
 */

echo "<h1>Email System Debug</h1>";

// Step 1: Check if config file exists and loads
echo "<h2>Step 1: Configuration File</h2>";
try {
    if (file_exists('config_application.php')) {
        echo "✅ config_application.php exists<br>";
        require_once 'config_application.php';
        echo "✅ config_application.php loaded successfully<br>";
    } else {
        echo "❌ config_application.php not found<br>";
        die("Cannot proceed without configuration file.");
    }
} catch (Exception $e) {
    echo "❌ Error loading config: " . $e->getMessage() . "<br>";
    die();
}

// Step 2: Check if email functions file exists
echo "<h2>Step 2: Email Functions File</h2>";
try {
    if (file_exists('email_functions.php')) {
        echo "✅ email_functions.php exists<br>";
        require_once 'email_functions.php';
        echo "✅ email_functions.php loaded successfully<br>";
    } else {
        echo "❌ email_functions.php not found<br>";
        die("Cannot proceed without email functions.");
    }
} catch (Exception $e) {
    echo "❌ Error loading email functions: " . $e->getMessage() . "<br>";
    die();
}

// Step 3: Check database connection
echo "<h2>Step 3: Database Connection</h2>";
try {
    $pdo = getDBConnection();
    echo "✅ Database connection successful<br>";
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "<br>";
}

// Step 4: Check email configuration constants
echo "<h2>Step 4: Email Configuration</h2>";
$required_constants = ['SMTP_HOST', 'SMTP_PORT', 'SMTP_USERNAME', 'SMTP_PASSWORD', 'FROM_EMAIL', 'FROM_NAME'];
foreach ($required_constants as $constant) {
    if (defined($constant)) {
        echo "✅ $constant is defined<br>";
    } else {
        echo "❌ $constant is NOT defined<br>";
    }
}

// Step 5: Check if PHPMailer is available
echo "<h2>Step 5: PHPMailer Availability</h2>";
if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
    echo "✅ PHPMailer class is available<br>";
} else {
    echo "❌ PHPMailer class is NOT available<br>";
    echo "Trying to load PHPMailer manually...<br>";
    
    // Try different possible paths for PHPMailer
    $phpmailer_paths = [
        'vendor/autoload.php',
        '../vendor/autoload.php',
        'phpmailer/autoload.php',
        'PHPMailer/src/PHPMailer.php'
    ];
    
    foreach ($phpmailer_paths as $path) {
        if (file_exists($path)) {
            echo "Found PHPMailer at: $path<br>";
            try {
                require_once $path;
                if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                    echo "✅ PHPMailer loaded successfully<br>";
                    break;
                }
            } catch (Exception $e) {
                echo "❌ Error loading PHPMailer from $path: " . $e->getMessage() . "<br>";
            }
        }
    }
}

// Step 6: Test basic email function existence
echo "<h2>Step 6: Email Functions</h2>";
$functions = ['sendApplicationConfirmation', 'sendDocumentReminder', 'testEmailConfiguration'];
foreach ($functions as $function) {
    if (function_exists($function)) {
        echo "✅ Function $function exists<br>";
    } else {
        echo "❌ Function $function does NOT exist<br>";
    }
}

echo "<h2>Debug Complete</h2>";
echo "<p>If all steps show ✅, the email system should work. If you see ❌, those are the issues to fix.</p>";
echo "<p><a href='test_email.php'>Try Full Email Test</a> | <a href='student_application.php'>Back to Application</a></p>";
?>