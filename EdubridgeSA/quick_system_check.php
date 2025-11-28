<?php
// Simple System Status Check
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>EduBridge SA - System Status</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            background: #f5f5f5; 
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .status { 
            padding: 15px; 
            margin: 10px 0; 
            border-radius: 8px; 
            border-left: 5px solid;
        }
        .success { 
            background: #d4edda; 
            color: #155724; 
            border-left-color: #28a745;
        }
        .error { 
            background: #f8d7da; 
            color: #721c24; 
            border-left-color: #dc3545;
        }
        .info { 
            background: #d1ecf1; 
            color: #0c5460; 
            border-left-color: #17a2b8;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            border-left-color: #ffc107;
        }
        h1 { color: #333; text-align: center; }
        h2 { color: #555; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        .nav-links {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .nav-links a {
            display: inline-block;
            margin: 5px 10px;
            padding: 10px 15px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
        .nav-links a:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎓 EduBridge SA - System Status Check</h1>
        
        <?php
        echo '<div class="status info">🔧 PHP Version: ' . phpversion() . '</div>';
        echo '<div class="status info">📅 Check performed at: ' . date('Y-m-d H:i:s') . '</div>';
        
        // Check critical files
        echo '<h2>📁 File System Check</h2>';
        $critical_files = [
            'config.php' => 'Database configuration',
            'security-utils.php' => 'Security utilities',
            'student-dashboard.php' => 'Student dashboard',
            'student-login.php' => 'Student login system',
            'admin_dashboard.php' => 'Admin dashboard'
        ];
        
        $all_files_ok = true;
        foreach ($critical_files as $file => $description) {
            if (file_exists($file)) {
                echo '<div class="status success">✅ ' . $file . ' - ' . $description . '</div>';
            } else {
                echo '<div class="status error">❌ ' . $file . ' - ' . $description . ' (MISSING)</div>';
                $all_files_ok = false;
            }
        }
        
        // Database connection test
        echo '<h2>🗄️ Database Connection</h2>';
        try {
            require_once 'config.php';
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            echo '<div class="status success">✅ Database connection established successfully</div>';
            
            // Quick table check
            echo '<h2>📊 Database Tables</h2>';
            $tables = ['applications', 'application_documents', 'parent_guardian_details', 'university_choices'];
            foreach ($tables as $table) {
                try {
                    $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
                    $count = $stmt->fetchColumn();
                    echo '<div class="status success">✅ Table: ' . $table . ' (' . $count . ' records)</div>';
                } catch (Exception $e) {
                    echo '<div class="status warning">⚠️ Table: ' . $table . ' - ' . $e->getMessage() . '</div>';
                }
            }
            
        } catch (Exception $e) {
            echo '<div class="status error">❌ Database connection failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
            $all_files_ok = false;
        }
        
        // Security utilities test
        echo '<h2>🔒 Security System</h2>';
        try {
            require_once 'security-utils.php';
            if (class_exists('SecurityUtils')) {
                echo '<div class="status success">✅ SecurityUtils class loaded successfully</div>';
                
                // Test basic security functions
                $test_input = "<script>alert('test')</script>";
                $sanitized = SecurityUtils::sanitizeInput($test_input);
                if ($sanitized !== $test_input) {
                    echo '<div class="status success">✅ Input sanitization working correctly</div>';
                } else {
                    echo '<div class="status warning">⚠️ Input sanitization may need review</div>';
                }
            } else {
                echo '<div class="status error">❌ SecurityUtils class not available</div>';
            }
        } catch (Exception $e) {
            echo '<div class="status error">❌ Security system error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        
        // Overall status
        echo '<h2>📋 Overall System Status</h2>';
        if ($all_files_ok) {
            echo '<div class="status success">🎉 System is operational and ready for use!</div>';
        } else {
            echo '<div class="status error">⚠️ System has issues that need attention</div>';
        }
        ?>
        
        <div class="nav-links">
            <h2>🔗 Quick Navigation</h2>
            <a href="student-login.php">👨‍🎓 Student Login</a>
            <a href="admin_dashboard.php">👨‍💼 Admin Dashboard</a>
            <a href="quick_system_check.php">🔍 Detailed Check</a>
            <a href="index.php">🏠 Home Page</a>
        </div>
    </div>
</body>
</html>