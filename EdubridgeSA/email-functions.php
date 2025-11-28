<?php
/**
 * Email Functions for Student Application System
 * EduBridge SA
 * 
 * This file contains all email-related functionality including:
 * - Application confirmation emails
 * - Document upload reminders
 * - Status update notifications
 */

require_once 'config_application.php';

// Check if PHPMailer is available
if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    // Try to include PHPMailer from common locations
    $phpmailer_paths = [
        __DIR__ . '/vendor/autoload.php',
        __DIR__ . '/phpmailer/autoload.php',
        __DIR__ . '/PHPMailer/src/PHPMailer.php'
    ];
    
    $phpmailer_loaded = false;
    foreach ($phpmailer_paths as $path) {
        if (file_exists($path)) {
            if (strpos($path, 'autoload.php') !== false) {
                require_once $path;
            } else {
                require_once $path;
                require_once dirname($path) . '/SMTP.php';
                require_once dirname($path) . '/Exception.php';
            }
            $phpmailer_loaded = true;
            break;
        }
    }
    
    if (!$phpmailer_loaded) {
        // Fallback: Use basic mail() function
        define('USE_BASIC_MAIL', true);
    }
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Send application confirmation email
 */
function sendApplicationConfirmationEmail($applicationData, $referenceNumber) {
    $subject = "Application Confirmation - Reference: $referenceNumber";
    $recipientEmail = $applicationData['email_address'];
    $recipientName = $applicationData['full_name'] . ' ' . $applicationData['surname'];
    
    // Create email content
    $emailBody = createConfirmationEmailBody($applicationData, $referenceNumber);
    
    // Send email
    $result = sendEmail($recipientEmail, $recipientName, $subject, $emailBody, 'confirmation');
    
    // Log email attempt
    logEmailNotification($applicationData['id'], 'confirmation', $recipientEmail, $subject, $emailBody, $result);
    
    return $result;
}

/**
 * Send document upload reminder email
 */
function sendDocumentReminderEmail($applicationData, $referenceNumber) {
    $subject = "Document Upload Reminder - Reference: $referenceNumber";
    $recipientEmail = $applicationData['email_address'];
    $recipientName = $applicationData['full_name'] . ' ' . $applicationData['surname'];
    
    // Create email content
    $emailBody = createDocumentReminderEmailBody($applicationData, $referenceNumber);
    
    // Send email
    $result = sendEmail($recipientEmail, $recipientName, $subject, $emailBody, 'reminder');
    
    // Log email attempt
    logEmailNotification($applicationData['id'], 'reminder', $recipientEmail, $subject, $emailBody, $result);
    
    return $result;
}

/**
 * Core email sending function
 */
function sendEmail($toEmail, $toName, $subject, $body, $emailType = 'general') {
    if (defined('USE_BASIC_MAIL') && USE_BASIC_MAIL) {
        return sendBasicEmail($toEmail, $toName, $subject, $body);
    }
    
    try {
        $mail = new PHPMailer(true);
        // Per-request email debug toggle: use DEBUG_MODE or ?debug_email=1
        $enableDebug = (defined('DEBUG_MODE') && DEBUG_MODE) || (isset($_GET['debug_email']) && $_GET['debug_email'] === '1');
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = SMTP_AUTH;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        $mail->Timeout = SMTP_TIMEOUT;

        // Enable SMTP debug logging for diagnostics
        if ($enableDebug) {
            $mail->SMTPDebug = SMTP::DEBUG_SERVER; // log client/server conversation
            $mail->Debugoutput = 'error_log';      // route to PHP error_log
        }
        
        // Recipients
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->addReplyTo(REPLY_TO_EMAIL, REPLY_TO_NAME);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));
        
        $mail->send();
        return ['success' => true, 'message' => 'Email sent successfully'];
        
    } catch (Exception $e) {
        $err = isset($mail) && !empty($mail->ErrorInfo) ? $mail->ErrorInfo : $e->getMessage();
        error_log("Email send failure ({$emailType}): " . $err);
        return ['success' => false, 'message' => "Email could not be sent. Error: $err"];
    }
}

