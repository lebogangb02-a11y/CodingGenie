<?php
require_once 'config.php';
require_once 'security-utils.php';

echo "<h2>Detailed Login Debug</h2>";

$test_email = 'lebogangb02@gmail.com';
$test_ref = 'APP2025097459';

echo "<h3>Testing Credentials:</h3>";
echo "<p><strong>Email:</strong> " . htmlspecialchars($test_email) . "</p>";
echo "<p><strong>Reference:</strong> " . htmlspecialchars($test_ref) . "</p>";

echo "<hr>";

// Step 1: Check if application exists with exact match
echo "<h3>Step 1: Direct Database Query</h3>";
$stmt = $pdo->prepare("SELECT id, email_address, reference_number, full_name, status, created_at FROM applications WHERE email_address = ? AND reference_number = ?");
$stmt->execute([$test_email, $test_ref]);
$application = $stmt->fetch(PDO::FETCH_ASSOC);

if ($application) {
    echo "<p style='color: green;'>✅ FOUND: Application exists with exact match!</p>";
    echo "<pre>" . print_r($application, true) . "</pre>";
} else {
    echo "<p style='color: red;'>❌ NOT FOUND: No exact match found</p>";
}

echo "<hr>";

// Step 2: Check email validation
echo "<h3>Step 2: Email Validation Test</h3>";
try {
    $validated_email = SecurityUtils::validateEmail($test_email);
    if ($validated_email) {
        echo "<p style='color: green;'>✅ Email validation passed: " . htmlspecialchars($validated_email) . "</p>";
    } else {
        echo "<p style='color: red;'>❌ Email validation failed</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Email validation error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";

// Step 3: Check reference number sanitization
echo "<h3>Step 3: Reference Number Sanitization Test</h3>";
try {
    $sanitized_ref = SecurityUtils::sanitizeInput(['ref' => $test_ref])['ref'];
    echo "<p style='color: green;'>✅ Reference sanitized: " . htmlspecialchars($sanitized_ref) . "</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Reference sanitization error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";

// Step 4: Check for similar applications
echo "<h3>Step 4: Similar Applications Check</h3>";
$stmt = $pdo->prepare("SELECT email_address, reference_number, full_name FROM applications WHERE email_address LIKE ? OR reference_number LIKE ? LIMIT 50");
$stmt->execute(['%' . $test_email . '%', '%' . $test_ref . '%']);
$similar = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($similar) {
    echo "<p>Found similar applications:</p>";
    foreach ($similar as $app) {
        echo "<p>Email: " . htmlspecialchars($app['email_address']) . " | Ref: " . htmlspecialchars($app['reference_number']) . " | Name: " . htmlspecialchars($app['full_name']) . "</p>";
    }
} else {
    echo "<p>No similar applications found</p>";
}

echo "<hr>";

// Step 5: Check all applications for this email
echo "<h3>Step 5: All Applications for This Email</h3>";
$stmt = $pdo->prepare("SELECT id, email_address, reference_number, full_name, status FROM applications WHERE email_address = ? LIMIT 200");
$stmt->execute([$test_email]);
$email_apps = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($email_apps) {
    echo "<p>Found " . count($email_apps) . " application(s) for this email:</p>";
    foreach ($email_apps as $app) {
        echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 5px;'>";
        echo "<p><strong>ID:</strong> " . htmlspecialchars($app['id']) . "</p>";
        echo "<p><strong>Email:</strong> " . htmlspecialchars($app['email_address']) . "</p>";
        echo "<p><strong>Reference:</strong> " . htmlspecialchars($app['reference_number']) . "</p>";
        echo "<p><strong>Name:</strong> " . htmlspecialchars($app['full_name'] ?? 'N/A') . "</p>";
        echo "<p><strong>Status:</strong> " . htmlspecialchars($app['status'] ?? 'N/A') . "</p>";
        echo "</div>";
    }
} else {
    echo "<p style='color: red;'>❌ No applications found for this email!</p>";
}

echo "<hr>";

// Step 6: Test the exact login logic
echo "<h3>Step 6: Simulate Login Process</h3>";
try {
    // Validate email
    $validated_email = SecurityUtils::validateEmail($test_email);
    if (!$validated_email) {
        throw new Exception('Email validation failed');
    }

    // Sanitize reference
    $sanitized_ref = SecurityUtils::sanitizeInput(['ref' => $test_ref])['ref'];

    // Query database
    $stmt = $pdo->prepare("SELECT id, email_address, reference_number, full_name, status FROM applications WHERE email_address = ? AND reference_number = ?");
    $stmt->execute([$validated_email, $sanitized_ref]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        echo "<p style='color: green; font-size: 18px;'>🎉 LOGIN SHOULD WORK! Application found successfully!</p>";
        echo "<p><strong>Application ID:</strong> " . htmlspecialchars($result['id']) . "</p>";
        echo "<p><strong>Student Name:</strong> " . htmlspecialchars($result['full_name'] ?? 'N/A') . "</p>";
    } else {
        echo "<p style='color: red; font-size: 18px;'>❌ LOGIN WILL FAIL - No matching application found</p>";
        echo "<p>Validated Email: " . htmlspecialchars($validated_email) . "</p>";
        echo "<p>Sanitized Reference: " . htmlspecialchars($sanitized_ref) . "</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Login simulation error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<h3>Database Connection Info</h3>";
echo "<p>Connected to database successfully</p>";
echo "<p>PDO Driver: " . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . "</p>";
