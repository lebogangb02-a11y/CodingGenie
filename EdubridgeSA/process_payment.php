<?php
// process_payment.php - PayFast integration (sandbox-friendly)
session_start();
header('X-Robots-Tag: noindex, nofollow', true);

// Prefer central config and helpers. `config.php` may provide a ready PDO in $pdo.
$pdo = null;
if (file_exists(__DIR__ . '/config.php')) {
    /**
     * `config.php` is expected to define DB constants and optionally a prebuilt
     * `$pdo` instance. Fall back to null if not present.
     */
    require_once __DIR__ . '/config.php';
}
// Include local security helpers if present (provides `require_csrf()` etc.)
if (file_exists(__DIR__ . '/includes/security_helpers.php')) {
    require_once __DIR__ . '/includes/security_helpers.php';
}

function ensurePaymentColumns($pdo)
{
    if (!$pdo) return;
    try {
        $pdo->exec("ALTER TABLE applications ADD COLUMN IF NOT EXISTS payment_status VARCHAR(20) DEFAULT NULL");
        $pdo->exec("ALTER TABLE applications ADD COLUMN IF NOT EXISTS payment_reference VARCHAR(64) DEFAULT NULL");
    } catch (Throwable $e) {
        try {
            $cols = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='" . DB_NAME . "' AND TABLE_NAME='applications'")->fetchAll(PDO::FETCH_COLUMN);
            if ($cols && !in_array('payment_status', $cols)) {
                $pdo->exec("ALTER TABLE applications ADD COLUMN payment_status VARCHAR(20) DEFAULT NULL");
            }
            if ($cols && !in_array('payment_reference', $cols)) {
                $pdo->exec("ALTER TABLE applications ADD COLUMN payment_reference VARCHAR(64) DEFAULT NULL");
            }
        } catch (Throwable $ignored) {
        }
    }
}

function generatePayFastSignature($data, $passPhrase = null)
{
    $pfOutput = '';
    foreach ($data as $key => $val) {
        if ($val !== '') {
            $pfOutput .= $key . '=' . urlencode(trim($val)) . '&';
        }
    }
    $getString = substr($pfOutput, 0, -1);
    if ($passPhrase !== null) {
        $getString .= '&passphrase=' . urlencode(trim($passPhrase));
    }
    return md5($getString);
}

