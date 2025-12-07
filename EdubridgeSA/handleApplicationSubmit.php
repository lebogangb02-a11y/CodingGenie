<?php
// ULTIMATE ERROR DEBUGGING - ADD THIS TO TOP
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug_errors.log');

// Start session FIRST
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include configuration IMMEDIATELY AFTER session start
require_once 'config.php';

// Security helpers (CSRF, escaping)
require_once __DIR__ . '/includes/security_helpers.php';
// Enforce CSRF for this POST-based form handler
require_csrf();
// Centralized upload helper
require_once __DIR__ . '/includes/upload_helper.php';

// NOW you can use CSRF_TOKEN_NAME and other constants
error_log("=== HANDLE APPLICATION SUBMIT STARTED ===");
error_log("Session ID: " . session_id());
error_log("CSRF Token Constant: " . CSRF_TOKEN_NAME);
error_log("CSRF Token in Session: " . ($_SESSION[CSRF_TOKEN_NAME] ?? 'NOT SET'));

// Use centralized email functions implementation

// Include PHPMailer classes
require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';
require_once 'email_functions.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * University Application Form Handler
 * EduBridge SA - Updated for existing database structure
 */

/**
 * Generate unique application reference
 */
function generateApplicationReference(PDO $pdo)
{
    $year = date('Y');
    $attempts = 0;
    $maxAttempts = 10;

    do {
        // Generate a 6-digit number
        $number = str_pad(random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        $reference = 'APP' . $year . $number;

        // Check if this reference already exists using PDO
        try {
            $stmt = $pdo->prepare("SELECT id FROM applications WHERE application_ref = ? LIMIT 1");
            $stmt->execute([$reference]);
            $exists = (bool) $stmt->fetchColumn();
        } catch (Exception $e) {
            error_log('generateApplicationReference DB error: ' . $e->getMessage());
            // On DB error, break and fallback to timestamp-based ref
            $exists = true;
        }

        if (!$exists) {
            return $reference;
        }

        $attempts++;
    } while ($attempts < $maxAttempts);

    // Fallback: use timestamp if we can't generate unique reference
    return 'APP' . $year . time();
}

/**
 * Validate form data
 */
function validateFormData($data)
{
    $errors = [];

    // Required fields that match the comprehensive form
    $requiredFields = [
        'first_name' => 'First Name',
        'last_name' => 'Last Name',
        'id_number' => 'ID Number',
        'dob' => 'Date of Birth',
        'gender' => 'Gender',
        'nationality' => 'Nationality',
        'address' => 'Address',
        'city' => 'City',
        'province' => 'Province',
        'postal_code' => 'Postal Code',
        'phone' => 'Phone Number',
        'email' => 'Email',
        'country' => 'Country',
        'emergency_name' => 'Emergency Contact Name',
        'emergency_relationship' => 'Emergency Contact Relationship',
        'emergency_phone' => 'Emergency Contact Phone',
        'matric_year' => 'Matric Year',
        'aps' => 'APS Score',
        'high_school_name' => 'High School Name',
        'program_choice_1' => 'First Program Choice',
        'study_mode' => 'Study Mode',
        'has_disability' => 'Disability Status',
        'previous_tertiary' => 'Previous Tertiary Education',
        'terms_conditions' => 'Terms and Conditions',
        'privacy_policy' => 'Privacy Policy'
    ];

    foreach ($requiredFields as $field => $label) {
        if (empty($data[$field])) {
            $errors[$field] = $label . ' is required.';
        }
    }

    // Email validation
    if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    // Phone validation
    if (!empty($data['phone']) && !preg_match('/^[0-9+\-\s()]{7,20}$/', $data['phone'])) {
        $errors['phone'] = 'Please enter a valid phone number.';
    }

    // Date of Birth validation
    if (!empty($data['dob'])) {
        $dob = new DateTime($data['dob']);
        $now = new DateTime();
        $age = $now->diff($dob)->y;

        if ($age < 16) {
            $errors['dob'] = 'Applicants must be at least 16 years old.';
        }
        if ($age > 100) {
            $errors['dob'] = 'Please enter a valid date of birth.';
        }
    }

    // APS Score validation
    if (!empty($data['aps']) && (!is_numeric($data['aps']) || $data['aps'] < 0 || $data['aps'] > 50)) {
        $errors['aps'] = 'APS Score must be a number between 0 and 50.';
    }

    // Matric Year validation
    if (!empty($data['matric_year'])) {
        $currentYear = (int)date('Y');
        $matricYear = (int)$data['matric_year'];

        if ($matricYear < $currentYear - 50 || $matricYear > $currentYear + 1) {
            $errors['matric_year'] = 'Please enter a valid matric year.';
        }
    }

    return $errors;
}

/**
 * Handle file uploads
 */
function handleFileUploads($files)
{
    $result = [
        'files' => [],
        'errors' => []
    ];

    $allowedTypes = [
        'application/pdf',
        'image/jpeg',
        'image/jpg'
    ];

    // Process each uploaded file
    foreach ($files as $field => $file) {
        // Multiple file inputs (like additional_documents)
        if (is_array($file['name'])) {
            $uploadedFiles = [];
            $fileErrors = [];

            $count = count($file['name']);
            for ($i = 0; $i < $count; $i++) {
                if (empty($file['name'][$i])) continue;

                $fileArray = [
                    'name' => $file['name'][$i],
                    'type' => $file['type'][$i] ?? null,
                    'tmp_name' => $file['tmp_name'][$i],
                    'error' => $file['error'][$i],
                    'size' => $file['size'][$i],
                ];

                $res = store_uploaded_file($fileArray, $field, $allowedTypes, MAX_FILE_SIZE);
                if (!$res['success']) {
                    $fileErrors[] = 'File ' . ($i + 1) . ' upload failed: ' . ($res['error'] ?? 'unknown');
                    continue;
                }

                $uploadedFiles[] = str_replace(realpath(__DIR__) . DIRECTORY_SEPARATOR, '', $res['path']);
            }

            if (!empty($uploadedFiles)) $result['files'][$field] = $uploadedFiles;
            if (!empty($fileErrors)) $result['errors'][$field] = implode(' ', $fileErrors);
        } else {
            if (empty($file['name'])) continue;

            $res = store_uploaded_file($file, $field, $allowedTypes, MAX_FILE_SIZE);
            if (!$res['success']) {
                $result['errors'][$field] = $res['error'] ?? 'Upload failed';
                continue;
            }

            $result['files'][$field] = str_replace(realpath(__DIR__) . DIRECTORY_SEPARATOR, '', $res['path']);
        }
    }

    return $result;
}

/**
 * Save application data to database
 */
function saveApplicationData(PDO $pdo, $data, $files)
{
    // Start transaction
    $pdo->beginTransaction();

    try {
        // Generate unique application reference
        $applicationRef = generateApplicationReference($pdo);

        // Comprehensive INSERT statement that matches new schema (68 columns - excluding auto_increment application_id)
        $sql = "INSERT INTO applications (
                application_ref, first_name, last_name, middle_name, title, gender, dob, id_number, marital_status, home_language, nationality, passport_number,
                email, phone, alternative_phone, address, city, province, postal_code, country,
                emergency_name, emergency_relationship, emergency_phone, emergency_email,
                has_disability, disability_details, previous_tertiary, previous_tertiary_details,
                high_school_name, matric_year, aps, maths_level, english_level, additional_qualifications,
                exam_number, highest_grade, employment_status,
                program_choice_1, program_choice_2, program_choice_3, 
                institution_choice_1, program_specialization_1, program_other_comment_1,
                institution_choice_2, program_specialization_2, program_other_comment_2,
                institution_choice_3, program_specialization_3, program_other_comment_3,
                intended_study_year, funding_source, study_mode, motivation,
                matric_certificate, id_document, proof_of_payment, additional_documents,
                academic_transcript, proof_of_residence, saqa_evaluation, marital_document,
                signature, signature_date, application_date, status,
                terms_conditions, privacy_policy, marketing_consent
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $pdo->prepare($sql);

        if (!$stmt) {
            throw new Exception('Database prepare failed (PDO).');
        }

        // Handle all form fields and assign to variables for execute()
        // Personal Information
        $firstName = (string)$data['first_name'];
        $lastName = (string)$data['last_name'];
        $middleName = (string)($data['middle_name'] ?? '');
        $idNumber = (string)$data['id_number'];
        $dob = (string)$data['dob'];
        $gender = (string)$data['gender'];
        $nationality = (string)($data['nationality'] ?? 'South African');
        $title = (string)($data['title'] ?? '');
        $homeLanguage = (string)($data['home_language'] ?? '');
        $maritalStatus = (string)($data['marital_status'] ?? '');
        $passportNumber = (string)($data['passport_number'] ?? '');

        // Contact Information
        $address = (string)$data['address'];
        $city = (string)$data['city'];
        $province = (string)$data['province'];
        $postalCode = (string)$data['postal_code'];
        $phone = (string)$data['phone'];
        $alternativePhone = (string)($data['alternative_phone'] ?? '');
        $email = (string)$data['email'];
        $country = (string)($data['country'] ?? 'South Africa');

        // Emergency Contact
        $emergencyName = (string)($data['emergency_name'] ?? '');
        $emergencyRelationship = (string)($data['emergency_relationship'] ?? '');
        $emergencyPhone = (string)($data['emergency_phone'] ?? '');
        $emergencyEmail = (string)($data['emergency_email'] ?? '');

        // Academic Information
        $matricYear = (int)($data['matric_year'] ?? 0);
        $examNumber = (string)($data['exam_number'] ?? '');
        $highestGrade = (string)($data['highest_grade'] ?? '');
        $aps = (int)($data['aps'] ?? 0);
        $highSchoolName = (string)($data['high_school_name'] ?? '');
        $mathsLevel = (string)($data['maths_level'] ?? '');
        $englishLevel = (string)($data['english_level'] ?? '');
        $additionalQualifications = (string)($data['additional_qualifications'] ?? '');

        // Employment and Program Information
        $employmentStatus = (string)($data['employment_status'] ?? '');
        $programChoice1 = (string)($data['program_choice_1'] ?? '');
        $programChoice2 = (string)($data['program_choice_2'] ?? '');
        $programChoice3 = (string)($data['program_choice_3'] ?? '');
        $institutionChoice1 = (string)($data['institution_choice_1'] ?? '');
        $institutionChoice2 = (string)($data['institution_choice_2'] ?? '');
        $institutionChoice3 = (string)($data['institution_choice_3'] ?? '');
        $programSpecialization1 = (string)($data['program_specialization_1'] ?? '');
        $programSpecialization2 = (string)($data['program_specialization_2'] ?? '');
        $programSpecialization3 = (string)($data['program_specialization_3'] ?? '');
        $programOtherComment1 = (string)($data['program_other_comment_1'] ?? '');
        $programOtherComment2 = (string)($data['program_other_comment_2'] ?? '');
        $programOtherComment3 = (string)($data['program_other_comment_3'] ?? '');
        $studyMode = (string)($data['study_mode'] ?? '');
        $intendedStudyYear = (string)($data['intended_study_year'] ?? '');
        $fundingSource = (string)($data['funding_source'] ?? '');
        $motivation = (string)($data['motivation'] ?? '');

        // Additional Information
        $hasDisability = (string)($data['has_disability'] ?? 'no');
        $disabilityDetails = (string)($data['disability_details'] ?? '');
        $previousTertiary = (string)($data['previous_tertiary'] ?? 'no');
        $previousTertiaryDetails = (string)($data['previous_tertiary_details'] ?? '');

        // Document Upload Paths
        $idDocument = (string)($files['files']['id_document'] ?? '');
        $matricCertificate = (string)($files['files']['matric_certificate'] ?? '');
        $proofOfPayment = (string)($files['files']['proof_of_payment'] ?? '');

        // Handle multiple additional documents as JSON
        $additionalDocuments = '';
        if (isset($files['files']['additional_documents']) && is_array($files['files']['additional_documents'])) {
            $additionalDocuments = json_encode($files['files']['additional_documents']);
        }

        // New document upload paths
        $academicTranscript = (string)($files['files']['academic_transcript'] ?? '');
        $proofOfResidence = (string)($files['files']['proof_of_residence'] ?? '');
        $saqaEvaluation = (string)($files['files']['saqa_evaluation'] ?? '');
        $maritalDocument = (string)($files['files']['marital_document'] ?? '');

        // Declaration and Consent Fields
        $signature = (string)($data['signature'] ?? '');
        $signatureDate = (string)($data['signature_date'] ?? '');
        $applicationDate = date('Y-m-d H:i:s'); // Current timestamp
        $status = 'Pending'; // Default status
        $termsConditions = isset($data['terms_conditions']) ? 1 : 0;
        $privacyPolicy = isset($data['privacy_policy']) ? 1 : 0;
        $marketingConsent = isset($data['marketing_consent']) ? 1 : 0;

        // Prepare values in the same order as the INSERT columns
        $values = [
            $applicationRef,
            $firstName,
            $lastName,
            $middleName,
            $title,
            $gender,
            $dob,
            $idNumber,
            $maritalStatus,
            $homeLanguage,
            $nationality,
            $passportNumber,
            $email,
            $phone,
            $alternativePhone,
            $address,
            $city,
            $province,
            $postalCode,
            $country,
            $emergencyName,
            $emergencyRelationship,
            $emergencyPhone,
            $emergencyEmail,
            $hasDisability,
            $disabilityDetails,
            $previousTertiary,
            $previousTertiaryDetails,
            $highSchoolName,
            $matricYear,
            $aps,
            $mathsLevel,
            $englishLevel,
            $additionalQualifications,
            $examNumber,
            $highestGrade,
            $employmentStatus,
            $programChoice1,
            $programChoice2,
            $programChoice3,
            $institutionChoice1,
            $programSpecialization1,
            $programOtherComment1,
            $institutionChoice2,
            $programSpecialization2,
            $programOtherComment2,
            $institutionChoice3,
            $programSpecialization3,
            $programOtherComment3,
            $intendedStudyYear,
            $fundingSource,
            $studyMode,
            $motivation,
            $matricCertificate,
            $idDocument,
            $proofOfPayment,
            $additionalDocuments,
            $academicTranscript,
            $proofOfResidence,
            $saqaEvaluation,
            $maritalDocument,
            $signature,
            $signatureDate,
            $applicationDate,
            $status,
            $termsConditions,
            $privacyPolicy,
            $marketingConsent
        ];

        // Execute insert
        if ($stmt->execute($values)) {
            $applicationId = (int)$pdo->lastInsertId();
            // Commit transaction
            $pdo->commit();

            // Return both legacy and new keys for compatibility
            return [
                'id' => $applicationId,
                'ref' => $applicationRef,
                'application_id' => $applicationId,
                'application_ref' => $applicationRef
            ];
        } else {
            $errorInfo = $stmt->errorInfo();
            throw new Exception('Database insert failed: ' . ($errorInfo[2] ?? 'Unknown error'));
        }
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Send email notification using PHPMailer with SMTP
 */
function sendEmailNotification($data, $applicationId)
{
    error_log("Starting sendEmailNotification function for application ID: {$applicationId}");
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        // Disable debug output in production (enable only for testing)
        $mail->SMTPDebug = 0;
        // $mail->Debugoutput = function($str, $level) {
        //     error_log("SMTP Debug Level $level: $str");
        // };

        // Additional SMTP options for better compatibility
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // Set timeout
        $mail->Timeout = 60;

        // Recipients
        $mail->setFrom(FROM_EMAIL, 'EduBridge SA');
        $mail->addAddress(ADMIN_EMAIL, 'EduBridge Admin');
        $mail->addReplyTo($data['email'], $data['first_name'] . ' ' . $data['last_name']);

        // Content
        $mail->isHTML(true);
        $mail->Subject = "New University Application - ID: {$applicationId}";

        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .header { background: #2c3e50; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .info-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                .info-table th, .info-table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
                .info-table th { background: #f8f9fa; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>New University Application</h1>
                <p>EduBridge SA</p>
            </div>
            <div class='content'>
                <h2>Application Details</h2>
                <p><strong>Application ID:</strong> {$applicationId}</p>
                <p><strong>Date:</strong> " . date('Y-m-d H:i:s') . "</p>
                
                <table class='info-table'>
                    <tr><th>Field</th><th>Value</th></tr>
                    <tr><td>Name</td><td>{$data['first_name']} {$data['last_name']}</td></tr>
                    <tr><td>Email</td><td>{$data['email']}</td></tr>
                    <tr><td>Phone</td><td>{$data['phone']}</td></tr>
                    <tr><td>ID Number</td><td>{$data['id_number']}</td></tr>
                    <tr><td>Date of Birth</td><td>{$data['dob']}</td></tr>
                    <tr><td>Gender</td><td>{$data['gender']}</td></tr>
                    <tr><td>Address</td><td>{$data['address']}, {$data['city']}, {$data['province']}, {$data['postal_code']}</td></tr>
                    <tr><td>High School</td><td>{$data['high_school_name']}</td></tr>
                    <tr><td>Matric Year</td><td>{$data['matric_year']}</td></tr>
                    <tr><td>APS Score</td><td>{$data['aps']}</td></tr>
                    <tr><td>First Program Choice</td><td>{$data['program_choice_1']}</td></tr>
                    <tr><td>Second Program Choice</td><td>{$data['program_choice_2']}</td></tr>
                    <tr><td>Study Mode</td><td>{$data['study_mode']}</td></tr>
                </table>
                
                <h3>Motivation</h3>
                <p>" . nl2br(htmlspecialchars($data['motivation'])) . "</p>
                
                <p><em>Please log in to the admin panel to view the complete application and uploaded documents.</em></p>
            </div>
        </body>
        </html>";

        $mail->Body = $message;

        // Plain text version for non-HTML clients
        $mail->AltBody = "New University Application - ID: {$applicationId}\n\n" .
            "Name: {$data['first_name']} {$data['last_name']}\n" .
            "Email: {$data['email']}\n" .
            "Phone: {$data['phone']}\n" .
            "First Program Choice: {$data['program_choice_1']}\n" .
            "Second Program Choice: {$data['program_choice_2']}\n" .
            "Study Mode: {$data['study_mode']}\n\n" .
            "Please log in to the admin panel to view the complete application.";

        $mail->send();
        error_log("Admin notification email sent successfully for application ID: {$applicationId}");

        // Send confirmation email to applicant
        sendConfirmationEmail($data, $applicationId);

        return true;
    } catch (Exception $e) {
        // Log the error for debugging
        error_log("PHPMailer Error: {$mail->ErrorInfo}");
        error_log("PHPMailer Exception: " . $e->getMessage());

        // Fallback to basic mail() function
        try {
            $to = ADMIN_EMAIL;
            $subject = "New University Application - ID: {$applicationId}";
            $from = FROM_EMAIL;

            $headers = "From: {$from}\r\n";
            $headers .= "Reply-To: {$data['email']}\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

            $fallbackMessage = "
            <h2>New University Application - ID: {$applicationId}</h2>
            <p><strong>Name:</strong> {$data['first_name']} {$data['last_name']}</p>
            <p><strong>Email:</strong> {$data['email']}</p>
            <p><strong>Phone:</strong> {$data['phone']}</p>
            <p><strong>First Program Choice:</strong> {$data['program_choice_1']}</p>
            <p><strong>Second Program Choice:</strong> {$data['program_choice_2']}</p>
            <p><strong>Study Mode:</strong> {$data['study_mode']}</p>
            <p><em>Please log in to the admin panel to view the complete application.</em></p>";

            $mailResult = mail($to, $subject, $fallbackMessage, $headers);
            if ($mailResult) {
                error_log("Fallback mail() function succeeded for application ID: {$applicationId}");
                // Also send confirmation to applicant using basic mail
                sendConfirmationEmailBasic($data, $applicationId);
            } else {
                error_log("Fallback mail() function also failed for application ID: {$applicationId}");
            }
            return $mailResult;
        } catch (Exception $fallbackError) {
            error_log("Email fallback also failed: " . $fallbackError->getMessage());
            throw new Exception("Email notification failed: " . $e->getMessage());
        }
    }
}

/**
 * Send confirmation email to applicant
 */
function sendConfirmationEmail($data, $applicationId)
{
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        // Additional SMTP options for better compatibility
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // Recipients
        $mail->setFrom(FROM_EMAIL, 'EduBridge SA');
        $mail->addAddress($data['email'], $data['first_name'] . ' ' . $data['last_name']);

        // Content
        $mail->isHTML(true);
        $mail->Subject = "Application Confirmation - EduBridge SA (ID: {$applicationId})";

        $confirmationMessage = "
        <html>
        <head>
            <style>
                body { margin:0; padding:0; font-family: Arial, sans-serif; line-height: 1.6; color: #333; background:#f4f6f8; }
                img { border:0; outline:none; text-decoration:none; max-width:100%; height:auto; }
                .header { background: linear-gradient(135deg, #1a5fb4 0%, #0d47a1 100%); color: white; padding: 24px; text-align: center; }
                .logo img { max-width: 140px; display:block; margin:0 auto; }
                .content { padding: 20px; background:#ffffff; }
                .highlight { background: #f8fbff; padding: 15px; border-left: 4px solid #1a5fb4; margin: 20px 0; }
                @media (max-width: 640px) { .content { padding: 16px; } }
            </style>
        </head>
        <body>
            <div class='header'>
                <div class='logo'>
                    <img src='" . BASE_URL . "/images/logo.png.jpg' alt='EduBridgeSA Logo'>
                </div>
                <h1 style='margin:6px 0 0 0;'>Application Received</h1>
            </div>
            <div class='content'>
                <p>Dear {$data['first_name']} {$data['last_name']},</p>
                
                <p>Thank you for submitting your application to EduBridge SA. We have successfully received your application.</p>
                
                <div class='highlight'>
                    <h3>Application Details</h3>
                    <p><strong>Application ID:</strong> {$applicationId}</p>
                    <p><strong>Date Submitted:</strong> " . date('Y-m-d H:i:s') . "</p>
                    <p><strong>First Program Choice:</strong> {$data['program_choice_1']}</p>
                    <p><strong>Study Mode:</strong> {$data['study_mode']}</p>
                </div>
                
                <h3>Next Steps</h3>
                <ul>
                    <li>Our admissions team will review your application</li>
                    <li>We will contact you within 5-7 business days</li>
                    <li>Please keep your application ID ({$applicationId}) for reference</li>
                </ul>
                
                <p>If you have any questions, please contact us at applications@edubridgesa.co.za</p>
                
                <p>Best regards,<br>
                EduBridge SA Admissions Team</p>
            </div>
        </body>
        </html>";

        $mail->Body = $confirmationMessage;
        $mail->AltBody = "Dear {$data['first_name']} {$data['last_name']}, Thank you for submitting your application to EduBridge SA. Your application ID is: {$applicationId}. We will contact you within 5-7 business days.";

        $mail->send();
        error_log("Confirmation email sent successfully to {$data['email']} for application ID: {$applicationId}");
    } catch (Exception $e) {
        error_log("Confirmation email failed for {$data['email']}: " . $e->getMessage());
        // Try basic mail as fallback
        sendConfirmationEmailBasic($data, $applicationId);
    }
}

/**
 * Send confirmation email using basic mail() function
 */
function sendConfirmationEmailBasic($data, $applicationId)
{
    try {
        $to = $data['email'];
        $subject = "Application Confirmation - EduBridge SA (ID: {$applicationId})";
        $from = FROM_EMAIL;

        $headers = "From: {$from}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

        $message = "
        <div style='background:linear-gradient(135deg, #1a5fb4 0%, #0d47a1 100%); color:#fff; padding:16px; text-align:center;'>
            <img src='" . BASE_URL . "/images/logo.png.jpg' alt='EduBridgeSA Logo' style='max-width:140px; display:block; margin:0 auto;'>
            <h2 style='margin:8px 0 0 0; font-family:Arial,sans-serif;'>Application Received</h2>
        </div>
        <div style='padding:16px; font-family:Arial,sans-serif; background:#ffffff;'>
            <p>Dear {$data['first_name']} {$data['last_name']},</p>
            <p>Thank you for submitting your application to EduBridge SA.</p>
            <p><strong>Application ID:</strong> {$applicationId}</p>
            <p><strong>Date:</strong> " . date('Y-m-d H:i:s') . "</p>
            <p>We will contact you within 5-7 business days.</p>
            <p>Best regards,<br>EduBridge SA Admissions Team</p>
        </div>";

        $result = mail($to, $subject, $message, $headers);
        if ($result) {
            error_log("Basic confirmation email sent successfully to {$data['email']} for application ID: {$applicationId}");
        } else {
            error_log("Basic confirmation email also failed for {$data['email']}");
        }
    } catch (Exception $e) {
        error_log("Basic confirmation email error: " . $e->getMessage());
    }
}

// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'errors' => []
];

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log('Application submission failed: Invalid request method');
    $_SESSION['form_message'] = 'Invalid request method.';
    $_SESSION['form_message_type'] = 'error';
    header('Location: apply.php');
    exit;
}

error_log('Application submission started - POST request received');

try {
    // Basic CSRF check (simplified)
    if (!isset($_POST[CSRF_TOKEN_NAME]) || !isset($_SESSION[CSRF_TOKEN_NAME])) {
        error_log('Application submission failed: CSRF token missing');
        throw new Exception('Security token missing. Please refresh the page and try again.');
    }

    if ($_POST[CSRF_TOKEN_NAME] !== $_SESSION[CSRF_TOKEN_NAME]) {
        error_log('Application submission failed: Invalid CSRF token');
        throw new Exception('Invalid security token. Please refresh the page and try again.');
    }

    error_log('Application submission: CSRF validation passed');

    // Map any potential field name variations
    $formData = $_POST;
    if (isset($formData['firstName']) && !isset($formData['first_name'])) {
        $formData['first_name'] = $formData['firstName'];
    }
    if (isset($formData['lastName']) && !isset($formData['last_name'])) {
        $formData['last_name'] = $formData['lastName'];
    }

    // Comprehensive validation
    $errors = validateFormData($formData);

    if (!empty($errors)) {
        error_log('Application submission failed: Form validation errors - ' . json_encode($errors));
        $_SESSION['form_errors'] = $errors;
        $_SESSION['form_message'] = 'Please correct the errors in your form.';
        $_SESSION['form_message_type'] = 'error';
        header('Location: apply.php');
        exit;
    }

    error_log('Application submission: Form validation passed');

    // Handle file uploads
    $uploadedFiles = handleFileUploads($_FILES);

    if (isset($uploadedFiles['errors']) && !empty($uploadedFiles['errors'])) {
        error_log('Application submission failed: File upload errors - ' . json_encode($uploadedFiles['errors']));
        $_SESSION['form_errors'] = $uploadedFiles['errors'];
        $_SESSION['form_message'] = 'There were issues with your file uploads.';
        $_SESSION['form_message_type'] = 'error';
        header('Location: apply.php');
        exit;
    }

    error_log('Application submission: File uploads handled successfully');

    // Use global PDO connection from config
    global $pdo;
    if (!isset($pdo) || !$pdo) {
        // Attempt to create PDO if not available
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } catch (Exception $e) {
            error_log('Application submission failed: Database connection error - ' . $e->getMessage());
            throw new Exception('Database connection failed.');
        }
    }

    error_log('Application submission: Database connection (PDO) available');

    // Save application data with file uploads
    error_log('Application submission: Attempting to save application data');
    $result = saveApplicationData($pdo, $formData, $uploadedFiles);
    $applicationId = $result['application_id'];
    $applicationRef = $result['application_ref'];
    error_log('Application submission: Application data saved successfully with ID: ' . $applicationId . ' and Reference: ' . $applicationRef);

    if ($applicationId) {
        // Send admin email notification (with error handling)
        try {
            error_log("Attempting to send admin email notification for application ID: {$applicationId}");
            error_log("Email will be sent to: " . ADMIN_EMAIL);
            sendEmailNotification($formData, $applicationId);
            error_log("Admin email notification sent successfully for application ID: {$applicationId}");
        } catch (Exception $emailError) {
            // Log email error but don't fail the application
            error_log('Admin email notification failed for application ID ' . $applicationId . ': ' . $emailError->getMessage());
        }

        // Send document upload notification to applicant (with error handling)
        try {
            error_log("Attempting to send document upload notification for application ref: {$applicationRef}");
            error_log("Email will be sent to: " . $formData['email']);
            sendDocumentUploadNotification($formData['email'], $formData['first_name'], $applicationRef);
            error_log("Document upload notification sent successfully for application ref: {$applicationRef}");
        } catch (Exception $emailError) {
            // Log email error but don't fail the application
            error_log('Document upload notification failed for application ref ' . $applicationRef . ': ' . $emailError->getMessage());
        }

        // Set success data for student dashboard
        $_SESSION['application_id'] = $applicationId;
        $_SESSION['application_ref'] = $applicationRef;
        $_SESSION['applicant_name'] = $formData['first_name'] . ' ' . $formData['last_name'];
        $_SESSION['applicant_email'] = $formData['email'];
        $_SESSION['form_message'] = 'Your application has been submitted successfully! Application Reference: ' . $applicationRef . ' (ID: ' . $applicationId . ')';
        $_SESSION['form_message_type'] = 'success';

        // Regenerate CSRF token
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));

        // Check if user is logged in to determine redirect
        if (isset($_SESSION['student_id']) && !empty($_SESSION['student_id'])) {
            header('Location: student-dashboard.php');
        } else {
            header('Location: thank-you.php');
        }
        exit;
    } else {
        throw new Exception('Failed to save your application. Please try again later.');
    }
} catch (Exception $e) {
    $_SESSION['form_message'] = 'An error occurred: ' . $e->getMessage();
    $_SESSION['form_message_type'] = 'error';

    // Log error for debugging
    error_log('Application form error: ' . $e->getMessage());

    header('Location: apply.php');
    exit;
}
