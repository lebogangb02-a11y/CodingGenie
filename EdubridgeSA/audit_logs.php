<?php
// audit_logs.php - Audit Logs Viewer
session_start();

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simple authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'u839420047_Edubridge');
define('DB_PASS', 'BAs1m@n3');
define('DB_NAME', 'u839420047_applications');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Create audit logs table if not exists
$pdo->exec("CREATE TABLE IF NOT EXISTS admin_activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_username VARCHAR(100) NOT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at),
    INDEX idx_admin (admin_username)
)");

// Get audit logs
try {
    $stmt = $pdo->query("SELECT * FROM admin_activity_logs ORDER BY created_at DESC LIMIT 100");
    $auditLogs = $stmt->fetchAll();
} catch (PDOException $e) {
    $auditLogs = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs - EduBridgeSA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .card-shadow { box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); }
        .navbar { background-color: #2c3e50; }
    </style>
</head>
<body>
<nav class="navbar navbar-dark mb-4">
    <div class="container-fluid">
        <span class="navbar-brand">
            <i class="bi bi-clock-history me-2"></i>Audit Logs
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
                <i class="bi bi-list-check me-2"></i>Recent Admin Activity
            </h5>
        </div>
        <div class="card-body">
            <?php if (empty($auditLogs)): ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-inbox display-4"></i>
                    <p class="mt-2">No audit logs found</p>
                    <small>Admin activities will appear here as they occur</small>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Admin</th>
                                <th>Action</th>
                                <th>Details</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($auditLogs as $log): ?>
                                <tr>
                                    <td>
                                        <small><?= date('M j, g:i A', strtotime($log['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($log['admin_username']) ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?= htmlspecialchars($log['action']) ?></span>
                                    </td>
                                    <td>
                                        <small><?= htmlspecialchars($log['details'] ?? '') ?></small>
                                    </td>
                                    <td>
                                        <code><?= htmlspecialchars($log['ip_address'] ?? '') ?></code>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>