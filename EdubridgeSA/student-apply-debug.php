<?php
// student-apply-debug.php - STEP BY STEP DEBUG
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<h2>🔧 DEBUG MODE - STEP BY STEP</h2>";
echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px;'>";

// Step 1: Session
echo "<h3>Step 1: Testing Session</h3>";
try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
        echo "✅ Session started successfully<br>";
    } else {
        echo "✅ Session already active<br>";
    }
    echo "Session ID: " . session_id() . "<br>";
    echo "Session Status: " . session_status() . "<br>";
} catch (Exception $e) {
    die("❌ Session failed: " . $e->getMessage());
}

// Step 2: Check if logged in
echo "<h3>Step 2: Checking Login Status</h3>";
if (!isset($_SESSION['student_logged_in']) || $_SESSION['student_logged_in'] !== true) {
    die("❌ Not logged in. Student logged in: " . ($_SESSION['student_logged_in'] ?? 'NOT SET'));
}
echo "✅ Logged in successfully<br>";
echo "Student ID: " . ($_SESSION['student_id'] ?? 'NOT SET') . "<br>";

// Step 3: Test config.php
echo "<h3>Step 3: Testing config.php</h3>";
if (!file_exists('config.php')) {
    die("❌ config.php file not found");
}

try {
    require_once 'config.php';
    echo "✅ config.php loaded successfully<br>";
    echo "DB_HOST: " . DB_HOST . "<br>";
    echo "DB_NAME: " . DB_NAME . "<br>";
    echo "DB_USER: " . DB_USER . "<br>";
} catch (Exception $e) {
    die("❌ config.php failed: " . $e->getMessage());
}

// Step 4: Test database connection
echo "<h3>Step 4: Testing Database Connection</h3>";
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Database connected successfully<br>";
    
    // Test a simple query
    $stmt = $pdo->query("SELECT 1 as test");
    $result = $stmt->fetch();
    echo "✅ Database query test passed<br>";
} catch (PDOException $e) {
    die("❌ Database connection failed: " . $e->getMessage());
}

// Step 5: Test session_config.php
echo "<h3>Step 5: Testing session_config.php</h3>";
if (file_exists('session_config.php')) {
    try {
        require_once 'session_config.php';
        echo "✅ session_config.php loaded successfully<br>";
    } catch (Exception $e) {
        die("❌ session_config.php failed: " . $e->getMessage());
    }
} else {
    echo "⚠️ session_config.php not found - skipping<br>";
}

// Step 6: Test security-utils.php
echo "<h3>Step 6: Testing security-utils.php</h3>";
if (file_exists('security-utils.php')) {
    try {
        require_once 'security-utils.php';
        echo "✅ security-utils.php loaded successfully<br>";
    } catch (Exception $e) {
        die("❌ security-utils.php failed: " . $e->getMessage());
    }
} else {
    echo "⚠️ security-utils.php not found - skipping<br>";
}

// Step 7: Test application data retrieval
echo "<h3>Step 7: Testing Application Data</h3>";
try {
    $student_id = $_SESSION['student_id'];
    $stmt = $pdo->prepare("SELECT * FROM applications WHERE student_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$student_id]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($application) {
        echo "✅ Application found - ID: " . $application['id'] . "<br>";
    } else {
        echo "⚠️ No application found for student ID: $student_id<br>";
    }
} catch (Exception $e) {
    die("❌ Application data retrieval failed: " . $e->getMessage());
}

// Step 8: Test universities data
echo "<h3>Step 8: Testing Universities Data</h3>";
try {
    $stmt = $pdo->query("SELECT * FROM universities ORDER BY name");
    $universities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "✅ Universities loaded: " . count($universities) . " found<br>";
} catch (Exception $e) {
    die("❌ Universities data failed: " . $e->getMessage());
}

echo "<h3 style='color: green;'>🎉 ALL TESTS PASSED SUCCESSFULLY!</h3>";
echo "</div>";

// Now include the actual form HTML
echo "<h2>Loading Form...</h2>";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Application - Debug Version</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .required::after { content: ' *'; color: red; }
        button { background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
    </style>
</head>
<body>
    <h1>Edit Application - Debug Version</h1>
    
    <form method="POST">
        <input type="hidden" name="debug_test" value="1">
        
        <div class="form-group">
            <label class="required">Full Name</label>
            <input type="text" name="full_name" value="<?php echo htmlspecialchars($application['full_name'] ?? ''); ?>" required>
        </div>
        
        <div class="form-group">
            <label class="required">Surname</label>
            <input type="text" name="surname" value="<?php echo htmlspecialchars($application['surname'] ?? ''); ?>" required>
        </div>
        
        <div class="form-group">
            <label class="required">Email</label>
            <input type="email" name="email_address" value="<?php echo htmlspecialchars($application['email_address'] ?? ''); ?>" required>
        </div>
        
        <button type="submit">Test Save</button>
    </form>
    
    <div style="margin-top: 30px; padding: 15px; background: #f5f5f5;">
        <h3>Application Data:</h3>
        <pre><?php print_r($application ?? []); ?></pre>
    </div>
</body>
</html>