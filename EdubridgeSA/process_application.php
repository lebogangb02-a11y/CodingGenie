<?php
/**
 * Application Form Submission Handler
 * EduBridge SA - University Application System
 * 
 * Handles form submission, validation, sanitization, and database storage
 * Redirects to document upload page after successful submission
 */

session_start();
require_once 'config.php';
require_once 'email_functions.php';

// Function to sanitize input data
function sanitizeInput($data) {
    if ($data === null) return null;
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Function to validate South African ID number
function validateSAIDNumber($idNumber) {
    // Remove any spaces or dashes
    $idNumber = preg_replace('/[\s\-]/', '', $idNumber);
    
    // Check if ID number is 13 digits
    if (!preg_match('/^\d{13}$/', $idNumber)) {
        return false;
    }
    
    // Validate checksum using Luhn algorithm
    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $digit = (int)$idNumber[$i];
        if ($i % 2 === 0) {
            $sum += $digit;
        } else {
            $doubled = $digit * 2;
            $sum += $doubled > 9 ? $doubled - 9 : $doubled;
        }
    }
    
    $checkDigit = (10 - ($sum % 10)) % 10;
    return $checkDigit === (int)$idNumber[12];
}

// Function to validate email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Function to validate phone number (South African format)
function isValidPhone($phone) {
    // Remove spaces, dashes, and brackets
    $phone = preg_replace('/[\s\-\(\)]/', '', $phone);
    
    // Check for valid SA phone number patterns
    return preg_match('/^(\+27|0)[0-9]{9}$/', $phone);
}

// Centralized email sending handled via email_functions.php

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['form_message'] = 'Invalid request method';
    $_SESSION['form_message_type'] = 'error';
    header('Location: apply_improved.php');
    exit;
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION[CSRF_TOKEN_NAME]) {
    $_SESSION['form_message'] = 'Security token mismatch. Please try again.';
    $_SESSION['form_message_type'] = 'error';
    header('Location: apply_improved.php');
    exit;
}

// Initialize variables
$errors = [];

