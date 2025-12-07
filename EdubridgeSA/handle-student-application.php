<?php
require_once 'config.php';
require_once 'session_config.php';
require_once __DIR__ . '/includes/security_helpers.php';
require_once __DIR__ . '/includes/upload_helper.php';

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

    // Handle file uploads via centralized helper
    $uploaded_files = [];

    // ID document
    if (isset($_FILES['id_document'])) {
        $res = store_uploaded_file($_FILES['id_document'], 'id_documents', ['application/pdf','image/jpeg','image/png'], MAX_FILE_SIZE);
        if (!$res['success']) {
            throw new Exception('ID upload failed: ' . $res['error']);
        }
        $uploaded_files['id_document'] = str_replace(realpath(__DIR__) . DIRECTORY_SEPARATOR, '', $res['path']);
    }

    // Matric certificate
    if (isset($_FILES['matric_certificate'])) {
        $res = store_uploaded_file($_FILES['matric_certificate'], 'matric_certificates', ['application/pdf','image/jpeg','image/png'], MAX_FILE_SIZE);
        if (!$res['success']) {
            throw new Exception('Matric upload failed: ' . $res['error']);
        }
        $uploaded_files['matric_certificate'] = str_replace(realpath(__DIR__) . DIRECTORY_SEPARATOR, '', $res['path']);
    }

    // Additional documents (up to 3)
    $additional_docs = [];
    if (isset($_FILES['additional_documents']) && is_array($_FILES['additional_documents']['name'])) {
        $count = min(3, count($_FILES['additional_documents']['name']));
        for ($i = 0; $i < $count; $i++) {
            if (($_FILES['additional_documents']['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $fileArray = [
                    'name' => $_FILES['additional_documents']['name'][$i],
                    'type' => $_FILES['additional_documents']['type'][$i] ?? null,
                    'tmp_name' => $_FILES['additional_documents']['tmp_name'][$i],
                    'error' => $_FILES['additional_documents']['error'][$i],
                    'size' => $_FILES['additional_documents']['size'][$i],
                ];

                $res = store_uploaded_file($fileArray, 'additional_documents', ['application/pdf','image/jpeg','image/png'], MAX_FILE_SIZE);
                if ($res['success']) {
                    $additional_docs[] = str_replace(realpath(__DIR__) . DIRECTORY_SEPARATOR, '', $res['path']);
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