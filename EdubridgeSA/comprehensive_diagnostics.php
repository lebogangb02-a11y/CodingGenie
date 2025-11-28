<?php
/**
 * Comprehensive System Diagnostics
 * EduBridge SA - Identify all potential issues
 */

echo "<h1>EduBridge SA - System Diagnostics</h1>";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; }
.success { color: green; font-weight: bold; }
.error { color: red; font-weight: bold; }
.warning { color: orange; font-weight: bold; }
.section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
.section h2 { margin-top: 0; color: #333; }
pre { background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto; }
</style>";

// 1. PHP Configuration Check
echo "<div class='section'>";
echo "<h2>1. PHP Configuration</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Memory Limit: " . ini_get('memory_limit') . "<br>";
echo "Max Execution Time: " . ini_get('max_execution_time') . "<br>";
echo "Upload Max Filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "Post Max Size: " . ini_get('post_max_size') . "<br>";

// Check required extensions
$required_extensions = ['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'curl', 'gd'];
foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<span class='success'>✓ $ext extension loaded</span><br>";
    } else {
        echo "<span class='error'>✗ $ext extension missing</span><br>";
    }
}
echo "</div>";

// 2. File Existence Check
echo "<div class='section'>";
echo "<h2>2. Critical Files Check</h2>";
$critical_files = [
    'config.php',
    'session_config.php',
    'security-utils.php',
    'student-login.php',
    'student-dashboard.php',
    'find-application.php',
    'home.php',
    'index.php'
];

foreach ($critical_files as $file) {
    if (file_exists($file)) {
        echo "<span class='success'>✓ $file exists</span><br>";
        
        // Check file readability and basic structure
        if (is_readable($file)) {
            echo "<span class='success'>  ✓ File is readable</span><br>";
            
            // Basic PHP file validation
            $content = file_get_contents($file);
            if (strpos($content, '<?php') !== false) {
                echo "<span class='success'>  ✓ Valid PHP file structure</span><br>";
            } else {
                echo "<span class='warning'>  ⚠ No PHP opening tag found</span><br>";
            }
        } else {
            echo "<span class='error'>  ✗ File is not readable</span><br>";
        }
    } else {
        echo "<span class='error'>✗ $file missing</span><br>";
    }
}

// Check for missing CSS file
if (!file_exists('styles.css')) {
    echo "<span class='warning'>⚠ styles.css missing (referenced in upload-documents.php)</span><br>";
}
echo "</div>";

// 3. Database Connection Test
echo "<div class='section'>";
echo "<h2>3. Database Connection</h2>";
try {
    require_once 'config.php';
    echo "<span class='success'>✓ Config loaded successfully</span><br>";
    
    if (isset($pdo)) {
        echo "<span class='success'>✓ Database connection established</span><br>";
        
        // Test database queries
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "Tables found: " . count($tables) . "<br>";
        echo "Tables: " . implode(', ', $tables) . "<br>";
        
        // Check applications table structure
        if (in_array('applications', $tables)) {
            echo "<span class='success'>✓ Applications table exists</span><br>";
            
            $stmt = $pdo->query("DESCRIBE applications");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "Applications table columns:<br>";
            foreach ($columns as $column) {
                echo "  - {$column['Field']} ({$column['Type']})<br>";
            }
            
            // Test sample query
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM applications");
            $result = $stmt->fetch();
            echo "Total applications: " . $result['count'] . "<br>";
        } else {
            echo "<span class='error'>✗ Applications table missing</span><br>";
        }
        
    } else {
        echo "<span class='error'>✗ Database connection failed - PDO object not created</span><br>";
    }
} catch (Exception $e) {
    echo "<span class='error'>✗ Database error: " . $e->getMessage() . "</span><br>";
}
echo "</div>";

