<?php
// system_status.php - System Status Dashboard
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

// Initialize status arrays
$dbStatus = [];
$fileSystemStatus = [];
$systemInfo = [];
$recentErrors = [];

// Check Database Status
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $dbStatus['connection'] = ['status' => 'success', 'message' => 'Connected successfully'];
    
    // Get table counts
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $dbStatus['tables_count'] = ['status' => 'success', 'message' => count($tables) . ' tables found'];
    
    // Check key tables
    $keyTables = ['applications', 'users', 'documents'];
    foreach ($keyTables as $table) {
        if (in_array($table, $tables)) {
            $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            $dbStatus["table_$table"] = ['status' => 'success', 'message' => "$count records"];
        } else {
            $dbStatus["table_$table"] = ['status' => 'warning', 'message' => 'Table not found'];
        }
    }
    
    // Get database size
    $sizeQuery = $pdo->query("
        SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb 
        FROM information_schema.tables 
        WHERE table_schema = '" . DB_NAME . "'
    ");
    $dbSize = $sizeQuery->fetchColumn();
    $dbStatus['database_size'] = ['status' => 'info', 'message' => $dbSize . ' MB'];
    
} catch (PDOException $e) {
    $dbStatus['connection'] = ['status' => 'danger', 'message' => 'Connection failed: ' . $e->getMessage()];
}

// Check File System Status
$uploadDirs = [
    'uploads/' => 'Main uploads directory',
    'uploads/id_documents/' => 'ID Documents',
    'uploads/matric_certificates/' => 'Matric Certificates', 
    'uploads/proof_of_residence/' => 'Proof of Residence',
    'uploads/additional_documents/' => 'Additional Documents'
];

foreach ($uploadDirs as $dir => $description) {
    if (is_dir($dir)) {
        $fileCount = count(glob($dir . "*"));
        $files = glob($dir . "*");
        $totalSize = 0;
        foreach ($files as $file) {
            if (is_file($file)) {
                $totalSize += filesize($file);
            }
        }
        $sizeFormatted = round($totalSize / 1024 / 1024, 2) . ' MB';
        
        $fileSystemStatus[$dir] = [
            'status' => 'success',
            'message' => "$fileCount files, $sizeFormatted",
            'description' => $description
        ];
    } else {
        $fileSystemStatus[$dir] = [
            'status' => 'warning', 
            'message' => 'Directory not found',
            'description' => $description
        ];
    }
}

// System Information
$systemInfo['php_version'] = PHP_VERSION;
$systemInfo['server_software'] = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
$systemInfo['max_upload_size'] = ini_get('upload_max_filesize');
$systemInfo['max_post_size'] = ini_get('post_max_size');
$systemInfo['memory_limit'] = ini_get('memory_limit');
$systemInfo['server_time'] = date('Y-m-d H:i:s');

