<?php
// Debug Form Handler
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'form_debug.log');

require_once 'config.php';
require_once 'session_config.php';

// Log everything
file_put_contents('form_debug.log', "\n\n=== FORM SUBMISSION DEBUG " . date('Y-m-d H:i:s') . " ===\n", FILE_APPEND);
file_put_contents('form_debug.log', "REQUEST METHOD: " . $_SERVER['REQUEST_METHOD'] . "\n", FILE_APPEND);
file_put_contents('form_debug.log', "POST DATA: " . print_r($_POST, true) . "\n", FILE_APPEND);
file_put_contents('form_debug.log', "SESSION DATA: " . print_r($_SESSION, true) . "\n", FILE_APPEND);
file_put_contents('form_debug.log', "FILES DATA: " . print_r($_FILES, true) . "\n", FILE_APPEND);

echo "<!DOCTYPE html><html><head><title>Debug Handler Response</title>";
echo "<style>body{font-family:Arial;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} pre{background:#f5f5f5;padding:10px;border:1px solid #ddd;}</style>";
echo "</head><body>";

echo "<h1>🔍 Debug Handler Response</h1>";
echo "<p><strong>Time:</strong> " . date('Y-m-d H:i:s') . "</p>";

echo "<h2>Request Information</h2>";
echo "<p><strong>Method:</strong> " . $_SERVER['REQUEST_METHOD'] . "</p>";
echo "<p><strong>Content Type:</strong> " . ($_SERVER['CONTENT_TYPE'] ?? 'Not set') . "</p>";

echo "<h2>POST Data Received</h2>";
if (!empty($_POST)) {
    echo "<pre>" . print_r($_POST, true) . "</pre>";
} else {
    echo "<p class='error'>No POST data received</p>";
}

echo "<h2>Session Data</h2>";
echo "<pre>" . print_r($_SESSION, true) . "</pre>";

echo "<h2>Files Data</h2>";
if (!empty($_FILES)) {
    echo "<pre>" . print_r($_FILES, true) . "</pre>";
} else {
    echo "<p class='info'>No files uploaded</p>";
}

// Test CSRF validation
echo "<h2>CSRF Validation Test</h2>";
if (isset($_POST[CSRF_TOKEN_NAME]) && isset($_SESSION[CSRF_TOKEN_NAME])) {
    if ($_POST[CSRF_TOKEN_NAME] === $_SESSION[CSRF_TOKEN_NAME]) {
        echo "<p class='success'>✓ CSRF token validation passed</p>";
    } else {
        echo "<p class='error'>✗ CSRF token validation failed</p>";
        echo "<p>POST token: " . $_POST[CSRF_TOKEN_NAME] . "</p>";
        echo "<p>Session token: " . $_SESSION[CSRF_TOKEN_NAME] . "</p>";
    }
} else {
    echo "<p class='error'>✗ CSRF token missing</p>";
    echo "<p>POST has token: " . (isset($_POST[CSRF_TOKEN_NAME]) ? 'YES' : 'NO') . "</p>";
    echo "<p>Session has token: " . (isset($_SESSION[CSRF_TOKEN_NAME]) ? 'YES' : 'NO') . "</p>";
}

// Test if we can call the actual handler
echo "<h2>Handler Function Test</h2>";
if (function_exists('validateFormData')) {
    echo "<p class='success'>✓ validateFormData function is available</p>";
} else {
    echo "<p class='error'>✗ validateFormData function not found</p>";
    
    // Try to include the handler file
    if (file_exists('handleApplicationSubmit.php')) {
        echo "<p class='info'>Attempting to load handleApplicationSubmit.php...</p>";
        try {
            include_once 'handleApplicationSubmit.php';
            if (function_exists('validateFormData')) {
                echo "<p class='success'>✓ validateFormData function loaded successfully</p>";
            } else {
                echo "<p class='error'>✗ validateFormData function still not available after include</p>";
            }
        } catch (Exception $e) {
            echo "<p class='error'>Error including handler: " . $e->getMessage() . "</p>";
        }
    }
}

echo "<h2>Next Steps</h2>";
echo "<div style='background:#fff3cd;padding:15px;border:1px solid #ffeaa7;border-radius:5px;'>";
echo "<p><strong>What to do next:</strong></p>";
echo "<ol>";
echo "<li>Check the form_debug.log file for detailed logs</li>";
echo "<li>Test the actual apply_test.php form</li>";
echo "<li>Compare the data being sent vs what's expected</li>";
echo "<li>Check if the issue is in form submission or handler processing</li>";
echo "</ol>";
echo "</div>";

echo "<p><a href='debug_form_submission.php'>← Back to Debug Form</a></p>";

echo "</body></html>";
?>