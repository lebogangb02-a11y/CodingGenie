<?php
require_once 'config.php';

try {
    // Create database connection
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    echo "<h2>Fix All Existing Users - Email Verification Issue</h2>";
    echo "<p>This script will identify and fix all users who were created before email verification was implemented.</p>";

    // Check if we should actually run the fix
    $runFix = isset($_GET['run']) && $_GET['run'] === 'true';

    // Find all users with verification issues
    echo "<h3>1. Scanning for Users with Verification Issues:</h3>";
    
    $stmt = $pdo->prepare("
        SELECT id, email, first_name, last_name, status, email_verified, verification_token, created_at 
        FROM users 
        WHERE email_verified = 0 OR status != 'active'
        ORDER BY created_at ASC
    ");
    $stmt->execute();
    $problematicUsers = $stmt->fetchAll();

    if (empty($problematicUsers)) {
        echo "<p style='color: green;'>✅ No users found with verification issues!</p>";
        echo "<p>All users in the system are properly verified and active.</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Found " . count($problematicUsers) . " users with verification issues:</p>";
        
        echo "<table border='1' cellpadding='10' cellspacing='0' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
        echo "<tr style='background: #f0f0f0;'>";
        echo "<th>ID</th><th>Email</th><th>Name</th><th>Status</th><th>Email Verified</th><th>Created</th><th>Issues</th>";
        echo "</tr>";
        
        foreach ($problematicUsers as $user) {
            $issues = [];
            if (!$user['email_verified']) $issues[] = "Not verified";
            if ($user['status'] !== 'active') $issues[] = "Status: " . $user['status'];
            
            echo "<tr>";
            echo "<td>" . $user['id'] . "</td>";
            echo "<td>" . htmlspecialchars($user['email']) . "</td>";
            echo "<td>" . htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) . "</td>";
            echo "<td>" . htmlspecialchars($user['status'] ?? 'NULL') . "</td>";
            echo "<td>" . ($user['email_verified'] ? 'Yes' : 'No') . "</td>";
            echo "<td>" . $user['created_at'] . "</td>";
            echo "<td style='color: red;'>" . implode(', ', $issues) . "</td>";
            echo "</tr>";
        }
        echo "</table>";

        if (!$runFix) {
            echo "<h3>2. Ready to Fix Issues:</h3>";
            echo "<p style='color: blue;'>📋 <strong>What will be fixed:</strong></p>";
            echo "<ul>";
            echo "<li>Set <code>email_verified = 1</code> for all unverified users</li>";
            echo "<li>Set <code>status = 'active'</code> for all inactive users</li>";
            echo "<li>Allow all existing users to login immediately</li>";
            echo "</ul>";
            
            echo "<p style='color: orange;'><strong>⚠️ This will affect " . count($problematicUsers) . " user accounts.</strong></p>";
            
            echo "<div style='margin: 20px 0; padding: 20px; background: #f9f9f9; border-radius: 5px;'>";
            echo "<h4>Choose an option:</h4>";
            echo "<p><a href='?run=true' style='background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>✅ Fix All Users</a>";
            echo "<a href='fix_existing_user.php' style='background: #007bff; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px;'>🔧 Fix Only My Account</a></p>";
            echo "</div>";
            
        } else {
            // Run the actual fix
            echo "<h3>2. Applying Fixes to All Users:</h3>";
            
            $stmt = $pdo->prepare("
                UPDATE users 
                SET email_verified = 1, status = 'active' 
                WHERE email_verified = 0 OR status != 'active'
            ");
            $result = $stmt->execute();
            $affectedRows = $stmt->rowCount();
            
            if ($result) {
                echo "<p style='color: green; font-weight: bold; font-size: 18px;'>✅ Successfully updated " . $affectedRows . " user accounts!</p>";
                
                // Verify the fixes
                echo "<h3>3. Verification of Fixes:</h3>";
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as total_users,
                           SUM(CASE WHEN email_verified = 1 THEN 1 ELSE 0 END) as verified_users,
                           SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_users
                    FROM users
                ");
                $stmt->execute();
                $stats = $stmt->fetch();
                
                echo "<ul>";
                echo "<li><strong>Total Users:</strong> " . $stats['total_users'] . "</li>";
                echo "<li><strong>Verified Users:</strong> " . $stats['verified_users'] . "</li>";
                echo "<li><strong>Active Users:</strong> " . $stats['active_users'] . "</li>";
                echo "</ul>";
                
                if ($stats['verified_users'] == $stats['total_users'] && $stats['active_users'] == $stats['total_users']) {
                    echo "<p style='color: green; font-weight: bold;'>🎉 All users are now verified and active!</p>";
                } else {
                    echo "<p style='color: orange;'>⚠️ Some users may still have issues. Please check manually.</p>";
                }
                
            } else {
                echo "<p style='color: red;'>❌ Failed to update user accounts!</p>";
            }
        }
    }

    // Show current system status
    echo "<hr>";
    echo "<h3>System Status Summary:</h3>";
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_users,
            SUM(CASE WHEN email_verified = 1 THEN 1 ELSE 0 END) as verified_count,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
            SUM(CASE WHEN email_verified = 0 OR status != 'active' THEN 1 ELSE 0 END) as problem_count
        FROM users
    ");
    $stmt->execute();
    $summary = $stmt->fetch();
    
    echo "<div style='display: flex; gap: 20px; margin: 20px 0;'>";
    echo "<div style='background: #e3f2fd; padding: 15px; border-radius: 5px; flex: 1;'>";
    echo "<h4>📊 Total Users</h4>";
    echo "<p style='font-size: 24px; margin: 0;'>" . $summary['total_users'] . "</p>";
    echo "</div>";
    
    echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 5px; flex: 1;'>";
    echo "<h4>✅ Verified</h4>";
    echo "<p style='font-size: 24px; margin: 0;'>" . $summary['verified_count'] . "</p>";
    echo "</div>";
    
    echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 5px; flex: 1;'>";
    echo "<h4>🟢 Active</h4>";
    echo "<p style='font-size: 24px; margin: 0;'>" . $summary['active_count'] . "</p>";
    echo "</div>";
    
    echo "<div style='background: " . ($summary['problem_count'] > 0 ? '#ffebee' : '#e8f5e8') . "; padding: 15px; border-radius: 5px; flex: 1;'>";
    echo "<h4>" . ($summary['problem_count'] > 0 ? '⚠️' : '✅') . " Issues</h4>";
    echo "<p style='font-size: 24px; margin: 0;'>" . $summary['problem_count'] . "</p>";
    echo "</div>";
    echo "</div>";

    if ($runFix && $summary['problem_count'] == 0) {
        echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 20px; border-radius: 5px; margin: 20px 0;'>";
        echo "<h4 style='color: #155724; margin-top: 0;'>🎉 All Done!</h4>";
        echo "<p style='color: #155724; margin-bottom: 0;'>All users can now login without email verification issues.</p>";
        echo "<p><a href='student-login.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Login Page</a></p>";
        echo "</div>";
    }

} catch (PDOException $e) {
    echo "<p style='color: red;'>Database error: " . $e->getMessage() . "</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>