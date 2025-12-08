<?php
// email_logs.php - Email Logs Viewer
session_start();

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simple authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

// Use centralized configuration (loads DB credentials from env/config.php)
require_once __DIR__ . '/config.php';
if (empty($pdo) || !($pdo instanceof PDO)) {
    die('Database connection unavailable.');
}

// Create email_logs table if not exists
$pdo->exec("CREATE TABLE IF NOT EXISTS email_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message_type VARCHAR(100),
    status ENUM('sent','failed','pending') DEFAULT 'pending',
    error_message TEXT,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_recipient (recipient_email),
    INDEX idx_sent_at (sent_at)
)");

// Get email logs
try {
    $stmt = $pdo->query("SELECT id, recipient, subject, status, sent_at, error_message FROM email_logs ORDER BY sent_at DESC LIMIT 100");
    $emailLogs = $stmt->fetchAll();
} catch (PDOException $e) {
    $emailLogs = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Logs - EduBridgeSA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .card-shadow {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }

        .navbar {
            background-color: #2c3e50;
        }

        .status-sent {
            color: #198754;
        }

        .status-failed {
            color: #dc3545;
        }

        .status-pending {
            color: #ffc107;
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-dark mb-4">
        <div class="container-fluid">
            <span class="navbar-brand">
                <i class="bi bi-envelope me-2"></i>Email Logs
            </span>
            <div>
                <a href="admin_dashboard.php" class="btn btn-outline-light btn-sm me-2">
                    <i class="bi bi-speedometer2 me-1"></i>Dashboard
                </a>
                <a href="admin_logout.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i>Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="card card-shadow">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-list-check me-2"></i>Recent Email Activity
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($emailLogs)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-envelope display-4"></i>
                        <p class="mt-2">No email logs found</p>
                        <small>Email logs will appear here when emails are sent through the system</small>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Recipient</th>
                                    <th>Subject</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Error</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($emailLogs as $log): ?>
                                    <tr>
                                        <td>
                                            <small><?= date('M j, g:i A', strtotime($log['sent_at'])) ?></small>
                                        </td>
                                        <td>
                                            <code><?= htmlspecialchars($log['recipient_email']) ?></code>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($log['subject']) ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?= htmlspecialchars($log['message_type'] ?? 'General') ?></span>
                                        </td>
                                        <td>
                                            <span class="status-<?= $log['status'] ?>">
                                                <i class="bi bi-<?= $log['status'] === 'sent' ? 'check-circle' : ($log['status'] === 'failed' ? 'x-circle' : 'clock') ?> me-1"></i>
                                                <?= ucfirst($log['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-danger"><?= htmlspecialchars($log['error_message'] ?? '') ?></small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Email Statistics -->
        <div class="row mt-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-primary"><?= count($emailLogs) ?></h3>
                        <p class="text-muted">Total Emails</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-success"><?= count(array_filter($emailLogs, fn($log) => $log['status'] === 'sent')) ?></h3>
                        <p class="text-muted">Sent</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-danger"><?= count(array_filter($emailLogs, fn($log) => $log['status'] === 'failed')) ?></h3>
                        <p class="text-muted">Failed</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-warning"><?= count(array_filter($emailLogs, fn($log) => $log['status'] === 'pending')) ?></h3>
                        <p class="text-muted">Pending</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>