<?php
require_once 'config.php';

try {
    echo "<h3>Fixing Applications Table Structure</h3>";
    
    // 1. Connect to database
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    echo "✅ Database connected successfully<br>";
    
    // 2. Check if applications table has user_id column
    $columns = $pdo->query("DESCRIBE applications")->fetchAll(PDO::FETCH_COLUMN);
    echo "Current columns: " . implode(', ', $columns) . "<br>";
    
    if (!in_array('user_id', $columns)) {
        echo "❌ user_id column missing. Adding it...<br>";
        
        // Add user_id column
        $pdo->exec("ALTER TABLE applications ADD COLUMN user_id INT NULL");
        echo "✅ user_id column added<br>";
        
        // Add foreign key constraint if users table exists
        $usersTable = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
        if ($usersTable) {
            $pdo->exec("ALTER TABLE applications ADD CONSTRAINT fk_applications_user_id 
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL");
            echo "✅ Foreign key constraint added<br>";
        }
    } else {
        echo "✅ user_id column already exists<br>";
    }
    
    // 3. Now link the application to your user
    $email = 'lebogangb02@gmail.com';
    $app_ref = 'APP2025097459';
    
    // Get your user ID
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "✅ User found with ID: {$user['id']}<br>";
        
        // Update application with user_id
        $stmt = $pdo->prepare("UPDATE applications SET user_id = ? WHERE reference_number = ?");
        $stmt->execute([$user['id'], $app_ref]);
        
        if ($stmt->rowCount() > 0) {
            echo "✅ Application successfully linked to your account!<br>";
        } else {
            echo "⚠️ No rows updated. Application might not exist.<br>";
        }
    } else {
        echo "❌ User not found<br>";
    }
    
    echo "<br><h3>🎉 FIX COMPLETE!</h3>";
    echo "<p>Your application should now be linked to your user account.</p>";
    echo '<br><a href="student-login.php" style="padding: 10px 20px; background: #1a5fb4; color: white; text-decoration: none; border-radius: 5px;">Try Login Now</a>';
    
} catch (Exception $e) {
    echo "<h3>❌ Error:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    
    // Show more detailed error info
    echo "<h4>Debug Info:</h4>";
    echo "<pre>";
    print_r($e->getTraceAsString());
    echo "</pre>";
}
?>