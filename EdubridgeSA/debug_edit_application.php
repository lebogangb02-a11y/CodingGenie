<?php
session_start();
require_once 'config.php';
require_once 'security-utils.php';

echo "<h2>Edit Application Debug Test</h2>";

// Test 1: Check if SecurityUtils class exists
echo "<h3>Test 1: SecurityUtils Class</h3>";
if (class_exists('SecurityUtils')) {
    echo "✅ SecurityUtils class exists<br>";
} else {
    echo "❌ SecurityUtils class NOT found<br>";
}

// Test 2: Check if isActionAllowed method exists
echo "<h3>Test 2: isActionAllowed Method</h3>";
if (method_exists('SecurityUtils', 'isActionAllowed')) {
    echo "✅ isActionAllowed method exists<br>";
} else {
    echo "❌ isActionAllowed method NOT found<br>";
}

// Test 3: Test isActionAllowed function with different statuses
echo "<h3>Test 3: isActionAllowed Function Tests</h3>";
$test_cases = [
    ['edit', 'draft'],
    ['edit', 'documents_pending'],
    ['edit', 'submitted'],
    ['edit', 'under_review'],
    ['edit', 'approved'],
    ['view', 'submitted']
];

foreach ($test_cases as $case) {
    $action = $case[0];
    $status = $case[1];
    try {
        $result = SecurityUtils::isActionAllowed($action, $status);
        echo "isActionAllowed('$action', '$status') = " . ($result ? 'TRUE' : 'FALSE') . "<br>";
    } catch (Exception $e) {
        echo "❌ Error testing isActionAllowed('$action', '$status'): " . $e->getMessage() . "<br>";
    }
}

// Test 4: Check session data
echo "<h3>Test 4: Session Data</h3>";
if (isset($_SESSION['student_id'])) {
    echo "Student ID: " . $_SESSION['student_id'] . "<br>";
} else {
    echo "❌ No student_id in session<br>";
}

if (isset($_SESSION['application_status'])) {
    echo "Application Status: " . $_SESSION['application_status'] . "<br>";
} else {
    echo "❌ No application_status in session<br>";
}

// Test 5: Database connection test
echo "<h3>Test 5: Database Connection</h3>";
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Database connection successful<br>";
    
    // Test query for application
    if (isset($_SESSION['student_id'])) {
        $stmt = $pdo->prepare("SELECT id, status FROM applications WHERE id = ?");
        $stmt->execute([$_SESSION['student_id']]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($application) {
            echo "✅ Application found: ID=" . $application['id'] . ", Status=" . $application['status'] . "<br>";
            
            // Test the actual validation that student-apply.php does
            $application_status = $application['status'];
            echo "<h3>Test 6: Actual Validation Logic</h3>";
            
            // Test the status check from student-apply.php line 20-25
            if (!in_array($application_status, ['draft', 'documents_pending', 'submitted'])) {
                echo "❌ Status check 1 FAILED: Status '$application_status' not in allowed list<br>";
            } else {
                echo "✅ Status check 1 PASSED: Status '$application_status' is allowed<br>";
            }
            
            // Test the SecurityUtils::isActionAllowed check from line 82
            try {
                $action_allowed = SecurityUtils::isActionAllowed('edit', $application_status);
                if (!$action_allowed) {
                    echo "❌ Status check 2 FAILED: isActionAllowed('edit', '$application_status') returned FALSE<br>";
                } else {
                    echo "✅ Status check 2 PASSED: isActionAllowed('edit', '$application_status') returned TRUE<br>";
                }
            } catch (Exception $e) {
                echo "❌ Status check 2 ERROR: " . $e->getMessage() . "<br>";
            }
            
        } else {
            echo "❌ No application found for student_id: " . $_SESSION['student_id'] . "<br>";
        }
    }
    
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "<br>";
}

// Test 6: Check if student-apply.php file exists and is readable
echo "<h3>Test 7: File Access</h3>";
if (file_exists('student-apply.php')) {
    echo "✅ student-apply.php exists<br>";
    if (is_readable('student-apply.php')) {
        echo "✅ student-apply.php is readable<br>";
    } else {
        echo "❌ student-apply.php is NOT readable<br>";
    }
} else {
    echo "❌ student-apply.php does NOT exist<br>";
}

echo "<h3>Test Complete</h3>";
echo "<p><a href='student-dashboard.php'>Back to Dashboard</a></p>";
?>