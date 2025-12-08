<?php
require_once 'config.php';

try {
    $email = 'lebogangb02@gmail.com';
    $password = 'password123'; // Change this to your preferred password
    $app_ref = 'APP2025097459';

    echo "<h3>Setting up student account for: $email</h3>";

    // 1. Check database connection
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

    // 2. Check if users table exists
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
    if (!$tableCheck) {
        echo "❌ Users table doesn't exist. Creating it...<br>";

        // Create users table
        $pdo->exec("
            CREATE TABLE users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id VARCHAR(20) UNIQUE,
                email VARCHAR(255) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                first_name VARCHAR(100),
                last_name VARCHAR(100),
                phone VARCHAR(20),
                date_of_birth DATE,
                id_number VARCHAR(13),
                school_university VARCHAR(255),
                address TEXT,
                city VARCHAR(100),
                province VARCHAR(50),
                postal_code VARCHAR(10),
                profile_picture VARCHAR(500),
                status ENUM('active','inactive','pending') DEFAULT 'active',
                email_verified TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                last_login TIMESTAMP NULL
            )
        ");
        echo "✅ Users table created<br>";
    }

    // 3. Check if user exists
    $stmt = $pdo->prepare("SELECT id, email, password_hash, role, created_at FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing_user) {
        echo "✅ User already exists! Updating password...<br>";

        // Update existing user
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, status = 'active', email_verified = 1 WHERE email = ?");
        $stmt->execute([$password_hash, $email]);

        echo "✅ Password updated!<br>";
        $student_id = $existing_user['student_id'];
    } else {
        echo "Creating new user account...<br>";

        // 4. Create new user
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $student_id = 'STU' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare("
            INSERT INTO users (email, password_hash, student_id, first_name, last_name, status, email_verified, created_at) 
            VALUES (?, ?, ?, 'Bongani', 'Dikgang', 'active', 1, NOW())
        ");
        $stmt->execute([$email, $password_hash, $student_id]);

        echo "✅ User account created!<br>";
        echo "Student ID: $student_id<br>";
    }

    // 5. Check if applications table exists
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'applications'")->fetch();
    if (!$tableCheck) {
        echo "❌ Applications table doesn't exist. Creating it...<br>";

        // Create applications table
        $pdo->exec("
            CREATE TABLE applications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email_address VARCHAR(255) NOT NULL,
                reference_number VARCHAR(20) UNIQUE,
                status VARCHAR(50) DEFAULT 'draft',
                user_id INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            )
        ");
        echo "✅ Applications table created<br>";
    }

    // 6. Link application to user
    $stmt = $pdo->prepare("SELECT id FROM applications WHERE email_address = ? AND reference_number = ?");
    $stmt->execute([$email, $app_ref]);
    $application = $stmt->fetch();

    if ($application) {
        echo "✅ Application found: $app_ref<br>";

        // Get user ID
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Update application with user_id
            $stmt = $pdo->prepare("UPDATE applications SET user_id = ? WHERE reference_number = ?");
            $stmt->execute([$user['id'], $app_ref]);
            echo "✅ Application linked to user account!<br>";
        }
    } else {
        echo "⚠️ No application found with reference: $app_ref<br>";
        echo "Creating new application...<br>";

        // Get user ID
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Create a new application
            $stmt = $pdo->prepare("
                INSERT INTO applications (email_address, reference_number, status, user_id, created_at, updated_at) 
                VALUES (?, ?, 'draft', ?, NOW(), NOW())
            ");
            $stmt->execute([$email, $app_ref, $user['id']]);
            echo "✅ New application created!<br>";
        }
    }

    echo "<br><h3>🎉 ACCOUNT SETUP COMPLETE!</h3>";
    echo "<p><strong>Email:</strong> $email</p>";
    echo "<p><strong>Password:</strong> $password</p>";
    echo "<p><strong>Student ID:</strong> $student_id</p>";
    echo "<p><strong>Reference:</strong> $app_ref</p>";

    echo "<br><h4>You can now login with:</h4>";
    echo "<p>✅ <strong>Password method:</strong> $email / $password</p>";
    echo "<p>✅ <strong>Reference method:</strong> $email / $app_ref</p>";

    echo '<br><a href="student-login.php" style="padding: 10px 20px; background: #1a5fb4; color: white; text-decoration: none; border-radius: 5px;">Go to Login</a>';
} catch (Exception $e) {
    echo "<h3>❌ Error:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<p>Check your database credentials in config.php</p>";
}
