<?php
/**
 * System Status Verification (Test Version)
 * Check database and file system status
 */

echo "<!DOCTYPE html><html><head><title>System Status Check</title>";
echo "<style>body{font-family:Arial;max-width:900px;margin:20px auto;padding:20px;background:#f5f5f5;}";
echo ".status-box{background:white;padding:20px;margin:10px 0;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.1);}";
echo ".success{color:#28a745;} .warning{color:#ffc107;} .error{color:#dc3545;} .info{color:#17a2b8;}";
echo "table{width:100%;border-collapse:collapse;margin:10px 0;} th,td{padding:8px;text-align:left;border-bottom:1px solid #ddd;}";
echo "th{background:#f8f9fa;} .btn{display:inline-block;padding:10px 20px;margin:5px;text-decoration:none;border-radius:5px;color:white;}";
echo ".btn-primary{background:#007bff;} .btn-success{background:#28a745;} .btn-warning{background:#ffc107;color:#000;}";
echo "</style></head><body>";

echo "<h1>🔧 System Status Verification (Test Version)</h1>";
echo "<p>⚠️ <strong>TEST VERSION</strong><br>This is a test version without authentication. For production use, ensure proper admin authentication is in place.</p>";

// Test both configurations
$configs = [
    'Production (Hostinger)' => 'config.php',
    'Local Development' => 'config_local.php'
];

foreach ($configs as $config_name => $config_file) {
    echo "<div class='status-box'>";
    echo "<h2>📊 Database Status - $config_name</h2>";
    
    if (!file_exists($config_file)) {
        echo "<p class='error'>❌ Configuration file '$config_file' not found</p>";
        continue;
    }
    
    // Temporarily capture any output from config file
    ob_start();
    try {
        // Reset any previous database constants
        $constants_to_undefine = ['DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME'];
        foreach ($constants_to_undefine as $const) {
            if (defined($const)) {
                // Can't undefine constants, so we'll use variables instead
            }
        }
        
        // Read config file content and extract database settings
        $config_content = file_get_contents($config_file);
        
        // Extract database settings using regex
        preg_match("/define\('DB_HOST',\s*'([^']+)'\);/", $config_content, $host_match);
        preg_match("/define\('DB_USER',\s*'([^']+)'\);/", $config_content, $user_match);
        preg_match("/define\('DB_PASS',\s*'([^']+)'\);/", $config_content, $pass_match);
        preg_match("/define\('DB_NAME',\s*'([^']+)'\);/", $config_content, $name_match);
        
        $db_host = $host_match[1] ?? 'localhost';
        $db_user = $user_match[1] ?? 'root';
        $db_pass = $pass_match[1] ?? '';
        $db_name = $name_match[1] ?? '';
        
        echo "<p><strong>Configuration:</strong> $db_user@$db_host/$db_name</p>";
        
        // Test connection
        try {
            $dsn = "mysql:host=$db_host;charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 5  // 5 second timeout
            ];
            
            $pdo = new PDO($dsn, $db_user, $db_pass, $options);
            echo "<p class='success'>✅ <strong>Connection Status:</strong> Connected successfully</p>";
            
            // Check if database exists
            try {
                $pdo->exec("USE `$db_name`");
                echo "<p class='success'>✅ <strong>Database '$db_name':</strong> Exists and accessible</p>";
                
                // Check tables
                $stmt = $pdo->query("SHOW TABLES");
                $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                if (empty($tables)) {
                    echo "<p class='warning'>🟡 <strong>Tables:</strong> No tables found</p>";
                } else {
                    echo "<p class='success'>✅ <strong>Tables found:</strong> " . count($tables) . " tables</p>";
                    
                    // Check key tables and record counts
                    $key_tables = ['applications', 'application_documents', 'application_status_history'];
                    $total_records = 0;
                    
                    echo "<table><tr><th>Table</th><th>Status</th><th>Records</th></tr>";
                    foreach ($key_tables as $table) {
                        if (in_array($table, $tables)) {
                            try {
                                $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
                                $count = $stmt->fetchColumn();
                                $total_records += $count;
                                $status = $count > 0 ? "📁 Has data" : "✅ Empty";
                                echo "<tr><td>$table</td><td>$status</td><td>$count</td></tr>";
                            } catch (Exception $e) {
                                echo "<tr><td>$table</td><td>❌ Error</td><td>-</td></tr>";
                            }
                        } else {
                            echo "<tr><td>$table</td><td>❌ Missing</td><td>-</td></tr>";
                        }
                    }
                    echo "</table>";
                    
                    if ($total_records > 0) {
                        echo "<p class='warning'>🟡 <strong>Database Status:</strong> Contains $total_records application records</p>";
                    } else {
                        echo "<p class='success'>✅ <strong>Database Status:</strong> Clean (no application data)</p>";
                    }
                }
                
            } catch (PDOException $e) {
                echo "<p class='error'>❌ <strong>Database '$db_name':</strong> " . $e->getMessage() . "</p>";
            }
            
        } catch (PDOException $e) {
            echo "<p class='error'>❌ <strong>Connection Status:</strong> Failed</p>";
            echo "<p class='error'><strong>Error:</strong> " . $e->getMessage() . "</p>";
            
            // Provide specific troubleshooting
            if (strpos($e->getMessage(), 'Access denied') !== false) {
                echo "<p class='info'>💡 <strong>Solution:</strong> Check username and password in $config_file</p>";
            } elseif (strpos($e->getMessage(), 'Connection refused') !== false || strpos($e->getMessage(), 'server has gone away') !== false) {
                echo "<p class='info'>💡 <strong>Solution:</strong> Start MySQL service or check if server is running</p>";
            }
        }
        
    } catch (Exception $e) {
        echo "<p class='error'>❌ <strong>Configuration Error:</strong> " . $e->getMessage() . "</p>";
    }
    
    ob_end_clean();
    echo "</div>";
}