// Keep local test hooks for development
if (isset($_GET['test_failure'])) {
    try {
        $applicationId = $_SESSION['application_id'] ?? null;
        if ($pdo && is_numeric($applicationId)) {
            ensurePaymentColumns($pdo);
            $pdo->prepare("UPDATE applications SET payment_status='failed', updated_at=NOW() WHERE id=?")->execute([(int)$applicationId]);
        }
    } catch (Throwable $e) {
        error_log('process_payment test_failure update error: ' . $e->getMessage());
    }
    header('Location: payment_failed.php?reason=' . urlencode('Test failure'));
    exit;
}
if (isset($_GET['test_success'])) {
    $applicationId = $_SESSION['application_id'] ?? uniqid('APP_');
    $paymentRef = 'PAY' . uniqid();
    try {
        if ($pdo && is_numeric($applicationId)) {
            ensurePaymentColumns($pdo);
            $pdo->prepare("UPDATE applications SET payment_status='paid', payment_reference=?, updated_at=NOW() WHERE id=?")->execute([$paymentRef, (int)$applicationId]);
        }
    } catch (Throwable $e) {
        error_log('process_payment test_success update error: ' . $e->getMessage());
    }
    header('Location: payment_success.php?ref=' . urlencode($paymentRef));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Enforce server-side CSRF if helper is available
    if (function_exists('require_csrf')) {
        require_csrf();
    }

    $applicantType = $_POST['applicant_type'] ?? 'unknown';
    $applicationId = $_SESSION['application_id'] ?? null;
    if (!$applicationId) {
        // Resolve application id via session email (fallback)
        try {
            $contactEmail = $_SESSION['student_email'] ?? $_SESSION['email'] ?? null;
            if ($pdo && $contactEmail) {
                $stmt = $pdo->prepare("SELECT id FROM applications WHERE email_address = ? ORDER BY updated_at DESC, id DESC LIMIT 1");
                $stmt->execute([$contactEmail]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && !empty($row['id'])) {
                    $applicationId = (int)$row['id'];
                }
            }
        } catch (Throwable $e) {
            error_log('process_payment resolve app id error: ' . $e->getMessage());
        }
    }
    if (!$applicationId) {
        $applicationId = uniqid('APP_');
    }

    // Calculate fee
    $amount = 0;
    if ($applicantType === 'non_grade12') $amount = 250;
    elseif ($applicantType === 'international') $amount = 500;
    else $amount = 0; // Grade 12 free

    if ($amount > 0) {
        // Helper: convert applicant type string to numeric code for PayFast custom_int1
        function getApplicantTypeCode($applicantType)
        {
            $typeCodes = [
                'grade12' => 1,
                'non_grade12' => 2,
                'international' => 3,
                'private_school' => 4
            ];
            return $typeCodes[$applicantType] ?? 0;
        }
        // Prepare PayFast redirect (LIVE production)
        $returnUrl = 'https://edubridgesa.co.za/payment_success.php';
        $cancelUrl = 'https://edubridgesa.co.za/payment_failed.php';
        $notifyUrl = 'https://edubridgesa.co.za/payfast_ipn.php';

        // Read merchant credentials from environment. Do NOT fall back to live keys.
        $merchantId = getenv('PAYFAST_MERCHANT_ID') ?: '';
        $merchantKey = getenv('PAYFAST_MERCHANT_KEY') ?: '';
        $passPhrase = getenv('PAYFAST_PASSPHRASE') ?: null; // optional

        $data = array(
            'merchant_id'   => $merchantId,
            'merchant_key'  => $merchantKey,
            'return_url'    => $returnUrl,
            'cancel_url'    => $cancelUrl,
            'notify_url'    => $notifyUrl,
            'name_first'    => $_SESSION['first_name'] ?? '',
            'name_last'     => $_SESSION['last_name'] ?? '',
            'email_address' => $_SESSION['student_email'] ?? $_SESSION['email'] ?? '',
            'm_payment_id'  => $applicationId,
            'amount'        => number_format($amount, 2, '.', ''),
            'item_name'     => 'EduBridge Application Fee - ' . $applicantType,
            'item_description' => 'University Application Processing Fee',
            // FIX: PayFast expects custom_int1 numeric; store string in custom_str1
            'custom_int1'   => getApplicantTypeCode($applicantType),
            'custom_str1'   => $applicantType,
            'custom_str2'   => $applicationId
        );

        // Build signature including passphrase (not posted to PayFast)
        $signData = $data;
        if ($passPhrase !== null && $passPhrase !== '') {
            $signData['passphrase'] = $passPhrase;
        }
        $signature = generatePayFastSignature($signData);
        $data['signature'] = $signature;

        // LIVE PayFast host
        $pfHost = 'https://www.payfast.co.za/eng/process';
        $output = '';
        foreach ($data as $key => $val) {
            $output .= '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($val) . '">';
        }

        echo '<form action="' . $pfHost . '" method="post" id="payfastForm">';
        echo $output;
        echo '</form>';
        echo '<script>document.getElementById("payfastForm").submit();</script>';
        exit;
    } else {
        // Free application, mark as paid locally and redirect success
        try {
            if ($pdo && is_numeric($applicationId)) {
                ensurePaymentColumns($pdo);
                $pdo->prepare("UPDATE applications SET payment_status='paid', payment_reference=?, updated_at=NOW() WHERE id=?")->execute(['FREE-' . $applicationId, (int)$applicationId]);
            }
        } catch (Throwable $e) {
            error_log('process_payment free update error: ' . $e->getMessage());
        }
        header('Location: payment_success.php?ref=' . urlencode('FREE-' . $applicationId));
        exit;
    }
}

http_response_code(405);
echo '<h3>Method Not Allowed</h3><p>Please submit the payment form.</p>';
