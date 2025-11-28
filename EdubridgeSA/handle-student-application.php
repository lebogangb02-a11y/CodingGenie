<?php
require_once 'config.php';
require_once 'session_config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: student-login.php');
    exit();
}

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['form_message'] = 'Invalid request method.';
    $_SESSION['form_message_type'] = 'error';
    header('Location: student-apply.php');
    exit();
}

// CSRF protection
if (!isset($_POST[CSRF_TOKEN_NAME]) || !isset($_SESSION[CSRF_TOKEN_NAME]) || 
    $_POST[CSRF_TOKEN_NAME] !== $_SESSION[CSRF_TOKEN_NAME]) {
    $_SESSION['form_message'] = 'Security token mismatch. Please try again.';
    $_SESSION['form_message_type'] = 'error';
    header('Location: student-apply.php');
    exit();
}

// Get student information from session
$student_id = $_SESSION['student_id'] ?? '';
$student_email = $_SESSION['email'] ?? '';
$student_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];

// Validate required fields
$required_fields = ['program_choice_1', 'study_mode'];
$errors = [];

foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
    }
}

// Validate file uploads
$required_files = ['id_document', 'matric_certificate'];
foreach ($required_files as $file_field) {
    if (!isset($_FILES[$file_field]) || $_FILES[$file_field]['error'] !== UPLOAD_ERR_OK) {
        $errors[] = ucfirst(str_replace('_', ' ', $file_field)) . ' is required.';
    }
}

