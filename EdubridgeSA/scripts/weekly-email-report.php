<?php
// scripts/weekly-email-report.php
require_once __DIR__ . '/../config/email.php';

function generateWeeklyReport() {
    $db = getPDO();
    if (!$db) {
        return '<html><body><h2>Weekly Email Analytics Report</h2><p>Database not available.</p></body></html>';
    }
    $weekStart = date('Y-m-d', strtotime('-7 days'));

    $stmt = $db->query(
        "SELECT 
            type, 
            COUNT(*) as total, 
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as successful, 
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed, 
            SUM(CASE WHEN status = 'sent_fallback' THEN 1 ELSE 0 END) as fallback, 
            ROUND(AVG(retry_count), 2) as avg_retries 
        FROM email_logs 
        WHERE created_at >= '{$weekStart}' 
        GROUP BY type 
        ORDER BY total DESC"
    );
    $stats = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

    $htmlReport = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='utf-8' />
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .stats-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            .stats-table th, .stats-table td { border: 1px solid #ddd; padding: 12px; text-align: left; }
            .stats-table th { background-color: #f8f9fa; }
            .success { color: #28a745; }
            .warning { color: #ffc107; }
            .danger { color: #dc3545; }
        </style>
    </head>
    <body>
        <h2>📧 Weekly Email Analytics Report</h2>
        <p>Period: {$weekStart} to " . date('Y-m-d') . "</p>
        <table class='stats-table'>
            <tr>
                <th>Email Type</th>
                <th>Total Sent</th>
                <th>Success Rate</th>
                <th>Failed</th>
                <th>Fallback</th>
                <th>Avg Retries</th>
            </tr>
    ";

    foreach ($stats as $stat) {
        $total = (int)($stat['total'] ?? 0);
        $successful = (int)($stat['successful'] ?? 0);
        $failed = (int)($stat['failed'] ?? 0);
        $fallback = (int)($stat['fallback'] ?? 0);
        $avgRetries = isset($stat['avg_retries']) ? (float)$stat['avg_retries'] : 0.0;
        $successRate = $total > 0 ? round(($successful / $total) * 100) : 0;
        $rateClass = $successRate >= 95 ? 'success' : ($successRate >= 80 ? 'warning' : 'danger');

        $htmlReport .= "
            <tr>
                <td{" . (empty($stat['type']) ? '' : '' ) . "}>{$stat['type']}</td>
                <td>{$total}</td>
                <td class='{$rateClass}'><strong>{$successRate}%</strong></td>
                <td>{$failed}</td>
                <td>{$fallback}</td>
                <td>{$avgRetries}</td>
            </tr>
        ";
    }

    $htmlReport .= "
        </table>
        <h3>📈 Recommendations</h3>
        <ul>
            <li>" . generateRecommendations($stats) . "</li>
        </ul>
    </body>
    </html>
    ";

    return $htmlReport;
}

function generateRecommendations($stats) {
    $recommendations = [];
    foreach ($stats as $stat) {
        $total = (int)($stat['total'] ?? 0);
        $successful = (int)($stat['successful'] ?? 0);
        $successRate = $total > 0 ? round(($successful / $total) * 100) : 0;
        $avgRetries = isset($stat['avg_retries']) ? (float)$stat['avg_retries'] : 0.0;

        if ($successRate < 80) {
            $recommendations[] = "Investigate {$stat['type']} emails: {$successRate}% success rate";
        }
        if ($avgRetries > 1) {
            $recommendations[] = "High retry rate for {$stat['type']}: {$avgRetries} average retries";
        }
    }
    return empty($recommendations) ? "All email systems operating normally" : implode('</li><li>', $recommendations);
}

// Send weekly report
$report = generateWeeklyReport();
$adminEmail = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : (getenv('ADMIN_EMAIL') ?: 'admin@edubridgesa.co.za');

$periodStart = date('Y-m-d', strtotime('-7 days'));
$periodEnd = date('Y-m-d');
$payload = [
    'template' => 'weekly_report',
    'periodStart' => $periodStart,
    'periodEnd' => $periodEnd,
];

$mail = getMailer();
if ($mail) {
    try {
        $mail->addAddress($adminEmail);
        $mail->isHTML(true);
        $mail->Subject = 'Weekly Email Analytics Report - ' . date('Y-m-d');
        $mail->Body = $report;
        if ($mail->send()) {
            logEmailAttempt('weekly_report', $adminEmail, 'sent', null, $payload);
            echo "Weekly report sent successfully\n";
        } else {
            logEmailAttempt('weekly_report', $adminEmail, 'failed', $mail->ErrorInfo, $payload);
            // Fallback to basic email
            @mail($adminEmail, 'Weekly Email Report', strip_tags($report));
            logEmailAttempt('weekly_report', $adminEmail, 'sent_fallback', null, $payload);
            echo "Weekly report sent via fallback\n";
        }
    } catch (Throwable $e) {
        error_log("Weekly report failed: " . $e->getMessage());
        logEmailAttempt('weekly_report', $adminEmail, 'failed', $e->getMessage(), $payload);
        // Fallback to basic email
        @mail($adminEmail, 'Weekly Email Report', strip_tags($report));
        logEmailAttempt('weekly_report', $adminEmail, 'sent_fallback', null, $payload);
        echo "Weekly report sent via fallback\n";
    }
} else {
    // No SMTP configured; fallback
    $ok = @mail($adminEmail, 'Weekly Email Report', strip_tags($report));
    logEmailAttempt('weekly_report', $adminEmail, $ok ? 'sent_fallback' : 'failed', $ok ? null : 'mail() returned false', $payload);
    echo $ok ? "Weekly report sent via fallback\n" : "Weekly report failed to send via fallback\n";
}

?>