<?php
session_start();
if (!isset($_SESSION['student_logged_in']) || $_SESSION['student_logged_in'] !== true) {
    header('Location: student-login.php');
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Simple Application</title>
</head>
<body>
    <h1>Simple Application Form</h1>
    <form action="handleApplicationSubmit.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? 'test'; ?>">
        
        <input type="text" name="first_name" placeholder="First Name" required>
        <input type="text" name="last_name" placeholder="Last Name" required>
        <input type="email" name="email" placeholder="Email" required>
        <input type="text" name="phone" placeholder="Phone" required>
        <input type="text" name="id_number" placeholder="ID Number" required>
        <input type="date" name="dob" required>
        <input type="text" name="gender" placeholder="Gender" required>
        <input type="text" name="address" placeholder="Address" required>
        <input type="text" name="city" placeholder="City" required>
        <input type="text" name="province" placeholder="Province" required>
        <input type="text" name="postal_code" placeholder="Postal Code" required>
        <input type="text" name="country" placeholder="Country" required>
        <input type="text" name="program_choice_1" placeholder="Program Choice" required>
        <input type="text" name="study_mode" placeholder="Study Mode" required>
        
        <input type="checkbox" name="terms_conditions" required> I agree to terms
        <input type="checkbox" name="privacy_policy" required> I agree to privacy policy
        
        <button type="submit">Submit Application</button>
    </form>
</body>
</html>