if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_message'] = 'Please correct the errors below.';
    $_SESSION['form_message_type'] = 'error';
    header('Location: student-apply.php');
    exit();
}

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

    // Get student details from users table
    $stmt = $pdo->prepare("
        SELECT first_name, last_name, email, phone, date_of_birth, id_number, school_university 
        FROM users 
        WHERE student_id = ?
    ");
    $stmt->execute([$student_id]);
    $student_details = $stmt->fetch();

    if (!$student_details) {
        throw new Exception('Student details not found.');
    }

    // Handle file uploads
    $uploaded_files = [];
    $upload_dir = UPLOAD_DIR;
    
    // Create upload directories if they don't exist
    $subdirs = ['id_documents', 'matric_certificates', 'additional_documents'];
    foreach ($subdirs as $subdir) {
        $dir_path = $upload_dir . '/' . $subdir;
        if (!is_dir($dir_path)) {
            mkdir($dir_path, 0755, true);
        }
    }

    // Upload ID document
    if (isset($_FILES['id_document']) && $_FILES['id_document']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['id_document'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png'];
        
        if (!in_array($file_extension, $allowed_extensions)) {
            throw new Exception('Invalid file type for ID document. Only PDF, JPG, and PNG files are allowed.');
        }
        
        if ($file['size'] > MAX_FILE_SIZE) {
            throw new Exception('ID document file size exceeds the maximum limit of ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB.');
        }
        
        $filename = $student_id . '_id_' . time() . '.' . $file_extension;
        $filepath = $upload_dir . '/id_documents/' . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $uploaded_files['id_document'] = 'uploads/id_documents/' . $filename;
        } else {
            throw new Exception('Failed to upload ID document.');
        }
    }

    // Upload Matric certificate
    if (isset($_FILES['matric_certificate']) && $_FILES['matric_certificate']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['matric_certificate'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png'];
        
        if (!in_array($file_extension, $allowed_extensions)) {
            throw new Exception('Invalid file type for Matric certificate. Only PDF, JPG, and PNG files are allowed.');
        }
        
        if ($file['size'] > MAX_FILE_SIZE) {
            throw new Exception('Matric certificate file size exceeds the maximum limit of ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB.');
        }
        
        $filename = $student_id . '_matric_' . time() . '.' . $file_extension;
        $filepath = $upload_dir . '/matric_certificates/' . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $uploaded_files['matric_certificate'] = 'uploads/matric_certificates/' . $filename;
        } else {
            throw new Exception('Failed to upload Matric certificate.');
        }
    }

    // Upload additional documents
    $additional_docs = [];
    if (isset($_FILES['additional_documents']) && is_array($_FILES['additional_documents']['name'])) {
        $file_count = count($_FILES['additional_documents']['name']);
        
        for ($i = 0; $i < $file_count && $i < 3; $i++) {
            if ($_FILES['additional_documents']['error'][$i] === UPLOAD_ERR_OK) {
                $file_name = $_FILES['additional_documents']['name'][$i];
                $file_tmp = $_FILES['additional_documents']['tmp_name'][$i];
                $file_size = $_FILES['additional_documents']['size'][$i];
                
                $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png'];
                
                if (in_array($file_extension, $allowed_extensions) && $file_size <= MAX_FILE_SIZE) {
                    $filename = $student_id . '_additional_' . ($i + 1) . '_' . time() . '.' . $file_extension;
                    $filepath = $upload_dir . '/additional_documents/' . $filename;
                    
                    if (move_uploaded_file($file_tmp, $filepath)) {
                        $additional_docs[] = 'uploads/additional_documents/' . $filename;
                    }
                }
            }
        }
    }

    // Prepare application data
    $application_data = [
        'student_id' => $student_id,
        'first_name' => $student_details['first_name'],
        'last_name' => $student_details['last_name'],
        'email' => $student_details['email'],
        'phone' => $student_details['phone'] ?? '',
        'date_of_birth' => $student_details['date_of_birth'] ?? '',
        'id_number' => $student_details['id_number'] ?? '',
        'school_university' => $student_details['school_university'] ?? '',
        'program_choice_1' => $_POST['program_choice_1'],
        'program_choice_2' => $_POST['program_choice_2'] ?? '',
        'study_mode' => $_POST['study_mode'],
        'institution_preference' => $_POST['institution_preference'] ?? '',
        'motivation' => $_POST['motivation'] ?? '',
        'id_document_path' => $uploaded_files['id_document'] ?? '',
        'matric_certificate_path' => $uploaded_files['matric_certificate'] ?? '',
        'additional_document_1' => $additional_docs[0] ?? '',
        'additional_document_2' => $additional_docs[1] ?? '',
        'additional_document_3' => $additional_docs[2] ?? '',
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s')
    ];

    // Insert application into database
    $sql = "INSERT INTO applications (
        student_id, first_name, last_name, email, phone, date_of_birth, id_number,
        school_university, program_choice_1, program_choice_2, study_mode, 
        institution_preference, motivation, id_document_path, matric_certificate_path,
        additional_document_1, additional_document_2, additional_document_3,
        status, created_at
    ) VALUES (
        :student_id, :first_name, :last_name, :email, :phone, :date_of_birth, :id_number,
        :school_university, :program_choice_1, :program_choice_2, :study_mode,
        :institution_preference, :motivation, :id_document_path, :matric_certificate_path,
        :additional_document_1, :additional_document_2, :additional_document_3,
        :status, :created_at
    )";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($application_data);
    
    $application_id = $pdo->lastInsertId();

    // Send notification emails
    try {
        sendApplicationNotification($application_data, $application_id);
    } catch (Exception $e) {
        // Log email error but don't fail the application
        error_log('Email notification failed: ' . $e->getMessage());
    }

    // Store application data for thank-you page
    $_SESSION['application_id'] = $application_id;
    $_SESSION['applicant_name'] = $data['first_name'] . ' ' . $data['last_name'];
    $_SESSION['applicant_email'] = $data['email'];
    $_SESSION['form_message'] = "Your application has been submitted successfully! Application ID: {$application_id}. You will receive a confirmation email shortly.";
    $_SESSION['form_message_type'] = 'success';
    
    // Regenerate CSRF token
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    
    // Redirect to thank-you page instead
    header('Location: thank-you.php');
    exit();

} catch (Exception $e) {
    $_SESSION['form_message'] = 'Application submission failed: ' . $e->getMessage();
    $_SESSION['form_message_type'] = 'error';
    header('Location: student-apply.php');
    exit();
}

/**
 * Send application notification emails
 */
