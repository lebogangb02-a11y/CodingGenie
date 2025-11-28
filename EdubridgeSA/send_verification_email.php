<?php
require_once 'config.php';

// Load PHPMailer classes globally
if (file_exists('PHPMailer/src/PHPMailer.php')) {
    require_once 'PHPMailer/src/Exception.php';
    require_once 'PHPMailer/src/PHPMailer.php';
    require_once 'PHPMailer/src/SMTP.php';
}

function sendVerificationEmail($email, $fullName, $verificationToken) {
    
    // Construct the verification URL using BASE_URL to avoid explicit ports like :443
    $verificationUrl = rtrim(BASE_URL, '/') . '/verify-email.php?token=' . urlencode($verificationToken);
    
    // Email subject
    $subject = 'Verify Your Email Address - EduBridge SA';
    
    // Email body (HTML)
    $htmlBody = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Email Verification</title>
        <style>
            body { font-family: system-ui, sans-serif; line-height: 1.5; color: #000; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: #fff; }
            .button { background: #1a5fb4; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 4px; }
            .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class="container">
            <h2>Verify your EduBridgeSA account</h2>
            <p>Hello ' . htmlspecialchars($fullName) . ',</p>
            <p>Please verify your email address to activate your EduBridgeSA account.</p>
            <p><a href="' . htmlspecialchars($verificationUrl) . '" class="button">Verify Email Address</a></p>
            <p>If the button does not work, copy this link into your web browser:</p>
            <p style="word-break: break-all; background: #f5f5f5; padding: 10px; border-radius: 5px;">' . htmlspecialchars($verificationUrl) . '</p>
            <p>This verification link expires in 24 hours.</p>
            <div class="footer">
                <p>EduBridgeSA<br>
                Email: support@edubridgesa.co.za</p>
                <p><a href="https://edubridgesa.co.za/privacy">Privacy Policy</a> |
                   <a href="https://edubridgesa.co.za/unsubscribe.php">Unsubscribe</a></p>
            </div>
        </div>
    </body>
    </html>';
    
    // Plain text version for email clients that don't support HTML
    $textBody = "Hello " . $fullName . ",\n\n";
    $textBody .= "Thank you for registering with EduBridge SA. To complete your registration and activate your account, please verify your email address by visiting the following link:\n\n";
    $textBody .= $verificationUrl . "\n\n";
    $textBody .= "Important: This verification link will expire in 24 hours for security reasons.\n\n";
    $textBody .= "If you didn't create an account with EduBridge SA, please ignore this email.\n\n";
    $textBody .= "Best regards,\nThe EduBridge SA Team";
    
    // Try to use PHPMailer if available
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        return sendEmailWithPHPMailer($email, $fullName, $subject, $htmlBody, $textBody);
    } else {
        // Fallback to PHP's built-in mail function
        return sendEmailWithBuiltInMail($email, $fullName, $subject, $htmlBody, $textBody);
    }
}

function sendEmailWithPHPMailer($email, $fullName, $subject, $htmlBody, $textBody) {
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST ?? 'localhost';
        $mail->SMTPAuth = SMTP_AUTH ?? false;
        $mail->Username = SMTP_USERNAME ?? '';
        $mail->Password = SMTP_PASSWORD ?? '';
        $mail->SMTPSecure = SMTP_SECURE ?? '';
        $mail->Port = SMTP_PORT ?? 587;
        
        // Recipients
        $mail->setFrom(FROM_EMAIL ?? 'noreply@edubridge.sa', FROM_NAME ?? 'EduBridge SA');
        $mail->addAddress($email, $fullName);
        $mail->addReplyTo(REPLY_TO_EMAIL ?? FROM_EMAIL ?? 'noreply@edubridge.sa', REPLY_TO_NAME ?? FROM_NAME ?? 'EduBridge SA');
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $textBody;
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $e->getMessage());
        // Fallback to built-in mail function
        return sendEmailWithBuiltInMail($email, $fullName, $subject, $htmlBody, $textBody);
    }
}

function sendEmailWithBuiltInMail($email, $fullName, $subject, $htmlBody, $textBody) {
    try {
        $fromEmail = FROM_EMAIL ?? 'noreply@edubridge.sa';
        $fromName = FROM_NAME ?? 'EduBridge SA';
        
        // Headers
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . $fromName . " <" . $fromEmail . ">\r\n";
        $headers .= "Reply-To: " . $fromEmail . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
        
        // Send email
        $result = mail($email, $subject, $htmlBody, $headers);
        
        if (!$result) {
            error_log("Built-in mail function failed to send email to: " . $email);
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Built-in mail Error: " . $e->getMessage());
        return false;
    }
}
?>