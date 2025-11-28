<?php
/**
 * Document Upload Processing Script
 * EduBridge SA - University Application System
 * 
 * Handles document file uploads and updates application status
 */

session_start();
require_once 'config.php';

// Function to sanitize input data
function sanitizeInput($data) {
    if ($data === null) return null;
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Function to handle file upload
function handleDocumentUpload($fileInputName, $applicationId, $docType) {
    if (!isset($_FILES[$fileInputName]) || $_FILES[$fileInputName]['error'] === UPLOAD_ERR_NO_FILE) {
        throw new Exception("No file was uploaded");
    }
    
    $file = $_FILES[$fileInputName];
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File size exceeds server limit',
            UPLOAD_ERR_FORM_SIZE => 'File size exceeds form limit',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        
        $errorMessage = $errorMessages[$file['error']] ?? 'Unknown upload error';
        throw new Exception($errorMessage);
    }
    
    // Check file size (5MB max)
    $maxSize = MAX_FILE_SIZE;
    if ($file['size'] > $maxSize) {
        throw new Exception("File size exceeds " . ($maxSize / 1024 / 1024) . "MB limit");
    }
    
    // Check file type
    $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedMimes)) {
        throw new Exception("Invalid file type. Only PDF, JPEG, and PNG files are allowed");
    }
    
    // Validate file extension
    $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($fileExtension, $allowedExtensions)) {
        throw new Exception("Invalid file extension. Only PDF, JPG, JPEG, and PNG files are allowed");
    }
    
    // Create upload directory if it doesn't exist
    $uploadDir = UPLOAD_DIR . '/documents/' . $applicationId . '/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception("Failed to create upload directory");
        }
    }
    
    // Generate unique filename
    $filename = $docType . '_' . time() . '_' . uniqid() . '.' . $fileExtension;
    $filepath = $uploadDir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception("Failed to move uploaded file");
    }
    
    return $filepath;
}

