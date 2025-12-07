<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'application_error.log');

require_once 'config.php';
require_once 'session_config.php';

// Debug-only guard
if (!defined('DEBUG_MODE') || DEBUG_MODE !== true) {
    http_response_code(404);
    exit;
}

echo "<h1>Application Submission Debug</h1>";

// Check upload directory
echo "<h2>1. Checking Upload Directory</h2>";
$upload_dir = 'uploads';
if (!file_exists($upload_dir)) {
    echo "<p style='color: red;'>❌ Upload directory does not exist. Creating it now...</p>";
    if (mkdir($upload_dir, 0755, true)) {
        echo "<p style='color: green;'>✅ Upload directory created successfully.</p>";
    } else {
        echo "<p style='color: red;'>❌ Failed to create upload directory. Please check permissions.</p>";
    }
} else {
    echo "<p style='color: green;'>✅ Upload directory exists.</p>";
}

// Check if directory is writable
if (is_writable($upload_dir)) {
    echo "<p style='color: green;'>✅ Upload directory is writable.</p>";
} else {
    echo "<p style='color: red;'>❌ Upload directory is not writable. Attempting to fix permissions...</p>";
    if (chmod($upload_dir, 0755)) {
        echo "<p style='color: green;'>✅ Permissions fixed successfully.</p>";
    } else {
        echo "<p style='color: red;'>❌ Failed to fix permissions. Please set them manually.</p>";
    }
}

// Create subdirectories
$subdirs = ['id_documents', 'matric_certificates', 'additional_documents'];
echo "<h2>2. Checking Upload Subdirectories</h2>";
foreach ($subdirs as $subdir) {
    $dir_path = $upload_dir . '/' . $subdir;
    if (!file_exists($dir_path)) {
        echo "<p>Creating $subdir directory...</p>";
        if (mkdir($dir_path, 0755, true)) {
            echo "<p style='color: green;'>✅ $subdir directory created successfully.</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to create $subdir directory.</p>";
        }
    } else {
        echo "<p style='color: green;'>✅ $subdir directory exists.</p>";

        // Check if directory is writable
        if (is_writable($dir_path)) {
            echo "<p style='color: green;'>✅ $subdir directory is writable.</p>";
        } else {
            echo "<p style='color: red;'>❌ $subdir directory is not writable.</p>";
            if (chmod($dir_path, 0755)) {
                echo "<p style='color: green;'>✅ Permissions fixed successfully.</p>";
            }
        }
    }
}

// Check database connection
echo "<h2>3. Testing Database Connection</h2>";
try {
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
    echo "<p style='color: green;'>✅ Database connection successful.</p>";

    // Check if applications table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'applications'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: green;'>✅ Applications table exists.</p>";

        // Check table structure
        $stmt = $pdo->query("DESCRIBE applications");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "<p>Table columns: " . implode(", ", $columns) . "</p>";
    } else {
        echo "<p style='color: red;'>❌ Applications table does not exist. Creating it now...</p>";

        // Create applications table
        $sql = "CREATE TABLE applications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id VARCHAR(50) NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL,
            phone VARCHAR(20),
            date_of_birth DATE,
            id_number VARCHAR(50),
            school_university VARCHAR(255),
            program_choice_1 VARCHAR(255) NOT NULL,
            program_choice_2 VARCHAR(255),
            study_mode VARCHAR(50) NOT NULL,
            institution_preference VARCHAR(255),
            motivation TEXT,
            id_document_path VARCHAR(255),
            matric_certificate_path VARCHAR(255),
            additional_document_1 VARCHAR(255),
            additional_document_2 VARCHAR(255),
            additional_document_3 VARCHAR(255),
            status VARCHAR(20) DEFAULT 'pending',
            created_at DATETIME,
            updated_at DATETIME
        )";

        try {
            $pdo->exec($sql);
            echo "<p style='color: green;'>✅ Applications table created successfully.</p>";
        } catch (PDOException $e) {
            echo "<p style='color: red;'>❌ Failed to create applications table: " . $e->getMessage() . "</p>";
        }
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Database connection failed: " . $e->getMessage() . "</p>";
}

// Check form submission handler
echo "<h2>4. Checking Form Handler</h2>";
$handler_file = 'handle-student-application.php';
if (file_exists($handler_file)) {
    echo "<p style='color: green;'>✅ Form handler file exists.</p>";

    // Check if the file is readable
    if (is_readable($handler_file)) {
        echo "<p style='color: green;'>✅ Form handler file is readable.</p>";
    } else {
        echo "<p style='color: red;'>❌ Form handler file is not readable.</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Form handler file does not exist.</p>";
}

// Fix common issues
echo "<h2>5. Applying Fixes</h2>";

// Fix 1: Update form action in student-apply.php
$apply_file = 'student-apply.php';
if (file_exists($apply_file)) {
    $content = file_get_contents($apply_file);
    if (strpos($content, 'action="handle-student-application.php"') !== false) {
        echo "<p style='color: green;'>✅ Form action is correctly set in student-apply.php.</p>";
    } else {
        echo "<p style='color: red;'>❌ Form action may be incorrect in student-apply.php.</p>";
    }
}

// Fix 2: Check for CSRF token in session
if (isset($_SESSION[CSRF_TOKEN_NAME])) {
    echo "<p style='color: green;'>✅ CSRF token exists in session.</p>";
} else {
    echo "<p style='color: red;'>❌ CSRF token does not exist in session. Generating new token...</p>";
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    echo "<p style='color: green;'>✅ New CSRF token generated.</p>";
}

// Fix 3: Check MAX_FILE_SIZE constant
if (defined('MAX_FILE_SIZE')) {
    echo "<p style='color: green;'>✅ MAX_FILE_SIZE constant is defined: " . MAX_FILE_SIZE . " bytes.</p>";
} else {
    echo "<p style='color: red;'>❌ MAX_FILE_SIZE constant is not defined. Setting default value...</p>";
    define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
    echo "<p style='color: green;'>✅ MAX_FILE_SIZE constant set to 10MB.</p>";
}

// Fix 4: Check UPLOAD_DIR constant
if (defined('UPLOAD_DIR')) {
    echo "<p style='color: green;'>✅ UPLOAD_DIR constant is defined: " . UPLOAD_DIR . "</p>";
} else {
    echo "<p style='color: red;'>❌ UPLOAD_DIR constant is not defined. Setting default value...</p>";
    define('UPLOAD_DIR', 'uploads');
    echo "<p style='color: green;'>✅ UPLOAD_DIR constant set to 'uploads'.</p>";
}

echo "<h2>6. Next Steps</h2>";
echo "<p>Try submitting your application again. If you still encounter issues, check the application_error.log file for detailed error messages.</p>";
echo "<p><a href='student-apply.php' style='display: inline-block; padding: 10px 20px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 5px;'>Go to Application Form</a></p>";
?>

<style>
    body {
        font-family: Arial, sans-serif;
        max-width: 800px;
        margin: 0 auto;
        padding: 20px;
        line-height: 1.6;
    }

    h1 {
        color: #2c3e50;
    }

    h2 {
        color: #34495e;
        margin-top: 20px;
    }

    a {
        color: #3498db;
        text-decoration: none;
    }

    a:hover {
        text-decoration: underline;
    }
</style>