// Check for recent errors
$errorLogs = [];
if (file_exists('error.log')) {
    $errorLogs = array_slice(file('error.log'), -10); // Last 10 errors
}
if (file_exists('debug_errors.log')) {
    $debugErrors = array_slice(file('debug_errors.log'), -10);
    $errorLogs = array_merge($errorLogs, $debugErrors);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Status - EduBridgeSA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .status-card { transition: transform 0.2s; }
        .status-card:hover { transform: translateY(-2px); }
        .status-success { border-left: 4px solid #198754; }
        .status-warning { border-left: 4px solid #ffc107; }
        .status-danger { border-left: 4px solid #dc3545; }
        .status-info { border-left: 4px solid #0dcaf0; }
        .navbar { background-color: #2c3e50; }
    </style>
</head>
<body>
<nav class="navbar navbar-dark mb-4">
    <div class="container-fluid">
        <span class="navbar-brand">
            <i class="bi bi-graph-up me-2"></i>System Status - EduBridgeSA
        </span>
        <div>
            <a href="admin_dashboard.php" class="btn btn-outline-light btn-sm me-2">
                <i class="bi bi-speedometer2 me-1"></i>Dashboard
            </a>
            <a href="manage_students.php" class="btn btn-outline-light btn-sm me-2">
                <i class="bi bi-people me-1"></i>Students
            </a>
            <a href="admin_logout.php" class="btn btn-outline-light btn-sm">
                <i class="bi bi-box-arrow-right me-1"></i>Logout
            </a>
        </div>
    </div>
</nav>

<div class="container-fluid">
    <!-- System Overview -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-3">
                        <i class="bi bi-heart-pulse me-2"></i>System Overview
                    </h4>
                    <div class="row">
                        <div class="col-md-3 text-center">
                            <div class="display-6 text-primary"><?= $dbStatus['tables_count']['message'] ?? '0' ?></div>
                            <small class="text-muted">Database Tables</small>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="display-6 text-success"><?= count($fileSystemStatus) ?></div>
                            <small class="text-muted">Storage Directories</small>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="display-6 text-info">PHP <?= $systemInfo['php_version'] ?></div>
                            <small class="text-muted">PHP Version</small>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="display-6 text-warning"><?= $systemInfo['max_upload_size'] ?></div>
                            <small class="text-muted">Max Upload Size</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Database Status -->
        <div class="col-lg-6">
            <div class="card status-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-database me-2"></i>Database Status
                    </h5>
                </div>
                <div class="card-body">
                    <?php foreach ($dbStatus as $key => $status): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2 p-2 status-<?= $status['status'] ?>">
                            <div>
                                <strong><?= ucfirst(str_replace('_', ' ', $key)) ?></strong>
                                <div class="text-muted small"><?= $status['message'] ?></div>
                            </div>
                            <span class="badge bg-<?= $status['status'] ?>">
                                <?= strtoupper($status['status']) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- File System Status -->
        <div class="col-lg-6">
            <div class="card status-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-folder me-2"></i>File System Status
                    </h5>
                </div>
                <div class="card-body">
                    <?php foreach ($fileSystemStatus as $dir => $status): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2 p-2 status-<?= $status['status'] ?>">
                            <div>
                                <strong><?= $status['description'] ?></strong>
                                <div class="text-muted small"><?= $dir ?></div>
                                <div class="text-muted small"><?= $status['message'] ?></div>
                            </div>
                            <span class="badge bg-<?= $status['status'] ?>">
                                <?= strtoupper($status['status']) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- System Information -->
        <div class="col-lg-6">
            <div class="card status-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-info-circle me-2"></i>System Information
                    </h5>
                </div>
                <div class="card-body">
                    <?php foreach ($systemInfo as $key => $value): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2 p-2">
                            <strong><?= ucfirst(str_replace('_', ' ', $key)) ?></strong>
                            <span class="text-muted"><?= htmlspecialchars($value) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Recent Errors -->
        <div class="col-lg-6">
            <div class="card status-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-exclamation-triangle me-2"></i>Recent Errors
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($errorLogs)): ?>
                        <div class="text-center text-muted py-3">
                            <i class="bi bi-check-circle display-4 text-success"></i>
                            <p class="mt-2">No recent errors found</p>
                        </div>
                    <?php else: ?>
                        <div style="max-height: 300px; overflow-y: auto;">
                            <?php foreach (array_reverse($errorLogs) as $error): ?>
                                <div class="border-bottom pb-2 mb-2">
                                    <small class="text-danger"><?= htmlspecialchars($error) ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-tools me-2"></i>Quick Maintenance
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-3">
                            <a href="check_db_status.php" class="btn btn-outline-primary w-100">
                                <i class="bi bi-database-check me-1"></i>Check DB
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="clean_uploaded_files.php" class="btn btn-outline-warning w-100">
                                <i class="bi bi-trash me-1"></i>Clean Files
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="full_system_check.php" class="btn btn-outline-info w-100">
                                <i class="bi bi-search me-1"></i>Full Check
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="database_troubleshoot.php" class="btn btn-outline-danger w-100">
                                <i class="bi bi-wrench me-1"></i>Troubleshoot
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Auto-refresh every 60 seconds
    setTimeout(() => {
        window.location.reload();
    }, 60000);
</script>
</body>
</html>