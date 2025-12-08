<?php
// config/email.php - Centralized email configuration and helpers

// Default SMTP config (override via environment or config_local.php)
defined('SMTP_HOST') or define('SMTP_HOST', getenv('SMTP_HOST') ?: 'mail.edubridgesa.co.za');
defined('SMTP_PORT') or define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: 587));
defined('SMTP_USER') or define('SMTP_USER', getenv('SMTP_USER') ?: 'noreply@edubridgesa.co.za');
defined('SMTP_PASS') or define('SMTP_PASS', getenv('SMTP_PASS') ?: '');
defined('SMTP_SECURE') or define('SMTP_SECURE', getenv('SMTP_SECURE') ?: 'tls');

// Backward-compatibility with older config constants
if (defined('SMTP_USERNAME') && !defined('SMTP_USER')) {
    define('SMTP_USER', SMTP_USERNAME);
}
if (defined('SMTP_PASSWORD') && !defined('SMTP_PASS')) {
    define('SMTP_PASS', SMTP_PASSWORD);
}

// Optional: allow local overrides
if (file_exists(__DIR__ . '/../config_local.php')) {
    require_once __DIR__ . '/../config_local.php';
}
if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
}

// PHPMailer requires (relative to project root)
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../PHPMailer/src/Exception.php';

function getMailer()
{
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        $mail->setFrom(SMTP_USER, 'EduBridge SA');
        return $mail;
    } catch (Throwable $e) {
        error_log('PHPMailer initialization failed: ' . $e->getMessage());
        return null;
    }
}

function getPDO()
{
    try {
        if (defined('DB_HOST')) {
            $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo;
        }
    } catch (Throwable $e) {
        error_log('getPDO error: ' . $e->getMessage());
    }
    return null;
}

function ensureEmailLogsTable($pdo)
{
    if (!$pdo) return;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS email_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(50) NOT NULL,
            recipient VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL,
            retry_count INT DEFAULT 0,
            last_retry_at TIMESTAMP NULL,
            error_message TEXT,
            payload_json TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        // Ensure new columns exist for existing deployments
        $cols = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='" . DB_NAME . "' AND TABLE_NAME='email_logs'")->fetchAll(PDO::FETCH_COLUMN);
        $existing = array_map('strtolower', $cols ?: []);
        if (!in_array('payload_json', $existing)) {
            $pdo->exec("ALTER TABLE email_logs ADD COLUMN payload_json TEXT NULL AFTER error_message");
        }
        if (!in_array('retry_count', $existing)) {
            $pdo->exec("ALTER TABLE email_logs ADD COLUMN retry_count INT DEFAULT 0 AFTER status");
        }
        if (!in_array('last_retry_at', $existing)) {
            $pdo->exec("ALTER TABLE email_logs ADD COLUMN last_retry_at TIMESTAMP NULL AFTER retry_count");
        }
    } catch (Throwable $e) {
        error_log('ensureEmailLogsTable error: ' . $e->getMessage());
    }
}

