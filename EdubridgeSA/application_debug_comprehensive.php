<?php
// Comprehensive Application Debug Script
// This script will trace the entire form submission flow to identify issues

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'debug_error.log');

require_once 'config.php';
require_once 'session_config.php';

// Only allow this debug page when DEBUG_MODE is explicitly enabled in config
if (!defined('DEBUG_MODE') || DEBUG_MODE !== true) {
    http_response_code(404);
    exit;
}

echo "<!DOCTYPE html><html><head><title>Application Debug</title>";
echo "<style>body{font-family:Arial;margin:20px;} .success{color:green;} .error{color:red;} .warning{color:orange;} .info{color:blue;} pre{background:#f5f5f5;padding:10px;border:1px solid #ddd;}</style>";
echo "</head><body>";

echo "<h1>🔍 Comprehensive Application Debug</h1>";
echo "<p><strong>Debug Time:</strong> " . date('Y-m-d H:i:s') . "</p>";

// Debug 1: Environment Check
echo "<h2>1. Environment Check</h2>";

echo "<h3>PHP Configuration</h3>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? '<span class="success">Active</span>' : '<span class="error">Inactive</span>') . "</p>";
echo "<p>Error Reporting: " . error_reporting() . "</p>";

// Debug 2: Session Analysis
echo "<h2>2. Session Analysis</h2>";

if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}

echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>CSRF Token: " . (isset($_SESSION[CSRF_TOKEN_NAME]) ? '<span class="success">Present (' . substr($_SESSION[CSRF_TOKEN_NAME], 0, 10) . '...)</span>' : '<span class="error">Missing</span>') . "</p>";

echo "<h3>Session Data:</h3>";
echo "<pre>" . print_r($_SESSION, true) . "</pre>";

// Debug 3: File Checks
echo "<h2>3. Critical File Checks</h2>";

$criticalFiles = [
    'apply_test.php' => 'Application Form',
    'handleApplicationSubmit.php' => 'Form Handler',
    'config.php' => 'Configuration',
    'session_config.php' => 'Session Config',
    'student-dashboard.php' => 'Dashboard',
    'thank-you.php' => 'Thank You Page'
];

foreach ($criticalFiles as $file => $description) {
    if (file_exists($file)) {
        echo "<p class='success'>✓ $description ($file) - EXISTS</p>";

        // Check file permissions
        if (is_readable($file)) {
            echo "<p class='info'>  → Readable: YES</p>";
        } else {
            echo "<p class='error'>  → Readable: NO</p>";
        }

        // Check for syntax errors
        $output = shell_exec("php -l $file 2>&1");
        if (strpos($output, 'No syntax errors') !== false) {
            echo "<p class='success'>  → Syntax: OK</p>";
        } else {
            echo "<p class='error'>  → Syntax Error: $output</p>";
        }
    } else {
        echo "<p class='error'>✗ $description ($file) - MISSING</p>";
    }
}

// Debug 4: Database Connection
echo "<h2>4. Database Connection Test</h2>";

try {
    $testQuery = $pdo->query("SELECT 1");
    echo "<p class='success'>✓ Database connection successful</p>";

    // Check critical tables
    $tables = ['users', 'applications', 'application_documents'];
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                echo "<p class='success'>✓ Table '$table' exists</p>";
            } else {
                echo "<p class='error'>✗ Table '$table' missing</p>";
            }
        } catch (Exception $e) {
            echo "<p class='error'>✗ Error checking table '$table': " . $e->getMessage() . "</p>";
        }
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ Database connection failed: " . $e->getMessage() . "</p>";
}

// Debug 5: Form Submission Simulation
echo "<h2>5. Form Submission Simulation</h2>";

// Simulate a complete form submission
$testData = [
    CSRF_TOKEN_NAME => $_SESSION[CSRF_TOKEN_NAME],
    'firstName' => 'Debug',
    'lastName' => 'Test',
    'email' => 'debug@test.com',
    'phone' => '0123456789',
    'idNumber' => '1234567890123',
    'dateOfBirth' => '2000-01-01',
    'gender' => 'male',
    'address' => '123 Test Street',
    'city' => 'Test City',
    'province' => 'Test Province',
    'postalCode' => '1234',
    'schoolName' => 'Test School',
    'schoolYear' => '2023',
    'firstChoice' => 'University of Cape Town',
    'firstProgram' => 'Computer Science',
    'subjects' => [
        ['name' => 'Mathematics', 'mark' => '85'],
        ['name' => 'English', 'mark' => '80']
    ]
];

echo "<h3>Test Data Prepared:</h3>";
echo "<pre>" . print_r($testData, true) . "</pre>";

// Debug 6: Validate Form Data Function Test
echo "<h2>6. Form Validation Test</h2>";

