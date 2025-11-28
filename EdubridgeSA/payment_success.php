<?php
session_start();
// Try to load configuration/DB
$pdo = null;
try {
    if (file_exists(__DIR__ . '/config.php')) { require_once __DIR__ . '/config.php'; }
    if (defined('DB_HOST')) {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
} catch (Throwable $e) { error_log('payment_success db init error: ' . $e->getMessage()); }

$paymentRef = $_GET['ref'] ?? 'Unknown';
$isVerified = false;
$applicationStatus = 'pending';

// Verify payment
if ($paymentRef !== 'Unknown') {
    if (strpos($paymentRef, 'FREE-') === 0) {
        $isVerified = true;
        $applicationStatus = 'under_review';
    } else {
        try {
            if ($pdo) {
                $stmt = $pdo->prepare("SELECT payment_status FROM applications WHERE payment_reference = ? LIMIT 1");
                $stmt->execute([$paymentRef]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $ps = strtolower($row['payment_status'] ?? '');
                if (in_array($ps, ['paid','complete','completed'])) { $isVerified = true; $applicationStatus = 'under_review'; }
            }
        } catch (Throwable $e) { error_log('payment_success verification error: ' . $e->getMessage()); $isVerified = true; $applicationStatus = 'under_review'; }
    }
    $_SESSION['application_status'] = $applicationStatus;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <title>Payment Successful - EduBridge</title>
    <style>
        body{font-family:system-ui,Arial,sans-serif;margin:0;background:#f8f9fa;}
        .wrap{display:flex;align-items:center;justify-content:center;min-height:100vh;padding:2rem}
        .card{background:#fff;border:1px solid #ddd;border-radius:12px;padding:2rem;max-width:600px;text-align:center;box-shadow:0 4px 12px rgba(0,0,0,.05)}
        h2{margin:0 0 .5rem}
        .ref{color:#6c757d;margin:.5rem 0 1rem}
        .verification-badge{background:#27ae60;color:#fff;padding:10px 20px;border-radius:20px;display:inline-block;margin:10px 0}
        .status-timeline{margin:20px 0;padding:20px;background:#eef2f7;border-radius:10px}
        a.btn{display:inline-block;background:#0d6efd;color:#fff;text-decoration:none;padding:.6rem 1rem;border-radius:8px}
        a.btn:hover{background:#0b5ed7}
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <?php if ($isVerified): ?><div class="verification-badge">✅ Payment Verified</div><?php endif; ?>
            <h2>✅ Payment Successful!</h2>
            <p class="ref">Reference: <?php echo htmlspecialchars($paymentRef); ?></p>
            <div class="status-timeline">
                <h3>What's Next?</h3>
                <p>✅ Payment Received<br>🔄 Application Under Review<br>⏳ Decision: 2-3 weeks</p>
            </div>
            <p><a href="student-dashboard.php?payment_status=<?php echo htmlspecialchars($applicationStatus); ?>" class="btn">View Dashboard</a></p>
            <script>
                setTimeout(function(){ window.location.href = 'student-dashboard.php?payment_status=<?php echo htmlspecialchars($applicationStatus); ?>'; }, 5000);
            </script>
        </div>
    </div>
</body>
</html>