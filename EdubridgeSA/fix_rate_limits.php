<?php
/**
 * Fix Rate Limits - Check Database Structure and Clear Limits
 */

require_once 'config.php';
require_once 'security-utils.php';

echo "<h1>🔧 Database Structure Check & Rate Limit Fix</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .info { color: blue; }
    .warning { color: orange; font-weight: bold; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    .btn { padding: 10px 20px; margin: 5px; background: #007cba; color: white; text-decoration: none; border-radius: 5px; display: inline-block; }
    pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; }
</style>";

try {
    // Get current client IP
    $client_ip = SecurityUtils::getClientIP();
    echo "<h2>🌐 Current Client Information</h2>";
    echo "<span class='info'>Your IP Address: $client_ip</span><br><br>";
    
    // Check if rate_limits table exists and its structure
    echo "<h2>🗄️ Database Table Analysis</h2>";
    
    // Check if rate_limits table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'rate_limits'");
    $table_exists = $stmt->fetch();
    
    if (!$table_exists) {
        echo "<span class='warning'>⚠️ rate_limits table does not exist</span><br>";
        echo "<span class='info'>Creating rate_limits table...</span><br>";
        
        $create_table = "
        CREATE TABLE rate_limits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_ip VARCHAR(45) NOT NULL,
            action_type VARCHAR(50) NOT NULL,
            attempt_count INT DEFAULT 1,
            window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_client_ip_action (client_ip, action_type),
            INDEX idx_window_start (window_start)
        )";
        
        $pdo->exec($create_table);
        echo "<span class='success'>✅ rate_limits table created successfully</span><br>";
    } else {
        echo "<span class='success'>✅ rate_limits table exists</span><br>";
        
        // Show table structure
        $stmt = $pdo->query("DESCRIBE rate_limits");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>📋 Table Structure:</h3>";
        echo "<table>";
        echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        foreach ($columns as $col) {
            echo "<tr>";
            echo "<td>{$col['Field']}</td>";
            echo "<td>{$col['Type']}</td>";
            echo "<td>{$col['Null']}</td>";
            echo "<td>{$col['Key']}</td>";
            echo "<td>{$col['Default']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Check what column name is used for IP
        $column_names = array_column($columns, 'Field');
        $ip_column = null;
        
        if (in_array('ip_address', $column_names)) {
            $ip_column = 'ip_address';
        } elseif (in_array('client_ip', $column_names)) {
            $ip_column = 'client_ip';
        } elseif (in_array('ip', $column_names)) {
            $ip_column = 'ip';
        }
        
        echo "<span class='info'>IP column detected: " . ($ip_column ?? 'NONE FOUND') . "</span><br><br>";
        
        // Show current rate limits
        if ($ip_column) {
            echo "<h3>⏱️ Current Rate Limits for Your IP:</h3>";
            $stmt = $pdo->prepare("SELECT * FROM rate_limits WHERE $ip_column = ? ORDER BY created_at DESC");
            $stmt->execute([$client_ip]);
            $rate_limits = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($rate_limits)) {
                echo "<span class='success'>✅ No rate limits found for your IP</span><br>";
            } else {
                echo "<span class='warning'>⚠️ Found " . count($rate_limits) . " rate limit entries:</span><br>";
                echo "<table>";
                echo "<tr>";
                foreach ($columns as $col) {
                    echo "<th>{$col['Field']}</th>";
                }
                echo "</tr>";
                
                foreach ($rate_limits as $limit) {
                    echo "<tr>";
                    foreach ($columns as $col) {
                        echo "<td>" . ($limit[$col['Field']] ?? 'NULL') . "</td>";
                    }
                    echo "</tr>";
                }
                echo "</table>";
            }
        }
    }
    
    // Clear rate limits section
    echo "<hr>";
    echo "<h2>🧹 Clear Rate Limits</h2>";
    
    if (isset($_POST['clear_limits'])) {
        // Determine the correct IP column
        $stmt = $pdo->query("DESCRIBE rate_limits");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $column_names = array_column($columns, 'Field');
        
        $ip_column = null;
        if (in_array('ip_address', $column_names)) {
            $ip_column = 'ip_address';
        } elseif (in_array('client_ip', $column_names)) {
            $ip_column = 'client_ip';
        } elseif (in_array('ip', $column_names)) {
            $ip_column = 'ip';
        }
        
        if ($ip_column) {
            $deleted = $pdo->prepare("DELETE FROM rate_limits WHERE $ip_column = ?");
            $deleted->execute([$client_ip]);
            $count = $deleted->rowCount();
            
            echo "<span class='success'>✅ Cleared $count rate limit entries for your IP using column '$ip_column'</span><br>";
            echo "<span class='info'>You can now try logging in again!</span><br><br>";
            
            // Log the rate limit clear
            SecurityUtils::logSecurityEvent(
                $pdo,
                'rate_limit_cleared',
                'Rate limits manually cleared for IP: ' . $client_ip,
                null,
                $client_ip
            );
            
            echo "<a href='student-login.php' class='btn'>→ Try Login Again</a><br><br>";
        } else {
            // If no IP column found, clear all rate limits (emergency option)
            $deleted = $pdo->query("DELETE FROM rate_limits");
            $count = $deleted->rowCount();
            echo "<span class='warning'>⚠️ No IP column found, cleared ALL $count rate limit entries</span><br>";
            echo "<a href='student-login.php' class='btn'>→ Try Login Again</a><br><br>";
        }
    }
    
    if (isset($_POST['clear_all'])) {
        $deleted = $pdo->query("DELETE FROM rate_limits");
        $count = $deleted->rowCount();
        echo "<span class='warning'>⚠️ Cleared ALL $count rate limit entries from database</span><br>";
        echo "<a href='student-login.php' class='btn'>→ Try Login Again</a><br><br>";
    }
    
    echo "<form method='POST' action='fix_rate_limits.php' style='margin: 10px 0;'>";
    echo "<button type='submit' name='clear_limits' class='btn' style='background: #dc3545;'>Clear Rate Limits for My IP</button>";
    echo "</form>";
    
    echo "<form method='POST' action='fix_rate_limits.php' style='margin: 10px 0;'>";
    echo "<button type='submit' name='clear_all' class='btn' style='background: #6c757d;' onclick='return confirm(\"Clear ALL rate limits for ALL users?\")'>Clear ALL Rate Limits (Emergency)</button>";
    echo "</form>";
    
    // Show valid credentials
    echo "<hr>";
    echo "<h2>✅ Valid Login Credentials</h2>";
    echo "<span class='info'>Use these credentials after clearing rate limits:</span><br><br>";
    
    $stmt = $pdo->prepare("SELECT reference_number, full_name, surname FROM applications WHERE email_address = ? ORDER BY created_at DESC");
    $stmt->execute(['lebogangb02@gmail.com']);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($applications)) {
        echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<strong>Email:</strong> lebogangb02@gmail.com<br><br>";
        echo "<strong>Valid Reference Numbers:</strong><br>";
        foreach ($applications as $app) {
            echo "• <code>{$app['reference_number']}</code> - {$app['full_name']} {$app['surname']}<br>";
        }
        echo "</div>";
        
        echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0; border: 1px solid #c3e6cb;'>";
        echo "<strong>🎯 Recommended Login:</strong><br>";
        echo "Email: <code>lebogangb02@gmail.com</code><br>";
        echo "Reference: <code>{$applications[0]['reference_number']}</code><br>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<span class='error'>❌ Error: " . $e->getMessage() . "</span><br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>