// Check if validateFormData function exists
if (function_exists('validateFormData')) {
    echo "<p class='success'>✓ validateFormData function exists</p>";

    try {
        $validationResult = validateFormData($testData);
        if ($validationResult === true) {
            echo "<p class='success'>✓ Test data validation passed</p>";
        } else {
            echo "<p class='error'>✗ Test data validation failed:</p>";
            echo "<pre>" . print_r($validationResult, true) . "</pre>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>✗ Validation function error: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p class='error'>✗ validateFormData function not found</p>";

    // Try to include the handler file to load the function
    if (file_exists('handleApplicationSubmit.php')) {
        echo "<p class='info'>Attempting to load handleApplicationSubmit.php...</p>";
        try {
            // Capture any output from including the file
            ob_start();
            include_once 'handleApplicationSubmit.php';
            $includeOutput = ob_get_clean();

            if (!empty($includeOutput)) {
                echo "<p class='warning'>Output from including handler:</p>";
                echo "<pre>" . htmlspecialchars($includeOutput) . "</pre>";
            }

            if (function_exists('validateFormData')) {
                echo "<p class='success'>✓ validateFormData function loaded successfully</p>";
            } else {
                echo "<p class='error'>✗ validateFormData function still not available</p>";
            }
        } catch (Exception $e) {
            echo "<p class='error'>✗ Error including handler: " . $e->getMessage() . "</p>";
        }
    }
}

// Debug 7: Email Function Test
echo "<h2>7. Email Function Test</h2>";

if (function_exists('sendEmailNotification')) {
    echo "<p class='success'>✓ sendEmailNotification function exists</p>";
} else {
    echo "<p class='error'>✗ sendEmailNotification function not found</p>";
}

// Debug 8: Redirect Logic Test
echo "<h2>8. Redirect Logic Analysis</h2>";

echo "<p><strong>Current Session State:</strong></p>";
echo "<ul>";
echo "<li>student_id: " . (isset($_SESSION['student_id']) ? $_SESSION['student_id'] : 'NOT SET') . "</li>";
echo "<li>user_id: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NOT SET') . "</li>";
echo "<li>applicant_name: " . (isset($_SESSION['applicant_name']) ? $_SESSION['applicant_name'] : 'NOT SET') . "</li>";
echo "</ul>";

echo "<p><strong>Expected Redirect Logic:</strong></p>";
echo "<ul>";
echo "<li>If student_id is set → Redirect to student-dashboard.php</li>";
echo "<li>If student_id is NOT set → Redirect to thank-you.php</li>";
echo "<li>On error → Redirect to apply.php</li>";
echo "</ul>";

// Debug 9: Error Log Check
echo "<h2>9. Error Log Analysis</h2>";

$errorLogs = ['error.log', 'error_log.txt', 'debug_error.log', 'php_errors.log'];
foreach ($errorLogs as $logFile) {
    if (file_exists($logFile)) {
        $logContent = file_get_contents($logFile);
        if (!empty($logContent)) {
            echo "<h3>$logFile (last 1000 characters):</h3>";
            echo "<pre>" . htmlspecialchars(substr($logContent, -1000)) . "</pre>";
        } else {
            echo "<p class='info'>$logFile exists but is empty</p>";
        }
    }
}

// Debug 10: Live Test Instructions
echo "<h2>10. Live Test Instructions</h2>";

echo "<div style='background:#e8f4fd;padding:15px;border:1px solid #bee5eb;border-radius:5px;'>";
echo "<h3>🧪 How to Test the Actual Form:</h3>";
echo "<ol>";
echo "<li><strong>Open apply_test.php</strong> in your browser</li>";
echo "<li><strong>Fill out the form completely</strong> (all required fields)</li>";
echo "<li><strong>Before submitting</strong>, open browser developer tools (F12)</li>";
echo "<li><strong>Go to Console tab</strong> to see JavaScript errors</li>";
echo "<li><strong>Go to Network tab</strong> to monitor the submission request</li>";
echo "<li><strong>Submit the form</strong> and watch what happens</li>";
echo "<li><strong>Check the Network tab</strong> for the POST request to handleApplicationSubmit.php</li>";
echo "<li><strong>Look at the response</strong> - is it a redirect or error?</li>";
echo "</ol>";
echo "</div>";

echo "<div style='background:#fff3cd;padding:15px;border:1px solid #ffeaa7;border-radius:5px;margin-top:10px;'>";
echo "<h3>🔍 What to Look For:</h3>";
echo "<ul>";
echo "<li><strong>JavaScript Errors:</strong> Check browser console for validation errors</li>";
echo "<li><strong>Network Request:</strong> Verify POST request is sent to handleApplicationSubmit.php</li>";
echo "<li><strong>Response Code:</strong> Should be 302 (redirect) for success, not 200</li>";
echo "<li><strong>Response Headers:</strong> Look for 'Location' header showing redirect destination</li>";
echo "<li><strong>Form Data:</strong> Verify all form fields including CSRF token are sent</li>";
echo "</ul>";
echo "</div>";

// Debug 11: Quick Fix Suggestions
echo "<h2>11. Potential Issues & Quick Fixes</h2>";

echo "<div style='background:#f8d7da;padding:15px;border:1px solid #f5c6cb;border-radius:5px;'>";
echo "<h3>🚨 Common Issues:</h3>";
echo "<ul>";
echo "<li><strong>JavaScript Validation:</strong> Form might be failing client-side validation</li>";
echo "<li><strong>Missing Fields:</strong> Required fields might not be filled</li>";
echo "<li><strong>File Upload Issues:</strong> Document uploads might be causing problems</li>";
echo "<li><strong>Session Issues:</strong> Session might be expiring or not persisting</li>";
echo "<li><strong>Database Errors:</strong> Insert operations might be failing silently</li>";
echo "</ul>";
echo "</div>";

echo "<p><strong>Next Steps:</strong></p>";
echo "<ol>";
echo "<li>Run this debug script and review all sections</li>";
echo "<li>Test the actual form with browser developer tools open</li>";
echo "<li>Check server error logs after form submission</li>";
echo "<li>Report back with specific error messages or behavior observed</li>";
echo "</ol>";

echo "</body></html>";
