<?php
require_once __DIR__ . '/config_application.php';
require_once __DIR__ . '/email_functions.php';

$run = isset($_GET['run']) && $_GET['run'] === '1';
$results = [];
if ($run) {
    $results = testEmailDeliverability();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Deliverability Test</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 20px; }
        .btn { display:inline-block; background:#1a5fb4; color:#fff; padding:10px 16px; text-decoration:none; border-radius:4px; }
        table { border-collapse: collapse; width: 100%; margin-top: 16px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f5f5f5; }
        .ok { color: #2e7d32; }
        .err { color: #c62828; }
        .note { font-size: 13px; color: #555; margin-top: 20px; }
    </style>
    </head>
<body>
    <h1>Email Deliverability Test</h1>
    <p>Send test verification emails to major providers and log results.</p>
    <p>
        <a class="btn" href="?run=1">Run Tests</a>
    </p>

    <?php if ($run): ?>
        <table>
            <tr>
                <th>Provider</th>
                <th>Email</th>
                <th>Status</th>
                <th>Message</th>
            </tr>
            <?php foreach ($results as $row): $success = is_array($row['result']) ? ($row['result']['success'] ?? false) : (bool)$row['result']; ?>
            <tr>
                <td><?php echo htmlspecialchars($row['provider']); ?></td>
                <td><?php echo htmlspecialchars($row['email']); ?></td>
                <td class="<?php echo $success ? 'ok' : 'err'; ?>">
                    <?php echo $success ? 'Sent' : 'Failed'; ?>
                </td>
                <td><?php echo htmlspecialchars(is_array($row['result']) ? ($row['result']['message'] ?? '') : ''); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <div class="note">
        <p>Tip: Also send a copy of your verification email to the unique address provided by <a href="https://www.mail-tester.com/" target="_blank" rel="noopener">mail-tester.com</a> and review SPF/DKIM/DMARC results.</p>
    </div>
</body>
</html>