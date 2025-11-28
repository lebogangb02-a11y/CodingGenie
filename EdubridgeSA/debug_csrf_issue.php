<?php
/**
 * CSRF Token Debug Script
 * Identifies the exact issue with CSRF token validation
 */

require_once 'config.php';
require_once 'session_config.php';

echo "<h1>CSRF Token Debug Report</h1>";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; }
.success { color: green; font-weight: bold; }
.error { color: red; font-weight: bold; }
.warning { color: orange; font-weight: bold; }
.info { color: blue; font-weight: bold; }
.section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
pre { background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto; }
</style>";

// 1. Check session status
echo "<div class='section'>";
echo "<h2>1. Session Status</h2>";
echo "Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? '<span class="success">ACTIVE</span>' : '<span class="error">INACTIVE</span>') . "<br>";
echo "Session ID: " . session_id() . "<br>";
echo "Session Name: " . session_name() . "<br>";
echo "</div>";

// 2. Check CSRF token constant
echo "<div class='section'>";
echo "<h2>2. CSRF Token Configuration</h2>";
if (defined('CSRF_TOKEN_NAME')) {
    echo "<span class='success'>✓ CSRF_TOKEN_NAME defined: " . CSRF_TOKEN_NAME . "</span><br>";
} else {
    echo "<span class='error'>✗ CSRF_TOKEN_NAME not defined</span><br>";
}
echo "</div>";

// 3. Check session CSRF token
echo "<div class='section'>";
echo "<h2>3. Session CSRF Token</h2>";
if (isset($_SESSION[CSRF_TOKEN_NAME])) {
    echo "<span class='success'>✓ CSRF token exists in session</span><br>";
    echo "Token: " . substr($_SESSION[CSRF_TOKEN_NAME], 0, 20) . "...<br>";
    echo "Token length: " . strlen($_SESSION[CSRF_TOKEN_NAME]) . " characters<br>";
} else {
    echo "<span class='error'>✗ CSRF token missing from session</span><br>";
    echo "<span class='info'>Generating new token...</span><br>";
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    echo "<span class='success'>✓ New token generated: " . substr($_SESSION[CSRF_TOKEN_NAME], 0, 20) . "...</span><br>";
}
echo "</div>";

// 4. Simulate form submission
echo "<div class='section'>";
echo "<h2>4. Form Submission Simulation</h2>";

// Create a test form
echo "<h3>Test Form (like apply_test.php)</h3>";
echo "<form method='POST' action='debug_csrf_issue.php'>";
echo "<input type='hidden' name='" . CSRF_TOKEN_NAME . "' value='" . $_SESSION[CSRF_TOKEN_NAME] . "'>";
echo "<input type='hidden' name='test_submission' value='1'>";
echo "<button type='submit'>Test Submit</button>";
echo "</form>";

// Check if this is a POST submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_submission'])) {
    echo "<h3>POST Submission Results</h3>";
    
    echo "<strong>POST Data:</strong><br>";
    echo "<pre>";
    foreach ($_POST as $key => $value) {
        if ($key === CSRF_TOKEN_NAME) {
            echo "$key: " . substr($value, 0, 20) . "...\n";
        } else {
            echo "$key: $value\n";
        }
    }
    echo "</pre>";
    
    echo "<strong>CSRF Validation Test:</strong><br>";
    if (!isset($_POST[CSRF_TOKEN_NAME])) {
        echo "<span class='error'>✗ CSRF token missing from POST data</span><br>";
    } elseif (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        echo "<span class='error'>✗ CSRF token missing from session</span><br>";
    } elseif ($_POST[CSRF_TOKEN_NAME] !== $_SESSION[CSRF_TOKEN_NAME]) {
        echo "<span class='error'>✗ CSRF token mismatch</span><br>";
        echo "POST token: " . substr($_POST[CSRF_TOKEN_NAME], 0, 20) . "...<br>";
        echo "Session token: " . substr($_SESSION[CSRF_TOKEN_NAME], 0, 20) . "...<br>";
    } else {
        echo "<span class='success'>✓ CSRF token validation PASSED</span><br>";
    }
}
echo "</div>";

// 5. Check apply_test.php form
echo "<div class='section'>";
echo "<h2>5. Apply Test Form Analysis</h2>";
if (file_exists('apply_test.php')) {
    $content = file_get_contents('apply_test.php');
    
    // Check for CSRF token in form
    if (strpos($content, 'CSRF_TOKEN_NAME') !== false) {
        echo "<span class='success'>✓ apply_test.php includes CSRF token</span><br>";
    } else {
        echo "<span class='error'>✗ apply_test.php missing CSRF token</span><br>";
    }
    
    // Check form action
    if (strpos($content, 'handleApplicationSubmit.php') !== false) {
        echo "<span class='success'>✓ Form submits to handleApplicationSubmit.php</span><br>";
    } else {
        echo "<span class='warning'>⚠ Form action unclear</span><br>";
    }
} else {
    echo "<span class='error'>✗ apply_test.php not found</span><br>";
}
echo "</div>";

// 6. Check handleApplicationSubmit.php
echo "<div class='section'>";
echo "<h2>6. Handler Analysis</h2>";
if (file_exists('handleApplicationSubmit.php')) {
    echo "<span class='success'>✓ handleApplicationSubmit.php exists</span><br>";
    
    $content = file_get_contents('handleApplicationSubmit.php');
    
    // Check for CSRF validation
    if (strpos($content, 'CSRF_TOKEN_NAME') !== false) {
        echo "<span class='success'>✓ Handler includes CSRF validation</span><br>";
    } else {
        echo "<span class='error'>✗ Handler missing CSRF validation</span><br>";
    }
} else {
    echo "<span class='error'>✗ handleApplicationSubmit.php not found</span><br>";
}
echo "</div>";

// 7. Recent error analysis
echo "<div class='section'>";
echo "<h2>7. Recent Error Analysis</h2>";
if (file_exists('error.log')) {
    $lines = file('error.log');
    $recent_lines = array_slice($lines, -10);
    
    echo "<strong>Recent CSRF-related errors:</strong><br>";
    echo "<pre>";
    foreach ($recent_lines as $line) {
        if (strpos($line, 'CSRF') !== false || strpos($line, 'token') !== false) {
            echo htmlspecialchars($line);
        }
    }
    echo "</pre>";
} else {
    echo "<span class='warning'>⚠ No error.log found</span><br>";
}
echo "</div>";

echo "<div class='section'>";
echo "<h2>Summary & Recommendations</h2>";
echo "<p>This debug script helps identify CSRF token issues. Common problems:</p>";
echo "<ul>";
echo "<li>Session not started before token generation</li>";
echo "<li>Token regenerated between form display and submission</li>";
echo "<li>Multiple tabs causing token conflicts</li>";
echo "<li>Form submitted to wrong handler</li>";
echo "<li>Token name mismatch between form and handler</li>";
echo "</ul>";
echo "</div>";
?>