// Function to send email notification
function sendEmailNotification($to, $subject, $message, $applicationId, $emailType) {
    global $pdo;
    
    try {
        // Email headers
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: " . FROM_NAME . " <" . FROM_EMAIL . ">" . "\r\n";
        $headers .= "Reply-To: " . REPLY_TO_NAME . " <" . REPLY_TO_EMAIL . ">" . "\r\n";
        
        // Send email
        $emailSent = mail($to, $subject, $message, $headers);
        
        // Log email attempt
        $logSql = "INSERT INTO email_logs (application_id, email_type, recipient_email, subject, status, error_message) 
                   VALUES (?, ?, ?, ?, ?, ?)";
        $logStmt = $pdo->prepare($logSql);
        $logStmt->execute([
            $applicationId,
            $emailType,
            $to,
            $subject,
            $emailSent ? 'sent' : 'failed',
            $emailSent ? null : 'Mail function returned false'
        ]);
        
        return $emailSent;
        
    } catch (Exception $e) {
        // Log email error
        $logSql = "INSERT INTO email_logs (application_id, email_type, recipient_email, subject, status, error_message) 
                   VALUES (?, ?, ?, ?, ?, ?)";
        $logStmt = $pdo->prepare($logSql);
        $logStmt->execute([
            $applicationId,
            $emailType,
            $to,
            $subject,
            'failed',
            $e->getMessage()
        ]);
        
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}

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

// Get and validate parameters
$applicationId = (int)$_POST['application_id'];
$token = sanitizeInput($_POST['token']);
$docType = sanitizeInput($_POST['doc_type']);

// Verify token
$expectedToken = md5($applicationId . AUTH_SALT);
if ($token !== $expectedToken) {
    $_SESSION['form_message'] = 'Invalid access token.';
    $_SESSION['form_message_type'] = 'error';
    header('Location: apply_improved.php');
    exit;
}

// Validate document type
$allowedDocTypes = ['id_document', 'matric_certificate', 'academic_transcript', 'proof_of_residence'];
if (!in_array($docType, $allowedDocTypes)) {
    $_SESSION['form_message'] = 'Invalid document type.';
    $_SESSION['form_message_type'] = 'error';
    header('Location: upload-documents.php?app_id=' . $applicationId . '&token=' . $token);
    exit;
}

try {
    // Get application details
    $sql = "SELECT * FROM applications WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$applicationId]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$application) {
        $_SESSION['form_message'] = 'Application not found.';
        $_SESSION['form_message_type'] = 'error';
        header('Location: apply_improved.php');
        exit;
    }
    
    // Check if document type already exists
    $checkSql = "SELECT id FROM documents WHERE application_id = ? AND doc_type = ?";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([$applicationId, $docType]);
    
    if ($checkStmt->fetch()) {
        $_SESSION['form_message'] = 'This document type has already been uploaded.';
        $_SESSION['form_message_type'] = 'warning';
        header('Location: upload-documents.php?app_id=' . $applicationId . '&token=' . $token);
        exit;
    }
    
    // Handle file upload
    $filePath = handleDocumentUpload('document', $applicationId, $docType);
    
    // Insert document record
    $insertSql = "INSERT INTO documents (application_id, doc_type, file_path) VALUES (?, ?, ?)";
    $insertStmt = $pdo->prepare($insertSql);
    $insertStmt->execute([$applicationId, $docType, $filePath]);
    
    // Check if all required documents are uploaded (proof_of_residence is optional)
    $requiredDocs = ['id_document', 'matric_certificate', 'academic_transcript'];
    $uploadedSql = "SELECT DISTINCT doc_type FROM documents WHERE application_id = ?";
    $uploadedStmt = $pdo->prepare($uploadedSql);
    $uploadedStmt->execute([$applicationId]);
    $uploadedDocTypes = $uploadedStmt->fetchAll(PDO::FETCH_COLUMN);
    
    $allUploaded = true;
    foreach ($requiredDocs as $requiredDoc) {
        if (!in_array($requiredDoc, $uploadedDocTypes)) {
            $allUploaded = false;
            break;
        }
    }
    
    // Update application status if all documents are uploaded
    if ($allUploaded && $application['status'] === 'Submitted (without docs)') {
        $updateSql = "UPDATE applications SET status = 'Submitted (with docs)' WHERE id = ?";
        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute([$applicationId]);
        
        // Generate application reference
        $applicationRef = 'EBS-' . str_pad($applicationId, 6, '0', STR_PAD_LEFT);
        
        // Send completion email
        $completionSubject = 'Application Complete – Thank You';
        $completionMessage = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Application Complete</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
                .success-badge { background: #28a745; color: white; padding: 8px 16px; border-radius: 20px; display: inline-block; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎉 EduBridge SA</h1>
                    <h2>Application Complete!</h2>
                </div>
                <div class='content'>
                    <p>Dear {$application['first_name']} {$application['last_name']},</p>
                    
                    <p>Congratulations! Your university application is now complete.</p>
                    
                    <p><strong>Application Reference:</strong> {$applicationRef}</p>
                    <p><strong>Status:</strong> <span class='success-badge'>Submitted (with docs)</span></p>
                    <p><strong>Completion Date:</strong> " . date('F j, Y g:i A') . "</p>
                    
                    <h3>What Happens Next?</h3>
                    <p>Our admissions team will now review your complete application, including all uploaded documents. You can expect to hear from us within <strong>5-10 business days</strong> regarding the status of your application.</p>
                    
                    <h3>Your Program Choices:</h3>
                    <p><strong>1st Choice:</strong> {$application['first_choice_program']} at {$application['first_choice_institution']}</p>
                    " . (!empty($application['second_choice_program']) ? "<p><strong>2nd Choice:</strong> {$application['second_choice_program']} at {$application['second_choice_institution']}</p>" : "") . "
                    " . (!empty($application['third_choice_program']) ? "<p><strong>3rd Choice:</strong> {$application['third_choice_program']} at {$application['third_choice_institution']}</p>" : "") . "
                    
                    <h3>Important Reminders:</h3>
                    <ul>
                        <li>Keep your application reference number for future correspondence</li>
                        <li>Check your email regularly for updates</li>
                        <li>Ensure your contact information is up to date</li>
                        <li>Prepare for potential interviews or additional requirements</li>
                    </ul>
                    
                    <p>Thank you for choosing EduBridge SA for your higher education journey. We're excited to help you achieve your academic goals!</p>
                    
                    <p>If you have any questions, please contact us:</p>
                    <ul>
                        <li>Email: " . ADMIN_EMAIL . "</li>
                        <li>Phone: +27 11 123 4567</li>
                    </ul>
                </div>
                <div class='footer'>
                    <p>© " . date('Y') . " EduBridge SA. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        sendEmailNotification($application['email'], $completionSubject, $completionMessage, $applicationId, 'application_complete');
        
        $_SESSION['form_message'] = 'Document uploaded successfully! Your application is now complete.';
        $_SESSION['form_message_type'] = 'success';
    } else {
        $_SESSION['form_message'] = 'Document uploaded successfully!';
        $_SESSION['form_message_type'] = 'success';
    }
    
    // Redirect back to upload page
    header('Location: upload-documents.php?app_id=' . $applicationId . '&token=' . $token);
    exit;
    
} catch (Exception $e) {
    error_log("Error in process_document_upload.php: " . $e->getMessage());
    $_SESSION['form_message'] = 'Upload failed: ' . $e->getMessage();
    $_SESSION['form_message_type'] = 'error';
    header('Location: upload-documents.php?app_id=' . $applicationId . '&token=' . $token);
    exit;
}
?>