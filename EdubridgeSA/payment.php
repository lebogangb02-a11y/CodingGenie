<?php
// payment.php
session_start();
$applicantType = $_GET['type'] ?? 'standard';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <title>Application Payment - EduBridge</title>
    <style>
        body{font-family:system-ui,Arial,sans-serif;margin:2rem;}
        .card{max-width:700px;margin:auto;border:1px solid #ddd;border-radius:8px;padding:1.5rem;}
        h2{margin-top:0}
        button{background:#0d6efd;color:#fff;border:none;border-radius:6px;padding:.6rem 1rem;cursor:pointer}
        button:hover{background:#0b5ed7}
        .fee{font-weight:bold;margin:.5rem 0 1rem}
    </style>
    </head>
<body>
    <div class="card">
        <h2>Complete Your Application Payment</h2>
        <p>Application type: <?php echo htmlspecialchars($applicantType); ?></p>
        <form action="process_payment.php" method="post">
            <input type="hidden" name="applicant_type" value="<?php echo htmlspecialchars($applicantType); ?>">
            <h3 class="fee">Application Fee: R250</h3>
            <button type="submit">Proceed to Payment</button>
        </form>
        <div style="margin-top: 20px; padding: 10px; background: #f8f9fa;">
            <small><strong>Testing:</strong></small>
            <div style="margin-top:.5rem">
                <a href="process_payment.php?test_failure=true" style="color: #e74c3c; margin-right:1rem">Simulate Failed Payment</a>
                <a href="process_payment.php?test_success=true" style="color: #27ae60;">Simulate Successful Payment</a>
            </div>
        </div>
    </div>
</body>
</html>