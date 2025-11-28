<?php
/**
 * EduBridge SA - System Diagnostics Script
 * This script checks for common issues that might prevent applications from working properly
 */

// Start output buffering to capture any errors
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduBridge SA - System Diagnostics</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        .test-section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .test-section h2 {
            color: #444;
            margin-top: 0;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }
        .status {
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            font-weight: bold;
        }
        .status.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .status.warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .details {
            background-color: #f8f9fa;
            padding: 15px;
            margin: 10px 0;
            border-left: 4px solid #007bff;
            font-family: monospace;
            font-size: 14px;
        }
        .summary {
            background-color: #e9ecef;
            padding: 20px;
            border-radius: 5px;
            margin-top: 30px;
        }
        .summary h3 {
            margin-top: 0;
            color: #495057;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        th, td {
            padding: 8px 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 EduBridge SA System Diagnostics</h1>
        <p><strong>Generated:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>

<?php

$errors = [];
$warnings = [];
$successes = [];

// Test 1: PHP Configuration
echo '<div class="test-section">';
echo '<h2>1. PHP Configuration</h2>';

$phpVersion = phpversion();
echo "<div class='details'>PHP Version: $phpVersion</div>";

if (version_compare($phpVersion, '7.4', '>=')) {
    echo "<div class='status success'>✅ PHP version is compatible ($phpVersion)</div>";
    $successes[] = "PHP version compatible";
} else {
    echo "<div class='status error'>❌ PHP version too old ($phpVersion). Minimum required: 7.4</div>";
    $errors[] = "PHP version too old";
}

// Check required extensions
$requiredExtensions = ['pdo', 'pdo_mysql', 'fileinfo', 'gd', 'mbstring'];
echo "<h3>Required PHP Extensions:</h3>";
echo "<table>";
echo "<tr><th>Extension</th><th>Status</th></tr>";

foreach ($requiredExtensions as $ext) {
    echo "<tr><td>$ext</td>";
    if (extension_loaded($ext)) {
        echo "<td style='color: green;'>✅ Loaded</td>";
        $successes[] = "Extension $ext loaded";
    } else {
        echo "<td style='color: red;'>❌ Missing</td>";
        $errors[] = "Missing PHP extension: $ext";
    }
    echo "</tr>";
}
echo "</table>";

echo '</div>';

// Test 2: Database Connection
echo '<div class="test-section">';
echo '<h2>2. Database Connection</h2>';

try {
    // Include session config first, then database config
    require_once 'session_config.php';
    require_once 'config.php';
    
    if (isset($pdo)) {
        echo "<div class='status success'>✅ Database connection successful</div>";
        $successes[] = "Database connection working";
        
        // Test database tables
        $tables = ['applications', 'application_subjects'];
        echo "<h3>Database Tables:</h3>";
        echo "<table>";
        echo "<tr><th>Table</th><th>Status</th><th>Record Count</th></tr>";
        
        foreach ($tables as $table) {
            try {
                $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
                $count = $stmt->fetchColumn();
                echo "<tr><td>$table</td><td style='color: green;'>✅ Exists</td><td>$count records</td></tr>";
                $successes[] = "Table $table exists with $count records";
            } catch (PDOException $e) {
                echo "<tr><td>$table</td><td style='color: red;'>❌ Error</td><td>" . htmlspecialchars($e->getMessage()) . "</td></tr>";
                $errors[] = "Database table error: $table - " . $e->getMessage();
            }
        }
        echo "</table>";
        
    } else {
        echo "<div class='status error'>❌ Database connection failed - PDO object not created</div>";
        $errors[] = "Database connection failed";
    }
    
} catch (Exception $e) {
    echo "<div class='status error'>❌ Database connection failed: " . htmlspecialchars($e->getMessage()) . "</div>";
    $errors[] = "Database connection error: " . $e->getMessage();
}

echo '</div>';

// Test 3: File Upload Directory
echo '<div class="test-section">';
echo '<h2>3. File Upload System</h2>';

$uploadDir = 'uploads/';
if (!file_exists($uploadDir)) {
    try {
        mkdir($uploadDir, 0755, true);
        echo "<div class='status success'>✅ Created uploads directory</div>";
        $successes[] = "Created uploads directory";
    } catch (Exception $e) {
        echo "<div class='status error'>❌ Cannot create uploads directory: " . htmlspecialchars($e->getMessage()) . "</div>";
        $errors[] = "Cannot create uploads directory";
    }
} else {
    echo "<div class='status success'>✅ Uploads directory exists</div>";
    $successes[] = "Uploads directory exists";
}

if (is_writable($uploadDir)) {
    echo "<div class='status success'>✅ Uploads directory is writable</div>";
    $successes[] = "Uploads directory writable";
} else {
    echo "<div class='status error'>❌ Uploads directory is not writable</div>";
    $errors[] = "Uploads directory not writable";
}

// Check disk space
$freeBytes = disk_free_space($uploadDir);
$freeMB = round($freeBytes / 1024 / 1024, 2);
echo "<div class='details'>Available disk space: {$freeMB} MB</div>";

if ($freeMB > 100) {
    echo "<div class='status success'>✅ Sufficient disk space available</div>";
    $successes[] = "Sufficient disk space";
} else {
    echo "<div class='status warning'>⚠️ Low disk space: {$freeMB} MB</div>";
    $warnings[] = "Low disk space: {$freeMB} MB";
}

echo '</div>';

// Test 4: Email System
echo '<div class="test-section">';
echo '<h2>4. Email System</h2>';

// Check if mail function exists
if (function_exists('mail')) {
    echo "<div class='status success'>✅ PHP mail() function is available</div>";
    $successes[] = "Mail function available";
    
    // Test email sending
    $testEmail = 'bongani@edubridgesa.co.za';
    $subject = 'System Diagnostics Test - ' . date('Y-m-d H:i:s');
    $message = "This is a test email from the EduBridge SA system diagnostics script.\n\nGenerated at: " . date('Y-m-d H:i:s');
    $headers = "From: EduBridge SA System <noreply@edubridgesa.co.za>";
    
    if (mail($testEmail, $subject, $message, $headers)) {
        echo "<div class='status success'>✅ Test email sent successfully to $testEmail</div>";
        $successes[] = "Test email sent successfully";
    } else {
        echo "<div class='status error'>❌ Failed to send test email</div>";
        $errors[] = "Failed to send test email";
    }
    
} else {
    echo "<div class='status error'>❌ PHP mail() function is not available</div>";
    $errors[] = "Mail function not available";
}

// Check mail configuration
echo "<h3>Mail Configuration:</h3>";
echo "<table>";
$mailSettings = [
    'sendmail_path' => ini_get('sendmail_path'),
    'SMTP' => ini_get('SMTP'),
    'smtp_port' => ini_get('smtp_port'),
    'sendmail_from' => ini_get('sendmail_from')
];

foreach ($mailSettings as $setting => $value) {
    echo "<tr><td>$setting</td><td>" . ($value ? htmlspecialchars($value) : '<em>Not set</em>') . "</td></tr>";
}
echo "</table>";

echo '</div>';

// Test 5: Application Form Files
echo '<div class="test-section">';
echo '<h2>5. Application System Files</h2>';

$requiredFiles = [
    'apply.php' => 'Application form',
    'process_application.php' => 'Application processor',
    'config.php' => 'Database configuration',
    'admin/dashboard.php' => 'Admin dashboard'
];

echo "<table>";
echo "<tr><th>File</th><th>Description</th><th>Status</th><th>Size</th></tr>";

foreach ($requiredFiles as $file => $description) {
    echo "<tr><td>$file</td><td>$description</td>";
    if (file_exists($file)) {
        $size = filesize($file);
        echo "<td style='color: green;'>✅ Exists</td><td>" . number_format($size) . " bytes</td>";
        $successes[] = "File $file exists";
    } else {
        echo "<td style='color: red;'>❌ Missing</td><td>-</td>";
        $errors[] = "Missing file: $file";
    }
    echo "</tr>";
}
echo "</table>";

echo '</div>';

// Test 6: Session Configuration
echo '<div class="test-section">';
echo '<h2>6. Session Configuration</h2>';

// Session should already be started by session_config.php included earlier
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "<div class='status success'>✅ Sessions are working</div>";
    $successes[] = "Sessions working";
} else {
    // Try to start session if not already active
    try {
        if (file_exists('session_config.php')) {
            require_once 'session_config.php';
            if (session_status() === PHP_SESSION_ACTIVE) {
                echo "<div class='status success'>✅ Sessions started successfully</div>";
                $successes[] = "Sessions working";
            } else {
                echo "<div class='status error'>❌ Sessions could not be started</div>";
                $errors[] = "Sessions not active";
            }
        } else {
            echo "<div class='status error'>❌ Session configuration file missing</div>";
            $errors[] = "Session configuration missing";
        }
    } catch (Exception $e) {
        echo "<div class='status error'>❌ Session error: " . htmlspecialchars($e->getMessage()) . "</div>";
        $errors[] = "Session error: " . $e->getMessage();
    }
}

