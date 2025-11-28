<?php
require_once 'config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Database Status Check</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .debug-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .info { color: blue; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>";

echo "<h1>🔍 Database Status Check</h1>";

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<div class='debug-section'>";
    echo "<h2>1. 📊 All Applications in Database</h2>";
    
    $stmt = $pdo->query("SELECT id, email_address, status, reference_number, created_at FROM applications ORDER BY created_at DESC");
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($applications)) {
        echo "<p class='warning'>⚠️ No applications found in database</p>";
    } else {
        echo "<table>";
        echo "<tr><th>ID</th><th>Email</th><th>Status</th><th>Reference</th><th>Created</th></tr>";
        
        foreach ($applications as $app) {
            echo "<tr>";
            echo "<td>{$app['id']}</td>";
            echo "<td>{$app['email_address']}</td>";
            echo "<td><strong>{$app['status']}</strong></td>";
            echo "<td>{$app['reference_number']}</td>";
            echo "<td>{$app['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";
    
    echo "<div class='debug-section'>";
    echo "<h2>2. 👥 All Users in Database</h2>";
    
    $stmt = $pdo->query("SELECT id, email, first_name, last_name, status, email_verified FROM users ORDER BY id");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($users)) {
        echo "<p class='warning'>⚠️ No users found in database</p>";
    } else {
        echo "<table>";
        echo "<tr><th>ID</th><th>Email</th><th>Name</th><th>Status</th><th>Verified</th></tr>";
        
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>{$user['id']}</td>";
            echo "<td>{$user['email']}</td>";
            echo "<td>{$user['first_name']} {$user['last_name']}</td>";
            echo "<td>{$user['status']}</td>";
            echo "<td>" . ($user['email_verified'] ? 'Yes' : 'No') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";
    
    echo "<div class='debug-section'>";
    echo "<h2>3. 🔗 User-Application Join Test</h2>";
    
    $stmt = $pdo->query("
        SELECT u.id as user_id, u.email, u.first_name, u.last_name, u.status as user_status, u.email_verified,
               a.id as application_id, a.status as application_status, a.reference_number
        FROM users u 
        LEFT JOIN applications a ON u.email = a.email_address
        ORDER BY u.id
    ");
    $joined_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($joined_data)) {
        echo "<p class='warning'>⚠️ No joined data found</p>";
    } else {
        echo "<table>";
        echo "<tr><th>User ID</th><th>Email</th><th>Name</th><th>User Status</th><th>Verified</th><th>App ID</th><th>App Status</th><th>Reference</th></tr>";
        
        foreach ($joined_data as $row) {
            echo "<tr>";
            echo "<td>{$row['user_id']}</td>";
            echo "<td>{$row['email']}</td>";
            echo "<td>{$row['first_name']} {$row['last_name']}</td>";
            echo "<td>{$row['user_status']}</td>";
            echo "<td>" . ($row['email_verified'] ? 'Yes' : 'No') . "</td>";
            echo "<td>" . ($row['application_id'] ?? 'NULL') . "</td>";
            echo "<td><strong>" . ($row['application_status'] ?? 'NULL') . "</strong></td>";
            echo "<td>" . ($row['reference_number'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";
    
    echo "<div class='debug-section'>";
    echo "<h2>4. 🎯 Specific Email Check</h2>";
    
    // Check for the specific email from the screenshot
    $test_email = 'bonganidikgang98@gmail.com';
    
    echo "<h3>Testing with email: $test_email</h3>";
    
    $stmt = $pdo->prepare("
        SELECT u.id, u.student_id, u.first_name, u.last_name, u.email, u.password_hash, u.status as user_status, u.email_verified,
               a.status as application_status, a.id as application_id, a.reference_number
        FROM users u 
        LEFT JOIN applications a ON u.email = a.email_address
        WHERE u.email = ? AND u.status = 'active' AND u.email_verified = 1
    ");
    $stmt->execute([$test_email]);
    $login_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($login_data) {
        echo "<p class='success'>✅ Login query would return data</p>";
        echo "<table>";
        echo "<tr><th>Field</th><th>Value</th></tr>";
        foreach ($login_data as $key => $value) {
            $display_value = $value ?? 'NULL';
            if ($key === 'application_status') {
                echo "<tr><td><strong>$key</strong></td><td><strong>$display_value</strong></td></tr>";
            } else {
                echo "<tr><td>$key</td><td>$display_value</td></tr>";
            }
        }
        echo "</table>";
        
        if ($login_data['application_status'] === null) {
            echo "<p class='error'>❌ Application status is NULL - this is the problem!</p>";
            echo "<p class='info'>💡 This means the user exists but has no application record, or the JOIN is not finding a match.</p>";
        } else {
            echo "<p class='success'>✅ Application status found: {$login_data['application_status']}</p>";
        }
    } else {
        echo "<p class='error'>❌ Login query would return no data</p>";
        echo "<p class='info'>This could be because:</p>";
        echo "<ul>";
        echo "<li>User doesn't exist</li>";
        echo "<li>User status is not 'active'</li>";
        echo "<li>Email is not verified</li>";
        echo "</ul>";
    }
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<p class='error'>❌ Database error: " . $e->getMessage() . "</p>";
}

echo "</body></html>";
?>