// 4. Session Configuration Test
echo "<div class='section'>";
echo "<h2>4. Session Configuration</h2>";
try {
    require_once 'session_config.php';
    echo "<span class='success'>✓ Session config loaded</span><br>";
    
    if (session_status() === PHP_SESSION_ACTIVE) {
        echo "<span class='success'>✓ Session started</span><br>";
        echo "Session ID: " . session_id() . "<br>";
        echo "Session name: " . session_name() . "<br>";
    } else {
        echo "<span class='warning'>⚠ Session not active</span><br>";
    }
    
    // Check session settings
    echo "Session cookie lifetime: " . ini_get('session.cookie_lifetime') . "<br>";
    echo "Session gc maxlifetime: " . ini_get('session.gc_maxlifetime') . "<br>";
    echo "Session cookie secure: " . (ini_get('session.cookie_secure') ? 'Yes' : 'No') . "<br>";
    echo "Session cookie httponly: " . (ini_get('session.cookie_httponly') ? 'Yes' : 'No') . "<br>";
    
} catch (Exception $e) {
    echo "<span class='error'>✗ Session config error: " . $e->getMessage() . "</span><br>";
}
echo "</div>";

// 5. Security Utils Test
echo "<div class='section'>";
echo "<h2>5. Security Utils</h2>";
try {
    require_once 'security-utils.php';
    echo "<span class='success'>✓ Security utils loaded</span><br>";
    
    // Test if SecurityUtils class exists
    if (class_exists('SecurityUtils')) {
        echo "<span class='success'>✓ SecurityUtils class available</span><br>";
        
        // Test some methods
        $methods = ['getClientIP', 'validateEmail', 'sanitizeInput'];
        foreach ($methods as $method) {
            if (method_exists('SecurityUtils', $method)) {
                echo "<span class='success'>  ✓ Method $method exists</span><br>";
            } else {
                echo "<span class='error'>  ✗ Method $method missing</span><br>";
            }
        }
    } else {
        echo "<span class='error'>✗ SecurityUtils class not found</span><br>";
    }
} catch (Exception $e) {
    echo "<span class='error'>✗ Security utils error: " . $e->getMessage() . "</span><br>";
}
echo "</div>";

// 6. Directory Permissions
echo "<div class='section'>";
echo "<h2>6. Directory Permissions</h2>";
$directories = ['uploads', 'PHPMailer', 'admin'];
foreach ($directories as $dir) {
    if (is_dir($dir)) {
        echo "<span class='success'>✓ Directory $dir exists</span><br>";
        if (is_writable($dir)) {
            echo "<span class='success'>  ✓ $dir is writable</span><br>";
        } else {
            echo "<span class='warning'>  ⚠ $dir is not writable</span><br>";
        }
    } else {
        echo "<span class='error'>✗ Directory $dir missing</span><br>";
    }
}
echo "</div>";

// 7. Email Configuration Test
echo "<div class='section'>";
echo "<h2>7. Email Configuration</h2>";
if (defined('SMTP_HOST')) {
    echo "<span class='success'>✓ SMTP configuration found</span><br>";
    echo "SMTP Host: " . SMTP_HOST . "<br>";
    echo "SMTP Port: " . SMTP_PORT . "<br>";
    echo "SMTP Username: " . SMTP_USERNAME . "<br>";
    echo "From Email: " . FROM_EMAIL . "<br>";
} else {
    echo "<span class='error'>✗ SMTP configuration missing</span><br>";
}

// Check PHPMailer
if (file_exists('PHPMailer/src/PHPMailer.php')) {
    echo "<span class='success'>✓ PHPMailer found</span><br>";
} else {
    echo "<span class='error'>✗ PHPMailer missing</span><br>";
}
echo "</div>";

// 8. Error Log Check
echo "<div class='section'>";
echo "<h2>8. Error Logs</h2>";
$log_files = ['error.log', 'error_log.txt'];
foreach ($log_files as $log_file) {
    if (file_exists($log_file)) {
        echo "<span class='success'>✓ $log_file exists</span><br>";
        $size = filesize($log_file);
        echo "  Size: " . number_format($size) . " bytes<br>";
        
        if ($size > 0) {
            echo "  Recent errors:<br>";
            $lines = file($log_file);
            $recent_lines = array_slice($lines, -10);
            echo "<pre>" . htmlspecialchars(implode('', $recent_lines)) . "</pre>";
        }
    } else {
        echo "<span class='warning'>⚠ $log_file not found</span><br>";
    }
}
echo "</div>";

echo "<h2>Diagnostic Complete</h2>";
echo "<p>Review the results above to identify any issues that need to be resolved.</p>";
?>