// File System Status (same as before)
echo "<div class='status-box'>";
echo "<h2>📁 File System Status</h2>";

$upload_dirs = [
    'uploads/',
    'uploads/id_documents/',
    'uploads/matric_certificates/',
    'uploads/additional_documents/',
    'uploads/proof_of_residence/'
];

$total_files = 0;
$total_size = 0;
$all_clean = true;

echo "<table><tr><th>Directory</th><th>Status</th><th>File Count</th><th>Size</th></tr>";

foreach ($upload_dirs as $dir) {
    if (!is_dir($dir)) {
        echo "<tr><td>$dir</td><td>❌ Missing</td><td>0</td><td>0 B</td></tr>";
        $all_clean = false;
        continue;
    }
    
    $files = glob($dir . '*');
    $files = array_filter($files, 'is_file');
    $count = count($files);
    $size = 0;
    
    foreach ($files as $file) {
        $size += filesize($file);
    }
    
    $total_files += $count;
    $total_size += $size;
    
    if ($count > 0) {
        $all_clean = false;
        $status = "📁 Has files";
    } else {
        $status = "✅ Empty";
    }
    
    $size_formatted = $size > 0 ? number_format($size / 1024, 2) . ' KB' : '0 B';
    echo "<tr><td>$dir</td><td>$status</td><td>$count</td><td>$size_formatted</td></tr>";
}

echo "</table>";

$file_status = $all_clean ? "✅ Clean" : "🟡 Contains files";
$total_size_formatted = $total_size > 1024 * 1024 ? 
    number_format($total_size / (1024 * 1024), 2) . ' MB' : 
    number_format($total_size / 1024, 2) . ' KB';

echo "<p><strong>File System Status:</strong> $file_status</p>";
echo "<p><strong>Total Files:</strong> $total_files</p>";
echo "<p><strong>Total Size:</strong> $total_size_formatted</p>";
echo "</div>";

// Action buttons
echo "<div class='status-box'>";
echo "<h2>🛠️ Available Actions</h2>";
echo "<a href='database_troubleshoot.php' class='btn btn-warning'>🔧 Database Troubleshooting</a>";
echo "<a href='file_only_reset.php' class='btn btn-warning' style='background:#dc3545;'>🗂️ File-Only Reset</a>";
echo "<a href='admin_login.php' class='btn btn-primary'>🔐 Admin Login (for reset tools)</a>";
echo "<a href='verify_system_status_test.php' class='btn btn-success'>🔄 Refresh Status</a>";
echo "</div>";

echo "</body></html>";
?>