function sendApplicationNotification($data, $applicationId) {
    // Include PHPMailer
    require_once 'PHPMailer/src/Exception.php';
    require_once 'PHPMailer/src/PHPMailer.php';
    require_once 'PHPMailer/src/SMTP.php';
    
    // Use fully qualified class names instead of 'use' statements inside function
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';

        // Send confirmation to student
        $mail->setFrom(FROM_EMAIL, 'EduBridge SA');
        $mail->addAddress($data['email'], $data['first_name'] . ' ' . $data['last_name']);
        
        $mail->isHTML(true);
        $mail->Subject = "Application Confirmation - EduBridge SA (ID: {$applicationId})";
        
        $confirmationMessage = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .header { background: #1a5fb4; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .highlight { background: #f8f9fa; padding: 15px; border-left: 4px solid #1a5fb4; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>Application Received</h1>
                <p>EduBridge SA</p>
            </div>
            <div class='content'>
                <h2>Dear {$data['first_name']} {$data['last_name']},</h2>
                
                <p>Thank you for submitting your application through your student portal. We have successfully received your application.</p>
                
                <div class='highlight'>
                    <h3>Application Details:</h3>
                    <p><strong>Application ID:</strong> {$applicationId}</p>
                    <p><strong>Student ID:</strong> {$data['student_id']}</p>
                    <p><strong>Date Submitted:</strong> " . date('Y-m-d H:i:s') . "</p>
                    <p><strong>First Program Choice:</strong> {$data['program_choice_1']}</p>
                    <p><strong>Study Mode:</strong> {$data['study_mode']}</p>
                </div>
                
                <h3>What happens next?</h3>
                <ul>
                    <li>Our admissions team will review your application</li>
                    <li>We will contact you within 5-7 business days</li>
                    <li>You can check your application status in your student dashboard</li>
                    <li>Please keep your application ID ({$applicationId}) for reference</li>
                </ul>
                
                <p>You can log in to your student portal at any time to check the status of your application.</p>
                
                <p>If you have any questions, please contact us at applications@edubridgesa.co.za</p>
                
                <p>Best regards,<br>
                EduBridge SA Admissions Team</p>
            </div>
        </body>
        </html>";
        
        $mail->Body = $confirmationMessage;
        $mail->send();

        // Send notification to admin
        $mail->clearAddresses();
        $mail->addAddress(ADMIN_EMAIL, 'EduBridge Admin');
        
        $mail->Subject = "New Student Application - ID: {$applicationId}";
        
        $adminMessage = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .header { background: #1a5fb4; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .info-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                .info-table th, .info-table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
                .info-table th { background: #f8f9fa; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>New Student Application</h1>
                <p>EduBridge SA Student Portal</p>
            </div>
            <div class='content'>
                <h2>Application Details</h2>
                <p><strong>Application ID:</strong> {$applicationId}</p>
                <p><strong>Student ID:</strong> {$data['student_id']}</p>
                <p><strong>Date:</strong> " . date('Y-m-d H:i:s') . "</p>
                
                <table class='info-table'>
                    <tr><th>Field</th><th>Value</th></tr>
                    <tr><td>Name</td><td>{$data['first_name']} {$data['last_name']}</td></tr>
                    <tr><td>Email</td><td>{$data['email']}</td></tr>
                    <tr><td>Phone</td><td>{$data['phone']}</td></tr>
                    <tr><td>First Program Choice</td><td>{$data['program_choice_1']}</td></tr>
                    <tr><td>Second Program Choice</td><td>{$data['program_choice_2']}</td></tr>
                    <tr><td>Study Mode</td><td>{$data['study_mode']}</td></tr>
                    <tr><td>Institution Preference</td><td>{$data['institution_preference']}</td></tr>
                </table>
                
                <p>Please log in to the admin panel to view the complete application and uploaded documents.</p>
            </div>
        </body>
        </html>";
        
        $mail->Body = $adminMessage;
        $mail->send();

    } catch (Exception $e) {
        throw new Exception('Email notification failed: ' . $e->getMessage());
    }
}
?>