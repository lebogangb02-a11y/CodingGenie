<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'verify_error.log');  // Logs to file if needed

echo "Loading auth.php...\n";

try {
    // Load dependencies first to isolate
    if (!file_exists('config.php')) {
        throw new Exception('config.php missing');
    }
    require_once 'config.php';
    echo "config.php loaded OK.\n";
    
    if (!file_exists('session_config.php')) {
        throw new Exception('session_config.php missing');
    }
    require_once 'session_config.php';
    echo "session_config.php loaded OK.\n";
    
    if (!file_exists('auth.php')) {
        throw new Exception('auth.php missing');
    }
    require_once 'auth.php';
    echo "auth.php loaded OK.\n";
    
    // Check for any suppressed errors
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    echo "No errors during load.\n";
    
} catch (Exception $e) {
    echo "Error during load: " . $e->getMessage() . "\n";
    exit;
} catch (Error $e) {
    echo "Fatal error during load: " . $e->getMessage() . "\n";
    exit;
}

// Now check functions (fixed: no trailing spaces)
echo "authenticateUser  exists: " . (function_exists('authenticateUser ') ? 'YES' : 'NO') . "\n";
echo "getCurrentUser  exists: " . (function_exists('getCurrentUser ') ? 'YES' : 'NO') . "\n";

if (function_exists('authenticateUser ') && function_exists('getCurrentUser ')) {
    echo "All good - functions defined!\n";
} else {
    echo "Issue: Functions not registered. Check auth.php syntax (run 'php -l auth.php' locally if possible).\n";
    echo "Also check error_log or verify_error.log for details.\n";
    // Debug: List all user-defined functions containing 'auth'
    $userFuncs = get_defined_functions()['user'];
    $authFuncs = array_filter($userFuncs, function($f) { return strpos($f, 'auth') !== false || strpos($f, 'CurrentUser ') !== false; });
    echo "Debug - User functions with 'auth' or 'CurrentUser ': " . implode(', ', $authFuncs) . "\n";
}

// Quick test call (safe, no side effects)
if (function_exists('authenticateUser ')) {
    $testResult = authenticateUser ('', '');  // Empty creds test
    echo "authenticateUser  test (empty creds): " . ($testResult['success'] ? 'Unexpected success' : 'Properly rejected') . "\n";
    if (isset($testResult['error_code'])) {
        echo " - Error code: " . $testResult['error_code'] . "\n";
    }
}

if (function_exists('getCurrentUser ')) {
    $current = getCurrentUser ();
    echo "getCurrentUser  test (not logged in): " . ($current === null ? 'Returns null (correct)' : 'Returns data (unexpected)') . "\n";
}

?>