$sessionPath = session_save_path();
echo "<div class='details'>Session save path: " . ($sessionPath ? $sessionPath : 'Default') . "</div>";

if (is_writable($sessionPath ?: sys_get_temp_dir())) {
    echo "<div class='status success'>✅ Session directory is writable</div>";
    $successes[] = "Session directory writable";
} else {
    echo "<div class='status error'>❌ Session directory is not writable</div>";
    $errors[] = "Session directory not writable";
}

echo '</div>';

// Test 7: Security Checks
echo '<div class="test-section">';
echo '<h2>7. Security Configuration</h2>';

// Check if error display is off in production
$displayErrors = ini_get('display_errors');
if ($displayErrors) {
    echo "<div class='status warning'>⚠️ Error display is enabled (should be disabled in production)</div>";
    $warnings[] = "Error display enabled in production";
} else {
    echo "<div class='status success'>✅ Error display is disabled</div>";
    $successes[] = "Error display properly configured";
}

// Check file upload limits
$maxFileSize = ini_get('upload_max_filesize');
$maxPostSize = ini_get('post_max_size');
echo "<div class='details'>Max file upload size: $maxFileSize</div>";
echo "<div class='details'>Max POST size: $maxPostSize</div>";

echo '</div>';

// Summary
echo '<div class="summary">';
echo '<h3>📊 Diagnostic Summary</h3>';

