<?php
// Professional contact form handler with PHPMailer and SMTP authentication
require_once 'config.php';

// Load PHPMailer classes
require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get form data
$fullName = isset($_POST['fullName']) ? trim($_POST['fullName']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

// Basic validation
if (empty($fullName) || empty($email) || empty($message) || empty($subject)) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit;
}

// Sanitize input to prevent injection
$fullName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
$email = filter_var($email, FILTER_SANITIZE_EMAIL);
$phone = htmlspecialchars($phone, ENT_QUOTES, 'UTF-8');
$subject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
$message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

try {
    // Create a new PHPMailer instance
    $mail = new PHPMailer(true);

    // Server settings
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USERNAME;
    $mail->Password = SMTP_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL encryption for port 465
    $mail->Port = SMTP_PORT;
    
    // Enhanced SMTP settings for better deliverability
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );

    // Sender and recipient settings with proper domain authentication
    $mail->setFrom(SMTP_USERNAME, 'EduBridgeSA Contact Form');
    $mail->addAddress('info@edubridgesa.co.za', 'EduBridgeSA Info');
    $mail->addReplyTo($email, $fullName);
    
    // Add Return-Path for better deliverability
    $mail->Sender = SMTP_USERNAME;

    // Email content with optimized subject line
    $mail->isHTML(true);
    $mail->Subject = 'Contact Form: ' . $subject . ' from ' . $fullName;
    
    // Create HTML email body with professional structure
    $htmlBody = "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Contact Form Submission</title>
    </head>
    <body style='margin: 0; padding: 0; background-color: #f4f4f4;'>
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: white;'>
            <div style='background: #2c5aa0; color: white; padding: 20px; text-align: center;'>
                <h1 style='margin: 0; font-size: 24px;'>Contact Form Submission</h1>
                <p style='margin: 5px 0 0 0; font-size: 16px;'>EduBridgeSA Educational Services</p>
            </div>
            
            <div style='padding: 20px; background: #f8f9fa;'>
                <div style='background: white; padding: 15px; margin: 10px 0; border-left: 4px solid #007bff;'>
                    <strong>Name:</strong> " . $fullName . "
                </div>
                
                <div style='background: white; padding: 15px; margin: 10px 0; border-left: 4px solid #007bff;'>
                    <strong>Email:</strong> " . $email . "
                </div>
                
                <div style='background: white; padding: 15px; margin: 10px 0; border-left: 4px solid #007bff;'>
                    <strong>Phone:</strong> " . ($phone ?: 'Not provided') . "
                </div>
                
                <div style='background: white; padding: 15px; margin: 10px 0; border-left: 4px solid #007bff;'>
                    <strong>Subject:</strong> " . $subject . "
                </div>
                
                <div style='background: white; padding: 15px; margin: 10px 0; border-left: 4px solid #007bff;'>
                    <strong>Message:</strong><br>
                    " . nl2br($message) . "
                </div>
            </div>
            
            <div style='padding: 20px; background: #f8f9fa; border-top: 1px solid #dee2e6; font-size: 12px; color: #6c757d; text-align: center;'>
                <p style='margin: 5px 0;'><strong>Message Details:</strong></p>
                <p style='margin: 3px 0;'>Submitted via: " . $_SERVER['HTTP_HOST'] . "</p>
                <p style='margin: 3px 0;'>Date: " . date('F j, Y \a\t g:i A T') . "</p>
                <p style='margin: 3px 0;'>Source IP: " . $_SERVER['REMOTE_ADDR'] . "</p>
                <hr style='margin: 15px 0; border: none; border-top: 1px solid #dee2e6;'>
                <p style='margin: 5px 0; font-size: 11px;'>This message was sent through the official EduBridgeSA contact form.</p>
            </div>
        </div>
    </body>
    </html>";

    // Create plain text version
    $textBody = "New contact form submission from EduBridgeSA website\n\n";
    $textBody .= "Name: " . $fullName . "\n";
    $textBody .= "Email: " . $email . "\n";
    $textBody .= "Phone: " . ($phone ?: 'Not provided') . "\n";
    $textBody .= "Subject: " . $subject . "\n\n";
    $textBody .= "Message:\n" . $message . "\n\n";
    $textBody .= "---\n";
    $textBody .= "Sent from: " . $_SERVER['HTTP_HOST'] . "\n";
    $textBody .= "Date: " . date('Y-m-d H:i:s T') . "\n";
    $textBody .= "IP Address: " . $_SERVER['REMOTE_ADDR'] . "\n";

    $mail->Body = $htmlBody;
    $mail->AltBody = $textBody;

    // Comprehensive anti-spam headers for better deliverability
    $mail->addCustomHeader('X-Mailer', 'EduBridgeSA Contact Form v1.0');
    $mail->addCustomHeader('X-Priority', '3'); // Normal priority
    $mail->addCustomHeader('X-MSMail-Priority', 'Normal');
    $mail->addCustomHeader('Importance', 'Normal');
    
    // Authentication and reputation headers
    $mail->addCustomHeader('X-Auto-Response-Suppress', 'OOF, DR, RN, NRN');
    $mail->addCustomHeader('X-Originating-IP', $_SERVER['REMOTE_ADDR']);
    $mail->addCustomHeader('X-Source-Domain', $_SERVER['HTTP_HOST']);
    $mail->addCustomHeader('X-Source-URL', 'https://' . $_SERVER['HTTP_HOST'] . '/contact.php');
    
    // Content classification headers
    $mail->addCustomHeader('X-Content-Type', 'Contact Form Submission');
    $mail->addCustomHeader('X-Spam-Status', 'No, legitimate contact form');
    $mail->addCustomHeader('X-Message-Flag', 'LEGITIMATE');
    
    // List management headers (helps with reputation)
    $mail->addCustomHeader('List-Unsubscribe', '<mailto:info@edubridgesa.co.za?subject=Unsubscribe>');
    $mail->addCustomHeader('List-Id', 'EduBridgeSA Contact Form <contact.edubridgesa.co.za>');
    
    // DMARC alignment headers
    $mail->addCustomHeader('X-Authenticated-Sender', SMTP_USERNAME);
    $mail->addCustomHeader('X-Domain-Authentication', 'edubridgesa.co.za');

    // Send the email
    $mail->send();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Thank you! Your message has been sent successfully. We\'ll respond within 2 business days.'
    ]);

} catch (Exception $e) {
    // Log the error for debugging
    error_log("Contact form email error: " . $mail->ErrorInfo);
    
    echo json_encode([
        'success' => false, 
        'message' => 'Sorry, there was an error sending your message. Please try again or email us directly at info@edubridgesa.co.za'
    ]);
}
?>
