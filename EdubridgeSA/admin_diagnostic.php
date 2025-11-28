<?php
/**
 * Admin Session Diagnostic Tool
 * EduBridge SA - Debug and test admin session configuration
 */

// Start session and include config
require_once 'session_config.php';
require_once 'config.php';

// Enable detailed error reporting for diagnostics
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if we should run tests
$run_tests = isset($_GET['run_tests']) && $_GET['run_tests'] === 'true';
$test_admin_id = isset($_GET['test_admin_id']) ? intval($_GET['test_admin_id']) : null;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Session Diagnostic - EduBridge SA</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8f9fa; color: #333; line-height: 1.6; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 2rem; text-align: center; margin-bottom: 2rem; border-radius: 10px; }
        .card { background: white; padding: 2rem; margin-bottom: 2rem; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .test-section { border-left: 4px solid #667eea; padding-left: 1rem; margin: 1.5rem 0; }
        .success { color: #28a745; background: #d4edda; padding: 0.5rem; border-radius: 5px; margin: 0.5rem 0; }
        .warning { color: #ffc107; background: #fff3cd; padding: 0.5rem; border-radius: 5px; margin: 0.5rem 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 0.5rem; border-radius: 5px; margin: 0.5rem 0; }
        .info { color: #17a2b8; background: #d1ecf1; padding: 0.5rem; border-radius: 5px; margin: 0.5rem 0; }
        pre { background: #f8f9fa; padding: 1rem; border-radius: 5px; overflow-x: auto; margin: 1rem 0; }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 5px; cursor: pointer; font-size: 1rem; font-weight: 600; text-decoration: none; display: inline-block; margin: 0.5rem; }
        .btn-primary { background: #667eea; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .form-group { margin-bottom: 1rem; }
        .form-control { width: 100%; padding: 0.75rem; border: 2px solid #e1e1e1; border-radius: 5px; font-size: 1rem; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; }
        @media (max-width: 768px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Admin Session Diagnostic Tool</h1>
            <p>EduBridge SA - Debug and test admin session configuration</p>
        </div>

        <div class="grid">
            <!-- Session Information -->
            <div class="card">
                <h2>Session Information</h2>
                <div class="test-section">
                    <h3>Current Session Status</h3>
                    <pre><?php 
                    echo "Session ID: " . session_id() . "\n";
                    echo "Session Name: " . session_name() . "\n";
                    echo "Session Status: " . session_status() . " (" . getSessionStatus(session_status()) . ")\n";
                    echo "Cookie Parameters:\n";
                    print_r(session_get_cookie_params());
                    ?></pre>
                </div>

                <div class="test-section">
                    <h3>Session Variables</h3>
                    <pre><?php 
                    if (empty($_SESSION)) {
                        echo "No session variables set.\n";
                    } else {
                        // Mask sensitive data
                        $session_copy = $_SESSION;
                        if (isset($session_copy['csrf_token'])) {
                            $session_copy['csrf_token'] = '***MASKED***';
                        }
                        print_r($session_copy);
                    }
                    ?></pre>
                </div>

                <div class="test-section">
                    <h3>Session Function Tests</h3>
                    <?php
                    $student_logged_in = isLoggedIn();
                    $admin_logged_in = function_exists('isAdminLoggedIn') ? isAdminLoggedIn() : false;
                    
                    echo "<div class='" . ($student_logged_in ? "success" : "warning") . "'>";
                    echo "Student Login Status: " . ($student_logged_in ? "LOGGED IN" : "NOT LOGGED IN");
                    echo "</div>";
                    
                    echo "<div class='" . ($admin_logged_in ? "success" : "warning") . "'>";
                    echo "Admin Login Status: " . ($admin_logged_in ? "LOGGED IN" : "NOT LOGGED IN");
                    echo "</div>";
                    
                    if (isset($_SESSION['user_type'])) {
                        echo "<div class='info'>User Type: " . $_SESSION['user_type'] . "</div>";
                    }
                    ?>
                </div>
            </div>

            <!-- Database Information -->
            <div class="card">
                <h2>Database & Configuration</h2>
                
                <div class="test-section">
                    <h3>Database Connection</h3>
                    <?php
                    try {
                        $stmt = $pdo->query("SELECT 1");
                        echo "<div class='success'>Database connection: SUCCESS</div>";
                        
                        // Check if admin tables exist
                        $tables_to_check = ['admin_users', 'admins', 'users', 'admin_activity_logs'];
                        foreach ($tables_to_check as $table) {
                            $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
                            $stmt->execute([$table]);
                            $exists = $stmt->rowCount() > 0;
                            echo "<div class='" . ($exists ? "success" : "warning") . "'>";
                            echo "Table '$table': " . ($exists ? "EXISTS" : "NOT FOUND");
                            echo "</div>";
                        }
                    } catch (PDOException $e) {
                        echo "<div class='error'>Database connection: FAILED - " . $e->getMessage() . "</div>";
                    }
                    ?>
                </div>

                <div class="test-section">
                    <h3>Admin Users in Database</h3>
                    <?php
                    try {
                        // Check admin_users table
                        $stmt = $pdo->query("SELECT id, email, first_name, last_name, role FROM admin_users WHERE status = 'active' LIMIT 10");
                        $admin_users = $stmt->fetchAll();
                        
                        if ($admin_users) {
                            echo "<div class='success'>Found " . count($admin_users) . " admin users:</div>";
                            echo "<pre>";
                            foreach ($admin_users as $user) {
                                echo "ID: {$user['id']} - {$user['email']} - {$user['first_name']} {$user['last_name']} - Role: {$user['role']}\n";
                            }
                            echo "</pre>";
                        } else {
                            echo "<div class='warning'>No admin users found in admin_users table.</div>";
                        }

                        // Check users table with admin role
                        $stmt = $pdo->query("SELECT id, email, first_name, last_name, role FROM users WHERE (role = 'admin' OR role = 'administrator') AND status = 'active' LIMIT 10");
                        $user_admins = $stmt->fetchAll();
                        
                        if ($user_admins) {
                            echo "<div class='info'>Found " . count($user_admins) . " admin users in users table:</div>";
                            echo "<pre>";
                            foreach ($user_admins as $user) {
                                echo "ID: {$user['id']} - {$user['email']} - {$user['first_name']} {$user['last_name']} - Role: {$user['role']}\n";
                            }
                            echo "</pre>";
                        }

                    } catch (PDOException $e) {
                        echo "<div class='error'>Error querying admin users: " . $e->getMessage() . "</div>";
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- Test Tools -->
        <div class="card">
            <h2>Admin Session Test Tools</h2>
            
            <div class="test-section">
                <h3>Manual Admin Session Test</h3>
                <form method="POST" action="admin_test_session.php">
                    <div class="form-group">
                        <label>Admin User ID to Test:</label>
                        <input type="number" name="test_admin_id" class="form-control" placeholder="Enter admin user ID" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Test Admin Session</button>
                </form>
                
                <form method="GET" style="margin-top: 1rem;">
                    <input type="hidden" name="run_tests" value="true">
                    <button type="submit" class="btn btn-success">Run Comprehensive Tests</button>
                </form>
            </div>

            <?php if ($run_tests): ?>
            <div class="test-section">
                <h3>Comprehensive Test Results</h3>
                <?php runComprehensiveTests($pdo, $test_admin_id); ?>
            </div>
            <?php endif; ?>

            <div class="test-section">
                <h3>Quick Actions</h3>
                <a href="?clear_session=true" class="btn btn-danger" onclick="return confirm('Are you sure you want to clear the session?')">Clear Session</a>
                <a href="admin_diagnostic.php" class="btn btn-warning">Refresh Diagnostics</a>
                <a href="admin_profile.php" class="btn btn-primary">Test Admin Profile Page</a>
                <a href="admin_dashboard.php" class="btn btn-primary">Test Admin Dashboard</a>
            </div>
        </div>

        <!-- Server Information -->
        <div class="card">
            <h2>Server Information</h2>
            <div class="test-section">
                <pre><?php
                echo "PHP Version: " . PHP_VERSION . "\n";
                echo "Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\n";
                echo "HTTP Host: " . ($_SERVER['HTTP_HOST'] ?? 'Unknown') . "\n";
                echo "HTTPS: " . (isset($_SERVER['HTTPS']) ? 'YES' : 'NO') . "\n";
                echo "Remote IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . "\n";
                echo "User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown') . "\n";
                echo "Session Save Path: " . session_save_path() . "\n";
                echo "Session GC Probability: " . ini_get('session.gc_probability') . "/" . ini_get('session.gc_divisor') . "\n";
                echo "Session GC Max Lifetime: " . ini_get('session.gc_maxlifetime') . " seconds\n";
                ?></pre>
            </div>
        </div>
    </div>
</body>
</html>

<?php

// Helper functions
function getSessionStatus($status) {
    switch($status) {
        case PHP_SESSION_DISABLED: return 'Disabled';
        case PHP_SESSION_NONE: return 'None';
        case PHP_SESSION_ACTIVE: return 'Active';
        default: return 'Unknown';
    }
}

function runComprehensiveTests($pdo, $test_admin_id = null) {
    echo "<h4>1. Session Configuration Test</h4>";
    testSessionConfiguration();
    
    echo "<h4>2. Admin Function Availability Test</h4>";
    testAdminFunctions();
    
    echo "<h4>3. Database Admin Access Test</h4>";
    testDatabaseAdminAccess($pdo);
    
    echo "<h4>4. Admin Session Simulation Test</h4>";
    testAdminSessionSimulation($pdo, $test_admin_id);
}

function testSessionConfiguration() {
    $checks = [
        'session.cookie_httponly' => ['value' => ini_get('session.cookie_httponly'), 'expected' => '1', 'type' => 'bool'],
        'session.use_strict_mode' => ['value' => ini_get('session.use_strict_mode'), 'expected' => '1', 'type' => 'bool'],
        'session.use_only_cookies' => ['value' => ini_get('session.use_only_cookies'), 'expected' => '1', 'type' => 'bool'],
        'session.cookie_secure' => ['value' => ini_get('session.cookie_secure'), 'expected' => isset($_SERVER['HTTPS']) ? '1' : '0', 'type' => 'bool'],
    ];
    
    foreach ($checks as $key => $check) {
        $passed = $check['value'] == $check['expected'];
        echo "<div class='" . ($passed ? "success" : "error") . "'>";
        echo "$key: " . ($check['value'] ? 'ON' : 'OFF') . " - " . ($passed ? "PASS" : "FAIL");
        echo "</div>";
    }
}

function testAdminFunctions() {
    $functions = [
        'isAdminLoggedIn',
        'setAdminLoginSession',
        'clearAdminLoginSession',
        'getAdminById',
        'checkAdminRememberMe'
    ];
    
    foreach ($functions as $function) {
        $exists = function_exists($function);
        echo "<div class='" . ($exists ? "success" : "error") . "'>";
        echo "Function $function(): " . ($exists ? "EXISTS" : "MISSING");
        echo "</div>";
    }
}

function testDatabaseAdminAccess($pdo) {
    try {
        // Test admin_users table structure
        $stmt = $pdo->query("DESCRIBE admin_users");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "<div class='info'>Admin Users Table Columns:</div>";
        echo "<pre>" . implode(", ", $columns) . "</pre>";
        
        // Test sample admin data
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM admin_users WHERE status = 'active'");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "<div class='" . ($count > 0 ? "success" : "warning") . "'>";
        echo "Active Admin Users: $count";
        echo "</div>";
        
    } catch (PDOException $e) {
        echo "<div class='error'>Database structure test failed: " . $e->getMessage() . "</div>";
    }
}

function testAdminSessionSimulation($pdo, $test_admin_id) {
    if (!$test_admin_id) {
        echo "<div class='warning'>No test admin ID provided. Using first available admin.</div>";
        
        // Get first admin user
        try {
            $stmt = $pdo->query("SELECT id FROM admin_users WHERE status = 'active' LIMIT 1");
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            $test_admin_id = $admin ? $admin['id'] : null;
        } catch (PDOException $e) {
            echo "<div class='error'>Cannot find test admin: " . $e->getMessage() . "</div>";
            return;
        }
    }
    
    if ($test_admin_id) {
        echo "<div class='info'>Testing with Admin ID: $test_admin_id</div>";
        
        try {
            $admin = getAdminById($test_admin_id);
            if ($admin) {
                // Simulate admin login
                if (function_exists('setAdminLoginSession')) {
                    setAdminLoginSession($admin);
                    echo "<div class='success'>Admin session simulation: SUCCESS</div>";
                    echo "<div class='info'>Check session variables above to verify admin session was set.</div>";
                } else {
                    echo "<div class='error'>setAdminLoginSession function not available</div>";
                }
            } else {
                echo "<div class='error'>Admin user not found with ID: $test_admin_id</div>";
            }
        } catch (Exception $e) {
            echo "<div class='error'>Session simulation failed: " . $e->getMessage() . "</div>";
        }
    } else {
        echo "<div class='error'>No admin users available for testing</div>";
    }
}

// Handle session clearing
if (isset($_GET['clear_session'])) {
    session_destroy();
    session_start();
    echo "<div class='success'>Session cleared successfully. Refresh the page.</div>";
}
?>