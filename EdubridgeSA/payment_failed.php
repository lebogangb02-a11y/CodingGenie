<?php
// payment_failed.php
$errorReason = $_GET['reason'] ?? 'Payment processing failed';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <title>Payment Failed - EduBridge</title>
    <style>
        body{font-family:system-ui,Arial,sans-serif;margin:0;background:#fff}
        .wrap{display:flex;align-items:center;justify-content:center;min-height:100vh;padding:2rem}
        .card{max-width:620px;border:1px solid #e5e7eb;border-radius:12px;padding:2rem;text-align:center;box-shadow:0 8px 18px rgba(0,0,0,.06)}
        h2{margin-top:0}
        .retry-options{margin:20px 0}
        .retry-options a{display:inline-block;margin:5px;padding:10px 15px;background:#e74c3c;color:#fff;text-decoration:none;border-radius:6px}
        .retry-options a:hover{background:#c0392b}
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h2>❌ Payment Failed</h2>
            <p>Reason: <?php echo htmlspecialchars($errorReason); ?></p>
            <div class="retry-options">
                <a href="payment.php?retry=true">Retry Payment</a>
                <a href="student-dashboard.php">Return to Dashboard</a>
                <a href="support.php">Contact Support</a>
            </div>
        </div>
    </div>
</body>
</html>