<?php
session_start();
require_once 'config.php';

// Generate CSRF token if not exists
if (empty($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}

echo "<h1>CSRF Token Test</h1>";
echo "<p>CSRF Token in Session: " . ($_SESSION[CSRF_TOKEN_NAME] ?? 'NOT SET') . "</p>";
echo "<p>CSRF Token Name Constant: " . CSRF_TOKEN_NAME . "</p>";

// Test form
?>
<form action="handleApplicationSubmit.php" method="POST">
    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo $_SESSION[CSRF_TOKEN_NAME]; ?>">
    <input type="text" name="first_name" value="Test" required>
    <input type="text" name="last_name" value="User" required>
    <input type="email" name="email" value="test@example.com" required>
    <input type="text" name="id_number" value="9901010001088" required>
    <input type="date" name="dob" value="1999-01-01" required>
    <input type="text" name="gender" value="Male" required>
    <input type="text" name="address" value="123 Test St" required>
    <input type="text" name="city" value="Test City" required>
    <input type="text" name="province" value="Gauteng" required>
    <input type="text" name="postal_code" value="1234" required>
    <input type="text" name="country" value="South Africa" required>
    <input type="text" name="phone" value="0123456789" required>
    <input type="text" name="program_choice_1" value="Computer Science" required>
    <input type="text" name="study_mode" value="Full-time" required>
    <input type="checkbox" name="terms_conditions" checked>
    <input type="checkbox" name="privacy_policy" checked>
    <button type="submit">Test CSRF Submission</button>
</form>