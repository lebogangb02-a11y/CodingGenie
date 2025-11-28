<?php
/**
 * Simple Application Submission Test
 * This script tests the actual application submission process
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/simple_test_log.txt');

echo "<h1>Simple Application Submission Test</h1>\n";
echo "<p><strong>Timestamp:</strong> " . date('Y-m-d H:i:s') . "</p>\n";

// Load session first to ensure CSRF token is generated
require_once 'session_config.php';
// Include configuration
require_once 'config.php';

echo "<h2>1. Configuration and Session Test</h2>\n";
echo "<p style='color: green;'>✓ Configuration loaded successfully</p>\n";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>\n";
echo "<p><strong>CSRF Token:</strong> " . substr($_SESSION[CSRF_TOKEN_NAME], 0, 10) . "...</p>\n";

// Test database connection
echo "<h2>2. Database Connection Test</h2>\n";
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }
    
    $conn->set_charset('utf8mb4');
    echo "<p style='color: green;'>✓ Database connection successful</p>\n";
    
    // Check applications table
    $result = $conn->query("SHOW TABLES LIKE 'applications'");
    if ($result->num_rows > 0) {
        echo "<p style='color: green;'>✓ Applications table exists</p>\n";
    } else {
        echo "<p style='color: red;'>✗ Applications table does not exist</p>\n";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Database error: " . $e->getMessage() . "</p>\n";
    exit;
}

// Simulate a real form submission by setting up $_POST and $_FILES
echo "<h2>3. Simulating Real Form Submission</h2>\n";

// Set up $_POST data exactly as it would come from the form
$_POST = [
    CSRF_TOKEN_NAME => $_SESSION[CSRF_TOKEN_NAME],
    'first_name' => 'Test',
    'last_name' => 'User',
    'middle_name' => '',
    'id_number' => '1234567890123',
    'dob' => '1995-01-01',
    'gender' => 'Male',
    'nationality' => 'South African',
    'title' => 'Mr',
    'home_language' => 'English',
    'marital_status' => 'Single',
    'passport_number' => '',
    'address' => '123 Test Street',
    'city' => 'Cape Town',
    'province' => 'Western Cape',
    'postal_code' => '8000',
    'phone' => '0123456789',
    'alternative_phone' => '',
    'email' => 'test@example.com',
    'country' => 'South Africa',
    'emergency_name' => 'Emergency Contact',
    'emergency_relationship' => 'Parent',
    'emergency_phone' => '0987654321',
    'emergency_email' => 'emergency@example.com',
    'matric_year' => '2020',
    'aps' => '35',
    'high_school_name' => 'Test High School',
    'maths_level' => 'Mathematics',
    'english_level' => 'English Home Language',
    'additional_qualifications' => 'None',
    'program_choice_1' => 'Bachelor of Arts',
    'program_choice_2' => 'Bachelor of Science',
    'study_mode' => 'Full-time',
    'motivation' => 'I am passionate about learning.',
    'has_disability' => 'no',
    'disability_details' => '',
    'previous_tertiary' => 'no',
    'previous_tertiary_details' => '',
    'terms_conditions' => '1',
    'privacy_policy' => '1',
    'marketing_consent' => '0'
];

// Set up $_FILES data (empty files)
$_FILES = [
    'id_document' => [
        'name' => '',
        'type' => '',
        'tmp_name' => '',
        'error' => UPLOAD_ERR_NO_FILE,
        'size' => 0
    ],
    'matric_certificate' => [
        'name' => '',
        'type' => '',
        'tmp_name' => '',
        'error' => UPLOAD_ERR_NO_FILE,
        'size' => 0
    ],
    'proof_of_payment' => [
        'name' => '',
        'type' => '',
        'tmp_name' => '',
        'error' => UPLOAD_ERR_NO_FILE,
        'size' => 0
    ],
    'additional_documents' => [
        'name' => [''],
        'type' => [''],
        'tmp_name' => [''],
        'error' => [UPLOAD_ERR_NO_FILE],
        'size' => [0]
    ]
];

// Set REQUEST_METHOD to POST
$_SERVER['REQUEST_METHOD'] = 'POST';

echo "<p style='color: green;'>✓ POST and FILES data set up</p>\n";
echo "<p><strong>Form fields count:</strong> " . count($_POST) . "</p>\n";

// Now test the actual submission process step by step
echo "<h2>4. Testing Submission Process</h2>\n";

// Test 1: CSRF validation
echo "<h3>4.1 CSRF Token Validation</h3>\n";
if (!isset($_POST[CSRF_TOKEN_NAME]) || !isset($_SESSION[CSRF_TOKEN_NAME])) {
    echo "<p style='color: red;'>✗ CSRF token missing</p>\n";
} elseif ($_POST[CSRF_TOKEN_NAME] !== $_SESSION[CSRF_TOKEN_NAME]) {
    echo "<p style='color: red;'>✗ Invalid CSRF token</p>\n";
} else {
    echo "<p style='color: green;'>✓ CSRF token validation passed</p>\n";
}

// Test 2: Include and test functions from handler
echo "<h3>4.2 Loading Handler Functions</h3>\n";

// Create a temporary file with just the functions
$handlerContent = file_get_contents('handleApplicationSubmit.php');

// Extract everything before the main processing logic (which starts with "// Initialize response")
$functionsPart = strstr($handlerContent, '// Initialize response', true);

if ($functionsPart) {
    // Remove the opening PHP tag and includes to avoid conflicts
    // Use a simpler approach to remove the header
    $lines = explode("\n", $functionsPart);
    $cleanLines = [];
    $skipLines = true;
    
    foreach ($lines as $line) {
        // Skip until we get past the includes
        if ($skipLines) {
            if (strpos($line, 'use PHPMailer') !== false || 
                strpos($line, 'require_once') !== false || 
                strpos($line, 'include_once') !== false ||
                strpos($line, '<?php') !== false) {
                continue;
            }
            $skipLines = false;
        }
        $cleanLines[] = $line;
    }
    
    $functionsPart = implode("\n", $cleanLines);
    
    // Evaluate the functions
    eval($functionsPart);
    echo "<p style='color: green;'>✓ Handler functions loaded successfully</p>\n";
    
    // Verify functions are actually loaded
    $functionsLoaded = [];
    if (function_exists('validateFormData')) $functionsLoaded[] = 'validateFormData';
    if (function_exists('handleFileUploads')) $functionsLoaded[] = 'handleFileUploads';
    if (function_exists('saveApplicationData')) $functionsLoaded[] = 'saveApplicationData';
    if (function_exists('sendEmailNotification')) $functionsLoaded[] = 'sendEmailNotification';
    
    echo "<p><strong>Functions loaded:</strong> " . implode(', ', $functionsLoaded) . "</p>\n";
    
} else {
    echo "<p style='color: red;'>✗ Could not extract functions from handler</p>\n";
    exit;
}

// Test 3: Form validation
echo "<h3>4.3 Form Validation</h3>\n";
if (function_exists('validateFormData')) {
    $validationErrors = validateFormData($_POST);
    if (empty($validationErrors)) {
        echo "<p style='color: green;'>✓ Form validation passed</p>\n";
    } else {
        echo "<p style='color: red;'>✗ Form validation failed:</p>\n";
        echo "<ul>\n";
        foreach ($validationErrors as $field => $error) {
            echo "<li><strong>{$field}:</strong> " . htmlspecialchars($error) . "</li>\n";
        }
        echo "</ul>\n";
    }
} else {
    echo "<p style='color: red;'>✗ validateFormData function not found</p>\n";
}

// Test 4: File upload handling
echo "<h3>4.4 File Upload Handling</h3>\n";
if (function_exists('handleFileUploads')) {
    $uploadResult = handleFileUploads($_FILES);
    if (isset($uploadResult['errors']) && empty($uploadResult['errors'])) {
        echo "<p style='color: green;'>✓ File upload handling passed (no files to upload)</p>\n";
    } else {
        echo "<p style='color: orange;'>⚠ File upload handling completed with notes:</p>\n";
        if (isset($uploadResult['errors']) && !empty($uploadResult['errors'])) {
            echo "<ul>\n";
            foreach ($uploadResult['errors'] as $field => $error) {
                echo "<li><strong>{$field}:</strong> " . htmlspecialchars($error) . "</li>\n";
            }
            echo "</ul>\n";
        }
    }
} else {
    echo "<p style='color: red;'>✗ handleFileUploads function not found</p>\n";
}

// Test 5: Database save
echo "<h3>4.5 Database Save Test</h3>\n";
if (function_exists('saveApplicationData')) {
    try {
        $uploadedFiles = ['files' => []];
        $applicationId = saveApplicationData($conn, $_POST, $uploadedFiles);
        
        if ($applicationId) {
            echo "<p style='color: green;'>✓ Application data saved successfully</p>\n";
            echo "<p><strong>Application ID:</strong> {$applicationId}</p>\n";
            
            // Test 6: Email notification
            echo "<h3>4.6 Email Notification Test</h3>\n";
            if (function_exists('sendEmailNotification')) {
                try {
                    $emailResult = sendEmailNotification($_POST, $applicationId);
                    if ($emailResult) {
                        echo "<p style='color: green;'>✓ Email notification sent successfully</p>\n";
                    } else {
                        echo "<p style='color: red;'>✗ Email notification failed</p>\n";
                    }
                } catch (Exception $e) {
                    echo "<p style='color: red;'>✗ Email notification error: " . $e->getMessage() . "</p>\n";
                }
            } else {
                echo "<p style='color: red;'>✗ sendEmailNotification function not found</p>\n";
            }
            
        } else {
            echo "<p style='color: red;'>✗ Failed to save application data</p>\n";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Error saving application data: " . $e->getMessage() . "</p>\n";
    }
} else {
    echo "<p style='color: red;'>✗ saveApplicationData function not found</p>\n";
}

echo "<h2>5. Summary</h2>\n";
echo "<p>This test simulates the exact same process as a real form submission.</p>\n";
echo "<p>If all tests pass, the issue might be with the actual form configuration or how it submits data.</p>\n";
echo "<p>Check the log file: simple_test_log.txt for detailed error information.</p>\n";

if (isset($conn)) {
    $conn->close();
}

echo "<p><strong>Test completed at:</strong> " . date('Y-m-d H:i:s') . "</p>\n";
?>