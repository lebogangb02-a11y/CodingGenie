<?php
// Simple JSON API to provide application status for the dashboard
header('Content-Type: application/json');
session_start();

$status = 'payment_pending';
$statusText = 'Payment Pending';
$progress = 25;
$success = true;

// Attempt DB lookup for the latest application by session email or reference
try {
    if (file_exists(__DIR__ . '/../config.php')) { require_once __DIR__ . '/../config.php'; }
    $pdo = null;
    if (defined('DB_HOST')) {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    $email = $_SESSION['student_email'] ?? $_SESSION['email'] ?? null;
    $ref = $_SESSION['reference_number'] ?? null;
    $row = null;
    if ($pdo && $email) {
        $stmt = $pdo->prepare("SELECT application_status, payment_status FROM applications WHERE email_address = ? ORDER BY updated_at DESC, id DESC LIMIT 1");
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if (!$row && $pdo && $ref) {
        $stmt = $pdo->prepare("SELECT application_status, payment_status FROM applications WHERE reference_number = ? ORDER BY updated_at DESC, id DESC LIMIT 1");
        $stmt->execute([$ref]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Resolve status based on payment/app flow
    $pf = strtolower($row['payment_status'] ?? '');
    $as = strtolower($row['application_status'] ?? ($_SESSION['application_status'] ?? ''));
    if (in_array($pf, ['failed'])) { $status = 'failed'; }
    elseif (in_array($pf, ['paid','complete','completed'])) { $status = 'under_review'; }
    elseif ($as) { $status = $as; }

    // Map to friendly text
    $mapText = [
        'payment_pending' => 'Payment Pending',
        'payment_verified' => 'Payment Verified',
        'under_review' => 'Application Under Review',
        'documents_approved' => 'Documents Approved',
        'accepted' => 'Accepted',
        'failed' => 'Payment Failed - Retry Required',
        'cancelled' => 'Application Cancelled'
    ];
    $statusText = $mapText[$status] ?? ucfirst(str_replace('_',' ', $status));

    // Progress mapping
    $mapProgress = [
        'payment_pending' => 25,
        'payment_verified' => 40,
        'under_review' => 60,
        'documents_approved' => 80,
        'accepted' => 100,
        'failed' => 10,
        'cancelled' => 0
    ];
    $progress = $mapProgress[$status] ?? 0;
} catch (Throwable $e) {
    $success = false;
}

echo json_encode([
    'success' => $success,
    'status' => $status,
    'status_text' => $statusText,
    'progress' => $progress
]);
?>