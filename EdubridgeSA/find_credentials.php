<?php
// find_credentials.php - Test database credentials
session_start();
$_SESSION['admin_logged_in'] = true;

echo "<h3>Testing Database Credentials</h3>";

// Common credential combinations to test
$testCombinations = [
    // Your current combination
    [
        'host' => 'localhost',
        'dbname' => 'u839420047_applications',
        'username' => 'u839420047_applications_user', 
        'password' => 'Eduweb@2024',
        'description' => 'Current credentials'
    ],
    // Alternative username patterns
    [
        'host' => 'localhost',
        'dbname' => 'u839420047_applications',
        'username' => 'u957103154_eduweb_user',
        'password' => 'Eduweb@2024',
        'description' => 'Alternative username pattern'
    ],
    [
        'host' => 'localhost',
        'dbname' => 'u839420047_applications',
        'username' => 'u839420047_eduweb_user',
        'password' => 'Eduweb@2024',
        'description' => 'Another username pattern'
    ],
    // Try without user suffix
    [
        'host' => 'localhost',
        'dbname' => 'u839420047_applications',
        'username' => 'u839420047',
        'password' => 'Eduweb@2024',
        'description' => 'Without _user suffix'
    ],
    // Try different database name
    [
        'host' => 'localhost',
        'dbname' => 'u957103154_eduweb',
        'username' => 'u957103154_eduweb_user',
        'password' => 'Eduweb@2024',
        'description' => 'Different database name'
    ]
];

foreach ($testCombinations as $combo) {
    echo "<div style='margin: 10px; padding: 10px; border: 1px solid #ccc;'>";
    echo "<strong>Testing:</strong> {$combo['description']}<br>";
    echo "DB: {$combo['dbname']}, User: {$combo['username']}<br>";
    
    try {
        $pdo = new PDO(
            "mysql:host={$combo['host']};dbname={$combo['dbname']};charset=utf8mb4",
            $combo['username'],
            $combo['password']
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Test query
        $tables = $pdo->query("SHOW TABLES")->rowCount();
        echo "✅ <span style='color: green;'><strong>SUCCESS!</strong> Connected to database. Found {$tables} tables.</span><br>";
        
        // Check if applications table exists
        $appsTable = $pdo->query("SHOW TABLES LIKE 'applications'")->rowCount();
        echo $appsTable > 0 ? "✅ Applications table exists<br>" : "⚠️ Applications table not found<br>";
        
        $pdo = null;
        
    } catch (PDOException $e) {
        echo "❌ <span style='color: red;'>FAILED: " . $e->getMessage() . "</span><br>";
    }
    
    echo "</div>";
}

echo "<hr><h4>Next Steps:</h4>";
echo "1. Look for the SUCCESS message above<br>";
echo "2. Use those credentials in your config.php file<br>";
echo "3. Delete this file after use for security";
?>