function logEmailAttempt($type, $recipient, $status, $errorMessage = null, $payload = null)
{
    $pdo = getPDO();
    if (!$pdo) return;
    ensureEmailLogsTable($pdo);
    try {
        $payloadJson = $payload ? json_encode($payload) : null;
        $stmt = $pdo->prepare('INSERT INTO email_logs (type, recipient, status, error_message, payload_json, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
        $stmt->execute([$type, $recipient, $status, $errorMessage, $payloadJson]);
    } catch (Throwable $e) {
        error_log('logEmailAttempt error: ' . $e->getMessage());
    }
}

function generatePaymentEmailTemplate($applicantName, $amount, $paymentRef)
{
    $safeName = htmlspecialchars($applicantName ?: 'Applicant');
    $safeAmount = htmlspecialchars($amount);
    $safeRef = htmlspecialchars($paymentRef);
    $dashboardUrl = 'https://edubridgesa.co.za/student-dashboard.php';
    return "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
        <h2 style='color: #2c3e50;'>Payment Confirmed 🎉</h2>
        <div style='background: #f8f9fa; padding: 20px; border-radius: 10px;'>
            <p><strong>Applicant:</strong> {$safeName}</p>
            <p><strong>Amount Paid:</strong> R{$safeAmount}</p>
            <p><strong>Reference:</strong> {$safeRef}</p>
            <p><strong>Status:</strong> Payment Verified ✅</p>
        </div>
        <div style='margin-top: 20px;'>
            <h3>Next Steps:</h3>
            <ol>
                <li>Application Review (2-3 weeks)</li>
                <li>Document Verification</li>
                <li>Final Decision</li>
            </ol>
        </div>
        <p>Track your application: <a href='{$dashboardUrl}'>View Dashboard</a></p>
    </div>";
}

function sendPaymentConfirmationBasic($email, $paymentRef, $amount)
{
    $subject = 'Payment Confirmed - EduBridge Application';
    $body = generatePaymentEmailTemplate('Applicant', $amount, $paymentRef);
    $headers = "Content-type: text/html\r\n";
    $ok = @mail($email, $subject, $body, $headers);
    logEmailAttempt('payment_confirmation', $email, $ok ? 'sent_fallback' : 'failed', $ok ? null : 'mail() returned false', [
        'email' => $email,
        'paymentRef' => $paymentRef,
        'amount' => $amount,
        'applicantName' => 'Applicant',
        'template' => 'payment_confirmation'
    ]);
    return $ok;
}

function sendPaymentConfirmation($email, $paymentRef, $amount, $applicantName)
{
    $mail = getMailer();
    if ($mail) {
        try {
            $mail->addAddress($email, $applicantName ?: $email);
            $mail->isHTML(true);
            $mail->Subject = 'Payment Confirmed - EduBridge Application';
            $mail->Body = generatePaymentEmailTemplate($applicantName, $amount, $paymentRef);
            if ($mail->send()) {
                logEmailAttempt('payment_confirmation', $email, 'sent', null, [
                    'email' => $email,
                    'paymentRef' => $paymentRef,
                    'amount' => $amount,
                    'applicantName' => $applicantName,
                    'template' => 'payment_confirmation'
                ]);
                return true;
            }
            logEmailAttempt('payment_confirmation', $email, 'failed', $mail->ErrorInfo, [
                'email' => $email,
                'paymentRef' => $paymentRef,
                'amount' => $amount,
                'applicantName' => $applicantName,
                'template' => 'payment_confirmation'
            ]);
        } catch (Throwable $e) {
            error_log('SMTP send error: ' . $e->getMessage());
            logEmailAttempt('payment_confirmation', $email, 'failed', $e->getMessage(), [
                'email' => $email,
                'paymentRef' => $paymentRef,
                'amount' => $amount,
                'applicantName' => $applicantName,
                'template' => 'payment_confirmation'
            ]);
        }
    }
    return sendPaymentConfirmationBasic($email, $paymentRef, $amount);
}

// Status update template and sender
function generateStatusEmailTemplate($applicantName, $newStatus, $applicationRef)
{
    $safeName = htmlspecialchars($applicantName ?: 'Applicant');
    $safeStatus = htmlspecialchars($newStatus ?: 'Updated');
    $safeRef = htmlspecialchars($applicationRef ?: 'N/A');
    $dashboardUrl = (defined('BASE_URL') ? BASE_URL : 'https://edubridgesa.co.za') . '/student-dashboard.php';
    return "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
        <h2 style='color:#2c3e50;'>Application Status Update</h2>
        <p>Hello {$safeName},</p>
        <p>Your application (Ref: {$safeRef}) status has changed to: <strong>{$safeStatus}</strong>.</p>
        <p>You can track progress and next steps on your dashboard.</p>
        <p><a href='{$dashboardUrl}'>View Dashboard</a></p>
    </div>";
}

function sendStatusUpdateEmail($email, $applicantName, $newStatus, $applicationRef)
{
    $type = 'status_update';
    $mail = getMailer();
    if ($mail) {
        try {
            $mail->addAddress($email, $applicantName ?: $email);
            $mail->isHTML(true);
            $mail->Subject = "Application Status Update - {$newStatus}";
            $mail->Body = generateStatusEmailTemplate($applicantName, $newStatus, $applicationRef);
            if ($mail->send()) {
                logEmailAttempt($type, $email, 'sent', null, [
                    'email' => $email,
                    'applicantName' => $applicantName,
                    'newStatus' => $newStatus,
                    'applicationRef' => $applicationRef,
                    'template' => 'status_update'
                ]);
                return true;
            }
            logEmailAttempt($type, $email, 'failed', $mail->ErrorInfo, [
                'email' => $email,
                'applicantName' => $applicantName,
                'newStatus' => $newStatus,
                'applicationRef' => $applicationRef,
                'template' => 'status_update'
            ]);
        } catch (Throwable $e) {
            error_log('Status email failed: ' . $e->getMessage());
            logEmailAttempt($type, $email, 'failed', $e->getMessage(), [
                'email' => $email,
                'applicantName' => $applicantName,
                'newStatus' => $newStatus,
                'applicationRef' => $applicationRef,
                'template' => 'status_update'
            ]);
        }
    }
    $fallbackOk = @mail($email, 'Application Status Update', "Your application status changed to: {$newStatus}");
    logEmailAttempt($type, $email, $fallbackOk ? 'sent_fallback' : 'failed', $fallbackOk ? null : 'mail() returned false', [
        'email' => $email,
        'applicantName' => $applicantName,
        'newStatus' => $newStatus,
        'applicationRef' => $applicationRef,
        'template' => 'status_update'
    ]);
    return $fallbackOk;
}

// Verification template and sender
function generateVerificationEmailTemplate($applicantName, $verificationCode)
{
    $safeName = htmlspecialchars($applicantName ?: 'Applicant');
    $safeCode = htmlspecialchars($verificationCode ?: '');
    $base = defined('BASE_URL') ? BASE_URL : 'https://edubridgesa.co.za';
    $verifyUrl = $base . '/verify-email.php?code=' . urlencode($safeCode);
    return "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
        <h2 style='color:#2c3e50;'>Verify Your Email</h2>
        <p>Hello {$safeName},</p>
        <p>Please verify your email address to continue your application.</p>
        <p><a href='{$verifyUrl}'>Click here to verify</a></p>
        <p>If the link doesn't work, use this code: <strong>{$safeCode}</strong></p>
    </div>";
}

function sendVerificationEmail($email, $applicantName, $verificationCode)
{
    $type = 'verification';
    $mail = getMailer();
    if ($mail) {
        try {
            $mail->addAddress($email, $applicantName ?: $email);
            $mail->isHTML(true);
            $mail->Subject = 'Verify Your Email - EduBridge SA';
            $mail->Body = generateVerificationEmailTemplate($applicantName, $verificationCode);
            if ($mail->send()) {
                logEmailAttempt($type, $email, 'sent', null, [
                    'email' => $email,
                    'applicantName' => $applicantName,
                    'verificationCode' => $verificationCode,
                    'template' => 'verification'
                ]);
                return true;
            }
            logEmailAttempt($type, $email, 'failed', $mail->ErrorInfo, [
                'email' => $email,
                'applicantName' => $applicantName,
                'verificationCode' => $verificationCode,
                'template' => 'verification'
            ]);
        } catch (Throwable $e) {
            error_log('Verification email failed: ' . $e->getMessage());
            logEmailAttempt($type, $email, 'failed', $e->getMessage(), [
                'email' => $email,
                'applicantName' => $applicantName,
                'verificationCode' => $verificationCode,
                'template' => 'verification'
            ]);
        }
    }
    $fallbackOk = @mail($email, 'Verify Your Email - EduBridge SA', "Your verification code is: {$verificationCode}");
    logEmailAttempt($type, $email, $fallbackOk ? 'sent_fallback' : 'failed', $fallbackOk ? null : 'mail() returned false', [
        'email' => $email,
        'applicantName' => $applicantName,
        'verificationCode' => $verificationCode,
        'template' => 'verification'
    ]);
    return $fallbackOk;
}

// True retry using stored payload
function retryFailedEmail($logId)
{
    $db = getPDO();
    if (!$db) return false;
    ensureEmailLogsTable($db);
    $stmt = $db->prepare("SELECT id, recipient, subject, status, payload_json, retry_count, created_at FROM email_logs WHERE id = ? AND status = 'failed'");
    $stmt->execute([$logId]);
    $failed = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$failed) return false;
    $payload = json_decode($failed['payload_json'] ?? '', true);
    if (!is_array($payload)) return false;
    $retryCount = (int)($failed['retry_count'] ?? 0) + 1;
    $success = false;
    switch ($failed['type']) {
        case 'payment_confirmation':
            $success = sendPaymentConfirmation(
                $payload['email'] ?? '',
                $payload['paymentRef'] ?? '',
                $payload['amount'] ?? '',
                $payload['applicantName'] ?? ''
            );
            break;
        case 'status_update':
            $success = sendStatusUpdateEmail(
                $payload['email'] ?? '',
                $payload['applicantName'] ?? '',
                $payload['newStatus'] ?? '',
                $payload['applicationRef'] ?? ''
            );
            break;
        case 'verification':
            $success = sendVerificationEmail(
                $payload['email'] ?? '',
                $payload['applicantName'] ?? '',
                $payload['verificationCode'] ?? ''
            );
            break;
        default:
            return false;
    }
    if ($success) {
        $upd = $db->prepare("UPDATE email_logs SET retry_count = ?, last_retry_at = NOW() WHERE id = ?");
        $upd->execute([$retryCount, $logId]);
    }
    return $success;
}

