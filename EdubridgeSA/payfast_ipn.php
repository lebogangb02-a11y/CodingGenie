<?php
header('X-Robots-Tag: noindex, nofollow', true);
// payfast_ipn.php - Handle PayFast IPN callbacks (sandbox-friendly)

// Optional DB connection to update application payment status
$pdo = null;
try {
    if (file_exists(__DIR__ . '/config.php')) { require_once __DIR__ . '/config.php'; }
    if (defined('DB_HOST')) {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
} catch (Throwable $e) { error_log('payfast_ipn db init error: ' . $e->getMessage()); }

// Centralized email sender
require_once __DIR__ . '/config/email.php';

function ensurePaymentColumns($pdo) {
    if (!$pdo) return;
    try {
        $pdo->exec("ALTER TABLE applications ADD COLUMN IF NOT EXISTS payment_status VARCHAR(20) DEFAULT NULL");
        $pdo->exec("ALTER TABLE applications ADD COLUMN IF NOT EXISTS payment_reference VARCHAR(64) DEFAULT NULL");
    } catch (Throwable $e) {
        try {
            $cols = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='" . DB_NAME . "' AND TABLE_NAME='applications'")->fetchAll(PDO::FETCH_COLUMN);
            if ($cols && !in_array('payment_status', $cols)) { $pdo->exec("ALTER TABLE applications ADD COLUMN payment_status VARCHAR(20) DEFAULT NULL"); }
            if ($cols && !in_array('payment_reference', $cols)) { $pdo->exec("ALTER TABLE applications ADD COLUMN payment_reference VARCHAR(64) DEFAULT NULL"); }
        } catch (Throwable $ignored) {}
    }
}

function validatePayFastIPN($pfHost = 'sandbox.payfast.co.za') {
    $pfParamString = '';
    foreach ($_POST as $key => $val) {
        if ($key !== 'signature') { $pfParamString .= $key . '=' . urlencode($val) . '&'; }
    }
    $pfParamString = substr($pfParamString, 0, -1);

    $url = 'https://' . $pfHost . '/eng/query/validate';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $pfParamString);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response === 'VALID';
}

if (validatePayFastIPN()) {
    // Map numeric applicant type back to string
    function getApplicantTypeFromCode($code) {
        $typeMapping = [
            1 => 'grade12',
            2 => 'non_grade12',
            3 => 'international',
            4 => 'private_school'
        ];
        return $typeMapping[(int)$code] ?? 'unknown';
    }

    $applicationId = $_POST['custom_str2'] ?? $_POST['custom_str1'] ?? $_POST['m_payment_id'] ?? null;
    $applicantTypeCode = $_POST['custom_int1'] ?? 0;
    $applicantType = getApplicantTypeFromCode($applicantTypeCode);
    $paymentStatusRaw = $_POST['payment_status'] ?? '';
    $pfPaymentId = $_POST['pf_payment_id'] ?? '';
    $amount = $_POST['amount_gross'] ?? $_POST['amount'] ?? '';
    $email = $_POST['email_address'] ?? '';

    $status = 'pending';
    $ps = strtoupper(trim($paymentStatusRaw));
    if ($ps === 'COMPLETE') $status = 'paid';
    elseif ($ps === 'FAILED') $status = 'failed';

    error_log("PayFast IPN VALID: application=" . $applicationId . ", status=" . $ps . ", pf_payment_id=" . $pfPaymentId . ", applicant_type=" . $applicantType);

    try {
        if ($pdo && $applicationId) {
            ensurePaymentColumns($pdo);
            $stmt = $pdo->prepare("UPDATE applications SET payment_status=?, payment_reference=?, updated_at=NOW() WHERE id = ? OR reference_number = ?");
            $stmt->execute([$status, $pfPaymentId, $applicationId, $applicationId]);
        }
    } catch (Throwable $e) { error_log('payfast_ipn update error: ' . $e->getMessage()); }
    // Send confirmation email on successful payment (SMTP with fallback)
    if ($ps === 'COMPLETE' && $email) {
        $applicantName = trim(($_POST['name_first'] ?? '') . ' ' . ($_POST['name_last'] ?? ''));
        sendPaymentConfirmation($email, $pfPaymentId, $amount, $applicantName);
    }
    http_response_code(200);
    echo 'OK';
} else {
    error_log('PayFast IPN INVALID: ' . json_encode($_POST));
    http_response_code(400);
    echo 'INVALID';
}