/**
 * Fallback email function using basic mail()
 */
function sendBasicEmail($toEmail, $toName, $subject, $body) {
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . FROM_NAME . " <" . FROM_EMAIL . ">" . "\r\n";
    $headers .= "Reply-To: " . REPLY_TO_EMAIL . "\r\n";
    
    $success = mail($toEmail, $subject, $body, $headers);
    
    if ($success) {
        return ['success' => true, 'message' => 'Email sent successfully (basic mail)'];
    } else {
        return ['success' => false, 'message' => 'Failed to send email using basic mail function'];
    }
}

/**
 * Create confirmation email body
 */
function createConfirmationEmailBody($applicationData, $referenceNumber) {
    $baseUrl = BASE_URL;
    
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { margin:0; padding:0; font-family: Arial, sans-serif; line-height: 1.6; color: #333; background:#f4f6f8; }
            img { border:0; outline:none; text-decoration:none; max-width:100%; height:auto; }
            .container { max-width: 600px; margin: 0 auto; padding: 0; }
            .header { background: linear-gradient(135deg, #1a5fb4 0%, #0d47a1 100%); color: white; padding: 24px; text-align: center; border-radius: 12px 12px 0 0; }
            .logo { margin-bottom: 8px; }
            .logo img { max-width: 140px; display:block; margin:0 auto; }
            .content { background: #ffffff; padding: 24px; border-radius: 0 0 12px 12px; }
            .reference-box { background: #e8f4fd; border: 2px solid #1a73e8; padding: 16px; margin: 20px 0; text-align: center; border-radius: 8px; }
            .reference-number { font-size: 24px; font-weight: bold; color: #0d47a1; letter-spacing: 0.5px; }
            .section-title { color:#0d47a1; margin-top: 24px; margin-bottom:8px; font-size:18px; }
            .next-steps { background: #f8fbff; padding: 16px; margin: 20px 0; border-radius: 8px; border-left: 4px solid #1a5fb4; }
            .button { display: inline-block; background: #1a5fb4; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 8px 6px; font-weight:600; }
            .button:hover { background:#164a96; }
            .footer { text-align: center; margin-top: 24px; color: #666; font-size: 13px; }
            .social a { color:#1a5fb4; text-decoration:none; margin:0 8px; }
            .social a:hover { text-decoration:underline; }
            @media (max-width: 640px) {
                .content { padding: 18px; }
                .header { padding: 18px; }
                .reference-number { font-size: 20px; }
                .button { width:100%; box-sizing:border-box; margin:6px 0; text-align:center; }
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <div class='logo'>
                    <img src='$baseUrl/images/logo.png.jpg' alt='EduBridgeSA Logo'>
                </div>
                <h2 style='margin:6px 0 0 0;'>Application Confirmation</h2>
            </div>
            
            <div class='content'>
                <p>Dear {$applicationData['full_name']} {$applicationData['surname']},</p>
                
                <p>Thank you for submitting your university application through EduBridge SA. We have successfully received your application details.</p>
                
                <div class='reference-box'>
                    <p><strong>Your Application Reference Number:</strong></p>
                    <div class='reference-number'>$referenceNumber</div>
                    <p><em>Please save this reference number for future correspondence</em></p>
                </div>
                
                <h3 class='section-title'>Application Summary</h3>
                <ul>
                    <li><strong>Name:</strong> {$applicationData['full_name']} {$applicationData['surname']}</li>
                    <li><strong>Email:</strong> {$applicationData['email_address']}</li>
                    <li><strong>Phone:</strong> " . ($applicationData['cellphone_number'] ?? 'Not provided') . "</li>
                    <li><strong>ID Number:</strong> " . ($applicationData['id_number'] ?? 'Not provided') . "</li>
                    <li><strong>Country:</strong> " . ($applicationData['country_of_residence'] ?? 'Not provided') . "</li>
                </ul>
                
                <div class='next-steps'>
                    <h3 class='section-title' style='margin-top:0;'>Next Steps</h3>
                    <ol>
                        <li><strong>Upload Documents:</strong> Upload your required documents using the link below</li>
                        <li><strong>Document Review:</strong> Our team will review your documents within 3-5 business days</li>
                        <li><strong>Application Processing:</strong> We will process your university applications</li>
                        <li><strong>Updates:</strong> You will receive email updates on your application status</li>
                    </ol>
                </div>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='$baseUrl/document_upload.php?ref=$referenceNumber' class='button' aria-label='Upload Documents'>Upload Documents</a>
                    <a href='$baseUrl/application_status.php?ref=$referenceNumber' class='button' aria-label='Check Application Status'>Check Status</a>
                </div>
                
                <h3 class='section-title'>Required Documents</h3>
                <ul>
                    <li>Certified copy of ID document</li>
                    <li>Proof of residential address</li>
                    <li>Parent/Guardian certified ID copy (if applicable)</li>
                    <li>Latest academic results</li>
                </ul>
                
                <p><strong>Important:</strong> Documents can be uploaded at any time using your reference number. You don't need to upload all documents immediately.</p>
                
                <div class='footer'>
                    <p>If you have any questions, please contact us:</p>
                    <p>Email: " . ADMIN_EMAIL . " • Website: $baseUrl</p>
                    <div class='social' style='margin-top:8px;'>
                        <a href='#' aria-label='Facebook'>Facebook</a>
                        <a href='#' aria-label='Twitter'>Twitter</a>
                        <a href='#' aria-label='Instagram'>Instagram</a>
                        <a href='#' aria-label='LinkedIn'>LinkedIn</a>
                    </div>
                    <p style='margin-top:12px;'><em>EduBridge SA • Bridging Dreams to Degrees</em></p>
                </div>
            </div>
        </div>
    </body>
    </html>";
    
    return $html;
}

/**
 * Create document reminder email body
 */
function createDocumentReminderEmailBody($applicationData, $referenceNumber) {
    $baseUrl = BASE_URL;
    
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .reference-box { background: #fff3e0; border: 2px solid #ff9800; padding: 15px; margin: 20px 0; text-align: center; border-radius: 5px; }
            .reference-number { font-size: 20px; font-weight: bold; color: #f57c00; }
            .reminder-box { background: #fff; padding: 20px; margin: 20px 0; border-radius: 5px; border-left: 4px solid #ff9800; }
            .button { display: inline-block; background: #ff9800; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
            .footer { text-align: center; margin-top: 30px; color: #666; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🎓 EduBridge SA</h1>
                <h2>Document Upload Reminder</h2>
            </div>
            
            <div class='content'>
                <p>Dear {$applicationData['full_name']} {$applicationData['surname']},</p>
                
                <p>This is a friendly reminder that you can upload your supporting documents for your university application.</p>
                
                <div class='reference-box'>
                    <p><strong>Your Application Reference Number:</strong></p>
                    <div class='reference-number'>$referenceNumber</div>
                </div>
                
                <div class='reminder-box'>
                    <h3>📄 Documents You Can Upload:</h3>
                    <ul>
                        <li>Certified copy of ID document</li>
                        <li>Proof of residential address</li>
                        <li>Parent/Guardian certified ID copy</li>
                        <li>Latest academic results</li>
                    </ul>
                    
                    <p><strong>Note:</strong> Document upload is optional and can be done at any time. Having documents uploaded helps speed up the application process.</p>
                </div>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='$baseUrl/document_upload.php?ref=$referenceNumber' class='button'>📎 Upload Documents Now</a>
                    <a href='$baseUrl/application_status.php?ref=$referenceNumber' class='button'>📊 Check Status</a>
                </div>
                
                <div class='footer'>
                    <p>If you have any questions, please contact us:</p>
                    <p>📧 Email: " . ADMIN_EMAIL . "</p>
                    <p>🌐 Website: $baseUrl</p>
                    <p><em>EduBridge SA - Your pathway to higher education</em></p>
                </div>
            </div>
        </div>
    </body>
    </html>";
    
    return $html;
}

/**
 * Log email notifications to database
 */
function logEmailNotification($applicationId, $emailType, $recipientEmail, $subject, $messageBody, $result) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            INSERT INTO email_notifications 
            (application_id, email_type, recipient_email, subject, message_body, sent_status, sent_at, error_message) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $sentStatus = $result['success'] ? 'sent' : 'failed';
        $sentAt = $result['success'] ? date('Y-m-d H:i:s') : null;
        $errorMessage = $result['success'] ? null : $result['message'];
        
        $stmt->execute([
            $applicationId,
            $emailType,
            $recipientEmail,
            $subject,
            $messageBody,
            $sentStatus,
            $sentAt,
            $errorMessage
        ]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Failed to log email notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Test email configuration
 */
function testEmailConfiguration() {
    $testEmail = ADMIN_EMAIL;
    $testSubject = "EduBridge SA - Email Configuration Test";
    $testBody = "
    <h2>Email Configuration Test</h2>
    <p>This is a test email to verify that the email configuration is working correctly.</p>
    <p>If you receive this email, your SMTP settings are configured properly.</p>
    <p>Test sent at: " . date('Y-m-d H:i:s') . "</p>
    ";
    
    return sendEmail($testEmail, 'EduBridge SA Admin', $testSubject, $testBody, 'test');
}

/**
 * Get email statistics
 */
function getEmailStatistics($applicationId = null) {
    global $pdo;
    
    try {
        if ($applicationId) {
            $stmt = $pdo->prepare("
                SELECT email_type, sent_status, COUNT(*) as count 
                FROM email_notifications 
                WHERE application_id = ? 
                GROUP BY email_type, sent_status
            ");
            $stmt->execute([$applicationId]);
        } else {
            $stmt = $pdo->query("
                SELECT email_type, sent_status, COUNT(*) as count 
                FROM email_notifications 
                GROUP BY email_type, sent_status
            ");
        }
        
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Send email verification email
 */
function sendVerificationEmail($email, $name, $verificationToken) {
    $subject = "EduBridge SA - Email Verification Required";
    $baseUrl = BASE_URL;
    
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .verification-box { background: #e8f5e8; border: 2px solid #4CAF50; padding: 20px; margin: 20px 0; text-align: center; border-radius: 5px; }
            .button { display: inline-block; background: #4CAF50; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; margin: 10px 5px; font-weight: bold; }
            .footer { text-align: center; margin-top: 30px; color: #666; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🎓 EduBridge SA</h1>
                <h2>Email Verification</h2>
            </div>
            
            <div class='content'>
                <p>Dear $name,</p>
                
                <p>Thank you for registering with EduBridge SA. To complete your registration and secure your account, please verify your email address.</p>
                
                <div class='verification-box'>
                    <h3>🔐 Verify Your Email Address</h3>
                    <p>Click the button below to verify your email address and activate your account:</p>
                    <a href='$baseUrl/verify-email.php?token=$verificationToken' class='button'>✓ Verify Email Address</a>
                </div>
                
                <p><strong>Important:</strong></p>
                <ul>
                    <li>This verification link will expire in 24 hours</li>
                    <li>You must verify your email to access all features</li>
                    <li>If you didn't create this account, please ignore this email</li>
                </ul>
                
                <p>If the button doesn't work, copy and paste this link into your browser:</p>
                <p style='word-break: break-all; background: #f0f0f0; padding: 10px; border-radius: 3px;'>
                    $baseUrl/verify-email.php?token=$verificationToken
                </p>
                
                <div class='footer'>
                    <p>If you have any questions, please contact us:</p>
                    <p>📧 Email: " . ADMIN_EMAIL . "</p>
                    <p>🌐 Website: $baseUrl</p>
                    <p><em>EduBridge SA - Your pathway to higher education</em></p>
                </div>
            </div>
        </div>
    </body>
    </html>";
    
    $result = sendEmail($email, $name, $subject, $html, 'verification');
    
    // Log email attempt if we have a database connection
    try {
        logEmailNotification(0, 'verification', $email, $subject, $html, $result);
    } catch (Exception $e) {
        // Continue even if logging fails
    }
    
    return $result;
}

/**
 * Send password reset email
 */
function sendPasswordResetEmail($email, $name, $resetToken) {
    $subject = "EduBridge SA - Password Reset Request";
    $baseUrl = BASE_URL;
    
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .reset-box { background: #ffebee; border: 2px solid #f44336; padding: 20px; margin: 20px 0; text-align: center; border-radius: 5px; }
            .button { display: inline-block; background: #f44336; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; margin: 10px 5px; font-weight: bold; }
            .footer { text-align: center; margin-top: 30px; color: #666; font-size: 14px; }
            .security-notice { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 20px 0; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🎓 EduBridge SA</h1>
                <h2>Password Reset Request</h2>
            </div>
            
            <div class='content'>
                <p>Dear $name,</p>
                
                <p>We received a request to reset your password for your EduBridge SA account. If you made this request, click the button below to reset your password.</p>
                
                <div class='reset-box'>
                    <h3>🔑 Reset Your Password</h3>
                    <p>Click the button below to create a new password:</p>
                    <a href='$baseUrl/reset-password.php?token=$resetToken' class='button'>🔄 Reset Password</a>
                </div>
                
                <div class='security-notice'>
                    <h4>🛡️ Security Information:</h4>
                    <ul>
                        <li>This password reset link will expire in 1 hour</li>
                        <li>If you didn't request this reset, please ignore this email</li>
                        <li>Your password will remain unchanged until you create a new one</li>
                        <li>For security, this link can only be used once</li>
                    </ul>
                </div>
                
                <p>If the button doesn't work, copy and paste this link into your browser:</p>
                <p style='word-break: break-all; background: #f0f0f0; padding: 10px; border-radius: 3px;'>
                    $baseUrl/reset-password.php?token=$resetToken
                </p>
                
                <p><strong>If you didn't request this password reset:</strong></p>
                <p>Please ignore this email. Your account is secure and no changes have been made.</p>
                
                <div class='footer'>
                    <p>If you have any questions, please contact us:</p>
                    <p>📧 Email: " . ADMIN_EMAIL . "</p>
                    <p>🌐 Website: $baseUrl</p>
                    <p><em>EduBridge SA - Your pathway to higher education</em></p>
                </div>
            </div>
        </div>
    </body>
    </html>";
    
    $result = sendEmail($email, $name, $subject, $html, 'password_reset');
    
    // Log email attempt if we have a database connection
    try {
        logEmailNotification(0, 'password_reset', $email, $subject, $html, $result);
    } catch (Exception $e) {
        // Continue even if logging fails
    }
    
    return $result;
}

/**
 * Send welcome email
 */
function sendWelcomeEmail($email, $name, $loginUrl = null) {
    $subject = "Welcome to EduBridge SA - Your Journey Begins!";
    $baseUrl = BASE_URL;
    $loginUrl = $loginUrl ?: "$baseUrl/student-login.php";
    
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .welcome-box { background: #e3f2fd; border: 2px solid #2196F3; padding: 20px; margin: 20px 0; text-align: center; border-radius: 5px; }
            .features-box { background: #fff; padding: 20px; margin: 20px 0; border-radius: 5px; border-left: 4px solid #4CAF50; }
            .button { display: inline-block; background: #667eea; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; margin: 10px 5px; font-weight: bold; }
            .footer { text-align: center; margin-top: 30px; color: #666; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🎓 EduBridge SA</h1>
                <h2>Welcome to Your Future!</h2>
            </div>
            
            <div class='content'>
                <p>Dear $name,</p>
                
                <div class='welcome-box'>
                    <h3>🌟 Welcome to EduBridge SA!</h3>
                    <p>Your account has been successfully created and verified. You're now ready to begin your journey toward higher education!</p>
                </div>
                
                <p>EduBridge SA is your gateway to university applications and educational opportunities across South Africa. We're here to support you every step of the way.</p>
                
                <div class='features-box'>
                    <h3>🚀 What You Can Do Now:</h3>
                    <ul>
                        <li><strong>Apply to Universities:</strong> Submit applications to multiple institutions</li>
                        <li><strong>Upload Documents:</strong> Securely store your academic records and certificates</li>
                        <li><strong>Track Applications:</strong> Monitor the status of your university applications</li>
                        <li><strong>Get Support:</strong> Access our guidance and support services</li>
                        <li><strong>Stay Updated:</strong> Receive notifications about your applications</li>
                    </ul>
                </div>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='$loginUrl' class='button'>🔑 Login to Your Account</a>
                    <a href='$baseUrl/student-apply.php' class='button'>📝 Start Application</a>
                </div>
                
                <h3>📚 Next Steps:</h3>
                <ol>
                    <li><strong>Complete Your Profile:</strong> Add your personal and academic information</li>
                    <li><strong>Upload Documents:</strong> Prepare your ID, academic records, and other required documents</li>
                    <li><strong>Research Universities:</strong> Explore our university database and programs</li>
                    <li><strong>Submit Applications:</strong> Apply to your chosen institutions</li>
                </ol>
                
                <p><strong>Need Help?</strong> Our support team is here to assist you with any questions about the application process, document requirements, or technical issues.</p>
                
                <div class='footer'>
                    <p>If you have any questions, please contact us:</p>
                    <p>📧 Email: " . ADMIN_EMAIL . "</p>
                    <p>🌐 Website: $baseUrl</p>
                    <p><em>EduBridge SA - Your pathway to higher education</em></p>
                    <hr>
                    <p><small>This email was sent because you created an account with EduBridge SA. If you believe this was sent in error, please contact our support team.</small></p>
                </div>
            </div>
        </div>
    </body>
    </html>";
    
    $result = sendEmail($email, $name, $subject, $html, 'welcome');
    
    // Log email attempt if we have a database connection
    try {
        logEmailNotification(0, 'welcome', $email, $subject, $html, $result);
    } catch (Exception $e) {
        // Continue even if logging fails
    }
    
    return $result;
}

/**
 * Function aliases for backward compatibility
 * These functions match the names expected by student_application.php
 */

/**
 * Send application confirmation (alias for sendApplicationConfirmationEmail)
 */
function sendApplicationConfirmation($email, $name, $referenceNumber) {
    // Create a basic application data array for the existing function
    $applicationData = [
        'email_address' => $email,
        'full_name' => explode(' ', $name)[0] ?? $name,
        'surname' => explode(' ', $name)[1] ?? '',
        'id' => 0 // Will be updated when we have the actual application ID
    ];
    
    return sendApplicationConfirmationEmail($applicationData, $referenceNumber);
}

/**
 * Send document reminder (alias for sendDocumentReminderEmail)
 */
function sendDocumentReminder($email, $name, $referenceNumber) {
    // Create a basic application data array for the existing function
    $applicationData = [
        'email_address' => $email,
        'full_name' => explode(' ', $name)[0] ?? $name,
        'surname' => explode(' ', $name)[1] ?? '',
        'id' => 0 // Will be updated when we have the actual application ID
    ];
    
    return sendDocumentReminderEmail($applicationData, $referenceNumber);
}
?>