try {
    // Validate required fields
    $requiredFields = [
        'first_name' => 'First Name',
        'last_name' => 'Last Name',
        'email' => 'Email Address',
        'phone' => 'Phone Number',
        'id_number' => 'SA ID Number',
        'aps_score' => 'APS Score',
        'first_choice_program' => 'First Choice Program',
        'first_choice_institution' => 'First Choice Institution',
        'first_choice_specialization' => 'First Choice Specialization'
    ];
    
    foreach ($requiredFields as $field => $label) {
        if (empty($_POST[$field])) {
            $errors[] = "$label is required";
        }
    }
    
    // Validate email format
    if (!empty($_POST['email']) && !isValidEmail($_POST['email'])) {
        $errors[] = "Invalid email format";
    }
    
    // Validate phone number
    if (!empty($_POST['phone']) && !isValidPhone($_POST['phone'])) {
        $errors[] = "Invalid phone number format. Use format: +27123456789 or 0123456789";
    }
    
    // Validate SA ID number
    if (!empty($_POST['id_number']) && !validateSAIDNumber($_POST['id_number'])) {
        $errors[] = "Invalid South African ID number";
    }
    
    // Validate APS score
    if (!empty($_POST['aps_score'])) {
        $apsScore = (int)$_POST['aps_score'];
        if ($apsScore < 0 || $apsScore > 42) {
            $errors[] = "APS Score must be between 0 and 42";
        }
    }
    
    // Check for duplicate email
    if (!empty($_POST['email'])) {
        $checkEmailSql = "SELECT id FROM applications WHERE email_address = ?";
        $checkEmailStmt = $pdo->prepare($checkEmailSql);
        $checkEmailStmt->execute([sanitizeInput($_POST['email'])]);
        
        if ($checkEmailStmt->fetch()) {
            $errors[] = "An application with this email address already exists";
        }
    }
    
    // If there are validation errors, redirect back with errors
    if (!empty($errors)) {
        $_SESSION['form_errors'] = $errors;
        $_SESSION['application_form_data'] = $_POST;
        header('Location: apply_improved.php');
        exit;
    }
    
    // Prepare data for database insertion
    $applicationData = [
        'first_name' => sanitizeInput($_POST['first_name']),
        'last_name' => sanitizeInput($_POST['last_name']),
        'email' => sanitizeInput($_POST['email']),
        'phone' => sanitizeInput($_POST['phone']),
        'id_number' => sanitizeInput($_POST['id_number']),
        'aps_score' => (int)$_POST['aps_score'],
        'first_choice_program' => sanitizeInput($_POST['first_choice_program']),
        'first_choice_institution' => sanitizeInput($_POST['first_choice_institution']),
        'first_choice_specialization' => sanitizeInput($_POST['first_choice_specialization']),
        'second_choice_program' => sanitizeInput($_POST['second_choice_program'] ?? null),
        'second_choice_institution' => sanitizeInput($_POST['second_choice_institution'] ?? null),
        'second_choice_specialization' => sanitizeInput($_POST['second_choice_specialization'] ?? null),
        'third_choice_program' => sanitizeInput($_POST['third_choice_program'] ?? null),
        'third_choice_institution' => sanitizeInput($_POST['third_choice_institution'] ?? null),
        'third_choice_specialization' => sanitizeInput($_POST['third_choice_specialization'] ?? null),
        'status' => 'Submitted (without docs)'
    ];
    
    // Insert application into database (mapped to live schema columns)
    $sql = "INSERT INTO applications (
        first_name, last_name, email_address, phone, id_number, aps,
        program_choice_1, institution_choice_1, program_specialization_1,
        program_choice_2, institution_choice_2, program_specialization_2,
        program_choice_3, institution_choice_3, program_specialization_3,
        status
    ) VALUES (
        :first_name, :last_name, :email, :phone, :id_number, :aps_score,
        :first_choice_program, :first_choice_institution, :first_choice_specialization,
        :second_choice_program, :second_choice_institution, :second_choice_specialization,
        :third_choice_program, :third_choice_institution, :third_choice_specialization,
        :status
    )";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($applicationData);
    $applicationId = $pdo->lastInsertId();
    
    // Generate application reference
    $applicationRef = 'EBS-' . str_pad($applicationId, 6, '0', STR_PAD_LEFT);
    
    // Send email notification to applicant
    $applicantSubject = 'Application Received – Next Steps';
    $documentUploadUrl = BASE_URL . '/upload-documents.php?app_id=' . $applicationId . '&token=' . md5($applicationId . AUTH_SALT);
    
    $applicantMessage = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Application Received</title>
        <style>
            body { margin:0; padding:0; font-family: Arial, sans-serif; line-height: 1.6; color: #333; background:#f4f6f8; }
            img { border:0; outline:none; text-decoration:none; max-width:100%; height:auto; }
            .container { max-width: 600px; margin: 0 auto; padding: 0; }
            .header { background: linear-gradient(135deg, #1a5fb4 0%, #0d47a1 100%); color: white; padding: 24px; text-align: center; border-radius: 12px 12px 0 0; }
            .logo img { max-width: 140px; display:block; margin:0 auto; }
            .content { padding: 20px; background: #ffffff; border-radius: 0 0 12px 12px; }
            .button { display: inline-block; background: #1a5fb4; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 10px 0; font-weight:600; }
            .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            @media (max-width: 640px) { .button { width:100%; box-sizing:border-box; } }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <div class='logo'>
                    <img src='" . BASE_URL . "/images/logo.png.jpg' alt='EduBridgeSA Logo'>
                </div>
                <h2 style='margin:6px 0 0 0;'>Application Received</h2>
            </div>
            <div class='content'>
                <p>Dear {$applicationData['first_name']} {$applicationData['last_name']},</p>
                
                <p>Thank you for submitting your university application! We have successfully received your application.</p>
                
                <p><strong>Application Reference:</strong> {$applicationRef}</p>
                <p><strong>Application Date:</strong> " . date('F j, Y') . "</p>
                <p><strong>Status:</strong> Submitted (Pending Documents)</p>
                
                <h3>Your Program Choices</h3>
                <p><strong>1st Choice:</strong> {$applicationData['first_choice_program']} at {$applicationData['first_choice_institution']}</p>
                " . (!empty($applicationData['second_choice_program']) ? "<p><strong>2nd Choice:</strong> {$applicationData['second_choice_program']} at {$applicationData['second_choice_institution']}</p>" : "") . "
                " . (!empty($applicationData['third_choice_program']) ? "<p><strong>3rd Choice:</strong> {$applicationData['third_choice_program']} at {$applicationData['third_choice_institution']}</p>" : "") . "
                
                <h3>Next Steps - Document Upload</h3>
                <p>To complete your application, please upload the required documents. Some documents are optional:</p>
                <ul>
                    <li>Copy of South African ID Document</li>
                    <li>Matric Certificate or Senior Certificate</li>
                    <li>Academic Transcript (if applicable)</li>
                    <li>Proof of Residence (optional)</li>
                    <li>Parent/Guardian Certified ID Copy (optional)</li>
                </ul>
                
                <p style='text-align: center;'>
                    <a href='{$documentUploadUrl}' class='button'>Upload Documents Now</a>
                </p>
                
                <p><strong>Important:</strong> Your application will be processed once all required documents have been uploaded. Optional documents can be added later.</p>
                
                <p>If you have any questions, please contact us:</p>
                <ul>
                    <li>Email: " . ADMIN_EMAIL . "</li>
                    <li>Phone: +27 11 123 4567</li>
                    <li>Website: " . BASE_URL . "</li>
                </ul>
            </div>
            <div class='footer'>
                <p>© " . date('Y') . " EduBridgeSA. All rights reserved.</p>
                <p><a href='#' style='color:#1a5fb4; text-decoration:none;'>Facebook</a> • <a href='#' style='color:#1a5fb4; text-decoration:none;'>Twitter</a> • <a href='#' style='color:#1a5fb4; text-decoration:none;'>Instagram</a> • <a href='#' style='color:#1a5fb4; text-decoration:none;'>LinkedIn</a></p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Send email to applicant using centralized email function and log result
    $recipientName = $applicationData['first_name'] . ' ' . $applicationData['last_name'];
    $emailResult = sendEmail($applicationData['email'], $recipientName, $applicantSubject, $applicantMessage, 'confirmation');
    // Log using unified email_notifications table
    try {
        logEmailNotification($applicationId, 'confirmation', $applicationData['email'], $applicantSubject, $applicantMessage, $emailResult);
    } catch (Exception $e) {
        // Continue even if logging fails
        error_log('Email log failed: ' . $e->getMessage());
    }
    
    // Clear form data from session
    unset($_SESSION['application_form_data']);
    unset($_SESSION['form_errors']);
    
    // Set success message and redirect to document upload
    $_SESSION['form_message'] = 'Your application has been submitted successfully! Reference: ' . $applicationRef;
    $_SESSION['form_message_type'] = 'success';
    $_SESSION['application_id'] = $applicationId;
    $_SESSION['application_ref'] = $applicationRef;
    
    // Redirect to document upload page
    header('Location: upload-documents.php?app_id=' . $applicationId . '&token=' . md5($applicationId . AUTH_SALT));
    exit;
    
} catch (PDOException $e) {
    // Database error
    error_log("Database error in process_application.php: " . $e->getMessage());
    $_SESSION['form_message'] = 'A database error occurred. Please try again later.';
    $_SESSION['form_message_type'] = 'error';
    $_SESSION['application_form_data'] = $_POST;
    header('Location: apply_improved.php');
    exit;
    
} catch (Exception $e) {
    // General error
    error_log("Error in process_application.php: " . $e->getMessage());
    $_SESSION['form_message'] = 'An error occurred while processing your application. Please try again.';
    $_SESSION['form_message_type'] = 'error';
    $_SESSION['application_form_data'] = $_POST;
    header('Location: apply_improved.php');
    exit;
}
?>