$totalTests = count($errors) + count($warnings) + count($successes);
echo "<p><strong>Total checks performed:</strong> $totalTests</p>";
echo "<p><strong>✅ Successful:</strong> " . count($successes) . "</p>";
echo "<p><strong>⚠️ Warnings:</strong> " . count($warnings) . "</p>";
echo "<p><strong>❌ Errors:</strong> " . count($errors) . "</p>";

if (count($errors) > 0) {
    echo "<h4 style='color: #721c24;'>🚨 Critical Issues Found:</h4>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li style='color: #721c24;'>$error</li>";
    }
    echo "</ul>";
}

if (count($warnings) > 0) {
    echo "<h4 style='color: #856404;'>⚠️ Warnings:</h4>";
    echo "<ul>";
    foreach ($warnings as $warning) {
        echo "<li style='color: #856404;'>$warning</li>";
    }
    echo "</ul>";
}

if (count($errors) == 0 && count($warnings) == 0) {
    echo "<div class='status success' style='font-size: 18px; text-align: center;'>🎉 All systems are functioning properly!</div>";
} elseif (count($errors) == 0) {
    echo "<div class='status warning' style='font-size: 18px; text-align: center;'>✅ System is functional with minor warnings</div>";
} else {
    echo "<div class='status error' style='font-size: 18px; text-align: center;'>🚨 Critical issues found that need attention</div>";
}

echo '</div>';

// Get any output buffer content
$output = ob_get_clean();
if ($output) {
    echo '<div class="test-section">';
    echo '<h2>8. PHP Errors/Warnings</h2>';
    echo "<div class='status error'>❌ PHP errors detected:</div>";
    echo "<div class='details'>" . htmlspecialchars($output) . "</div>";
    echo '</div>';
}

?>

        <div style="text-align: center; margin-top: 30px; color: #666;">
            <p>EduBridge SA System Diagnostics v1.0</p>
            <p>For technical support, contact: <a href="mailto:bongani@edubridgesa.co.za">bongani@edubridgesa.co.za</a></p>
        </div>
    </div>
</body>
</html>