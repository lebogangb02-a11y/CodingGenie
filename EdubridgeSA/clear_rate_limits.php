<?php
/**
 * Clear Rate Limits and Check Security Logs
 */

require_once 'config.php';
require_once 'security-utils.php';

echo "<h1>🔧 Rate Limit Management</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .info { color: blue; }
    .warning { color: orange; font-weight: bold; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    .btn { padding: 10px 20px; margin: 5px; background: #007cba; color: white; text-decoration: none; border-radius: 5px; }
</style>";

try {
    // Get current client IP
    $client_ip = SecurityUtils::getClientIP();
    echo "<h2>🌐 Current Client Information</h2>";
    echo "<span class='info'>Your IP Address: $client_ip</span><br><br>";
    
    // Check current rate limits
    echo "<h2>⏱️ Current Rate Limits</h2>";
    $stmt = $pdo->prepare("SELECT * FROM rate_limits WHERE ip_address = ? ORDER BY created_at DESC");
    $stmt->execute([$client_ip]);
    $rate_limits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($rate_limits)) {
        echo "<span class='success'>✅ No rate limits found for your IP</span><br>";
    } else {
        echo "<span class='warning'>⚠️ Found " . count($rate_limits) . " rate limit entries:</span><br><br>";
        
        echo "<table>";
        echo "<tr><th>Action Type</th><th>Attempts</th><th>Window Start</th><th>Created</th><th>Status</th></tr>";
        
        foreach ($rate_limits as $limit) {
            $window_end = date('Y-m-d H:i:s', strtotime($limit['window_start']) + 900); // 15 minutes
            $is_expired = time() > (strtotime($limit['window_start']) + 900);
            
            echo "<tr>";
            echo "<td>{$limit['action_type']}</td>";
            echo "<td>{$limit['attempt_count']}</td>";
            echo "<td>{$limit['window_start']}</td>";
            echo "<td>{$limit['created_at']}</td>";
            echo "<td>" . ($is_expired ? "<span class='success'>Expired</span>" : "<span class='error'>Active</span>") . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Check security logs
    echo "<h2>🔒 Recent Security Events</h2>";
    $stmt = $pdo->prepare("SELECT * FROM security_logs WHERE ip_address = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$client_ip]);
    $security_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($security_logs)) {
        echo "<table>";
        echo "<tr><th>Event Type</th><th>Description</th><th>User ID</th><th>Time</th></tr>";
        
        foreach ($security_logs as $log) {
            echo "<tr>";
            echo "<td>{$log['event_type']}</td>";
            echo "<td>{$log['description']}</td>";
            echo "<td>" . ($log['user_id'] ?? 'N/A') . "</td>";
            echo "<td>{$log['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<span class='info'>No security events found for your IP</span><br>";
    }
    
    // Clear rate limits button
    echo "<hr>";
    echo "<h2>🧹 Clear Rate Limits</h2>";
    
    if (isset($_POST['clear_limits'])) {
        $deleted = $pdo->prepare("DELETE FROM rate_limits WHERE ip_address = ?");
        $deleted->execute([$client_ip]);
        $count = $deleted->rowCount();
        
        echo "<span class='success'>✅ Cleared $count rate limit entries for your IP</span><br>";
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
    }
    
    echo "<form method='POST' action='clear_rate_limits.php'>";
    echo "<button type='submit' name='clear_limits' class='btn' style='background: #dc3545;'>Clear All Rate Limits for My IP</button>";
    echo "</form>";
    
    echo "<br><span class='warning'>⚠️ This will clear all rate limiting for your IP address</span><br>";
    
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
    }
    
} catch (Exception $e) {
    echo "<span class='error'>❌ Error: " . $e->getMessage() . "</span><br>";
}
?>