// Slack/Webhook alert notifications
function sendAlertNotification($message, $level = 'warning')
{
    $slackWebhook = getenv('SLACK_WEBHOOK_URL');
    if ($slackWebhook) {
        $data = [
            'text' => "\xF0\x9F\x9A\xA8 EduBridge Alert: $message",
            'username' => 'EduBridge Monitor',
            'icon_emoji' => $level === 'critical' ? '🔴' : '⚠️'
        ];
        if (function_exists('curl_init')) {
            $ch = curl_init($slackWebhook);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch);
            curl_close($ch);
        } else {
            // Fallback without curl
            $opts = [
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'content' => json_encode($data),
                    'ignore_errors' => true,
                ]
            ];
            @file_get_contents($slackWebhook, false, stream_context_create($opts));
        }
    }
    error_log("ALERT [$level]: $message");
}

// Enhanced health check with alerts
function checkEmailHealth()
{
    $db = getPDO();
    if (!$db) {
        sendAlertNotification('Database not available for email health check', 'critical');
        return 0;
    }
    ensureEmailLogsTable($db);
    $today = date('Y-m-d');
    try {
        $stmt = $db->query("SELECT COUNT(*) as cnt FROM email_logs WHERE DATE(created_at) = '{$today}' AND status = 'failed'");
        $failures = (int)($stmt ? $stmt->fetchColumn() : 0);
        if ($failures > 10) {
            sendAlertNotification("High email failure rate: {$failures} failed emails today", 'critical');
        }
        return $failures;
    } catch (Throwable $e) {
        error_log('checkEmailHealth error: ' . $e->getMessage());
        return 0;
    }
}
