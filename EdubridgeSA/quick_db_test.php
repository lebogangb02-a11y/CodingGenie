<?php
/**
 * Quick Database Connection Test
 * Simplified version with clear output
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Quick Database Test</h1>";
echo "<style>body{font-family:Arial;max-width:600px;margin:20px auto;padding:20px;background:#f5f5f5;}</style>";

echo "<h2>🔌 Testing Database Connections</h2>";

// Test 1: Check if MySQL extension is available
echo "<div style='background:white;padding:15px;margin:10px 0;border-radius:5px;'>";
echo "<h3>Step 1: PHP MySQL Extension Check</h3>";
if (extension_loaded('pdo_mysql')) {
    echo "✅ <strong>PDO MySQL extension is loaded</strong><br>";
} else {
    echo "❌ <strong>PDO MySQL extension is NOT loaded</strong><br>";
    echo "💡 <strong>Solution:</strong> Install php-mysql or enable pdo_mysql extension<br>";
}
echo "</div>";

// Test 2: Local MySQL Connection
echo "<div style='background:white;padding:15px;margin:10px 0;border-radius:5px;'>";
echo "<h3>Step 2: Local MySQL Test</h3>";
echo "<strong>Testing:</strong> localhost with root/(empty password)<br>";

try {
    $pdo = new PDO("mysql:host=localhost", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5
    ]);
    
    echo "✅ <strong>LOCAL CONNECTION SUCCESSFUL!</strong><br>";
    
    // Show databases
    $stmt = $pdo->query("SHOW DATABASES");
    $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "📁 <strong>Available databases:</strong> " . implode(', ', $databases) . "<br>";
    
    // Check if our target database exists
    if (in_array('edubridgesa_local', $databases)) {
        echo "✅ <strong>Target database 'edubridgesa_local' exists!</strong><br>";
    } else {
        echo "⚠️ <strong>Target database 'edubridgesa_local' does not exist</strong><br>";
        echo "💡 <strong>Solution:</strong> Create it or use an existing database<br>";
    }
    
} catch (PDOException $e) {
    echo "❌ <strong>LOCAL CONNECTION FAILED:</strong> " . $e->getMessage() . "<br>";
    
    if (strpos($e->getMessage(), 'Connection refused') !== false) {
        echo "💡 <strong>Cause:</strong> MySQL server is not running<br>";
        echo "🔧 <strong>Solution:</strong> Start XAMPP/WAMP or MySQL service<br>";
    } elseif (strpos($e->getMessage(), 'Access denied') !== false) {
        echo "💡 <strong>Cause:</strong> Wrong credentials<br>";
        echo "🔧 <strong>Try:</strong> root/root or root/password<br>";
    }
}
echo "</div>";

// Test 3: Alternative local credentials
echo "<div style='background:white;padding:15px;margin:10px 0;border-radius:5px;'>";
echo "<h3>Step 3: Alternative Credentials Test</h3>";

$credentials = [
    ['root', 'root'],
    ['root', 'password'],
    ['root', '123456']
];

$found_working = false;
foreach ($credentials as [$user, $pass]) {
    echo "<strong>Testing:</strong> $user / $pass ... ";
    
    try {
        $pdo = new PDO("mysql:host=localhost", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 3
        ]);
        echo "✅ <strong>SUCCESS!</strong><br>";
        $found_working = true;
        
        // Show databases
        $stmt = $pdo->query("SHOW DATABASES");
        $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "📁 <strong>Databases:</strong> " . implode(', ', $databases) . "<br>";
        break;
        
    } catch (PDOException $e) {
        echo "❌ Failed<br>";
    }
}

if (!$found_working) {
    echo "⚠️ <strong>No working local credentials found</strong><br>";
}
echo "</div>";

// Test 4: Production database (if local fails)
echo "<div style='background:white;padding:15px;margin:10px 0;border-radius:5px;'>";
echo "<h3>Step 4: Production Database Test</h3>";
echo "<strong>Testing:</strong> Hostinger database connection<br>";

try {
    // Note: Using the actual remote host instead of localhost
    $pdo = new PDO("mysql:host=srv1150.hstgr.io", "u839420047_Edubridge", "BAs1m@n3", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 10
    ]);
    
    echo "✅ <strong>PRODUCTION CONNECTION SUCCESSFUL!</strong><br>";
    
    // Try to use the database
    $pdo->exec("USE u839420047_applications");
    echo "✅ <strong>Database accessible!</strong><br>";
    
    // Count tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "📊 <strong>Tables found:</strong> " . count($tables) . " tables<br>";
    
} catch (PDOException $e) {
    echo "❌ <strong>PRODUCTION CONNECTION FAILED:</strong> " . $e->getMessage() . "<br>";
    echo "💡 <strong>Note:</strong> This is normal if remote access is restricted<br>";
}
echo "</div>";

// Recommendations
echo "<div style='background:#e7f3ff;padding:15px;margin:10px 0;border-radius:5px;border-left:4px solid #007bff;'>";
echo "<h3>🎯 Recommendations</h3>";
echo "<strong>Based on the results above:</strong><br><br>";
echo "1. <strong>If local MySQL works:</strong> Use local development setup<br>";
echo "2. <strong>If no local MySQL:</strong> Install XAMPP/WAMP and start MySQL<br>";
echo "3. <strong>If production works:</strong> Can use live database (not recommended for testing)<br>";
echo "4. <strong>If nothing works:</strong> Install a local MySQL server<br>";
echo "</div>";

echo "<p style='text-align:center;'>";
echo "<a href='verify_system_status_test.php' style='background:#28a745;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;margin:5px;'>🔄 Back to System Status</a> ";
echo "<a href='admin_login.php' style='background:#007bff;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;margin:5px;'>🔐 Admin Login</a>";
echo "</p>";
?>