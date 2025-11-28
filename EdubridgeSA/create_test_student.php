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

    echo "Database connection successful!\n";

    // Check if test student already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute(['test@student.com']);
    $existingUser = $stmt->fetch();

    if ($existingUser) {
        echo "Test student already exists. Updating password...\n";
        
        // Update existing user
        $hashedPassword = password_hash('123456', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, status = 'active' WHERE email = ?");
        $stmt->execute([$hashedPassword, 'test@student.com']);
        
        echo "Test student password updated successfully!\n";
    } else {
        echo "Creating new test student...\n";
        
        // Create new test student
        $hashedPassword = password_hash('123456', PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("
            INSERT INTO users (
                email, 
                password_hash, 
                first_name, 
                last_name, 
                student_id, 
                status, 
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            'test@student.com',
            $hashedPassword,
            'Test',
            'Student',
            'TEST001',
            'active'
        ]);
        
        echo "Test student created successfully!\n";
    }

    // Verify the test student
    $stmt = $pdo->prepare("SELECT id, email, first_name, last_name, student_id, status FROM users WHERE email = ?");
    $stmt->execute(['test@student.com']);
    $user = $stmt->fetch();

    if ($user) {
        echo "\nTest student details:\n";
        echo "ID: " . $user['id'] . "\n";
        echo "Email: " . $user['email'] . "\n";
        echo "Name: " . $user['first_name'] . " " . $user['last_name'] . "\n";
        echo "Student ID: " . $user['student_id'] . "\n";
        echo "Status: " . $user['status'] . "\n";
        
        // Test password verification
        $testPassword = '123456';
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE email = ?");
        $stmt->execute(['test@student.com']);
        $passwordData = $stmt->fetch();
        
        if (password_verify($testPassword, $passwordData['password_hash'])) {
            echo "Password verification: SUCCESS ✓\n";
        } else {
            echo "Password verification: FAILED ✗\n";
        }
    } else {
        echo "Error: Could not retrieve test student data.\n";
    }

} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>