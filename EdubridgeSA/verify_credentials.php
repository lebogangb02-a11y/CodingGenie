<?php
require_once 'config.php';

echo "<h2>Credential Verification</h2>";

// Check for lebogangb02@gmail.com
$email = 'lebogangb02@gmail.com';
$stmt = $pdo->prepare("SELECT email_address, reference_number, full_name, surname FROM applications WHERE email_address = ?");
$stmt->execute([$email]);
$application = $stmt->fetch(PDO::FETCH_ASSOC);

if ($application) {
    echo "<h3>Found Application:</h3>";
    echo "<p><strong>Email:</strong> " . htmlspecialchars($application['email_address']) . "</p>";
    echo "<p><strong>Reference Number:</strong> " . htmlspecialchars($application['reference_number']) . "</p>";
    echo "<p><strong>Name:</strong> " . htmlspecialchars($application['full_name']) . " " . htmlspecialchars($application['surname']) . "</p>";
    
    echo "<hr>";
    echo "<h3>Use these credentials to login:</h3>";
    echo "<p><strong>Email:</strong> " . htmlspecialchars($application['email_address']) . "</p>";
    echo "<p><strong>Reference Number:</strong> " . htmlspecialchars($application['reference_number']) . "</p>";
} else {
    echo "<p>No application found for email: " . htmlspecialchars($email) . "</p>";
    
    // Show all applications for debugging
    echo "<h3>All Applications in Database:</h3>";
    $stmt = $pdo->prepare("SELECT email_address, reference_number, full_name FROM applications LIMIT 10");
    $stmt->execute();
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($applications as $app) {
        echo "<p>" . htmlspecialchars($app['email_address']) . " - " . htmlspecialchars($app['reference_number']) . " - " . htmlspecialchars($app['full_name']) . "</p>";
    }
}
?>