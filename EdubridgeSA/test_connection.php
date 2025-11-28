<?php
/**
 * Test Database Connection
 */
echo "<h2>Testing Database Connection</h2>";

try {
    require_once 'config.php';
    
    echo "✅ Config file loaded successfully<br>";
    
    $pdo = getDBConnection();
    echo "✅ Database connection successful!<br>";
    
    // Test query
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM applications");
    $result = $stmt->fetch();
    echo "✅ Applications table exists with " . $result['count'] . " records<br>";
    
    echo "<h3>✅ All tests passed! Database is working correctly.</h3>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "Please check your database credentials in config.php";
}
?>