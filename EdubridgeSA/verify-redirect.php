<?php
// Secure redirect for verification clicks with lightweight logging
require_once __DIR__ . '/config_application.php';

$token = isset($_GET['t']) ? trim($_GET['t']) : '';

// Basic validation: 64-char hex from bin2hex(random_bytes(32))
if ($token === '' || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
    http_response_code(400);
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Invalid Token</title></head><body>";
    echo "<p>Invalid verification link. Please request a new email.</p>";
    echo "<p><a href='" . BASE_URL . "/resend-verification.php'>Resend Verification</a></p>";
    echo "</body></html>";
    exit;
}

// Log click to file (fallback); switch to DB later if needed
try {
    $logFile = __DIR__ . '/click_log.json';
    $log = [];
    if (file_exists($logFile)) {
        $log = json_decode(@file_get_contents($logFile), true) ?: [];
    }
    $log[] = [
        'type' => 'verification_click',
        'token' => $token,
        'ts' => date('c'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'ua' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ];
    @file_put_contents($logFile, json_encode($log, JSON_PRETTY_PRINT));
} catch (Throwable $e) {
    // Non-fatal: continue to redirect
}

// Redirect to actual verification endpoint
$dest = rtrim(BASE_URL, '/') . '/verify-email.php?token=' . urlencode($token);
header('Location: ' . $dest, true, 302);
exit;
?>