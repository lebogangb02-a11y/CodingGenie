<?php
require_once __DIR__ . '/config_application.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - EduBridgeSA</title>
    <style>
        body { font-family: system-ui, sans-serif; line-height: 1.6; color: #000; margin: 0; background: #f7f7f7; }
        .container { max-width: 800px; margin: 0 auto; padding: 24px; background: #fff; }
        h1 { margin-top: 0; }
        a { color: #1a5fb4; }
        .section { margin-bottom: 16px; }
        .footer { margin-top: 24px; font-size: 13px; color: #555; }
    </style>
    </head>
<body>
    <div class="container">
        <h1>Privacy Policy</h1>
        <div class="section">
            <p>EduBridgeSA respects your privacy. We only use your information to provide and improve the student application services offered on this website.</p>
        </div>
        <div class="section">
            <h2>Information We Collect</h2>
            <p>Account registration details (name, email, phone), application data, and documents you upload to support your application.</p>
        </div>
        <div class="section">
            <h2>How We Use Your Information</h2>
            <p>To process applications, provide account access, send transactional emails (such as verification and status updates), and to maintain the security of our services.</p>
        </div>
        <div class="section">
            <h2>Contact</h2>
            <p>If you have questions about this policy, contact us at <a href="mailto:support@edubridgesa.co.za">support@edubridgesa.co.za</a>.</p>
        </div>
        <div class="section">
            <h2>Your Choices</h2>
            <p>You can manage your email preferences or unsubscribe from non-essential emails using our <a href="/unsubscribe.php">Unsubscribe</a> page.</p>
        </div>
        <div class="footer">
            <p>Last updated: <?php echo date('Y-m-d'); ?> • EduBridgeSA • <?php echo htmlspecialchars(parse_url(BASE_URL, PHP_URL_HOST)); ?></p>
        </div>
    </div>
    </body>
    </html>